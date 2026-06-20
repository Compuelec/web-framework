<?php

// CMS page wrapper for the Production Manager plugin. The CMS template includes
// this file for a page with type_page = "custom" and url_page =
// "production-manager"; it then loads the plugin's own view.

if (!isset($_SESSION['admin'])) {
    header('Location: ' . ($cmsBasePath ?? '') . '/login');
    exit;
}

$pluginDir = __DIR__ . '/../../../../../plugins/production-manager';

if (!file_exists($pluginDir)) {
    echo '<div class="alert alert-danger m-4">Plugin production-manager no encontrado.</div>';
    return;
}

include $pluginDir . '/views/main.php';
