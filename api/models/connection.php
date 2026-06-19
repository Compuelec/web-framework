<?php

require_once __DIR__ . "/get.model.php";
require_once __DIR__ . "/../vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class Connection{

	// Load configuration
	static public function getConfig(){
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
			'database' => [
				'host' => getenv('DB_HOST'),
				'name' => getenv('DB_NAME'),
				'user' => getenv('DB_USER'),
				'pass' => getenv('DB_PASS'),
				'charset' => 'utf8mb4'
			],
			'api' => [
				'key' => getenv('API_KEY'),
				'public_access_tables' => ['']
			],
			'jwt' => [
				'secret' => getenv('JWT_SECRET'),
				'expiration' => 86400
			],
			'password' => [
				'salt' => getenv('PASSWORD_SALT'),
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

	// Tables allowed to be written without a user token (token=no mode).
	// Used by internal CMS flows (login, install, 2FA) that run before a
	// JWT exists. Defaults to the known CMS-internal tables when not set.
	static public function internalWriteTables(){
		$config = self::getConfig();
		$tables = $config['api']['internal_write_tables'] ?? null;
		if(!is_array($tables)){
			$tables = ['admins', 'pages', 'modules', 'folders', 'columns'];
		}
		return $tables;
	}

	// Database connection
	static public function connect(){
		$config = self::getConfig();
		$dbConfig = $config['database'] ?? [];
		
		// Validate required database configuration
		if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
			error_log("Database Connection Error: Missing required database configuration");
			return null;
		}
		
		try{
			$link = new PDO(
				"mysql:host=".$dbConfig['host'].";dbname=".$dbConfig['name'],
				$dbConfig['user'], 
				$dbConfig['pass']
			);

			$link->exec("set names ".($dbConfig['charset'] ?? 'utf8mb4'));

		}catch(PDOException $e){
			// Log error instead of die() to prevent breaking JSON response
			error_log("Database Connection Error: " . $e->getMessage());
			// Return null and let the calling code handle it
			return null;
		}

		return $link;

	}

	/**
	 * Validate that a string is a safe SQL identifier (table/column name).
	 * Only allows alphanumeric characters and underscores.
	 */
	static public function sanitizeIdentifier($name) {
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
			return null;
		}
		return $name;
	}

	/**
	 * Validate a single (optionally table-qualified) SQL identifier used in
	 * relational queries. Allows only: *, col, table.col, table.*
	 * Returns the name when safe, or null otherwise.
	 */
	static public function sanitizeQualifiedIdentifier($name) {
		$name = trim((string)$name);
		if ($name === '*') {
			return $name;
		}
		if (preg_match('/^[a-zA-Z0-9_]+(\.([a-zA-Z0-9_]+|\*))?$/', $name)) {
			return $name;
		}
		return null;
	}

	/**
	 * Validate a comma-separated list of identifiers (select/linkTo/type/
	 * filterTo) used in relational queries. Returns false if any item is
	 * not a safe SQL identifier, so the caller can reject the request.
	 */
	static public function validIdentifierList($list) {
		if ($list === null || $list === '') {
			return false;
		}
		foreach (explode(",", $list) as $item) {
			if (self::sanitizeQualifiedIdentifier($item) === null) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validate orderMode value — only ASC or DESC allowed.
	 */
	static public function sanitizeOrderMode($mode) {
		$mode = strtoupper((string)$mode);
		return in_array($mode, ['ASC', 'DESC'], true) ? $mode : null;
	}

	// Validate table and columns existence
	static public function getColumnsData($table, $columns){

		// Validate table name before using in query
		if (Connection::sanitizeIdentifier($table) === null) {
			return null;
		}

		$database = Connection::infoDatabase()["database"];

		$link = Connection::connect();
		if ($link === null) {
			return null;
		}
		$stmt = $link->prepare("SELECT COLUMN_NAME AS item FROM information_schema.columns WHERE table_schema = :db AND table_name = :table");
		$stmt->execute([':db' => $database, ':table' => $table]);
		$validate = $stmt->fetchAll(PDO::FETCH_OBJ);

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

		$config = self::getConfig();
		$jwtSecret = $config['jwt']['secret'] ?? '';

		if(empty($token) || empty($jwtSecret)){
			return "no-auth";
		}

		// 1. Cryptographically verify the token: a valid HS256 signature
		//    and a non-expired "exp" claim. A forged or tampered token
		//    throws here and never reaches the database lookup.
		try{

			JWT::decode($token, $jwtSecret, array('HS256'));

		}catch(ExpiredException $e){

			return "expired";

		}catch(\Exception $e){

			// Invalid signature, malformed token, wrong algorithm, etc.
			return "no-auth";
		}

		// 2. Confirm the token still matches the one stored for the user,
		//    so tokens can be revoked/rotated server-side (logout, reissue).
		$user = GetModel::getDataFilter($table, "token_exp_".$suffix, "token_".$suffix, $token, null,null,null,null);

		if(empty($user)){
			return "no-auth";
		}

		return "ok";

	}

}