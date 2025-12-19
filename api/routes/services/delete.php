<?php

require_once "models/connection.php";
require_once "controllers/delete.controller.php";

if(isset($_GET["id"]) && isset($_GET["nameId"])){

	$columns = array($_GET["nameId"]);

	// Validate table and columns

	if(empty(Connection::getColumnsData($table, $columns))){

		$json = array(
		 	'status' => 400,
		 	'results' => "Error: Fields in the form do not match the database"
		);

		echo json_encode($json, http_response_code($json["status"]));

		return;

	}

	// DELETE request for authorized users

	if(isset($_GET["token"])){

		if($_GET["token"] == "no" && isset($_GET["except"])){

			/*=============================================
			Validar la tabla y las columnas
			=============================================*/

			$columns = array($_GET["except"]);

			if(empty(Connection::getColumnsData($table, $columns))){

				$json = array(
				 	'status' => 400,
				 	'results' => "Error: Fields in the form do not match the database"
				);

				echo json_encode($json, http_response_code($json["status"]));

				return;

			}

			// Request controller response to delete data in any table

			$response = new DeleteController();
			$response -> deleteData($table,$_GET["id"],$_GET["nameId"]);	


		}else{

			$tableToken = $_GET["table"] ?? "users";
			$suffix = $_GET["suffix"] ?? "user";

			$validate = Connection::tokenValidate($_GET["token"],$tableToken,$suffix);

			// Request controller response to delete data in any table
				
			if($validate == "ok"){
		
				$response = new DeleteController();
				$response -> deleteData($table,$_GET["id"],$_GET["nameId"]);

			}

			// Error when token has expired

			if($validate == "expired"){

				$json = array(
				 	'status' => 303,
				 	'results' => "Error: The token has expired"
				);

				echo json_encode($json, http_response_code($json["status"]));

				return;

			}

			// Error when token doesn't match in database

			if($validate == "no-auth"){

				$json = array(
				 	'status' => 400,
				 	'results' => "Error: The user is not authorized"
				);

				echo json_encode($json, http_response_code($json["status"]));

				return;

			}

		}

	// Error when token is not sent

	}else{

		$json = array(
		 	'status' => 400,
		 	'results' => "Error: Authorization required"
		);

		echo json_encode($json, http_response_code($json["status"]));

		return;	

	}	

}

