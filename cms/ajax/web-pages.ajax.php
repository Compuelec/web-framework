<?php
/**
 * Web Pages builder — AJAX endpoint (visual, configurable).
 *
 * Generates and edits public frontend pages from the browser, reusing the
 * configurable engine in tools/page-builder.php. Generated pages embed their
 * own config so they can be re-opened and edited.
 *
 * Actions (POST):
 *   columns   list the columns of a table (for the form)
 *   generate  build/overwrite the page(s) from the submitted config
 *   list      list existing builder-generated pages
 *   load      return the embedded config of an existing page (for editing)
 */

define('SESSION_INIT_INCLUDED', true);
require_once __DIR__ . '/session-init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if (!SessionController::validateCsrfRequest()) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}
$role = $_SESSION['admin']->rol_admin ?? '';
if (!in_array($role, ['superadmin', 'admin'], true)) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../tools/page-builder.php';
require_once __DIR__ . '/../../api/models/connection.php';

$pagesDir = realpath(__DIR__ . '/../../web/pages');
$action   = $_POST['action'] ?? '';

/* ============================ tables ============================ */
// List user/custom data tables only (framework + plugin tables are hidden).
if ($action === 'tables') {
    $link = Connection::connect();
    if ($link === null) {
        echo json_encode(['success' => false, 'error' => 'DB connection failed']);
        exit;
    }
    try {
        $db = $link->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $link->prepare("SELECT TABLE_NAME AS t FROM information_schema.tables WHERE table_schema = :db AND table_type = 'BASE TABLE' ORDER BY TABLE_NAME");
        $stmt->execute([':db' => $db]);
        $all    = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $custom = array_values(array_filter($all, function ($t) { return !pb_isSystemTable($t); }));
        echo json_encode(['success' => true, 'tables' => $custom]);
    } catch (Throwable $e) {
        Logger::error('web-pages tables failed', ['error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => 'No se pudieron leer las tablas']);
    }
    exit;
}

/* ============================ columns ============================ */
if ($action === 'columns') {
    $table = (string)($_POST['table'] ?? '');
    if (!pb_isIdentifier($table)) {
        echo json_encode(['success' => false, 'error' => 'Tabla inválida']);
        exit;
    }
    $link = Connection::connect();
    if ($link === null) {
        echo json_encode(['success' => false, 'error' => 'DB connection failed']);
        exit;
    }
    try {
        $db = $link->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $link->prepare("SELECT COLUMN_NAME AS c FROM information_schema.columns WHERE table_schema = :db AND table_name = :t ORDER BY ORDINAL_POSITION");
        $stmt->execute([':db' => $db, ':t' => $table]);
        echo json_encode(['success' => true, 'columns' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
    } catch (Throwable $e) {
        Logger::error('web-pages columns failed', ['error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => 'No se pudieron leer las columnas']);
    }
    exit;
}

/* ============================ list ============================ */
if ($action === 'list') {
    $pages = [];
    if ($pagesDir !== false) {
        foreach (glob($pagesDir . '/*.php') as $file) {
            $base = basename($file, '.php');
            if (substr($base, -7) === '-detail') {
                continue;
            }
            $cfg = pb_extractConfig(@file_get_contents($file));
            if ($cfg) {
                $pages[] = [
                    'file'    => $base,
                    'table'   => $cfg['table'] ?? '',
                    'heading' => $cfg['heading'] ?? $base,
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'pages' => $pages]);
    exit;
}

/* ============================ load ============================ */
if ($action === 'load') {
    $file = (string)($_POST['file'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $file) || $pagesDir === false) {
        echo json_encode(['success' => false, 'error' => 'Archivo inválido']);
        exit;
    }
    $path = $pagesDir . DIRECTORY_SEPARATOR . $file . '.php';
    $cfg  = file_exists($path) ? pb_extractConfig(@file_get_contents($path)) : null;
    if (!$cfg) {
        echo json_encode(['success' => false, 'error' => 'No se pudo leer la configuración de la página']);
        exit;
    }
    echo json_encode(['success' => true, 'config' => $cfg]);
    exit;
}

/* ============================ generate ============================ */
if ($action !== 'generate') {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$config = [
    'table'       => $_POST['table']   ?? '',
    'fileName'    => $_POST['name']    ?? '',
    'titleColumn' => $_POST['title']   ?? '',
    'columns'     => (array)($_POST['columns'] ?? []),
    'heading'     => $_POST['heading'] ?? '',
    'intro'       => $_POST['intro']   ?? '',
    'layout'      => $_POST['layout']  ?? 'cards',
    'accent'      => $_POST['accent']  ?? '#0d6efd',
    'perRow'      => $_POST['perRow']  ?? 3,
    'withDetail'  => !empty($_POST['detail']),
    'customCss'   => $_POST['customCss']  ?? '',
    'customHtml'  => $_POST['customHtml'] ?? '',
    'customJs'    => $_POST['customJs']   ?? '',
];

try {
    $cfg = pb_normalizeConfig($config);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$targets = [['file' => $cfg['fileName'], 'source' => buildConfigurablePage($cfg)]];
if ($cfg['withDetail']) {
    $targets[] = ['file' => $cfg['detailFile'], 'source' => buildConfigurableDetail($cfg)];
}

if ($pagesDir === false) {
    echo json_encode(['success' => false, 'error' => 'web/pages directory not found']);
    exit;
}

$sources = [];
foreach ($targets as $tgt) {
    $sources[$tgt['file'] . '.php'] = $tgt['source'];
}

if (is_writable($pagesDir)) {
    $createdPaths = [];
    $ok = true;
    foreach ($targets as $tgt) {
        $path = $pagesDir . DIRECTORY_SEPARATOR . $tgt['file'] . '.php';
        if (@file_put_contents($path, $tgt['source']) === false) {
            $ok = false;
            break;
        }
        $createdPaths[] = $path;
    }
    if ($ok) {
        echo json_encode([
            'success' => true,
            'written' => true,
            'files'   => array_map('basename', $createdPaths),
            'urlPath' => 'web/pages/' . $cfg['fileName'] . '.php',
        ]);
        exit;
    }
    foreach ($createdPaths as $p) {
        @unlink($p);
    }
    Logger::error('Web page generation failed mid-write; rolled back', ['dir' => $pagesDir]);
    echo json_encode(['success' => false, 'error' => 'No se pudieron escribir todos los archivos.']);
    exit;
}

Logger::warning('web/pages not writable; returning generated source', ['dir' => $pagesDir]);
echo json_encode([
    'success' => true,
    'written' => false,
    'reason'  => 'El directorio web/pages no es escribible por el servidor web. Usa "Estado del Sistema" para repararlo, o descarga los archivos.',
    'sources' => $sources,
]);
