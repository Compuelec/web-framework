<?php 

require_once "../controllers/curl.controller.php";


class PagesAjax{

	/*=============================================
	Cambiar el orden de página
	=============================================*/ 

	public $idPage;
	public $index; 
	public $token; 

	public function updatePageOrder(){

		$url = "pages?id=".base64_decode($this->idPage)."&nameId=id_page&token=".$this->token."&table=admins&suffix=admin";
		$method = "PUT";
		$fields = "order_page=".$this->index;

		$updateOrder = CurlController::request($url,$method,$fields);

		if($updateOrder->status == 200){

			echo $updateOrder->status;
		
		}

	}

	/*=============================================
	Eliminar Página
	=============================================*/ 

	public $idPageDelete;

	public function deletePage(){

		/*=============================================
		Validar módulos vinculados a la página
		=============================================*/

		$url = "modules?linkTo=id_page_module&equalTo=".base64_decode($this->idPageDelete);
		$method = "GET";
		$fields = array();

		$getModule = CurlController::request($url,$method,$fields);

		if($getModule->status == 200){

			echo "error";
		
		}else{

			$url = "pages?id=".base64_decode($this->idPageDelete)."&nameId=id_page&token=".$this->token."&table=admins&suffix=admin";
			$method = "DELETE";
			$fields = array();

			$deletePage = CurlController::request($url,$method,$fields);

			if($deletePage->status == 200){

				echo $deletePage->status;
			}

		}

	}

	/*=============================================
	Get Menu Pages
	Get pages with type "menu" for parent page selector
	=============================================*/ 

	public $currentPageId;

	public function getMenuPages(){

		$url = "pages?linkTo=type_page&equalTo=menu&orderBy=title_page&orderMode=ASC&select=id_page,title_page";
		$method = "GET";
		$fields = array();

		$menuPages = CurlController::request($url,$method,$fields);

		header('Content-Type: application/json');

		if($menuPages->status == 200){

			$results = $menuPages->results;
			
			// Filter out current page if editing
			if($this->currentPageId){
				$results = array_filter($results, function($page) {
					return $page->id_page != $this->currentPageId;
				});
				$results = array_values($results); // Re-index array
			}

			echo json_encode(array(
				'status' => 200,
				'results' => $results
			));
		
		}else{

			echo json_encode(array(
				'status' => 200,
				'results' => array()
			));

		}

	}

	/*=============================================
	Check if Plugin Page Exists
	Check if a plugin already has a page created
	=============================================*/ 

	public $pluginUrl;

	public function checkPluginExists(){

		require_once __DIR__ . "/../../plugins/plugins-registry.php";

		header('Content-Type: application/json');

		if(empty($this->pluginUrl)){
			echo json_encode(array(
				'exists' => false
			));
			return;
		}

		$exists = PluginsRegistry::pluginPageExists($this->pluginUrl);

		echo json_encode(array(
			'exists' => $exists
		));

	}

	/*=============================================
	Get Available Plugins
	Get list of all registered plugins
	=============================================*/ 

	public function getAvailablePlugins(){

		require_once __DIR__ . "/../../plugins/plugins-registry.php";

		header('Content-Type: application/json');

		$allPlugins = PluginsRegistry::getAllPlugins();
		$availablePlugins = array();

		foreach($allPlugins as $pluginName => $pluginConfig){
			// Check if plugin page already exists
			$pluginUrl = $pluginConfig['url'] ?? $pluginName;
			$pageExists = PluginsRegistry::pluginPageExists($pluginUrl);
			
			if(!$pageExists){
				$availablePlugins[] = array(
					'name' => $pluginName,
					'url' => $pluginUrl,
					'displayName' => $pluginConfig['name'] ?? $pluginName,
					'description' => $pluginConfig['description'] ?? '',
					'icon' => $pluginConfig['icon'] ?? 'bi-gear',
					'type' => $pluginConfig['type'] ?? 'general',
					'version' => $pluginConfig['version'] ?? '',
					'author' => $pluginConfig['author'] ?? ''
				);
			}
		}

		echo json_encode(array(
			'status' => 200,
			'results' => $availablePlugins
		));

	}

}

if(isset($_POST["idPage"])){

	$ajax = new PagesAjax();
	$ajax -> idPage = $_POST["idPage"];
	$ajax -> index = $_POST["index"];
	$ajax -> token = $_POST["token"];
	$ajax -> updatePageOrder();
}



if(isset($_POST["idPageDelete"])){

	$ajax = new PagesAjax();
	$ajax -> idPageDelete = $_POST["idPageDelete"];
	$ajax -> token = $_POST["token"];
	$ajax -> deletePage();
}

if(isset($_POST["getMenuPages"])){

	$ajax = new PagesAjax();
	$ajax -> currentPageId = isset($_POST["currentPageId"]) ? $_POST["currentPageId"] : null;
	$ajax -> getMenuPages();
}

if(isset($_POST["checkPluginExists"])){

	$ajax = new PagesAjax();
	$ajax -> pluginUrl = isset($_POST["pluginUrl"]) ? $_POST["pluginUrl"] : '';
	$ajax -> checkPluginExists();
}

if(isset($_POST["getAvailablePlugins"])){

	$ajax = new PagesAjax();
	$ajax -> getAvailablePlugins();
}