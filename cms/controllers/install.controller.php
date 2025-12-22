<?php 

require_once __DIR__ . '/path-updater.controller.php';

class InstallController{

	// Load configuration
	public static function getConfig(){
		// Try CMS config first
		$configPath = __DIR__ . '/../config.php';
		if(file_exists($configPath)){
			$config = require $configPath;
			if(is_array($config)){
				return $config;
			}
		}
		$examplePath = __DIR__ . '/../config.example.php';
		if(file_exists($examplePath)){
			$config = require $examplePath;
			if(is_array($config)){
				return $config;
			}
		}
		// Fallback to API config
		$apiConfigPath = __DIR__ . '/../../api/config.php';
		if(file_exists($apiConfigPath)){
			$config = require $apiConfigPath;
			if(is_array($config)){
				return $config;
			}
		}
		// Last resort: defaults
		return [
			'database' => [
				'host' => 'localhost',
				'name' => 'chatcenter',
				'user' => 'root',
				'pass' => '',
				'charset' => 'utf8mb4'
			]
		];
	}

	// Database configuration
	static public function infoDatabase(){
		$config = self::getConfig();
		$dbConfig = $config['database'] ?? [];
		return [
			"database" => $dbConfig['name'],
			"user" => $dbConfig['user'],
			"pass" => $dbConfig['pass']
		];
	}

	// Database connection
	static public function connect(){
		$config = self::getConfig();
		$dbConfig = $config['database'] ?? [];
		
		// If no charset is defined, use utf8mb4 as default
		$charset = $dbConfig['charset'] ?? 'utf8mb4';
		
		try{
			// First try to connect without specifying database (to create it if it doesn't exist)
			$link = new PDO(
				"mysql:host=".($dbConfig['host']).";charset=".$charset,
				$dbConfig['user'],
				$dbConfig['pass']
			);
			
			// Try to use the database
			$link->exec("USE `".($dbConfig['name'])."`");
			$link->exec("set names ".$charset);

		}catch(PDOException $e){
			// If it fails, try with the database directly
			try {
				$link = new PDO(
					"mysql:host=".($dbConfig['host']).";dbname=".($dbConfig['name']).";charset=".$charset,
					$dbConfig['user'],
					$dbConfig['pass']
				);
				$link->exec("set names ".$charset);
			} catch(PDOException $e2) {
				die("Error: ".$e2->getMessage());
			}
		}

		return $link;

	}

	// System installation

	public function install(){

		if(isset($_POST["email_admin"])){

			// Get database configuration from config files (not from form)
			$config = self::getConfig();
			$dbConfig = $config['database'] ?? [];
			
			// Validate that database configuration exists
			if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
				$errorMsgEscaped = json_encode("La configuración de base de datos no está completa en config.php. Por favor, configure la base de datos en cms/config.php o cms/config.example.php antes de instalar.", JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
				echo '<script>
					fncMatPreloader("off");
					fncFormatInputs();
					fncSweetAlert("error", "Error de configuración", ' . $errorMsgEscaped . ');
				</script>';
				return;
			}
			
			// Detect domain (for information purposes)
			$domainInfo = PathUpdaterController::detectDomain();
			
			// Try to update API URLs only if files are writable (non-blocking)
			// If files are not writable, we continue anyway - user can update URLs manually
			$cmsConfigResult = PathUpdaterController::updateCmsConfigUrlsOnly($domainInfo);
			$apiConfigResult = PathUpdaterController::updateApiConfigUrlsOnly($domainInfo);
			
			// Log warnings if URLs couldn't be updated, but don't stop installation
			if (!$cmsConfigResult['success']) {
				error_log("Warning: Could not update CMS URLs: " . $cmsConfigResult['message']);
			}
			if (!$apiConfigResult['success']) {
				error_log("Warning: Could not update API URLs: " . $apiConfigResult['message']);
			}
			
			// Clear any cached config by forcing a reload
			clearstatcache();

			// Check if database already exists and has data (imported from package)
			// Try to detect old domain from database before creating tables
			$oldDomain = null;
			try {
				$oldDomain = PathUpdaterController::detectOldDomainFromDatabase($dbConfig);
			} catch (Exception $e) {
				// Database might not exist yet, that's okay - will check again after installation
			}
			
			$newDomain = $domainInfo['base_url'];

			// Check if tables already exist before starting installation
			$requiredTables = ['admins', 'pages', 'modules', 'columns', 'folders', 'files', 'activity_logs'];
			$existingTables = [];

			foreach($requiredTables as $table){
				if(InstallController::getTable($table) == 200){
					$existingTables[] = $table;
				}
			}

			// If any tables exist, show error and stop installation
			if(!empty($existingTables)){
				$tablesList = implode(', ', $existingTables);
				$dropCommand = "DROP TABLE IF EXISTS " . implode(', ', $existingTables) . ";";
				$message = "Las siguientes tablas ya existen en la base de datos: <strong>" . $tablesList . "</strong><br><br>" .
						   "Para realizar una instalación limpia, debe eliminar estas tablas primero.<br><br>" .
						   "Puede hacerlo desde su cliente de base de datos (phpMyAdmin, MySQL Workbench, etc.) o ejecutando el siguiente comando SQL:<br><br>" .
						   "<code style='background: #f4f4f4; padding: 5px; border-radius: 3px;'>" . htmlspecialchars($dropCommand) . "</code>";
				
				$messageEscaped = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
				echo '<script>
					fncMatPreloader("off");
					fncFormatInputs();
					fncSweetAlert("error", 
						"Tablas ya existen en la base de datos", 
						' . $messageEscaped . '
					);
				</script>';
				return;
			}

			echo '<script>
					fncMatPreloader("on");
					fncSweetAlert("loading", "Instalando...", "");
				</script>';
			
			// Create admins table
			
			$sqlAdmins = "CREATE TABLE admins ( 
				id_admin INT NOT NULL AUTO_INCREMENT,
				email_admin TEXT NULL DEFAULT NULL,
				password_admin TEXT NULL DEFAULT NULL, 
				rol_admin TEXT NULL DEFAULT NULL,
				permissions_admin TEXT NULL DEFAULT NULL, 
				token_admin TEXT NULL DEFAULT NULL,
				token_exp_admin TEXT NULL DEFAULT NULL,
				status_admin INT NULL DEFAULT '1',
				title_admin TEXT NULL DEFAULT NULL,  
				symbol_admin TEXT NULL DEFAULT NULL,
				font_admin TEXT NULL DEFAULT NULL,
				color_admin TEXT NULL DEFAULT NULL,
				back_admin TEXT NULL DEFAULT NULL, 
				scode_admin TEXT NULL DEFAULT NULL, 
				chatgpt_admin TEXT NULL DEFAULT NULL, 
				date_created_admin DATE NULL DEFAULT NULL,
				date_updated_admin TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id_admin))";

			$stmtAdmins = InstallController::connect()->prepare($sqlAdmins);

			// Create pages table
			
			$sqlPages = "CREATE TABLE pages ( 
				id_page INT NOT NULL AUTO_INCREMENT,
				title_page TEXT NULL DEFAULT NULL,
				url_page TEXT NULL DEFAULT NULL,
				icon_page TEXT NULL DEFAULT NULL,
				type_page TEXT NULL DEFAULT NULL,
				order_page INT NULL DEFAULT '1',
				date_created_page DATE NULL DEFAULT NULL,
				date_updated_page TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id_page))";

			$stmtPages = InstallController::connect()->prepare($sqlPages);

			// Create modules table
			
			$sqlModules = "CREATE TABLE modules ( 
				id_module INT NOT NULL AUTO_INCREMENT,
				id_page_module INT NULL DEFAULT '0',
				type_module TEXT NULL DEFAULT NULL,
				title_module TEXT NULL DEFAULT NULL,
				suffix_module TEXT NULL DEFAULT NULL,
				content_module TEXT NULL DEFAULT NULL,
				width_module INT NULL DEFAULT '100',
				editable_module INT NULL DEFAULT '1',
				date_created_module DATE NULL DEFAULT NULL,
				date_updated_module TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id_module))";

			$stmtModules = InstallController::connect()->prepare($sqlModules);

			// Create columns table
			
			$sqlColumns = "CREATE TABLE columns ( 
				id_column INT NOT NULL AUTO_INCREMENT,
				id_module_column INT NULL DEFAULT '0',
				title_column TEXT NULL DEFAULT NULL,
				alias_column TEXT NULL DEFAULT NULL,
				type_column TEXT NULL DEFAULT NULL,
				matrix_column TEXT NULL DEFAULT NULL,
				visible_column INT NULL DEFAULT '1',
				date_created_column DATE NULL DEFAULT NULL,
				date_updated_column TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id_column))";

			$stmtColumns = InstallController::connect()->prepare($sqlColumns);

			// Create folders table

			$sqlFolders = "CREATE TABLE folders ( 
				id_folder INT NOT NULL AUTO_INCREMENT,
				name_folder TEXT NULL DEFAULT NULL,
				size_folder TEXT NULL DEFAULT NULL,
				total_folder DOUBLE NULL DEFAULT '0',
				max_upload_folder TEXT NULL DEFAULT NULL,
				url_folder TEXT NULL DEFAULT NULL,
				keys_folder TEXT NULL DEFAULT NULL,
				date_created_folder DATE NULL DEFAULT NULL,
				date_updated_folder TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id_folder))";

			$stmtFolders = InstallController::connect()->prepare($sqlFolders);

			// Create files table

			$sqlFiles = "CREATE TABLE files ( 
				id_file INT NOT NULL AUTO_INCREMENT,
				id_folder_file INT NULL DEFAULT '0',
				name_file TEXT NULL DEFAULT NULL,
				extension_file TEXT NULL DEFAULT NULL,
				type_file TEXT NULL DEFAULT NULL,
				size_file DOUBLE NULL DEFAULT '0',
				link_file TEXT NULL DEFAULT NULL,
				thumbnail_vimeo_file TEXT NULL DEFAULT NULL,
				id_mailchimp_file TEXT NULL DEFAULT NULL,
				date_created_file DATE NULL DEFAULT NULL,
				date_updated_file TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id_file))";

			$stmtFiles = InstallController::connect()->prepare($sqlFiles);

			// Create activity_logs table
			
			$sqlActivityLogs = "CREATE TABLE activity_logs ( 
				id_log INT NOT NULL AUTO_INCREMENT,
				action_log TEXT NULL DEFAULT NULL,
				entity_log TEXT NULL DEFAULT NULL,
				entity_id_log INT NULL DEFAULT NULL,
				description_log TEXT NULL DEFAULT NULL,
				admin_id_log INT NULL DEFAULT NULL,
				ip_address_log TEXT NULL DEFAULT NULL,
				user_agent_log TEXT NULL DEFAULT NULL,
				date_created_log DATETIME NULL DEFAULT NULL,
				PRIMARY KEY (id_log),
				INDEX idx_admin_id (admin_id_log),
				INDEX idx_entity (entity_log(50)),
				INDEX idx_action (action_log(50)),
				INDEX idx_date_created (date_created_log)
			)";

			$stmtActivityLogs = InstallController::connect()->prepare($sqlActivityLogs);

			// Try to create tables with error handling
			$tablesCreated = false;
			$errorMessage = '';

			try {
				if($stmtAdmins->execute() && 
				   $stmtPages->execute() &&
				   $stmtModules->execute() &&
				   $stmtColumns->execute() &&
				   $stmtFolders->execute() &&
				   $stmtFiles->execute() &&
				   $stmtActivityLogs->execute()
				){
					$tablesCreated = true;
				}
			} catch(PDOException $e) {
				$errorMessage = $e->getMessage();
				$tablesCreated = false;
			}

			if($tablesCreated){

				// Wait for database and information_schema to be ready
				// Try up to 10 times with increasing delays
				$maxRetries = 10;
				$tablesReady = false;
				$requiredTables = ['pages', 'admins'];
				$requiredPagesColumns = ['id_page', 'title_page', 'url_page', 'icon_page', 'type_page', 'order_page', 'date_created_page'];
				
				for($i = 0; $i < $maxRetries; $i++){
					$delay = ($i + 1) * 500000; // 0.5s, 1s, 1.5s, 2s, 2.5s, etc.
					if($i > 0){
						usleep($delay);
					}
					
					// Check if tables are accessible via information_schema
					$allReady = true;
					foreach($requiredTables as $table){
						if(InstallController::getTable($table) != 200){
							$allReady = false;
							break;
						}
					}
					
					// Also verify that required columns exist in pages table
					if($allReady){
						$database = InstallController::infoDatabase()["database"];
						$columnsCheck = InstallController::connect()->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = '$database' AND table_name = 'pages' AND COLUMN_NAME IN ('" . implode("','", $requiredPagesColumns) . "')")->fetchAll(PDO::FETCH_COLUMN);
						
						if(count($columnsCheck) < count($requiredPagesColumns)){
							$allReady = false;
						}
					}
					
					if($allReady){
						$tablesReady = true;
						break;
					}
				}
				
				if(!$tablesReady){
					$errorMessage = "Las tablas se crearon pero no están completamente sincronizadas con information_schema. Por favor, espere unos segundos e intente nuevamente.<br><br>Si el problema persiste, verifique que la base de datos esté funcionando correctamente.";
					$errorMessageEscaped = json_encode($errorMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
					echo '<script>
						fncMatPreloader("off");
						fncFormatInputs();
						fncSweetAlert("error","Error de sincronización",' . $errorMessageEscaped . ');
					</script>';
					return;
				}

				// Create super admin

				$url = "admins?register=true&suffix=admin";
				$method = "POST";
				$fields = array(
					"email_admin" => trim($_POST["email_admin"]),
					"password_admin" => trim($_POST["password_admin"]),
					"rol_admin" => "superadmin",
					"permissions_admin" => '{"todo":"on"}',		
					"title_admin" => trim($_POST["title_admin"]),
					"symbol_admin" => trim($_POST["symbol_admin"]),
					"font_admin" => trim($_POST["font_admin"]),
					"color_admin" => trim($_POST["color_admin"]),
					"back_admin" => trim($_POST["back_admin"]),
					"date_created_admin" => date("Y-m-d")
				);

				$register = CurlController::request($url,$method,$fields);

				// Create home page

				$url = "pages?token=no&except=id_page";
				$method = "POST";
				$fields = array(
					"title_page" => "Inicio",
					"url_page" => "inicio",
					"icon_page" => "bi bi-house-door-fill",
					"type_page" => "modules",
					"order_page" => 1,
					"date_created_page" => date("Y-m-d")
				);

				$homePage = CurlController::request($url,$method,$fields);
				
				// Get API config for error message (force reload)
				clearstatcache();
				$apiConfig = self::getConfig();
				$apiBaseUrl = $apiConfig['api']['base_url'] ?? 'http://localhost/chatcenter/api/';
				
				// Debug: Log full response for troubleshooting (escape for HTML/JS)
				$fullResponse = json_encode($homePage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
				
				// Check if home page creation failed
				if(!is_object($homePage)){
					$errorDetails = 'La respuesta de la API no es un objeto válido. Tipo recibido: ' . gettype($homePage);
					if(is_string($homePage)){
						$errorDetails .= '. Contenido: ' . htmlspecialchars($homePage, ENT_QUOTES, 'UTF-8');
					}
				} elseif(!isset($homePage->status)){
					$errorDetails = 'La respuesta de la API no contiene el campo "status".';
				} elseif((int)$homePage->status !== 200){
					$errorDetails = 'El status de la respuesta es ' . (int)$homePage->status . ' (se esperaba 200).';
					if(isset($homePage->message)){
						$errorDetails .= ' Mensaje: ' . htmlspecialchars($homePage->message, ENT_QUOTES, 'UTF-8');
					}
					if(isset($homePage->results)){
						if(is_string($homePage->results)){
							$errorDetails .= ' Error: ' . htmlspecialchars($homePage->results, ENT_QUOTES, 'UTF-8');
						} elseif(is_array($homePage->results) && isset($homePage->results[0]) && isset($homePage->results[2])){
							$errorDetails .= ' Error de base de datos: ' . htmlspecialchars($homePage->results[2], ENT_QUOTES, 'UTF-8') . ' (Código: ' . $homePage->results[0] . ')';
						} else {
							$errorDetails .= ' Resultados: ' . htmlspecialchars(json_encode($homePage->results, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
						}
					}
				} else {
					// Success case - check if we can extract the page ID
					$homePageId = null;
					if(isset($homePage->results)){
						if(is_object($homePage->results) && isset($homePage->results->lastId)){
							$homePageId = $homePage->results->lastId;
						} elseif(is_object($homePage->results) && isset($homePage->results->id_page)){
							$homePageId = $homePage->results->id_page;
						} elseif(is_array($homePage->results) && isset($homePage->results[0])){
							if(is_object($homePage->results[0]) && isset($homePage->results[0]->lastId)){
								$homePageId = $homePage->results[0]->lastId;
							} elseif(is_object($homePage->results[0]) && isset($homePage->results[0]->id_page)){
								$homePageId = $homePage->results[0]->id_page;
							} elseif(is_array($homePage->results[0]) && isset($homePage->results[0]['lastId'])){
								$homePageId = $homePage->results[0]['lastId'];
							} elseif(is_array($homePage->results[0]) && isset($homePage->results[0]['id_page'])){
								$homePageId = $homePage->results[0]['id_page'];
							}
						}
					}
					
					// If we couldn't extract ID but status is 200, it's still a success
					// The page was created even if we can't get the ID
					if($homePageId === null){
						error_log("Warning: Home page created but couldn't extract ID from response");
					}
					
					$errorDetails = '';
				}
				
				// If there's an error, show detailed message
				if(!empty($errorDetails)){
					$errorMessage = "No se pudo crear la página de inicio.<br><br>";
					$errorMessage .= "<strong>Detalles del error:</strong><br>" . htmlspecialchars($errorDetails, ENT_QUOTES, 'UTF-8') . "<br><br>";
					$errorMessage .= "<strong>Respuesta completa de la API:</strong><br>";
					// Escape the JSON response properly for HTML
					$fullResponseEscaped = htmlspecialchars($fullResponse, ENT_QUOTES, 'UTF-8');
					$errorMessage .= "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px; font-size: 0.85rem; max-height: 200px; overflow-y: auto;'>" . $fullResponseEscaped . "</pre><br>";
					$errorMessage .= "<strong>Verifique que:</strong><br>";
					$errorMessage .= "1. La API esté accesible en: <code>" . htmlspecialchars($apiBaseUrl, ENT_QUOTES, 'UTF-8') . "pages</code><br>";
					$errorMessage .= "2. La base de datos esté configurada correctamente<br>";
					$errorMessage .= "3. Las tablas se hayan creado correctamente<br>";
					$errorMessage .= "4. La tabla 'pages' tenga las columnas correctas<br>";
					$errorMessage .= "5. No haya conflictos con registros existentes";
					
					// Escape properly for JavaScript - use JSON_HEX flags to prevent syntax errors
					$errorMessageEscaped = json_encode($errorMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
					
					echo '<script>
						fncMatPreloader("off");
						fncFormatInputs();
						fncSweetAlert("error","Error al crear página de inicio",' . $errorMessageEscaped . ');
					</script>';
					return;
				}

				// Create admins page

				$url = "pages?token=no&except=id_page";
				$method = "POST";
				$fields = array(
					"title_page" => "Admins",
					"url_page" => "admins",
					"icon_page" => "bi bi-person-fill-gear",
					"type_page" => "modules",
					"order_page" => 2,
					"date_created_page" => date("Y-m-d")
				);

				$adminPage = CurlController::request($url,$method,$fields);

				$adminPageId = null;
				$errorDetails = '';

				// Get API config for error message (force reload)
				clearstatcache();
				$apiConfig = self::getConfig();
				$apiBaseUrl = $apiConfig['api']['base_url'] ?? 'http://localhost/chatcenter/api/';

				// Debug: Log full response for troubleshooting (escape for HTML/JS)
				$fullResponse = json_encode($adminPage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

				// Debug: Check response structure and extract lastId
				if(!is_object($adminPage)){
					$errorDetails = 'La respuesta de la API no es un objeto. Tipo: ' . gettype($adminPage);
					if(is_string($adminPage)){
						$errorDetails .= '. Contenido: ' . substr($adminPage, 0, 200);
					}
				} elseif(!isset($adminPage->status)){
					$errorDetails = 'La respuesta no tiene el campo "status". Estructura: ' . json_encode($adminPage, JSON_PRETTY_PRINT);
				} elseif((int)$adminPage->status !== 200){
					$errorDetails = 'Error HTTP: ' . $adminPage->status . '. Mensaje: ' . (isset($adminPage->message) ? $adminPage->message : 'Sin mensaje');
					if(isset($adminPage->results)){
						if(is_string($adminPage->results)){
							$errorDetails .= '. Respuesta: ' . $adminPage->results;
						} elseif(is_object($adminPage->results) || is_array($adminPage->results)){
							$errorDetails .= '. Detalles: ' . json_encode($adminPage->results, JSON_PRETTY_PRINT);
						}
					}
				} elseif(!isset($adminPage->results)){
					$errorDetails = 'La respuesta no tiene el campo "results". Estructura completa: ' . json_encode($adminPage, JSON_PRETTY_PRINT);
				} else {
					// Try multiple ways to extract lastId or id_page
					if(is_object($adminPage->results)){
						// Direct access to lastId
						if(isset($adminPage->results->lastId)){
							$adminPageId = $adminPage->results->lastId;
						} elseif(isset($adminPage->results->id_page)){
							// Sometimes the API returns the created record directly
							$adminPageId = $adminPage->results->id_page;
						} else {
							// Try as array
							$resultsArray = (array)$adminPage->results;
							if(isset($resultsArray['lastId'])){
								$adminPageId = $resultsArray['lastId'];
							} elseif(isset($resultsArray['id_page'])){
								$adminPageId = $resultsArray['id_page'];
							}
						}
					} elseif(is_array($adminPage->results)){
						// Check if it's a PDO error array (numeric keys)
						if(isset($adminPage->results[0]) && isset($adminPage->results[2]) && is_numeric($adminPage->results[0])){
							$errorDetails = 'Error de base de datos: ' . $adminPage->results[2] . ' (Código: ' . $adminPage->results[0] . ')';
						} elseif(isset($adminPage->results['lastId'])){
							// Array with lastId key
							$adminPageId = $adminPage->results['lastId'];
						} elseif(isset($adminPage->results['id_page'])){
							// Array with id_page key
							$adminPageId = $adminPage->results['id_page'];
						} elseif(isset($adminPage->results[0])){
							// Array with first element
							if(is_object($adminPage->results[0])){
								// Try lastId first
								if(isset($adminPage->results[0]->lastId)){
									$adminPageId = $adminPage->results[0]->lastId;
								} elseif(isset($adminPage->results[0]->id_page)){
									$adminPageId = $adminPage->results[0]->id_page;
								}
							} elseif(is_array($adminPage->results[0])){
								if(isset($adminPage->results[0]['lastId'])){
									$adminPageId = $adminPage->results[0]['lastId'];
								} elseif(isset($adminPage->results[0]['id_page'])){
									$adminPageId = $adminPage->results[0]['id_page'];
								}
							}
						}
						
						// If still no ID found, create error
						if($adminPageId === null && empty($errorDetails)){
							$errorDetails = 'El campo "results" es un array inesperado: ' . json_encode($adminPage->results, JSON_PRETTY_PRINT);
						}
					} else {
						$errorDetails = 'El campo "results" tiene un tipo inesperado: ' . gettype($adminPage->results);
						if(is_string($adminPage->results)){
							$errorDetails .= '. Contenido: ' . $adminPage->results;
						}
					}
					
					// If we still don't have lastId, create detailed error
					if($adminPageId === null && empty($errorDetails)){
						$availableFields = is_object($adminPage->results) ? implode(', ', array_keys((array)$adminPage->results)) : (is_array($adminPage->results) ? implode(', ', array_keys($adminPage->results)) : 'N/A');
						$errorDetails = 'No se pudo extraer "lastId" o "id_page" de la respuesta. Campos disponibles en "results": ' . $availableFields;
						if(is_object($adminPage->results) && isset($adminPage->results->comment)){
							$errorDetails .= '. Comentario: ' . $adminPage->results->comment;
						}
					}
				}

				if($adminPageId === null){
					// Build error message safely
					$errorMessage = "No se pudo crear la página de administradores.<br><br>";
					$errorMessage .= "<strong>Detalles del error:</strong><br>" . htmlspecialchars($errorDetails, ENT_QUOTES, 'UTF-8') . "<br><br>";
					$errorMessage .= "<strong>Respuesta completa de la API:</strong><br>";
					// Escape the JSON response properly for HTML
					$fullResponseEscaped = htmlspecialchars($fullResponse, ENT_QUOTES, 'UTF-8');
					$errorMessage .= "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px; font-size: 0.85rem; max-height: 200px; overflow-y: auto;'>" . $fullResponseEscaped . "</pre><br>";
					$errorMessage .= "<strong>Verifique que:</strong><br>";
					$errorMessage .= "1. La API esté accesible en: <code>" . htmlspecialchars($apiBaseUrl, ENT_QUOTES, 'UTF-8') . "pages</code><br>";
					$errorMessage .= "2. La base de datos esté configurada correctamente<br>";
					$errorMessage .= "3. Las tablas se hayan creado correctamente<br>";
					$errorMessage .= "4. La tabla 'pages' tenga las columnas correctas";
					
					// Escape properly for JavaScript - use JSON_HEX flags to prevent syntax errors
					$errorMessageEscaped = json_encode($errorMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
					
					echo '<script>
						fncMatPreloader("off");
						fncFormatInputs();
						fncSweetAlert("error","Error al crear página de administradores",' . $errorMessageEscaped . ');
					</script>';

					return;
				}

				// Create files page

				$url = "pages?token=no&except=id_page";
				$method = "POST";
				$fields = array(
					"title_page" => "Archivos",
					"url_page" => "archivos",
					"icon_page" => "bi bi-file-earmark-image",
					"type_page" => "custom",
					"order_page" => 3,
					"date_created_page" => date("Y-m-d")
				);

				$filesPage = CurlController::request($url,$method,$fields);

				// Create updates page

				$url = "pages?token=no&except=id_page";
				$method = "POST";
				$fields = array(
					"title_page" => "Actualizaciones",
					"url_page" => "updates",
					"icon_page" => "bi bi-arrow-repeat",
					"type_page" => "custom",
					"order_page" => 4,
					"date_created_page" => date("Y-m-d")
				);

				$updatesPage = CurlController::request($url,$method,$fields);

				// Create activity logs page

				$url = "pages?token=no&except=id_page";
				$method = "POST";
				$fields = array(
					"title_page" => "Logs",
					"url_page" => "activity_logs",
					"icon_page" => "bi bi-journal-text",
					"type_page" => "custom",
					"order_page" => 5,
					"date_created_page" => date("Y-m-d")
				);

				$activityLogsPage = CurlController::request($url,$method,$fields);

				// Create activity logs page directory and file if page was created successfully
				if($activityLogsPage->status == 200){
					$activityLogsDirectory = __DIR__ . '/../views/pages/custom/activity_logs';
					
					// Create directory if it doesn't exist
					if(!file_exists($activityLogsDirectory)){
						mkdir($activityLogsDirectory, 0755, true);
					}
					
					// Verify that the activity_logs.php file exists
					// The file should already exist from our earlier creation, but we ensure it's there
					$activityLogsFile = $activityLogsDirectory . '/activity_logs.php';
					if(!file_exists($activityLogsFile)){
						// The file should exist, but if it doesn't, we'll note it in error log
						// In a normal installation, the file should already be in place
						error_log("Warning: activity_logs.php file not found at: " . $activityLogsFile);
					}
				}

				// Create packaging page using setup controller
				require_once __DIR__ . '/packaging-setup.controller.php';
				$packagingPageResult = PackagingSetupController::ensurePackagingPage();
				$packagingPage = (object)['status' => $packagingPageResult['success'] ? 200 : 500];

				// Create Breadcrumb module for admins page

				$url = "modules?token=no&except=id_module";
				$method = "POST";
				$fields = array(
					"id_page_module" => $adminPageId,
					"type_module" => "breadcrumbs",
					"title_module" => "Administradores",
					"date_created_module"  => date("Y-m-d")
				);

				$breadcrumbModule = CurlController::request($url,$method,$fields);

				// Create Table module for admins page

				$url = "modules?token=no&except=id_module";
				$method = "POST";
				$fields = array(
					"id_page_module" => $adminPageId,
					"type_module" => "tables",
					"title_module" => "admins",
					"suffix_module" => "admin",
					"editable_module" => 0,
					"date_created_module"  => date("Y-m-d")
				);

				$tableModule = CurlController::request($url,$method,$fields);

				// Create server folder

				$url = "folders?token=no&except=id_folder";
				$method = "POST";
				$fields = array(
					"name_folder" => "Server",
					"size_folder" => "200000000000",
					"max_upload_folder" => "500000000",
					"url_folder" => $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"],
					"date_created_folder"  => date("Y-m-d")
				);

				$serverFolder = CurlController::request($url,$method,$fields);

				if($register->status == 200 && 
				   $homePage->status == 200 &&
				   $adminPage->status == 200 &&
				   $filesPage->status == 200 &&
				   $updatesPage->status == 200 &&
				   $activityLogsPage->status == 200 &&
				   $packagingPage->status == 200 &&
				   $breadcrumbModule->status == 200 &&
				   $tableModule->status == 200 &&
				   $serverFolder->status == 200
				){

					// Create each column for admins table

					$columns = array(
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "rol_admin",
							"alias_column" => "rol",
							"type_column" =>  "select",
							"matrix_column"  => "superadmin,admin,editor",
							"visible_column" => 1,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "permissions_admin",
							"alias_column" => "permisos",
							"type_column" =>  "object",
							"matrix_column"  => "",
							"visible_column" => 1,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "email_admin",
							"alias_column" => "email",
							"type_column" =>  "email",
							"matrix_column"  => "",
							"visible_column" => 1,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "password_admin",
							"alias_column" => "pass",
							"type_column" =>  "password",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "token_admin",
							"alias_column" => "token",
							"type_column" =>  "text",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "token_exp_admin",
							"alias_column" => "expiración",
							"type_column" =>  "text",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "status_admin",
							"alias_column" => "estado",
							"type_column" =>  "boolean",
							"matrix_column"  => "",
							"visible_column" => 1,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "title_admin",
							"alias_column" => "título",
							"type_column" =>  "text",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "symbol_admin",
							"alias_column" => "simbolo",
							"type_column" =>  "text",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "font_admin",
							"alias_column" => "tipografía",
							"type_column" =>  "text",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "color_admin",
							"alias_column" => "color",
							"type_column" =>  "text",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "back_admin",
							"alias_column" => "fondo",
							"type_column" =>  "text",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "scode_admin",
							"alias_column" => "seguridad",
							"type_column" =>  "text",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						],
						[	
							"id_module_column" => $tableModule->results->lastId,
							"title_column" =>  "chatgpt_admin",
							"alias_column" => "chatgpt",
							"type_column" =>  "object",
							"matrix_column"  => "",
							"visible_column" => 0,
							"date_created_column" => date("Y-m-d")
						]
					);

					$countColumns = 0;

					foreach ($columns as $key => $value) {
						
						$url = "columns?token=no&except=id_column";
						$method = "POST";
						$fields = array(
							"id_module_column" => $value["id_module_column"],
							"title_column" =>  $value["title_column"],
							"alias_column" => $value["alias_column"],
							"type_column" =>  $value["type_column"],
							"matrix_column"  => $value["matrix_column"],
							"visible_column" => $value["visible_column"],
							"date_created_column" => $value["date_created_column"]
						);

						$createColumn = CurlController::request($url,$method,$fields);

						if($createColumn->status == 200){

							$countColumns++;

						}
							
					}

					if($countColumns == count($columns)){

						// Final update: Ensure all database URLs are updated to new domain
						// This handles cases where database was imported from package
						if (isset($oldDomain) && $oldDomain && $oldDomain !== $newDomain) {
							$finalDbUpdate = PathUpdaterController::updateDatabaseUrls($oldDomain, $newDomain, $dbConfig);
							// Log but don't show error to user as installation is complete
							if ($finalDbUpdate['success'] && $finalDbUpdate['updated_count'] > 0) {
								error_log("Info: Updated " . $finalDbUpdate['updated_count'] . " database records with new domain URLs");
							}
						}

						echo '<script>
						fncMatPreloader("off");
						fncFormatInputs();
						fncSweetAlert("success","La instalación se realizó exitosamente",setTimeout(()=>location.reload(),1250));
						</script>';

					}	

				}

			} else {
				// If table creation failed, show error
				$errorMsg = !empty($errorMessage) 
					? "Error al crear las tablas: " . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') 
					: "Error al crear las tablas. Verifique que tenga permisos suficientes en la base de datos.";
				
				$errorMsgEscaped = json_encode($errorMsg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
				echo '<script>
					fncMatPreloader("off");
					fncFormatInputs();
					fncSweetAlert("error", "Error en la instalación", ' . $errorMsgEscaped . ');
				</script>';
			}		

		}

	}

	// Validate table existence in database

	static public function getTable($table){

		$database = InstallController::infoDatabase()["database"];
		$validate = InstallController::connect()->query("SELECT COLUMN_NAME AS item FROM information_schema.columns WHERE table_schema = '$database' AND table_name = '$table'")->fetchAll(PDO::FETCH_OBJ);

		// Validate table existence

		if(!empty($validate)){

			return 200;
		
		}else{

			return 404;
		}

	}

	// Get database tables

	static public function getTables(){

		$tables = InstallController::connect()->query("SHOW FULL TABLES")->fetchAll(PDO::FETCH_COLUMN);

		// Validate tables existence

		if(!empty($tables)){

			return $tables;
		}


	}

}

?>