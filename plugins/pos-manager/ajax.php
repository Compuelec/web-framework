<?php

/**
 * POS Manager — AJAX endpoint.
 * Auth + role + CSRF guarded dispatch for the cashier point-of-sale.
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

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../cms/controllers/session.controller.php';
require_once __DIR__ . '/controllers/pos-manager.controller.php';

$controller = new PosManagerController();

// Role gate — only configured roles may operate the register.
$role = $_SESSION['admin']->rol_admin ?? '';
if (!in_array($role, $controller->rolesAllowed(), true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$action = $_POST['ajax_action'] ?? '';

// State-changing actions require a valid CSRF token.
$writeActions = ['create_sale', 'save_settings'];
if (in_array($action, $writeActions, true) && !SessionController::validateCsrfRequest()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Configuration actions are superadmin-only.
$configActions = ['get_tables', 'get_columns', 'get_settings', 'save_settings'];
if (in_array($action, $configActions, true) && $role !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

switch ($action) {

    case 'search_products':
        echo json_encode($controller->searchProducts(trim($_POST['q'] ?? ''), 60));
        break;

    case 'get_tables':
        echo json_encode(['success' => true, 'tables' => $controller->getTables()]);
        break;

    case 'get_columns':
        echo json_encode(['success' => true, 'columns' => $controller->getColumns(trim($_POST['table'] ?? ''))]);
        break;

    case 'get_settings':
        echo json_encode($controller->getSettings());
        break;

    case 'save_settings':
        $cfg = json_decode($_POST['config'] ?? '{}', true);
        echo json_encode($controller->saveSettings($cfg));
        break;

    case 'create_sale':
        $items     = json_decode($_POST['items'] ?? '[]', true) ?: [];
        $payment   = trim($_POST['payment'] ?? '');
        $cashierId = (int) ($_SESSION['admin']->id_admin ?? 0);
        $result    = $controller->createSale($items, $payment, $cashierId);
        if (!empty($result['success']) && !empty($result['sale']['id'])) {
            require_once __DIR__ . '/../../core/activity_log.php';
            logActivity('create', 'sale', $result['sale']['id']);
        }
        echo json_encode($result);
        break;

    case 'get_receipt':
        echo json_encode($controller->getReceipt((int) ($_POST['sale_id'] ?? 0)));
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
