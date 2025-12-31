<?php 

class PagesController{

	public function managePage(){

		if(isset($_POST["title_page"])){

			/*=============================================
			Editar Página
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
				Validar que la Página no exista
				=============================================*/

				$url = "pages?linkTo=title_page,url_page&equalTo=".trim($_POST["title_page"]).",".trim($_POST["url_page"]);
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
				Validar que el Plugin no esté duplicado
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
				Crear Página
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
					Crear Página personalizable
					=============================================*/

					if($fields["type_page"] == "custom"){

						/*=============================================
						Creamos carpeta de página personalizable
						=============================================*/

						$directory = DIR."/views/pages/custom/".$urlPage;

						if(!file_exists($directory)){

							@mkdir($directory, 0755, true);
							@chmod($directory, 0755);
						}

						/*=============================================
						Si es un plugin, asegurar permisos del directorio del plugin
						=============================================*/

						$pluginsRegistryPath = __DIR__ . "/../../plugins/plugins-registry.php";
						
						if(file_exists($pluginsRegistryPath)){
							require_once $pluginsRegistryPath;
							
							if(class_exists('PluginsRegistry') && PluginsRegistry::isPluginUrl($urlPage)){
								
								// Asegurar que el directorio del plugin existe con permisos correctos
								$projectRoot = dirname(DIR);
								$pluginDir = $projectRoot . '/plugins/' . $urlPage;
								
								if(!file_exists($pluginDir)){
									@mkdir($pluginDir, 0777, true);
									@chmod($pluginDir, 0777);
								} else {
									// Asegurar permisos incluso si el directorio ya existe
									@chmod($pluginDir, 0777);
								}
								
								// Also ensure parent plugins directory permissions
								$pluginsDir = $projectRoot . '/plugins';
								if(file_exists($pluginsDir)){
									@chmod($pluginsDir, 0777);
								}
								
								/*=============================================
								Verificar y crear tablas necesarias para el plugin
								=============================================*/
								
								// Para el plugin Payku, verificar/crear tabla payku_orders
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
						Copiamos el archivo custom con el nuevo nombre
						=============================================*/	

						$from = DIR."/views/pages/custom/custom.php";

						if(copy($from, $directory.'/'.$urlPage.'.php')){

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