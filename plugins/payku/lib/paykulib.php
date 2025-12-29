<?php

/**
 * Payku Library
 * Adapted from WordPress plugin to work independently
 */

class Paykulib {
    
    private $url;
    private $increment;
    private $products_cart;
    private $email;
    private $currency;
    private $privateToken;
    private $urlReturn;
    private $urlConfirmar;
    private $orderId;
    private $orderTotal;
    
    public function __construct() {
        $this->url = '';
        $this->increment = '';
        $this->products_cart = '';
        $this->email = '';
        $this->currency = '';
        $this->privateToken = '';
        $this->urlReturn = '';
        $this->urlConfirmar = '';
        $this->orderId = '';
        $this->orderTotal = '';
    }
    
    private function singleText($input) {
        return preg_replace("/[^\sa-záéíóúA-ZÁÉÍÓÚñÑ0-9]+/", "", $input);
    }
    
    private function limpiar_soloNumerico($input) {
        return preg_replace("/[^0-9]+/", "", $input);
    }
    
    private function getEndpointweb() {
        return 'https://app.payku.cl/';
    }
    
    private function getEndpointwebDev() {
        return 'https://des.payku.cl/';
    }
    
    public function setOrderId($orderid) {
        // Remove any non-alphanumeric characters except hyphens and underscores
        // Keep order ID as string to preserve format like "TEST-20251229213456"
        $this->orderId = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$orderid);
    }
    
    public function setUrl($url) {
        // Set correct endpoint based on platform
        // PROD = production, anything else = development
        if (!is_null($url) && (strcmp($url, 'PROD') === 0)) {
            $this->url = self::getEndpointweb(); // https://app.payku.cl/
        } else {
            $this->url = self::getEndpointwebDev(); // https://des.payku.cl/
        }
    }
    
    public function setUrlReturn($url = '') {
        $this->urlReturn = (filter_var($url, FILTER_VALIDATE_URL)) ? $url : (filter_var($url, FILTER_SANITIZE_URL));
    }
    
    public function setUrlConfirmar($url) {
        $this->urlConfirmar = (filter_var($url, FILTER_VALIDATE_URL)) ? $url : (filter_var($url, FILTER_SANITIZE_URL));
    }
    
    public function setEmail($email) {
        $this->email = (filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : (filter_var($email, FILTER_SANITIZE_EMAIL));
    }
    
    public function setIncrement($increment) {
        $this->increment = (filter_var($increment, FILTER_VALIDATE_FLOAT)) ? $increment : (filter_var($increment, FILTER_SANITIZE_NUMBER_FLOAT));
    }
    
    public function setCurrency($currency) {
        $this->currency = (!is_null($currency) && (strcmp($currency, 'CLP') === 0)) ? $currency : '';
    }
    
    public function setProducts($productos) {
        $this->products_cart = (!empty($productos)) ? $productos : [];
    }
    
    public function setPrivateToken($privateToken) {
        // Token should not be sanitized with singleText as it may contain special characters
        // Only trim and validate that it's a non-empty string
        $this->privateToken = (!empty($privateToken) && is_string($privateToken)) ? trim($privateToken) : '';
    }
    
    public function setOrderTotal($orderTotal) {
        $this->orderTotal = (!empty($orderTotal) && is_numeric($orderTotal)) ? $orderTotal : (filter_var($orderTotal, FILTER_SANITIZE_NUMBER_INT));
    }
    
    public function substringAll($text, $count = 200) {
        return (strlen($text) > $count) ? substr($text, 0, $count) : $text;
    }
    
    public function getproducts() {
        $i = count($this->products_cart);
        $detail = '';
        
        // Handle different product formats
        if (is_array($this->products_cart)) {
            foreach ($this->products_cart as $product) {
                if (is_array($product) || is_object($product)) {
                    $quantity = is_array($product) ? ($product['quantity'] ?? $product['qty'] ?? 1) : ($product->quantity ?? $product->qty ?? 1);
                    $name = is_array($product) ? ($product['name'] ?? $product['title'] ?? '') : ($product->name ?? $product->title ?? '');
                    
                    $detail .= $quantity . ' x ' . $name;
                    $last_iteration = !(--$i);
                    if (!$last_iteration) {
                        $detail .= ' - ';
                    }
                }
            }
        }
        
        return $this->singleText($this->substringAll($detail, 490));
    }
    
    public function getOrderId() {
        return $this->orderId;
    }
    
    public function getEndpoint() {
        return $this->url . 'api/transaction';
    }
    
    public function getUrlreturn($order_id = 0) {
        // Get base URL dynamically
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        
        // Get base path from REQUEST_URI or SCRIPT_NAME
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Extract base path - search for /web-framework/ or /cms/ or /api/
        $basePath = '';
        
        // Use fixed base path for web-framework
        // This ensures URLs are always correct regardless of where script is called from
        $basePath = '/web-framework';
        
        return $protocol . $host . $basePath . '/plugins/payku/result-payku.php?order_id=' . urlencode($order_id);
    }
    
    public function getUrlConfirmar() {
        // Get base URL dynamically
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        
        // Use fixed base path for web-framework
        // This ensures URLs are always correct regardless of where script is called from
        $basePath = '/web-framework';
        
        return $protocol . $host . $basePath . '/plugins/payku/webhook-payku.php';
    }
    
    public function getIncremOrder() {
        return (is_numeric($this->increment) && ($this->increment > 0) && ($this->increment <= 100)) ? $this->increment : 0;
    }
    
    public function getSumIncremInteg() {
        return (self::getIncremOrder() > 0) ? round(($this->orderTotal * $this->increment) / 100.0) : 0;
    }
    
    public function getPrivateToken() {
        return $this->privateToken;
    }
    
    public function getOrderTotalTax() {
        return $this->orderTotal + self::getSumIncremInteg();
    }
    
    public function getOrderTotal() {
        return $this->orderTotal;
    }
    
    public function getCurrencycode() {
        return $this->currency;
    }
    
    public function getEmail() {
        return $this->email;
    }
    
    /**
     * Get transaction data from Payku API
     */
    public function datosGet($payment_key, $token, $data_action) {
        $headers = array();
        $headers[] = 'Cache-Control: no-cache';
        $headers[] = 'Authorization: Bearer ' . trim($token);
        $headers[] = 'Content-Type: application/json; charset=utf-8';
        
        $url = $data_action . '/' . $payment_key;
        
        // Log request for debugging
        error_log('Payku datosGet - URL: ' . $url);
        error_log('Payku datosGet - Payment Key: ' . $payment_key);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            error_log('Payku datosGet cURL Error: ' . $curlError);
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        // Log response
        error_log('Payku datosGet - HTTP Code: ' . $httpCode);
        error_log('Payku datosGet - Response: ' . $result);
        
        $decoded = json_decode($result);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Payku datosGet JSON Error: ' . json_last_error_msg());
            return null;
        }
        
        return $decoded;
    }
    
    /**
     * Connect to Payku API to create transaction
     */
    public function apiConnect($data, $token, $url) {
        $headers = [];
        $headers[] = 'Accept: application/json,text/plain,*/*';
        
        // Clean token - remove whitespace and ensure it's a valid string
        $cleanToken = trim((string)$token);
        $headers[] = 'Authorization: Bearer ' . $cleanToken;
        $headers[] = 'Content-Type: application/json';
        
        $apiUrl = $this->url . 'api/transaction';
        $postData = json_encode($data);
        
        // Log request for debugging (remove in production)
        error_log('Payku API Request URL: ' . $apiUrl);
        error_log('Payku API Request Data: ' . $postData);
        error_log('Payku API Token (full): ' . $cleanToken);
        error_log('Payku API Token length: ' . strlen($cleanToken));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            error_log('Payku API cURL Error: ' . $curlError);
            curl_close($ch);
            return [
                'error' => 'cURL Error: ' . $curlError,
                'http_code' => $httpCode
            ];
        }
        
        curl_close($ch);
        
        // Log response for debugging
        error_log('Payku API Response HTTP Code: ' . $httpCode);
        error_log('Payku API Response: ' . $result);
        
        // Check authentication errors
        if ($httpCode === 401 || $httpCode === 403) {
            error_log('Payku API Authentication Error - HTTP Code: ' . $httpCode);
            error_log('Payku API Authentication Error - Response: ' . $result);
        }
        
        $decoded = json_decode($result, true);
        
        // If JSON decoding failed, return raw response
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Payku API JSON Decode Error: ' . json_last_error_msg());
            return [
                'error' => 'Invalid JSON response: ' . substr($result, 0, 200),
                'http_code' => $httpCode,
                'raw_response' => $result
            ];
        }
        
        // Add HTTP code to response for debugging
        if (is_array($decoded)) {
            $decoded['http_code'] = $httpCode;
        }
        
        return $decoded;
    }
}

