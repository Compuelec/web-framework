<?php
/**
 * Validación contable — reporte on-demand de problemas comunes:
 *   1. Folios consecutivos en ventas (regla SII).
 *   2. Comprobantes sin asiento (orphans).
 *   3. Cuadre IVA = 19% del neto.
 *   4. Cuadre interno del comprobante (neto + IVA + exento == total).
 *   5. Estado vs asiento (anulado no debería tener asiento).
 *
 * Todas las queries son baratas; corremos todo on-load (sin caché, sin
 * botón). El contador entra a la página, ve el estado AHORA.
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
require_once __DIR__ . '/_lib/rut.php';
wpb_require_role(['contador', 'lectura']);

$db = Connection::connect();

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

/* =========================================================================
   1. Folios consecutivos (solo ventas — el SII obliga numeración secuencial
      sin saltos en los DOCUMENTOS EMITIDOS por la empresa).
   ========================================================================= */
$folioReport = []; // tipo => { folios: [...], gaps: [...], dupes: [...] }
if ($db) {
    try {
        $stmt = $db->query(
            "SELECT tipo_documento_venta, folio_venta
             FROM comprobantes_venta
             WHERE estado_venta != 'anulado' AND folio_venta > 0
             ORDER BY tipo_documento_venta, folio_venta"
        );
        $byTipo = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $byTipo[$r['tipo_documento_venta']][] = (int)$r['folio_venta'];
        }
        foreach ($byTipo as $tipo => $folios) {
            // Detect duplicates (a folio appearing 2+ times in the same tipo).
            $counts = array_count_values($folios);
            $dupes  = array_keys(array_filter($counts, fn($c) => $c > 1));
            sort($dupes);

            // Detect gaps: between min and max, list missing folios.
            $unique = array_values(array_unique($folios));
            sort($unique);
            $gaps = [];
            if (count($unique) >= 2) {
                $min = $unique[0]; $max = end($unique);
                $expected = range($min, $max);
                $missing  = array_diff($expected, $unique);
                if ($missing) {
                    // Group consecutive missing folios into ranges to avoid
                    // listing 100 individual numbers in the UI.
                    $missing = array_values($missing);
                    $start = $missing[0];
                    $prev  = $missing[0];
                    for ($i = 1; $i <= count($missing); $i++) {
                        $curr = $missing[$i] ?? null;
                        if ($curr !== $prev + 1) {
                            $gaps[] = $start === $prev ? (string)$start : ($start . '–' . $prev);
                            $start = $curr;
                        }
                        $prev = $curr;
                    }
                }
            }
            $folioReport[$tipo] = [
                'count' => count($folios),
                'min'   => $unique[0] ?? null,
                'max'   => end($unique) ?: null,
                'gaps'  => $gaps,
                'dupes' => $dupes,
            ];
        }
    } catch (Throwable $e) {}
}

/* =========================================================================
   2. Comprobantes sin asiento (orphans).
   ========================================================================= */
$orphansVenta  = [];
$orphansCompra = [];
if ($db) {
    try {
        $orphansVenta = $db->query(
            "SELECT v.id_venta, v.folio_venta, v.fecha_venta, v.tipo_documento_venta, v.total_venta,
                    cl.razon_social_cliente
             FROM comprobantes_venta v
             LEFT JOIN clientes cl ON cl.id_cliente = v.cliente_venta
             LEFT JOIN asientos a  ON a.origen_asiento = 'venta' AND a.origen_id_asiento = v.id_venta
             WHERE v.estado_venta != 'anulado' AND a.id_asiento IS NULL"
        )->fetchAll(PDO::FETCH_ASSOC);
        $orphansCompra = $db->query(
            "SELECT c.id_compra, c.folio_compra, c.fecha_compra, c.tipo_documento_compra, c.total_compra,
                    p.razon_social_proveedor
             FROM comprobantes_compra c
             LEFT JOIN proveedores p ON p.id_proveedor = c.proveedor_compra
             LEFT JOIN asientos a    ON a.origen_asiento = 'compra' AND a.origen_id_asiento = c.id_compra
             WHERE c.estado_compra != 'anulado' AND a.id_asiento IS NULL"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

/* =========================================================================
   3. Cuadre del IVA (19% del neto). Solo aplica a factura_afecta (venta y
      compra) + boleta (venta).
   ========================================================================= */
$ivaIssues = []; // each: { kind: venta|compra, id, folio, neto, iva, esperado }
if ($db) {
    try {
        $stmt = $db->query(
            "SELECT id_venta AS id, folio_venta AS folio, tipo_documento_venta AS tipo,
                    neto_venta AS neto, iva_venta AS iva
             FROM comprobantes_venta
             WHERE estado_venta != 'anulado'
               AND tipo_documento_venta IN ('factura_afecta','boleta')"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $esperado = round($r['neto'] * 0.19);
            if (abs($esperado - $r['iva']) > 1) {
                $ivaIssues[] = ['kind'=>'venta'] + $r + ['esperado'=>$esperado];
            }
        }
        $stmt = $db->query(
            "SELECT id_compra AS id, folio_compra AS folio, tipo_documento_compra AS tipo,
                    neto_compra AS neto, iva_compra AS iva
             FROM comprobantes_compra
             WHERE estado_compra != 'anulado'
               AND tipo_documento_compra = 'factura_afecta'"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $esperado = round($r['neto'] * 0.19);
            if (abs($esperado - $r['iva']) > 1) {
                $ivaIssues[] = ['kind'=>'compra'] + $r + ['esperado'=>$esperado];
            }
        }
    } catch (Throwable $e) {}
}

/* =========================================================================
   4. Cuadre interno del comprobante (neto + IVA + exento == total).
   ========================================================================= */
$sumaIssues = [];
if ($db) {
    try {
        $stmt = $db->query(
            "SELECT 'venta' AS kind, id_venta AS id, folio_venta AS folio,
                    neto_venta AS neto, iva_venta AS iva, exento_venta AS exento, total_venta AS total
             FROM comprobantes_venta
             WHERE estado_venta != 'anulado'
               AND ABS((neto_venta + iva_venta + exento_venta) - total_venta) > 1"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $sumaIssues[] = $r; }

        $stmt = $db->query(
            "SELECT 'compra' AS kind, id_compra AS id, folio_compra AS folio,
                    neto_compra AS neto, iva_compra AS iva, exento_compra AS exento, total_compra AS total
             FROM comprobantes_compra
             WHERE estado_compra != 'anulado'
               AND ABS((neto_compra + iva_compra + exento_compra) - total_compra) > 1"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $sumaIssues[] = $r; }
    } catch (Throwable $e) {}
}

/* =========================================================================
   5. Estado vs asiento — un anulado NO debería tener asiento validado.
   ========================================================================= */
$estadoIssues = [];
if ($db) {
    try {
        $stmt = $db->query(
            "SELECT 'venta' AS kind, v.id_venta AS id, v.folio_venta AS folio,
                    v.estado_venta AS estado, a.numero_asiento, a.estado_asiento
             FROM comprobantes_venta v
             JOIN asientos a ON a.origen_asiento = 'venta' AND a.origen_id_asiento = v.id_venta
             WHERE v.estado_venta = 'anulado' AND a.estado_asiento != 'anulado'"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $estadoIssues[] = $r; }
        $stmt = $db->query(
            "SELECT 'compra' AS kind, c.id_compra AS id, c.folio_compra AS folio,
                    c.estado_compra AS estado, a.numero_asiento, a.estado_asiento
             FROM comprobantes_compra c
             JOIN asientos a ON a.origen_asiento = 'compra' AND a.origen_id_asiento = c.id_compra
             WHERE c.estado_compra = 'anulado' AND a.estado_asiento != 'anulado'"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $estadoIssues[] = $r; }
    } catch (Throwable $e) {}
}

/* =========================================================================
   6. RUTs inválidos (clientes + proveedores). Aplica módulo 11 del SII.
      Listas vacías (sin RUT) NO cuentan como inválidas — son simplemente
      datos incompletos que el usuario puede completar después.
   ========================================================================= */
$rutIssues = []; // each: { kind: cliente|proveedor, id, razon_social, rut, dv_esperado }
if ($db) {
    try {
        $stmt = $db->query("SELECT id_cliente AS id, razon_social_cliente AS razon, rut_cliente AS rut FROM clientes");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (empty(trim($r['rut'] ?? ''))) { continue; }
            if (!rut_is_valid($r['rut'])) {
                $clean = rut_clean($r['rut']);
                $body  = substr($clean, 0, -1);
                $dv    = ctype_digit($body) ? rut_dv($body) : '?';
                $rutIssues[] = ['kind'=>'cliente'] + $r + ['dv_esperado' => $dv];
            }
        }
        $stmt = $db->query("SELECT id_proveedor AS id, razon_social_proveedor AS razon, rut_proveedor AS rut FROM proveedores");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (empty(trim($r['rut'] ?? ''))) { continue; }
            if (!rut_is_valid($r['rut'])) {
                $clean = rut_clean($r['rut']);
                $body  = substr($clean, 0, -1);
                $dv    = ctype_digit($body) ? rut_dv($body) : '?';
                $rutIssues[] = ['kind'=>'proveedor'] + $r + ['dv_esperado' => $dv];
            }
        }
    } catch (Throwable $e) {}
}

/* =========================================================================
   Resumen global
   ========================================================================= */
$totalProblemas = 0;
foreach ($folioReport as $r) { $totalProblemas += count($r['gaps']) + count($r['dupes']); }
$totalProblemas += count($orphansVenta) + count($orphansCompra)
                + count($ivaIssues) + count($sumaIssues) + count($estadoIssues)
                + count($rutIssues);

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Validación contable — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .seccion { margin-bottom: 1.5rem; }
    .seccion-head {
        display: flex; justify-content: space-between; align-items: center;
        padding: .65rem 1rem; border-radius: 6px 6px 0 0; font-weight: 600;
        color: #fff;
    }
    .seccion-head.ok    { background: #198754; }
    .seccion-head.warn  { background: #fd7e14; }
    .seccion-head.error { background: #dc3545; }
    .seccion-body { border: 1px solid #dee2e6; border-top: 0; border-radius: 0 0 6px 6px; padding: 1rem; }
    .resumen-box { padding: 1rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 500; }
    .resumen-box.ok    { background: #d1e7dd; color: #0a3622; border-left: 4px solid #198754; }
    .resumen-box.warn  { background: #fff3cd; color: #664d03; border-left: 4px solid #fd7e14; }
    .resumen-box.error { background: #f8d7da; color: #58151c; border-left: 4px solid #dc3545; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: .82rem; background: #e9ecef; color: #495057; margin-right: 4px; }
    .pill.bad { background: #f8d7da; color: #842029; }
    table.compact td, table.compact th { padding: .35rem .6rem; font-size: .9rem; }
    code { background: #f1f3f5; padding: 1px 5px; border-radius: 3px; }
</style>
</head>
<body>
<?= wpb_render_user_bar() ?>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Validación contable</h1>
            <p class="text-muted mb-0">Reporte automático de problemas frecuentes. Actualizado al cargar la página.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/dashboard-contable" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
            <a href="/validacion" class="btn btn-outline-primary btn-sm">↻ Recargar</a>
        </div>
    </div>

    <?php $tipoResumen = $totalProblemas === 0 ? 'ok' : ($totalProblemas <= 3 ? 'warn' : 'error'); ?>
    <div class="resumen-box <?= $tipoResumen ?>">
        <?php if ($totalProblemas === 0): ?>
            ✓ Todo cuadra — no se detectaron problemas.
        <?php else: ?>
            ⚠ <strong><?= $totalProblemas ?> problema(s) detectado(s).</strong>
            Revisá las secciones marcadas en naranja o rojo abajo.
        <?php endif; ?>
    </div>

    <!--========================== 1. Folios ========================== -->
    <div class="seccion">
        <?php
            $folioProblemas = 0;
            foreach ($folioReport as $r) { $folioProblemas += count($r['gaps']) + count($r['dupes']); }
            $cls = $folioProblemas === 0 ? 'ok' : 'warn';
        ?>
        <div class="seccion-head <?= $cls ?>">
            <span>1. Folios consecutivos en ventas</span>
            <span><?= $folioProblemas === 0 ? '✓ sin saltos ni duplicados' : '⚠ ' . $folioProblemas . ' problema(s)' ?></span>
        </div>
        <div class="seccion-body">
            <p class="small text-muted mb-2">
                El SII obliga a que los documentos emitidos por la empresa (facturas, boletas, etc.)
                tengan folios consecutivos sin saltos. Cada tipo de documento mantiene su propia serie.
            </p>
            <?php if (!$folioReport): ?>
                <p class="text-muted mb-0">No hay comprobantes de venta cargados todavía.</p>
            <?php else: ?>
                <table class="table compact mb-0">
                    <thead><tr><th>Tipo</th><th>Cantidad</th><th>Rango</th><th>Saltos (folios faltantes)</th><th>Duplicados</th></tr></thead>
                    <tbody>
                    <?php foreach ($folioReport as $tipo => $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($tipo) ?></td>
                            <td><?= (int)$r['count'] ?></td>
                            <td><?= $r['min'] !== null ? ($r['min'] . ' – ' . $r['max']) : '—' ?></td>
                            <td>
                                <?php if (!$r['gaps']): ?>
                                    <span class="pill">sin saltos</span>
                                <?php else: foreach ($r['gaps'] as $g): ?>
                                    <span class="pill bad"><?= htmlspecialchars($g) ?></span>
                                <?php endforeach; endif; ?>
                            </td>
                            <td>
                                <?php if (!$r['dupes']): ?>
                                    <span class="pill">ninguno</span>
                                <?php else: foreach ($r['dupes'] as $d): ?>
                                    <span class="pill bad"><?= (int)$d ?></span>
                                <?php endforeach; endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!--========================== 2. Sin asiento ========================== -->
    <div class="seccion">
        <?php
            $orfanos = count($orphansVenta) + count($orphansCompra);
            $cls = $orfanos === 0 ? 'ok' : 'warn';
        ?>
        <div class="seccion-head <?= $cls ?>">
            <span>2. Comprobantes sin asiento contable</span>
            <span><?= $orfanos === 0 ? '✓ todo contabilizado' : '⚠ ' . $orfanos . ' pendiente(s)' ?></span>
        </div>
        <div class="seccion-body">
            <p class="small text-muted mb-2">
                Comprobantes activos (no anulados) que no tienen su asiento doble partida generado.
                Andá a <a href="/generar-asientos">generar asientos</a> para procesarlos.
            </p>
            <?php if (!$orfanos): ?>
                <p class="mb-0 text-success">✓ Todos los comprobantes activos tienen asiento.</p>
            <?php else: ?>
                <table class="table compact mb-0">
                    <thead><tr><th>Origen</th><th>ID</th><th>Folio</th><th>Fecha</th><th>Cliente / proveedor</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($orphansVenta as $v): ?>
                        <tr>
                            <td><span class="pill">venta</span></td>
                            <td>#<?= (int)$v['id_venta'] ?></td>
                            <td><?= htmlspecialchars($v['folio_venta']) ?></td>
                            <td><?= htmlspecialchars($v['fecha_venta']) ?></td>
                            <td><?= htmlspecialchars($v['razon_social_cliente'] ?? '(sin cliente)') ?></td>
                            <td class="text-end"><?= pesos($v['total_venta']) ?></td>
                        </tr>
                    <?php endforeach; foreach ($orphansCompra as $c): ?>
                        <tr>
                            <td><span class="pill">compra</span></td>
                            <td>#<?= (int)$c['id_compra'] ?></td>
                            <td><?= htmlspecialchars($c['folio_compra']) ?></td>
                            <td><?= htmlspecialchars($c['fecha_compra']) ?></td>
                            <td><?= htmlspecialchars($c['razon_social_proveedor'] ?? '(sin proveedor)') ?></td>
                            <td class="text-end"><?= pesos($c['total_compra']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!--========================== 3. IVA 19% ========================== -->
    <div class="seccion">
        <?php $cls = $ivaIssues ? 'error' : 'ok'; ?>
        <div class="seccion-head <?= $cls ?>">
            <span>3. Cuadre del IVA (19% del neto)</span>
            <span><?= $ivaIssues ? '🛑 ' . count($ivaIssues) . ' fila(s) descuadradas' : '✓ todo OK' ?></span>
        </div>
        <div class="seccion-body">
            <p class="small text-muted mb-2">
                En documentos afectos (factura afecta, boleta), el IVA debe ser exactamente
                el 19% del neto. Tolerancia de $1 por redondeo.
            </p>
            <?php if (!$ivaIssues): ?>
                <p class="mb-0 text-success">✓ Todos los documentos afectos tienen IVA correcto.</p>
            <?php else: ?>
                <table class="table compact mb-0">
                    <thead><tr><th>Origen</th><th>ID</th><th>Folio</th><th>Tipo</th><th class="text-end">Neto</th><th class="text-end">IVA cargado</th><th class="text-end">IVA esperado</th><th class="text-end">Diferencia</th></tr></thead>
                    <tbody>
                    <?php foreach ($ivaIssues as $i): ?>
                        <tr>
                            <td><span class="pill"><?= htmlspecialchars($i['kind']) ?></span></td>
                            <td>#<?= (int)$i['id'] ?></td>
                            <td><?= htmlspecialchars($i['folio']) ?></td>
                            <td><?= htmlspecialchars($i['tipo']) ?></td>
                            <td class="text-end"><?= pesos($i['neto']) ?></td>
                            <td class="text-end"><?= pesos($i['iva']) ?></td>
                            <td class="text-end"><?= pesos($i['esperado']) ?></td>
                            <td class="text-end text-danger"><?= pesos($i['iva'] - $i['esperado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!--========================== 4. Suma interna ========================== -->
    <div class="seccion">
        <?php $cls = $sumaIssues ? 'error' : 'ok'; ?>
        <div class="seccion-head <?= $cls ?>">
            <span>4. Cuadre interno del comprobante (neto + IVA + exento = total)</span>
            <span><?= $sumaIssues ? '🛑 ' . count($sumaIssues) . ' fila(s) descuadradas' : '✓ todo OK' ?></span>
        </div>
        <div class="seccion-body">
            <p class="small text-muted mb-2">
                El monto Total debe ser exactamente la suma de Neto + IVA + Exento.
                Si no cuadra, el comprobante quedó mal cargado y no se puede generar el asiento.
            </p>
            <?php if (!$sumaIssues): ?>
                <p class="mb-0 text-success">✓ Todos los comprobantes cuadran internamente.</p>
            <?php else: ?>
                <table class="table compact mb-0">
                    <thead><tr><th>Origen</th><th>ID</th><th>Folio</th><th class="text-end">Neto</th><th class="text-end">IVA</th><th class="text-end">Exento</th><th class="text-end">Suma esperada</th><th class="text-end">Total cargado</th><th class="text-end">Diferencia</th></tr></thead>
                    <tbody>
                    <?php foreach ($sumaIssues as $s):
                        $sumaCalc = $s['neto'] + $s['iva'] + $s['exento'];
                    ?>
                        <tr>
                            <td><span class="pill"><?= htmlspecialchars($s['kind']) ?></span></td>
                            <td>#<?= (int)$s['id'] ?></td>
                            <td><?= htmlspecialchars($s['folio']) ?></td>
                            <td class="text-end"><?= pesos($s['neto']) ?></td>
                            <td class="text-end"><?= pesos($s['iva']) ?></td>
                            <td class="text-end"><?= pesos($s['exento']) ?></td>
                            <td class="text-end"><?= pesos($sumaCalc) ?></td>
                            <td class="text-end"><?= pesos($s['total']) ?></td>
                            <td class="text-end text-danger"><?= pesos($s['total'] - $sumaCalc) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!--========================== 5. Estado vs asiento ========================== -->
    <div class="seccion">
        <?php $cls = $estadoIssues ? 'error' : 'ok'; ?>
        <div class="seccion-head <?= $cls ?>">
            <span>5. Comprobantes anulados con asiento activo</span>
            <span><?= $estadoIssues ? '🛑 ' . count($estadoIssues) . ' inconsistencia(s)' : '✓ todo OK' ?></span>
        </div>
        <div class="seccion-body">
            <p class="small text-muted mb-2">
                Si un comprobante está anulado, su asiento contable también debería estarlo
                (sino sus montos siguen impactando en el balance).
            </p>
            <?php if (!$estadoIssues): ?>
                <p class="mb-0 text-success">✓ Estados consistentes.</p>
            <?php else: ?>
                <table class="table compact mb-0">
                    <thead><tr><th>Origen</th><th>ID</th><th>Folio</th><th>Estado comprobante</th><th>Asiento N°</th><th>Estado asiento</th></tr></thead>
                    <tbody>
                    <?php foreach ($estadoIssues as $e): ?>
                        <tr>
                            <td><span class="pill"><?= htmlspecialchars($e['kind']) ?></span></td>
                            <td>#<?= (int)$e['id'] ?></td>
                            <td><?= htmlspecialchars($e['folio']) ?></td>
                            <td><span class="pill bad"><?= htmlspecialchars($e['estado']) ?></span></td>
                            <td>N° <?= (int)$e['numero_asiento'] ?></td>
                            <td><span class="pill bad"><?= htmlspecialchars($e['estado_asiento']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!--========================== 6. RUTs inválidos ========================== -->
    <div class="seccion">
        <?php $cls = $rutIssues ? 'error' : 'ok'; ?>
        <div class="seccion-head <?= $cls ?>">
            <span>6. RUTs inválidos (clientes + proveedores)</span>
            <span><?= $rutIssues ? '🛑 ' . count($rutIssues) . ' RUT(s) con dígito verificador incorrecto' : '✓ todos los RUTs cargados son válidos' ?></span>
        </div>
        <div class="seccion-body">
            <p class="small text-muted mb-2">
                Validación oficial del SII por módulo 11. Detecta cuando alguien
                tipeó mal el dígito verificador (el número/letra después del guión).
                Los registros sin RUT no aparecen acá — son datos incompletos, no errores.
            </p>
            <?php if (!$rutIssues): ?>
                <p class="mb-0 text-success">✓ Todos los RUTs cargados pasan la validación del SII.</p>
            <?php else: ?>
                <table class="table compact mb-0">
                    <thead><tr><th>Origen</th><th>ID</th><th>Razón social</th><th>RUT cargado</th><th>DV esperado</th></tr></thead>
                    <tbody>
                    <?php foreach ($rutIssues as $r): ?>
                        <tr>
                            <td><span class="pill"><?= htmlspecialchars($r['kind']) ?></span></td>
                            <td>#<?= (int)$r['id'] ?></td>
                            <td><?= htmlspecialchars($r['razon'] ?? '—') ?></td>
                            <td><code><?= htmlspecialchars($r['rut']) ?></code></td>
                            <td><strong class="text-success"><?= htmlspecialchars($r['dv_esperado']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="small text-muted mt-2 mb-0">
                    Para corregir: andá al CMS (sección Clientes / Proveedores), abrí el registro y reemplazá el DV con el sugerido.
                </p>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
