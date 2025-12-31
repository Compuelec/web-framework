<!-- The Modal -->
<div class="modal fade" id="myBooleans">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content rounded">

			<form method="POST" class="needs-validation" novalidate>

				<!-- Modal Header -->
				<div class="modal-header">
					<h4 class="modal-title">Cambiar estado</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>

				<!-- Modal body -->
				<div class="modal-body px-4">

					<div class="mb-3">
						<h6 class="text-muted mb-3">
							<i class="bi bi-toggle-on"></i> Estado
						</h6>
					</div>

					<div class="card border-0 bg-light">
						<div class="card-body">
							<div class="form-group">
								<label for="valueBoolean" class="form-label small fw-semibold">Cambiar estado</label>
								<select  
									class="form-select form-select-sm rounded" 
									id="valueBoolean"
									name="valueBoolean"
								>
									<option value="0">False</option>
									<option value="1">True</option>
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
					<div><button type="button" class="btn btn-default backColor rounded changeBooleans">Guardar</button></div> 
				</div>

			</form>

		</div>
	</div>
</div>