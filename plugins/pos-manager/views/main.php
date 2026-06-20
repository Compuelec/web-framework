<?php

// Cashier point-of-sale UI. Rendered inside the CMS template ($cmsBasePath in
// scope). The plugin assets live at the project root, so strip the trailing /cms.

$cmsBasePath     = $cmsBasePath ?? '';
$projectBasePath = preg_replace('#/cms$#', '', $cmsBasePath);

require_once __DIR__ . '/../controllers/pos-manager.controller.php';
$posCtrl  = new PosManagerController();
$posReady = $posCtrl->isConfigured();
$adminId  = (int) ($_SESSION['admin']->id_admin ?? 0);
$isSuper  = (($_SESSION['admin']->rol_admin ?? '') === 'superadmin');
?>

<link rel="stylesheet" href="<?php echo $projectBasePath ?>/plugins/pos-manager/assets/css/pos-manager.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/pos-manager.css') ?>">

<div class="container-fluid py-4 px-4" id="pos-app">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>Caja (POS)</h4>
            <small class="text-muted">Vende productos y descuenta el stock al instante</small>
        </div>
        <?php if ($isSuper): ?>
            <button class="btn btn-sm btn-outline-secondary" id="pos-settings-btn">
                <i class="bi bi-gear me-1"></i>Configuración
            </button>
        <?php endif; ?>
    </div>

    <?php if (!$posReady): ?>

        <div class="alert alert-warning">
            <strong>POS no configurado:</strong> <?php echo htmlspecialchars($posCtrl->configError()); ?>
            <?php if ($isSuper): ?>Abre <strong>Configuración&nbsp;⚙</strong> y mapea tus tablas.<?php endif; ?>
        </div>

    <?php else: ?>

        <div class="row g-3">
            <!-- Products -->
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <input type="text" id="pos-search" class="form-control mb-3" placeholder="Buscar producto…" autocomplete="off">
                        <div id="pos-products" class="row g-2"><div class="text-muted small">Cargando…</div></div>
                    </div>
                </div>
            </div>
            <!-- Cart -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header fw-semibold py-2"><i class="bi bi-cart3 me-1"></i>Carrito</div>
                    <div class="card-body">
                        <div id="pos-cart"><p class="text-muted small mb-0">Agrega productos para vender.</p></div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Total</span>
                            <span class="fs-4 fw-bold text-primary" id="pos-total">$0</span>
                        </div>
                        <div class="mt-3">
                            <label class="form-label small fw-semibold">Método de pago</label>
                            <select id="pos-payment" class="form-select form-select-sm">
                                <?php foreach ($posCtrl->paymentMethods() as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m) ?>"><?php echo htmlspecialchars(ucfirst($m)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button id="pos-confirm" class="btn btn-primary w-100 mt-3" disabled>
                            <i class="bi bi-check2-circle me-1"></i>Confirmar venta
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- Receipt modal -->
<div class="modal fade" id="pos-receipt-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-1"></i>Comprobante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="pos-receipt-body"></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>

<?php if ($isSuper): ?>
<!-- Settings modal (superadmin) -->
<div class="modal fade" id="pos-settings-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-gear me-1"></i>Configuración del POS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Mapea las tablas y columnas de tu proyecto. No necesitas editar archivos.</p>
                <div id="pos-cfg-product" class="mb-3"></div>
                <div id="pos-cfg-sale" class="mb-3"></div>
                <div id="pos-cfg-sale_item" class="mb-3"></div>
                <hr>
                <label class="form-label fw-semibold small">Métodos de pago</label>
                <div id="pos-cfg-payments" class="d-flex flex-wrap gap-2 mb-2"></div>
                <div class="input-group input-group-sm" style="max-width:320px">
                    <input type="text" id="pos-cfg-pay-new" class="form-control" placeholder="Ej: transferencia">
                    <button type="button" class="btn btn-outline-secondary" id="pos-cfg-pay-add"><i class="bi bi-plus-lg"></i> Agregar</button>
                </div>
                <div id="pos-cfg-msg" class="small mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="pos-cfg-save"><i class="bi bi-check-lg me-1"></i>Guardar configuración</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    window.POS_AJAX  = <?php echo json_encode($projectBasePath . '/plugins/pos-manager/ajax.php') ?>;
    window.POS_ADMIN = <?php echo $adminId ?>;
    window.POS_SUPER = <?php echo $isSuper ? 'true' : 'false' ?>;
</script>
<script src="<?php echo $projectBasePath ?>/plugins/pos-manager/assets/js/pos-manager.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/pos-manager.js') ?>"></script>
