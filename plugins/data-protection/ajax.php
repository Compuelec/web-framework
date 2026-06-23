<?php

/**
 * Data Protection — AJAX endpoint.
 * Auth + role + CSRF guarded dispatch for data-subject rights (Ley 21.719).
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
require_once __DIR__ . '/controllers/data-protection.controller.php';

$controller = new DataProtectionController();

// Role gate.
$role = $_SESSION['admin']->rol_admin ?? '';
if (!in_array($role, $controller->rolesAllowed(), true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$action  = $_POST['ajax_action'] ?? '';
$adminId = (int) ($_SESSION['admin']->id_admin ?? 0);

// State-changing actions require a valid CSRF token.
$writeActions = ['erase_subject', 'create_request', 'update_request', 'save_dataset', 'delete_dataset'];
if (in_array($action, $writeActions, true) && !SessionController::validateCsrfRequest()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

switch ($action) {

    case 'find_subject':
        echo json_encode($controller->findSubject($_POST['q'] ?? ''));
        break;

    case 'export_subject':
        echo json_encode($controller->exportSubject($_POST['q'] ?? ''));
        break;

    case 'erase_subject':
        $result = $controller->eraseSubject($_POST['q'] ?? '', $_POST['mode'] ?? 'anonymize');
        if (!empty($result['success'])) {
            require_once __DIR__ . '/../../core/activity_log.php';
            logActivity($result['mode'] === 'delete' ? 'delete' : 'update', 'personal_data', 0);
        }
        echo json_encode($result);
        break;

    case 'datasets_meta':
        echo json_encode(['success' => true, 'datasets' => $controller->datasetsMeta()]);
        break;

    /* ---- visual configuration ---- */
    case 'list_tables':
        echo json_encode($controller->listTables());
        break;

    case 'list_columns':
        echo json_encode($controller->listColumns($_POST['table'] ?? ''));
        break;

    case 'list_datasets':
        echo json_encode($controller->getDatasets());
        break;

    case 'save_dataset':
        $payload = json_decode($_POST['dataset'] ?? '{}', true) ?: [];
        $result = $controller->saveDataset($payload);
        if (!empty($result['success'])) {
            require_once __DIR__ . '/../../core/activity_log.php';
            logActivity('update', 'dp_dataset', 0);
        }
        echo json_encode($result);
        break;

    case 'delete_dataset':
        echo json_encode($controller->deleteDataset($_POST['table'] ?? ''));
        break;

    case 'list_requests':
        echo json_encode($controller->listRequests($_POST['status'] ?? ''));
        break;

    case 'create_request':
        $result = $controller->createRequest(
            $_POST['type'] ?? '',
            $_POST['subject'] ?? '',
            $_POST['channel'] ?? '',
            $_POST['notes'] ?? '',
            $adminId
        );
        if (!empty($result['success'])) {
            require_once __DIR__ . '/../../core/activity_log.php';
            logActivity('create', 'dp_request', $result['id']);
        }
        echo json_encode($result);
        break;

    case 'update_request':
        echo json_encode($controller->updateRequest(
            (int) ($_POST['id'] ?? 0),
            $_POST['status'] ?? '',
            $_POST['notes'] ?? null,
            $adminId
        ));
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
