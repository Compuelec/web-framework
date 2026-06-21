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

<!--=============================================
Visual page builder (DnD) — loaded only on this view. The compiler is a
pure module; visual.js wires the modal-fullscreen builder. CSS scoped to
.wpb-visual so it doesn't bleed.
===============================================-->
<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/web-pages/visual-builder.css?v=<?php echo @filemtime(__DIR__ . '/../../../assets/css/web-pages/visual-builder.css') ?>">
<script src="<?php echo $cmsBasePath ?>/views/assets/plugins/sortablejs/Sortable.min.js"></script>
<script src="<?php echo $cmsBasePath ?>/views/assets/js/web-pages/web-pages-compile.js?v=<?php echo @filemtime(__DIR__ . '/../../../assets/js/web-pages/web-pages-compile.js') ?>"></script>
<script src="<?php echo $cmsBasePath ?>/views/assets/js/web-pages/web-pages-visual.js?v=<?php echo @filemtime(__DIR__ . '/../../../assets/js/web-pages/web-pages-visual.js') ?>"></script>

<div class="container-fluid py-4 px-4" id="web-pages-builder">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-semibold">
                <i class="bi bi-window-plus me-2 text-primary"></i>Generador de Páginas Web
            </h4>
            <small class="text-muted">Escribe tu HTML e inserta los datos de tu tabla donde quieras</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-primary" id="wpb-open-visual" type="button"
                    data-bs-toggle="modal" data-bs-target="#wpb-visual-modal">
                <i class="bi bi-grid-3x3-gap me-1"></i>Editar visual (arrastrar bloques)
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="wpb-new"><i class="bi bi-plus-lg me-1"></i>Nueva página</button>
        </div>
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

                    <!-- Page type: data-backed (table) vs blank (free HTML/CSS/JS) -->
                    <div class="mb-3 wpb-page-only">
                        <label class="form-label fw-semibold d-block mb-2">Tipo de página</label>
                        <div class="d-flex gap-2 flex-wrap" id="wpb-mode">
                            <input type="radio" class="btn-check" name="wpb-mode" id="wpb-mode-table" value="table" checked>
                            <label class="btn btn-outline-primary px-3" for="wpb-mode-table"><i class="bi bi-table me-1"></i>Con datos de una tabla</label>
                            <input type="radio" class="btn-check" name="wpb-mode" id="wpb-mode-static" value="static">
                            <label class="btn btn-outline-secondary px-3" for="wpb-mode-static"><i class="bi bi-file-earmark-code me-1"></i>Página en blanco (HTML/CSS/JS)</label>
                        </div>
                    </div>

                    <div class="row wpb-page-only">
                        <div class="col-6 mb-3 wpb-table-only">
                            <label class="form-label fw-semibold" for="wpb-table">Tabla de datos</label>
                            <select class="form-select form-select-sm" id="wpb-table"><option value="">Cargando…</option></select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold" for="wpb-heading">Título de la página</label>
                            <input type="text" class="form-control form-control-sm" id="wpb-heading" placeholder="Opcional">
                        </div>
                    </div>

                    <div class="mb-2 wpb-page-only wpb-table-only">
                        <label class="form-label fw-semibold mb-1">Insertar datos</label>
                        <div class="small text-muted mb-1">
                            Haz clic en un campo para insertar su etiqueta. Para listar <b>todos</b> los registros,
                            envuelve el HTML con el botón <span class="badge bg-secondary">Repetir</span>.
                        </div>
                        <div id="wpb-fields" class="mb-2"><span class="text-muted small">Elige una tabla</span></div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="wpb-repeat" disabled>
                                <i class="bi bi-arrow-repeat me-1"></i>Repetir por cada registro
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success" id="wpb-form" disabled>
                                <i class="bi bi-ui-checks me-1"></i>Insertar formulario (crear/editar)
                            </button>
                        </div>
                    </div>

                    <div class="mb-3 mt-3 wpb-page-only">
                        <label class="form-label fw-semibold d-block mb-2">Acceso a la página</label>
                        <div class="d-flex gap-2 flex-wrap" id="wpb-visibility">
                            <input type="radio" class="btn-check" name="wpb-visibility" id="wpb-vis-public" value="public" checked>
                            <label class="btn btn-outline-success px-3" for="wpb-vis-public"><i class="bi bi-globe2 me-1"></i>Pública</label>
                            <input type="radio" class="btn-check" name="wpb-visibility" id="wpb-vis-private" value="private">
                            <label class="btn btn-outline-primary px-3" for="wpb-vis-private"><i class="bi bi-lock-fill me-1"></i>Privada (con login)</label>
                        </div>
                    </div>

                    <div id="wpb-access" class="border rounded p-2 mb-3 wpb-page-only" style="display:none;">
                        <div class="small text-muted mb-2">Permitir acceso a (si no marcas nada, cualquier usuario logueado entra):</div>
                        <label class="form-label small fw-semibold mb-1">Grupos / roles</label>
                        <div id="wpb-roles" class="mb-2"><span class="text-muted small">—</span></div>
                        <label class="form-label small fw-semibold mb-1">Usuarios específicos</label>
                        <div id="wpb-users" style="max-height:120px; overflow:auto;"><span class="text-muted small">—</span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-template">Tu HTML</label>
                        <textarea class="form-control font-monospace" id="wpb-template" rows="12"
                            placeholder="&lt;div class=&quot;row&quot;&gt;&#10;  {{#cada}}&#10;    &lt;div class=&quot;col-4&quot;&gt;{{name}}&lt;/div&gt;&#10;  {{/cada}}&#10;&lt;/div&gt;"></textarea>
                    </div>

                    <div class="accordion mb-3 wpb-page-only" id="wpb-seo-acc">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#wpb-seo-body">
                                    <i class="bi bi-search me-2"></i>SEO y redes sociales
                                </button>
                            </h2>
                            <div id="wpb-seo-body" class="accordion-collapse collapse" data-bs-parent="#wpb-seo-acc">
                                <div class="accordion-body">
                                    <label class="form-label small fw-semibold" for="wpb-meta-title">Meta Title (buscadores)</label>
                                    <input type="text" class="form-control form-control-sm mb-2" id="wpb-meta-title" maxlength="60" placeholder="Título para Google (máx 60)">
                                    <label class="form-label small fw-semibold" for="wpb-meta-desc">Meta Description</label>
                                    <textarea class="form-control form-control-sm mb-2" id="wpb-meta-desc" rows="2" maxlength="160" placeholder="Descripción para buscadores (máx 160)"></textarea>
                                    <hr class="my-2">
                                    <div class="small text-muted mb-1"><i class="bi bi-share me-1"></i>Open Graph (al compartir en redes)</div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-8">
                                            <input type="text" class="form-control form-control-sm" id="wpb-og-title" placeholder="OG Title (vacío = Meta Title)">
                                        </div>
                                        <div class="col-4">
                                            <select class="form-select form-select-sm" id="wpb-og-type">
                                                <option value="website">website</option>
                                                <option value="article">article</option>
                                                <option value="product">product</option>
                                                <option value="profile">profile</option>
                                            </select>
                                        </div>
                                    </div>
                                    <textarea class="form-control form-control-sm mb-2" id="wpb-og-desc" rows="2" placeholder="OG Description (vacío = Meta Description)"></textarea>
                                    <input type="text" class="form-control form-control-sm" id="wpb-og-image" placeholder="OG Image — URL de imagen para compartir">
                                </div>
                            </div>
                        </div>
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

                    <div class="form-check mb-2 wpb-page-only">
                        <input class="form-check-input" type="checkbox" id="wpb-home">
                        <label class="form-check-label small" for="wpb-home">
                            <i class="bi bi-house-door me-1"></i>Usar como <strong>página de inicio</strong> (la raíz del dominio abrirá esta página)
                        </label>
                    </div>

                    <div class="d-flex align-items-end gap-2">
                        <div class="flex-grow-1 wpb-page-only">
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

<!--=============================================
Visual builder — fullscreen modal

Bootstrap 5 `.modal-fullscreen`. The visual surface lives here so it gets
the whole viewport for the palette / canvas / props panels.

Saving and the rest of the persistence wiring land in a future commit;
for now the modal hosts the DnD playground and a "Volver al código"
button that closes the modal without changes (data flows commit 7/N).
===============================================-->
<div class="modal fade" id="wpb-visual-modal" tabindex="-1" aria-labelledby="wpb-visual-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fw-semibold" id="wpb-visual-modal-title">
                    <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Editor visual de páginas
                    <small class="text-muted ms-2" id="wpb-visual-modal-subtitle"></small>
                </h5>
                <div class="d-flex gap-2 align-items-center">
                    <!-- Inline file-name input — mirrors the code-mode
                         #wpb-name field in both directions so the user
                         doesn't have to close the modal to set the file
                         name before saving. -->
                    <div class="input-group input-group-sm" style="width:230px">
                        <span class="input-group-text"><i class="bi bi-file-earmark"></i></span>
                        <input type="text" class="form-control" id="wpb-visual-name"
                               placeholder="nombre-del-archivo" aria-label="Nombre del archivo">
                        <span class="input-group-text">.php</span>
                    </div>
                    <!-- Editor vs. Preview toggle. Replaces the canvas with
                         an iframe that renders the compiled HTML so the
                         user can see how the final page will look without
                         leaving the modal. Palette and props panel are
                         always visible. -->
                    <div class="btn-group btn-group-sm" role="group" aria-label="Modo visual" id="wpb-view-toggle">
                        <input type="radio" class="btn-check" name="wpb-view" id="wpb-view-editor" value="editor" checked>
                        <label class="btn btn-outline-secondary" for="wpb-view-editor"><i class="bi bi-pencil-square me-1"></i>Editor</label>
                        <input type="radio" class="btn-check" name="wpb-view" id="wpb-view-preview" value="preview">
                        <label class="btn btn-outline-secondary" for="wpb-view-preview"><i class="bi bi-eye me-1"></i>Vista previa</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="wpb-visual-save" disabled>
                        <i class="bi bi-check2 me-1"></i>Guardar
                    </button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="wpb-visual" id="wpb-visual-surface">
                    <aside class="wpb-visual-palette" id="wpb-palette">
                        <div class="small text-muted text-center py-4 px-2">
                            <i class="bi bi-three-dots-vertical d-block fs-2 mb-2"></i>
                            Paleta de bloques<br>
                            <em>(próximo commit)</em>
                        </div>
                    </aside>
                    <main class="wpb-visual-canvas" id="wpb-canvas">
                        <div class="text-center text-muted small py-5">
                            <i class="bi bi-arrow-down-square d-block fs-1 mb-2"></i>
                            Arrastrá un bloque desde la paleta para empezar<br>
                            <em>(canvas listo, DnD viene en el próximo commit)</em>
                        </div>
                    </main>
                    <!-- Preview iframe — sandboxed for safety. Its srcdoc
                         is rebuilt by web-pages-visual.js every time the
                         user switches to Vista previa (and when the tree
                         changes while in preview mode). -->
                    <iframe class="wpb-visual-preview"
                            id="wpb-preview-frame"
                            title="Vista previa de la página"
                            sandbox="allow-same-origin"
                            style="display:none"></iframe>
                    <aside class="wpb-visual-props" id="wpb-props">
                        <div class="small text-muted text-center py-4 px-2">
                            <i class="bi bi-sliders d-block fs-2 mb-2"></i>
                            Propiedades del bloque<br>
                            <em>(próximo commit)</em>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</div>

<!--=============================================
Wire the "Editar visual" button to delegate mount/unmount of the visual
surface to web-pages-visual.js. Kept inline because it's view-local.
===============================================-->
<script>
(function () {
    "use strict";
    var $modal = document.getElementById("wpb-visual-modal");
    if (!$modal) { return; }
    // Bootstrap fires show.bs.modal before the modal becomes visible, and
    // shown.bs.modal once it is fully in the DOM. Mount on `shown` so
    // SortableJS measures real container sizes.
    $modal.addEventListener("shown.bs.modal", function () {
        if (window.WebPagesVisual && typeof window.WebPagesVisual.mount === "function") {
            window.WebPagesVisual.mount();
        }
    });
    $modal.addEventListener("hidden.bs.modal", function () {
        if (window.WebPagesVisual && typeof window.WebPagesVisual.unmount === "function") {
            window.WebPagesVisual.unmount();
        }
    });
})();
</script>
