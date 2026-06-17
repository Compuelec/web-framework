<?php

require_once __DIR__ . "/../models/get.model.php";
require_once __DIR__ . "/../models/post.model.php";
require_once __DIR__ . "/../models/connection.php";

require_once __DIR__ . "/../vendor/autoload.php";
use Firebase\JWT\JWT;

require_once __DIR__ . "/../models/put.model.php";

// File-based rate limiter for login endpoints
class LoginRateLimiter {

    private static $maxAttempts  = 10;
    private static $windowSeconds = 900; // 15 minutes
    private static $lockSeconds   = 900; // 15-minute lockout

    private static function filePath($ip, $suffix) {
        $key = 'ratelimit_' . hash('sha256', $ip . '|login|' . $suffix);
        // Prefer a project-local, non-web-accessible directory over the shared
        // system temp dir (which other local users can read/tamper with on
        // shared hosting). Fall back to sys_get_temp_dir() if it is unavailable
        // so login rate-limiting keeps working.
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
            // Deny any direct web access to this runtime directory
            @file_put_contents(dirname($dir) . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\n");
        }
        if (!is_writable($dir)) {
            $dir = sys_get_temp_dir();
        }
        return $dir . DIRECTORY_SEPARATOR . $key . '.json';
    }

    public static function isBlocked($ip, $suffix) {
        $file = self::filePath($ip, $suffix);
        if (!file_exists($file)) return false;
        $data = json_decode(@file_get_contents($file), true);
        if (!$data) return false;
        $now = time();
        if (!empty($data['locked_until']) && $now < $data['locked_until']) return true;
        if ($now - ($data['first_attempt_at'] ?? 0) > self::$windowSeconds) {
            @unlink($file);
            return false;
        }
        return false;
    }

    public static function remainingLockSeconds($ip, $suffix) {
        $file = self::filePath($ip, $suffix);
        if (!file_exists($file)) return 0;
        $data = json_decode(@file_get_contents($file), true);
        return (!$data || empty($data['locked_until'])) ? 0 : max(0, $data['locked_until'] - time());
    }

    public static function recordFailure($ip, $suffix) {
        $file = self::filePath($ip, $suffix);
        $now  = time();
        $data = file_exists($file) ? (json_decode(@file_get_contents($file), true) ?: []) : [];
        if (empty($data) || $now - ($data['first_attempt_at'] ?? 0) > self::$windowSeconds) {
            $data = ['attempts' => 1, 'first_attempt_at' => $now];
        } else {
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        }
        if ($data['attempts'] >= self::$maxAttempts) {
            $data['locked_until'] = $now + self::$lockSeconds;
            error_log("Login rate limit hit: IP={$ip} suffix={$suffix} locked until " . date('Y-m-d H:i:s', $data['locked_until']));
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    public static function clearFailures($ip, $suffix) {
        @unlink(self::filePath($ip, $suffix));
    }
}

class PostController{

	// POST request to create data

	static public function postData($table, $data, $suffix = null){

		$response = PostModel::postData($table, $data);
		
		// Check if response indicates database connection error
		if (is_array($response) && isset($response["error"]) && $response["error"] == "Unable to connect to database") {
			$json = array(
				'status' => 500,
				'results' => 'Database connection failed. Please check your database configuration.'
			);
			http_response_code(500);
			echo json_encode($json);
			return;
		}
		
		// Check if response is an error array (from errorInfo())
		if (is_array($response) && isset($response[0]) && is_numeric($response[0]) && isset($response[2])) {
			error_log("PostController DB error: " . ($response[2] ?? 'unknown'));
			$json = array('status' => 500, 'results' => 'Database error');
			http_response_code(500);
			echo json_encode($json);
			return;
		}
		
		// Check if response is successful
		if (is_array($response) && isset($response["comment"]) && $response["comment"] == "The process was successful") {
			// Get the created record
			$lastId = $response["lastId"] ?? null;
			if ($lastId) {
				
				// Determine suffix - try to get from parameter, or infer from table name
				if ($suffix === null) {
					// Try to infer suffix from table name
					if (preg_match('/(\w+?)s$/', $table, $matches)) {
						$suffix = $matches[1]; // Remove 's' at the end (e.g., "properties" -> "property")
					} else {
						$suffix = $table; // Use table name as suffix
					}
				}
				
				$idField = "id_" . $suffix;
				$createdRecord = GetModel::getDataFilter($table, "*", $idField, $lastId, null, null, null, null);
				if (!empty($createdRecord)) {
					$return = new PostController();
					$return -> fncResponse($createdRecord, null, $suffix);
					return;
				}
			}
			// If we can't get the record, return the success response as-is
			$return = new PostController();
			$return -> fncResponse(array((object)$response), null, $suffix);
			return;
		}
		
		// Default response
		$return = new PostController();
		$return -> fncResponse($response, null, $suffix);

	}

	// POST request to register user

	static public function postRegister($table, $data, $suffix){

		if(isset($data["password_".$suffix]) && $data["password_".$suffix] != null){

			$data["password_".$suffix] = password_hash($data["password_".$suffix], PASSWORD_BCRYPT);

			$response = PostModel::postData($table, $data);

			$return = new PostController();
			$return -> fncResponse($response,null,$suffix);

		}else{

			// Register users from external apps

			$response = PostModel::postData($table, $data);

			if(isset($response["comment"]) && $response["comment"] == "The process was successful" ){

				// Validate user exists in database

				$response = GetModel::getDataFilter($table, "*", "email_".$suffix, $data["email_".$suffix], null,null,null,null);
				
				if(!empty($response)){		

					$token = Connection::jwt($response[0]->{"id_".$suffix}, $response[0]->{"email_".$suffix});

					$config = Connection::getConfig();
					$jwtSecret = $config['jwt']['secret'] ?? '';
					$jwt = JWT::encode($token, $jwtSecret);

					// Update database with user token

					$data = array(

						"token_".$suffix => $jwt,
						"token_exp_".$suffix => $token["exp"]

					);

					$update = PutModel::putData($table, $data, $response[0]->{"id_".$suffix}, "id_".$suffix);

					if(isset($update["comment"]) && $update["comment"] == "The process was successful" ){

						$response[0]->{"token_".$suffix} = $jwt;
						$response[0]->{"token_exp_".$suffix} = $token["exp"];

						$return = new PostController();
						$return -> fncResponse($response, null,$suffix);

					}

				}


			}


		}

	}

	// POST request for user login

	static public function postLogin($table, $data, $suffix){

		$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

		// Rate limit check before any DB query
		if (LoginRateLimiter::isBlocked($ip, $suffix)) {
			$remaining = ceil(LoginRateLimiter::remainingLockSeconds($ip, $suffix) / 60);
			$json = ['status' => 429, 'results' => "Too many failed attempts. Try again in {$remaining} minute(s)."];
			http_response_code(429);
			echo json_encode($json);
			return;
		}

		// Validate user exists in database

		$response = GetModel::getDataFilter($table, "*", "email_".$suffix, $data["email_".$suffix], null,null,null,null);

		if(!empty($response)){

			if($response[0]->{"password_".$suffix} != null)	{

				// Verify password using bcrypt — compatible with both old crypt() and new password_hash() hashes
				if(password_verify($data["password_".$suffix], $response[0]->{"password_".$suffix})){

					LoginRateLimiter::clearFailures($ip, $suffix);

					$token = Connection::jwt($response[0]->{"id_".$suffix}, $response[0]->{"email_".$suffix});

					$config = Connection::getConfig();
					$jwtSecret = $config['jwt']['secret'] ?? '';
					$jwt = JWT::encode($token, $jwtSecret);

					// Update database with user token

					$data = array(

						"token_".$suffix => $jwt,
						"token_exp_".$suffix => $token["exp"]

					);

					$update = PutModel::putData($table, $data, $response[0]->{"id_".$suffix}, "id_".$suffix);

					if(isset($update["comment"]) && $update["comment"] == "The process was successful" ){

						$response[0]->{"token_".$suffix} = $jwt;
						$response[0]->{"token_exp_".$suffix} = $token["exp"];

						$return = new PostController();
						$return -> fncResponse($response, null,$suffix);

					}


				}else{

					LoginRateLimiter::recordFailure($ip, $suffix);
					error_log("Failed login (wrong password): table={$table} suffix={$suffix} ip={$ip}");
					$response = null;
					$return = new PostController();
					$return -> fncResponse($response, "Wrong password",$suffix);

				}

			}else{

				// Update token for users logged in from external apps

				LoginRateLimiter::clearFailures($ip, $suffix);

				$token = Connection::jwt($response[0]->{"id_".$suffix}, $response[0]->{"email_".$suffix});

				$config = Connection::getConfig();
				$jwtSecret = $config['jwt']['secret'] ?? '';
				$jwt = JWT::encode($token, $jwtSecret);

				$data = array(

					"token_".$suffix => $jwt,
					"token_exp_".$suffix => $token["exp"]

				);

				$update = PutModel::putData($table, $data, $response[0]->{"id_".$suffix}, "id_".$suffix);

				if(isset($update["comment"]) && $update["comment"] == "The process was successful" ){

					$response[0]->{"token_".$suffix} = $jwt;
					$response[0]->{"token_exp_".$suffix} = $token["exp"];

					$return = new PostController();
					$return -> fncResponse($response, null,$suffix);

				}

			}

		}else{

			LoginRateLimiter::recordFailure($ip, $suffix);
			error_log("Failed login (wrong email): table={$table} suffix={$suffix} ip={$ip}");
			$response = null;
			$return = new PostController();
			$return -> fncResponse($response, "Wrong email",$suffix);

		}


	}

	// Controller responses

	public function fncResponse($response,$error,$suffix){

		if(!empty($response)){

			// Check if response is an array with "comment" key (success response from PostModel)
			if (is_array($response) && isset($response["comment"]) && $response["comment"] == "The process was successful") {
				// This is a success response from PostModel, convert to proper format
				$json = array(
					'status' => 200,
					'results' => array((object)$response)
				);
			} else {
				// Remove password from response if it's an array of objects
				if (is_array($response) && isset($response[0]) && is_object($response[0]) && isset($response[0]->{"password_".$suffix})) {
					unset($response[0]->{"password_".$suffix});
				}

				$json = array(
					'status' => 200,
					'results' => $response
				);
			}

		}else{

			if($error != null){

				$json = array(
					'status' => 400,
					"results" => $error
				);

			}else{

				$json = array(

					'status' => 404,
					'results' => 'Not Found',
					'method' => 'post'

				);
			}

		}

		http_response_code($json["status"]);
		echo json_encode($json);

	}

}