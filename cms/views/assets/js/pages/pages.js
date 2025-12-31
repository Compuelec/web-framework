/*=============================================
Abrir ventana modal de páginas
=============================================*/

var CMS_AJAX_PATH = window.CMS_AJAX_PATH || "/ajax";

// Load menu pages for parent page selector
function loadMenuPages(currentPageId = null, callback = null) {
	var data = new FormData();
	data.append("getMenuPages", "1");
	if(currentPageId) {
		data.append("currentPageId", currentPageId);
	}
	
	$.ajax({
		url: CMS_AJAX_PATH + "/pages.ajax.php",
		method: "POST",
		data: data,
		contentType: false,
		cache: false,
		processData: false,
		success: function(response) {
			try {
				var data = typeof response === 'string' ? JSON.parse(response) : response;
				var parentSelect = $("#parent_page");
				parentSelect.find('option:not(:first)').remove();
				
				if(data && data.status == 200 && data.results && data.results.length > 0) {
					data.results.forEach(function(menuPage) {
						parentSelect.append(
							$('<option></option>')
								.attr('value', menuPage.id_page)
								.text(menuPage.title_page)
						);
					});
				}
				
				// Execute callback after loading options
				if(callback && typeof callback === 'function') {
					callback();
				}
			} catch(e) {
				console.error("Error parsing menu pages response:", e);
				if(callback && typeof callback === 'function') {
					callback();
				}
			}
		},
		error: function() {
			console.error("Error loading menu pages");
			if(callback && typeof callback === 'function') {
				callback();
			}
		}
	});
}

// Show/hide parent page field based on type selection
function toggleParentPageField() {
	var typePage = $("#type_page").val();
	var parentGroup = $("#parent_page_group");
	
	if(typePage == "menu") {
		parentGroup.hide();
		$("#parent_page").val("0");
	} else {
		parentGroup.show();
	}
}

$(document).on("click",".myPage",function(){

	$("#myPage").modal("show");

	var page = $(this).attr("page");
	
	$("#myPage").on('shown.bs.modal',function(){

		$("input[name='id_page']").remove();

		// Load menu pages for parent selector
		var currentPageId = null;
		var pageData = null;
		if(page != undefined) {
			pageData = JSON.parse(page);
			currentPageId = pageData.id_page;
		}
		
		// Load menu pages and set parent page value after options are loaded
		loadMenuPages(currentPageId, function() {
			if(pageData) {
				// Set parent page if exists (after options are loaded)
				if(pageData.parent_page) {
					$("#parent_page").val(pageData.parent_page);
				} else {
					$("#parent_page").val("0");
				}
			}
		});

		if(page != undefined){

			/*=============================================
			Editar Página
			=============================================*/

			$("#title_page").before(`

				<input type="hidden" id="id_page" name="id_page" value="${btoa(pageData.id_page)}">

			`)

			$("#title_page").val(pageData.title_page);
			$("#url_page").val(pageData.url_page);
			$("#icon_page").val(pageData.icon_page);
			$("#type_page").val(pageData.type_page);
			
			// Update icon preview
			const iconPreview = document.getElementById('iconPagePreview');
			if (iconPreview && pageData.icon_page) {
				iconPreview.className = `bi ${pageData.icon_page}`;
			}
			
			// Toggle parent page field visibility
			toggleParentPageField();
			// Hide plugin selector when editing
			$("#plugin_selector_group").hide();
		

		}else{

			$("#title_page").val('');
			$("#url_page").val('');
			$("#icon_page").val('bi-gear');
			$("#type_page").val('');
			$("#parent_page").val("0");
			
			// Reset icon preview
			const iconPreview = document.getElementById('iconPagePreview');
			if (iconPreview) {
				iconPreview.className = 'bi bi-gear';
			}
			
			// Hide parent page field for new pages
			$("#parent_page_group").hide();
			// Hide plugin selector for new pages
			$("#plugin_selector_group").hide();
			$("#selected_plugin").val('');
		}

	})

})

// Track if type change is from plugin selection (to avoid clearing fields)
var isPluginTypeChange = false;

// Toggle parent page field when type changes
$(document).on("change", "#type_page", function() {
	// Only toggle plugin selector if change is not from plugin selection
	if (!isPluginTypeChange) {
		toggleParentPageField();
		togglePluginSelector();
	} else {
		// Reset flag and just toggle parent page field
		isPluginTypeChange = false;
		toggleParentPageField();
	}
});

// Show/hide plugin selector based on type selection
function togglePluginSelector() {
	var typePage = $("#type_page").val();
	var pluginGroup = $("#plugin_selector_group");
	var selectedPlugin = $("#selected_plugin");
	
	// IMPORTANT: Never clear title_page or url_page fields here
	// These fields should only be cleared when:
	// 1. Modal opens for new page (handled in modal open handler)
	// 2. Plugin is deselected (handled in plugin change handler)
	
	if(typePage == "plugins") {
		pluginGroup.show();
		// Load plugins if not already loaded
		if(selectedPlugin.find('option').length <= 1) {
			loadAvailablePlugins();
		}
	} else {
		pluginGroup.hide();
		// Only clear plugin selection, NEVER clear title/URL fields
		selectedPlugin.val('');
		$('#selected_plugin_info').hide();
		// DO NOT clear title_page or url_page here - they should persist when type changes
	}
}

// Load available plugins from server
function loadAvailablePlugins() {
	$.ajax({
		url: CMS_AJAX_PATH + '/pages.ajax.php',
		method: 'POST',
		data: {
			getAvailablePlugins: '1'
		},
		success: function(response) {
			try {
				var data = typeof response === 'string' ? JSON.parse(response) : response;
				var pluginSelect = $("#selected_plugin");
				
				// Clear existing options except the first one
				pluginSelect.find('option:not(:first)').remove();
				
				if(data && data.status == 200 && data.results && data.results.length > 0) {
					data.results.forEach(function(plugin) {
						pluginSelect.append(
							$('<option></option>')
								.attr('value', plugin.url)
								.attr('data-plugin-name', plugin.name)
								.attr('data-plugin-display', plugin.displayName)
								.attr('data-plugin-description', plugin.description)
								.attr('data-plugin-icon', plugin.icon)
								.text(plugin.displayName)
						);
					});
				} else {
					pluginSelect.append(
						$('<option></option>')
							.attr('value', '')
							.text('No hay plugins disponibles')
							.prop('disabled', true)
					);
				}
			} catch(e) {
				console.error('Error parsing plugins response:', e);
			}
		},
		error: function() {
			console.error('Error loading plugins');
		}
	});
}

// Handle plugin selection
$(document).on('change', '#selected_plugin', function() {
	var selectedUrl = $(this).val();
	var selectedOption = $(this).find('option:selected');
	
	if(selectedUrl) {
		var pluginName = selectedOption.attr('data-plugin-display') || selectedUrl;
		var pluginDescription = selectedOption.attr('data-plugin-description') || '';
		var pluginIcon = selectedOption.attr('data-plugin-icon') || 'bi-gear';
		
		// Show plugin info
		$('#selected_plugin_name').html('<i class="bi ' + pluginIcon + '"></i> ' + pluginName);
		$('#selected_plugin_description').text(pluginDescription);
		$('#selected_plugin_info').show();
		
		// Auto-fill fields (only if creating new page)
		if (!$('#id_page').length) {
			// Set flag to prevent clearing fields when type changes
			isPluginTypeChange = true;
			
			$('#url_page').val(selectedUrl);
			$('#title_page').val(pluginName);
			$('#icon_page').val(pluginIcon);
			$('#type_page').val('custom'); // Change to custom type
			
			// Update icon preview
			const iconPreview = document.getElementById('iconPagePreview');
			if (iconPreview) {
				iconPreview.className = 'bi ' + pluginIcon;
			}
			
			// Show parent page field
			toggleParentPageField();
			
			// Hide plugin selector group since we changed to custom
			$('#plugin_selector_group').hide();
		}
	} else {
		// Plugin was deselected - clear fields that were auto-filled by plugin
		$('#selected_plugin_info').hide();
		
		// Only clear fields if they were auto-filled by a plugin (not manually entered)
		// Check if we're creating a new page and fields match a plugin pattern
		if (!$('#id_page').length) {
			// Store original values before clearing to check if they were plugin-generated
			var currentUrl = $('#url_page').val();
			var currentTitle = $('#title_page').val();
			
			// Clear fields only if they appear to be plugin-generated
			// (This is a simple check - you might want to track if fields were auto-filled)
			$('#url_page').val('');
			$('#title_page').val('');
			$('#icon_page').val('bi-gear');
			
			// Update icon preview
			const iconPreview = document.getElementById('iconPagePreview');
			if (iconPreview) {
				iconPreview.className = 'bi bi-gear';
			}
		}
	}
});

/*=============================================
Plugin Detection and Info Display
=============================================*/

// Plugin registry (should match server-side)
var pluginsRegistry = {
	'payku': {
		name: 'Payku - Sistema de Pagos',
		description: 'Plugin de integración con Payku para procesar pagos online (Visa, Mastercard, Magna, American Express, Diners y Redcompra)',
		icon: 'bi-credit-card',
		type: 'payment'
	}
};

// Check if URL is a plugin and show info
function checkPluginUrl(url) {
	var pluginInfo = pluginsRegistry[url.toLowerCase()];
	var alertDiv = $('#plugin_info_alert');
	var warningDiv = $('#plugin_duplicate_warning');
	
	// Hide both alerts first
	alertDiv.hide();
	warningDiv.hide();
	
	if (pluginInfo) {
		// Check if plugin page already exists
		$.ajax({
			url: CMS_AJAX_PATH + '/pages.ajax.php',
			method: 'POST',
			data: {
				checkPluginExists: '1',
				pluginUrl: url
			},
			success: function(response) {
				try {
					var data = typeof response === 'string' ? JSON.parse(response) : response;
					
					if (data.exists) {
						// Show warning that plugin already exists
						warningDiv.show();
						alertDiv.hide();
					} else {
						// Show plugin info
						$('#plugin_name').html('<i class="bi ' + pluginInfo.icon + '"></i> ' + pluginInfo.name);
						$('#plugin_description').text(pluginInfo.description);
						alertDiv.show();
						warningDiv.hide();
						
						// Auto-fill some fields if creating new page
						if (!$('#id_page').length) {
							// Set flag to prevent clearing fields when type changes
							isPluginTypeChange = true;
							
							$('#title_page').val(pluginInfo.name);
							$('#icon_page').val(pluginInfo.icon);
							$('#type_page').val('custom');
							
							// Update icon preview
							const iconPreview = document.getElementById('iconPagePreview');
							if (iconPreview) {
								iconPreview.className = 'bi ' + pluginInfo.icon;
							}
							
							// Show parent page field
							toggleParentPageField();
						}
					}
				} catch(e) {
					console.error('Error checking plugin:', e);
					// Still show plugin info even if check fails
					$('#plugin_name').html('<i class="bi ' + pluginInfo.icon + '"></i> ' + pluginInfo.name);
					$('#plugin_description').text(pluginInfo.description);
					alertDiv.show();
				}
			},
			error: function() {
				// On error, still show plugin info
				$('#plugin_name').html('<i class="bi ' + pluginInfo.icon + '"></i> ' + pluginInfo.name);
				$('#plugin_description').text(pluginInfo.description);
				alertDiv.show();
			}
		});
	}
}

// Monitor URL input for plugin detection
$(document).on('input blur', '#url_page', function() {
	var url = $(this).val().trim().toLowerCase();
	if (url) {
		checkPluginUrl(url);
	} else {
		$('#plugin_info_alert').hide();
		$('#plugin_duplicate_warning').hide();
	}
});

/*=============================================
Cambiar orden de páginas
=============================================*/

// Track if we're dragging to prevent link clicks
var isDragging = false;

// Handle mousedown to show grab cursor
$(document).on('mousedown', '#sortable > li.list-group-item', function(e) {
	// Only if not clicking on buttons or action menus
	if ($(e.target).closest('.page-actions-wrapper, .page-menu-toggle, button').length === 0) {
		var $item = $(this);
		$item.addClass('mousedown-active');
		$('body').addClass('mousedown-active');
		
		// Also apply cursor directly to ensure it shows
		$item.css('cursor', 'grab');
		$item.find('a, .menu-toggle').css('cursor', 'grab');
	}
});

// Handle mouseup to remove grab cursor
$(document).on('mouseup', function(e) {
	$('#sortable > li.list-group-item').each(function() {
		$(this).removeClass('mousedown-active');
		$(this).css('cursor', '');
		$(this).find('a, .menu-toggle').css('cursor', '');
	});
	$('.submenu-item').each(function() {
		$(this).removeClass('mousedown-active');
		$(this).css('cursor', '');
		$(this).find('a').css('cursor', '');
	});
	$('body').removeClass('mousedown-active');
});

// Sortable for main pages (exclude submenu items)
$("#sortable").sortable({
	placeholder: 'sort-highlight',
	// Only cancel on buttons and action menus, allow dragging from anywhere else including links
	cancel: '.submenu, .submenu *, .submenu-item, .submenu-item *, .page-actions-wrapper, .page-actions-wrapper *, button.page-menu-toggle',
	forcePlaceholderSize: true,
	zIndex:999999,
	items: '> li',
	cursor: 'grabbing',
	cursorAt: { top: 20, left: 20 },
	delay: 150, // Delay to distinguish from click - allows normal click on links
	distance: 10, // Minimum distance (pixels) to start dragging - requires click and hold
	start: function(event, ui) {
		// Prevent main sortable if dragging from submenu
		if (ui.item.hasClass('submenu-item') || ui.item.closest('.submenu').length > 0) {
			$(this).sortable("cancel");
			return false;
		}
		// Mark as dragging
		isDragging = true;
		// Add grabbing cursor class
		$('body').addClass('dragging-menu-item');
		ui.helper.css('cursor', 'grabbing');
		// Prevent link navigation during drag
		ui.item.find('a, .menu-toggle').on('click.drag', function(e) {
			e.preventDefault();
			e.stopPropagation();
			return false;
		});
	},
	stop: function(event, ui) {
		// Remove dragging flag
		isDragging = false;
		// Remove grabbing cursor class
		$('body').removeClass('dragging-menu-item');
		// Re-enable link clicks after a short delay
		setTimeout(function() {
			ui.item.find('a, .menu-toggle').off('click.drag');
		}, 50);
	},
	out: function(event,ui){
		
		// Only process main pages (not subpages)
		var listPage = $("#sortable > li");
		var countList = 0;

		listPage.each((i)=>{

			var idPage = $(listPage[i]).attr("idPage");
			var index = i+1;

			var data = new FormData();
			data.append("idPage",idPage);
			data.append("index", index);
			data.append("token", localStorage.getItem("tokenAdmin"));

			$.ajax({

				url: CMS_AJAX_PATH + "/pages.ajax.php",
				method:"POST",
				data:data,
				contentType:false,
				cache:false,
				processData:false,
				success: function(response){
					
					if(response == 200){

						countList++;

						if(countList == listPage.length){

							fncToastr("success", "El orden del menú ha sido actualizado con éxito");
						}
					}

				}

			})

		})

	}

})

// Sortable for subpages within each submenu
function initSubmenuSortables() {
	$(".submenu").each(function() {
		var $submenu = $(this);
		
		// Destroy existing sortable if it exists
		if ($submenu.hasClass('ui-sortable')) {
			try {
				$submenu.sortable("destroy");
			} catch(e) {
				// Ignore errors if sortable doesn't exist
			}
		}
		
		// Only initialize if submenu has items
		var $submenuItems = $submenu.find('.submenu-item');
		if ($submenuItems.length > 0) {
			// Make sure submenu is visible for initialization
			var wasHidden = $submenu.is(':hidden');
			if (wasHidden) {
				$submenu.css({display: 'block', visibility: 'hidden', position: 'absolute'});
			}
			
			// Handle mousedown on submenu items to show grab cursor
			$submenu.on('mousedown', '.submenu-item', function(e) {
				// Only if not clicking on buttons or action menus
				if ($(e.target).closest('.page-actions-wrapper, .page-menu-toggle, button').length === 0) {
					var $item = $(this);
					$item.addClass('mousedown-active');
					$('body').addClass('mousedown-active');
					
					// Also apply cursor directly to ensure it shows
					$item.css('cursor', 'grab');
					$item.find('a').css('cursor', 'grab');
				}
			});
			
			$submenu.sortable({
				placeholder: 'sort-highlight',
				// Only cancel on buttons and action menus, allow dragging from anywhere else including links
				cancel: '.page-actions-wrapper, .page-actions-wrapper *, button.page-menu-toggle',
				forcePlaceholderSize: true,
				zIndex: 1000000,
				items: '> .submenu-item',
				tolerance: 'pointer',
				cursor: 'grabbing',
				cursorAt: { top: 20, left: 20 },
				delay: 150, // Delay to distinguish from click
				distance: 10, // Minimum distance (pixels) to start dragging - requires click and hold
				connectWith: false,
				start: function(event, ui) {
					// Disable main sortable when dragging submenu item
					$("#sortable").sortable("disable");
					// Mark as dragging
					isDragging = true;
					// Make sure the item being dragged is visible
					ui.helper.css('display', 'block');
					ui.helper.css('cursor', 'grabbing');
					// Add grabbing cursor class
					$('body').addClass('dragging-menu-item');
					// Prevent link navigation during drag
					ui.item.find('a').on('click.drag', function(e) {
						e.preventDefault();
						e.stopPropagation();
						return false;
					});
				},
				stop: function(event, ui) {
					// Re-enable main sortable after dragging submenu item
					$("#sortable").sortable("enable");
					// Remove dragging flag
					isDragging = false;
					// Remove grabbing cursor class
					$('body').removeClass('dragging-menu-item');
					// Re-enable link clicks after a short delay
					setTimeout(function() {
						ui.item.find('a').off('click.drag');
					}, 50);
				},
				update: function(event, ui) {
					// Get all subpages in this submenu after reorder
					var $submenuItems = $submenu.find('.submenu-item');
					var countList = 0;
					
					if ($submenuItems.length === 0) return;
					
					$submenuItems.each(function(index) {
						var idPage = $(this).attr("idPage");
						var orderIndex = index + 1;
						
						var data = new FormData();
						data.append("idPage", idPage);
						data.append("index", orderIndex);
						data.append("token", localStorage.getItem("tokenAdmin"));
						
						$.ajax({
							url: CMS_AJAX_PATH + "/pages.ajax.php",
							method: "POST",
							data: data,
							contentType: false,
							cache: false,
							processData: false,
							success: function(response) {
								if(response == 200) {
									countList++;
									if(countList == $submenuItems.length) {
										fncToastr("success", "El orden de las subpáginas ha sido actualizado con éxito");
									}
								}
							}
						});
					});
				}
			});
			
			// Restore original display state
			if (wasHidden) {
				$submenu.css({display: '', visibility: '', position: ''});
			}
		}
	});
}

// Make initSubmenuSortables available globally
window.initSubmenuSortables = initSubmenuSortables;

// Initialize submenu sortables on page load
$(document).ready(function() {
	// Wait a bit for DOM to be ready
	setTimeout(function() {
		initSubmenuSortables();
	}, 500);
});

// Re-initialize submenu sortables when menus are expanded
$(document).on('click', '.menu-toggle', function() {
	setTimeout(function() {
		initSubmenuSortables();
	}, 300);
});

/*=============================================
Eliminar una página
=============================================*/

$(document).on("click",".deletePage",function(){

	var idPage = $(this).attr("idPage");
	
	if(atob(idPage) == 1 || atob(idPage) == 2){

		fncToastr("error", "Esta página no se puede borrar");
		return;
	}

	fncSweetAlert("confirm", "¿Está seguro de borrar esta página?", "").then(resp=>{

		if(resp){
			
			var data = new FormData();
			data.append("idPageDelete",idPage);
			data.append("token", localStorage.getItem("tokenAdmin"));

			$.ajax({

				url: CMS_AJAX_PATH + "/pages.ajax.php",
				method:"POST",
				data:data,
				contentType:false,
				cache:false,
				processData:false,
				success: function(response){
					
					if(response == 200){

						fncSweetAlert("success","La página ha sido eliminada con éxito",setTimeout(()=>{
							// Redirect to home page instead of reloading
							// This prevents 404 error if user was on the deleted page
							window.location.href = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/') + '/';
						},1250));
					
					}else{

						fncToastr("error", "ERROR: La página tiene módulos vinculados");
					}
				}

			})

		}
	})

})