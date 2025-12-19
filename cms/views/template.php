<?php 

/*=============================================
Iniciar variables de sesión
=============================================*/

ob_start();
session_start();

/*=============================================
Base Path del CMS (ej: /chatcenter/cms)
=============================================*/

$cmsBasePath = TemplateController::cmsBasePath();

/*=============================================
Capturar parámetros de la url
=============================================*/

$requestPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

/* Remover el prefijo del CMS del request para rutear correctamente */
if($cmsBasePath !== "" && strpos($requestPath, $cmsBasePath) === 0){
	$requestPath = substr($requestPath, strlen($cmsBasePath));
}

$routesArray = explode("/", $requestPath);

array_shift($routesArray);

foreach ($routesArray as $key => $value) {
	
	$routesArray[$key] = explode("?",$value)[0];
}

/*=============================================
Validar si existe la base de datos con la tabla admins
=============================================*/

$url = "admins";
$method = "GET";
$fields = array();

$adminTable = CurlController::request($url,$method,$fields);

// echo '<pre>$adminTable '; print_r($adminTable); echo '</pre>';

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

// echo '<pre>$admin '; print_r($admin); echo '</pre>';

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
	Validamos si admin existe
	===============================================-->

	<?php if (!empty($admin)): ?>

		<!--=============================================
		Título del Dashboard
		===============================================-->

		<title><?php echo $admin->title_admin ?></title>

		<!--=============================================
		Típografía del dashboard
		===============================================-->

		<?php if ($admin->font_admin != null): ?>

			<style>
				<?php echo $admin->font_admin ?>
			</style>

		<?php endif ?>

		<!--=============================================
		Estilos propios del dashboard
		===============================================-->

		<style>
			
			/*=============================================
			Típografía del dashboard
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
			Color del dashboard
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
	</script>

	<script src="<?php echo $cmsBasePath ?>/views/assets/js/alerts/alerts.js"></script>

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
		PLANTILLA DASHBOARD
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
						Validar permisos
						===========================================-->

						<?php if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && ($_SESSION["admin"]->rol_admin == "superadmin" || $_SESSION["admin"]->rol_admin == "admin" || ($_SESSION["admin"]->rol_admin == "editor" && isset($_SESSION["admin"]->permissions_admin) && isset(json_decode(urldecode($_SESSION["admin"]->permissions_admin), true)[$routesArray[0]]) && json_decode(urldecode($_SESSION["admin"]->permissions_admin), true)[$routesArray[0]] == "on"))): ?>

							<!--=========================================
							Agregamos páginas dinámicas y personalizadas
							===========================================-->

							<?php 

								$url = "pages?linkTo=url_page&equalTo=".$routesArray[0];
								$method = "GET";
								$fields = array();

								$page = CurlController::request($url,$method,$fields);
								
								// Check if page is not null and has valid structure
								if($page !== null && is_object($page) && isset($page->status) && $page->status == 200 && isset($page->results) && is_array($page->results) && count($page->results) > 0 && isset($page->results[0]->type_page)){
									
									if($page->results[0]->type_page == "modules"){

										include "pages/dynamic/dynamic.php";
									
									}else if($page->results[0]->type_page == "custom"){

										include "pages/custom/".$routesArray[0]."/".$routesArray[0].".php";
									
									}else{

										include "pages/404/404.php";
									
									}
								
								}else{

									include "pages/404/404.php";
								
								}

							?>

						<?php else: ?>

							<?php include "pages/404/404.php"; ?>

						<?php endif ?>
						
					<?php endif ?>

				<?php else: ?>


					<!--=========================================
				 	Validar permisos para super y admins
					===========================================-->

					<?php if ($_SESSION["admin"]->rol_admin == "superadmin" || $_SESSION["admin"]->rol_admin == "admin"): ?>

						<!--=========================================
						Agregamos la página inicial
						===========================================-->

						<?php 

							$url = "pages?linkTo=order_page&equalTo=1";
							$method = "GET";
							$fields = array();

							$page = CurlController::request($url,$method,$fields);

							if($page->status == 200 && $page->results[0]->type_page == "modules"){

								include "pages/dynamic/dynamic.php";
							
							}else if($page->status == 200 && $page->results[0]->type_page == "custom"){

								include "pages/custom/".$page->results[0]->url_page."/".$page->results[0]->url_page.".php";
							
							}else{

								include "pages/404/404.php";
							
							}
						
						?>

					<?php else: ?>

					<!--=========================================
				 	Validar permisos para editores
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

									include "pages/custom/".$page->results[0]->url_page."/".$page->results[0]->url_page.".php";
								
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
    	Incluimos modal de perfiles
    	=============================================*/

    	include "modules/modals/profile.php"; 
		require_once "controllers/admins.controller.php";
		$update = new AdminsController();
	    $update->updateAdmin();

	    if($_SESSION["admin"]->rol_admin == "superadmin"){

	    	/*=============================================
	    	Incluimos modal de páginas
	    	=============================================*/

		    include "views/modules/modals/pages.php";

		    require_once "controllers/pages.controller.php";
			$managePage = new PagesController();
		    $managePage->managePage();

		    /*=============================================
	    	Incluimos modal de módulos
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
	
	<!-- Nuevas funcionalidades mejoradas -->
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/search/global-search.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/export/export-data.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/notifications/notifications.js"></script>
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/performance/performance.js"></script>
		
<?php endif ?>
		
	<script src="<?php echo $cmsBasePath ?>/views/assets/js/forms/forms.js"></script>
	
	
</body>
</html>