<?php

/**
 * Export Data AJAX Endpoint
 * Handles data export in different formats
 */

session_start();

require_once "../controllers/curl.controller.php";
require_once "../controllers/template.controller.php";

// Check if user is authenticated
if (!isset($_SESSION["admin"])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$module = isset($_GET['module']) ? $_GET['module'] : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($module)) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

try {
    // Get module info to get suffix
    $urlModule = "modules?linkTo=title_module&equalTo=" . $module . "&select=suffix_module";
    $method = "GET";
    $fields = array();
    
    $moduleInfo = CurlController::request($urlModule, $method, $fields);
    $suffix = 'id';
    
    if ($moduleInfo->status == 200 && isset($moduleInfo->results[0])) {
        $suffix = $moduleInfo->results[0]->suffix_module;
    }
    
    // Get all data from the table
    $url = $module . "?orderBy=id_" . $suffix . "&orderMode=DESC";
    
    // Apply filters if provided
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $url .= "&search=" . urlencode($_GET['search']);
    }
    
    if (isset($_GET['between1']) && isset($_GET['between2'])) {
        $url .= "&between1=" . $_GET['between1'] . "&between2=" . $_GET['between2'];
    }
    
    $data = CurlController::request($url, $method, $fields);
    
    if ($data->status != 200 || !isset($data->results)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    }
    
    // Get module ID first
    $urlModuleId = "modules?linkTo=title_module&equalTo=" . $module . "&select=id_module";
    $moduleIdResult = CurlController::request($urlModuleId, $method, $fields);
    $moduleId = 0;
    
    if ($moduleIdResult->status == 200 && isset($moduleIdResult->results[0])) {
        $moduleId = $moduleIdResult->results[0]->id_module;
    }
    
    // Get module columns
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
                $value = $value == 1 ? 'Yes' : 'No';
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
    
    // Export according to format
    switch ($format) {
        case 'csv':
            exportCSV($exportData, $module, false);
            break;
        case 'excel':
            exportExcel($exportData, $module);
            break;
        case 'pdf':
            exportPDF($exportData, $module, $visibleColumns);
            break;
        default:
            exportCSV($exportData, $module, false);
    }
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: " . $e->getMessage();
}

function exportCSV($data, $filename, $addBOM = true) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel compatibility if requested
    if ($addBOM) {
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    }
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function exportExcel($data, $filename) {
    // Excel format: CSV with BOM and .xls extension for compatibility
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    foreach ($data as $row) {
        fputcsv($output, $row, "\t"); // Use tab separator for Excel
    }
    
    fclose($output);
    exit;
}

function exportPDF($data, $filename, $columns) {
    // Generate HTML page that uses jsPDF to create and download PDF
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generando PDF...</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .loading {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner"></div>
        <p>Generando PDF...</p>
    </div>
    <script>
        const { jsPDF } = window.jspdf;
        
        // Create PDF document
        const doc = new jsPDF({
            orientation: "landscape",
            unit: "mm",
            format: "a4"
        });
        
        // Set font
        doc.setFont("helvetica");
        
        // Add title
        doc.setFontSize(16);
        doc.text("' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '", 14, 15);
        
        // Add export date
        doc.setFontSize(10);
        doc.setTextColor(100, 100, 100);
        doc.text("Fecha de exportación: ' . date('Y-m-d H:i:s') . '", 14, 22);
        
        // Prepare table data
        const tableData = [';
    
    // Add headers
    $headers = array();
    foreach ($data[0] as $header) {
        $headers[] = '"' . addslashes($header) . '"';
    }
    echo '[' . implode(',', $headers) . ']';
    
    // Add data rows
    for ($i = 1; $i < count($data); $i++) {
        echo ',
            [';
        $rowData = array();
        foreach ($data[$i] as $cell) {
            $rowData[] = '"' . addslashes(str_replace(array("\r\n", "\r", "\n"), " ", $cell)) . '"';
        }
        echo implode(',', $rowData) . ']';
    }
    
    echo '
        ];
        
        // Add table using autoTable plugin
        doc.autoTable({
            head: [tableData[0]],
            body: tableData.slice(1),
            startY: 28,
            styles: {
                fontSize: 8,
                cellPadding: 2,
                overflow: "linebreak"
            },
            headStyles: {
                fillColor: [74, 85, 104],
                textColor: [255, 255, 255],
                fontStyle: "bold"
            },
            alternateRowStyles: {
                fillColor: [247, 250, 252]
            },
            margin: { top: 28, left: 14, right: 14 },
            tableWidth: "auto"
        });
        
        // Add footer
        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(100, 100, 100);
            doc.text(
                "Página " + i + " de " + pageCount + " | Total de registros: ' . (count($data) - 1) . ' | Generado por CMS Builder",
                doc.internal.pageSize.getWidth() / 2,
                doc.internal.pageSize.getHeight() - 10,
                { align: "center" }
            );
        }
        
        // Save PDF
        doc.save("' . addslashes($filename) . '_' . date('Y-m-d') . '.pdf");
        
        // Close only this popup window (not the main page)
        setTimeout(function() {
            // Only close if this is a popup window (opened with window.open)
            if (window.opener) {
                window.close();
            } else {
                // If not a popup, show message and allow user to close manually
                document.querySelector(".loading").innerHTML = `
                    <div style="color: #28a745;">
                        <i class="bi bi-check-circle" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                        <p style="font-size: 16px; margin: 0;">PDF generado y descargado exitosamente</p>
                        <p style="font-size: 12px; color: #666; margin-top: 10px;">Puedes cerrar esta ventana</p>
                    </div>
                `;
            }
        }, 500);
    </script>
</body>
</html>';
    
    exit;
}

