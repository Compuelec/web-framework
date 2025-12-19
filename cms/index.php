<?php 
// Error handling
define('DIR',__DIR__);

ini_set("display_errors", 1);
ini_set("log_errors", 1);
ini_set("error_log", DIR."/php_error_log");

// Load configuration for timezone
$configPath = __DIR__ . '/config.php';
if(file_exists($configPath)){
	$config = require $configPath;
	$timezone = $config['timezone'] ?? 'America/Santiago';
} else {
	$timezone = 'America/Santiago';
}
date_default_timezone_set($timezone);

require_once "controllers/template.controller.php";
require_once "controllers/curl.controller.php";
require_once "extensions/vendor/autoload.php";

$index = new TemplateController();
$index -> index();

?>