<?php

/**
 * Theme Settings AJAX Endpoint
 * Read and write CMS theme settings (stored in cms_settings table).
 */

define('SESSION_INIT_INCLUDED', true);
require_once __DIR__ . '/session-init.php';
require_once __DIR__ . '/../../api/models/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only superadmin can change theme
$isSuperadmin = ($_SESSION['admin']->rol_admin ?? '') === 'superadmin';

$action = $_POST['action'] ?? $_GET['action'] ?? 'get';
$link   = Connection::connect();

if (!$link) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

// Ensure table exists
$link->exec("
    CREATE TABLE IF NOT EXISTS `cms_settings` (
        `id_setting`           INT          NOT NULL AUTO_INCREMENT,
        `key_setting`          VARCHAR(100) NOT NULL,
        `value_setting`        TEXT         NULL,
        `date_updated_setting` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_setting`),
        UNIQUE KEY `uk_key` (`key_setting`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

switch ($action) {

    case 'get':
        $stmt   = $link->query("SELECT key_setting, value_setting FROM cms_settings WHERE key_setting LIKE 'theme_%'");
        $rows   = $stmt->fetchAll(PDO::FETCH_OBJ);
        $theme  = [];
        foreach ($rows as $row) {
            $theme[$row->key_setting] = $row->value_setting;
        }
        echo json_encode(['success' => true, 'theme' => $theme]);
        break;

    case 'save':
        if (!$isSuperadmin) {
            echo json_encode(['success' => false, 'error' => 'Solo el superadmin puede cambiar el tema']);
            exit;
        }

        $allowed = ['theme_primary', 'theme_sidebar_bg', 'theme_active_bg', 'theme_active_color', 'theme_active_border'];
        $stmt    = $link->prepare("
            INSERT INTO cms_settings (key_setting, value_setting)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value_setting = VALUES(value_setting)
        ");

        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                // Validate: must be a valid hex color
                if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $val)) continue;
                $stmt->execute([$key, $val]);
            }
        }

        // Clear session cache so next page load picks up new values
        unset($_SESSION['cms_theme']);

        echo json_encode(['success' => true]);
        break;

    case 'reset':
        if (!$isSuperadmin) {
            echo json_encode(['success' => false, 'error' => 'Solo el superadmin puede cambiar el tema']);
            exit;
        }

        $defaults = [
            'theme_primary'       => '#6c5ffc',
            'theme_sidebar_bg'    => '#ffffff',
            'theme_active_bg'     => '#eff6ff',
            'theme_active_color'  => '#1e40af',
            'theme_active_border' => '#3b82f6',
        ];

        $stmt = $link->prepare("
            INSERT INTO cms_settings (key_setting, value_setting)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value_setting = VALUES(value_setting)
        ");
        foreach ($defaults as $key => $val) {
            $stmt->execute([$key, $val]);
        }

        unset($_SESSION['cms_theme']);
        echo json_encode(['success' => true, 'theme' => $defaults]);
        break;

    case 'get_seo':
        $stmt = $link->query("SELECT key_setting, value_setting FROM cms_settings WHERE key_setting LIKE 'seo_%'");
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        $seo  = [];
        foreach ($rows as $row) {
            $seo[$row->key_setting] = $row->value_setting;
        }
        echo json_encode(['success' => true, 'seo' => $seo]);
        break;

    case 'save_seo':
        if (!$isSuperadmin) {
            echo json_encode(['success' => false, 'error' => 'Solo el superadmin puede cambiar la configuración SEO']);
            exit;
        }

        $allowedSeo = ['seo_default_title', 'seo_default_description', 'seo_canonical_base_url'];
        $stmt = $link->prepare("
            INSERT INTO cms_settings (key_setting, value_setting)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value_setting = VALUES(value_setting)
        ");

        foreach ($allowedSeo as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                $stmt->execute([$key, $val]);
            }
        }

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
