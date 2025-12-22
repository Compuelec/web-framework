<?php

/**
 * Packaging Setup Controller
 * 
 * Handles automatic creation of packaging page during installation and updates
 */

require_once __DIR__ . '/curl.controller.php';

class PackagingSetupController {
    
    /**
     * Ensure packaging page exists
     * Creates the page if it doesn't exist
     * 
     * @return array Result with success status
     */
    public static function ensurePackagingPage() {
        // Check if page already exists
        $url = "pages?linkTo=url_page&equalTo=packaging";
        $method = "GET";
        $fields = array();
        
        $existingPage = CurlController::request($url, $method, $fields);
        
        // If page exists, return success
        if ($existingPage && isset($existingPage->status) && $existingPage->status == 200 && 
            isset($existingPage->results) && is_array($existingPage->results) && count($existingPage->results) > 0) {
            return [
                'success' => true,
                'message' => 'Packaging page already exists',
                'created' => false
            ];
        }
        
        // Create the page
        $url = "pages?token=no&except=id_page";
        $method = "POST";
        $fields = array(
            "title_page" => "Empaquetado",
            "url_page" => "packaging",
            "icon_page" => "bi bi-box-seam",
            "type_page" => "custom",
            "order_page" => 6,
            "date_created_page" => date("Y-m-d")
        );
        
        $createPage = CurlController::request($url, $method, $fields);
        
        if ($createPage && isset($createPage->status) && $createPage->status == 200) {
            // Create directory and file
            $packagingDirectory = __DIR__ . '/../views/pages/custom/packaging';
            
            if (!file_exists($packagingDirectory)) {
                mkdir($packagingDirectory, 0755, true);
            }
            
            // Verify that the packaging.php file exists
            $packagingFile = $packagingDirectory . '/packaging.php';
            if (!file_exists($packagingFile)) {
                // File should exist from the package, but log if it doesn't
                error_log("Warning: packaging.php file not found at: " . $packagingFile);
            }
            
            return [
                'success' => true,
                'message' => 'Packaging page created successfully',
                'created' => true
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to create packaging page',
            'created' => false
        ];
    }
}

