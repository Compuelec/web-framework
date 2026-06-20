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

/**
 * Build an id → label map for a relations column, fetched once (not per row).
 * matrix_column is "admins", "tabla" or "tabla:columna" (display column).
 */
function exp_buildRelationMap($matrix) {
    $parts = explode(':', (string)$matrix, 2);
    $table = trim($parts[0]);
    $displayCol = (isset($parts[1]) && trim($parts[1]) !== '') ? trim($parts[1]) : null;
    $map = [];
    if ($table === '') { return $map; }

    if ($table === 'admins') {
        $resp = CurlController::request("admins?select=id_admin,title_admin,email_admin", "GET", []);
        if (is_object($resp) && $resp->status == 200 && !empty($resp->results)) {
            foreach ($resp->results as $r) {
                $r = (array)$r;
                $label = (!empty($r['title_admin'])) ? $r['title_admin'] : ($r['email_admin'] ?? ($r['id_admin'] ?? ''));
                $map[(string)($r['id_admin'] ?? '')] = urldecode((string)$label);
            }
        }
        return $map;
    }

    // Module-backed table: resolve its id column from the module suffix.
    $mod = CurlController::request("relations?rel=modules,pages&type=module,page&linkTo=type_module,title_module&equalTo=tables," . $table . "&select=url_page,suffix_module", "GET", []);
    if (!is_object($mod) || $mod->status != 200 || empty($mod->results)) { return $map; }
    $suffix = trim((string)($mod->results[0]->suffix_module ?? ''));
    if ($suffix === '') { return $map; }
    $idCol = 'id_' . $suffix;
    $rows = CurlController::request($table, "GET", []);
    if (!is_object($rows) || $rows->status != 200 || empty($rows->results)) { return $map; }
    foreach ($rows->results as $r) {
        $r = (array)$r;
        if (!array_key_exists($idCol, $r)) { continue; }
        if ($displayCol !== null && array_key_exists($displayCol, $r)) {
            $label = $r[$displayCol];
        } else {
            $keys = array_keys($r);
            $label = $r[$keys[1] ?? $keys[0]] ?? '';
        }
        $map[(string)$r[$idCol]] = urldecode((string)$label);
    }
    return $map;
}

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

// Never export framework / sensitive tables, regardless of role.
$blacklist = ['admins', 'roles', 'pages', 'modules', 'columns', 'folders', 'files',
    'cms_settings', 'activity_logs', 'framework_migrations', 'dashboard_widgets',
    'page_seo', 'payku_orders', 'workflows'];
if (in_array(strtolower($module), $blacklist, true)) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

try {
    // A page usually has several modules sharing the same title_module (e.g. a
    // breadcrumbs module plus the table module). Fetch them all and pick the
    // TABLE module with a real suffix — grabbing results[0] blindly can land on
    // the breadcrumbs row (empty suffix), which builds an invalid "id_" order
    // column and makes the API return 404 (surfacing here as a silent 500).
    $urlModule = "modules?linkTo=title_module&equalTo=" . urlencode($module) . "&select=id_module,suffix_module,type_module,id_page_module";
    $method = "GET";
    $fields = array();

    $moduleInfo = CurlController::request($urlModule, $method, $fields);

    $chosen = null;
    if (is_object($moduleInfo) && $moduleInfo->status == 200 && !empty($moduleInfo->results)) {
        foreach ($moduleInfo->results as $m) {
            $s = trim((string)($m->suffix_module ?? ''));
            if ($s === '') { continue; }
            // Prefer the tables module; otherwise the first one with a suffix.
            if (($m->type_module ?? '') === 'tables') { $chosen = $m; break; }
            if ($chosen === null) { $chosen = $m; }
        }
    }

    // Only a registered "tables" module may be exported — this blocks arbitrary
    // table names that aren't exposed as a data table in the CMS.
    if ($chosen === null) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }

    $suffix   = trim((string)$chosen->suffix_module) ?: 'id';
    $moduleId = $chosen->id_module ?? 0;

    // Access control: superadmin/admin have full access; other roles (editor)
    // may only export tables on pages they have permission for.
    $role = $_SESSION['admin']->rol_admin ?? '';
    if ($role !== 'superadmin' && $role !== 'admin') {
        $idPage   = (int)($chosen->id_page_module ?? 0);
        $pageResp = CurlController::request("pages?linkTo=id_page&equalTo=" . $idPage . "&select=url_page", $method, $fields);
        $urlPage  = (is_object($pageResp) && $pageResp->status == 200 && !empty($pageResp->results))
            ? ($pageResp->results[0]->url_page ?? '') : '';
        $perms = json_decode(urldecode($_SESSION['admin']->permissions_admin ?? ''), true);
        if (!is_array($perms) || $urlPage === '' || ($perms[$urlPage] ?? '') !== 'on') {
            header('HTTP/1.1 403 Forbidden');
            exit;
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

    // Pre-resolve relations columns to id → label maps (one fetch per column).
    $relationMaps = [];
    foreach ($visibleColumns as $col) {
        if ($col->type_column === 'relations' && !empty($col->matrix_column)) {
            $relationMaps[$col->title_column] = exp_buildRelationMap($col->matrix_column);
        }
    }

    foreach ($data->results as $index => $row) {
        $rowData = [$index + 1];
        
        foreach ($visibleColumns as $col) {
            $value = $row->{$col->title_column} ?? '';
            
            if (is_string($value)) {
                $value = urldecode($value);
            }
            
            // Format according to type — consistent with the table listing.
            if ($col->type_column == 'boolean') {
                $value = $value == 1 ? 'Sí' : 'No';
            } else if ($col->type_column == 'money') {
                $value = is_numeric($value) ? TemplateController::formatMoney($value) : $value;
            } else if ($col->type_column == 'double') {
                $value = is_numeric($value) ? number_format($value, 2) : $value;
            } else if ($col->type_column == 'measure') {
                if (is_numeric($value)) {
                    $unit = '';
                    if (!empty($col->matrix_column)) {
                        $unit = isset($row->{$col->matrix_column})
                            ? urldecode((string)$row->{$col->matrix_column})
                            : $col->matrix_column;
                    }
                    $num = rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
                    $value = trim($num . ' ' . $unit);
                }
            } else if ($col->type_column == 'date' || $col->type_column == 'datetime' || $col->type_column == 'time') {
                $value = TemplateController::formatListDate($col->type_column, $value);
            } else if ($col->type_column == 'relations') {
                $key = (string)$value;
                if (isset($relationMaps[$col->title_column][$key])) {
                    $value = $relationMaps[$col->title_column][$key];
                }
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
