<?php

/**
 * Payku Plugin Configuration Example
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
    // Enable/disable plugin
    'enabled' => false,
    
    // Platform: 'TEST' or 'PROD'
    'platform_id' => 'TEST',
    
    // Payment mode: '1' = Direct to Webpay, '99' = Show payment gateway
    'pagoDirecto' => '1',
    
    // Public token from Payku
    'token_publico' => 'your-public-token-here',
    
    // Marketplace token (optional, only for marketplace users)
    'marketplace' => '',
    
    // Increment percentage (0 = no increment)
    'incremento' => '0',
    
    // Order status after successful payment
    'estadoPago' => 'completed',
    
    // Enable/disable debug logging to debug.log file
    // Recommended: false in production, true for development/debugging
    'debug_enabled' => false
];

