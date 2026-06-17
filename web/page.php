<?php
// Public page router — resolves a slug to its SEO metadata and renders the template

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
    || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:');
ini_set('display_errors', $isLocal ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_log');

$configPath = __DIR__ . '/config.php';
$config     = null;
if (file_exists($configPath)) {
    $config = require $configPath;
} elseif (file_exists(__DIR__ . '/config.example.php')) {
    $config = require __DIR__ . '/config.example.php';
}

$timezone = is_array($config) ? ($config['timezone'] ?? 'America/Santiago') : 'America/Santiago';
date_default_timezone_set($timezone);

require_once __DIR__ . '/controllers/api.controller.php';

$baseUrl  = is_array($config) && isset($config['site']['base_url'])
    ? rtrim($config['site']['base_url'], '/') . '/'
    : 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';

$siteName   = is_array($config) && isset($config['site']['name'])   ? $config['site']['name']   : 'My Website';
$pageTitle  = is_array($config) && isset($config['site']['title'])  ? $config['site']['title']  : 'My Website';
$seoMeta    = null;
$seoSettings = [];

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_GET['slug']))) : '';

if ($slug !== '') {
    try {
        // Load the SEO record for this slug
        $seoResponse = ApiController::getByFilter('page_seo', 'slug_seo', $slug);

        if ($seoResponse->status === 200 && !empty($seoResponse->results)) {
            $seoMeta   = $seoResponse->results[0];
            $pageTitle = $seoMeta->meta_title_seo ?? $pageTitle;
        }

        // Load global SEO settings
        $settingsResponse = ApiController::getAll('cms_settings', '*', 'id_setting', 'ASC', 0, 500);
        if ($settingsResponse->status === 200 && !empty($settingsResponse->results)) {
            foreach ($settingsResponse->results as $row) {
                $seoSettings[$row->key_setting] = $row->value_setting;
            }
        }
    } catch (Exception $e) {
        error_log('page.php SEO lookup error: ' . $e->getMessage());
    }
}

// Page content placeholder — extend with real content lookup as needed
ob_start();
?>
<div class="container my-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <?php if ($seoMeta): ?>
                <h1><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="text-muted small">Slug: <code><?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?></code></p>
            <?php else: ?>
                <div class="alert alert-warning">
                    <strong>404 — Page not found</strong>
                    <p>No page found for slug <code><?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?></code>.</p>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();

include __DIR__ . '/views/template.php';
