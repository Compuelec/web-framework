<?php
/**
 * Payku Plugin Configuration
 * 
 * This file contains sensitive configuration data including API tokens.
 * DO NOT commit this file to version control.
 * 
 * Security: Prevents direct HTTP access while allowing PHP includes
 */

// Prevent direct HTTP access
// Allow access only when included/required from PHP code
if (php_sapi_name() !== 'cli') {
    // Check if it's a direct HTTP request (not included)
    $isDirectAccess = (
        // Direct access from browser
        (isset($_SERVER['REQUEST_METHOD']) && 
         basename($_SERVER['PHP_SELF']) === 'config.php') ||
        // Access via URL
        (isset($_SERVER['HTTP_HOST']) && 
         isset($_SERVER['REQUEST_URI']) &&
         strpos($_SERVER['REQUEST_URI'], 'config.php') !== false)
    );
    
    if ($isDirectAccess) {
        http_response_code(403);
        header('Content-Type: text/plain');
        die('403 Forbidden: Direct access to this file is not allowed.');
    }
}

return array (
  'enabled' => true,
  'platform_id' => 'TEST',
  'pagoDirecto' => '1',
  'token_publico' => 'tkpu113739a40843392355a5b0c6464e',
  'marketplace' => '',
  'incremento' => '0',
  'estadoPago' => 'completed',
  'debug_enabled' => false
);
