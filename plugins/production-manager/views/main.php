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

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-hammer me-2"></i>Fabricación</h4>
            <small class="text-muted">Produce unidades de un producto; descuenta los insumos según su receta</small>
        </div>
    </div>

    <?php if (!$pmReady): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($pmCtrl->configError()) ?></div>
    <?php else: ?>

    <div class="row g-3">
        <!-- Product picker -->
        <div class="col-lg-5">
            <div class="card rounded shadow-sm">
                <div class="card-body">
                    <label class="form-label small fw-semibold">Producto a fabricar</label>
                    <input type="text" id="pm-search" class="form-control form-control-sm mb-2" placeholder="Buscar producto…" autocomplete="off">
                    <div id="pm-results" class="list-group list-group-flush" style="max-height:320px;overflow:auto"></div>
                </div>
            </div>
        </div>

        <!-- Production panel -->
        <div class="col-lg-7">
            <div class="card rounded shadow-sm">
                <div class="card-body">
                    <div id="pm-empty" class="text-muted small">Selecciona un producto para ver su receta.</div>
                    <div id="pm-panel" style="display:none">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="fw-semibold" id="pm-prod-name"></div>
                                <small class="text-muted">Stock actual: <span id="pm-prod-stock"></span></small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label small mb-0">Cantidad</label>
                                <input type="number" id="pm-qty" class="form-control form-control-sm" min="1" step="1" value="1" style="width:90px">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead><tr class="small text-muted"><th>Insumo</th><th class="text-end">Necesita</th><th class="text-end">Disponible</th></tr></thead>
                                <tbody id="pm-recipe"></tbody>
                            </table>
                        </div>
                        <div id="pm-norecipe" class="text-danger small" style="display:none"><i class="bi bi-exclamation-circle me-1"></i>Este producto no tiene receta definida.</div>

                        <button id="pm-produce" class="btn btn-primary w-100 mt-2" disabled>
                            <i class="bi bi-hammer me-1"></i>Fabricar
                        </button>
                    </div>
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
