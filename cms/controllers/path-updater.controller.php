<?php

/**
 * Path Updater Controller
 * 
 * Controller to update paths and configurations when deploying
 * the system to a new server or domain.
 */

class PathUpdaterController {
    
    /**
     * Detects the current domain based on the request URL
     */
    public static function detectDomain() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Get project base directory
        $basePath = dirname(dirname(dirname($scriptName)));
        $basePath = str_replace('\\', '/', $basePath);
        
        // Build base URL
        $baseUrl = $protocol . '://' . $host . $basePath;
        
        // Clean double slashes
        $baseUrl = str_replace('//', '/', $baseUrl);
        $baseUrl = str_replace('http:/', 'http://', $baseUrl);
        $baseUrl = str_replace('https:/', 'https://', $baseUrl);
        
        return [
            'protocol' => $protocol,
            'host' => $host,
            'base_path' => $basePath,
            'base_url' => rtrim($baseUrl, '/'),
            'cms_url' => rtrim($baseUrl, '/') . '/cms',
            'api_url' => rtrim($baseUrl, '/') . '/api'
        ];
    }
    
    /**
     * Generates a secure API key
     */
    public static function generateApiKey() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Generates a secure JWT secret
     */
    public static function generateJwtSecret() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Generates a Blowfish password salt
     */
    public static function generatePasswordSalt() {
        // Generate salt in Blowfish format: $2a$07$... (22 characters after $2a$07$)
        $salt = '$2a$07$';
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
        for ($i = 0; $i < 22; $i++) {
            $salt .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $salt;
    }
    
    /**
     * Updates the CMS configuration file
     */
    public static function updateCmsConfig($domainInfo, $dbConfig, $apiKey = null, $passwordSalt = null) {
        $configPath = __DIR__ . '/../config.php';
        $examplePath = __DIR__ . '/../config.example.php';
        
        // If config.php doesn't exist, create it from example
        if (!file_exists($configPath) && file_exists($examplePath)) {
            copy($examplePath, $configPath);
        }
        
        if (!file_exists($configPath)) {
            return ['success' => false, 'message' => 'No se encontró el archivo de configuración'];
        }
        
        // Load current configuration
        $config = require $configPath;
        
        // Update configuration
        if (isset($dbConfig['host'])) {
            $config['database']['host'] = $dbConfig['host'];
        }
        if (isset($dbConfig['name'])) {
            $config['database']['name'] = $dbConfig['name'];
        }
        if (isset($dbConfig['user'])) {
            $config['database']['user'] = $dbConfig['user'];
        }
        if (isset($dbConfig['pass'])) {
            $config['database']['pass'] = $dbConfig['pass'];
        }
        
        // Update API URL
        $config['api']['base_url'] = $domainInfo['api_url'] . '/';
        
        // Generate new keys if not provided
        if ($apiKey === null) {
            $apiKey = self::generateApiKey();
        }
        $config['api']['key'] = $apiKey;
        
        // Generate new salt if not provided
        if ($passwordSalt === null) {
            $passwordSalt = self::generatePasswordSalt();
        }
        $config['password']['salt'] = $passwordSalt;
        
        // Save configuration
        $configContent = self::generateConfigFile($config);
        $result = file_put_contents($configPath, $configContent);
        
        if ($result === false) {
            return ['success' => false, 'message' => 'No se pudo escribir el archivo de configuración'];
        }
        
        return [
            'success' => true,
            'message' => 'Configuración del CMS actualizada',
            'api_key' => $apiKey,
            'password_salt' => $passwordSalt
        ];
    }
    
    /**
     * Updates the API configuration file
     */
    public static function updateApiConfig($dbConfig, $apiKey = null, $jwtSecret = null, $passwordSalt = null) {
        $configPath = __DIR__ . '/../../api/config.php';
        $examplePath = __DIR__ . '/../../api/config.example.php';
        
        // If config.php doesn't exist, create it from example
        if (!file_exists($configPath) && file_exists($examplePath)) {
            copy($examplePath, $configPath);
        }
        
        if (!file_exists($configPath)) {
            return ['success' => false, 'message' => 'No se encontró el archivo de configuración de la API'];
        }
        
        // Load current configuration
        $config = require $configPath;
        
        // Update database configuration
        if (isset($dbConfig['host'])) {
            $config['database']['host'] = $dbConfig['host'];
        }
        if (isset($dbConfig['name'])) {
            $config['database']['name'] = $dbConfig['name'];
        }
        if (isset($dbConfig['user'])) {
            $config['database']['user'] = $dbConfig['user'];
        }
        if (isset($dbConfig['pass'])) {
            $config['database']['pass'] = $dbConfig['pass'];
        }
        
        // Generate new keys if not provided
        if ($apiKey === null) {
            $apiKey = self::generateApiKey();
        }
        $config['api']['key'] = $apiKey;
        
        if ($jwtSecret === null) {
            $jwtSecret = self::generateJwtSecret();
        }
        $config['jwt']['secret'] = $jwtSecret;
        
        if ($passwordSalt === null) {
            $passwordSalt = self::generatePasswordSalt();
        }
        $config['password']['salt'] = $passwordSalt;
        
        // Save configuration
        $configContent = self::generateConfigFile($config);
        $result = file_put_contents($configPath, $configContent);
        
        if ($result === false) {
            return ['success' => false, 'message' => 'No se pudo escribir el archivo de configuración de la API'];
        }
        
        return [
            'success' => true,
            'message' => 'Configuración de la API actualizada',
            'api_key' => $apiKey,
            'jwt_secret' => $jwtSecret,
            'password_salt' => $passwordSalt
        ];
    }
    
    /**
     * Converts an array to PHP configuration code
     */
    private static function arrayToPhpConfig($config, $indent = 0) {
        $spaces = str_repeat('    ', $indent);
        $php = "";
        
        foreach ($config as $key => $value) {
            $php .= $spaces . "    ";
            $php .= is_numeric($key) ? $key : "'" . addslashes($key) . "'";
            $php .= " => ";
            
            if (is_array($value)) {
                $php .= "[\n";
                $php .= self::arrayToPhpConfig($value, $indent + 1);
                $php .= $spaces . "    ]";
            } else {
                if (is_string($value)) {
                    $php .= "'" . addslashes($value) . "'";
                } elseif (is_bool($value)) {
                    $php .= $value ? 'true' : 'false';
                } elseif (is_null($value)) {
                    $php .= 'null';
                } else {
                    $php .= $value;
                }
            }
            $php .= ",\n";
        }
        
        return $php;
    }
    
    /**
     * Generates the complete PHP configuration file content
     */
    private static function generateConfigFile($config) {
        $php = "<?php\n";
        $php .= "/**\n";
        $php .= " * Configuration File\n";
        $php .= " * \n";
        $php .= " * This file should never be accessed directly via URL.\n";
        $php .= " * It should only be included by PHP scripts.\n";
        $php .= " */\n\n";
        $php .= "// Prevent direct access\n";
        $php .= "if (basename(\$_SERVER['PHP_SELF']) === 'config.php') {\n";
        $php .= "    http_response_code(403);\n";
        $php .= "    die('Direct access to this file is not allowed.');\n";
        $php .= "}\n\n";
        $php .= "return [\n";
        $php .= self::arrayToPhpConfig($config, 0);
        $php .= "];\n";
        
        return $php;
    }
    
    /**
     * Updates URLs in database fields
     * 
     * @param string $oldDomain Old domain/URL to replace
     * @param string $newDomain New domain/URL
     * @param array $dbConfig Database configuration
     * @return array Result with success status and updated records count
     */
    public static function updateDatabaseUrls($oldDomain, $newDomain, $dbConfig) {
        try {
            // Connect to database
            $link = new PDO(
                "mysql:host=" . ($dbConfig['host'] ?? 'localhost') . ";dbname=" . ($dbConfig['name'] ?? 'chatcenter'),
                $dbConfig['user'] ?? 'root',
                $dbConfig['pass'] ?? ''
            );
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $updatedCount = 0;
            $updatedTables = [];
            
            // Fields that may contain URLs - table => [fields]
            $urlFields = [
                'folders' => ['url_folder'],
                // Add more tables and fields as needed
            ];
            
            foreach ($urlFields as $table => $fields) {
                // Check if table exists
                $checkTable = $link->query("SHOW TABLES LIKE '$table'");
                if ($checkTable->rowCount() === 0) {
                    continue;
                }
                
                foreach ($fields as $field) {
                    // Check if field exists
                    $checkField = $link->query("SHOW COLUMNS FROM `$table` LIKE '$field'");
                    if ($checkField->rowCount() === 0) {
                        continue;
                    }
                    
                    // Update URLs in this field
                    $sql = "UPDATE `$table` SET `$field` = REPLACE(`$field`, :oldDomain, :newDomain) WHERE `$field` LIKE :pattern";
                    $stmt = $link->prepare($sql);
                    $stmt->execute([
                        ':oldDomain' => $oldDomain,
                        ':newDomain' => $newDomain,
                        ':pattern' => '%' . $oldDomain . '%'
                    ]);
                    
                    $rowsAffected = $stmt->rowCount();
                    if ($rowsAffected > 0) {
                        $updatedCount += $rowsAffected;
                        if (!isset($updatedTables[$table])) {
                            $updatedTables[$table] = 0;
                        }
                        $updatedTables[$table] += $rowsAffected;
                    }
                }
            }
            
            return [
                'success' => true,
                'updated_count' => $updatedCount,
                'updated_tables' => $updatedTables,
                'message' => "Updated $updatedCount records in database"
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database update failed: ' . $e->getMessage(),
                'updated_count' => 0
            ];
        }
    }
    
    /**
     * Detects old domain from database
     * 
     * @param array $dbConfig Database configuration
     * @return string|null Old domain/base URL found or null
     */
    public static function detectOldDomainFromDatabase($dbConfig) {
        try {
            $link = new PDO(
                "mysql:host=" . ($dbConfig['host'] ?? 'localhost') . ";dbname=" . ($dbConfig['name'] ?? 'chatcenter'),
                $dbConfig['user'] ?? 'root',
                $dbConfig['pass'] ?? ''
            );
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check folders table for URLs
            $checkTable = $link->query("SHOW TABLES LIKE 'folders'");
            if ($checkTable->rowCount() > 0) {
                $stmt = $link->query("SELECT url_folder FROM folders WHERE url_folder IS NOT NULL AND url_folder != '' LIMIT 5");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $result) {
                    if (!empty($result['url_folder'])) {
                        $url = trim($result['url_folder']);
                        
                        // Extract full base URL (protocol + domain + path if exists)
                        if (preg_match('#^(https?://[^/]+(?:/[^/]*)?)#', $url, $matches)) {
                            $baseUrl = rtrim($matches[1], '/');
                            
                            // Skip if it's already the current domain (will be checked later)
                            // Return the first valid URL found
                            if ($baseUrl && $baseUrl !== 'http://localhost' && $baseUrl !== 'https://localhost') {
                                return $baseUrl;
                            }
                        }
                    }
                }
                
                // If we found localhost URLs, return a generic localhost pattern
                foreach ($results as $result) {
                    if (!empty($result['url_folder'])) {
                        $url = trim($result['url_folder']);
                        if (preg_match('#^(https?://localhost(?:/[^/]*)?)#', $url, $matches)) {
                            return rtrim($matches[1], '/');
                        }
                    }
                }
            }
            
            return null;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Searches and replaces localhost references in PHP files
     */
    public static function updatePhpFiles($oldDomain, $newDomain) {
        $rootDir = dirname(dirname(dirname(__DIR__)));
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $updatedFiles = [];
        $excludeDirs = ['vendor', 'node_modules', '.git', 'packages', 'backups'];
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }
            
            $filePath = $file->getRealPath();
            $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $filePath);
            
            // Exclude certain directories
            $shouldExclude = false;
            foreach ($excludeDirs as $excludeDir) {
                if (strpos($relativePath, $excludeDir) !== false) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if ($shouldExclude || pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }
            
            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }
            
            // Search and replace references
            $newContent = str_replace($oldDomain, $newDomain, $content);
            
            if ($content !== $newContent) {
                file_put_contents($filePath, $newContent);
                $updatedFiles[] = $relativePath;
            }
        }
        
        return $updatedFiles;
    }
}

