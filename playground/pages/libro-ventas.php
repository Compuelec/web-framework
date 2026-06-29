<?php
/**
 * Libro de Ventas — comprobantes de venta del período con razón social
 * resuelta + indicador de si ya tiene asiento generado.
 *
 * Custom page (no pasa por el page-builder) porque hace JOIN a `clientes`
 * y a `asientos` que el template {{#cada}} no expresa.
 */

require_once __DIR__ . '/../config.php';

$config   = require __DIR__ . '/../config.php';
$siteCfg  = is_array($config) ? $config : [];
$siteName = $siteCfg['site']['name'] ?? 'Contabilidad';
$baseUrl  = isset($siteCfg['site']['base_url']) ? rtrim($siteCfg['site']['base_url'], '/') . '/' : '/';

if (!empty($siteCfg['timezone'])) {
    date_default_timezone_set($siteCfg['timezone']);
}

require_once __DIR__ . '/../../api/models/connection.php';
$db = Connection::connect();

$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$inicio = sprintf('%04d-%02d-01', $anio, $mes);
$fin    = date('Y-m-t', strtotime($inicio));

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

// Resolves cliente_venta (id) → razón social. LEFT JOIN so a deleted cliente
// still shows the comprobante (with a placeholder name).
$ventas = [];
$totales = ['neto' => 0, 'iva' => 0, 'exento' => 0, 'total' => 0];
if ($db) {
    try {
        $stmt = $db->prepare(
            "SELECT v.*,
                    c.razon_social_cliente,
                    c.rut_cliente,
                    a.id_asiento,
                    a.numero_asiento
             FROM comprobantes_venta v
             LEFT JOIN clientes c ON c.id_cliente = v.cliente_venta
             LEFT JOIN asientos a ON a.origen_asiento = 'venta' AND a.origen_id_asiento = v.id_venta
             WHERE v.fecha_venta BETWEEN :a AND :b
             ORDER BY v.fecha_venta DESC, v.id_venta DESC"
        );
        $stmt->execute([':a' => $inicio, ':b' => $fin]);
        $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ventas as $v) {
            if (($v['estado_venta'] ?? '') === 'anulado') { continue; }
            $totales['neto']   += (float)$v['neto_venta'];
            $totales['iva']    += (float)$v['iva_venta'];
            $totales['exento'] += (float)$v['exento_venta'];
            $totales['total']  += (float)$v['total_venta'];
        }
    } catch (Throwable $e) {
        $ventas = [];
    }
}

$nombreMes = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][$mes];

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Libro de Ventas — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .table-libro th { background: #f5f5f5; font-weight: 600; vertical-align: middle; }
    .table-libro td { vertical-align: middle; }
    .table-libro .small-rut { color: #6c757d; font-size: .8rem; }
    .anulado td { text-decoration: line-through; color: #999; }
    .badge-asiento { background: #198754; }
    .badge-pendiente { background: #ffc107; color: #333; }
    tfoot td { font-weight: 600; background: #f8f9fa; border-top: 2px solid #333 !important; }
    .totales-row td { font-size: 1.05rem; }
</style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Libro de Ventas</h1>
            <p class="text-muted mb-0"><?= $nombreMes ?> <?= $anio ?></p>
        </div>
        <form class="d-flex gap-2" method="get">
            <select class="form-select" name="mes">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $mes ? 'selected' : '' ?>>
                        <?= ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][$m] ?>
                    </option>
                <?php endfor; ?>
            </select>
            <input type="number" class="form-control" style="width:100px" name="anio" value="<?= $anio ?>" min="2020" max="2099">
            <button class="btn btn-primary">Ver</button>
        </form>
    </div>

    <?php if (!$ventas): ?>
        <div class="alert alert-info">
            No hay comprobantes de venta en <?= $nombreMes ?> <?= $anio ?>.
            <a href="/dashboard-contable">← Volver al dashboard</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-libro table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Folio</th>
                        <th>Cliente</th>
                        <th>Glosa</th>
                        <th class="text-end">Neto</th>
                        <th class="text-end">IVA</th>
                        <th class="text-end">Exento</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th>Asiento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $v): ?>
                        <tr class="<?= ($v['estado_venta'] ?? '') === 'anulado' ? 'anulado' : '' ?>">
                            <td><?= htmlspecialchars($v['fecha_venta']) ?></td>
                            <td><?= htmlspecialchars($v['tipo_documento_venta']) ?></td>
                            <td><?= htmlspecialchars($v['folio_venta']) ?></td>
                            <td>
                                <?php if ($v['razon_social_cliente']): ?>
                                    <strong><?= htmlspecialchars($v['razon_social_cliente']) ?></strong>
                                    <?php if ($v['rut_cliente']): ?>
                                        <div class="small-rut"><?= htmlspecialchars($v['rut_cliente']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">(cliente eliminado · id <?= (int)$v['cliente_venta'] ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars(mb_substr($v['glosa_venta'] ?? '', 0, 60)) ?></td>
                            <td class="text-end"><?= pesos($v['neto_venta']) ?></td>
                            <td class="text-end"><?= pesos($v['iva_venta']) ?></td>
                            <td class="text-end"><?= pesos($v['exento_venta']) ?></td>
                            <td class="text-end"><strong><?= pesos($v['total_venta']) ?></strong></td>
                            <td><?= htmlspecialchars($v['estado_venta'] ?? '') ?></td>
                            <td>
                                <?php if ($v['id_asiento']): ?>
                                    <span class="badge badge-asiento" title="Asiento N° <?= (int)$v['numero_asiento'] ?>">✓ N° <?= (int)$v['numero_asiento'] ?></span>
                                <?php else: ?>
                                    <span class="badge badge-pendiente" title="Falta generar asiento">pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="totales-row">
                        <td colspan="5" class="text-end">Totales (sin anulados):</td>
                        <td class="text-end"><?= pesos($totales['neto']) ?></td>
                        <td class="text-end"><?= pesos($totales['iva']) ?></td>
                        <td class="text-end"><?= pesos($totales['exento']) ?></td>
                        <td class="text-end"><?= pesos($totales['total']) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-3">
            <a href="/dashboard-contable" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
            <a href="/generar-asientos" class="btn btn-outline-primary btn-sm">Generar asientos pendientes</a>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
