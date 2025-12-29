<?php

/**
 * Web Configuration Example File
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
    // API Configuration
    'api' => [
        'base_url' => 'http://localhost/web-framework/api/',  // API base URL
        'key' => '565df46569b3e83efa0bda457e89d2c139da6bce9976028a6ef6f43aed8a79ee'                          // API key from api/config.php
    ],
    
    // Site Configuration
    'site' => [
        'name' => 'My Website',
        'title' => 'My Website - Home',
        'description' => 'Website description',
        'base_url' => 'http://localhost/web-framework/web/'
    ],
    
    // Timezone
    'timezone' => 'America/Santiago'
];

