<?php
/**
 * Contabilidad PyMe AJAX handler.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Require an authenticated admin session.
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/controllers/contabilidad.controller.php';

$controller = new ContabilidadController();
$action = $_POST['ajax_action'] ?? '';

switch ($action) {
    case 'get_all':
        echo json_encode(['success' => true, 'data' => $controller->getAll()]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
