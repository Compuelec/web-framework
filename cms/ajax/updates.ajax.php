<?php

/**
 * Updates AJAX Endpoint
 * 
 * Handles AJAX requests for framework updates
 */

// Define constant to indicate session-init is being included
define('SESSION_INIT_INCLUDED', true);

require_once __DIR__ . '/session-init.php';

require_once __DIR__ . '/../controllers/updates.controller.php';

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
        case 'check':
            // Check for available updates
            $updateInfo = UpdatesController::checkForUpdates();
            echo json_encode([
                'success' => true,
                'data' => $updateInfo
            ]);
            break;
            
        case 'history':
            // Get update history
            $history = UpdatesController::getUpdateHistory();
            echo json_encode([
                'success' => true,
                'data' => $history
            ]);
            break;
            
        case 'update':
            // Process update
            $targetVersion = $_POST['version'] ?? '';
            
            if (empty($targetVersion)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Version is required'
                ]);
                exit;
            }
            
            $result = UpdatesController::processUpdate($targetVersion);
            echo json_encode($result);
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
