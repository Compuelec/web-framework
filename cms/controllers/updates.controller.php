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
     * Check GitHub for latest release
     * 
     * @param string $owner GitHub repository owner
     * @param string $repo GitHub repository name
     * @param string $token Optional GitHub token for private repos
     * @return array|null Release information or null on error
     */
    private static function checkGitHubRelease($owner, $repo, $token = null) {
        $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Framework-Update-Checker/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $headers = ['Accept: application/vnd.github.v3+json'];
        if ($token) {
            $headers[] = "Authorization: token {$token}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && $result) {
            $release = json_decode($result, true);
            if ($release && isset($release['tag_name'])) {
                return $release;
            }
        } else if ($httpCode === 404) {
            // Try tags API if releases/latest doesn't exist
            return self::checkGitHubTag($owner, $repo, $token);
        }
        
        return null;
    }
    
    /**
     * Check GitHub for latest tag (fallback if no releases)
     * 
     * @param string $owner GitHub repository owner
     * @param string $repo GitHub repository name
     * @param string $token Optional GitHub token
     * @return array|null Tag information or null on error
     */
    private static function checkGitHubTag($owner, $repo, $token = null) {
        $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/tags";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Framework-Update-Checker/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $headers = ['Accept: application/vnd.github.v3+json'];
        if ($token) {
            $headers[] = "Authorization: token {$token}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $result) {
            $tags = json_decode($result, true);
            if (is_array($tags) && count($tags) > 0) {
                // Get the first tag (usually the latest)
                $tag = $tags[0];
                return [
                    'tag_name' => $tag['name'],
                    'zipball_url' => $tag['zipball_url'] ?? "https://github.com/{$owner}/{$repo}/archive/refs/tags/{$tag['name']}.zip"
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Check for available updates
     * 
     * This method checks GitHub releases/tags, remote server, or local file for available updates
     * Priority: GitHub > Remote Server > Local File
     * 
     * @return array Update information
     */
    public static function checkForUpdates() {
        $currentVersion = VersionManager::getCurrentVersion();
        $config = self::getConfig();
        
        $response = [
            'current_version' => $currentVersion,
            'update_available' => false,
            'latest_version' => $currentVersion,
            'update_info' => null,
            'error' => null
        ];
        
        try {
            $updateConfig = $config['updates'] ?? [];
            
            // Priority 1: Check GitHub if configured
            if (isset($updateConfig['github_owner']) && isset($updateConfig['github_repo'])) {
                $owner = $updateConfig['github_owner'];
                $repo = $updateConfig['github_repo'];
                $token = $updateConfig['github_token'] ?? null;
                
                $release = self::checkGitHubRelease($owner, $repo, $token);
                
                if ($release) {
                    // Extract version from tag (remove 'v' prefix if present)
                    $latestVersion = preg_replace('/^v/', '', $release['tag_name']);
                    
                    // Validate version format
                    if (preg_match('/^\d+\.\d+\.\d+/', $latestVersion)) {
                        $response['latest_version'] = $latestVersion;
                        $response['update_available'] = VersionManager::isUpdateAvailable($latestVersion);
                        
                        if ($response['update_available']) {
                            // Find zipball URL
                            $zipUrl = null;
                            if (isset($release['zipball_url'])) {
                                $zipUrl = $release['zipball_url'];
                            } else if (isset($release['assets'])) {
                                foreach ($release['assets'] as $asset) {
                                    if (isset($asset['browser_download_url']) && 
                                        pathinfo($asset['browser_download_url'], PATHINFO_EXTENSION) === 'zip') {
                                        $zipUrl = $asset['browser_download_url'];
                                        break;
                                    }
                                }
                            }
                            
                            // Fallback: construct zip URL from tag
                            if (!$zipUrl) {
                                $zipUrl = "https://github.com/{$owner}/{$repo}/archive/refs/tags/{$release['tag_name']}.zip";
                            }
                            
                            $response['update_info'] = [
                                'latest_version' => $latestVersion,
                                'release_date' => isset($release['published_at']) ? date('Y-m-d', strtotime($release['published_at'])) : null,
                                'changelog' => [
                                    $latestVersion => [
                                        'version' => $latestVersion,
                                        'release_date' => isset($release['published_at']) ? date('Y-m-d', strtotime($release['published_at'])) : null,
                                        'type' => VersionManager::isMajorUpdate($currentVersion, $latestVersion) ? 'major' : 'minor',
                                        'changes' => isset($release['body']) ? array_filter(explode("\n", $release['body'])) : [],
                                        'breaking_changes' => VersionManager::isMajorUpdate($currentVersion, $latestVersion),
                                        'requires_migration' => false
                                    ]
                                ],
                                'download_url' => $zipUrl,
                                'source' => 'github',
                                'github_release' => $release
                            ];
                            $response['is_major_update'] = VersionManager::isMajorUpdate($currentVersion, $latestVersion);
                        }
                    }
                } else {
                    $response['error'] = 'Unable to fetch GitHub release information';
                }
            } 
            // Priority 2: Check remote server (if configured and GitHub not used)
            else {
                $updateServerUrl = $updateConfig['server_url'] ?? null;
                $updateInfoFile = __DIR__ . '/../../updates/update-info.json';
                
                // Try local file first (for testing)
                if (file_exists($updateInfoFile)) {
                    $updateInfo = json_decode(file_get_contents($updateInfoFile), true);
                    
                    if ($updateInfo && isset($updateInfo['latest_version'])) {
                        $latestVersion = $updateInfo['latest_version'];
                        $response['latest_version'] = $latestVersion;
                        
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
                } 
                // Try remote server (if configured)
                else if (!empty($updateServerUrl) && filter_var($updateServerUrl, FILTER_VALIDATE_URL)) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $updateServerUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Framework-Update-Checker/1.0');
                    
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
     * Find mysqldump executable path
     * 
     * @return string The path to mysqldump or 'mysqldump' if not found in common paths
     */
    private static function findMysqldumpPath() {
        $config = self::getConfig();
        // 1. Check if path is configured in cms/config.php
        if (isset($config['database']['mysqldump_path']) && !empty($config['database']['mysqldump_path'])) {
            return $config['database']['mysqldump_path'];
        }
        
        // 2. Check common paths for XAMPP (macOS/Linux) and MAMP (macOS)
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
        
        // 3. Fallback: rely on system PATH
        return 'mysqldump';
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

        $mysqldumpPath = self::findMysqldumpPath();
        // Create database backup using mysqldump
        $command = sprintf(
            '%s -h %s -u %s%s %s > %s 2>&1',
            escapeshellarg($mysqldumpPath),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            !empty($dbPass) ? ' -p' . escapeshellarg($dbPass) : '',
            escapeshellarg($dbName),
            escapeshellarg($dbBackupFile)
        );

        exec($command, $output, $returnCode);

        // If backup failed, return error info
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'backup_path' => $backupPath,
                'error' => 'Database backup failed. Return code: ' . $returnCode . '. Output: ' . implode("\n", $output)
            ];
        }

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
     * Download ZIP file from URL
     * 
     * @param string $url URL to download from
     * @param string $destination Destination file path
     * @param string $token Optional GitHub token for private repos
     * @return array Result with success status and file path
     */
    private static function downloadZip($url, $destination, $token = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout for large files
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Framework-Update-Checker/1.0');
        
        $headers = [];
        if ($token) {
            $headers[] = "Authorization: token {$token}";
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $fp = fopen($destination, 'w');
        if (!$fp) {
            return [
                'success' => false,
                'error' => 'Cannot create destination file'
            ];
        }
        
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        
        if ($httpCode === 200 && file_exists($destination) && filesize($destination) > 0) {
            return [
                'success' => true,
                'file_path' => $destination,
                'file_size' => filesize($destination)
            ];
        } else {
            if (file_exists($destination)) {
                unlink($destination);
            }
            return [
                'success' => false,
                'error' => $curlError ?: "HTTP {$httpCode}: Failed to download update"
            ];
        }
    }
    
    /**
     * Extract ZIP file to destination
     * 
     * @param string $zipPath Path to ZIP file
     * @param string $destination Destination directory
     * @return array Result with success status
     */
    private static function extractZip($zipPath, $destination) {
        if (!class_exists('ZipArchive')) {
            return [
                'success' => false,
                'error' => 'ZipArchive class not available. Please install php-zip extension.'
            ];
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);
        
        if ($result !== TRUE) {
            return [
                'success' => false,
                'error' => "Cannot open ZIP file. Error code: {$result}"
            ];
        }
        
        // Create destination directory
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        // Extract all files
        $extracted = $zip->extractTo($destination);
        $zip->close();
        
        if (!$extracted) {
            return [
                'success' => false,
                'error' => 'Failed to extract ZIP file'
            ];
        }
        
        return [
            'success' => true,
            'extracted_to' => $destination
        ];
    }
    
    /**
     * Copy files from extracted update to project, preserving config files
     * 
     * @param string $sourceDir Source directory (extracted update)
     * @param string $targetDir Target directory (project root)
     * @return array Result with success status and files copied
     */
    private static function copyUpdateFiles($sourceDir, $targetDir) {
        $filesCopied = [];
        $errors = [];
        $preservedFiles = [
            'api/config.php',
            'cms/config.php',
            'VERSION'
        ];
        
        // Find the actual project directory inside the extracted zip
        // GitHub zips usually have format: owner-repo-tag/
        $dirs = glob($sourceDir . '/*', GLOB_ONLYDIR);
        if (count($dirs) > 0) {
            $sourceDir = $dirs[0];
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $sourcePath);
            
            // Skip preserved files
            $shouldPreserve = false;
            foreach ($preservedFiles as $preserved) {
                if (strpos($relativePath, $preserved) !== false) {
                    $shouldPreserve = true;
                    break;
                }
            }
            
            if ($shouldPreserve) {
                continue;
            }
            
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDirPath = dirname($targetPath);
                if (!is_dir($targetDirPath)) {
                    mkdir($targetDirPath, 0755, true);
                }
                
                if (copy($sourcePath, $targetPath)) {
                    $filesCopied[] = $relativePath;
                } else {
                    $errors[] = "Failed to copy: {$relativePath}";
                }
            }
        }
        
        return [
            'success' => empty($errors),
            'files_copied' => $filesCopied,
            'errors' => $errors
        ];
    }
    
    /**
     * Process framework update
     * 
     * Downloads update from GitHub or configured source, extracts and installs it
     * 
     * @param string $targetVersion Target version to update to
     * @return array Result with success status and update details
     */
    public static function processUpdate($targetVersion) {
        $currentVersion = VersionManager::getCurrentVersion();
        $config = self::getConfig();
        
        // Validate target version
        if (!preg_match('/^\d+\.\d+\.\d+/', $targetVersion)) {
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
                    'error' => 'Failed to create backup: ' . ($backup['error'] ?? 'Unknown error')
                ];
            }
            
            // Step 2: Get update info to find download URL
            $updateInfo = self::checkForUpdates();
            if (!$updateInfo['update_available'] || !isset($updateInfo['update_info']['download_url'])) {
                return [
                    'success' => false,
                    'error' => 'Update information not available or download URL not found'
                ];
            }
            
            $downloadUrl = $updateInfo['update_info']['download_url'];
            $updateConfig = $config['updates'] ?? [];
            $token = $updateConfig['github_token'] ?? null;
            
            // Step 3: Download update package
            // Use project's temp directory instead of system temp to avoid permission issues
            $projectTempDir = __DIR__ . '/../../tmp';
            if (!is_dir($projectTempDir)) {
                if (!mkdir($projectTempDir, 0755, true)) {
                    return [
                        'success' => false,
                        'error' => 'Failed to create temporary directory. Please ensure the project directory is writable.'
                    ];
                }
            }
            
            // Clean up old temporary directories (older than 1 hour)
            self::cleanupOldTempDirs($projectTempDir);
            
            $tempDir = $projectTempDir . '/framework_update_' . time();
            if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create temporary directory for update. Please check directory permissions.'
                ];
            }
            
            $zipPath = $tempDir . '/update.zip';
            $downloadResult = self::downloadZip($downloadUrl, $zipPath, $token);
            
            if (!$downloadResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to download update: ' . ($downloadResult['error'] ?? 'Unknown error')
                ];
            }
            
            // Step 4: Extract ZIP
            $extractDir = $tempDir . '/extracted';
            $extractResult = self::extractZip($zipPath, $extractDir);
            
            if (!$extractResult['success']) {
                unlink($zipPath);
                return [
                    'success' => false,
                    'error' => 'Failed to extract update: ' . ($extractResult['error'] ?? 'Unknown error')
                ];
            }
            
            // Step 5: Copy files to project (preserving config files)
            $projectRoot = __DIR__ . '/../../';
            $copyResult = self::copyUpdateFiles($extractDir, $projectRoot);
            
            // Clean up temporary files
            self::deleteDirectory($tempDir);
            
            if (!$copyResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to copy update files: ' . implode(', ', $copyResult['errors'])
                ];
            }
            
            // Step 6: Run database migrations
            $migrationResult = self::runMigrations($currentVersion, $targetVersion);
            
            // Step 7: Update version
            VersionManager::updateVersion($targetVersion);
            
            // Step 8: Record update
            $status = ($migrationResult['success'] && $copyResult['success']) ? 'completed' : 'completed_with_warnings';
            $notes = [];
            if (!$migrationResult['success']) {
                $notes[] = 'Migration warnings: ' . ($migrationResult['notes'] ?? 'Unknown');
            }
            if (!empty($copyResult['errors'])) {
                $notes[] = 'File copy errors: ' . implode(', ', $copyResult['errors']);
            }
            
            self::recordUpdate(
                $currentVersion,
                $targetVersion,
                $status,
                implode(' | ', $notes)
            );
            
            return [
                'success' => true,
                'from_version' => $currentVersion,
                'to_version' => $targetVersion,
                'backup_path' => $backup['backup_path'],
                'migrations' => $migrationResult,
                'files_copied' => count($copyResult['files_copied']),
                'copy_errors' => $copyResult['errors']
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
     * Clean up old temporary directories
     * 
     * @param string $tempDir Base temporary directory
     */
    private static function cleanupOldTempDirs($tempDir) {
        if (!is_dir($tempDir)) {
            return;
        }
        
        $dirs = glob($tempDir . '/framework_update_*', GLOB_ONLYDIR);
        $oneHourAgo = time() - 3600; // 1 hour ago
        
        foreach ($dirs as $dir) {
            // Extract timestamp from directory name
            if (preg_match('/framework_update_(\d+)$/', $dir, $matches)) {
                $dirTimestamp = (int)$matches[1];
                if ($dirTimestamp < $oneHourAgo) {
                    self::deleteDirectory($dir);
                }
            }
        }
    }
    
    /**
     * Delete directory recursively
     * 
     * @param string $dir Directory to delete
     * @return bool Success status
     */
    private static function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
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
