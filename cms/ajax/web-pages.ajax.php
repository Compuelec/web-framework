<?php
/**
 * Web Pages builder — AJAX endpoint.
 *
 * Generates public frontend pages (web/pages/*.php) from the browser, reusing
 * the tested generation engine in tools/make-web-page.php. This is the visual,
 * non-technical counterpart of the make-web-page.php CLI.
 *
 * Actions (POST):
 *   generate   table, title, name, detail -> writes the page files (or returns
 *              the generated source if web/pages/ is not writable)
 */

define('SESSION_INIT_INCLUDED', true);
require_once __DIR__ . '/session-init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// CSRF protection for state-changing requests.
if (!SessionController::validateCsrfRequest()) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

require_once __DIR__ . '/../../tools/make-web-page.php';

$action = $_POST['action'] ?? '';

if ($action !== 'generate') {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$table      = trim((string)($_POST['table'] ?? ''));
$titleCol   = trim((string)($_POST['title'] ?? ''));
$fileName   = trim((string)($_POST['name']  ?? ''));
$withDetail = !empty($_POST['detail']);

// Build the generator options (reuses the CLI's validation + builders).
$flags = [];
if ($titleCol !== '') { $flags['title'] = $titleCol; }
if ($fileName !== '') { $flags['name']  = $fileName; }

try {
    $opts = mwp_resolveOptions($table, $flags);
} catch (Throwable $e) {
    Logger::warning('Web page generation rejected', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$targets = [['file' => $opts['fileName'], 'source' => buildWebPageSource($opts)]];
if ($withDetail) {
    $targets[] = ['file' => $opts['detailFile'], 'source' => buildDetailPageSource($opts)];
}

$pagesDir = realpath(__DIR__ . '/../../web/pages');
if ($pagesDir === false) {
    echo json_encode(['success' => false, 'error' => 'web/pages directory not found']);
    exit;
}

// Write the files when the directory is writable; otherwise hand the source
// back to the UI so the user can download it.
$created  = [];
$sources  = [];
$writable = is_writable($pagesDir);

foreach ($targets as $tgt) {
    $sources[$tgt['file'] . '.php'] = $tgt['source'];
    if (!$writable) {
        continue;
    }
    $path = $pagesDir . DIRECTORY_SEPARATOR . $tgt['file'] . '.php';
    if (@file_put_contents($path, $tgt['source']) !== false) {
        $created[] = $tgt['file'] . '.php';
    }
}

if ($writable && count($created) === count($targets)) {
    echo json_encode([
        'success'  => true,
        'written'  => true,
        'files'    => $created,
        'urlPath'  => 'web/pages/' . $opts['fileName'] . '.php',
    ]);
    exit;
}

// Not writable (or a write failed): return the source for download.
Logger::warning('web/pages not writable; returning generated source', ['dir' => $pagesDir]);
echo json_encode([
    'success' => true,
    'written' => false,
    'reason'  => 'El directorio web/pages no es escribible por el servidor web. Descarga los archivos y colócalos en web/pages/.',
    'sources' => $sources,
]);
