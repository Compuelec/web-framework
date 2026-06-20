<?php

/**
 * Production Manager plugin — generic, configurable manufacturing.
 *
 * Produces N units of a product, consuming the supplies (insumos) defined in its
 * recipe and increasing the product's stock — all in one atomic DB transaction
 * with conditional UPDATEs so supply stock can never go negative. Data-agnostic:
 * map it to your tables in config.php (see config.example.php).
 *
 * Integration:
 *   - Screen: a CMS page (type_page = custom, url_page = production-manager)
 *     whose wrapper includes views/main.php.
 *   - AJAX:   ajax.php (search_products / get_recipe / produce).
 *   - Logic:  controllers/production-manager.controller.php.
 *
 * Ships no schema; products/supplies/recipes/production are ordinary data tables
 * created with the CMS module builder and referenced from config.php.
 */
