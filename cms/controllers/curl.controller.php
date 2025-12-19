<?php 

class CurlController{

	// Load configuration
	private static function getConfig(){
		$configPath = __DIR__ . '/../config.php';
		if(file_exists($configPath)){
			$config = require $configPath;
			if(is_array($config)){
				return $config;
			}
		}
		// Fallback to example if config doesn't exist
		$examplePath = __DIR__ . '/../config.example.php';
		if(file_exists($examplePath)){
			$config = require $examplePath;
			if(is_array($config)){
				return $config;
			}
		}
		// Last resort: use environment variables or defaults
		return [
			'api' => [
				'base_url' => getenv('API_BASE_URL') ?: 'http://localhost/chatcenter/api/',
				'key' => getenv('API_KEY') ?: ''
			],
			'openai' => [
				'api_url' => getenv('OPENAI_API_URL') ?: 'https://api.openai.com/v1/chat/completions',
				'model' => getenv('OPENAI_MODEL') ?: 'gpt-4-0613',
				'token' => getenv('OPENAI_TOKEN') ?: '',
				'organization' => getenv('OPENAI_ORG') ?: ''
			]
		];
	}

	// API requests
	static public function request($url,$method,$fields){

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

		curl_close($curl);

		// Handle cURL errors
		if ($response === false || !empty($curlError)) {
			return (object)[
				'status' => 500,
				'message' => 'Error de conexión: ' . ($curlError ?: 'Error desconocido'),
				'results' => []
			];
		}

		// Decode JSON response
		$decodedResponse = json_decode($response);

		// If JSON decode failed or returned null, return error object
		if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
			return (object)[
				'status' => 500,
				'message' => 'Error al decodificar respuesta JSON: ' . json_last_error_msg(),
				'results' => []
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
		$apiToken = $token ?: ($openaiConfig['token'] ?? '');
		$apiOrg = $org ?: ($openaiConfig['organization'] ?? '');
		$apiUrl = $openaiConfig['api_url'] ?? 'https://api.openai.com/v1/chat/completions';
		$model = $openaiConfig['model'] ?? 'gpt-4-0613';

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
