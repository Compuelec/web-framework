<?php
// Sitemap controller — serves cached sitemap.xml; regenerates on ?regenerate=1 or when called
// directly by SeoController::regenerateSitemap()

$configPath = __DIR__ . '/config.php';
$config     = file_exists($configPath) ? require $configPath : (file_exists(__DIR__ . '/config.example.php') ? require __DIR__ . '/config.example.php' : []);

require_once __DIR__ . '/controllers/api.controller.php';

$cacheFile = __DIR__ . '/sitemap.xml';
$isRegenerate = isset($_GET['regenerate']);
$isCli        = php_sapi_name() === 'cli';

// Serve cached file if it exists and this is not a regeneration request
if (!$isRegenerate && file_exists($cacheFile)) {
    header('Content-Type: application/xml; charset=utf-8');
    readfile($cacheFile);
    exit;
}

// Build sitemap from live data
try {
    // Fetch global SEO settings for the canonical base URL
    $settingsResponse = ApiController::getAll('cms_settings', '*', 'id_setting', 'ASC', 0, 500);
    $canonicalBase    = '';

    if ($settingsResponse->status === 200 && !empty($settingsResponse->results)) {
        foreach ($settingsResponse->results as $row) {
            if ($row->key_setting === 'seo_canonical_base_url') {
                $canonicalBase = rtrim($row->value_setting ?? '', '/');
                break;
            }
        }
    }

    // Fetch all page_seo records joined with pages
    $seoResponse = ApiController::getAll('page_seo', '*', 'id_seo', 'ASC', 0, 1000);

    $urls = [];

    if ($seoResponse->status === 200 && !empty($seoResponse->results)) {
        // Fetch pages to filter by status
        $pagesResponse = ApiController::getAll('pages', '*', 'id_page', 'ASC', 0, 1000);
        $activePageIds = [];

        if ($pagesResponse->status === 200 && !empty($pagesResponse->results)) {
            foreach ($pagesResponse->results as $page) {
                // Include all pages unless explicitly marked inactive/draft
                $status = strtolower($page->status_page ?? 'active');
                if (!in_array($status, ['inactive', 'draft', 'hidden'], true)) {
                    $activePageIds[(int)$page->id_page] = true;
                }
            }
        }

        foreach ($seoResponse->results as $row) {
            $pageId = (int)($row->id_page_seo ?? 0);

            // Skip pages not in the active set (when pages data is available)
            if (!empty($activePageIds) && !isset($activePageIds[$pageId])) {
                continue;
            }

            $slug     = $row->slug_seo ?? '';
            $updated  = $row->date_updated_seo ?? date('Y-m-d');
            $lastmod  = substr($updated, 0, 10); // YYYY-MM-DD

            if ($slug === '') {
                continue;
            }

            $loc = $canonicalBase ? $canonicalBase . '/' . htmlspecialchars($slug, ENT_XML1) : '/' . htmlspecialchars($slug, ENT_XML1);

            $urls[] = [
                'loc'        => $loc,
                'lastmod'    => htmlspecialchars($lastmod, ENT_XML1),
                'changefreq' => 'monthly',
                'priority'   => '0.8',
            ];
        }
    }

    // Build XML
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

    foreach ($urls as $entry) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>'        . $entry['loc']        . '</loc>'        . PHP_EOL;
        $xml .= '    <lastmod>'    . $entry['lastmod']    . '</lastmod>'    . PHP_EOL;
        $xml .= '    <changefreq>' . $entry['changefreq'] . '</changefreq>' . PHP_EOL;
        $xml .= '    <priority>'   . $entry['priority']   . '</priority>'   . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }

    $xml .= '</urlset>' . PHP_EOL;

    // Write cache atomically
    $tmpFile = $cacheFile . '.tmp';
    file_put_contents($tmpFile, $xml);
    rename($tmpFile, $cacheFile);

    // Output if this is a direct HTTP request (not a background regeneration)
    if (!$isRegenerate || !$isCli) {
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
    }

} catch (Exception $e) {
    // Never expose DB errors in the sitemap response
    if (!$isRegenerate) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><body><h1>503 Service Unavailable</h1><p>Sitemap temporarily unavailable.</p></body></html>';
    }
}
