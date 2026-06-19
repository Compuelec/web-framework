<?php
/**
 * Root entry point.
 *
 * Serves the PUBLIC site (web/) at the domain root, so a deployed app lives at
 * https://your-domain/ with the admin at /cms and the API at /api. The public
 * home page is whatever you mark as "home" in the page builder; clean URLs
 * (/slug) are routed to the public pages by the .htaccess.
 *
 * (Previously this redirected to /cms. To go back to an admin-first root, replace
 * the require below with a redirect to /cms.)
 */

require __DIR__ . '/web/index.php';
