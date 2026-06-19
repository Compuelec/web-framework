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

<div class="container-fluid py-4 px-4" id="system-health">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-semibold">
                <i class="bi bi-heart-pulse me-2 text-primary"></i>Estado del Sistema
            </h4>
            <small class="text-muted">Verifica que el sistema pueda escribir en los directorios que necesita</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="sh-check">
                <i class="bi bi-arrow-clockwise me-1"></i>Verificar
            </button>
            <button class="btn btn-sm btn-primary" id="sh-fix">
                <i class="bi bi-wrench-adjustable me-1"></i>Intentar reparar
            </button>
        </div>
    </div>

    <div id="sh-summary" class="mb-3"></div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Directorio</th>
                        <th>Para qué</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody id="sh-rows">
                    <tr><td colspan="3" class="text-muted text-center py-4">Verificando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        El sistema crea y ajusta los directorios que puede. Si alguno necesita un cambio de
        propietario (algo que solo puede hacer el administrador del servidor), se muestra el
        comando exacto que debes ejecutar o entregar a tu proveedor de hosting.
    </p>
</div>
