<?php

/**
 * Activity Logs AJAX Endpoint
 * 
 * Handles AJAX requests for activity logs
 */

session_start();

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
            
            if (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] !== 'get') {
                $filters['action'] = $_GET['action'];
            }
            if (isset($_GET['entity']) && !empty($_GET['entity'])) {
                $filters['entity'] = $_GET['entity'];
            }
            if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }
            if (isset($_GET['admin_id']) && !empty($_GET['admin_id'])) {
                $filters['admin_id'] = (int)$_GET['admin_id'];
            }
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = $page * $limit;
            
            $logs = ActivityLogsController::getLogs($filters, $limit, $offset);
            
            // Get total count for pagination (simplified - in production you'd want a separate count method)
            $totalLogs = count(ActivityLogsController::getLogs($filters, 10000, 0));
            
            echo json_encode([
                'success' => true,
                'data' => $logs,
                'total' => $totalLogs,
                'page' => $page,
                'limit' => $limit
            ]);
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
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
