# Data Protection (Ley 21.719) — Implementation

A generic, configurable plugin to help a project comply with Chile's Personal
Data Protection law (Ley 21.719): exercise data-subject rights across your tables
and track ARCOP requests. **Technical aid, not legal advice.**

## What it does (increment 1)

- **Buscar titular**: find a person by an identifier (email, RUT, …) across every
  table you declare as holding personal data.
- **Exportar** their data as structured JSON (right of **access** + **portability**).
- **Borrar / Anonimizar** their data across all tables atomically (right of
  **cancellation**): `delete` removes the rows; `anonymize` keeps them but scrubs
  the personal columns (`null` / `redact` / `hash`).
- **Solicitudes ARCOP**: a request inbox (access, rectification, cancellation,
  opposition, portability, blocking) with auto-computed legal **due dates** and
  status tracking. Stored in the plugin's own `dp_requests` table.

## Files

```
plugins/data-protection/
├── data-protection.php                 # plugin entry / docs
├── config.example.php                  # map YOUR personal-data tables (copy → config.php)
├── config.php                          # local, gitignored
├── ajax.php                            # auth + role + CSRF guarded dispatch
├── controllers/data-protection.controller.php
├── views/main.php                      # CMS UI (tabs: requests, subject search)
├── assets/js/data-protection.js
└── assets/css/data-protection.css
cms/views/pages/custom/data-protection/data-protection.php   # CMS page wrapper
plugins/plugins-registry.php                                 # registers it
```

## Install

1. Registered in `plugins/plugins-registry.php` (`url: data-protection`).
2. Copy `config.example.php` → `config.php` and declare each table that holds
   personal data: its `id`, the `subject_keys` (columns that identify a person),
   the `fields` to export, which are `sensitive`, and the `anonymize` strategy
   per column.
3. Create a CMS page so it shows in the sidebar:
   `type_page = custom`, `url_page = data-protection`, `icon = bi-shield-lock`.

## Security

- All table/column names from config are validated as `^[a-zA-Z0-9_]+$`; all
  values are bound parameters.
- `ajax.php` requires an admin session, a role in `roles_allowed`, and a CSRF
  token on every write (erase, create/update request).
- Erasures run in a single transaction (atomic) and are recorded in
  `activity_logs` for accountability.

## Roadmap (next increments)

- **RAT** (Registro de Actividades de Tratamiento): purpose, legal basis,
  retention and recipients per dataset.
- **Consent**: consent registry + valid checkbox in public web forms + cookie banner.
- **Retention/purge** and **breach log** (72h notification checklist).
