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

try {
	require_once "controllers/routes.controller.php";

	$index = new RoutesController();
	$index -> index();
} catch (Exception $e) {
	// Return error response
	$json = array(
		'status' => 500,
		'results' => 'Internal server error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
	);
	http_response_code(500);
	echo json_encode($json);
	error_log("API Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
} catch (Error $e) {
	// Return error response
	$json = array(
		'status' => 500,
		'results' => 'Internal server error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
	);
	http_response_code(500);
	echo json_encode($json);
	error_log("API Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}