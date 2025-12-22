<?php
/**
 * Packaging Script
 * 
 * This script creates a ZIP package of the project excluding sensitive files
 * to deploy it on a production server.
 * 
 * Usage: php package.php
 */

// Configuration
$projectName = 'chatcenter';
$excludePatterns = [
    // Sensitive configuration files
    '**/config.php',
    '**/.env',
    '**/.env.*',
    
    // Directories to exclude
    '**/vendor/**',
    '**/node_modules/**',
    '**/backups/**',
    '**/.git/**',
    '**/.cursor/**',
    '**/.vscode/**',
    '**/.idea/**',
    
    // Temporary files and logs
    '**/*.log',
    '**/*.tmp',
    '**/*.temp',
    '**/*.bak',
    '**/*.backup',
    '**/*.old',
    '**/*.orig',
    '**/logs/**',
    '**/tmp/**',
    
    // Database files
    '**/*.sql',
    '**/*.db',
    '**/*.sqlite',
    '**/*.sqlite3',
    
    // System files
    '**/.DS_Store',
    '**/Thumbs.db',
    '**/desktop.ini',
    
    // The packaging script itself
    '**/package.php',
];

$includePatterns = [
    // Include configuration example files
    '**/config.example.php',
];

// Project root directory
$rootDir = __DIR__;
$outputDir = $rootDir . '/packages';
$timestamp = date('Y-m-d_His');
$zipFileName = $projectName . '_' . $timestamp . '.zip';
$zipFilePath = $outputDir . '/' . $zipFileName;

// Create output directory if it doesn't exist
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Check if a file should be excluded
function shouldExclude($filePath, $rootDir, $excludePatterns) {
    $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $filePath);
    $relativePath = str_replace('\\', '/', $relativePath);
    
    foreach ($excludePatterns as $pattern) {
        // Convert glob pattern to regex
        $regex = '#^' . str_replace(
            ['**', '*', '/'],
            ['.*', '[^/]*', '/'],
            preg_quote($pattern, '#')
        ) . '$#i';
        
        if (preg_match($regex, $relativePath) || preg_match($regex, '/' . $relativePath)) {
            return true;
        }
    }
    
    return false;
}

// Check if a file should be included (overrides exclusions)
function shouldInclude($filePath, $rootDir, $includePatterns) {
    $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $filePath);
    $relativePath = str_replace('\\', '/', $relativePath);
    
    foreach ($includePatterns as $pattern) {
        $regex = '#^' . str_replace(
            ['**', '*', '/'],
            ['.*', '[^/]*', '/'],
            preg_quote($pattern, '#')
        ) . '$#i';
        
        if (preg_match($regex, $relativePath) || preg_match($regex, '/' . $relativePath)) {
            return true;
        }
    }
    
    return false;
}

// Recursively add files to ZIP
function addToZip($zip, $dir, $rootDir, $excludePatterns, $includePatterns) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        
        // Skip if it's a directory
        if ($file->isDir()) {
            continue;
        }
        
        // Check if it should be excluded
        if (shouldExclude($filePath, $rootDir, $excludePatterns)) {
            // Check if it should be included anyway
            if (!shouldInclude($filePath, $rootDir, $includePatterns)) {
                continue;
            }
        }
        
        // Calculate relative path for ZIP
        $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace('\\', '/', $relativePath);
        
        // Add file to ZIP
        $zip->addFile($filePath, $relativePath);
    }
}

// Create ZIP file
echo "📦 Creando paquete del proyecto...\n";
echo "Directorio raíz: $rootDir\n";
echo "Archivo de salida: $zipFilePath\n\n";

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("❌ No se pudo crear el archivo ZIP: $zipFilePath\n");
}

// Add files to ZIP
addToZip($zip, $rootDir, $rootDir, $excludePatterns, $includePatterns);

// Add installation README file
$installReadme = <<<'README'
# Instalación del Sistema

## Pasos para instalar:

1. Sube todos los archivos a tu servidor
2. Accede a: http://tu-dominio.com/cms/install
3. Completa el formulario de instalación
4. El sistema detectará automáticamente tu dominio y actualizará las configuraciones

## Notas importantes:

- Asegúrate de tener PHP 7.4 o superior
- Necesitas una base de datos MySQL/MariaDB
- Los archivos config.php se generarán automáticamente durante la instalación
- No olvides configurar los permisos de escritura en los directorios necesarios

## Configuración manual (opcional):

Si prefieres configurar manualmente:

1. Copia `cms/config.example.php` a `cms/config.php`
2. Copia `api/config.example.php` a `api/config.php`
3. Edita los archivos config.php con tus datos
4. Actualiza las rutas de `localhost` a tu dominio

README;

$zip->addFromString('INSTALL.md', $installReadme);

$zip->close();

// Get file size
$fileSize = filesize($zipFilePath);
$fileSizeMB = round($fileSize / 1024 / 1024, 2);

echo "✅ Paquete creado exitosamente!\n";
echo "📁 Archivo: $zipFileName\n";
echo "📊 Tamaño: $fileSizeMB MB\n";
echo "📍 Ubicación: $zipFilePath\n\n";
echo "💡 Puedes subir este archivo a tu servidor y descomprimirlo.\n";
echo "💡 Luego accede a: http://tu-dominio.com/cms/install para completar la instalación.\n";

