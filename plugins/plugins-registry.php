<?php

/**
 * Plugins Registry
 * Registers available plugins and their information
 */

class PluginsRegistry {
    
    private static $plugins = [];
    
    /**
     * Register a plugin
     */
    public static function register($pluginName, $config) {
        self::$plugins[$pluginName] = $config;
    }
    
    /**
     * Get plugin by URL
     */
    public static function getPluginByUrl($url) {
        foreach (self::$plugins as $pluginName => $config) {
            if (isset($config['url']) && $config['url'] === $url) {
                return array_merge(['name' => $pluginName], $config);
            }
        }
        return null;
    }
    
    /**
     * Check if URL is a plugin
     */
    public static function isPluginUrl($url) {
        return self::getPluginByUrl($url) !== null;
    }
    
    /**
     * Get all registered plugins
     */
    public static function getAllPlugins() {
        return self::$plugins;
    }
    
    /**
     * Check if plugin page already exists
     */
    public static function pluginPageExists($pluginUrl) {
        // Calculate project root directory
        // If DIR is defined and points to cms/, go up one level
        // Otherwise, calculate from current file location
        if (defined('DIR')) {
            // If DIR points to cms/, go up one level to get project root
            if (basename(DIR) === 'cms') {
                $projectRoot = dirname(DIR);
            } else {
                $projectRoot = DIR;
            }
        } else {
            // Calculate project root from plugin location
            $pluginDir = __DIR__;
            $projectRoot = dirname($pluginDir);
        }
        
        $connectionPath = $projectRoot . '/api/models/connection.php';
        
        if (!file_exists($connectionPath)) {
            error_log("PluginsRegistry: Connection file not found at: " . $connectionPath);
            return false;
        }
        
        require_once $connectionPath;
        
        $link = Connection::connect();
        if (!$link) {
            return false;
        }
        
        try {
            $sql = "SELECT id_page FROM pages WHERE url_page = :url_page AND type_page = 'custom' LIMIT 1";
            $stmt = $link->prepare($sql);
            $stmt->execute([':url_page' => $pluginUrl]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("PluginsRegistry error: " . $e->getMessage());
            return false;
        }
    }
}

// Register Payku plugin
PluginsRegistry::register('payku', [
    'url' => 'payku',
    'name' => 'Payku - Sistema de Pagos',
    'description' => 'Plugin de integración con Payku para procesar pagos online (Visa, Mastercard, Magna, American Express, Diners y Redcompra)',
    'icon' => 'bi-credit-card',
    'type' => 'payment',
    'version' => '1.0.0',
    'author' => 'Payku Integration'
]);

