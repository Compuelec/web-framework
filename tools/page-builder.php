<?php
/**
 * Template-based public page builder.
 *
 * Generates a self-contained, EDITABLE public page from a configuration object.
 * The user writes their own HTML template with simple tags that bind to a
 * table's data:
 *
 *   {{field}}             a column value (escaped)
 *   {{#cada}}...{{/cada}} repeat the inner HTML once per record
 *
 * Tags outside a repeat block use the "single" record (the ?id= one, or the
 * first row). The full config is embedded in the generated file as base64 so
 * the page renders itself and the visual builder can read it back to edit it.
 *
 * Column VALUES are always escaped with htmlspecialchars; the surrounding HTML
 * (template, CSS, JS) is the admin's own content and is emitted verbatim.
 */

function pb_isIdentifier($name) {
    return is_string($name) && preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
}

/**
 * Framework / bundled-plugin tables. These are infrastructure, not user data,
 * so the visual page builder hides them and only offers custom (user-created)
 * tables. When a new plugin ships its own table, add it here.
 */
function pb_systemTables() {
    static $tables = null;
    if ($tables === null) {
        $tables = [
            // core framework
            'admins', 'roles', 'pages', 'modules', 'columns', 'folders', 'files',
            'cms_settings', 'activity_logs',
            // bundled plugins
            'dashboard_widgets', 'page_seo', 'payku_orders', 'workflows',
        ];
    }
    return $tables;
}

/**
 * Whether a table is a framework/system table (case-insensitive).
 */
function pb_isSystemTable($table) {
    return in_array(strtolower((string)$table), pb_systemTables(), true);
}

/* ------------------------------------------------------------------ *
 * Template renderer (used by the CMS live preview). The generated page
 * embeds an equivalent inline copy so it stays self-contained — keep the
 * two in sync (covered by tests/page_builder_test.php).
 * ------------------------------------------------------------------ */

/**
 * Replace {{field}} tags in a fragment with one row's escaped values.
 */
function pb_replaceFields($html, array $row) {
    // Expand image-gallery blocks first: {{#imagenes campo}}<img src="{{url}}">{{/imagenes}}
    // Repeats the inner HTML once per URL in the field's JSON array.
    $html = preg_replace_callback('/\{\{#imagenes\s+([a-zA-Z0-9_]+)\s*\}\}(.*?)\{\{\/imagenes\}\}/s', function ($m) use ($row) {
        $urls = pb_imageUrls(array_key_exists($m[1], $row) ? $row[$m[1]] : '');
        $out  = '';
        foreach ($urls as $url) {
            $out .= str_replace('{{url}}', htmlspecialchars($url, ENT_QUOTES), $m[2]);
        }
        return $out;
    }, $html);

    // Then simple {{field}} tags.
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($row) {
        $val = array_key_exists($m[1], $row) ? $row[$m[1]] : '';
        return htmlspecialchars(is_scalar($val) ? (string) $val : '', ENT_QUOTES);
    }, $html);
}

/**
 * Decode a multi-image field value (a JSON array of URLs, possibly
 * URL-encoded) into a flat list of string URLs.
 */
function pb_imageUrls($raw) {
    if (is_array($raw)) {
        $decoded = $raw;
    } else {
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(urldecode((string) $raw), true);
        }
    }
    if (!is_array($decoded)) {
        return [];
    }
    $urls = [];
    foreach ($decoded as $u) {
        if (is_scalar($u) && $u !== '') {
            $urls[] = (string) $u;
        }
    }
    return $urls;
}

/**
 * Render a template against the record set; tags outside a repeat block use
 * the $single record.
 */
function pb_renderTemplate($template, array $records, array $single) {
    // Split on the repeat block, capturing the inner HTML. Even-index parts are
    // outside any block (rendered once with $single); odd-index parts are the
    // captured inner blocks (rendered once per record). Each segment is filled
    // exactly once, so field values that happen to contain {{...}} are never
    // re-evaluated.
    $parts = preg_split('/\{\{#cada\}\}(.*?)\{\{\/cada\}\}/s', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            foreach ($records as $rec) {
                $out .= pb_replaceFields($part, (array) $rec);
            }
        } else {
            $out .= pb_replaceFields($part, $single);
        }
    }
    return $out;
}

/**
 * Pick the "single" record: the one matching $id, else the first row.
 */
function pb_pickSingle(array $records, $idColumn, $id) {
    if ($id !== null && $id !== '') {
        foreach ($records as $rec) {
            $row = (array) $rec;
            if (isset($row[$idColumn]) && (string) $row[$idColumn] === (string) $id) {
                return $row;
            }
        }
    }
    return isset($records[0]) ? (array) $records[0] : [];
}

function pb_deriveSuffix($table) {
    if (substr($table, -3) === 'ies') {
        return substr($table, 0, -3) . 'y';
    }
    if (substr($table, -1) === 's' && substr($table, -2) !== 'ss') {
        return substr($table, 0, -1);
    }
    return $table;
}

/**
 * Validate and fill defaults for a builder config. Throws on bad input.
 *
 * @return array
 */
function pb_normalizeConfig(array $raw) {
    $table = (string)($raw['table'] ?? '');
    if (!pb_isIdentifier($table)) {
        throw new InvalidArgumentException('Tabla inválida.');
    }

    $suffix      = pb_isIdentifier($raw['suffix'] ?? '')      ? $raw['suffix']      : pb_deriveSuffix($table);
    $idColumn    = pb_isIdentifier($raw['idColumn'] ?? '')    ? $raw['idColumn']    : ('id_' . $suffix);
    $titleColumn = pb_isIdentifier($raw['titleColumn'] ?? '') ? $raw['titleColumn'] : ('name_' . $suffix);

    $fileName = trim((string)($raw['fileName'] ?? ''));
    if ($fileName === '') {
        $fileName = $table;
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fileName)) {
        throw new InvalidArgumentException('Nombre de archivo inválido.');
    }

    return [
        'table'       => $table,
        'suffix'      => $suffix,
        'idColumn'    => $idColumn,
        'titleColumn' => $titleColumn,
        'fileName'    => $fileName,
        'heading'     => (string)($raw['heading'] ?? ''),
        // The user's HTML template with {{field}} / {{#cada}} tags.
        'template'    => (string)($raw['template'] ?? ''),
        'customCss'   => (string)($raw['customCss'] ?? ''),
        'customJs'    => (string)($raw['customJs'] ?? ''),
    ];
}

/**
 * Extract the embedded config from a generated page's source (for editing).
 *
 * @return array|null
 */
function pb_extractConfig($source) {
    if (preg_match("/wpbConfig\\s*=\\s*'([A-Za-z0-9+\\/=]+)'/", (string)$source, $m)) {
        $decoded = json_decode(base64_decode($m[1]), true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return null;
}

/**
 * Build the PHP source of a builder page. The page loads its table's records,
 * renders the embedded HTML template against them, and wraps the result in the
 * site layout. It embeds its own config so the builder can edit it later.
 */
function buildConfigurablePage(array $config) {
    $cfg = pb_normalizeConfig($config);
    $b64 = base64_encode(json_encode($cfg));

    return <<<PHP
<?php
/**
 * Generated by the Web Page Builder — edit it from the CMS ("Páginas Web").
 * Do not remove the \$wpbConfig line; the builder reads it to let you edit this page.
 */

\$wpbConfig = '{$b64}';
\$cfg = json_decode(base64_decode(\$wpbConfig), true) ?: [];

\$configPath = __DIR__ . '/../config.php';
\$siteCfg = file_exists(\$configPath)
    ? require \$configPath
    : (file_exists(__DIR__ . '/../config.example.php') ? require __DIR__ . '/../config.example.php' : []);

if (is_array(\$siteCfg) && !empty(\$siteCfg['timezone'])) {
    date_default_timezone_set(\$siteCfg['timezone']);
}

require_once __DIR__ . '/../controllers/api.controller.php';

\$siteName = (is_array(\$siteCfg) && isset(\$siteCfg['site']['name'])) ? \$siteCfg['site']['name'] : 'My Website';

\$table    = \$cfg['table'] ?? '';
\$idColumn = \$cfg['idColumn'] ?? 'id';
\$template = \$cfg['template'] ?? '';

\$records = [];
\$error   = null;
try {
    \$response = ApiController::getAll(\$table, '*', \$idColumn, 'DESC', 0, 200);
    if (\$response->status == 200) {
        \$records = \$response->results;
    } elseif (\$response->status != 404) {
        \$error = 'Could not load data.';
    }
} catch (Throwable \$e) {
    \$error = 'Could not load data.';
}

// Template renderer (mirrors tools/page-builder.php).
if (!function_exists('wpb_fields')) {
    function wpb_image_urls(\$raw) {
        \$decoded = is_array(\$raw) ? \$raw : json_decode((string) \$raw, true);
        if (!is_array(\$decoded)) { \$decoded = json_decode(urldecode((string) \$raw), true); }
        if (!is_array(\$decoded)) { return []; }
        \$urls = [];
        foreach (\$decoded as \$u) { if (is_scalar(\$u) && \$u !== '') { \$urls[] = (string) \$u; } }
        return \$urls;
    }
    function wpb_fields(\$html, array \$row) {
        // Image-gallery blocks: {{#imagenes campo}}<img src="{{url}}">{{/imagenes}}
        \$html = preg_replace_callback('/\\{\\{#imagenes\\s+([a-zA-Z0-9_]+)\\s*\\}\\}(.*?)\\{\\{\\/imagenes\\}\\}/s', function (\$m) use (\$row) {
            \$urls = wpb_image_urls(array_key_exists(\$m[1], \$row) ? \$row[\$m[1]] : '');
            \$out = '';
            foreach (\$urls as \$url) { \$out .= str_replace('{{url}}', htmlspecialchars(\$url, ENT_QUOTES), \$m[2]); }
            return \$out;
        }, \$html);
        return preg_replace_callback('/\\{\\{\\s*([a-zA-Z0-9_]+)\\s*\\}\\}/', function (\$m) use (\$row) {
            \$val = array_key_exists(\$m[1], \$row) ? \$row[\$m[1]] : '';
            return htmlspecialchars(is_scalar(\$val) ? (string) \$val : '', ENT_QUOTES);
        }, \$html);
    }
    function wpb_render(\$template, array \$records, array \$single) {
        // Split on the repeat block (capturing inner HTML) so each segment is
        // filled exactly once — no re-evaluation of {{...}} found in data.
        \$parts = preg_split('/\\{\\{#cada\\}\\}(.*?)\\{\\{\\/cada\\}\\}/s', \$template, -1, PREG_SPLIT_DELIM_CAPTURE);
        \$out = '';
        foreach (\$parts as \$i => \$part) {
            if (\$i % 2 === 1) {
                foreach (\$records as \$rec) { \$out .= wpb_fields(\$part, (array) \$rec); }
            } else {
                \$out .= wpb_fields(\$part, \$single);
            }
        }
        return \$out;
    }
}

\$recordId = isset(\$_GET['id']) ? \$_GET['id'] : null;
\$single   = [];
if (\$recordId !== null && \$recordId !== '') {
    foreach (\$records as \$rec) {
        \$row = (array) \$rec;
        if (isset(\$row[\$idColumn]) && (string) \$row[\$idColumn] === (string) \$recordId) { \$single = \$row; break; }
    }
}
if (!\$single && isset(\$records[0])) { \$single = (array) \$records[0]; }

\$pageTitle       = (!empty(\$cfg['heading']) ? \$cfg['heading'] : \$siteName) . ' - ' . \$siteName;
\$pageDescription = '';

\$body = \$error ? '' : wpb_render(\$template, \$records, \$single);

ob_start();
?>
<style>
<?php echo \$cfg['customCss'] ?? ''; ?>
</style>

<div class="container my-5" id="wpb-page">
    <?php if (!empty(\$cfg['heading'])): ?>
        <h1 class="mb-4"><?php echo htmlspecialchars(\$cfg['heading'], ENT_QUOTES); ?></h1>
    <?php endif; ?>

    <?php if (\$error): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars(\$error, ENT_QUOTES); ?></div>
    <?php else: ?>
        <?php echo \$body; ?>
    <?php endif; ?>
</div>

<script>
<?php echo \$cfg['customJs'] ?? ''; ?>
</script>
<?php
\$pageContent = ob_get_clean();
include __DIR__ . '/../views/template.php';

PHP;
}
