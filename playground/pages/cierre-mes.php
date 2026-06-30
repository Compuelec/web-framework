<?php
/**
 * Cierre de mes — bloqueo de un período una vez declarado al SII.
 *
 * Flujo:
 *  - Form para elegir mes/año a cerrar. Al elegir, corre el chequeo
 *    pre-cierre (orphans, sumas descuadradas, asientos descuadrados).
 *  - Si hay problemas, lista los problemas y NO permite cerrar.
 *  - Si todo está OK, muestra el botón "Cerrar mes" + un textarea opcional
 *    para notas (ej. "F29 mayo subido el 12/06").
 *  - Tabla con el historial de meses cerrados. Cada fila tiene un botón
 *    "Reabrir" que solo aparece para superadmin (caso excepcional).
 *
 * Permisos:
 *  - contador: puede cerrar meses.
 *  - superadmin/admin: además puede reabrir.
 *  - lectura: solo ve el historial.
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
require_once __DIR__ . '/_lib/cierres.php';
wpb_require_role(['contador', 'lectura']);
$db = Connection::connect();

$user = wpb_current_user();
$rol  = $user['role'] ?? '';
$puedeCerrar  = in_array($rol, ['contador', 'admin', 'superadmin'], true);
$puedeReabrir = in_array($rol, ['admin', 'superadmin'], true);

$NOMBRES_MES = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$flash = null;
$mesElegido  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anioElegido = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

/* =========================================================================
   Acciones (POST)
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    wpb_csrf_check();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cerrar' && $puedeCerrar) {
        $mes  = (int)($_POST['mes']  ?? 0);
        $anio = (int)($_POST['anio'] ?? 0);
        $notas = trim((string)($_POST['notas'] ?? ''));

        // Re-chequear precierre antes de escribir (defensa contra TOCTOU)
        $problemas = chequeo_precierre($db, $mes, $anio);
        if ($problemas) {
            $flash = ['type' => 'danger', 'msg' => 'No se puede cerrar: ' . implode(' ', $problemas)];
        } else {
            $res = cerrar_mes($db, $mes, $anio, $user['email'] ?? '?', $notas);
            if (isset($res['error'])) {
                $flash = ['type' => 'danger', 'msg' => $res['error']];
            } else {
                $flash = ['type' => 'success',
                    'msg' => sprintf('Mes %s %d cerrado. Ningún comprobante de ese período se puede editar más.',
                                     $NOMBRES_MES[$mes] ?? '?', $anio)];
                $mesElegido = $mes; $anioElegido = $anio;
            }
        }
    }

    if ($accion === 'reabrir' && $puedeReabrir) {
        $mes  = (int)($_POST['mes']  ?? 0);
        $anio = (int)($_POST['anio'] ?? 0);
        $res = reabrir_mes($db, $mes, $anio);
        if (isset($res['error'])) {
            $flash = ['type' => 'danger', 'msg' => $res['error']];
        } else {
            $flash = ['type' => 'warning',
                'msg' => sprintf('Mes %s %d reabierto. Ya se pueden cargar/editar comprobantes de ese período.',
                                 $NOMBRES_MES[$mes] ?? '?', $anio)];
        }
    }
}

/* =========================================================================
   Estado actual
   ========================================================================= */
$historial = $db ? meses_cerrados($db) : [];
$problemasMesActual = $db ? chequeo_precierre($db, $mesElegido, $anioElegido) : [];
$mesYaCerrado = $db ? mes_esta_cerrado($db, sprintf('%04d-%02d-01', $anioElegido, $mesElegido)) : false;

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cierre de mes — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .card-cierre { max-width: 760px; margin: 0 auto; }
    .seccion { margin-bottom: 1.5rem; }
    .check-line { padding: .35rem 0; }
    .check-ok    { color: #198754; }
    .check-fail  { color: #b91c1c; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: .82rem; background: #e9ecef; color: #495057; }
    .pill.cerrado { background: #f8d7da; color: #842029; }
    .pill.abierto { background: #d1e7dd; color: #0a3622; }
    table.compact td, table.compact th { padding: .5rem .75rem; font-size: .92rem; }
</style>
</head>
<body>
<?= wpb_render_user_bar() ?>
<div class="container py-4">
    <div class="card-cierre">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">Cierre de mes</h1>
                <p class="text-muted mb-0">Bloquea un período una vez declarado al SII.</p>
            </div>
            <a href="/dashboard-contable" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif; ?>

        <!-- Selector de mes/año -->
        <form method="get" class="d-flex gap-2 mb-4">
            <select class="form-select" name="mes">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $mesElegido ? 'selected' : '' ?>><?= $NOMBRES_MES[$m] ?></option>
                <?php endfor; ?>
            </select>
            <input type="number" class="form-control" style="width:120px" name="anio" value="<?= $anioElegido ?>" min="2020" max="2099">
            <button class="btn btn-primary">Ver chequeos</button>
        </form>

        <!-- Estado del mes elegido -->
        <div class="seccion">
            <h5 class="mb-3">
                <?= $NOMBRES_MES[$mesElegido] ?> <?= $anioElegido ?>
                <?php if ($mesYaCerrado): ?>
                    <span class="pill cerrado ms-2">CERRADO</span>
                <?php else: ?>
                    <span class="pill abierto ms-2">abierto</span>
                <?php endif; ?>
            </h5>

            <?php if ($mesYaCerrado): ?>
                <div class="alert alert-info mb-3">
                    Este mes ya está cerrado. No se pueden cargar, editar o anular comprobantes de este período.
                </div>
                <?php if ($puedeReabrir): ?>
                    <form method="post" onsubmit="return confirm('¿Reabrir <?= $NOMBRES_MES[$mesElegido] ?> <?= $anioElegido ?>? Los comprobantes del mes vuelven a ser editables.')">
                        <?= wpb_csrf_field() ?>
                        <input type="hidden" name="accion" value="reabrir">
                        <input type="hidden" name="mes"  value="<?= $mesElegido ?>">
                        <input type="hidden" name="anio" value="<?= $anioElegido ?>">
                        <button class="btn btn-outline-warning btn-sm">⚠ Reabrir mes (admin)</button>
                    </form>
                <?php endif; ?>

            <?php elseif (!$puedeCerrar): ?>
                <div class="alert alert-secondary mb-3">
                    Tu rol "<?= htmlspecialchars($rol) ?>" no puede cerrar meses. Solo el contador (o un admin) puede.
                </div>

            <?php else: ?>
                <p class="text-muted small mb-2">Antes de cerrar el mes, todos estos chequeos tienen que pasar:</p>
                <div class="border rounded p-3 mb-3">
                <?php if (!$problemasMesActual): ?>
                    <div class="check-line check-ok">✓ Todos los comprobantes del mes tienen su asiento.</div>
                    <div class="check-line check-ok">✓ Las sumas neto + IVA + exento = total cuadran.</div>
                    <div class="check-line check-ok">✓ Todos los asientos del mes tienen Σ Debe = Σ Haber.</div>
                <?php else: ?>
                    <?php foreach ($problemasMesActual as $p): ?>
                        <div class="check-line check-fail">✗ <?= htmlspecialchars($p) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>

                <?php if (!$problemasMesActual): ?>
                    <form method="post" onsubmit="return confirm('¿Cerrar <?= $NOMBRES_MES[$mesElegido] ?> <?= $anioElegido ?>? Después de esto los comprobantes del mes NO se podrán editar (salvo que un admin reabra).')">
                        <?= wpb_csrf_field() ?>
                        <input type="hidden" name="accion" value="cerrar">
                        <input type="hidden" name="mes"  value="<?= $mesElegido ?>">
                        <input type="hidden" name="anio" value="<?= $anioElegido ?>">
                        <div class="mb-3">
                            <label class="form-label" for="notas">Notas (opcional)</label>
                            <textarea class="form-control" id="notas" name="notas" rows="2" placeholder="Ej. F29 declarado el 12/07/2026, comprobante CAF 4823"></textarea>
                        </div>
                        <button class="btn btn-success">✓ Cerrar mes</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted small mb-0">Corrigí los problemas (desde el CMS o las páginas correspondientes) y volvé a esta página.
                        Tip: andá a <a href="/validacion">validación contable</a> para verlos todos.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Historial -->
        <div class="seccion">
            <h5 class="mb-3">Historial de cierres</h5>
            <?php if (!$historial): ?>
                <p class="text-muted small mb-0">Ningún mes cerrado todavía.</p>
            <?php else: ?>
                <table class="table table-sm compact mb-0">
                    <thead><tr><th>Período</th><th>Cerrado el</th><th>Por</th><th>Notas</th><?php if ($puedeReabrir): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($historial as $h): ?>
                        <tr>
                            <td><strong><?= $NOMBRES_MES[(int)$h['mes_cierre']] ?? '?' ?> <?= (int)$h['anio_cierre'] ?></strong></td>
                            <td class="small text-muted"><?= htmlspecialchars($h['fecha_cierre'] ?? '') ?></td>
                            <td class="small"><?= htmlspecialchars($h['usuario_cierre'] ?? '') ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($h['notas_cierre'] ?? '') ?: '—' ?></td>
                            <?php if ($puedeReabrir): ?>
                                <td>
                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Reabrir <?= $NOMBRES_MES[(int)$h['mes_cierre']] ?> <?= (int)$h['anio_cierre'] ?>?')">
                                        <?= wpb_csrf_field() ?>
                                        <input type="hidden" name="accion" value="reabrir">
                                        <input type="hidden" name="mes"  value="<?= (int)$h['mes_cierre'] ?>">
                                        <input type="hidden" name="anio" value="<?= (int)$h['anio_cierre'] ?>">
                                        <button class="btn btn-link btn-sm text-warning p-0">reabrir</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
