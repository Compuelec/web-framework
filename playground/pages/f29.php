<?php
/**
 * Formulario 29 — declaración mensual de IVA (SII Chile).
 *
 * Este NO es un sistema de declaración electrónica al SII (eso requiere
 * certificado digital, firma XML, conexión al webservice del SII). Es una
 * página que calcula los códigos relevantes del F29 del mes elegido para
 * que el contador los copie al portal del SII al hacer la declaración real.
 *
 * Códigos calculados (subset usado por la mayoría de las pymes):
 *
 *   VENTAS / DÉBITOS
 *   502   N° de facturas afectas emitidas (afecta + nota débito)
 *   503   IVA Débito Fiscal de facturas afectas
 *   142   Ventas exentas y no gravadas
 *   110   N° de boletas emitidas
 *   111   Monto neto de boletas
 *   149   Monto total de boletas (incluye IVA)
 *   537   TOTAL DÉBITOS (suma de IVA cobrado)
 *
 *   COMPRAS / CRÉDITOS
 *   519   Facturas recibidas — número
 *   520   IVA Crédito Fiscal de facturas recibidas
 *   538   TOTAL CRÉDITOS (suma de IVA pagado)
 *
 *   RETENCIONES (boletas de honorarios recibidas)
 *   601   N° de boletas honorarios recibidas
 *   602   Monto bruto de honorarios
 *   077   Total retenciones a pagar al SII
 *
 *   IVA A PAGAR
 *   89    IVA Determinado = 537 - 538 (positivo = a pagar, negativo = remanente)
 *   91    Total a pagar = 89 + 077
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

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

$NOMBRES_MES = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$inicio = sprintf('%04d-%02d-01', $anio, $mes);
$fin    = date('Y-m-t', strtotime($inicio));

/* =========================================================================
   Cálculos — todos los queries excluyen los anulados y filtran por mes.
   ========================================================================= */
function scalarQuery(PDO $db, string $sql, array $params): float {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

$params = [':a' => $inicio, ':b' => $fin];

// Ventas / Débitos
$c502 = (int)scalarQuery($db,
    "SELECT COUNT(*) FROM comprobantes_venta
     WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'
       AND tipo_documento_venta IN ('factura_afecta','nota_debito')", $params);
$c503 = scalarQuery($db,
    "SELECT COALESCE(SUM(iva_venta),0) FROM comprobantes_venta
     WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'
       AND tipo_documento_venta IN ('factura_afecta','nota_debito')", $params);
$c142 = scalarQuery($db,
    "SELECT COALESCE(SUM(exento_venta),0) FROM comprobantes_venta
     WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'", $params);
$c110 = (int)scalarQuery($db,
    "SELECT COUNT(*) FROM comprobantes_venta
     WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'
       AND tipo_documento_venta = 'boleta'", $params);
$c111 = scalarQuery($db,
    "SELECT COALESCE(SUM(neto_venta),0) FROM comprobantes_venta
     WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'
       AND tipo_documento_venta = 'boleta'", $params);
$c149 = scalarQuery($db,
    "SELECT COALESCE(SUM(total_venta),0) FROM comprobantes_venta
     WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'
       AND tipo_documento_venta = 'boleta'", $params);
// 537 = IVA total emitido en el mes (afectas + boletas + notas débito - notas crédito)
$c537 = scalarQuery($db,
    "SELECT COALESCE(SUM(iva_venta),0) FROM comprobantes_venta
     WHERE fecha_venta BETWEEN :a AND :b AND estado_venta != 'anulado'", $params);

// Compras / Créditos
$c519 = (int)scalarQuery($db,
    "SELECT COUNT(*) FROM comprobantes_compra
     WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'
       AND tipo_documento_compra IN ('factura_afecta','nota_debito')", $params);
$c520 = scalarQuery($db,
    "SELECT COALESCE(SUM(iva_compra),0) FROM comprobantes_compra
     WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'
       AND tipo_documento_compra IN ('factura_afecta','nota_debito')", $params);
$c538 = scalarQuery($db,
    "SELECT COALESCE(SUM(iva_compra),0) FROM comprobantes_compra
     WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'", $params);

// Retenciones (honorarios recibidos)
$c601 = (int)scalarQuery($db,
    "SELECT COUNT(*) FROM comprobantes_compra
     WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'
       AND tipo_documento_compra = 'boleta_honorarios'", $params);
$c602 = scalarQuery($db,
    "SELECT COALESCE(SUM(exento_compra + neto_compra),0) FROM comprobantes_compra
     WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'
       AND tipo_documento_compra = 'boleta_honorarios'", $params);
$c077 = scalarQuery($db,
    "SELECT COALESCE(SUM(retencion_compra),0) FROM comprobantes_compra
     WHERE fecha_compra BETWEEN :a AND :b AND estado_compra != 'anulado'", $params);

// Determinación final
$c89 = $c537 - $c538;          // IVA determinado (+: a pagar, -: remanente)
$c91 = max(0, $c89) + $c077;   // Total a pagar (si hay remanente, solo retenciones)

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Formulario 29 — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .f29-wrap { max-width: 900px; margin: 0 auto; }
    .seccion { margin-bottom: 1.5rem; }
    .seccion-head {
        background: #0d6efd; color: #fff;
        padding: .5rem 1rem; border-radius: 6px 6px 0 0;
        font-weight: 600;
    }
    .seccion-head.ventas    { background: #198754; }
    .seccion-head.compras   { background: #dc3545; }
    .seccion-head.retencion { background: #fd7e14; }
    .seccion-head.resultado { background: #6f42c1; }
    .tabla-codigos { width: 100%; border: 1px solid #dee2e6; border-top: 0; }
    .tabla-codigos td { padding: .5rem 1rem; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .tabla-codigos .codigo {
        width: 80px; font-family: monospace; font-weight: 600;
        background: #f8f9fa; color: #495057; text-align: center;
    }
    .tabla-codigos .valor {
        width: 180px; text-align: right; font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .resultado-row td { font-size: 1.1rem; background: #f8f9fa; }
    .resultado-row td.valor { color: #0d6efd; }
    .a-pagar td.valor    { color: #b91c1c; }
    .remanente td.valor  { color: #198754; }

    @media print {
        body { background: white !important; }
        .no-print { display: none !important; }
        .seccion-head { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .container { max-width: 100% !important; }
    }
</style>
</head>
<body>
<?= wpb_render_user_bar() ?>
<div class="container py-4">
    <div class="f29-wrap">

        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h1 class="mb-0">Formulario 29</h1>
                <p class="text-muted mb-0">Declaración mensual de IVA — <?= $NOMBRES_MES[$mes] ?> <?= $anio ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="/dashboard-contable" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
                <button onclick="window.print()" class="btn btn-success btn-sm">🖨 Imprimir / PDF</button>
            </div>
        </div>

        <!-- Selector mes / año -->
        <form method="get" class="d-flex gap-2 mb-4 no-print">
            <select class="form-select" name="mes">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $mes ? 'selected' : '' ?>><?= $NOMBRES_MES[$m] ?></option>
                <?php endfor; ?>
            </select>
            <input type="number" class="form-control" style="width:120px" name="anio" value="<?= $anio ?>" min="2020" max="2099">
            <button class="btn btn-primary">Ver</button>
        </form>

        <!-- Header visible al imprimir -->
        <div class="d-none d-print-block mb-3">
            <h2 class="mb-1">Formulario 29 — <?= htmlspecialchars($siteName) ?></h2>
            <p class="mb-0 text-muted">Período tributario: <?= $NOMBRES_MES[$mes] ?> <?= $anio ?> · Generado: <?= date('Y-m-d H:i') ?></p>
        </div>

        <!-- VENTAS / DÉBITOS -->
        <div class="seccion">
            <div class="seccion-head ventas">Ventas / Débitos Fiscales</div>
            <table class="tabla-codigos">
                <tr><td class="codigo">502</td><td>Facturas afectas emitidas — N°</td><td class="valor"><?= (int)$c502 ?></td></tr>
                <tr><td class="codigo">503</td><td>IVA Débito de facturas afectas</td><td class="valor"><?= pesos($c503) ?></td></tr>
                <tr><td class="codigo">142</td><td>Ventas exentas y no gravadas</td><td class="valor"><?= pesos($c142) ?></td></tr>
                <tr><td class="codigo">110</td><td>Boletas emitidas — N°</td><td class="valor"><?= (int)$c110 ?></td></tr>
                <tr><td class="codigo">111</td><td>Boletas — monto neto</td><td class="valor"><?= pesos($c111) ?></td></tr>
                <tr><td class="codigo">149</td><td>Boletas — monto total (con IVA)</td><td class="valor"><?= pesos($c149) ?></td></tr>
                <tr class="resultado-row"><td class="codigo">537</td><td><strong>TOTAL DÉBITOS IVA</strong></td><td class="valor"><?= pesos($c537) ?></td></tr>
            </table>
        </div>

        <!-- COMPRAS / CRÉDITOS -->
        <div class="seccion">
            <div class="seccion-head compras">Compras / Créditos Fiscales</div>
            <table class="tabla-codigos">
                <tr><td class="codigo">519</td><td>Facturas recibidas — N°</td><td class="valor"><?= (int)$c519 ?></td></tr>
                <tr><td class="codigo">520</td><td>IVA Crédito de facturas recibidas</td><td class="valor"><?= pesos($c520) ?></td></tr>
                <tr class="resultado-row"><td class="codigo">538</td><td><strong>TOTAL CRÉDITOS IVA</strong></td><td class="valor"><?= pesos($c538) ?></td></tr>
            </table>
        </div>

        <!-- RETENCIONES -->
        <div class="seccion">
            <div class="seccion-head retencion">Retenciones (Boletas de Honorarios recibidas)</div>
            <table class="tabla-codigos">
                <tr><td class="codigo">601</td><td>Boletas de honorarios recibidas — N°</td><td class="valor"><?= (int)$c601 ?></td></tr>
                <tr><td class="codigo">602</td><td>Monto bruto de honorarios</td><td class="valor"><?= pesos($c602) ?></td></tr>
                <tr class="resultado-row"><td class="codigo">077</td><td><strong>Total Retenciones a Pagar al SII</strong></td><td class="valor"><?= pesos($c077) ?></td></tr>
            </table>
        </div>

        <!-- RESULTADO -->
        <div class="seccion">
            <div class="seccion-head resultado">Determinación del Impuesto</div>
            <table class="tabla-codigos">
                <tr class="resultado-row <?= $c89 >= 0 ? '' : 'remanente' ?>">
                    <td class="codigo">89</td>
                    <td>
                        <strong>IVA Determinado</strong>
                        <div class="small text-muted">Débitos (537) − Créditos (538)</div>
                    </td>
                    <td class="valor"><?= pesos($c89) ?>
                        <?php if ($c89 < 0): ?>
                            <div class="small text-success">Remanente para el mes siguiente</div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr class="resultado-row a-pagar">
                    <td class="codigo">91</td>
                    <td>
                        <strong>TOTAL A PAGAR</strong>
                        <div class="small text-muted">IVA Determinado (89, mínimo 0) + Retenciones (077)</div>
                    </td>
                    <td class="valor"><strong><?= pesos($c91) ?></strong></td>
                </tr>
            </table>
        </div>

        <!-- Recordatorios -->
        <div class="alert alert-info small no-print">
            <strong>Recordatorios:</strong>
            <ul class="mb-0">
                <li>El F29 se declara hasta el <strong>día 12</strong> del mes siguiente al período (o 20 si pagás con PEC).</li>
                <li>Este resumen es <em>solo para tu referencia</em> — la declaración legal se hace en <a href="https://www.sii.cl/" target="_blank">sii.cl</a> o vía un facturador electrónico.</li>
                <li>Si el código 89 te dio negativo, no hay IVA a pagar este mes; el remanente se descuenta del próximo período.</li>
                <li>Si hubo retenciones (077), igual debes pagarlas aunque el IVA dé remanente.</li>
            </ul>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
