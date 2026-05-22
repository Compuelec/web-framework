<?php
/**
 * Dashboard Manager — Configuration
 * Copy this file as config.php and adjust values
 */
return [
    'plugin' => [
        'name'    => 'Dashboard Manager',
        'version' => '1.0.0',
        'enabled' => true,
    ],
    // Tables to exclude from widget table selector
    'excluded_tables' => [
        'admins',
        'framework_migrations',
        'dashboard_widgets',
    ],
    // Maximum widgets per admin
    'max_widgets' => 20,
];
