<?php

/**
 * Data Protection (Ley 21.719) — configuration template.
 *
 * Copy to `config.php` (kept local, gitignored) and map it to YOUR tables. The
 * plugin is generic: it lets you find, export, and erase/anonymize a data
 * subject's personal data across every table you declare here, track ARCOP
 * requests, and keep a Registry of Processing Activities (RAT).
 *
 * SECURITY: every `table`/column below is interpolated into SQL, so it MUST be a
 * bare SQL identifier (^[a-zA-Z0-9_]+$). All VALUES are bound parameters.
 *
 * This is a technical aid for compliance, NOT legal advice.
 */

return [

    // Tables that hold personal data. One entry per table.
    'datasets' => [
        [
            'table'        => 'clientes',
            'id'           => 'id_cliente',
            // Columns that identify a person — a subject is matched if ANY of
            // these equals the searched value (email, RUT, etc.).
            'subject_keys' => ['email_cliente', 'rut_cliente'],
            'label'        => 'Clientes',
            // Personal-data fields returned on access/portability export.
            'fields'       => ['nombre_cliente', 'email_cliente', 'rut_cliente', 'telefono_cliente', 'direccion_cliente'],
            // Subset of `fields` that are SENSITIVE (health, beliefs, etc.).
            'sensitive'    => [],
            // How to anonymize each column on erasure: 'null' | 'redact' | 'hash'.
            // Columns not listed are left untouched (e.g. keep an order's total).
            // NOTE: 'hash' writes a 64-char SHA-256 hex, so the column must be at
            // least VARCHAR(64); for short columns use 'null' or 'redact'.
            'anonymize'    => [
                'nombre_cliente'    => 'redact',
                'email_cliente'     => 'null',
                'rut_cliente'       => 'hash',
                'telefono_cliente'  => 'null',
                'direccion_cliente' => 'null',
            ],
            // Optional: retention window (days) for the purge tool.
            'retention_days' => 1825,
            // Optional: legal basis / purpose, surfaced in the RAT.
            'purpose'        => 'Gestión de ventas y postventa',
            'legal_basis'    => 'Ejecución de contrato',
        ],
    ],

    // Who may use the data-protection tools in the CMS.
    'roles_allowed' => ['superadmin', 'admin'],

    // Legal deadline (days) to answer an ARCOP request — used to compute due dates.
    'response_days' => 30,
];
