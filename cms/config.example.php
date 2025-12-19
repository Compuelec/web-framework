<?php

/**
 * CMS Configuration Example File
 * 
 * Copy this file to config.php and fill in your actual values.
 * The config.php file is ignored by git for security.
 */

return [
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
    ]
];
