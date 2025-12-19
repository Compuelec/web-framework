<?php

require_once "get.model.php";

class Connection{

	// Load configuration
	static public function getConfig(){
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
			'database' => [
				'host' => getenv('DB_HOST') ?: 'localhost',
				'name' => getenv('DB_NAME') ?: 'chatcenter',
				'user' => getenv('DB_USER') ?: 'root',
				'pass' => getenv('DB_PASS') ?: '',
				'charset' => 'utf8mb4'
			],
			'api' => [
				'key' => getenv('API_KEY') ?: '',
				'public_access_tables' => ['']
			],
			'jwt' => [
				'secret' => getenv('JWT_SECRET') ?: '',
				'expiration' => 86400
			],
			'password' => [
				'salt' => getenv('PASSWORD_SALT') ?: '$2a$07$azybxcags23425sdg23sdfhsd$'
			]
		];
	}

	// Database configuration
	static public function infoDatabase(){
		$config = self::getConfig();
		return [
			"database" => $config['database']['name'],
			"user" => $config['database']['user'],
			"pass" => $config['database']['pass']
		];
	}

	// API Key
	static public function apikey(){
		$config = self::getConfig();
		return $config['api']['key'];
	}

	// Public access tables
	static public function publicAccess(){
		$config = self::getConfig();
		return $config['api']['public_access_tables'];
	}

	// Database connection
	static public function connect(){
		$config = self::getConfig();
		$dbConfig = $config['database'] ?? [];
		
		try{
			$link = new PDO(
				"mysql:host=".($dbConfig['host'] ?? 'localhost').";dbname=".($dbConfig['name'] ?? 'chatcenter'),
				$dbConfig['user'] ?? 'root', 
				$dbConfig['pass'] ?? ''
			);

			$link->exec("set names ".($dbConfig['charset'] ?? 'utf8mb4'));

		}catch(PDOException $e){

			die("Error: ".$e->getMessage());

		}

		return $link;

	}

	// Validate table and columns existence
	static public function getColumnsData($table, $columns){

		$database = Connection::infoDatabase()["database"];

		$validate = Connection::connect()
		->query("SELECT COLUMN_NAME AS item FROM information_schema.columns WHERE table_schema = '$database' AND table_name = '$table'")
		->fetchAll(PDO::FETCH_OBJ);

		if(empty($validate)){

			return null;

		}else{

			// Handle global column selection
			if($columns[0] == "*"){
				
				array_shift($columns);

			}

			// Validate column existence
			$sum = 0;
				
			foreach ($validate as $key => $value) {

				$sum += in_array($value->item, $columns);	
				
						
			}

			return $sum == count($columns) ? $validate : null;
			
		}

	}

	// Generate authentication token

	static public function jwt($id, $email){

		$time = time();

		$token = array(

			"iat" =>  $time, // Token start time
			"exp" => $time + (60*60*24), // Token expiration (1 day)
			"data" => [

				"id" => $id,
				"email" => $email
			]

		);

		return $token;
	}

	// Validate security token
	static public function tokenValidate($token,$table,$suffix){

		$user = GetModel::getDataFilter($table, "token_exp_".$suffix, "token_".$suffix, $token, null,null,null,null);
		
		if(!empty($user)){

			// Check if token has expired
			$time = time();

			if($time < $user[0]->{"token_exp_".$suffix}){

				return "ok";

			}else{

				return "expired";
			}

		}else{

			return "no-auth";

		}

	}

}