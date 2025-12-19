<?php

/**
 * Updates Controller
 * 
 * Handles framework updates checking and installation
 */

require_once __DIR__ . '/../../core/version.php';

class UpdatesController {
    
    /**
     * Get configuration
     */
    private static function getConfig() {
        // Try CMS config first
        $configPath = __DIR__ . '/../config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            if (is_array($config)) {
                return $config;
            }
        }
        $examplePath = __DIR__ . '/../config.example.php';
        if (file_exists($examplePath)) {
            $config = require $examplePath;
            if (is_array($config)) {
                return $config;
            }
        }
        // Fallback to API config
        $apiConfigPath = __DIR__ . '/../../api/config.php';
        if (file_exists($apiConfigPath)) {
            $config = require $apiConfigPath;
            if (is_array($config)) {
                return $config;
            }
        }
        $apiExamplePath = __DIR__ . '/../../api/config.example.php';
        if (file_exists($apiExamplePath)) {
            $config = require $apiExamplePath;
            if (is_array($config)) {
                return $config;
            }
        }
        return [];
    }
    
    /**
     * Get database connection
     */
    private static function connect() {
        $config = self::getConfig();
        $dbConfig = $config['database'] ?? [];
        
        try {
            $link = new PDO(
                "mysql:host=" . ($dbConfig['host'] ?? 'localhost') . ";dbname=" . ($dbConfig['name'] ?? 'chatcenter'),
                $dbConfig['user'] ?? 'root',
                $dbConfig['pass'] ?? ''
            );
            $link->exec("set names " . ($dbConfig['charset'] ?? 'utf8mb4'));
            $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
        
        return $link;
    }
    
    /**
     * Check for available updates
     * 
     * This method checks a remote server or local file for available updates
     * You can configure the update server URL in config.php
     * 
     * @return array Update information
     */
    public static function checkForUpdates() {
        $currentVersion = VersionManager::getCurrentVersion();
        $config = self::getConfig();
        
        // Update server URL - can be configured in config.php
        $updateServerUrl = $config['updates']['server_url'] ?? 'https://updates.yourframework.com/api/check';
        
        // For now, we'll use a local JSON file as example
        // In production, this would be a remote API endpoint
        $updateInfoFile = __DIR__ . '/../../updates/update-info.json';
        
        $response = [
            'current_version' => $currentVersion,
            'update_available' => false,
            'latest_version' => $currentVersion,
            'update_info' => null,
            'error' => null
        ];
        
        try {
            // Try to get update info from local file first (for testing)
            if (file_exists($updateInfoFile)) {
                $updateInfo = json_decode(file_get_contents($updateInfoFile), true);
                
                if ($updateInfo && isset($updateInfo['latest_version'])) {
                    $latestVersion = $updateInfo['latest_version'];
                    $response['latest_version'] = $latestVersion;
                    
                    // Check if update_available is explicitly set in JSON, otherwise compare versions
                    if (isset($updateInfo['update_available'])) {
                        $response['update_available'] = (bool)$updateInfo['update_available'];
                    } else {
                        $response['update_available'] = VersionManager::isUpdateAvailable($latestVersion);
                    }
                    
                    if ($response['update_available']) {
                        $response['update_info'] = $updateInfo;
                        $response['is_major_update'] = VersionManager::isMajorUpdate($currentVersion, $latestVersion);
                    }
                }
            } else {
                // Try remote server (if configured)
                if (!empty($updateServerUrl) && filter_var($updateServerUrl, FILTER_VALIDATE_URL)) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $updateServerUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Framework-Update-Checker/1.0');
                    
                    // Send current version
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                        'current_version' => $currentVersion,
                        'framework' => 'web-framework'
                    ]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json'
                    ]);
                    
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode === 200 && $result) {
                        $updateInfo = json_decode($result, true);
                        
                        if ($updateInfo && isset($updateInfo['latest_version'])) {
                            $latestVersion = $updateInfo['latest_version'];
                            $response['latest_version'] = $latestVersion;
                            $response['update_available'] = VersionManager::isUpdateAvailable($latestVersion);
                            
                            if ($response['update_available']) {
                                $response['update_info'] = $updateInfo;
                                $response['is_major_update'] = VersionManager::isMajorUpdate($currentVersion, $latestVersion);
                            }
                        }
                    } else {
                        $response['error'] = 'Unable to connect to update server';
                    }
                }
            }
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
        
        return $response;
    }
    
    /**
     * Get update history from database
     */
    public static function getUpdateHistory() {
        $link = self::connect();
        
        try {
            // Check if updates table exists
            $stmt = $link->query("SHOW TABLES LIKE 'framework_updates'");
            if ($stmt->rowCount() === 0) {
                return [];
            }
            
            $stmt = $link->prepare("
                SELECT * FROM framework_updates 
                ORDER BY updated_at DESC 
                LIMIT 20
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Record update in database
     */
    private static function recordUpdate($fromVersion, $toVersion, $status, $notes = '') {
        $link = self::connect();
        
        try {
            // Create table if it doesn't exist
            $link->exec("
                CREATE TABLE IF NOT EXISTS framework_updates (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    from_version VARCHAR(20) NOT NULL,
                    to_version VARCHAR(20) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    notes TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_updated_at (updated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $stmt = $link->prepare("
                INSERT INTO framework_updates (from_version, to_version, status, notes)
                VALUES (:from_version, :to_version, :status, :notes)
            ");
            
            $stmt->execute([
                ':from_version' => $fromVersion,
                ':to_version' => $toVersion,
                ':status' => $status,
                ':notes' => $notes
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Error recording update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create backup before update
     */
    private static function createBackup() {
        $backupDir = __DIR__ . '/../../backups';
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $backupDir . '/backup_' . $timestamp;
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
        
        // Backup database
        $config = self::getConfig();
        $dbConfig = $config['database'] ?? [];
        
        $dbName = $dbConfig['name'] ?? 'chatcenter';
        $dbUser = $dbConfig['user'] ?? 'root';
        $dbPass = $dbConfig['pass'] ?? '';
        $dbHost = $dbConfig['host'] ?? 'localhost';
        
        $dbBackupFile = $backupPath . '/database.sql';
        
        // Create database backup using mysqldump
        $command = sprintf(
            'mysqldump -h %s -u %s %s %s > %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            !empty($dbPass) ? '-p' . escapeshellarg($dbPass) : '',
            escapeshellarg($dbName),
            escapeshellarg($dbBackupFile)
        );
        
        exec($command, $output, $returnCode);
        
        // Backup critical files
        $filesToBackup = [
            'api/config.php',
            'cms/config.php',
            'VERSION'
        ];
        
        foreach ($filesToBackup as $file) {
            $sourcePath = __DIR__ . '/../../' . $file;
            if (file_exists($sourcePath)) {
                $destPath = $backupPath . '/' . str_replace('/', '_', $file);
                copy($sourcePath, $destPath);
            }
        }
        
        return [
            'success' => $returnCode === 0,
            'backup_path' => $backupPath,
            'db_backup' => file_exists($dbBackupFile) ? $dbBackupFile : null
        ];
    }
    
    /**
     * Process framework update
     * 
     * This is a simplified version. In production, you would:
     * 1. Download the update package
     * 2. Verify integrity (checksums)
     * 3. Extract files
     * 4. Run migrations
     * 5. Update version
     */
    public static function processUpdate($targetVersion) {
        $currentVersion = VersionManager::getCurrentVersion();
        
        // Validate target version
        if (!preg_match('/^\d+\.\d+\.\d+$/', $targetVersion)) {
            return [
                'success' => false,
                'error' => 'Invalid version format'
            ];
        }
        
        // Check if update is valid
        if (VersionManager::compareVersions($currentVersion, $targetVersion) >= 0) {
            return [
                'success' => false,
                'error' => 'Target version must be greater than current version'
            ];
        }
        
        try {
            // Step 1: Create backup
            $backup = self::createBackup();
            if (!$backup['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to create backup'
                ];
            }
            
            // Step 2: Run database migrations
            $migrationResult = self::runMigrations($currentVersion, $targetVersion);
            
            // Step 3: Update files (in production, this would download and extract)
            // For now, we'll just update the version file
            // In production, you would:
            // - Download update package from server
            // - Verify checksum
            // - Extract to temporary directory
            // - Replace files (preserving config.php)
            // - Run post-update scripts
            
            // Step 4: Update version
            VersionManager::updateVersion($targetVersion);
            
            // Step 5: Record update
            $status = $migrationResult['success'] ? 'completed' : 'completed_with_warnings';
            self::recordUpdate(
                $currentVersion,
                $targetVersion,
                $status,
                $migrationResult['notes'] ?? ''
            );
            
            return [
                'success' => true,
                'from_version' => $currentVersion,
                'to_version' => $targetVersion,
                'backup_path' => $backup['backup_path'],
                'migrations' => $migrationResult
            ];
            
        } catch (Exception $e) {
            self::recordUpdate(
                $currentVersion,
                $targetVersion,
                'failed',
                'Error: ' . $e->getMessage()
            );
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Run database migrations for version update
     */
    private static function runMigrations($fromVersion, $toVersion) {
        $link = self::connect();
        $migrationsDir = __DIR__ . '/../../migrations';
        $migrationsRun = [];
        $errors = [];
        
        try {
            // Create migrations table if it doesn't exist
            $link->exec("
                CREATE TABLE IF NOT EXISTS framework_migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    version VARCHAR(20) NOT NULL,
                    migration_file VARCHAR(255) NOT NULL,
                    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_version_file (version, migration_file)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Get all migration files for versions between from and to
            if (is_dir($migrationsDir)) {
                $files = glob($migrationsDir . '/*.sql');
                
                foreach ($files as $file) {
                    $filename = basename($file);
                    // Migration files should be named like: 1.0.0_to_1.0.1.sql
                    if (preg_match('/^(\d+\.\d+\.\d+)_to_(\d+\.\d+\.\d+)\.sql$/', $filename, $matches)) {
                        $migrationFrom = $matches[1];
                        $migrationTo = $matches[2];
                        
                        // Check if this migration is in our version range
                        if (VersionManager::compareVersions($migrationFrom, $fromVersion) >= 0 &&
                            VersionManager::compareVersions($migrationTo, $toVersion) <= 0) {
                            
                            // Check if already executed
                            $stmt = $link->prepare("
                                SELECT COUNT(*) FROM framework_migrations 
                                WHERE version = :version AND migration_file = :file
                            ");
                            $stmt->execute([
                                ':version' => $migrationTo,
                                ':file' => $filename
                            ]);
                            
                            if ($stmt->fetchColumn() == 0) {
                                // Execute migration
                                $sql = file_get_contents($file);
                                
                                // Split by semicolons and execute each statement
                                $statements = array_filter(
                                    array_map('trim', explode(';', $sql)),
                                    function($stmt) {
                                        return !empty($stmt) && !preg_match('/^--/', $stmt);
                                    }
                                );
                                
                                $link->beginTransaction();
                                
                                try {
                                    foreach ($statements as $statement) {
                                        $link->exec($statement);
                                    }
                                    
                                    // Record migration
                                    $stmt = $link->prepare("
                                        INSERT INTO framework_migrations (version, migration_file)
                                        VALUES (:version, :file)
                                    ");
                                    $stmt->execute([
                                        ':version' => $migrationTo,
                                        ':file' => $filename
                                    ]);
                                    
                                    $link->commit();
                                    $migrationsRun[] = $filename;
                                    
                                } catch (PDOException $e) {
                                    $link->rollBack();
                                    $errors[] = "Error in $filename: " . $e->getMessage();
                                }
                            }
                        }
                    }
                }
            }
            
            return [
                'success' => empty($errors),
                'migrations_run' => $migrationsRun,
                'errors' => $errors,
                'notes' => empty($errors) 
                    ? 'All migrations executed successfully' 
                    : 'Some migrations had errors: ' . implode(', ', $errors)
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'errors' => [$e->getMessage()],
                'notes' => 'Migration execution failed'
            ];
        }
    }
}
