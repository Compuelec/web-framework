<?php

/**
 * CMS Configuration Example File
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
    // Database Configuration
    'database' => [
        'host' => 'localhost',
        'name' => 'chatcenter',
        'user' => 'root',
        'pass' => ''
    ],
    // Webhook Configuration (Meta/WhatsApp)
    'webhook' => [
        'token' => 'your-webhook-token-here'
    ],

    // Email Configuration (PHPMailer)
    'email' => [
        'from_email' => 'noreply@dashboard.com',
        'from_name' => 'CMS-BUILDER'
    ],

    // API Configuration
    'api' => [
        'base_url' => 'http://localhost/chatcenter/api/',
        'key' => 'your-api-key-here'
    ],

    // OpenAI/ChatGPT Configuration
    'openai' => [
        'api_url' => 'https://api.openai.com/v1/chat/completions',
        'model' => 'gpt-4-0613',
        'token' => 'your-openai-token-here',
        'organization' => 'your-openai-org-id-here'
    ],

    // Timezone
    'timezone' => 'America/Santiago',

    // Password encryption
    'password' => [
        'salt' => 'your-password-salt-here' // Blowfish salt format: $2a$07$... (22 chars)
    ],

    // Updates Configuration
    'updates' => [
        // Option 1: GitHub Releases/Tags (recommended)
        // The system will automatically check releases/tags on GitHub
        'github_owner' => 'tu-usuario-github',        // GitHub user or organization
        'github_repo' => 'tu-repositorio',            // Repository name
        'github_token' => null,                       // GitHub token (optional, only for private repos)
                                                      // Generate a token at: https://github.com/settings/tokens
        
        // Option 2: Custom update server (alternative to GitHub)
        // If github_owner and github_repo are configured, this option is ignored
        'server_url' => 'https://updates.yourframework.com/api/check',
        
        // Option 3: Local file (only for development/testing)
        // If GitHub or server_url are not configured, updates/update-info.json will be used
        
        // Enable automatic update checking
        'auto_check' => true,
        
        // Automatic check interval in hours (only if auto_check is true)
        'check_interval' => 24
    ]
];
