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
            
            // Split SQL into individual statements
            $statements = array_filter(
                array_map('trim', explode(';', $sqlContent)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^--/', $stmt) && !preg_match('/^\/\*/', $stmt);
                }
            );
            
            // Execute each statement
            $executed = 0;
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    try {
                        $link->exec($statement);
                        $executed++;
                    } catch (PDOException $e) {
                        // Log but continue - some statements might fail (like DROP TABLE IF EXISTS on non-existent tables)
                        error_log("SQL execution warning: " . $e->getMessage());
                    }
                }
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
        if (!isset($_POST["email_admin"])) {
            return;
        }
        
        // Detect domain and update configurations automatically
        $domainInfo = PathUpdaterController::detectDomain();
        
        // Get database configuration from form
        $dbConfig = [
            'host' => $_POST['db_host'] ?? '',
            'name' => $_POST['db_name'] ?? '',
            'user' => $_POST['db_user'] ?? '',
            'pass' => $_POST['db_pass'] ?? ''
        ];
        
        // Validate database configuration
        if (empty($dbConfig['host']) || empty($dbConfig['name']) || empty($dbConfig['user']) || !isset($dbConfig['pass'])) {
            $errorMsgEscaped = json_encode("Todos los campos de base de datos son requeridos", JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
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
        if (self::hasDatabaseFile()) {
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
        
        if (!$cmsConfigResult['success'] || !$apiConfigResult['success']) {
            $errorMsg = "Error al actualizar configuraciones:<br>";
            if (!$cmsConfigResult['success']) {
                $errorMsg .= "- CMS: " . $cmsConfigResult['message'] . "<br>";
            }
            if (!$apiConfigResult['success']) {
                $errorMsg .= "- API: " . $apiConfigResult['message'] . "<br>";
            }
            
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
        
        // Step 4: Update admin configuration if provided
        if (!empty($_POST['title_admin']) || !empty($_POST['symbol_admin']) || !empty($_POST['color_admin']) || !empty($_POST['font_admin'])) {
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
        
        // Step 5: Update admin password if provided
        if (!empty($_POST['password_admin'])) {
            require_once __DIR__ . '/curl.controller.php';
            
            $url = "admins?linkTo=rol_admin&equalTo=superadmin";
            $method = "GET";
            $fields = array();
            $adminResult = CurlController::request($url, $method, $fields);
            
            if ($adminResult && isset($adminResult->status) && $adminResult->status == 200 && 
                isset($adminResult->results) && is_array($adminResult->results) && count($adminResult->results) > 0) {
                
                $adminId = $adminResult->results[0]->id_admin;
                $url = "admins?id=" . $adminId . "&nameId=id_admin&token=no&except=id_admin,email_admin,rol_admin,permissions_admin,token_admin,token_exp_admin,status_admin,date_created_admin";
                $method = "PUT";
                $updateFields = array('password_admin' => trim($_POST['password_admin']));
                CurlController::request($url, $method, $updateFields);
            }
        }
        
        // Success message
        $successMsg = "Instalación completada exitosamente.";
        if ($dbRestored) {
            $successMsg .= " La base de datos ha sido restaurada y las referencias actualizadas.";
        }
        
        $successMsgEscaped = json_encode($successMsg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        echo '<script>
            fncMatPreloader("off");
            fncFormatInputs();
            fncSweetAlert("success", "Instalación exitosa", ' . $successMsgEscaped . ', function() {
                location.href = "../";
            });
        </script>';
    }
}

