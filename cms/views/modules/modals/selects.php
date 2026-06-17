<!-- The Modal -->
<div class="modal fade" id="mySelects">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content rounded">

			<form method="POST" class="needs-validation" novalidate>

				<!-- Modal Header -->
				<div class="modal-header">
					<h4 class="modal-title">Cambiar selección</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>

				<!-- Modal body -->
				<div class="modal-body px-4">

					<div class="mb-3">
						<h6 class="text-muted mb-3">
							<i class="bi bi-list-check"></i> Selección
						</h6>
					</div>

					<div class="card border-0 bg-light">
						<div class="card-body">
							<div class="form-group">
								<label for="valueSelect" class="form-label small fw-semibold">Seleccionar valor</label>
								<select  
									class="form-select form-select-sm rounded" 
									id="valueSelect"
									name="valueSelect"
								>
								</select>
								<div class="valid-feedback">Válido.</div>
								<div class="invalid-feedback">Campo inválido.</div>
							</div>
						</div>
					</div>

				</div>

				<!-- Modal footer -->
				<div class="modal-footer d-flex justify-content-between">
					<div><button type="button" class="btn btn-dark rounded" data-bs-dismiss="modal">Cerrar</button></div>
					<div><button type="button" class="btn btn-default backColor rounded changeSelects">Guardar</button></div> 
				</div>

			</form>

		</div>
	</div>
</div>