<?php

/**
 * Global Search AJAX Endpoint
 * Handles global search across pages, modules, and data
 */

require_once __DIR__ . '/session-init.php';

require_once "../controllers/curl.controller.php";
require_once "../controllers/template.controller.php";

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION["admin"])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'results' => []
    ]);
    exit;
}

$term = isset($_POST['term']) ? trim($_POST['term']) : '';
$token = isset($_POST['token']) ? $_POST['token'] : '';

if (empty($term) || strlen($term) < 2) {
    echo json_encode([
        'success' => true,
        'results' => []
    ]);
    exit;
}

$results = [];
$cmsBasePath = TemplateController::cmsBasePath();

try {
    // Search in pages
    $url = "pages?search=" . urlencode($term) . "&select=id_page,title_page,url_page,type_page";
    $method = "GET";
    $fields = array();
    
    $pages = CurlController::request($url, $method, $fields);
    
    if ($pages->status == 200 && isset($pages->results)) {
        foreach ($pages->results as $page) {
            // Check permissions
            $hasPermission = false;
            if ($_SESSION["admin"]->rol_admin == "superadmin" || 
                $_SESSION["admin"]->rol_admin == "admin") {
                $hasPermission = true;
            } else if ($_SESSION["admin"]->rol_admin == "editor") {
                $permissions = json_decode(urldecode($_SESSION["admin"]->permissions_admin), true);
                if (isset($permissions[$page->url_page]) && $permissions[$page->url_page] == "on") {
                    $hasPermission = true;
                }
            }
            
            if ($hasPermission) {
                $results[] = [
                    'type' => 'page',
                    'title' => $page->title_page,
                    'description' => 'Page: ' . $page->url_page,
                    'url' => $cmsBasePath . '/' . $page->url_page,
                    'icon' => 'bi-file-text'
                ];
            }
        }
    }
    
    // Search in modules (only for superadmin)
    if ($_SESSION["admin"]->rol_admin == "superadmin") {
        $url = "modules?search=" . urlencode($term) . "&select=id_module,title_module,url_page";
        $modules = CurlController::request($url, $method, $fields);
        
        if ($modules->status == 200 && isset($modules->results)) {
            foreach ($modules->results as $module) {
                $results[] = [
                    'type' => 'module',
                    'title' => $module->title_module,
                    'description' => 'Module in: ' . $module->url_page,
                    'url' => $cmsBasePath . '/' . $module->url_page,
                    'icon' => 'bi-grid'
                ];
            }
        }
    }
    
    // Search in data tables (limited to first 5 results per table)
    // Get all modules that are tables
    $url = "modules?linkTo=type_module&equalTo=tables&select=title_module,suffix_module,url_page";
    $tableModules = CurlController::request($url, $method, $fields);
    
    if ($tableModules->status == 200 && isset($tableModules->results)) {
        foreach ($tableModules->results as $module) {
            // Check permissions for this module's page
            $hasPermission = false;
            if ($_SESSION["admin"]->rol_admin == "superadmin" || 
                $_SESSION["admin"]->rol_admin == "admin") {
                $hasPermission = true;
            } else if ($_SESSION["admin"]->rol_admin == "editor") {
                $permissions = json_decode(urldecode($_SESSION["admin"]->permissions_admin), true);
                if (isset($permissions[$module->url_page]) && $permissions[$module->url_page] == "on") {
                    $hasPermission = true;
                }
            }
            
            if ($hasPermission) {
                // Search in this table
                $tableName = $module->title_module;
                $url = $tableName . "?search=" . urlencode($term) . "&startAt=0&endAt=5";
                $tableData = CurlController::request($url, $method, $fields);
                
                if ($tableData->status == 200 && isset($tableData->results)) {
                    foreach ($tableData->results as $row) {
                        // Get first text field as title
                        $title = 'Record #' . $row->{'id_' . $module->suffix_module};
                        $rowArray = (array)$row;
                        foreach ($rowArray as $key => $value) {
                            if ($key != 'id_' . $module->suffix_module && 
                                !empty($value) && 
                                strlen($value) < 100) {
                                $title = urldecode($value);
                                break;
                            }
                        }
                        
                        $results[] = [
                            'type' => 'data',
                            'title' => $title,
                            'description' => 'In: ' . $module->title_module,
                            'url' => $cmsBasePath . '/' . $module->url_page . '/manage/' . base64_encode($row->{'id_' . $module->suffix_module}),
                            'icon' => 'bi-database'
                        ];
                    }
                }
            }
        }
    }
    
    // Limit total results to 20
    $results = array_slice($results, 0, 20);
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'results' => []
    ]);
}

