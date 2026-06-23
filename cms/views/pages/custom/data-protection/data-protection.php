<?php

// CMS page wrapper for the Data Protection plugin. The CMS template includes
// this file for a page with type_page = "custom" and url_page =
// "data-protection"; it then loads the plugin's own view.

if (!isset($_SESSION['admin'])) {
    header('Location: ' . ($cmsBasePath ?? '') . '/login');
    exit;
}

// Configuration/compliance plugin: superadmin & admin only — even if an editor
// were granted this page via RBAC, the tools must not be usable by other roles.
$role = $_SESSION['admin']->rol_admin ?? '';
if (!in_array($role, ['superadmin', 'admin'], true)) {
    echo '<div class="alert alert-danger m-4"><i class="bi bi-shield-lock me-2"></i>'
        . 'Acceso restringido: solo administradores pueden gestionar la protección de datos.</div>';
    return;
}

$pluginDir = __DIR__ . '/../../../../../plugins/data-protection';

if (!file_exists($pluginDir)) {
    echo '<div class="alert alert-danger m-4">Plugin data-protection no encontrado.</div>';
    return;
}

include $pluginDir . '/views/main.php';
