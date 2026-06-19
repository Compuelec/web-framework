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
        
        // Calculate base path based on the actual file structure
        // This controller is in: cms/controllers/path-updater.controller.php
        // The project root is 2 levels up from this file
        $controllerPath = __DIR__; // cms/controllers
        $cmsPath = dirname($controllerPath); // cms
        $projectRoot = dirname($cmsPath); // project root
        
        // Get the document root to calculate relative path
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $documentRoot = str_replace('\\', '/', rtrim($documentRoot, '/'));
        $projectRoot = str_replace('\\', '/', rtrim($projectRoot, '/'));
        
        // Calculate the base path relative to document root
        if (!empty($documentRoot) && strpos($projectRoot, $documentRoot) === 0) {
            // Project is inside document root
            $basePath = substr($projectRoot, strlen($documentRoot));
            $basePath = str_replace('\\', '/', $basePath);
        } else {
            // Fallback: use REQUEST_URI to extract path
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            
            // Try to extract base path from REQUEST_URI
            // Remove query string
            $requestPath = parse_url($requestUri, PHP_URL_PATH);
            $requestPath = $requestPath ?: '';
            
            // If accessing /cms/install or similar, extract base
            if (preg_match('#^(.+?)/cms/#', $requestPath, $matches)) {
                $basePath = $matches[1];
            } elseif (preg_match('#^(.+?)/cms$#', $requestPath, $matches)) {
                $basePath = $matches[1];
            } else {
                // Fallback to script name method
                $scriptPath = dirname($scriptName);
                // If script is in cms/, go up one level
                if (strpos($scriptPath, '/cms') !== false) {
                    $basePath = dirname($scriptPath);
                } else {
                    $basePath = $scriptPath;
                }
            }
        }
        
        // Normalize base path
        $basePath = str_replace('\\', '/', $basePath);
        $basePath = rtrim($basePath, '/');
        
        // If base path is empty or just '/', it means project is in document root
        if (empty($basePath) || $basePath === '/') {
            $basePath = '';
        }
        
        // Build base URL
        $baseUrl = $protocol . '://' . $host . ($basePath ? '/' . ltrim($basePath, '/') : '');
        
        // Clean double slashes (but preserve protocol://)
        $baseUrl = preg_replace('#([^:])//+#', '$1/', $baseUrl);
        
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
        $configDir = dirname($configPath);
        
        // Ensure directory exists and is writable
        if (!is_dir($configDir)) {
            if (!@mkdir($configDir, 0755, true)) {
                return ['success' => false, 'message' => 'No se pudo crear el directorio de configuración: ' . $configDir];
            }
        }
        
        // Check if directory is writable, but don't block if it's not (try to write anyway)
        if (!is_writable($configDir)) {
            // Try to fix permissions if we can
            @chmod($configDir, 0755);
            // If still not writable, try to continue anyway (file might be writable even if dir isn't)
        }
        
        // If config.php doesn't exist, create it from example
        if (!file_exists($configPath) && file_exists($examplePath)) {
            if (!copy($examplePath, $configPath)) {
                return ['success' => false, 'message' => 'No se pudo copiar el archivo de ejemplo. Verifique permisos.'];
            }
            // Set permissions on new file
            chmod($configPath, 0644);
        }
        
        if (!file_exists($configPath)) {
            return ['success' => false, 'message' => 'No se encontró el archivo de configuración y no se pudo crear desde el ejemplo'];
        }
        
        // Check if file is writable, try to fix permissions if not
        if (!is_writable($configPath)) {
            // Try to make file writable
            @chmod($configPath, 0644);
            // If still not writable, try to continue anyway (might work with directory permissions)
            if (!is_writable($configPath)) {
                // Try one more time with 0666
                @chmod($configPath, 0666);
            }
        }
        
        // Load current configuration
        $config = require $configPath;
        
        // Ensure all required sections exist
        if (!isset($config['database'])) {
            $config['database'] = [];
        }
        if (!isset($config['api'])) {
            $config['api'] = [];
        }
        if (!isset($config['password'])) {
            $config['password'] = [];
        }
        
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
        // Ensure charset is set
        if (!isset($config['database']['charset'])) {
            $config['database']['charset'] = 'utf8mb4';
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
        
        // Try to write with error handling
        $result = @file_put_contents($configPath, $configContent, LOCK_EX);
        
        if ($result === false) {
            $error = error_get_last();
            $errorMsg = 'No se pudo escribir el archivo de configuración';
            if ($error && isset($error['message'])) {
                $errorMsg .= ': ' . $error['message'];
            }
            $errorMsg .= '. Verifique permisos de escritura en: ' . $configPath;
            return ['success' => false, 'message' => $errorMsg];
        }
        
        // Clear file stat cache to ensure fresh reads
        clearstatcache(true, $configPath);
        
        return [
            'success' => true,
            'message' => 'Configuración del CMS actualizada',
            'api_key' => $apiKey,
            'password_salt' => $passwordSalt
        ];
    }
    
    /**
     * Updates only API URLs in CMS configuration (does not touch database config)
     * Returns success even if file is not writable (non-blocking)
     */
    public static function updateCmsConfigUrlsOnly($domainInfo) {
        $configPath = __DIR__ . '/../config.php';
        $examplePath = __DIR__ . '/../config.example.php';
        
        // If config.php doesn't exist, try to create it from example
        if (!file_exists($configPath) && file_exists($examplePath)) {
            if (!@copy($examplePath, $configPath)) {
                return ['success' => false, 'message' => 'No se pudo crear config.php desde el ejemplo'];
            }
            @chmod($configPath, 0644);
        }
        
        if (!file_exists($configPath)) {
            return ['success' => false, 'message' => 'No se encontró el archivo de configuración'];
        }
        
        // Check if file is writable - if not, return success anyway (non-blocking)
        if (!is_writable($configPath)) {
            return ['success' => false, 'message' => 'El archivo de configuración no tiene permisos de escritura (puede continuar la instalación)'];
        }
        
        // Load current configuration
        $config = require $configPath;
        
        // Ensure api section exists
        if (!isset($config['api'])) {
            $config['api'] = [];
        }
        
        // Update only API URL
        $config['api']['base_url'] = $domainInfo['api_url'] . '/';
        
        // Save configuration
        $configContent = self::generateConfigFile($config);
        $result = @file_put_contents($configPath, $configContent, LOCK_EX);
        
        if ($result === false) {
            return ['success' => false, 'message' => 'No se pudo escribir el archivo de configuración (puede continuar la instalación)'];
        }
        
        clearstatcache(true, $configPath);
        
        return [
            'success' => true,
            'message' => 'URLs del CMS actualizadas'
        ];
    }
    
    /**
     * Updates only API URLs in API configuration (does not touch database config)
     * Returns success even if file is not writable (non-blocking)
     */
    public static function updateApiConfigUrlsOnly($domainInfo) {
        $configPath = __DIR__ . '/../../api/config.php';
        $examplePath = __DIR__ . '/../../api/config.example.php';
        
        // If config.php doesn't exist, try to create it from example
        if (!file_exists($configPath) && file_exists($examplePath)) {
            if (!@copy($examplePath, $configPath)) {
                return ['success' => false, 'message' => 'No se pudo crear config.php de la API desde el ejemplo'];
            }
            @chmod($configPath, 0644);
        }
        
        if (!file_exists($configPath)) {
            return ['success' => false, 'message' => 'No se encontró el archivo de configuración de la API'];
        }
        
        // Check if file is writable - if not, return success anyway (non-blocking)
        if (!is_writable($configPath)) {
            return ['success' => false, 'message' => 'El archivo de configuración de la API no tiene permisos de escritura (puede continuar la instalación)'];
        }
        
        // Load current configuration
        $config = require $configPath;
        
        // Save configuration (API config doesn't have base_url, so we just ensure it's valid)
        $configContent = self::generateConfigFile($config);
        $result = @file_put_contents($configPath, $configContent, LOCK_EX);
        
        if ($result === false) {
            return ['success' => false, 'message' => 'No se pudo escribir el archivo de configuración de la API (puede continuar la instalación)'];
        }
        
        clearstatcache(true, $configPath);
        
        return [
            'success' => true,
            'message' => 'Configuración de la API actualizada'
        ];
    }
    
    /**
     * Updates the API configuration file
     */
    public static function updateApiConfig($dbConfig, $apiKey = null, $jwtSecret = null, $passwordSalt = null) {
        $configPath = __DIR__ . '/../../api/config.php';
        $examplePath = __DIR__ . '/../../api/config.example.php';
        $configDir = dirname($configPath);
        
        // Ensure directory exists and is writable
        if (!is_dir($configDir)) {
            if (!mkdir($configDir, 0755, true)) {
                return ['success' => false, 'message' => 'No se pudo crear el directorio de configuración de la API: ' . $configDir];
            }
        }
        
        // Check if directory is writable, but don't block if it's not (try to write anyway)
        if (!is_writable($configDir)) {
            // Try to fix permissions if we can
            @chmod($configDir, 0755);
            // If still not writable, try to continue anyway (file might be writable even if dir isn't)
        }
        
        // If config.php doesn't exist, create it from example
        if (!file_exists($configPath) && file_exists($examplePath)) {
            if (!@copy($examplePath, $configPath)) {
                return ['success' => false, 'message' => 'No se pudo copiar el archivo de ejemplo de la API. Verifique permisos.'];
            }
            // Set permissions on new file
            @chmod($configPath, 0644);
        }
        
        if (!file_exists($configPath)) {
            return ['success' => false, 'message' => 'No se encontró el archivo de configuración de la API y no se pudo crear desde el ejemplo'];
        }
        
        // Check if file is writable, try to fix permissions if not
        if (!is_writable($configPath)) {
            // Try to make file writable
            @chmod($configPath, 0644);
            // If still not writable, try to continue anyway (might work with directory permissions)
            if (!is_writable($configPath)) {
                // Try one more time with 0666
                @chmod($configPath, 0666);
            }
        }
        
        // Load current configuration
        $config = require $configPath;
        
        // Ensure all required sections exist
        if (!isset($config['database'])) {
            $config['database'] = [];
        }
        if (!isset($config['api'])) {
            $config['api'] = [];
        }
        if (!isset($config['jwt'])) {
            $config['jwt'] = [];
        }
        if (!isset($config['password'])) {
            $config['password'] = [];
        }
        
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
        // Ensure charset is set
        if (!isset($config['database']['charset'])) {
            $config['database']['charset'] = 'utf8mb4';
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
        
        // Try to write with error handling
        $result = @file_put_contents($configPath, $configContent, LOCK_EX);
        
        if ($result === false) {
            $error = error_get_last();
            $errorMsg = 'No se pudo escribir el archivo de configuración de la API';
            if ($error && isset($error['message'])) {
                $errorMsg .= ': ' . $error['message'];
            }
            $errorMsg .= '. Verifique permisos de escritura en: ' . $configPath;
            return ['success' => false, 'message' => $errorMsg];
        }
        
        // Clear file stat cache to ensure fresh reads
        clearstatcache(true, $configPath);
        
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
            // Validate required database configuration
            if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
                return [
                    'success' => false,
                    'message' => 'Database configuration is incomplete',
                    'updated_count' => 0
                ];
            }
            
            // Connect to database
            $link = new PDO(
                "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['name'],
                $dbConfig['user'],
                $dbConfig['pass']
            );
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if ($oldDomain === '' || $newDomain === '' || $oldDomain === $newDomain) {
                return [
                    'success' => true,
                    'updated_count' => 0,
                    'updated_tables' => [],
                    'message' => 'No domain change needed'
                ];
            }

            $updatedCount = 0;
            $updatedTables = [];

            // Scan EVERY text-like column of EVERY table in this database for the
            // old domain and replace it. This catches image URLs and links stored
            // in any table — system tables (files, page_seo, …) AND user-created
            // tables (productos.imagen_producto, propiedades.imagene_destacada, the
            // multi-image JSON arrays, etc.) — not just a hardcoded list.
            $colStmt = $link->prepare(
                "SELECT TABLE_NAME AS t, COLUMN_NAME AS c
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :db
                   AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json')"
            );
            $colStmt->execute([':db' => $dbConfig['name']]);
            $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($columns as $col) {
                $table = $col['t'];
                $field = $col['c'];
                // Identifiers come from information_schema (safe); backtick-quote them.
                $sql = "UPDATE `{$table}` SET `{$field}` = REPLACE(`{$field}`, :oldDomain, :newDomain) WHERE `{$field}` LIKE :pattern";
                try {
                    $stmt = $link->prepare($sql);
                    $stmt->execute([
                        ':oldDomain' => $oldDomain,
                        ':newDomain' => $newDomain,
                        ':pattern'   => '%' . $oldDomain . '%'
                    ]);
                } catch (PDOException $e) {
                    // Skip columns that can't be updated (generated columns, etc.)
                    continue;
                }

                $rowsAffected = $stmt->rowCount();
                if ($rowsAffected > 0) {
                    $updatedCount += $rowsAffected;
                    $updatedTables[$table] = ($updatedTables[$table] ?? 0) + $rowsAffected;
                }
            }

            return [
                'success' => true,
                'updated_count' => $updatedCount,
                'updated_tables' => $updatedTables,
                'message' => "Updated $updatedCount records across " . count($updatedTables) . " table(s)"
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
            // Validate required database configuration
            if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
                return null;
            }
            
            $link = new PDO(
                "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['name'],
                $dbConfig['user'],
                $dbConfig['pass']
            );
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // The old base URL is the part of a stored absolute URL before a known
            // framework segment (/cms/, /web/ or /api/). Using the full path (not
            // just the host) captures subfolder installs correctly, e.g.
            //   http://localhost/proyectos-web/web-framework/cms/views/assets/files/x.webp
            //   → http://localhost/proyectos-web/web-framework
            $extract = function ($val) {
                if (is_string($val) && preg_match('#(https?://.+?)/(?:cms|web|api)/#i', $val, $m)) {
                    return rtrim($m[1], '/');
                }
                return null;
            };

            // Prefer cms_settings (holds full URLs: logo, OG image, canonical base).
            try {
                $rows = $link->query("SELECT value_setting FROM cms_settings WHERE value_setting LIKE '%http%' LIMIT 100")
                             ->fetchAll(PDO::FETCH_COLUMN);
                foreach ($rows as $v) { if ($b = $extract($v)) { return $b; } }
            } catch (PDOException $e) { /* table may not exist */ }

            // Fall back to scanning every text column for a stored URL (covers
            // user tables with image URLs).
            $cols = $link->prepare(
                "SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :db
                   AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json')"
            );
            $cols->execute([':db' => $dbConfig['name']]);
            foreach ($cols->fetchAll(PDO::FETCH_ASSOC) as $col) {
                try {
                    $v = $link->query(
                        "SELECT `{$col['c']}` FROM `{$col['t']}`
                         WHERE `{$col['c']}` LIKE '%http%/cms/%'
                            OR `{$col['c']}` LIKE '%http%/web/%'
                            OR `{$col['c']}` LIKE '%http%/api/%' LIMIT 1"
                    )->fetchColumn();
                } catch (PDOException $e) { continue; }
                if ($b = $extract($v)) { return $b; }
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

