<?php 

require_once "models/delete.model.php";

class DeleteController{

	/*=============================================
	DELETE request to delete data
	=============================================*/

	static public function deleteData($table, $id, $nameId){

		$response = DeleteModel::deleteData($table, $id, $nameId);
		
		$return = new DeleteController();
		$return -> fncResponse($response);

	}

	/*=============================================
	Controller responses
	=============================================*/

	public function fncResponse($response){

		if(!empty($response)){

			$json = array(

				'status' => 200,
				'results' => $response

			);

		}else{

			$json = array(

				'status' => 404,
				'results' => 'Not Found',
				'method' => 'delete'

			);

		}

		http_response_code($json["status"]);
		echo json_encode($json);

	}

}