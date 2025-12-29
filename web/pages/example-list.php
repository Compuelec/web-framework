<?php
/**
 * Example: Display Data in List Format
 * 
 * This example shows how to fetch filtered data from a dynamic table
 * and display it in a card/list format.
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

$pageTitle = 'Example List - ' . $siteName;
$pageDescription = 'Example of displaying dynamic table data in list format';

// Configuration: Change this to your actual table name
$tableName = 'pages';      // Replace with your table name
$idColumn = 'id_page';         // Replace with your ID column name
$titleColumn = 'title_page';      // Replace with your title column name
$descriptionColumn = 'description_column'; // Replace with your description column name

// Fetch data from API
$data = [];
$error = null;
$total = 0;

try {
    // Example 1: Get all records
    // $response = ApiController::getAll($tableName, '*', $idColumn, 'DESC', 0, 10);
    
    // Example 2: Get filtered records (uncomment to use)
    // $response = ApiController::getByFilter($tableName, 'status', 'active', '*', $idColumn, 'DESC');
    
    // Example 3: Search records (uncomment to use)
    // $response = ApiController::search($tableName, $titleColumn, 'search term', '*', $idColumn, 'DESC', 0, 10);
    
    // For this example, we'll use getAll
    $response = ApiController::getAll($tableName, '*', $idColumn, 'DESC', 0, 10);
    
    if ($response->status == 200) {
        $data = $response->results;
        $total = $response->total ?? count($data);
    } else {
        $error = $response->message ?? 'Error fetching data';
    }
} catch (Exception $e) {
    $error = 'Exception: ' . $e->getMessage();
}

// Page content
ob_start();
?>
<div class="container my-5">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="mb-4"><i class="fas fa-list"></i> Example: List Display</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Configuration Required</h5>
                    <p><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
                    <p>To use this example:</p>
                    <ol>
                        <li>Open <code>web/pages/example-list.php</code></li>
                        <li>Configure the table name and column names</li>
                        <li>Make sure your API is configured in <code>web/config.php</code></li>
                    </ol>
                </div>
            <?php elseif (empty($data)): ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> No Data Found</h5>
                    <p>The table exists but has no records. Add some data through the CMS.</p>
                </div>
            <?php else: ?>
                <div class="mb-3">
                    <p class="text-muted">Showing <?php echo count($data); ?> of <?php echo $total; ?> records</p>
                </div>
                
                <div class="row">
                    <?php foreach ($data as $record): 
                        $recordArray = (array)$record;
                        $recordId = $recordArray[$idColumn] ?? null;
                        $recordTitle = $recordArray[$titleColumn] ?? 'No Title';
                        $recordDescription = $recordArray[$descriptionColumn] ?? '';
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($recordTitle); ?>
                                    </h5>
                                    <?php if ($recordDescription): ?>
                                        <p class="card-text">
                                            <?php echo htmlspecialchars(substr($recordDescription, 0, 100)); ?>
                                            <?php echo strlen($recordDescription) > 100 ? '...' : ''; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Display other fields -->
                                    <div class="small text-muted mt-2">
                                        <?php 
                                        foreach ($recordArray as $key => $value):
                                            if (!in_array($key, [$idColumn, $titleColumn, $descriptionColumn]) && !empty($value)):
                                        ?>
                                            <div><strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> 
                                                <?php echo htmlspecialchars($value); ?>
                                            </div>
                                        <?php 
                                            endif;
                                        endforeach;
                                        ?>
                                    </div>
                                </div>
                                <?php if ($recordId): ?>
                                    <div class="card-footer">
                                        <a href="<?php echo $baseUrl; ?>pages/example-detail.php?id=<?php echo urlencode($recordId); ?>" 
                                           class="btn btn-sm btn-primary">
                                            View Details <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <h3>Code Examples</h3>
                <div class="card bg-light">
                    <div class="card-body">
                        <h5>Get All Records</h5>
                        <pre><code><?php echo htmlspecialchars('$response = ApiController::getAll(\'table_name\', \'*\', \'id_table\', \'DESC\', 0, 10);'); ?></code></pre>
                        
                        <h5 class="mt-3">Get Filtered Records</h5>
                        <pre><code><?php echo htmlspecialchars('$response = ApiController::getByFilter(\'table_name\', \'status\', \'active\', \'*\', \'id_table\', \'DESC\');'); ?></code></pre>
                        
                        <h5 class="mt-3">Search Records</h5>
                        <pre><code><?php echo htmlspecialchars('$response = ApiController::search(\'table_name\', \'title_column\', \'search term\', \'*\', \'id_table\', \'DESC\', 0, 10);'); ?></code></pre>
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

