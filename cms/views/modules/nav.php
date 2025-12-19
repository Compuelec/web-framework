<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom d-lg-flex justify-content-lg-between">
					
	<div>
		<button class="btn btn-default border-0" id="menu-toggle">
			<i class="bi bi-list"></i>
		</button>
	</div>

	<div class="d-flex">

		<?php
		// Check for updates (only for superadmin and admin)
		if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && ($_SESSION["admin"]->rol_admin == "superadmin" || $_SESSION["admin"]->rol_admin == "admin")) {
			try {
				require_once __DIR__ . '/../../controllers/updates.controller.php';
				$updateCheck = UpdatesController::checkForUpdates();
				if (isset($updateCheck['update_available']) && $updateCheck['update_available']) {
		?>
			<div class="p-2 position-relative">
				<a href="<?php echo $cmsBasePath ?>/updates" class="text-warning" title="Actualización disponible">
					<i class="bi bi-bell-fill"></i>
					<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
						1
					</span>
				</a>
			</div>
		<?php
				}
			} catch (Exception $e) {
				// Silently fail - don't break the page if update check fails
				error_log("Error checking for updates: " . $e->getMessage());
			}
		}
		?>

		<div class="p-2">

			<a href="#myProfile" data-bs-toggle="modal" style="color:inherit;">
				<i class="bi bi-person-circle"></i>
				<?php echo isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) ? $_SESSION["admin"]->rol_admin : 'guest' ?>
			</a>

		</div>

		<div class="p-2 mx-2">
			
			<a href="<?php echo $cmsBasePath ?>/logout" class="text-dark">				
				<i class="bi bi-box-arrow-right"></i>
			</a>

		</div>

	</div>

</nav>