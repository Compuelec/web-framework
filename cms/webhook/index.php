<?php 

// Load configuration
$configPath = __DIR__ . '/../config.php';
$config = null;
if(file_exists($configPath)){
	$config = require $configPath;
}

if(!is_array($config)){
	$examplePath = __DIR__ . '/../config.example.php';
	$config = file_exists($examplePath) ? require $examplePath : [];
}

if(is_array($config)){
	$config['webhook']['token'] = getenv('WEBHOOK_TOKEN') ?: ($config['webhook']['token'] ?? '');
}

$token = $config['webhook']['token'] ?? '';

if($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["hub_verify_token"])){

	// Use hash_equals to prevent timing attacks when comparing tokens
	if(!empty($token) && hash_equals($token, $_GET["hub_verify_token"])){

		echo $_GET["hub_challenge"];

		exit;

	}else{

		http_response_code(403);
		echo "Invalid token";
		exit;
	}

}

// Receive response from WhatsApp API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	// Get JSON content
	$input = file_get_contents('php://input');

	// Log to PHP error log instead of a file inside the web root
	error_log("[webhook] " . $input);

}

 ?>