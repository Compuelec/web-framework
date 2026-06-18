<?php

require_once "../controllers/curl.controller.php";
require_once "../controllers/template.controller.php";
require_once __DIR__ . "/../../core/activity_log.php";

// Authentication guard — require a valid admin session for every action
define('SESSION_INIT_INCLUDED', true);
require_once __DIR__ . '/session-init.php';

if(!isset($_SESSION["admin"])){
	header('Content-Type: application/json');
	http_response_code(401);
	echo json_encode(["status" => 401, "results" => "Unauthorized"]);
	exit;
}

// CSRF protection for state-changing requests
if(!SessionController::validateCsrfRequest()){
	header('Content-Type: application/json');
	http_response_code(403);
	echo json_encode(["status" => 403, "results" => "Invalid CSRF token"]);
	exit;
}

class DynamicTablesController{

	/*=============================================
    Delete items
    =============================================*/

	public $idItemDelete;
    public $tableDelete;
	public $suffixDelete;
	public $token;

    public function deleteItems(){

    	$idItems = explode(",",$this->idItemDelete);
    	$countDelete = 0;

    	foreach ($idItems as $key => $value) {
    		
    		$url = $this->tableDelete."?id=".(int)base64_decode($value, true)."&nameId=id_".$this->suffixDelete."&token=".$this->token."&table=admins&suffix=admin";
			$method = "DELETE";
			$fields = array();

			$deleteItem = CurlController::request($url,$method,$fields);

			if($deleteItem->status == 200){
				
				/*=============================================
				Log delete activity
				=============================================*/
				
				if (function_exists('logActivity')) {
					$entityId = (int)base64_decode($value, true);
					logActivity('delete', $this->tableDelete, $entityId, 'Record deletion in table ' . $this->tableDelete);
				}

				$countDelete++;

			}

    	}

    	// Always respond so the client never receives an empty body on a
    	// partial failure: 200 only when every item was deleted, 500 otherwise.
    	echo $countDelete == count($idItems) ? 200 : 500;

	}

	/*=============================================
    Return filtered table
    =============================================*/

    public $contentModule;
	public $orderBy;
	public $orderMode;
	public $limit;
	public $page;
	public $rolAdmin;
	public $search;
	public $between1;
	public $between2;

	public function loadAjaxTable(){

		$cmsBasePath = TemplateController::cmsBasePath();

		$module = (json_decode($this->contentModule));
		// Ensure page and limit are integers
		$page = (int)($this->page ?? 1);
		if($page < 1){ $page = 1; }
		$limit = (int)($this->limit ?? 10);
		if($limit < 1){ $limit = 10; }
		$startAt = ($page - 1) * $limit;
		$table = array(); 
		$totalPages = 0;
		$totalData = 0;


    	/*=============================================
		Filter by search
		=============================================*/

		if($this->search != ""){

			/*=============================================
			Search columns
			=============================================*/

			$linkTo = array();

			foreach ($module->columns as $key => $value) {
			
				if($value->visible_column == 1){

					if( $value->type_column == "text" ||
						$value->type_column == "textarea" ||
						$value->type_column == "int" ||
						$value->type_column == "double" ||
						$value->type_column == "money" ||  
						$value->type_column == "color" || 
						$value->type_column == "link" ||
						$value->type_column == "select" ||
						$value->type_column == "array" || 
						$value->type_column == "date" ||
						$value->type_column == "time" ||
						$value->type_column == "datetime"){

						array_push($linkTo, $value->title_column);
					}
				}
			}

			/*=============================================
			Search iteration
			=============================================*/
			foreach ($linkTo as $key => $value) {

				$url = $module->title_module."?linkTo=".$value."&search=".str_replace(" ", "_", $this->search)."&orderBy=".$this->orderBy."&orderMode=".$this->orderMode."&startAt=".$startAt."&endAt=".$limit;
				$method = "GET";
				$fields = array();

				$table = CurlController::request($url,$method,$fields);

				if($table->status == 200){

					$table = $table->results;

					/*=============================================
					Fetch the total content of the table
					=============================================*/
					
					$url = $module->title_module."?linkTo=".$value."&search=".str_replace(" ", "_", $this->search)."&select=id_".$module->suffix_module;
					$totalData = CurlController::request($url,$method,$fields)->total;
					$totalPages = ceil($totalData/$limit);

					break;
					
				}else{

					$table = array();
				}
		
			}
			
		}else{

			$url = $module->title_module."?linkTo=date_created_".$module->suffix_module."&between1=".$this->between1."&between2=".$this->between2."&orderBy=".$this->orderBy."&orderMode=".$this->orderMode."&startAt=".$startAt."&endAt=".$limit;

			$method = "GET";
			$fields = array();

			$table = CurlController::request($url,$method,$fields);

			if($table->status == 200){

				$table = $table->results;

				/*=============================================
				Fetch the total content of the table
				=============================================*/
				
				$url = $module->title_module."?linkTo=date_created_".$module->suffix_module."&between1=".$this->between1."&between2=".$this->between2."&select=id_".$module->suffix_module;
				$totalData = CurlController::request($url,$method,$fields)->total;
				$totalPages = $limit > 0 ? ceil($totalData/$limit) : 0;
				
			}else{

				$table = array();
			}

		}
	
		/*=============================================
    	Return the table in HTML format
    	=============================================*/

    	$HTMLTable = "";


    	if(!empty($table)){

    		foreach(json_decode(json_encode($table),true) as $key => $value){

				$HTMLTable .= '<tr>
						<td>'.($key+1+$startAt).'</td>';

						if ($this->rolAdmin == "superadmin" || $module->editable_module == 1){

							$HTMLTable .= '<td>
		    					<div class="form-check formCheck">
		    						<input class="form-check-input checkItem" type="checkbox" idItem="'.base64_encode($value["id_".$module->suffix_module]).'">
		    					</div>
		    				</td>';

		    			}

					    foreach ($module->columns as $index => $item){

							if ($item->visible_column == 1){
								
    							$HTMLTable .= '<td>';

								/*=============================================
								Image-type content
								=============================================*/

								if($item->type_column == "image"){

									if(!empty($value[$item->title_column])){

										$HTMLTable .= '<a href="'.urldecode($value[$item->title_column]).'" target="_blank">
										<img src="'.urldecode($value[$item->title_column]).'" class="rounded" style="width:60px; height:60px; object-fit: cover; object-position:center;">
										</a>';

									}else{

										$HTMLTable.= '<img src="'.$cmsBasePath.'/views/assets/img/file.png" class="rounded" style="width:60px; height:60px; object-fit: cover; object-position:center;">';
									}

								/*=============================================
								Multi-image type content
								=============================================*/

								}else if($item->type_column == "multiimage"){

									$multiImgs = json_decode(urldecode($value[$item->title_column]), true);
									if(is_array($multiImgs) && count($multiImgs)){
										foreach($multiImgs as $imgUrl){
											$HTMLTable .= '<a href="'.htmlspecialchars($imgUrl, ENT_QUOTES).'" target="_blank">
											<img src="'.htmlspecialchars($imgUrl, ENT_QUOTES).'" class="rounded me-1 mb-1" style="width:45px; height:45px; object-fit: cover; object-position:center;">
											</a>';
										}
									}else{
										$HTMLTable.= '<img src="'.$cmsBasePath.'/views/assets/img/file.png" class="rounded" style="width:45px; height:45px; object-fit: cover; object-position:center;">';
									}

								/*=============================================
								Video-type content
								=============================================*/

								}else if($item->type_column == "video"){

									$HTMLTable .= '<a href="'.urldecode($value[$item->title_column]).'" target="_blank">
										<img src="'.$cmsBasePath.'/views/assets/img/video.png" class="rounded" style="width:60px; height:60px; object-fit: cover; object-position:center;">
									</a>';

								/*=============================================
								Other files content type
								=============================================*/

								}else if($item->type_column == "file"){

									$HTMLTable .= '<a href="'.urldecode($value[$item->title_column]).'" target="_blank">
										<img src="'.$cmsBasePath.'/views/assets/img/file.png" class="rounded" style="width:60px; height:60px; object-fit: cover; object-position:center;">
									</a>';


								/*=============================================
								Boolean-type content
								=============================================*/

								}else if($item->type_column == "boolean"){


									if($value[$item->title_column] == 1){	

										$checked = 'checked';
										$label = "ON";
									
									}else{

										$checked = '';
										$label = "OFF";
									}

									if ($this->rolAdmin == "superadmin" || $module->editable_module == 1){

										$HTMLTable .= '<div class="form-check form-switch">
										<input class="form-check-input px-3 changeBoolean" type="checkbox" id="mySwtich" '.$checked.' idItem="'.base64_encode($value["id_".$module->suffix_module]).'" table="'.$module->title_module.'" suffix="'.$module->suffix_module.'" column="'.$item->title_column.'">
										<label class="form-check-label ps-1 align-middle" for="mySwitch">'.$label.'</label>
										</div>';

									}else{

										$HTMLTable .= '<label class="form-check-label ps-1 align-middle" for="mySwitch">'.$label.'</label>';
									}

								/*=============================================
								Array-type content
								=============================================*/
							    }else if($item->type_column == "array" && !empty($value[$item->title_column])){

							    	$typeArray = explode(",",urldecode($value[$item->title_column]));

							    	foreach ($typeArray as $num => $elem){
								
										$HTMLTable .= '<span class="badge badge-sm badge-default rounded bg-dark py-1 px-2 mx-1 mt-1 border small">'.TemplateController::reduceText($elem,25).'</span>';

									}

								/*=============================================
								Object-type content
								=============================================*/

								}else if($item->type_column == "object" && !empty($value[$item->title_column])){

							    	$typeJSON = json_decode(urldecode($value[$item->title_column]));

							    	foreach ($typeJSON as $num => $elem){

							    		$HTMLTable .= '<span class="badge badge-sm badge-default rounded py-1 px-2 mx-1 mt-1 border text-dark text-uppercase small">'.$num.': '.$elem.'</span>';

							    	}

							    /*=============================================
								Link-type content
								=============================================*/

								}else if($item->type_column == "link"){

							    	$HTMLTable .= '<a href="'.$value[$item->title_column].'" target="_blank" class="badge badge-default border rounded bg-indigo">'.TemplateController::reduceText(urldecode($value[$item->title_column]), 20).'</a>';

								/*=============================================
								Color-type content
								=============================================*/

								}else if($item->type_column == "color"){

							    	$HTMLTable .= '<div class="rounded border" style="width:25px; height:25px; background:'.urldecode($value[$item->title_column]).'"></div>';

							    /*=============================================
								Double-type content
								=============================================*/

								}else if($item->type_column == "money"){

							    	$HTMLTable .= '$'.number_format(urldecode($value[$item->title_column]),2);

								/*=============================================
								Relations-type content
								=============================================*/

								}else if($item->type_column == "relations"){

									if($item->matrix_column != null && $value[$item->title_column] > 0){

										$url = "relations?rel=modules,pages&type=module,page&linkTo=type_module,title_module&equalTo=tables,".$item->matrix_column."&select=url_page,suffix_module";
										$method = "GET";
										$array = array();

										$urlPage = CurlController::request($url,$method,$fields)->results[0]->url_page;
										$suffixModule = CurlController::request($url,$method,$fields)->results[0]->suffix_module;

										$url = $item->matrix_column.'?linkTo=id_'.$suffixModule."&equalTo=".$value[$item->title_column];
										$relation = CurlController::request($url,$method,$fields);
										$arrayRelation  = (array)$relation->results[0];
				
										$HTMLTable .= '<a href="'.$cmsBasePath.'/'.$urlPage.'/manage/'.base64_encode($value[$item->title_column]).'" target="_blank" class="badge badge-default border rounded bg-indigo">'.urldecode($arrayRelation[array_keys($arrayRelation)[1]]).'</a>';

									}else{

										$HTMLTable .= $value[$item->title_column]; 

									}

								/*=============================================
								Order-type content
								=============================================*/

								}else if($item->type_column == "order"){

									$HTMLTable .= '<input type="number" class="form-control form-control-sm rounded changeOrder" value="'.$value[$item->title_column].'" style="width:55px" idItem="'.base64_encode($value["id_".$module->suffix_module]).'" table="'.$module->title_module.'" suffix="'.$module->suffix_module.'" column="'.$item->title_column.'">';

								}else{

	        						$HTMLTable .= TemplateController::reduceText(urldecode($value[$item->title_column]),25); 

	        					}

	        					$HTMLTable .= '</td>';

	        				}		
			
						}

				 		if ($this->rolAdmin == "superadmin" || $module->editable_module == 1){

							$HTMLTable .= '<td class="text-center">
		    					<a href="'.$cmsBasePath.'/'.$module->url_page.'/manage/'.base64_encode($value["id_".$module->suffix_module]).'/copy" class="btn btn-sm text-dark rounded m-0 p-0 border-0">
		    						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-copy" viewBox="0 0 16 16">
									  <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>
									</svg>
		    					</a>
			    					<a href="'.$cmsBasePath.'/'.$module->url_page.'/manage/'.base64_encode($value["id_".$module->suffix_module]).'" class="btn btn-sm text-primary rounded m-0 p-0 border-0">
			    						<i class="bi bi-pencil-square"></i>
			    					</a>
			    					<button type="button" class="btn btn-sm text-maroon rounded m-0 p-0 border-0 deleteItem" idItem="'.base64_encode($value["id_".$module->suffix_module]).'" table="'.$module->title_module.'" suffix="'.$module->suffix_module.'">
			    						<i class="bi bi-trash"></i>
			    					</button>
			    				</td>';

    					}

				$HTMLTable .= '</tr>';

		
			}

    	}

    	$response = array(

    		"HTMLTable" => $HTMLTable,
    		"totalData" => $totalData,
    		"totalPages" => $totalPages
    	);

    	echo json_encode($response);


	}

	/*=============================================
    Toggle boolean state
    =============================================*/
    public $boolChange;
    public $idItemChange;
    public $tableChange;
	public $suffixChange;
	public $columnChange;

	public function changeBooleanItems(){

		$idItems = explode(",", $this->idItemChange);
		$countChange = 0;

		foreach ($idItems as $key => $value) {

			if($this->boolChange == "false" || $this->boolChange == 0){

    			$this->boolChange = 0;
    		
    		}else{

    			$this->boolChange = 1;
    		}

    		$url = $this->tableChange."?id=".(int)base64_decode($value, true)."&nameId=id_".$this->suffixChange."&token=".$this->token."&table=admins&suffix=admin";
    		$method = "PUT";
    		$fields = $this->columnChange."=".$this->boolChange;

    		$updateItem = CurlController::request($url,$method,$fields);

    		if($updateItem->status == 200){

    			$countChange++;
    		}

		}

		echo $countChange == count($idItems) ? 200 : 500;
	}

	/*=============================================
    Change selection
    =============================================*/
    public $itemSelect;
    public $idItemSelect;
    public $tableSelect;
	public $suffixSelect;
	public $columnSelect;

	public function changeSelectItems(){

		$idItems = explode(",", $this->idItemSelect);
		$countSelect = 0;

		foreach ($idItems as $key => $value) {

    		$url = $this->tableSelect."?id=".(int)base64_decode($value, true)."&nameId=id_".$this->suffixSelect."&token=".$this->token."&table=admins&suffix=admin";
    		$method = "PUT";
    		$fields = $this->columnSelect."=".$this->itemSelect;

    		$updateItem = CurlController::request($url,$method,$fields);

    		if($updateItem->status == 200){

    			$countSelect++;
    		}

		}

		echo $countSelect == count($idItems) ? 200 : 500;
	}

	/*=============================================
    Change order
    =============================================*/

    public $numOrder;
    public $idItemOrder;
    public $tableOrder;
	public $suffixOrder;
	public $columnOrder;

	public function changeOrderItems(){

		$url = $this->tableOrder."?id=".(int)base64_decode($this->idItemOrder, true)."&nameId=id_".$this->suffixOrder."&token=".$this->token."&table=admins&suffix=admin";
		$method = "PUT";
		$fields = $this->columnOrder."=".$this->numOrder;

		$updateItem = CurlController::request($url,$method,$fields);

		if($updateItem->status == 200){

			echo 200;

		}else{

			echo 500;
		}

	}

}

/*=============================================
POST variables
=============================================*/ 

if(isset($_POST["idItemDelete"])){

	$ajax = new DynamicTablesController();
    $ajax -> idItemDelete = $_POST["idItemDelete"];
    $ajax -> tableDelete = $_POST["tableDelete"];
    $ajax -> suffixDelete = $_POST["suffixDelete"];
    $ajax -> token = $_POST["token"];  
    $ajax -> deleteItems();

}

/*=============================================
Return filtered table
=============================================*/

if(isset($_POST["contentModule"])){

	$ajax = new DynamicTablesController();
    $ajax -> contentModule = $_POST["contentModule"];
    $ajax -> orderBy = $_POST["orderBy"];  
    $ajax -> orderMode = $_POST["orderMode"]; 
    $ajax -> limit = $_POST["limit"]; 
    $ajax -> page = $_POST["page"];
    $ajax -> rolAdmin = $_POST["rolAdmin"];  
    $ajax -> search = $_POST["search"];  
    $ajax -> between1 = $_POST["between1"];  
    $ajax -> between2 = $_POST["between2"];  
    $ajax -> loadAjaxTable();

}


/*=============================================
Toggle boolean state
=============================================*/

if(isset($_POST["tableChange"])){

    $ajax = new DynamicTablesController();
    $ajax -> boolChange = $_POST["boolChange"];
    $ajax -> idItemChange = $_POST["idItemChange"];
    $ajax -> tableChange = $_POST["tableChange"];
    $ajax -> suffixChange = $_POST["suffixChange"];
    $ajax -> columnChange = $_POST["columnChange"];
    $ajax -> token = $_POST["token"];  
    $ajax -> changeBooleanItems();

}

/*=============================================
Change selection
=============================================*/

if(isset($_POST["tableSelect"])){

    $ajax = new DynamicTablesController();
    $ajax -> itemSelect = $_POST["itemSelect"];
    $ajax -> idItemSelect = $_POST["idItemSelect"];
    $ajax -> tableSelect = $_POST["tableSelect"];
    $ajax -> suffixSelect = $_POST["suffixSelect"];
    $ajax -> columnSelect = $_POST["columnSelect"];
    $ajax -> token = $_POST["token"];  
    $ajax -> changeSelectItems();

}

/*=============================================
Change order
=============================================*/

if(isset($_POST["tableOrder"])){

    $ajax = new DynamicTablesController();
    $ajax -> numOrder = $_POST["numOrder"];
    $ajax -> idItemOrder = $_POST["idItemOrder"];
    $ajax -> tableOrder = $_POST["tableOrder"];
    $ajax -> suffixOrder = $_POST["suffixOrder"];
    $ajax -> columnOrder = $_POST["columnOrder"];
    $ajax -> token = $_POST["token"];  
    $ajax -> changeOrderItems();

}


