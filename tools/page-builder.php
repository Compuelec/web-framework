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
/**
 * Localization (currency + date formats) for public-page formatting tags.
 * Reads $GLOBALS['__wpb_loc'] when the host set it (from the site/CMS config),
 * else sensible defaults matching the CMS listing defaults.
 */
function pb_loc() {
    $loc = (isset($GLOBALS['__wpb_loc']) && is_array($GLOBALS['__wpb_loc'])) ? $GLOBALS['__wpb_loc'] : [];
    $cur = (isset($loc['currency']) && is_array($loc['currency'])) ? $loc['currency'] : [];
    return [
        'currency'        => array_merge(['symbol' => '$', 'decimals' => 2, 'thousands_sep' => ',', 'decimal_sep' => '.'], $cur),
        'date_format'     => $loc['date_format']     ?? 'd-m-Y',
        'datetime_format' => $loc['datetime_format'] ?? 'd-m-Y H:i',
        'time_format'     => $loc['time_format']     ?? 'H:i',
    ];
}

function pb_replaceFields($html, array $row, array $formRow = []) {
    // Form blocks: {{#form}} ...inputs... {{/form}} → a submit form. Inputs are
    // prefilled only in edit mode ($formRow); create forms appear empty.
    $html = preg_replace_callback('/\{\{#form\}\}(.*?)\{\{\/form\}\}/s', function ($m) use ($formRow) {
        return '<form method="post" enctype="multipart/form-data" class="wpb-form">'
             . pb_formFields($m[1], $formRow)
             . '</form>';
    }, $html);

    // Conditional blocks: {{#si campo}}…{{/si}} keeps the inner HTML when the field
    // is truthy; {{#no campo}}…{{/no}} keeps it when falsy (empty / 0). Inner tags
    // are processed by the passes below.
    $html = preg_replace_callback('/\{\{#si\s+([a-zA-Z0-9_]+)\s*\}\}(.*?)\{\{\/si\}\}/s', function ($m) use ($row) {
        $v = array_key_exists($m[1], $row) ? $row[$m[1]] : null;
        return (!empty($v) && (string)$v !== '0') ? $m[2] : '';
    }, $html);
    $html = preg_replace_callback('/\{\{#no\s+([a-zA-Z0-9_]+)\s*\}\}(.*?)\{\{\/no\}\}/s', function ($m) use ($row) {
        $v = array_key_exists($m[1], $row) ? $row[$m[1]] : null;
        return (empty($v) || (string)$v === '0') ? $m[2] : '';
    }, $html);

    // Expand image-gallery blocks first: {{#imagenes campo}}<img src="{{url}}">{{/imagenes}}
    // Repeats the inner HTML once per URL in the field's JSON array.
    $html = preg_replace_callback('/\{\{#imagenes\s+([a-zA-Z0-9_]+)\s*\}\}(.*?)\{\{\/imagenes\}\}/s', function ($m) use ($row) {
        $urls = pb_imageUrls(array_key_exists($m[1], $row) ? $row[$m[1]] : '');
        $out  = '';
        foreach ($urls as $url) {
            $escaped = htmlspecialchars($url, ENT_QUOTES);
            $out .= preg_replace_callback('/\{\{\s*url\s*\}\}/', function () use ($escaped) {
                return $escaped;
            }, $m[2]);
        }
        return $out;
    }, $html);

    // Single-image tags: {{img campo}} → the column's image URL. Single-image
    // columns are stored URL-encoded (like everywhere else in the CMS), so the
    // value is urldecoded before use; a plain {{campo}} would emit the raw
    // percent-encoded string and the browser could not load it.
    $html = preg_replace_callback('/\{\{\s*img\s+([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($row) {
        $val = array_key_exists($m[1], $row) && is_scalar($row[$m[1]]) ? (string) $row[$m[1]] : '';
        return htmlspecialchars(urldecode($val), ENT_QUOTES);
    }, $html);

    // {{money campo}} → format the column as currency (localization config).
    $c = pb_loc()['currency'];
    $html = preg_replace_callback('/\{\{\s*money\s+([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($row, $c) {
        $val = array_key_exists($m[1], $row) ? $row[$m[1]] : null;
        if ($val === null || $val === '') { return ''; }
        $v = is_numeric($val) ? (float) $val : 0;
        return htmlspecialchars($c['symbol'] . number_format($v, (int) $c['decimals'], $c['decimal_sep'], $c['thousands_sep']), ENT_QUOTES);
    }, $html);

    // {{fecha campo}} / {{date campo}} → friendly date/datetime/time, raw if unparseable.
    $loc = pb_loc();
    $html = preg_replace_callback('/\{\{\s*(?:fecha|date)\s+([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($row, $loc) {
        $raw = (array_key_exists($m[1], $row) && is_scalar($row[$m[1]])) ? trim((string) $row[$m[1]]) : '';
        if ($raw === '' || strpos($raw, '0000-00-00') === 0) { return ''; }
        $ts = strtotime($raw);
        if ($ts === false) { return htmlspecialchars($raw, ENT_QUOTES); }
        $fmt = (strpos($raw, ':') !== false)
            ? ((strpos($raw, '-') !== false || strpos($raw, '/') !== false) ? $loc['datetime_format'] : $loc['time_format'])
            : $loc['date_format'];
        return htmlspecialchars(date($fmt, $ts), ENT_QUOTES);
    }, $html);

    // Then simple {{field}} tags.
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($row) {
        $val = array_key_exists($m[1], $row) ? $row[$m[1]] : '';
        return htmlspecialchars(is_scalar($val) ? (string) $val : '', ENT_QUOTES);
    }, $html);
}

/**
 * Render form-input tags inside a {{#form}} block, prefilled from $row:
 *   {{input campo}} {{textarea campo}} {{file campo}} {{submit Texto}}
 */
function pb_formFields($html, array $row) {
    $val = function ($k) use ($row) {
        return htmlspecialchars((string) (array_key_exists($k, $row) && is_scalar($row[$k]) ? $row[$k] : ''), ENT_QUOTES);
    };
    $html = preg_replace_callback('/\{\{\s*input\s+([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($val) {
        return '<input type="text" class="form-control mb-2" name="' . $m[1] . '" value="' . $val($m[1]) . '" placeholder="' . $m[1] . '">';
    }, $html);
    $html = preg_replace_callback('/\{\{\s*textarea\s+([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($val) {
        return '<textarea class="form-control mb-2" name="' . $m[1] . '" rows="4" placeholder="' . $m[1] . '">' . $val($m[1]) . '</textarea>';
    }, $html);
    $html = preg_replace_callback('/\{\{\s*file\s+([a-zA-Z0-9_]+)\s*\}\}/', function ($m) {
        return '<input type="file" class="form-control mb-2" name="' . $m[1] . '">';
    }, $html);
    $html = preg_replace_callback('/\{\{\s*submit(?:\s+([^}]+?))?\s*\}\}/', function ($m) {
        $text = isset($m[1]) && trim($m[1]) !== '' ? trim($m[1]) : 'Guardar';
        return '<button type="submit" class="btn btn-primary">' . htmlspecialchars($text, ENT_QUOTES) . '</button>';
    }, $html);
    return $html;
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
function pb_renderTemplate($template, array $records, array $single, array $formRow = []) {
    // Split on the repeat block, capturing the inner HTML. Even-index parts are
    // outside any block (rendered once with $single); odd-index parts are the
    // captured inner blocks (rendered once per record). Each segment is filled
    // exactly once, so field values that happen to contain {{...}} are never
    // re-evaluated. $formRow prefills {{#form}} inputs (edit mode only).
    $parts = preg_split('/\{\{#cada\}\}(.*?)\{\{\/cada\}\}/s', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            foreach ($records as $rec) {
                $out .= pb_replaceFields($part, (array) $rec, []);
            }
        } else {
            $out .= pb_replaceFields($part, $single, $formRow);
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
    // The table is optional: an empty table means a static page (no data binding),
    // useful for landing/contact pages. A non-empty table must be a valid identifier.
    $table = (string)($raw['table'] ?? '');
    if ($table !== '' && !pb_isIdentifier($table)) {
        throw new InvalidArgumentException('Tabla inválida.');
    }

    $suffix      = pb_isIdentifier($raw['suffix'] ?? '')      ? $raw['suffix']      : ($table !== '' ? pb_deriveSuffix($table) : '');
    $idColumn    = pb_isIdentifier($raw['idColumn'] ?? '')    ? $raw['idColumn']    : ($suffix !== '' ? 'id_' . $suffix : 'id');
    $titleColumn = pb_isIdentifier($raw['titleColumn'] ?? '') ? $raw['titleColumn'] : ($suffix !== '' ? 'name_' . $suffix : 'name');

    $fileName = trim((string)($raw['fileName'] ?? ''));
    if ($fileName === '') {
        $fileName = $table;
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fileName)) {
        throw new InvalidArgumentException('Nombre de archivo inválido.');
    }

    // The table's column names — used to validate form submissions on the
    // generated page (only real columns are written).
    $columns = [];
    foreach ((array)($raw['columns'] ?? []) as $c) {
        if (pb_isIdentifier($c)) {
            $columns[] = $c;
        }
    }

    // Visual builder fields. `builderMode` records which surface the page
    // was last saved from ("code" or "visual"); `blocks` is the JSON tree
    // the visual builder serializes (only meaningful when builderMode is
    // "visual"). Both are preserved in the embedded config so the page
    // can be re-opened in its original mode. Pages created before this
    // commit have neither and open in code mode by default.
    $builderMode = (string)($raw['builderMode'] ?? 'code');
    if ($builderMode !== 'visual') { $builderMode = 'code'; }
    $blocks = (is_array($raw['blocks'] ?? null)) ? $raw['blocks'] : null;
    // Defensive: a page tagged "visual" without a blocks tree would open
    // the visual modal on an empty canvas and the user might overwrite a
    // real, hand-written .php without realising it. Fall back to "code"
    // so the next edit lands in the textarea where the actual HTML is.
    if ($builderMode === 'visual' && $blocks === null) {
        $builderMode = 'code';
    }

    return [
        'table'       => $table,
        'suffix'      => $suffix,
        'idColumn'    => $idColumn,
        'titleColumn' => $titleColumn,
        'fileName'    => $fileName,
        'heading'     => (string)($raw['heading'] ?? ''),
        // The user's HTML template with {{field}} / {{#cada}} / {{#form}} tags.
        'template'    => (string)($raw['template'] ?? ''),
        'customCss'   => (string)($raw['customCss'] ?? ''),
        'customJs'    => (string)($raw['customJs'] ?? ''),
        // Interactive options.
        'private'     => !empty($raw['private']),   // require login to view/submit
        'columns'     => $columns,                  // writable columns for forms
        // Access control (only applies when private). Empty both = any logged-in
        // user. accessRoles = allowed rol_admin values; accessUsers = allowed
        // admin ids.
        'accessRoles' => array_values(array_filter(array_map('strval', (array)($raw['accessRoles'] ?? [])), 'strlen')),
        'accessUsers' => array_values(array_filter(array_map('strval', (array)($raw['accessUsers'] ?? [])), 'strlen')),
        // SEO / Open Graph (emitted by the generated page's meta tags).
        'metaTitle'   => (string)($raw['metaTitle'] ?? ''),
        'metaDesc'    => (string)($raw['metaDesc'] ?? ''),
        'ogTitle'     => (string)($raw['ogTitle'] ?? ''),
        'ogType'      => (string)($raw['ogType'] ?? 'website'),
        'ogDesc'      => (string)($raw['ogDesc'] ?? ''),
        'ogImage'     => (string)($raw['ogImage'] ?? ''),
        // Visual builder round-trip data — see comment above.
        'builderMode' => $builderMode,
        'blocks'      => $blocks,
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
\$baseUrl  = (is_array(\$siteCfg) && isset(\$siteCfg['site']['base_url'])) ? rtrim(\$siteCfg['site']['base_url'], '/') . '/' : '/';

// Localization for {{money}} / {{fecha}} formatting tags (optional).
\$GLOBALS['__wpb_loc'] = (is_array(\$siteCfg) && isset(\$siteCfg['localization']) && is_array(\$siteCfg['localization'])) ? \$siteCfg['localization'] : [];

\$table    = \$cfg['table'] ?? '';
\$idColumn = \$cfg['idColumn'] ?? 'id';
\$template = \$cfg['template'] ?? '';
\$columns  = !empty(\$cfg['columns']) ? \$cfg['columns'] : [];
\$private  = !empty(\$cfg['private']);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
\$authKey  = 'wpb_auth_' . preg_replace('/[^a-zA-Z0-9_]/', '', (\$cfg['fileName'] ?? 'page'));
\$recordId = isset(\$_GET['id']) ? \$_GET['id'] : null;

// Logout
if (isset(\$_GET['wpb_logout'])) {
    unset(\$_SESSION[\$authKey]);
    header('Location: ' . strtok(\$_SERVER['REQUEST_URI'], '?'));
    exit;
}

\$accessRoles = isset(\$cfg['accessRoles']) && is_array(\$cfg['accessRoles']) ? \$cfg['accessRoles'] : [];
\$accessUsers = isset(\$cfg['accessUsers']) && is_array(\$cfg['accessUsers']) ? \$cfg['accessUsers'] : [];
\$loginError  = null;
\$formMessage = null;

// Login for private pages — validates against existing admins via the API.
\$user = (isset(\$_SESSION[\$authKey]) && is_array(\$_SESSION[\$authKey])) ? \$_SESSION[\$authKey] : null;
if (\$private && \$user === null && (\$_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset(\$_POST['wpb_login'])) {
    try {
        \$resp = ApiController::getByFilter('admins', 'email_admin', trim(\$_POST['wpb_email'] ?? ''));
        if (isset(\$resp->status) && \$resp->status == 200 && !empty(\$resp->results)) {
            \$admin = (array) \$resp->results[0];
            if (!empty(\$admin['password_admin']) && password_verify((string)(\$_POST['wpb_password'] ?? ''), \$admin['password_admin'])) {
                \$user = [
                    'id'    => (string)(\$admin['id_admin'] ?? ''),
                    'role'  => (string)(\$admin['rol_admin'] ?? ''),
                    'email' => (string)(\$admin['email_admin'] ?? ''),
                ];
                session_regenerate_id(true); // prevent session fixation
                \$_SESSION[\$authKey] = \$user;
            } else { \$loginError = 'Credenciales inválidas.'; }
        } else { \$loginError = 'Credenciales inválidas.'; }
    } catch (Throwable \$e) { \$loginError = 'No se pudo validar el acceso.'; }
}

\$isAuthed = !\$private || \$user !== null;

// Access check: a logged-in user must match an allowed role or be an allowed
// user; if no roles/users are configured, any logged-in user is allowed.
\$hasAccess = true;
if (\$private) {
    \$hasAccess = false;
    if (\$user !== null) {
        if (empty(\$accessRoles) && empty(\$accessUsers)) {
            \$hasAccess = true;
        } elseif (in_array(\$user['role'], \$accessRoles, true) || in_array(\$user['id'], \$accessUsers, true)) {
            \$hasAccess = true;
        }
    }
}

// Form submit (create / update) — only when authorized AND allowed.
if (\$isAuthed && \$hasAccess && (\$_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !isset(\$_POST['wpb_login'])) {
    \$fields = [];
    foreach (\$columns as \$col) {
        if (\$col === \$idColumn) { continue; }
        if (isset(\$_POST[\$col]) && is_scalar(\$_POST[\$col])) { \$fields[\$col] = trim((string) \$_POST[\$col]); }
    }
    foreach (\$_FILES as \$col => \$file) {
        if (!in_array(\$col, \$columns, true) || \$col === \$idColumn) { continue; }
        if (!isset(\$file['error']) || \$file['error'] !== UPLOAD_ERR_OK) { continue; }
        \$ext = strtolower(pathinfo(\$file['name'], PATHINFO_EXTENSION));
        if (!in_array(\$ext, ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','txt','csv','zip'], true)) { continue; }
        \$uploadDir = __DIR__ . '/../uploads';
        if (!is_dir(\$uploadDir)) { @mkdir(\$uploadDir, 0775, true); }
        \$newName = bin2hex(random_bytes(8)) . '.' . \$ext;
        if (@move_uploaded_file(\$file['tmp_name'], \$uploadDir . '/' . \$newName)) {
            \$fields[\$col] = \$baseUrl . 'uploads/' . \$newName;
        }
    }
    if (\$fields) {
        try {
            // Only authorized private pages may UPDATE an existing record;
            // public pages can only CREATE (prevents anonymous edits via ?id).
            if (\$recordId !== null && \$recordId !== '' && \$private) {
                ApiController::update(\$table, \$recordId, \$fields, \$idColumn);
            } else {
                ApiController::create(\$table, \$fields);
            }
            \$formMessage = 'Guardado correctamente.';
        } catch (Throwable \$e) { \$formMessage = 'No se pudo guardar.'; }
    }
}

\$records = [];
\$error   = null;
if (\$table !== '') { // static pages (no table) skip the data fetch
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
    function wpb_form_fields(\$html, array \$row) {
        \$val = function (\$k) use (\$row) {
            return htmlspecialchars((string) (array_key_exists(\$k, \$row) && is_scalar(\$row[\$k]) ? \$row[\$k] : ''), ENT_QUOTES);
        };
        \$html = preg_replace_callback('/\\{\\{\\s*input\\s+([a-zA-Z0-9_]+)\\s*\\}\\}/', function (\$m) use (\$val) {
            return '<input type="text" class="form-control mb-2" name="' . \$m[1] . '" value="' . \$val(\$m[1]) . '" placeholder="' . \$m[1] . '">';
        }, \$html);
        \$html = preg_replace_callback('/\\{\\{\\s*textarea\\s+([a-zA-Z0-9_]+)\\s*\\}\\}/', function (\$m) use (\$val) {
            return '<textarea class="form-control mb-2" name="' . \$m[1] . '" rows="4" placeholder="' . \$m[1] . '">' . \$val(\$m[1]) . '</textarea>';
        }, \$html);
        \$html = preg_replace_callback('/\\{\\{\\s*file\\s+([a-zA-Z0-9_]+)\\s*\\}\\}/', function (\$m) {
            return '<input type="file" class="form-control mb-2" name="' . \$m[1] . '">';
        }, \$html);
        \$html = preg_replace_callback('/\\{\\{\\s*submit(?:\\s+([^}]+?))?\\s*\\}\\}/', function (\$m) {
            \$text = isset(\$m[1]) && trim(\$m[1]) !== '' ? trim(\$m[1]) : 'Guardar';
            return '<button type="submit" class="btn btn-primary">' . htmlspecialchars(\$text, ENT_QUOTES) . '</button>';
        }, \$html);
        return \$html;
    }
    function wpb_loc() {
        \$loc = (isset(\$GLOBALS['__wpb_loc']) && is_array(\$GLOBALS['__wpb_loc'])) ? \$GLOBALS['__wpb_loc'] : [];
        \$cur = (isset(\$loc['currency']) && is_array(\$loc['currency'])) ? \$loc['currency'] : [];
        return [
            'currency'        => array_merge(['symbol' => '\$', 'decimals' => 2, 'thousands_sep' => ',', 'decimal_sep' => '.'], \$cur),
            'date_format'     => \$loc['date_format']     ?? 'd-m-Y',
            'datetime_format' => \$loc['datetime_format'] ?? 'd-m-Y H:i',
            'time_format'     => \$loc['time_format']     ?? 'H:i',
        ];
    }
    function wpb_fields(\$html, array \$row, array \$formRow = []) {
        // Form blocks: {{#form}} ...inputs... {{/form}} — prefilled only in edit
        // mode (\$formRow), so create forms appear empty.
        \$html = preg_replace_callback('/\\{\\{#form\\}\\}(.*?)\\{\\{\\/form\\}\\}/s', function (\$m) use (\$formRow) {
            return '<form method="post" enctype="multipart/form-data" class="wpb-form">' . wpb_form_fields(\$m[1], \$formRow) . '</form>';
        }, \$html);
        // Conditional blocks: {{#si campo}}…{{/si}} (truthy) / {{#no campo}}…{{/no}} (falsy).
        \$html = preg_replace_callback('/\\{\\{#si\\s+([a-zA-Z0-9_]+)\\s*\\}\\}(.*?)\\{\\{\\/si\\}\\}/s', function (\$m) use (\$row) {
            \$v = array_key_exists(\$m[1], \$row) ? \$row[\$m[1]] : null;
            return (!empty(\$v) && (string)\$v !== '0') ? \$m[2] : '';
        }, \$html);
        \$html = preg_replace_callback('/\\{\\{#no\\s+([a-zA-Z0-9_]+)\\s*\\}\\}(.*?)\\{\\{\\/no\\}\\}/s', function (\$m) use (\$row) {
            \$v = array_key_exists(\$m[1], \$row) ? \$row[\$m[1]] : null;
            return (empty(\$v) || (string)\$v === '0') ? \$m[2] : '';
        }, \$html);
        // Image-gallery blocks: {{#imagenes campo}}<img src="{{url}}">{{/imagenes}}
        \$html = preg_replace_callback('/\\{\\{#imagenes\\s+([a-zA-Z0-9_]+)\\s*\\}\\}(.*?)\\{\\{\\/imagenes\\}\\}/s', function (\$m) use (\$row) {
            \$urls = wpb_image_urls(array_key_exists(\$m[1], \$row) ? \$row[\$m[1]] : '');
            \$out = '';
            foreach (\$urls as \$url) {
                \$escUrl = htmlspecialchars(\$url, ENT_QUOTES);
                \$out .= preg_replace_callback('/\\{\\{\\s*url\\s*\\}\\}/', function () use (\$escUrl) { return \$escUrl; }, \$m[2]);
            }
            return \$out;
        }, \$html);
        // Single-image tags: {{img campo}} → the column's URL-encoded image URL,
        // decoded so the browser can load it (multi-image uses {{#imagenes}}).
        \$html = preg_replace_callback('/\\{\\{\\s*img\\s+([a-zA-Z0-9_]+)\\s*\\}\\}/', function (\$m) use (\$row) {
            \$val = array_key_exists(\$m[1], \$row) && is_scalar(\$row[\$m[1]]) ? (string) \$row[\$m[1]] : '';
            return htmlspecialchars(urldecode(\$val), ENT_QUOTES);
        }, \$html);
        // {{money campo}} → currency; {{fecha|date campo}} → friendly date/time.
        \$c = wpb_loc()['currency'];
        \$html = preg_replace_callback('/\\{\\{\\s*money\\s+([a-zA-Z0-9_]+)\\s*\\}\\}/', function (\$m) use (\$row, \$c) {
            \$val = array_key_exists(\$m[1], \$row) ? \$row[\$m[1]] : null;
            if (\$val === null || \$val === '') { return ''; }
            \$v = is_numeric(\$val) ? (float) \$val : 0;
            return htmlspecialchars(\$c['symbol'] . number_format(\$v, (int) \$c['decimals'], \$c['decimal_sep'], \$c['thousands_sep']), ENT_QUOTES);
        }, \$html);
        \$loc = wpb_loc();
        \$html = preg_replace_callback('/\\{\\{\\s*(?:fecha|date)\\s+([a-zA-Z0-9_]+)\\s*\\}\\}/', function (\$m) use (\$row, \$loc) {
            \$raw = (array_key_exists(\$m[1], \$row) && is_scalar(\$row[\$m[1]])) ? trim((string) \$row[\$m[1]]) : '';
            if (\$raw === '' || strpos(\$raw, '0000-00-00') === 0) { return ''; }
            \$ts = strtotime(\$raw);
            if (\$ts === false) { return htmlspecialchars(\$raw, ENT_QUOTES); }
            \$fmt = (strpos(\$raw, ':') !== false)
                ? ((strpos(\$raw, '-') !== false || strpos(\$raw, '/') !== false) ? \$loc['datetime_format'] : \$loc['time_format'])
                : \$loc['date_format'];
            return htmlspecialchars(date(\$fmt, \$ts), ENT_QUOTES);
        }, \$html);
        return preg_replace_callback('/\\{\\{\\s*([a-zA-Z0-9_]+)\\s*\\}\\}/', function (\$m) use (\$row) {
            \$val = array_key_exists(\$m[1], \$row) ? \$row[\$m[1]] : '';
            return htmlspecialchars(is_scalar(\$val) ? (string) \$val : '', ENT_QUOTES);
        }, \$html);
    }
    function wpb_render(\$template, array \$records, array \$single, array \$formRow = []) {
        // Split on the repeat block (capturing inner HTML) so each segment is
        // filled exactly once — no re-evaluation of {{...}} found in data.
        \$parts = preg_split('/\\{\\{#cada\\}\\}(.*?)\\{\\{\\/cada\\}\\}/s', \$template, -1, PREG_SPLIT_DELIM_CAPTURE);
        \$out = '';
        foreach (\$parts as \$i => \$part) {
            if (\$i % 2 === 1) {
                foreach (\$records as \$rec) { \$out .= wpb_fields(\$part, (array) \$rec, []); }
            } else {
                \$out .= wpb_fields(\$part, \$single, \$formRow);
            }
        }
        return \$out;
    }
}

\$single   = [];
if (\$recordId !== null && \$recordId !== '') {
    foreach (\$records as \$rec) {
        \$row = (array) \$rec;
        if (isset(\$row[\$idColumn]) && (string) \$row[\$idColumn] === (string) \$recordId) { \$single = \$row; break; }
    }
}
if (!\$single && isset(\$records[0])) { \$single = (array) \$records[0]; }

\$pageTitle       = (!empty(\$cfg['heading']) ? \$cfg['heading'] : \$siteName) . ' - ' . \$siteName;
\$pageDescription = \$cfg['metaDesc'] ?? '';

// SEO / Open Graph meta — web/views/template.php emits these tags.
\$seoMeta = (object) [
    'meta_title_seo' => \$cfg['metaTitle'] ?? '',
    'meta_desc_seo'  => \$cfg['metaDesc'] ?? '',
    'og_title_seo'   => \$cfg['ogTitle'] ?? '',
    'og_desc_seo'    => \$cfg['ogDesc'] ?? '',
    'og_image_seo'   => \$cfg['ogImage'] ?? '',
    'og_type_seo'    => !empty(\$cfg['ogType']) ? \$cfg['ogType'] : 'website',
    'slug_seo'       => '',
];

// In edit mode (?id) the form is prefilled with that record; in create mode it
// is empty (so a new submission doesn't show the first record's data).
\$formRow = (\$recordId !== null && \$recordId !== '') ? \$single : [];
\$body = (\$error || !\$hasAccess) ? '' : wpb_render(\$template, \$records, \$single, \$formRow);

ob_start();
?>
<style>
<?php echo \$cfg['customCss'] ?? ''; ?>
</style>

<div class="container my-5" id="wpb-page">
    <?php if (!empty(\$cfg['heading'])): ?>
        <h1 class="mb-4"><?php echo htmlspecialchars(\$cfg['heading'], ENT_QUOTES); ?></h1>
    <?php endif; ?>

    <?php if (\$private && \$isAuthed): ?>
        <p class="text-end"><a href="?wpb_logout=1" class="small">Cerrar sesión</a></p>
    <?php endif; ?>

    <?php if (\$formMessage): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars(\$formMessage, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <?php if (\$private && !\$isAuthed): ?>
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card"><div class="card-body">
                    <h5 class="mb-3">Iniciar sesión</h5>
                    <?php if (\$loginError): ?>
                        <div class="alert alert-danger py-2"><?php echo htmlspecialchars(\$loginError, ENT_QUOTES); ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="wpb_login" value="1">
                        <input type="email" name="wpb_email" class="form-control mb-2" placeholder="Email" required>
                        <input type="password" name="wpb_password" class="form-control mb-3" placeholder="Contraseña" required>
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>
                    </form>
                </div></div>
            </div>
        </div>
    <?php elseif (!\$hasAccess): ?>
        <div class="alert alert-warning">No tienes permiso para ver esta página.</div>
    <?php elseif (\$error): ?>
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
