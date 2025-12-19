<?php 

// Load configuration
$configPath = __DIR__ . '/../config.php';
if(file_exists($configPath)){
	$config = require $configPath;
} else {
	// Fallback to example or environment variable
	$examplePath = __DIR__ . '/../config.example.php';
	$config = file_exists($examplePath) ? require $examplePath : [];
	$config['webhook']['token'] = getenv('WEBHOOK_TOKEN') ?: ($config['webhook']['token'] ?? '');
}

$token = $config['webhook']['token'] ?? '';

if($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["hub_verify_token"])){

	if($_GET["hub_verify_token"] == $token){

		echo $_GET["hub_challenge"];

		exit;
	
	}else{

		echo "Token inválido";
        exit;
	}

}

// Receive response from WhatsApp API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	// Get JSON content
    $input = file_get_contents('php://input');

    file_put_contents("webhook_log.txt", $input."\n\n", FILE_APPEND); 

}

 ?>