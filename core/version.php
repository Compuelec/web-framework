<?php

/**
 * Version Management Class
 * 
 * Handles framework version information and comparison
 */

class VersionManager {
    
    /**
     * Get current framework version
     * 
     * @return string Current version (e.g., "1.0.0")
     */
    public static function getCurrentVersion() {
        $versionFile = __DIR__ . '/../VERSION';
        
        if (file_exists($versionFile)) {
            $version = trim(file_get_contents($versionFile));
            return $version;
        }
        
        // Fallback version if VERSION file doesn't exist
        return '1.0.0';
    }
    
    /**
     * Compare two version strings
     * 
     * @param string $version1 First version
     * @param string $version2 Second version
     * @return int Returns -1 if version1 < version2, 0 if equal, 1 if version1 > version2
     */
    public static function compareVersions($version1, $version2) {
        return version_compare($version1, $version2);
    }
    
    /**
     * Check if an update is available
     * 
     * @param string $latestVersion Latest available version
     * @return bool True if update is available
     */
    public static function isUpdateAvailable($latestVersion) {
        $currentVersion = self::getCurrentVersion();
        return self::compareVersions($currentVersion, $latestVersion) < 0;
    }
    
    /**
     * Get version parts (major, minor, patch)
     * 
     * @param string $version Version string
     * @return array Array with 'major', 'minor', 'patch' keys
     */
    public static function getVersionParts($version) {
        $parts = explode('.', $version);
        return [
            'major' => isset($parts[0]) ? (int)$parts[0] : 0,
            'minor' => isset($parts[1]) ? (int)$parts[1] : 0,
            'patch' => isset($parts[2]) ? (int)$parts[2] : 0
        ];
    }
    
    /**
     * Check if update is a major version (breaking changes)
     * 
     * @param string $currentVersion Current version
     * @param string $latestVersion Latest version
     * @return bool True if it's a major version update
     */
    public static function isMajorUpdate($currentVersion, $latestVersion) {
        $current = self::getVersionParts($currentVersion);
        $latest = self::getVersionParts($latestVersion);
        
        return $latest['major'] > $current['major'];
    }
    
    /**
     * Update version file
     * 
     * @param string $newVersion New version to set
     * @return bool True on success
     */
    public static function updateVersion($newVersion) {
        $versionFile = __DIR__ . '/../VERSION';
        
        // Validate version format (semantic versioning)
        if (!preg_match('/^\d+\.\d+\.\d+$/', $newVersion)) {
            return false;
        }
        
        return file_put_contents($versionFile, $newVersion . "\n") !== false;
    }
}
