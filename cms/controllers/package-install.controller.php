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
    /**
     * Locate a usable `mysql` client binary, or null if none is found.
     */
    private static function findMysqlBinary() {
        $candidates = [
            '/Applications/XAMPP/bin/mysql', '/opt/lampp/bin/mysql',
            '/usr/bin/mysql', '/usr/local/bin/mysql', '/usr/local/mysql/bin/mysql',
        ];
        foreach ($candidates as $c) {
            if (@is_file($c) && @is_executable($c)) { return $c; }
        }
        if (function_exists('shell_exec')) {
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (!in_array('shell_exec', $disabled, true)) {
                $which = @shell_exec('command -v mysql 2>/dev/null');
                $which = is_string($which) ? trim($which) : '';
                if ($which !== '' && @is_executable($which)) { return $which; }
            }
        }
        return null;
    }

    /**
     * Import a dump with the mysql client (streamed, memory-safe). Returns the
     * result array on success, or null when the client/shell isn't available or
     * the import failed — so the caller can fall back to the PHP parser.
     */
    private static function restoreViaMysqlCli($dbConfig, $dbFile) {
        if (!function_exists('proc_open')) { return null; }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('proc_open', $disabled, true)) { return null; }

        $bin = self::findMysqlBinary();
        if ($bin === null) { return null; }

        $cmd = escapeshellarg($bin)
             . ' --host=' . escapeshellarg($dbConfig['host'])
             . ' --user=' . escapeshellarg($dbConfig['user'])
             . ' --default-character-set=utf8mb4 '
             . escapeshellarg($dbConfig['name']);

        $descriptors = [
            0 => ['file', $dbFile, 'r'],   // stdin ← the dump (streamed)
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        // Pass the password via the environment, never on the command line.
        // getenv() is more reliable than $_ENV (which can be empty per variables_order).
        $env = array_merge(getenv() ?: [], ['MYSQL_PWD' => (string) $dbConfig['pass']]);
        $proc = @proc_open($cmd, $descriptors, $pipes, null, $env);
        if (!is_resource($proc)) { return null; }

        $stderr = is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';
        foreach ($pipes as $p) { if (is_resource($p)) { fclose($p); } }
        $code = proc_close($proc);

        if ($code === 0) {
            return ['success' => true, 'message' => 'Base de datos restaurada con el cliente mysql.', 'method' => 'cli'];
        }
        error_log('Restore via mysql CLI failed (code ' . $code . '): ' . substr((string) $stderr, 0, 300));
        return null; // fall back to the PHP parser
    }

    /* ---- recursive fs helpers (used by the upload restore) ---- */
    private static function locateInTree($dir, $name) {
        $direct = $dir . '/' . $name;
        if (is_file($direct)) { return $direct; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if ($f->isFile() && $f->getFilename() === $name) { return $f->getPathname(); } }
        return null;
    }
    private static function copyTree($src, $dst) {
        if (!is_dir($dst)) { @mkdir($dst, 0775, true); }
        $count = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            $sub = $it->getSubPathName();
            if (strpos($sub, '..') !== false) { continue; } // guard against path traversal
            $target = $dst . '/' . $sub;
            if ($item->isDir()) { if (!is_dir($target)) { @mkdir($target, 0775, true); } }
            elseif (@copy($item->getPathname(), $target)) { $count++; }
        }
        return $count;
    }
    private static function rrmdir($dir) {
        if (!is_dir($dir)) { return; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($dir);
    }

    /** True only for a well-formed http(s) base URL with a real host. Guards the
     *  URL rewrite against bogus domains (e.g. when not in a real web request). */
    private static function isSaneBaseUrl($url) {
        if (!is_string($url) || $url === '') { return false; }
        $p = parse_url($url);
        if (empty($p['scheme']) || empty($p['host'])) { return false; }
        if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) { return false; }
        if (isset($p['path']) && strpos($p['path'], '/.') !== false) { return false; }
        return true;
    }

    /**
     * Restore the platform from an uploaded package .zip: extract it, import its
     * database.sql, (optionally) copy back the uploaded files, and rewrite the
     * stored URLs to this server's domain. Does NOT overwrite the running code.
     */
    public static function restoreFromZip($zipPath, $includeFiles = true) {
        if (!class_exists('ZipArchive')) { return ['success' => false, 'message' => 'ZipArchive no está disponible en el servidor.']; }
        if (!is_file($zipPath)) { return ['success' => false, 'message' => 'Archivo de paquete no encontrado.']; }

        $rootDir = dirname(dirname(__DIR__));

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) { return ['success' => false, 'message' => 'No se pudo abrir el ZIP (¿archivo válido?).']; }

        // Create the temp extraction dir in the first writable location. The system
        // temp dir is often unwritable for the web-server user (it inherits the
        // launching user's TMPDIR), so fall back to app-owned dirs like packages/.
        $tmp = null;
        foreach ([sys_get_temp_dir(), $rootDir . '/packages', $rootDir . '/cms', $rootDir] as $base) {
            if (!$base) { continue; }
            $candidate = rtrim($base, '/') . '/pkg_restore_' . bin2hex(random_bytes(6));
            if (@mkdir($candidate, 0775, true)) { $tmp = $candidate; break; }
        }
        if ($tmp === null) {
            $zip->close();
            return ['success' => false, 'message' => 'No se pudo crear un directorio temporal escribible (revisa permisos de escritura del servidor).'];
        }

        // Everything below runs with the temp dir present; the finally guarantees
        // it (and the sensitive dump inside) is always removed, even on error.
        try {
            if (!$zip->extractTo($tmp)) { $zip->close(); return ['success' => false, 'message' => 'No se pudo extraer el ZIP.']; }
            $zip->close();

            $dbFile = self::locateInTree($tmp, 'database.sql');
            if ($dbFile === null) { return ['success' => false, 'message' => 'El paquete no contiene database.sql.']; }
            $packageRoot = dirname($dbFile);

            require_once __DIR__ . '/install.controller.php';
            require_once __DIR__ . '/path-updater.controller.php';
            $config = InstallController::getConfig();
            $dbConfig = $config['database'] ?? [];
            if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
                return ['success' => false, 'message' => 'La base de datos no está configurada en cms/config.php.'];
            }

            // 1) restore the database from the package dump (overwrites current data)
            $restore = self::restoreDatabase($dbConfig, $dbFile);
            if (!$restore['success']) { return ['success' => false, 'message' => 'Restauración de BD: ' . $restore['message']]; }

            // 2) bring back the uploaded files so images/logos resolve
            $filesCopied = 0;
            if ($includeFiles) {
                $srcFiles = $packageRoot . '/cms/views/assets/files';
                if (is_dir($srcFiles)) { $filesCopied = self::copyTree($srcFiles, $rootDir . '/cms/views/assets/files'); }
            }

            // 3) rewrite stored URLs to this server's domain (config + database).
            // Only when the detected domain is a sane http(s) URL — otherwise (e.g. run
            // outside a normal web request) skip it so we never corrupt stored URLs.
            $domainInfo = PathUpdaterController::detectDomain();
            $newDomain  = $domainInfo['base_url'] ?? '';
            $oldDomain  = PathUpdaterController::detectOldDomainFromDatabase($dbConfig);
            $urlsUpdated = 0;
            if (self::isSaneBaseUrl($newDomain)) {
                PathUpdaterController::updateCmsConfigUrlsOnly($domainInfo);
                PathUpdaterController::updateApiConfigUrlsOnly($domainInfo);
                PathUpdaterController::updateWebConfigUrlsOnly($domainInfo);
                if ($oldDomain && $oldDomain !== $newDomain) {
                    $up = PathUpdaterController::updateDatabaseUrls($oldDomain, $newDomain, $dbConfig);
                    $urlsUpdated = $up['updated_count'] ?? 0;
                }
            } else {
                error_log('restoreFromZip: skipped URL rewrite — unsafe detected domain: ' . $newDomain);
            }

            // Drop the opcode cache so the rewritten config files (and any restored
            // code) are picked up on the next request, and refresh stat caches.
            if (function_exists('opcache_reset')) { @opcache_reset(); }
            clearstatcache();

            return [
                'success'      => true,
                'message'      => 'Restauración completada.',
                'method'       => $restore['method'] ?? 'pdo',
                'files_copied' => $filesCopied,
                'urls_updated' => $urlsUpdated,
                'old_domain'   => $oldDomain,
                'new_domain'   => $newDomain,
            ];
        } finally {
            self::rrmdir($tmp);
        }
    }

    public static function restoreDatabase($dbConfig, $dbFile = null) {
        $rootDir = dirname(dirname(__DIR__));
        if ($dbFile === null) { $dbFile = $rootDir . '/database.sql'; }

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

            // Fast path: import with the mysql client when available — it streams
            // the dump instead of loading it into PHP memory, so large databases
            // restore without timeouts/OOM. Falls through to the PDO parser below
            // when the binary or shell exec isn't available.
            $cli = self::restoreViaMysqlCli($dbConfig, $dbFile);
            if ($cli !== null) { return $cli; }

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
        // Update the public site config (web/config.php) too — otherwise its
        // base_url keeps the packaged domain and the public pages load cross-origin.
        $webConfigResult = PathUpdaterController::updateWebConfigUrlsOnly($domainInfo);

        // Log warnings but don't block installation if config files can't be written
        // The database restoration is more critical
        if (!$cmsConfigResult['success']) {
            error_log("Warning: Could not update CMS config: " . $cmsConfigResult['message']);
        }
        if (!$apiConfigResult['success']) {
            error_log("Warning: Could not update API config: " . $apiConfigResult['message']);
        }
        if (!$webConfigResult['success']) {
            error_log("Warning: Could not update public site (web) config: " . $webConfigResult['message']);
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
        if ($dbRestored) {
            // Get database file path if not already set
            if ($databaseFilePath === null) {
                $rootDir = dirname(dirname(__DIR__));
                $databaseFilePath = $rootDir . '/database.sql';
            }
            
            if ($databaseFilePath && file_exists($databaseFilePath)) {
                // Try to delete the file
                $deleteResult = @unlink($databaseFilePath);
                if ($deleteResult) {
                    error_log("Info: Successfully deleted database.sql file after installation: " . $databaseFilePath);
                } else {
                    // Try to get more info about why it failed
                    $error = error_get_last();
                    $filePerms = file_exists($databaseFilePath) ? substr(sprintf('%o', fileperms($databaseFilePath)), -4) : 'N/A';
                    $dirPerms = is_dir(dirname($databaseFilePath)) ? substr(sprintf('%o', fileperms(dirname($databaseFilePath))), -4) : 'N/A';
                    error_log("Warning: Could not delete database.sql file: " . $databaseFilePath . ". File perms: $filePerms, Dir perms: $dirPerms. Error: " . ($error['message'] ?? 'Unknown'));
                }
            } else {
                error_log("Info: database.sql file not found or already deleted: " . ($databaseFilePath ?? 'N/A'));
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

