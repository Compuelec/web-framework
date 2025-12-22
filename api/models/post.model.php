<?php 

require_once __DIR__ . "/connection.php";

class PostModel{

	// POST request to create data dynamically

	static public function postData($table, $data){

		$columns = "";
		$params = "";

		foreach ($data as $key => $value) {
			
			$columns .= $key.",";
			
			$params .= ":".$key.",";
			
		}

		$columns = substr($columns, 0, -1);
		$params = substr($params, 0, -1);


		$sql = "INSERT INTO $table ($columns) VALUES ($params)";

		$link = Connection::connect();
		if ($link === null) {
			return array(
				"comment" => "Database connection failed",
				"error" => "Unable to connect to database"
			);
		}
		$stmt = $link->prepare($sql);

		foreach ($data as $key => $value) {

			$stmt->bindParam(":".$key, $data[$key], PDO::PARAM_STR);
		
		}

		if($stmt -> execute()){

			$response = array(

				"lastId" => $link->lastInsertId(),
				"comment" => "The process was successful"
			);

			return $response;
		
		}else{

			return $link->errorInfo();

		}


	}

}