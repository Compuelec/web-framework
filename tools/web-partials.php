<?php
/**
 * Shared helpers for the public site's header/footer partials (web/partials/).
 *
 * Used by the page builder AJAX (cms/ajax/web-pages.ajax.php) and by the installer
 * (tools/setup.php), so a fresh install/restore auto-creates them. The public
 * template (web/views/template.php) still falls back to a built-in nav/footer when
 * they are missing, so the site never breaks even if these files are absent.
 *
 * One header + footer for the whole public site, edited in the page builder
 * (HTML + CSS + JS). Each file embeds its config so the editor can round-trip it.
 */

function wpb_partialsDir() {
    $web = realpath(__DIR__ . '/../web');
    return $web === false ? false : $web . DIRECTORY_SEPARATOR . 'partials';
}

function wpb_partialPath($which) {
    $dir = wpb_partialsDir();
    if ($dir === false) { return false; }
    return $dir . DIRECTORY_SEPARATOR . ($which === 'footer' ? 'footer.php' : 'header.php');
}

function wpb_defaultPartial($which) {
    if ($which === 'footer') {
        return [
            'html' => "<footer class=\"bg-dark text-light py-4 mt-5\">\n"
                    . "    <div class=\"container text-center\">\n"
                    . "        <p class=\"mb-0\">&copy; <?php echo date('Y'); ?> <?php echo \$siteName ?? 'My Website'; ?></p>\n"
                    . "    </div>\n"
                    . "</footer>",
            'css' => '', 'js' => '',
        ];
    }
    return [
        'html' => "<nav class=\"navbar navbar-expand-lg navbar-dark bg-dark\">\n"
                . "    <div class=\"container\">\n"
                . "        <a class=\"navbar-brand\" href=\"<?php echo \$baseUrl; ?>\"><?php echo \$siteName ?? 'My Website'; ?></a>\n"
                . "    </div>\n"
                . "</nav>",
        'css' => '', 'js' => '',
    ];
}

// Build the partial file: an embedded config (for round-trip editing) followed by
// the rendered CSS + HTML + JS.
function wpb_buildPartialFile($html, $css, $js) {
    $cfg = base64_encode(json_encode(['html' => $html, 'css' => $css, 'js' => $js]));
    $out = "<!--wpb-partial:" . $cfg . "-->\n";
    if (trim($css) !== '') { $out .= "<style>\n" . $css . "\n</style>\n"; }
    $out .= $html . "\n";
    if (trim($js) !== '') { $out .= "<script>\n" . $js . "\n</script>\n"; }
    return $out;
}

// Parse a partial file back into html/css/js using its embedded config.
function wpb_parsePartialFile($content) {
    if (preg_match('/<!--wpb-partial:([A-Za-z0-9+\/=]+)-->/', $content, $m)) {
        $json = base64_decode($m[1], true);
        $data = $json !== false ? json_decode($json, true) : null;
        if (is_array($data)) {
            return [
                'html' => (string)($data['html'] ?? ''),
                'css'  => (string)($data['css'] ?? ''),
                'js'   => (string)($data['js'] ?? ''),
            ];
        }
    }
    // Legacy / hand-edited file: treat the whole content as HTML.
    return ['html' => (string)$content, 'css' => '', 'js' => ''];
}

// Create web/partials/ and the default header.php + footer.php if they're missing.
// Safe to call repeatedly; never overwrites an existing (customized) partial.
function wpb_ensurePartials() {
    $dir = wpb_partialsDir();
    if ($dir === false) { return; }
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    foreach (['header', 'footer'] as $which) {
        $p = wpb_partialPath($which);
        if ($p && !file_exists($p)) {
            $d = wpb_defaultPartial($which);
            @file_put_contents($p, wpb_buildPartialFile($d['html'], $d['css'], $d['js']));
        }
    }
}
