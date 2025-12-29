<?php

// Load PHPMailer autoload if not already loaded
$autoloadPath = __DIR__ . '/../extensions/vendor/autoload.php';
if (file_exists($autoloadPath) && !class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once $autoloadPath;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Professional Email Service
 * Handles email sending using PHPMailer with SMTP support
 */
class EmailService {
    
    private $config;
    private $mail;
    
    /**
     * Constructor - Loads email configuration
     */
    public function __construct() {
        $configPath = __DIR__ . '/../config.php';
        if (file_exists($configPath)) {
            $this->config = require $configPath;
        } else {
            $examplePath = __DIR__ . '/../config.example.php';
            $this->config = file_exists($examplePath) ? require $examplePath : [];
        }
        
        // Set timezone
        $timezone = $this->config['timezone'] ?? 'America/Santiago';
        date_default_timezone_set($timezone);
        
        // Initialize PHPMailer
        $this->mail = new PHPMailer(true);
        $this->configureMailer();
    }
    
    /**
     * Configure PHPMailer with settings from config
     */
    private function configureMailer() {
        $emailConfig = $this->config['email'] ?? [];
        $smtpConfig = $emailConfig['smtp'] ?? [];
        
        // Basic settings
        $this->mail->CharSet = 'utf-8';
        $this->mail->Encoding = 'base64';
        
        // SMTP Configuration
        if (!empty($smtpConfig['enabled']) && $smtpConfig['enabled'] === true) {
            // Use SMTP
            $this->mail->isSMTP();
            $this->mail->Host = $smtpConfig['host'] ?? 'smtp.gmail.com';
            $this->mail->Port = $smtpConfig['port'] ?? 587;
            $this->mail->SMTPAuth = $smtpConfig['auth'] ?? true;
            $this->mail->Username = $smtpConfig['username'] ?? '';
            $this->mail->Password = $smtpConfig['password'] ?? '';
            $this->mail->Timeout = $smtpConfig['timeout'] ?? 30;
            
            // Set encryption
            $secure = $smtpConfig['secure'] ?? 'tls';
            if ($secure === 'ssl') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $this->mail->SMTPSecure = '';
            }
            
            // Debug mode
            $debugLevel = $smtpConfig['debug'] ?? 0;
            if ($debugLevel > 0) {
                $this->mail->SMTPDebug = $debugLevel === 1 ? SMTP::DEBUG_CLIENT : SMTP::DEBUG_SERVER;
            }
        } else {
            // Use PHP mail() function
            $this->mail->isMail();
            $this->mail->UseSendmailOptions = 0;
        }
        
        // Set default from address
        $fromEmail = $emailConfig['from_email'] ?? 'noreply@dashboard.com';
        $fromName = $emailConfig['from_name'] ?? 'CMS-BUILDER';
        $this->mail->setFrom($fromEmail, $fromName);
    }
    
    /**
     * Send email with HTML template
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $title Email title (displayed in template)
     * @param string $message Email message content (HTML)
     * @param string $link Optional link URL
     * @param string $linkText Optional link text (default: "Click here")
     * @return array Result array with 'success' boolean and 'message' string
     */
    public function sendEmail($to, $subject, $title, $message, $link = '', $linkText = 'Click here') {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            // Set recipient
            $this->mail->addAddress($to);
            
            // Set subject
            $this->mail->Subject = $subject;
            
            // Build HTML template
            $htmlBody = $this->buildEmailTemplate($title, $message, $link, $linkText);
            $this->mail->msgHTML($htmlBody);
            
            // Set alternative plain text body
            $plainText = strip_tags($message);
            $this->mail->AltBody = $plainText;
            
            // Send email
            $this->mail->send();
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email could not be sent. Error: ' . $this->mail->ErrorInfo
            ];
        }
    }
    
    /**
     * Send custom email with full control
     * 
     * @param array $options Email options:
     *   - 'to': string|array - Recipient(s) email address(es)
     *   - 'subject': string - Email subject
     *   - 'body': string - HTML body content
     *   - 'alt_body': string - Plain text alternative
     *   - 'from_email': string - Optional custom from email
     *   - 'from_name': string - Optional custom from name
     *   - 'reply_to': string|array - Optional reply-to address(es)
     *   - 'cc': string|array - Optional CC address(es)
     *   - 'bcc': string|array - Optional BCC address(es)
     *   - 'attachments': array - Optional array of file paths
     * @return array Result array with 'success' boolean and 'message' string
     */
    public function sendCustomEmail($options) {
        try {
            // Clear previous data
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            $this->mail->clearReplyTos();
            $this->mail->clearAttachments();
            
            // Set recipients
            if (isset($options['to'])) {
                if (is_array($options['to'])) {
                    foreach ($options['to'] as $email) {
                        $this->mail->addAddress($email);
                    }
                } else {
                    $this->mail->addAddress($options['to']);
                }
            }
            
            // Set subject
            $this->mail->Subject = $options['subject'] ?? '';
            
            // Set body
            $this->mail->msgHTML($options['body'] ?? '');
            $this->mail->AltBody = $options['alt_body'] ?? strip_tags($options['body'] ?? '');
            
            // Custom from address
            if (isset($options['from_email'])) {
                $fromName = $options['from_name'] ?? '';
                $this->mail->setFrom($options['from_email'], $fromName);
            }
            
            // Reply-to
            if (isset($options['reply_to'])) {
                if (is_array($options['reply_to'])) {
                    foreach ($options['reply_to'] as $email) {
                        $this->mail->addReplyTo($email);
                    }
                } else {
                    $this->mail->addReplyTo($options['reply_to']);
                }
            }
            
            // CC
            if (isset($options['cc'])) {
                if (is_array($options['cc'])) {
                    foreach ($options['cc'] as $email) {
                        $this->mail->addCC($email);
                    }
                } else {
                    $this->mail->addCC($options['cc']);
                }
            }
            
            // BCC
            if (isset($options['bcc'])) {
                if (is_array($options['bcc'])) {
                    foreach ($options['bcc'] as $email) {
                        $this->mail->addBCC($email);
                    }
                } else {
                    $this->mail->addBCC($options['bcc']);
                }
            }
            
            // Attachments
            if (isset($options['attachments']) && is_array($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    if (is_array($attachment)) {
                        // Array format: ['path' => '/path/to/file', 'name' => 'optional_name']
                        $path = $attachment['path'] ?? $attachment[0] ?? '';
                        $name = $attachment['name'] ?? $attachment[1] ?? '';
                        if ($path && file_exists($path)) {
                            $this->mail->addAttachment($path, $name);
                        }
                    } else {
                        // Simple string path
                        if (file_exists($attachment)) {
                            $this->mail->addAttachment($attachment);
                        }
                    }
                }
            }
            
            // Send email
            $this->mail->send();
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email could not be sent. Error: ' . $this->mail->ErrorInfo
            ];
        }
    }
    
    /**
     * Build HTML email template
     * 
     * @param string $title Email title
     * @param string $message Email message content
     * @param string $link Optional link URL
     * @param string $linkText Optional link text
     * @return string HTML template
     */
    private function buildEmailTemplate($title, $message, $link = '', $linkText = 'Haz clic aquí') {
        $linkHtml = '';
        if (!empty($link)) {
            $linkHtml = '
                <a href="' . htmlspecialchars($link) . '" target="_blank" style="text-decoration: none; margin-top:10px">
                    <div style="line-height:25px; background:#000; width:60%; padding:10px; color:white; border-radius:5px; margin: 20px auto;">' . htmlspecialchars($linkText) . '</div>
                </a>';
        }
        
        return '
            <div style="width:100%; background:#eee; position:relative; font-family:sans-serif; padding-top:40px; padding-bottom: 40px;">
                <div style="position:relative; margin:auto; width:600px; background:white; padding:20px">
                    <center>
                        <h3 style="font-weight:100; color:#999">' . htmlspecialchars($title) . '</h3>
                        <hr style="border:1px solid #ccc; width:80%">
                        ' . $message . '
                        ' . $linkHtml . '
                        <hr style="border:1px solid #ccc; width:80%">
                        <h5 style="font-weight:100; color:#999">Si no solicitó el envío de este correo, haga caso omiso de este mensaje.</h5>
                    </center>
                </div>
            </div>';
    }
    
    /**
     * Get PHPMailer instance for advanced usage
     * 
     * @return PHPMailer PHPMailer instance
     */
    public function getMailer() {
        return $this->mail;
    }
}

