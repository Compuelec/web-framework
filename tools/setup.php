<?php
/**
 * Project setup / bootstrap.
 *
 * Idempotent. Run after installing or restoring a backup to make sure the
 * instance has its config files and writable directories. Intended to be run
 * by setup.sh (which also fixes ownership/permissions), but works standalone:
 *
 *     php tools/setup.php
 *
 * What it does:
 *   - Creates config.php from config.example.php for cms/, web/ and each plugin
 *     (only when missing — never overwrites a real config).
 *   - Specifically generates a WORKING web/config.php by reusing the API
 *     base_url + key from cms/config.php (the public site and the CMS talk to
 *     the same API), deriving the public site base_url from it.
 *   - Ensures the writable directories exist.
 *
 * It does NOT change ownership/permissions (PHP can't), and never prints secrets.
 */

$root = dirname(__DIR__);
$created = [];
$skipped = [];

require_once $root . '/tools/web-config.php';   // wpb_ensureWritableDirs(), wpb_ensureWebConfig()
require_once $root . '/tools/web-partials.php';  // wpb_ensurePartials()

function out($m) { fwrite(STDOUT, $m . PHP_EOL); }

/* ---------------------------------------------------------------------------
 * 1. Writable directories (create if missing, grant write permission if needed)
 * ------------------------------------------------------------------------- */
$dirs = wpb_ensureWritableDirs();
foreach ($dirs['created'] as $rel) { $created[] = $rel . '/'; }
foreach ($dirs['fixed']   as $rel) { $created[] = $rel . '/ (write permission granted)'; }
foreach ($dirs['failed']  as $rel) { out('! Could not create/grant write on ' . $rel . ' — run via setup.sh / sudo.'); }

/* ---------------------------------------------------------------------------
 * 2. config.php from config.example.php (cms, web, plugins) — only if missing
 * ------------------------------------------------------------------------- */
$configDirs = array_merge([$root . '/cms', $root . '/web'], glob($root . '/plugins/*', GLOB_ONLYDIR) ?: []);
foreach ($configDirs as $dir) {
    $example = $dir . '/config.example.php';
    $config  = $dir . '/config.php';
    if (file_exists($example) && !file_exists($config)) {
        // web/config.php is generated from cms below; skip the plain copy here.
        if ($dir === $root . '/web') {
            continue;
        }
        if (@copy($example, $config)) {
            $created[] = str_replace($root . '/', '', $config);
        } else {
            out('! Could not create ' . str_replace($root . '/', '', $config) . ' (permission? run via setup.sh / sudo).');
        }
    } elseif (file_exists($config)) {
        $skipped[] = str_replace($root . '/', '', $config) . ' (already exists)';
    }
}

/* ---------------------------------------------------------------------------
 * 3. web/config.php — generate a working file from cms/config.php
 * ------------------------------------------------------------------------- */
$webCfg = wpb_ensureWebConfig();
switch ($webCfg['status']) {
    case 'created':
        $created[] = 'web/config.php (from cms/config.php)';
        if (!$webCfg['derived']) {
            out('! Could not derive the public site URL — edit web/config.php and set site.base_url.');
        }
        break;
    case 'exists':
        $skipped[] = 'web/config.php (already exists)';
        break;
    case 'no-cms-config':
        out('! web/config.php not created: configure cms/config.php first (api.base_url + api.key).');
        break;
    case 'write-failed':
        out('! Could not write web/config.php (permission?) — run via setup.sh / sudo.');
        break;
}

/* ---------------------------------------------------------------------------
 * 4. Shared header/footer partials — part of the public view, auto-created so a
 *    fresh install/restore has them (the template still falls back if missing).
 * ------------------------------------------------------------------------- */
$partialExisted = [
    'header' => file_exists(wpb_partialPath('header')),
    'footer' => file_exists(wpb_partialPath('footer')),
];
wpb_ensurePartials();
foreach ($partialExisted as $which => $existed) {
    $rel  = 'web/partials/' . $which . '.php';
    $path = wpb_partialPath('header' === $which ? 'header' : 'footer');
    if ($path && file_exists($path)) {
        if ($existed) { $skipped[] = $rel . ' (already exists)'; }
        else          { $created[] = $rel; }
    } else {
        out('! Could not create ' . $rel . ' (permission?) — run via setup.sh / sudo.');
    }
}

/* ---------------------------------------------------------------------------
 * Summary
 * ------------------------------------------------------------------------- */
out('');
out('Setup summary');
out('-------------');
if ($created) {
    out('Created:');
    foreach ($created as $c) { out('  + ' . $c); }
} else {
    out('Nothing to create — everything already in place.');
}
if ($skipped) {
    out('Kept:');
    foreach ($skipped as $s) { out('  = ' . $s); }
}
out('');
out('Review the generated config.php files and fill any remaining secrets');
out('(database, email, payment keys, etc.).');
