<?php

/**
 * Plugin Loader System
 * Loads and manages plugins similar to WordPress
 */

if (!defined('DIR')) {
    define('DIR', dirname(__DIR__));
}

class PluginsLoader {
    
    private static $loadedPlugins = [];
    private static $pluginsDir = __DIR__;
    
    /**
     * Initialize plugin system
     */
    public static function init() {
        self::loadPlugins();
    }
    
    /**
     * Load all active plugins
     */
    private static function loadPlugins() {
        $pluginsDir = self::$pluginsDir;
        
        if (!is_dir($pluginsDir)) {
            return;
        }
        
        // Scan plugins directory
        $plugins = array_filter(glob($pluginsDir . '/*'), 'is_dir');
        
        foreach ($plugins as $pluginPath) {
            $pluginName = basename($pluginPath);
            $mainFile = $pluginPath . '/' . $pluginName . '.php';
            
            // Check if plugin main file exists
            if (file_exists($mainFile)) {
                // Check if plugin is active (we'll store this in config or database)
                if (self::isPluginActive($pluginName)) {
                    require_once $mainFile;
                    self::$loadedPlugins[$pluginName] = $pluginPath;
                }
            }
        }
    }
    
    /**
     * Check if plugin is active
     * For now, we'll check if plugin directory exists
     * Later this can be stored in database or config
     */
    private static function isPluginActive($pluginName) {
        // TODO: Check database or config for plugin status
        // For now, all plugins in directory are considered active
        return true;
    }
    
    /**
     * Get loaded plugins
     */
    public static function getLoadedPlugins() {
        return self::$loadedPlugins;
    }
    
    /**
     * Check if plugin is loaded
     */
    public static function isPluginLoaded($pluginName) {
        return isset(self::$loadedPlugins[$pluginName]);
    }
    
    /**
     * Get plugin path
     */
    public static function getPluginPath($pluginName) {
        return self::$loadedPlugins[$pluginName] ?? null;
    }
    
    /**
     * Get plugin URL (for assets)
     */
    public static function getPluginUrl($pluginName) {
        $pluginPath = self::getPluginPath($pluginName);
        if ($pluginPath) {
            // Calculate relative URL from document root
            $docRoot = $_SERVER['DOCUMENT_ROOT'];
            $relativePath = str_replace($docRoot, '', $pluginPath);
            return rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . $relativePath;
        }
        return null;
    }
}

// Auto-initialize if not called manually
if (!defined('PLUGINS_LOADED')) {
    PluginsLoader::init();
    define('PLUGINS_LOADED', true);
}

