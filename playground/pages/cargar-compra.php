<?php
/**
 * Cargar compra — formulario optimizado para registrar una factura/boleta
 * recibida de un proveedor en un solo paso. Misma lógica que cargar-venta
 * pero con proveedor + categoría (la categoría apunta a la cuenta de gasto
 * que el asiento usará).
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
wpb_require_role(['contador']);

require_once __DIR__ . '/_lib/asientos.php';
$db = Connection::connect();

/* =========================================================================
   Helpers
   ========================================================================= */

function pesos($n) {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}

function guardarArchivo(string $field): ?string {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $name = $_FILES[$field]['name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['pdf','jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed, true)) { return null; }
    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }
    $newName = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!@move_uploaded_file($_FILES[$field]['tmp_name'], $uploadsDir . '/' . $newName)) { return null; }
    return '/web/uploads/' . $newName;
}

/* =========================================================================
   Acción: guardar
   ========================================================================= */

$flash       = null;
$valoresPrev = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    wpb_csrf_check(); // rejects forged POSTs before we touch the DB

    $tipo      = trim($_POST['tipo_documento'] ?? '');
    $folio     = (int)($_POST['folio'] ?? 0);
    $fecha     = trim($_POST['fecha'] ?? '');
    $proveedor = (int)($_POST['proveedor'] ?? 0);
    $categoria = (int)($_POST['categoria'] ?? 0);
    $glosa     = trim($_POST['glosa'] ?? '');
    $neto      = (float)str_replace(['.', ','], ['', '.'], (string)($_POST['neto'] ?? '0'));
    $iva       = (float)str_replace(['.', ','], ['', '.'], (string)($_POST['iva'] ?? '0'));
    $exento    = (float)str_replace(['.', ','], ['', '.'], (string)($_POST['exento'] ?? '0'));
    $total     = $neto + $iva + $exento;
    $estado    = trim($_POST['estado'] ?? 'registrado');
    // La retención SOLO aplica a boletas de honorarios. Para cualquier otro
    // tipo de documento, se ignora aunque el form la mande (defensa contra
    // un POST manual).
    $retencion = $tipo === 'boleta_honorarios'
        ? (float)str_replace(['.', ','], ['', '.'], (string)($_POST['retencion'] ?? '0'))
        : 0.0;

    $errors = [];
    if (!preg_match('/^(factura_afecta|factura_exenta|boleta_honorarios|nota_credito|nota_debito)$/', $tipo)) {
        $errors[] = 'Tipo de documento inválido.';
    }
    if ($folio <= 0)                       { $errors[] = 'Folio debe ser un número positivo.'; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { $errors[] = 'Fecha inválida.'; }
    if ($proveedor <= 0)                   { $errors[] = 'Tenés que elegir un proveedor.'; }
    if ($categoria <= 0)                   { $errors[] = 'Tenés que elegir una categoría de gasto.'; }
    if ($total <= 0)                       { $errors[] = 'El total debe ser mayor que cero.'; }
    if (!in_array($estado, ['registrado','pagado','anulado'], true)) { $errors[] = 'Estado inválido.'; }

    // Guard: si el mes ya está cerrado, no se puede cargar más en él.
    if (mes_esta_cerrado($db, $fecha)) {
        $errors[] = 'El mes de esa fecha está cerrado. Para cargar acá, primero reabrí el mes desde /cierre-mes.';
    }

    // Hard validations to prevent rows that would later fail to produce a
    // balanced asiento. The user can still load an `anulado` row without
    // these checks, since that path skips the asiento generation entirely.
    if ($estado !== 'anulado') {
        // Boletas de honorarios + facturas exentas no llevan IVA por ley.
        if (in_array($tipo, ['boleta_honorarios', 'factura_exenta'], true) && $iva != 0) {
            $errors[] = sprintf('Una %s no lleva IVA. Pasá el monto a "Exento" o "Neto" y dejá el IVA en 0.', str_replace('_', ' ', $tipo));
        }
        // Factura afecta: el IVA tiene que ser ~19% del neto (tolerancia de $1 por redondeo).
        if ($tipo === 'factura_afecta') {
            $ivaEsperado = round($neto * 0.19);
            if (abs($ivaEsperado - $iva) > 1) {
                $errors[] = sprintf(
                    'El IVA debería ser %s (19%% del neto %s) pero está en %s. Ajustá los montos.',
                    pesos($ivaEsperado), pesos($neto), pesos($iva)
                );
            }
        }
        // Boleta de honorarios: la retención debe ser exactamente 10% del bruto.
        // El bruto en honorarios va en "exento" porque no lleva IVA. Tolerancia $1.
        if ($tipo === 'boleta_honorarios' && $retencion > 0) {
            $bruto = $exento + $neto; // por si alguien lo cargó en neto en vez de exento
            $retEsperada = round($bruto * 0.10);
            if (abs($retEsperada - $retencion) > 1) {
                $errors[] = sprintf(
                    'La retención debería ser %s (10%% del bruto %s) pero está en %s.',
                    pesos($retEsperada), pesos($bruto), pesos($retencion)
                );
            }
        }
    }

    // No softs warnings — todo lo importante es bloqueante para evitar
    // comprobantes con asiento descuadrado.
    $advertencias = [];

    if (!$errors) {
        $db->beginTransaction();
        try {
            $archivo = guardarArchivo('archivo');

            $stmt = $db->prepare(
                "INSERT INTO comprobantes_compra
                    (tipo_documento_compra, folio_compra, fecha_compra, proveedor_compra, categoria_compra,
                     glosa_compra, archivo_compra, neto_compra, iva_compra, retencion_compra, exento_compra,
                     total_compra, estado_compra, date_created_compra)
                 VALUES (:tipo, :folio, :fecha, :prov, :cat, :glosa, :archivo, :neto, :iva, :ret, :exento, :total, :estado, CURDATE())"
            );
            $stmt->execute([
                ':tipo'    => $tipo,
                ':folio'   => $folio,
                ':fecha'   => $fecha,
                ':prov'    => $proveedor,
                ':cat'     => $categoria,
                ':glosa'   => $glosa,
                ':archivo' => $archivo,
                ':neto'    => $neto,
                ':iva'     => $iva,
                ':ret'     => $retencion,
                ':exento'  => $exento,
                ':total'   => $total,
                ':estado'  => $estado,
            ]);
            $compraId = (int)$db->lastInsertId();

            $asientoMsg = '';
            if ($estado !== 'anulado') {
                $compiled = compileAsientoCompra($db, [
                    'tipo_documento_compra' => $tipo,
                    'folio_compra'          => $folio,
                    'categoria_compra'      => $categoria,
                    'neto_compra'           => $neto,
                    'iva_compra'            => $iva,
                    'retencion_compra'      => $retencion,
                    'exento_compra'         => $exento,
                    'total_compra'          => $total,
                ]);
                if (isset($compiled['error'])) { throw new RuntimeException($compiled['error']); }
                $res = insertarAsiento($db, 'compra', $compraId, $fecha, $compiled['glosa'], $compiled['lineas']);
                if (isset($res['error'])) { throw new RuntimeException($res['error']); }
                $asientoMsg = ' Asiento N° ' . $res['numero'] . ' generado.';
            }

            $db->commit();
            $msgFlash = sprintf('Compra #%d cargada (folio %d).%s', $compraId, $folio, $asientoMsg);
            if ($advertencias) { $msgFlash .= ' Advertencias: ' . implode(' ', $advertencias); }
            $flash = ['type' => 'success', 'msg' => $msgFlash];
            $valoresPrev = [];
        } catch (Throwable $e) {
            $db->rollBack();
            $flash = ['type' => 'danger', 'msg' => 'No se pudo guardar: ' . $e->getMessage()];
        }
    } else {
        $flash = ['type' => 'danger', 'msg' => 'Revisá los campos: ' . implode(' ', $errors)];
    }
}

/* =========================================================================
   Catálogos para el form (proveedores + categorías)
   ========================================================================= */

$proveedores = [];
$categorias  = [];
if ($db) {
    try {
        $proveedores = $db->query(
            "SELECT id_proveedor, razon_social_proveedor, rut_proveedor
             FROM proveedores
             ORDER BY razon_social_proveedor ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $categorias = $db->query(
            "SELECT cg.id_categoria, cg.nombre_categoria, pc.codigo_cuenta, pc.nombre_cuenta
             FROM categorias_gasto cg
             LEFT JOIN plan_cuentas pc ON pc.id_cuenta = cg.cuenta_categoria
             ORDER BY cg.nombre_categoria ASC"
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
<title>Cargar compra — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .form-card { max-width: 760px; margin: 0 auto; }
    .montos-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: .75rem; }
    .ret-hidden { display: none; }
    .total-display {
        background: #fdecea; border-radius: 6px; padding: .75rem 1rem;
        font-weight: 600; color: #b91c1c; text-align: right;
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
                <h1 class="mb-0">Cargar compra</h1>
                <p class="text-muted mb-0">Una factura recibida. Al guardar, el asiento contable se genera solo.</p>
            </div>
            <a href="/dashboard-contable" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="form-compra">
            <?= wpb_csrf_field() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="tipo_documento">Tipo de documento</label>
                    <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                        <option value="">— elegí uno —</option>
                        <?php
                        $tipos = [
                            'factura_afecta'     => 'Factura afecta',
                            'factura_exenta'     => 'Factura exenta',
                            'boleta_honorarios'  => 'Boleta de honorarios',
                            'nota_credito'       => 'Nota de crédito',
                            'nota_debito'        => 'Nota de débito',
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

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="proveedor">Proveedor</label>
                    <select class="form-select" id="proveedor" name="proveedor" required>
                        <option value="">— elegí uno —</option>
                        <?php $provSel = (int)($valoresPrev['proveedor'] ?? 0); foreach ($proveedores as $p): ?>
                            <option value="<?= (int)$p['id_proveedor'] ?>" <?= $provSel === (int)$p['id_proveedor'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['razon_social_proveedor']) ?>
                                <?php if ($p['rut_proveedor']): ?> · <?= htmlspecialchars($p['rut_proveedor']) ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="categoria">Categoría de gasto</label>
                    <select class="form-select" id="categoria" name="categoria" required>
                        <option value="">— elegí una —</option>
                        <?php $catSel = (int)($valoresPrev['categoria'] ?? 0); foreach ($categorias as $c): ?>
                            <option value="<?= (int)$c['id_categoria'] ?>" <?= $catSel === (int)$c['id_categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre_categoria']) ?>
                                <?php if ($c['codigo_cuenta']): ?> (<?= htmlspecialchars($c['codigo_cuenta']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">Define a qué cuenta de gasto se carga el asiento.</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="glosa">Glosa / descripción</label>
                <textarea class="form-control" id="glosa" name="glosa" rows="2"
                          placeholder="Ej. Luz mes de junio"><?= htmlspecialchars($valoresPrev['glosa'] ?? '') ?></textarea>
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
                <div id="retencion-cell" class="ret-hidden">
                    <label class="form-label" for="retencion">Retención (10%)</label>
                    <input type="number" class="form-control" id="retencion" name="retencion" step="1" min="0"
                           value="<?= htmlspecialchars($valoresPrev['retencion'] ?? '0') ?>">
                    <div class="hint" id="ret-auto-hint">solo boletas de honorarios</div>
                </div>
                <div>
                    <label class="form-label">Total</label>
                    <div class="total-display" id="total-display">$ 0</div>
                    <div class="hint" id="a-pagar-hint" style="display:none">Al proveedor: <strong id="a-pagar-val">$ 0</strong></div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="estado">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="registrado" <?= ($valoresPrev['estado'] ?? 'registrado') === 'registrado' ? 'selected' : '' ?>>Registrado</option>
                        <option value="pagado"     <?= ($valoresPrev['estado'] ?? '') === 'pagado'     ? 'selected' : '' ?>>Pagado</option>
                        <option value="anulado"    <?= ($valoresPrev['estado'] ?? '') === 'anulado'    ? 'selected' : '' ?>>Anulado (no genera asiento)</option>
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
                <a href="/cargar-compra" class="btn btn-outline-secondary">Limpiar</a>
                <a href="/libro-compras" class="btn btn-link ms-auto">Ver libro de compras →</a>
            </div>
            <div id="form-validation-msg" class="alert alert-warning mt-3" style="display:none"></div>

        </form>
    </div>
</div>

<script>
(function () {
    // Auto-IVA + validación en vivo. Reglas SII:
    //   factura_afecta      → IVA == 19% del neto (±$1)
    //   factura_exenta      → IVA == 0
    //   boleta_honorarios   → IVA == 0 (el monto va en Exento o Neto sin IVA)
    //   nota_credito / debito → libre (las maneja el contador)
    // El botón submit queda deshabilitado mientras la validación falla.
    var $tipo      = document.getElementById('tipo_documento');
    var $neto      = document.getElementById('neto');
    var $iva       = document.getElementById('iva');
    var $exento    = document.getElementById('exento');
    var $retencion = document.getElementById('retencion');
    var $retCell   = document.getElementById('retencion-cell');
    var $retHint   = document.getElementById('ret-auto-hint');
    var $estado    = document.getElementById('estado');
    var $total     = document.getElementById('total-display');
    var $aPagar    = document.getElementById('a-pagar-hint');
    var $aPagarVal = document.getElementById('a-pagar-val');
    var $hint      = document.getElementById('iva-auto-hint');
    var $submit    = document.getElementById('btn-submit');
    var $msg       = document.getElementById('form-validation-msg');
    var ivaTouched = false;
    var retTouched = false;

    function tipoLlevaIva()       { return $tipo.value === 'factura_afecta'; }
    function tipoLlevaRetencion() { return $tipo.value === 'boleta_honorarios'; }
    function pesos(n)             { return '$ ' + n.toLocaleString('es-CL'); }

    function validar(neto, iva, retencion, exento) {
        if ($estado.value === 'anulado') { return null; }
        var t = $tipo.value;
        if ((t === 'factura_exenta' || t === 'boleta_honorarios') && iva !== 0) {
            return 'Una ' + t.replace('_', ' ') + ' no lleva IVA. Pasá el monto a "Exento" o "Neto" y dejá el IVA en 0.';
        }
        if (t === 'factura_afecta') {
            var esperado = Math.round(neto * 0.19);
            if (Math.abs(esperado - iva) > 1) {
                return 'El IVA debería ser ' + pesos(esperado) + ' (19% del neto ' + pesos(neto) + ') pero está en ' + pesos(iva) + '.';
            }
        }
        if (t === 'boleta_honorarios' && retencion > 0) {
            var bruto = exento + neto;
            var rEsperada = Math.round(bruto * 0.10);
            if (Math.abs(rEsperada - retencion) > 1) {
                return 'La retención debería ser ' + pesos(rEsperada) + ' (10% del bruto ' + pesos(bruto) + ') pero está en ' + pesos(retencion) + '.';
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

        // Retención: solo en boletas de honorarios. Cuando aplica, auto-calcula
        // 10% del bruto (neto + exento) si el usuario no la editó.
        if (tipoLlevaRetencion()) {
            $retCell.classList.remove('ret-hidden');
            if (!retTouched) {
                $retencion.value = Math.round((neto + exento) * 0.10);
            }
        } else {
            $retCell.classList.add('ret-hidden');
            $retencion.value = 0;
            retTouched = false;
            $retHint.textContent = 'solo boletas de honorarios';
        }
        var retencion = parseFloat($retencion.value) || 0;

        // Si hay retención, mostrá cuánto se le paga realmente al proveedor.
        if (retencion > 0) {
            $aPagar.style.display = '';
            $aPagarVal.textContent = pesos(total - retencion);
        } else {
            $aPagar.style.display = 'none';
        }

        var err = validar(neto, iva, retencion, exento);
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
    $retencion.addEventListener('input', function () { retTouched = true; $retHint.textContent = 'editada a mano'; recalcular(); });
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
