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

// CSRF protection for state-changing actions. The token is sent via the
// X-CSRF-Token header (native fetch is patched in auth-interceptor.js) or
// the _csrf_token POST field.
$mutatingActions = ['save', 'reset', 'save_seo'];
if (in_array($action, $mutatingActions, true)) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? '');
    if (!SessionController::validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

$link   = Connection::connect();

if (!$link) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

try {

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

        $colorKeys = ['theme_primary', 'theme_sidebar_bg', 'theme_active_bg', 'theme_active_color', 'theme_active_border'];
        $textKeys  = ['theme_brand_title', 'theme_brand_logo', 'theme_brand_symbol', 'theme_brand_favicon']; // brand name, logo URL, icon class, favicon URL (not colors)
        $allowed   = array_merge($colorKeys, $textKeys);
        $stmt      = $link->prepare("
            INSERT INTO cms_settings (key_setting, value_setting)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value_setting = VALUES(value_setting)
        ");

        foreach ($allowed as $key) {
            if (!isset($_POST[$key])) continue;
            $val = trim($_POST[$key]);
            if (in_array($key, $colorKeys, true)) {
                // Color keys must be a valid hex color.
                if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $val)) continue;
            } else {
                // Brand title/logo: plain text, tags stripped and length-capped.
                $val = mb_substr(strip_tags($val), 0, 255);
            }
            $stmt->execute([$key, $val]);
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

                // Reject a malformed canonical base URL before storing it.
                // Use parse_url() (not FILTER_VALIDATE_URL) so IDN domains and
                // accented paths common in a Spanish CMS are accepted.
                if ($key === 'seo_canonical_base_url' && $val !== '') {
                    $parsed = parse_url($val);
                    if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])
                        || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
                        echo json_encode(['success' => false, 'error' => 'URL canónica inválida']);
                        exit;
                    }
                }

                $stmt->execute([$key, $val]);
            }
        }

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

} catch (Throwable $e) {
    Logger::error("Theme settings AJAX error", ['exception' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
