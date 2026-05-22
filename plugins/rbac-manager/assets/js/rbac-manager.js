/* RBAC Manager - JavaScript */

(function () {
    'use strict';

    const cmsBasePath    = window.CMS_BASE_PATH || '';
    const projectBasePath = cmsBasePath.replace(/\/cms$/, '');
    const AJAX_URL = projectBasePath + '/plugins/rbac-manager/ajax.php';

    let allPages     = [];
    let selectedRole = null;

    // =========================================================
    // Bootstrap
    // =========================================================

    document.addEventListener('DOMContentLoaded', function () {
        loadRoles();
        loadAdmins();

        document.getElementById('btn-new-role').addEventListener('click', openNewRoleForm);
        document.getElementById('role-form').addEventListener('submit', saveRole);
        document.getElementById('btn-cancel-role').addEventListener('click', closeRoleForm);
    });

    // =========================================================
    // Roles
    // =========================================================

    function loadRoles() {
        post({ ajax_action: 'get_roles' }, function (res) {
            const list = document.getElementById('roles-list');
            if (!res.success || !res.roles.length) {
                list.innerHTML = '<div class="rbac-empty"><i class="bi bi-shield-x"></i>No hay roles creados</div>';
                return;
            }

            list.innerHTML = res.roles.map(function (r) {
                return `<div class="rbac-role-item list-group-item list-group-item-action px-3 py-2"
                             data-id="${r.id_role}" onclick="RBAC.selectRole(${r.id_role})">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${escHtml(r.name_role)}</strong>
                            ${r.description_role ? `<br><small class="text-muted">${escHtml(r.description_role)}</small>` : ''}
                        </div>
                        <button class="btn btn-sm text-danger" onclick="event.stopPropagation();RBAC.deleteRole(${r.id_role},'${escHtml(r.name_role)}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>`;
            }).join('');
        });
    }

    window.RBAC = window.RBAC || {};

    window.RBAC.selectRole = function (roleId) {
        selectedRole = roleId;

        // Highlight active
        document.querySelectorAll('.rbac-role-item').forEach(function (el) {
            el.classList.toggle('active', parseInt(el.dataset.id) === roleId);
        });

        // Load pages first, then role data
        ensurePages(function () {
            post({ ajax_action: 'get_role', id_role: roleId }, function (res) {
                if (!res.success) { alert(res.error); return; }
                openRoleForm(res.role);
            });
        });
    };

    window.RBAC.deleteRole = function (roleId, roleName) {
        Swal.fire({
            title: '¿Eliminar rol?',
            text: `Se eliminará el rol "${roleName}". Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            post({ ajax_action: 'delete_role', id_role: roleId }, function (res) {
                if (!res.success) { fncToastr('error', res.error); return; }
                fncToastr('success', 'Rol eliminado');
                if (selectedRole === roleId) closeRoleForm();
                loadRoles();
                loadAdmins();
            });
        });
    };

    // =========================================================
    // Role form
    // =========================================================

    function openNewRoleForm() {
        selectedRole = null;
        document.getElementById('role-form').reset();
        document.getElementById('input-role-id').value = '';
        document.querySelectorAll('.rbac-role-item').forEach(function (el) {
            el.classList.remove('active');
        });

        ensurePages(function () {
            renderMatrix({});
            document.getElementById('role-editor').style.display = 'block';
            document.getElementById('role-editor-title').textContent = 'Nuevo Rol';
        });
    }

    function openRoleForm(role) {
        document.getElementById('input-role-id').value  = role.id_role;
        document.getElementById('input-role-name').value = role.name_role;
        document.getElementById('input-role-desc').value = role.description_role || '';
        renderMatrix(role.permissions || {});
        document.getElementById('role-editor').style.display = 'block';
        document.getElementById('role-editor-title').textContent = 'Editar Rol';
    }

    function closeRoleForm() {
        document.getElementById('role-editor').style.display = 'none';
        document.querySelectorAll('.rbac-role-item').forEach(function (el) {
            el.classList.remove('active');
        });
        selectedRole = null;
    }

    function saveRole(e) {
        e.preventDefault();

        const permissions = collectPermissions();

        const data = {
            ajax_action:       'save_role',
            id_role:           document.getElementById('input-role-id').value,
            name_role:         document.getElementById('input-role-name').value.trim(),
            description_role:  document.getElementById('input-role-desc').value.trim(),
            permissions_role:  JSON.stringify(permissions),
        };

        fncMatPreloader('on');
        post(data, function (res) {
            fncMatPreloader('off');
            if (!res.success) { fncToastr('error', res.error); return; }
            fncToastr('success', 'Rol guardado correctamente');
            loadRoles();
            loadAdmins();
            closeRoleForm();
        });
    }

    // =========================================================
    // Permission matrix
    // =========================================================

    const ACTIONS = ['read', 'create', 'update', 'delete'];
    const ACTION_LABELS = { read: 'Leer', create: 'Crear', update: 'Editar', delete: 'Eliminar' };

    function renderMatrix(permissions) {
        const tbody = document.getElementById('matrix-body');

        if (!allPages.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">No hay páginas disponibles</td></tr>';
            return;
        }

        // Toggle-all header row
        let html = `<tr class="toggle-all-row">
            <td>Seleccionar todo</td>`;
        ACTIONS.forEach(function (action) {
            html += `<td class="action-col">
                <input type="checkbox" class="form-check-input toggle-all-chk" data-action="${action}"
                    onchange="RBAC.toggleAll('${action}', this.checked)">
            </td>`;
        });
        html += '</tr>';

        allPages.forEach(function (page) {
            const pPerms = permissions[page.url_page] || {};
            html += `<tr>
                <td>
                    <i class="${escHtml(page.icon_page)} page-icon text-muted"></i>
                    ${escHtml(page.title_page)}
                    <small class="text-muted ms-1">(${escHtml(page.url_page)})</small>
                </td>`;
            ACTIONS.forEach(function (action) {
                const checked = pPerms[action] ? 'checked' : '';
                html += `<td class="action-col">
                    <input type="checkbox" class="form-check-input perm-chk"
                        data-page="${escHtml(page.url_page)}" data-action="${action}" ${checked}>
                </td>`;
            });
            html += '</tr>';
        });

        tbody.innerHTML = html;
        syncToggleAllState();
    }

    function collectPermissions() {
        const permissions = {};
        document.querySelectorAll('.perm-chk').forEach(function (chk) {
            const page   = chk.dataset.page;
            const action = chk.dataset.action;
            if (!permissions[page]) permissions[page] = {};
            permissions[page][action] = chk.checked ? 1 : 0;
        });
        return permissions;
    }

    window.RBAC.toggleAll = function (action, checked) {
        document.querySelectorAll(`.perm-chk[data-action="${action}"]`).forEach(function (chk) {
            chk.checked = checked;
        });
    };

    function syncToggleAllState() {
        ACTIONS.forEach(function (action) {
            const all  = document.querySelectorAll(`.perm-chk[data-action="${action}"]`);
            const chkd = document.querySelectorAll(`.perm-chk[data-action="${action}"]:checked`);
            const hdr  = document.querySelector(`.toggle-all-chk[data-action="${action}"]`);
            if (!hdr) return;
            hdr.checked       = all.length > 0 && chkd.length === all.length;
            hdr.indeterminate = chkd.length > 0 && chkd.length < all.length;
        });
    }

    // =========================================================
    // Admin assignments
    // =========================================================

    function loadAdmins() {
        post({ ajax_action: 'get_admins' }, function (res) {
            const tbody = document.getElementById('admins-tbody');
            if (!res.success) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">${escHtml(res.error)}</td></tr>`;
                return;
            }

            post({ ajax_action: 'get_roles' }, function (rolesRes) {
                const roles = (rolesRes.success && rolesRes.roles) ? rolesRes.roles : [];

                const roleOptions = roles.map(function (r) {
                    return `<option value="${r.id_role}">${escHtml(r.name_role)}</option>`;
                }).join('');

                if (!res.admins.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No hay administradores</td></tr>';
                    return;
                }

                tbody.innerHTML = res.admins.map(function (a) {
                    const roleBadge = a.name_role
                        ? `<span class="role-badge">${escHtml(a.name_role)}</span>`
                        : `<span class="role-badge no-role">Sin rol RBAC</span>`;

                    const selectedId = a.id_role_admin || '';

                    return `<tr class="admin-row">
                        <td>${escHtml(a.email_admin)}</td>
                        <td><span class="badge bg-secondary">${escHtml(a.rol_admin)}</span></td>
                        <td>${roleBadge}</td>
                        <td>
                            <div class="d-flex gap-2 align-items-center">
                                <select class="form-select form-select-sm" id="role-select-${a.id_admin}" style="max-width:200px">
                                    <option value="">— Sin rol RBAC —</option>
                                    ${roleOptions}
                                </select>
                                <button class="btn btn-sm btn-primary" onclick="RBAC.assignRole(${a.id_admin})">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
                }).join('');

                // Set current values after render
                res.admins.forEach(function (a) {
                    const sel = document.getElementById(`role-select-${a.id_admin}`);
                    if (sel && a.id_role_admin) sel.value = a.id_role_admin;
                });
            });
        });
    }

    window.RBAC.assignRole = function (adminId) {
        const sel    = document.getElementById('role-select-' + adminId);
        const roleId = sel ? sel.value : '';

        post({ ajax_action: 'assign_role', admin_id: adminId, role_id: roleId }, function (res) {
            if (!res.success) { fncToastr('error', res.error); return; }
            fncToastr('success', 'Rol asignado correctamente');
            loadAdmins();
        });
    };

    // =========================================================
    // Helpers
    // =========================================================

    function ensurePages(cb) {
        if (allPages.length) { cb(); return; }
        post({ ajax_action: 'get_pages' }, function (res) {
            allPages = (res.success && res.pages) ? res.pages : [];
            cb();
        });
    }

    function post(data, cb) {
        const fd = new FormData();
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(cb)
            .catch(function (err) {
                console.error('RBAC AJAX error:', err);
                fncToastr('error', 'Error de comunicación');
            });
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})();
