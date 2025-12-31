<?php
/**
 * Simple Email Test Script (Command Line / Direct Access)
 * 
 * Usage:
 * - Via browser: http://localhost/web-framework/cms/test-email-simple.php?email=your-email@example.com
 * - Via command line: php test-email-simple.php your-email@example.com
 * 
 * SECURITY WARNING: Remove or protect this file in production!
 */

// Error handling
ini_set("display_errors", 1);
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/php_error_log");

// Load configuration
$configPath = __DIR__ . '/config.php';
$config = null;
if (file_exists($configPath)) {
    $config = require $configPath;
}

if (!is_array($config)) {
    $examplePath = __DIR__ . '/config.example.php';
    if (file_exists($examplePath)) {
        $config = require $examplePath;
    }
}

$timezone = is_array($config) ? ($config['timezone'] ?? 'America/Santiago') : 'America/Santiago';
date_default_timezone_set($timezone);

// Load required files
require_once "extensions/vendor/autoload.php";
require_once "controllers/email.service.php";

// Get email from command line argument or GET parameter
$testEmail = null;
if (php_sapi_name() === 'cli') {
    // Command line mode
    $testEmail = $argv[1] ?? null;
} else {
    // Web mode
    $testEmail = $_GET['email'] ?? null;
}

// If no email provided, show usage
if (!$testEmail) {
    if (php_sapi_name() === 'cli') {
        echo "Usage: php test-email-simple.php your-email@example.com\n";
        echo "\nOr via browser: test-email-simple.php?email=your-email@example.com\n";
    } else {
        echo "<h1>Email Test Script</h1>";
        echo "<p>Usage: test-email-simple.php?email=your-email@example.com</p>";
        echo "<p>Or use the full test interface: <a href='test-email.php'>test-email.php</a></p>";
    }
    exit;
}

// Validate email
if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    $message = "Invalid email address: " . htmlspecialchars($testEmail);
    if (php_sapi_name() === 'cli') {
        echo "ERROR: $message\n";
    } else {
        echo "<h1>Error</h1><p>$message</p>";
    }
    exit(1);
}

// Get configuration info
$emailConfig = $config['email'] ?? [];
$smtpConfig = $emailConfig['smtp'] ?? [];
$smtpEnabled = !empty($smtpConfig['enabled']) && $smtpConfig['enabled'] === true;

// Display configuration
if (php_sapi_name() === 'cli') {
    echo "========================================\n";
    echo "Email System Test\n";
    echo "========================================\n\n";
    echo "Configuration:\n";
    echo "  SMTP Enabled: " . ($smtpEnabled ? "YES" : "NO (Using PHP mail())") . "\n";
    if ($smtpEnabled) {
        echo "  SMTP Host: " . ($smtpConfig['host'] ?? 'Not configured') . "\n";
        echo "  SMTP Port: " . ($smtpConfig['port'] ?? 'Not configured') . "\n";
        echo "  SMTP Secure: " . ($smtpConfig['secure'] ?? 'Not configured') . "\n";
    }
    echo "  From Email: " . ($emailConfig['from_email'] ?? 'Not configured') . "\n";
    echo "  From Name: " . ($emailConfig['from_name'] ?? 'Not configured') . "\n";
    echo "\n";
    echo "Sending test email to: $testEmail\n";
    echo "Please wait...\n\n";
} else {
    echo "<h1>Email System Test</h1>";
    echo "<h2>Configuration:</h2>";
    echo "<ul>";
    echo "<li><strong>SMTP Enabled:</strong> " . ($smtpEnabled ? "YES" : "NO (Using PHP mail())") . "</li>";
    if ($smtpEnabled) {
        echo "<li><strong>SMTP Host:</strong> " . htmlspecialchars($smtpConfig['host'] ?? 'Not configured') . "</li>";
        echo "<li><strong>SMTP Port:</strong> " . htmlspecialchars($smtpConfig['port'] ?? 'Not configured') . "</li>";
        echo "<li><strong>SMTP Secure:</strong> " . htmlspecialchars($smtpConfig['secure'] ?? 'Not configured') . "</li>";
    }
    echo "<li><strong>From Email:</strong> " . htmlspecialchars($emailConfig['from_email'] ?? 'Not configured') . "</li>";
    echo "<li><strong>From Name:</strong> " . htmlspecialchars($emailConfig['from_name'] ?? 'Not configured') . "</li>";
    echo "</ul>";
    echo "<p><strong>Sending test email to:</strong> " . htmlspecialchars($testEmail) . "</p>";
    echo "<p>Please wait...</p>";
    flush();
}

// Send test email
try {
    $emailService = new EmailService();
    
    $result = $emailService->sendEmail(
        $testEmail,
        'Test Email from CMS',
        'Test Email',
        '<p>This is a test email sent from the CMS email system.</p><p>If you received this email, the system is working correctly!</p>',
        'https://example.com',
        'Visit Example'
    );
    
    if ($result['success']) {
        $successMessage = "✅ SUCCESS! Email sent successfully to $testEmail";
        if (php_sapi_name() === 'cli') {
            echo $successMessage . "\n";
            echo "\nCheck the recipient's inbox (and spam folder) for the email.\n";
        } else {
            echo "<h2 style='color: green;'>$successMessage</h2>";
            echo "<p>Check the recipient's inbox (and spam folder) for the email.</p>";
        }
        exit(0);
    } else {
        $errorMessage = "❌ ERROR: " . $result['message'];
        if (php_sapi_name() === 'cli') {
            echo $errorMessage . "\n";
            echo "\nCheck cms/php_error_log for more details.\n";
        } else {
            echo "<h2 style='color: red;'>$errorMessage</h2>";
            echo "<p>Check <code>cms/php_error_log</code> for more details.</p>";
        }
        exit(1);
    }
} catch (Exception $e) {
    $errorMessage = "❌ EXCEPTION: " . $e->getMessage();
    if (php_sapi_name() === 'cli') {
        echo $errorMessage . "\n";
        echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    } else {
        echo "<h2 style='color: red;'>$errorMessage</h2>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
    exit(1);
}

