<?php
if (!isset($_SESSION['admin'])) {
    header('Location: ' . ($cmsBasePath ?? '') . '/login');
    exit;
}

// Generating server files is an admin-level action (defense in depth — the
// AJAX endpoint enforces the same restriction).
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
            <small class="text-muted">Crea páginas públicas para tus tablas sin escribir código</small>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-table">Tabla</label>
                        <select class="form-select" id="wpb-table">
                            <option value="">Cargando tablas…</option>
                        </select>
                        <div class="form-text">Elige la tabla cuyos registros quieres mostrar.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-title">Columna del título</label>
                        <select class="form-select" id="wpb-title" disabled>
                            <option value="">Selecciona una tabla primero</option>
                        </select>
                        <div class="form-text">El campo que se mostrará como título de cada tarjeta.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="wpb-name">Nombre del archivo</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="wpb-name" placeholder="se completa con el nombre de la tabla">
                            <span class="input-group-text">.php</span>
                        </div>
                        <div class="form-text">Se creará en <code>web/pages/</code>. Solo letras, números, guiones.</div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="wpb-detail" checked>
                        <label class="form-check-label" for="wpb-detail">
                            Generar también la página de <strong>detalle</strong> (vista individual de cada registro)
                        </label>
                    </div>

                    <button class="btn btn-primary" id="wpb-generate" disabled>
                        <i class="bi bi-magic me-1"></i>Generar página
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div id="wpb-result"></div>
        </div>
    </div>
</div>
