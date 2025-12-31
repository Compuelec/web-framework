<?php
/**
 * Email System Test Script
 * 
 * This script allows you to test the email system implementation.
 * Access it via: http://localhost/web-framework/cms/test-email.php
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
require_once "controllers/template.controller.php";

// Get email configuration
$emailConfig = $config['email'] ?? [];
$smtpConfig = $emailConfig['smtp'] ?? [];
$smtpEnabled = !empty($smtpConfig['enabled']) && $smtpConfig['enabled'] === true;

// Handle form submission
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $testEmail = filter_var($_POST['test_email'], FILTER_SANITIZE_EMAIL);
    $testSubject = $_POST['test_subject'] ?? 'Test Email from CMS';
    $testMessage = $_POST['test_message'] ?? 'This is a test email message.';
    $testLink = $_POST['test_link'] ?? '';
    
    if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            // Test using EmailService
            $emailService = new EmailService();
            $result = $emailService->sendEmail(
                $testEmail,
                $testSubject,
                'Test Email',
                '<p>' . htmlspecialchars($testMessage) . '</p>',
                $testLink,
                'Click here'
            );
        } catch (Exception $e) {
            $error = 'Exception: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid email address';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .config-info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .config-info h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .config-info p {
            margin: 5px 0;
            color: #555;
        }
        .config-info .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.enabled {
            background: #28a745;
            color: white;
        }
        .status.disabled {
            background: #dc3545;
            color: white;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn:active {
            transform: translateY(0);
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .code-block {
            background: #f4f4f4;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
            overflow-x: auto;
        }
        .code-block code {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Email System Test</h1>
            <p>Test the professional email sending system</p>
        </div>
        
        <div class="content">
            <!-- Configuration Info -->
            <div class="config-info">
                <h3>Current Configuration</h3>
                <p>
                    <strong>SMTP Status:</strong>
                    <span class="status <?php echo $smtpEnabled ? 'enabled' : 'disabled'; ?>">
                        <?php echo $smtpEnabled ? 'ENABLED' : 'DISABLED (Using PHP mail())'; ?>
                    </span>
                </p>
                <?php if ($smtpEnabled): ?>
                    <p><strong>SMTP Host:</strong> <?php echo htmlspecialchars($smtpConfig['host'] ?? 'Not configured'); ?></p>
                    <p><strong>SMTP Port:</strong> <?php echo htmlspecialchars($smtpConfig['port'] ?? 'Not configured'); ?></p>
                    <p><strong>SMTP Secure:</strong> <?php echo htmlspecialchars($smtpConfig['secure'] ?? 'Not configured'); ?></p>
                    <p><strong>SMTP Username:</strong> <?php echo htmlspecialchars($smtpConfig['username'] ?? 'Not configured'); ?></p>
                <?php endif; ?>
                <p><strong>From Email:</strong> <?php echo htmlspecialchars($emailConfig['from_email'] ?? 'Not configured'); ?></p>
                <p><strong>From Name:</strong> <?php echo htmlspecialchars($emailConfig['from_name'] ?? 'Not configured'); ?></p>
            </div>

            <!-- Results -->
            <?php if ($result !== null): ?>
                <?php if ($result['success']): ?>
                    <div class="alert alert-success">
                        <strong>✅ Success!</strong> Email sent successfully to <?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        <strong>❌ Error:</strong> <?php echo htmlspecialchars($result['message']); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($error !== null): ?>
                <div class="alert alert-error">
                    <strong>❌ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Test Form -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="test_email">Recipient Email Address *</label>
                    <input 
                        type="email" 
                        id="test_email" 
                        name="test_email" 
                        required 
                        placeholder="your-email@example.com"
                        value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="test_subject">Subject</label>
                    <input 
                        type="text" 
                        id="test_subject" 
                        name="test_subject" 
                        placeholder="Test Email Subject"
                        value="<?php echo htmlspecialchars($_POST['test_subject'] ?? 'Test Email from CMS'); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="test_message">Message</label>
                    <textarea 
                        id="test_message" 
                        name="test_message" 
                        placeholder="Enter your test message here..."
                    ><?php echo htmlspecialchars($_POST['test_message'] ?? 'This is a test email message sent from the CMS email system.'); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="test_link">Link URL (Optional)</label>
                    <input 
                        type="url" 
                        id="test_link" 
                        name="test_link" 
                        placeholder="https://example.com"
                        value="<?php echo htmlspecialchars($_POST['test_link'] ?? ''); ?>"
                    >
                </div>

                <button type="submit" class="btn">Send Test Email</button>
            </form>

            <!-- Instructions -->
            <div class="alert alert-info" style="margin-top: 30px;">
                <strong>ℹ️ Instructions:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li>Make sure you have configured your email settings in <code>cms/config.php</code></li>
                    <li>If using SMTP, ensure the credentials are correct</li>
                    <li>For Gmail, use an "App Password" instead of your regular password</li>
                    <li>Check your spam folder if you don't receive the email</li>
                    <li>Check <code>cms/php_error_log</code> for detailed error messages</li>
                </ul>
            </div>

            <!-- Code Example -->
            <div class="code-block">
                <strong>Example PHP Code:</strong><br><br>
                <code>
require_once 'controllers/email.service.php';<br><br>
$emailService = new EmailService();<br>
$result = $emailService->sendEmail(<br>
&nbsp;&nbsp;&nbsp;&nbsp;'recipient@example.com',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'Subject',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'Title',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'&lt;p&gt;Message content&lt;/p&gt;',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'https://example.com',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'Click here'<br>
);<br><br>
if ($result['success']) {<br>
&nbsp;&nbsp;&nbsp;&nbsp;echo "Email sent!";<br>
} else {<br>
&nbsp;&nbsp;&nbsp;&nbsp;echo "Error: " . $result['message'];<br>
}
                </code>
            </div>
        </div>
    </div>
</body>
</html>

