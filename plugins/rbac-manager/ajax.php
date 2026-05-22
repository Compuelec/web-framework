<?php

/**
 * RBAC Manager - AJAX Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only superadmin can manage roles
if ($_SESSION['admin']->rol_admin !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

require_once __DIR__ . '/controllers/rbac-manager.controller.php';

try {
    $controller = new RBACManagerController();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Internal error: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['ajax_action'] ?? $_GET['ajax_action'] ?? '';

switch ($action) {

    // --- Roles ---
    case 'get_roles':
        echo json_encode($controller->getRoles());
        break;

    case 'get_role':
        echo json_encode($controller->getRole($_POST['id_role'] ?? 0));
        break;

    case 'save_role':
        echo json_encode($controller->saveRole($_POST));
        break;

    case 'delete_role':
        echo json_encode($controller->deleteRole($_POST['id_role'] ?? 0));
        break;

    // --- Pages (for permission matrix) ---
    case 'get_pages':
        echo json_encode($controller->getPages());
        break;

    // --- Assignments ---
    case 'get_admins':
        echo json_encode($controller->getAdmins());
        break;

    case 'assign_role':
        echo json_encode($controller->assignRole(
            $_POST['admin_id'] ?? 0,
            $_POST['role_id'] ?? null
        ));
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
}
