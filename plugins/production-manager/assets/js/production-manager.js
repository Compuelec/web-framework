/* Production Manager — manufacturing UI (Fabricar / Recetas / Historial). */
(function () {
    'use strict';

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function num(n) { n = parseFloat(n); return (isNaN(n) ? 0 : (Math.round(n * 1000) / 1000)).toString(); }
    function post(data) { return $.post(window.PM_AJAX, data); }
    function unitOf(x) { return x && x.unit ? (' ' + esc(x.unit)) : ''; }

    /* ===================== FABRICAR ===================== */
    var mkProd = null, mkRecipe = [], mkYield = 1;

    function mkSearch() {
        post({ ajax_action: 'search_products', q: $('#mk-search').val().trim() }).done(function (res) {
            renderProductList($('#mk-results'), res, 'mk-pick');
        });
    }
    var mkTimer = null;
    $('#pm-app').on('input', '#mk-search', function () { clearTimeout(mkTimer); mkTimer = setTimeout(mkSearch, 250); });

    $('#pm-app').on('click', '.mk-pick', function () {
        mkProd = pickData($(this));
        $('.mk-pick').removeClass('active'); $(this).addClass('active');
        $('#mk-name').text(mkProd.name);
        $('#mk-stock').text(mkProd.stock);
        $('#mk-empty').hide(); $('#mk-panel').show(); $('#mk-qty').val(1);
        post({ ajax_action: 'get_recipe', product_id: mkProd.id }).done(function (res) {
            mkRecipe = (res && res.success) ? res.recipe : [];
            mkYield = (res && res.yield) ? parseFloat(res.yield) : 1;
            $('#mk-yield-note').text(mkYield > 1 ? (' · receta rinde ' + num(mkYield) + ' por lote') : '');
            renderMake();
        });
    });

    function renderMake() {
        var qty = Math.max(1, parseInt($('#mk-qty').val(), 10) || 1);
        var $b = $('#mk-recipe').empty();
        if (!mkRecipe.length) {
            $('#mk-norecipe').show(); $('#mk-max').text(''); $('#mk-produce').prop('disabled', true);
            return;
        }
        $('#mk-norecipe').hide();
        var enough = true, maxUnits = Infinity;
        mkRecipe.forEach(function (r) {
            var perUnit = (parseFloat(r.per_unit) || 0) / mkYield;
            var need = perUnit * qty, avail = parseFloat(r.available) || 0;
            var canMake = perUnit > 0 ? Math.floor(avail / perUnit) : Infinity;
            if (canMake < maxUnits) { maxUnits = canMake; }
            var short = need > avail; if (short) { enough = false; }
            var unit = r.unit ? (' ' + r.unit) : '';
            var $tr = $('<tr>', { 'class': short ? 'pm-short' : '' });
            $tr.append($('<td>').text(r.name));
            $tr.append($('<td class="text-end fw-semibold">').text(num(need) + unit));
            $tr.append($('<td>', { 'class': 'text-end ' + (short ? 'text-danger fw-semibold' : 'text-muted') }).text(num(avail) + unit));
            $b.append($tr);
        });
        if (maxUnits === Infinity) { maxUnits = 0; }
        $('#mk-max').html('<i class="bi bi-lightning-charge-fill"></i> Máx. ahora: ' + maxUnits);
        $('#mk-produce').prop('disabled', !enough).attr('title', enough ? '' : 'No hay insumos suficientes');
    }
    $('#pm-app').on('input', '#mk-qty', renderMake);

    $('#pm-app').on('click', '#mk-produce', function () {
        if (!mkProd) { return; }
        var qty = Math.max(1, parseInt($('#mk-qty').val(), 10) || 1);
        var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Fabricando…');
        post({ ajax_action: 'produce', product_id: mkProd.id, qty: qty }).done(function (res) {
            $btn.html('<i class="bi bi-hammer me-1"></i>Fabricar');
            if (res && res.success) {
                $('#pm-result-body').html(
                    '<div class="text-center"><i class="bi bi-check2-circle text-success" style="font-size:2.2rem"></i></div>' +
                    '<p class="text-center mb-0 mt-2">Se fabricaron <strong>' + qty + '</strong> u. de <strong>' + esc(mkProd.name) + '</strong>.<br>' +
                    '<span class="small text-muted">Stock e insumos actualizados.</span></p>'
                );
                new bootstrap.Modal(document.getElementById('pm-result-modal')).show();
                post({ ajax_action: 'get_recipe', product_id: mkProd.id }).done(function (r2) {
                    mkRecipe = (r2 && r2.success) ? r2.recipe : mkRecipe; renderMake();
                });
                mkSearch();
                if (typeof fncToastr === 'function') { fncToastr('success', 'Fabricación registrada'); }
                return;
            }
            $btn.prop('disabled', false);
            if (res && res.error === 'insufficient_supply') {
                var s = res.supply || {}, u = s.unit ? (' ' + s.unit) : '';
                msg('error', 'Falta insumo: "' + (s.name || '') + '" (necesita ' + num(s.required) + u + ', hay ' + num(s.available) + u + ').');
            } else if (res && res.error === 'no_recipe') { msg('error', 'Este producto no tiene receta.'); }
            else if (res && res.error === 'product_not_found') { msg('error', 'El producto ya no existe.'); mkSearch(); }
            else { msg('error', (res && res.error) || 'No se pudo fabricar.'); }
        }).fail(function () { $btn.prop('disabled', false).html('<i class="bi bi-hammer me-1"></i>Fabricar'); msg('error', 'Sin conexión con el servidor.'); });
    });

    /* ===================== RECETAS (editor) ===================== */
    var rcProd = null, rcLines = [];

    function rcSearch() {
        post({ ajax_action: 'search_products', q: $('#rc-search').val().trim() }).done(function (res) {
            renderProductList($('#rc-results'), res, 'rc-pick');
        });
    }
    var rcTimer = null;
    $('#pm-app').on('input', '#rc-search', function () { clearTimeout(rcTimer); rcTimer = setTimeout(rcSearch, 250); });

    $('#pm-app').on('click', '.rc-pick', function () {
        rcProd = pickData($(this));
        $('.rc-pick').removeClass('active'); $(this).addClass('active');
        $('#rc-name').text(rcProd.name);
        $('#rc-empty').hide(); $('#rc-panel').show(); $('#rc-msg').empty();
        post({ ajax_action: 'get_recipe', product_id: rcProd.id }).done(function (res) {
            $('#rc-yield').val((res && res.yield) ? num(res.yield) : 1);
            rcLines = ((res && res.success) ? res.recipe : []).map(function (r) {
                return { supply_id: parseInt(r.supply_id, 10), name: r.name, unit: r.unit || '', qty: parseFloat(r.per_unit) };
            });
            renderLines();
        });
    });

    function renderLines() {
        var $c = $('#rc-lines').empty();
        if (!rcLines.length) { $c.html('<div class="pm-empty-note">Aún sin insumos. Agrega abajo.</div>'); return; }
        rcLines.forEach(function (l, i) {
            $c.append(
                '<div class="pm-ing" data-i="' + i + '">' +
                '<span class="pm-ing-name">' + esc(l.name) + '</span>' +
                '<div class="input-group input-group-sm pm-ing-qty">' +
                '<input type="number" class="form-control rc-qty" min="0" step="any" value="' + esc(l.qty) + '">' +
                (l.unit ? '<span class="input-group-text">' + esc(l.unit) + '</span>' : '') + '</div>' +
                '<button type="button" class="btn btn-sm btn-link text-danger rc-rm p-0"><i class="bi bi-x-lg"></i></button></div>'
            );
        });
    }
    $('#pm-app').on('input', '.rc-qty', function () {
        var i = $(this).closest('.pm-ing').data('i'); rcLines[i].qty = parseFloat($(this).val()) || 0;
    });
    $('#pm-app').on('click', '.rc-rm', function () {
        var i = $(this).closest('.pm-ing').data('i'); rcLines.splice(i, 1); renderLines();
    });

    // add-ingredient search dropdown
    var rcAddTimer = null;
    $('#pm-app').on('input', '#rc-add-search', function () {
        var q = $(this).val().trim();
        clearTimeout(rcAddTimer);
        rcAddTimer = setTimeout(function () {
            post({ ajax_action: 'search_supplies', q: q }).done(function (res) {
                var $d = $('#rc-add-results').empty();
                if (!res || !res.success || !res.supplies.length) { $d.hide(); return; }
                res.supplies.forEach(function (s) {
                    var $b = $('<button>', { type: 'button', 'class': 'pm-dd-item rc-add', 'data-id': s.id, 'data-name': s.name, 'data-unit': s.unit || '' });
                    $b.text(s.name);
                    if (s.unit) { $b.append($('<span class="text-muted">').text(' (' + s.unit + ')')); }
                    $d.append($b);
                });
                $d.show();
            });
        }, 200);
    });
    $('#pm-app').on('click', '.rc-add', function () {
        var id = parseInt($(this).data('id'), 10);
        if (!rcLines.some(function (l) { return l.supply_id === id; })) {
            rcLines.push({ supply_id: id, name: $(this).data('name'), unit: $(this).data('unit'), qty: 1 });
            renderLines();
        }
        $('#rc-add-search').val(''); $('#rc-add-results').empty().hide();
    });
    $(document).on('click', function (e) { if (!$(e.target).closest('#rc-add-search,#rc-add-results').length) { $('#rc-add-results').hide(); } });

    $('#pm-app').on('click', '#rc-save', function () {
        if (!rcProd) { return; }
        var lines = rcLines.filter(function (l) { return l.qty > 0; }).map(function (l) { return { supply: l.supply_id, qty: l.qty }; });
        var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Guardando…');
        post({ ajax_action: 'save_recipe', product_id: rcProd.id, yield: parseFloat($('#rc-yield').val()) || 1, lines: JSON.stringify(lines) }).done(function (res) {
            $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar receta');
            if (res && res.success) { $('#rc-msg').html('<span class="text-success"><i class="bi bi-check-circle me-1"></i>Receta guardada (' + res.lines + ' insumos).</span>'); if (typeof fncToastr === 'function') { fncToastr('success', 'Receta guardada'); } }
            else { $('#rc-msg').html('<span class="text-danger">' + esc((res && res.error) || 'Error') + '</span>'); }
        }).fail(function () { $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar receta'); $('#rc-msg').html('<span class="text-danger">Sin conexión.</span>'); });
    });

    /* ===================== HISTORIAL ===================== */
    $('#pm-history-tab').on('shown.bs.tab', loadHistory);
    function loadHistory() {
        post({ ajax_action: 'get_productions' }).done(function (res) {
            var $b = $('#hi-rows').empty();
            if (!res || !res.success || !res.productions.length) { $b.html('<tr><td colspan="5" class="text-muted small">Sin producciones aún.</td></tr>'); return; }
            res.productions.forEach(function (p) {
                $b.append('<tr><td class="text-muted">#' + p.id + '</td><td class="fw-semibold">' + esc(p.product) + '</td><td class="text-end">' + esc(p.qty) + '</td><td>' + esc(p.user_name || '—') + '</td><td class="small text-muted">' + esc(p.date || '') + '</td></tr>');
            });
        });
    }

    /* ===================== shared ===================== */
    function renderProductList($el, res, cls) {
        $el.empty();
        if (!res || !res.success || !res.products.length) { $el.append($('<div class="text-muted small p-2">').text('Sin resultados.')); return; }
        res.products.forEach(function (p) {
            var $b = $('<button>', { type: 'button', 'class': 'pm-li ' + cls, 'data-id': p.id, 'data-name': p.name, 'data-stock': p.stock });
            $b.append($('<span>').text(p.name));
            $b.append($('<span class="pm-li-badge">').text('Stock: ' + p.stock));
            $el.append($b);
        });
    }
    function pickData($el) { return { id: parseInt($el.data('id'), 10), name: $el.data('name'), stock: $el.data('stock') }; }
    function msg(type, text) { if (typeof fncSweetAlert === 'function') { fncSweetAlert(type, text, ''); } else { alert(text); } }

    // Defer first POSTs to DOM-ready so the CSRF interceptor is active.
    $(function () { mkSearch(); rcSearch(); });

})();
