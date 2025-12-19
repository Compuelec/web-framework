<?php

/**
 * Configuration Example File
 * 
 * Copy this file to config.php and fill in your actual values.
 * The config.php file is ignored by git for security.
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'config.example.php') {
    http_response_code(403);
    die('Direct access to this file is not allowed.');
}

return [
    // Database configuration
    'database' => [
        'host' => 'localhost',
        'name' => 'chatcenter',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],

    // API Configuration
    'api' => [
        'key' => 'your-api-key-here',
        'public_access_tables' => ['']
    ],

    // JWT Configuration
    'jwt' => [
        'secret' => 'your-jwt-secret-key-here',
        'expiration' => 86400 // 1 day in seconds
    ],

    // Password encryption
    'password' => [
        'salt' => 'your-password-salt-here' // Blowfish salt format: $2a$07$... (22 chars)
    ]
];
