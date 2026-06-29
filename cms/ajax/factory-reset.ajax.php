<?php

/**
 * Factory Reset AJAX Endpoint (superadmin only)
 *
 * Restores the CMS to its fresh-install state to speed up bootstrapping a new
 * custom project from this framework:
 *   - Drops user-created custom tables (registered in `modules`) AND plugin
 *     tables (discovered from the plugins' source); both recreate on demand.
 *   - Clears the data of the framework tables (pages, modules, columns, folders,
 *     files, activity_logs, workflows, cms_settings, page_seo, notifications),
 *     keeping migration/update tracking intact.
 *   - Keeps ONLY the superadmin that triggers the reset (other admins removed).
 *   - Re-seeds the default install pages/modules/columns/folder so the panel
 *     stays usable. The system pages (dashboard, apariencia, ...) are recreated
 *     by the template auto-setup on the next load.
 *
 * Backup strategy: before anything destructive, a full project package
 * (database + files) is created via PackagingController::createPackage(). It is
 * stored in packages/ and remains available in the Empaquetado list for
 * download or one-click restore. The reset aborts if the backup fails.
 */

define('SESSION_INIT_INCLUDED', true);
require_once __DIR__ . '/session-init.php';
require_once __DIR__ . '/../controllers/install.controller.php';
require_once __DIR__ . '/../controllers/packaging.controller.php';

// Creating the backup package (DB + files) can take a while.
set_time_limit(300);
ini_set('max_execution_time', '300');

// ── Auth: must be a logged-in superadmin ──────────────────────────────────
if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$isSuperadmin = ($_SESSION['admin']->rol_admin ?? '') === 'superadmin';
if (!$isSuperadmin) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Solo el superadmin puede restablecer el sistema']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

header('Content-Type: application/json');

// ── CSRF protection for the destructive action ────────────────────────────
if ($action === 'reset') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? '');
    if (!SessionController::validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ── Run the factory reset ─────────────────────────────────────────────────
try {

    $link    = InstallController::connect();
    $adminId = (int) ($_SESSION['admin']->id_admin ?? 0);

    if ($adminId <= 0) {
        echo json_encode(['success' => false, 'error' => 'No se pudo identificar al superadmin actual']);
        exit;
    }

    // Guard list: tables that must NEVER be dropped. These are the framework
    // core (install) tables plus infrastructure tables that hold state which
    // does not self-regenerate (migration/update tracking) or framework data
    // tables whose structure must survive (cleared via TRUNCATE instead).
    $guardTables = [
        // Core install tables.
        'admins', 'pages', 'modules', 'columns', 'folders', 'files', 'activity_logs', 'workflows',
        // Framework data tables (structure preserved, data cleared below).
        'cms_settings', 'page_seo', 'notifications',
        // Framework infrastructure: keep state, do NOT touch.
        'framework_migrations', 'framework_updates',
    ];
    $guardLower = array_map('strtolower', $guardTables);

    // All tables currently in the database, indexed by lowercase name so we can
    // match case-insensitively (macOS MySQL is case-insensitive by default).
    $allTables    = $link->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $existingByLc = [];
    foreach ($allTables as $t) {
        $existingByLc[strtolower($t)] = $t;
    }

    // Tables to drop = user-created custom tables (registered in `modules`) +
    // plugin tables (discovered from the plugins' source code). Both kinds are
    // safe to drop: custom data is the user's, and every plugin recreates its
    // tables on next load/activation. The guard list is always excluded.
    $customTables = [];
    try {
        $rows = $link->query("SELECT DISTINCT title_module FROM modules WHERE type_module = 'tables'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $t) {
            $t = strtolower(trim((string) $t));
            if ($t !== '') {
                $customTables[] = $t;
            }
        }
    } catch (Throwable $e) {
        // modules table may not exist yet; nothing custom to drop then.
    }

    $pluginTables = factoryResetDiscoverPluginTables(); // lowercase names

    $dropCandidates = array_unique(array_merge($customTables, $pluginTables));
    $tablesToDrop   = [];
    foreach ($dropCandidates as $lc) {
        if (in_array($lc, $guardLower, true)) {
            continue; // never drop a guarded table
        }
        if (!isset($existingByLc[$lc])) {
            continue; // table doesn't actually exist
        }
        $tablesToDrop[] = $existingByLc[$lc]; // real-case name
    }

    // Preserve the current superadmin's row (credentials, profile, token).
    $stmt = $link->prepare("SELECT * FROM admins WHERE id_admin = ?");
    $stmt->execute([$adminId]);
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$superadmin) {
        echo json_encode(['success' => false, 'error' => 'No se encontró el registro del superadmin actual']);
        exit;
    }

    // Backup: create a full project package (database + files) BEFORE anything
    // destructive. It lands in packages/ and stays available in the Empaquetado
    // list for download or one-click restore. Abort the reset if it fails or if
    // the database could not be included — a backup without the DB is useless.
    $backup = PackagingController::createPackage();
    if (empty($backup['success'])) {
        echo json_encode([
            'success' => false,
            'error'   => 'No se pudo crear el paquete de respaldo, por lo que no se borró nada. Detalle: '
                . ($backup['message'] ?? 'error desconocido'),
        ]);
        exit;
    }
    if (empty($backup['database_included'])) {
        echo json_encode([
            'success' => false,
            'error'   => 'El paquete de respaldo se creó sin la base de datos, por lo que no se borró nada. Detalle: '
                . ($backup['database_export_error'] ?? 'no se pudo exportar la base de datos'),
        ]);
        exit;
    }

    // Destructive phase.
    $link->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Drop custom + plugin tables.
    foreach ($tablesToDrop as $t) {
        $link->exec("DROP TABLE IF EXISTS `" . str_replace('`', '', $t) . "`");
    }

    // Clear framework data tables (structure preserved). page_seo and
    // notifications are emptied too so no rows point at deleted pages/data.
    $truncateTables = ['pages', 'modules', 'columns', 'folders', 'files', 'activity_logs', 'workflows', 'cms_settings', 'page_seo', 'notifications'];
    foreach ($truncateTables as $t) {
        if (isset($existingByLc[strtolower($t)])) {
            $link->exec("TRUNCATE TABLE `" . $existingByLc[strtolower($t)] . "`");
        }
    }

    // Keep only the current superadmin.
    $del = $link->prepare("DELETE FROM admins WHERE id_admin <> ?");
    $del->execute([$adminId]);

    $link->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Re-seed the default install pages/modules/columns/folder.
    factoryResetSeed($link, $adminId);

    // Remove orphaned custom-page folders on disk (best-effort).
    $folders = factoryResetCleanupFolders();

    // Remove builder/custom public pages (web/pages), keeping framework examples.
    $webPages = factoryResetCleanupWebPages();

    // Log the operation (activity_logs was just truncated, so this is row #1).
    factoryResetLog($link, $adminId, count($tablesToDrop), count($folders['deleted']), $backup['filename'] ?? '');

    echo json_encode([
        'success'           => true,
        'dropped'           => $tablesToDrop,
        'dropped_count'     => count($tablesToDrop),
        'folders_deleted'   => $folders['deleted'],
        'folders_failed'    => $folders['failed'],
        'web_pages_deleted' => $webPages['deleted'],
        'web_pages_failed'  => $webPages['failed'],
        'backup_package'    => $backup['filename'] ?? '',
        'backup_size_mb'    => $backup['size_mb'] ?? null,
    ]);

} catch (Throwable $e) {
    if (class_exists('Logger')) {
        Logger::error('Factory reset error', ['exception' => $e->getMessage()]);
    }
    echo json_encode(['success' => false, 'error' => 'Error durante el restablecimiento: ' . $e->getMessage()]);
}

// ──────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────

/**
 * Discover plugin table names by scanning every plugin's PHP source for
 * `CREATE TABLE [IF NOT EXISTS] <name>` statements. Returns lowercase names.
 *
 * This keeps the reset future-proof: any new plugin that creates a table is
 * picked up automatically, and the caller's guard list prevents core/framework
 * tables from being dropped even if a plugin references them.
 *
 * @return string[] lowercase table names
 */
function factoryResetDiscoverPluginTables(): array
{
    $names      = [];
    $pluginsDir = __DIR__ . '/../../plugins';
    if (!is_dir($pluginsDir)) {
        return $names;
    }

    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginsDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $code = @file_get_contents($file->getPathname());
            if ($code === false || $code === '') {
                continue;
            }
            if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $code, $m)) {
                foreach ($m[1] as $name) {
                    $names[] = strtolower($name);
                }
            }
        }
    } catch (Throwable $e) {
        // Scanning is best-effort; fall back to whatever was collected.
    }

    return array_values(array_unique($names));
}

/**
 * Re-create the default pages/modules/columns/folder created by a fresh install
 * so the admin panel remains usable right after the reset.
 */
function factoryResetSeed(PDO $link, int $adminId): void
{
    $today = date('Y-m-d');

    // ── Default pages ─────────────────────────────────────────────────────
    $pages = [
        ['Inicio',          'inicio',        'bi bi-house-door-fill',     'modules', 1],
        ['Admins',          'admins',        'bi bi-person-fill-gear',    'modules', 2],
        ['Archivos',        'archivos',      'bi bi-file-earmark-image',  'custom',  3],
        ['Actualizaciones', 'updates',       'bi bi-arrow-repeat',        'custom',  4],
        ['Logs',            'activity_logs', 'bi bi-journal-text',        'custom',  5],
    ];

    $insPage = $link->prepare(
        "INSERT INTO pages (title_page, url_page, icon_page, type_page, parent_page, order_page, date_created_page)
         VALUES (?, ?, ?, ?, 0, ?, ?)"
    );

    $adminsPageId = 0;
    foreach ($pages as $p) {
        $insPage->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $today]);
        if ($p[1] === 'admins') {
            $adminsPageId = (int) $link->lastInsertId();
        }
    }

    if ($adminsPageId <= 0) {
        return; // Should not happen, but bail out safely.
    }

    // ── Modules on the Admins page ────────────────────────────────────────
    $insModule = $link->prepare(
        "INSERT INTO modules (id_page_module, type_module, title_module, suffix_module, editable_module, date_created_module)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    // Breadcrumbs module.
    $insModule->execute([$adminsPageId, 'breadcrumbs', 'Administradores', null, 1, $today]);

    // Admins table module.
    $insModule->execute([$adminsPageId, 'tables', 'admins', 'admin', 0, $today]);
    $adminsModuleId = (int) $link->lastInsertId();

    // ── Columns for the admins table module (mirrors install.controller) ──
    $columns = [
        ['rol_admin',        'rol',         'select',   'superadmin,admin,editor', 1],
        ['permissions_admin','permisos',    'object',   '',                        1],
        ['email_admin',      'email',       'email',    '',                        1],
        ['password_admin',   'pass',        'password', '',                        0],
        ['token_admin',      'token',       'text',     '',                        0],
        ['token_exp_admin',  'expiración',  'text',     '',                        0],
        ['status_admin',     'estado',      'boolean',  '',                        1],
        ['title_admin',      'título',      'text',     '',                        0],
        ['symbol_admin',     'simbolo',     'text',     '',                        0],
        ['font_admin',       'tipografía',  'text',     '',                        0],
        ['color_admin',      'color',       'text',     '',                        0],
        ['back_admin',       'fondo',       'text',     '',                        0],
        ['scode_admin',      'seguridad',   'text',     '',                        0],
        ['chatgpt_admin',    'chatgpt',     'object',   '',                        0],
    ];

    $insColumn = $link->prepare(
        "INSERT INTO columns (id_module_column, title_column, alias_column, type_column, matrix_column, visible_column, date_created_column)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($columns as $c) {
        $insColumn->execute([$adminsModuleId, $c[0], $c[1], $c[2], $c[3], $c[4], $today]);
    }

    // ── Default Server folder ─────────────────────────────────────────────
    $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
    $host   = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $insFolder = $link->prepare(
        "INSERT INTO folders (name_folder, size_folder, max_upload_folder, url_folder, date_created_folder)
         VALUES (?, ?, ?, ?, ?)"
    );
    $insFolder->execute(['Server', '200000000000', '500000000', $scheme . '://' . $host, $today]);

    // ── Packaging page (optional, best-effort) ────────────────────────────
    $packagingSetup = __DIR__ . '/../controllers/packaging-setup.controller.php';
    if (file_exists($packagingSetup)) {
        try {
            require_once $packagingSetup;
            if (class_exists('PackagingSetupController') && method_exists('PackagingSetupController', 'ensurePackagingPage')) {
                PackagingSetupController::ensurePackagingPage();
            }
        } catch (Throwable $e) {
            // Non-critical; ignore.
        }
    }

    // ── Páginas Web (visual builder) page (best-effort) ───────────────────
    $webPagesSetup = __DIR__ . '/../controllers/web-pages-setup.controller.php';
    if (file_exists($webPagesSetup)) {
        try {
            require_once $webPagesSetup;
            if (class_exists('WebPagesSetupController') && method_exists('WebPagesSetupController', 'ensureWebPagesPage')) {
                WebPagesSetupController::ensureWebPagesPage();
            }
        } catch (Throwable $e) {
            // Non-critical; ignore.
        }
    }
}

/**
 * Remove orphaned custom-page folders left under cms/views/pages/custom/.
 *
 * Protected (never deleted): the framework's own system pages and every
 * installed plugin's page folder (matched by exact name or "plugin-" prefix,
 * e.g. payku-test). Everything else is treated as a user-created page that the
 * reset orphaned. Deletion is best-effort: folders the web server cannot remove
 * (different owner/permissions) are reported as failed instead of aborting.
 *
 * @return array{deleted:string[],failed:string[]}
 */
function factoryResetCleanupFolders(): array
{
    $deleted = [];
    $failed  = [];

    $customDir = __DIR__ . '/../views/pages/custom';
    if (!is_dir($customDir)) {
        return ['deleted' => $deleted, 'failed' => $failed];
    }

    // Framework system pages that must always remain.
    $systemPages = ['activity_logs', 'apariencia', 'archivos', 'dashboard', 'packaging', 'system-health', 'updates', 'web-pages'];

    // Installed plugin names (their custom pages must remain).
    $pluginNames = [];
    $pluginsDir  = __DIR__ . '/../../plugins';
    if (is_dir($pluginsDir)) {
        foreach (scandir($pluginsDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($pluginsDir . '/' . $entry)) {
                $pluginNames[] = $entry;
            }
        }
    }

    foreach (scandir($customDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $customDir . '/' . $entry;
        if (!is_dir($path)) {
            continue; // skip files like custom.php
        }
        if (in_array($entry, $systemPages, true)) {
            continue;
        }
        // Protect plugin pages: exact name or "<plugin>-..." variants.
        $isPluginPage = false;
        foreach ($pluginNames as $pn) {
            if ($entry === $pn || strpos($entry, $pn . '-') === 0) {
                $isPluginPage = true;
                break;
            }
        }
        if ($isPluginPage) {
            continue;
        }

        if (factoryResetRrmdir($path)) {
            $deleted[] = $entry;
        } else {
            $failed[] = $entry;
        }
    }

    return ['deleted' => $deleted, 'failed' => $failed];
}

/**
 * Remove builder-generated / custom public pages from web/pages so a factory
 * reset returns the public site to a clean install state. The framework example
 * pages (example-*.php) and protected files (.htaccess) are always kept.
 */
function factoryResetCleanupWebPages(): array
{
    $deleted = [];
    $failed  = [];

    $pagesDir = realpath(__DIR__ . '/../../web/pages');
    if ($pagesDir === false || !is_dir($pagesDir)) {
        return ['deleted' => $deleted, 'failed' => $failed];
    }

    foreach (glob($pagesDir . '/*.php') as $file) {
        $base = basename($file, '.php');
        // Keep the framework's example pages; remove everything else.
        if (strpos($base, 'example-') === 0) {
            continue;
        }
        if (@unlink($file)) {
            $deleted[] = basename($file);
        } else {
            $failed[] = basename($file);
        }
    }

    // Reset the home-page marker so the site falls back to the default landing.
    $homeMarker = $pagesDir . '/../partials/home.txt';
    if (is_file($homeMarker)) {
        @unlink($homeMarker);
    }

    return ['deleted' => $deleted, 'failed' => $failed];
}

/**
 * Recursively delete a directory. Returns false if any entry could not be
 * removed (e.g. owned by another user).
 */
function factoryResetRrmdir(string $dir): bool
{
    if (!is_dir($dir)) {
        return @unlink($dir);
    }
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            if (!factoryResetRrmdir($path)) {
                return false;
            }
        } elseif (!@unlink($path)) {
            return false;
        }
    }
    return @rmdir($dir);
}

/**
 * Record the factory reset in the (freshly emptied) activity_logs table.
 */
function factoryResetLog(PDO $link, int $adminId, int $droppedCount, int $foldersDeleted, string $backupFile = ''): void
{
    try {
        $stmt = $link->prepare(
            "INSERT INTO activity_logs
                (action_log, entity_log, entity_id_log, description_log, admin_id_log, ip_address_log, user_agent_log, date_created_log)
             VALUES ('factory_reset', 'system', NULL, ?, ?, ?, ?, ?)"
        );
        $desc = "Factory reset ejecutado. Tablas eliminadas: $droppedCount. Carpetas custom eliminadas: $foldersDeleted."
            . ($backupFile !== '' ? " Respaldo: $backupFile" : '');
        $stmt->execute([
            $desc,
            $adminId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        // Logging must never break the reset.
    }
}
