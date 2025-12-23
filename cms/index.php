<?php 
// Error handling
define('DIR',__DIR__);

ini_set("display_errors", 1);
ini_set("log_errors", 1);
ini_set("error_log", DIR."/php_error_log");

// Load configuration for timezone
$configPath = __DIR__ . '/config.php';
$config = null;
if(file_exists($configPath)){
	$config = require $configPath;
}

if(!is_array($config)){
	$examplePath = __DIR__ . '/config.example.php';
	if(file_exists($examplePath)){
		$config = require $examplePath;
	}
}

$timezone = is_array($config) ? ($config['timezone'] ?? 'America/Santiago') : 'America/Santiago';
date_default_timezone_set($timezone);

require_once "controllers/template.controller.php";
require_once "controllers/curl.controller.php";
require_once "extensions/vendor/autoload.php";
require_once __DIR__ . "/../core/activity_log.php";

// Ensure required pages exist (only if database is configured)
if (is_array($config) && isset($config['database'])) {
    try {
        require_once "controllers/packaging-setup.controller.php";
        // Run in background, don't block if it fails
        @PackagingSetupController::ensurePackagingPage();
        
        // Ensure all custom pages have their files created
        require_once "controllers/pages-setup.controller.php";
        @PagesSetupController::ensureCustomPagesFiles();
    } catch (Exception $e) {
        // Silently fail - this is not critical for page load
        error_log("Warning: Could not ensure pages: " . $e->getMessage());
    }
}

$index = new TemplateController();
$index -> index();

?>
