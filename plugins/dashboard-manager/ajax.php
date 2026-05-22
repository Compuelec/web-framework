<?php
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/controllers/dashboard-manager.controller.php';

$controller = new DashboardManagerController();
$adminId    = (int)($_SESSION['admin']->id_admin ?? 0);
$action     = $_POST['ajax_action'] ?? '';

switch ($action) {

    case 'get_widgets':
        $widgets = $controller->getWidgets($adminId);
        echo json_encode(['success' => true, 'widgets' => $widgets]);
        break;

    case 'get_widget_data':
        $widgetId = (int)($_POST['widget_id'] ?? 0);
        $result   = $controller->getWidgetData($widgetId, $adminId);
        echo json_encode($result);
        break;

    case 'get_tables':
        $tables = $controller->getTables();
        echo json_encode(['success' => true, 'tables' => $tables]);
        break;

    case 'get_columns':
        $table   = trim($_POST['table'] ?? '');
        $columns = $controller->getColumns($table);
        echo json_encode(['success' => true, 'columns' => $columns]);
        break;

    case 'save_widget':
        $data = [
            'type'    => trim($_POST['type']    ?? ''),
            'title'   => trim($_POST['title']   ?? ''),
            'config'  => json_decode($_POST['config'] ?? '{}', true) ?? [],
            'width'   => trim($_POST['width']   ?? 'col-md-4'),
            'refresh' => (int)($_POST['refresh'] ?? 0),
        ];
        echo json_encode($controller->saveWidget($adminId, $data));
        break;

    case 'update_widget':
        $widgetId = (int)($_POST['widget_id'] ?? 0);
        $data = [
            'title'   => trim($_POST['title']  ?? ''),
            'config'  => json_decode($_POST['config'] ?? '{}', true) ?? [],
            'width'   => trim($_POST['width']  ?? 'col-md-4'),
            'refresh' => (int)($_POST['refresh'] ?? 0),
        ];
        echo json_encode($controller->updateWidget($widgetId, $adminId, $data));
        break;

    case 'delete_widget':
        $widgetId = (int)($_POST['widget_id'] ?? 0);
        echo json_encode($controller->deleteWidget($widgetId, $adminId));
        break;

    case 'update_positions':
        $positions = json_decode($_POST['positions'] ?? '[]', true) ?? [];
        echo json_encode($controller->updatePositions($adminId, $positions));
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
