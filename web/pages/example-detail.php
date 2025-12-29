<?php
/**
 * Example: Display Single Record Detail
 * 
 * This example shows how to fetch a single record by ID
 * and display its details.
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

// Configuration: Change this to your actual table name
$tableName = 'pages';  // Replace with your table name
$idColumn = 'id_page';     // Replace with your ID column name

// Get record ID from URL
$recordId = $_GET['id'] ?? null;

// Fetch data from API
$record = null;
$error = null;

if (!$recordId) {
    $error = 'No record ID provided';
} else {
    try {
        $response = ApiController::getById($tableName, $recordId, $idColumn);
        
        if ($response->status == 200 && !empty($response->results)) {
            $record = $response->results[0];
        } else {
            $error = $response->message ?? 'Record not found';
        }
    } catch (Exception $e) {
        $error = 'Exception: ' . $e->getMessage();
    }
}

$pageTitle = ($record ? 'Record Details' : 'Record Not Found') . ' - ' . $siteName;
$pageDescription = 'Example of displaying a single record detail';

// Page content
ob_start();
?>
<div class="container my-5">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="mb-4"><i class="fas fa-file-alt"></i> Example: Record Detail</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Error</h5>
                    <p><?php echo htmlspecialchars($error); ?></p>
                    <?php if ($error === 'No record ID provided'): ?>
                        <p>Usage: <code>example-detail.php?id=123</code></p>
                    <?php endif; ?>
                    <a href="<?php echo $baseUrl; ?>pages/example-list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            <?php elseif (!$record): ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Record Not Found</h5>
                    <p>The record with ID <?php echo htmlspecialchars($recordId); ?> was not found.</p>
                    <a href="<?php echo $baseUrl; ?>pages/example-list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            <?php else: 
                $recordArray = (array)$record;
            ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-database"></i> Record Details
                            <span class="badge bg-primary ms-2">ID: <?php echo htmlspecialchars($recordId); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <?php foreach ($recordArray as $key => $value): ?>
                                <dt class="col-sm-3">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?>
                                </dt>
                                <dd class="col-sm-9">
                                    <?php 
                                    if (is_null($value)) {
                                        echo '<span class="text-muted">(empty)</span>';
                                    } elseif (is_bool($value)) {
                                        echo $value ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>';
                                    } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                                        echo '<a href="' . htmlspecialchars($value) . '" target="_blank">' . htmlspecialchars($value) . ' <i class="fas fa-external-link-alt"></i></a>';
                                    } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                        echo '<a href="mailto:' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</a>';
                                    } else {
                                        echo htmlspecialchars($value);
                                    }
                                    ?>
                                </dd>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <h3>Code Example</h3>
                <div class="card bg-light">
                    <div class="card-body">
                        <pre><code><?php echo htmlspecialchars('<?php
// Get record ID from URL
$recordId = $_GET[\'id\'] ?? null;

// Fetch record by ID
$response = ApiController::getById(\'table_name\', $recordId, \'id_table\');

if ($response->status == 200 && !empty($response->results)) {
    $record = $response->results[0];
    // Display record...
}
?>'); ?></code></pre>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="<?php echo $baseUrl; ?>pages/example-list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-home"></i> Home
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

