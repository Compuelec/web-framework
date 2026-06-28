<?php
/**
 * Generar asientos pendientes — toma comprobantes de venta/compra que aún no
 * tienen un asiento contable asociado y los lista con un botón para generarlo.
 *
 * Diseño:
 *  - Sin login (corre en /web/ como el resto). En producción esto debería ser
 *    una página privada — la doc dice que `private: true` lo activa via
 *    create_page; lo agregamos en una iteración futura cuando definamos roles.
 *  - El POST `?accion=generar&tipo=venta&id=N` ejecuta la lógica y redirige
 *    de vuelta para evitar reenvíos.
 *  - La lógica contable está en helpers locales (compileAsientoVenta /
 *    compileAsientoCompra) para que sean fáciles de testear y leer.
 *
 * Lógica de los asientos (chilena básica):
 *  Venta afecta (factura/boleta con IVA):
 *      D  Clientes              total
 *         H  Ventas afectas        neto
 *         H  IVA Débito Fiscal     iva
 *  Venta exenta:
 *      D  Clientes              total
 *         H  Ventas exentas        exento
 *  Compra afecta:
 *      D  <Cuenta de la categoría>  neto
 *      D  IVA Crédito Fiscal        iva
 *         H  Proveedores              total
 *  Compra exenta o boleta de honorarios (sin IVA crédito fiscal):
 *      D  <Cuenta de la categoría>  total
 *         H  Proveedores              total
 *
 * Validación: si Σ Debe ≠ Σ Haber, el INSERT no se ejecuta y se muestra error.
 */

require_once __DIR__ . '/../config.php';

$config  = require __DIR__ . '/../config.php';
$siteCfg = is_array($config) ? $config : [];
$siteName = $siteCfg['site']['name'] ?? 'Contabilidad';

if (!empty($siteCfg['timezone'])) {
    date_default_timezone_set($siteCfg['timezone']);
}

require_once __DIR__ . '/../../api/models/connection.php';
$db = Connection::connect();

/* =========================================================================
   Helpers
   ========================================================================= */

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

function cuentaPorCodigo(PDO $db, string $codigo): ?array {
    $stmt = $db->prepare("SELECT id_cuenta, nombre_cuenta FROM plan_cuentas WHERE codigo_cuenta = :c LIMIT 1");
    $stmt->execute([':c' => $codigo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Devuelve el siguiente número de asiento (autoincremental por id_asiento + 1).
 * Si la tabla está vacía empieza en 1. Tomamos un lock optimista — basta para
 * un único usuario; en producción habría que envolver todo en una transacción.
 */
function nextNumeroAsiento(PDO $db): int {
    $stmt = $db->query("SELECT COALESCE(MAX(numero_asiento), 0) + 1 AS n FROM asientos");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['n'] ?? 1);
}

/**
 * Construye las líneas D/H para una venta. Devuelve { lineas, glosa, error }.
 */
function compileAsientoVenta(PDO $db, array $v): array {
    $neto   = (float)($v['neto_venta']   ?? 0);
    $iva    = (float)($v['iva_venta']    ?? 0);
    $exento = (float)($v['exento_venta'] ?? 0);
    $total  = (float)($v['total_venta']  ?? 0);
    $afecta = $iva > 0 || $neto > 0;

    $clientes      = cuentaPorCodigo($db, '1.1.03');
    $ventasAfectas = cuentaPorCodigo($db, '4.1.01');
    $ventasExentas = cuentaPorCodigo($db, '4.1.02');
    $ivaDebito     = cuentaPorCodigo($db, '2.1.02');

    if (!$clientes) { return ['error' => 'Falta la cuenta 1.1.03 Clientes en el plan de cuentas']; }

    $lineas = [];
    $orden = 1;
    $lineas[] = [
        'cuenta_linea' => (int)$clientes['id_cuenta'],
        'glosa_linea'  => 'Factura/boleta N° ' . ($v['folio_venta'] ?? '?'),
        'debe_linea'   => $total,
        'haber_linea'  => 0,
        'orden_linea'  => $orden++,
    ];
    if ($neto > 0 && $ventasAfectas) {
        $lineas[] = [
            'cuenta_linea' => (int)$ventasAfectas['id_cuenta'],
            'glosa_linea'  => 'Neto afecto',
            'debe_linea'   => 0,
            'haber_linea'  => $neto,
            'orden_linea'  => $orden++,
        ];
    }
    if ($iva > 0 && $ivaDebito) {
        $lineas[] = [
            'cuenta_linea' => (int)$ivaDebito['id_cuenta'],
            'glosa_linea'  => 'IVA 19%',
            'debe_linea'   => 0,
            'haber_linea'  => $iva,
            'orden_linea'  => $orden++,
        ];
    }
    if ($exento > 0 && $ventasExentas) {
        $lineas[] = [
            'cuenta_linea' => (int)$ventasExentas['id_cuenta'],
            'glosa_linea'  => 'Monto exento',
            'debe_linea'   => 0,
            'haber_linea'  => $exento,
            'orden_linea'  => $orden++,
        ];
    }

    $glosa = sprintf('Venta — %s N° %s', $v['tipo_documento_venta'] ?? 'doc', $v['folio_venta'] ?? '?');
    return ['lineas' => $lineas, 'glosa' => $glosa];
}

/**
 * Construye las líneas D/H para una compra. La cuenta de gasto/costo viene
 * de la categoría asociada (categorias_gasto.cuenta_categoria).
 */
function compileAsientoCompra(PDO $db, array $c): array {
    $neto   = (float)($c['neto_compra']   ?? 0);
    $iva    = (float)($c['iva_compra']    ?? 0);
    $exento = (float)($c['exento_compra'] ?? 0);
    $total  = (float)($c['total_compra']  ?? 0);

    // Cuenta de la categoría
    $catId = (int)($c['categoria_compra'] ?? 0);
    if (!$catId) { return ['error' => 'El comprobante no tiene categoría']; }
    $catStmt = $db->prepare("SELECT cuenta_categoria FROM categorias_gasto WHERE id_categoria = :id LIMIT 1");
    $catStmt->execute([':id' => $catId]);
    $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
    if (!$cat || !$cat['cuenta_categoria']) {
        return ['error' => 'La categoría ' . $catId . ' no tiene cuenta contable asociada'];
    }
    $cuentaGastoId = (int)$cat['cuenta_categoria'];

    $proveedores = cuentaPorCodigo($db, '2.1.01');
    $ivaCredito  = cuentaPorCodigo($db, '1.1.04');
    if (!$proveedores) { return ['error' => 'Falta la cuenta 2.1.01 Proveedores en el plan de cuentas']; }

    $lineas = [];
    $orden = 1;
    // Una línea de gasto por el neto (si hay IVA) o por el total (si es exento/honorarios).
    $importeGasto = $iva > 0 ? $neto : ($total > 0 ? $total : $exento);
    $lineas[] = [
        'cuenta_linea' => $cuentaGastoId,
        'glosa_linea'  => 'Gasto del período',
        'debe_linea'   => $importeGasto,
        'haber_linea'  => 0,
        'orden_linea'  => $orden++,
    ];
    if ($iva > 0 && $ivaCredito) {
        $lineas[] = [
            'cuenta_linea' => (int)$ivaCredito['id_cuenta'],
            'glosa_linea'  => 'IVA crédito fiscal',
            'debe_linea'   => $iva,
            'haber_linea'  => 0,
            'orden_linea'  => $orden++,
        ];
    }
    $lineas[] = [
        'cuenta_linea' => (int)$proveedores['id_cuenta'],
        'glosa_linea'  => 'Factura proveedor N° ' . ($c['folio_compra'] ?? '?'),
        'debe_linea'   => 0,
        'haber_linea'  => $total,
        'orden_linea'  => $orden++,
    ];

    $glosa = sprintf('Compra — %s N° %s', $c['tipo_documento_compra'] ?? 'doc', $c['folio_compra'] ?? '?');
    return ['lineas' => $lineas, 'glosa' => $glosa];
}

/**
 * Inserta el asiento + sus líneas en una transacción. Si la suma D ≠ H,
 * aborta sin tocar la BD. Devuelve el id del asiento creado o un error string.
 */
function insertarAsiento(PDO $db, string $origen, int $origenId, string $fecha, string $glosa, array $lineas) {
    $sumD = array_sum(array_column($lineas, 'debe_linea'));
    $sumH = array_sum(array_column($lineas, 'haber_linea'));
    if (abs($sumD - $sumH) > 0.01) {
        return ['error' => sprintf('Asiento descuadrado: Debe=%s Haber=%s', $sumD, $sumH)];
    }

    $db->beginTransaction();
    try {
        $numero = nextNumeroAsiento($db);
        $stmt = $db->prepare(
            "INSERT INTO asientos
                (numero_asiento, fecha_asiento, glosa_asiento, origen_asiento, origen_id_asiento,
                 total_debe_asiento, total_haber_asiento, estado_asiento, date_created_asiento)
             VALUES (:n, :f, :g, :o, :oid, :td, :th, 'validado', CURDATE())"
        );
        $stmt->execute([
            ':n'   => $numero,
            ':f'   => $fecha,
            ':g'   => $glosa,
            ':o'   => $origen,
            ':oid' => $origenId,
            ':td'  => $sumD,
            ':th'  => $sumH,
        ]);
        $asientoId = (int)$db->lastInsertId();

        $lineStmt = $db->prepare(
            "INSERT INTO asiento_lineas
                (asiento_linea, cuenta_linea, glosa_linea, debe_linea, haber_linea, orden_linea, date_created_linea)
             VALUES (:a, :c, :g, :d, :h, :o, CURDATE())"
        );
        foreach ($lineas as $l) {
            $lineStmt->execute([
                ':a' => $asientoId,
                ':c' => $l['cuenta_linea'],
                ':g' => $l['glosa_linea'],
                ':d' => $l['debe_linea'],
                ':h' => $l['haber_linea'],
                ':o' => $l['orden_linea'],
            ]);
        }
        $db->commit();
        return ['ok' => true, 'asiento_id' => $asientoId, 'numero' => $numero];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['error' => 'Error al insertar: ' . $e->getMessage()];
    }
}

/* =========================================================================
   Acción: generar
   ========================================================================= */

$flash = null;
if (isset($_GET['accion']) && $_GET['accion'] === 'generar' && $db) {
    $tipo = $_GET['tipo'] ?? '';
    $id   = (int)($_GET['id'] ?? 0);
    if ($id > 0 && in_array($tipo, ['venta','compra'], true)) {
        if ($tipo === 'venta') {
            $stmt = $db->prepare("SELECT * FROM comprobantes_venta WHERE id_venta = :id");
            $stmt->execute([':id' => $id]);
            $v = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($v) {
                $compiled = compileAsientoVenta($db, $v);
                if (isset($compiled['error'])) {
                    $flash = ['type' => 'danger', 'msg' => 'Venta #' . $id . ': ' . $compiled['error']];
                } else {
                    $res = insertarAsiento($db, 'venta', $id, $v['fecha_venta'], $compiled['glosa'], $compiled['lineas']);
                    if (isset($res['error'])) {
                        $flash = ['type' => 'danger', 'msg' => 'Venta #' . $id . ': ' . $res['error']];
                    } else {
                        $flash = ['type' => 'success', 'msg' => 'Asiento N° ' . $res['numero'] . ' creado para venta #' . $id];
                    }
                }
            } else {
                $flash = ['type' => 'danger', 'msg' => 'Venta #' . $id . ' no encontrada'];
            }
        } else {
            $stmt = $db->prepare("SELECT * FROM comprobantes_compra WHERE id_compra = :id");
            $stmt->execute([':id' => $id]);
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($c) {
                $compiled = compileAsientoCompra($db, $c);
                if (isset($compiled['error'])) {
                    $flash = ['type' => 'danger', 'msg' => 'Compra #' . $id . ': ' . $compiled['error']];
                } else {
                    $res = insertarAsiento($db, 'compra', $id, $c['fecha_compra'], $compiled['glosa'], $compiled['lineas']);
                    if (isset($res['error'])) {
                        $flash = ['type' => 'danger', 'msg' => 'Compra #' . $id . ': ' . $res['error']];
                    } else {
                        $flash = ['type' => 'success', 'msg' => 'Asiento N° ' . $res['numero'] . ' creado para compra #' . $id];
                    }
                }
            } else {
                $flash = ['type' => 'danger', 'msg' => 'Compra #' . $id . ' no encontrada'];
            }
        }
    }
}

/* =========================================================================
   Listado: ventas + compras pendientes (sin asiento asociado).
   ========================================================================= */

$ventasPendientes = [];
$comprasPendientes = [];
if ($db) {
    try {
        $stmt = $db->prepare(
            "SELECT v.* FROM comprobantes_venta v
             LEFT JOIN asientos a ON a.origen_asiento = 'venta' AND a.origen_id_asiento = v.id_venta
             WHERE v.estado_venta != 'anulado' AND a.id_asiento IS NULL
             ORDER BY v.fecha_venta ASC, v.id_venta ASC"
        );
        $stmt->execute();
        $ventasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare(
            "SELECT c.* FROM comprobantes_compra c
             LEFT JOIN asientos a ON a.origen_asiento = 'compra' AND a.origen_id_asiento = c.id_compra
             WHERE c.estado_compra != 'anulado' AND a.id_asiento IS NULL
             ORDER BY c.fecha_compra ASC, c.id_compra ASC"
        );
        $stmt->execute();
        $comprasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Tablas no existen aún → quedan vacíos.
    }
}

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Generar asientos pendientes — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .seccion { margin-bottom: 2.5rem; }
    .seccion-titulo { padding-bottom: .5rem; border-bottom: 2px solid #dee2e6; margin-bottom: 1rem; }
    .table-pendientes th { background: #f8f9fa; }
    .table-pendientes td { vertical-align: middle; }
    .btn-generar { white-space: nowrap; }
</style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Generar asientos pendientes</h1>
            <p class="text-muted mb-0">Comprobantes sin asiento contable. Aprieta el botón para crear el asiento doble partida.</p>
        </div>
        <a href="/web/dashboard-contable" class="btn btn-outline-secondary">← Dashboard</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="seccion">
        <h3 class="seccion-titulo">Ventas pendientes (<?= count($ventasPendientes) ?>)</h3>
        <?php if (!$ventasPendientes): ?>
            <p class="text-muted">No hay ventas sin asiento. 🎉</p>
        <?php else: ?>
            <table class="table table-pendientes">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Folio</th>
                        <th>Glosa</th>
                        <th class="text-end">Neto</th>
                        <th class="text-end">IVA</th>
                        <th class="text-end">Exento</th>
                        <th class="text-end">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ventasPendientes as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['fecha_venta']) ?></td>
                        <td><?= htmlspecialchars($v['tipo_documento_venta']) ?></td>
                        <td><?= htmlspecialchars($v['folio_venta']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars(mb_substr($v['glosa_venta'] ?? '', 0, 50)) ?></td>
                        <td class="text-end"><?= pesos($v['neto_venta']) ?></td>
                        <td class="text-end"><?= pesos($v['iva_venta']) ?></td>
                        <td class="text-end"><?= pesos($v['exento_venta']) ?></td>
                        <td class="text-end"><strong><?= pesos($v['total_venta']) ?></strong></td>
                        <td>
                            <a class="btn btn-sm btn-success btn-generar"
                               href="?accion=generar&tipo=venta&id=<?= (int)$v['id_venta'] ?>">
                                Generar asiento
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="seccion">
        <h3 class="seccion-titulo">Compras pendientes (<?= count($comprasPendientes) ?>)</h3>
        <?php if (!$comprasPendientes): ?>
            <p class="text-muted">No hay compras sin asiento. 🎉</p>
        <?php else: ?>
            <table class="table table-pendientes">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Folio</th>
                        <th>Glosa</th>
                        <th class="text-end">Neto</th>
                        <th class="text-end">IVA</th>
                        <th class="text-end">Exento</th>
                        <th class="text-end">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($comprasPendientes as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['fecha_compra']) ?></td>
                        <td><?= htmlspecialchars($c['tipo_documento_compra']) ?></td>
                        <td><?= htmlspecialchars($c['folio_compra']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars(mb_substr($c['glosa_compra'] ?? '', 0, 50)) ?></td>
                        <td class="text-end"><?= pesos($c['neto_compra']) ?></td>
                        <td class="text-end"><?= pesos($c['iva_compra']) ?></td>
                        <td class="text-end"><?= pesos($c['exento_compra']) ?></td>
                        <td class="text-end"><strong><?= pesos($c['total_compra']) ?></strong></td>
                        <td>
                            <a class="btn btn-sm btn-success btn-generar"
                               href="?accion=generar&tipo=compra&id=<?= (int)$c['id_compra'] ?>">
                                Generar asiento
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
