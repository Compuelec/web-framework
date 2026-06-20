<?php

/**
 * Export Data AJAX Endpoint
 * Handles data export in different formats
 */

// Define constant to indicate session-init is being included
define('SESSION_INIT_INCLUDED', true);

require_once __DIR__ . '/session-init.php';

require_once "../controllers/curl.controller.php";
require_once "../controllers/template.controller.php";

// Check if user is authenticated
if (!isset($_SESSION["admin"])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$module = isset($_GET['module']) ? $_GET['module'] : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($module)) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

try {
    // A page usually has several modules sharing the same title_module (e.g. a
    // breadcrumbs module plus the table module). Fetch them all and pick the
    // TABLE module with a real suffix — grabbing results[0] blindly can land on
    // the breadcrumbs row (empty suffix), which builds an invalid "id_" order
    // column and makes the API return 404 (surfacing here as a silent 500).
    $urlModule = "modules?linkTo=title_module&equalTo=" . urlencode($module) . "&select=id_module,suffix_module,type_module";
    $method = "GET";
    $fields = array();

    $moduleInfo = CurlController::request($urlModule, $method, $fields);

    $suffix = 'id';
    $moduleId = 0;

    if (is_object($moduleInfo) && $moduleInfo->status == 200 && !empty($moduleInfo->results)) {
        $chosen = null;
        foreach ($moduleInfo->results as $m) {
            $s = trim((string)($m->suffix_module ?? ''));
            if ($s === '') { continue; }
            // Prefer the tables module; otherwise the first one with a suffix.
            if (($m->type_module ?? '') === 'tables') { $chosen = $m; break; }
            if ($chosen === null) { $chosen = $m; }
        }
        if ($chosen !== null) {
            $suffix   = trim((string)$chosen->suffix_module);
            $moduleId = $chosen->id_module ?? 0;
        }
    }
    
    // Get all data from the table
    $url = $module . "?orderBy=id_" . $suffix . "&orderMode=DESC";
    
    // Apply filters if provided
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $url .= "&search=" . urlencode($_GET['search']);
    }
    
    if (isset($_GET['between1']) && isset($_GET['between2'])) {
        $url .= "&between1=" . urlencode($_GET['between1']) . "&between2=" . urlencode($_GET['between2']);
    }
    
    $data = CurlController::request($url, $method, $fields);
    
    if ($data->status != 200 || !isset($data->results)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    }
    
    // Get module columns ($moduleId was resolved above with the suffix)
    $urlColumns = "columns?linkTo=id_module_column&equalTo=" . $moduleId;
    $columns = CurlController::request($urlColumns, $method, $fields);
    
    $columnList = [];
    if ($columns->status == 200 && isset($columns->results)) {
        $columnList = $columns->results;
    }
    
    $visibleColumns = array_filter($columnList, function($col) {
        return $col->visible_column == 1;
    });
    
    // Prepare data
    $exportData = [];
    $headers = ['#'];
    
    foreach ($visibleColumns as $col) {
        $headers[] = $col->alias_column ?: $col->title_column;
    }
    
    $exportData[] = $headers;
    
    foreach ($data->results as $index => $row) {
        $rowData = [$index + 1];
        
        foreach ($visibleColumns as $col) {
            $value = $row->{$col->title_column} ?? '';
            
            if (is_string($value)) {
                $value = urldecode($value);
            }
            
            // Format according to type
            if ($col->type_column == 'boolean') {
                $value = $value == 1 ? 'Sí' : 'No';
            } else if ($col->type_column == 'money' || $col->type_column == 'double') {
                $value = is_numeric($value) ? number_format($value, 2) : $value;
            } else if ($col->type_column == 'image' || $col->type_column == 'file' || $col->type_column == 'video') {
                $value = $value ?: 'No file';
            }
            
            // Clean HTML
            $value = strip_tags($value);
            
            $rowData[] = $value;
        }
        
        $exportData[] = $rowData;
    }
    
    // Return the prepared data as JSON. The browser builds the CSV / styled XLSX
    // / styled PDF client-side with locally-vendored libraries (no CDN), so there
    // is no popup and the files come out formatted (see export-data.js).
    $colTypes = ['index'];
    foreach ($visibleColumns as $col) {
        $colTypes[] = $col->type_column;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'title'   => $module,
        'rows'    => $exportData,   // rows[0] = headers, the rest are data
        'types'   => $colTypes,     // aligned with each column ('index' = #)
    ]);
    exit;

} catch (Exception $e) {
    Logger::error("Export AJAX error", ['exception' => $e->getMessage()]);
    header('HTTP/1.1 500 Internal Server Error');
    echo "Internal server error";
}
