<?php
/**
 * Workflow Manager AJAX Handler
 * Handles all AJAX requests for the plugin
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security check
if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only superadmin can access
if ($_SESSION['admin']->rol_admin !== 'superadmin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Set JSON header early
header('Content-Type: application/json');

// Log for debugging
error_log("WorkflowManager AJAX: Request received - action: " . ($_POST['ajax_action'] ?? 'none'));

try {
    // Load controller
    require_once __DIR__ . '/controllers/workflow-manager.controller.php';

    // Initialize controller
    $controller = new WorkflowManagerController();
} catch (Exception $e) {
    error_log("WorkflowManager AJAX: Error loading controller - " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error: ' . $e->getMessage()]);
    exit;
}

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    switch ($_POST['ajax_action']) {
        case 'get_workflow':
            echo json_encode($controller->getWorkflow($_POST['module_id'] ?? 0));
            break;
        case 'save_workflow':
            echo json_encode($controller->saveWorkflow($_POST));
            break;
        case 'get_modules':
            echo json_encode($controller->getModulesWithWorkflow());
            break;
        case 'get_roles':
            echo json_encode($controller->getRoles());
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No action specified']);
}
