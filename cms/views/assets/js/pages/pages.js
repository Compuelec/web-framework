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
		

		}else{

			$("#title_page").val('');
			$("#url_page").val('');
			$("#icon_page").val('');
			$("#type_page").val('');
			$("#parent_page").val("0");
			
			// Reset icon preview
			const iconPreview = document.getElementById('iconPagePreview');
			if (iconPreview) {
				iconPreview.className = 'bi bi-gear';
			}
			
			// Hide parent page field for new pages
			$("#parent_page_group").hide();
		}

	})

})

// Toggle parent page field when type changes
$(document).on("change", "#type_page", function() {
	toggleParentPageField();
});

/*=============================================
Cambiar orden de páginas
=============================================*/

// Sortable for main pages (exclude submenu items)
$("#sortable").sortable({
	placeholder: 'sort-highlight',
	handle: '.handle',
	forcePlaceholderSize: true,
	zIndex:999999,
	items: '> li',
	cancel: '.submenu, .submenu *, .submenu-item, .submenu-item *, .submenu .handle',
	start: function(event, ui) {
		// Prevent main sortable if dragging from submenu
		if (ui.item.hasClass('submenu-item') || ui.item.closest('.submenu').length > 0) {
			$(this).sortable("cancel");
			return false;
		}
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
			
			$submenu.sortable({
				placeholder: 'sort-highlight',
				handle: '.submenu-item .handle, .handle',
				forcePlaceholderSize: true,
				zIndex: 1000000,
				items: '> .submenu-item',
				tolerance: 'pointer',
				cursor: 'move',
				connectWith: false,
				start: function(event, ui) {
					// Disable main sortable when dragging submenu item
					$("#sortable").sortable("disable");
					// Make sure the item being dragged is visible
					ui.helper.css('display', 'block');
				},
				stop: function(event, ui) {
					// Re-enable main sortable after dragging submenu item
					$("#sortable").sortable("enable");
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

						fncSweetAlert("success","La página ha sido eliminada con éxito",setTimeout(()=>location.reload(),1250));
					
					}else{

						fncToastr("error", "ERROR: La página tiene módulos vinculados");
					}
				}

			})

		}
	})

})