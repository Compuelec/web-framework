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