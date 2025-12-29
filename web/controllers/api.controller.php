<?php

/**
 * API Controller
 * Handles all API requests to the REST API
 * 
 * This controller provides methods to interact with the dynamic CMS tables
 * through the REST API endpoints.
 */
class ApiController {
    
    private static $config = null;
    
    /**
     * Load configuration
     * @return array Configuration array
     * @throws Exception If configuration is missing or invalid
     */
    private static function getConfig() {
        if (self::$config !== null) {
            return self::$config;
        }
        
        $configPath = __DIR__ . '/../config.php';
        if (file_exists($configPath)) {
            self::$config = require $configPath;
        } else {
            $examplePath = __DIR__ . '/../config.example.php';
            if (file_exists($examplePath)) {
                self::$config = require $examplePath;
            } else {
                // Try environment variables as last resort
                $envBaseUrl = getenv('API_BASE_URL');
                $envKey = getenv('API_KEY');
                
                if ($envBaseUrl && $envKey) {
                    self::$config = [
                        'api' => [
                            'base_url' => $envBaseUrl,
                            'key' => $envKey
                        ]
                    ];
                } else {
                    throw new Exception(
                        'API configuration is missing. ' .
                        'Please create web/config.php from web/config.example.php and configure your API settings. ' .
                        'Alternatively, set API_BASE_URL and API_KEY environment variables.'
                    );
                }
            }
        }
        
        // Validate required configuration
        if (!is_array(self::$config) || 
            !isset(self::$config['api']) || 
            !isset(self::$config['api']['base_url']) || 
            empty(self::$config['api']['base_url']) ||
            !isset(self::$config['api']['key']) || 
            empty(self::$config['api']['key'])) {
            throw new Exception(
                'API configuration is incomplete. ' .
                'Please ensure web/config.php contains valid api.base_url and api.key values. ' .
                'Or set API_BASE_URL and API_KEY environment variables.'
            );
        }
        
        return self::$config;
    }
    
    /**
     * Make API request
     * 
     * @param string $table Table name
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $params Query parameters for GET requests
     * @param array $fields Data fields for POST/PUT requests
     * @return object Response object with status, results, and message
     */
    public static function request($table, $method = 'GET', $params = [], $fields = []) {
        try {
            $config = self::getConfig();
        } catch (Exception $e) {
            error_log("ApiController Configuration Error: " . $e->getMessage());
            return (object)[
                'status' => 500,
                'message' => $e->getMessage(),
                'results' => [],
                'total' => 0
            ];
        }
        
        // Validate configuration values
        if (empty($config['api']['base_url']) || empty($config['api']['key'])) {
            $errorMsg = 'API configuration is incomplete. Please configure api.base_url and api.key in web/config.php';
            error_log("ApiController Configuration Error: " . $errorMsg);
            return (object)[
                'status' => 500,
                'message' => $errorMsg,
                'results' => [],
                'total' => 0
            ];
        }
        
        $apiBaseUrl = $config['api']['base_url'];
        $apiKey = $config['api']['key'];
        
        // Build URL with query parameters
        $url = rtrim($apiBaseUrl, '/') . '/' . $table;
        if (!empty($params) && $method === 'GET') {
            $url .= '?' . http_build_query($params);
        }
        
        $curl = curl_init();
        
        // Prepare headers
        $headers = [
            'Authorization: ' . $apiKey
        ];
        
        // Prepare POST/PUT data
        $postFields = '';
        if (in_array($method, ['POST', 'PUT']) && !empty($fields)) {
            if (is_array($fields)) {
                $postFields = http_build_query($fields);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } else {
                $postFields = $fields;
            }
        }
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        
        curl_close($curl);
        
        // Handle cURL errors
        if ($response === false || !empty($curlError)) {
            error_log("ApiController Error - cURL Error: " . $curlError);
            error_log("ApiController Error - URL: " . $url);
            return (object)[
                'status' => 500,
                'message' => 'Connection error: ' . ($curlError ?: 'Unknown error'),
                'results' => [],
                'total' => 0
            ];
        }
        
        // Decode JSON response
        $decodedResponse = json_decode($response);
        
        // Handle JSON decode errors
        if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("ApiController JSON Error - Response: " . substr($response, 0, 500));
            return (object)[
                'status' => $httpCode ?: 500,
                'message' => 'Error decoding JSON response: ' . json_last_error_msg(),
                'results' => [],
                'total' => 0
            ];
        }
        
        // Ensure response has required properties
        if (!isset($decodedResponse->status)) {
            $decodedResponse->status = $httpCode ?: 200;
        }
        
        // Ensure results is an array
        if (!isset($decodedResponse->results)) {
            $decodedResponse->results = [];
        } elseif (!is_array($decodedResponse->results)) {
            // If results is not an array (could be string, object, etc.), convert to array
            if (is_object($decodedResponse->results)) {
                $decodedResponse->results = [$decodedResponse->results];
            } else {
                // If it's a string or other type, wrap in array or set to empty
                $decodedResponse->results = [];
            }
        }
        
        // Calculate total if not set
        if (!isset($decodedResponse->total)) {
            $decodedResponse->total = is_array($decodedResponse->results) 
                ? count($decodedResponse->results) 
                : 0;
        }
        
        return $decodedResponse;
    }
    
    /**
     * Get all records from a table
     * 
     * @param string $table Table name
     * @param string $select Columns to select (default: *)
     * @param string $orderBy Column to order by
     * @param string $orderMode Order mode (ASC/DESC)
     * @param int $startAt Start position for pagination
     * @param int $endAt End position for pagination
     * @return object Response object
     */
    public static function getAll($table, $select = '*', $orderBy = null, $orderMode = 'ASC', $startAt = null, $endAt = null) {
        $params = [];
        if ($select !== '*') {
            $params['select'] = $select;
        }
        if ($orderBy) {
            $params['orderBy'] = $orderBy;
            $params['orderMode'] = $orderMode;
        }
        if ($startAt !== null) {
            $params['startAt'] = $startAt;
        }
        if ($endAt !== null) {
            $params['endAt'] = $endAt;
        }
        
        return self::request($table, 'GET', $params);
    }
    
    /**
     * Get records filtered by column value
     * 
     * @param string $table Table name
     * @param string $linkTo Column name to filter
     * @param mixed $equalTo Value to match
     * @param string $select Columns to select
     * @param string $orderBy Column to order by
     * @param string $orderMode Order mode (ASC/DESC)
     * @return object Response object
     */
    public static function getByFilter($table, $linkTo, $equalTo, $select = '*', $orderBy = null, $orderMode = 'ASC') {
        $params = [
            'linkTo' => $linkTo,
            'equalTo' => $equalTo
        ];
        if ($select !== '*') {
            $params['select'] = $select;
        }
        if ($orderBy) {
            $params['orderBy'] = $orderBy;
            $params['orderMode'] = $orderMode;
        }
        
        return self::request($table, 'GET', $params);
    }
    
    /**
     * Search records in a table
     * 
     * @param string $table Table name
     * @param string $linkTo Column name to search in
     * @param string $search Search term
     * @param string $select Columns to select
     * @param string $orderBy Column to order by
     * @param string $orderMode Order mode (ASC/DESC)
     * @param int $startAt Start position for pagination
     * @param int $endAt End position for pagination
     * @return object Response object
     */
    public static function search($table, $linkTo, $search, $select = '*', $orderBy = null, $orderMode = 'ASC', $startAt = null, $endAt = null) {
        $params = [
            'linkTo' => $linkTo,
            'search' => $search
        ];
        if ($select !== '*') {
            $params['select'] = $select;
        }
        if ($orderBy) {
            $params['orderBy'] = $orderBy;
            $params['orderMode'] = $orderMode;
        }
        if ($startAt !== null) {
            $params['startAt'] = $startAt;
        }
        if ($endAt !== null) {
            $params['endAt'] = $endAt;
        }
        
        return self::request($table, 'GET', $params);
    }
    
    /**
     * Get records by ID
     * 
     * @param string $table Table name
     * @param mixed $id Record ID
     * @param string $nameId ID column name (default: id_{table})
     * @return object Response object
     */
    public static function getById($table, $id, $nameId = null) {
        if ($nameId === null) {
            // Try to guess the ID column name
            $nameId = 'id_' . $table;
        }
        
        $params = [
            'id' => $id,
            'nameId' => $nameId
        ];
        
        return self::request($table, 'GET', $params);
    }
    
    /**
     * Get records in a range
     * 
     * @param string $table Table name
     * @param string $linkTo Column name
     * @param mixed $between1 Start value
     * @param mixed $between2 End value
     * @param string $select Columns to select
     * @param string $orderBy Column to order by
     * @param string $orderMode Order mode (ASC/DESC)
     * @return object Response object
     */
    public static function getByRange($table, $linkTo, $between1, $between2, $select = '*', $orderBy = null, $orderMode = 'ASC') {
        $params = [
            'linkTo' => $linkTo,
            'between1' => $between1,
            'between2' => $between2
        ];
        if ($select !== '*') {
            $params['select'] = $select;
        }
        if ($orderBy) {
            $params['orderBy'] = $orderBy;
            $params['orderMode'] = $orderMode;
        }
        
        return self::request($table, 'GET', $params);
    }
    
    /**
     * Create a new record
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @return object Response object
     */
    public static function create($table, $data) {
        return self::request($table, 'POST', [], $data);
    }
    
    /**
     * Update a record
     * 
     * @param string $table Table name
     * @param mixed $id Record ID
     * @param array $data Data to update
     * @param string $nameId ID column name
     * @return object Response object
     */
    public static function update($table, $id, $data, $nameId = null) {
        if ($nameId === null) {
            $nameId = 'id_' . $table;
        }
        
        $params = [
            'id' => $id,
            'nameId' => $nameId
        ];
        
        return self::request($table, 'PUT', $params, $data);
    }
    
    /**
     * Delete a record
     * 
     * @param string $table Table name
     * @param mixed $id Record ID
     * @param string $nameId ID column name
     * @return object Response object
     */
    public static function delete($table, $id, $nameId = null) {
        if ($nameId === null) {
            $nameId = 'id_' . $table;
        }
        
        $params = [
            'id' => $id,
            'nameId' => $nameId
        ];
        
        return self::request($table, 'DELETE', $params);
    }
}

