<?php

/**
 * Updates Page
 * 
 * Interface for checking and installing framework updates
 */

require_once __DIR__ . '/../../../../controllers/updates.controller.php';

$currentVersion = VersionManager::getCurrentVersion();
$updateInfo = UpdatesController::checkForUpdates();
$updateHistory = UpdatesController::getUpdateHistory();

?>

<div class="container-fluid p-4">
	
	<!-- Page Header -->
	<div class="d-flex justify-content-between align-items-center mb-4">
		<div>
			<h2 class="mb-1">
				<i class="bi bi-arrow-repeat"></i> Actualizaciones del Framework
			</h2>
			<p class="text-muted mb-0">Gestiona las actualizaciones de tu framework</p>
		</div>
		<button class="btn btn-primary" id="checkUpdatesBtn">
			<i class="bi bi-arrow-clockwise"></i> Verificar Actualizaciones
		</button>
	</div>

	<!-- Current Version Card -->
	<div class="card mb-4">
		<div class="card-body">
			<div class="d-flex justify-content-between align-items-center">
				<div>
					<h5 class="card-title mb-1">Versión Actual</h5>
					<p class="text-muted mb-0">Tu framework está ejecutando la versión:</p>
				</div>
				<div class="text-end">
					<span class="badge bg-primary fs-6 px-3 py-2">v<?php echo htmlspecialchars($currentVersion); ?></span>
				</div>
			</div>
		</div>
	</div>

	<!-- Update Available Alert -->
	<?php if ($updateInfo['update_available']): ?>
		<div class="alert alert-warning alert-dismissible fade show" role="alert">
			<div class="d-flex align-items-center">
				<i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
				<div class="flex-grow-1">
					<h5 class="alert-heading mb-1">¡Actualización Disponible!</h5>
					<p class="mb-0">
						Hay una nueva versión disponible: <strong>v<?php echo htmlspecialchars($updateInfo['latest_version']); ?></strong>
						<?php if (isset($updateInfo['is_major_update']) && $updateInfo['is_major_update']): ?>
							<span class="badge bg-danger ms-2">Actualización Mayor</span>
						<?php endif; ?>
					</p>
					<?php if (isset($updateInfo['update_info']['changelog'][$updateInfo['latest_version']])): ?>
						<details class="mt-2">
							<summary class="cursor-pointer">Ver cambios</summary>
							<ul class="mt-2 mb-0">
								<?php foreach ($updateInfo['update_info']['changelog'][$updateInfo['latest_version']]['changes'] as $change): ?>
									<li><?php echo htmlspecialchars($change); ?></li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
				</div>
			</div>
			<button type="button" class="btn btn-warning mt-3" id="installUpdateBtn" data-version="<?php echo htmlspecialchars($updateInfo['latest_version']); ?>">
				<i class="bi bi-download"></i> Instalar Actualización
			</button>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php else: ?>
		<div class="alert alert-success" role="alert">
			<i class="bi bi-check-circle-fill me-2"></i>
			<strong>¡Todo actualizado!</strong> Estás ejecutando la última versión del framework.
		</div>
	<?php endif; ?>

	<!-- Update History -->
	<div class="card">
		<div class="card-header">
			<h5 class="mb-0">
				<i class="bi bi-clock-history"></i> Historial de Actualizaciones
			</h5>
		</div>
		<div class="card-body">
			<?php if (empty($updateHistory)): ?>
				<p class="text-muted mb-0">No hay historial de actualizaciones aún.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th>Desde</th>
								<th>Hacia</th>
								<th>Estado</th>
								<th>Fecha</th>
								<th>Notas</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($updateHistory as $update): ?>
								<tr>
									<td><span class="badge bg-secondary">v<?php echo htmlspecialchars($update['from_version']); ?></span></td>
									<td><span class="badge bg-primary">v<?php echo htmlspecialchars($update['to_version']); ?></span></td>
									<td>
										<?php
										$statusClass = 'secondary';
										if ($update['status'] === 'completed') $statusClass = 'success';
										elseif ($update['status'] === 'failed') $statusClass = 'danger';
										elseif ($update['status'] === 'completed_with_warnings') $statusClass = 'warning';
										?>
										<span class="badge bg-<?php echo $statusClass; ?>">
											<?php echo htmlspecialchars($update['status']); ?>
										</span>
									</td>
									<td><?php echo date('d/m/Y H:i', strtotime($update['updated_at'])); ?></td>
									<td><?php echo htmlspecialchars($update['notes'] ?? ''); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

</div>

<!-- Update Progress Modal -->
<div class="modal fade" id="updateProgressModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">
					<i class="bi bi-arrow-repeat"></i> Instalando Actualización
				</h5>
			</div>
			<div class="modal-body">
				<div class="text-center mb-3">
					<div class="spinner-border text-primary" role="status">
						<span class="visually-hidden">Cargando...</span>
					</div>
				</div>
				<div id="updateProgressSteps">
					<p class="mb-2"><i class="bi bi-hourglass-split"></i> Creando respaldo...</p>
					<p class="mb-2 text-muted"><i class="bi bi-circle"></i> Ejecutando migraciones...</p>
					<p class="mb-2 text-muted"><i class="bi bi-circle"></i> Actualizando archivos...</p>
					<p class="mb-0 text-muted"><i class="bi bi-circle"></i> Finalizando...</p>
				</div>
				<div class="progress mt-3" style="height: 25px;">
					<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
$(document).ready(function() {
	
	// Check for updates button
	$('#checkUpdatesBtn').on('click', function() {
		const btn = $(this);
		const originalHtml = btn.html();
		
		btn.prop('disabled', true).html('<i class="bi bi-arrow-clockwise spin"></i> Verificando...');
		
		$.ajax({
			url: '<?php echo $cmsBasePath; ?>/ajax/updates.ajax.php?action=check',
			method: 'GET',
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					// Reload page to show update info
					location.reload();
				} else {
					alert('Error al verificar actualizaciones: ' + (response.error || 'Error desconocido'));
				}
			},
			error: function() {
				alert('Error al conectar con el servidor de actualizaciones');
			},
			complete: function() {
				btn.prop('disabled', false).html(originalHtml);
			}
		});
	});
	
	// Install update button
	$('#installUpdateBtn').on('click', function() {
		if (!confirm('¿Estás seguro de que deseas instalar esta actualización? Se creará un respaldo automático antes de proceder.')) {
			return;
		}
		
		const version = $(this).data('version');
		const modal = new bootstrap.Modal(document.getElementById('updateProgressModal'));
		modal.show();
		
		// Update progress steps
		const steps = [
			{ text: 'Creando respaldo...', progress: 25 },
			{ text: 'Ejecutando migraciones...', progress: 50 },
			{ text: 'Actualizando archivos...', progress: 75 },
			{ text: 'Finalizando...', progress: 100 }
		];
		
		let currentStep = 0;
		
		const updateProgress = function(stepIndex) {
			if (stepIndex < steps.length) {
				$('#updateProgressSteps p').eq(stepIndex).removeClass('text-muted').html('<i class="bi bi-check-circle-fill text-success"></i> ' + steps[stepIndex].text);
				$('.progress-bar').css('width', steps[stepIndex].progress + '%');
				
				if (stepIndex < steps.length - 1) {
					$('#updateProgressSteps p').eq(stepIndex + 1).removeClass('text-muted').html('<i class="bi bi-hourglass-split"></i> ' + steps[stepIndex + 1].text);
				}
			}
		};
		
		// Simulate progress (in production, this would be real-time updates via WebSocket or polling)
		updateProgress(0);
		setTimeout(() => updateProgress(1), 1000);
		setTimeout(() => updateProgress(2), 2000);
		setTimeout(() => updateProgress(3), 3000);
		
		// Make actual update request
		$.ajax({
			url: '<?php echo $cmsBasePath; ?>/ajax/updates.ajax.php',
			method: 'POST',
			data: {
				action: 'update',
				version: version
			},
			dataType: 'json',
			success: function(response) {
				setTimeout(() => {
					modal.hide();
					
					if (response.success) {
						alert('¡Actualización instalada exitosamente!\n\nVersión anterior: ' + response.from_version + '\nVersión nueva: ' + response.to_version);
						location.reload();
					} else {
						alert('Error al instalar la actualización: ' + (response.error || 'Error desconocido'));
					}
				}, 1000);
			},
			error: function(xhr) {
				modal.hide();
				let errorMsg = 'Error al conectar con el servidor';
				if (xhr.responseJSON && xhr.responseJSON.error) {
					errorMsg = xhr.responseJSON.error;
				}
				alert('Error al instalar la actualización: ' + errorMsg);
			}
		});
	});
	
	// Add spinning animation
	$('<style>').prop('type', 'text/css').html(`
		@keyframes spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		.spin {
			animation: spin 1s linear infinite;
		}
		.cursor-pointer {
			cursor: pointer;
		}
	`).appendTo('head');
	
});
</script>
