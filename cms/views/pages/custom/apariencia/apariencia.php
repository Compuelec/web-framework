<?php
if (!isset($_SESSION['admin'])) {
    header('Location: ' . ($cmsBasePath ?? '') . '/login');
    exit;
}

$isSuperadmin = ($_SESSION['admin']->rol_admin ?? '') === 'superadmin';
?>

<div class="container-fluid py-4 px-4" id="theme-editor">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-semibold">
                <i class="bi bi-palette me-2 text-primary"></i>Apariencia
            </h4>
            <small class="text-muted">Personaliza los colores del panel de administración</small>
        </div>
        <?php if ($isSuperadmin): ?>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="te-reset-btn">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar por defecto
            </button>
            <button class="btn btn-sm btn-primary" id="te-save-btn">
                <i class="bi bi-check-lg me-1"></i>Guardar cambios
            </button>
        </div>
        <?php endif ?>
    </div>

    <?php if (!$isSuperadmin): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>Solo el superadmin puede modificar la apariencia del CMS.
    </div>
    <?php endif ?>

    <!-- Brand / identity -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-stars me-1"></i>Marca / Identidad</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="te-brand-title">Nombre del dashboard</label>
                    <input type="text" class="form-control" id="te-brand-title" maxlength="120" placeholder="Nombre que se muestra en el menú">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold d-block">Logo</label>
                    <div class="d-flex align-items-center gap-3">
                        <div id="te-brand-logo-preview" class="border rounded d-flex align-items-center justify-content-center" style="width:64px;height:64px;background:#f8f9fa;overflow:hidden;">
                            <i class="bi bi-image text-muted"></i>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="hidden" id="te-brand-logo" value="">
                            <label class="btn btn-sm btn-outline-primary mb-0">
                                <i class="bi bi-upload me-1"></i>Subir logo
                                <input type="file" accept="image/*" id="te-brand-logo-file" class="d-none">
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="te-brand-logo-clear">Quitar</button>
                            <span class="spinner-border spinner-border-sm text-primary" id="te-brand-logo-spin" style="display:none;"></span>
                        </div>
                    </div>
                    <div class="form-text">Si hay logo, se muestra arriba del nombre y el ícono en el menú.</div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="te-brand-symbol">Símbolo / ícono</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi-grid" id="te-brand-symbol-preview"></i></span>
                        <input type="text" class="form-control" id="te-brand-symbol" placeholder="bi-house, bi-building, bi-gear…">
                    </div>
                    <div class="form-text">Clase de <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icons</a> (ej. <code>bi-building</code>). El color usa el <strong>Color primario</strong> de abajo.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Color pickers -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-semibold mb-3">Colores del tema</h6>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Color primario</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="form-control form-control-color te-color-input" style="width:48px;height:38px" id="te-primary" data-key="theme_primary">
                            <input type="text" class="form-control form-control-sm te-hex-input" id="te-primary-hex" placeholder="#6c5ffc" maxlength="7">
                            <small class="text-muted text-nowrap">Botones, badges, acentos</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fondo del sidebar</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="form-control form-control-color te-color-input" style="width:48px;height:38px" id="te-sidebar-bg" data-key="theme_sidebar_bg">
                            <input type="text" class="form-control form-control-sm te-hex-input" id="te-sidebar-bg-hex" placeholder="#ffffff" maxlength="7">
                            <small class="text-muted text-nowrap">Fondo del menú lateral</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fondo de ítem activo</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="form-control form-control-color te-color-input" style="width:48px;height:38px" id="te-active-bg" data-key="theme_active_bg">
                            <input type="text" class="form-control form-control-sm te-hex-input" id="te-active-bg-hex" placeholder="#eff6ff" maxlength="7">
                            <small class="text-muted text-nowrap">Resaltado del enlace activo</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Color de ítem activo</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="form-control form-control-color te-color-input" style="width:48px;height:38px" id="te-active-color" data-key="theme_active_color">
                            <input type="text" class="form-control form-control-sm te-hex-input" id="te-active-color-hex" placeholder="#1e40af" maxlength="7">
                            <small class="text-muted text-nowrap">Texto del enlace activo</small>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Borde / ícono activo</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="form-control form-control-color te-color-input" style="width:48px;height:38px" id="te-active-border" data-key="theme_active_border">
                            <input type="text" class="form-control form-control-sm te-hex-input" id="te-active-border-hex" placeholder="#3b82f6" maxlength="7">
                            <small class="text-muted text-nowrap">Borde izquierdo + ícono activo</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preset palettes -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">
                    <h6 class="fw-semibold mb-3">Paletas predefinidas</h6>
                    <div class="d-flex flex-wrap gap-2" id="te-presets">
                        <?php
                        $presets = [
                            ['name' => 'Violeta (por defecto)', 'primary' => '#6c5ffc', 'sidebar_bg' => '#ffffff', 'active_bg' => '#eff6ff', 'active_color' => '#1e40af', 'active_border' => '#3b82f6'],
                            ['name' => 'Azul océano',           'primary' => '#0d6efd', 'sidebar_bg' => '#ffffff', 'active_bg' => '#e7f1ff', 'active_color' => '#0a3d91', 'active_border' => '#0d6efd'],
                            ['name' => 'Verde esmeralda',       'primary' => '#09ad95', 'sidebar_bg' => '#ffffff', 'active_bg' => '#e6faf7', 'active_color' => '#065f52', 'active_border' => '#09ad95'],
                            ['name' => 'Rojo coral',            'primary' => '#e8264f', 'sidebar_bg' => '#ffffff', 'active_bg' => '#fff0f3', 'active_color' => '#8b0a24', 'active_border' => '#e8264f'],
                            ['name' => 'Naranja vivo',          'primary' => '#fc7303', 'sidebar_bg' => '#ffffff', 'active_bg' => '#fff4e6', 'active_color' => '#7c3800', 'active_border' => '#fc7303'],
                            ['name' => 'Sidebar oscuro',        'primary' => '#6c5ffc', 'sidebar_bg' => '#1e2139', 'active_bg' => '#2c3050', 'active_color' => '#a5b4fc', 'active_border' => '#6c5ffc'],
                        ];
                        foreach ($presets as $p):
                        ?>
                        <button class="btn btn-sm btn-outline-secondary te-preset-btn"
                            data-primary="<?php echo htmlspecialchars($p['primary']) ?>"
                            data-sidebar_bg="<?php echo htmlspecialchars($p['sidebar_bg']) ?>"
                            data-active_bg="<?php echo htmlspecialchars($p['active_bg']) ?>"
                            data-active_color="<?php echo htmlspecialchars($p['active_color']) ?>"
                            data-active_border="<?php echo htmlspecialchars($p['active_border']) ?>"
                            title="<?php echo htmlspecialchars($p['name']) ?>">
                            <span class="d-inline-block rounded-circle me-1"
                                  style="width:12px;height:12px;background:<?php echo htmlspecialchars($p['primary']) ?>;vertical-align:middle"></span>
                            <?php echo htmlspecialchars($p['name']) ?>
                        </button>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live preview -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pb-0 pt-3 px-4">
                    <h6 class="fw-semibold mb-0">Vista previa</h6>
                </div>
                <div class="card-body p-4">
                    <div id="te-preview" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;display:flex;height:280px">
                        <!-- Simulated sidebar -->
                        <div id="tp-sidebar" style="width:180px;flex-shrink:0;padding:12px 0;border-right:1px solid #e5e7eb">
                            <div style="padding:10px 14px;font-weight:600;font-size:12px;color:#888;border-bottom:1px solid #eee;margin-bottom:6px">MI EMPRESA</div>
                            <div class="tp-nav-item" style="padding:8px 14px;font-size:12px;color:#374151;cursor:pointer">
                                <i class="bi bi-house me-2"></i>Dashboard
                            </div>
                            <div class="tp-nav-item tp-active" style="padding:8px 14px;font-size:12px;font-weight:600;border-left:3px solid;padding-left:11px;cursor:pointer">
                                <i class="bi bi-palette me-2"></i>Apariencia
                            </div>
                            <div class="tp-nav-item" style="padding:8px 14px;font-size:12px;color:#374151;cursor:pointer">
                                <i class="bi bi-people me-2"></i>Usuarios
                            </div>
                            <div class="tp-nav-item" style="padding:8px 14px;font-size:12px;color:#374151;cursor:pointer">
                                <i class="bi bi-gear me-2"></i>Configuración
                            </div>
                        </div>
                        <!-- Simulated content -->
                        <div style="flex:1;padding:16px;background:#f9fafb">
                            <div style="display:flex;gap:8px;margin-bottom:12px">
                                <div class="tp-badge" style="padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;color:#fff">Activo</div>
                                <div style="padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#e9ecef;color:#555">Inactivo</div>
                            </div>
                            <div style="display:flex;gap:8px;margin-bottom:12px">
                                <div class="tp-btn" style="padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;color:#fff;cursor:pointer">Guardar</div>
                                <div style="padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;background:#e9ecef;color:#555;cursor:pointer">Cancelar</div>
                            </div>
                            <div style="background:#fff;border-radius:8px;padding:12px;border:1px solid #e5e7eb">
                                <div class="tp-heading" style="font-size:13px;font-weight:700;margin-bottom:6px">Estadísticas</div>
                                <div style="font-size:11px;color:#888">Ejemplo de contenido del panel</div>
                                <div style="margin-top:8px;display:flex;gap:6px">
                                    <div class="tp-metric" style="flex:1;padding:8px;border-radius:6px;text-align:center">
                                        <div style="font-size:16px;font-weight:700">248</div>
                                        <div style="font-size:10px;opacity:0.7">Usuarios</div>
                                    </div>
                                    <div class="tp-metric" style="flex:1;padding:8px;border-radius:6px;text-align:center">
                                        <div style="font-size:16px;font-weight:700">1.2k</div>
                                        <div style="font-size:10px;opacity:0.7">Pedidos</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    'use strict';

    var AJAX_URL = (window.CMS_AJAX_PATH || '') + '/theme-settings.ajax.php';

    // Map field IDs to data keys and preview targets
    var fields = [
        { key: 'theme_primary',       inputId: 'te-primary',       hexId: 'te-primary-hex' },
        { key: 'theme_sidebar_bg',    inputId: 'te-sidebar-bg',    hexId: 'te-sidebar-bg-hex' },
        { key: 'theme_active_bg',     inputId: 'te-active-bg',     hexId: 'te-active-bg-hex' },
        { key: 'theme_active_color',  inputId: 'te-active-color',  hexId: 'te-active-color-hex' },
        { key: 'theme_active_border', inputId: 'te-active-border', hexId: 'te-active-border-hex' },
    ];

    // ── Load current theme ────────────────────────────────

    function loadTheme() {
        fetch(AJAX_URL, {
            method: 'POST',
            body: new URLSearchParams({ action: 'get' })
        })
        .then(r => r.json())
        .then(function (res) {
            if (!res.success) return;
            var t = res.theme || {};
            fields.forEach(function (f) {
                var val = t[f.key] || getDefaultVal(f.key);
                setField(f, val);
            });
            var brandTitleEl = document.getElementById('te-brand-title');
            if (brandTitleEl) brandTitleEl.value = t.theme_brand_title || '';
            var brandSymbolEl = document.getElementById('te-brand-symbol');
            if (brandSymbolEl) brandSymbolEl.value = t.theme_brand_symbol || '';
            setSymbolPreview(t.theme_brand_symbol || '');
            setBrandLogo(t.theme_brand_logo || '');
            applyPreview();
        });
    }

    function setSymbolPreview(cls) {
        var p = document.getElementById('te-brand-symbol-preview');
        if (p) p.className = (cls && cls.trim()) ? cls.trim() : 'bi-grid';
    }

    function setBrandLogo(url) {
        document.getElementById('te-brand-logo').value = url || '';
        var preview = document.getElementById('te-brand-logo-preview');
        if (url) {
            preview.innerHTML = '<img alt="logo" style="width:100%;height:100%;object-fit:contain;">';
            preview.firstChild.src = url;
        } else {
            preview.innerHTML = '<i class="bi bi-image text-muted"></i>';
        }
    }

    function getDefaultVal(key) {
        var defaults = {
            theme_primary:       '#6c5ffc',
            theme_sidebar_bg:    '#ffffff',
            theme_active_bg:     '#eff6ff',
            theme_active_color:  '#1e40af',
            theme_active_border: '#3b82f6',
        };
        return defaults[key] || '#000000';
    }

    function setField(f, val) {
        var colorEl = document.getElementById(f.inputId);
        var hexEl   = document.getElementById(f.hexId);
        if (colorEl) colorEl.value = val;
        if (hexEl)   hexEl.value   = val;
    }

    // ── Apply to preview ──────────────────────────────────

    function applyPreview() {
        var primary      = getVal('te-primary');
        var sidebarBg    = getVal('te-sidebar-bg');
        var activeBg     = getVal('te-active-bg');
        var activeColor  = getVal('te-active-color');
        var activeBorder = getVal('te-active-border');

        var sidebar = document.getElementById('tp-sidebar');
        if (sidebar) sidebar.style.background = sidebarBg;

        document.querySelectorAll('.tp-active').forEach(function (el) {
            el.style.background    = activeBg;
            el.style.color         = activeColor;
            el.style.borderColor   = activeBorder;
        });
        document.querySelectorAll('.tp-active i').forEach(function (el) {
            el.style.color = activeBorder;
        });
        document.querySelectorAll('.tp-badge, .tp-btn').forEach(function (el) {
            el.style.background = primary;
        });
        document.querySelectorAll('.tp-metric').forEach(function (el) {
            el.style.background = hexToRgba(primary, 0.1);
            el.style.color      = primary;
        });
        document.querySelectorAll('.tp-heading').forEach(function (el) {
            el.style.color = primary;
        });
    }

    function getVal(id) {
        var el = document.getElementById(id);
        return el ? el.value : '#000000';
    }

    // ── Bind events ───────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        loadTheme();

        fields.forEach(function (f) {
            // Color picker → hex input + preview
            var colorEl = document.getElementById(f.inputId);
            var hexEl   = document.getElementById(f.hexId);

            if (colorEl) colorEl.addEventListener('input', function () {
                if (hexEl) hexEl.value = colorEl.value;
                applyPreview();
            });

            // Hex input → color picker + preview
            if (hexEl) hexEl.addEventListener('input', function () {
                if (/^#[0-9a-fA-F]{6}$/.test(hexEl.value)) {
                    if (colorEl) colorEl.value = hexEl.value;
                    applyPreview();
                }
            });
        });

        // Preset buttons
        document.querySelectorAll('.te-preset-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var map = {
                    theme_primary:       'primary',
                    theme_sidebar_bg:    'sidebar_bg',
                    theme_active_bg:     'active_bg',
                    theme_active_color:  'active_color',
                    theme_active_border: 'active_border',
                };
                fields.forEach(function (f) {
                    var dataAttr = 'data-' + map[f.key];
                    var val = btn.getAttribute(dataAttr);
                    if (val) setField(f, val);
                });
                applyPreview();
            });
        });

        // Brand logo upload / clear
        var logoInput = document.getElementById('te-brand-logo-file');
        if (logoInput) logoInput.addEventListener('change', function () {
            if (!this.files || !this.files.length) return;
            var spin = document.getElementById('te-brand-logo-spin');
            spin.style.display = '';
            var data = new FormData();
            data.append('file', this.files[0]);
            data.append('folder', '1');
            data.append('token', window.CMS_TOKEN || '');
            fetch((window.CMS_AJAX_PATH || '') + '/files.ajax.php', { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    spin.style.display = 'none';
                    if (res && res.status === 200 && res.link) { setBrandLogo(res.link); }
                    else { fncToastr('error', (res && res.error) || 'No se pudo subir el logo'); }
                })
                .catch(function () { spin.style.display = 'none'; fncToastr('error', 'Error al subir el logo'); });
            this.value = '';
        });
        var logoClear = document.getElementById('te-brand-logo-clear');
        if (logoClear) logoClear.addEventListener('click', function () { setBrandLogo(''); });

        // Symbol preview as you type
        var symInput = document.getElementById('te-brand-symbol');
        if (symInput) symInput.addEventListener('input', function () { setSymbolPreview(this.value); });

        // Save
        var saveBtn = document.getElementById('te-save-btn');
        if (saveBtn) saveBtn.addEventListener('click', function () {
            var params = new URLSearchParams({ action: 'save' });
            fields.forEach(function (f) {
                params.append(f.key, getVal(f.inputId));
            });
            params.append('theme_brand_title', getVal('te-brand-title'));
            params.append('theme_brand_symbol', getVal('te-brand-symbol'));
            params.append('theme_brand_logo', document.getElementById('te-brand-logo').value);

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

            fetch(AJAX_URL, { method: 'POST', body: params })
            .then(r => r.json())
            .then(function (res) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar cambios';
                if (res.success) {
                    // Apply immediately to the real page CSS vars
                    applyToPage();
                    if (window.toastr) toastr.success('Tema guardado. Recarga la página para ver todos los cambios.');
                } else {
                    if (window.toastr) toastr.error(res.error || 'Error al guardar');
                }
            });
        });

        // Reset
        var resetBtn = document.getElementById('te-reset-btn');
        if (resetBtn) resetBtn.addEventListener('click', function () {
            if (!confirm('¿Restaurar el tema por defecto?')) return;
            fetch(AJAX_URL, {
                method: 'POST',
                body: new URLSearchParams({ action: 'reset' })
            })
            .then(r => r.json())
            .then(function (res) {
                if (res.success) {
                    loadTheme();
                    applyToPage();
                    if (window.toastr) toastr.success('Tema restaurado');
                }
            });
        });
    });

    // Apply colors to live page (CSS vars) without reload
    function applyToPage() {
        var root  = document.documentElement;
        var primary      = getVal('te-primary');
        var sidebarBg    = getVal('te-sidebar-bg');
        var activeBg     = getVal('te-active-bg');
        var activeColor  = getVal('te-active-color');
        var activeBorder = getVal('te-active-border');

        root.style.setProperty('--tp-primary',       primary);
        root.style.setProperty('--tp-sidebar-bg',    sidebarBg);
        root.style.setProperty('--tp-active-bg',     activeBg);
        root.style.setProperty('--tp-active-color',  activeColor);
        root.style.setProperty('--tp-active-border', activeBorder);

        // Also update generated style tag
        var styleEl = document.getElementById('cms-theme-vars');
        if (styleEl) {
            styleEl.textContent = buildCssVars(primary, sidebarBg, activeBg, activeColor, activeBorder);
        }
    }

    function buildCssVars(primary, sidebarBg, activeBg, activeColor, activeBorder) {
        return [
            ':root{',
            '--tp-primary:'       + primary       + ';',
            '--tp-sidebar-bg:'    + sidebarBg     + ';',
            '--tp-active-bg:'     + activeBg      + ';',
            '--tp-active-color:'  + activeColor   + ';',
            '--tp-active-border:' + activeBorder  + ';',
            '}',
            '.bg-primary{background:' + primary + ' !important;}',
            '.text-primary{color:' + primary + ' !important;}',
            '.btn-primary{background:' + primary + ' !important;border-color:' + primary + ' !important;}',
            '#sidebar-wrapper{background:' + sidebarBg + ' !important;}',
            '#sidebar-wrapper a.bg-transparent.active{background:' + activeBg + ' !important;color:' + activeColor + ' !important;border-left-color:' + activeBorder + ' !important;}',
            '#sidebar-wrapper a.bg-transparent.active i{color:' + activeBorder + ' !important;}',
        ].join('');
    }

    function hexToRgba(hex, alpha) {
        var r = parseInt(hex.slice(1,3),16);
        var g = parseInt(hex.slice(3,5),16);
        var b = parseInt(hex.slice(5,7),16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

})();
</script>
