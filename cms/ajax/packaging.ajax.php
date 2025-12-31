<?php

/**
 * Packaging AJAX Endpoint
 * 
 * Handles AJAX requests for project packaging
 */

// Define constant to indicate session-init is being included
define('SESSION_INIT_INCLUDED', true);

require_once __DIR__ . '/session-init.php';

// Suppress warnings that might interfere with JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Increase execution time for package creation (can take a while)
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', 300);

require_once __DIR__ . '/../controllers/packaging.controller.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['admin'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

// Only superadmin and admin can access packaging
if (!isset($_SESSION['admin']->rol_admin) || 
    ($_SESSION['admin']->rol_admin !== 'superadmin' && $_SESSION['admin']->rol_admin !== 'admin')) {
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient permissions'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // Create new package
            $result = PackagingController::createPackage();
            
            // Log result for debugging (remove in production)
            if (isset($result['success']) && $result['success']) {
                error_log("Package created successfully: " . ($result['filename'] ?? 'unknown'));
            } else {
                error_log("Package creation failed: " . ($result['message'] ?? 'unknown error'));
            }
            
            if (json_encode($result) === false) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al codificar la respuesta JSON: ' . json_last_error_msg(),
                    'debug' => $result
                ]);
            } else {
                echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            break;
            
        case 'list':
            // Get list of packages
            $packages = PackagingController::getPackages();
            echo json_encode([
                'success' => true,
                'data' => $packages
            ]);
            break;
            
        case 'delete':
            // Delete a package
            $filename = $_POST['filename'] ?? '';
            
            if (empty($filename)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Filename is required'
                ]);
                break;
            }
            
            $result = PackagingController::deletePackage($filename);
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

