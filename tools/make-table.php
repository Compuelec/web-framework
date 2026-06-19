<?php
/**
 * make-table.php — create a data section (table + admin CRUD) from the CLI.
 *
 * Builds the same thing the CMS "Modular" section builder does: a MySQL table,
 * plus the pages/modules/columns rows so the section shows up in the admin menu
 * with automatic create/read/update/delete. After this, use tools/make-page.php
 * to build the public page that lists the data.
 *
 * Usage:
 *     php tools/make-table.php config.json
 *     php tools/make-table.php '{"name":"productos","title":"Productos","icon":"bi bi-box","fields":[{"name":"nombre","type":"text"},{"name":"precio","type":"money"},{"name":"stock","type":"int"},{"name":"imagen","type":"image"}]}'
 *
 * Config:
 *     name    string  table name (a-z0-9_); also the section URL
 *     title   string  section title shown in the admin menu (default: name)
 *     icon    string  Bootstrap icon class, e.g. "bi bi-box" (default: bi bi-table)
 *     suffix  string  column suffix (default: derived singular of name)
 *     fields  array   [{ name, type, alias?, visible? }]
 *                       type: text|textarea|int|double|money|boolean|date|email|
 *                             link|color|select|image|multiimage|file|object|json
 *
 * Prints a JSON result including the final column names (use them in the page
 * template). Exit 0 on success, 1 on error.
 */

$root = dirname(__DIR__);
require_once $root . '/tools/page-builder.php';   // pb_deriveSuffix, pb_isIdentifier
require_once $root . '/api/models/connection.php';

function mt_fail($msg) {
    fwrite(STDERR, json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

// Map a field type to its MySQL definition (mirrors TemplateController::typeColumn).
function mt_sqlType($t) {
    switch ($t) {
        case 'object':                 return "TEXT NULL DEFAULT '{}'";
        case 'json': case 'multiimage':return "TEXT NULL DEFAULT '[]'";
        case 'int': case 'relations': case 'order': return "INT NULL DEFAULT '0'";
        case 'boolean':                return "INT NULL DEFAULT '1'";
        case 'double': case 'money':   return "DOUBLE NULL DEFAULT '0'";
        case 'date':                   return "DATE NULL DEFAULT NULL";
        case 'time':                   return "TIME NULL DEFAULT NULL";
        default:                       return "TEXT NULL DEFAULT NULL"; // text, email, image, select, …
    }
}

// ---- read config -------------------------------------------------------------
$arg = $argv[1] ?? '';
$raw = ($arg !== '' && is_file($arg)) ? file_get_contents($arg) : ($arg !== '' ? $arg : stream_get_contents(STDIN));
$config = json_decode((string)$raw, true);
if (!is_array($config)) { mt_fail('Invalid JSON config: ' . json_last_error_msg()); }

$name = strtolower(trim((string)($config['name'] ?? '')));
if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
    mt_fail('Invalid "name": start with a letter, use only lowercase letters, digits or underscores.');
}
$title  = (string)($config['title'] ?? ucfirst($name));
$icon   = (string)($config['icon'] ?? 'bi bi-table');
$suffix = (string)($config['suffix'] ?? '');
$suffix = pb_isIdentifier($suffix) ? $suffix : pb_deriveSuffix($name);
$fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
if (!$fields) { mt_fail('Provide at least one field in "fields".'); }

// ---- normalize the fields ----------------------------------------------------
$cols = [];
foreach ($fields as $f) {
    $fname = strtolower(trim((string)($f['name'] ?? '')));
    if ($fname === '') { mt_fail('Each field needs a "name".'); }
    // Follow the framework convention <name>_<suffix> (unless already suffixed).
    if (substr($fname, -strlen('_' . $suffix)) !== '_' . $suffix) { $fname .= '_' . $suffix; }
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $fname)) { mt_fail('Invalid field name: ' . $fname); }
    $type = (string)($f['type'] ?? 'text');
    $cols[] = [
        'name'    => $fname,
        'alias'   => (string)($f['alias'] ?? str_replace('_' . $suffix, '', $fname)),
        'type'    => $type,
        'visible' => array_key_exists('visible', $f) ? (int)!empty($f['visible']) : 1,
        'matrix'  => (string)($f['matrix'] ?? ''),
    ];
}

$link = Connection::connect();
if ($link === null) { mt_fail('Could not connect to the database.'); }

// ---- guard: table / section must not already exist ---------------------------
$q = $link->query("SHOW TABLES LIKE " . $link->quote($name));
if ($q && $q->fetch()) { mt_fail("Table '{$name}' already exists. Choose another name."); }
$dupPage = $link->prepare("SELECT 1 FROM pages WHERE url_page = ? LIMIT 1");
$dupPage->execute([$name]);
if ($dupPage->fetch()) { mt_fail("A section with url '{$name}' already exists."); }

// ---- 1) create the table -----------------------------------------------------
$createSql = "CREATE TABLE `{$name}` (
    id_{$suffix} INT NOT NULL AUTO_INCREMENT,
    date_created_{$suffix} DATE NULL DEFAULT NULL,
    date_updated_{$suffix} TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_{$suffix}))";
if ($link->exec($createSql) === false) { mt_fail('Could not create the table.'); }

$after = "id_{$suffix}";
foreach ($cols as $c) {
    $link->exec("ALTER TABLE `{$name}` ADD `{$c['name']}` " . mt_sqlType($c['type']) . " AFTER `{$after}`");
    $after = $c['name'];
}

// ---- 2) register the section (pages + modules + columns) ---------------------
$today = date('Y-m-d');
$qOrder = $link->query("SELECT COALESCE(MAX(order_page),0)+1 FROM pages");
$order  = $qOrder ? (int)$qOrder->fetchColumn() : 1;

$ins = $link->prepare(
    "INSERT INTO pages (title_page, url_page, icon_page, type_page, parent_page, order_page, date_created_page)
     VALUES (?,?,?,?,0,?,?)"
);
$ins->execute([$title, $name, $icon, 'modules', $order, $today]);
$pageId = (int)$link->lastInsertId();

// breadcrumbs module (header) + tables module (the CRUD grid)
$insMod = $link->prepare(
    "INSERT INTO modules (id_page_module, type_module, title_module, suffix_module, width_module, editable_module, date_created_module)
     VALUES (?,?,?,?,?,?,?)"
);
$insMod->execute([$pageId, 'breadcrumbs', $title, null, 100, 1, $today]);
$insMod->execute([$pageId, 'tables', $name, $suffix, 100, 0, $today]);
$tablesModuleId = (int)$link->lastInsertId();

$insCol = $link->prepare(
    "INSERT INTO columns (id_module_column, title_column, alias_column, type_column, matrix_column, conditions_column, visible_column, date_created_column)
     VALUES (?,?,?,?,?,?,?,?)"
);
foreach ($cols as $c) {
    $insCol->execute([$tablesModuleId, $c['name'], $c['alias'], $c['type'], $c['matrix'], '', $c['visible'], $today]);
}

// ---- done --------------------------------------------------------------------
echo json_encode([
    'success'   => true,
    'table'     => $name,
    'idColumn'  => "id_{$suffix}",
    'suffix'    => $suffix,
    'columns'   => array_map(function ($c) { return $c['name']; }, $cols),
    'sectionUrl'=> $name,
    'note'      => 'The section now appears in the CMS menu (create/read/update/delete). Use tools/make-page.php with "table":"' . $name . '" to build the public page.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);
