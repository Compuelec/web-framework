<?php

/**
 * POS Manager plugin — generic, configurable point-of-sale.
 *
 * Confirms sales and decrements product stock atomically (single DB transaction
 * with a conditional UPDATE so stock can never go negative). It is data-agnostic:
 * map it to your tables in config.php (see config.example.php).
 *
 * Integration:
 *   - Cashier screen: a CMS page (type_page = custom, url_page = pos-manager)
 *     whose wrapper includes views/main.php.
 *   - AJAX:           ajax.php (search_products / create_sale / get_receipt).
 *   - Logic:          controllers/pos-manager.controller.php.
 *
 * This plugin ships no schema; the products/sales tables are ordinary data
 * tables created with the CMS module builder and referenced from config.php.
 */
