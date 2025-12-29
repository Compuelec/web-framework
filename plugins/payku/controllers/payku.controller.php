<?php

/**
 * Controlador de Pagos Payku
 * Maneja el procesamiento de pagos y callbacks
 */

// Get project root directory
// If DIR is already defined (from CMS), we need to go up one level
// Otherwise, calculate from plugin location
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
    $projectRoot = dirname(dirname($pluginDir));
    define('DIR', $projectRoot);
}

// Ensure we have the correct project root
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', $projectRoot);
}

require_once PROJECT_ROOT . '/api/models/connection.php';

// Load Payku library if not already loaded
if (!class_exists('Paykulib')) {
    require_once PROJECT_ROOT . '/plugins/payku/lib/paykulib.php';
}

class PaykuPlugin {
    
    private static $config = null;
    
    /**
     * Inicializar plugin
     */
    public static function init() {
        self::loadConfig();
        self::registerRoutes();
    }
    
    /**
     * Load plugin configuration
     */
    private static function loadConfig() {
        $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : (defined('DIR') && basename(DIR) === 'cms' ? dirname(DIR) : DIR);
        $configPath = $projectRoot . '/plugins/payku/config.php';
        if (file_exists($configPath)) {
            self::$config = require $configPath;
        } else {
            // Use default configuration
            self::$config = [
                'enabled' => false,
                'platform_id' => 'TEST',
                'pagoDirecto' => '1',
                'token_publico' => '',
                'marketplace' => '',
                'incremento' => '0',
                'estadoPago' => 'completed'
            ];
        }
    }
    
    /**
     * Get plugin configuration
     */
    public static function getConfig($key = null) {
        // Load configuration if not already loaded
        if (self::$config === null) {
            self::loadConfig();
        }
        
        if ($key === null) {
            return self::$config;
        }
        return self::$config[$key] ?? null;
    }
    
    /**
     * Register plugin routes
     */
    private static function registerRoutes() {
        // Routes are handled via webhook and result files
    }
    
    /**
     * Process payment
     * Creates a payment transaction and redirects to Payku
     */
    public static function processPayment($orderData) {
        // Validate configuration
        $config = self::getConfig();
        if (!$config['enabled'] || empty(trim($config['token_publico'] ?? ''))) {
            return [
                'success' => false,
                'error' => 'Payku plugin is not configured or enabled'
            ];
        }
        
        // Validate currency
        $currency = $orderData['currency'] ?? 'CLP';
        if ($currency !== 'CLP') {
            return [
                'success' => false,
                'error' => 'Payku solo acepta moneda CLP'
            ];
        }
        
        // Initialize Payku library
        $payku = new Paykulib();
        $payku->setOrderId($orderData['order_id']);
        $payku->setEmail($orderData['email']);
        $payku->setCurrency($currency);
        $payku->setOrderTotal($orderData['amount']);
        $payku->setProducts($orderData['products'] ?? []);
        $payku->setUrl(self::getConfig('platform_id'));
        $payku->setIncrement(self::getConfig('incremento'));
        
        // Ensure token is trimmed and validated
        $token = trim(self::getConfig('token_publico') ?? '');
        
        // Validate token format (should start with 'tkpu' for public token)
        if (empty($token)) {
            return [
                'success' => false,
                'error' => 'El token público no está configurado. Ve a /payku para configurarlo.'
            ];
        }
        
        if (!preg_match('/^tkpu[a-z0-9]{32}$/i', $token)) {
            error_log('Payku: El formato del token puede ser inválido. Token: ' . substr($token, 0, 10) . '...');
        }
        
        $payku->setPrivateToken($token);
        
        // Prepare payment data
        $data_action = $payku->getEndpoint();
        $order_id = $payku->getOrderId();
        $token = $payku->getPrivateToken();
        $email = $payku->getEmail();
        $currency = $payku->getCurrencycode();
        $amount = $payku->getOrderTotalTax();
        $detail = $payku->getproducts();
        
        if (empty($detail)) {
            $detail = "Orden N. " . $order_id;
        }
        
        $return_url = $payku->getUrlreturn($order_id);
        $notify_url = $payku->getUrlConfirmar();
        $pagoDirecto = self::getConfig('pagoDirecto');
        
        $data = [
            'email' => $email,
            'order' => $order_id,
            'subject' => strlen($detail) < 500 ? $detail : substr($detail, 0, 490) . '...',
            'amount' => $amount,
            'payment' => $pagoDirecto,
            'urlreturn' => $return_url,
            'urlnotify' => $notify_url
        ];
        
        // Add marketplace token if configured
        // IMPORTANT: Only add marketplace if token is actually configured and not empty
        $marketplace = trim(self::getConfig('marketplace') ?? '');
        if (!empty($marketplace) && strlen($marketplace) > 5) {
            $data['marketplace'] = $marketplace;
        } else {
            // Remove marketplace field if not configured to avoid API errors
            unset($data['marketplace']);
        }
        
        // Create transaction in Payku
        try {
            $resultado = $payku->apiConnect($data, $token, $data_action);
            
            if (isset($resultado['url']) && !empty($resultado['url'])) {
                // Save order to database
                self::saveOrder($orderData, $resultado);
                
                return [
                    'success' => true,
                    'order_id' => $order_id,
                    'redirect_url' => $resultado['url']
                ];
            } else {
                $errorMsg = 'Error al crear la transacción de pago.';
                
                // Check specific error messages from Payku API
                if (isset($resultado['message'])) {
                    $errorMsg = $resultado['message'];
                } elseif (isset($resultado['error'])) {
                    $errorMsg = is_string($resultado['error']) ? $resultado['error'] : print_r($resultado['error'], true);
                } elseif (isset($resultado['errors']) && is_array($resultado['errors'])) {
                    $errorMsg = implode(', ', $resultado['errors']);
                } elseif (isset($resultado['raw_response'])) {
                    $errorMsg = 'Respuesta inválida de la API: ' . substr($resultado['raw_response'], 0, 200);
                }
                
                // Log complete response for debugging
                error_log("Payku API error response: " . print_r($resultado, true));
                
                // Always include debug information to help diagnose the problem
                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'debug' => [
                        'api_response' => $resultado,
                        'http_code' => $resultado['http_code'] ?? 'N/A',
                        'request_data' => $data,
                        'api_url' => $data_action
                    ]
                ];
            }
        } catch (Exception $e) {
            error_log("Payku exception: " . $e->getMessage());
            error_log("Payku exception trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => 'Error al procesar el pago: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Save order to database
     */
    private static function saveOrder($orderData, $paykuResponse) {
        // Ensure Connection class is available
        if (!class_exists('Connection')) {
            $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : (defined('DIR') && basename(DIR) === 'cms' ? dirname(DIR) : DIR);
            require_once $projectRoot . '/api/models/connection.php';
        }
        
        $link = Connection::connect();
        if (!$link) {
            return false;
        }
        
        try {
            // Check if orders table exists, create it if it doesn't
            $sqlCheck = "SHOW TABLES LIKE 'payku_orders'";
            $stmtCheck = $link->query($sqlCheck);
            
            if ($stmtCheck->rowCount() == 0) {
                // Crear tabla
                $sqlCreate = "CREATE TABLE payku_orders (
                    id_order INT NOT NULL AUTO_INCREMENT,
                    order_id VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(10) NOT NULL DEFAULT 'CLP',
                    status VARCHAR(50) NOT NULL DEFAULT 'pending',
                    transaction_id VARCHAR(255) NULL,
                    payment_key VARCHAR(255) NULL,
                    transaction_key VARCHAR(255) NULL,
                    verification_key VARCHAR(255) NULL,
                    payku_response TEXT NULL,
                    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    date_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_order),
                    UNIQUE KEY unique_order_id (order_id),
                    INDEX idx_status (status),
                    INDEX idx_email (email),
                    INDEX idx_date_created (date_created)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $link->exec($sqlCreate);
            } else {
                // Check if UNIQUE constraint exists, add it if it doesn't
                try {
                    $sqlCheckUnique = "SELECT COUNT(*) as count FROM information_schema.table_constraints 
                                       WHERE table_schema = DATABASE() 
                                       AND table_name = 'payku_orders' 
                                       AND constraint_name = 'unique_order_id'";
                    $stmtUnique = $link->query($sqlCheckUnique);
                    $hasUnique = $stmtUnique->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                    
                    if (!$hasUnique) {
                        // Add UNIQUE constraint if it doesn't exist
                        $sqlAddUnique = "ALTER TABLE payku_orders ADD UNIQUE KEY unique_order_id (order_id)";
                        $link->exec($sqlAddUnique);
                        error_log("Payku: Se agregó restricción UNIQUE a order_id");
                    }
                } catch (PDOException $e) {
                    // Ignore if constraint already exists or other errors
                    error_log("Payku: No se pudo agregar restricción UNIQUE (puede que ya exista): " . $e->getMessage());
                }
            }
            
            // Insertar o actualizar orden
            $sql = "INSERT INTO payku_orders 
                    (order_id, email, amount, currency, status, payment_key, payku_response) 
                    VALUES (:order_id, :email, :amount, :currency, :status, :payment_key, :payku_response)
                    ON DUPLICATE KEY UPDATE 
                    payment_key = VALUES(payment_key),
                    payku_response = VALUES(payku_response),
                    date_updated = CURRENT_TIMESTAMP";
            
            $stmt = $link->prepare($sql);
            $stmt->execute([
                ':order_id' => $orderData['order_id'],
                ':email' => $orderData['email'],
                ':amount' => $orderData['amount'],
                ':currency' => $orderData['currency'] ?? 'CLP',
                ':status' => 'pending',
                ':payment_key' => $paykuResponse['id'] ?? null,
                ':payku_response' => json_encode($paykuResponse)
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Payku saveOrder error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update order status
     */
    public static function updateOrderStatus($order_id, $status, $transactionData = []) {
        // Ensure Connection class is available
        if (!class_exists('Connection')) {
            $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : (defined('DIR') && basename(DIR) === 'cms' ? dirname(DIR) : DIR);
            require_once $projectRoot . '/api/models/connection.php';
        }
        
        $link = Connection::connect();
        if (!$link) {
            return false;
        }
        
        try {
            $sql = "UPDATE payku_orders 
                    SET status = :status,
                        transaction_id = :transaction_id,
                        payment_key = :payment_key,
                        transaction_key = :transaction_key,
                        verification_key = :verification_key,
                        payku_response = :payku_response,
                        date_updated = CURRENT_TIMESTAMP
                    WHERE order_id = :order_id";
            
            $stmt = $link->prepare($sql);
            $stmt->execute([
                ':order_id' => $order_id,
                ':status' => $status,
                ':transaction_id' => $transactionData['transaction_id'] ?? null,
                ':payment_key' => $transactionData['payment_key'] ?? null,
                ':transaction_key' => $transactionData['transaction_key'] ?? null,
                ':verification_key' => $transactionData['verification_key'] ?? null,
                ':payku_response' => isset($transactionData['payku_response']) ? $transactionData['payku_response'] : null
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Payku updateOrderStatus error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get order by order_id
     */
    public static function getOrder($order_id) {
        // Ensure Connection class is available
        if (!class_exists('Connection')) {
            $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : (defined('DIR') && basename(DIR) === 'cms' ? dirname(DIR) : DIR);
            require_once $projectRoot . '/api/models/connection.php';
        }
        
        $link = Connection::connect();
        if (!$link) {
            return null;
        }
        
        try {
            $sql = "SELECT * FROM payku_orders WHERE order_id = :order_id LIMIT 1";
            $stmt = $link->prepare($sql);
            $stmt->execute([':order_id' => $order_id]);
            
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Payku getOrder error: " . $e->getMessage());
            return null;
        }
    }
}

