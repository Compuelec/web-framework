<?php

/**
 * Package Install Controller
 * 
 * Handles installation from packaged files, including database restoration
 * and URL reference updates
 */

require_once __DIR__ . '/path-updater.controller.php';
require_once __DIR__ . '/install.controller.php';

class PackageInstallController {
    
    /**
     * Check if database.sql exists in the package
     * 
     * @return bool True if database.sql exists
     */
    public static function hasDatabaseFile() {
        $rootDir = dirname(dirname(__DIR__));
        $dbFile = $rootDir . '/database.sql';
        return file_exists($dbFile) && is_readable($dbFile);
    }
    
    /**
     * Restore database from database.sql file
     * 
     * @param array $dbConfig Database configuration
     * @return array Result with success status and message
     */
    public static function restoreDatabase($dbConfig) {
        $rootDir = dirname(dirname(__DIR__));
        $dbFile = $rootDir . '/database.sql';
        
        if (!file_exists($dbFile)) {
            return [
                'success' => false,
                'message' => 'database.sql file not found in package'
            ];
        }
        
        // Validate required database configuration
        if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
            return [
                'success' => false,
                'message' => 'Database configuration is incomplete'
            ];
        }
        
        try {
            // Use PDO method for database restoration
            // Connect to database server (without specifying database)
            $link = new PDO(
                "mysql:host=" . $dbConfig['host'] . ";charset=utf8mb4",
                $dbConfig['user'],
                $dbConfig['pass']
            );
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database if it doesn't exist
            $link->exec("CREATE DATABASE IF NOT EXISTS `" . $dbConfig['name'] . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Read SQL file
            $sqlContent = file_get_contents($dbFile);
            
            if ($sqlContent === false) {
                return [
                    'success' => false,
                    'message' => 'Could not read database.sql file'
                ];
            }
            
            // Connect to the specific database
            $link = new PDO(
                "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['name'] . ";charset=utf8mb4",
                $dbConfig['user'],
                $dbConfig['pass']
            );
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Remove comments and clean SQL
            $sqlContent = preg_replace('/^--.*$/m', '', $sqlContent); // Remove single-line comments
            $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent); // Remove multi-line comments
            
            // Split SQL into statements more carefully
            // Handle statements that may contain semicolons inside quotes
            $statements = [];
            $currentStatement = '';
            $inQuotes = false;
            $quoteChar = null;
            
            for ($i = 0; $i < strlen($sqlContent); $i++) {
                $char = $sqlContent[$i];
                $currentStatement .= $char;
                
                // Track quotes
                if (($char === '"' || $char === "'" || $char === '`') && ($i === 0 || $sqlContent[$i-1] !== '\\')) {
                    if (!$inQuotes) {
                        $inQuotes = true;
                        $quoteChar = $char;
                    } elseif ($char === $quoteChar) {
                        $inQuotes = false;
                        $quoteChar = null;
                    }
                }
                
                // If we hit a semicolon and we're not in quotes, it's the end of a statement
                if ($char === ';' && !$inQuotes) {
                    $stmt = trim($currentStatement);
                    if (!empty($stmt) && strlen($stmt) > 1) {
                        $statements[] = $stmt;
                    }
                    $currentStatement = '';
                }
            }
            
            // Add any remaining statement
            if (!empty(trim($currentStatement))) {
                $statements[] = trim($currentStatement);
            }
            
            // Execute each statement
            $executed = 0;
            $errors = [];
            foreach ($statements as $index => $statement) {
                $statement = trim($statement);
                if (empty($statement) || strlen($statement) < 3) {
                    continue;
                }
                
                // Skip empty statements or comments
                if (preg_match('/^(SET|LOCK|UNLOCK)\s+/i', $statement)) {
                    // These are important, execute them
                } elseif (preg_match('/^(DROP|CREATE|INSERT|UPDATE|DELETE)\s+/i', $statement)) {
                    // These are important, execute them
                } else {
                    // Skip other statements that might be empty or comments
                    continue;
                }
                
                try {
                    $result = $link->exec($statement);
                    $executed++;
                    
                    // Log INSERT statements to verify they're executing
                    if (preg_match('/^INSERT\s+/i', $statement)) {
                        error_log("Executed INSERT statement #$executed: " . substr($statement, 0, 150));
                    }
                } catch (PDOException $e) {
                    // Log error but continue - some statements might fail (like DROP TABLE IF EXISTS on non-existent tables)
                    $errorMsg = $e->getMessage();
                    $errorCode = $e->getCode();
                    
                    // Only log if it's not a "table doesn't exist" error for DROP statements
                    if (!preg_match('/Unknown table/i', $errorMsg) && $errorCode != '42S02') {
                        error_log("SQL execution warning (statement #$index): " . $errorMsg . " | Statement: " . substr($statement, 0, 150));
                        $errors[] = "Statement #$index: " . substr($statement, 0, 100) . "... Error: " . $errorMsg;
                    }
                }
            }
            
            // Log summary
            error_log("SQL restoration completed. Executed: $executed statements. Errors: " . count($errors));
            if (!empty($errors)) {
                error_log("SQL restoration errors: " . implode(" | ", array_slice($errors, 0, 5)));
            }
            
            return [
                'success' => true,
                'message' => "Database restored successfully. Executed $executed statements.",
                'statements_executed' => $executed
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database restoration failed: ' . $e->getMessage()
            ];
        }
    }
    
    
    /**
     * Update database URLs from old domain to new domain
     * 
     * @param array $dbConfig Database configuration
     * @return array Result with success status and updated count
     */
    public static function updateDatabaseUrls($dbConfig) {
        // Detect old domain from database
        $oldDomain = PathUpdaterController::detectOldDomainFromDatabase($dbConfig);
        
        if (!$oldDomain) {
            return [
                'success' => true,
                'message' => 'No old domain found in database',
                'updated_count' => 0
            ];
        }
        
        // Detect new domain
        $domainInfo = PathUpdaterController::detectDomain();
        $newDomain = $domainInfo['base_url'];
        
        if ($oldDomain === $newDomain) {
            return [
                'success' => true,
                'message' => 'Domain unchanged, no updates needed',
                'updated_count' => 0
            ];
        }
        
        // Update URLs in database
        return PathUpdaterController::updateDatabaseUrls($oldDomain, $newDomain, $dbConfig);
    }
    
    /**
     * Install package (main entry point)
     * 
     * @return void
     */
    public function install() {
        // For package installation, we don't require email_admin since it comes from database
        // But we still need to check if form was submitted
        $isPackageInstall = self::hasDatabaseFile();
        
        if ($isPackageInstall) {
            // For package installation, only check if form was submitted (email_admin is hidden field)
            if (!isset($_POST["email_admin"])) {
                return;
            }
        } else {
            // For clean installation, require email_admin
            if (!isset($_POST["email_admin"])) {
                return;
            }
        }
        
        // Detect domain and update configurations automatically
        $domainInfo = PathUpdaterController::detectDomain();
        
        // Get database configuration from config.php (not from form to prevent modification)
        $config = InstallController::getConfig();
        $dbConfig = $config['database'] ?? [];
        
        // If config is incomplete, try to get from hidden form fields as fallback
        if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
            $dbConfig = [
                'host' => $_POST['db_host'] ?? '',
                'name' => $_POST['db_name'] ?? '',
                'user' => $_POST['db_user'] ?? '',
                'pass' => $_POST['db_pass'] ?? ''
            ];
        }
        
        // Validate database configuration
        if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
            $errorMsgEscaped = json_encode("La configuración de la base de datos está incompleta en cms/config.php o cms/config.example.php. Por favor, configure host, name, user y pass.", JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            echo '<script>
                fncMatPreloader("off");
                fncFormatInputs();
                fncSweetAlert("error", "Error de configuración", ' . $errorMsgEscaped . ');
            </script>';
            return;
        }
        
        echo '<script>
            fncMatPreloader("on");
            fncSweetAlert("loading", "Instalando desde paquete...", "");
        </script>';
        
        // Step 1: Restore database if database.sql exists
        $dbRestored = false;
        $databaseFilePath = null;
        if (self::hasDatabaseFile()) {
            $rootDir = dirname(dirname(__DIR__));
            $databaseFilePath = $rootDir . '/database.sql';
            
            $restoreResult = self::restoreDatabase($dbConfig);
            
            if (!$restoreResult['success']) {
                $errorMsgEscaped = json_encode("Error al restaurar la base de datos: " . $restoreResult['message'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                echo '<script>
                    fncMatPreloader("off");
                    fncFormatInputs();
                    fncSweetAlert("error", "Error de restauración", ' . $errorMsgEscaped . ');
                </script>';
                return;
            }
            
            $dbRestored = true;
        }
        
        // Step 2: Update configurations
        $cmsConfigResult = PathUpdaterController::updateCmsConfig($domainInfo, $dbConfig);
        $apiConfigResult = PathUpdaterController::updateApiConfig(
            $dbConfig,
            $cmsConfigResult['api_key'] ?? null,
            null,
            $cmsConfigResult['password_salt'] ?? null
        );
        
        // Log warnings but don't block installation if config files can't be written
        // The database restoration is more critical
        if (!$cmsConfigResult['success']) {
            error_log("Warning: Could not update CMS config: " . $cmsConfigResult['message']);
        }
        if (!$apiConfigResult['success']) {
            error_log("Warning: Could not update API config: " . $apiConfigResult['message']);
        }
        
        // Only fail if both failed and database was not restored
        if (!$cmsConfigResult['success'] && !$apiConfigResult['success'] && !$dbRestored) {
            $errorMsg = "Error al actualizar configuraciones:<br>";
            if (!$cmsConfigResult['success']) {
                $errorMsg .= "- CMS: " . $cmsConfigResult['message'] . "<br>";
            }
            if (!$apiConfigResult['success']) {
                $errorMsg .= "- API: " . $apiConfigResult['message'] . "<br>";
            }
            $errorMsg .= "<br><strong>Sugerencia:</strong> Ejecute estos comandos en la terminal para corregir los permisos:<br>";
            $errorMsg .= "<code>chmod 755 " . dirname(__DIR__) . "</code><br>";
            $errorMsg .= "<code>chmod 755 " . dirname(dirname(__DIR__)) . "/api</code><br>";
            $errorMsg .= "<code>chmod 644 " . dirname(__DIR__) . "/config.php</code><br>";
            $errorMsg .= "<code>chmod 644 " . dirname(dirname(__DIR__)) . "/api/config.php</code>";
            
            $errorMsgEscaped = json_encode($errorMsg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            echo '<script>
                fncMatPreloader("off");
                fncFormatInputs();
                fncSweetAlert("error", "Error de configuración", ' . $errorMsgEscaped . ');
            </script>';
            return;
        }
        
        // Step 3: Update database URLs if database was restored
        if ($dbRestored) {
            $urlUpdateResult = self::updateDatabaseUrls($dbConfig);
            if ($urlUpdateResult['success'] && $urlUpdateResult['updated_count'] > 0) {
                error_log("Updated " . $urlUpdateResult['updated_count'] . " database records with new domain URLs");
            }
        }
        
        // Step 4: Skip admin configuration updates for package installation
        // The database restoration already includes all admin information
        // Only update dashboard configuration if explicitly provided (optional)
        if (!empty($_POST['title_admin']) || !empty($_POST['symbol_admin']) || !empty($_POST['color_admin']) || !empty($_POST['font_admin']) || !empty($_POST['back_admin'])) {
            require_once __DIR__ . '/curl.controller.php';
            
            // Get first admin (usually superadmin)
            $url = "admins?linkTo=rol_admin&equalTo=superadmin";
            $method = "GET";
            $fields = array();
            $adminResult = CurlController::request($url, $method, $fields);
            
            if ($adminResult && isset($adminResult->status) && $adminResult->status == 200 && 
                isset($adminResult->results) && is_array($adminResult->results) && count($adminResult->results) > 0) {
                
                $adminId = $adminResult->results[0]->id_admin;
                $updateFields = array();
                
                // Only update dashboard appearance settings, not admin credentials
                if (!empty($_POST['title_admin'])) {
                    $updateFields['title_admin'] = trim($_POST['title_admin']);
                }
                if (!empty($_POST['symbol_admin'])) {
                    $updateFields['symbol_admin'] = trim($_POST['symbol_admin']);
                }
                if (!empty($_POST['color_admin'])) {
                    $updateFields['color_admin'] = trim($_POST['color_admin']);
                }
                if (!empty($_POST['font_admin'])) {
                    $updateFields['font_admin'] = trim($_POST['font_admin']);
                }
                if (!empty($_POST['back_admin'])) {
                    $updateFields['back_admin'] = trim($_POST['back_admin']);
                }
                
                if (!empty($updateFields)) {
                    $url = "admins?id=" . $adminId . "&nameId=id_admin&token=no&except=id_admin,email_admin,password_admin,rol_admin,permissions_admin,token_admin,token_exp_admin,status_admin,date_created_admin";
                    $method = "PUT";
                    CurlController::request($url, $method, $updateFields);
                }
            }
        }
        
        // Step 5: Skip admin password update for package installation
        // The password is already in the restored database
        
        // Delete database.sql file after successful installation (before showing success message)
        if ($dbRestored && $databaseFilePath && file_exists($databaseFilePath)) {
            $deleteResult = @unlink($databaseFilePath);
            if ($deleteResult) {
                error_log("Info: Deleted database.sql file after successful installation");
            } else {
                error_log("Warning: Could not delete database.sql file: " . $databaseFilePath);
            }
        }
        
        // Success message
        $successMsg = "Instalación completada exitosamente.";
        if ($dbRestored) {
            $successMsg .= " La base de datos ha sido restaurada y las referencias actualizadas.";
        }
        
        // Calculate redirect URL (to CMS root, which will redirect to login if not authenticated)
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $cmsBasePath = '';
        if (preg_match('#^(.*?/cms)(?:/|$)#', $scriptPath, $matches)) {
            $cmsBasePath = $matches[1];
        } else {
            $cmsBasePath = dirname($scriptPath);
        }
        $redirectUrl = rtrim($cmsBasePath, '/') . '/';
        
        $successMsgEscaped = json_encode($successMsg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        
        echo '<script>
            fncMatPreloader("off");
            fncFormatInputs();
            fncSweetAlert("success", "Instalación exitosa", ' . $successMsgEscaped . ', "");
            setTimeout(function() {
                window.location.href = "' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '";
            }, 2000);
        </script>';
    }
}

