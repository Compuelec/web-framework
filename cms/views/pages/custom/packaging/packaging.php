<?php

/**
 * Packaging Page
 * 
 * Interface for creating and managing project packages
 */

require_once __DIR__ . '/../../../../controllers/packaging.controller.php';

$packages = PackagingController::getPackages();

?>

<div class="container-fluid p-4">
	
	<!-- Page Header -->
	<div class="d-flex justify-content-between align-items-center mb-4">
		<div>
			<h2 class="mb-1">
				<i class="bi bi-box-seam"></i> Empaquetado del Sistema
			</h2>
			<p class="text-muted mb-0">Crea paquetes del proyecto para desplegar en servidores de producción</p>
		</div>
		<button class="btn btn-primary" id="createPackageBtn">
			<i class="bi bi-plus-circle"></i> Crear Nuevo Paquete
		</button>
	</div>

	<!-- Info Card -->
	<div class="card mb-4">
		<div class="card-body">
			<h5 class="card-title mb-3">
				<i class="bi bi-info-circle"></i> Información
			</h5>
			<div class="row">
				<div class="col-md-6">
					<p class="mb-2">
						<strong>¿Qué se incluye en el paquete?</strong>
					</p>
					<ul class="mb-0">
						<li>Todos los archivos fuente del proyecto</li>
						<li>Archivos de ejemplo de configuración</li>
						<li>Scripts y controladores</li>
						<li>Vistas y assets</li>
						<li><strong>Base de datos completa (database.sql)</strong></li>
					</ul>
				</div>
				<div class="col-md-6">
					<p class="mb-2">
						<strong>¿Qué se excluye del paquete?</strong>
					</p>
					<ul class="mb-0">
						<li>Archivos de configuración sensibles (config.php)</li>
						<li>Dependencias (vendor/, node_modules/)</li>
						<li>Archivos de respaldo y logs</li>
						<li>Archivos temporales</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<!-- Packages List -->
	<div class="card">
		<div class="card-header">
			<h5 class="mb-0">
				<i class="bi bi-archive"></i> Paquetes Creados
			</h5>
		</div>
		<div class="card-body">
			<?php if (empty($packages)): ?>
				<div class="text-center py-5">
					<i class="bi bi-inbox fs-1 text-muted"></i>
					<p class="text-muted mt-3 mb-0">No hay paquetes creados aún</p>
					<p class="text-muted small">Haz clic en "Crear Nuevo Paquete" para comenzar</p>
				</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th>Nombre del Archivo</th>
								<th>Tamaño</th>
								<th>Fecha de Creación</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="packagesTableBody">
							<?php foreach ($packages as $package): ?>
								<tr>
									<td>
										<i class="bi bi-file-zip text-primary"></i>
										<?php echo htmlspecialchars($package['filename']); ?>
									</td>
									<td>
										<span class="badge bg-secondary">
											<?php echo number_format($package['size_mb'], 2); ?> MB
										</span>
									</td>
									<td>
										<?php echo htmlspecialchars($package['created']); ?>
									</td>
									<td>
										<a href="<?php echo htmlspecialchars($package['download_url']); ?>" 
										   class="btn btn-sm btn-primary" 
										   download>
											<i class="bi bi-download"></i> Descargar
										</a>
										<button class="btn btn-sm btn-danger delete-package-btn" 
										        data-filename="<?php echo htmlspecialchars($package['filename']); ?>">
											<i class="bi bi-trash"></i> Eliminar
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

</div>

<!-- Loading Modal -->
<div class="modal fade" id="packagingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-body text-center py-4">
				<div class="spinner-border text-primary mb-3" role="status">
					<span class="visually-hidden">Cargando...</span>
				</div>
				<p class="mb-0" id="packagingModalMessage">Creando paquete...</p>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const createBtn = document.getElementById('createPackageBtn');
	const packagesTableBody = document.getElementById('packagesTableBody');
	const packagingModal = new bootstrap.Modal(document.getElementById('packagingModal'));
	
	// Create package
	if (createBtn) {
		createBtn.addEventListener('click', function() {
			createPackage();
		});
	}
	
	// Delete package buttons
	document.querySelectorAll('.delete-package-btn').forEach(btn => {
		btn.addEventListener('click', function() {
			const filename = this.getAttribute('data-filename');
			deletePackage(filename);
		});
	});
	
	function createPackage() {
		document.getElementById('packagingModalMessage').textContent = 'Creando paquete...';
		packagingModal.show();
		
		fetch('ajax/packaging.ajax.php?action=create', {
			method: 'GET',
			headers: {
				'Content-Type': 'application/json'
			}
		})
		.then(response => response.json())
		.then(data => {
			packagingModal.hide();
			
			if (data.success) {
				const dbMessage = data.database_included 
					? ' Incluye la base de datos completa (database.sql).'
					: ' Nota: No se pudo incluir la base de datos.';
				
				fncSweetAlert(
					'success',
					'Paquete creado exitosamente',
					`El paquete "${data.filename}" se ha creado correctamente (${data.size_mb} MB).${dbMessage}`,
					function() {
						location.reload();
					}
				);
			} else {
				fncSweetAlert(
					'error',
					'Error al crear paquete',
					data.message || 'No se pudo crear el paquete'
				);
			}
		})
		.catch(error => {
			packagingModal.hide();
			fncSweetAlert(
				'error',
				'Error',
				'Ocurrió un error al crear el paquete: ' + error.message
			);
		});
	}
	
	function deletePackage(filename) {
		fncSweetAlert(
			'warning',
			'¿Eliminar paquete?',
			`¿Estás seguro de que deseas eliminar "${filename}"?`,
			function() {
				const formData = new FormData();
				formData.append('action', 'delete');
				formData.append('filename', filename);
				
				fetch('ajax/packaging.ajax.php', {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						fncSweetAlert(
							'success',
							'Paquete eliminado',
							'El paquete se ha eliminado correctamente.',
							function() {
								location.reload();
							}
						);
					} else {
						fncSweetAlert(
							'error',
							'Error',
							data.message || 'No se pudo eliminar el paquete'
						);
					}
				})
				.catch(error => {
					fncSweetAlert(
						'error',
						'Error',
						'Ocurrió un error al eliminar el paquete: ' + error.message
					);
				});
			},
			true
		);
	}
});
</script>

