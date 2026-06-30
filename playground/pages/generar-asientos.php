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
 *  - La lógica contable vive en web/pages/_lib/asientos.php (compartida con
 *    /cargar-venta y /cargar-compra). Las recetas del asiento están
 *    documentadas en ese archivo.
 */

require_once __DIR__ . '/../config.php';

$config  = require __DIR__ . '/../config.php';
$siteCfg = is_array($config) ? $config : [];
$siteName = $siteCfg['site']['name'] ?? 'Contabilidad';

if (!empty($siteCfg['timezone'])) {
    date_default_timezone_set($siteCfg['timezone']);
}

require_once __DIR__ . '/../../api/models/connection.php';
require_once __DIR__ . '/_lib/auth.php';
require_once __DIR__ . '/_lib/cierres.php';
wpb_require_role(['contador']);

require_once __DIR__ . '/_lib/asientos.php';
$db = Connection::connect();

/* =========================================================================
   Helpers (display only — accounting logic lives in _lib/asientos.php)
   ========================================================================= */

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}


/* =========================================================================
   Acción: generar
   ========================================================================= */

$flash = null;
// Switched from GET to POST + CSRF: a mutator over GET is a bad-practice
// CSRF target — just visiting `<img src="...?accion=generar&...">` from
// another site would create rows on behalf of a logged-in user. POST + token
// closes both paths.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($_POST['accion'] ?? '') === 'generar'
    && $db) {
    wpb_csrf_check();
    $tipo = $_POST['tipo'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    if ($id > 0 && in_array($tipo, ['venta','compra'], true)) {
        if ($tipo === 'venta') {
            $stmt = $db->prepare("SELECT * FROM comprobantes_venta WHERE id_venta = :id");
            $stmt->execute([':id' => $id]);
            $v = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($v && mes_esta_cerrado($db, (string)$v['fecha_venta'])) {
                $flash = ['type' => 'danger', 'msg' => 'Venta #' . $id . ': el mes ' . substr($v['fecha_venta'], 0, 7) . ' está cerrado.'];
            } elseif ($v) {
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
            if ($c && mes_esta_cerrado($db, (string)$c['fecha_compra'])) {
                $flash = ['type' => 'danger', 'msg' => 'Compra #' . $id . ': el mes ' . substr($c['fecha_compra'], 0, 7) . ' está cerrado.'];
            } elseif ($c) {
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
<?= wpb_render_user_bar() ?>
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
                            <form method="post" class="d-inline">
                                <?= wpb_csrf_field() ?>
                                <input type="hidden" name="accion" value="generar">
                                <input type="hidden" name="tipo" value="venta">
                                <input type="hidden" name="id" value="<?= (int)$v['id_venta'] ?>">
                                <button type="submit" class="btn btn-sm btn-success btn-generar">Generar asiento</button>
                            </form>
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
                            <form method="post" class="d-inline">
                                <?= wpb_csrf_field() ?>
                                <input type="hidden" name="accion" value="generar">
                                <input type="hidden" name="tipo" value="compra">
                                <input type="hidden" name="id" value="<?= (int)$c['id_compra'] ?>">
                                <button type="submit" class="btn btn-sm btn-success btn-generar">Generar asiento</button>
                            </form>
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
