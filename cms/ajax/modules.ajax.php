<?php 

require_once "../controllers/curl.controller.php";
require_once "../controllers/install.controller.php";

class ModulesAjax{

	/*=============================================
	Eliminar Módulo
	=============================================*/ 

	public $idModuleDelete;
	public $token;

	/*=============================================
	Helper function to recursively delete directory
	=============================================*/

	private function deleteDirectory($dir){
		if(!file_exists($dir) || !is_dir($dir)){
			return false;
		}

		$files = array_diff(@scandir($dir), array('.', '..'));
		
		foreach($files as $file){
			$filePath = $dir.'/'.$file;
			if(is_dir($filePath)){
				$this->deleteDirectory($filePath);
			}else{
				@unlink($filePath);
			}
		}
		
		return @rmdir($dir);
	} 

	public function deleteModule(){

		/*=============================================
		Traer la info del módulo para saber si es tabla
		=============================================*/

		$url = "modules?linkTo=id_module&equalTo=".base64_decode($this->idModuleDelete)."&select=type_module,title_module";
		$method = "GET";
		$fields = array();

		$module = CurlController::request($url,$method,$fields);

		if($module->status != 200){
			echo "error";
			return;
		}

		$moduleType = $module->results[0]->type_module;
		$moduleTitle = $module->results[0]->title_module;

		/*=============================================
		Si es módulo de tipo tabla, eliminar columnas primero
		=============================================*/

		if($moduleType == "tables"){

			/*=============================================
			Obtener todas las columnas vinculadas al módulo
			=============================================*/

			$url = "columns?linkTo=id_module_column&equalTo=".base64_decode($this->idModuleDelete);
			$method = "GET";
			$fields = array();

			$getColumns = CurlController::request($url,$method,$fields);

			if($getColumns->status == 200 && isset($getColumns->results) && is_array($getColumns->results)){

				/*=============================================
				Eliminar todas las columnas asociadas
				=============================================*/

				foreach($getColumns->results as $column){

					$url = "columns?id=".$column->id_column."&nameId=id_column&token=".$this->token."&table=admins&suffix=admin";
					$method = "DELETE";
					$fields = array();

					$deleteColumn = CurlController::request($url,$method,$fields);

					// Continue even if some columns fail to delete
				}

				/*=============================================
				Eliminar la tabla de la BD en MySQL
				=============================================*/

				$sqlDestroyTable = "DROP TABLE IF EXISTS ".$moduleTitle;

				$stmtDestroyTable = InstallController::connect()->prepare($sqlDestroyTable);

				$stmtDestroyTable->execute();

			}else{

				/*=============================================
				Si no hay columnas, solo eliminar la tabla
				=============================================*/

				$sqlDestroyTable = "DROP TABLE IF EXISTS ".$moduleTitle;

				$stmtDestroyTable = InstallController::connect()->prepare($sqlDestroyTable);

				$stmtDestroyTable->execute();

			}

		}else if($moduleType == "custom"){

			/*=============================================
			Delete custom module folder and file
			=============================================*/

			// Ensure DIR is defined
			if(!defined('DIR')){
				define('DIR', dirname(__DIR__));
			}

			$moduleName = str_replace(" ","_",$moduleTitle);
			$moduleDir = DIR."/views/pages/dynamic/custom/".$moduleName;

			// Recursive function to delete directory and its content
			if(file_exists($moduleDir) && is_dir($moduleDir)){
				
				// Delete all files and subdirectories
				$files = array_diff(@scandir($moduleDir), array('.', '..'));
				
				foreach($files as $file){
					$filePath = $moduleDir.'/'.$file;
					if(is_dir($filePath)){
						// Delete subdirectory recursively
						$this->deleteDirectory($filePath);
					}else{
						// Delete file
						@unlink($filePath);
					}
				}
				
				// Delete empty directory
				@rmdir($moduleDir);
			}

		}else{

			/*=============================================
			Para otros tipos de módulos, validar columnas vinculadas
			=============================================*/

			$url = "columns?linkTo=id_module_column&equalTo=".base64_decode($this->idModuleDelete);
			$method = "GET";
			$fields = array();

			$getColumn = CurlController::request($url,$method,$fields);

			if($getColumn->status == 200){

				echo "error";
				return;
			}

		}

		/*=============================================
		Eliminar el módulo
		=============================================*/

		$url = "modules?id=".base64_decode($this->idModuleDelete)."&nameId=id_module&token=".$this->token."&table=admins&suffix=admin";
		$method = "DELETE";
		$fields = array();

		$deleteModule = CurlController::request($url,$method,$fields);

		if($deleteModule->status == 200){

			echo $deleteModule->status;
		}else{

			echo "error";
		}

	}

	/*=============================================
	Get database tables
	=============================================*/

	public function getTables(){

		$link = InstallController::connect();

		if($link === null){

			header('Content-Type: application/json');
			echo json_encode([
				'status' => 500,
				'results' => []
			]);
			return;

		}

		try{

			// Get only table names (not views)
			$database = InstallController::infoDatabase()["database"];
			$tables = $link->query("SHOW TABLES FROM `$database`")->fetchAll(PDO::FETCH_COLUMN);

			if(!empty($tables)){

				// Filter out system tables if needed
				$filteredTables = array_filter($tables, function($table) {
					// Exclude system tables (you can customize this filter)
					return !in_array($table, ['information_schema', 'performance_schema', 'mysql', 'sys']);
				});

				header('Content-Type: application/json');
				echo json_encode([
					'status' => 200,
					'results' => array_values($filteredTables)
				]);

			}else{

				header('Content-Type: application/json');
				echo json_encode([
					'status' => 404,
					'results' => []
				]);

			}

		}catch(PDOException $e){

			header('Content-Type: application/json');
			echo json_encode([
				'status' => 500,
				'results' => []
			]);

		}

	}

	/*=============================================
	Get columns from a table
	=============================================*/

	public $tableName;

	public function getTableColumns(){

		if(empty($this->tableName)){

			header('Content-Type: application/json');
			echo json_encode([
				'status' => 400,
				'results' => []
			]);
			return;

		}

		$link = InstallController::connect();

		if($link === null){

			header('Content-Type: application/json');
			echo json_encode([
				'status' => 500,
				'results' => []
			]);
			return;

		}

		try{

			$database = InstallController::infoDatabase()["database"];
			$columns = $link->query("SELECT COLUMN_NAME AS item FROM information_schema.columns WHERE table_schema = '$database' AND table_name = '".$this->tableName."' ORDER BY ORDINAL_POSITION")
				->fetchAll(PDO::FETCH_OBJ);

			$columnNames = array_map(function($col) {
				return $col->item;
			}, $columns);

			header('Content-Type: application/json');
			echo json_encode([
				'status' => 200,
				'results' => $columnNames
			]);

		}catch(PDOException $e){

			header('Content-Type: application/json');
			echo json_encode([
				'status' => 500,
				'results' => []
			]);

		}

	}

}

if(isset($_POST["idModuleDelete"])){

	$ajax = new ModulesAjax();
	$ajax -> idModuleDelete = $_POST["idModuleDelete"];
	$ajax -> token = $_POST["token"];
	$ajax -> deleteModule();

}else if(isset($_GET["action"]) && $_GET["action"] == "getTables"){

	$ajax = new ModulesAjax();
	$ajax -> getTables();

}else if(isset($_POST["action"]) && $_POST["action"] == "getTableColumns"){

	$ajax = new ModulesAjax();
	$ajax -> tableName = $_POST["tableName"] ?? "";
	$ajax -> getTableColumns();

}