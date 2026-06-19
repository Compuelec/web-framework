<?php
/**
 * Shared helpers to bootstrap the public site (web/):
 *   - generate web/config.php from cms/config.php, and
 *   - ensure the writable directories exist and are writable.
 *
 * Used by the web installer (install.controller.php), the CLI installer
 * (tools/setup.php), and the page-builder AJAX, so every install path produces a
 * working public site without manual steps.
 */

function wpb_frameworkRoot() {
    return dirname(__DIR__); // tools/ → framework root
}

/** Directories that must be writable for the CMS + public site to work. */
function wpb_writableDirs() {
    return ['web/pages', 'web/partials', 'logs', 'api/tmp', 'cms/views/assets/files', 'packages'];
}

/**
 * Create each writable directory if missing and grant write permission (0775) if
 * it lacks it. The web server user owns these on a normal install, so it can fix
 * them. Returns ['created'=>[], 'fixed'=>[], 'failed'=>[]] (paths relative to root).
 */
function wpb_ensureWritableDirs() {
    $root = wpb_frameworkRoot();
    $created = $fixed = $failed = [];
    foreach (wpb_writableDirs() as $rel) {
        $dir = $root . '/' . $rel;
        if (!is_dir($dir)) {
            if (@mkdir($dir, 0775, true)) { @chmod($dir, 0775); $created[] = $rel; }
            else { $failed[] = $rel; continue; }
        }
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
            if (is_writable($dir)) { $fixed[] = $rel; } else { $failed[] = $rel; }
        }
    }
    return ['created' => $created, 'fixed' => $fixed, 'failed' => $failed];
}

/**
 * Generate web/config.php from cms/config.php if it is missing. Idempotent.
 * Returns ['status'=>'created'|'exists'|'no-cms-config'|'write-failed', 'detail'=>string,
 *          'derived'=>bool] (derived=false means site.base_url should be set manually).
 */
function wpb_ensureWebConfig() {
    $root      = wpb_frameworkRoot();
    $webConfig = $root . '/web/config.php';
    if (file_exists($webConfig)) {
        return ['status' => 'exists', 'detail' => 'web/config.php already exists', 'derived' => true];
    }
    $cmsConfigPath = $root . '/cms/config.php';
    $cms = file_exists($cmsConfigPath) ? @include $cmsConfigPath : null;
    if (!is_array($cms) || empty($cms['api']['base_url']) || empty($cms['api']['key'])) {
        return ['status' => 'no-cms-config', 'detail' => 'Configure cms/config.php first (api.base_url + api.key)', 'derived' => false];
    }
    // The public site usually sits next to the API (…/api → …/web). If that shape
    // doesn't apply (subdomain / different host), site.base_url must be set manually.
    $apiBase  = rtrim($cms['api']['base_url'], '/');
    $siteBase = preg_replace('#/api$#', '/web', $apiBase);
    $derived  = ($siteBase !== $apiBase);
    $siteBase = rtrim($derived ? $siteBase : $apiBase, '/') . '/';
    $data = [
        'api'  => ['base_url' => $apiBase . '/', 'key' => $cms['api']['key']],
        'site' => ['name' => 'My Website', 'title' => 'My Website', 'description' => '', 'base_url' => $siteBase],
        'timezone' => $cms['timezone'] ?? 'UTC',
    ];
    $php = "<?php\n"
         . "// Auto-generated from cms/config.php. Edit as needed.\n"
         . "if (basename(\$_SERVER['PHP_SELF']) === 'config.php') { http_response_code(403); die('Forbidden'); }\n"
         . "return " . var_export($data, true) . ";\n";
    if (@file_put_contents($webConfig, $php) === false) {
        return ['status' => 'write-failed', 'detail' => 'Could not write web/config.php (permissions)', 'derived' => $derived];
    }
    return ['status' => 'created', 'detail' => 'web/config.php created from cms/config.php', 'derived' => $derived];
}
