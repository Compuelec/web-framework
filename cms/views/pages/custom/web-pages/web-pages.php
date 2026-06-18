<?php
if (!isset($_SESSION['admin'])) {
    header('Location: ' . ($cmsBasePath ?? '') . '/login');
    exit;
}
if (!in_array($_SESSION['admin']->rol_admin ?? '', ['superadmin', 'admin'], true)) {
    echo '<div class="container-fluid p-4"><div class="alert alert-danger">No tienes permisos para acceder a esta página.</div></div>';
    return;
}
?>

<!--=============================================
CodeMirror 5 for the builder's code editors. The CMS loads the legacy
CodeMirror 3 in the <head>, so we stash that reference, load CM5, capture it as
WPB_CM, and restore the original global CodeMirror — keeping the two isolated.
===============================================-->
<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/plugins/codemirror5/codemirror5.css">
<script>window.WPB_CM_PREV = window.CodeMirror;</script>
<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/codemirror5/codemirror5.js"></script>
<script>window.WPB_CM = window.CodeMirror; window.CodeMirror = window.WPB_CM_PREV;</script>

<div class="container-fluid py-4 px-4" id="web-pages-builder">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-semibold">
                <i class="bi bi-window-plus me-2 text-primary"></i>Generador de Páginas Web
            </h4>
            <small class="text-muted">Escribe tu HTML e inserta los datos de tu tabla donde quieras</small>
        </div>
        <button class="btn btn-sm btn-outline-secondary" id="wpb-new"><i class="bi bi-plus-lg me-1"></i>Nueva página</button>
    </div>

    <div class="row g-3">
        <!-- Existing pages -->
        <div class="col-lg-2">
            <div class="card">
                <div class="card-header fw-semibold py-2">Páginas creadas</div>
                <div class="list-group list-group-flush" id="wpb-pages">
                    <div class="list-group-item text-muted small">Cargando…</div>
                </div>
            </div>
        </div>

        <!-- Editor -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <input type="hidden" id="wpb-editing" value="">

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold" for="wpb-table">Tabla de datos</label>
                            <select class="form-select form-select-sm" id="wpb-table"><option value="">Cargando…</option></select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold" for="wpb-heading">Título de la página</label>
                            <input type="text" class="form-control form-control-sm" id="wpb-heading" placeholder="Opcional">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">Insertar datos</label>
                        <div class="small text-muted mb-1">
                            Haz clic en un campo para insertar su etiqueta. Para listar <b>todos</b> los registros,
                            envuelve el HTML con el botón <span class="badge bg-secondary">Repetir</span>.
                        </div>
                        <div id="wpb-fields" class="mb-2"><span class="text-muted small">Elige una tabla</span></div>
                        <button type="button" class="btn btn-sm btn-outline-primary me-1" id="wpb-repeat" disabled>
                            <i class="bi bi-arrow-repeat me-1"></i>Repetir por cada registro
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" id="wpb-form" disabled>
                            <i class="bi bi-ui-checks me-1"></i>Insertar formulario (crear/editar)
                        </button>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="wpb-private">
                        <label class="form-check-label" for="wpb-private">
                            Página privada — requiere iniciar sesión (admins) para verla/editarla
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-template">Tu HTML</label>
                        <textarea class="form-control font-monospace" id="wpb-template" rows="12"
                            placeholder="&lt;div class=&quot;row&quot;&gt;&#10;  {{#cada}}&#10;    &lt;div class=&quot;col-4&quot;&gt;{{name}}&lt;/div&gt;&#10;  {{/cada}}&#10;&lt;/div&gt;"></textarea>
                    </div>

                    <div class="accordion mb-3" id="wpb-advanced">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#wpb-adv-body">
                                    <i class="bi bi-code-slash me-2"></i>CSS y JavaScript
                                </button>
                            </h2>
                            <div id="wpb-adv-body" class="accordion-collapse collapse" data-bs-parent="#wpb-advanced">
                                <div class="accordion-body">
                                    <label class="form-label small fw-semibold" for="wpb-css">CSS</label>
                                    <textarea class="form-control font-monospace mb-2" id="wpb-css" rows="3" placeholder=".card { border-radius: 12px; }"></textarea>
                                    <label class="form-label small fw-semibold" for="wpb-js">JavaScript</label>
                                    <textarea class="form-control font-monospace" id="wpb-js" rows="3" placeholder="console.log('hola');"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-end gap-2">
                        <div class="flex-grow-1">
                            <label class="form-label fw-semibold" for="wpb-name">Nombre del archivo</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="wpb-name" placeholder="se completa con la tabla">
                                <span class="input-group-text">.php</span>
                            </div>
                        </div>
                        <button class="btn btn-primary" id="wpb-generate" disabled>
                            <i class="bi bi-magic me-1"></i><span id="wpb-generate-label">Crear página</span>
                        </button>
                    </div>
                    <div id="wpb-result" class="mt-3"></div>
                </div>
            </div>
        </div>

        <!-- Live preview -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold"><i class="bi bi-eye me-1"></i>Vista previa</span>
                    <small class="text-muted" id="wpb-preview-info"></small>
                </div>
                <div class="card-body p-0">
                    <iframe id="wpb-preview" style="width:100%; height:600px; border:0;" title="Vista previa"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
