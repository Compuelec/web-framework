<?php
/**
 * Cobros recibidos — listado de pagos recibidos de clientes con totales
 * agrupados por medio (caja / banco / payku), y enlace al asiento de cada
 * cobro. Custom page por los JOIN a clientes, ventas y asientos.
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
wpb_require_role(['contador', 'lectura']);
$db = Connection::connect();

$mes    = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anio   = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$inicio = sprintf('%04d-%02d-01', $anio, $mes);
$fin    = date('Y-m-t', strtotime($inicio));

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

$cobros  = [];
$totales = ['caja' => 0, 'banco' => 0, 'payku' => 0, 'total' => 0, 'count' => 0];
if ($db) {
    try {
        $stmt = $db->prepare(
            "SELECT co.*,
                    cl.razon_social_cliente,
                    cl.rut_cliente,
                    v.folio_venta,
                    v.tipo_documento_venta,
                    a.id_asiento,
                    a.numero_asiento
             FROM cobros co
             LEFT JOIN clientes cl          ON cl.id_cliente = co.cliente_cobro
             LEFT JOIN comprobantes_venta v ON v.id_venta    = co.venta_cobro
             LEFT JOIN asientos a           ON a.origen_asiento = 'cobro' AND a.origen_id_asiento = co.id_cobro
             WHERE co.fecha_cobro BETWEEN :a AND :b
             ORDER BY co.fecha_cobro DESC, co.id_cobro DESC"
        );
        $stmt->execute([':a' => $inicio, ':b' => $fin]);
        $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cobros as $c) {
            if (($c['estado_cobro'] ?? '') === 'anulado') { continue; }
            $medio = (string)($c['medio_cobro'] ?? '');
            $monto = (float)$c['monto_cobro'];
            if (isset($totales[$medio])) { $totales[$medio] += $monto; }
            $totales['total'] += $monto;
            $totales['count']++;
        }
    } catch (Throwable $e) {
        $cobros = [];
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
<title>Cobros recibidos — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .table-cobros th { background: #f5f5f5; font-weight: 600; vertical-align: middle; }
    .table-cobros td { vertical-align: middle; }
    .anulado td { text-decoration: line-through; color: #999; }
    .badge-asiento { background: #198754; }
    .badge-medio-caja  { background: #ffc107; color: #333; }
    .badge-medio-banco { background: #0d6efd; }
    .badge-medio-payku { background: #6f42c1; }
    .summary { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .summary-card {
        background: #fff; border: 1px solid #dee2e6; border-radius: 8px;
        padding: 1rem 1.25rem; flex: 1; min-width: 160px;
    }
    .summary-card .label { color: #6c757d; font-size: .85rem; text-transform: uppercase; }
    .summary-card .value { font-size: 1.4rem; font-weight: 600; color: #198754; }
    .summary-card.total .value { color: #0d6efd; }
    tfoot td { font-weight: 600; background: #f8f9fa; border-top: 2px solid #333 !important; }
</style>
</head>
<body>
<?= wpb_render_user_bar() ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Cobros recibidos</h1>
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

    <?php if (!$cobros): ?>
        <div class="alert alert-info">
            No hay cobros registrados en <?= $nombreMes ?> <?= $anio ?>.
            <a href="/cargar-cobro">Cargar un cobro nuevo →</a>
        </div>
    <?php else: ?>
        <div class="summary">
            <div class="summary-card">
                <div class="label">Caja (efectivo)</div>
                <div class="value"><?= pesos($totales['caja']) ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Banco (transferencia)</div>
                <div class="value"><?= pesos($totales['banco']) ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Payku (online)</div>
                <div class="value"><?= pesos($totales['payku']) ?></div>
            </div>
            <div class="summary-card total">
                <div class="label">Total · <?= $totales['count'] ?> cobros</div>
                <div class="value"><?= pesos($totales['total']) ?></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-cobros table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Venta</th>
                        <th>Medio</th>
                        <th class="text-end">Monto</th>
                        <th>Glosa</th>
                        <th>Estado</th>
                        <th>Asiento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cobros as $c): ?>
                        <?php $medio = $c['medio_cobro'] ?? ''; ?>
                        <tr class="<?= ($c['estado_cobro'] ?? '') === 'anulado' ? 'anulado' : '' ?>">
                            <td><?= htmlspecialchars($c['fecha_cobro']) ?></td>
                            <td>
                                <?php if ($c['razon_social_cliente']): ?>
                                    <strong><?= htmlspecialchars($c['razon_social_cliente']) ?></strong>
                                    <?php if ($c['rut_cliente']): ?>
                                        <div class="text-muted small"><?= htmlspecialchars($c['rut_cliente']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">(cliente eliminado · id <?= (int)$c['cliente_cobro'] ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['folio_venta']): ?>
                                    <?= htmlspecialchars($c['tipo_documento_venta']) ?> N° <?= htmlspecialchars($c['folio_venta']) ?>
                                <?php else: ?>
                                    <span class="text-muted small">(venta #<?= (int)$c['venta_cobro'] ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-medio-<?= htmlspecialchars($medio) ?>"><?= htmlspecialchars($medio) ?></span></td>
                            <td class="text-end"><strong><?= pesos($c['monto_cobro']) ?></strong></td>
                            <td class="small text-muted"><?= htmlspecialchars(mb_substr($c['glosa_cobro'] ?? '', 0, 60)) ?></td>
                            <td><?= htmlspecialchars($c['estado_cobro'] ?? '') ?></td>
                            <td>
                                <?php if ($c['id_asiento']): ?>
                                    <span class="badge badge-asiento">✓ N° <?= (int)$c['numero_asiento'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">sin asiento</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end">Total cobros (sin anulados):</td>
                        <td class="text-end"><?= pesos($totales['total']) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-3 d-flex gap-2 flex-wrap">
            <a href="/dashboard-contable" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
            <a href="/cargar-cobro" class="btn btn-primary btn-sm">Cargar cobro nuevo</a>
            <a href="/libro-ventas?mes=<?= (int)$mes ?>&anio=<?= (int)$anio ?>" class="btn btn-outline-primary btn-sm ms-auto">Libro de ventas →</a>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
