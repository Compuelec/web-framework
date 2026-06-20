<?php

/**
 * POS Manager — controller.
 *
 * Generic, configuration-driven point-of-sale logic:
 *   - searchProducts(): list products for the cart.
 *   - createSale():      record a sale + its lines and decrement product stock
 *                        atomically (single transaction, conditional UPDATE so
 *                        stock can never go negative under concurrency).
 *   - getReceipt():      re-read a sale for printing.
 *   - settings:          the mapping (which table/columns are products, where
 *                        sales/lines are stored, payment methods) is stored
 *                        visually in the `pos_settings` table and edited from the
 *                        CMS — no file editing. config.php is an optional fallback.
 *
 * All table/column names come from the (validated) config; values are bound.
 */

require_once __DIR__ . '/../../../cms/controllers/install.controller.php';

class PosManagerController {

    /** @var PDO */
    private $link;
    private $config = null;
    private $configError = null;

    /** Tables hidden from the configuration pickers. */
    private $excludedTables = [
        'admins', 'roles', 'pages', 'modules', 'columns', 'folders', 'files',
        'cms_settings', 'activity_logs', 'framework_migrations',
        'dashboard_widgets', 'page_seo', 'payku_orders', 'workflows', 'pos_settings',
    ];

    public function __construct() {
        $this->link = InstallController::connect();
        $this->ensureSettingsTable();
        $this->loadConfig();
    }

    /* ============================================================
       Settings storage (pos_settings) + config resolution
       ============================================================ */

    private function ensureSettingsTable() {
        try {
            $this->link->exec("
                CREATE TABLE IF NOT EXISTS `pos_settings` (
                    `id_setting`           INT NOT NULL AUTO_INCREMENT,
                    `config_setting`       LONGTEXT NULL,
                    `date_updated_setting` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_setting`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Exception $e) {
            error_log('POS ensureSettingsTable error: ' . $e->getMessage());
        }
    }

    /** Resolve config: DB settings first, then config.php fallback. */
    private function loadConfig() {
        $cfg = $this->readDbSettings();
        if ($cfg === null) {
            $path = __DIR__ . '/../config.php';
            if (file_exists($path)) {
                $fileCfg = require $path;
                if (is_array($fileCfg)) { $cfg = $fileCfg; }
            }
        }
        if (!is_array($cfg)) {
            $this->configError = 'El POS aún no está configurado. Abre la configuración (⚙) y mapea tus tablas.';
            return;
        }

        $err = $this->validateConfig($cfg);
        if ($err !== null) { $this->configError = $err; return; }

        $cfg['roles_allowed']    = $cfg['roles_allowed']    ?? ['superadmin', 'admin', 'cashier'];
        $cfg['payment_methods']  = (!empty($cfg['payment_methods']) && is_array($cfg['payment_methods']))
            ? array_values(array_filter(array_map('strval', $cfg['payment_methods'])))
            : ['efectivo', 'tarjeta'];
        $cfg['completed_status'] = $cfg['completed_status'] ?? 'completed';
        $this->config = $cfg;
    }

    private function readDbSettings() {
        try {
            $row = $this->link->query("SELECT config_setting FROM pos_settings ORDER BY id_setting DESC LIMIT 1")->fetch(PDO::FETCH_OBJ);
            if ($row && $row->config_setting) {
                $decoded = json_decode($row->config_setting, true);
                if (is_array($decoded)) { return $decoded; }
            }
        } catch (Exception $e) {
            error_log('POS readDbSettings error: ' . $e->getMessage());
        }
        return null;
    }

    /** Returns an error string, or null if the config is structurally valid. */
    private function validateConfig($cfg) {
        $required = [
            'product'   => ['table', 'id', 'name', 'price', 'stock'],
            'sale'      => ['table', 'id', 'cashier', 'total', 'payment', 'status'],
            'sale_item' => ['table', 'id', 'sale', 'product', 'qty', 'unit_price', 'subtotal'],
        ];
        foreach ($required as $group => $keys) {
            if (!isset($cfg[$group]) || !is_array($cfg[$group])) {
                return "Configuración incompleta: falta el grupo '$group'.";
            }
            foreach ($keys as $k) {
                if (empty($cfg[$group][$k]) || !$this->isIdentifier($cfg[$group][$k])) {
                    return "Identificador inválido o ausente en $group.$k.";
                }
            }
        }
        foreach (['image', 'active', 'category'] as $opt) {
            if (!empty($cfg['product'][$opt]) && !$this->isIdentifier($cfg['product'][$opt])) {
                return "Identificador inválido en product.$opt.";
            }
        }
        if (!empty($cfg['sale']['date']) && !$this->isIdentifier($cfg['sale']['date'])) {
            return 'Identificador inválido en sale.date.';
        }
        return null;
    }

    /** Whitelist: only bare SQL identifiers may be interpolated into SQL. */
    private function isIdentifier($s) {
        return is_string($s) && preg_match('/^[a-zA-Z0-9_]+$/', $s) === 1;
    }

    public function isConfigured()   { return $this->configError === null; }
    public function configError()    { return $this->configError; }
    public function rolesAllowed()   { return ($this->config['roles_allowed']   ?? ['superadmin', 'admin', 'cashier']); }
    public function paymentMethods() { return ($this->config['payment_methods'] ?? ['efectivo', 'tarjeta']); }

    /* ============================================================
       Settings API (for the visual configuration screen)
       ============================================================ */

    /** Current config (or empty skeleton) to prefill the settings form. */
    public function getSettings() {
        $cfg = $this->readDbSettings();
        if ($cfg === null) {
            $path = __DIR__ . '/../config.php';
            if (file_exists($path)) {
                $fileCfg = require $path;
                if (is_array($fileCfg)) { $cfg = $fileCfg; }
            }
        }
        return [
            'success'  => true,
            'config'   => is_array($cfg) ? $cfg : null,
            'tables'   => $this->getTables(),
        ];
    }

    /** Validate and persist the config from the settings screen. */
    public function saveSettings($cfg) {
        if (!is_array($cfg)) { return ['success' => false, 'error' => 'Configuración inválida.']; }
        $err = $this->validateConfig($cfg);
        if ($err !== null) { return ['success' => false, 'error' => $err]; }

        // Normalize payment methods.
        $pm = [];
        foreach ((array)($cfg['payment_methods'] ?? []) as $m) {
            $m = trim((string)$m);
            if ($m !== '' && !in_array($m, $pm, true)) { $pm[] = $m; }
        }
        if (!$pm) { $pm = ['efectivo']; }
        $cfg['payment_methods']  = $pm;
        $cfg['roles_allowed']    = $cfg['roles_allowed']    ?? ['superadmin', 'admin', 'cashier'];
        $cfg['completed_status'] = $cfg['completed_status'] ?? 'completed';

        try {
            $json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
            $exists = (int)$this->link->query("SELECT COUNT(*) FROM pos_settings")->fetchColumn();
            if ($exists > 0) {
                $id = (int)$this->link->query("SELECT id_setting FROM pos_settings ORDER BY id_setting DESC LIMIT 1")->fetchColumn();
                $this->link->prepare("UPDATE pos_settings SET config_setting = ? WHERE id_setting = ?")->execute([$json, $id]);
            } else {
                $this->link->prepare("INSERT INTO pos_settings (config_setting) VALUES (?)")->execute([$json]);
            }
            return ['success' => true];
        } catch (Exception $e) {
            error_log('POS saveSettings error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo guardar la configuración.'];
        }
    }

    /** User/data tables available to map (system tables hidden). */
    public function getTables() {
        try {
            $tables = $this->link->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            return array_values(array_filter($tables, function ($t) {
                return !in_array(strtolower($t), $this->excludedTables, true);
            }));
        } catch (Exception $e) {
            return [];
        }
    }

    /** Columns of a table (for the mapping dropdowns). */
    public function getColumns($table) {
        if (!$this->isIdentifier($table)) { return []; }
        try {
            return $this->link->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }

    /* ============================================================
       Product search
       ============================================================ */

    public function searchProducts($q = '', $limit = 60) {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => $this->configError];
        }
        $p = $this->config['product'];

        $cols = [
            "`{$p['id']}` AS id",
            "`{$p['name']}` AS name",
            "`{$p['price']}` AS price",
            "`{$p['stock']}` AS stock",
        ];
        if (!empty($p['image']))  { $cols[] = "`{$p['image']}` AS image"; }
        if (!empty($p['active'])) { $cols[] = "`{$p['active']}` AS active"; }

        $sql    = "SELECT " . implode(', ', $cols) . " FROM `{$p['table']}`";
        $where  = [];
        $params = [];
        if (!empty($p['active'])) { $where[] = "`{$p['active']}` = 1"; }
        if ($q !== '') {
            $where[]      = "`{$p['name']}` LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }
        if ($where) { $sql .= " WHERE " . implode(' AND ', $where); }
        $sql .= " ORDER BY (`{$p['stock']}` > 0) DESC, `{$p['name']}` ASC LIMIT " . (int) $limit;

        try {
            $stmt = $this->link->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
            foreach ($rows as $r) {
                $r->id    = (int) $r->id;
                $r->price = (float) $r->price;
                $r->stock = (int) $r->stock;
                if (isset($r->image)) { $r->image = urldecode((string) $r->image); }
            }
            return ['success' => true, 'products' => $rows];
        } catch (Exception $e) {
            error_log('POS searchProducts error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudieron cargar los productos.'];
        }
    }

    /* ============================================================
       Create sale (atomic, race-safe stock decrement)
       ============================================================ */

    public function createSale($items, $payment, $cashierId) {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => $this->configError];
        }
        if (!is_array($items) || count($items) === 0) {
            return ['success' => false, 'error' => 'El carrito está vacío.'];
        }
        if (!in_array($payment, $this->paymentMethods(), true)) {
            return ['success' => false, 'error' => 'Método de pago inválido.'];
        }

        $cart = [];
        foreach ($items as $it) {
            $pid = (int) ($it['product_id'] ?? 0);
            $qty = (int) ($it['qty'] ?? 0);
            if ($pid <= 0 || $qty < 1) {
                return ['success' => false, 'error' => 'Hay una línea inválida en el carrito.'];
            }
            $cart[$pid] = ($cart[$pid] ?? 0) + $qty;
        }

        $p = $this->config['product'];
        $s = $this->config['sale'];
        $d = $this->config['sale_item'];
        $activeCond = !empty($p['active']) ? " AND `{$p['active']}` = 1" : '';

        try {
            $this->link->beginTransaction();

            $hCols  = ["`{$s['cashier']}`", "`{$s['total']}`", "`{$s['payment']}`", "`{$s['status']}`"];
            $hPlace = ['?', '?', '?', '?'];
            $hVals  = [(int) $cashierId, 0, $payment, $this->config['completed_status']];
            if (!empty($s['date'])) { $hCols[] = "`{$s['date']}`"; $hPlace[] = 'NOW()'; }
            $hSql = "INSERT INTO `{$s['table']}` (" . implode(', ', $hCols) . ") VALUES (" . implode(', ', $hPlace) . ")";
            $this->link->prepare($hSql)->execute($hVals);
            $saleId = (int) $this->link->lastInsertId();

            $decSql = "UPDATE `{$p['table']}` SET `{$p['stock']}` = `{$p['stock']}` - ? "
                    . "WHERE `{$p['id']}` = ? AND `{$p['stock']}` >= ?" . $activeCond;
            $priceSql = "SELECT `{$p['name']}` AS name, `{$p['price']}` AS price "
                      . "FROM `{$p['table']}` WHERE `{$p['id']}` = ?";
            $lineSql = "INSERT INTO `{$d['table']}` "
                     . "(`{$d['sale']}`, `{$d['product']}`, `{$d['qty']}`, `{$d['unit_price']}`, `{$d['subtotal']}`) "
                     . "VALUES (?, ?, ?, ?, ?)";

            $total = 0.0;
            foreach ($cart as $pid => $qty) {
                $dec = $this->link->prepare($decSql);
                $dec->execute([$qty, $pid, $qty]);
                if ($dec->rowCount() !== 1) {
                    $this->link->rollBack();
                    return [
                        'success' => false,
                        'error'   => 'insufficient_stock',
                        'product' => $this->productBrief($pid),
                    ];
                }

                $ps = $this->link->prepare($priceSql);
                $ps->execute([$pid]);
                $prod = $ps->fetch(PDO::FETCH_OBJ);
                $unit = (float) ($prod->price ?? 0);
                $sub  = $unit * $qty;
                $total += $sub;

                $this->link->prepare($lineSql)->execute([$saleId, $pid, $qty, $unit, $sub]);
            }

            $this->link->prepare("UPDATE `{$s['table']}` SET `{$s['total']}` = ? WHERE `{$s['id']}` = ?")
                       ->execute([$total, $saleId]);

            $this->link->commit();
            return ['success' => true, 'sale' => $this->getReceiptData($saleId)];

        } catch (Exception $e) {
            if ($this->link->inTransaction()) { $this->link->rollBack(); }
            error_log('POS createSale error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo registrar la venta.'];
        }
    }

    private function productBrief($pid) {
        $p = $this->config['product'];
        try {
            $stmt = $this->link->prepare("SELECT `{$p['name']}` AS name, `{$p['stock']}` AS stock FROM `{$p['table']}` WHERE `{$p['id']}` = ?");
            $stmt->execute([(int) $pid]);
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return ['id' => (int) $pid, 'name' => $row->name ?? '', 'available' => (int) ($row->stock ?? 0)];
        } catch (Exception $e) {
            return ['id' => (int) $pid, 'name' => '', 'available' => 0];
        }
    }

    /* ============================================================
       Receipt
       ============================================================ */

    public function getReceipt($saleId) {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => $this->configError];
        }
        $data = $this->getReceiptData((int) $saleId);
        return $data ? ['success' => true, 'sale' => $data] : ['success' => false, 'error' => 'Venta no encontrada.'];
    }

    private function getReceiptData($saleId) {
        $s = $this->config['sale'];
        $d = $this->config['sale_item'];
        $p = $this->config['product'];

        $hs = $this->link->prepare("SELECT * FROM `{$s['table']}` WHERE `{$s['id']}` = ?");
        $hs->execute([(int) $saleId]);
        $h = $hs->fetch(PDO::FETCH_ASSOC);
        if (!$h) { return null; }

        $ls = $this->link->prepare(
            "SELECT di.`{$d['qty']}` AS qty, di.`{$d['unit_price']}` AS unit_price, di.`{$d['subtotal']}` AS subtotal, "
            . "pr.`{$p['name']}` AS name "
            . "FROM `{$d['table']}` di "
            . "LEFT JOIN `{$p['table']}` pr ON di.`{$d['product']}` = pr.`{$p['id']}` "
            . "WHERE di.`{$d['sale']}` = ?"
        );
        $ls->execute([(int) $saleId]);
        $lines = $ls->fetchAll(PDO::FETCH_OBJ);

        return [
            'id'      => (int) $h[$s['id']],
            'total'   => (float) $h[$s['total']],
            'payment' => $h[$s['payment']] ?? '',
            'status'  => $h[$s['status']] ?? '',
            'date'    => (!empty($s['date']) && isset($h[$s['date']])) ? $h[$s['date']] : '',
            'items'   => array_map(function ($l) {
                return [
                    'name'       => $l->name,
                    'qty'        => (int) $l->qty,
                    'unit_price' => (float) $l->unit_price,
                    'subtotal'   => (float) $l->subtotal,
                ];
            }, $lines),
        ];
    }
}
