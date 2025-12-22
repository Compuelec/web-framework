<?php 

$url = "pages?orderBy=order_page&orderMode=ASC";
$method = "GET";
$fields = array();

$pages  = CurlController::request($url,$method,$fields);

if($pages->status == 200){

	$allPages = $pages->results;
	
	// Organize pages: main pages (parent_page = 0 or null) and subpages
	$mainPages = array();
	$subPages = array();
	
	foreach($allPages as $page) {
		$parentId = isset($page->parent_page) && $page->parent_page > 0 ? $page->parent_page : 0;
		
		if($parentId == 0) {
			$mainPages[] = $page;
		} else {
			if(!isset($subPages[$parentId])) {
				$subPages[$parentId] = array();
			}
			$subPages[$parentId][] = $page;
		}
	}
	
	$pages = $mainPages;

}else{

	$pages = array();
	$subPages = array();
	
}

?>

<div class="bg-white shadow" id="sidebar-wrapper">

	<div class="sidebar-heading bg-white text-dark my-2">
		<i class="<?php echo $_SESSION["admin"]->symbol_admin ?> textColor"></i>
		<span class="menu-text"><?php echo $_SESSION["admin"]->title_admin ?></span>
	</div>

	<hr class="mt-0 borderDashboard">

	<ul class="list-group list-group-flush" id="sortable">

		<?php if (!empty($pages)): ?>

			<?php foreach ($pages as $key => $value): ?>


				<?php if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && ($_SESSION["admin"]->rol_admin == "superadmin" || $_SESSION["admin"]->rol_admin == "admin" || ($_SESSION["admin"]->rol_admin == "editor" && isset($_SESSION["admin"]->permissions_admin) && isset(json_decode(urldecode($_SESSION["admin"]->permissions_admin), true)[$value->url_page]) && json_decode(urldecode($_SESSION["admin"]->permissions_admin), true)[$value->url_page] == "on"))): ?>

				<?php 
					// Check if this is a menu page with subpages
					$hasSubPages = isset($subPages[$value->id_page]) && count($subPages[$value->id_page]) > 0;
					$isMenuPage = $value->type_page == "menu";
				?>

				<li class="list-group-item position-relative <?php echo $isMenuPage ? 'menu-item' : '' ?>" idPage="<?php echo base64_encode($value->id_page) ?>">
					
					<?php if ($isMenuPage && isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && $_SESSION["admin"]->rol_admin == "superadmin"): ?>
						<span class="handle page-drag-handle" style="cursor:move; opacity: 0; transition: opacity 0.2s ease;">
							<i class="bi bi-arrows-move"></i>
						</span>
					<?php endif ?>

				<?php if ($isMenuPage): ?>
					
					<!-- Menu page: not clickable, acts as dropdown trigger -->
					<div class="menu-toggle bg-transparent text-dark d-flex align-items-center" style="cursor: pointer; position: relative;" data-menu-id="<?php echo $value->id_page ?>">
						<i class="<?php echo $value->icon_page ?> textColor"></i> 
						<span class="menu-text flex-grow-1" style="margin-right: 8px;"><?php echo $value->title_page ?></span>
						<i class="bi bi-chevron-down menu-arrow textColor" style="transition: transform 0.3s; width: 16px; flex-shrink: 0; pointer-events: none;"></i>
						
						<?php if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && $_SESSION["admin"]->rol_admin == "superadmin"): ?>
							<div class="page-actions-wrapper">
								<button type="button" class="btn btn-sm text-muted rounded page-menu-toggle" data-page-id="<?php echo base64_encode($value->id_page) ?>" style="padding: 0.25rem 0.5rem;">
									<i class="bi bi-three-dots-vertical"></i>
								</button>
								
								<div class="page-actions-menu" id="menu-<?php echo base64_encode($value->id_page) ?>" style="display: none;">
									<button type="button" class="page-action-item myPage" page='<?php echo json_encode($value) ?>'>
										<i class="bi bi-pencil-square"></i>
										<span>Editar</span>
									</button>
									<button type="button" class="page-action-item deletePage" idPage="<?php echo base64_encode($value->id_page) ?>">
										<i class="bi bi-trash"></i>
										<span>Eliminar</span>
									</button>
								</div>
							</div>
						<?php endif ?>
					</div>
						
						<!-- Subpages container (hidden by default) -->
						<?php if ($hasSubPages): ?>
							<ul class="submenu" id="submenu-<?php echo $value->id_page ?>" style="display: none;">
								<?php foreach ($subPages[$value->id_page] as $subPage): ?>
									<?php 
										// Check permissions for subpage
										$hasPermission = true;
										if($_SESSION["admin"]->rol_admin == "editor") {
											$permissions = json_decode(urldecode($_SESSION["admin"]->permissions_admin), true);
											$hasPermission = isset($permissions[$subPage->url_page]) && $permissions[$subPage->url_page] == "on";
										}
									?>
									<?php if ($hasPermission): ?>
										<li class="submenu-item position-relative" idPage="<?php echo base64_encode($subPage->id_page) ?>">
											<?php if ($subPage->type_page == "external_link" || $subPage->type_page == "internal_link"): ?>
												<a class="submenu-link" href="<?php echo urldecode($subPage->url_page) ?>" <?php if ($subPage->type_page == "external_link"): ?>  target="_blank" <?php endif ?>>
											<?php else: ?>
												<a class="submenu-link" href="<?php echo $cmsBasePath ?>/<?php echo $subPage->url_page ?>">
											<?php endif ?>
												<i class="<?php echo $subPage->icon_page ?>"></i> 
												<span class="menu-text"><?php echo $subPage->title_page ?></span>
											</a>
											
											<?php if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && $_SESSION["admin"]->rol_admin == "superadmin"): ?>
												<span class="handle page-drag-handle" style="cursor:move; opacity: 0; transition: opacity 0.2s ease;">
													<i class="bi bi-arrows-move"></i>
												</span>
												
												<div class="page-actions-wrapper">
													<button type="button" class="btn btn-sm text-muted rounded page-menu-toggle" data-page-id="<?php echo base64_encode($subPage->id_page) ?>" style="padding: 0.25rem 0.5rem;">
														<i class="bi bi-three-dots-vertical"></i>
													</button>
													
													<div class="page-actions-menu" id="menu-<?php echo base64_encode($subPage->id_page) ?>" style="display: none;">
														<button type="button" class="page-action-item myPage" page='<?php echo json_encode($subPage) ?>'>
															<i class="bi bi-pencil-square"></i>
															<span>Editar</span>
														</button>
														<button type="button" class="page-action-item deletePage" idPage="<?php echo base64_encode($subPage->id_page) ?>">
															<i class="bi bi-trash"></i>
															<span>Eliminar</span>
														</button>
													</div>
												</div>
											<?php endif ?>
										</li>
									<?php endif ?>
								<?php endforeach ?>
							</ul>
						<?php endif ?>

					<?php else: ?>

						<!-- Regular page: clickable link -->
						<?php if ($value->type_page == "external_link" || $value->type_page == "internal_link"): ?>
							<a class="bg-transparent text-dark" href="<?php echo urldecode($value->url_page) ?>" <?php if ($value->type_page == "external_link"): ?>  target="_blank" <?php endif ?>>
						<?php else: ?>
						<a class="bg-transparent text-dark" href="<?php echo $cmsBasePath ?>/<?php echo $value->url_page ?>">
						<?php endif ?>
							<i class="<?php echo $value->icon_page ?> textColor"></i> 
							<span class="menu-text"><?php echo $value->title_page ?></span>
						</a>
						
					<?php endif ?>
	
				 	<?php if (!$isMenuPage && isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && $_SESSION["admin"]->rol_admin == "superadmin"): ?>

				 		<span class="handle page-drag-handle" style="cursor:move; opacity: 0; transition: opacity 0.2s ease;">
				 			<i class="bi bi-arrows-move"></i>
				 			</span>

				 		<div class="page-actions-wrapper">
				 			<button type="button" class="btn btn-sm text-muted rounded page-menu-toggle" data-page-id="<?php echo base64_encode($value->id_page) ?>" style="padding: 0.25rem 0.5rem;">
				 				<i class="bi bi-three-dots-vertical"></i>
				 			</button>

				 			<div class="page-actions-menu" id="menu-<?php echo base64_encode($value->id_page) ?>" style="display: none;">
				 				<button type="button" class="page-action-item myPage" page='<?php echo json_encode($value) ?>'>
				 					<i class="bi bi-pencil-square"></i>
				 					<span>Editar</span>
				 				</button>
				 				<button type="button" class="page-action-item deletePage" idPage="<?php echo base64_encode($value->id_page) ?>">
				 					<i class="bi bi-trash"></i>
				 					<span>Eliminar</span>
				 			</button>
				 			</div>
				 		</div>

				 	<?php endif ?>

				</li>

				<?php endif ?>
				
			<?php endforeach ?>
			
		<?php endif ?>

	</ul>

	<?php if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && $_SESSION["admin"]->rol_admin == "superadmin"): ?>

		<hr class="borderDashboard">

		<button class="btn btn-default border rounded btn-sm ms-3 menu-text mt-2 myPage">Agregar Página</button>
		
	<?php endif ?>

</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
	// Get current page URL to highlight active menu items
	var currentPath = window.location.pathname;
	var cmsBasePath = '<?php echo $cmsBasePath; ?>';
	var currentPage = currentPath.replace(cmsBasePath + '/', '').split('/')[0];
	
	// Highlight active menu items
	document.querySelectorAll('#sidebar-wrapper a.bg-transparent, .submenu-link').forEach(function(link) {
		var href = link.getAttribute('href');
		if (href) {
			var linkPage = href.split('/').pop().split('?')[0];
			if (linkPage === currentPage || (currentPage === '' && linkPage === 'inicio')) {
				link.classList.add('active');
				// If it's a submenu item, expand parent menu
				var submenuItem = link.closest('.submenu-item');
				if (submenuItem) {
					var submenu = submenuItem.closest('.sidebar-submenu');
					if (submenu) {
						var menuId = submenu.id.replace('submenu-', '');
						var menuToggle = document.querySelector('.menu-toggle[data-menu-id="' + menuId + '"]');
						if (menuToggle) {
							var menuItem = menuToggle.closest('.menu-item');
							if (menuItem) {
								submenu.style.display = 'block';
								submenu.style.maxHeight = submenu.scrollHeight + 'px';
								menuItem.classList.add('active');
							}
						}
					}
				}
			}
		}
	});
	
	// Handle menu toggle clicks with smooth animation
	document.querySelectorAll('.menu-toggle').forEach(function(toggle) {
		toggle.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			var menuId = this.getAttribute('data-menu-id');
			var submenu = document.getElementById('submenu-' + menuId);
			var menuItem = this.closest('.menu-item');
			
			if (submenu) {
				var isExpanded = submenu.style.display === 'block' || menuItem.classList.contains('active');
				
				if (isExpanded) {
					// Collapse
					submenu.style.display = 'none';
					menuItem.classList.remove('active');
				} else {
					// Expand
					submenu.style.display = 'block';
					menuItem.classList.add('active');
					
					// Initialize sortable for submenu after it's visible
					if (typeof window.initSubmenuSortables === 'function') {
						setTimeout(function() {
							window.initSubmenuSortables();
						}, 200);
					}
				}
			}
		});
	});
	
	// Handle three dots menu toggle
	document.querySelectorAll('.page-menu-toggle').forEach(function(toggle) {
		toggle.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			var menuId = this.getAttribute('data-page-id');
			var menu = document.getElementById('menu-' + menuId);
			
			// Close all other menus
			document.querySelectorAll('.page-actions-menu').forEach(function(otherMenu) {
				if (otherMenu !== menu && otherMenu.style.display !== 'none') {
					otherMenu.style.display = 'none';
					otherMenu.style.position = 'absolute';
					otherMenu.style.right = '0';
					otherMenu.style.top = '100%';
				}
			});
			
			// Toggle current menu
			if (menu) {
				if (menu.style.display === 'none' || menu.style.display === '') {
					// Lower z-index of other action wrappers to prevent overlap
					document.querySelectorAll('.page-actions-wrapper').forEach(function(wrapper) {
						if (wrapper !== toggle.closest('.page-actions-wrapper')) {
							wrapper.style.zIndex = '1';
						}
					});
					
					var wrapper = toggle.closest('.page-actions-wrapper');
					var wrapperRect = wrapper.getBoundingClientRect();
					var viewportHeight = window.innerHeight;
					
					// Estimate menu height (approximately 80px for 2 items)
					var estimatedMenuHeight = 80;
					
					// Check if menu would be cut off at bottom
					var spaceBelow = viewportHeight - wrapperRect.bottom;
					var spaceAbove = wrapperRect.top;
					
					// Use fixed positioning if menu would be cut off
					if (spaceBelow < estimatedMenuHeight + 10 && spaceAbove > estimatedMenuHeight) {
						// Open upward using fixed positioning
						menu.style.position = 'fixed';
						menu.style.right = (window.innerWidth - wrapperRect.right) + 'px';
						menu.style.bottom = (viewportHeight - wrapperRect.top + 4) + 'px';
						menu.style.top = 'auto';
						menu.style.left = 'auto';
						menu.style.marginTop = '0';
					} else {
						// Open downward (normal)
						menu.style.position = 'absolute';
						menu.style.right = '0';
						menu.style.top = '100%';
						menu.style.left = 'auto';
						menu.style.bottom = 'auto';
						menu.style.marginTop = '4px';
					}
					
					menu.style.display = 'block';
				} else {
					menu.style.display = 'none';
					// Restore z-index of all action wrappers
					document.querySelectorAll('.page-actions-wrapper').forEach(function(wrapper) {
						wrapper.style.zIndex = '';
					});
				}
			}
		});
	});
	
	// Close menus when clicking outside
	document.addEventListener('click', function(e) {
		if (!e.target.closest('.page-actions-wrapper')) {
			document.querySelectorAll('.page-actions-menu').forEach(function(menu) {
				menu.style.display = 'none';
			});
			// Restore z-index of all action wrappers
			document.querySelectorAll('.page-actions-wrapper').forEach(function(wrapper) {
				wrapper.style.zIndex = '';
			});
		}
	});
	
});
</script>