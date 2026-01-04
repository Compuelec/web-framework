<?php
/**
 * Workflow Manager Custom Page
 * This file is loaded by the CMS when accessing the workflow-manager custom page
 */

$pluginDir = __DIR__ . '/../../../../../plugins/workflow-manager';

// Check plugin exists
if (!file_exists($pluginDir)) {
    echo '<div class="alert alert-danger m-4">';
    echo '<h5><i class="bi bi-exclamation-triangle me-2"></i>Plugin no encontrado</h5>';
    echo '<p>El plugin Workflow Manager no esta instalado. Por favor, verifica que existe en la carpeta <code>plugins/workflow-manager/</code></p>';
    echo '</div>';
    return;
}

// Security check
if (!isset($_SESSION['admin'])) {
    header('Location: ' . ($cmsBasePath ?? '') . '/login');
    exit;
}

// Only superadmin can access
if ($_SESSION['admin']->rol_admin !== 'superadmin') {
    echo '<div class="alert alert-danger m-4">Acceso denegado. Solo superadmin puede acceder a esta seccion.</div>';
    return;
}

// Load plugin config
$pluginConfig = require $pluginDir . '/config.php';

// Load main view (AJAX is handled by ajax.php)
include $pluginDir . '/views/main.php';
