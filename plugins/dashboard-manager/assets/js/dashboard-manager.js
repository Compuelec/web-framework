/* =========================================================
   Dashboard Manager — Main JS
   ========================================================= */

(function () {
    'use strict';

    // ── Config ────────────────────────────────────────────
    const cmsBase     = (window.DM_CMS_BASE  || window.CMS_BASE_PATH || '').replace(/\/$/, '');
    const projectBase = cmsBase.replace(/\/cms$/, '');
    const AJAX_URL    = projectBase + '/plugins/dashboard-manager/ajax.php';
    const adminId     = window.DM_ADMIN_ID || 0;

    // Chart instances (to destroy before re-render)
    const chartInstances = {};
    // Auto-refresh timers
    const refreshTimers = {};
    // Cached table list
    let _tables = null;
    // Widget pending deletion
    let _pendingDeleteId = null;
    // Edit mode
    let editMode = false;
    // Current editing widget id (null = new)
    let _editingId = null;
    // Selected type in step-1 modal
    let _selectedType = null;

    // ── Bootstrap modal instances ─────────────────────────
    let _addModal, _deleteModal;

    // ── Init ─────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        _addModal    = new bootstrap.Modal(document.getElementById('dm-modal'));
        _deleteModal = new bootstrap.Modal(document.getElementById('dm-delete-modal'));

        loadWidgets();
        bindGlobalEvents();
    });

    // ── Load all widgets ──────────────────────────────────
    function loadWidgets() {
        setLoading(true);
        post({ ajax_action: 'get_widgets' }).then(function (res) {
            setLoading(false);
            if (!res.success) return;
            renderGrid(res.widgets || []);
        }).catch(function () { setLoading(false); });
    }

    // ── Render entire grid ────────────────────────────────
    function renderGrid(widgets) {
        const grid = document.getElementById('dm-grid');
        grid.innerHTML = '';

        // Clear old timers
        Object.values(refreshTimers).forEach(clearInterval);

        if (!widgets || widgets.length === 0) {
            document.getElementById('dm-empty').classList.remove('d-none');
            return;
        }
        document.getElementById('dm-empty').classList.add('d-none');

        widgets.forEach(function (w) {
            grid.appendChild(buildWidgetShell(w));
        });

        initSortable();

        // Load data for each widget
        widgets.forEach(function (w) {
            fetchWidgetData(w.id_widget);
            scheduleRefresh(w);
        });
    }

    // ── Build widget shell HTML ───────────────────────────
    function buildWidgetShell(w) {
        const col = document.createElement('div');
        col.className = 'dm-widget-item ' + (w.width_widget || 'col-md-4');
        col.dataset.id = w.id_widget;

        col.innerHTML = `
            <div class="dm-widget-card h-100">
                <div class="dm-widget-header">
                    <span class="dm-drag-handle"><i class="bi bi-grip-vertical"></i></span>
                    <span class="dm-widget-title">${esc(w.title_widget || typeLabel(w.type_widget))}</span>
                    <div class="dm-widget-actions">
                        <button class="dm-action-btn dm-btn-refresh" data-id="${w.id_widget}" title="Actualizar">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button class="dm-action-btn dm-btn-config" data-id="${w.id_widget}" title="Configurar">
                            <i class="bi bi-gear"></i>
                        </button>
                        <button class="dm-action-btn dm-btn-delete" data-id="${w.id_widget}" title="Eliminar">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="dm-widget-body" id="dm-body-${w.id_widget}">
                    <div class="dm-widget-loading">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                    </div>
                </div>
            </div>`;
        return col;
    }

    // ── Fetch data for one widget ─────────────────────────
    function fetchWidgetData(widgetId) {
        post({ ajax_action: 'get_widget_data', widget_id: widgetId })
            .then(function (res) {
                if (!res.success) return;
                renderWidgetContent(widgetId, res.type, res.data, res.config);
            });
    }

    // ── Render widget content by type ────────────────────
    function renderWidgetContent(id, type, data, config) {
        const body = document.getElementById('dm-body-' + id);
        if (!body) return;

        // Destroy previous chart if any
        if (chartInstances[id]) {
            chartInstances[id].destroy();
            delete chartInstances[id];
        }

        switch (type) {
            case 'metric':     body.innerHTML = renderMetric(data, config);    break;
            case 'kpi':        body.innerHTML = renderKpi(data, config);        break;
            case 'chart':      renderChart(id, body, data, config);            break;
            case 'recent':     body.innerHTML = renderRecent(data, config);    body.classList.add('dm-no-pad'); break;
            case 'activity':   body.innerHTML = renderActivity(data);          body.classList.add('dm-no-pad'); break;
            case 'quicklinks': body.innerHTML = renderQuicklinks(data, config); body.classList.add('dm-no-pad'); break;
            case 'html':       body.innerHTML = renderHtml(data);              break;
            case 'system':     body.innerHTML = renderSystem(data);            break;
            default:           body.innerHTML = '<p class="text-muted small p-2">Widget sin contenido</p>';
        }
    }

    // ── Widget renderers ──────────────────────────────────

    function renderMetric(data, config) {
        const value  = formatNumber(data.value ?? 0);
        const label  = config.label  || '';
        const icon   = config.icon   || 'bi-bar-chart-line';
        const color  = config.color  || 'primary';
        const suffix = config.suffix || '';
        return `
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="dm-metric-value text-${color}">${value}${suffix ? '<span class="fs-5 fw-normal text-muted ms-1">' + esc(suffix) + '</span>' : ''}</div>
                    ${label ? `<div class="dm-metric-label">${esc(label)}</div>` : ''}
                </div>
                <i class="bi ${esc(icon)} dm-metric-icon text-${color}"></i>
            </div>`;
    }

    function renderKpi(data, config) {
        const curr   = formatNumber(data.current  ?? 0);
        const prev   = formatNumber(data.previous ?? 0);
        const trend  = parseFloat(data.trend ?? 0);
        const label  = config.label  || '';
        const period = config.period_days || 30;
        const trendClass = trend > 0 ? 'up' : trend < 0 ? 'down' : 'flat';
        const trendIcon  = trend > 0 ? 'bi-arrow-up'  : trend < 0 ? 'bi-arrow-down' : 'bi-dash';
        const trendText  = trend > 0 ? '+' + trend + '%' : trend + '%';

        return `
            <div class="text-center py-2">
                <div class="dm-kpi-value">${curr}</div>
                <div class="dm-kpi-trend ${trendClass}">
                    <i class="bi ${trendIcon}"></i> ${trendText} vs período anterior
                </div>
                <div class="text-muted mt-1" style="font-size:11px">
                    ${label ? esc(label) + ' — ' : ''}Últimos ${period} días (anterior: ${prev})
                </div>
            </div>`;
    }

    function renderChart(widgetId, body, data, config) {
        const chartType = config.chart_type || 'line';
        const label     = config.label || 'Registros';
        const color     = config.color || '#5569ff';
        const labels    = data.labels || [];
        const values    = data.values || [];

        body.innerHTML = `<div class="dm-chart-container"><canvas id="dm-chart-${widgetId}"></canvas></div>`;

        // Wait for next tick (DOM insert)
        setTimeout(function () {
            const ctx = document.getElementById('dm-chart-' + widgetId);
            if (!ctx || typeof Chart === 'undefined') return;

            chartInstances[widgetId] = new Chart(ctx, {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: values,
                        backgroundColor: hexToRgba(color, 0.12),
                        borderColor: color,
                        borderWidth: 2,
                        pointRadius: labels.length > 30 ? 0 : 3,
                        tension: 0.3,
                        fill: chartType === 'line',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 6, font: { size: 10 } } },
                        y: { beginAtZero: true, ticks: { font: { size: 10 } } }
                    }
                }
            });
        }, 0);
    }

    function renderRecent(data) {
        const records = data.records || [];
        const columns = data.columns || [];
        if (records.length === 0) return '<p class="text-muted small p-3">Sin registros.</p>';

        const headers = columns.map(c => `<th>${esc(c)}</th>`).join('');
        const rows = records.map(function (rec) {
            const cells = columns.map(c => `<td>${esc(rec[c] != null ? String(rec[c]) : '')}</td>`).join('');
            return `<tr>${cells}</tr>`;
        }).join('');

        return `
            <div class="table-responsive">
                <table class="table dm-recent-table table-hover mb-0">
                    <thead class="table-light"><tr>${headers}</tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    }

    function renderActivity(data) {
        const logs = data.logs || [];
        if (logs.length === 0) return '<p class="text-muted small p-3">Sin actividad reciente.</p>';

        const items = logs.map(function (log) {
            const bc = 'dm-badge-' + (log.action_log || 'default');
            const who = log.name_admin ? esc(log.name_admin) : 'Sistema';
            const desc = log.description_log ? ' — ' + esc(log.description_log) : '';
            return `
                <li class="dm-activity-item">
                    <span class="dm-activity-badge ${bc}">${esc(log.action_log || '?')}</span>
                    <span>${who} · <strong>${esc(log.entity_log || '')}</strong> #${esc(log.entity_id_log || '')}${desc}</span>
                    <span class="dm-activity-meta">${esc(log.date_created_log || '')}</span>
                </li>`;
        }).join('');

        return `<ul class="dm-activity-list">${items}</ul>`;
    }

    function renderQuicklinks(data, config) {
        const links = data.links || [];
        if (links.length === 0) return '<p class="text-muted small p-3">Sin accesos configurados.</p>';

        const btns = links.map(function (lnk) {
            const href = lnk.url ? (cmsBase + '/' + lnk.url.replace(/^\//, '')) : '#';
            return `<a href="${esc(href)}" class="dm-quicklink-btn btn btn-${esc(lnk.color || 'primary')}">
                        <i class="bi ${esc(lnk.icon || 'bi-link')}"></i> ${esc(lnk.label || lnk.url)}
                    </a>`;
        }).join('');

        return `<div class="dm-quicklinks">${btns}</div>`;
    }

    function renderHtml(data) {
        return `<div class="dm-html-body">${data.content || ''}</div>`;
    }

    function renderSystem(data) {
        const rows = [
            { label: 'PHP',   value: data.php_version   || '—' },
            { label: 'MySQL', value: data.mysql_version  || '—' },
            { label: 'OS',    value: data.server_os      || '—' },
        ].map(r => `<div class="dm-system-row"><span class="dm-system-label">${r.label}</span><span class="dm-system-value">${esc(r.value)}</span></div>`).join('');

        let diskHtml = '';
        if (data.disk_percent !== null) {
            const pct = data.disk_percent;
            const barClass = pct > 85 ? 'bg-danger' : pct > 65 ? 'bg-warning' : 'bg-success';
            diskHtml = `
                <div class="dm-system-row flex-column align-items-start gap-1">
                    <div class="d-flex justify-content-between w-100">
                        <span class="dm-system-label">Disco usado</span>
                        <span class="dm-system-value">${pct}%</span>
                    </div>
                    <div class="progress w-100" style="height:6px">
                        <div class="progress-bar ${barClass}" style="width:${pct}%"></div>
                    </div>
                    <div style="font-size:11px;color:#aaa">${data.disk_free_gb} GB libres de ${data.disk_total_gb} GB</div>
                </div>`;
        }

        return `<div>${rows}${diskHtml}</div>`;
    }

    // ── Sortable init ─────────────────────────────────────
    function initSortable() {
        if (typeof $ === 'undefined' || typeof $.fn.sortable === 'undefined') return;

        $('#dm-grid').sortable({
            handle: '.dm-drag-handle',
            items:  '.dm-widget-item',
            tolerance: 'pointer',
            placeholder: 'dm-widget-item ui-sortable-placeholder col-md-4',
            update: function () { savePositions(); }
        });
    }

    function savePositions() {
        const items = document.querySelectorAll('#dm-grid .dm-widget-item');
        const positions = [];
        items.forEach(function (el, idx) {
            positions.push({ id: parseInt(el.dataset.id), position: idx });
        });
        post({ ajax_action: 'update_positions', positions: JSON.stringify(positions) });
    }

    // ── Auto-refresh ──────────────────────────────────────
    function scheduleRefresh(w) {
        const secs = parseInt(w.refresh_widget || 0);
        if (secs <= 0) return;
        refreshTimers[w.id_widget] = setInterval(function () {
            fetchWidgetData(w.id_widget);
        }, secs * 1000);
    }

    // ── Edit mode toggle ──────────────────────────────────
    function bindGlobalEvents() {
        document.getElementById('dm-toggle-edit').addEventListener('click', function () {
            editMode = !editMode;
            document.body.classList.toggle('dm-edit-mode', editMode);
            this.classList.toggle('active', editMode);
            this.querySelector('i').className = editMode ? 'bi bi-check-lg me-1' : 'bi bi-pencil-square me-1';
            this.querySelector('i').nextSibling.textContent = editMode ? 'Listo' : 'Editar';
        });

        // Widget action buttons (delegated)
        document.getElementById('dm-grid').addEventListener('click', function (e) {
            const btn = e.target.closest('[data-id]');
            if (!btn) return;
            const id = parseInt(btn.dataset.id);

            if (btn.classList.contains('dm-btn-delete')) {
                _pendingDeleteId = id;
                _deleteModal.show();
            } else if (btn.classList.contains('dm-btn-config')) {
                openEditModal(id);
            } else if (btn.classList.contains('dm-btn-refresh')) {
                fetchWidgetData(id);
            }
        });

        // Confirm delete
        document.getElementById('dm-confirm-delete').addEventListener('click', function () {
            if (!_pendingDeleteId) return;
            post({ ajax_action: 'delete_widget', widget_id: _pendingDeleteId }).then(function (res) {
                _deleteModal.hide();
                if (res.success) {
                    const el = document.querySelector('[data-id="' + _pendingDeleteId + '"]');
                    if (el) el.remove();
                    if (document.querySelectorAll('#dm-grid .dm-widget-item').length === 0) {
                        document.getElementById('dm-empty').classList.remove('d-none');
                    }
                }
                _pendingDeleteId = null;
            });
        });

        // Type cards (step 1)
        document.querySelectorAll('.dm-type-card').forEach(function (card) {
            card.addEventListener('click', function () {
                document.querySelectorAll('.dm-type-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                _selectedType = card.dataset.type;
                // Wait until the table list is loaded before rendering the
                // type-specific fields, otherwise the table <select> renders
                // empty (loadTables() is async and renderTypeFields reads
                // _tables synchronously).
                loadTables(function () { goToStep2(_selectedType); });
            });
        });

        // Back button in modal
        document.getElementById('dm-back-btn').addEventListener('click', function () {
            goToStep1();
        });

        // Save widget button
        document.getElementById('dm-save-btn').addEventListener('click', function () {
            saveWidget();
        });

        // Reset modal on close
        document.getElementById('dm-modal').addEventListener('hidden.bs.modal', function () {
            resetModal();
        });

        // Pre-load tables whenever the modal opens for the add flow. Using the
        // modal's show event covers BOTH the header "Agregar widget" button and
        // the empty-state "Agregar primer widget" button, which both open the
        // modal via data-bs-toggle. The edit flow (_editingId set) loads its own
        // tables through openEditModal().
        document.getElementById('dm-modal').addEventListener('show.bs.modal', function () {
            if (_editingId === null) {
                _tables = null; // Invalidate cache so newly created tables appear
                resetModal();
                loadTables();
            }
        });
    }

    // ── Modal: Add / Edit ─────────────────────────────────

    function openEditModal(widgetId) {
        _editingId = widgetId;
        document.getElementById('dm-modal-title').innerHTML = '<i class="bi bi-gear me-2 text-primary"></i>Editar widget';

        // Fetch current widget data to pre-fill form
        post({ ajax_action: 'get_widget_data', widget_id: widgetId }).then(function (res) {
            if (!res.success) return;
            _selectedType = res.type;
            loadTables(function () {
                goToStep2(res.type, res.config, {
                    title:   res.title   || '',
                    width:   res.width   || 'col-md-4',
                    refresh: res.refresh || 0,
                });
            });
            _addModal.show();
        });
    }

    // Restore hidden meta fields back into config when saving
    // We store title/width/refresh in DOM fields; config comes from type-specific fields

    function goToStep1() {
        document.getElementById('dm-step-type').classList.remove('d-none');
        document.getElementById('dm-step-config').classList.add('d-none');
        document.getElementById('dm-save-btn').classList.add('d-none');
        document.getElementById('dm-modal-title').innerHTML = '<i class="bi bi-plus-circle me-2 text-primary"></i>Nuevo widget';
        _selectedType = null;
    }

    function goToStep2(type, existingConfig, meta) {
        document.getElementById('dm-step-type').classList.add('d-none');
        document.getElementById('dm-step-config').classList.remove('d-none');
        document.getElementById('dm-save-btn').classList.remove('d-none');
        document.getElementById('dm-selected-type-label').textContent = typeLabel(type);

        // Pre-fill common fields
        if (meta) {
            document.getElementById('dm-f-title').value   = meta.title   || '';
            document.getElementById('dm-f-width').value   = meta.width   || 'col-md-4';
            document.getElementById('dm-f-refresh').value = meta.refresh || '0';
        }

        // Render type-specific fields
        renderTypeFields(type, existingConfig || {});
    }

    function resetModal() {
        goToStep1();
        document.getElementById('dm-f-title').value   = '';
        document.getElementById('dm-f-width').value   = 'col-md-4';
        document.getElementById('dm-f-refresh').value = '0';
        document.getElementById('dm-type-fields').innerHTML = '';
        document.querySelectorAll('.dm-type-card').forEach(c => c.classList.remove('selected'));
        _editingId = null;
        _selectedType = null;
    }

    // ── Type-specific config forms ────────────────────────

    function renderTypeFields(type, cfg) {
        const container = document.getElementById('dm-type-fields');
        container.innerHTML = '';

        const tables  = _tables || [];
        const tableOpts = tables.map(t => `<option value="${esc(t)}" ${cfg.table === t ? 'selected' : ''}>${esc(t)}</option>`).join('');
        const periodOpts = [7,14,30,90,365].map(d =>
            `<option value="${d}" ${parseInt(cfg.period||30) === d ? 'selected':''}>${d} días</option>`).join('');

        if (type === 'metric') {
            container.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Tabla</label>
                        <select class="form-select form-select-sm" id="dm-f-table" onchange="window._dmLoadCols(this.value,'dm-f-column')">
                            <option value="">— Elige tabla —</option>${tableOpts}
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Operación</label>
                        <select class="form-select form-select-sm" id="dm-f-operation">
                            <option value="count" ${cfg.operation==='count'||!cfg.operation?'selected':''}>Contar registros</option>
                            <option value="sum"   ${cfg.operation==='sum'?'selected':''}>Sumar columna</option>
                            <option value="avg"   ${cfg.operation==='avg'?'selected':''}>Promedio columna</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Columna (para suma/promedio)</label>
                        <select class="form-select form-select-sm" id="dm-f-column">
                            <option value="">— Opcional —</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Etiqueta</label>
                        <input type="text" class="form-control form-control-sm" id="dm-f-label" value="${esc(cfg.label||'')}" placeholder="Ej: clientes activos">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Sufijo (opcional)</label>
                        <input type="text" class="form-control form-control-sm" id="dm-f-suffix" value="${esc(cfg.suffix||'')}" placeholder="Ej: kg">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Icono Bootstrap</label>
                        <input type="text" class="form-control form-control-sm" id="dm-f-icon" value="${esc(cfg.icon||'bi-bar-chart-line')}" placeholder="bi-bar-chart-line">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Color</label>
                        <select class="form-select form-select-sm" id="dm-f-color">
                            ${colorOpts(cfg.color)}
                        </select>
                    </div>
                </div>`;
            if (cfg.table) window._dmLoadCols(cfg.table, 'dm-f-column', cfg.column);

        } else if (type === 'kpi') {
            container.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Tabla</label>
                        <select class="form-select form-select-sm" id="dm-f-table" onchange="window._dmLoadCols(this.value,'dm-f-date-col')">
                            <option value="">— Elige tabla —</option>${tableOpts}
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Columna de fecha</label>
                        <select class="form-select form-select-sm" id="dm-f-date-col">
                            <option value="">— Elige columna —</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Período (días)</label>
                        <input type="number" class="form-control form-control-sm" id="dm-f-period-days" value="${parseInt(cfg.period_days||30)}" min="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Etiqueta</label>
                        <input type="text" class="form-control form-control-sm" id="dm-f-label" value="${esc(cfg.label||'')}" placeholder="Ej: Pedidos">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Color</label>
                        <select class="form-select form-select-sm" id="dm-f-color">${colorOpts(cfg.color)}</select>
                    </div>
                </div>`;
            if (cfg.table) window._dmLoadCols(cfg.table, 'dm-f-date-col', cfg.date_column);

        } else if (type === 'chart') {
            container.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Tabla</label>
                        <select class="form-select form-select-sm" id="dm-f-table" onchange="window._dmLoadCols(this.value,'dm-f-date-col')">
                            <option value="">— Elige tabla —</option>${tableOpts}
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Columna de fecha</label>
                        <select class="form-select form-select-sm" id="dm-f-date-col">
                            <option value="">— Elige columna —</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Período</label>
                        <select class="form-select form-select-sm" id="dm-f-period">${periodOpts}</select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Tipo de gráfico</label>
                        <select class="form-select form-select-sm" id="dm-f-chart-type">
                            <option value="line" ${cfg.chart_type==='line'||!cfg.chart_type?'selected':''}>Línea</option>
                            <option value="bar"  ${cfg.chart_type==='bar'?'selected':''}>Barras</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Color</label>
                        <input type="color" class="form-control form-control-sm form-control-color" id="dm-f-color" value="${cfg.color||'#5569ff'}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Etiqueta del dataset</label>
                        <input type="text" class="form-control form-control-sm" id="dm-f-label" value="${esc(cfg.label||'')}" placeholder="Ej: Pedidos por día">
                    </div>
                </div>`;
            if (cfg.table) window._dmLoadCols(cfg.table, 'dm-f-date-col', cfg.date_column);

        } else if (type === 'recent') {
            container.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small">Tabla</label>
                        <select class="form-select form-select-sm" id="dm-f-table">
                            <option value="">— Elige tabla —</option>${tableOpts}
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Cantidad de registros</label>
                        <input type="number" class="form-control form-control-sm" id="dm-f-limit" value="${parseInt(cfg.limit||5)}" min="1" max="20">
                    </div>
                </div>`;

        } else if (type === 'activity') {
            container.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Cantidad de entradas</label>
                        <input type="number" class="form-control form-control-sm" id="dm-f-limit" value="${parseInt(cfg.limit||10)}" min="1" max="20">
                    </div>
                </div>`;

        } else if (type === 'quicklinks') {
            const links = cfg.links || [];
            const rows = links.map((lnk, i) => buildLinkRow(i, lnk)).join('');
            container.innerHTML = `
                <div id="dm-links-container">${rows}</div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="window._dmAddLink()">
                    <i class="bi bi-plus-lg me-1"></i>Agregar enlace
                </button>`;
            if (links.length === 0) window._dmAddLink();

        } else if (type === 'html') {
            container.innerHTML = `
                <div>
                    <label class="form-label small">Contenido HTML</label>
                    <textarea class="form-control form-control-sm" id="dm-f-html" rows="6" placeholder="<p>Tu HTML aquí...</p>">${esc(cfg.content||'')}</textarea>
                    <div class="text-muted mt-1" style="font-size:11px">El contenido se renderiza tal cual en el widget.</div>
                </div>`;

        } else if (type === 'system') {
            container.innerHTML = `<p class="text-muted small">Este widget no requiere configuración adicional. Muestra PHP, MySQL y estado del disco.</p>`;
        }
    }

    function buildLinkRow(i, lnk) {
        const colorBtns = ['primary','success','danger','warning','info','secondary','dark'].map(c =>
            `<option value="${c}" ${(lnk && lnk.color === c) ? 'selected' : ''}>${c}</option>`
        ).join('');
        return `
            <div class="dm-link-row mb-2" data-link-idx="${i}">
                <input type="text" class="form-control form-control-sm" placeholder="Etiqueta" value="${esc(lnk&&lnk.label||'')}">
                <input type="text" class="form-control form-control-sm" placeholder="URL (ej: clientes)" value="${esc(lnk&&lnk.url||'')}">
                <input type="text" class="form-control form-control-sm" style="width:120px" placeholder="Icono bi-*" value="${esc(lnk&&lnk.icon||'bi-link')}">
                <select class="form-select form-select-sm" style="width:100px">${colorBtns}</select>
                <button type="button" class="btn btn-sm btn-outline-danger dm-remove-link" onclick="this.closest('.dm-link-row').remove()">
                    <i class="bi bi-x"></i>
                </button>
            </div>`;
    }

    // ── Collect config from form ──────────────────────────

    function collectConfig(type) {
        const f  = id => document.getElementById(id);
        const fv = id => { const el = f(id); return el ? el.value : ''; };
        const fi = id => { const el = f(id); return el ? parseInt(el.value)||0 : 0; };

        const config = {};

        if (type === 'metric') {
            config.table     = fv('dm-f-table');
            config.operation = fv('dm-f-operation');
            config.column    = fv('dm-f-column');
            config.label     = fv('dm-f-label');
            config.suffix    = fv('dm-f-suffix');
            config.icon      = fv('dm-f-icon')  || 'bi-bar-chart-line';
            config.color     = fv('dm-f-color') || 'primary';
        } else if (type === 'kpi') {
            config.table       = fv('dm-f-table');
            config.date_column = fv('dm-f-date-col');
            config.period_days = fi('dm-f-period-days') || 30;
            config.label       = fv('dm-f-label');
            config.color       = fv('dm-f-color') || 'primary';
        } else if (type === 'chart') {
            config.table       = fv('dm-f-table');
            config.date_column = fv('dm-f-date-col');
            config.period      = fv('dm-f-period')     || '30';
            config.chart_type  = fv('dm-f-chart-type') || 'line';
            config.color       = fv('dm-f-color')      || '#5569ff';
            config.label       = fv('dm-f-label');
        } else if (type === 'recent') {
            config.table = fv('dm-f-table');
            config.limit = fi('dm-f-limit') || 5;
        } else if (type === 'activity') {
            config.limit = fi('dm-f-limit') || 10;
        } else if (type === 'quicklinks') {
            const rows = document.querySelectorAll('#dm-links-container .dm-link-row');
            config.links = [];
            rows.forEach(function (row) {
                const inputs  = row.querySelectorAll('input');
                const selects = row.querySelectorAll('select');
                config.links.push({
                    label: inputs[0] ? inputs[0].value : '',
                    url:   inputs[1] ? inputs[1].value : '',
                    icon:  inputs[2] ? inputs[2].value : 'bi-link',
                    color: selects[0] ? selects[0].value : 'primary',
                });
            });
        } else if (type === 'html') {
            config.content = fv('dm-f-html');
        }

        return config;
    }

    // ── Save widget (add or update) ───────────────────────

    function saveWidget() {
        if (!_selectedType) return;

        const config  = collectConfig(_selectedType);
        const title   = document.getElementById('dm-f-title').value.trim();
        const width   = document.getElementById('dm-f-width').value;
        const refresh = document.getElementById('dm-f-refresh').value;

        const payload = {
            type:    _selectedType,
            title:   title,
            config:  JSON.stringify(config),
            width:   width,
            refresh: refresh,
        };

        if (_editingId) {
            payload.ajax_action = 'update_widget';
            payload.widget_id   = _editingId;
        } else {
            payload.ajax_action = 'save_widget';
        }

        const saveBtn = document.getElementById('dm-save-btn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

        post(payload).then(function (res) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar widget';

            if (!res.success) { alert('Error al guardar el widget.'); return; }
            _addModal.hide();
            loadWidgets(); // Reload the full grid
        });
    }

    // ── Load tables for selects ───────────────────────────

    function loadTables(callback) {
        if (_tables !== null) { if (callback) callback(); return; }
        post({ ajax_action: 'get_tables' }).then(function (res) {
            _tables = res.tables || [];
            if (callback) callback();
        });
    }

    // Exposed globally for onchange handlers inside injected HTML
    window._dmLoadCols = function (tableName, selectId, currentValue) {
        const sel = document.getElementById(selectId);
        if (!sel || !tableName) return;
        sel.innerHTML = '<option value="">Cargando...</option>';
        post({ ajax_action: 'get_columns', table: tableName }).then(function (res) {
            const cols = res.columns || [];
            sel.innerHTML = '<option value="">— Elige columna —</option>' +
                cols.map(c => `<option value="${esc(c)}" ${c === currentValue ? 'selected' : ''}>${esc(c)}</option>`).join('');
        });
    };

    window._dmAddLink = function () {
        const container = document.getElementById('dm-links-container');
        if (!container) return;
        const idx = container.querySelectorAll('.dm-link-row').length;
        container.insertAdjacentHTML('beforeend', buildLinkRow(idx, null));
    };

    // ── Helpers ───────────────────────────────────────────

    function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        return fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json());
    }

    function setLoading(on) {
        document.getElementById('dm-loading').classList.toggle('d-none', !on);
        document.getElementById('dm-grid').classList.toggle('d-none', on);
    }

    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#039;');
    }

    function formatNumber(n) {
        const num = parseFloat(n);
        if (isNaN(num)) return '0';
        if (Number.isInteger(num)) return num.toLocaleString('es-CL');
        return num.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1,3), 16);
        const g = parseInt(hex.slice(3,5), 16);
        const b = parseInt(hex.slice(5,7), 16);
        return `rgba(${r},${g},${b},${alpha})`;
    }

    function typeLabel(type) {
        const labels = {
            metric:     'Métrica',
            kpi:        'KPI',
            chart:      'Gráfico',
            recent:     'Registros recientes',
            activity:   'Actividad reciente',
            quicklinks: 'Accesos rápidos',
            html:       'HTML libre',
            system:     'Estado del sistema',
        };
        return labels[type] || type;
    }

    function colorOpts(current) {
        return ['primary','success','danger','warning','info','secondary','dark'].map(c =>
            `<option value="${c}" ${current === c ? 'selected' : ''}>${c}</option>`
        ).join('');
    }

})();
