<?php

/**
 * Production Manager — controller.
 *
 * Generic, configurable manufacturing: produce N units of a product, consuming
 * its recipe's supplies (insumos) and increasing the product's stock — all in a
 * single atomic, race-safe transaction. The plugin is data-agnostic: it only
 * needs to know which tables hold products, supplies, recipes and (optionally) a
 * production log, mapped in config.php.
 *
 * SECURITY: every table/column from config is validated as a bare SQL identifier
 * (^[a-zA-Z0-9_]+$) before being interpolated; all values are bound parameters.
 */

require_once __DIR__ . '/../../../cms/controllers/install.controller.php';

class ProductionManagerController {

    private $link;
    private $config = null;
    private $configError = '';

    public function __construct() {
        $this->link = InstallController::connect();
        $this->loadConfig();
    }

    /* ============================================================ Config */

    private function loadConfig() {
        $path = __DIR__ . '/../config.php';
        if (!file_exists($path)) {
            $this->configError = 'El plugin no está configurado (falta config.php).';
            return;
        }
        $cfg = require $path;
        if (!is_array($cfg)) {
            $this->configError = 'config.php no devuelve un arreglo válido.';
            return;
        }
        $err = $this->validateConfig($cfg);
        if ($err !== null) { $this->configError = $err; return; }
        $cfg['completed_status'] = $cfg['completed_status'] ?? 'completed';
        $cfg['roles_allowed']    = (!empty($cfg['roles_allowed']) && is_array($cfg['roles_allowed']))
            ? $cfg['roles_allowed'] : ['superadmin', 'admin'];
        $this->config = $cfg;
    }

    private function isIdentifier($s) {
        return is_string($s) && preg_match('/^[a-zA-Z0-9_]+$/', $s) === 1;
    }

    /** Validate that every required table/column is a safe identifier. */
    private function validateConfig($cfg) {
        $required = [
            'product'    => ['table', 'id', 'name', 'stock'],
            'supply'     => ['table', 'id', 'name', 'stock'],
            'recipe'     => ['table', 'product', 'supply', 'qty'],
            'production' => ['table', 'id', 'product', 'qty'],
        ];
        foreach ($required as $group => $keys) {
            if (empty($cfg[$group]) || !is_array($cfg[$group])) {
                return "Falta la sección '$group' en config.php.";
            }
            foreach ($keys as $k) {
                if (empty($cfg[$group][$k]) || !$this->isIdentifier($cfg[$group][$k])) {
                    return "Identificador inválido o faltante en $group.$k.";
                }
            }
        }
        // Optional identifiers.
        foreach ([['product', 'yield'], ['supply', 'unit'], ['production', 'user'], ['production', 'status'], ['production', 'date']] as $opt) {
            if (!empty($cfg[$opt[0]][$opt[1]]) && !$this->isIdentifier($cfg[$opt[0]][$opt[1]])) {
                return "Identificador inválido en {$opt[0]}.{$opt[1]}.";
            }
        }
        return null;
    }

    public function isConfigured() { return $this->config !== null; }
    public function configError()  { return $this->configError; }
    public function rolesAllowed() { return $this->config['roles_allowed'] ?? ['superadmin', 'admin']; }

    /* ============================================================ Reads */

    /** Products that can be manufactured (match by name). */
    public function searchProducts($q, $limit = 60) {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $p = $this->config['product'];
        $where = '';
        $params = [];
        if ($q !== '') { $where = "WHERE `{$p['name']}` LIKE ?"; $params[] = '%' . $q . '%'; }
        try {
            $sql = "SELECT `{$p['id']}` AS id, `{$p['name']}` AS name, `{$p['stock']}` AS stock "
                 . "FROM `{$p['table']}` {$where} ORDER BY `{$p['name']}` ASC LIMIT " . (int)$limit;
            $st = $this->link->prepare($sql);
            $st->execute($params);
            return ['success' => true, 'products' => $st->fetchAll(PDO::FETCH_OBJ)];
        } catch (Exception $e) {
            error_log('Production searchProducts: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudieron cargar los productos.'];
        }
    }

    /** The recipe (supplies + per-unit qty + current availability) for a product. */
    public function getRecipe($productId) {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $r = $this->config['recipe'];
        $s = $this->config['supply'];
        $unitSel = !empty($s['unit']) ? ", su.`{$s['unit']}` AS unit" : '';
        try {
            $sql = "SELECT su.`{$s['id']}` AS supply_id, su.`{$s['name']}` AS name, "
                 . "re.`{$r['qty']}` AS per_unit, su.`{$s['stock']}` AS available{$unitSel} "
                 . "FROM `{$r['table']}` re "
                 . "JOIN `{$s['table']}` su ON re.`{$r['supply']}` = su.`{$s['id']}` "
                 . "WHERE re.`{$r['product']}` = ?";
            $st = $this->link->prepare($sql);
            $st->execute([(int)$productId]);
            $lines = $st->fetchAll(PDO::FETCH_OBJ);
            return ['success' => true, 'recipe' => $lines, 'yield' => $this->productYield($productId)];
        } catch (Exception $e) {
            error_log('Production getRecipe: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo cargar la receta.'];
        }
    }

    /** Batch yield: how many product units one recipe "batch" makes (default 1). */
    public function productYield($productId) {
        $p = $this->config['product'];
        if (empty($p['yield'])) { return 1.0; }
        try {
            $st = $this->link->prepare("SELECT `{$p['yield']}` AS y FROM `{$p['table']}` WHERE `{$p['id']}` = ?");
            $st->execute([(int)$productId]);
            $y = (float)$st->fetchColumn();
            return $y > 0 ? $y : 1.0;
        } catch (Exception $e) { return 1.0; }
    }

    /** Supplies for the recipe editor's ingredient picker. */
    public function searchSupplies($q, $limit = 60) {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $s = $this->config['supply'];
        $unitSel = !empty($s['unit']) ? ", `{$s['unit']}` AS unit" : '';
        $where = ''; $params = [];
        if ($q !== '') { $where = "WHERE `{$s['name']}` LIKE ?"; $params[] = '%' . $q . '%'; }
        try {
            $st = $this->link->prepare("SELECT `{$s['id']}` AS id, `{$s['name']}` AS name{$unitSel} FROM `{$s['table']}` {$where} ORDER BY `{$s['name']}` ASC LIMIT " . (int)$limit);
            $st->execute($params);
            return ['success' => true, 'supplies' => $st->fetchAll(PDO::FETCH_OBJ)];
        } catch (Exception $e) {
            error_log('Production searchSupplies: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudieron cargar los insumos.'];
        }
    }

    /**
     * Replace a product's recipe with the given lines and (optionally) its batch
     * yield. $lines = [ ['supply'=>id, 'qty'=>number], ... ].
     */
    public function saveRecipe($productId, $lines, $yield = 1) {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $productId = (int) $productId;
        if ($productId <= 0) { return ['success' => false, 'error' => 'Producto inválido.']; }
        if (!is_array($lines)) { $lines = []; }

        // Validate + dedupe lines (merge same supply).
        $clean = [];
        foreach ($lines as $l) {
            $sid = (int)($l['supply'] ?? 0);
            $qty = (float)($l['qty'] ?? 0);
            if ($sid <= 0 || $qty <= 0) { continue; }
            $clean[$sid] = ($clean[$sid] ?? 0) + $qty;
        }
        $yield = (float)$yield; if ($yield <= 0) { $yield = 1.0; }

        $r = $this->config['recipe'];
        $p = $this->config['product'];
        try {
            $this->link->beginTransaction();
            $this->link->prepare("DELETE FROM `{$r['table']}` WHERE `{$r['product']}` = ?")->execute([$productId]);
            if ($clean) {
                $ins = $this->link->prepare("INSERT INTO `{$r['table']}` (`{$r['product']}`, `{$r['supply']}`, `{$r['qty']}`) VALUES (?, ?, ?)");
                foreach ($clean as $sid => $qty) { $ins->execute([$productId, $sid, $qty]); }
            }
            if (!empty($p['yield'])) {
                $this->link->prepare("UPDATE `{$p['table']}` SET `{$p['yield']}` = ? WHERE `{$p['id']}` = ?")->execute([$yield, $productId]);
            }
            $this->link->commit();
            return ['success' => true, 'lines' => count($clean)];
        } catch (Exception $e) {
            if ($this->link->inTransaction()) { $this->link->rollBack(); }
            error_log('Production saveRecipe: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo guardar la receta.'];
        }
    }

    /** Recent production runs (joined to product + user names). */
    public function getProductions($limit = 25) {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $pr = $this->config['production'];
        $p  = $this->config['product'];
        $dateSel = !empty($pr['date']) ? "pr.`{$pr['date']}`" : "NULL";
        $userJoin = ''; $userSel = "'' AS user_name";
        if (!empty($pr['user'])) {
            $userJoin = "LEFT JOIN admins a ON pr.`{$pr['user']}` = a.id_admin";
            $userSel  = "COALESCE(NULLIF(a.title_admin,''), a.email_admin, '') AS user_name";
        }
        try {
            $sql = "SELECT pr.`{$pr['id']}` AS id, pr.`{$pr['qty']}` AS qty, "
                 . "pp.`{$p['name']}` AS product, {$dateSel} AS date, {$userSel} "
                 . "FROM `{$pr['table']}` pr "
                 . "LEFT JOIN `{$p['table']}` pp ON pr.`{$pr['product']}` = pp.`{$p['id']}` "
                 . "{$userJoin} ORDER BY pr.`{$pr['id']}` DESC LIMIT " . (int)$limit;
            $st = $this->link->query($sql);
            return ['success' => true, 'productions' => $st ? $st->fetchAll(PDO::FETCH_OBJ) : []];
        } catch (Exception $e) {
            error_log('Production getProductions: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo cargar el historial.'];
        }
    }

    /* ============================================================ Produce */

    /**
     * Manufacture $qty units of $productId: consume each recipe supply
     * (required = qty * per_unit) atomically and add $qty to the product stock.
     */
    public function produce($productId, $qty, $userId = 0) {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $productId = (int) $productId;
        $qty = (int) $qty;
        if ($productId <= 0 || $qty < 1) {
            return ['success' => false, 'error' => 'Producto o cantidad inválidos.'];
        }

        $p = $this->config['product'];
        $s = $this->config['supply'];
        $r = $this->config['recipe'];
        $pr = $this->config['production'];

        try {
            // Recipe lines (read before the transaction; the conditional UPDATE is
            // what actually guards stock under concurrency).
            $rs = $this->link->prepare(
                "SELECT re.`{$r['supply']}` AS supply, re.`{$r['qty']}` AS per_unit "
                . "FROM `{$r['table']}` re WHERE re.`{$r['product']}` = ?"
            );
            $rs->execute([$productId]);
            $lines = $rs->fetchAll(PDO::FETCH_OBJ);
            if (!$lines) {
                return ['success' => false, 'error' => 'no_recipe'];
            }
            // Lock supplies in a consistent order (by id) to avoid deadlocks when
            // two production runs share supplies.
            usort($lines, function ($a, $b) { return (int)$a->supply <=> (int)$b->supply; });
            // Recipe quantities are expressed per "batch" of `yield` units.
            $yield = $this->productYield($productId);

            $this->link->beginTransaction();

            $decSql  = "UPDATE `{$s['table']}` SET `{$s['stock']}` = `{$s['stock']}` - ? "
                     . "WHERE `{$s['id']}` = ? AND `{$s['stock']}` >= ?";
            $decStmt = $this->link->prepare($decSql);

            foreach ($lines as $line) {
                $required = (float)$line->per_unit * $qty / $yield;
                if ($required <= 0) { continue; }
                $decStmt->execute([$required, (int)$line->supply, $required]);
                if ($decStmt->rowCount() !== 1) {
                    $this->link->rollBack();
                    return [
                        'success' => false,
                        'error'   => 'insufficient_supply',
                        'supply'  => $this->supplyBrief((int)$line->supply, $required),
                    ];
                }
            }

            // Add the produced units to the product stock — must hit exactly one row,
            // else the product is gone and the whole run rolls back (no supplies lost).
            $prodStmt = $this->link->prepare("UPDATE `{$p['table']}` SET `{$p['stock']}` = `{$p['stock']}` + ? WHERE `{$p['id']}` = ?");
            $prodStmt->execute([$qty, $productId]);
            if ($prodStmt->rowCount() !== 1) {
                $this->link->rollBack();
                return ['success' => false, 'error' => 'product_not_found'];
            }

            // Production log row.
            $cols  = ["`{$pr['product']}`", "`{$pr['qty']}`"];
            $ph    = ['?', '?'];
            $vals  = [$productId, $qty];
            if (!empty($pr['user']))   { $cols[] = "`{$pr['user']}`";   $ph[] = '?';     $vals[] = (int)$userId; }
            if (!empty($pr['status'])) { $cols[] = "`{$pr['status']}`"; $ph[] = '?';     $vals[] = $this->config['completed_status']; }
            if (!empty($pr['date']))   { $cols[] = "`{$pr['date']}`";   $ph[] = 'NOW()'; }
            $this->link->prepare("INSERT INTO `{$pr['table']}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $ph) . ")")
                       ->execute($vals);
            $prodId = (int)$this->link->lastInsertId();

            $this->link->commit();
            return ['success' => true, 'production' => ['id' => $prodId, 'product_id' => $productId, 'qty' => $qty]];

        } catch (Exception $e) {
            if ($this->link->inTransaction()) { $this->link->rollBack(); }
            error_log('Production produce: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo registrar la fabricación.'];
        }
    }

    private function supplyBrief($supplyId, $required) {
        $s = $this->config['supply'];
        try {
            $unitSel = !empty($s['unit']) ? ", `{$s['unit']}` AS unit" : '';
            $st = $this->link->prepare("SELECT `{$s['name']}` AS name, `{$s['stock']}` AS available{$unitSel} FROM `{$s['table']}` WHERE `{$s['id']}` = ?");
            $st->execute([(int)$supplyId]);
            $row = $st->fetch(PDO::FETCH_OBJ);
            return [
                'name'      => $row ? ($row->name ?? '') : '',
                'available' => $row ? (float)($row->available ?? 0) : 0.0,
                'required'  => (float)$required,
                'unit'      => $row ? ($row->unit ?? '') : '',
            ];
        } catch (Exception $e) {
            return ['name' => '', 'available' => 0, 'required' => (float)$required, 'unit' => ''];
        }
    }
}
