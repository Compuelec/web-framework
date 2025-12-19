<?php

// Error handling
define('DIR',__DIR__);

ini_set("display_errors", 0);
ini_set("log_errors", 1);
ini_set("error_log", DIR."/php_error_log");

// CORS configuration
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('content-type: application/json; charset=utf-8');

// Handle preflight requests
if($_SERVER['REQUEST_METHOD'] == 'OPTIONS'){
	http_response_code(200);
	exit();
}

require_once "controllers/routes.controller.php";

$index = new RoutesController();
$index -> index();