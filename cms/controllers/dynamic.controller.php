<?php 

class DynamicController{

	// Dynamic data management

	public function manage(){

		if(isset($_POST["module"])){

			echo '<script>

				fncMatPreloader("on");
			    fncSweetAlert("loading", "Procesando...", "");

			</script>';

			$module = json_decode($_POST["module"]);

			// Edit data

			if(isset($_POST["idItem"])){

				// Update data

				$url = $module->title_module."?id=".base64_decode($_POST["idItem"])."&nameId=id_".$module->suffix_module."&token=".$_SESSION["admin"]->token_admin."&table=admins&suffix=admin";
				$method = "PUT";
				$fields = "";
				$count = 0;

				foreach ($module->columns as $key => $value) {

					if($value->type_column == "password" && !empty($_POST[$value->title_column])){

						$passwordSalt = TemplateController::getPasswordSalt();
						$fields.= $value->title_column."=".crypt(trim($_POST[$value->title_column]), $passwordSalt)."&";

					}else if($value->type_column == "email"){

						$fields.= $value->title_column."=".trim($_POST[$value->title_column])."&";

					}else{
					
						$fields.= $value->title_column."=".urlencode(trim($_POST[$value->title_column]))."&";

					}
					
					$count++;

					if($count == count($module->columns)){

						$fields = substr($fields,0,-1);

						$update = CurlController::request($url,$method,$fields);

						if($update->status == 200){
							
							/*=============================================
							Log update activity
							=============================================*/
							
							if (function_exists('logActivity')) {
								$entityId = base64_decode($_POST["idItem"]);
								logActivity('update', $module->title_module, $entityId, 'Record update in module ' . $module->title_module);
							}

							echo '

								<script>

									fncMatPreloader("off");
									fncFormatInputs();
								    fncSweetAlert("success","El registro ha sido actualizado con éxito", setTimeout(()=>window.location="/'.$module->url_page.'",1000));
									

								</script>

							';
							
						}
					}
				
				}


			}else{
		
				// Create data

				$url = $module->title_module."?token=".$_SESSION["admin"]->token_admin."&table=admins&suffix=admin";
				$method = "POST";
				$fields = array();
				$count = 0;

				foreach ($module->columns as $key => $value) {

					if($value->type_column == "password"){

						$passwordSalt = TemplateController::getPasswordSalt();
						$fields[$value->title_column] = crypt(trim($_POST[$value->title_column]), $passwordSalt);
					
					}else if($value->type_column == "email"){

						$fields[$value->title_column] = trim($_POST[$value->title_column]);
					}else{
					
						$fields[$value->title_column] = urlencode(trim($_POST[$value->title_column]));

					}
					
					$count++;

					if($count == count($module->columns)){

						$fields["date_created_".$module->suffix_module] = date("Y-m-d");

						$save = CurlController::request($url,$method,$fields);

						if($save->status == 200){
							
							/*=============================================
							Log create activity
							=============================================*/
							
							if (function_exists('logActivity')) {
								// Try to get the created record ID from response
								$entityId = null;
								if (isset($save->results) && is_array($save->results) && count($save->results) > 0) {
									$firstResult = $save->results[0];
									$idField = 'id_' . $module->suffix_module;
									$entityId = $firstResult->$idField ?? null;
								}
								logActivity('create', $module->title_module, $entityId, 'Record creation in module ' . $module->title_module);
							}

							echo '

								<script>

									fncMatPreloader("off");
									fncFormatInputs();
								    fncSweetAlert("success","El registro ha sido guardado con éxito", setTimeout(()=>window.location="/'.$module->url_page.'",1000));
									

								</script>

							';
							
						}
					}
				
				}

			}

		}

	}

}