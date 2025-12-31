<?php

/**
 * Activity Logs AJAX Endpoint
 * 
 * Handles AJAX requests for activity logs
 */

// Define constant to indicate session-init is being included
define('SESSION_INIT_INCLUDED', true);

require_once __DIR__ . '/session-init.php';

require_once __DIR__ . '/../controllers/activity_logs.controller.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['admin'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Get logs with filters
            $filters = [];
            
            // Use 'filter_action' to avoid conflict with 'action' parameter
            if (isset($_GET['filter_action']) && !empty($_GET['filter_action'])) {
                $filters['action'] = $_GET['filter_action'];
            }
            if (isset($_GET['entity']) && !empty($_GET['entity'])) {
                $filters['entity'] = $_GET['entity'];
            }
            if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'] . ' 00:00:00';
            }
            if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'] . ' 23:59:59';
            }
            if (isset($_GET['admin_id']) && !empty($_GET['admin_id'])) {
                $filters['admin_id'] = (int)$_GET['admin_id'];
            }
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = $page * $limit;
            
            // Get logs
            $logs = ActivityLogsController::getLogs($filters, $limit, $offset);
            
            // Ensure logs is an array
            if (!is_array($logs)) {
                $logs = [];
            }
            
            // Get total count for pagination
            $totalLogs = ActivityLogsController::getLogsCount($filters);
            
            echo json_encode([
                'success' => true,
                'data' => $logs,
                'total' => (int)$totalLogs,
                'page' => $page,
                'limit' => $limit
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'clear':
            // Clear all logs (only for superadmin)
            if (!isset($_SESSION['admin']) || $_SESSION['admin']->rol_admin !== 'superadmin') {
                echo json_encode([
                    'success' => false,
                    'error' => 'No tienes permisos para realizar esta acción'
                ]);
                exit;
            }
            
            $result = ActivityLogsController::clearLogs();
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Logs eliminados exitosamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al eliminar los logs'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Activity logs AJAX error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
