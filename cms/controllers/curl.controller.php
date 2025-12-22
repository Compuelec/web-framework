<?php 

class CurlController{

	// Load configuration
	private static function getConfig(){
		$configPath = __DIR__ . '/../config.php';
		// Clear cache to ensure we read the latest config
		clearstatcache(true, $configPath);
		
		if(file_exists($configPath)){
			$config = require $configPath;
			if(is_array($config)){
				return $config;
			}
		}
		// Fallback to example if config doesn't exist
		$examplePath = __DIR__ . '/../config.example.php';
		clearstatcache(true, $examplePath);
		if(file_exists($examplePath)){
			$config = require $examplePath;
			if(is_array($config)){
				return $config;
			}
		}
		// Last resort: use environment variables or defaults
		return [
			'api' => [
				'base_url' => getenv('API_BASE_URL'),
				'key' => getenv('API_KEY')
			],
			'openai' => [
				'api_url' => getenv('OPENAI_API_URL'),
				'model' => getenv('OPENAI_MODEL'),
				'token' => getenv('OPENAI_TOKEN'),
				'organization' => getenv('OPENAI_ORG')
			]
		];
	}

	// API requests
	static public function request($url,$method,$fields){

		// Force reload config to get latest values
		clearstatcache();
		$config = self::getConfig();
		$apiBaseUrl = $config['api']['base_url'] ?? 'http://localhost/chatcenter/api/';
		$apiKey = $config['api']['key'] ?? '';

		$curl = curl_init();

		// Convert array to form-urlencoded string if needed
		$postFields = $fields;
		if(is_array($fields) && ($method == 'POST' || $method == 'PUT')){
			$postFields = http_build_query($fields);
		}

		$headers = array(
			'Authorization: '.$apiKey
		);

		// Add Content-Type for POST/PUT requests
		if(($method == 'POST' || $method == 'PUT') && is_array($fields)){
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
		}

		curl_setopt_array($curl, array(
			CURLOPT_URL => $apiBaseUrl.$url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_POSTFIELDS => $postFields,
			CURLOPT_HTTPHEADER => $headers,
		));

		$response = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$curlError = curl_error($curl);
		$curlInfo = curl_getinfo($curl);

		curl_close($curl);

		// Handle cURL errors
		if ($response === false || !empty($curlError)) {
			error_log("CurlController Error - cURL Error: " . $curlError);
			error_log("CurlController Error - URL: " . $apiBaseUrl . $url);
			return (object)[
				'status' => 500,
				'message' => 'Error de conexión: ' . ($curlError ?: 'Error desconocido'),
				'results' => []
			];
		}

		// Log response info for debugging
		if (empty($response)) {
			error_log("CurlController Warning - Empty response from: " . $apiBaseUrl . $url);
			error_log("CurlController Warning - HTTP Code: " . $httpCode);
			error_log("CurlController Warning - cURL Info: " . json_encode($curlInfo));
		}

		// Log raw response for debugging if it's not valid JSON
		$trimmedResponse = trim($response);
		$isJson = false;
		if (!empty($trimmedResponse) && ($trimmedResponse[0] === '{' || $trimmedResponse[0] === '[')) {
			$isJson = true;
		}

		// Decode JSON response
		$decodedResponse = json_decode($response);

		// If JSON decode failed or returned null, return error object with raw response
		if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
			// Log the raw response for debugging
			$responseLength = strlen($response);
			$responsePreview = $responseLength > 0 ? substr($response, 0, 500) : '(respuesta vacía)';
			error_log("CurlController JSON Error - Raw Response Length: " . $responseLength);
			error_log("CurlController JSON Error - Raw Response (first 500 chars): " . $responsePreview);
			error_log("CurlController JSON Error - URL: " . $apiBaseUrl . $url);
			error_log("CurlController JSON Error - HTTP Code: " . $httpCode);
			error_log("CurlController JSON Error - Method: " . $method);
			if ($method == 'POST' || $method == 'PUT') {
				error_log("CurlController JSON Error - Post Fields: " . (is_array($fields) ? json_encode($fields) : $fields));
			}
			
			// Try to extract error message from HTML if it's an HTML error page
			$errorMessage = 'Error al decodificar respuesta JSON: ' . json_last_error_msg();
			if (empty($response)) {
				$errorMessage .= '. La respuesta del servidor está vacía. Verifica que la API esté funcionando correctamente.';
			} elseif (stripos($response, '<html') !== false || stripos($response, '<!DOCTYPE') !== false) {
				// It's HTML, try to extract error message
				if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $response, $matches)) {
					$errorMessage .= '. El servidor devolvió HTML: ' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
				} elseif (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $response, $matches)) {
					$errorMessage .= '. El servidor devolvió HTML: ' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
				} else {
					$errorMessage .= '. El servidor devolvió HTML en lugar de JSON';
				}
				// Show first 200 chars of HTML response
				$htmlPreview = substr(strip_tags($response), 0, 200);
				if (!empty($htmlPreview)) {
					$errorMessage .= '. Contenido: ' . htmlspecialchars($htmlPreview, ENT_QUOTES, 'UTF-8');
				}
			} else {
				// It's not HTML, show first 200 chars
				$preview = substr($response, 0, 200);
				$errorMessage .= '. Respuesta: ' . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
			}
			
			return (object)[
				'status' => $httpCode ?: 500,
				'message' => $errorMessage,
				'results' => [],
				'raw_response' => $responsePreview // Include raw response for debugging
			];
		}

		// If decoded response is null but no JSON error, return error object
		if ($decodedResponse === null) {
			return (object)[
				'status' => $httpCode ?: 500,
				'message' => 'Respuesta vacía del servidor',
				'results' => []
			];
		}

		// Ensure response has status property
		if (!isset($decodedResponse->status)) {
			$decodedResponse->status = $httpCode ?: 200;
		}

		// Ensure response has results property if it's an array response
		if (!isset($decodedResponse->results) && is_array($decodedResponse)) {
			$decodedResponse = (object)[
				'status' => $httpCode ?: 200,
				'results' => $decodedResponse
			];
		}

		return $decodedResponse;

	}

	// OpenAI/ChatGPT API requests
	static public function chatGPT($content,$token,$org){

		$config = self::getConfig();
		$openaiConfig = $config['openai'] ?? [];
		
		// Use provided token/org or fallback to config
		$apiToken = $token ?: ($openaiConfig['token']);
		$apiOrg = $org ?: ($openaiConfig['organization']);
		$apiUrl = $openaiConfig['api_url'];
		$model = $openaiConfig['model'];

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $apiUrl,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>'{
		    "model": "'.$model.'",
		    "messages":[{"role": "user", "content": "'.$content.'"}]
		}',
		  CURLOPT_HTTPHEADER => array(
		    'Authorization: Bearer '.$apiToken,
		    'OpenAI-Organization: '.$apiOrg,
		    'Content-Type: application/json'
		  ),
		));

		$response = curl_exec($curl);

		curl_close($curl);
		$response = json_decode($response);
		return $response->choices[0]->message->content;

	}

}
