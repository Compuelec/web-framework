<?php 

class PagesController{

	public function managePage(){

		if(isset($_POST["title_page"])){

			/*=============================================
			Edit Page
			=============================================*/

			if(isset($_POST["id_page"])){

				$url = "pages?id=".base64_decode($_POST["id_page"])."&nameId=id_page&token=".$_SESSION["admin"]->token_admin."&table=admins&suffix=admin";
				$method = "PUT";
				
				$parentPage = isset($_POST["parent_page"]) && $_POST["parent_page"] != "0" ? $_POST["parent_page"] : 0;
				$fields = "title_page=".trim($_POST["title_page"])."&url_page=".urlencode(strtolower(trim($_POST["url_page"])))."&icon_page=".trim($_POST["icon_page"])."&type_page=".$_POST["type_page"]."&parent_page=".$parentPage;
			
				$update = CurlController::request($url,$method,$fields);

				if($update->status == 200){

					echo '

					<script>

						fncMatPreloader("off");
						fncFormatInputs();
					    fncSweetAlert("success","La página ha sido actualizada con éxito",setTimeout(()=>location.reload(),1250));	

					</script>

					';

				}


			}else{

				/*=============================================
				Validate that Page does not exist
				=============================================*/

				$url = "pages?linkTo=title_page,url_page&equalTo=".urlencode(trim($_POST["title_page"])).",".urlencode(trim($_POST["url_page"]));
				$method = "GET";
				$fields = array();

				$getPage = CurlController::request($url,$method,$fields);
				
				if($getPage->status == 200){

					echo '

					<script>

						fncMatPreloader("off");
						fncFormatInputs();
					    fncToastr("error","ERROR: Esta página ya existe");	

					</script>

					';

					return;

				}

				/*=============================================
				Validate that Plugin is not duplicated
				=============================================*/

				$pluginUrl = trim($_POST["url_page"]);
				$pluginsRegistryPath = __DIR__ . "/../../plugins/plugins-registry.php";
				
				if(file_exists($pluginsRegistryPath)){
					require_once $pluginsRegistryPath;
					
					if(class_exists('PluginsRegistry') && PluginsRegistry::isPluginUrl($pluginUrl)){
						
						try {
							if(PluginsRegistry::pluginPageExists($pluginUrl)){

								echo '

								<script>

									fncMatPreloader("off");
									fncFormatInputs();
								    fncToastr("error","ERROR: Este plugin ya tiene una página creada. No se puede duplicar.");	

								</script>

								';

								return;

							}
						} catch (Exception $e) {
							// If plugin check fails, log error but continue with page creation
							error_log("Plugin validation error: " . $e->getMessage());
						}
					}
				}

				/*=============================================
				Create Page
				=============================================*/

				$url = "pages?token=".$_SESSION["admin"]->token_admin."&table=admins&suffix=admin";
				$method = "POST";
				
				$parentPage = isset($_POST["parent_page"]) && $_POST["parent_page"] != "0" ? $_POST["parent_page"] : 0;
				
				$fields = array(
					"title_page" => trim($_POST["title_page"]),
					"url_page" => urlencode(strtolower(trim($_POST["url_page"]))),
					"icon_page" => trim($_POST["icon_page"]),
					"type_page" =>$_POST["type_page"],
					"parent_page" => $parentPage,
					"order_page" => 1000,
					"date_created_page" => date("Y-m-d")
				);

				$create = CurlController::request($url,$method,$fields);

				if($create->status == 200){

					// Get CMS base path for proper redirects
					require_once __DIR__ . '/template.controller.php';
					$cmsBasePath = TemplateController::cmsBasePath();
					$urlPage = urldecode($fields["url_page"]);

					/*=============================================
					Create Custom Page
					=============================================*/

					if($fields["type_page"] == "custom"){

						/*=============================================
						Create custom page folder
						=============================================*/

						$directory = DIR."/views/pages/custom/".$urlPage;

						if(!file_exists($directory)){

							@mkdir($directory, 0755, true);
							@chmod($directory, 0755);
						}

						/*=============================================
						If it's a plugin, ensure plugin directory permissions
						=============================================*/

						$pluginsRegistryPath = __DIR__ . "/../../plugins/plugins-registry.php";
						
						if(file_exists($pluginsRegistryPath)){
							require_once $pluginsRegistryPath;
							
							if(class_exists('PluginsRegistry') && PluginsRegistry::isPluginUrl($urlPage)){
								
								// Ensure plugin directory exists with correct permissions
								$projectRoot = dirname(DIR);
								$pluginDir = $projectRoot . '/plugins/' . $urlPage;
								
								if(!file_exists($pluginDir)){
									@mkdir($pluginDir, 0777, true);
									@chmod($pluginDir, 0777);
								} else {
									// Ensure permissions even if directory already exists
									@chmod($pluginDir, 0777);
								}
								
								// Also ensure parent plugins directory permissions
								$pluginsDir = $projectRoot . '/plugins';
								if(file_exists($pluginsDir)){
									@chmod($pluginsDir, 0777);
								}
								
								/*=============================================
								Verify and create necessary tables for the plugin
								=============================================*/
								
								// For Payku plugin, verify/create payku_orders table
								if($urlPage === 'payku'){
									$paykuControllerPath = $projectRoot . '/plugins/payku/controllers/payku.controller.php';
									if(file_exists($paykuControllerPath)){
										require_once $paykuControllerPath;
										if(class_exists('PaykuPlugin')){
											PaykuPlugin::ensureTable();
										}
									}
								}
							}
						}

						/*=============================================
						Copy custom file with new name
						=============================================*/	

						$from = DIR."/views/pages/custom/custom.php";
						$to = $directory.'/'.$urlPage.'.php';

						// Ensure source file exists
						if(!file_exists($from)){
							error_log("Pages Controller Error - Template file not found: " . $from);
							echo '
							<script>
								fncMatPreloader("off");
								fncFormatInputs();
								fncToastr("error","ERROR: Archivo plantilla no encontrado");
							</script>';
							return;
						}

						// Ensure directory is writable
						if(!is_writable($directory)){
							error_log("Pages Controller Error - Directory not writable: " . $directory);
							@chmod($directory, 0755);
							if(!is_writable($directory)){
								echo '
								<script>
									fncMatPreloader("off");
									fncFormatInputs();
									fncToastr("error","ERROR: Sin permisos para crear archivo en el directorio");
								</script>';
								return;
							}
						}

						if(@copy($from, $to)){

							echo '

							<script>

								fncMatPreloader("off");
								fncFormatInputs();
							    fncSweetAlert("success","La página ha sido creada con éxito",setTimeout(()=>{
									// Reload page to update sidebar
									location.reload();
								},1250));	

							</script>

							';

						}else{

							// Log error if copy fails
							$error = error_get_last();
							error_log("Pages Controller Error - Failed to copy file from '{$from}' to '{$to}'. Error: " . ($error ? $error['message'] : 'Unknown error'));
							
							// If file copy fails, still reload to show in sidebar
							echo '

							<script>

								fncMatPreloader("off");
								fncFormatInputs();
							    fncSweetAlert("success","La página ha sido creada con éxito",setTimeout(()=>{
									// Reload page to update sidebar
									location.reload();
								},1250));	

							</script>

							';

						}

					}else if($fields["type_page"] == "external_link" || $fields["type_page"] == "internal_link"){

						echo '

						<script>

							fncMatPreloader("off");
							fncFormatInputs();
						    fncSweetAlert("success","La página ha sido creada con éxito",setTimeout(()=>location.reload(),1250));	

						</script>

						';


					}else if($fields["type_page"] == "menu"){

						// For menu pages, just reload to show in list
						echo '

						<script>

							fncMatPreloader("off");
							fncFormatInputs();
						    fncSweetAlert("success","La página ha sido creada con éxito",setTimeout(()=>location.reload(),1250));	

						</script>

						';

					}else{

						// For modules type pages, reload to update sidebar
						echo '

						<script>

							fncMatPreloader("off");
							fncFormatInputs();
						    fncSweetAlert("success","La página ha sido creada con éxito",setTimeout(()=>{
								// Reload page to update sidebar
								location.reload();
							},1250));	

						</script>

						';

					}

				}


			}


		}

	}

}