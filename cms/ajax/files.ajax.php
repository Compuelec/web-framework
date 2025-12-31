<?php 

// Start output buffering to prevent any output before JSON
ob_start();

define('DIR', __DIR__);

// Disable display_errors to prevent HTML output in JSON responses
ini_set("display_errors",0);
ini_set("log_errors",1);
ini_set("error_log", DIR."/php_error_log");

// Set JSON header early to ensure proper response type
header('Content-Type: application/json');

require_once "../controllers/template.controller.php";
require_once "../controllers/curl.controller.php";

// Function to get base URL (protocol + host + base path)
function getBaseUrl(){
	$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$cmsBasePath = TemplateController::cmsBasePath();
	
	// Build base URL
	$baseUrl = $protocol . '://' . $host . $cmsBasePath;
	
	// Clean double slashes (but preserve http:// or https://)
	$baseUrl = preg_replace('#([^:])//+#', '$1/', $baseUrl);
	
	return rtrim($baseUrl, '/');
}

class FilesController{

	/*=============================================
	Subir Archivos a los Servidores
	=============================================*/

	public $file;
	public $folder;
	public $token;

	public function ajaxUploadFiles(){

		/*=============================================
		Clear any output buffer to ensure clean JSON response
		=============================================*/
		
		ob_clean();

		/*=============================================
		Traer info del folder
		=============================================*/

		$url = "folders?linkTo=id_folder&equalTo=".$this->folder;
		$method = "GET";
		$fields = array();

		$folder = CurlController::request($url,$method,$fields);

		// Check if folder request was successful
		if(!is_object($folder) || !isset($folder->status)){
			$response = array(
				"status" => 500,
				"error" => "Error getting folder information: Invalid response from API"
			);
			ob_clean();
			echo json_encode($response);
			ob_end_flush();
			return;
		}

		if($folder->status == 200){

			// Validate that results exist and have at least one element
			if(!isset($folder->results) || !is_array($folder->results) || empty($folder->results)){
				$response = array(
					"status" => 404,
					"error" => "Folder not found or has no results"
				);
				ob_clean();
				echo json_encode($response);
				ob_end_flush();
				return;
			}

			$folder = $folder->results[0];

			/*=============================================
			Validar el peso máximo del archivo de acuerdo al servidor
			=============================================*/

			if($this->file["size"] > $folder->max_upload_folder){

				$response = array(

					"status" => 404,
					"error" => "Los archivos que pesan más de ".($folder->max_upload_folder/1000000)."MB no suben al servidor ".$folder->name_folder

				);

				ob_clean();
				echo json_encode($response);
				ob_end_flush();
				return;
			}

			/*=============================================
			Capturamos la extensión del archivo
			=============================================*/

			$extension = explode(".",$this->file["name"]);

			/*=============================================
			Creamos el nombre del archivo
			=============================================*/

			$fileName = uniqid().getdate()["seconds"].".".end($extension);
	
			/*=============================================
			Subiendo archivos al servidor propio
			=============================================*/

			if($this->folder == 1){

				/*=============================================
				Capturar ruta donde guardaremos el archivo
				=============================================*/

				// Use absolute path to avoid path resolution issues
				$baseDir = dirname(__DIR__);
				$filesDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "files" . DIRECTORY_SEPARATOR;
				
				// Ensure directory exists
				if(!file_exists($filesDir)){
					if(!@mkdir($filesDir, 0777, true)){
						$response = array(
							"status" => 500,
							"error" => "Error creating files directory. Check write permissions."
						);
						ob_clean();
						echo json_encode($response);
						ob_end_flush();
						return;
					}
					// Set permissions after creation
					@chmod($filesDir, 0777);
				}
				
				// Check if directory is writable
				if(!is_writable($filesDir)){
					// Try to change permissions
					@chmod($filesDir, 0777);
					
					// Also try to change permissions of parent directories if needed
					$parentDir = dirname($filesDir);
					if(!is_writable($parentDir)){
						@chmod($parentDir, 0777);
					}
					
					if(!is_writable($filesDir)){
						// Get current permissions for better error message
						$perms = substr(sprintf('%o', fileperms($filesDir)), -4);
						$response = array(
							"status" => 500,
							"error" => "Files directory is not writable. Current permissions: " . $perms . ". Please run in terminal: chmod -R 777 " . $filesDir . " or contact your system administrator."
						);
						ob_clean();
						echo json_encode($response);
						ob_end_flush();
						return;
					}
				}

				$path = $filesDir . $fileName;

				/*=============================================
				Movemos archivo temporal a esa ruta
				=============================================*/

				if(move_uploaded_file($this->file["tmp_name"], $path)){

					/*=============================================
					Subimos información de archivos a la base de datos
					=============================================*/

					$url = "files?token=".$this->token."&table=admins&suffix=admin";
					$method = "POST";
					
					// Build the link_file - save only relative path from cms directory (without domain)
					// This makes it portable when migrating to different domains
					// Format: views/assets/files/filename.jpg
					
					// Get relative path from cms directory to the file
					$relativePath = str_replace($baseDir, "", $path);
					$relativePath = str_replace("\\", "/", $relativePath); // Normalize slashes
					$relativePath = ltrim($relativePath, "/"); // Remove leading slash
					
					// Ensure the path starts with views/assets/files
					if(strpos($relativePath, "views/assets/files/") !== 0){
						$relativePath = "views/assets/files/" . basename($path);
					}
					
					// Save only the relative path (without domain base)
					$linkFile = $relativePath;
					
					$fields = array(

						"id_folder_file" => $this->folder,
						"extension_file" => end($extension),
						"name_file" => str_replace(".".end($extension), "", $this->file["name"]),
						"type_file" => $this->file["type"],
						"size_file" => $this->file["size"],
						"link_file" => $linkFile,
						"date_created_file" => date("Y-m-d")
					);

					$uploadData = CurlController::request($url,$method,$fields);

					// Check if upload data request was successful
					if(!is_object($uploadData) || !isset($uploadData->status)){
						$response = array(
							"status" => 500,
							"error" => "Error uploading file information: Invalid response from API"
						);
						ob_clean();
						echo json_encode($response);
						ob_end_flush();
						return;
					}

					if($uploadData->status == 200){

						/*=============================================
						Devolvemos la información a javascript
						=============================================*/

						// Handle different response structures from API
						// According to the log, the API returns:
						// {'status' => 200, 'results' => [{'lastId': '2', 'comment': 'The process was successful'}]}
						$idFile = null;
						
						// Check if results is an array with the created record (most common case)
						if(isset($uploadData->results) && is_array($uploadData->results) && !empty($uploadData->results)){
							// Get the first result (the created record)
							$createdRecord = $uploadData->results[0];
							if(is_object($createdRecord)){
								// Try lastId first (this is what the API actually returns)
								if(isset($createdRecord->lastId)){
									$idFile = $createdRecord->lastId;
								} elseif(isset($createdRecord->{"lastId"})){
									$idFile = $createdRecord->{"lastId"};
								} elseif(isset($createdRecord->id_file)){
									$idFile = $createdRecord->id_file;
								} elseif(isset($createdRecord->{"id_file"})){
									$idFile = $createdRecord->{"id_file"};
								}
							} elseif(is_array($createdRecord)){
								// Try lastId first
								if(isset($createdRecord["lastId"])){
									$idFile = $createdRecord["lastId"];
								} elseif(isset($createdRecord['lastId'])){
									$idFile = $createdRecord['lastId'];
								} elseif(isset($createdRecord["id_file"])){
									$idFile = $createdRecord["id_file"];
								} elseif(isset($createdRecord['id_file'])){
									$idFile = $createdRecord['id_file'];
								}
							}
						}
						
						// Check if results is an object with lastId property (fallback)
						if($idFile === null && isset($uploadData->results) && is_object($uploadData->results)){
							if(isset($uploadData->results->lastId)){
								$idFile = $uploadData->results->lastId;
							} elseif(isset($uploadData->results->{"lastId"})){
								$idFile = $uploadData->results->{"lastId"};
							}
						}
						
						// Check if lastId is directly in the response (another fallback)
						if($idFile === null && isset($uploadData->lastId)){
							$idFile = $uploadData->lastId;
						}
						
						// If we still don't have an ID, log and return error
						if($idFile === null){
							// Log the actual structure for debugging
							error_log("Files API Response Structure: " . json_encode($uploadData, JSON_PRETTY_PRINT));
							$response = array(
								"status" => 500,
								"error" => "Error uploading file information: Could not extract file ID from API response. Check server logs for details."
							);
							ob_clean();
							echo json_encode($response);
							ob_end_flush();
							return;
						}

						// Build full URL for JavaScript response (but save only relative path in DB)
						$fullUrl = TemplateController::buildFileUrl($fields["link_file"]);

						$response = array(

							"status" => 200,
							"id_file" => $idFile,
							"link" => $fullUrl, // Full URL for JavaScript
							"reduce_link" => TemplateController::reduceText($fields["link_file"],35)."...", // Show relative path in display
							"date" => $fields["date_created_file"].", ".date("H:m:s")

						);

						ob_clean();
						echo json_encode($response);
						ob_end_flush();

					}else{

						/*=============================================
						Error uploading to database
						=============================================*/

						$response = array(
							"status" => 500,
							"error" => "Error uploading file information to database"
						);

						ob_clean();
						echo json_encode($response);
						ob_end_flush();
					}

				}else{

					/*=============================================
					Error moving file
					=============================================*/

					$response = array(
						"status" => 500,
						"error" => "Error moving uploaded file"
					);

					ob_clean();
					echo json_encode($response);
					ob_end_flush();
				}

			}else{

				/*=============================================
				Folder not found
				=============================================*/

				$response = array(
					"status" => 404,
					"error" => "Folder not found"
				);

				ob_clean();
				echo json_encode($response);
				ob_end_flush();
			}
		
		}else{

			/*=============================================
			Error getting folder information
			=============================================*/

			$response = array(
				"status" => 500,
				"error" => "Error getting folder information"
			);

			ob_clean();
			echo json_encode($response);
			ob_end_flush();
		}
	
	}

	/*=============================================
	Calcular el peso total de archivos de un folder
	=============================================*/

	public $idFolder;

	public function updateServer(){

		/*=============================================
		Traer todos los archivos vinculados al folder
		=============================================*/

		$url = "files?linkTo=id_folder_file&equalTo=".$this->idFolder."&select=size_file";
		$method = "GET";
		$fields = array();

		$files = CurlController::request($url,$method,$fields);

		if($files->status == 200){

			$files = $files->results;
			$totalSize = 0;
			$countFiles = 0;

			foreach ($files as $key => $value) {
				
				$totalSize += $value->size_file;
				$countFiles++;

				if($countFiles == count($files)){

					/*=============================================
					Actualizar Folders
					=============================================*/

					$url = 	"folders?id=".$this->idFolder."&nameId=id_folder&token=".$this->token."&table=admins&suffix=admin";
					$method = "PUT";
					$fields = "total_folder=".$totalSize;

					$folders = CurlController::request($url,$method,$fields);

					if($folders->status == 200){

						echo $folders->status;
					}
				}
			}
		}


	}

	/*=============================================
	Eliminar archivo del servidor y de la BD
	=============================================*/

	public $idFileDelete;
	public $idFolderDelete;

	public function deleteFile(){

		/*=============================================
		Traer la data del archivo
		=============================================*/

		$url = "files?linkTo=id_file&equalTo=".$this->idFileDelete;
		$method = "GET";
		$fields = array();

		$getFile = CurlController::request($url, $method, $fields);

		if($getFile->status == 200){

			$getFile = $getFile->results[0];

		}

		/*=============================================
		Traer la data del folder
		=============================================*/

		$url = "folders?linkTo=id_folder&equalTo=".$this->idFolderDelete;

		$getFolder = CurlController::request($url, $method, $fields);

		if($getFolder->status == 200){

			$getFolder = $getFolder->results[0];

		}

		/*=============================================
		Eliminando archivo del servidor local
		=============================================*/

		if($this->idFolderDelete == 1){

			/*=============================================
			Borrar archivo del servidor
			=============================================*/
			unlink(str_replace($_SERVER["HTTP_ORIGIN"],"..",$getFile->link_file));
			
		}

		/*=============================================
		Actualizar capacidad total del servidor
		=============================================*/

		$url = "folders?id=".$this->idFolderDelete."&nameId=id_folder&token=".$this->token."&table=admins&suffix=admin";
		$method = "PUT";
		$fields = "total_folder=".$getFolder->total_folder-$getFile->size_file;

		$updateFolder = CurlController::request($url,$method,$fields);

		/*=============================================
		Eliminar registro de la base de datos
		=============================================*/

		$url = "files?id=".$this->idFileDelete."&nameId=id_file&token=".$this->token."&table=admins&suffix=admin";
		$method = "DELETE";
		$fields = array();

		$deleteFile = CurlController::request($url,$method,$fields);

		if($updateFolder->status == 200 && $deleteFile->status == 200){

			echo $deleteFile->status;
		}

	}

	/*=============================================
	Actualizar el nombre del Archivo
	=============================================*/

	public $name;
	public $idFile;

	public function updateName(){


		$url = "files?id=".$this->idFile."&nameId=id_file&token=".$this->token."&table=admins&suffix=admin";
		$method = "PUT";
		$fields = "name_file=".$this->name;

		$update = CurlController::request($url,$method,$fields);

		if($update->status == 200){

			echo $update->status;
		} 
	}

	/*=============================================
	Función para cargar archivos
	=============================================*/

	public $search;
	public $sortBy;
	public $filterBy;
	public $arrayFolders;
	public $startAt;
	public $endAt;

	public function loadFiles(){

		$htmlList = "";
		$htmlGrid = "";
		$load = array();

		if(count(json_decode($this->arrayFolders)) == 5){
			
			if($this->filterBy == "ALL"){
		
				if(!empty($this->search)){

					$url = "relations?rel=files,folders&type=file,folder&linkTo=name_file&search=".urlencode($this->search)."&orderBy=".explode("-",$this->sortBy)[0]."&orderMode=".explode("-",$this->sortBy)[1]."&startAt=".$this->startAt."&endAt=".$this->endAt;

				
				}else{

					$url = "relations?rel=files,folders&type=file,folder&orderBy=".explode("-",$this->sortBy)[0]."&orderMode=".explode("-",$this->sortBy)[1]."&startAt=".$this->startAt."&endAt=".$this->endAt;

				}

			}else{

				if(!empty($this->search)){

					$url = "relations?rel=files,folders&type=file,folder&linkTo=name_file,type_file&search=".urlencode($this->search).",".urlencode($this->filterBy)."&orderBy=".explode("-",$this->sortBy)[0]."&orderMode=".explode("-",$this->sortBy)[1]."&startAt=".$this->startAt."&endAt=".$this->endAt;

				
				}else{

					$url = "relations?rel=files,folders&type=file,folder&linkTo=type_file&equalTo=".urlencode($this->filterBy)."&orderBy=".explode("-",$this->sortBy)[0]."&orderMode=".explode("-",$this->sortBy)[1]."&startAt=".$this->startAt."&endAt=".$this->endAt;

				}
			}

			$method = "GET";
			$fields = array();

			$loadFolders = CurlController::request($url,$method,$fields);

			if($loadFolders->status == 200){

				$load = $loadFolders->results;

			}

		}else{

			foreach (json_decode($this->arrayFolders) as $key => $value) {
				
				if($this->filterBy == "ALL"){
		
					if(!empty($this->search)){

						$url = "relations?rel=files,folders&type=file,folder&linkTo=name_file,id_folder&search=".urlencode($this->search).",".$value."&orderBy=".explode("-",$this->sortBy)[0]."&orderMode=".explode("-",$this->sortBy)[1]."&startAt=".$this->startAt."&endAt=".$this->endAt;

					
					}else{

						$url = "relations?rel=files,folders&type=file,folder&linkTo=id_folder&equalTo=".$value."&orderBy=".explode("-",$this->sortBy)[0]."&orderMode=".explode("-",$this->sortBy)[1]."&startAt=".$this->startAt."&endAt=".$this->endAt;

					}

				}else{

					if(!empty($this->search)){

						$url = "relations?rel=files,folders&type=file,folder&linkTo=name_file,type_file,id_folder&search=".urlencode($this->search).",".urlencode($this->filterBy).",".$value."&orderBy=".explode("-",$this->sortBy)[0]."&orderMode=".explode("-",$this->sortBy)[1]."&startAt=".$this->startAt."&endAt=".$this->endAt;

					
					}else{

						$url = "relations?rel=files,folders&type=file,folder&linkTo=type_file,id_folder&equalTo=".urlencode($this->filterBy).",".$value."&orderBy=".explode("-",$this->sortBy)[0]."&orderMode=".explode("-",$this->sortBy)[1]."&startAt=".$this->startAt."&endAt=".$this->endAt;

					}
				}

				$method = "GET";
				$fields = array();

				$loadFolders = CurlController::request($url,$method,$fields);

				if($loadFolders->status == 200){

					$load = array_merge($load, $loadFolders->results);

				}


			}

		}

		$countFiles = 0;

		if(!empty($load)){

			foreach ($load as $key => $value) {

				$countFiles++;

				/*=============================================
				Organizar la vista de la lista
				=============================================*/

				$pathList = TemplateController::returnThumbnailList($value);

				$htmlList .= '<tr style="height:100px">

						<td>
							'.$pathList.'
						</td>

						<td class="align-middle">
							<div class="input-group">
								<input type="text" class="form-control changeName" value="'.$value->name_file.'" idFile="'.$value->id_file.'">
								<span class="input-group-text">.'.$value->extension_file.'</span>
							</div>
						</td>

						<td class="align-middle">'.number_format($value->size_file/1000000,2).' MB</td>

						<td class="align-middle">
							<span class="badge bg-dark rounded px-3 py-2 text-white">'.$value->name_folder.'</span>
						</td>

						<td class="align-middle">
							<a href="'.TemplateController::buildFileUrl($value->link_file).'" target="_blank">
								'.TemplateController::reduceText($value->link_file,35).'...
								<i class="bi bi-box-arrow-up-right ps-2 btn"></i>
							</a>
						</td>

						<td class="align-middle">'.$value->date_updated_file.'</td>

						<td class="align-middle">
						  <svg class="bi bi-copy copyLink" copy="'.TemplateController::buildFileUrl($value->link_file).'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="cursor:pointer">
							  <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>
							</svg>
						  <i class="bi bi-trash ps-2 btn deleteFile" idFile="'.$value->id_file.'" idFolder="'.$value->id_folder.'" mode="list"></i>
						</td>

					</tr>';

				/*=============================================
				Organizar la vista de la cuadrícula
				=============================================*/

				$pathGrid = TemplateController::returnThumbnailGrid($value);

				$htmlGrid .= '<div class="col">
	 			
				 			<div class="card rounded p-3 border-0 shadow my-3">
				 				
				 				<div class="card-header bg-white border-0 p-0">
				 					
				 					<div class="d-flex justify-content-between mb-2">
				 						
				 						<div class="bg-white">
				 							<a href="'.TemplateController::buildFileUrl($value->link_file).'" target="_blank">
											<i class="bi bi-box-arrow-up-right ps-2 btn p-0"></i>
											</a>
										</div>

										<div class="bg-white m-0">
											<svg  class="bi bi-copy copyLink" copy="'.TemplateController::buildFileUrl($value->link_file).'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="cursor:pointer">
												<path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>
											</svg>
											<i class="bi bi-trash p-0 ps-2 btn deleteFile" idFile="'.$value->id_file.'" idFolder="'.$value->id_folder.'" mode="grid"></i>
										</div>

				 					</div>
				 				</div>

				 				'.$pathGrid.'

				 				<div class="card-body p-1">
				 					
				 					<p class="card-text">
				 						
				 						<div class="input-group">
											<input type="text" class="form-control changeName" value="'.$value->name_file.'" idFile="'.$value->id_file.'">
											<span class="input-group-text">.'.$value->extension_file.'</span>
										</div>

										<div class="d-flex justify-content-between mt-3">

											<div class="bg-white">
												<p class="small mt-1">'.number_format($value->size_file/1000000,2).' MB</p>
											</div>

											<div class="bg-white m-0">
												<span class="badge bg-dark border rounded px-3 py-2 text-white">'.$value->name_folder.'</span>
											</div>
										</div>

										<h6 class="float-end my-0 py-0">
											<small>'.$value->date_updated_file.'</small>
										</h6>

				 					</p>

				 				</div>

				 			</div>

				 		</div>';

				/*=============================================
				Finaliza el recorrido Foreach
				=============================================*/

				if($countFiles == count($load)){

					$response = array(

						"htmlList" => $htmlList,
						"htmlGrid" => $htmlGrid

					);

					ob_clean();
					echo json_encode($response);
					ob_end_flush();

				}
			}

		}else{

			$response = array(

				"htmlList" => $htmlList,
				"htmlGrid" => $htmlGrid

			);

			ob_clean();
			echo json_encode($response);
			ob_end_flush();

		}

	}


}

/*=============================================
Subir Archivos a los Servidores
=============================================*/

if(isset($_FILES["file"])){

	try {
		$ajax = new FilesController();
		$ajax -> file = $_FILES["file"];
		$ajax -> folder  = $_POST["folder"];
		$ajax -> token = $_POST["token"];
		$ajax -> ajaxUploadFiles();
	} catch(Exception $e) {
		// Catch any unexpected errors and return JSON
		$response = array(
			"status" => 500,
			"error" => "Error uploading file: " . $e->getMessage()
		);
		ob_clean();
		echo json_encode($response);
		ob_end_flush();
	}

}

/*=============================================
Calcular el peso total de archivos de un folder
=============================================*/

if(isset($_POST["idFolder"])){

	$ajax = new FilesController();
	$ajax -> idFolder  = $_POST["idFolder"];
	$ajax -> token = $_POST["token"];
	$ajax -> updateServer();

}

/*=============================================
Eliminar archivo del servidor y de la BD
=============================================*/

if(isset($_POST["idFolderDelete"])){

	$ajax = new FilesController();
	$ajax -> idFileDelete  = $_POST["idFileDelete"];
	$ajax -> idFolderDelete  = $_POST["idFolderDelete"];
	$ajax -> token = $_POST["token"];
	$ajax -> deleteFile();

}

/*=============================================
Actualizar el nombre del Archivo
=============================================*/

if(isset($_POST["name"])){

	$ajax = new FilesController();
	$ajax -> name  = $_POST["name"];
	$ajax -> idFile  = $_POST["idFile"];
	$ajax -> token = $_POST["token"];
	$ajax -> updateName();

}

/*=============================================
Función para cargar archivos
=============================================*/

if(isset($_POST["search"])){

	$ajax = new FilesController();
	$ajax -> search = $_POST["search"];
	$ajax -> sortBy = $_POST["sortBy"];
	$ajax -> filterBy = $_POST["filterBy"];
	$ajax -> arrayFolders = $_POST["arrayFolders"];
	$ajax -> startAt = $_POST["startAt"];
	$ajax -> endAt = $_POST["endAt"];
	$ajax -> loadFiles();

}


