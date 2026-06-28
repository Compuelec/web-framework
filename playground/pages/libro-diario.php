<?php
/**
 * Libro Diario — todos los asientos contables con sus líneas D/H, ordenados
 * cronológicamente. Custom page (no pasa por el page-builder) porque combina
 * dos tablas con un JOIN que {{#cada}} no puede expresar.
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

$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$inicio = sprintf('%04d-%02d-01', $anio, $mes);
$fin    = date('Y-m-t', strtotime($inicio));

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

// Carga todos los asientos del periodo + sus líneas + el código/nombre de la cuenta.
$asientos = [];
if ($db) {
    try {
        $stmt = $db->prepare(
            "SELECT a.id_asiento, a.numero_asiento, a.fecha_asiento, a.glosa_asiento,
                    a.origen_asiento, a.origen_id_asiento, a.estado_asiento,
                    a.total_debe_asiento, a.total_haber_asiento
             FROM asientos a
             WHERE a.fecha_asiento BETWEEN :a AND :b
             ORDER BY a.fecha_asiento ASC, a.numero_asiento ASC, a.id_asiento ASC"
        );
        $stmt->execute([':a' => $inicio, ':b' => $fin]);
        $asientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Carga las líneas de todos los asientos en una sola query para evitar N+1.
        if ($asientos) {
            $ids = array_column($asientos, 'id_asiento');
            $place = implode(',', array_fill(0, count($ids), '?'));
            $linStmt = $db->prepare(
                "SELECT l.id_linea, l.asiento_linea, l.glosa_linea, l.debe_linea, l.haber_linea, l.orden_linea,
                        c.codigo_cuenta, c.nombre_cuenta
                 FROM asiento_lineas l
                 LEFT JOIN plan_cuentas c ON c.id_cuenta = l.cuenta_linea
                 WHERE l.asiento_linea IN ($place)
                 ORDER BY l.asiento_linea ASC, l.orden_linea ASC, l.id_linea ASC"
            );
            $linStmt->execute($ids);
            $allLines = $linStmt->fetchAll(PDO::FETCH_ASSOC);
            $byAsiento = [];
            foreach ($allLines as $l) {
                $byAsiento[$l['asiento_linea']][] = $l;
            }
            foreach ($asientos as &$a) {
                $a['lineas'] = $byAsiento[$a['id_asiento']] ?? [];
            }
            unset($a);
        }
    } catch (Throwable $e) {
        $asientos = [];
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
<title>Libro Diario — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .asiento-card { margin-bottom: 1.5rem; border: 1px solid #dee2e6; border-radius: .5rem; overflow: hidden; }
    .asiento-head { background: #f8f9fa; padding: .75rem 1rem; border-bottom: 1px solid #dee2e6; }
    .asiento-head .num { font-weight: 600; color: #0d6efd; }
    .asiento-head .fecha { color: #6c757d; }
    .lineas { width: 100%; }
    .lineas th { background: #fff; border-bottom: 1px solid #dee2e6; padding: .5rem 1rem; font-size: .85rem; color: #6c757d; }
    .lineas td { padding: .4rem 1rem; border-bottom: 1px solid #f0f0f0; }
    .lineas .codigo { font-family: monospace; color: #6c757d; }
    .lineas .indent { padding-left: 2rem; }
    .lineas tfoot td { font-weight: 600; border-top: 2px solid #333; }
    .estado-borrador  { color: #6c757d; }
    .estado-validado  { color: #198754; }
    .estado-anulado   { color: #dc3545; text-decoration: line-through; }
</style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Libro Diario</h1>
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

    <?php if (!$asientos): ?>
        <div class="alert alert-info">
            No hay asientos en el período <?= $nombreMes ?> <?= $anio ?>.
            Para registrar uno, andá a la sección "Asientos contables" del CMS y sus "Líneas".
        </div>
    <?php else: ?>
        <?php foreach ($asientos as $a): ?>
            <div class="asiento-card">
                <div class="asiento-head d-flex justify-content-between">
                    <div>
                        <span class="num">N° <?= htmlspecialchars($a['numero_asiento'] ?? '?') ?></span>
                        <span class="fecha ms-3"><?= htmlspecialchars($a['fecha_asiento']) ?></span>
                        <span class="ms-3 text-muted small">[<?= htmlspecialchars($a['origen_asiento'] ?? 'manual') ?>]</span>
                    </div>
                    <span class="estado-<?= htmlspecialchars($a['estado_asiento'] ?? 'borrador') ?>">
                        <?= htmlspecialchars($a['estado_asiento'] ?? 'borrador') ?>
                    </span>
                </div>
                <?php if (!empty($a['glosa_asiento'])): ?>
                    <div class="px-3 py-2 small text-muted"><?= nl2br(htmlspecialchars($a['glosa_asiento'])) ?></div>
                <?php endif; ?>
                <table class="lineas">
                    <thead>
                        <tr>
                            <th style="width:120px">Código</th>
                            <th>Cuenta / glosa</th>
                            <th style="width:140px" class="text-end">Debe</th>
                            <th style="width:140px" class="text-end">Haber</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $sumD = 0; $sumH = 0;
                            foreach ($a['lineas'] as $l):
                                $sumD += (float)$l['debe_linea'];
                                $sumH += (float)$l['haber_linea'];
                                $isDebe = (float)$l['debe_linea'] > 0;
                        ?>
                            <tr>
                                <td class="codigo"><?= htmlspecialchars($l['codigo_cuenta'] ?? '—') ?></td>
                                <td class="<?= $isDebe ? '' : 'indent' ?>">
                                    <strong><?= htmlspecialchars($l['nombre_cuenta'] ?? '(cuenta eliminada)') ?></strong>
                                    <?php if (!empty($l['glosa_linea'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($l['glosa_linea']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= $isDebe ? pesos($l['debe_linea']) : '' ?></td>
                                <td class="text-end"><?= !$isDebe && (float)$l['haber_linea'] > 0 ? pesos($l['haber_linea']) : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-end">Totales:</td>
                            <td class="text-end"><?= pesos($sumD) ?></td>
                            <td class="text-end"><?= pesos($sumH) ?></td>
                        </tr>
                        <?php if (abs($sumD - $sumH) > 0.01): ?>
                            <tr><td colspan="4" class="text-danger small p-2">⚠ Asiento descuadrado: D − H = <?= pesos($sumD - $sumH) ?></td></tr>
                        <?php endif; ?>
                    </tfoot>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="mt-4">
        <a href="/web/dashboard-contable">← Volver al dashboard</a>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
