<?php 

class CurlController{

	// Load configuration
	private static function getConfig(){
		$configPath = __DIR__ . '/../config.php';
		if(file_exists($configPath)){
			return require $configPath;
		}
		// Fallback to example if config doesn't exist
		$examplePath = __DIR__ . '/../config.example.php';
		if(file_exists($examplePath)){
			return require $examplePath;
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

		curl_setopt_array($curl, array(
			CURLOPT_URL => $apiBaseUrl.$url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_POSTFIELDS => $fields,
			CURLOPT_HTTPHEADER => array(
				'Authorization: '.$apiKey
			),
		));

		$response = curl_exec($curl);

		curl_close($curl);
		$response = json_decode($response);
		return $response;

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
