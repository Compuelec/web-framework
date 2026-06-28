<?php
/**
 * Dashboard contable — vista rápida de la salud financiera del mes en curso.
 *
 * Custom page (no pasa por el page-builder del framework): muestra agregados
 * que el template engine {{#cada}} no puede calcular. Cargada por web/index.php
 * a través de la convención web/pages/<slug>.php.
 */

require_once __DIR__ . '/../config.php';

$config  = require __DIR__ . '/../config.php';
$siteCfg = is_array($config) ? $config : [];
$siteName = $siteCfg['site']['name'] ?? 'Contabilidad';
$baseUrl  = isset($siteCfg['site']['base_url']) ? rtrim($siteCfg['site']['base_url'], '/') . '/' : '/';

if (!empty($siteCfg['timezone'])) {
    date_default_timezone_set($siteCfg['timezone']);
}

// Connect via the same PDO the API uses.
require_once __DIR__ . '/../../api/models/connection.php';
$db = Connection::connect();

$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$inicio = sprintf('%04d-%02d-01', $anio, $mes);
$fin    = date('Y-m-t', strtotime($inicio));

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

function totalSum($db, $sql, $params = []) {
    if (!$db) { return 0; }
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && isset($row['total']) ? (float)$row['total'] : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

$ventasNeto   = totalSum($db, "SELECT COALESCE(SUM(neto_venta),0) AS total FROM comprobantes_venta WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'", [':a'=>$inicio, ':b'=>$fin]);
$ventasIva    = totalSum($db, "SELECT COALESCE(SUM(iva_venta),0)  AS total FROM comprobantes_venta WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'", [':a'=>$inicio, ':b'=>$fin]);
$ventasExento = totalSum($db, "SELECT COALESCE(SUM(exento_venta),0) AS total FROM comprobantes_venta WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'", [':a'=>$inicio, ':b'=>$fin]);
$ventasTotal  = totalSum($db, "SELECT COALESCE(SUM(total_venta),0) AS total FROM comprobantes_venta WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'", [':a'=>$inicio, ':b'=>$fin]);

$comprasNeto   = totalSum($db, "SELECT COALESCE(SUM(neto_compra),0) AS total FROM comprobantes_compra WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'", [':a'=>$inicio, ':b'=>$fin]);
$comprasIva    = totalSum($db, "SELECT COALESCE(SUM(iva_compra),0)  AS total FROM comprobantes_compra WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'", [':a'=>$inicio, ':b'=>$fin]);
$comprasExento = totalSum($db, "SELECT COALESCE(SUM(exento_compra),0) AS total FROM comprobantes_compra WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'", [':a'=>$inicio, ':b'=>$fin]);
$comprasTotal  = totalSum($db, "SELECT COALESCE(SUM(total_compra),0) AS total FROM comprobantes_compra WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'", [':a'=>$inicio, ':b'=>$fin]);

$ivaPagar     = $ventasIva - $comprasIva;   // débito - crédito fiscal
$resultadoMes = $ventasNeto + $ventasExento - $comprasNeto - $comprasExento;

$nombreMes = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][$mes];

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard contable — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .card-metric { border-left: 4px solid; }
    .card-metric.ventas    { border-left-color: #198754; }
    .card-metric.compras   { border-left-color: #dc3545; }
    .card-metric.iva       { border-left-color: #ffc107; }
    .card-metric.resultado { border-left-color: #0d6efd; }
    .metric-value { font-size: 1.6rem; font-weight: 600; }
    .metric-label { color: #6c757d; font-size: .9rem; }
</style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Dashboard contable</h1>
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

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-metric ventas">
                <div class="card-body">
                    <div class="metric-label">Ventas del mes (total)</div>
                    <div class="metric-value text-success"><?= pesos($ventasTotal) ?></div>
                    <div class="small text-muted">Neto: <?= pesos($ventasNeto) ?> · IVA: <?= pesos($ventasIva) ?> · Exento: <?= pesos($ventasExento) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-metric compras">
                <div class="card-body">
                    <div class="metric-label">Compras del mes (total)</div>
                    <div class="metric-value text-danger"><?= pesos($comprasTotal) ?></div>
                    <div class="small text-muted">Neto: <?= pesos($comprasNeto) ?> · IVA: <?= pesos($comprasIva) ?> · Exento: <?= pesos($comprasExento) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-metric iva">
                <div class="card-body">
                    <div class="metric-label">IVA a pagar (D.F. − C.F.)</div>
                    <div class="metric-value <?= $ivaPagar >= 0 ? 'text-warning' : 'text-success' ?>"><?= pesos($ivaPagar) ?></div>
                    <div class="small text-muted">D. Fiscal <?= pesos($ventasIva) ?> · C. Fiscal <?= pesos($comprasIva) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-metric resultado">
                <div class="card-body">
                    <div class="metric-label">Resultado del mes (neto)</div>
                    <div class="metric-value <?= $resultadoMes >= 0 ? 'text-success' : 'text-danger' ?>"><?= pesos($resultadoMes) ?></div>
                    <div class="small text-muted">Ingresos − Gastos sin IVA</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5>Accesos rápidos</h5>
            <ul class="mb-0">
                <li><a href="/libro-ventas">Libro de Ventas</a></li>
                <li><a href="/libro-compras">Libro de Compras</a></li>
                <li><a href="/libro-diario">Libro Diario</a></li>
                <li><a href="/balance">Balance</a></li>
            </ul>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
