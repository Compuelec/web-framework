<?php

require_once "models/connection.php";
require_once "controllers/get.controller.php";

$requestPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/api/index.php")), "/");

if($basePath !== "" && $basePath !== "/" && substr($requestPath, 0, strlen($basePath)) === $basePath){
	$requestPath = substr($requestPath, strlen($basePath));
}

$requestPath = trim($requestPath, "/");
$routesArray = $requestPath === "" ? [] : explode("/", $requestPath);

// No API request
if(count($routesArray) == 0){

	$json = array(

		'status' => 404,
		'results' => 'Not Found'

	);

	http_response_code($json["status"]);
	echo json_encode($json);

	return;

}

// API request handling
if(count($routesArray) == 1 && isset($_SERVER['REQUEST_METHOD'])){

	$table = $routesArray[0];

	// Handle special routes (plugins, services, etc.)
	if($table == "payku"){
		include "services/payku.php";
		return;
	}

	$headers = function_exists("getallheaders") ? getallheaders() : [];
	$authorization = $headers["Authorization"] ?? ($_SERVER["HTTP_AUTHORIZATION"] ?? ($_SERVER["REDIRECT_HTTP_AUTHORIZATION"] ?? null));

	// Validate API key
	if(!$authorization || $authorization != Connection::apikey()){
		
		if(in_array($table, Connection::publicAccess()) == 0){
	
			$json = array(
		
				'status' => 400,
				"results" => "You are not authorized to make this request"
			);

			http_response_code($json["status"]);
	echo json_encode($json);

			return;

		}else{
			// Public access
			$response = new GetController();
			$response -> getData($table, "*",null,null,null,null);

			return;
		}
	
	}

	// GET requests
	if($_SERVER['REQUEST_METHOD'] == "GET"){

		include "services/get.php";

	}

	// POST requests
	if($_SERVER['REQUEST_METHOD'] == "POST"){
		
		include "services/post.php";

	}

	// PUT requests
	if($_SERVER['REQUEST_METHOD'] == "PUT"){

		include "services/put.php";

	}

	// DELETE requests
	if($_SERVER['REQUEST_METHOD'] == "DELETE"){

		include "services/delete.php";

	}

}


