<?php
/**
 * Generar link de pago Payku — toma una venta y crea una orden de cobro
 * online en Payku para enviar al cliente.
 *
 * Modo real: si el plugin Payku está habilitado (config.enabled = true y
 * token configurado), llama a PaykuPlugin::processPayment() que devuelve
 * una URL de checkout de Payku. Guardamos esa URL en
 * comprobantes_venta.link_pago_venta y la mostramos para copiar.
 *
 * Modo simulado: si Payku no está enchufado, generamos una URL ficticia
 * /pago-simulado?token=<random> que sirve solo para demostrar el flujo.
 * El cobro real se sigue cargando manualmente desde /cargar-cobro.
 *
 * El webhook real de Payku (plugins/payku/webhook-payku.php) marca la
 * orden como `completed` cuando el cliente paga. Un job/cron debería
 * traducir ese cambio a un cobro+asiento; para el playground, queda como
 * TODO con un endpoint dummy.
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
wpb_require_role(['contador']);
$db = Connection::connect();

function pesos($n) { return '$ ' . number_format((float)$n, 0, ',', '.'); }

$ventaId = (int)($_GET['venta'] ?? $_POST['venta'] ?? 0);
$venta   = null;
$flash   = null;
$link    = null;
$paykuEnabled = false;
$paykuReason  = '';

// Detect Payku readiness without requiring the plugin if not present.
$paykuLoader = __DIR__ . '/../../plugins/payku/payku.php';
if (file_exists($paykuLoader)) {
    require_once $paykuLoader;
    if (class_exists('PaykuPlugin')) {
        $cfg = PaykuPlugin::getConfig();
        if (!empty($cfg['enabled']) && !empty(trim($cfg['token_publico'] ?? ''))) {
            $paykuEnabled = true;
        } else {
            $paykuReason = empty($cfg['enabled'])
                ? 'El plugin Payku está instalado pero deshabilitado.'
                : 'Falta configurar token_publico.';
        }
    } else {
        $paykuReason = 'PaykuPlugin class no se cargó (posible error en plugin).';
    }
} else {
    $paykuReason = 'Plugin Payku no instalado.';
}

if ($db && $ventaId > 0) {
    $stmt = $db->prepare(
        "SELECT v.*, c.razon_social_cliente, c.rut_cliente
         FROM comprobantes_venta v
         LEFT JOIN clientes c ON c.id_cliente = v.cliente_venta
         WHERE v.id_venta = :id LIMIT 1"
    );
    $stmt->execute([':id' => $ventaId]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $venta) {
    wpb_csrf_check();
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash = ['type' => 'danger', 'msg' => 'Email del cliente inválido.'];
    } else {
        // Compute saldo pendiente (no permitas generar link por más de lo que se debe).
        $saldoStmt = $db->prepare(
            "SELECT v.total_venta
                    - COALESCE((SELECT SUM(co.monto_cobro) FROM cobros co
                                WHERE co.venta_cobro = v.id_venta AND co.estado_cobro = 'registrado'), 0)
                    AS saldo
             FROM comprobantes_venta v WHERE v.id_venta = :id"
        );
        $saldoStmt->execute([':id' => $ventaId]);
        $saldo = (float)($saldoStmt->fetchColumn() ?: 0);
        if ($saldo <= 0) {
            $flash = ['type' => 'warning', 'msg' => 'Esta venta no tiene saldo pendiente.'];
        } else {
            $orderId = 'V' . $venta['id_venta'] . '-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            if ($paykuEnabled) {
                // Paykulib::getproducts() espera array de productos con
                // 'name' y 'quantity' (o el objeto equivalente). Pasar un
                // string crashea con TypeError en count().
                $res = PaykuPlugin::processPayment([
                    'order_id' => $orderId,
                    'email'    => $email,
                    'amount'   => $saldo,
                    'currency' => 'CLP',
                    'products' => [[
                        'name'     => $venta['tipo_documento_venta'] . ' N° ' . $venta['folio_venta'],
                        'quantity' => 1,
                    ]],
                ]);
                if (!empty($res['success']) && !empty($res['redirect_url'])) {
                    $link = $res['redirect_url'];
                    $db->prepare("UPDATE comprobantes_venta SET link_pago_venta = :l WHERE id_venta = :id")
                       ->execute([':l' => $link, ':id' => $ventaId]);
                    $flash = ['type' => 'success', 'msg' => 'Link de pago Payku generado.'];
                } else {
                    $flash = ['type' => 'danger', 'msg' => 'Error de Payku: ' . ($res['error'] ?? 'sin detalle')];
                }
            } else {
                // Modo simulado: URL ficticia para demostrar el flujo.
                $base = isset($siteCfg['site']['base_url']) ? rtrim($siteCfg['site']['base_url'], '/') : '';
                $link = $base . '/pago-simulado?order=' . urlencode($orderId);
                $db->prepare("UPDATE comprobantes_venta SET link_pago_venta = :l WHERE id_venta = :id")
                   ->execute([':l' => $link, ':id' => $ventaId]);
                $flash = ['type' => 'info', 'msg' => 'Link simulado generado (Payku no está enchufado). ' . $paykuReason];
            }
        }
    }
}

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Generar link de pago — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .card-wrap { max-width: 640px; margin: 0 auto; }
    .link-box {
        background: #e7f5ee; border: 1px solid #198754; border-radius: 6px;
        padding: 1rem; font-family: ui-monospace, monospace; word-break: break-all;
    }
    .status-pill {
        display: inline-block; padding: 2px 10px; border-radius: 12px;
        font-size: .8rem; font-weight: 600;
    }
    .status-on  { background: #d1e7dd; color: #0a3622; }
    .status-off { background: #fff3cd; color: #664d03; }
</style>
</head>
<body>
<?= wpb_render_user_bar() ?>
<div class="container py-4">
    <div class="card-wrap">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">Generar link de pago</h1>
            <a href="/libro-ventas" class="btn btn-outline-secondary btn-sm">← Libro de ventas</a>
        </div>

        <p>
            Estado Payku:
            <?php if ($paykuEnabled): ?>
                <span class="status-pill status-on">✓ habilitado</span>
            <?php else: ?>
                <span class="status-pill status-off">modo simulado</span>
                <small class="text-muted">· <?= htmlspecialchars($paykuReason) ?></small>
            <?php endif; ?>
        </p>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif; ?>

        <?php if (!$venta): ?>
            <div class="alert alert-warning">No se encontró la venta. Pasá <code>?venta=&lt;id&gt;</code> en la URL.</div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-1"><?= htmlspecialchars($venta['tipo_documento_venta']) ?> N° <?= htmlspecialchars($venta['folio_venta']) ?></h5>
                    <p class="text-muted mb-2"><?= htmlspecialchars($venta['razon_social_cliente'] ?? '(cliente eliminado)') ?> · <?= htmlspecialchars($venta['rut_cliente'] ?? '') ?></p>
                    <p class="mb-0">Total a cobrar: <strong><?= pesos($venta['total_venta']) ?></strong></p>
                </div>
            </div>

            <?php if (!$link): ?>
                <form method="post">
                    <?= wpb_csrf_field() ?>
                    <input type="hidden" name="venta" value="<?= (int)$venta['id_venta'] ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email del cliente</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               placeholder="cliente@ejemplo.cl">
                        <div class="form-text">Payku envía la confirmación de pago a este email.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Generar link de pago</button>
                </form>
            <?php else: ?>
                <p>Link generado — copiá y enviá al cliente:</p>
                <div class="link-box mb-3"><?= htmlspecialchars($link) ?></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm"
                            onclick="navigator.clipboard.writeText('<?= htmlspecialchars($link, ENT_QUOTES) ?>'); this.textContent='✓ copiado';">
                        📋 Copiar link
                    </button>
                    <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Abrir</a>
                    <a href="/cargar-cobro" class="btn btn-link btn-sm ms-auto">Cargar cobro manual →</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
