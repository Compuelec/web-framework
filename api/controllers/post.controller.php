<?php 

require_once __DIR__ . "/../models/get.model.php";
require_once __DIR__ . "/../models/post.model.php";
require_once __DIR__ . "/../models/connection.php";

require_once __DIR__ . "/../vendor/autoload.php";
use Firebase\JWT\JWT;

require_once __DIR__ . "/../models/put.model.php";

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
			// This is a PDO errorInfo array
			$json = array(
				'status' => 500,
				'results' => 'Database error: ' . $response[2]
			);
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

			$config = Connection::getConfig();
			$passwordSalt = $config['password']['salt'] ?? '$2a$07$azybxcags23425sdg23sdfhsd$';
			$crypt = crypt($data["password_".$suffix], $passwordSalt);

			$data["password_".$suffix] = $crypt;

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

		// Validate user exists in database

		$response = GetModel::getDataFilter($table, "*", "email_".$suffix, $data["email_".$suffix], null,null,null,null);
		
		if(!empty($response)){	

			if($response[0]->{"password_".$suffix} != null)	{
			
				// Encrypt password
				$config = Connection::getConfig();
				$passwordSalt = $config['password']['salt'] ?? '$2a$07$azybxcags23425sdg23sdfhsd$';
				$crypt = crypt($data["password_".$suffix], $passwordSalt);

				if($response[0]->{"password_".$suffix} == $crypt){

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

					$response = null;
					$return = new PostController();
					$return -> fncResponse($response, "Wrong password",$suffix);

				}

			}else{

				// Update token for users logged in from external apps

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