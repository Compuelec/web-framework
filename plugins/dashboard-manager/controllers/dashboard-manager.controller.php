<?php
require_once __DIR__ . '/../../../cms/controllers/install.controller.php';

class DashboardManagerController {

    private $link;
    private $excludedTables = ['admins', 'framework_migrations', 'dashboard_widgets'];

    public function __construct() {
        $this->link = InstallController::connect();
        $this->ensureTableExists();
    }

    // Create table if it does not exist
    private function ensureTableExists() {
        $this->link->exec("
            CREATE TABLE IF NOT EXISTS `dashboard_widgets` (
                `id_widget`           INT         NOT NULL AUTO_INCREMENT,
                `id_admin_widget`     INT         NOT NULL DEFAULT 0,
                `type_widget`         VARCHAR(50) NOT NULL DEFAULT 'metric',
                `title_widget`        TEXT        NULL,
                `config_widget`       TEXT        NULL,
                `position_widget`     INT         NULL DEFAULT 0,
                `width_widget`        VARCHAR(20) NULL DEFAULT 'col-md-4',
                `refresh_widget`      INT         NULL DEFAULT 0,
                `date_created_widget` DATE        NULL,
                PRIMARY KEY (`id_widget`),
                INDEX `idx_admin_position` (`id_admin_widget`, `position_widget`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    // Get all widgets for an admin ordered by position
    public function getWidgets($adminId) {
        $stmt = $this->link->prepare(
            "SELECT * FROM dashboard_widgets WHERE id_admin_widget = ? ORDER BY position_widget ASC"
        );
        $stmt->execute([(int)$adminId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // Dispatch data fetch to appropriate method based on widget type
    public function getWidgetData($widgetId, $adminId) {
        $stmt = $this->link->prepare(
            "SELECT * FROM dashboard_widgets WHERE id_widget = ? AND id_admin_widget = ?"
        );
        $stmt->execute([(int)$widgetId, (int)$adminId]);
        $widget = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$widget) {
            return ['success' => false, 'error' => 'Widget not found'];
        }

        $config = json_decode($widget->config_widget ?? '{}', true) ?? [];

        switch ($widget->type_widget) {
            case 'metric':     $data = $this->getMetricData($config);    break;
            case 'chart':      $data = $this->getChartData($config);     break;
            case 'recent':     $data = $this->getRecentData($config);    break;
            case 'kpi':        $data = $this->getKpiData($config);       break;
            case 'activity':   $data = $this->getActivityData($config);  break;
            case 'quicklinks': $data = ['links' => $config['links'] ?? []]; break;
            case 'html':       $data = ['content' => $config['content'] ?? '']; break;
            case 'system':     $data = $this->getSystemData();           break;
            default:           $data = [];
        }

        return [
            'success' => true,
            'data'    => $data,
            'type'    => $widget->type_widget,
            'config'  => $config,
            'title'   => $widget->title_widget   ?? '',
            'width'   => $widget->width_widget   ?? 'col-md-4',
            'refresh' => (int)($widget->refresh_widget ?? 0),
        ];
    }

    // Metric: count/sum/avg of a column in a table
    private function getMetricData($config) {
        $table     = $config['table']     ?? '';
        $operation = $config['operation'] ?? 'count';
        $column    = $config['column']    ?? '';

        if (empty($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return ['value' => 0];
        }

        try {
            if ($operation === 'sum' && !empty($column) && preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                $stmt = $this->link->query("SELECT COALESCE(SUM(`{$column}`), 0) as value FROM `{$table}`");
            } elseif ($operation === 'avg' && !empty($column) && preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                $stmt = $this->link->query("SELECT ROUND(COALESCE(AVG(`{$column}`), 0), 2) as value FROM `{$table}`");
            } else {
                $stmt = $this->link->query("SELECT COUNT(*) as value FROM `{$table}`");
            }
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return ['value' => $row->value ?? 0];
        } catch (Exception $e) {
            return ['value' => 0];
        }
    }

    // Chart: records grouped by date over a period
    private function getChartData($config) {
        $table      = $config['table']       ?? '';
        $dateColumn = $config['date_column'] ?? '';
        $period     = (int)($config['period'] ?? 30);

        if (empty($table) || empty($dateColumn)) return ['labels' => [], 'values' => []];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table))      return ['labels' => [], 'values' => []];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dateColumn)) return ['labels' => [], 'values' => []];

        try {
            $stmt = $this->link->prepare("
                SELECT DATE(`{$dateColumn}`) as d, COUNT(*) as v
                FROM `{$table}`
                WHERE `{$dateColumn}` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND `{$dateColumn}` IS NOT NULL
                GROUP BY DATE(`{$dateColumn}`)
                ORDER BY d ASC
            ");
            $stmt->execute([$period]);
            $rows   = $stmt->fetchAll(PDO::FETCH_OBJ);
            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = $row->d;
                $values[] = (int)$row->v;
            }
            return ['labels' => $labels, 'values' => $values];
        } catch (Exception $e) {
            return ['labels' => [], 'values' => []];
        }
    }

    // Recent: last N records from a table
    private function getRecentData($config) {
        $table = $config['table'] ?? '';
        $limit = min((int)($config['limit'] ?? 5), 20);

        if (empty($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return ['records' => [], 'columns' => []];
        }

        try {
            $colStmt    = $this->link->query("SHOW COLUMNS FROM `{$table}`");
            $allColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($allColumns)) return ['records' => [], 'columns' => []];

            $idColumn = $allColumns[0];
            $stmt = $this->link->query(
                "SELECT * FROM `{$table}` ORDER BY `{$idColumn}` DESC LIMIT {$limit}"
            );
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Trim long values for display
            foreach ($records as &$rec) {
                foreach ($rec as $k => &$v) {
                    if (is_string($v) && strlen($v) > 80) {
                        $v = mb_substr($v, 0, 77) . '...';
                    }
                }
            }
            return ['records' => $records, 'columns' => array_slice($allColumns, 0, 5)];
        } catch (Exception $e) {
            return ['records' => [], 'columns' => []];
        }
    }

    // KPI: current period count vs previous period with trend %
    private function getKpiData($config) {
        $table      = $config['table']       ?? '';
        $dateColumn = $config['date_column'] ?? '';
        $period     = (int)($config['period_days'] ?? 30);

        if (empty($table) || empty($dateColumn)) return ['current' => 0, 'previous' => 0, 'trend' => 0];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table))      return ['current' => 0, 'previous' => 0, 'trend' => 0];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dateColumn)) return ['current' => 0, 'previous' => 0, 'trend' => 0];

        try {
            $stmtCurr = $this->link->prepare("
                SELECT COUNT(*) FROM `{$table}`
                WHERE `{$dateColumn}` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ");
            $stmtCurr->execute([$period]);
            $current = (int)$stmtCurr->fetchColumn();

            $stmtPrev = $this->link->prepare("
                SELECT COUNT(*) FROM `{$table}`
                WHERE `{$dateColumn}` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND `{$dateColumn}` <  DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ");
            $stmtPrev->execute([$period * 2, $period]);
            $previous = (int)$stmtPrev->fetchColumn();

            $trend = 0;
            if ($previous > 0) {
                $trend = round((($current - $previous) / $previous) * 100, 1);
            } elseif ($current > 0) {
                $trend = 100;
            }

            return ['current' => $current, 'previous' => $previous, 'trend' => $trend];
        } catch (Exception $e) {
            return ['current' => 0, 'previous' => 0, 'trend' => 0];
        }
    }

    // Activity: last N entries from activity_logs
    private function getActivityData($config) {
        $limit = min((int)($config['limit'] ?? 10), 20);

        try {
            $check = $this->link->query("SHOW TABLES LIKE 'activity_logs'");
            if ($check->rowCount() === 0) return ['logs' => []];

            $stmt = $this->link->prepare("
                SELECT al.action_log, al.entity_log, al.entity_id_log,
                       al.description_log, al.date_created_log, a.name_admin
                FROM activity_logs al
                LEFT JOIN admins a ON al.admin_id_log = a.id_admin
                ORDER BY al.id_log DESC
                LIMIT {$limit}
            ");
            $stmt->execute();
            return ['logs' => $stmt->fetchAll(PDO::FETCH_OBJ)];
        } catch (Exception $e) {
            return ['logs' => []];
        }
    }

    // System: PHP/MySQL versions, disk usage
    private function getSystemData() {
        $data = [
            'php_version'   => PHP_VERSION,
            'mysql_version' => '',
            'disk_free_gb'  => null,
            'disk_total_gb' => null,
            'disk_percent'  => null,
            'server_os'     => PHP_OS,
        ];

        try {
            $row = $this->link->query("SELECT VERSION() as v")->fetch(PDO::FETCH_OBJ);
            $data['mysql_version'] = $row->v ?? '';
        } catch (Exception $e) {}

        $projectRoot = __DIR__;
        $diskFree    = @disk_free_space($projectRoot);
        $diskTotal   = @disk_total_space($projectRoot);
        if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
            $data['disk_free_gb']  = round($diskFree  / (1024 ** 3), 1);
            $data['disk_total_gb'] = round($diskTotal / (1024 ** 3), 1);
            $data['disk_percent']  = round((1 - $diskFree / $diskTotal) * 100, 1);
        }

        return $data;
    }

    // Get user tables (excluding system/internal tables)
    public function getTables() {
        try {
            $stmt   = $this->link->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return array_values(array_filter($tables, function($t) {
                return !in_array($t, $this->excludedTables);
            }));
        } catch (Exception $e) {
            return [];
        }
    }

    // Get columns of a table
    public function getColumns($tableName) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) return [];
        try {
            $stmt = $this->link->query("SHOW COLUMNS FROM `{$tableName}`");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }

    // Save a new widget
    public function saveWidget($adminId, $data) {
        $stmt = $this->link->prepare(
            "SELECT COALESCE(MAX(position_widget), -1) + 1 FROM dashboard_widgets WHERE id_admin_widget = ?"
        );
        $stmt->execute([(int)$adminId]);
        $nextPos = (int)$stmt->fetchColumn();

        $stmt = $this->link->prepare("
            INSERT INTO dashboard_widgets
                (id_admin_widget, type_widget, title_widget, config_widget,
                 position_widget, width_widget, refresh_widget, date_created_widget)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([
            (int)$adminId,
            $data['type']              ?? 'metric',
            $data['title']             ?? '',
            json_encode($data['config'] ?? [], JSON_UNESCAPED_UNICODE),
            $nextPos,
            $data['width']             ?? 'col-md-4',
            (int)($data['refresh']     ?? 0),
        ]);

        return ['success' => true, 'id' => (int)$this->link->lastInsertId()];
    }

    // Update widget config/title/width
    public function updateWidget($id, $adminId, $data) {
        $stmt = $this->link->prepare("
            UPDATE dashboard_widgets
            SET title_widget   = ?,
                config_widget  = ?,
                width_widget   = ?,
                refresh_widget = ?
            WHERE id_widget = ? AND id_admin_widget = ?
        ");
        $stmt->execute([
            $data['title']         ?? '',
            json_encode($data['config'] ?? [], JSON_UNESCAPED_UNICODE),
            $data['width']         ?? 'col-md-4',
            (int)($data['refresh'] ?? 0),
            (int)$id,
            (int)$adminId,
        ]);
        return ['success' => $stmt->rowCount() > 0];
    }

    // Delete a widget
    public function deleteWidget($id, $adminId) {
        $stmt = $this->link->prepare(
            "DELETE FROM dashboard_widgets WHERE id_widget = ? AND id_admin_widget = ?"
        );
        $stmt->execute([(int)$id, (int)$adminId]);
        return ['success' => $stmt->rowCount() > 0];
    }

    // Bulk update widget positions after drag-and-drop
    public function updatePositions($adminId, $positions) {
        $stmt = $this->link->prepare(
            "UPDATE dashboard_widgets SET position_widget = ? WHERE id_widget = ? AND id_admin_widget = ?"
        );
        foreach ($positions as $pos) {
            $stmt->execute([(int)$pos['position'], (int)$pos['id'], (int)$adminId]);
        }
        return ['success' => true];
    }
}
