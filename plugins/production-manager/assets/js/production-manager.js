/* Production Manager — manufacturing UI. */
(function () {
    'use strict';

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function num(n) { n = parseFloat(n); return (isNaN(n) ? 0 : (Math.round(n * 1000) / 1000)).toString(); }
    function post(data) { return $.post(window.PM_AJAX, data); }

    var current = null;   // { id, name, stock }
    var recipe  = [];     // [{ supply_id, name, per_unit, available, unit }]

    /* ---------- product search ---------- */
    function runSearch() {
        post({ ajax_action: 'search_products', q: $('#pm-search').val().trim() })
            .done(function (res) {
                var $r = $('#pm-results').empty();
                if (!res || !res.success || !res.products.length) {
                    $r.html('<div class="text-muted small p-2">Sin resultados.</div>');
                    return;
                }
                res.products.forEach(function (p) {
                    $r.append(
                        '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center pm-pick" ' +
                        'data-id="' + p.id + '" data-name="' + esc(p.name) + '" data-stock="' + esc(p.stock) + '">' +
                        '<span>' + esc(p.name) + '</span>' +
                        '<span class="badge bg-light text-secondary border">Stock: ' + esc(p.stock) + '</span></button>'
                    );
                });
            });
    }

    var searchTimer = null;
    $('#pm-app').on('input', '#pm-search', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(runSearch, 250);
    });

    /* ---------- pick a product → load recipe ---------- */
    $('#pm-app').on('click', '.pm-pick', function () {
        current = { id: parseInt($(this).data('id'), 10), name: $(this).data('name'), stock: $(this).data('stock') };
        $('.pm-pick').removeClass('active');
        $(this).addClass('active');
        $('#pm-prod-name').text(current.name);
        $('#pm-prod-stock').text(current.stock);
        $('#pm-empty').hide();
        $('#pm-panel').show();
        $('#pm-qty').val(1);

        post({ ajax_action: 'get_recipe', product_id: current.id }).done(function (res) {
            recipe = (res && res.success) ? res.recipe : [];
            renderRecipe();
        });
    });

    function renderRecipe() {
        var qty = Math.max(1, parseInt($('#pm-qty').val(), 10) || 1);
        var $b = $('#pm-recipe').empty();
        if (!recipe.length) {
            $('#pm-norecipe').show();
            $('#pm-produce').prop('disabled', true);
            return;
        }
        $('#pm-norecipe').hide();
        var enough = true;
        recipe.forEach(function (r) {
            var need = parseFloat(r.per_unit) * qty;
            var avail = parseFloat(r.available);
            var unit = r.unit ? (' ' + esc(r.unit)) : '';
            var short = need > avail;
            if (short) { enough = false; }
            $b.append(
                '<tr class="' + (short ? 'table-danger' : '') + '">' +
                '<td>' + esc(r.name) + '</td>' +
                '<td class="text-end fw-semibold">' + num(need) + unit + '</td>' +
                '<td class="text-end ' + (short ? 'text-danger fw-semibold' : 'text-muted') + '">' + num(avail) + unit + '</td>' +
                '</tr>'
            );
        });
        $('#pm-produce').prop('disabled', !enough)
            .attr('title', enough ? '' : 'No hay insumos suficientes');
    }

    $('#pm-app').on('input', '#pm-qty', renderRecipe);

    /* ---------- produce ---------- */
    $('#pm-app').on('click', '#pm-produce', function () {
        if (!current) { return; }
        var qty = Math.max(1, parseInt($('#pm-qty').val(), 10) || 1);
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Fabricando…');

        post({ ajax_action: 'produce', product_id: current.id, qty: qty })
            .done(function (res) {
                $btn.prop('disabled', false).html('<i class="bi bi-hammer me-1"></i>Fabricar');
                if (res && res.success) {
                    $('#pm-result-body').html(
                        '<div class="text-center"><i class="bi bi-check2-circle text-success" style="font-size:2rem"></i></div>' +
                        '<p class="text-center mb-0 mt-2">Se fabricaron <strong>' + qty + '</strong> u. de <strong>' + esc(current.name) + '</strong>.<br>' +
                        '<span class="small text-muted">Stock e insumos actualizados.</span></p>'
                    );
                    new bootstrap.Modal(document.getElementById('pm-result-modal')).show();
                    // refresh stock + availability
                    post({ ajax_action: 'get_recipe', product_id: current.id }).done(function (r2) {
                        recipe = (r2 && r2.success) ? r2.recipe : recipe;
                        renderRecipe();
                    });
                    runSearch();
                    if (typeof fncToastr === 'function') { fncToastr('success', 'Fabricación registrada'); }
                    return;
                }
                if (res && res.error === 'insufficient_supply') {
                    var s = res.supply || {};
                    var u = s.unit ? (' ' + s.unit) : '';
                    msg('error', 'Falta insumo: "' + (s.name || '') + '" (necesita ' + num(s.required) + u + ', hay ' + num(s.available) + u + ').');
                    return;
                }
                if (res && res.error === 'no_recipe') { msg('error', 'Este producto no tiene receta definida.'); return; }
                msg('error', (res && res.error) || 'No se pudo registrar la fabricación.');
            })
            .fail(function () {
                $btn.prop('disabled', false).html('<i class="bi bi-hammer me-1"></i>Fabricar');
                msg('error', 'No se pudo contactar al servidor.');
            });
    });

    function msg(type, text) {
        if (typeof fncSweetAlert === 'function') { fncSweetAlert(type, text, ''); }
        else { alert(text); }
    }

    // Defer the first POST to DOM-ready so the CSRF interceptor is active.
    $(function () { runSearch(); });

})();
