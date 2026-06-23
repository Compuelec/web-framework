/* Data Protection (Ley 21.719) — ARCOP requests + subject find/export/erase. */
(function () {
    'use strict';

    function post(data) { return $.post(window.DP_AJAX, data); }
    function num(n) { n = parseInt(n, 10); return isNaN(n) ? 0 : n; }

    var TYPE = {
        access: 'Acceso', rectification: 'Rectificación', cancellation: 'Cancelación',
        opposition: 'Oposición', portability: 'Portabilidad', blocking: 'Bloqueo'
    };
    var STATUS = { pending: 'Pendiente', in_progress: 'En proceso', done: 'Resuelta', rejected: 'Rechazada' };
    var STATUS_CLS = { pending: 'bg-warning text-dark', in_progress: 'bg-info text-dark', done: 'bg-success', rejected: 'bg-secondary' };

    function toast(type, msg) { if (typeof fncToastr === 'function') { fncToastr(type, msg); } }
    function alertMsg(type, title, text) { if (typeof fncSweetAlert === 'function') { fncSweetAlert(type, title, text); } else { alert(title + (text ? ': ' + text : '')); } }

    /* ===================== SOLICITUDES ARCOP ===================== */
    function loadRequests() {
        post({ ajax_action: 'list_requests', status: $('#dp-req-filter').val() }).done(function (res) {
            var $b = $('#dp-req-rows').empty();
            if (!res || !res.success || !res.requests.length) {
                $b.append($('<tr>').append($('<td colspan="6" class="text-muted small p-3">').text('Sin solicitudes.')));
                return;
            }
            res.requests.forEach(function (r) {
                var overdue = (r.days_left !== null && parseInt(r.days_left, 10) < 0 && r.status_request !== 'done' && r.status_request !== 'rejected');
                var $due = $('<td class="small">');
                $due.append($('<span>').text(r.due_request || '—'));
                if (overdue) { $due.append($('<span class="badge bg-danger ms-1">').text('vencida')); }
                else if (r.days_left !== null && r.status_request !== 'done' && r.status_request !== 'rejected') {
                    $due.append($('<span class="text-muted ms-1">').text('(' + r.days_left + 'd)'));
                }

                var $sel = $('<select class="form-select form-select-sm dp-req-status">').attr('data-id', r.id_request).css('width', 'auto');
                ['pending', 'in_progress', 'done', 'rejected'].forEach(function (s) {
                    $('<option>').attr('value', s).text(STATUS[s]).prop('selected', s === r.status_request).appendTo($sel);
                });

                var $tr = $('<tr>');
                $tr.append($('<td class="text-muted">').text('#' + r.id_request));
                $tr.append($('<td>').append($('<span class="badge bg-light text-dark border">').text(TYPE[r.type_request] || r.type_request)));
                $tr.append($('<td>').text(r.subject_request || '—'));
                $tr.append($('<td>').append($('<span class="badge ' + (STATUS_CLS[r.status_request] || 'bg-secondary') + '">').text(STATUS[r.status_request] || r.status_request)));
                $tr.append($due);
                $tr.append($('<td>').append($sel));
                $b.append($tr);
            });
        });
    }

    $('#dp-app').on('change', '#dp-req-filter', loadRequests);
    $('#dp-requests-tab').on('shown.bs.tab', loadRequests);

    $('#dp-app').on('change', '.dp-req-status', function () {
        var id = $(this).data('id'), status = $(this).val();
        post({ ajax_action: 'update_request', id: id, status: status }).done(function (res) {
            if (res && res.success) { toast('success', 'Solicitud actualizada'); loadRequests(); }
            else { alertMsg('error', 'No se pudo actualizar', (res && res.error) || ''); }
        });
    });

    $('#dp-app').on('click', '#dp-req-save', function () {
        var subject = $('#dp-req-subject').val().trim();
        if (!subject) { alertMsg('warning', 'Falta el titular', 'Indica el email, RUT o nombre.'); return; }
        var $btn = $(this).prop('disabled', true);
        post({
            ajax_action: 'create_request',
            type: $('#dp-req-type').val(),
            subject: subject,
            channel: $('#dp-req-channel').val().trim(),
            notes: $('#dp-req-notes').val().trim()
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (res && res.success) {
                toast('success', 'Solicitud registrada (vence ' + (res.due || '') + ')');
                $('#dp-req-subject').val(''); $('#dp-req-channel').val(''); $('#dp-req-notes').val('');
                loadRequests();
            } else { alertMsg('error', 'No se pudo registrar', (res && res.error) || ''); }
        }).fail(function () { $btn.prop('disabled', false); alertMsg('error', 'Error de conexión', ''); });
    });

    /* ===================== BUSCAR TITULAR ===================== */
    var lastQuery = '';

    function renderSubject(res) {
        var $c = $('#dp-sub-datasets').empty();
        $('#dp-sub-total').text(num(res.total));
        $('#dp-sub-label').text(res.subject);
        $('#dp-sub-empty').hide();
        $('#dp-sub-result').show();
        if (!res.datasets.length) {
            $c.append($('<div class="text-muted small py-2">').text('No se encontraron datos personales para ese identificador.'));
            $('#dp-sub-anon, #dp-sub-del, #dp-sub-export').prop('disabled', true);
            return;
        }
        $('#dp-sub-anon, #dp-sub-del, #dp-sub-export').prop('disabled', false);
        res.datasets.forEach(function (d) {
            var $card = $('<div class="dp-ds mb-3">');
            var $h = $('<div class="dp-ds-h">');
            $h.append($('<strong>').text(d.label));
            $h.append($('<span class="badge bg-light text-dark border ms-2">').text(d.count + ' registro(s)'));
            $card.append($h);

            var cols = d.fields || [];
            var $tbl = $('<table class="table table-sm mb-0">');
            var $thead = $('<tr class="small text-muted">').append($('<th>').text('#'));
            cols.forEach(function (c) {
                var $th = $('<th>').text(c);
                if ((d.sensitive || []).indexOf(c) >= 0) { $th.append($('<span class="badge bg-danger ms-1">').text('sensible')); }
                $thead.append($th);
            });
            $tbl.append($('<thead>').append($thead));
            var $tb = $('<tbody>');
            (d.rows || []).forEach(function (row) {
                var $tr = $('<tr>').append($('<td class="text-muted">').text(row[d.id_column]));
                cols.forEach(function (c) { $tr.append($('<td>').text(row[c] == null ? '—' : String(row[c]))); });
                $tb.append($tr);
            });
            $tbl.append($tb);
            $card.append($('<div class="table-responsive">').append($tbl));
            $c.append($card);
        });
    }

    function findSubject() {
        var q = $('#dp-sub-q').val().trim();
        if (!q) { alertMsg('warning', 'Falta el identificador', ''); return; }
        lastQuery = q;
        post({ ajax_action: 'find_subject', q: q }).done(function (res) {
            if (res && res.success) { renderSubject(res); }
            else if (res && res.error === 'no_datasets') { alertMsg('info', 'Aún no configuras tablas', 'Ve a la pestaña Configuración y marca qué tablas tienen datos personales.'); }
            else { alertMsg('error', 'No se pudo buscar', (res && res.error) || ''); }
        });
    }
    $('#dp-app').on('click', '#dp-sub-find', findSubject);
    $('#dp-app').on('keydown', '#dp-sub-q', function (e) { if (e.key === 'Enter') { findSubject(); } });

    $('#dp-app').on('click', '#dp-sub-export', function () {
        if (!lastQuery) { return; }
        post({ ajax_action: 'export_subject', q: lastQuery }).done(function (res) {
            if (!res || !res.success) { alertMsg('error', 'No se pudo exportar', (res && res.error) || ''); return; }
            var blob = new Blob([JSON.stringify(res, null, 2)], { type: 'application/json' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'datos_' + lastQuery.replace(/[^a-z0-9._-]+/gi, '_') + '.json';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
            toast('success', 'Exportación generada');
        });
    });

    function erase(mode) {
        if (!lastQuery) { return; }
        var isDelete = mode === 'delete';
        Swal.fire({
            title: isDelete ? '¿Borrar los datos del titular?' : '¿Anonimizar los datos del titular?',
            html: (isDelete
                ? 'Se <strong>eliminarán</strong> los registros personales de <code>' + $('<div>').text(lastQuery).html() + '</code> en todas las tablas declaradas.'
                : 'Se <strong>anonimizarán</strong> los datos personales de <code>' + $('<div>').text(lastQuery).html() + '</code> (los registros se conservan sin identificar).')
                + '<br><span class="text-danger">Esta acción no se puede deshacer.</span>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: isDelete ? '#d33' : '#e0a800',
            confirmButtonText: isDelete ? 'Sí, borrar' : 'Sí, anonimizar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (!result.isConfirmed) { return; }
            post({ ajax_action: 'erase_subject', q: lastQuery, mode: mode }).done(function (res) {
                if (res && res.success) {
                    toast('success', (isDelete ? 'Datos borrados' : 'Datos anonimizados') + ' (' + num(res.total_rows) + ' registros)');
                    findSubject();
                } else { alertMsg('error', 'No se pudo completar', (res && res.error) || ''); }
            }).fail(function () { alertMsg('error', 'Error de conexión', ''); });
        });
    }
    $('#dp-app').on('click', '#dp-sub-anon', function () { erase('anonymize'); });
    $('#dp-app').on('click', '#dp-sub-del', function () { erase('delete'); });

    /* ===================== CONFIGURACIÓN (visual) ===================== */
    var cfgDatasets = {};   // table -> saved config
    var STRAT = { '': 'No tocar', 'null': 'Vaciar', 'redact': 'Tachar', 'hash': 'Hashear' };

    function loadConfig() {
        post({ ajax_action: 'list_datasets' }).done(function (res) {
            cfgDatasets = {};
            var $l = $('#dp-cfg-list').empty();
            var list = (res && res.success) ? res.datasets : [];
            if (!list.length) { $l.append($('<div class="text-muted small">').text('Aún no hay tablas marcadas con datos personales.')); }
            list.forEach(function (d) {
                cfgDatasets[d.table] = d;
                var $row = $('<div class="dp-cfg-item">');
                var $info = $('<div>');
                $info.append($('<div class="fw-semibold">').text(d.label || d.table));
                $info.append($('<div class="small text-muted">').text(d.table + ' · ' + (d.fields || []).length + ' campos · clave: ' + d.pk));
                $row.append($info);
                var $btns = $('<div class="btn-group btn-group-sm">');
                $btns.append($('<button class="btn btn-outline-secondary dp-cfg-edit" title="Editar"><i class="bi bi-pencil"></i></button>').attr('data-table', d.table));
                $btns.append($('<button class="btn btn-outline-danger dp-cfg-del" title="Quitar"><i class="bi bi-trash"></i></button>').attr('data-table', d.table));
                $row.append($btns);
                $l.append($row);
            });
        });
        post({ ajax_action: 'list_tables' }).done(function (res) {
            var $s = $('#dp-cfg-table').empty().append($('<option value="">').text('Selecciona una tabla…'));
            ((res && res.success) ? res.tables : []).forEach(function (t) {
                $s.append($('<option>').val(t.name).text(t.name + (t.configured ? '  ✓' : '')));
            });
        });
    }
    $('#dp-config-tab').on('shown.bs.tab', loadConfig);

    function renderColumns(table, columns, saved) {
        saved = saved || {};
        var fields = saved.fields || [], keys = saved.subject_keys || [], sens = saved.sensitive || [], anon = saved.anonymize || {};
        $('#dp-cfg-label').val(saved.label || table);
        $('#dp-cfg-purpose').val(saved.purpose || '');
        $('#dp-cfg-legal').val(saved.legal_basis || '');
        $('#dp-cfg-retention').val(saved.retention_days != null ? saved.retention_days : '');

        var $pk = $('#dp-cfg-pk').empty();
        columns.forEach(function (c) { $pk.append($('<option>').val(c.name).text(c.name + (c.pk ? ' (clave)' : ''))); });
        var pkVal = saved.pk || (columns.find(function (c) { return c.pk; }) || {}).name || (columns[0] || {}).name;
        $pk.val(pkVal);

        var $b = $('#dp-cfg-cols').empty();
        columns.forEach(function (c) {
            var isField = fields.indexOf(c.name) >= 0, isKey = keys.indexOf(c.name) >= 0, isSens = sens.indexOf(c.name) >= 0;
            var $tr = $('<tr>').attr('data-col', c.name);
            $tr.append($('<td>').append($('<span class="fw-semibold">').text(c.name)).append($('<span class="text-muted small ms-1">').text(c.type)));
            $tr.append($('<td class="text-center">').append($('<input type="checkbox" class="form-check-input dp-c-field">').prop('checked', isField)));
            $tr.append($('<td class="text-center">').append($('<input type="checkbox" class="form-check-input dp-c-key">').prop('checked', isKey)));
            $tr.append($('<td class="text-center">').append($('<input type="checkbox" class="form-check-input dp-c-sens">').prop('checked', isSens)));
            var $sel = $('<select class="form-select form-select-sm dp-c-strat">');
            Object.keys(STRAT).forEach(function (k) { $sel.append($('<option>').val(k).text(STRAT[k])); });
            $sel.val(anon[c.name] || '');
            $tr.append($('<td>').append($sel));
            $b.append($tr);
        });
        $('#dp-cfg-empty').hide();
        $('#dp-cfg-editor').show();
    }

    function openTable(table) {
        if (!table) { $('#dp-cfg-editor').hide(); $('#dp-cfg-empty').show(); return; }
        post({ ajax_action: 'list_columns', table: table }).done(function (res) {
            if (!res || !res.success) { alertMsg('error', 'No se pudieron leer las columnas', (res && res.error) || ''); return; }
            renderColumns(table, res.columns, cfgDatasets[table]);
            $('#dp-cfg-editor').data('table', table);
        });
    }
    $('#dp-app').on('change', '#dp-cfg-table', function () { openTable($(this).val()); });
    $('#dp-app').on('click', '.dp-cfg-edit', function () {
        var t = $(this).data('table'); $('#dp-cfg-table').val(t); openTable(t);
    });

    $('#dp-app').on('click', '.dp-cfg-del', function () {
        var t = $(this).data('table');
        Swal.fire({
            title: '¿Quitar esta tabla?', text: '"' + t + '" dejará de tratarse como tabla con datos personales (no borra datos).',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            post({ ajax_action: 'delete_dataset', table: t }).done(function (res) {
                if (res && res.success) { toast('success', 'Tabla quitada'); loadConfig(); $('#dp-cfg-editor').hide(); $('#dp-cfg-empty').show(); }
                else { alertMsg('error', 'No se pudo quitar', (res && res.error) || ''); }
            });
        });
    });

    $('#dp-app').on('click', '#dp-cfg-save', function () {
        var table = $('#dp-cfg-editor').data('table');
        if (!table) { return; }
        var fields = [], keys = [], sens = [], anon = {};
        $('#dp-cfg-cols tr').each(function () {
            var col = $(this).data('col');
            if ($(this).find('.dp-c-field').prop('checked')) { fields.push(col); }
            if ($(this).find('.dp-c-key').prop('checked')) { keys.push(col); }
            if ($(this).find('.dp-c-sens').prop('checked')) { sens.push(col); }
            var strat = $(this).find('.dp-c-strat').val();
            if (strat) { anon[col] = strat; }
        });
        if (!keys.length) { alertMsg('warning', 'Falta el identificador', 'Marca al menos una columna que identifique al titular (email, RUT…).'); return; }
        var payload = {
            table: table, label: $('#dp-cfg-label').val().trim(), pk: $('#dp-cfg-pk').val(),
            subject_keys: keys, fields: fields, sensitive: sens, anonymize: anon,
            purpose: $('#dp-cfg-purpose').val().trim(), legal_basis: $('#dp-cfg-legal').val(),
            retention_days: $('#dp-cfg-retention').val()
        };
        var $btn = $(this).prop('disabled', true);
        post({ ajax_action: 'save_dataset', dataset: JSON.stringify(payload) }).done(function (res) {
            $btn.prop('disabled', false);
            if (res && res.success) { toast('success', 'Tabla guardada'); loadConfig(); }
            else { alertMsg('error', 'No se pudo guardar', (res && res.error) || ''); }
        }).fail(function () { $btn.prop('disabled', false); alertMsg('error', 'Error de conexión', ''); });
    });

    // Defer the first POST to DOM-ready so the CSRF interceptor is active.
    $(function () { loadRequests(); loadConfig(); });

})();
