<?php
/**
 * Balance General — saldos por cuenta agrupados por tipo (activo, pasivo,
 * patrimonio, ingresos, gastos, costos). Calcula los saldos sumando débitos
 * y créditos de asiento_lineas, sin importar el período (saldos acumulados
 * desde el inicio).
 */

require_once __DIR__ . '/../config.php';

$config  = require __DIR__ . '/../config.php';
$siteCfg = is_array($config) ? $config : [];
$siteName = $siteCfg['site']['name'] ?? 'Contabilidad';
$baseUrl  = isset($siteCfg['site']['base_url']) ? rtrim($siteCfg['site']['base_url'], '/') . '/' : '/';

if (!empty($siteCfg['timezone'])) {
    date_default_timezone_set($siteCfg['timezone']);
}

require_once __DIR__ . '/../../api/models/connection.php';
$db = Connection::connect();

// Fecha de corte: por defecto hoy, configurable por ?hasta=YYYY-MM-DD
$hasta = isset($_GET['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'])
    ? $_GET['hasta']
    : date('Y-m-d');

function pesos($n) {
    $abs = abs((float)$n);
    return ($n < 0 ? '-' : '') . '$ ' . number_format($abs, 0, ',', '.');
}

// Por cada cuenta, computa saldo acumulado = ΣDebe − ΣHaber.
// Para cuentas de naturaleza acreedora (pasivo, patrimonio, ingreso) se
// invierte el signo para que el saldo "natural" sea positivo.
$saldos = [];
if ($db) {
    try {
        $stmt = $db->prepare(
            "SELECT c.id_cuenta, c.codigo_cuenta, c.nombre_cuenta, c.tipo_cuenta, c.naturaleza_cuenta, c.nivel_cuenta,
                    COALESCE(SUM(l.debe_linea), 0) AS debe,
                    COALESCE(SUM(l.haber_linea), 0) AS haber
             FROM plan_cuentas c
             LEFT JOIN asiento_lineas l ON l.cuenta_linea = c.id_cuenta
             LEFT JOIN asientos a ON a.id_asiento = l.asiento_linea
                AND a.fecha_asiento <= :hasta
                AND a.estado_asiento != 'anulado'
             WHERE c.activa_cuenta = 1 AND c.nivel_cuenta >= 3
             GROUP BY c.id_cuenta, c.codigo_cuenta, c.nombre_cuenta, c.tipo_cuenta, c.naturaleza_cuenta, c.nivel_cuenta
             ORDER BY c.codigo_cuenta ASC"
        );
        $stmt->execute([':hasta' => $hasta]);
        $saldos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $saldos = [];
    }
}

// Agrupa por tipo. Para cada cuenta el "saldo natural" depende de su naturaleza:
//   deudora  → saldo = Debe − Haber  (positivo si la cuenta "tiene")
//   acreedora → saldo = Haber − Debe
$grupos = [
    'activo'     => ['titulo' => 'ACTIVO',     'cuentas' => [], 'total' => 0],
    'pasivo'     => ['titulo' => 'PASIVO',     'cuentas' => [], 'total' => 0],
    'patrimonio' => ['titulo' => 'PATRIMONIO', 'cuentas' => [], 'total' => 0],
    'ingreso'    => ['titulo' => 'INGRESOS',   'cuentas' => [], 'total' => 0],
    'gasto'      => ['titulo' => 'GASTOS',     'cuentas' => [], 'total' => 0],
    'costo'      => ['titulo' => 'COSTOS',     'cuentas' => [], 'total' => 0],
];

foreach ($saldos as $row) {
    $debe  = (float)$row['debe'];
    $haber = (float)$row['haber'];
    $saldo = $row['naturaleza_cuenta'] === 'deudora' ? $debe - $haber : $haber - $debe;
    $tipo  = $row['tipo_cuenta'];
    if (!isset($grupos[$tipo])) { continue; }
    if (abs($saldo) < 0.01) { continue; } // ocultá cuentas en 0
    $grupos[$tipo]['cuentas'][] = $row + ['saldo' => $saldo];
    $grupos[$tipo]['total'] += $saldo;
}

$totalActivo     = $grupos['activo']['total'];
$totalPasivo     = $grupos['pasivo']['total'];
$totalPatrimonio = $grupos['patrimonio']['total'];
$totalIngresos   = $grupos['ingreso']['total'];
$totalGastos     = $grupos['gasto']['total'] + $grupos['costo']['total'];
$utilidadEjercicio = $totalIngresos - $totalGastos;

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Balance — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .grupo-card { margin-bottom: 1.25rem; }
    .grupo-head { background: #0d6efd; color: #fff; padding: .5rem 1rem; border-radius: .35rem .35rem 0 0; font-weight: 600; }
    .grupo-head.activo     { background: #198754; }
    .grupo-head.pasivo     { background: #dc3545; }
    .grupo-head.patrimonio { background: #6f42c1; }
    .grupo-head.ingreso    { background: #20c997; }
    .grupo-head.gasto      { background: #fd7e14; }
    .grupo-head.costo      { background: #ffc107; color: #333; }
    .cuentas { width: 100%; }
    .cuentas td { padding: .35rem 1rem; border-bottom: 1px solid #f0f0f0; }
    .cuentas .codigo { font-family: monospace; color: #6c757d; width: 120px; }
    .cuentas .saldo { text-align: right; font-variant-numeric: tabular-nums; width: 160px; }
    .cuentas tfoot td { font-weight: 600; border-top: 2px solid #333; background: #f8f9fa; }
    .totales-table th { background: #f8f9fa; padding: .75rem 1rem; }
    .totales-table td { padding: .75rem 1rem; text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
    .check-ok       { color: #198754; }
    .check-warning  { color: #dc3545; }
</style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Balance</h1>
            <p class="text-muted mb-0">Saldos acumulados al <?= htmlspecialchars($hasta) ?></p>
        </div>
        <form class="d-flex gap-2" method="get">
            <input type="date" class="form-control" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
            <button class="btn btn-primary">Ver</button>
        </form>
    </div>

    <?php
        $empty = true;
        foreach ($grupos as $g) { if (!empty($g['cuentas'])) { $empty = false; break; } }
        if ($empty):
    ?>
        <div class="alert alert-info">
            No hay movimientos contables aún. Cargá comprobantes y asientos para ver los saldos.
        </div>
    <?php else: ?>

    <div class="row">
        <div class="col-lg-6">
            <?php foreach (['activo', 'gasto', 'costo'] as $clave): $g = $grupos[$clave]; if (empty($g['cuentas'])) continue; ?>
                <div class="grupo-card">
                    <div class="grupo-head <?= $clave ?>"><?= $g['titulo'] ?></div>
                    <table class="cuentas">
                        <tbody>
                            <?php foreach ($g['cuentas'] as $c): ?>
                                <tr>
                                    <td class="codigo"><?= htmlspecialchars($c['codigo_cuenta']) ?></td>
                                    <td><?= htmlspecialchars($c['nombre_cuenta']) ?></td>
                                    <td class="saldo"><?= pesos($c['saldo']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">Total <?= $g['titulo'] ?></td>
                                <td class="saldo"><?= pesos($g['total']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="col-lg-6">
            <?php foreach (['pasivo', 'patrimonio', 'ingreso'] as $clave): $g = $grupos[$clave]; if (empty($g['cuentas'])) continue; ?>
                <div class="grupo-card">
                    <div class="grupo-head <?= $clave ?>"><?= $g['titulo'] ?></div>
                    <table class="cuentas">
                        <tbody>
                            <?php foreach ($g['cuentas'] as $c): ?>
                                <tr>
                                    <td class="codigo"><?= htmlspecialchars($c['codigo_cuenta']) ?></td>
                                    <td><?= htmlspecialchars($c['nombre_cuenta']) ?></td>
                                    <td class="saldo"><?= pesos($c['saldo']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">Total <?= $g['titulo'] ?></td>
                                <td class="saldo"><?= pesos($g['total']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h5>Cuadre</h5>
            <table class="totales-table w-100">
                <tr><th>Total Activo</th>             <td><?= pesos($totalActivo) ?></td></tr>
                <tr><th>Total Pasivo + Patrimonio</th><td><?= pesos($totalPasivo + $totalPatrimonio + $utilidadEjercicio) ?></td></tr>
                <tr>
                    <th>Resultado del ejercicio (Ingresos − Gastos)</th>
                    <td class="<?= $utilidadEjercicio >= 0 ? 'check-ok' : 'check-warning' ?>"><?= pesos($utilidadEjercicio) ?></td>
                </tr>
                <tr>
                    <th>Diferencia</th>
                    <?php
                        $dif = $totalActivo - ($totalPasivo + $totalPatrimonio + $utilidadEjercicio);
                        $clase = abs($dif) < 0.01 ? 'check-ok' : 'check-warning';
                    ?>
                    <td class="<?= $clase ?>"><?= pesos($dif) ?> <?= abs($dif) < 0.01 ? '✓ cuadra' : '⚠ no cuadra' ?></td>
                </tr>
            </table>
        </div>
    </div>

    <?php endif; ?>

    <div class="mt-4">
        <a href="/web/dashboard-contable">← Volver al dashboard</a>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
