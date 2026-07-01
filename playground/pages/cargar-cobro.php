<?php
/**
 * Cargar cobro — registra un cobro recibido de un cliente + genera el
 * asiento contable (D Caja|Banco / H Clientes) en una sola transacción.
 *
 * Espejo simétrico de cargar-pago: misma UX, misma lógica de saldo
 * pendiente (total_venta − Σ cobros registrados), pero del otro lado del
 * negocio (la plata entra, no sale).
 *
 * Si el cobro total cubre la deuda, se marca la venta como 'pagado'.
 * Si es parcial, queda con 'registrado' y la siguiente vez aparece con su
 * saldo actualizado.
 */

require_once __DIR__ . '/../config.php';
$config   = require __DIR__ . '/../config.php';
$siteCfg  = is_array($config) ? $config : [];
$siteName = $siteCfg['site']['name'] ?? 'Contabilidad';

if (!empty($siteCfg['timezone'])) {
    date_default_timezone_set($siteCfg['timezone']);
}

require_once __DIR__ . '/../../api/models/connection.php';
require_once __DIR__ . '/_lib/auth.php';
require_once __DIR__ . '/_lib/cierres.php';
require_once __DIR__ . '/_lib/asientos.php';
wpb_require_role(['contador']);
$db = Connection::connect();

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

/**
 * Saldo pendiente de una venta: total_venta − Σ cobros registrados.
 * Null si la venta no existe.
 */
function saldoVentaPendiente(PDO $db, int $ventaId): ?float {
    $stmt = $db->prepare(
        "SELECT v.total_venta
                - COALESCE(
                    (SELECT SUM(co.monto_cobro) FROM cobros co
                     WHERE co.venta_cobro = v.id_venta AND co.estado_cobro = 'registrado'),
                    0
                  ) AS saldo
         FROM comprobantes_venta v
         WHERE v.id_venta = :id LIMIT 1"
    );
    $stmt->execute([':id' => $ventaId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (float)$row['saldo'] : null;
}

/* =========================================================================
   Acción: guardar
   ========================================================================= */

$flash       = null;
$valoresPrev = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    wpb_csrf_check();

    $cliente = (int)($_POST['cliente'] ?? 0);
    $venta   = (int)($_POST['venta']   ?? 0);
    $fecha   = trim($_POST['fecha'] ?? '');
    $medio   = trim($_POST['medio'] ?? '');
    $monto   = (float)str_replace(['.', ','], ['', '.'], (string)($_POST['monto'] ?? '0'));
    $glosa   = trim($_POST['glosa'] ?? '');

    $errors = [];
    if ($cliente <= 0) { $errors[] = 'Elegí un cliente.'; }
    if ($venta   <= 0) { $errors[] = 'Elegí la venta que estás cobrando.'; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { $errors[] = 'Fecha inválida.'; }
    if (!in_array($medio, ['caja','banco','payku'], true)) { $errors[] = 'Medio de cobro inválido.'; }

    if (mes_esta_cerrado($db, $fecha)) {
        $errors[] = 'El mes de esa fecha está cerrado. Para cargar acá, primero reabrí el mes desde /cierre-mes.';
    }
    if ($monto <= 0) { $errors[] = 'El monto debe ser mayor que cero.'; }

    if (!$errors) {
        $saldo = saldoVentaPendiente($db, $venta);
        if ($saldo === null) {
            $errors[] = 'La venta #' . $venta . ' no existe.';
        } elseif ($monto > $saldo + 0.01) {
            $errors[] = sprintf('El monto (%s) excede el saldo pendiente de la venta (%s).',
                                pesos($monto), pesos($saldo));
        }
    }

    if (!$errors) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO cobros
                    (fecha_cobro, venta_cobro, cliente_cobro, medio_cobro, monto_cobro,
                     glosa_cobro, estado_cobro, date_created_cobro)
                 VALUES (:f, :v, :c, :m, :mt, :g, 'registrado', CURDATE())"
            );
            $stmt->execute([
                ':f'  => $fecha,
                ':v'  => $venta,
                ':c'  => $cliente,
                ':m'  => $medio,
                ':mt' => $monto,
                ':g'  => $glosa,
            ]);
            $cobroId = (int)$db->lastInsertId();

            $compiled = compileAsientoCobro($db, [
                'venta_cobro' => $venta,
                'medio_cobro' => $medio,
                'monto_cobro' => $monto,
            ]);
            if (isset($compiled['error'])) { throw new RuntimeException($compiled['error']); }
            $res = insertarAsiento($db, 'cobro', $cobroId, $fecha, $compiled['glosa'], $compiled['lineas']);
            if (isset($res['error']))      { throw new RuntimeException($res['error']); }

            // Si el cobro cubre el saldo, marca la venta como 'pagado'.
            $saldoTrasCobro = $saldo - $monto;
            if ($saldoTrasCobro < 0.01) {
                $u = $db->prepare("UPDATE comprobantes_venta SET estado_venta = 'pagado' WHERE id_venta = :id");
                $u->execute([':id' => $venta]);
            }

            $db->commit();
            $flash = ['type' => 'success',
                'msg' => sprintf('Cobro #%d registrado (%s). Asiento N° %d generado.%s',
                                 $cobroId, pesos($monto), $res['numero'],
                                 $saldoTrasCobro < 0.01 ? ' Venta marcada como pagada.' : sprintf(' Saldo restante: %s.', pesos($saldoTrasCobro)))];
            $valoresPrev = [];
        } catch (Throwable $e) {
            $db->rollBack();
            $flash = ['type' => 'danger', 'msg' => 'No se pudo guardar: ' . $e->getMessage()];
        }
    } else {
        $flash = ['type' => 'danger', 'msg' => 'Revisá los campos: ' . implode(' ', $errors)];
    }
}

/* =========================================================================
   Datos para el form
   ========================================================================= */

$clientes = [];
$ventasPorCliente = []; // { cliente_id => [ { id, folio, fecha, total, saldo } ] }
if ($db) {
    try {
        $clientes = $db->query(
            "SELECT id_cliente, razon_social_cliente, rut_cliente
             FROM clientes
             ORDER BY razon_social_cliente ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->query(
            "SELECT v.id_venta, v.cliente_venta, v.folio_venta, v.fecha_venta,
                    v.total_venta, v.tipo_documento_venta,
                    v.total_venta
                      - COALESCE((SELECT SUM(co.monto_cobro) FROM cobros co
                                  WHERE co.venta_cobro = v.id_venta AND co.estado_cobro = 'registrado'), 0)
                      AS saldo
             FROM comprobantes_venta v
             WHERE v.estado_venta != 'anulado'
             HAVING saldo > 0
             ORDER BY v.fecha_venta ASC, v.id_venta ASC"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ventasPorCliente[(int)$r['cliente_venta']][] = $r;
        }
    } catch (Throwable $e) {}
}

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cargar cobro — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .form-card { max-width: 760px; margin: 0 auto; }
    .ventas-table { font-size: .9rem; }
    .ventas-table th, .ventas-table td { padding: .35rem .6rem; }
    .ventas-table input[type="radio"] { cursor: pointer; }
    .ventas-table tr:hover { background: #f8f9fa; cursor: pointer; }
    .ventas-table .selected { background: #e7f5ee !important; }
    .ventas-empty { color: #6c757d; padding: 1rem; text-align: center; }
    .hint { color: #6c757d; font-size: .85rem; }
</style>
</head>
<body>
<?= wpb_render_user_bar() ?>
<div class="container py-4">
    <div class="form-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">Cargar cobro de cliente</h1>
                <p class="text-muted mb-0">Registra el pago recibido por una factura/boleta y genera el asiento doble partida.</p>
            </div>
            <a href="/dashboard-contable" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif; ?>

        <form method="post" id="form-cobro">
            <?= wpb_csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="cliente">Cliente</label>
                <select class="form-select" id="cliente" name="cliente" required>
                    <option value="">— elegí uno —</option>
                    <?php $cliSel = (int)($valoresPrev['cliente'] ?? 0); foreach ($clientes as $c): ?>
                        <option value="<?= (int)$c['id_cliente'] ?>" <?= $cliSel === (int)$c['id_cliente'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['razon_social_cliente']) ?>
                            <?php if ($c['rut_cliente']): ?> · <?= htmlspecialchars($c['rut_cliente']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Solo aparecen clientes con ventas pendientes de cobro.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Venta a cobrar</label>
                <div id="ventas-container" class="border rounded">
                    <div class="ventas-empty">Elegí un cliente para ver sus ventas pendientes.</div>
                </div>
                <input type="hidden" id="venta" name="venta" value="<?= (int)($valoresPrev['venta'] ?? 0) ?>">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="fecha">Fecha del cobro</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" required
                           value="<?= htmlspecialchars($valoresPrev['fecha'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="medio">Medio de cobro</label>
                    <select class="form-select" id="medio" name="medio" required>
                        <option value="">— elegí uno —</option>
                        <option value="caja"  <?= ($valoresPrev['medio'] ?? '') === 'caja'  ? 'selected' : '' ?>>Caja (efectivo)</option>
                        <option value="banco" <?= ($valoresPrev['medio'] ?? '') === 'banco' ? 'selected' : '' ?>>Banco (transferencia)</option>
                        <option value="payku" <?= ($valoresPrev['medio'] ?? '') === 'payku' ? 'selected' : '' ?>>Payku (pago online)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="monto">Monto</label>
                    <input type="number" class="form-control" id="monto" name="monto" step="1" min="1" required
                           value="<?= htmlspecialchars($valoresPrev['monto'] ?? '') ?>">
                    <div id="saldo-info" class="hint">elegí una venta primero</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="glosa">Glosa / referencia (opcional)</label>
                <input type="text" class="form-control" id="glosa" name="glosa"
                       placeholder="Ej. transferencia ref. 87654 / Order Payku #..."
                       value="<?= htmlspecialchars($valoresPrev['glosa'] ?? '') ?>">
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" id="btn-submit" disabled>Registrar cobro y generar asiento</button>
                <a href="/cargar-cobro" class="btn btn-outline-secondary">Limpiar</a>
                <a href="/libro-ventas" class="btn btn-link ms-auto">Ver libro de ventas →</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var VENTAS = <?= json_encode($ventasPorCliente, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var $cliente   = document.getElementById('cliente');
    var $ventaId   = document.getElementById('venta');
    var $monto     = document.getElementById('monto');
    var $saldoInfo = document.getElementById('saldo-info');
    var $container = document.getElementById('ventas-container');
    var $submit    = document.getElementById('btn-submit');
    var saldoSeleccionado = null;

    function pesos(n) { return '$ ' + (n||0).toLocaleString('es-CL'); }

    function updateSubmitState() {
        var validVenta = $ventaId.value && saldoSeleccionado !== null;
        var validMonto = parseFloat($monto.value) > 0
                      && saldoSeleccionado !== null
                      && parseFloat($monto.value) <= saldoSeleccionado + 0.01;
        $submit.disabled = !(validVenta && validMonto);
    }

    function selectVenta(v) {
        $ventaId.value = String(v.id_venta);
        saldoSeleccionado = parseFloat(v.saldo);
        $monto.value = Math.round(saldoSeleccionado);
        $saldoInfo.innerHTML = 'Saldo pendiente: <strong>' + pesos(saldoSeleccionado) + '</strong>'
                             + ' · podés cargar un monto menor para cobrar parcial.';
        $container.querySelectorAll('tr.selected').forEach(function (r) { r.classList.remove('selected'); });
        var row = $container.querySelector('tr[data-id="' + v.id_venta + '"]');
        if (row) { row.classList.add('selected'); }
        updateSubmitState();
    }

    function renderVentas(cliId) {
        var ventas = VENTAS[cliId] || [];
        $ventaId.value = '';
        saldoSeleccionado = null;
        $monto.value = '';
        $saldoInfo.textContent = 'elegí una venta primero';
        updateSubmitState();

        if (!ventas.length) {
            $container.innerHTML = '<div class="ventas-empty">' +
                (cliId ? 'Este cliente no tiene ventas pendientes.' : 'Elegí un cliente para ver sus ventas pendientes.') +
                '</div>';
            return;
        }
        var html = '<table class="table ventas-table mb-0">' +
            '<thead><tr><th></th><th>Fecha</th><th>Folio</th><th>Tipo</th>' +
            '<th class="text-end">Total</th><th class="text-end">Saldo</th></tr></thead><tbody>';
        ventas.forEach(function (v) {
            html += '<tr data-id="' + v.id_venta + '">' +
                '<td><input type="radio" name="venta_pick" value="' + v.id_venta + '"></td>' +
                '<td>' + v.fecha_venta + '</td>' +
                '<td>' + v.folio_venta + '</td>' +
                '<td>' + v.tipo_documento_venta + '</td>' +
                '<td class="text-end">' + pesos(parseFloat(v.total_venta)) + '</td>' +
                '<td class="text-end"><strong>' + pesos(parseFloat(v.saldo)) + '</strong></td>' +
                '</tr>';
        });
        html += '</tbody></table>';
        $container.innerHTML = html;

        $container.querySelectorAll('tr[data-id]').forEach(function (row) {
            row.addEventListener('click', function () {
                var id = parseInt(row.dataset.id, 10);
                var venta = ventas.find(function (x) { return parseInt(x.id_venta, 10) === id; });
                if (venta) {
                    row.querySelector('input[type="radio"]').checked = true;
                    selectVenta(venta);
                }
            });
        });
    }

    $cliente.addEventListener('change', function () {
        renderVentas(parseInt(this.value, 10) || 0);
    });
    $monto.addEventListener('input', updateSubmitState);

    if ($cliente.value) {
        renderVentas(parseInt($cliente.value, 10));
    }
})();
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
