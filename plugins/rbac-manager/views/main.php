<?php
/**
 * RBAC Manager - Main View
 */
?>

<?php $projectBasePath = preg_replace('#/cms$#', '', $cmsBasePath ?? ''); // "" at root, not "/" (avoids //plugins/... protocol-relative URLs) ?>
<link rel="stylesheet" href="<?php echo $projectBasePath ?>/plugins/rbac-manager/assets/css/rbac-manager.css">

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-1">
                                <i class="bi bi-shield-lock text-primary me-2"></i>
                                RBAC Manager
                            </h4>
                            <p class="text-muted mb-0">Gestión de roles y permisos para administradores</p>
                        </div>
                        <span class="badge bg-primary">v<?php echo $pluginConfig['plugin']['version'] ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="rbacTabs">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-roles">
                <i class="bi bi-shield me-1"></i> Roles
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-assignments">
                <i class="bi bi-people me-1"></i> Asignaciones
            </a>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ================================================
             Tab 1: Roles
             ================================================ -->
        <div class="tab-pane fade show active" id="tab-roles">
            <div class="row">

                <!-- Left: role list -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Roles definidos</h6>
                            <button id="btn-new-role" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-lg me-1"></i>Nuevo Rol
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div id="roles-list" class="list-group list-group-flush">
                                <div class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                    <p class="small text-muted mt-2 mb-0">Cargando roles...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info card -->
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-body">
                            <h6 class="mb-2"><i class="bi bi-info-circle me-1 text-primary"></i>Cómo funciona</h6>
                            <small class="text-muted">
                                <p class="mb-1"><strong>superadmin / admin:</strong> Acceso total, ignora roles RBAC.</p>
                                <p class="mb-1"><strong>editor con rol RBAC:</strong> Permisos según la matriz de este panel.</p>
                                <p class="mb-0"><strong>editor sin rol RBAC:</strong> Funciona con el sistema anterior de permisos por página.</p>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Right: role editor -->
                <div class="col-md-8">
                    <div id="role-editor" style="display:none;">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="mb-0" id="role-editor-title">Nuevo Rol</h6>
                            </div>
                            <div class="card-body">
                                <form id="role-form">
                                    <input type="hidden" id="input-role-id" name="id_role">

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Nombre del rol <span class="text-danger">*</span></label>
                                            <input type="text" id="input-role-name" name="name_role"
                                                class="form-control" placeholder="Ej: Ventas, Soporte..." required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Descripción</label>
                                            <input type="text" id="input-role-desc" name="description_role"
                                                class="form-control" placeholder="Opcional">
                                        </div>
                                    </div>

                                    <!-- Permission matrix -->
                                    <label class="form-label fw-semibold">Permisos por página</label>
                                    <div class="table-responsive">
                                        <table class="table table-bordered permission-matrix mb-3">
                                            <thead>
                                                <tr>
                                                    <th style="min-width:200px">Página</th>
                                                    <th>Leer</th>
                                                    <th>Crear</th>
                                                    <th>Editar</th>
                                                    <th>Eliminar</th>
                                                </tr>
                                            </thead>
                                            <tbody id="matrix-body">
                                                <tr><td colspan="5" class="text-center text-muted py-3">
                                                    <div class="spinner-border spinner-border-sm" role="status"></div>
                                                    Cargando páginas...
                                                </td></tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" id="btn-cancel-role" class="btn btn-outline-secondary">
                                            Cancelar
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-floppy me-1"></i>Guardar Rol
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Placeholder when no role selected -->
                    <div id="role-placeholder" class="rbac-empty" style="display:none;">
                        <i class="bi bi-shield-slash"></i>
                        Selecciona un rol para editarlo o crea uno nuevo
                    </div>
                </div>

            </div>
        </div>

        <!-- ================================================
             Tab 2: Assignments
             ================================================ -->
        <div class="tab-pane fade" id="tab-assignments">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="bi bi-people me-2"></i>Asignar roles a administradores</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Email</th>
                                    <th>Tipo</th>
                                    <th>Rol RBAC actual</th>
                                    <th>Asignar rol</th>
                                </tr>
                            </thead>
                            <tbody id="admins-tbody">
                                <tr>
                                    <td colspan="4" class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        Cargando administradores...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                <i class="bi bi-lightbulb me-2"></i>
                <strong>Nota:</strong> Los roles RBAC solo aplican a administradores de tipo <code>editor</code>.
                Los tipos <code>superadmin</code> y <code>admin</code> tienen acceso completo sin restricciones.
            </div>
        </div>

    </div>

</div>

<!-- Show placeholder when no role selected on roles tab -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var placeholder = document.getElementById('role-placeholder');
    var editor      = document.getElementById('role-editor');

    // Show placeholder initially
    if (placeholder && editor && editor.style.display === 'none') {
        placeholder.style.display = 'block';
    }

    // Hide placeholder when editor is shown
    var observer = new MutationObserver(function () {
        if (placeholder) {
            placeholder.style.display = editor.style.display === 'none' ? 'block' : 'none';
        }
    });
    observer.observe(editor, { attributes: true, attributeFilter: ['style'] });
});
</script>

<script src="<?php echo $projectBasePath ?>/plugins/rbac-manager/assets/js/rbac-manager.js"></script>
