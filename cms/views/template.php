<?php 

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . "/../controllers/session.controller.php";
require_once __DIR__ . "/../controllers/template.controller.php";
require_once __DIR__ . "/../../api/models/connection.php";

// Validate token if admin session exists
if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"])) {
    $currentUserId = $_SESSION["admin"]->id_admin ?? null;
    $currentUserToken = $_SESSION["admin"]->token_admin ?? null;
    
    // Validate token expiration
    if (!empty($currentUserToken)) {
        $tokenValidation = Connection::tokenValidate($currentUserToken, "admins", "admin");
        
        if ($tokenValidation == "expired" || $tokenValidation == "no-auth") {
            // Token expired or invalid - destroy session and redirect to login
            session_destroy();
            session_start();
            
            // Redirect to login
            $cmsBasePath = TemplateController::cmsBasePath();
            header("Location: " . $cmsBasePath . "/login");
            exit();
        }
    }
    
    SessionController::startUniqueSession($currentUserId, $currentUserToken);
}

$cmsBasePath = TemplateController::cmsBasePath();

$requestPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if($cmsBasePath !== "" && strpos($requestPath, $cmsBasePath) === 0){
	$requestPath = substr($requestPath, strlen($cmsBasePath));
}

$routesArray = explode("/", $requestPath);

array_shift($routesArray);

foreach ($routesArray as $key => $value) {
	
	$routesArray[$key] = explode("?",$value)[0];
}


$url = "admins";
$method = "GET";
$fields = array();

$adminTable = CurlController::request($url,$method,$fields);

// Initialize admin as null
$admin = null;

// Check if adminTable is not null and has the expected structure
if($adminTable !== null && is_object($adminTable)){
	
	if(isset($adminTable->status) && $adminTable->status == 404){
		
		$admin = null;
		
	}else if(isset($adminTable->status) && $adminTable->status == 200){
		
		// Check if results exists and has at least one element
		if(isset($adminTable->results) && is_array($adminTable->results) && count($adminTable->results) > 0){
			$admin = $adminTable->results[0];
		}
		
	}
	
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="https://cdn-icons-png.flaticon.com/512/9966/9966194.png">

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

	<!--=============================================
	Validate if admin exists
	===============================================-->

	<?php if (!empty($admin)): ?>

		<!--=============================================
		Dashboard Title
		===============================================-->

		<title><?php echo $admin->title_admin ?></title>

		<!--=============================================
		Dashboard Typography
		===============================================-->

		<?php if ($admin->font_admin != null): ?>

			<style>
				<?php echo $admin->font_admin ?>
			</style>

		<?php endif ?>

		<!--=============================================
		Dashboard Custom Styles
		===============================================-->

		<style>
			
			/*=============================================
			Dashboard Typography
			=============================================*/

			<?php if ($admin->font_admin != null):?>

				body{
					<?php 
						$fontParts = explode("\n\n", $admin->font_admin);
						if(isset($fontParts[1])){
							echo $fontParts[1];
						}else{
							echo $admin->font_admin;
						}
					?>
				}

			<?php endif ?>

			/*=============================================
			Dashboard Color
			=============================================*/

			.backColor{
				background: <?php echo $admin->color_admin ?> !important;
				color: #FFF !important;
				border: 0 !important;
			}

			.form-check-input:checked{
				background-color: <?php echo $admin->color_admin ?> !important;
			    border-color: <?php echo $admin->color_admin ?> !important;
			}

			.textColor{
				color: <?php echo $admin->color_admin ?> !important;
			}

			.page-item.active .page-link {
				z-index: 3;
				color: #fff !important;
				background-color: <?php echo $admin->color_admin ?> !important;
				border-color: <?php echo $admin->color_admin ?> !important;
			}

			.page-link {
				color: <?php echo $admin->color_admin ?> !important;		
			}

		</style>

	<?php else: ?>

		<title>CMS Builder</title>

	<?php endif ?>

	<!--=============================================
	CUSTOM JS SERVER
	===============================================-->

	<script>
		window.CMS_BASE_PATH = <?php echo json_encode($cmsBasePath); ?>;
		window.CMS_AJAX_PATH = (window.CMS_BASE_PATH || "") + "/ajax";
		window.CMS_ASSETS_PATH = (window.CMS_BASE_PATH || "") + "/views/assets";
		<?php if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && isset($_SESSION["admin"]->token_admin)): ?>
		window.CMS_TOKEN = <?php echo json_encode($_SESSION["admin"]->token_admin); ?>;
		// Sync with localStorage
		if (typeof(Storage) !== "undefined") {
			localStorage.setItem("tokenAdmin", window.CMS_TOKEN);
		}
		<?php endif ?>
	</script>

	<script src="<?php echo $cmsBasePath ?>/views/assets/js/alerts/alerts.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/auth/auth-interceptor.js"></script>

	<!--=============================================
	PLUGINS CSS
	===============================================-->

	<!-- https://www.w3schools.com/bootstrap5/ -->
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/bootstrap5/bootstrap.min.css" >
	<!-- https://fontawesome.com/v5/search -->
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/fontawesome-free/css/all.min.css">
	<!-- https://icons.getbootstrap.com/ -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css">
	<!-- https://www.jqueryscript.net/demo/Google-Inbox-Style-Linear-Preloader-Plugin-with-jQuery-CSS3/#google_vignette -->
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/material-preloader/material-preloader.css">
	<!-- https://codeseven.github.io/toastr/demo.html -->
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/toastr/toastr.min.css">
	<!--  https://www.daterangepicker.com/ -->
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/daterangepicker/daterangepicker.css">
	<!-- https://bootstrap-tagsinput.github.io/bootstrap-tagsinput/examples/ -->
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/tags-input/tags-input.css">
	<!-- https://select2.org/ -->
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/select2/select2.min.css">
    <link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/select2/select2-bootstrap4.min.css">
    <!-- https://xdsoft.net/jqplugins/datetimepicker/ -->
    <link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/datetimepicker/datetimepicker.min.css">
    <!-- https://summernote.org -->	
    <link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/summernote/summernote-bs4.min.css"> 
    <link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/summernote/summernote.min.css">
    <link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/summernote/emoji.css">
    <!-- https://codemirror.net/ -->
    <link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/codemirror/codemirror.css">
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/codemirror/monokai.css">

	<!--=============================================
	PLUGINS JS
	===============================================-->

	<!-- https://jquery.com/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/jquery/jquery.min.js"></script>
	<!-- https://jqueryui.com/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/jquery-ui/jquery-ui.min.js"></script>
	<!-- https://www.w3schools.com/bootstrap5/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/bootstrap5/bootstrap.bundle.min.js"></script>
	<!-- https://sweetalert2.github.io/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/sweetalert/sweetalert.min.js"></script> 
	<!-- https://www.jqueryscript.net/demo/Google-Inbox-Style-Linear-Preloader-Plugin-with-jQuery-CSS3/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/material-preloader/material-preloader.js"></script> 
	<!-- https://codeseven.github.io/toastr/demo.html -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/toastr/toastr.min.js"></script>
	<!-- http://josecebe.github.io/twbs-pagination/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/twbs-pagination/twbs-pagination.min.js"></script> 
	<!-- https://momentjs.com/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/moment/moment.min.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/moment/moment-with-locales.min.js"></script>
	<!--  https://www.daterangepicker.com/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/daterangepicker/daterangepicker.js"></script>	
	<!-- https://bootstrap-tagsinput.github.io/bootstrap-tagsinput/examples/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/tags-input/tags-input.js"></script> 
	<!-- https://select2.org/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/select2/select2.full.min.js"></script>
	<!-- https://xdsoft.net/jqplugins/datetimepicker/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/datetimepicker/datetimepicker.full.min.js"></script>
	<!-- https://summernote.org -->	
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/summernote/summernote.min.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/summernote/summernote-bs4.js"></script>
    <script src="<?php echo $cmsBasePath ?>/views/assets/plugins/summernote/summernote-code-beautify-plugin.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/summernote/emoji.config.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/summernote/tam-emoji.min.js"></script>
	<!-- https://codemirror.net/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/codemirror/codemirror.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/codemirror/xml.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/codemirror/formatting.js"></script>
	<!-- https://www.chartjs.org/ -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/chartjs/chartjs.min.js"></script>

	<!--=============================================
	CUSTOM CSS
	===============================================-->
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/custom/custom.css">
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/dashboard/dashboard.css">
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/colors/colors.css">
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/fms/fms.css">
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/chat/chat.css">
	<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/improvements/improvements.css">


</head>
<body>

	<?php 

	if(isset($_SESSION["admin"])){
		if(!isset($_SESSION['_unique_session_info'])){
			if(is_object($_SESSION["admin"])){
				$currentUserId = $_SESSION["admin"]->id_admin ?? null;
				$currentUserToken = $_SESSION["admin"]->token_admin ?? null;
				SessionController::startUniqueSession($currentUserId, $currentUserToken);
			}
		} else {
			$isValid = SessionController::validateSession();
			
			if(!$isValid && isset($_SESSION["admin"])){
				if(is_object($_SESSION["admin"])){
					$currentUserId = $_SESSION["admin"]->id_admin ?? null;
					$currentUserToken = $_SESSION["admin"]->token_admin ?? null;
					$domain = SessionController::getDomain();
					$sessionId = substr(md5($domain), 0, 16);
					$_SESSION['_unique_session_info'] = [
						'domain' => $domain,
						'user_id' => $currentUserId,
						'session_id' => $sessionId,
						'created_at' => time()
					];
				}
			}
		}
	}

	if(!isset($_SESSION["admin"])){

		if($admin == null){

			include "pages/install/install.php";

		}else{

			include "pages/login/login.php";
		}

	}

	?>

	<?php if (isset($_SESSION["admin"])): ?>

		<?php
		// Auto-setup Activity Logs System (runs automatically on every page load)
		require_once __DIR__ . '/../controllers/activity_logs.controller.php';
		
		// Ensure table exists (creates automatically if needed)
		ActivityLogsController::getLogs([], 1, 0);
		
		// Ensure page exists in database (creates automatically if needed)
		$url = "pages?linkTo=url_page&equalTo=activity_logs";
		$method = "GET";
		$fields = array();
		$pageCheck = CurlController::request($url, $method, $fields);
		
		$pageExists = false;
		if ($pageCheck && is_object($pageCheck) && isset($pageCheck->status) && $pageCheck->status == 200) {
			if (isset($pageCheck->results) && is_array($pageCheck->results) && count($pageCheck->results) > 0) {
				$pageExists = true;
			}
		}
		
		if (!$pageExists) {
			// Create the page automatically
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
			CurlController::request($url, $method, $fields);
		}
		?>

		<!--=============================================
		DASHBOARD TEMPLATE
		===============================================-->

		<div class="d-flex backDashboard" id="wrapper">
			
			<!--=============================================
			SIDEBAR
			===============================================-->

			<?php include "modules/sidebar.php" ?>

			<div id="page-content-wrapper">
				
				<!--=============================================
				NAV
				===============================================-->

				<?php include "modules/nav.php" ?>

				<!--=============================================
				MAIN PAGE
				===============================================-->

				<?php if (!empty($routesArray[0])): ?>

					<?php if ($routesArray[0] == "logout"): ?>

						<?php include "pages/".$routesArray[0]."/".$routesArray[0].".php"; ?>

					<?php else: ?>

						<!--=========================================
						Validate permissions
						===========================================-->

						<?php if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && ($_SESSION["admin"]->rol_admin == "superadmin" || $_SESSION["admin"]->rol_admin == "admin" || ($_SESSION["admin"]->rol_admin == "editor" && isset($_SESSION["admin"]->permissions_admin) && isset(json_decode(urldecode($_SESSION["admin"]->permissions_admin), true)[$routesArray[0]]) && json_decode(urldecode($_SESSION["admin"]->permissions_admin), true)[$routesArray[0]] == "on"))): ?>

							<!--=========================================
							Add dynamic and custom pages
							===========================================-->

							<?php 

								$url = "pages?linkTo=url_page&equalTo=".$routesArray[0];
								$method = "GET";
								$fields = array();

								$page = CurlController::request($url,$method,$fields);
								
								// Check if page is not null and has valid structure
								if($page !== null && is_object($page) && isset($page->status) && $page->status == 200 && isset($page->results) && is_array($page->results) && count($page->results) > 0 && isset($page->results[0]->type_page)){
									
									// Menu pages are not navigable - redirect to first subpage or show 404
									if($page->results[0]->type_page == "menu"){
										
										// Try to get first subpage
										$url = "pages?linkTo=parent_page&equalTo=".$page->results[0]->id_page."&orderBy=order_page&orderMode=ASC&limit=1";
										$subPageRequest = CurlController::request($url, "GET", array());
										
										if($subPageRequest->status == 200 && isset($subPageRequest->results) && count($subPageRequest->results) > 0){
											// Redirect to first subpage
											$firstSubPage = $subPageRequest->results[0];
											header("Location: ".$cmsBasePath."/".$firstSubPage->url_page);
											exit;
										} else {
											// No subpages, show 404
											include "pages/404/404.php";
										}
									
									}else if($page->results[0]->type_page == "modules"){

										include "pages/dynamic/dynamic.php";
									
									}else if($page->results[0]->type_page == "custom"){

										// Check if custom page file exists, try to create it if it doesn't
										$customPagePath = "pages/custom/".$routesArray[0]."/".$routesArray[0].".php";
										$customPageFullPath = __DIR__ . "/" . $customPagePath;
										
										if (!file_exists($customPageFullPath)) {
											// Try to create using PagesSetupController
											require_once __DIR__ . "/../controllers/pages-setup.controller.php";
											PagesSetupController::ensureCustomPageFile($routesArray[0]);
										}
										
										// Only include if file exists
										if (file_exists($customPageFullPath)) {
											include $customPagePath;
										} else {
											// Show friendly error message
											echo '<div class="container-fluid p-4">';
											echo '<div class="alert alert-warning">';
											echo '<h4>Página no disponible</h4>';
											echo '<p>La página "' . htmlspecialchars($page->results[0]->title_page ?? $routesArray[0]) . '" existe en la base de datos pero el archivo no se pudo crear.</p>';
											echo '<p class="mb-0"><small>Por favor, verifica los permisos del directorio <code>cms/views/pages/custom/</code> o crea el archivo manualmente.</small></p>';
											echo '</div>';
											echo '</div>';
										}
									
									}else{

										include "pages/404/404.php";
									
									}
								
								}else{
									// Page not found in database
									// Check if URL might be a plugin that was deleted
									$isPluginUrl = false;
									if(class_exists('PluginsRegistry')){
										$isPluginUrl = PluginsRegistry::isPluginUrl($routesArray[0]);
									} else {
										$pluginsRegistryPath = __DIR__ . "/../../plugins/plugins-registry.php";
										if(file_exists($pluginsRegistryPath)){
											require_once $pluginsRegistryPath;
											if(class_exists('PluginsRegistry')){
												$isPluginUrl = PluginsRegistry::isPluginUrl($routesArray[0]);
											}
										}
									}
									
									if($isPluginUrl){
										// Show friendly message for deleted plugin page
										echo '<div class="container-fluid backgroundImage"';
										if (!empty($admin->back_admin)) {
											echo ' style="background-image: url(' . $admin->back_admin . ')"';
										}
										echo '>';
										echo '<div class="d-flex flex-wrap justify-content-center align-content-center vh-100">';
										echo '<div class="card rounded p-4 w-25 text-center" style="min-width: 320px !important;">';
										echo '<h1 class="textColor">404</h1>';
										echo '<h3><i class="fas fa-exclamation-triangle text-default textColor"></i> Página de Plugin Eliminada</h3>';
										echo '<p>La página del plugin <strong>' . htmlspecialchars($routesArray[0]) . '</strong> ha sido eliminada.</p>';
										echo '<p class="mb-0">Puedes <a href="' . $cmsBasePath . '/"><strong>regresar a la página de inicio</strong></a> o crear una nueva página para este plugin.</p>';
										echo '</div>';
										echo '</div>';
										echo '</div>';
									} else {
										include "pages/404/404.php";
									}
								}

							?>

						<?php else: ?>

							<?php include "pages/404/404.php"; ?>

						<?php endif ?>
						
					<?php endif ?>

				<?php else: ?>


					<!--=========================================
				 	Validate permissions for super and admins
					===========================================-->

					<?php if ($_SESSION["admin"]->rol_admin == "superadmin" || $_SESSION["admin"]->rol_admin == "admin"): ?>

						<!--=========================================
						Add initial page
						===========================================-->

						<?php 

							$url = "pages?linkTo=order_page&equalTo=1";
							$method = "GET";
							$fields = array();

							$page = CurlController::request($url,$method,$fields);

							if($page->status == 200 && $page->results[0]->type_page == "modules"){

								include "pages/dynamic/dynamic.php";
							
							}else if($page->status == 200 && $page->results[0]->type_page == "custom"){

								// Check if custom page file exists, try to create it if it doesn't
								$customPagePath = "pages/custom/".$page->results[0]->url_page."/".$page->results[0]->url_page.".php";
								$customPageFullPath = __DIR__ . "/" . $customPagePath;
								
								if (!file_exists($customPageFullPath)) {
									// Try to create using PagesSetupController
									require_once __DIR__ . "/../controllers/pages-setup.controller.php";
									PagesSetupController::ensureCustomPageFile($page->results[0]->url_page);
								}
								
								// Only include if file exists
								if (file_exists($customPageFullPath)) {
									include $customPagePath;
								} else {
									// Show friendly error message
									echo '<div class="container-fluid p-4">';
									echo '<div class="alert alert-warning">';
									echo '<h4>Página no disponible</h4>';
									echo '<p>La página "' . htmlspecialchars($page->results[0]->title_page ?? $page->results[0]->url_page) . '" existe en la base de datos pero el archivo no se pudo crear.</p>';
									echo '<p class="mb-0"><small>Por favor, verifica los permisos del directorio <code>cms/views/pages/custom/</code> o crea el archivo manualmente.</small></p>';
									echo '</div>';
									echo '</div>';
								}
							
							}else{

								include "pages/404/404.php";
							
							}
						
						?>

					<?php else: ?>

					<!--=========================================
				 	Validate permissions for editors
					===========================================-->

						<?php if ($_SESSION["admin"]->rol_admin == "editor"): ?>

							<?php

								$url = "pages?linkTo=url_page&equalTo=".array_keys(json_decode(urldecode($_SESSION["admin"]->permissions_admin),true))[0];
								$method = "GET";
								$fields = array();

								$page = CurlController::request($url,$method,$fields);

								$routesArray[0] = array_keys(json_decode(urldecode($_SESSION["admin"]->permissions_admin),true))[0];

								if($page->status == 200 && $page->results[0]->type_page == "modules"){

									include "pages/dynamic/dynamic.php";
								
								}else if($page->status == 200 && $page->results[0]->type_page == "custom"){

									// Check if custom page file exists, try to create it if it doesn't
									$customPagePath = "pages/custom/".$page->results[0]->url_page."/".$page->results[0]->url_page.".php";
									$customPageFullPath = __DIR__ . "/" . $customPagePath;
									
									if (!file_exists($customPageFullPath)) {
										// Try to create using PagesSetupController
										require_once __DIR__ . "/../controllers/pages-setup.controller.php";
										PagesSetupController::ensureCustomPageFile($page->results[0]->url_page);
									}
									
									// Only include if file exists
									if (file_exists($customPageFullPath)) {
										include $customPagePath;
									} else {
										// Show friendly error message
										echo '<div class="container-fluid p-4">';
										echo '<div class="alert alert-warning">';
										echo '<h4>Página no disponible</h4>';
										echo '<p>La página "' . htmlspecialchars($page->results[0]->title_page ?? $page->results[0]->url_page) . '" existe en la base de datos pero el archivo no se pudo crear.</p>';
										echo '<p class="mb-0"><small>Por favor, verifica los permisos del directorio <code>cms/views/pages/custom/</code> o crea el archivo manualmente.</small></p>';
										echo '</div>';
										echo '</div>';
									}
								
								}else{

									include "pages/404/404.php";
								
								}

							?>

						<?php endif ?>

					<?php endif ?>

				<?php endif ?>

			</div>

		</div>

		<?php 

		/*=============================================
    	Include profile modal
    	=============================================*/

    	include "modules/modals/profile.php"; 
		require_once "controllers/admins.controller.php";
		$update = new AdminsController();
	    $update->updateAdmin();

	    if(isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && $_SESSION["admin"]->rol_admin == "superadmin"){

	    	/*=============================================
	    	Include pages modal
	    	=============================================*/

		    include "views/modules/modals/pages.php";

		    require_once "controllers/pages.controller.php";
			$managePage = new PagesController();
		    $managePage->managePage();

		    /*=============================================
	    	Include modules modal
	    	=============================================*/

		    include "views/modules/modals/modules.php";

		    require_once "controllers/modules.controller.php";
			$manageModule = new ModulesController();
			$manageModule->manageModule();
   
		}

		?>

	<!--=============================================
	CUSTOM JS
	===============================================-->

	<script src="<?php echo $cmsBasePath ?>/views/assets/js/dashboard/dashboard.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/pages/pages.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/modules/modules.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/dynamic-forms/dynamic-forms.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/dynamic-tables/dynamic-tables.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/fms/fms.js"></script>
	
	<!-- New improved features -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/search/global-search.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/export/export-data.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/notifications/notifications.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/performance/performance.js"></script>
		
<?php endif ?>
		
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/forms/forms.js"></script>
	
	
</body>
</html>