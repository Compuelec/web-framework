<?php

/**
 * Production Manager — AJAX endpoint.
 * Auth + role + CSRF guarded dispatch for manufacturing.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true, 'samesite' => 'Lax',
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
require_once __DIR__ . '/controllers/production-manager.controller.php';

$controller = new ProductionManagerController();

// Role gate.
$role = $_SESSION['admin']->rol_admin ?? '';
if (!in_array($role, $controller->rolesAllowed(), true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$action = $_POST['ajax_action'] ?? '';

// State-changing actions require a valid CSRF token.
if ($action === 'produce' && !SessionController::validateCsrfRequest()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

switch ($action) {

    case 'search_products':
        echo json_encode($controller->searchProducts(trim($_POST['q'] ?? ''), 60));
        break;

    case 'get_recipe':
        echo json_encode($controller->getRecipe((int)($_POST['product_id'] ?? 0)));
        break;

    case 'produce':
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty       = (int)($_POST['qty'] ?? 0);
        $userId    = (int)($_SESSION['admin']->id_admin ?? 0);
        $result    = $controller->produce($productId, $qty, $userId);
        if (!empty($result['success']) && !empty($result['production']['id'])) {
            require_once __DIR__ . '/../../core/activity_log.php';
            logActivity('create', 'production', $result['production']['id']);
        }
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
