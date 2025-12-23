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

          <div class="form-group mb-3">

            <label for="title_page">Título<sup>*</sup></label>

            <input 
            type="text"
            class="form-control rounded form-control-sm"
            id="title_page"
            name="title_page"
            required
            >

            <div class="valid-feedback">Válido.</div>
            <div class="invalid-feedback">Campo inválido.</div>

          </div>

          <div class="form-group mb-3">

            <label for="url_page">URL<sup>*</sup></label>

            <input 
            type="text"
            class="form-control rounded form-control-sm"
            id="url_page"
            name="url_page"
            required
            >
            <div class="valid-feedback">Válido.</div>
            <div class="invalid-feedback">Campo inválido.</div>

          </div>

          <div class="form-group mb-3">

            <label for="icon_page">Icono<sup>*</sup></label>

            <div class="input-group">
              <span class="input-group-text">
                <i class="bi" id="iconPagePreview">bi-gear</i>
              </span>
              <input 
              type="text"
              class="form-control rounded form-control-sm cleanIcon"
              id="icon_page"
              name="icon_page"
              placeholder="Seleccionar icono"
              readonly
              required
              >
              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#iconSelectorModalPage">
                <i class="bi bi-grid-3x3-gap"></i>
              </button>
            </div>
            <div class="valid-feedback">Válido.</div>
            <div class="invalid-feedback">Campo inválido.</div>

          </div>

          <div class="form-group mb-3">

            <label for="type_page">Tipo<sup>*</sup></label>

            <select
            class="form-select form-select-sm rounded" 
            name="type_page" 
            id="type_page">
              
              <option value="modules">Modular</option>
              <option value="custom">Personalizable</option>
              <option value="menu">Menú</option>
              <option value="external_link">Enlace Externo</option>
              <option value="internal_link">Enlace Interno</option>

            </select>

            <div class="valid-feedback">Válido.</div>
            <div class="invalid-feedback">Campo inválido.</div>

          </div>

          <div class="form-group mb-3" id="parent_page_group" style="display: none;">

            <label for="parent_page">Página Padre</label>

            <select
            class="form-select form-select-sm rounded" 
            name="parent_page" 
            id="parent_page">
              
              <option value="0">Ninguna (Página Principal)</option>

            </select>

            <div class="valid-feedback">Válido.</div>
            <div class="invalid-feedback">Campo inválido.</div>

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