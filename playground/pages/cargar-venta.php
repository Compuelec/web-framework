<?php
/**
 * Cargar venta — formulario optimizado para que el contador cargue una
 * factura/boleta en un solo paso. Al confirmar:
 *   1. Inserta el comprobante en `comprobantes_venta`.
 *   2. Si subió un PDF/imagen, lo guarda en web/uploads/ y deja la URL en
 *      `archivo_venta`.
 *   3. Genera el asiento contable y sus líneas.
 *
 * Si cualquier paso falla, se hace rollback de la BD y NO queda comprobante
 * huérfano sin asiento.
 *
 * No login. Cuando agreguemos roles, esta página debería ir bajo el rol
 * `contador`.
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

require_once __DIR__ . '/_lib/asientos.php';
$db = Connection::connect();

/* =========================================================================
   Helpers
   ========================================================================= */

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

/**
 * Stores an uploaded file in web/uploads/<random>.<ext> and returns the
 * public URL. Whitelisted extensions only. Returns null if no file or
 * upload failed.
 */
function guardarArchivo(string $field): ?string {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $name = $_FILES[$field]['name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['pdf','jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed, true)) {
        return null; // silently ignore — caller can detect by checking the column
    }
    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }
    $newName = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!@move_uploaded_file($_FILES[$field]['tmp_name'], $uploadsDir . '/' . $newName)) {
        return null;
    }
    return '/web/uploads/' . $newName;
}

/* =========================================================================
   Acción: guardar
   ========================================================================= */

$flash       = null;
$valoresPrev = $_POST; // si falla la validación, repintamos el form con lo que escribió

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    // Sanitize inputs
    $tipo     = trim($_POST['tipo_documento'] ?? '');
    $folio    = (int)($_POST['folio'] ?? 0);
    $fecha    = trim($_POST['fecha'] ?? '');
    $cliente  = (int)($_POST['cliente'] ?? 0);
    $glosa    = trim($_POST['glosa'] ?? '');
    $neto     = (float)str_replace(['.', ','], ['', '.'], (string)($_POST['neto'] ?? '0'));
    $iva      = (float)str_replace(['.', ','], ['', '.'], (string)($_POST['iva'] ?? '0'));
    $exento   = (float)str_replace(['.', ','], ['', '.'], (string)($_POST['exento'] ?? '0'));
    $total    = $neto + $iva + $exento;
    $estado   = trim($_POST['estado'] ?? 'emitido');

    // Validations
    $errors = [];
    if (!preg_match('/^(factura_afecta|factura_exenta|boleta|nota_credito|nota_debito)$/', $tipo)) {
        $errors[] = 'Tipo de documento inválido.';
    }
    if ($folio <= 0)                       { $errors[] = 'Folio debe ser un número positivo.'; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { $errors[] = 'Fecha inválida.'; }
    if ($cliente <= 0)                     { $errors[] = 'Tenés que elegir un cliente.'; }
    if ($total <= 0)                       { $errors[] = 'El total debe ser mayor que cero.'; }
    if (!in_array($estado, ['emitido','anulado'], true)) { $errors[] = 'Estado inválido.'; }

    // Hard validations to prevent rows that would later fail to produce a
    // balanced asiento. Skipped when estado='anulado' since that path
    // doesn't generate an asiento.
    if ($estado !== 'anulado') {
        // Factura exenta no lleva IVA por ley.
        if ($tipo === 'factura_exenta' && $iva != 0) {
            $errors[] = 'Una factura exenta no lleva IVA. Pasá el monto a "Exento" y dejá el IVA en 0.';
        }
        // Factura afecta / boleta: el IVA tiene que ser ~19% del neto (tolerancia $1).
        if ($tipo === 'factura_afecta' || $tipo === 'boleta') {
            $ivaEsperado = round($neto * 0.19);
            if (abs($ivaEsperado - $iva) > 1) {
                $errors[] = sprintf(
                    'El IVA debería ser %s (19%% del neto %s) pero está en %s. Ajustá los montos.',
                    pesos($ivaEsperado), pesos($neto), pesos($iva)
                );
            }
        }
    }

    // No soft warnings — los chequeos están todos como bloqueantes.
    $advertencias = [];

    if (!$errors) {
        $db->beginTransaction();
        try {
            // 1. Save file (optional)
            $archivo = guardarArchivo('archivo');

            // 2. Insert comprobante
            $stmt = $db->prepare(
                "INSERT INTO comprobantes_venta
                    (tipo_documento_venta, folio_venta, fecha_venta, cliente_venta, glosa_venta,
                     archivo_venta, neto_venta, iva_venta, exento_venta, total_venta, estado_venta,
                     date_created_venta)
                 VALUES (:tipo, :folio, :fecha, :cliente, :glosa, :archivo, :neto, :iva, :exento, :total, :estado, CURDATE())"
            );
            $stmt->execute([
                ':tipo'    => $tipo,
                ':folio'   => $folio,
                ':fecha'   => $fecha,
                ':cliente' => $cliente,
                ':glosa'   => $glosa,
                ':archivo' => $archivo,
                ':neto'    => $neto,
                ':iva'     => $iva,
                ':exento'  => $exento,
                ':total'   => $total,
                ':estado'  => $estado,
            ]);
            $ventaId = (int)$db->lastInsertId();

            // 3. Generate asiento (only if not anulado)
            $asientoMsg = '';
            if ($estado !== 'anulado') {
                $compiled = compileAsientoVenta($db, [
                    'tipo_documento_venta' => $tipo,
                    'folio_venta'          => $folio,
                    'neto_venta'           => $neto,
                    'iva_venta'            => $iva,
                    'exento_venta'         => $exento,
                    'total_venta'          => $total,
                ]);
                if (isset($compiled['error'])) {
                    throw new RuntimeException($compiled['error']);
                }
                $res = insertarAsiento($db, 'venta', $ventaId, $fecha, $compiled['glosa'], $compiled['lineas']);
                if (isset($res['error'])) {
                    throw new RuntimeException($res['error']);
                }
                $asientoMsg = ' Asiento N° ' . $res['numero'] . ' generado.';
            }

            $db->commit();
            $msgFlash = sprintf('Venta #%d cargada (folio %d).%s', $ventaId, $folio, $asientoMsg);
            if ($advertencias) {
                $msgFlash .= ' Advertencias: ' . implode(' ', $advertencias);
            }
            $flash = ['type' => 'success', 'msg' => $msgFlash];
            $valoresPrev = []; // limpia el form
        } catch (Throwable $e) {
            $db->rollBack();
            $flash = ['type' => 'danger', 'msg' => 'No se pudo guardar: ' . $e->getMessage()];
        }
    } else {
        $flash = ['type' => 'danger', 'msg' => 'Revisá los campos: ' . implode(' ', $errors)];
    }
}

/* =========================================================================
   Catálogos para el form (clientes)
   ========================================================================= */

$clientes = [];
if ($db) {
    try {
        $clientes = $db->query(
            "SELECT id_cliente, razon_social_cliente, rut_cliente
             FROM clientes
             ORDER BY razon_social_cliente ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

include __DIR__ . '/../partials/header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cargar venta — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .form-card { max-width: 760px; margin: 0 auto; }
    .montos-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: .75rem; }
    .total-display {
        background: #e7f5ee; border-radius: 6px; padding: .75rem 1rem;
        font-weight: 600; color: #198754; text-align: right;
    }
    .hint { color: #6c757d; font-size: .85rem; }
</style>
</head>
<body>
<?= wpb_render_user_bar() ?>
<div class="container py-4">
    <div class="form-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">Cargar venta</h1>
                <p class="text-muted mb-0">Una factura o boleta. Al guardar, el asiento contable se genera solo.</p>
            </div>
            <a href="/dashboard-contable" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="form-venta">

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="tipo_documento">Tipo de documento</label>
                    <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                        <option value="">— elegí uno —</option>
                        <?php
                        $tipos = [
                            'factura_afecta' => 'Factura afecta',
                            'factura_exenta' => 'Factura exenta',
                            'boleta'         => 'Boleta',
                            'nota_credito'   => 'Nota de crédito',
                            'nota_debito'    => 'Nota de débito',
                        ];
                        $tipoSel = $valoresPrev['tipo_documento'] ?? '';
                        foreach ($tipos as $val => $label):
                        ?>
                            <option value="<?= $val ?>" <?= $tipoSel === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="folio">Folio (N° del documento)</label>
                    <input type="number" class="form-control" id="folio" name="folio" required min="1"
                           value="<?= htmlspecialchars($valoresPrev['folio'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="fecha">Fecha</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" required
                           value="<?= htmlspecialchars($valoresPrev['fecha'] ?? date('Y-m-d')) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="cliente">Cliente</label>
                <select class="form-select" id="cliente" name="cliente" required>
                    <option value="">— elegí un cliente —</option>
                    <?php $clientSel = (int)($valoresPrev['cliente'] ?? 0); foreach ($clientes as $c): ?>
                        <option value="<?= (int)$c['id_cliente'] ?>" <?= $clientSel === (int)$c['id_cliente'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['razon_social_cliente']) ?>
                            <?php if ($c['rut_cliente']): ?> · <?= htmlspecialchars($c['rut_cliente']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Si el cliente no está en la lista, cargalo desde el CMS (sección Clientes) y volvé acá.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="glosa">Glosa / descripción</label>
                <textarea class="form-control" id="glosa" name="glosa" rows="2"
                          placeholder="Ej. Servicios de impresión 1000 trípticos"><?= htmlspecialchars($valoresPrev['glosa'] ?? '') ?></textarea>
            </div>

            <h5 class="mt-4">Montos</h5>
            <div class="montos-grid mb-3">
                <div>
                    <label class="form-label" for="neto">Neto</label>
                    <input type="number" class="form-control" id="neto" name="neto" step="1" min="0"
                           value="<?= htmlspecialchars($valoresPrev['neto'] ?? '0') ?>">
                </div>
                <div>
                    <label class="form-label" for="iva">IVA (19%)</label>
                    <input type="number" class="form-control" id="iva" name="iva" step="1" min="0"
                           value="<?= htmlspecialchars($valoresPrev['iva'] ?? '0') ?>">
                    <div class="hint" id="iva-auto-hint">se calcula solo</div>
                </div>
                <div>
                    <label class="form-label" for="exento">Exento</label>
                    <input type="number" class="form-control" id="exento" name="exento" step="1" min="0"
                           value="<?= htmlspecialchars($valoresPrev['exento'] ?? '0') ?>">
                </div>
                <div>
                    <label class="form-label">Total</label>
                    <div class="total-display" id="total-display">$ 0</div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="estado">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="emitido" <?= ($valoresPrev['estado'] ?? 'emitido') === 'emitido' ? 'selected' : '' ?>>Emitido</option>
                        <option value="anulado" <?= ($valoresPrev['estado'] ?? '') === 'anulado' ? 'selected' : '' ?>>Anulado (no genera asiento)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="archivo">Archivo (PDF / imagen — opcional)</label>
                    <input type="file" class="form-control" id="archivo" name="archivo"
                           accept=".pdf,.jpg,.jpeg,.png,.webp">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" id="btn-submit">Cargar y generar asiento</button>
                <a href="/cargar-venta" class="btn btn-outline-secondary">Limpiar</a>
                <a href="/libro-ventas" class="btn btn-link ms-auto">Ver libro de ventas →</a>
            </div>
            <div id="form-validation-msg" class="alert alert-warning mt-3" style="display:none"></div>

        </form>
    </div>
</div>

<script>
(function () {
    // Auto-calcula el IVA al escribir el neto, según el tipo de documento.
    // El usuario puede sobrescribir el IVA manualmente — si lo hace, dejamos
    // de auto-calcular para no pisarle el valor.
    //
    // También valida en vivo que los montos cuadren con las reglas SII:
    //   factura_afecta / boleta → IVA == 19% del neto (±$1)
    //   factura_exenta           → IVA == 0
    //   nota_credito / nota_debito → libres (las maneja el contador)
    // Si la validación falla, deshabilita el botón submit y muestra alerta.
    var $tipo   = document.getElementById('tipo_documento');
    var $neto   = document.getElementById('neto');
    var $iva    = document.getElementById('iva');
    var $exento = document.getElementById('exento');
    var $estado = document.getElementById('estado');
    var $total  = document.getElementById('total-display');
    var $hint   = document.getElementById('iva-auto-hint');
    var $submit = document.getElementById('btn-submit');
    var $msg    = document.getElementById('form-validation-msg');
    var ivaTouched = false;

    function tipoLlevaIva() {
        var v = $tipo.value;
        return v === 'factura_afecta' || v === 'boleta';
    }

    function pesos(n) { return '$ ' + n.toLocaleString('es-CL'); }

    function validar(neto, iva) {
        // Anulado no genera asiento — no validamos montos.
        if ($estado.value === 'anulado') { return null; }
        var t = $tipo.value;
        if (t === 'factura_exenta' && iva !== 0) {
            return 'Una factura exenta no lleva IVA. Pasá el monto a "Exento" y dejá el IVA en 0.';
        }
        if (t === 'factura_afecta' || t === 'boleta') {
            var esperado = Math.round(neto * 0.19);
            if (Math.abs(esperado - iva) > 1) {
                return 'El IVA debería ser ' + pesos(esperado) + ' (19% del neto ' + pesos(neto) + ') pero está en ' + pesos(iva) + '.';
            }
        }
        return null;
    }

    function recalcular() {
        var neto   = parseFloat($neto.value)   || 0;
        var exento = parseFloat($exento.value) || 0;
        if (!ivaTouched) {
            $iva.value = tipoLlevaIva() ? Math.round(neto * 0.19) : 0;
        }
        var iva   = parseFloat($iva.value) || 0;
        var total = neto + iva + exento;
        $total.textContent = pesos(total);

        var err = validar(neto, iva);
        if (err) {
            $msg.textContent = err;
            $msg.style.display = '';
            $submit.disabled = true;
        } else {
            $msg.style.display = 'none';
            $msg.textContent = '';
            $submit.disabled = false;
        }
    }

    $iva.addEventListener('input', function () { ivaTouched = true; $hint.textContent = 'editado a mano'; recalcular(); });
    $neto.addEventListener('input', recalcular);
    $exento.addEventListener('input', recalcular);
    $estado.addEventListener('change', recalcular);
    $tipo.addEventListener('change', function () {
        ivaTouched = false;
        $hint.textContent = 'se calcula solo';
        recalcular();
    });
    recalcular();
})();
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
