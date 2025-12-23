<?php

/**
 * Activity Logs Page
 * 
 * Interface for viewing activity logs
 */

require_once __DIR__ . '/../../../../controllers/activity_logs.controller.php';
require_once __DIR__ . '/../../../../controllers/template.controller.php';

// Get CMS base path
$cmsBasePath = TemplateController::cmsBasePath();

// Get initial logs (table and page are auto-created by template.php on every page load)
$initialLogs = ActivityLogsController::getLogs([], 10, 0);

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
			<div class="d-flex justify-content-between align-items-center mt-3" id="paginationContainer">
				<div class="d-flex align-items-center gap-3">
					<small class="text-muted">Mostrando <span id="showingFrom">0</span> - <span id="showingTo">0</span> de <span id="totalLogs">0</span> registros</small>
					<div class="d-flex align-items-center gap-2">
						<label class="form-label mb-0 small">Registros por página:</label>
						<select class="form-select form-select-sm" id="limitSelect" style="width: auto;">
							<option value="10" selected>10</option>
							<option value="25">25</option>
							<option value="50">50</option>
							<option value="100">100</option>
							<option value="200">200</option>
						</select>
					</div>
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
	let currentLimit = 10;
	let currentFilters = {};
	
	// Load logs function
	function loadLogs(page = 0, limit = 10, filters = {}) {
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
				if (response && response.success && response.data !== undefined) {
					// Handle empty array case
					const logsArray = Array.isArray(response.data) ? response.data : [];
					displayLogs(logsArray);
					const total = response.total || 0;
					updatePagination(total, page, limit);
					$('#logsCount').text(total + ' registros');
				} else {
					const errorMsg = (response && response.error) ? response.error : 'Error desconocido';
					$('#logsTableBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Error al cargar los logs: ' + errorMsg + '</td></tr>');
					$('#logsCount').text('0 registros');
					$('#paginationContainer').hide();
				}
			},
			error: function(xhr, status, error) {
				console.error('Error loading logs:', status, error, xhr.responseText);
				$('#logsTableBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Error al conectar con el servidor: ' + error + '</td></tr>');
				$('#logsCount').text('0 registros');
				$('#paginationContainer').hide();
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
		// Note: logsCount will be updated by updatePagination with total count
	}
	
	// Update pagination
	function updatePagination(total, page, limit) {
		const totalPages = Math.ceil(total / limit);
		const showingFrom = total > 0 ? page * limit + 1 : 0;
		const showingTo = Math.min((page + 1) * limit, total);
		
		$('#showingFrom').text(showingFrom);
		$('#showingTo').text(showingTo);
		$('#totalLogs').text(total);
		
		// Generate pagination buttons
		let paginationHtml = '';
		
		if (totalPages <= 1) {
			$('#pagination').html('');
			$('#paginationContainer').hide();
			return;
		}
		
		$('#paginationContainer').show();
		
		// Previous button
		paginationHtml += '<li class="page-item' + (page === 0 ? ' disabled' : '') + '">';
		paginationHtml += '<a class="page-link" href="javascript:void(0);" data-page="' + (page - 1) + '">';
		paginationHtml += '<i class="bi bi-chevron-left"></i>';
		paginationHtml += '</a></li>';
		
		// Page numbers
		const maxVisiblePages = 7;
		let startPage = Math.max(0, page - Math.floor(maxVisiblePages / 2));
		let endPage = Math.min(totalPages - 1, startPage + maxVisiblePages - 1);
		
		// Adjust start if we're near the end
		if (endPage - startPage < maxVisiblePages - 1) {
			startPage = Math.max(0, endPage - maxVisiblePages + 1);
		}
		
		// First page
		if (startPage > 0) {
			paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="0">1</a></li>';
			if (startPage > 1) {
				paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
			}
		}
		
		// Page range
		for (let i = startPage; i <= endPage; i++) {
			paginationHtml += '<li class="page-item' + (i === page ? ' active' : '') + '">';
			paginationHtml += '<a class="page-link" href="javascript:void(0);" data-page="' + i + '">' + (i + 1) + '</a>';
			paginationHtml += '</li>';
		}
		
		// Last page
		if (endPage < totalPages - 1) {
			if (endPage < totalPages - 2) {
				paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
			}
			paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="' + (totalPages - 1) + '">' + totalPages + '</a></li>';
		}
		
		// Next button
		paginationHtml += '<li class="page-item' + (page >= totalPages - 1 ? ' disabled' : '') + '">';
		paginationHtml += '<a class="page-link" href="javascript:void(0);" data-page="' + (page + 1) + '">';
		paginationHtml += '<i class="bi bi-chevron-right"></i>';
		paginationHtml += '</a></li>';
		
		$('#pagination').html(paginationHtml);
		
		// Attach click handlers to pagination links after HTML is updated
		$('#pagination .page-link').off('click.pagination').on('click.pagination', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			if ($(this).parent().hasClass('disabled')) {
				return false;
			}
			
			const newPage = parseInt($(this).data('page'));
			
			if (!isNaN(newPage) && newPage >= 0 && newPage !== currentPage) {
				currentPage = newPage;
				loadLogs(currentPage, currentLimit, currentFilters);
				// Scroll to top of table
				$('html, body').animate({
					scrollTop: $('#logsTable').offset().top - 100
				}, 300);
			}
			return false;
		});
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
		
		// Use 'filter_action' to avoid conflict with AJAX 'action' parameter
		if (action) currentFilters.filter_action = action;
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
	
	// Limit selector change
	$('#limitSelect').on('change', function() {
		currentLimit = parseInt($(this).val());
		currentPage = 0;
		loadLogs(currentPage, currentLimit, currentFilters);
	});
	
	// Clear logs button
	$('#clearLogsBtn').on('click', function() {
		Swal.fire({
            title: '¿Estás seguro?',
            text: '¿Estás seguro de que deseas eliminar todos los logs de actividad? Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) {
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
						Swal.fire('Logs eliminados exitosamente', '', 'success');
						currentPage = 0;
						loadLogs(currentPage, currentLimit, currentFilters);
					} else {
						Swal.fire('Error', 'Error al eliminar logs: ' + (response.error || 'Error desconocido'), 'error');
					}
				},
				error: function() {
					Swal.fire('Error', 'Error al conectar con el servidor', 'error');
				}
			});
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
