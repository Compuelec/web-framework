<?php

/**
 * Payku Payment Gateway Plugin
 * Plugin Name: Payku Webpay
 * Description: Payment gateway integration with Payku (Visa, Mastercard, Magna, American Express, Diners and Redcompra)
 * Version: 1.0.0
 * Author: Payku Integration
 */

if (!defined('DIR')) {
    define('DIR', dirname(__DIR__, 2));
}

// Load Payku library
require_once __DIR__ . '/lib/paykulib.php';

// Load plugin controller
require_once __DIR__ . '/controllers/payku.controller.php';

// Initialize plugin
PaykuPlugin::init();

