<?php

// CMS page wrapper for the POS Manager plugin. The CMS template includes this
// file for a page with type_page = "custom" and url_page = "pos-manager"; it
// then loads the plugin's own view.

if (!isset($_SESSION['admin'])) {
    header('Location: ' . ($cmsBasePath ?? '') . '/login');
    exit;
}

$pluginDir = __DIR__ . '/../../../../../plugins/pos-manager';

if (!file_exists($pluginDir)) {
    echo '<div class="alert alert-danger m-4">Plugin pos-manager no encontrado.</div>';
    return;
}

include $pluginDir . '/views/main.php';
