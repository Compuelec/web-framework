<?php

/**
 * Session Initialization Helper for AJAX endpoints
 * Validates token and returns status
 * Can be used as standalone endpoint or included in other files
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/../controllers/session.controller.php';
require_once __DIR__ . '/../../api/models/connection.php';

$userId = null;
$userToken = null;

if (isset($_SESSION['admin']) && is_object($_SESSION['admin'])) {
    $userId = $_SESSION['admin']->id_admin ?? null;
    $userToken = $_SESSION['admin']->token_admin ?? null;
    
    // Validate token expiration
    if (!empty($userToken)) {
        $tokenValidation = Connection::tokenValidate($userToken, "admins", "admin");
        
        if ($tokenValidation == "expired") {
            // Only output if this is a direct request, not an include
            if (!defined('SESSION_INIT_INCLUDED')) {
                header('Content-Type: application/json');
                http_response_code(303);
                echo json_encode([
                    'status' => 303,
                    'results' => 'The token has expired'
                ]);
                exit();
            }
            // If included, the calling code should handle authentication
            // We'll just skip session initialization
        }
        
        if ($tokenValidation == "no-auth") {
            // Only output if this is a direct request, not an include
            if (!defined('SESSION_INIT_INCLUDED')) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode([
                    'status' => 401,
                    'results' => 'The user is not authorized'
                ]);
                exit();
            }
            // If included, the calling code should handle authentication
            // We'll just skip session initialization
        }
    }
}

SessionController::startUniqueSession($userId, $userToken);

// Only return JSON if this is a direct request, not an include
if (!defined('SESSION_INIT_INCLUDED')) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 200,
        'results' => 'Token is valid'
    ]);
    exit();
}

