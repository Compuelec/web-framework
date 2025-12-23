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
        $rootDir = dirname(dirname(__DIR__));
        $outputDir = $rootDir . '/packages';
        
        // Try to get project name from database (title_admin from admins table)
        $projectName = self::getProjectNameFromDatabase();
        
        // Fallback to directory name if database name is not available
        if (empty($projectName)) {
            $projectName = basename($rootDir);
        }
        
        // Sanitize project name for filename (remove special characters, spaces, etc.)
        $projectName = self::sanitizeProjectName($projectName);
        
        return [
            'project_name' => $projectName,
            'root_dir' => $rootDir,
            'output_dir' => $outputDir
        ];
    }
    
    /**
     * Get project name from database (title_admin)
     * 
     * @return string|null Project name or null if not available
     */
    private static function getProjectNameFromDatabase() {
        try {
            // Get database configuration
            $configPath = __DIR__ . '/../config.php';
            $config = null;
            
            if (file_exists($configPath)) {
                $config = require $configPath;
            }
            
            if (!is_array($config) || !isset($config['database'])) {
                return null;
            }
            
            $dbConfig = $config['database'];
            
            // Validate required database configuration
            if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
                return null;
            }
            
            $dbName = $dbConfig['name'];
            $dbUser = $dbConfig['user'];
            $dbPass = $dbConfig['pass'];
            $dbHost = $dbConfig['host'];
            
            // Connect to database
            $link = new PDO(
                "mysql:host=" . $dbHost . ";dbname=" . $dbName . ";charset=utf8mb4",
                $dbUser,
                $dbPass
            );
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Get title_admin from admins table (first superadmin or admin)
            $stmt = $link->prepare("SELECT title_admin FROM admins WHERE rol_admin IN ('superadmin', 'admin') AND title_admin IS NOT NULL AND title_admin != '' ORDER BY id_admin ASC LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['title_admin'])) {
                return trim($result['title_admin']);
            }
            
            return null;
            
        } catch (Exception $e) {
            // If database connection fails, return null (will use fallback)
            return null;
        }
    }
    
    /**
     * Sanitize project name for use in filename
     * 
     * @param string $name Project name
     * @return string Sanitized name
     */
    private static function sanitizeProjectName($name) {
        // Remove special characters, keep only alphanumeric, spaces, hyphens, and underscores
        $name = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $name);
        // Replace spaces with hyphens
        $name = preg_replace('/\s+/', '-', $name);
        // Remove multiple consecutive hyphens
        $name = preg_replace('/-+/', '-', $name);
        // Trim hyphens from start and end
        $name = trim($name, '-');
        // Convert to lowercase
        $name = strtolower($name);
        // Limit length
        if (strlen($name) > 50) {
            $name = substr($name, 0, 50);
        }
        // If empty after sanitization, use default
        if (empty($name)) {
            $name = 'project';
        }
        return $name;
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
            // Note: config.php and vendor/ are now INCLUDED in the package
            '**/.env',
            '**/.env.*',
            '**/node_modules/**',
            '**/backups/**',
            '.git',
            '.git/**',
            '**/.git',
            '**/.git/**',
            '.gitignore',
            '**/.gitignore',
            '.cursor',
            '.cursor/**',
            '**/.cursor',
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
        $databaseIncluded = false;
        
        if (isset($dbExportResult['success']) && $dbExportResult['success'] === true && !empty($dbExportResult['sql_content'])) {
            $zipResult = $zip->addFromString('database.sql', $dbExportResult['sql_content']);
            if ($zipResult) {
                $databaseIncluded = true;
            } else {
                error_log('Failed to add database.sql to ZIP: ' . $zip->getStatusString());
            }
        } else {
            // Log error for debugging
            $errorMsg = isset($dbExportResult['message']) ? $dbExportResult['message'] : 'Unknown error';
            error_log('Database export failed: ' . $errorMsg);
            if (isset($dbExportResult['temp_file']) && file_exists($dbExportResult['temp_file'])) {
                error_log('Temp file exists: ' . $dbExportResult['temp_file'] . ' (size: ' . filesize($dbExportResult['temp_file']) . ')');
            }
        }
        
        // Add installation README
        $installReadme = self::generateInstallReadme($databaseIncluded);
        $zip->addFromString('INSTALL.md', $installReadme);
        
        $zip->close();
        
        // Set appropriate permissions for the ZIP file (readable and writable by owner and group)
        if (file_exists($zipFilePath)) {
            @chmod($zipFilePath, 0664);
        }
        
        // Clean up temporary SQL file if it was created
        if (isset($dbExportResult['temp_file']) && file_exists($dbExportResult['temp_file'])) {
            @unlink($dbExportResult['temp_file']);
        }
        
        // Get file size
        $fileSize = filesize($zipFilePath);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        
        // Calculate download URL relative to CMS base path
        // From cms/views/pages/custom/packaging/ to packages/ at root
        // Need to go up 4 levels: ../../../../packages/
        $downloadUrl = '../../../../packages/' . $zipFileName;
        
        return [
            'success' => true,
            'message' => 'Paquete creado exitosamente' . ($databaseIncluded ? ' (incluye base de datos)' : ' (sin base de datos)'),
            'filename' => $zipFileName,
            'filepath' => $zipFilePath,
            'size' => $fileSize,
            'size_mb' => $fileSizeMB,
            'files_count' => $filesAdded,
            'download_url' => $downloadUrl,
            'database_included' => $databaseIncluded,
            'database_export_error' => $databaseIncluded ? null : ($dbExportResult['message'] ?? 'No se pudo exportar la base de datos')
        ];
    }
    
    /**
     * Check if a file should be excluded
     */
    private static function shouldExclude($filePath, $rootDir, $excludePatterns) {
        $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace('\\', '/', $relativePath);
        
        // Normalize: remove leading slash if present
        $relativePath = ltrim($relativePath, '/');
        
        // Split path into components
        $pathParts = explode('/', $relativePath);
        
        foreach ($excludePatterns as $pattern) {
            // Normalize pattern: remove leading slash if present
            $pattern = ltrim($pattern, '/');
            
            // Remove trailing /** or /* from pattern for directory matching
            $basePattern = rtrim($pattern, '/**');
            $basePattern = rtrim($basePattern, '/*');
            
            // Check for exact match (for root-level files/folders)
            if ($relativePath === $pattern || $relativePath === $basePattern) {
                return true;
            }
            
            // Check if path starts with pattern (for directories)
            if (strpos($relativePath, $basePattern . '/') === 0 || strpos($relativePath, $pattern . '/') === 0) {
                return true;
            }
            
            // Check if any path component matches the pattern or base pattern
            foreach ($pathParts as $part) {
                if ($part === $pattern || $part === $basePattern) {
                    return true;
                }
                // Also check without leading dot for patterns like .git, .cursor
                if (strpos($pattern, '.') === 0 && $part === substr($pattern, 1)) {
                    return true;
                }
            }
            
            // Convert glob pattern to regex for more complex patterns
            // ** matches any number of directories
            // * matches any characters except /
            $regexPattern = str_replace(
                ['**', '*', '/'],
                ['.*', '[^/]*', '/'],
                preg_quote($pattern, '#')
            );
            
            // Match pattern at start of path or anywhere in path
            $regex = '#^(.*/)?' . $regexPattern . '(/.*)?$#i';
            
            if (preg_match($regex, $relativePath)) {
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
            $filePath = $file->getRealPath();
            
            // Check if this is a directory - if it should be excluded, skip it and all its contents
            if ($file->isDir()) {
                if (self::shouldExclude($filePath, $rootDir, $excludePatterns)) {
                    // Skip this directory and all its contents by calling next() multiple times
                    // This prevents processing any files within excluded directories
                    $files->next();
                    continue;
                }
                continue; // Skip directories, we only add files
            }
            
            // For files, check if they should be excluded
            // Also check if any parent directory should be excluded
            $shouldSkip = false;
            $currentPath = $filePath;
            
            // Check the file itself and all parent directories
            while ($currentPath !== $rootDir && $currentPath !== dirname($currentPath)) {
                if (self::shouldExclude($currentPath, $rootDir, $excludePatterns)) {
                    $shouldSkip = true;
                    break;
                }
                $currentPath = dirname($currentPath);
            }
            
            if ($shouldSkip) {
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
        
        // Validate required database configuration
        if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
            return [
                'success' => false,
                'message' => 'Database configuration is incomplete. Please configure all database settings in config.php'
            ];
        }
        
        $dbName = $dbConfig['name'];
        $dbUser = $dbConfig['user'];
        $dbPass = $dbConfig['pass'];
        $dbHost = $dbConfig['host'];
        
        // Try to export using PDO first (more reliable from web server)
        try {
            $link = new PDO(
                "mysql:host=" . $dbHost . ";dbname=" . $dbName . ";charset=utf8mb4",
                $dbUser,
                $dbPass
            );
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Get all tables
            $tables = $link->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                return [
                    'success' => false,
                    'message' => 'No tables found in database'
                ];
            }
            
            // Build SQL dump manually
            $sqlContent = "-- Database Export\n";
            $sqlContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sqlContent .= "-- Database: " . $dbName . "\n\n";
            $sqlContent .= "SET FOREIGN_KEY_CHECKS=0;\n";
            $sqlContent .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
            $sqlContent .= "SET AUTOCOMMIT=0;\n";
            $sqlContent .= "START TRANSACTION;\n\n";
            
            foreach ($tables as $table) {
                // Get table structure
                $createTable = $link->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $sqlContent .= "DROP TABLE IF EXISTS `$table`;\n";
                $sqlContent .= $createTable['Create Table'] . ";\n\n";
                
                // Get table data
                $rows = $link->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $sqlContent .= "LOCK TABLES `$table` WRITE;\n";
                    $sqlContent .= "INSERT INTO `$table` VALUES ";
                    
                    $values = [];
                    foreach ($rows as $row) {
                        $rowValues = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $rowValues[] = 'NULL';
                            } else {
                                $rowValues[] = $link->quote($value);
                            }
                        }
                        $values[] = '(' . implode(',', $rowValues) . ')';
                    }
                    $sqlContent .= implode(',', $values) . ";\n";
                    $sqlContent .= "UNLOCK TABLES;\n\n";
                }
            }
            
            $sqlContent .= "COMMIT;\n";
            $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            return [
                'success' => true,
                'sql_content' => $sqlContent,
                'size' => strlen($sqlContent)
            ];
            
        } catch (PDOException $e) {
            // If PDO fails, try mysqldump as fallback
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
                    'message' => 'Database export failed. PDO error: ' . $e->getMessage() . '. mysqldump return code: ' . $returnCode . '. Output: ' . implode("\n", $output),
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
            // Calculate download URL relative to CMS base path
            // From cms/views/pages/custom/packaging/ to packages/ at root
            // Need to go up 4 levels: ../../../../packages/
            $downloadUrl = '../../../../packages/' . basename($file);
            
            $packages[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'size_mb' => round(filesize($file) / 1024 / 1024, 2),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
                'download_url' => $downloadUrl
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
        
        // Try to make directory writable if it's not
        if (!is_writable($outputDir)) {
            @chmod($outputDir, 0777);
        }
        
        // Try to make file writable if it's not (for deletion)
        if (file_exists($filePath) && !is_writable($filePath)) {
            @chmod($filePath, 0666);
        }
        
        // Try to delete the file
        $error = null;
        set_error_handler(function($errno, $errstr) use (&$error) {
            $error = $errstr;
        });
        
        $deleted = @unlink($filePath);
        restore_error_handler();
        
        if ($deleted) {
            return [
                'success' => true,
                'message' => 'Package deleted successfully'
            ];
        }
        
        // Provide more detailed error message
        $errorMessage = 'No se pudo eliminar el archivo';
        if ($error) {
            $errorMessage .= ': ' . $error;
        } else {
            // Check common issues
            if (!is_writable($filePath)) {
                $errorMessage .= '. El archivo no tiene permisos de escritura.';
            } else if (!is_writable($outputDir)) {
                $errorMessage .= '. El directorio packages/ no tiene permisos de escritura.';
            } else {
                $errorMessage .= '. Verifica los permisos del archivo y del directorio.';
            }
        }
        
        return [
            'success' => false,
            'message' => $errorMessage
        ];
    }
}

