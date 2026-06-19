<?php
// Strip only the trailing "/cms". dirname() would turn "/cms" (CMS at the domain
// root) into "/", making asset URLs "//plugins/..." — a protocol-relative URL the
// browser reads as host "plugins" (https://plugins/...). This yields "" at the root.
$projectBasePath = preg_replace('#/cms$#', '', $cmsBasePath);
$adminId = $_SESSION['admin']->id_admin ?? 0;
?>

<link rel="stylesheet" href="<?php echo $projectBasePath ?>/plugins/dashboard-manager/assets/css/dashboard-manager.css">

<div class="container-fluid py-4 px-4" id="dm-app">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-semibold">
                <i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard
            </h4>
            <small class="text-muted">Tu espacio de trabajo personalizado</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="dm-toggle-edit" title="Editar layout">
                <i class="bi bi-pencil-square me-1"></i>Editar
            </button>
            <button class="btn btn-sm btn-primary" id="dm-add-btn" data-bs-toggle="modal" data-bs-target="#dm-modal">
                <i class="bi bi-plus-lg me-1"></i>Agregar widget
            </button>
        </div>
    </div>

    <!-- Empty state -->
    <div id="dm-empty" class="text-center py-5 d-none">
        <i class="bi bi-grid-3x3-gap display-1 text-muted opacity-50"></i>
        <h5 class="mt-3 text-muted">Sin widgets aún</h5>
        <p class="text-muted small mb-4">Agrega widgets para ver métricas, gráficos y más en tu dashboard.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dm-modal">
            <i class="bi bi-plus-lg me-1"></i>Agregar primer widget
        </button>
    </div>

    <!-- Loading state -->
    <div id="dm-loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="text-muted mt-2 small">Cargando dashboard...</p>
    </div>

    <!-- Widget grid -->
    <div id="dm-grid" class="row g-3"></div>

</div>

<!-- Add / Edit Widget Modal -->
<div class="modal fade" id="dm-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold" id="dm-modal-title">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Nuevo widget
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">

                <!-- Step 1: Type selector (only when adding) -->
                <div id="dm-step-type">
                    <p class="text-muted small mb-3">Elige el tipo de widget:</p>
                    <div class="row g-2" id="dm-type-grid">
                        <div class="col-6 col-md-3">
                            <div class="dm-type-card text-center p-3 rounded border" data-type="metric">
                                <i class="bi bi-bar-chart-line fs-2 text-primary"></i>
                                <div class="small fw-semibold mt-1">Métrica</div>
                                <div class="text-muted" style="font-size:11px">Contador / suma</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="dm-type-card text-center p-3 rounded border" data-type="kpi">
                                <i class="bi bi-graph-up-arrow fs-2 text-success"></i>
                                <div class="small fw-semibold mt-1">KPI + Tendencia</div>
                                <div class="text-muted" style="font-size:11px">Período vs anterior</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="dm-type-card text-center p-3 rounded border" data-type="chart">
                                <i class="bi bi-graph-up fs-2 text-info"></i>
                                <div class="small fw-semibold mt-1">Gráfico</div>
                                <div class="text-muted" style="font-size:11px">Línea / barra</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="dm-type-card text-center p-3 rounded border" data-type="recent">
                                <i class="bi bi-table fs-2 text-warning"></i>
                                <div class="small fw-semibold mt-1">Registros recientes</div>
                                <div class="text-muted" style="font-size:11px">Mini tabla</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="dm-type-card text-center p-3 rounded border" data-type="activity">
                                <i class="bi bi-clock-history fs-2 text-secondary"></i>
                                <div class="small fw-semibold mt-1">Actividad reciente</div>
                                <div class="text-muted" style="font-size:11px">Historial de acciones</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="dm-type-card text-center p-3 rounded border" data-type="quicklinks">
                                <i class="bi bi-lightning-charge fs-2 text-danger"></i>
                                <div class="small fw-semibold mt-1">Accesos rápidos</div>
                                <div class="text-muted" style="font-size:11px">Botones de navegación</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="dm-type-card text-center p-3 rounded border" data-type="html">
                                <i class="bi bi-code-slash fs-2 text-dark"></i>
                                <div class="small fw-semibold mt-1">HTML libre</div>
                                <div class="text-muted" style="font-size:11px">Embeds y notas</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="dm-type-card text-center p-3 rounded border" data-type="system">
                                <i class="bi bi-cpu fs-2 text-muted"></i>
                                <div class="small fw-semibold mt-1">Estado del sistema</div>
                                <div class="text-muted" style="font-size:11px">PHP, MySQL, disco</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Configuration form -->
                <div id="dm-step-config" class="d-none">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <button class="btn btn-sm btn-link p-0 text-muted" id="dm-back-btn">
                            <i class="bi bi-arrow-left me-1"></i>Atrás
                        </button>
                        <span class="text-muted">|</span>
                        <span class="small fw-semibold" id="dm-selected-type-label"></span>
                    </div>

                    <!-- Common fields -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Título del widget</label>
                            <input type="text" class="form-control form-control-sm" id="dm-f-title" placeholder="Ej: Total clientes">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Ancho</label>
                            <select class="form-select form-select-sm" id="dm-f-width">
                                <option value="col-md-4">1/3 (pequeño)</option>
                                <option value="col-md-6">1/2 (mediano)</option>
                                <option value="col-md-8">2/3 (grande)</option>
                                <option value="col-md-12">Completo</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Auto-actualizar</label>
                            <select class="form-select form-select-sm" id="dm-f-refresh">
                                <option value="0">Nunca</option>
                                <option value="30">30 segundos</option>
                                <option value="60">1 minuto</option>
                                <option value="300">5 minutos</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Type-specific fields (rendered by JS) -->
                    <div id="dm-type-fields"></div>
                </div>

            </div>
            <div class="modal-footer border-0 pt-0" id="dm-modal-footer">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary d-none" id="dm-save-btn">
                    <i class="bi bi-check-lg me-1"></i>Guardar widget
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete confirm modal -->
<div class="modal fade" id="dm-delete-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center py-4">
                <i class="bi bi-trash3 fs-1 text-danger mb-2 d-block"></i>
                <p class="mb-0">¿Eliminar este widget?</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm btn-danger" id="dm-confirm-delete">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.DM_ADMIN_ID  = <?php echo (int)$adminId ?>;
    window.DM_CMS_BASE  = <?php echo json_encode($cmsBasePath) ?>;
</script>
<script src="<?php echo $projectBasePath ?>/plugins/dashboard-manager/assets/js/dashboard-manager.js"></script>
