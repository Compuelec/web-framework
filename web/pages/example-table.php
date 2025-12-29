<?php
/**
 * Example: Display Data in Table Format
 * 
 * This example shows how to fetch data from a dynamic table
 * and display it in a Bootstrap table.
 */

// Error handling
ini_set("display_errors", 1);
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/../../php_error_log");

// Load configuration
$configPath = __DIR__ . '/../config.php';
$config = null;
if (file_exists($configPath)) {
    $config = require $configPath;
} else {
    $examplePath = __DIR__ . '/../config.example.php';
    if (file_exists($examplePath)) {
        $config = require $examplePath;
    }
}

$timezone = is_array($config) ? ($config['timezone'] ?? 'America/Santiago') : 'America/Santiago';
date_default_timezone_set($timezone);

// Load API controller
require_once __DIR__ . '/../controllers/api.controller.php';

// Set base URL
$baseUrl = is_array($config) && isset($config['site']['base_url']) 
    ? $config['site']['base_url'] 
    : 'http://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/';
$baseUrl = rtrim($baseUrl, '/') . '/';

$siteName = is_array($config) && isset($config['site']['name']) 
    ? $config['site']['name'] 
    : 'My Website';

$pageTitle = 'Example Table - ' . $siteName;
$pageDescription = 'Example of displaying dynamic table data';

// Configuration: Change this to your actual table name
$tableName = 'pages';  // Replace with your table name
$idColumn = 'id_page';      // Replace with your ID column name

// Fetch data from API
$data = [];
$error = null;
$total = 0;
$configError = false;

try {
    // Get all records with pagination
    // Parameters: table, select, orderBy, orderMode, startAt, endAt
    $response = ApiController::getAll($tableName, '*', $idColumn, 'DESC', 0, 10);
    
    if ($response->status == 200) {
        $data = $response->results;
        $total = $response->total ?? count($data);
    } else {
        $error = $response->message ?? 'Error fetching data';
        // Check if it's a configuration error
        if (strpos($error, 'configuration') !== false || strpos($error, 'config.php') !== false) {
            $configError = true;
        }
    }
} catch (Exception $e) {
    $error = 'Exception: ' . $e->getMessage();
    if (strpos($error, 'configuration') !== false || strpos($error, 'config.php') !== false) {
        $configError = true;
    }
}

// Page content
ob_start();
?>
<div class="container my-5">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="mb-4"><i class="fas fa-table"></i> Example: Table Display</h1>
            
            <?php if ($error): ?>
                <div class="alert <?php echo $configError ? 'alert-danger' : 'alert-warning'; ?>">
                    <h5><i class="fas fa-exclamation-triangle"></i> <?php echo $configError ? 'Configuration Error' : 'Error'; ?></h5>
                    <p><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
                    <?php if ($configError): ?>
                        <hr>
                        <p><strong>To fix this:</strong></p>
                        <ol>
                            <li>Copy <code>web/config.example.php</code> to <code>web/config.php</code></li>
                            <li>Edit <code>web/config.php</code> and configure:
                                <ul>
                                    <li><code>api.base_url</code> - Your API base URL</li>
                                    <li><code>api.key</code> - Your API key (same as in <code>api/config.php</code>)</li>
                                </ul>
                            </li>
                            <li>Make sure the API is running and accessible</li>
                        </ol>
                    <?php else: ?>
                        <p>To use this example:</p>
                        <ol>
                            <li>Open <code>web/pages/example-table.php</code></li>
                            <li>Change <code>$tableName</code> to your actual table name</li>
                            <li>Change <code>$idColumn</code> to your actual ID column name</li>
                            <li>Make sure your API is configured in <code>web/config.php</code></li>
                        </ol>
                    <?php endif; ?>
                </div>
            <?php elseif (empty($data)): ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> No Data Found</h5>
                    <p>The table exists but has no records. Add some data through the CMS.</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-database"></i> Data from Table: <?php echo htmlspecialchars($tableName); ?>
                            <span class="badge bg-primary ms-2"><?php echo $total; ?> records</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <?php 
                                        // Get column names from first record
                                        if (!empty($data)) {
                                            $firstRecord = (array)$data[0];
                                            foreach (array_keys($firstRecord) as $column): 
                                        ?>
                                            <th><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $column))); ?></th>
                                        <?php 
                                            endforeach;
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $record): ?>
                                        <tr>
                                            <?php 
                                            $recordArray = (array)$record;
                                            foreach ($recordArray as $value): 
                                            ?>
                                                <td><?php echo htmlspecialchars($value ?? ''); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <h3>Code Example</h3>
                <div class="card bg-light">
                    <div class="card-body">
                        <pre><code><?php echo htmlspecialchars('<?php
// Fetch data from API
$response = ApiController::getAll(\'your_table_name\', \'*\', \'id_your_table\', \'DESC\', 0, 10);

if ($response->status == 200) {
    $data = $response->results;
    // Display data...
}
?>'); ?></code></pre>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="<?php echo $baseUrl; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();

// Include template
include __DIR__ . '/../views/template.php';
?>

