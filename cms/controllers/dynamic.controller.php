<?php

class DynamicController{

	/**
	 * Evaluate if a field's conditions are met based on POST data
	 * @param string|null $conditionsJson JSON string with conditions
	 * @param array $postData POST data to evaluate against
	 * @return bool True if conditions are met (field should be processed)
	 */
	private function evaluateConditions($conditionsJson, $postData) {
		if (empty($conditionsJson)) {
			return true; // No conditions = always process
		}

		try {
			$decoded = urldecode($conditionsJson);
			$conditions = json_decode($decoded, true);

			if (!$conditions || empty($conditions['rules'])) {
				return true;
			}

			$results = [];
			foreach ($conditions['rules'] as $rule) {
				$results[] = $this->evaluateRule($rule, $postData);
			}

			$operator = $conditions['operator'] ?? 'and';
			if ($operator === 'and') {
				return !in_array(false, $results, true);
			} else {
				return in_array(true, $results, true);
			}
		} catch (Exception $e) {
			return true; // On error, process the field
		}
	}

	/**
	 * Evaluate a single condition rule
	 * @param array $rule Rule with field, operator, value
	 * @param array $postData POST data
	 * @return bool True if rule is satisfied
	 */
	private function evaluateRule($rule, $postData) {
		if (!isset($rule['field']) || !isset($rule['operator'])) {
			return true;
		}

		$fieldValue = $postData[$rule['field']] ?? '';
		$ruleValue = $rule['value'] ?? '';

		switch ($rule['operator']) {
			case 'equals':
				return (string)$fieldValue === (string)$ruleValue;
			case 'not_equals':
				return (string)$fieldValue !== (string)$ruleValue;
			case 'empty':
				return empty($fieldValue);
			case 'not_empty':
				return !empty($fieldValue);
			default:
				return true;
		}
	}

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

				// Check if field conditions are met
				$conditionsJson = $value->conditions_column ?? null;
				$conditionsMet = $this->evaluateConditions($conditionsJson, $_POST);

				// Get field value from POST, use empty string if not set or conditions not met
				$fieldValue = $conditionsMet ? ($_POST[$value->title_column] ?? '') : '';

				if($value->type_column == "password" && !empty($fieldValue)){

					$passwordSalt = TemplateController::getPasswordSalt();
					$fields.= $value->title_column."=".crypt(trim($fieldValue), $passwordSalt)."&";

				}else if($value->type_column == "email"){

					$fields.= $value->title_column."=".trim($fieldValue)."&";

				}else{

					$fields.= $value->title_column."=".urlencode(trim($fieldValue))."&";

				}

					$count++;

					if($count == count($module->columns)){

						$fields = substr($fields,0,-1);

						$update = CurlController::request($url,$method,$fields);

						// Always close loading modal first
						echo '<script>fncMatPreloader("off"); fncSweetAlert("close", "", "");</script>';

						// Verify response structure
						if (!is_object($update)) {
							$update = (object)['status' => 500, 'message' => 'Respuesta inválida del servidor', 'results' => []];
						}
						if (!isset($update->status)) {
							$update->status = 500;
						}

						if($update->status == 200){
							
							/*=============================================
							Log update activity
							=============================================*/
							
							if (function_exists('logActivity')) {
								$entityId = base64_decode($_POST["idItem"]);
								logActivity('update', $module->title_module, $entityId, 'Record update in module ' . $module->title_module);
							}

							// Determine redirect URL
							require_once __DIR__ . '/template.controller.php';
							$cmsBasePath = TemplateController::cmsBasePath();
							
							if (isset($module->url_page) && !empty($module->url_page)) {
								$redirectUrl = $cmsBasePath . '/' . $module->url_page;
							} else {
								// Use JavaScript to get current path and remove /manage and any following segments
								$redirectUrl = 'window.location.pathname.replace(/\\/manage(\\/.*)?$/, "")';
							}

							echo '

								<script>

									fncFormatInputs();
									fncSweetAlert("success","El registro ha sido actualizado con éxito", "");
									setTimeout(function(){
										var redirectUrl = ' . (strpos($redirectUrl, 'window.location') !== false ? $redirectUrl : '"' . $redirectUrl . '"') . ';
										window.location = typeof redirectUrl === "string" ? redirectUrl : redirectUrl;
									}, 1000);
									

								</script>

							';
							
						} else {
							// Handle error - show detailed error message
							$errorMessage = "Error al actualizar el registro";
							$errorDetails = array();
							
							// Get error details from response
							if (isset($update->message)) {
								$errorMessage = $update->message;
							} elseif (isset($update->results)) {
								if (is_string($update->results)) {
									$errorMessage = $update->results;
								} elseif (is_array($update->results)) {
									if (isset($update->results[2])) {
										$errorMessage = $update->results[2];
									} else {
										$errorMessage = "Error en la respuesta del servidor";
										$errorDetails[] = "Respuesta: " . json_encode($update->results, JSON_UNESCAPED_UNICODE);
									}
								} elseif (is_object($update->results)) {
									$errorMessage = "Error en la respuesta del servidor";
									$errorDetails[] = "Respuesta: " . json_encode($update->results, JSON_UNESCAPED_UNICODE);
								}
							}
							
							// Add status code if available
							if (isset($update->status)) {
								$errorDetails[] = "Código de estado: " . $update->status;
							}
							
							// Log error for debugging
							$fullResponse = json_encode($update, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
							error_log("Dynamic Controller Update Error - Full Response: " . $fullResponse);
							error_log("Dynamic Controller Update Error - URL: " . $url);
							
							// Build final message
							$finalMessage = $errorMessage;
							if (!empty($errorDetails)) {
								$finalMessage .= "\\n\\n" . implode("\\n", $errorDetails);
							}
							
							// Also log to console for debugging
							$consoleLog = "console.error('Error al actualizar:', " . json_encode($update, JSON_HEX_APOS | JSON_HEX_QUOT) . ");";
							
							echo '<script>
								' . $consoleLog . '
								fncSweetAlert("error", "Error al actualizar", ' . json_encode($finalMessage, JSON_HEX_APOS | JSON_HEX_QUOT) . ');
							</script>';
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

				// Check if field conditions are met
				$conditionsJson = $value->conditions_column ?? null;
				$conditionsMet = $this->evaluateConditions($conditionsJson, $_POST);

				// Get field value from POST, use empty string if not set or conditions not met
				$fieldValue = $conditionsMet ? ($_POST[$value->title_column] ?? '') : '';

				if($value->type_column == "password"){

					// Only process password if value is provided
					if(!empty($fieldValue)){
						$passwordSalt = TemplateController::getPasswordSalt();
						$fields[$value->title_column] = crypt(trim($fieldValue), $passwordSalt);
					}

				}else if($value->type_column == "email"){

					$fields[$value->title_column] = trim($fieldValue);
				}else{

					$fields[$value->title_column] = urlencode(trim($fieldValue));

				}

					$count++;

					if($count == count($module->columns)){

						$fields["date_created_".$module->suffix_module] = date("Y-m-d");

						$save = CurlController::request($url,$method,$fields);

						// Always close loading modal first
						echo '<script>fncMatPreloader("off"); fncSweetAlert("close", "", "");</script>';

						// Verify response structure
						if (!is_object($save)) {
							$save = (object)['status' => 500, 'message' => 'Respuesta inválida del servidor', 'results' => []];
						}
						if (!isset($save->status)) {
							$save->status = 500;
						}

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

							// Determine redirect URL
							require_once __DIR__ . '/template.controller.php';
							$cmsBasePath = TemplateController::cmsBasePath();
							
							if (isset($module->url_page) && !empty($module->url_page)) {
								$redirectUrl = $cmsBasePath . '/' . $module->url_page;
							} else {
								// Use JavaScript to get current path and remove /manage and any following segments
								$redirectUrl = 'window.location.pathname.replace(/\\/manage(\\/.*)?$/, "")';
							}

							echo '

								<script>

									fncFormatInputs();
									fncSweetAlert("success","El registro ha sido guardado con éxito", "");
									setTimeout(function(){
										var redirectUrl = ' . (strpos($redirectUrl, 'window.location') !== false ? $redirectUrl : '"' . $redirectUrl . '"') . ';
										window.location = typeof redirectUrl === "string" ? redirectUrl : redirectUrl;
									}, 1000);
									

								</script>

							';
							
						} else {
							// Handle error - show detailed error message
							$errorMessage = "Error al guardar el registro";
							$errorDetails = array();
							
							// Get error details from response
							if (isset($save->message)) {
								$errorMessage = $save->message;
								// If there's a raw_response, include it in details
								if (isset($save->raw_response)) {
									$errorDetails[] = "Respuesta del servidor: " . substr($save->raw_response, 0, 300);
								}
							} elseif (isset($save->results)) {
								if (is_string($save->results)) {
									$errorMessage = $save->results;
								} elseif (is_array($save->results)) {
									if (isset($save->results[2])) {
										$errorMessage = $save->results[2];
									} else {
										$errorMessage = "Error en la respuesta del servidor";
										$errorDetails[] = "Respuesta: " . json_encode($save->results, JSON_UNESCAPED_UNICODE);
									}
								} elseif (is_object($save->results)) {
									$errorMessage = "Error en la respuesta del servidor";
									$errorDetails[] = "Respuesta: " . json_encode($save->results, JSON_UNESCAPED_UNICODE);
								}
							}
							
							// Add status code if available
							if (isset($save->status)) {
								$errorDetails[] = "Código de estado: " . $save->status;
							}
							
							// Log full response for debugging
							$fullResponse = json_encode($save, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
							error_log("Dynamic Controller Save Error - Full Response: " . $fullResponse);
							error_log("Dynamic Controller Save Error - URL: " . $url);
							error_log("Dynamic Controller Save Error - Fields: " . json_encode($fields, JSON_UNESCAPED_UNICODE));
							
							// Build final message - always include status code and some details
							$finalMessage = $errorMessage;
							if (empty($errorDetails) && isset($save->status)) {
								$errorDetails[] = "Código de estado: " . $save->status;
							}
							if (!empty($errorDetails)) {
								$finalMessage .= "\\n\\n" . implode("\\n", $errorDetails);
							}
							
							// If we still don't have useful info, show the full response (truncated)
							if ($errorMessage == "Error al guardar el registro" && empty($errorDetails)) {
								$responsePreview = json_encode($save, JSON_UNESCAPED_UNICODE);
								if (strlen($responsePreview) > 300) {
									$responsePreview = substr($responsePreview, 0, 300) . "...";
								}
								$finalMessage .= "\\n\\nRespuesta del servidor: " . $responsePreview;
							}
							
							// Also log to console for debugging
							$consoleLog = "console.error('Error al guardar:', " . json_encode($save, JSON_HEX_APOS | JSON_HEX_QUOT) . ");";
							
							echo '<script>
								' . $consoleLog . '
								fncSweetAlert("error", "Error al guardar", ' . json_encode($finalMessage, JSON_HEX_APOS | JSON_HEX_QUOT) . ');
							</script>';
						}
					}
				
				}

			}

		}

	}

}