<?php
if (!isset($_SESSION['admin'])) {
    header('Location: ' . ($cmsBasePath ?? '') . '/login');
    exit;
}

$pluginDir = __DIR__ . '/../../../../../plugins/dashboard-manager';

if (!file_exists($pluginDir)) {
    echo '<div class="alert alert-danger m-4">Plugin dashboard-manager no encontrado.</div>';
    return;
}

include $pluginDir . '/views/main.php';
