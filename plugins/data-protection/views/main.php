<?php

// Data Protection UI. Rendered inside the CMS template ($cmsBasePath in scope).
// Plugin assets live at the project root, so strip the trailing /cms.

$cmsBasePath     = $cmsBasePath ?? '';
$projectBasePath = rtrim(preg_replace('#/cms/?$#', '', $cmsBasePath), '/');

require_once __DIR__ . '/../controllers/data-protection.controller.php';
$dpCtrl  = new DataProtectionController();
$dpReady = $dpCtrl->isConfigured();          // DB connection OK
$hasData = $dpReady && $dpCtrl->hasDatasets(); // at least one table configured
$stats   = $dpReady ? $dpCtrl->requestStats() : ['pending' => 0, 'overdue' => 0];
$startCfg = $dpReady && !$hasData;            // start on the config tab if nothing set up
?>

<link rel="stylesheet" href="<?php echo $projectBasePath ?>/plugins/data-protection/assets/css/data-protection.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/data-protection.css') ?>">

<div class="container-fluid py-3" id="dp-app">

    <div class="d-flex align-items-center mb-3">
        <div class="dp-logo me-3"><i class="bi bi-shield-lock"></i></div>
        <div>
            <h4 class="mb-0 fw-bold">Protección de Datos <small class="text-muted fw-normal">· Ley 21.719</small></h4>
            <small class="text-muted">Marca qué tablas tienen datos personales y atiende los derechos de los titulares (ARCOP)</small>
        </div>
    </div>

    <?php if (!$dpReady): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($dpCtrl->configError()) ?></div>
    <?php else: ?>

    <?php if ($startCfg): ?>
        <div class="alert alert-info py-2"><i class="bi bi-info-circle me-1"></i>
            <strong>Primer paso:</strong> en la pestaña <strong>Configuración</strong> marca qué tablas guardan datos personales. Luego podrás buscar, exportar y borrar los datos de cada persona.
        </div>
    <?php endif; ?>

    <ul class="nav nav-pills dp-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link <?php echo $startCfg ? '' : 'active' ?>" data-bs-toggle="pill" data-bs-target="#dp-tab-requests" type="button" id="dp-requests-tab">
            <i class="bi bi-inbox me-1"></i>Solicitudes ARCOP
            <?php if ($stats['pending']): ?><span class="badge rounded-pill bg-light text-dark ms-1"><?php echo (int)$stats['pending'] ?></span><?php endif; ?>
            <?php if ($stats['overdue']): ?><span class="badge rounded-pill bg-danger ms-1" title="Vencidas"><?php echo (int)$stats['overdue'] ?></span><?php endif; ?>
        </button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#dp-tab-subject" type="button"><i class="bi bi-person-vcard me-1"></i>Buscar titular</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#dp-tab-consents" type="button" id="dp-consents-tab"><i class="bi bi-hand-thumbs-up me-1"></i>Consentimientos</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#dp-tab-rat" type="button" id="dp-rat-tab"><i class="bi bi-journal-text me-1"></i>RAT</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#dp-tab-cookies" type="button" id="dp-cookies-tab"><i class="bi bi-cookie me-1"></i>Cookies</button></li>
        <li class="nav-item"><button class="nav-link <?php echo $startCfg ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#dp-tab-config" type="button" id="dp-config-tab"><i class="bi bi-sliders me-1"></i>Configuración</button></li>
    </ul>

    <div class="tab-content">

        <!-- ============ SOLICITUDES ARCOP ============ -->
        <div class="tab-pane fade <?php echo $startCfg ? '' : 'show active' ?>" id="dp-tab-requests">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="dp-card">
                        <div class="dp-card-h"><i class="bi bi-plus-circle me-1"></i>Registrar solicitud</div>
                        <div class="dp-card-b">
                            <label class="form-label small fw-semibold mb-1">Tipo de derecho</label>
                            <select id="dp-req-type" class="form-select form-select-sm mb-2">
                                <option value="access">Acceso</option>
                                <option value="rectification">Rectificación</option>
                                <option value="cancellation">Cancelación / supresión</option>
                                <option value="opposition">Oposición</option>
                                <option value="portability">Portabilidad</option>
                                <option value="blocking">Bloqueo</option>
                            </select>
                            <label class="form-label small fw-semibold mb-1">Titular (email / RUT / nombre)</label>
                            <input type="text" id="dp-req-subject" class="form-control form-control-sm mb-2" placeholder="ej. juan@correo.cl" autocomplete="off">
                            <label class="form-label small fw-semibold mb-1">Canal</label>
                            <input type="text" id="dp-req-channel" class="form-control form-control-sm mb-2" placeholder="web / email / presencial" autocomplete="off">
                            <label class="form-label small fw-semibold mb-1">Notas</label>
                            <textarea id="dp-req-notes" class="form-control form-control-sm mb-2" rows="2"></textarea>
                            <button id="dp-req-save" class="btn dp-btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Registrar</button>
                            <div class="small text-muted mt-2">Plazo legal de respuesta calculado automáticamente.</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="dp-card">
                        <div class="dp-card-h d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-inbox me-1"></i>Solicitudes</span>
                            <select id="dp-req-filter" class="form-select form-select-sm" style="width:auto">
                                <option value="">Todas</option>
                                <option value="pending">Pendientes</option>
                                <option value="in_progress">En proceso</option>
                                <option value="done">Resueltas</option>
                                <option value="rejected">Rechazadas</option>
                            </select>
                        </div>
                        <div class="dp-card-b p-0">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr class="small text-muted"><th>#</th><th>Tipo</th><th>Titular</th><th>Estado</th><th>Vence</th><th></th></tr></thead>
                                <tbody id="dp-req-rows"><tr><td colspan="6" class="text-muted small p-3">Cargando…</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ BUSCAR TITULAR ============ -->
        <div class="tab-pane fade" id="dp-tab-subject">
            <div class="dp-card">
                <div class="dp-card-b">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>Busca a una persona por su identificador (email, RUT…) en todas las tablas marcadas con datos personales.
                        Luego puedes <strong>exportar</strong> sus datos (acceso/portabilidad) o <strong>borrarlos/anonimizarlos</strong> (cancelación).
                    </div>
                    <div class="input-group mb-3">
                        <input type="text" id="dp-sub-q" class="form-control" placeholder="email, RUT o identificador del titular…" autocomplete="off">
                        <button id="dp-sub-find" class="btn dp-btn-primary"><i class="bi bi-search me-1"></i>Buscar</button>
                    </div>
                    <div id="dp-sub-empty" class="text-muted small py-3 text-center">Ingresa un identificador y busca.</div>
                    <div id="dp-sub-result" style="display:none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><strong id="dp-sub-total">0</strong> registro(s) para <code id="dp-sub-label"></code></div>
                            <div class="btn-group">
                                <button id="dp-sub-export" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Exportar JSON</button>
                                <button id="dp-sub-anon" class="btn btn-sm btn-outline-warning"><i class="bi bi-eraser me-1"></i>Anonimizar</button>
                                <button id="dp-sub-del" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Borrar</button>
                            </div>
                        </div>
                        <div id="dp-sub-datasets"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ CONSENTIMIENTOS ============ -->
        <div class="tab-pane fade" id="dp-tab-consents">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="dp-card">
                        <div class="dp-card-h"><i class="bi bi-plus-circle me-1"></i>Registrar consentimiento</div>
                        <div class="dp-card-b">
                            <label class="form-label small fw-semibold mb-1">Titular (email / RUT)</label>
                            <input type="text" id="dp-con-subject" class="form-control form-control-sm mb-2" autocomplete="off">
                            <label class="form-label small fw-semibold mb-1">Finalidad</label>
                            <input type="text" id="dp-con-purpose" class="form-control form-control-sm mb-2" placeholder="ej. newsletter, marketing" autocomplete="off">
                            <label class="form-label small fw-semibold mb-1">Estado</label>
                            <select id="dp-con-status" class="form-select form-select-sm mb-2">
                                <option value="granted">Otorgado</option>
                                <option value="withdrawn">Revocado</option>
                            </select>
                            <button id="dp-con-save" class="btn dp-btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Registrar</button>
                            <div class="small text-muted mt-2">Los consentimientos del sitio público (banner/formularios) se registran solos aquí.</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="dp-card">
                        <div class="dp-card-h d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-hand-thumbs-up me-1"></i>Registro de consentimientos</span>
                            <div class="d-flex gap-2">
                                <input type="text" id="dp-con-filter" class="form-control form-control-sm" placeholder="filtrar por titular…" style="width:auto">
                                <select id="dp-con-fstatus" class="form-select form-select-sm" style="width:auto">
                                    <option value="">Todos</option><option value="granted">Otorgados</option><option value="withdrawn">Revocados</option>
                                </select>
                            </div>
                        </div>
                        <div class="dp-card-b p-0">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr class="small text-muted"><th>Titular</th><th>Finalidad</th><th>Estado</th><th>Canal</th><th>Fecha</th><th></th></tr></thead>
                                <tbody id="dp-con-rows"><tr><td colspan="6" class="text-muted small p-3">Cargando…</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ RAT (Registro de Actividades de Tratamiento) ============ -->
        <div class="tab-pane fade" id="dp-tab-rat">
            <div class="dp-card">
                <div class="dp-card-h d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-text me-1"></i>Registro de Actividades de Tratamiento (RAT)</span>
                    <button id="dp-rat-print" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Imprimir / PDF</button>
                </div>
                <div class="dp-card-b" id="dp-rat-wrap">
                    <div class="alert alert-info py-2 small mb-3"><i class="bi bi-info-circle me-1"></i>
                        Registro de cada tratamiento de datos personales (accountability). Se genera a partir de las tablas que marcaste en <strong>Configuración</strong>.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle" id="dp-rat-table">
                            <thead class="table-light"><tr class="small">
                                <th>Actividad</th><th>Tabla</th><th>Finalidad</th><th>Categorías de datos</th>
                                <th>Datos sensibles</th><th>Base legal</th><th>Destinatarios</th><th>Retención</th>
                            </tr></thead>
                            <tbody id="dp-rat-rows"><tr><td colspan="8" class="text-muted small p-3">Cargando…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ COOKIES ============ -->
        <div class="tab-pane fade" id="dp-tab-cookies">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="dp-card">
                        <div class="dp-card-h"><i class="bi bi-cookie me-1"></i>Banner de cookies del sitio público</div>
                        <div class="dp-card-b">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="dp-ck-enabled" checked>
                                <label class="form-check-label small fw-semibold" for="dp-ck-enabled">Mostrar el banner en el sitio público</label>
                            </div>
                            <label class="form-label small fw-semibold mb-1">Texto del aviso</label>
                            <textarea id="dp-ck-text" class="form-control form-control-sm mb-2" rows="3"></textarea>
                            <label class="form-label small fw-semibold mb-1">URL de la política de privacidad</label>
                            <input type="text" id="dp-ck-policy" class="form-control form-control-sm mb-2" placeholder="https://tu-sitio.cl/privacidad">
                            <div class="row g-2 mb-2">
                                <div class="col"><label class="form-label small fw-semibold mb-1">Botón aceptar</label><input type="text" id="dp-ck-accept" class="form-control form-control-sm"></div>
                                <div class="col"><label class="form-label small fw-semibold mb-1">Botón rechazar</label><input type="text" id="dp-ck-reject" class="form-control form-control-sm"></div>
                            </div>
                            <div class="d-flex justify-content-end"><button id="dp-ck-save" class="btn dp-btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="dp-card">
                        <div class="dp-card-h"><i class="bi bi-code-slash me-1"></i>Activarlo en tu sitio</div>
                        <div class="dp-card-b">
                            <p class="small text-muted">Agrega esta línea una vez en la plantilla del sitio público (antes de <code>&lt;/body&gt;</code>):</p>
                            <pre class="dp-snippet"><code>&lt;script src="<?php echo htmlspecialchars($projectBasePath) ?>/plugins/data-protection/assets/public/cookie-banner.js" defer&gt;&lt;/script&gt;</code></pre>
                            <p class="small text-muted mb-0">El banner toma el texto de aquí, muestra el aviso una vez por visitante y <strong>registra la decisión</strong> en la pestaña Consentimientos.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ CONFIGURACIÓN ============ -->
        <div class="tab-pane fade <?php echo $startCfg ? 'show active' : '' ?>" id="dp-tab-config">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="dp-card">
                        <div class="dp-card-h"><i class="bi bi-table me-1"></i>Tablas con datos personales</div>
                        <div class="dp-card-b">
                            <div id="dp-cfg-list"><div class="text-muted small">Cargando…</div></div>
                            <hr>
                            <label class="form-label small fw-semibold mb-1">Agregar / editar una tabla</label>
                            <select id="dp-cfg-table" class="form-select form-select-sm"><option value="">Selecciona una tabla…</option></select>
                            <div class="small text-muted mt-1">Elige una tabla y marca abajo qué columnas son datos personales.</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="dp-card">
                        <div class="dp-card-h"><i class="bi bi-ui-checks me-1"></i>Columnas</div>
                        <div class="dp-card-b">
                            <div id="dp-cfg-empty" class="text-muted small py-3 text-center">Selecciona una tabla a la izquierda.</div>
                            <div id="dp-cfg-editor" style="display:none">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-7">
                                        <label class="form-label small fw-semibold mb-1">Nombre visible</label>
                                        <input type="text" id="dp-cfg-label" class="form-control form-control-sm" placeholder="ej. Clientes">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-semibold mb-1">Columna clave (única)</label>
                                        <select id="dp-cfg-pk" class="form-select form-select-sm"></select>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm dp-cfg-cols align-middle">
                                        <thead><tr class="small text-muted">
                                            <th>Columna</th>
                                            <th class="text-center" title="Incluir en exportación/búsqueda">Dato personal</th>
                                            <th class="text-center" title="Identifica al titular (email, RUT)">Identifica</th>
                                            <th class="text-center">Sensible</th>
                                            <th>Al anonimizar</th>
                                        </tr></thead>
                                        <tbody id="dp-cfg-cols"></tbody>
                                    </table>
                                </div>
                                <div class="row g-2 mt-1">
                                    <div class="col-md-5">
                                        <label class="form-label small fw-semibold mb-1">Finalidad</label>
                                        <input type="text" id="dp-cfg-purpose" class="form-control form-control-sm" placeholder="ej. Gestión de ventas">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold mb-1">Base legal</label>
                                        <select id="dp-cfg-legal" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            <option>Consentimiento</option>
                                            <option>Ejecución de contrato</option>
                                            <option>Obligación legal</option>
                                            <option>Interés legítimo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold mb-1">Retención (días)</label>
                                        <input type="number" id="dp-cfg-retention" class="form-control form-control-sm" min="0" placeholder="opcional">
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label small fw-semibold mb-1">Destinatarios <small class="text-muted">(con quién se comparten)</small></label>
                                    <input type="text" id="dp-cfg-recipients" class="form-control form-control-sm" placeholder="ej. proveedor de pagos, contabilidad externa…">
                                </div>
                                <div class="d-flex justify-content-end mt-3">
                                    <button id="dp-cfg-save" class="btn dp-btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar tabla</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>
</div>

<script>
    window.DP_AJAX = <?php echo json_encode($projectBasePath . '/plugins/data-protection/ajax.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?php echo $projectBasePath ?>/plugins/data-protection/assets/js/data-protection.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/data-protection.js') ?>"></script>
