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

<div class="container-fluid py-4 px-4" id="web-pages-builder">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-semibold">
                <i class="bi bi-window-plus me-2 text-primary"></i>Generador de Páginas Web
            </h4>
            <small class="text-muted">Crea y edita páginas públicas para tus tablas, sin escribir código</small>
        </div>
        <button class="btn btn-sm btn-outline-secondary" id="wpb-new"><i class="bi bi-plus-lg me-1"></i>Nueva página</button>
    </div>

    <div class="row g-4">
        <!-- Existing pages to edit -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header fw-semibold">Páginas creadas</div>
                <div class="list-group list-group-flush" id="wpb-pages">
                    <div class="list-group-item text-muted small">Cargando…</div>
                </div>
            </div>
        </div>

        <!-- Builder form -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <input type="hidden" id="wpb-editing" value="">

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-table">Tabla</label>
                        <select class="form-select" id="wpb-table"><option value="">Cargando…</option></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-heading">Título de la página</label>
                        <input type="text" class="form-control" id="wpb-heading" placeholder="Ej: Nuestros Productos">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-intro">Texto introductorio (opcional)</label>
                        <textarea class="form-control" id="wpb-intro" rows="2" placeholder="Un párrafo breve bajo el título"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-title">Columna del título</label>
                        <select class="form-select" id="wpb-title" disabled><option value="">Elige una tabla</option></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Columnas a mostrar</label>
                        <div id="wpb-columns" class="border rounded p-2" style="max-height: 160px; overflow:auto;">
                            <span class="text-muted small">Elige una tabla</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-7 mb-3">
                            <label class="form-label fw-semibold">Diseño</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="wpb-layout" id="wpb-l-cards" value="cards" checked>
                                <label class="btn btn-outline-secondary btn-sm" for="wpb-l-cards"><i class="bi bi-grid-3x3-gap"></i> Tarjetas</label>
                                <input type="radio" class="btn-check" name="wpb-layout" id="wpb-l-table" value="table">
                                <label class="btn btn-outline-secondary btn-sm" for="wpb-l-table"><i class="bi bi-table"></i> Tabla</label>
                                <input type="radio" class="btn-check" name="wpb-layout" id="wpb-l-list" value="list">
                                <label class="btn btn-outline-secondary btn-sm" for="wpb-l-list"><i class="bi bi-list-ul"></i> Lista</label>
                            </div>
                        </div>
                        <div class="col-3 mb-3">
                            <label class="form-label fw-semibold" for="wpb-perrow">Por fila</label>
                            <select class="form-select" id="wpb-perrow">
                                <option>2</option><option selected>3</option><option>4</option>
                            </select>
                        </div>
                        <div class="col-2 mb-3">
                            <label class="form-label fw-semibold" for="wpb-accent">Color</label>
                            <input type="color" class="form-control form-control-color w-100" id="wpb-accent" value="#0d6efd">
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="wpb-detail">
                        <label class="form-check-label" for="wpb-detail">Generar también una página de detalle (vista individual de cada registro)</label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-name">Nombre del archivo</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="wpb-name" placeholder="se completa con la tabla">
                            <span class="input-group-text">.php</span>
                        </div>
                    </div>

                    <!-- Advanced -->
                    <div class="accordion mb-3" id="wpb-advanced">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#wpb-adv-body">
                                    <i class="bi bi-code-slash me-2"></i>Avanzado: CSS, HTML y JavaScript
                                </button>
                            </h2>
                            <div id="wpb-adv-body" class="accordion-collapse collapse" data-bs-parent="#wpb-advanced">
                                <div class="accordion-body">
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold" for="wpb-css">CSS personalizado</label>
                                        <textarea class="form-control font-monospace" id="wpb-css" rows="3" placeholder=".card { border-radius: 12px; }"></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold" for="wpb-html">HTML personalizado (debajo del título)</label>
                                        <textarea class="form-control font-monospace" id="wpb-html" rows="3" placeholder="&lt;div&gt;…&lt;/div&gt;"></textarea>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label small fw-semibold" for="wpb-js">JavaScript personalizado</label>
                                        <textarea class="form-control font-monospace" id="wpb-js" rows="3" placeholder="console.log('hola');"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary" id="wpb-generate" disabled>
                        <i class="bi bi-magic me-1"></i><span id="wpb-generate-label">Crear página</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Result / preview -->
        <div class="col-lg-4">
            <div id="wpb-result"></div>
        </div>
    </div>
</div>
