<!-- The Modal -->
<div class="modal fade" id="myPage">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded">

      <form method="POST" class="needs-validation" novalidate>

        <!-- Modal Header -->
        <div class="modal-header">
          <h4 class="modal-title text-capitalize">Páginas</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <!-- Modal body -->
        <div class="modal-body px-4">

          <div class="mb-3">
            <h6 class="text-muted mb-3">
              <i class="bi bi-file-earmark-text"></i> Información General
            </h6>
          </div>

          <div class="row g-3">
            
            <!--=============================================
            Basic Information Section
            ===============================================-->
            
            <div class="col-12">
              <div class="card border-0 bg-light">
                <div class="card-body">
                  <h6 class="card-title text-muted mb-3">
                    <i class="bi bi-info-circle"></i> Datos Básicos
                  </h6>
                  
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="title_page" class="form-label small fw-semibold">Título<sup>*</sup></label>
                      <input 
                        type="text"
                        class="form-control form-control-sm rounded"
                        id="title_page"
                        name="title_page"
                        placeholder="Nombre de la página"
                        required
                      >
                      <div class="valid-feedback">Válido.</div>
                      <div class="invalid-feedback">Campo inválido.</div>
                    </div>

                    <div class="col-md-6">
                      <label for="url_page" class="form-label small fw-semibold">URL<sup>*</sup></label>
                      <input 
                        type="text"
                        class="form-control form-control-sm rounded"
                        id="url_page"
                        name="url_page"
                        placeholder="url-de-la-pagina"
                        required
                      >
                      <div class="valid-feedback">Válido.</div>
                      <div class="invalid-feedback">Campo inválido.</div>
                      <!-- Plugin Info Alert -->
                      <div id="plugin_info_alert" class="alert alert-info mt-2" style="display: none;">
                        <strong id="plugin_name"></strong>
                        <p class="mb-0 small" id="plugin_description"></p>
                      </div>
                      <!-- Plugin Duplicate Warning -->
                      <div id="plugin_duplicate_warning" class="alert alert-warning mt-2" style="display: none;">
                        <strong>⚠️ Plugin ya existe</strong>
                        <p class="mb-0 small">Este plugin ya tiene una página creada. No se puede duplicar.</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!--=============================================
            Appearance Section
            ===============================================-->
            
            <div class="col-12">
              <div class="card border-0 bg-light">
                <div class="card-body">
                  <h6 class="card-title text-muted mb-3">
                    <i class="bi bi-palette"></i> Apariencia
                  </h6>
                  
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="icon_page" class="form-label small fw-semibold">Icono<sup>*</sup></label>
                      <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white">
                          <i class="bi" id="iconPagePreview">bi-gear</i>
                        </span>
                        <input 
                          type="text"
                          class="form-control form-control-sm rounded cleanIcon"
                          id="icon_page"
                          name="icon_page"
                          value="bi-gear"
                          placeholder="Seleccionar icono"
                          readonly
                          required
                        >
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#iconSelectorModalPage" title="Seleccionar icono">
                          <i class="bi bi-grid-3x3-gap"></i>
                        </button>
                      </div>
                      <div class="valid-feedback">Válido.</div>
                      <div class="invalid-feedback">Campo inválido.</div>
                    </div>

                    <div class="col-md-6">
                      <label for="type_page" class="form-label small fw-semibold">Tipo<sup>*</sup></label>
                      <select
                        class="form-select form-select-sm rounded" 
                        name="type_page" 
                        id="type_page"
                      >
                        <option value="modules">Modular</option>
                        <option value="custom">Personalizable</option>
                        <option value="plugins">Plugin</option>
                        <option value="menu">Menú</option>
                        <option value="external_link">Enlace Externo</option>
                        <option value="internal_link">Enlace Interno</option>
                      </select>
                      <div class="valid-feedback">Válido.</div>
                      <div class="invalid-feedback">Campo inválido.</div>
                    </div>
                  </div>
                  
                  <!-- Plugin Selector (shown when type is "plugins") -->
                  <div class="row g-3 mt-2" id="plugin_selector_group" style="display: none;">
                    <div class="col-md-12">
                      <label for="selected_plugin" class="form-label small fw-semibold">Seleccionar Plugin<sup>*</sup></label>
                      <select
                        class="form-select form-select-sm rounded" 
                        name="selected_plugin" 
                        id="selected_plugin"
                      >
                        <option value="">-- Seleccione un plugin --</option>
                      </select>
                      <div class="valid-feedback">Válido.</div>
                      <div class="invalid-feedback">Debe seleccionar un plugin.</div>
                      <!-- Plugin Info Alert -->
                      <div id="selected_plugin_info" class="alert alert-info mt-2" style="display: none;">
                        <strong id="selected_plugin_name"></strong>
                        <p class="mb-0 small" id="selected_plugin_description"></p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!--=============================================
            Hierarchy Section
            ===============================================-->
            
            <div class="col-12" id="parent_page_group" style="display: none;">
              <div class="card border-0 bg-light">
                <div class="card-body">
                  <h6 class="card-title text-muted mb-3">
                    <i class="bi bi-diagram-3"></i> Jerarquía
                  </h6>
                  
                  <div class="row g-3">
                    <div class="col-md-12">
                      <label for="parent_page" class="form-label small fw-semibold">Página Padre</label>
                      <select
                        class="form-select form-select-sm rounded" 
                        name="parent_page" 
                        id="parent_page"
                      >
                        <option value="0">Ninguna (Página Principal)</option>
                      </select>
                      <div class="valid-feedback">Válido.</div>
                      <div class="invalid-feedback">Campo inválido.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- Modal footer -->
        <div class="modal-footer d-flex justify-content-between">
          
          <div><button type="button" class="btn btn-dark rounded" data-bs-dismiss="modal">Cerrar</button></div>
          <div><button type="submit" class="btn btn-default backColor rounded">Guardar</button></div>
          
        </div>

      </form>

    </div>
  </div>
</div>

<!--=============================================
Icon Selector Modal for Pages - Reusable Component
===============================================-->
<div class="modal fade" id="iconSelectorModalPage" tabindex="-1" aria-labelledby="iconSelectorModalPageLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="iconSelectorModalPageLabel">
					<i class="bi bi-palette"></i> Seleccionar Icono
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<input type="text" class="form-control" id="iconSearchPage" placeholder="Buscar icono...">
				</div>
				<div class="icon-grid" id="iconGridPage" style="max-height: 500px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px;">
					<!-- Icons will be loaded dynamically -->
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
			</div>
		</div>
	</div>
</div>

<!--=============================================
Initialize Icon Selector for Pages
===============================================-->
<?php
// Get CMS base path
require_once __DIR__ . '/../../../controllers/template.controller.php';
$cmsBasePath = TemplateController::cmsBasePath();
?>
<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/selectors/selectors.css">
<script src="<?php echo $cmsBasePath ?>/views/assets/js/selectors/icon-selector.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Initialize icon selector for pages
	initIconSelector({
		inputId: 'icon_page',
		previewId: 'iconPagePreview',
		modalId: 'iconSelectorModalPage',
		gridId: 'iconGridPage',
		searchId: 'iconSearchPage'
	});
});
</script>