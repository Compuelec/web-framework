<?php

/**
 * Pages Setup Controller
 * 
 * Handles automatic creation of custom page files
 */

require_once __DIR__ . '/curl.controller.php';

class PagesSetupController {
    
    /**
     * Ensure all custom pages have their files created
     * 
     * @return array Result with success status
     */
    public static function ensureCustomPagesFiles() {
        // Get all custom pages from database
        $url = "pages?linkTo=type_page&equalTo=custom";
        $method = "GET";
        $fields = array();
        
        $pages = CurlController::request($url, $method, $fields);
        
        if (!$pages || !isset($pages->status) || $pages->status != 200 || 
            !isset($pages->results) || !is_array($pages->results)) {
            return [
                'success' => false,
                'message' => 'Could not fetch pages from database'
            ];
        }
        
        $created = 0;
        $failed = 0;
        $customPagesDir = __DIR__ . '/../views/pages/custom';
        $templateFile = $customPagesDir . '/custom.php';
        
        foreach ($pages->results as $page) {
            if (!isset($page->url_page) || empty($page->url_page)) {
                continue;
            }
            
            $pageDir = $customPagesDir . '/' . $page->url_page;
            $pageFile = $pageDir . '/' . $page->url_page . '.php';
            
            // Skip if file already exists
            if (file_exists($pageFile)) {
                continue;
            }
            
            // Try to create directory
            if (!file_exists($pageDir)) {
                // Check if parent directory is writable
                if (!is_writable($customPagesDir)) {
                    $failed++;
                    error_log("Warning: Cannot create page directory for '{$page->url_page}'. Parent directory is not writable: {$customPagesDir}");
                    continue;
                }
                
                if (!@mkdir($pageDir, 0755, true)) {
                    $failed++;
                    error_log("Warning: Failed to create directory for page '{$page->url_page}': {$pageDir}");
                    continue;
                }
            }
            
            // Create page file
            if (file_exists($templateFile)) {
                if (@copy($templateFile, $pageFile)) {
                    $created++;
                } else {
                    $failed++;
                    error_log("Warning: Failed to copy template for page '{$page->url_page}': {$pageFile}");
                }
            } else {
                // Create basic page file
                $content = "<?php\n\n/**\n * Custom Page: " . htmlspecialchars($page->title_page ?? $page->url_page) . "\n */\n\n?>\n\n<div class=\"container-fluid p-4\">\n\t<h2><?php echo htmlspecialchars(\$page->results[0]->title_page ?? 'Custom Page'); ?></h2>\n\t<p>This is a custom page. Edit this file to customize its content.</p>\n</div>\n";
                if (@file_put_contents($pageFile, $content)) {
                    $created++;
                } else {
                    $failed++;
                    error_log("Warning: Failed to create file for page '{$page->url_page}': {$pageFile}");
                }
            }
        }
        
        return [
            'success' => true,
            'created' => $created,
            'failed' => $failed,
            'message' => "Created {$created} page files" . ($failed > 0 ? ", {$failed} failed" : "")
        ];
    }
    
    /**
     * Ensure a specific custom page file exists
     * 
     * @param string $urlPage URL of the page
     * @return bool Success status
     */
    public static function ensureCustomPageFile($urlPage) {
        $customPagesDir = __DIR__ . '/../views/pages/custom';
        $pageDir = $customPagesDir . '/' . $urlPage;
        $pageFile = $pageDir . '/' . $urlPage . '.php';
        
        // Return true if file already exists
        if (file_exists($pageFile)) {
            return true;
        }
        
        // Check if parent directory is writable
        if (!is_writable($customPagesDir)) {
            return false;
        }
        
        // Create directory if needed
        if (!file_exists($pageDir)) {
            if (!@mkdir($pageDir, 0755, true)) {
                return false;
            }
        }
        
        // Create file
        $templateFile = $customPagesDir . '/custom.php';
        if (file_exists($templateFile)) {
            return @copy($templateFile, $pageFile);
        } else {
            $content = "<?php\n\n/**\n * Custom Page: " . htmlspecialchars($urlPage) . "\n */\n\n?>\n\n<div class=\"container-fluid p-4\">\n\t<h2>Custom Page</h2>\n\t<p>This is a custom page. Edit this file to customize its content.</p>\n</div>\n";
            return @file_put_contents($pageFile, $content) !== false;
        }
    }
}

