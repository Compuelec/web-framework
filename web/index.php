<?php
/**
 * Web Index File
 * 
 * This is the main entry point for the public website.
 * It demonstrates how to use the API controller to fetch data from dynamic tables.
 */

// Error handling
ini_set("display_errors", 1);
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/php_error_log");

// Load configuration
$configPath = __DIR__ . '/config.php';
$config = null;
if (file_exists($configPath)) {
    $config = require $configPath;
} else {
    $examplePath = __DIR__ . '/config.example.php';
    if (file_exists($examplePath)) {
        $config = require $examplePath;
    }
}

// Set timezone
$timezone = is_array($config) ? ($config['timezone'] ?? 'America/Santiago') : 'America/Santiago';
date_default_timezone_set($timezone);

// Load API controller
require_once __DIR__ . '/controllers/api.controller.php';

// Set base URL
$baseUrl = is_array($config) && isset($config['site']['base_url']) 
    ? $config['site']['base_url'] 
    : 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
$baseUrl = rtrim($baseUrl, '/') . '/';

// Site configuration
$siteName = is_array($config) && isset($config['site']['name']) 
    ? $config['site']['name'] 
    : 'My Website';

// Page configuration
$pageTitle = is_array($config) && isset($config['site']['title']) 
    ? $config['site']['title'] 
    : 'Home - My Website';
$pageDescription = is_array($config) && isset($config['site']['description']) 
    ? $config['site']['description'] 
    : 'Website description';

// Example: Fetch data from a dynamic table
// Replace 'your_table_name' with an actual table name from your CMS
$exampleData = null;
$exampleError = null;
$configError = false;

// Check if API configuration exists
try {
    $testConfig = require __DIR__ . '/config.php';
    if (!isset($testConfig['api']['base_url']) || empty($testConfig['api']['base_url']) ||
        !isset($testConfig['api']['key']) || empty($testConfig['api']['key'])) {
        $configError = true;
        $exampleError = 'API configuration is incomplete. Please configure api.base_url and api.key in web/config.php';
    }
} catch (Exception $e) {
    $configError = true;
    $exampleError = 'Configuration file not found. Please create web/config.php from web/config.example.php';
}

// Uncomment and modify the following to fetch real data:
/*
try {
    // Example: Get all records from a table
    $response = ApiController::getAll('your_table_name', '*', 'id_your_table', 'DESC', 0, 10);
    
    if ($response->status == 200) {
        $exampleData = $response->results;
    } else {
        $exampleError = $response->message ?? 'Error fetching data';
        if (strpos($exampleError, 'configuration') !== false) {
            $configError = true;
        }
    }
} catch (Exception $e) {
    $exampleError = 'Exception: ' . $e->getMessage();
    if (strpos($exampleError, 'configuration') !== false) {
        $configError = true;
    }
}
*/

// Page content
ob_start();
?>
<div class="container my-5">
    <div class="row">
        <div class="col-lg-12">
            <div class="jumbotron bg-light p-5 rounded">
                <h1 class="display-4">Welcome to <?php echo htmlspecialchars($siteName); ?></h1>
                <p class="lead">This is a base template for using the Dynamic CMS Framework.</p>
                <hr class="my-4">
                <p>This example demonstrates how to integrate the CMS dynamic tables with your public website.</p>
                
                <?php if ($configError): ?>
                    <div class="alert alert-danger mt-4">
                        <h5><i class="fas fa-exclamation-triangle"></i> Configuration Required</h5>
                        <p><strong>Error:</strong> <?php echo htmlspecialchars($exampleError); ?></p>
                        <hr>
                        <p><strong>To fix this:</strong></p>
                        <ol class="mb-0">
                            <li>Copy <code>web/config.example.php</code> to <code>web/config.php</code></li>
                            <li>Edit <code>web/config.php</code> and configure:
                                <ul>
                                    <li><code>api.base_url</code> - Your API base URL (e.g., <code>http://localhost/web-framework/api/</code>)</li>
                                    <li><code>api.key</code> - Your API key (same as in <code>api/config.php</code>)</li>
                                </ul>
                            </li>
                            <li>Make sure the API is running and accessible</li>
                        </ol>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mt-4">
                        <h5><i class="fas fa-info-circle"></i> Getting Started</h5>
                        <ul class="mb-0">
                            <li>Configure your API settings in <code>web/config.php</code></li>
                            <li>Check the example pages in <code>web/pages/</code> directory</li>
                            <li>Use <code>ApiController</code> to fetch data from dynamic tables</li>
                            <li>See <code>.cursor/docs/WEB_GUIDE.md</code> for detailed documentation</li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-table"></i> Example Table</h5>
                                <p class="card-text">See how to display data in a table format.</p>
                                <a href="<?php echo $baseUrl; ?>pages/example-table.php" class="btn btn-primary">View Example</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-list"></i> Example List</h5>
                                <p class="card-text">See how to display data in a list format.</p>
                                <a href="<?php echo $baseUrl; ?>pages/example-list.php" class="btn btn-primary">View Example</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-file-alt"></i> Example Detail</h5>
                                <p class="card-text">See how to display a single record detail.</p>
                                <a href="<?php echo $baseUrl; ?>pages/example-detail.php" class="btn btn-primary">View Example</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($exampleData !== null): ?>
                    <div class="mt-4">
                        <h3>Example Data</h3>
                        <pre><?php print_r($exampleData); ?></pre>
                    </div>
                <?php endif; ?>
                
                <?php if ($exampleError !== null): ?>
                    <div class="alert alert-warning mt-4">
                        <strong>Note:</strong> <?php echo htmlspecialchars($exampleError); ?>
                        <br><small>This is expected if you haven't configured a table name yet.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();

// Include template
include __DIR__ . '/views/template.php';
?>

