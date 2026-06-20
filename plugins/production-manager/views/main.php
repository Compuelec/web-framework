<?php

// Manufacturing UI. Rendered inside the CMS template ($cmsBasePath in scope).
// Plugin assets live at the project root, so strip the trailing /cms.

$cmsBasePath     = $cmsBasePath ?? '';
$projectBasePath = preg_replace('#/cms/?$#', '', $cmsBasePath);

require_once __DIR__ . '/../controllers/production-manager.controller.php';
$pmCtrl  = new ProductionManagerController();
$pmReady = $pmCtrl->isConfigured();
$adminId = (int) ($_SESSION['admin']->id_admin ?? 0);
?>

<link rel="stylesheet" href="<?php echo $projectBasePath ?>/plugins/production-manager/assets/css/production-manager.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/production-manager.css') ?>">

<div class="container-fluid py-3" id="pm-app">

    <div class="d-flex align-items-center mb-3">
        <div class="pm-logo me-3"><i class="bi bi-gear-wide-connected"></i></div>
        <div>
            <h4 class="mb-0 fw-bold">Fabricación</h4>
            <small class="text-muted">Produce unidades consumiendo los insumos de cada receta</small>
        </div>
    </div>

    <?php if (!$pmReady): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($pmCtrl->configError()) ?></div>
    <?php else: ?>

    <ul class="nav nav-pills pm-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pm-tab-make" type="button"><i class="bi bi-hammer me-1"></i>Fabricar</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pm-tab-recipes" type="button"><i class="bi bi-journal-text me-1"></i>Recetas</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pm-tab-history" type="button" id="pm-history-tab"><i class="bi bi-clock-history me-1"></i>Historial</button></li>
    </ul>

    <div class="tab-content">

        <!-- ============ FABRICAR ============ -->
        <div class="tab-pane fade show active" id="pm-tab-make">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="pm-card">
                        <div class="pm-card-h"><i class="bi bi-box-seam me-1"></i>Producto a fabricar</div>
                        <div class="pm-card-b">
                            <input type="text" id="mk-search" class="form-control form-control-sm mb-2" placeholder="Buscar producto…" autocomplete="off">
                            <div id="mk-results" class="pm-list"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="pm-card">
                        <div class="pm-card-b">
                            <div id="mk-empty" class="text-muted small py-4 text-center"><i class="bi bi-arrow-left-circle me-1"></i>Selecciona un producto.</div>
                            <div id="mk-panel" style="display:none">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="fw-bold fs-5" id="mk-name"></div>
                                        <div class="small text-muted">Stock actual: <span id="mk-stock" class="fw-semibold"></span><span id="mk-yield-note"></span></div>
                                    </div>
                                    <span class="pm-max" id="mk-max"></span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <label class="form-label small mb-0 fw-semibold">Cantidad a fabricar</label>
                                    <input type="number" id="mk-qty" class="form-control form-control-sm" min="1" step="1" value="1" style="width:100px">
                                </div>
                                <table class="table table-sm pm-recipe-table">
                                    <thead><tr class="small text-muted"><th>Insumo</th><th class="text-end">Necesita</th><th class="text-end">Disponible</th></tr></thead>
                                    <tbody id="mk-recipe"></tbody>
                                </table>
                                <div id="mk-norecipe" class="pm-empty-note" style="display:none"><i class="bi bi-exclamation-circle me-1"></i>Sin receta. Defínela en la pestaña <strong>Recetas</strong>.</div>
                                <button id="mk-produce" class="btn pm-btn-primary w-100 mt-2" disabled><i class="bi bi-hammer me-1"></i>Fabricar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ RECETAS ============ -->
        <div class="tab-pane fade" id="pm-tab-recipes">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="pm-card">
                        <div class="pm-card-h"><i class="bi bi-box-seam me-1"></i>Producto</div>
                        <div class="pm-card-b">
                            <input type="text" id="rc-search" class="form-control form-control-sm mb-2" placeholder="Buscar producto…" autocomplete="off">
                            <div id="rc-results" class="pm-list"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="pm-card">
                        <div class="pm-card-b">
                            <div id="rc-empty" class="text-muted small py-4 text-center"><i class="bi bi-arrow-left-circle me-1"></i>Selecciona un producto para editar su receta.</div>
                            <div id="rc-panel" style="display:none">
                                <div class="fw-bold fs-5 mb-1" id="rc-name"></div>
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <label class="form-label small mb-0 fw-semibold">Rinde</label>
                                    <input type="number" id="rc-yield" class="form-control form-control-sm" min="1" step="1" value="1" style="width:90px">
                                    <span class="small text-muted">unidades por lote (las cantidades de abajo son por ese lote)</span>
                                </div>
                                <div class="pm-ing-head small text-muted">Insumos del lote</div>
                                <div id="rc-lines"></div>
                                <div class="pm-add-row mt-2">
                                    <div class="position-relative flex-grow-1">
                                        <input type="text" id="rc-add-search" class="form-control form-control-sm" placeholder="Agregar insumo…" autocomplete="off">
                                        <div id="rc-add-results" class="pm-dropdown"></div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-3">
                                    <button id="rc-save" class="btn pm-btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar receta</button>
                                </div>
                                <div id="rc-msg" class="small mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ HISTORIAL ============ -->
        <div class="tab-pane fade" id="pm-tab-history">
            <div class="pm-card">
                <div class="pm-card-b">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr class="small text-muted"><th>#</th><th>Producto</th><th class="text-end">Cantidad</th><th>Responsable</th><th>Fecha</th></tr></thead>
                        <tbody id="hi-rows"><tr><td colspan="5" class="text-muted small">Cargando…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>
</div>

<!-- Result modal -->
<div class="modal fade" id="pm-result-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-check2-circle me-1"></i>Fabricación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="pm-result-body"></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>

<script>
    window.PM_AJAX = <?php echo json_encode($projectBasePath . '/plugins/production-manager/ajax.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?php echo $projectBasePath ?>/plugins/production-manager/assets/js/production-manager.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/production-manager.js') ?>"></script>
