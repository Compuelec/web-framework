<?php

/**
 * Payku Plugin Administration Controller
 * Handles plugin configuration and management in the CMS
 */

require_once "controllers/curl.controller.php";
require_once "controllers/template.controller.php";

// Load plugin controller with proper route handling
$pluginControllerPath = __DIR__ . "/../../plugins/payku/controllers/payku.controller.php";
if (file_exists($pluginControllerPath)) {
    require_once $pluginControllerPath;
} else {
    // Try alternative path
    $projectRoot = dirname(__DIR__, 2);
    $pluginControllerPath = $projectRoot . '/plugins/payku/controllers/payku.controller.php';
    if (file_exists($pluginControllerPath)) {
        require_once $pluginControllerPath;
    }
}

class PaykuController {
    
    /**
     * Manage Payku plugin configuration
     */
    public function managePayku() {
        if (!isset($_SESSION["admin"]) || $_SESSION["admin"]->rol_admin != "superadmin") {
            return;
        }
        
        if (isset($_POST["save_payku_config"])) {
            $this->saveConfig();
        }
    }
    
    /**
     * Save plugin configuration
     */
    private function saveConfig() {
        echo '<script>
            fncMatPreloader("on");
            fncSweetAlert("loading", "Guardando configuración...", "");
        </script>';
        
        // Prepare configuration
        $config = [
            'enabled' => isset($_POST["enabled"]) ? true : false,
            'platform_id' => $_POST["platform_id"] ?? 'TEST',
            'pagoDirecto' => $_POST["pagoDirecto"] ?? '1',
            'token_publico' => $_POST["token_publico"] ?? '',
            'marketplace' => $_POST["marketplace"] ?? '',
            'incremento' => $_POST["incremento"] ?? '0',
            'estadoPago' => $_POST["estadoPago"] ?? 'completed',
            'debug_enabled' => isset($_POST["debug_enabled"]) ? true : false
        ];
        
        // Save to configuration file
        // DIR points to cms/, so we need to go up one level
        $projectRoot = dirname(DIR);
        $configPath = $projectRoot . '/plugins/payku/config.php';
        $configDir = dirname($configPath);
        
        // Ensure directory exists with proper permissions
        if (!is_dir($configDir)) {
            // Create directory with full permissions
            if (!@mkdir($configDir, 0777, true)) {
                $errorMsg = "No se pudo crear el directorio: " . $configDir;
                error_log("Payku config error: " . $errorMsg);
                echo '<script>
                    fncMatPreloader("off");
                    fncSweetAlert("error", "Error: ' . addslashes($errorMsg) . '", "");
                </script>';
                return;
            }
            // Set permissions after creation
            @chmod($configDir, 0777);
        }
        
        // Always ensure directory is writable (fix permissions if necessary)
        if (!is_writable($configDir)) {
            // Try to make it writable
            @chmod($configDir, 0777);
            
            // Also ensure parent directories are writable
            $parentDir = dirname($configDir);
            if (is_dir($parentDir) && !is_writable($parentDir)) {
                @chmod($parentDir, 0777);
            }
            
            // Verify again after fixing permissions
            if (!is_writable($configDir)) {
                $errorMsg = "El directorio no es escribible: " . $configDir . ". Por favor, contacte al administrador del sistema.";
                error_log("Payku config error: " . $errorMsg);
                echo '<script>
                    fncMatPreloader("off");
                    fncSweetAlert("error", "Error: ' . addslashes($errorMsg) . '", "");
                </script>';
                return;
            }
        }
        
        // Write configuration file
        $configContent = "<?php\n";
        $configContent .= "/**\n";
        $configContent .= " * Payku Plugin Configuration\n";
        $configContent .= " * \n";
        $configContent .= " * This file contains sensitive configuration data including API tokens.\n";
        $configContent .= " * DO NOT commit this file to version control.\n";
        $configContent .= " * \n";
        $configContent .= " * Security: Prevents direct HTTP access while allowing PHP includes\n";
        $configContent .= " */\n\n";
        $configContent .= "// Prevent direct HTTP access\n";
        $configContent .= "// Allow access only when included/required from PHP code\n";
        $configContent .= "if (php_sapi_name() !== 'cli') {\n";
        $configContent .= "    // Check if it's a direct HTTP request (not included)\n";
        $configContent .= "    \$isDirectAccess = (\n";
        $configContent .= "        // Direct access from browser\n";
        $configContent .= "        (isset(\$_SERVER['REQUEST_METHOD']) && \n";
        $configContent .= "         basename(\$_SERVER['PHP_SELF']) === 'config.php') ||\n";
        $configContent .= "        // Access via URL\n";
        $configContent .= "        (isset(\$_SERVER['HTTP_HOST']) && \n";
        $configContent .= "         isset(\$_SERVER['REQUEST_URI']) &&\n";
        $configContent .= "         strpos(\$_SERVER['REQUEST_URI'], 'config.php') !== false)\n";
        $configContent .= "    );\n";
        $configContent .= "    \n";
        $configContent .= "    if (\$isDirectAccess) {\n";
        $configContent .= "        http_response_code(403);\n";
        $configContent .= "        header('Content-Type: text/plain');\n";
        $configContent .= "        die('403 Prohibido: El acceso directo a este archivo no está permitido.');\n";
        $configContent .= "    }\n";
        $configContent .= "}\n\n";
        $configContent .= "return " . var_export($config, true) . ";\n";
        
        $writeResult = @file_put_contents($configPath, $configContent);
        
        if ($writeResult !== false) {
            // Ensure file has correct permissions
            // Set permissions so both owner and group can write
            @chmod($configPath, 0664);
            // Try to set ownership to current user if possible
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                // If running as root, set ownership to web server user
                $webUser = getenv('APACHE_RUN_USER') ?: 'daemon';
                @chown($configPath, $webUser);
            }
            
            // Redirect to avoid POST resubmission loop
            // Use JavaScript to show success message and redirect
            echo '<script>
                fncMatPreloader("off");
                Swal.fire({
                    icon: "success",
                    title: "Correcto",
                    text: "Configuración guardada exitosamente",
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    // Redirect to same page without POST parameters
                    window.location.href = window.location.pathname;
                });
            </script>';
            return; // Stop execution to prevent form resubmission
        } else {
            $lastError = error_get_last();
            $errorMsg = "No se pudo escribir el archivo: " . $configPath;
            if ($lastError) {
                $errorMsg .= " - " . $lastError['message'];
            }
            error_log("Payku config error: " . $errorMsg);
            echo '<script>
                fncMatPreloader("off");
                fncSweetAlert("error", "Error al guardar la configuración. ' . addslashes($errorMsg) . '", "");
            </script>';
        }
    }
    
    /**
     * Get current configuration
     */
    public static function getConfig() {
        // DIR points to cms/, so we need to go up one level
        $projectRoot = dirname(DIR);
        $configPath = $projectRoot . '/plugins/payku/config.php';
        
        if (file_exists($configPath)) {
            return require $configPath;
        }
        
        // Return default configuration
        return [
            'enabled' => false,
            'platform_id' => 'TEST',
            'pagoDirecto' => '1',
            'token_publico' => '',
            'marketplace' => '',
            'incremento' => '0',
            'estadoPago' => 'completed',
            'debug_enabled' => false
        ];
    }
}

