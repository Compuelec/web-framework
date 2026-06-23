<?php

/**
 * Data Protection plugin (Ley 21.719) — generic, configurable privacy tools.
 *
 * Helps a project comply with Chile's Personal Data Protection law: find a data
 * subject across your tables, export their data (access + portability), erase or
 * anonymize it (cancellation), and track ARCOP requests with legal deadlines.
 *
 * Data-agnostic: declare which tables hold personal data, and the columns that
 * identify a person, in config.php (see config.example.php). The plugin creates
 * its own `dp_requests` table for the request log.
 *
 * Integration:
 *   - Screen: a CMS page (type_page = custom, url_page = data-protection)
 *     whose wrapper includes views/main.php.
 *   - AJAX:   ajax.php (find/export/erase subject; ARCOP request CRUD).
 *   - Logic:  controllers/data-protection.controller.php.
 *
 * This is a technical aid for compliance, NOT legal advice.
 */
