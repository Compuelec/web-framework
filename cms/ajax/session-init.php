<?php

/**
 * Session Initialization Helper for AJAX endpoints
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

$userId = null;
$userToken = null;

if (isset($_SESSION['admin']) && is_object($_SESSION['admin'])) {
    $userId = $_SESSION['admin']->id_admin ?? null;
    $userToken = $_SESSION['admin']->token_admin ?? null;
}

SessionController::startUniqueSession($userId, $userToken);

