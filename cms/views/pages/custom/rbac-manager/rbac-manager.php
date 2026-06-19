<?php
/**
 * RBAC Manager - Custom CMS Page
 * Loaded by the CMS when accessing the rbac-manager custom page.
 */

$pluginDir = __DIR__ . '/../../../../../plugins/rbac-manager';

if (!file_exists($pluginDir)) {
    echo '<div class="alert alert-danger m-4">';
    echo '<h5><i class="bi bi-exclamation-triangle me-2"></i>Plugin no encontrado</h5>';
    echo '<p>El plugin RBAC Manager no está instalado. Verifica que existe en <code>plugins/rbac-manager/</code></p>';
    echo '</div>';
    return;
}

if (!isset($_SESSION['admin'])) {
    header('Location: ' . ($cmsBasePath ?? '') . '/login');
    exit;
}

// Only superadmin can manage roles
if ($_SESSION['admin']->rol_admin !== 'superadmin') {
    echo '<div class="alert alert-danger m-4">';
    echo '<i class="bi bi-lock me-2"></i>Acceso denegado. Solo el superadmin puede gestionar roles.';
    echo '</div>';
    return;
}

$pluginConfig = require $pluginDir . '/config.php';

include $pluginDir . '/views/main.php';
