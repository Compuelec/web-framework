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
        // Opción 1: GitHub Releases/Tags (recomendado)
        // El sistema verificará automáticamente los releases/tags en GitHub
        'github_owner' => 'tu-usuario-github',        // Usuario u organización de GitHub
        'github_repo' => 'tu-repositorio',            // Nombre del repositorio
        'github_token' => null,                       // Token de GitHub (opcional, solo para repos privados)
                                                      // Genera un token en: https://github.com/settings/tokens
        
        // Opción 2: Servidor de actualizaciones personalizado (alternativa a GitHub)
        // Si se configuran github_owner y github_repo, esta opción se ignora
        'server_url' => 'https://updates.yourframework.com/api/check',
        
        // Opción 3: Archivo local (solo para desarrollo/testing)
        // Si no se configura GitHub ni server_url, se usará updates/update-info.json
        
        // Habilitar verificación automática de actualizaciones
        'auto_check' => true,
        
        // Intervalo de verificación automática en horas (solo si auto_check es true)
        'check_interval' => 24
    ]
];
