<?php
/**
 * Plugin generator
 *
 * Scaffolds a complete, self-contained plugin under plugins/<name>/ following
 * the project's plugin structure (entry file, config, controller, view, AJAX
 * handler with a session guard, and a protective .htaccess) and registers it
 * in plugins/plugins-registry.php.
 *
 * Usage:
 *   php tools/make-plugin.php <plugin-name> [options]
 *
 * Options:
 *   --label=<s>    Human-readable name (default: derived from <plugin-name>)
 *   --desc=<s>     Plugin description
 *   --icon=<s>     Bootstrap icon class (default: bi-puzzle)
 *   --type=<s>     Plugin type: custom|system|payment (default: custom)
 *   --author=<s>   Author (default: "Web Framework")
 *   --force        Overwrite existing plugin files
 *   --no-register  Do not modify plugins-registry.php (print the snippet instead)
 *
 * Example:
 *   php tools/make-plugin.php my-plugin --label="My Plugin" --icon=bi-star
 */

/**
 * Validate a kebab-case plugin name.
 */
function plugin_isValidName($name) {
    return is_string($name) && preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $name) === 1;
}

/**
 * "my-plugin" -> "MyPlugin"
 */
function plugin_studly($name) {
    return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
}

/**
 * "my-plugin" -> "My Plugin"
 */
function plugin_label($name) {
    return ucwords(str_replace('-', ' ', $name));
}

/**
 * Resolve CLI options into the generator option array. Throws on bad input.
 */
function plugin_resolveOptions($name, array $flags) {
    if (!plugin_isValidName($name)) {
        throw new InvalidArgumentException("Invalid plugin name: '{$name}' (use kebab-case, e.g. my-plugin).");
    }
    $type = (isset($flags['type']) && is_string($flags['type'])) ? strtolower($flags['type']) : 'custom';
    if (!in_array($type, ['custom', 'system', 'payment'], true)) {
        throw new InvalidArgumentException("Invalid type: '{$type}' (custom|system|payment).");
    }
    $label = (isset($flags['label']) && is_string($flags['label'])) ? $flags['label'] : plugin_label($name);
    return [
        'name'        => $name,
        'class'       => plugin_studly($name) . 'Controller',
        'label'       => $label,
        'description' => (isset($flags['desc']) && is_string($flags['desc'])) ? $flags['desc'] : ($label . ' plugin'),
        'icon'        => (isset($flags['icon']) && is_string($flags['icon'])) ? $flags['icon'] : 'bi-puzzle',
        'type'        => $type,
        'author'      => (isset($flags['author']) && is_string($flags['author'])) ? $flags['author'] : 'Web Framework',
        'table'       => 'plugin_' . str_replace('-', '_', $name),
    ];
}

/**
 * Build the registration snippet for plugins-registry.php.
 */
function buildPluginRegistration(array $o) {
    $name  = $o['name'];
    $label = addslashes($o['label']);
    $desc  = addslashes($o['description']);
    $icon  = addslashes($o['icon']);
    $type  = $o['type'];
    $auth  = addslashes($o['author']);

    return <<<PHP

// Register {$label} plugin
PluginsRegistry::register('{$name}', [
    'url'         => '{$name}',
    'name'        => '{$label}',
    'description' => '{$desc}',
    'icon'        => '{$icon}',
    'type'        => '{$type}',
    'version'     => '1.0.0',
    'author'      => '{$auth}'
]);

PHP;
}

/**
 * Build every plugin file as a map of relative-path => contents. Pure
 * function (no I/O) so it can be unit tested.
 *
 * @return array<string,string>
 */
function buildPluginFiles(array $o) {
    $name  = $o['name'];
    $class = $o['class'];
    $label = $o['label'];
    $table = $o['table'];

    $files = [];

    // Entry point.
    $files["{$name}.php"] = <<<PHP
<?php
/**
 * {$label}
 * Plugin entry point.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/controllers/{$name}.controller.php';

{$class}::init();

PHP;

    // Config.
    $files['config.php'] = <<<PHP
<?php
/**
 * {$label} configuration.
 */

return [
    'plugin' => [
        'name'    => '{$label}',
        'version' => '1.0.0',
        'enabled' => true,
    ],
    'settings' => [
        // Add plugin-specific settings here.
    ],
];

PHP;

    // Controller.
    $files["controllers/{$name}.controller.php"] = <<<PHP
<?php
/**
 * {$label} controller.
 */

require_once __DIR__ . '/../../../cms/controllers/install.controller.php';

class {$class} {

    private static \$config = null;
    private \$link;

    public function __construct() {
        \$this->link = InstallController::connect();
        \$this->ensureTableExists();
    }

    /**
     * Plugin bootstrap (called from the entry point).
     */
    public static function init() {
        self::loadConfig();
    }

    /**
     * Load and cache the plugin configuration.
     */
    private static function loadConfig() {
        if (self::\$config === null) {
            \$path = __DIR__ . '/../config.php';
            self::\$config = file_exists(\$path) ? require \$path : [];
        }
        return self::\$config;
    }

    /**
     * Create the plugin table on first use.
     */
    private function ensureTableExists() {
        if (\$this->link === null) {
            return;
        }
        \$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id`           INT          NOT NULL AUTO_INCREMENT,
            `title`        VARCHAR(255) NULL,
            `date_created` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try {
            \$this->link->exec(\$sql);
        } catch (Exception \$e) {
            error_log('{$label} table creation error: ' . \$e->getMessage());
        }
    }

    /**
     * Example: fetch all rows.
     */
    public function getAll() {
        if (\$this->link === null) {
            return [];
        }
        \$stmt = \$this->link->query("SELECT * FROM `{$table}` ORDER BY id DESC");
        return \$stmt->fetchAll(PDO::FETCH_OBJ);
    }
}

PHP;

    // View.
    $files['views/main.php'] = <<<PHP
<?php
/**
 * {$label} main view. Rendered by the CMS when the plugin page is opened.
 */
?>
<div class="container my-5">
    <h1 class="mb-4">{$label}</h1>
    <p class="text-muted">Plugin scaffold — build the UI here.</p>
</div>

PHP;

    // AJAX handler (with a session guard).
    $files['ajax.php'] = <<<PHP
<?php
/**
 * {$label} AJAX handler.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Require an authenticated admin session.
if (!isset(\$_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/controllers/{$name}.controller.php';

\$controller = new {$class}();
\$action = \$_POST['ajax_action'] ?? '';

switch (\$action) {
    case 'get_all':
        echo json_encode(['success' => true, 'data' => \$controller->getAll()]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

PHP;

    // Directory protection.
    $files['.htaccess'] = <<<HT
# Protect plugin configuration from direct web access
<FilesMatch "^(config\.php|config\.example\.php)$">
    Require all denied
</FilesMatch>

HT;

    return $files;
}

// ---------------------------------------------------------------------------
// CLI entry point (skipped when this file is required from tests).
// ---------------------------------------------------------------------------
if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {

    $args = array_slice($argv, 1);
    $name = null;
    $flags = [];

    foreach ($args as $arg) {
        if (strpos($arg, '--') === 0) {
            $kv = explode('=', substr($arg, 2), 2);
            $flags[$kv[0]] = $kv[1] ?? true;
        } elseif ($name === null) {
            $name = $arg;
        }
    }

    if ($name === null || isset($flags['help'])) {
        fwrite(STDOUT, "Usage: php tools/make-plugin.php <plugin-name> [--label= --desc= --icon= --type= --author= --force --no-register]\n");
        exit($name === null ? 1 : 0);
    }

    try {
        $opts = plugin_resolveOptions($name, $flags);
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }

    $pluginDir = __DIR__ . '/../plugins/' . $name;
    if (is_dir($pluginDir) && !isset($flags['force'])) {
        fwrite(STDERR, "Error: plugins/{$name} already exists. Use --force to overwrite.\n");
        exit(1);
    }

    $files = buildPluginFiles($opts);
    foreach ($files as $rel => $contents) {
        $path = $pluginDir . '/' . $rel;
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            fwrite(STDERR, "Error: could not create {$dir}.\n");
            exit(1);
        }
        if (@file_put_contents($path, $contents) === false) {
            fwrite(STDERR, "Error: could not write {$path}.\n");
            exit(1);
        }
    }
    fwrite(STDOUT, "Created plugins/{$name}/ (" . count($files) . " files).\n");

    // Register the plugin (idempotent).
    $registry = __DIR__ . '/../plugins/plugins-registry.php';
    $snippet = buildPluginRegistration($opts);

    if (isset($flags['no-register'])) {
        fwrite(STDOUT, "\nAdd this to plugins/plugins-registry.php:\n" . $snippet);
    } else {
        $current = @file_get_contents($registry);
        if ($current === false) {
            fwrite(STDERR, "Warning: could not read the registry; add this manually:\n" . $snippet);
        } elseif (strpos($current, "register('{$name}'") !== false) {
            fwrite(STDOUT, "Plugin '{$name}' is already registered; skipping registry update.\n");
        } elseif (@file_put_contents($registry, rtrim($current) . "\n" . $snippet, LOCK_EX) === false) {
            fwrite(STDERR, "Warning: could not update the registry; add this manually:\n" . $snippet);
        } else {
            fwrite(STDOUT, "Registered '{$name}' in plugins/plugins-registry.php.\n");
        }
    }

    exit(0);
}
