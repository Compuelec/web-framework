/*=============================================
POS Manager — cashier point-of-sale (front-end)

Searches products, builds a cart, and confirms the sale through the plugin AJAX
endpoint. jQuery is used so the global CSRF interceptor attaches the token to the
create_sale request automatically.
=============================================*/

(function () {
    'use strict';

    var $app = $('#pos-app');
    if (!$app.length || !window.POS_AJAX) { return; }

    var cart = {};      // id -> { id, name, price, stock, qty }
    var products = {};  // id -> latest product snapshot from the server

    function money(n) { return '$' + Math.round(n).toLocaleString('es-CL'); }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
        });
    }

    function post(data) {
        return $.ajax({ url: window.POS_AJAX, method: 'POST', dataType: 'json', data: data });
    }

    /* ---------- product search ---------- */
    var searchTimer = null;

    function runSearch() {
        var q = $('#pos-search').val().trim();
        post({ ajax_action: 'search_products', q: q })
            .done(function (res) {
                var $grid = $('#pos-products');
                if (!res || !res.success) { $grid.html('<div class="text-danger small">No se pudieron cargar los productos.</div>'); return; }
                products = {};
                if (!res.products.length) { $grid.html('<div class="text-muted small">Sin resultados.</div>'); return; }
                $grid.empty();
                res.products.forEach(function (p) {
                    products[p.id] = p;
                    var out = p.stock <= 0;
                    var $col = $('<div class="col-6 col-md-4"></div>');
                    $col.html(
                        '<button type="button" class="btn btn-outline-secondary w-100 h-100 text-start pos-prod" data-id="' + p.id + '"' + (out ? ' disabled' : '') + '>' +
                            '<div class="fw-semibold text-truncate">' + esc(p.name) + '</div>' +
                            '<div class="small text-primary">' + money(p.price) + '</div>' +
                            '<div class="small ' + (out ? 'text-danger' : 'text-muted') + '">' + (out ? 'Agotado' : ('Stock: ' + p.stock)) + '</div>' +
                        '</button>'
                    );
                    $grid.append($col);
                });
            })
            .fail(function () { $('#pos-products').html('<div class="text-danger small">Error de conexión.</div>'); });
    }

    $('#pos-search').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(runSearch, 250);
    });

    /* ---------- cart ---------- */
    $('#pos-products').on('click', '.pos-prod', function () {
        var id = $(this).data('id');
        var p = products[id];
        if (!p) { return; }
        if (!cart[id]) { cart[id] = { id: p.id, name: p.name, price: p.price, stock: p.stock, qty: 0 }; }
        if (cart[id].qty < cart[id].stock) { cart[id].qty++; }
        else { fncToastr('warning', 'No hay más stock de ' + p.name); }
        renderCart();
    });

    function getDiscount() {
        var $d = $('#pos-discount');
        if (!$d.length) { return 0; }
        var v = parseFloat($d.val());
        return (isNaN(v) || v < 0) ? 0 : v;
    }

    function renderCart() {
        var $c = $('#pos-cart');
        var ids = Object.keys(cart).filter(function (k) { return cart[k].qty > 0; });
        if (!ids.length) {
            $c.html('<p class="text-muted small mb-0">Agrega productos para vender.</p>');
            $('#pos-subtotal').text('$0');
            $('#pos-total').text('$0');
            $('#pos-confirm').prop('disabled', true);
            return;
        }
        var subtotal = 0, rows = '';
        ids.forEach(function (id) {
            var it = cart[id];
            var sub = it.price * it.qty;
            subtotal += sub;
            var badge = it.manual ? ' <span class="badge bg-light text-secondary border" style="font-size:9px">manual</span>' : '';
            rows +=
                '<div class="d-flex align-items-center justify-content-between mb-2 pos-line" data-id="' + esc(id) + '">' +
                    '<div class="me-2 flex-grow-1">' +
                        '<div class="small fw-semibold text-truncate">' + esc(it.name) + badge + '</div>' +
                        '<div class="text-muted" style="font-size:11px">' + money(it.price) + ' c/u</div>' +
                    '</div>' +
                    '<div class="btn-group btn-group-sm me-2">' +
                        '<button type="button" class="btn btn-outline-secondary pos-dec">-</button>' +
                        '<span class="btn btn-light disabled px-2">' + it.qty + '</span>' +
                        '<button type="button" class="btn btn-outline-secondary pos-inc">+</button>' +
                    '</div>' +
                    '<div class="small fw-semibold" style="min-width:64px;text-align:right">' + money(sub) + '</div>' +
                    '<button type="button" class="btn btn-sm btn-link text-danger pos-rm p-0 ms-1"><i class="bi bi-x-lg"></i></button>' +
                '</div>';
        });
        $c.html(rows);
        var discount = Math.min(getDiscount(), subtotal);
        $('#pos-subtotal').text(money(subtotal));
        $('#pos-total').text(money(subtotal - discount));
        $('#pos-confirm').prop('disabled', false);
    }

    $('#pos-cart').on('click', '.pos-inc', function () {
        var id = $(this).closest('.pos-line').data('id');
        var it = cart[id];
        if (it.manual || it.qty < it.stock) { it.qty++; } else { fncToastr('warning', 'Sin más stock disponible.'); }
        renderCart();
    });
    $('#pos-cart').on('click', '.pos-dec', function () {
        var id = $(this).closest('.pos-line').data('id');
        cart[id].qty--;
        if (cart[id].qty <= 0) { delete cart[id]; }
        renderCart();
    });
    $('#pos-cart').on('click', '.pos-rm', function () {
        delete cart[$(this).closest('.pos-line').data('id')];
        renderCart();
    });

    /* ---------- manual items + discount ---------- */
    var manualSeq = 0;
    $('#pos-manual-btn').on('click', function () {
        $('#pos-manual-name').val(''); $('#pos-manual-price').val(0); $('#pos-manual-qty').val(1);
        new bootstrap.Modal(document.getElementById('pos-manual-modal')).show();
    });
    $('#pos-manual-add').on('click', function () {
        var name = $('#pos-manual-name').val().trim();
        var price = parseFloat($('#pos-manual-price').val());
        var qty = parseInt($('#pos-manual-qty').val(), 10);
        if (!name || isNaN(price) || price < 0 || isNaN(qty) || qty < 1) { fncToastr('warning', 'Completa nombre, precio y cantidad válidos.'); return; }
        cart['m' + (++manualSeq)] = { manual: true, name: name, price: price, qty: qty };
        renderCart();
        bootstrap.Modal.getInstance(document.getElementById('pos-manual-modal')).hide();
    });
    $('#pos-app').on('input', '#pos-discount', function () { renderCart(); });

    /* ---------- confirm sale ---------- */
    $('#pos-confirm').on('click', function () {
        var items = Object.keys(cart).filter(function (k) { return cart[k].qty > 0; }).map(function (id) {
            var it = cart[id];
            return it.manual
                ? { manual: true, name: it.name, price: it.price, qty: it.qty }
                : { product_id: parseInt(id, 10), qty: it.qty };
        });
        if (!items.length) { return; }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Procesando…');

        post({ ajax_action: 'create_sale', items: JSON.stringify(items), payment: $('#pos-payment').val(), discount: getDiscount() })
            .done(function (res) {
                $btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i>Confirmar venta');
                if (res && res.success) {
                    showReceipt(res.sale);
                    cart = {};
                    if ($('#pos-discount').length) { $('#pos-discount').val(0); }
                    renderCart();
                    runSearch();
                    fncToastr('success', 'Venta registrada');
                    return;
                }
                if (res && res.error === 'insufficient_stock') {
                    var prod = res.product || {};
                    fncSweetAlert('error', 'Sin stock suficiente de "' + (prod.name || '') + '" (disponible: ' + (prod.available || 0) + ').', '');
                    runSearch();
                    return;
                }
                if (res && res.error === 'discount_not_allowed') {
                    fncSweetAlert('error', 'Tu rol no tiene permiso para aplicar descuentos.', '');
                    return;
                }
                if (res && res.error === 'payment_not_allowed') {
                    fncSweetAlert('error', 'Tu rol no puede usar ese método de pago.', '');
                    return;
                }
                fncSweetAlert('error', (res && res.error) || 'No se pudo registrar la venta.', '');
            })
            .fail(function () {
                $btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i>Confirmar venta');
                fncSweetAlert('error', 'No se pudo contactar al servidor.', '');
            });
    });

    function showReceipt(sale) {
        var rows = sale.items.map(function (i) {
            return '<tr><td>' + esc(i.name) + '</td><td class="text-center">' + i.qty + '</td><td class="text-end">' + money(i.subtotal) + '</td></tr>';
        }).join('');
        var totals = '';
        if (sale.discount && sale.discount > 0) {
            totals =
                '<div class="d-flex justify-content-between small"><span>Subtotal</span><span>' + money(sale.subtotal) + '</span></div>' +
                '<div class="d-flex justify-content-between small text-success"><span>Descuento</span><span>-' + money(sale.discount) + '</span></div>';
        }
        var rc = window.POS_RECEIPT || {};
        var header = '';
        if (rc.logo)     { header += '<div class="text-center mb-2"><img src="' + esc(rc.logo) + '" alt="" style="max-height:54px;max-width:170px"></div>'; }
        if (rc.business) { header += '<div class="text-center fw-bold">' + esc(rc.business) + '</div>'; }
        if (rc.address)  { header += '<div class="text-center small text-muted">' + esc(rc.address) + '</div>'; }
        if (rc.phone)    { header += '<div class="text-center small text-muted">' + esc(rc.phone) + '</div>'; }
        if (header)      { header += '<hr class="my-2">'; }
        var footer = rc.footer ? '<hr class="my-2"><div class="text-center small text-muted">' + esc(rc.footer) + '</div>' : '';

        $('#pos-receipt-body').html(
            header +
            '<div class="small text-muted mb-2">Venta #' + sale.id + (sale.date ? (' · ' + esc(sale.date)) : '') + '<br>Pago: ' + esc(sale.payment) + '</div>' +
            '<table class="table table-sm mb-2"><thead><tr><th>Ítem</th><th class="text-center">Cant</th><th class="text-end">Subtotal</th></tr></thead><tbody>' + rows + '</tbody></table>' +
            totals +
            '<div class="d-flex justify-content-between fw-bold"><span>Total</span><span>' + money(sale.total) + '</span></div>' +
            footer
        );
        new bootstrap.Modal(document.getElementById('pos-receipt-modal')).show();
    }

    /* ================= settings (superadmin) ================= */
    var CFG = {
        product:   { title: 'Productos', fields: [
            ['id', 'ID (clave primaria)', true], ['name', 'Nombre', true], ['price', 'Precio', true], ['stock', 'Stock', true],
            ['image', 'Imagen', false], ['active', 'Activo (1/0)', false], ['category', 'Categoría', false] ] },
        sale:      { title: 'Ventas (cabecera)', fields: [
            ['id', 'ID', true], ['cashier', 'Cajero (id admin)', true], ['total', 'Total', true], ['payment', 'Método de pago', true], ['status', 'Estado', true], ['date', 'Fecha', false], ['discount', 'Descuento', false] ] },
        sale_item: { title: 'Detalle de venta', fields: [
            ['id', 'ID', true], ['sale', 'Venta (FK)', true], ['product', 'Producto (FK)', true], ['qty', 'Cantidad', true], ['unit_price', 'Precio unitario', true], ['subtotal', 'Subtotal', true], ['name', 'Nombre del ítem (ítems manuales)', false] ] }
    };
    var cfgTables = [];
    var cfgPayments = [];
    var cfgCashiers = [];    // [{ id, name, role }]
    var cfgPermissions = {}; // { discount: [ids], payments: { id: [methods] } }

    function colSelect(group, key, label, required) {
        var req = required ? ' <span class="text-danger">*</span>' : ' <span class="text-muted">(opcional)</span>';
        return '<div class="col-md-4 mb-2"><label class="form-label small mb-1">' + esc(label) + req + '</label>' +
               '<select class="form-select form-select-sm pos-cfg-col" id="cfg-' + group + '-' + key + '"><option value="">—</option></select></div>';
    }

    function buildGroup(group) {
        var def = CFG[group];
        var html = '<div class="card border-0 bg-light"><div class="card-body">' +
            '<h6 class="text-muted mb-2"><i class="bi bi-table me-1"></i>' + esc(def.title) + '</h6>' +
            '<label class="form-label small mb-1">Tabla</label>' +
            '<select class="form-select form-select-sm pos-cfg-table mb-2" id="cfg-' + group + '-table" data-group="' + group + '"><option value="">— Elige tabla —</option></select>' +
            '<div class="row">';
        def.fields.forEach(function (f) { html += colSelect(group, f[0], f[1], f[2]); });
        html += '</div></div></div>';
        $('#pos-cfg-' + group).html(html);
    }

    function fillTableOptions(cfg) {
        ['product', 'sale', 'sale_item'].forEach(function (group) {
            var cur = (cfg[group] || {}).table || '';
            var $t = $('#cfg-' + group + '-table');
            cfgTables.forEach(function (t) { $t.append('<option value="' + esc(t) + '"' + (t === cur ? ' selected' : '') + '>' + esc(t) + '</option>'); });
        });
    }

    function loadColumns(group, table, currentMap) {
        if (!table) { $('#pos-cfg-' + group + ' .pos-cfg-col').html('<option value="">—</option>'); return; }
        post({ ajax_action: 'get_columns', table: table }).done(function (res) {
            var cols = (res && res.columns) || [];
            CFG[group].fields.forEach(function (f) {
                var $s = $('#cfg-' + group + '-' + f[0]);
                var cur = (currentMap && currentMap[f[0]]) || '';
                $s.html('<option value="">—</option>');
                cols.forEach(function (c) { $s.append('<option value="' + esc(c) + '"' + (c === cur ? ' selected' : '') + '>' + esc(c) + '</option>'); });
            });
        });
    }

    function renderPayments() {
        var $p = $('#pos-cfg-payments').empty();
        if (!cfgPayments.length) { $p.html('<span class="text-muted small">Sin métodos.</span>'); return; }
        cfgPayments.forEach(function (m, i) {
            $p.append('<span class="badge bg-secondary d-inline-flex align-items-center gap-1">' + esc(m) +
                ' <i class="bi bi-x-lg pos-cfg-pay-rm" style="cursor:pointer" data-i="' + i + '"></i></span>');
        });
    }

    /* ---------- permissions (per cashier: discount + payment matrix) ---------- */
    function cashierCanDiscount(id) {
        if (!cfgPermissions.discount) { return true; }            // not configured = all
        return cfgPermissions.discount.map(String).indexOf(String(id)) !== -1;
    }
    function cashierAllowsPayment(id, m) {
        var pm = cfgPermissions.payments && cfgPermissions.payments[String(id)];
        if (!pm) { return true; }                                 // not restricted = all
        return pm.indexOf(m) !== -1;
    }

    function renderPermissions() {
        if (!cfgCashiers.length) {
            $('#pos-cfg-permissions').html('<span class="text-muted small">No hay cajeros (admins con un rol permitido) para configurar.</span>');
            return;
        }
        var html = '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr>' +
            '<th class="small">Cajero</th><th class="small text-center">Descuento</th>';
        cfgPayments.forEach(function (m) { html += '<th class="small text-center">' + esc(m) + '</th>'; });
        html += '</tr></thead><tbody>';
        cfgCashiers.forEach(function (c) {
            html += '<tr><td class="small fw-semibold">' + esc(c.name) + ' <span class="text-muted fw-normal">(' + esc(c.role) + ')</span></td>' +
                '<td class="text-center"><input type="checkbox" class="form-check-input perm-disc" data-id="' + c.id + '"' + (cashierCanDiscount(c.id) ? ' checked' : '') + '></td>';
            cfgPayments.forEach(function (m) {
                html += '<td class="text-center"><input type="checkbox" class="form-check-input perm-pay" data-id="' + c.id + '" data-method="' + esc(m) + '"' + (cashierAllowsPayment(c.id, m) ? ' checked' : '') + '></td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        $('#pos-cfg-permissions').html(html);
    }

    // Read the current permission checkboxes back into cfgPermissions (so toggling
    // payment methods can re-render the matrix without losing in-progress edits).
    function syncPermissionsFromUI() {
        if (!$('#pos-cfg-permissions .perm-disc').length) { return; }
        cfgPermissions.discount = cfgCashiers.filter(function (c) {
            return $('.perm-disc[data-id="' + c.id + '"]').is(':checked');
        }).map(function (c) { return String(c.id); });
        cfgPermissions.payments = {};
        cfgCashiers.forEach(function (c) {
            var allowed = cfgPayments.filter(function (m) {
                var $cb = $('.perm-pay[data-id="' + c.id + '"][data-method="' + m + '"]');
                return $cb.length ? $cb.is(':checked') : true; // a brand-new method defaults to allowed
            });
            if (allowed.length !== cfgPayments.length) { cfgPermissions.payments[String(c.id)] = allowed; }
        });
    }

    function openSettings() {
        $('#pos-cfg-msg').html('');
        buildGroup('product'); buildGroup('sale'); buildGroup('sale_item');
        post({ ajax_action: 'get_settings' }).done(function (res) {
            if (!res || !res.success) { $('#pos-cfg-msg').html('<span class="text-danger">No se pudo cargar la configuración.</span>'); return; }
            cfgTables = res.tables || [];
            var cfg = res.config || {};
            fillTableOptions(cfg);
            cfgPayments = (cfg.payment_methods && cfg.payment_methods.length) ? cfg.payment_methods.slice() : ['efectivo', 'tarjeta'];
            cfgCashiers = res.cashiers || [];
            cfgPermissions = (cfg.permissions && typeof cfg.permissions === 'object') ? cfg.permissions : {};
            var rc = cfg.receipt || {};
            $('#pos-rcpt-business').val(rc.business || '');
            $('#pos-rcpt-phone').val(rc.phone || '');
            $('#pos-rcpt-address').val(rc.address || '');
            $('#pos-rcpt-logo').val(rc.logo || '');
            $('#pos-rcpt-footer').val(rc.footer || '');
            renderPayments();
            renderPermissions();
            ['product', 'sale', 'sale_item'].forEach(function (group) {
                var t = (cfg[group] || {}).table || '';
                if (t) { loadColumns(group, t, cfg[group]); }
            });
        });
        new bootstrap.Modal(document.getElementById('pos-settings-modal')).show();
    }

    $('#pos-settings-btn').on('click', openSettings);
    $('#pos-settings-modal').on('change', '.pos-cfg-table', function () {
        loadColumns($(this).data('group'), $(this).val(), null);
    });
    $('#pos-cfg-pay-add').on('click', function () {
        var v = $('#pos-cfg-pay-new').val().trim().toLowerCase();
        if (v && cfgPayments.indexOf(v) === -1) {
            syncPermissionsFromUI();
            cfgPayments.push(v);
            renderPayments(); renderPermissions();
        }
        $('#pos-cfg-pay-new').val('');
    });
    $('#pos-cfg-payments').on('click', '.pos-cfg-pay-rm', function () {
        syncPermissionsFromUI();
        cfgPayments.splice($(this).data('i'), 1);
        renderPayments(); renderPermissions();
    });

    $('#pos-cfg-save').on('click', function () {
        var cfg = { payment_methods: cfgPayments }, missing = [];
        ['product', 'sale', 'sale_item'].forEach(function (group) {
            cfg[group] = { table: $('#cfg-' + group + '-table').val() };
            if (!cfg[group].table) { missing.push(CFG[group].title + ': tabla'); }
            CFG[group].fields.forEach(function (f) {
                var val = $('#cfg-' + group + '-' + f[0]).val();
                if (val) { cfg[group][f[0]] = val; }
                else if (f[2]) { missing.push(CFG[group].title + ': ' + f[1]); }
            });
        });
        if (missing.length) { $('#pos-cfg-msg').html('<span class="text-danger">Faltan campos obligatorios — ' + esc(missing.join(', ')) + '</span>'); return; }
        syncPermissionsFromUI();
        cfg.permissions = cfgPermissions;
        cfg.receipt = {
            business: ($('#pos-rcpt-business').val() || '').trim(),
            phone:    ($('#pos-rcpt-phone').val()    || '').trim(),
            address:  ($('#pos-rcpt-address').val()  || '').trim(),
            logo:     ($('#pos-rcpt-logo').val()     || '').trim(),
            footer:   ($('#pos-rcpt-footer').val()   || '').trim()
        };
        var $b = $(this);
        $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Guardando…');
        post({ ajax_action: 'save_settings', config: JSON.stringify(cfg) }).done(function (res) {
            $b.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar configuración');
            if (res && res.success) { fncToastr('success', 'Configuración guardada'); setTimeout(function () { location.reload(); }, 800); }
            else { $('#pos-cfg-msg').html('<span class="text-danger">' + esc((res && res.error) || 'Error') + '</span>'); }
        }).fail(function () {
            $b.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar configuración');
            $('#pos-cfg-msg').html('<span class="text-danger">Error de conexión.</span>');
        });
    });

    // Defer the first POST to DOM-ready so the CSRF interceptor is active.
    $(function () { if ($('#pos-search').length) { runSearch(); } });

})();
