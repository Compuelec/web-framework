# Data Protection (Ley 21.719) — Implementation

A generic, configurable plugin to help a project comply with Chile's Personal
Data Protection law (Ley 21.719). Everything is configured **visually from the
CMS** — no file editing. **Technical aid, not legal advice.**

## What it does

- **Configuración**: pick a table and mark, with checkboxes, which columns are
  personal data, which identify the subject (email/RUT), which are sensitive, and
  the anonymization strategy — plus purpose, legal basis, recipients and
  retention. Stored in `dp_datasets`; `config.php` is only an optional fallback.
- **Buscar titular**: find a person across every configured table and **export**
  their data as JSON (access + portability) or **erase / anonymize** it
  (cancellation), atomically and recorded in `activity_logs`.
- **Solicitudes ARCOP**: a request inbox (access, rectification, cancellation,
  opposition, portability, blocking) with auto-computed legal **due dates**.
- **RAT** (Registro de Actividades de Tratamiento): the accountability registry,
  generated from the configured tables, with print/PDF export.
- **Consentimientos**: a consent log; register/withdraw from the CMS, and consents
  captured on the public site are recorded automatically. Table `dp_consents`.
- **Cookies**: configure the public cookie banner (text, policy link, labels) and
  copy a one-line snippet into the public template. The banner records each
  visitor's choice. Settings in `dp_settings`, served by `public.php`.

## Files

```
plugins/data-protection/
├── data-protection.php                 # plugin entry / docs
├── config.example.php                  # optional fallback mapping (UI is primary)
├── config.php                          # local, gitignored
├── ajax.php                            # CMS dispatch: auth + role + CSRF
├── public.php                          # PUBLIC endpoint: cookie banner + web-form consent
├── controllers/data-protection.controller.php
├── views/main.php                      # CMS UI (6 tabs)
├── assets/js/data-protection.js
├── assets/css/data-protection.css
├── assets/public/cookie-banner.js      # drop-in banner for the public site
└── migrations/*.sql                    # dp_datasets / dp_requests (auto-created too)
cms/views/pages/custom/data-protection/data-protection.php   # CMS page wrapper
plugins/plugins-registry.php                                 # registers it
```

Plugin tables (auto-created): `dp_datasets`, `dp_requests`, `dp_consents`, `dp_settings`.

## Install

1. Registered in `plugins/plugins-registry.php` (`url: data-protection`).
2. Create a CMS page so it shows in the sidebar:
   `type_page = custom`, `url_page = data-protection`, `icon = bi-shield-lock`.
3. Open the page → **Configuración** → mark which tables hold personal data.
4. (Optional) **Cookies** tab → set the banner text and add the snippet to the
   public site template.

## Security

- All table/column names are validated as `^[a-zA-Z0-9_]+$` **and** checked
  against the real schema; every value is a bound parameter.
- `ajax.php` requires an admin session, a role in `roles_allowed`, and a CSRF
  token on every write. `public.php` only logs consent and is guarded by a
  same-origin check; it never reads or deletes personal data.
- Erasures run in a single transaction (atomic) and are recorded in
  `activity_logs` for accountability.

## Roadmap (future)

- Retention/purge tool (auto-delete past the retention window) and a breach log
  with the 72-hour notification checklist.
- Encryption-at-rest helpers for sensitive columns.
