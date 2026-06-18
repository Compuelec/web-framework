<?php
/**
 * make-page.php — create a public page from the CLI (for humans or AI agents).
 *
 * Generates the SAME kind of page the visual builder produces: it embeds the
 * page config as base64 ($wpbConfig), so the page shows up in the CMS under
 * "Páginas Web" and can be edited there afterwards.
 *
 * Usage:
 *     php tools/make-page.php path/to/config.json
 *     cat config.json | php tools/make-page.php
 *     php tools/make-page.php '{"name":"contacto","heading":"Contacto","template":"<h1>Hola</h1>"}'
 *
 * Config keys (all optional except "name"):
 *     name        string  file name (a-z0-9_-); the page is web/pages/<name>.php
 *     heading     string  page title shown in the browser tab + builder list
 *     template    string  your HTML (supports the builder tags — see the docs)
 *     table       string  data table to bind (enables {{field}}, {{#cada}}, forms)
 *     customCss   string  CSS injected into the page
 *     customJs    string  JavaScript injected into the page
 *     metaTitle, metaDesc, ogTitle, ogType, ogDesc, ogImage  string  SEO / Open Graph
 *     private     bool    require login (default false)
 *     accessRoles array   allowed rol_admin values (when private)
 *     accessUsers array   allowed admin ids (when private)
 *     isHome      bool    also set this page as the site home (web/index.php)
 *
 * Exit code 0 on success, 1 on error. Prints a JSON result.
 */

$root = dirname(__DIR__);
require_once $root . '/tools/page-builder.php';
require_once $root . '/tools/web-config.php';
require_once $root . '/tools/web-partials.php';
require_once $root . '/api/models/connection.php';

function fail($msg) {
    fwrite(STDERR, json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

// ---- read the config (arg = file path, arg = inline JSON, or stdin) ----------
$arg = $argv[1] ?? '';
if ($arg !== '' && is_file($arg)) {
    $raw = file_get_contents($arg);
} elseif ($arg !== '') {
    $raw = $arg; // inline JSON
} else {
    $raw = stream_get_contents(STDIN);
}
if (trim((string)$raw) === '') {
    fail('No config provided. Pass a JSON file path, inline JSON, or pipe JSON via stdin.');
}
$config = json_decode($raw, true);
if (!is_array($config)) {
    fail('Invalid JSON config: ' . json_last_error_msg());
}

// ---- validate the file name --------------------------------------------------
$name = (string)($config['name'] ?? $config['fileName'] ?? '');
if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
    fail('Invalid "name": use only lowercase letters, digits, hyphens or underscores.');
}
$config['fileName'] = $name;

// ---- look up the table's real PK + columns (best effort) ---------------------
$config['columns'] = $config['columns'] ?? [];
$table = (string)($config['table'] ?? '');
if ($table !== '' && pb_isIdentifier($table)) {
    try {
        $link = Connection::connect();
        if ($link !== null) {
            $db   = Connection::infoDatabase()['database'];
            $stmt = $link->prepare(
                "SELECT COLUMN_NAME AS name, COLUMN_KEY AS k FROM information_schema.columns
                 WHERE table_schema = :db AND table_name = :t ORDER BY ORDINAL_POSITION"
            );
            $stmt->execute([':db' => $db, ':t' => $table]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $config['columns'][] = $c['name'];
                if ($c['k'] === 'PRI') { $config['idColumn'] = $c['name']; }
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "! Warning: could not read columns for table '{$table}' ({$e->getMessage()}). Continuing.\n");
    }
}

// ---- build + write the page --------------------------------------------------
try {
    $cfg    = pb_normalizeConfig($config);
    $source = buildConfigurablePage($cfg);
} catch (Throwable $e) {
    fail('Could not build the page: ' . $e->getMessage());
}

wpb_ensureWritableDirs();
wpb_ensureWebConfig();   // make sure the page can reach the API
wpb_ensurePartials();    // make sure the shared header/footer exist

$pagesDir = $root . '/web/pages';
if (!is_dir($pagesDir)) { @mkdir($pagesDir, 0775, true); }
$path = $pagesDir . '/' . $cfg['fileName'] . '.php';

if (@file_put_contents($path, $source) === false) {
    fail('Could not write ' . $path . ' (check write permissions on web/pages).');
}
@chmod($path, 0664); // group-writable so the CMS (web server) can edit it too

// ---- optional: set as the site home page ------------------------------------
$homeSet = false;
if (!empty($config['isHome'])) {
    $dir = wpb_partialsDir();
    if ($dir !== false) {
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $homeSet = @file_put_contents($dir . '/home.txt', $cfg['fileName']) !== false;
    }
}

echo json_encode([
    'success' => true,
    'file'    => 'web/pages/' . $cfg['fileName'] . '.php',
    'slug'    => $cfg['fileName'],
    'isHome'  => $homeSet,
    'note'    => 'The page now appears in the CMS under "Páginas Web" and can be edited there.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);
