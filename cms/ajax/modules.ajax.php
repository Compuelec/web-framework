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
	Función auxiliar para eliminar directorio recursivamente
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
		Validar columnas vinculadas al módulo
		=============================================*/

		$url = "columns?linkTo=id_module_column&equalTo=".base64_decode($this->idModuleDelete);
		$method = "GET";
		$fields = array();

		$getColumn = CurlController::request($url,$method,$fields);

		if($getColumn->status == 200){

			echo "error";
		
		}else{

			/*=============================================
			Traer la info del módulo para saber si es tabla
			=============================================*/

			$url = "modules?linkTo=id_module&equalTo=".base64_decode($this->idModuleDelete)."&select=type_module,title_module";
			$method = "GET";
			$fields = array();

			$module = CurlController::request($url,$method,$fields);

			if($module->status == 200){

				if($module->results[0]->type_module == "tables"){

					/*=============================================
					Eliminar la tabla de la BD en MySQL
					=============================================*/

					$sqlDestroyTable = "DROP TABLE ".$module->results[0]->title_module;

					$stmtDestroyTable = InstallController::connect()->prepare($sqlDestroyTable);

					$stmtDestroyTable->execute();
				
				}else if($module->results[0]->type_module == "custom"){

					/*=============================================
					Eliminar carpeta y archivo del módulo personalizable
					=============================================*/

					// Asegurar que DIR esté definido
					if(!defined('DIR')){
						define('DIR', dirname(__DIR__));
					}

					$moduleName = str_replace(" ","_",$module->results[0]->title_module);
					$moduleDir = DIR."/views/pages/dynamic/custom/".$moduleName;

					// Función recursiva para eliminar directorio y su contenido
					if(file_exists($moduleDir) && is_dir($moduleDir)){
						
						// Eliminar todos los archivos y subdirectorios
						$files = array_diff(@scandir($moduleDir), array('.', '..'));
						
						foreach($files as $file){
							$filePath = $moduleDir.'/'.$file;
							if(is_dir($filePath)){
								// Eliminar subdirectorio recursivamente
								$this->deleteDirectory($filePath);
							}else{
								// Eliminar archivo
								@unlink($filePath);
							}
						}
						
						// Eliminar el directorio vacío
						@rmdir($moduleDir);
					}
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
			}

		}

	}

}

if(isset($_POST["idModuleDelete"])){

	$ajax = new ModulesAjax();
	$ajax -> idModuleDelete = $_POST["idModuleDelete"];
	$ajax -> token = $_POST["token"];
	$ajax -> deleteModule();
}