<?php

// Error handling
define('DIR',__DIR__);

ini_set("display_errors", 0);
ini_set("log_errors", 1);
ini_set("error_log", DIR."/php_error_log");

// CORS configuration — restrict to allowed origins defined in config
$apiConfig = require DIR . '/config.php';
$allowedOrigins = $apiConfig['api']['allowed_origins'] ?? [];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('content-type: application/json; charset=utf-8');

// Handle preflight requests
if($_SERVER['REQUEST_METHOD'] == 'OPTIONS'){
	http_response_code(200);
	exit();
}

try {
	require_once "controllers/routes.controller.php";

	$index = new RoutesController();
	$index -> index();
} catch (Exception $e) {
	error_log("API Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
	$json = array('status' => 500, 'results' => 'Internal server error');
	http_response_code(500);
	echo json_encode($json);
} catch (Error $e) {
	error_log("API Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
	$json = array('status' => 500, 'results' => 'Internal server error');
	http_response_code(500);
	echo json_encode($json);
}