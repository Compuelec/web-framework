<?php

/**
 * Packaging Page
 * 
 * Interface for creating and managing project packages
 */

require_once __DIR__ . '/../../../../controllers/packaging.controller.php';

$packages = PackagingController::getPackages();

// Calculate base path for downloads
// Use the global $cmsBasePath from template.php, or calculate it
if (!isset($cmsBasePath)) {
    require_once __DIR__ . '/../../../../controllers/template.controller.php';
    $cmsBasePath = TemplateController::cmsBasePath();
}
// Remove /cms from the base path to get project root
$projectBasePath = str_replace('/cms', '', $cmsBasePath);
// If base path is empty or just ".", use relative path
if (empty($projectBasePath) || $projectBasePath === '.') {
    $projectBasePath = '../../../../';
} else {
    $projectBasePath = rtrim($projectBasePath, '/');
}

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
						<li>Archivos de configuración (config.php)</li>
						<li>Dependencias (vendor/)</li>
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
						<li>Archivos de respaldo y logs</li>
						<li>Archivos temporales</li>
						<li>Archivos del sistema (.DS_Store, Thumbs.db)</li>
						<li>Archivos de control de versiones (.git/)</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<?php if (($_SESSION['admin']->rol_admin ?? '') === 'superadmin'): ?>
	<!-- Restore / Migrate Card (superadmin only) -->
	<div class="card mb-4 border-warning">
		<div class="card-body">
			<h5 class="card-title mb-2">
				<i class="bi bi-arrow-counterclockwise"></i> Restaurar / Migrar desde un paquete
			</h5>
			<p class="text-muted small mb-3">
				Sube un paquete <code>.zip</code> para restaurar esta plataforma con sus datos. Importa la base de datos del paquete,
				recupera los archivos subidos (imágenes) y reescribe las URLs al dominio de este servidor.
				<strong>No</strong> sobrescribe el código en ejecución.
			</p>
			<div class="alert alert-warning py-2 small mb-3">
				<i class="bi bi-exclamation-triangle me-1"></i>
				<strong>Atención:</strong> esto <strong>reemplaza la base de datos actual</strong> de este servidor por la del paquete.
				Haz un respaldo antes si tienes datos que conservar.
			</div>
			<div class="row g-2 align-items-center">
				<div class="col-md-6">
					<input type="file" id="restoreFile" class="form-control" accept=".zip,application/zip">
				</div>
				<div class="col-md-3">
					<div class="form-check">
						<input class="form-check-input" type="checkbox" id="restoreIncludeFiles" checked>
						<label class="form-check-label small" for="restoreIncludeFiles">Incluir imágenes subidas</label>
					</div>
				</div>
				<div class="col-md-3 text-md-end">
					<button class="btn btn-warning" id="restoreBtn">
						<i class="bi bi-arrow-counterclockwise"></i> Restaurar
					</button>
				</div>
			</div>
			<div id="restoreResult" class="mt-3"></div>
		</div>
	</div>
	<?php endif; ?>

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
										<a href="<?php echo htmlspecialchars($projectBasePath . '/packages/' . $package['filename']); ?>" 
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
	
	// Restore / migrate from an uploaded package
	function escHtml(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
	const restoreBtn = document.getElementById('restoreBtn');
	if (restoreBtn) {
		restoreBtn.addEventListener('click', function() {
			const file = document.getElementById('restoreFile').files[0];
			if (!file) {
				Swal.fire({ icon: 'warning', title: 'Falta el paquete', text: 'Selecciona un archivo .zip del paquete.' });
				return;
			}
			Swal.fire({
				title: '¿Restaurar / migrar plataforma?',
				text: 'Esto REEMPLAZARÁ la base de datos actual de este servidor por la del paquete. Esta acción no se puede deshacer.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#d33',
				cancelButtonColor: '#3085d6',
				confirmButtonText: 'Sí, restaurar',
				cancelButtonText: 'Cancelar'
			}).then((result) => {
				if (!result.isConfirmed) { return; }

				const fd = new FormData();
				fd.append('action', 'restore');
				fd.append('package', file);
				fd.append('include_files', document.getElementById('restoreIncludeFiles').checked ? '1' : '0');

				document.getElementById('packagingModalMessage').textContent = 'Restaurando plataforma… (puede tardar varios minutos)';
				packagingModal.show();
				document.getElementById('restoreResult').innerHTML = '';

				fetch('ajax/packaging.ajax.php', { method: 'POST', body: fd })
					.then(r => r.text())
					.then(text => {
						packagingModal.hide();
						const box = document.getElementById('restoreResult');
						let data; try { data = JSON.parse(text); } catch (e) { box.innerHTML = '<div class="alert alert-danger">Respuesta inválida del servidor.</div>'; return; }
						if (data.success) {
							let html = '<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><strong>Restauración completada.</strong><br>'
								+ 'Método: ' + (data.method === 'cli' ? 'cliente mysql' : 'PHP') + ' · '
								+ 'Imágenes copiadas: ' + (parseInt(data.files_copied, 10) || 0) + ' · '
								+ 'URLs actualizadas: ' + (parseInt(data.urls_updated, 10) || 0);
							if (data.old_domain && data.old_domain !== data.new_domain) {
								html += '<br>Dominio: <code>' + escHtml(data.old_domain) + '</code> → <code>' + escHtml(data.new_domain) + '</code>';
							}
							html += '</div>';
							box.innerHTML = html;
							Swal.fire({ icon: 'success', title: 'Plataforma restaurada', showConfirmButton: false, timer: 1800 });
						} else {
							box.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i>' + escHtml(data.message || 'No se pudo restaurar.') + '</div>';
							Swal.fire({ icon: 'error', title: 'No se pudo restaurar', text: data.message || '' });
						}
					})
					.catch(() => {
						packagingModal.hide();
						document.getElementById('restoreResult').innerHTML = '<div class="alert alert-danger">Error de conexión durante la restauración.</div>';
						Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Ocurrió un error durante la restauración.' });
					});
			});
		});
	}

	function createPackage() {
		document.getElementById('packagingModalMessage').textContent = 'Creando paquete...';
		packagingModal.show();
		
		fetch('ajax/packaging.ajax.php?action=create', {
			method: 'GET',
			headers: {
				'Content-Type': 'application/json'
			}
		})
		.then(response => {
			// Check if response is ok
			if (!response.ok) {
				throw new Error('HTTP error! status: ' + response.status);
			}
			// Get response text first to debug
			return response.text();
		})
		.then(text => {
			// Try to parse JSON
			let data;
			try {
				data = JSON.parse(text);
			} catch (e) {
				console.error('Error parsing JSON:', e);
				console.error('Response text:', text);
				throw new Error('Error al parsear la respuesta del servidor: ' + e.message);
			}
			
			packagingModal.hide();
			
			if (data.success) {
				const dbMessage = data.database_included 
					? ' Incluye la base de datos completa (database.sql).'
					: ' Nota: No se pudo incluir la base de datos.';
				
				Swal.fire({
					icon: 'success',
					title: 'Paquete creado exitosamente',
					text: `El paquete "${data.filename}" se ha creado correctamente (${data.size_mb} MB).${dbMessage}`,
					showConfirmButton: false,
					timer: 2000
				}).then(() => {
					location.reload();
				});
			} else {
				fncSweetAlert(
					'error',
					data.message || data.error || 'No se pudo crear el paquete',
					''
				);
			}
		})
		.catch(error => {
			packagingModal.hide();
			console.error('Error creating package:', error);
			fncSweetAlert(
				'error',
				'Ocurrió un error al crear el paquete: ' + error.message,
				''
			);
		});
	}
	
	function deletePackage(filename) {
		Swal.fire({
			title: '¿Eliminar paquete?',
			text: `¿Estás seguro de que deseas eliminar "${filename}"?`,
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			cancelButtonColor: '#3085d6',
			confirmButtonText: 'Sí, eliminar',
			cancelButtonText: 'Cancelar'
		}).then((result) => {
			if (result.isConfirmed) {
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
						Swal.fire({
							icon: 'success',
							title: 'Paquete eliminado',
							text: 'El paquete se ha eliminado correctamente.',
							showConfirmButton: false,
							timer: 1500
						}).then(() => {
							location.reload();
						});
					} else {
						Swal.fire({
							icon: 'error',
							title: 'Error',
							text: data.message || 'No se pudo eliminar el paquete'
						});
					}
				})
				.catch(error => {
					Swal.fire({
						icon: 'error',
						title: 'Error',
						text: 'Ocurrió un error al eliminar el paquete: ' + error.message
					});
				});
			}
		});
	}
});
</script>

