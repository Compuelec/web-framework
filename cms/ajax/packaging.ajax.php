<?php

/**
 * Packaging AJAX Endpoint
 * 
 * Handles AJAX requests for project packaging
 */

session_start();

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
            echo json_encode($result);
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

