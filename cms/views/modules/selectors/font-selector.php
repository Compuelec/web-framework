<!--=============================================
Font Selector Modal - Reusable Component
===============================================-->
<div class="modal fade" id="fontSelectorModal" tabindex="-1" aria-labelledby="fontSelectorModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="fontSelectorModalLabel">
					<i class="bi bi-type"></i> Seleccionar Tipografía
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<input type="text" class="form-control" id="fontSearch" placeholder="Buscar fuente...">
				</div>
				<div class="font-list" id="fontList" style="max-height: 500px; overflow-y: auto;">
					<!-- Fonts will be loaded dynamically -->
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
			</div>
		</div>
	</div>
</div>

