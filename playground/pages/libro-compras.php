<?php
/**
 * Libro de Compras — comprobantes de compra del período con razón social
 * del proveedor, nombre de la categoría y estado del asiento.
 *
 * Custom page (no pasa por el page-builder) porque hace JOIN a `proveedores`,
 * `categorias_gasto` y `asientos`.
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
require_once __DIR__ . '/_lib/auth.php';
require_once __DIR__ . '/_lib/sii.php';
wpb_require_role(['contador', 'lectura']);

$db = Connection::connect();

$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$inicio = sprintf('%04d-%02d-01', $anio, $mes);
$fin    = date('Y-m-t', strtotime($inicio));

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

$compras = [];
$totales = ['neto' => 0, 'iva' => 0, 'exento' => 0, 'total' => 0];
if ($db) {
    try {
        $stmt = $db->prepare(
            "SELECT c.*,
                    p.razon_social_proveedor,
                    p.rut_proveedor,
                    cat.nombre_categoria,
                    a.id_asiento,
                    a.numero_asiento
             FROM comprobantes_compra c
             LEFT JOIN proveedores p     ON p.id_proveedor   = c.proveedor_compra
             LEFT JOIN categorias_gasto cat ON cat.id_categoria = c.categoria_compra
             LEFT JOIN asientos a        ON a.origen_asiento = 'compra' AND a.origen_id_asiento = c.id_compra
             WHERE c.fecha_compra BETWEEN :a AND :b
             ORDER BY c.fecha_compra DESC, c.id_compra DESC"
        );
        $stmt->execute([':a' => $inicio, ':b' => $fin]);
        $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($compras as $c) {
            if (($c['estado_compra'] ?? '') === 'anulado') { continue; }
            $totales['neto']   += (float)$c['neto_compra'];
            $totales['iva']    += (float)$c['iva_compra'];
            $totales['exento'] += (float)$c['exento_compra'];
            $totales['total']  += (float)$c['total_compra'];
        }
    } catch (Throwable $e) {
        $compras = [];
    }
}

$nombreMes = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][$mes];

// CSV export (formato SII): mismo patrón que libro-ventas.
if (($_GET['formato'] ?? '') === 'csv-sii') {
    $filename = sprintf('libro-compras-%04d-%02d.csv', $anio, $mes);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo sii_csv_libro_compras($compras);
    exit;
}

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Libro de Compras — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .table-libro th { background: #f5f5f5; font-weight: 600; vertical-align: middle; }
    .table-libro td { vertical-align: middle; }
    .table-libro .small-rut { color: #6c757d; font-size: .8rem; }
    .anulado td { text-decoration: line-through; color: #999; }
    .badge-asiento { background: #198754; }
    .badge-pendiente { background: #ffc107; color: #333; }
    .pill-cat { background: #e9ecef; color: #495057; padding: 1px 8px; border-radius: 12px; font-size: .8rem; }
    tfoot td { font-weight: 600; background: #f8f9fa; border-top: 2px solid #333 !important; }
    .totales-row td { font-size: 1.05rem; }
</style>
</head>
<body>
<?= wpb_render_user_bar() ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Libro de Compras</h1>
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

    <?php if (!$compras): ?>
        <div class="alert alert-info">
            No hay comprobantes de compra en <?= $nombreMes ?> <?= $anio ?>.
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
                        <th>Proveedor</th>
                        <th>Categoría</th>
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
                    <?php foreach ($compras as $c): ?>
                        <tr class="<?= ($c['estado_compra'] ?? '') === 'anulado' ? 'anulado' : '' ?>">
                            <td><?= htmlspecialchars($c['fecha_compra']) ?></td>
                            <td><?= htmlspecialchars($c['tipo_documento_compra']) ?></td>
                            <td><?= htmlspecialchars($c['folio_compra']) ?></td>
                            <td>
                                <?php if ($c['razon_social_proveedor']): ?>
                                    <strong><?= htmlspecialchars($c['razon_social_proveedor']) ?></strong>
                                    <?php if ($c['rut_proveedor']): ?>
                                        <div class="small-rut"><?= htmlspecialchars($c['rut_proveedor']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">(proveedor eliminado · id <?= (int)$c['proveedor_compra'] ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['nombre_categoria']): ?>
                                    <span class="pill-cat"><?= htmlspecialchars($c['nombre_categoria']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">(sin categoría)</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars(mb_substr($c['glosa_compra'] ?? '', 0, 50)) ?></td>
                            <td class="text-end"><?= pesos($c['neto_compra']) ?></td>
                            <td class="text-end"><?= pesos($c['iva_compra']) ?></td>
                            <td class="text-end"><?= pesos($c['exento_compra']) ?></td>
                            <td class="text-end"><strong><?= pesos($c['total_compra']) ?></strong></td>
                            <td><?= htmlspecialchars($c['estado_compra'] ?? '') ?></td>
                            <td>
                                <?php if ($c['id_asiento']): ?>
                                    <span class="badge badge-asiento" title="Asiento N° <?= (int)$c['numero_asiento'] ?>">✓ N° <?= (int)$c['numero_asiento'] ?></span>
                                <?php else: ?>
                                    <span class="badge badge-pendiente" title="Falta generar asiento">pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="totales-row">
                        <td colspan="6" class="text-end">Totales (sin anulados):</td>
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
            <a href="?mes=<?= (int)$mes ?>&anio=<?= (int)$anio ?>&formato=csv-sii" class="btn btn-success btn-sm ms-auto">
                ⬇ Descargar CSV (formato SII)
            </a>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
