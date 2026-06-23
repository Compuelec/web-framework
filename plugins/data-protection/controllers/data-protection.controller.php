<?php

/**
 * Data Protection controller (Ley 21.719).
 *
 * Generic privacy tools to exercise data-subject rights across a project's
 * tables: find a person, export their data (access + portability), erase or
 * anonymize it (cancellation), and track ARCOP requests with legal deadlines.
 *
 * Which tables hold personal data is configured VISUALLY from the CMS (stored in
 * `dp_datasets`); a config.php is supported only as an optional fallback. All
 * table/column names are validated as bare SQL identifiers and checked against
 * the real schema; every value is a bound parameter.
 */

require_once dirname(__DIR__, 3) . '/cms/controllers/install.controller.php';

class DataProtectionController
{
    private $link;
    private $dbName = '';
    private $datasets = [];
    private $rolesAllowed = ['superadmin', 'admin'];
    private $responseDays = 30;
    private $configError = '';

    const STRATEGIES = ['null', 'redact', 'hash'];

    public function __construct()
    {
        $this->link = InstallController::connect();
        if ($this->link instanceof PDO) {
            try { $this->dbName = (string) $this->link->query('SELECT DATABASE()')->fetchColumn(); } catch (Exception $e) {}
            $this->ensureDatasetsTable();
            $this->ensureRequestsTable();
            $this->ensureConsentTables();
        }
        $this->loadConfig();
    }

    /* ----------------------------- config ----------------------------- */

    private function loadConfig()
    {
        // Optional globals from config.php (roles, response days).
        $path = __DIR__ . '/../config.php';
        $fileCfg = file_exists($path) ? require $path : null;
        if (is_array($fileCfg)) {
            $this->rolesAllowed = $fileCfg['roles_allowed'] ?? $this->rolesAllowed;
            $this->responseDays = (int) ($fileCfg['response_days'] ?? 30);
        }

        if (!$this->link instanceof PDO) {
            $this->configError = 'Sin conexión a la base de datos.';
            return;
        }

        // Datasets configured visually (DB) take precedence; config.php is a fallback.
        $db = $this->loadDatasetsFromDb();
        if ($db) {
            $this->datasets = $db;
        } elseif (is_array($fileCfg) && !empty($fileCfg['datasets'])) {
            $res = $this->validateDatasets($fileCfg['datasets']);
            if ($res['ok']) { $this->datasets = $res['datasets']; }
        }
    }

    private function isIdent($s)
    {
        return is_string($s) && preg_match('/^[a-zA-Z0-9_]+$/', $s) === 1;
    }

    /** Validate a datasets array. Returns ['ok'=>bool, 'datasets'=>[], 'error'=>'']. */
    private function validateDatasets($arr)
    {
        $out = [];
        foreach ((array) $arr as $i => $d) {
            if (empty($d['table']) || !$this->isIdent($d['table'])) { return ['ok' => false, 'datasets' => [], 'error' => "datasets[$i]: tabla inválida"]; }
            if (empty($d['id']) || !$this->isIdent($d['id'])) { return ['ok' => false, 'datasets' => [], 'error' => "datasets[$i]: id inválido"]; }
            $keys = $d['subject_keys'] ?? [];
            if (!is_array($keys) || !$keys) { return ['ok' => false, 'datasets' => [], 'error' => "datasets[$i]: subject_keys vacío"]; }
            foreach (array_merge($keys, $d['fields'] ?? [], array_keys($d['anonymize'] ?? [])) as $col) {
                if (!$this->isIdent($col)) { return ['ok' => false, 'datasets' => [], 'error' => "datasets[$i]: columna inválida '$col'"]; }
            }
            $out[] = $d;
        }
        return ['ok' => true, 'datasets' => $out, 'error' => ''];
    }

    private function ensureDatasetsTable()
    {
        try {
            $this->link->exec(
                "CREATE TABLE IF NOT EXISTS dp_datasets (
                    id_dataset INT NOT NULL AUTO_INCREMENT,
                    table_dataset VARCHAR(64) NOT NULL,
                    label_dataset VARCHAR(160) NULL,
                    pk_dataset VARCHAR(64) NULL,
                    subject_keys_dataset TEXT NULL,
                    fields_dataset TEXT NULL,
                    sensitive_dataset TEXT NULL,
                    anonymize_dataset TEXT NULL,
                    purpose_dataset TEXT NULL,
                    legal_basis_dataset VARCHAR(160) NULL,
                    recipients_dataset TEXT NULL,
                    retention_days_dataset INT NULL,
                    date_created_dataset DATETIME NULL,
                    date_updated_dataset TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_dataset),
                    UNIQUE KEY uq_table_dataset (table_dataset)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            // Add columns introduced after the first release (no-op if present).
            $this->ensureColumn('dp_datasets', 'recipients_dataset', 'TEXT NULL');
        } catch (Exception $e) {
            error_log('DataProtection ensureDatasetsTable: ' . $e->getMessage());
        }
    }

    /** Add a column to a table if it doesn't already exist. */
    private function ensureColumn($table, $col, $definition)
    {
        try {
            $st = $this->link->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $st->execute([$this->dbName, $table, $col]);
            if (!$st->fetchColumn()) {
                $this->link->exec("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
            }
        } catch (Exception $e) {
            error_log('DataProtection ensureColumn: ' . $e->getMessage());
        }
    }

    private function loadDatasetsFromDb()
    {
        try {
            $rows = $this->link->query("SELECT * FROM dp_datasets ORDER BY id_dataset ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $d = [
                'table'          => $r['table_dataset'],
                'id'             => $r['pk_dataset'],
                'subject_keys'   => json_decode($r['subject_keys_dataset'] ?? '[]', true) ?: [],
                'label'          => $r['label_dataset'] ?: $r['table_dataset'],
                'fields'         => json_decode($r['fields_dataset'] ?? '[]', true) ?: [],
                'sensitive'      => json_decode($r['sensitive_dataset'] ?? '[]', true) ?: [],
                'anonymize'      => json_decode($r['anonymize_dataset'] ?? '{}', true) ?: [],
                'purpose'        => $r['purpose_dataset'] ?? '',
                'legal_basis'    => $r['legal_basis_dataset'] ?? '',
                'recipients'     => $r['recipients_dataset'] ?? '',
                'retention_days' => $r['retention_days_dataset'] !== null ? (int) $r['retention_days_dataset'] : null,
            ];
            $res = $this->validateDatasets([$d]);
            if ($res['ok']) { $out[] = $d; }
        }
        return $out;
    }

    public function isConfigured() { return $this->link instanceof PDO && $this->configError === ''; }
    public function hasDatasets()  { return !empty($this->datasets); }
    public function configError()  { return $this->configError ?: 'No configurado.'; }
    public function rolesAllowed() { return $this->rolesAllowed; }

    /* -------------------- visual configuration API -------------------- */

    /** True if a base table with this exact name exists in the current schema. */
    private function tableExists($table)
    {
        if (!$this->isIdent($table)) { return false; }
        $st = $this->link->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND TABLE_TYPE = 'BASE TABLE'");
        $st->execute([$this->dbName, $table]);
        return (bool) $st->fetchColumn();
    }

    /** Real columns of a table: [['name'=>, 'type'=>, 'pk'=>bool], ...]. */
    public function listColumns($table)
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        if (!$this->tableExists($table)) { return ['success' => false, 'error' => 'Tabla no encontrada.']; }
        $st = $this->link->prepare("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
        $st->execute([$this->dbName, $table]);
        $cols = array_map(function ($c) {
            return ['name' => $c['COLUMN_NAME'], 'type' => $c['DATA_TYPE'], 'pk' => $c['COLUMN_KEY'] === 'PRI'];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
        return ['success' => true, 'columns' => $cols];
    }

    private function tableColumnNames($table)
    {
        $st = $this->link->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $st->execute([$this->dbName, $table]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    /** All base tables in the schema, flagged with whether they're already configured. */
    public function listTables()
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $st = $this->link->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
        $st->execute([$this->dbName]);
        $all = $st->fetchAll(PDO::FETCH_COLUMN);
        $configured = array_column($this->datasets, 'table');
        $internal = ['dp_datasets', 'dp_requests'];
        $tables = [];
        foreach ($all as $t) {
            if (in_array($t, $internal, true)) { continue; }
            $tables[] = ['name' => $t, 'configured' => in_array($t, $configured, true)];
        }
        return ['success' => true, 'tables' => $tables];
    }

    /** Configured datasets in a UI-friendly shape (for the list and the editor). */
    public function getDatasets()
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $out = array_map(function ($d) {
            return [
                'table'          => $d['table'],
                'label'          => $d['label'] ?? $d['table'],
                'pk'             => $d['id'],
                'subject_keys'   => $d['subject_keys'] ?? [],
                'fields'         => $d['fields'] ?? [],
                'sensitive'      => $d['sensitive'] ?? [],
                'anonymize'      => $d['anonymize'] ?? [],
                'purpose'        => $d['purpose'] ?? '',
                'legal_basis'    => $d['legal_basis'] ?? '',
                'recipients'     => $d['recipients'] ?? '',
                'retention_days' => $d['retention_days'] ?? null,
            ];
        }, $this->datasets);
        return ['success' => true, 'datasets' => $out];
    }

    /** Create or update a dataset (which columns of a table are personal data). */
    public function saveDataset($p)
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $table = $p['table'] ?? '';
        if (!$this->isIdent($table) || !$this->tableExists($table)) {
            return ['success' => false, 'error' => 'Selecciona una tabla válida.'];
        }
        $colSet = array_flip($this->tableColumnNames($table));

        $pk = $p['pk'] ?? '';
        if (!$this->isIdent($pk) || !isset($colSet[$pk])) {
            return ['success' => false, 'error' => 'Selecciona la columna identificadora (clave) de la tabla.'];
        }

        $clean = function ($arr) use ($colSet) {
            return array_values(array_filter(array_unique((array) $arr), function ($c) use ($colSet) {
                return is_string($c) && isset($colSet[$c]);
            }));
        };
        $subjectKeys = $clean($p['subject_keys'] ?? []);
        if (!$subjectKeys) {
            return ['success' => false, 'error' => 'Marca al menos una columna como identificador del titular (email, RUT…).'];
        }
        $fields    = $clean($p['fields'] ?? []);
        $sensitive = $clean($p['sensitive'] ?? []);

        $anon = [];
        foreach ((array) ($p['anonymize'] ?? []) as $col => $strat) {
            if (isset($colSet[$col]) && in_array($strat, self::STRATEGIES, true)) { $anon[$col] = $strat; }
        }

        $retention = (isset($p['retention_days']) && $p['retention_days'] !== '' && $p['retention_days'] !== null)
            ? max(0, (int) $p['retention_days']) : null;

        try {
            $st = $this->link->prepare(
                "INSERT INTO dp_datasets
                 (table_dataset, label_dataset, pk_dataset, subject_keys_dataset, fields_dataset, sensitive_dataset, anonymize_dataset, purpose_dataset, legal_basis_dataset, recipients_dataset, retention_days_dataset, date_created_dataset)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                   label_dataset=VALUES(label_dataset), pk_dataset=VALUES(pk_dataset),
                   subject_keys_dataset=VALUES(subject_keys_dataset), fields_dataset=VALUES(fields_dataset),
                   sensitive_dataset=VALUES(sensitive_dataset), anonymize_dataset=VALUES(anonymize_dataset),
                   purpose_dataset=VALUES(purpose_dataset), legal_basis_dataset=VALUES(legal_basis_dataset),
                   recipients_dataset=VALUES(recipients_dataset), retention_days_dataset=VALUES(retention_days_dataset)"
            );
            $st->execute([
                $table, trim((string) ($p['label'] ?? $table)) ?: $table, $pk,
                json_encode(array_values($subjectKeys)), json_encode(array_values($fields)),
                json_encode(array_values($sensitive)), json_encode($anon, JSON_FORCE_OBJECT),
                trim((string) ($p['purpose'] ?? '')), trim((string) ($p['legal_basis'] ?? '')),
                trim((string) ($p['recipients'] ?? '')), $retention,
            ]);
            return ['success' => true];
        } catch (Exception $e) {
            error_log('DataProtection saveDataset: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo guardar la configuración.'];
        }
    }

    public function deleteDataset($table)
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        if (!$this->isIdent($table)) { return ['success' => false, 'error' => 'Tabla inválida.']; }
        try {
            $st = $this->link->prepare("DELETE FROM dp_datasets WHERE table_dataset = ?");
            $st->execute([$table]);
            return ['success' => true];
        } catch (Exception $e) {
            error_log('DataProtection deleteDataset: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo quitar la tabla.'];
        }
    }

    public function datasetsMeta()
    {
        return array_map(function ($d) {
            return [
                'table'          => $d['table'],
                'label'          => $d['label'] ?? $d['table'],
                'fields'         => $d['fields'] ?? [],
                'sensitive'      => $d['sensitive'] ?? [],
                'purpose'        => $d['purpose'] ?? '',
                'legal_basis'    => $d['legal_basis'] ?? '',
                'retention_days' => $d['retention_days'] ?? null,
            ];
        }, $this->datasets);
    }

    /* ------------------------ subject lookup ------------------------ */

    private function subjectWhere($dataset, $value)
    {
        $conds = [];
        $params = [];
        foreach ($dataset['subject_keys'] as $k) {
            $conds[] = "`$k` = ?";
            $params[] = $value;
        }
        return ['(' . implode(' OR ', $conds) . ')', $params];
    }

    public function findSubject($value)
    {
        if (!$this->hasDatasets()) { return ['success' => false, 'error' => 'no_datasets']; }
        $value = trim((string) $value);
        if ($value === '') { return ['success' => false, 'error' => 'empty_query']; }

        $results = [];
        $total = 0;
        foreach ($this->datasets as $d) {
            [$where, $params] = $this->subjectWhere($d, $value);
            $fields = $d['fields'] ?? [];
            $cols = array_unique(array_merge([$d['id']], $fields));
            $select = implode(', ', array_map(function ($c) { return "`$c`"; }, $cols));
            try {
                $st = $this->link->prepare("SELECT $select FROM `{$d['table']}` WHERE $where LIMIT 500");
                $st->execute($params);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('DataProtection findSubject: ' . $e->getMessage());
                $rows = [];
            }
            if ($rows) {
                $total += count($rows);
                $results[] = [
                    'table'     => $d['table'],
                    'label'     => $d['label'] ?? $d['table'],
                    'id_column' => $d['id'],
                    'fields'    => $fields,
                    'sensitive' => $d['sensitive'] ?? [],
                    'count'     => count($rows),
                    'rows'      => $rows,
                ];
            }
        }
        return ['success' => true, 'subject' => $value, 'total' => $total, 'datasets' => $results];
    }

    public function exportSubject($value)
    {
        $found = $this->findSubject($value);
        if (empty($found['success'])) { return $found; }
        return [
            'success'      => true,
            'subject'      => $found['subject'],
            'generated_at' => date('c'),
            'total'        => $found['total'],
            'data'         => array_map(function ($d) {
                return ['table' => $d['table'], 'label' => $d['label'], 'records' => $d['rows']];
            }, $found['datasets']),
        ];
    }

    public function eraseSubject($value, $mode = 'anonymize')
    {
        if (!$this->hasDatasets()) { return ['success' => false, 'error' => 'no_datasets']; }
        $value = trim((string) $value);
        if ($value === '') { return ['success' => false, 'error' => 'empty_query']; }
        $mode = $mode === 'delete' ? 'delete' : 'anonymize';

        $affected = [];
        try {
            $this->link->beginTransaction();
            foreach ($this->datasets as $d) {
                [$where, $params] = $this->subjectWhere($d, $value);

                if ($mode === 'delete') {
                    $st = $this->link->prepare("DELETE FROM `{$d['table']}` WHERE $where");
                    $st->execute($params);
                    if ($st->rowCount() > 0) {
                        $affected[] = ['table' => $d['table'], 'label' => $d['label'] ?? $d['table'], 'rows' => $st->rowCount(), 'mode' => 'delete'];
                    }
                    continue;
                }

                $map = $d['anonymize'] ?? [];
                if (!$map) { continue; }
                $sets = [];
                foreach ($map as $col => $strategy) {
                    if (!$this->isIdent($col)) { continue; }
                    switch ($strategy) {
                        case 'null': $sets[] = "`$col` = NULL"; break;
                        case 'hash': $sets[] = "`$col` = SHA2(`$col`, 256)"; break;
                        case 'redact':
                        default:     $sets[] = "`$col` = '[anonimizado]'"; break;
                    }
                }
                if (!$sets) { continue; }
                $st = $this->link->prepare("UPDATE `{$d['table']}` SET " . implode(', ', $sets) . " WHERE $where");
                $st->execute($params);
                if ($st->rowCount() > 0) {
                    $affected[] = ['table' => $d['table'], 'label' => $d['label'] ?? $d['table'], 'rows' => $st->rowCount(), 'mode' => 'anonymize'];
                }
            }
            $this->link->commit();
        } catch (Exception $e) {
            if ($this->link->inTransaction()) { $this->link->rollBack(); }
            error_log('DataProtection eraseSubject: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo completar el borrado/anonimización (revisa que las columnas a anonimizar acepten el valor; "hash" requiere columna de 64+ caracteres).'];
        }

        $totalRows = array_sum(array_column($affected, 'rows'));
        return ['success' => true, 'subject' => $value, 'mode' => $mode, 'total_rows' => $totalRows, 'affected' => $affected];
    }

    /* ------------------------ ARCOP requests ------------------------ */

    private function ensureRequestsTable()
    {
        try {
            $this->link->exec(
                "CREATE TABLE IF NOT EXISTS dp_requests (
                    id_request INT NOT NULL AUTO_INCREMENT,
                    type_request VARCHAR(20) NOT NULL,
                    subject_request TEXT NULL,
                    channel_request VARCHAR(40) NULL,
                    status_request VARCHAR(20) NOT NULL DEFAULT 'pending',
                    notes_request TEXT NULL,
                    handler_request INT NULL,
                    due_request DATE NULL,
                    date_created_request DATETIME NULL,
                    date_updated_request TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_request)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Exception $e) {
            error_log('DataProtection ensureRequestsTable: ' . $e->getMessage());
        }
    }

    private static $TYPES = ['access', 'rectification', 'cancellation', 'opposition', 'portability', 'blocking'];

    public function createRequest($type, $subject, $channel = '', $notes = '', $handlerId = 0)
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $type = in_array($type, self::$TYPES, true) ? $type : '';
        if ($type === '') { return ['success' => false, 'error' => 'invalid_type']; }
        try {
            $due = date('Y-m-d', strtotime('+' . max(1, $this->responseDays) . ' days'));
            $st = $this->link->prepare(
                "INSERT INTO dp_requests (type_request, subject_request, channel_request, status_request, notes_request, handler_request, due_request, date_created_request)
                 VALUES (?, ?, ?, 'pending', ?, ?, ?, NOW())"
            );
            $st->execute([$type, trim((string) $subject), trim((string) $channel), trim((string) $notes), (int) $handlerId ?: null, $due]);
            return ['success' => true, 'id' => (int) $this->link->lastInsertId(), 'due' => $due];
        } catch (Exception $e) {
            error_log('DataProtection createRequest: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo registrar la solicitud.'];
        }
    }

    public function listRequests($status = '')
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        try {
            $sql = "SELECT r.*, COALESCE(NULLIF(a.title_admin,''), a.email_admin, '') AS handler_name,
                           DATEDIFF(r.due_request, CURDATE()) AS days_left
                    FROM dp_requests r LEFT JOIN admins a ON r.handler_request = a.id_admin";
            $params = [];
            if (in_array($status, ['pending', 'in_progress', 'done', 'rejected'], true)) {
                $sql .= " WHERE r.status_request = ?";
                $params[] = $status;
            }
            $sql .= " ORDER BY r.id_request DESC LIMIT 200";
            $st = $this->link->prepare($sql);
            $st->execute($params);
            return ['success' => true, 'requests' => $st->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            error_log('DataProtection listRequests: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudieron cargar las solicitudes.'];
        }
    }

    public function updateRequest($id, $status, $notes = null, $handlerId = 0)
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        if (!in_array($status, ['pending', 'in_progress', 'done', 'rejected'], true)) {
            return ['success' => false, 'error' => 'invalid_status'];
        }
        try {
            if ($notes !== null) {
                $st = $this->link->prepare("UPDATE dp_requests SET status_request = ?, notes_request = ?, handler_request = ? WHERE id_request = ?");
                $st->execute([$status, trim((string) $notes), (int) $handlerId ?: null, (int) $id]);
            } else {
                $st = $this->link->prepare("UPDATE dp_requests SET status_request = ?, handler_request = ? WHERE id_request = ?");
                $st->execute([$status, (int) $handlerId ?: null, (int) $id]);
            }
            return ['success' => $st->rowCount() >= 0];
        } catch (Exception $e) {
            error_log('DataProtection updateRequest: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo actualizar la solicitud.'];
        }
    }

    public function requestStats()
    {
        if (!$this->isConfigured()) { return ['pending' => 0, 'overdue' => 0]; }
        try {
            $pending = (int) $this->link->query("SELECT COUNT(*) FROM dp_requests WHERE status_request IN ('pending','in_progress')")->fetchColumn();
            $overdue = (int) $this->link->query("SELECT COUNT(*) FROM dp_requests WHERE status_request IN ('pending','in_progress') AND due_request < CURDATE()")->fetchColumn();
            return ['pending' => $pending, 'overdue' => $overdue];
        } catch (Exception $e) {
            return ['pending' => 0, 'overdue' => 0];
        }
    }

    /* ------------------ consent + cookie settings ------------------ */

    private function ensureConsentTables()
    {
        try {
            $this->link->exec(
                "CREATE TABLE IF NOT EXISTS dp_consents (
                    id_consent INT NOT NULL AUTO_INCREMENT,
                    subject_consent VARCHAR(190) NULL,
                    purpose_consent VARCHAR(190) NULL,
                    status_consent VARCHAR(20) NOT NULL DEFAULT 'granted',
                    channel_consent VARCHAR(40) NULL,
                    source_consent VARCHAR(190) NULL,
                    evidence_consent TEXT NULL,
                    ip_consent VARCHAR(64) NULL,
                    user_agent_consent TEXT NULL,
                    date_created_consent DATETIME NULL,
                    date_withdrawn_consent DATETIME NULL,
                    PRIMARY KEY (id_consent),
                    KEY idx_subject (subject_consent)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $this->link->exec(
                "CREATE TABLE IF NOT EXISTS dp_settings (
                    key_setting VARCHAR(64) NOT NULL,
                    value_setting TEXT NULL,
                    PRIMARY KEY (key_setting)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Exception $e) {
            error_log('DataProtection ensureConsentTables: ' . $e->getMessage());
        }
    }

    /**
     * Record a consent event. Used both from the CMS (manual) and from the public
     * endpoint (cookie banner / web forms). Server-side values (IP/UA) are passed
     * in by the caller; all strings are length-capped.
     */
    public function recordConsent($d)
    {
        if (!$this->link instanceof PDO) { return ['success' => false, 'error' => 'db']; }
        $cap = function ($v, $n) { return mb_substr(trim((string) $v), 0, $n); };
        $purpose = $cap($d['purpose'] ?? '', 190);
        if ($purpose === '') { return ['success' => false, 'error' => 'missing_purpose']; }
        $status = (($d['status'] ?? 'granted') === 'withdrawn') ? 'withdrawn' : 'granted';
        try {
            $st = $this->link->prepare(
                "INSERT INTO dp_consents (subject_consent, purpose_consent, status_consent, channel_consent, source_consent, evidence_consent, ip_consent, user_agent_consent, date_created_consent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $st->execute([
                $cap($d['subject'] ?? '', 190), $purpose, $status,
                $cap($d['channel'] ?? '', 40), $cap($d['source'] ?? '', 190),
                $cap($d['evidence'] ?? '', 1000), $cap($d['ip'] ?? '', 64), $cap($d['user_agent'] ?? '', 500),
            ]);
            return ['success' => true, 'id' => (int) $this->link->lastInsertId()];
        } catch (Exception $e) {
            error_log('DataProtection recordConsent: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo registrar el consentimiento.'];
        }
    }

    public function listConsents($subject = '', $status = '')
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        try {
            $sql = "SELECT * FROM dp_consents";
            $conds = []; $params = [];
            if (trim($subject) !== '') { $conds[] = "subject_consent LIKE ?"; $params[] = '%' . trim($subject) . '%'; }
            if (in_array($status, ['granted', 'withdrawn'], true)) { $conds[] = "status_consent = ?"; $params[] = $status; }
            if ($conds) { $sql .= " WHERE " . implode(' AND ', $conds); }
            $sql .= " ORDER BY id_consent DESC LIMIT 300";
            $st = $this->link->prepare($sql);
            $st->execute($params);
            return ['success' => true, 'consents' => $st->fetchAll(PDO::FETCH_ASSOC)];
        } catch (Exception $e) {
            error_log('DataProtection listConsents: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudieron cargar los consentimientos.'];
        }
    }

    /** Mark a consent as withdrawn (revocation is a right; we keep the audit trail). */
    public function withdrawConsent($id)
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        try {
            $st = $this->link->prepare("UPDATE dp_consents SET status_consent = 'withdrawn', date_withdrawn_consent = NOW() WHERE id_consent = ?");
            $st->execute([(int) $id]);
            return ['success' => true];
        } catch (Exception $e) {
            error_log('DataProtection withdrawConsent: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo revocar.'];
        }
    }

    public function consentStats()
    {
        if (!$this->isConfigured()) { return ['granted' => 0, 'withdrawn' => 0]; }
        try {
            $g = (int) $this->link->query("SELECT COUNT(*) FROM dp_consents WHERE status_consent = 'granted'")->fetchColumn();
            $w = (int) $this->link->query("SELECT COUNT(*) FROM dp_consents WHERE status_consent = 'withdrawn'")->fetchColumn();
            return ['granted' => $g, 'withdrawn' => $w];
        } catch (Exception $e) {
            return ['granted' => 0, 'withdrawn' => 0];
        }
    }

    /* ------------------------- settings (cookies) ------------------------- */

    public function getSettings()
    {
        $defaults = [
            'cookie_enabled'    => '1',
            'cookie_text'       => 'Usamos cookies propias y de terceros para mejorar tu experiencia. Puedes aceptarlas o rechazarlas.',
            'cookie_policy_url' => '',
            'cookie_accept'     => 'Aceptar',
            'cookie_reject'     => 'Rechazar',
        ];
        if (!$this->link instanceof PDO) { return $defaults; }
        try {
            $rows = $this->link->query("SELECT key_setting, value_setting FROM dp_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            return array_merge($defaults, is_array($rows) ? $rows : []);
        } catch (Exception $e) {
            return $defaults;
        }
    }

    public function saveSettings($arr)
    {
        if (!$this->isConfigured()) { return ['success' => false, 'error' => $this->configError]; }
        $allowed = ['cookie_enabled', 'cookie_text', 'cookie_policy_url', 'cookie_accept', 'cookie_reject'];
        try {
            $st = $this->link->prepare("INSERT INTO dp_settings (key_setting, value_setting) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_setting = VALUES(value_setting)");
            foreach ($allowed as $k) {
                if (array_key_exists($k, (array) $arr)) {
                    $st->execute([$k, mb_substr((string) $arr[$k], 0, 2000)]);
                }
            }
            return ['success' => true];
        } catch (Exception $e) {
            error_log('DataProtection saveSettings: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se pudo guardar la configuración de cookies.'];
        }
    }
}
