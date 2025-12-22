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
        } else {
            $errorMessage = "HTTP $httpCode";
            if ($result) {
                $decoded = json_decode($result, true);
                if (isset($decoded['message'])) {
                    $errorMessage .= ": " . $decoded['message'];
                }
            }
            if ($curlError) {
                $errorMessage .= " (cURL: $curlError)";
            }
            error_log("GitHub API Error: $errorMessage");
            self::$lastGitHubError = $errorMessage;
            
            if ($httpCode === 404) {
                // Try tags API if releases/latest doesn't exist
                return self::checkGitHubTag($owner, $repo, $token);
            }
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
        $curlError = curl_error($ch);
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
        } else {
            $errorMessage = "HTTP $httpCode";
            if ($result) {
                $decoded = json_decode($result, true);
                if (isset($decoded['message'])) {
                    $errorMessage .= ": " . $decoded['message'];
                }
            }
            if ($curlError) {
                $errorMessage .= " (cURL: $curlError)";
            }
            self::$lastGitHubError = $errorMessage;
        }
        
        return null;
    }
    
    /**
     * Check GitHub for a specific release by tag
     * 
     * @param string $owner GitHub repository owner
     * @param string $repo GitHub repository name
     * @param string $tag Tag name (e.g., "1.0.1" or "v1.0.1")
     * @param string $token Optional GitHub token
     * @return array|null Release information or null on error
     */
    private static function checkGitHubReleaseByTag($owner, $repo, $tag, $token = null) {
        $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/releases/tags/{$tag}";
        
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
        } else {
            $errorMessage = "HTTP $httpCode";
            if ($result) {
                $decoded = json_decode($result, true);
                if (isset($decoded['message'])) {
                    $errorMessage .= ": " . $decoded['message'];
                }
            }
            if ($curlError) {
                $errorMessage .= " (cURL: $curlError)";
            }
            error_log("GitHub API Error: $errorMessage");
            self::$lastGitHubError = $errorMessage;
        }
        
        return null;
    }

    private static $lastGitHubError = null;
    private static $lastPermissionsError = null;

    public static function getLastGitHubError() {
        return self::$lastGitHubError;
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
                            
                            // Procesar el body del release para extraer los cambios
                            $changes = [];
                            if (isset($release['body']) && !empty($release['body'])) {
                                $body = trim($release['body']);
                                // Dividir por líneas y procesar
                                $lines = explode("\n", $body);
                                foreach ($lines as $line) {
                                    $line = trim($line);
                                    // Ignorar líneas vacías y encabezados markdown
                                    if (empty($line) || preg_match('/^#+\s/', $line)) {
                                        continue;
                                    }
                                    // Remover marcadores de lista markdown (-, *, +) y numeración
                                    $line = preg_replace('/^[-*+]\s+/', '', $line);
                                    $line = preg_replace('/^\d+\.\s+/', '', $line);
                                    // Remover enlaces markdown [texto](url) -> texto
                                    $line = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $line);
                                    // Remover código markdown `código` -> código
                                    $line = preg_replace('/`([^`]+)`/', '$1', $line);
                                    // Remover negritas/cursivas markdown
                                    $line = preg_replace('/\*\*([^\*]+)\*\*/', '$1', $line);
                                    $line = preg_replace('/\*([^\*]+)\*/', '$1', $line);
                                    $line = preg_replace('/__([^_]+)__/', '$1', $line);
                                    $line = preg_replace('/_([^_]+)_/', '$1', $line);
                                    
                                    if (!empty($line)) {
                                        $changes[] = $line;
                                    }
                                }
                            }
                            
                            $response['update_info'] = [
                                'latest_version' => $latestVersion,
                                'release_date' => isset($release['published_at']) ? date('Y-m-d', strtotime($release['published_at'])) : null,
                                'changelog' => [
                                    $latestVersion => [
                                        'version' => $latestVersion,
                                        'release_date' => isset($release['published_at']) ? date('Y-m-d', strtotime($release['published_at'])) : null,
                                        'type' => VersionManager::isMajorUpdate($currentVersion, $latestVersion) ? 'major' : 'minor',
                                        'changes' => $changes,
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
                    // GitHub falló, intentar obtener información del release específico por tag
                    // Primero intentar obtener el release por tag específico si tenemos la versión del archivo local
                    $updateInfoFile = __DIR__ . '/../../updates/update-info.json';
                    $releaseFromGitHub = null;
                    
                    // Si tenemos archivo local, intentar obtener el release específico de GitHub usando el tag
                    if (file_exists($updateInfoFile)) {
                        $updateInfo = json_decode(file_get_contents($updateInfoFile), true);
                        
                        if ($updateInfo && isset($updateInfo['latest_version'])) {
                            $latestVersion = $updateInfo['latest_version'];
                            
                            // Intentar obtener el release específico de GitHub por tag
                            $tagName = 'v' . $latestVersion; // Intentar con prefijo 'v'
                            $releaseFromGitHub = self::checkGitHubReleaseByTag($owner, $repo, $tagName, $token);
                            
                            // Si no funciona con 'v', intentar sin prefijo
                            if (!$releaseFromGitHub) {
                                $releaseFromGitHub = self::checkGitHubReleaseByTag($owner, $repo, $latestVersion, $token);
                            }
                        }
                    }
                    
                    // Si encontramos el release en GitHub, usar esa información
                    if ($releaseFromGitHub) {
                        $latestVersion = preg_replace('/^v/', '', $releaseFromGitHub['tag_name']);
                        
                        if (preg_match('/^\d+\.\d+\.\d+/', $latestVersion)) {
                            $response['latest_version'] = $latestVersion;
                            $response['update_available'] = VersionManager::isUpdateAvailable($latestVersion);
                            
                            if ($response['update_available']) {
                                // Procesar el body del release para extraer los cambios
                                $changes = [];
                                if (isset($releaseFromGitHub['body']) && !empty($releaseFromGitHub['body'])) {
                                    $body = trim($releaseFromGitHub['body']);
                                    $lines = explode("\n", $body);
                                    foreach ($lines as $line) {
                                        $line = trim($line);
                                        if (empty($line) || preg_match('/^#+\s/', $line)) {
                                            continue;
                                        }
                                        $line = preg_replace('/^[-*+]\s+/', '', $line);
                                        $line = preg_replace('/^\d+\.\s+/', '', $line);
                                        $line = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $line);
                                        $line = preg_replace('/`([^`]+)`/', '$1', $line);
                                        $line = preg_replace('/\*\*([^\*]+)\*\*/', '$1', $line);
                                        $line = preg_replace('/\*([^\*]+)\*/', '$1', $line);
                                        $line = preg_replace('/__([^_]+)__/', '$1', $line);
                                        $line = preg_replace('/_([^_]+)_/', '$1', $line);
                                        
                                        if (!empty($line)) {
                                            $changes[] = $line;
                                        }
                                    }
                                }
                                
                                // Encontrar zipball URL
                                $zipUrl = null;
                                if (isset($releaseFromGitHub['zipball_url'])) {
                                    $zipUrl = $releaseFromGitHub['zipball_url'];
                                } else if (isset($releaseFromGitHub['assets'])) {
                                    foreach ($releaseFromGitHub['assets'] as $asset) {
                                        if (isset($asset['browser_download_url']) && 
                                            pathinfo($asset['browser_download_url'], PATHINFO_EXTENSION) === 'zip') {
                                            $zipUrl = $asset['browser_download_url'];
                                            break;
                                        }
                                    }
                                }
                                
                                if (!$zipUrl) {
                                    $zipUrl = "https://github.com/{$owner}/{$repo}/archive/refs/tags/{$releaseFromGitHub['tag_name']}.zip";
                                }
                                
                                $response['update_info'] = [
                                    'latest_version' => $latestVersion,
                                    'release_date' => isset($releaseFromGitHub['published_at']) ? date('Y-m-d', strtotime($releaseFromGitHub['published_at'])) : null,
                                    'changelog' => [
                                        $latestVersion => [
                                            'version' => $latestVersion,
                                            'release_date' => isset($releaseFromGitHub['published_at']) ? date('Y-m-d', strtotime($releaseFromGitHub['published_at'])) : null,
                                            'type' => VersionManager::isMajorUpdate($currentVersion, $latestVersion) ? 'major' : 'minor',
                                            'changes' => $changes,
                                            'breaking_changes' => VersionManager::isMajorUpdate($currentVersion, $latestVersion),
                                            'requires_migration' => false
                                        ]
                                    ],
                                    'download_url' => $zipUrl,
                                    'source' => 'github',
                                    'github_release' => $releaseFromGitHub
                                ];
                                $response['is_major_update'] = VersionManager::isMajorUpdate($currentVersion, $latestVersion);
                                $response['error'] = null;
                            }
                        }
                    }
                    
                    // Si no se encontró en GitHub, usar archivo local como fallback
                    if (!$releaseFromGitHub && file_exists($updateInfoFile)) {
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
                                $response['error'] = 'Using local update information. GitHub release not found. (' . self::getLastGitHubError() . ')';
                            }
                        } else {
                            $response['error'] = 'Unable to fetch GitHub release information and local update file is invalid';
                        }
                    } else if (!$releaseFromGitHub) {
                        $response['error'] = 'Unable to fetch GitHub release information and local update file not found';
                    }
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
     * Normalize path to use correct directory separators
     * 
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private static function normalizePath($path) {
        // Reemplazar todas las barras y backslashes con el separador del sistema
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        // Eliminar separadores duplicados
        $path = preg_replace('/[' . preg_quote(DIRECTORY_SEPARATOR, '/') . ']+/', DIRECTORY_SEPARATOR, $path);
        // Eliminar separador final
        return rtrim($path, DIRECTORY_SEPARATOR);
    }
    
    /**
     * Get Project Root Path
     */
    private static function getRootPath() {
        // Método manual infalible: 3 niveles arriba desde este archivo
        $rootPath = dirname(dirname(dirname(__FILE__)));
        // Normalizar la ruta
        return self::normalizePath($rootPath);
    }

    /**
     * Test if a directory is writable by actually writing a test file
     * 
     * @param string $dir Directory to test
     * @return bool True if writable, false otherwise
     */
    private static function testDirectoryWritable($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $testFile = $dir . DIRECTORY_SEPARATOR . '.write_test_' . time() . '_' . mt_rand(1000, 9999);
        $testContent = 'test_' . time();
        
        $result = @file_put_contents($testFile, $testContent);
        if ($result !== false) {
            // Verificar que el contenido se escribió correctamente
            $readContent = @file_get_contents($testFile);
            @unlink($testFile);
            return ($readContent === $testContent);
        }
        
        // Si falla la escritura, capturar el último error de PHP
        $lastError = error_get_last();
        if ($lastError && isset($lastError['message'])) {
            self::$lastPermissionsError = 'Failed to write test file to ' . $dir . ': ' . $lastError['message'];
        } else {
            self::$lastPermissionsError = 'Failed to write test file to ' . $dir . '. Unknown error.';
        }
        
        return false;
    }
    
    /**
     * Get a writable directory for backups
     * Tries backups directory first, then system temp directory as fallback
     * 
     * @return array Result with 'success', 'directory', and 'error' keys
     */
    private static function getWritableBackupDirectory() {
        $config = self::getConfig();
        $updateConfig = $config['updates'] ?? [];
        $customBackupPath = $updateConfig['backup_path'] ?? null;

        $directoriesToTry = [];
        $allErrors = [];
        
        // 1. Intentar con la ruta de backup personalizada si está configurada
        if (!empty($customBackupPath)) {
            $directoriesToTry[] = [
                'path' => $customBackupPath,
                'is_temp' => false,
                'create' => true,
                'name' => 'Ruta personalizada configurada'
            ];
        }

        // 2. Intentar el directorio backups/ del proyecto (PRIORIDAD ALTA - más confiable)
        $rootPath = self::getRootPath();
        $projectBackupDir = $rootPath . DIRECTORY_SEPARATOR . 'backups';
        $directoriesToTry[] = [
            'path' => $projectBackupDir,
            'is_temp' => false,
            'create' => true,
            'name' => 'Directorio backups/ del proyecto'
        ];

        // 3. Intentar el directorio temporal del sistema con subdirectorio específico
        $sysTempDir = sys_get_temp_dir();
        if ($sysTempDir && is_dir($sysTempDir)) {
            $directoriesToTry[] = [
                'path' => $sysTempDir . DIRECTORY_SEPARATOR . 'web-framework-backups',
                'is_temp' => true,
                'create' => true,
                'name' => 'Directorio temporal del sistema (con subdirectorio)',
                'warning' => 'Usando directorio temporal del sistema. El backup se guardará en: ' . $sysTempDir . DIRECTORY_SEPARATOR . 'web-framework-backups'
            ];
        }

        // 4. Directorio temporal del sistema directamente (sin subdirectorio) como último recurso
        if ($sysTempDir && is_dir($sysTempDir)) {
            $directoriesToTry[] = [
                'path' => $sysTempDir,
                'is_temp' => true,
                'create' => false,
                'name' => 'Directorio temporal del sistema (directo)',
                'warning' => 'Usando directorio temporal del sistema directamente: ' . $sysTempDir
            ];
        }

        foreach ($directoriesToTry as $dirInfo) {
            $dir = $dirInfo['path'];
            $dirName = $dirInfo['name'] ?? $dir;
            
            // Resetear el error antes de probar este directorio
            self::$lastPermissionsError = null;
            
            // Intentar crear el directorio si no existe y está configurado para crearse
            if ($dirInfo['create'] && !is_dir($dir)) {
                // Intentar crear con 0755 primero, luego 0777 si falla
                $created = false;
                if (@mkdir($dir, 0755, true)) {
                    $created = true;
                    @chmod($dir, 0777); // Intentar permisos más amplios después de crear
                } elseif (@mkdir($dir, 0777, true)) {
                    $created = true;
                }
                
                if (!$created) {
                    $error = "No se pudo crear el directorio: {$dir}";
                    $allErrors[] = "[{$dirName}] {$error}";
                    error_log("Backup directory creation failed: {$dir}");
                    continue;
                }
            }
            
            // Verificar que el directorio existe
            if (!is_dir($dir)) {
                $error = "El directorio no existe: {$dir}";
                $allErrors[] = "[{$dirName}] {$error}";
                continue;
            }
            
            // Intentar cambiar permisos si no es escribible
            if (!is_writable($dir)) {
                // Intentar cambiar permisos (puede fallar si no tenemos permisos)
                @chmod($dir, 0755);
                if (!is_writable($dir)) {
                    @chmod($dir, 0777);
                }
            }
            
            // Verificar si podemos escribir realmente (prueba real de escritura)
            if (self::testDirectoryWritable($dir)) {
                $result = [
                    'success' => true,
                    'directory' => $dir,
                    'is_temp' => $dirInfo['is_temp']
                ];
                
                if (isset($dirInfo['warning'])) {
                    $result['warning'] = $dirInfo['warning'];
                }
                
                return $result;
            } else {
                // Capturar el error específico de este directorio
                $error = self::$lastPermissionsError ?? "No se pudo escribir en el directorio";
                $allErrors[] = "[{$dirName}] {$error}";
            }
        }
        
        // Si llegamos aquí, ningún directorio funcionó
        // Construir mensaje de error con todos los intentos
        $finalError = 'No se pudo encontrar un directorio escribible para backups. ';
        $finalError .= 'Se intentaron los siguientes directorios:' . PHP_EOL;
        foreach ($allErrors as $error) {
            $finalError .= '  - ' . $error . PHP_EOL;
        }
        $finalError .= PHP_EOL . 'Solución: Asegúrese de que el directorio backups/ del proyecto (' . $projectBackupDir . ') tenga permisos de escritura (chmod 755 o 777) para el usuario del servidor web.';

        return [
            'success' => false,
            'error' => $finalError
        ];
    }

    /**
     * Create backup before update
     */
    private static function createBackup() {
        // Obtener un directorio escribible (intenta backups primero, luego temp del sistema)
        $writableDir = self::getWritableBackupDirectory();
        
        if (!$writableDir['success']) {
            return [
                'success' => false,
                'error' => $writableDir['error']
            ];
        }
        
        $backupDir = $writableDir['directory'];
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . 'backup_' . $timestamp;
        
        if (!is_dir($backupPath)) {
            if (!@mkdir($backupPath, 0755, true)) {
                if (!@mkdir($backupPath, 0777, true)) {
                    return [
                        'success' => false,
                        'error' => 'No se pudo crear el directorio de backup específico: ' . $backupPath
                    ];
                }
            }
            // Asegurar que sea 0777 después de la creación
            if (is_dir($backupPath)) {
                @chmod($backupPath, 0777);
            }
        }
        
        // Verificar escritura en el directorio de backup específico
        // Si no es escribible, intentar ajustar permisos (0755, luego 0777)
        if (!is_writable($backupPath)) {
            @chmod($backupPath, 0755); // Intentar 0755 (más seguro)
            if (!is_writable($backupPath)) {
                @chmod($backupPath, 0777); // Intentar 0777 si 0755 falla
            }
        }

        // Ahora, verificar si podemos escribir un archivo de prueba
        $testFile = $backupPath . DIRECTORY_SEPARATOR . '.write_test_' . time();
        if (@file_put_contents($testFile, 'test') === false) {
            $lastError = error_get_last();
            $errorMsg = 'El directorio de backup no tiene permisos de escritura: ' . $backupPath;
            if ($lastError && isset($lastError['message'])) {
                $errorMsg .= '. Error: ' . $lastError['message'];
            }
            return [
                'success' => false,
                'error' => $errorMsg
            ];
        }
        @unlink($testFile);
        
        // No usar realpath() aquí, ya que puede fallar en entornos restrictivos
        $dbBackupFile = $backupPath . DIRECTORY_SEPARATOR . 'database.sql';
        
        // Native PHP Database Backup (Agnostic to server environment)
        try {
            $link = self::connect();
            $tables = [];
            $result = $link->query('SHOW TABLES');
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $sql = "-- Framework Auto-Backup\n";
            $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // Table structure
                $res = $link->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $sql .= "\n\n-- Structure for table `$table` --\n\n";
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $res['Create Table'] . ";\n\n";
                
                // Table data
                $res = $link->query("SELECT * FROM `$table` ");
                $sql .= "-- Data for table `$table` --\n";
                while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                    $keys = array_keys($row);
                    $values = array_values($row);
                    $sql .= "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (";
                    $processedValues = [];
                    foreach ($values as $value) {
                        if (is_null($value)) $processedValues[] = "NULL";
                        else $processedValues[] = $link->quote($value);
                    }
                    $sql .= implode(", ", $processedValues) . ");\n";
                }
            }
            $sql .= "\nSET FOREIGN_KEY_CHECKS=1;";
            
            // Verificar permisos antes de escribir
            if (!is_writable($backupPath)) {
                @chmod($backupPath, 0777);
            }
            
            $bytesWritten = @file_put_contents($dbBackupFile, $sql);
            if ($bytesWritten === false) {
                $lastError = error_get_last();
                $errorMsg = 'No se pudo escribir el archivo de backup de la base de datos: ' . $dbBackupFile;
                if ($lastError && isset($lastError['message'])) {
                    $errorMsg .= '. Error: ' . $lastError['message'];
                }
                return [
                    'success' => false,
                    'error' => $errorMsg
                ];
            }
            
            // Asegurar permisos del archivo creado
            @chmod($dbBackupFile, 0666);
            $dbBackupSuccess = true;
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Native database backup failed: ' . $e->getMessage()
            ];
        }
        
        // Backup critical files
        $filesToBackup = [
            'api/config.php',
            'cms/config.php',
            'VERSION'
        ];
        
        $backupErrors = [];
        foreach ($filesToBackup as $file) {
            $sourcePath = __DIR__ . '/../../' . $file;
            if (file_exists($sourcePath)) {
                $destPath = $backupPath . '/' . str_replace('/', '_', $file);
                if (!@copy($sourcePath, $destPath)) {
                    $backupErrors[] = "No se pudo copiar: {$file}";
                } else {
                    // Asegurar permisos del archivo copiado
                    @chmod($destPath, 0666);
                }
            }
        }
        
        // Si hay errores críticos al hacer backup de archivos, reportarlos
        if (!empty($backupErrors) && !file_exists($dbBackupFile)) {
            return [
                'success' => false,
                'error' => 'Error al crear backup: ' . implode(', ', $backupErrors)
            ];
        }
        
        $result = [
            'success' => true,
            'backup_path' => $backupPath,
            'db_backup' => file_exists($dbBackupFile) ? $dbBackupFile : null
        ];
        
        // Incluir warning si se está usando directorio temporal
        if (isset($writableDir['warning'])) {
            $result['warning'] = $writableDir['warning'];
        }
        
        return $result;
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
        // Asegurarse de que el directorio destino existe y tiene permisos de escritura
        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir)) {
            // Intentar crear el directorio con 0755, luego 0777 si falla
            if (!@mkdir($destinationDir, 0755, true)) {
                if (!@mkdir($destinationDir, 0777, true)) {
                    return [
                        'success' => false,
                        'error' => 'No se pudo crear el directorio para la descarga: ' . $destinationDir . '. Verifique los permisos del directorio padre.'
                    ];
                }
            }
            // Asegurar que sea 0777 después de la creación
            if (is_dir($destinationDir)) {
                @chmod($destinationDir, 0777);
            }
        }
        
        // Verificar escritura intentando crear un archivo de prueba
        // Si no es escribible, intentar ajustar permisos (0755, luego 0777)
        if (!is_writable($destinationDir)) {
            @chmod($destinationDir, 0755); // Intentar 0755 (más seguro)
            if (!is_writable($destinationDir)) {
                @chmod($destinationDir, 0777); // Intentar 0777 si 0755 falla
            }
        }

        $testFile = $destinationDir . DIRECTORY_SEPARATOR . '.write_test_' . time();
        $canWrite = false;
        
        if (@file_put_contents($testFile, 'test') !== false) {
            @unlink($testFile);
            $canWrite = true;
        }
        
        if (!$canWrite) {
            $lastError = error_get_last();
            $errorMsg = 'El directorio de descarga no tiene permisos de escritura: ' . $destinationDir;
            if ($lastError && isset($lastError['message'])) {
                $errorMsg .= '. Error: ' . $lastError['message'];
            }
            $errorMsg .= '. Verifique los permisos del directorio o la configuración de temp_dir en php.ini.';
            return [
                'success' => false,
                'error' => $errorMsg
            ];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Descargar a una variable, no a archivo directo
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Framework-Update-Checker/1.0');
        
        $headers = [];
        if ($token) {
            $headers[] = "Authorization: token {$token}";
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $fileData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && $fileData) {
            // Usar file_put_contents (el método que sabemos que funciona en tu servidor)
            $bytesWritten = @file_put_contents($destination, $fileData);
            if ($bytesWritten !== false) {
                // Asegurar permisos del archivo creado
                @chmod($destination, 0666);
                return [
                    'success' => true,
                    'file_path' => $destination,
                    'file_size' => $bytesWritten
                ];
            } else {
                $lastError = error_get_last();
                $errorMsg = 'file_put_contents failed at: ' . $destination;
                if ($lastError && isset($lastError['message'])) {
                    $errorMsg .= '. Error: ' . $lastError['message'];
                }
                $errorMsg .= '. Verifique los permisos del directorio: ' . $destinationDir;
                return [
                    'success' => false,
                    'error' => $errorMsg
                ];
            }
        } else {
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
        
        // Normalizar rutas para evitar problemas con barras dobles
        $sourceDir = self::normalizePath($sourceDir);
        $targetDir = self::normalizePath($targetDir);
        
        // Find the actual project directory inside the extracted zip
        // GitHub zips usually have format: owner-repo-tag/
        $dirs = glob($sourceDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        if (count($dirs) > 0) {
            $sourceDir = self::normalizePath($dirs[0]);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $sourcePath);
            
            // Normalizar la ruta relativa
            $relativePath = self::normalizePath($relativePath);
            
            // Skip preserved files
            $shouldPreserve = false;
            foreach ($preservedFiles as $preserved) {
                $normalizedPreserved = self::normalizePath($preserved);
                if (strpos($relativePath, $normalizedPreserved) !== false) {
                    $shouldPreserve = true;
                    break;
                }
            }
            
            if ($shouldPreserve) {
                continue;
            }
            
            // Construir ruta destino normalizada
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    if (!@mkdir($targetPath, 0755, true)) {
                        if (!@mkdir($targetPath, 0777, true)) {
                            $errors[] = "Failed to create directory: {$relativePath}";
                            continue;
                        }
                    }
                    if (is_dir($targetPath)) {
                        @chmod($targetPath, 0777);
                    }
                } else {
                    // Si el directorio ya existe, asegurar que sea escribible
                    if (!is_writable($targetPath)) {
                        @chmod($targetPath, 0755);
                        if (!is_writable($targetPath)) {
                            @chmod($targetPath, 0777);
                        }
                    }
                }
            } else {
                $targetDirPath = dirname($targetPath);
                
                // Asegurar que el directorio padre existe y tiene permisos
                if (!is_dir($targetDirPath)) {
                    if (!@mkdir($targetDirPath, 0755, true)) {
                        if (!@mkdir($targetDirPath, 0777, true)) {
                            $errors[] = "Failed to create parent directory for file: {$relativePath}";
                            continue;
                        }
                    }
                    if (is_dir($targetDirPath)) {
                        @chmod($targetDirPath, 0777);
                    }
                } else {
                    // Si el directorio ya existe, asegurar que sea escribible
                    if (!is_writable($targetDirPath)) {
                        @chmod($targetDirPath, 0755);
                        if (!is_writable($targetDirPath)) {
                            @chmod($targetDirPath, 0777);
                        }
                    }
                }
                
                // Si el archivo destino ya existe, cambiar sus permisos primero
                if (file_exists($targetPath)) {
                    // Intentar hacer el archivo escribible
                    @chmod($targetPath, 0666);
                    // Si aún no es escribible, intentar 0777
                    if (!is_writable($targetPath)) {
                        @chmod($targetPath, 0777);
                    }
                }
                
                // Intentar copiar el archivo
                $copySuccess = false;
                $attempts = 0;
                $maxAttempts = 3;
                
                while (!$copySuccess && $attempts < $maxAttempts) {
                    if (@copy($sourcePath, $targetPath)) {
                        @chmod($targetPath, 0666);
                        $filesCopied[] = $relativePath;
                        $copySuccess = true;
                    } else {
                        $attempts++;
                        
                        // En cada intento, mejorar permisos
                        if (file_exists($targetPath)) {
                            @chmod($targetPath, 0666);
                            if (!is_writable($targetPath)) {
                                @chmod($targetPath, 0777);
                            }
                        }
                        @chmod($targetDirPath, 0777);
                        
                        // Si es el último intento, registrar el error
                        if ($attempts >= $maxAttempts) {
                            $lastError = error_get_last();
                            $errorMsg = "Failed to copy file: {$relativePath}";
                            if ($lastError && isset($lastError['message'])) {
                                $errorMsg .= '. Error: ' . $lastError['message'];
                            }
                            // Agregar información sobre permisos
                            $errorMsg .= '. Target path: ' . $targetPath;
                            if (file_exists($targetPath)) {
                                $perms = substr(sprintf('%o', fileperms($targetPath)), -4);
                                $errorMsg .= ' (exists, perms: ' . $perms . ')';
                            }
                            $dirPerms = substr(sprintf('%o', fileperms($targetDirPath)), -4);
                            $errorMsg .= '. Dir perms: ' . $dirPerms;
                            $errors[] = $errorMsg;
                        } else {
                            // Pequeña pausa antes del siguiente intento
                            usleep(100000); // 0.1 segundos
                        }
                    }
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
            // Usamos la misma carpeta del backup que ya sabemos que funciona 100%
            $tempDir = $backup['backup_path'];
            if (empty($tempDir)) {
                $tempDir = self::getRootPath() . DIRECTORY_SEPARATOR . 'backups';
            }
            $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'update_' . time() . '.zip';
            
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
            // Usar getRootPath() para obtener la ruta correcta del proyecto
            $projectRoot = self::getRootPath();
            $copyResult = self::copyUpdateFiles($extractDir, $projectRoot);
            
            // Clean up temporary files (solo el directorio extracted y el ZIP, no el backup completo)
            if (is_dir($extractDir)) {
                self::deleteDirectory($extractDir);
            }
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            
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
