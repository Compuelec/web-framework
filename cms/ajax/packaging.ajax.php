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
            
        case 'restore':
            // Restore the platform from an uploaded package .zip (DESTRUCTIVE:
            // overwrites the current database). Superadmin only.
            if (($_SESSION['admin']->rol_admin ?? '') !== 'superadmin') {
                echo json_encode(['success' => false, 'message' => 'Solo un superadmin puede restaurar la plataforma.']);
                break;
            }
            $err = $_FILES['package']['error'] ?? UPLOAD_ERR_NO_FILE;
            if (!isset($_FILES['package']) || $err !== UPLOAD_ERR_OK) {
                $msg = ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE)
                    ? 'El paquete supera el tamaño máximo de subida del servidor (ajusta upload_max_filesize / post_max_size en php.ini).'
                    : 'No se recibió el archivo de paquete (.zip).';
                echo json_encode(['success' => false, 'message' => $msg]);
                break;
            }
            if (!is_uploaded_file($_FILES['package']['tmp_name'])) {
                echo json_encode(['success' => false, 'message' => 'Subida inválida.']);
                break;
            }
            $ext = strtolower(pathinfo($_FILES['package']['name'] ?? '', PATHINFO_EXTENSION));
            if ($ext !== 'zip') {
                echo json_encode(['success' => false, 'message' => 'El archivo debe ser un .zip válido.']);
                break;
            }
            require_once __DIR__ . '/../controllers/package-install.controller.php';
            $includeFiles = !isset($_POST['include_files']) || in_array($_POST['include_files'], ['1', 'true', 'on'], true);
            $result = PackageInstallController::restoreFromZip($_FILES['package']['tmp_name'], $includeFiles);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    Logger::error("Packaging AJAX error", ['exception' => $e->getMessage()]);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}

