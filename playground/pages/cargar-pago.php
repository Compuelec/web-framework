<?php
/**
 * Cargar pago — registra un pago efectuado a un proveedor + genera el
 * asiento contable (D Proveedores / H Caja|Banco) en una sola transacción.
 *
 * Diseño:
 *  - El usuario elige proveedor → JS llena la lista de compras pendientes
 *    de ese proveedor (compras sin asiento de pago o con pago parcial).
 *  - El monto del pago puede ser TOTAL (paga la deuda completa) o PARCIAL.
 *    El sistema computa "saldo pendiente" = total_compra − Σ pagos previos.
 *  - Si el pago es total, marca la compra como 'pagado' automáticamente.
 *  - Si es parcial, deja la compra en 'registrado' y la siguiente vez
 *    aparece con su saldo actualizado.
 *
 * Source-of-truth: la tabla `pagos`. El `estado_compra` se actualiza como
 * derivado conveniente (para los reportes), pero quien decide cuánto se
 * pagó es la suma de pagos no anulados.
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
require_once __DIR__ . '/_lib/asientos.php';
wpb_require_role(['contador']);
$db = Connection::connect();

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

/**
 * Returns the outstanding balance of a compra (total - sum of registered
 * pagos). Null if the compra doesn't exist.
 */
function saldoPendiente(PDO $db, int $compraId): ?float {
    $stmt = $db->prepare(
        "SELECT c.total_compra
                - COALESCE(
                    (SELECT SUM(p.monto_pago) FROM pagos p
                     WHERE p.compra_pago = c.id_compra AND p.estado_pago = 'registrado'),
                    0
                  ) AS saldo
         FROM comprobantes_compra c
         WHERE c.id_compra = :id LIMIT 1"
    );
    $stmt->execute([':id' => $compraId]);
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

    $proveedor = (int)($_POST['proveedor'] ?? 0);
    $compra    = (int)($_POST['compra']    ?? 0);
    $fecha     = trim($_POST['fecha'] ?? '');
    $medio     = trim($_POST['medio'] ?? '');
    $monto     = (float)str_replace(['.', ','], ['', '.'], (string)($_POST['monto'] ?? '0'));
    $glosa     = trim($_POST['glosa'] ?? '');

    $errors = [];
    if ($proveedor <= 0) { $errors[] = 'Elegí un proveedor.'; }
    if ($compra    <= 0) { $errors[] = 'Elegí la compra que estás pagando.'; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { $errors[] = 'Fecha inválida.'; }
    if (!in_array($medio, ['caja','banco'], true))    { $errors[] = 'Medio de pago inválido.'; }
    if ($monto <= 0)     { $errors[] = 'El monto debe ser mayor que cero.'; }

    // No permitir pagar más que el saldo pendiente.
    if (!$errors) {
        $saldo = saldoPendiente($db, $compra);
        if ($saldo === null) {
            $errors[] = 'La compra #' . $compra . ' no existe.';
        } elseif ($monto > $saldo + 0.01) {
            $errors[] = sprintf('El monto (%s) excede el saldo pendiente de la compra (%s).',
                                pesos($monto), pesos($saldo));
        }
    }

    if (!$errors) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO pagos
                    (fecha_pago, compra_pago, proveedor_pago, medio_pago, monto_pago,
                     glosa_pago, estado_pago, date_created_pago)
                 VALUES (:f, :cp, :pr, :m, :mt, :g, 'registrado', CURDATE())"
            );
            $stmt->execute([
                ':f'  => $fecha,
                ':cp' => $compra,
                ':pr' => $proveedor,
                ':m'  => $medio,
                ':mt' => $monto,
                ':g'  => $glosa,
            ]);
            $pagoId = (int)$db->lastInsertId();

            $compiled = compileAsientoPago($db, [
                'compra_pago' => $compra,
                'medio_pago'  => $medio,
                'monto_pago'  => $monto,
            ]);
            if (isset($compiled['error'])) { throw new RuntimeException($compiled['error']); }
            $res = insertarAsiento($db, 'pago', $pagoId, $fecha, $compiled['glosa'], $compiled['lineas']);
            if (isset($res['error']))      { throw new RuntimeException($res['error']); }

            // Si el pago cubre el saldo restante, marca la compra como 'pagado'.
            $saldoTrasPago = $saldo - $monto;
            if ($saldoTrasPago < 0.01) {
                $u = $db->prepare("UPDATE comprobantes_compra SET estado_compra = 'pagado' WHERE id_compra = :id");
                $u->execute([':id' => $compra]);
            }

            $db->commit();
            $flash = ['type' => 'success',
                'msg' => sprintf('Pago #%d registrado (%s). Asiento N° %d generado.%s',
                                 $pagoId, pesos($monto), $res['numero'],
                                 $saldoTrasPago < 0.01 ? ' Compra marcada como pagada.' : sprintf(' Saldo restante: %s.', pesos($saldoTrasPago)))];
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

$proveedores = [];
$comprasPorProveedor = []; // { proveedor_id => [ { id, folio, fecha, total, saldo } ] }
if ($db) {
    try {
        $proveedores = $db->query(
            "SELECT id_proveedor, razon_social_proveedor, rut_proveedor
             FROM proveedores
             ORDER BY razon_social_proveedor ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Eager-load todas las compras con saldo > 0 (en una sola query). El JS
        // filtra por proveedor al cambiar el dropdown.
        $stmt = $db->query(
            "SELECT c.id_compra, c.proveedor_compra, c.folio_compra, c.fecha_compra,
                    c.total_compra,
                    c.tipo_documento_compra,
                    c.total_compra
                      - COALESCE((SELECT SUM(p.monto_pago) FROM pagos p
                                  WHERE p.compra_pago = c.id_compra AND p.estado_pago = 'registrado'), 0)
                      AS saldo
             FROM comprobantes_compra c
             WHERE c.estado_compra != 'anulado'
             HAVING saldo > 0
             ORDER BY c.fecha_compra ASC, c.id_compra ASC"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $comprasPorProveedor[(int)$r['proveedor_compra']][] = $r;
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
<title>Cargar pago — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .form-card { max-width: 760px; margin: 0 auto; }
    .compras-table { font-size: .9rem; }
    .compras-table th, .compras-table td { padding: .35rem .6rem; }
    .compras-table input[type="radio"] { cursor: pointer; }
    .compras-table tr:hover { background: #f8f9fa; cursor: pointer; }
    .compras-table .selected { background: #e7f5ee !important; }
    .compras-empty { color: #6c757d; padding: 1rem; text-align: center; }
    .hint { color: #6c757d; font-size: .85rem; }
    .saldo-display {
        background: #fff3cd; border-radius: 6px; padding: .5rem .75rem;
        font-weight: 600; color: #664d03; text-align: right; font-size: .9rem;
    }
</style>
</head>
<body>
<?= wpb_render_user_bar() ?>
<div class="container py-4">
    <div class="form-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">Cargar pago a proveedor</h1>
                <p class="text-muted mb-0">Registra el pago de una factura y genera el asiento doble partida.</p>
            </div>
            <a href="/dashboard-contable" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif; ?>

        <form method="post" id="form-pago">
            <?= wpb_csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="proveedor">Proveedor</label>
                <select class="form-select" id="proveedor" name="proveedor" required>
                    <option value="">— elegí uno —</option>
                    <?php $provSel = (int)($valoresPrev['proveedor'] ?? 0); foreach ($proveedores as $p): ?>
                        <option value="<?= (int)$p['id_proveedor'] ?>" <?= $provSel === (int)$p['id_proveedor'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['razon_social_proveedor']) ?>
                            <?php if ($p['rut_proveedor']): ?> · <?= htmlspecialchars($p['rut_proveedor']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Solo aparecen proveedores con compras pendientes de pago.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Compra a pagar</label>
                <div id="compras-container" class="border rounded">
                    <div class="compras-empty">Elegí un proveedor para ver sus compras pendientes.</div>
                </div>
                <input type="hidden" id="compra" name="compra" value="<?= (int)($valoresPrev['compra'] ?? 0) ?>">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="fecha">Fecha del pago</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" required
                           value="<?= htmlspecialchars($valoresPrev['fecha'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="medio">Medio de pago</label>
                    <select class="form-select" id="medio" name="medio" required>
                        <option value="">— elegí uno —</option>
                        <option value="caja"  <?= ($valoresPrev['medio'] ?? '') === 'caja'  ? 'selected' : '' ?>>Caja (efectivo)</option>
                        <option value="banco" <?= ($valoresPrev['medio'] ?? '') === 'banco' ? 'selected' : '' ?>>Banco (transferencia)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="monto">Monto</label>
                    <input type="number" class="form-control" id="monto" name="monto" step="1" min="1" required
                           value="<?= htmlspecialchars($valoresPrev['monto'] ?? '') ?>">
                    <div id="saldo-info" class="hint">elegí una compra primero</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="glosa">Glosa / referencia (opcional)</label>
                <input type="text" class="form-control" id="glosa" name="glosa"
                       placeholder="Ej. transferencia ref. 12345"
                       value="<?= htmlspecialchars($valoresPrev['glosa'] ?? '') ?>">
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" id="btn-submit" disabled>Registrar pago y generar asiento</button>
                <a href="/cargar-pago" class="btn btn-outline-secondary">Limpiar</a>
                <a href="/libro-compras" class="btn btn-link ms-auto">Ver libro de compras →</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // Server-side serialized snapshot of pending compras grouped by proveedor.
    // The JS just filters this map when the proveedor select changes.
    var COMPRAS = <?= json_encode($comprasPorProveedor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var $proveedor = document.getElementById('proveedor');
    var $compraId  = document.getElementById('compra');
    var $monto     = document.getElementById('monto');
    var $saldoInfo = document.getElementById('saldo-info');
    var $container = document.getElementById('compras-container');
    var $submit    = document.getElementById('btn-submit');
    var saldoSeleccionado = null;

    function pesos(n) { return '$ ' + (n||0).toLocaleString('es-CL'); }

    function updateSubmitState() {
        var validCompra = $compraId.value && saldoSeleccionado !== null;
        var validMonto  = parseFloat($monto.value) > 0
                       && saldoSeleccionado !== null
                       && parseFloat($monto.value) <= saldoSeleccionado + 0.01;
        $submit.disabled = !(validCompra && validMonto);
    }

    function selectCompra(c) {
        $compraId.value = String(c.id_compra);
        saldoSeleccionado = parseFloat(c.saldo);
        $monto.value = Math.round(saldoSeleccionado); // default: pago total
        $saldoInfo.innerHTML = 'Saldo pendiente: <strong>' + pesos(saldoSeleccionado) + '</strong>'
                             + ' · podés cargar un monto menor para pagar parcial.';
        // Highlight in table
        $container.querySelectorAll('tr.selected').forEach(function (r) { r.classList.remove('selected'); });
        var row = $container.querySelector('tr[data-id="' + c.id_compra + '"]');
        if (row) { row.classList.add('selected'); }
        updateSubmitState();
    }

    function renderCompras(provId) {
        var compras = COMPRAS[provId] || [];
        $compraId.value = '';
        saldoSeleccionado = null;
        $monto.value = '';
        $saldoInfo.textContent = 'elegí una compra primero';
        updateSubmitState();

        if (!compras.length) {
            $container.innerHTML = '<div class="compras-empty">' +
                (provId ? 'Este proveedor no tiene compras pendientes.' : 'Elegí un proveedor para ver sus compras pendientes.') +
                '</div>';
            return;
        }
        var html = '<table class="table compras-table mb-0">' +
            '<thead><tr><th></th><th>Fecha</th><th>Folio</th><th>Tipo</th>' +
            '<th class="text-end">Total</th><th class="text-end">Saldo</th></tr></thead><tbody>';
        compras.forEach(function (c) {
            html += '<tr data-id="' + c.id_compra + '">' +
                '<td><input type="radio" name="compra_pick" value="' + c.id_compra + '"></td>' +
                '<td>' + c.fecha_compra + '</td>' +
                '<td>' + c.folio_compra + '</td>' +
                '<td>' + c.tipo_documento_compra + '</td>' +
                '<td class="text-end">' + pesos(parseFloat(c.total_compra)) + '</td>' +
                '<td class="text-end"><strong>' + pesos(parseFloat(c.saldo)) + '</strong></td>' +
                '</tr>';
        });
        html += '</tbody></table>';
        $container.innerHTML = html;

        // Click a row → select that compra
        $container.querySelectorAll('tr[data-id]').forEach(function (row) {
            row.addEventListener('click', function () {
                var id = parseInt(row.dataset.id, 10);
                var compra = compras.find(function (x) { return parseInt(x.id_compra, 10) === id; });
                if (compra) {
                    row.querySelector('input[type="radio"]').checked = true;
                    selectCompra(compra);
                }
            });
        });
    }

    $proveedor.addEventListener('change', function () {
        renderCompras(parseInt(this.value, 10) || 0);
    });
    $monto.addEventListener('input', updateSubmitState);

    // Initial render: if the form came back with a proveedor selected (POST
    // error path), render its compras.
    if ($proveedor.value) {
        renderCompras(parseInt($proveedor.value, 10));
    }
})();
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
