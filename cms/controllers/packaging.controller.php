<?php

/**
 * Packaging Controller
 * 
 * Handles project packaging for deployment
 */

class PackagingController {
    
    /**
     * Get packaging configuration
     */
    private static function getPackagingConfig() {
        $projectName = 'chatcenter';
        $rootDir = dirname(dirname(__DIR__));
        $outputDir = $rootDir . '/packages';
        
        return [
            'project_name' => $projectName,
            'root_dir' => $rootDir,
            'output_dir' => $outputDir
        ];
    }
    
    /**
     * Create project package
     * 
     * @return array Result with success status and package info
     */
    public static function createPackage() {
        $config = self::getPackagingConfig();
        $projectName = $config['project_name'];
        $rootDir = $config['root_dir'];
        $outputDir = $config['output_dir'];
        
        // Create output directory if it doesn't exist
        if (!file_exists($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'No se pudo crear el directorio de salida'
                ];
            }
        }
        
        // Exclude patterns
        $excludePatterns = [
            '**/config.php',
            '**/.env',
            '**/.env.*',
            '**/vendor/**',
            '**/node_modules/**',
            '**/backups/**',
            '**/.git/**',
            '**/.cursor/**',
            '**/.vscode/**',
            '**/.idea/**',
            '**/*.log',
            '**/*.tmp',
            '**/*.temp',
            '**/*.bak',
            '**/*.backup',
            '**/*.old',
            '**/*.orig',
            '**/logs/**',
            '**/tmp/**',
            '**/*.sql',
            '**/*.db',
            '**/*.sqlite',
            '**/*.sqlite3',
            '**/.DS_Store',
            '**/Thumbs.db',
            '**/desktop.ini',
            '**/package.php',
            '**/packages/**',
        ];
        
        $includePatterns = [
            '**/config.example.php',
        ];
        
        $timestamp = date('Y-m-d_His');
        $zipFileName = $projectName . '_' . $timestamp . '.zip';
        $zipFilePath = $outputDir . '/' . $zipFileName;
        
        // Create ZIP file
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el archivo ZIP'
            ];
        }
        
        // Add files to ZIP
        $filesAdded = self::addToZip($zip, $rootDir, $rootDir, $excludePatterns, $includePatterns);
        
        // Export and add database
        $dbExportResult = self::exportDatabase();
        if ($dbExportResult['success'] && !empty($dbExportResult['sql_content'])) {
            $zip->addFromString('database.sql', $dbExportResult['sql_content']);
        }
        
        // Add installation README
        $installReadme = self::generateInstallReadme($dbExportResult['success']);
        $zip->addFromString('INSTALL.md', $installReadme);
        
        $zip->close();
        
        // Clean up temporary SQL file if it was created
        if (isset($dbExportResult['temp_file']) && file_exists($dbExportResult['temp_file'])) {
            @unlink($dbExportResult['temp_file']);
        }
        
        // Get file size
        $fileSize = filesize($zipFilePath);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        
        return [
            'success' => true,
            'message' => 'Paquete creado exitosamente' . ($dbExportResult['success'] ? ' (incluye base de datos)' : ''),
            'filename' => $zipFileName,
            'filepath' => $zipFilePath,
            'size' => $fileSize,
            'size_mb' => $fileSizeMB,
            'files_count' => $filesAdded,
            'download_url' => '../../packages/' . $zipFileName,
            'database_included' => $dbExportResult['success'] ?? false
        ];
    }
    
    /**
     * Check if a file should be excluded
     */
    private static function shouldExclude($filePath, $rootDir, $excludePatterns) {
        $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace('\\', '/', $relativePath);
        
        foreach ($excludePatterns as $pattern) {
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
    
    /**
     * Check if a file should be included (overrides exclusions)
     */
    private static function shouldInclude($filePath, $rootDir, $includePatterns) {
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
    
    /**
     * Recursively add files to ZIP
     */
    private static function addToZip($zip, $dir, $rootDir, $excludePatterns, $includePatterns) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $filesCount = 0;
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }
            
            $filePath = $file->getRealPath();
            
            if (self::shouldExclude($filePath, $rootDir, $excludePatterns)) {
                if (!self::shouldInclude($filePath, $rootDir, $includePatterns)) {
                    continue;
                }
            }
            
            $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $filePath);
            $relativePath = str_replace('\\', '/', $relativePath);
            
            $zip->addFile($filePath, $relativePath);
            $filesCount++;
        }
        
        return $filesCount;
    }
    
    /**
     * Export database to SQL
     * 
     * @return array Result with success status and SQL content
     */
    private static function exportDatabase() {
        // Get database configuration
        $configPath = __DIR__ . '/../config.php';
        $config = null;
        
        if (file_exists($configPath)) {
            $config = require $configPath;
        }
        
        if (!is_array($config) || !isset($config['database'])) {
            $examplePath = __DIR__ . '/../config.example.php';
            if (file_exists($examplePath)) {
                $config = require $examplePath;
            }
        }
        
        if (!is_array($config) || !isset($config['database'])) {
            return [
                'success' => false,
                'message' => 'Database configuration not found'
            ];
        }
        
        $dbConfig = $config['database'];
        $dbName = $dbConfig['name'] ?? 'chatcenter';
        $dbUser = $dbConfig['user'] ?? 'root';
        $dbPass = $dbConfig['pass'] ?? '';
        $dbHost = $dbConfig['host'] ?? 'localhost';
        
        // Find mysqldump path
        $mysqldumpPath = self::findMysqldumpPath();
        
        // Create temporary file for SQL export
        $tempFile = sys_get_temp_dir() . '/packaging_db_' . time() . '.sql';
        
        // Build mysqldump command
        $command = sprintf(
            '%s -h %s -u %s%s %s > %s 2>&1',
            escapeshellarg($mysqldumpPath),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            !empty($dbPass) ? ' -p' . escapeshellarg($dbPass) : '',
            escapeshellarg($dbName),
            escapeshellarg($tempFile)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($tempFile) || filesize($tempFile) === 0) {
            return [
                'success' => false,
                'message' => 'Database export failed. Return code: ' . $returnCode . '. Output: ' . implode("\n", $output),
                'temp_file' => file_exists($tempFile) ? $tempFile : null
            ];
        }
        
        // Read SQL content
        $sqlContent = file_get_contents($tempFile);
        
        return [
            'success' => true,
            'sql_content' => $sqlContent,
            'temp_file' => $tempFile,
            'size' => strlen($sqlContent)
        ];
    }
    
    /**
     * Find mysqldump executable path
     * 
     * @return string Path to mysqldump
     */
    private static function findMysqldumpPath() {
        // Try to get from config
        $configPath = __DIR__ . '/../config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            if (isset($config['database']['mysqldump_path']) && !empty($config['database']['mysqldump_path'])) {
                return $config['database']['mysqldump_path'];
            }
        }
        
        // Check common paths
        $commonPaths = [
            '/Applications/XAMPP/xamppfiles/bin/mysqldump',
            '/opt/lampp/bin/mysqldump',
            '/Applications/MAMP/Library/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ];
        
        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }
        
        // Fallback: rely on system PATH
        return 'mysqldump';
    }
    
    /**
     * Generate installation README content
     * 
     * @param bool $includeDatabase Whether database was included
     */
    private static function generateInstallReadme($includeDatabase = false) {
        $readme = "# System Installation\n\n";
        
        if ($includeDatabase) {
            $readme .= "## Database Import:\n\n";
            $readme .= "This package includes a complete database export (`database.sql`).\n\n";
            $readme .= "To import the database:\n\n";
            $readme .= "1. Create a new database in your MySQL/MariaDB server\n";
            $readme .= "2. Import the database:\n";
            $readme .= "   ```bash\n";
            $readme .= "   mysql -u your_user -p your_database < database.sql\n";
            $readme .= "   ```\n\n";
            $readme .= "   Or using phpMyAdmin:\n";
            $readme .= "   - Select your database\n";
            $readme .= "   - Go to \"Import\" tab\n";
            $readme .= "   - Choose `database.sql` file\n";
            $readme .= "   - Click \"Go\"\n\n";
        }
        
        $readme .= "## Installation Steps:\n\n";
        $readme .= "1. Upload all files to your server\n";
        
        if ($includeDatabase) {
            $readme .= "2. Import the database (see above)\n";
            $readme .= "3. Access: http://your-domain.com/cms/install\n";
            $readme .= "4. Complete the installation form (or configure manually)\n";
            $readme .= "5. The system will automatically detect your domain and update configurations\n";
        } else {
            $readme .= "2. Access: http://your-domain.com/cms/install\n";
            $readme .= "3. Complete the installation form\n";
            $readme .= "4. The system will automatically detect your domain and update configurations\n";
        }
        
        $readme .= "\n## Important Notes:\n\n";
        $readme .= "- Make sure you have PHP 7.4 or higher\n";
        $readme .= "- You need a MySQL/MariaDB database\n";
        $readme .= "- config.php files will be generated automatically during installation\n";
        $readme .= "- Don't forget to configure write permissions on necessary directories\n\n";
        $readme .= "## Manual Configuration (optional):\n\n";
        $readme .= "If you prefer to configure manually:\n\n";
        $readme .= "1. Copy `cms/config.example.php` to `cms/config.php`\n";
        $readme .= "2. Copy `api/config.example.php` to `api/config.php`\n";
        $readme .= "3. Edit the config.php files with your data\n";
        $readme .= "4. Update routes from `localhost` to your domain\n";
        
        return $readme;
    }
    
    /**
     * Get list of existing packages
     * 
     * @return array List of packages
     */
    public static function getPackages() {
        $config = self::getPackagingConfig();
        $outputDir = $config['output_dir'];
        
        if (!file_exists($outputDir)) {
            return [];
        }
        
        $packages = [];
        $files = glob($outputDir . '/*.zip');
        
        foreach ($files as $file) {
            $packages[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'size_mb' => round(filesize($file) / 1024 / 1024, 2),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
                'download_url' => '../../packages/' . basename($file)
            ];
        }
        
        // Sort by creation date (newest first)
        usort($packages, function($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return $packages;
    }
    
    /**
     * Delete a package file
     * 
     * @param string $filename Package filename
     * @return array Result with success status
     */
    public static function deletePackage($filename) {
        $config = self::getPackagingConfig();
        $outputDir = $config['output_dir'];
        
        // Security: only allow deleting .zip files
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'zip') {
            return [
                'success' => false,
                'message' => 'Invalid file type'
            ];
        }
        
        $filePath = $outputDir . '/' . basename($filename);
        
        // Security: ensure file is in packages directory
        $realPath = realpath($filePath);
        $realOutputDir = realpath($outputDir);
        
        if (!$realPath || strpos($realPath, $realOutputDir) !== 0) {
            return [
                'success' => false,
                'message' => 'Invalid file path'
            ];
        }
        
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => 'File not found'
            ];
        }
        
        if (unlink($filePath)) {
            return [
                'success' => true,
                'message' => 'Package deleted successfully'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Could not delete file'
        ];
    }
}

