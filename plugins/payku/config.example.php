<?php
/**
 * Payku Plugin Configuration — TEMPLATE
 *
 * Copy this file to `config.php` and fill in your real Payku credentials.
 * The real `config.php` is git-ignored and MUST NOT be committed.
 *
 * Security: prevents direct HTTP access while allowing PHP includes.
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
  'platform_id' => 'TEST',                 // 'TEST' for sandbox, your platform id for production
  'pagoDirecto' => '1',
  'token_publico' => 'your-payku-token-here',
  'marketplace' => '',
  'incremento' => '0',
  'estadoPago' => 'completed',
  'debug_enabled' => false
);
