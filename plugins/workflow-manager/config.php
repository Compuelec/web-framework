<?php

/**
 * Workflow Manager Plugin Configuration
 */

return [
    'plugin' => [
        'name' => 'Workflow Manager',
        'version' => '1.0.0',
        'description' => 'Administrador visual de workflows para personalizar estados y transiciones',
        'author' => 'Web Framework'
    ],
    'settings' => [
        'allow_delete_states' => true,
        'allow_custom_colors' => true,
        'max_states_per_workflow' => 10,
        'max_transitions_per_workflow' => 20
    ]
];
