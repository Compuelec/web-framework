<?php

/**
 * Activity Logs Page
 * 
 * Interface for viewing activity logs
 */

require_once __DIR__ . '/../../../../controllers/activity_logs.controller.php';

// Get initial logs (table and page are auto-created by template.php on every page load)
$initialLogs = ActivityLogsController::getLogs([], 50, 0);

?>

<div class="container-fluid p-4">
	
	<!-- Page Header -->
	<div class="d-flex justify-content-between align-items-center mb-4">
		<div>
			<h2 class="mb-1">
				<i class="bi bi-journal-text"></i> Logs de Actividad
			</h2>
			<p class="text-muted mb-0">Registro de acciones realizadas en el sistema</p>
		</div>
		<div>
			<button class="btn btn-outline-secondary btn-sm" id="refreshLogsBtn">
				<i class="bi bi-arrow-clockwise"></i> Actualizar
			</button>
			<button class="btn btn-outline-danger btn-sm" id="clearLogsBtn">
				<i class="bi bi-trash"></i> Limpiar Logs
			</button>
		</div>
	</div>

	<!-- Filters Card -->
	<div class="card mb-4">
		<div class="card-header">
			<h5 class="mb-0">
				<i class="bi bi-funnel"></i> Filtros
			</h5>
		</div>
		<div class="card-body">
			<div class="row g-3">
				<div class="col-md-3">
					<label class="form-label">Acción</label>
					<select class="form-select" id="filterAction">
						<option value="">Todas las acciones</option>
						<option value="login">Login</option>
						<option value="logout">Logout</option>
						<option value="create">Crear</option>
						<option value="update">Actualizar</option>
						<option value="delete">Eliminar</option>
						<option value="view">Ver</option>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label">Entidad</label>
					<input type="text" class="form-control" id="filterEntity" placeholder="Ej: admin, page, module">
				</div>
				<div class="col-md-3">
					<label class="form-label">Fecha Desde</label>
					<input type="date" class="form-control" id="filterDateFrom">
				</div>
				<div class="col-md-3">
					<label class="form-label">Fecha Hasta</label>
					<input type="date" class="form-control" id="filterDateTo">
				</div>
			</div>
			<div class="row mt-3">
				<div class="col-12">
					<button class="btn btn-primary" id="applyFiltersBtn">
						<i class="bi bi-search"></i> Aplicar Filtros
					</button>
					<button class="btn btn-outline-secondary" id="clearFiltersBtn">
						<i class="bi bi-x-circle"></i> Limpiar Filtros
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Logs Table Card -->
	<div class="card">
		<div class="card-header d-flex justify-content-between align-items-center">
			<h5 class="mb-0">
				<i class="bi bi-list-ul"></i> Registros de Actividad
			</h5>
			<span class="badge bg-primary" id="logsCount">0 registros</span>
		</div>
		<div class="card-body">
			<div class="table-responsive">
				<table class="table table-hover table-striped" id="logsTable">
					<thead>
						<tr>
							<th>Fecha/Hora</th>
							<th>Acción</th>
							<th>Entidad</th>
							<th>ID Entidad</th>
							<th>Usuario</th>
							<th>IP</th>
							<th>Descripción</th>
						</tr>
					</thead>
					<tbody id="logsTableBody">
						<?php if (empty($initialLogs)): ?>
							<tr>
								<td colspan="7" class="text-center text-muted py-4">
									<i class="bi bi-inbox fs-1 d-block mb-2"></i>
									No hay registros de actividad aún
								</td>
							</tr>
						<?php else: ?>
							<?php foreach ($initialLogs as $log): ?>
								<tr>
									<td>
										<small><?php echo date('d/m/Y H:i:s', strtotime($log->date_created_log)); ?></small>
									</td>
									<td>
										<span class="badge bg-<?php 
											echo $log->action_log == 'delete' ? 'danger' : 
												($log->action_log == 'create' ? 'success' : 
												($log->action_log == 'update' ? 'warning' : 'info')); 
										?>">
											<?php echo htmlspecialchars($log->action_log); ?>
										</span>
									</td>
									<td><?php echo htmlspecialchars($log->entity_log ?? '-'); ?></td>
									<td>
										<?php if ($log->entity_id_log): ?>
											<code><?php echo htmlspecialchars($log->entity_id_log); ?></code>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ($log->email_admin): ?>
											<div>
												<strong><?php echo htmlspecialchars($log->title_admin ?? $log->email_admin); ?></strong>
												<br>
												<small class="text-muted"><?php echo htmlspecialchars($log->email_admin); ?></small>
											</div>
										<?php else: ?>
											<span class="text-muted">Sistema</span>
										<?php endif; ?>
									</td>
									<td>
										<small class="text-muted"><?php echo htmlspecialchars($log->ip_address_log ?? '-'); ?></small>
									</td>
									<td>
										<?php if ($log->description_log): ?>
											<span title="<?php echo htmlspecialchars($log->description_log); ?>">
												<?php echo htmlspecialchars(mb_substr($log->description_log, 0, 50)) . (mb_strlen($log->description_log) > 50 ? '...' : ''); ?>
											</span>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			
			<!-- Pagination -->
			<div class="d-flex justify-content-between align-items-center mt-3" id="paginationContainer" style="display: none !important;">
				<div>
					<small class="text-muted">Mostrando <span id="showingFrom">0</span> - <span id="showingTo">0</span> de <span id="totalLogs">0</span> registros</small>
				</div>
				<nav>
					<ul class="pagination pagination-sm mb-0" id="pagination">
						<!-- Pagination will be generated by JavaScript -->
					</ul>
				</nav>
			</div>
		</div>
	</div>

</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">
					<i class="bi bi-info-circle"></i> Detalles del Log
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="logDetailsContent">
				<!-- Content will be loaded dynamically -->
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<script>
$(document).ready(function() {
	let currentPage = 0;
	let currentLimit = 50;
	let currentFilters = {};
	
	// Load logs function
	function loadLogs(page = 0, limit = 50, filters = {}) {
		$.ajax({
			url: '<?php echo $cmsBasePath; ?>/ajax/activity_logs.ajax.php',
			method: 'GET',
			data: {
				action: 'get',
				page: page,
				limit: limit,
				...filters
			},
			dataType: 'json',
			beforeSend: function() {
				$('#logsTableBody').html('<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></td></tr>');
			},
			success: function(response) {
				if (response.success && response.data) {
					displayLogs(response.data);
					updatePagination(response.total || response.data.length, page, limit);
				} else {
					$('#logsTableBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Error al cargar los logs: ' + (response.error || 'Error desconocido') + '</td></tr>');
				}
			},
			error: function() {
				$('#logsTableBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Error al conectar con el servidor</td></tr>');
			}
		});
	}
	
	// Display logs in table
	function displayLogs(logs) {
		if (!logs || logs.length === 0) {
			$('#logsTableBody').html('<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay registros que coincidan con los filtros</td></tr>');
			$('#logsCount').text('0 registros');
			return;
		}
		
		let html = '';
		logs.forEach(function(log) {
			const actionClass = log.action_log == 'delete' ? 'danger' : 
								log.action_log == 'create' ? 'success' : 
								log.action_log == 'update' ? 'warning' : 'info';
			
			const date = new Date(log.date_created_log);
			const formattedDate = date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES');
			
			html += '<tr>';
			html += '<td><small>' + formattedDate + '</small></td>';
			html += '<td><span class="badge bg-' + actionClass + '">' + escapeHtml(log.action_log) + '</span></td>';
			html += '<td>' + escapeHtml(log.entity_log || '-') + '</td>';
			html += '<td>' + (log.entity_id_log ? '<code>' + escapeHtml(log.entity_id_log) + '</code>' : '<span class="text-muted">-</span>') + '</td>';
			html += '<td>';
			if (log.email_admin) {
				html += '<div><strong>' + escapeHtml(log.title_admin || log.email_admin) + '</strong><br><small class="text-muted">' + escapeHtml(log.email_admin) + '</small></div>';
			} else {
				html += '<span class="text-muted">Sistema</span>';
			}
			html += '</td>';
			html += '<td><small class="text-muted">' + escapeHtml(log.ip_address_log || '-') + '</small></td>';
			html += '<td>';
			if (log.description_log) {
				const desc = log.description_log.length > 50 ? log.description_log.substring(0, 50) + '...' : log.description_log;
				html += '<span title="' + escapeHtml(log.description_log) + '">' + escapeHtml(desc) + '</span>';
			} else {
				html += '<span class="text-muted">-</span>';
			}
			html += '</td>';
			html += '</tr>';
		});
		
		$('#logsTableBody').html(html);
		$('#logsCount').text(logs.length + ' registros');
	}
	
	// Update pagination
	function updatePagination(total, page, limit) {
		// Simple pagination - can be enhanced later
		$('#showingFrom').text(page * limit + 1);
		$('#showingTo').text(Math.min((page + 1) * limit, total));
		$('#totalLogs').text(total);
	}
	
	// Escape HTML
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text ? text.toString().replace(/[&<>"']/g, m => map[m]) : '';
	}
	
	// Refresh logs button
	$('#refreshLogsBtn').on('click', function() {
		loadLogs(currentPage, currentLimit, currentFilters);
	});
	
	// Apply filters button
	$('#applyFiltersBtn').on('click', function() {
		currentFilters = {};
		currentPage = 0;
		
		const action = $('#filterAction').val();
		const entity = $('#filterEntity').val();
		const dateFrom = $('#filterDateFrom').val();
		const dateTo = $('#filterDateTo').val();
		
		if (action) currentFilters.action = action;
		if (entity) currentFilters.entity = entity;
		if (dateFrom) currentFilters.date_from = dateFrom;
		if (dateTo) currentFilters.date_to = dateTo;
		
		loadLogs(currentPage, currentLimit, currentFilters);
	});
	
	// Clear filters button
	$('#clearFiltersBtn').on('click', function() {
		$('#filterAction').val('');
		$('#filterEntity').val('');
		$('#filterDateFrom').val('');
		$('#filterDateTo').val('');
		currentFilters = {};
		currentPage = 0;
		loadLogs(currentPage, currentLimit, currentFilters);
	});
	
	// Clear logs button
	$('#clearLogsBtn').on('click', function() {
		if (!confirm('¿Estás seguro de que deseas eliminar todos los logs de actividad? Esta acción no se puede deshacer.')) {
			return;
		}
		
		$.ajax({
			url: '<?php echo $cmsBasePath; ?>/ajax/activity_logs.ajax.php',
			method: 'POST',
			data: {
				action: 'clear'
			},
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					alert('Logs eliminados exitosamente');
					loadLogs(currentPage, currentLimit, currentFilters);
				} else {
					alert('Error al eliminar logs: ' + (response.error || 'Error desconocido'));
				}
			},
			error: function() {
				alert('Error al conectar con el servidor');
			}
		});
	});
	
	// Auto-refresh every 30 seconds
	setInterval(function() {
		loadLogs(currentPage, currentLimit, currentFilters);
	}, 30000);
	
	// Initial load
	loadLogs(currentPage, currentLimit, currentFilters);
});
</script>
