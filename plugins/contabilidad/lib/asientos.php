<?php
/**
 * Asientos library — shared accounting logic for venta/compra → asiento.
 *
 * Used by /generar-asientos (batch button-driven) and by /cargar-venta /
 * /cargar-compra (single-transaction insert + asiento in one click).
 *
 * Public API:
 *   cuentaPorCodigo(PDO $db, string $codigo): ?array
 *   compileAsientoVenta(PDO $db, array $venta): array { error?, lineas?, glosa? }
 *   compileAsientoCompra(PDO $db, array $compra): array { error?, lineas?, glosa? }
 *   nextNumeroAsiento(PDO $db): int
 *   insertarAsiento(PDO $db, string $origen, int $origenId, string $fecha,
 *                   string $glosa, array $lineas): array { ok?, asiento_id?, numero? | error? }
 *
 * Accounting recipes (Chile basics):
 *   Venta afecta:
 *     D  Clientes              total
 *        H  Ventas afectas        neto
 *        H  IVA Débito Fiscal     iva
 *   Venta exenta:
 *     D  Clientes              total
 *        H  Ventas exentas        exento
 *   Compra afecta:
 *     D  <Categoría's cuenta>     neto
 *     D  IVA Crédito Fiscal       iva
 *        H  Proveedores              total
 *   Compra exenta / boleta de honorarios:
 *     D  <Categoría's cuenta>     total
 *        H  Proveedores              total
 *
 * Σ Debe = Σ Haber is enforced inside insertarAsiento(); the recipes above
 * always balance, so a mismatch means a bad cuenta lookup or a corrupt
 * comprobante row — surfacing it as an error is the right behavior.
 */

// Guard against double-include when several pages pull this in.
if (defined('WPB_ASIENTOS_LIB_LOADED')) { return; }
define('WPB_ASIENTOS_LIB_LOADED', true);

/**
 * Looks up a cuenta by its hierarchical code (e.g. "1.1.03" for Clientes).
 * Returns null if not found — the caller decides whether that's fatal.
 */
function cuentaPorCodigo(PDO $db, string $codigo): ?array {
    $stmt = $db->prepare("SELECT id_cuenta, nombre_cuenta FROM plan_cuentas WHERE codigo_cuenta = :c LIMIT 1");
    $stmt->execute([':c' => $codigo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Returns max(numero_asiento) + 1. Empty table starts at 1. Optimistic —
 * fine for a single-user demo, would need a SELECT … FOR UPDATE inside a
 * transaction for production multi-user.
 */
function nextNumeroAsiento(PDO $db): int {
    $stmt = $db->query("SELECT COALESCE(MAX(numero_asiento), 0) + 1 AS n FROM asientos");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['n'] ?? 1);
}

/**
 * Builds the D/H lines for a venta. Returns { lineas, glosa } on success,
 * { error } on failure (missing master account).
 */
function compileAsientoVenta(PDO $db, array $v): array {
    $neto   = (float)($v['neto_venta']   ?? 0);
    $iva    = (float)($v['iva_venta']    ?? 0);
    $exento = (float)($v['exento_venta'] ?? 0);
    $total  = (float)($v['total_venta']  ?? 0);

    $clientes      = cuentaPorCodigo($db, '1.1.03');
    $ventasAfectas = cuentaPorCodigo($db, '4.1.01');
    $ventasExentas = cuentaPorCodigo($db, '4.1.02');
    $ivaDebito     = cuentaPorCodigo($db, '2.1.02');

    if (!$clientes) { return ['error' => 'Falta la cuenta 1.1.03 Clientes en el plan de cuentas']; }

    $lineas = [];
    $orden  = 1;
    $lineas[] = [
        'cuenta_linea' => (int)$clientes['id_cuenta'],
        'glosa_linea'  => 'Factura/boleta N° ' . ($v['folio_venta'] ?? '?'),
        'debe_linea'   => $total,
        'haber_linea'  => 0,
        'orden_linea'  => $orden++,
    ];
    if ($neto > 0 && $ventasAfectas) {
        $lineas[] = [
            'cuenta_linea' => (int)$ventasAfectas['id_cuenta'],
            'glosa_linea'  => 'Neto afecto',
            'debe_linea'   => 0,
            'haber_linea'  => $neto,
            'orden_linea'  => $orden++,
        ];
    }
    if ($iva > 0 && $ivaDebito) {
        $lineas[] = [
            'cuenta_linea' => (int)$ivaDebito['id_cuenta'],
            'glosa_linea'  => 'IVA 19%',
            'debe_linea'   => 0,
            'haber_linea'  => $iva,
            'orden_linea'  => $orden++,
        ];
    }
    if ($exento > 0 && $ventasExentas) {
        $lineas[] = [
            'cuenta_linea' => (int)$ventasExentas['id_cuenta'],
            'glosa_linea'  => 'Monto exento',
            'debe_linea'   => 0,
            'haber_linea'  => $exento,
            'orden_linea'  => $orden++,
        ];
    }

    $glosa = sprintf('Venta — %s N° %s', $v['tipo_documento_venta'] ?? 'doc', $v['folio_venta'] ?? '?');
    return ['lineas' => $lineas, 'glosa' => $glosa];
}

/**
 * Builds the D/H lines for a compra. Uses the categoria's `cuenta_categoria`
 * as the gasto/costo account.
 */
function compileAsientoCompra(PDO $db, array $c): array {
    $tipo      = (string)($c['tipo_documento_compra'] ?? '');
    $neto      = (float)($c['neto_compra']      ?? 0);
    $iva       = (float)($c['iva_compra']       ?? 0);
    $exento    = (float)($c['exento_compra']    ?? 0);
    $total     = (float)($c['total_compra']     ?? 0);
    $retencion = (float)($c['retencion_compra'] ?? 0);

    $catId = (int)($c['categoria_compra'] ?? 0);
    if (!$catId) { return ['error' => 'El comprobante no tiene categoría']; }
    $catStmt = $db->prepare("SELECT cuenta_categoria FROM categorias_gasto WHERE id_categoria = :id LIMIT 1");
    $catStmt->execute([':id' => $catId]);
    $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
    if (!$cat || !$cat['cuenta_categoria']) {
        return ['error' => 'La categoría ' . $catId . ' no tiene cuenta contable asociada'];
    }
    $cuentaGastoId = (int)$cat['cuenta_categoria'];

    $proveedores       = cuentaPorCodigo($db, '2.1.01');
    $ivaCredito        = cuentaPorCodigo($db, '1.1.04');
    $retencionPorPagar = cuentaPorCodigo($db, '2.1.04');
    if (!$proveedores) { return ['error' => 'Falta la cuenta 2.1.01 Proveedores en el plan de cuentas']; }
    if ($retencion > 0 && !$retencionPorPagar) {
        return ['error' => 'Falta la cuenta 2.1.04 Retención honorarios por pagar en el plan de cuentas'];
    }

    $lineas = [];
    $orden  = 1;
    // El "gasto" es lo que entra a la categoría contable: neto + exento.
    // Para boletas de honorarios el bruto es gasto completo; la retención
    // no resta del gasto, sale del lado del haber junto con Proveedores
    // (es una deuda al SII, no al proveedor).
    $importeGasto = $neto + $exento;
    if ($importeGasto > 0) {
        $lineas[] = [
            'cuenta_linea' => $cuentaGastoId,
            'glosa_linea'  => $tipo === 'boleta_honorarios' ? 'Honorarios profesionales' : 'Gasto del período',
            'debe_linea'   => $importeGasto,
            'haber_linea'  => 0,
            'orden_linea'  => $orden++,
        ];
    }
    if ($iva > 0 && $ivaCredito) {
        $lineas[] = [
            'cuenta_linea' => (int)$ivaCredito['id_cuenta'],
            'glosa_linea'  => 'IVA crédito fiscal',
            'debe_linea'   => $iva,
            'haber_linea'  => 0,
            'orden_linea'  => $orden++,
        ];
    }
    // En honorarios con retención, al proveedor solo se le paga total -
    // retención; la diferencia queda como deuda al SII. En cualquier otro
    // caso, retención == 0 y al proveedor se le debe el total entero.
    $aPagarProveedor = $total - $retencion;
    $lineas[] = [
        'cuenta_linea' => (int)$proveedores['id_cuenta'],
        'glosa_linea'  => 'Factura proveedor N° ' . ($c['folio_compra'] ?? '?'),
        'debe_linea'   => 0,
        'haber_linea'  => $aPagarProveedor,
        'orden_linea'  => $orden++,
    ];
    if ($retencion > 0 && $retencionPorPagar) {
        $lineas[] = [
            'cuenta_linea' => (int)$retencionPorPagar['id_cuenta'],
            'glosa_linea'  => 'Retención 10% boleta honorarios N° ' . ($c['folio_compra'] ?? '?'),
            'debe_linea'   => 0,
            'haber_linea'  => $retencion,
            'orden_linea'  => $orden++,
        ];
    }

    $glosa = sprintf('Compra — %s N° %s', $tipo ?: 'doc', $c['folio_compra'] ?? '?');
    return ['lineas' => $lineas, 'glosa' => $glosa];
}

/**
 * Builds the D/H lines for a pago a proveedor. The recipe is the mirror
 * of compra:
 *
 *   D  Proveedores            monto
 *      H  Caja  / Banco          monto
 *
 * Uses the `pagos` row as input. `medio_pago` ∈ {caja, banco} picks the
 * H account by codigo_cuenta (1.1.01 / 1.1.02). The folio of the
 * underlying compra is read so the glosa is informative.
 */
function compileAsientoPago(PDO $db, array $p): array {
    $monto = (float)($p['monto_pago'] ?? 0);
    $medio = (string)($p['medio_pago'] ?? '');
    if ($monto <= 0) {
        return ['error' => 'El monto del pago debe ser mayor que cero'];
    }
    $codigoBanco = $medio === 'caja' ? '1.1.01' : ($medio === 'banco' ? '1.1.02' : null);
    if ($codigoBanco === null) {
        return ['error' => 'Medio de pago inválido (esperado: caja o banco)'];
    }

    $proveedores = cuentaPorCodigo($db, '2.1.01');
    if (!$proveedores) {
        return ['error' => 'Falta la cuenta 2.1.01 Proveedores en el plan de cuentas'];
    }
    $cuentaBanco = cuentaPorCodigo($db, $codigoBanco);
    if (!$cuentaBanco) {
        return ['error' => 'Falta la cuenta ' . $codigoBanco . ' (' . $medio . ') en el plan de cuentas'];
    }

    // Pull the originating compra's folio for the glosa (informational).
    $compraId = (int)($p['compra_pago'] ?? 0);
    $folio    = '?';
    if ($compraId > 0) {
        $stmt = $db->prepare("SELECT folio_compra FROM comprobantes_compra WHERE id_compra = :id LIMIT 1");
        $stmt->execute([':id' => $compraId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { $folio = (string)$row['folio_compra']; }
    }

    $lineas = [
        [
            'cuenta_linea' => (int)$proveedores['id_cuenta'],
            'glosa_linea'  => 'Pago factura proveedor N° ' . $folio,
            'debe_linea'   => $monto,
            'haber_linea'  => 0,
            'orden_linea'  => 1,
        ],
        [
            'cuenta_linea' => (int)$cuentaBanco['id_cuenta'],
            'glosa_linea'  => 'Salida por ' . $medio,
            'debe_linea'   => 0,
            'haber_linea'  => $monto,
            'orden_linea'  => 2,
        ],
    ];

    $glosa = sprintf('Pago — factura compra N° %s · medio: %s', $folio, $medio);
    return ['lineas' => $lineas, 'glosa' => $glosa];
}

/**
 * Builds the D/H lines for a cobro recibido de un cliente. Inversa
 * simétrica del pago a proveedor:
 *
 *   D  Caja / Banco             monto
 *      H  Clientes                  monto
 *
 * `medio_cobro` ∈ {caja, banco, payku}. payku se contabiliza como banco
 * (Payku acredita a una cuenta bancaria); si después se necesita una
 * cuenta separada (ej. para conciliar comisiones Payku), se puede crear
 * 1.1.05 Cuenta Payku y mapearla acá.
 */
function compileAsientoCobro(PDO $db, array $p): array {
    $monto = (float)($p['monto_cobro'] ?? 0);
    $medio = (string)($p['medio_cobro'] ?? '');
    if ($monto <= 0) {
        return ['error' => 'El monto del cobro debe ser mayor que cero'];
    }
    if ($medio === 'caja') {
        $codigoCuenta = '1.1.01';
    } elseif ($medio === 'banco' || $medio === 'payku') {
        $codigoCuenta = '1.1.02';
    } else {
        return ['error' => 'Medio de cobro inválido (esperado: caja, banco o payku)'];
    }

    $clientes    = cuentaPorCodigo($db, '1.1.03');
    $cuentaIngreso = cuentaPorCodigo($db, $codigoCuenta);
    if (!$clientes)      { return ['error' => 'Falta la cuenta 1.1.03 Clientes en el plan de cuentas']; }
    if (!$cuentaIngreso) { return ['error' => 'Falta la cuenta ' . $codigoCuenta . ' en el plan de cuentas']; }

    $ventaId = (int)($p['venta_cobro'] ?? 0);
    $folio   = '?';
    if ($ventaId > 0) {
        $stmt = $db->prepare("SELECT folio_venta FROM comprobantes_venta WHERE id_venta = :id LIMIT 1");
        $stmt->execute([':id' => $ventaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { $folio = (string)$row['folio_venta']; }
    }

    $lineas = [
        [
            'cuenta_linea' => (int)$cuentaIngreso['id_cuenta'],
            'glosa_linea'  => 'Cobro factura/boleta N° ' . $folio . ' (' . $medio . ')',
            'debe_linea'   => $monto,
            'haber_linea'  => 0,
            'orden_linea'  => 1,
        ],
        [
            'cuenta_linea' => (int)$clientes['id_cuenta'],
            'glosa_linea'  => 'Aplicación cobro factura N° ' . $folio,
            'debe_linea'   => 0,
            'haber_linea'  => $monto,
            'orden_linea'  => 2,
        ],
    ];

    $glosa = sprintf('Cobro — factura venta N° %s · medio: %s', $folio, $medio);
    return ['lineas' => $lineas, 'glosa' => $glosa];
}

/**
 * Inserts asiento + lineas in a transaction. Refuses to write if Σ Debe ≠
 * Σ Haber (within $0.01). Returns the new asiento_id + numero on success.
 *
 * Transaction handling: PDO doesn't support nested transactions, so if the
 * caller already started one (e.g. /cargar-venta wraps the
 * comprobante + asiento in one outer tx so a failure rolls back both),
 * we DON'T open or commit ours — we let the caller manage it. The Σ Debe
 * = Σ Haber check happens regardless.
 */
function insertarAsiento(PDO $db, string $origen, int $origenId, string $fecha, string $glosa, array $lineas): array {
    $sumD = array_sum(array_column($lineas, 'debe_linea'));
    $sumH = array_sum(array_column($lineas, 'haber_linea'));
    if (abs($sumD - $sumH) > 0.01) {
        return ['error' => sprintf('Asiento descuadrado: Debe=%s Haber=%s', $sumD, $sumH)];
    }

    $weStartedTx = !$db->inTransaction();
    if ($weStartedTx) { $db->beginTransaction(); }

    try {
        $numero = nextNumeroAsiento($db);
        $stmt = $db->prepare(
            "INSERT INTO asientos
                (numero_asiento, fecha_asiento, glosa_asiento, origen_asiento, origen_id_asiento,
                 total_debe_asiento, total_haber_asiento, estado_asiento, date_created_asiento)
             VALUES (:n, :f, :g, :o, :oid, :td, :th, 'validado', CURDATE())"
        );
        $stmt->execute([
            ':n'   => $numero,
            ':f'   => $fecha,
            ':g'   => $glosa,
            ':o'   => $origen,
            ':oid' => $origenId,
            ':td'  => $sumD,
            ':th'  => $sumH,
        ]);
        $asientoId = (int)$db->lastInsertId();

        $lineStmt = $db->prepare(
            "INSERT INTO asiento_lineas
                (asiento_linea, cuenta_linea, glosa_linea, debe_linea, haber_linea, orden_linea, date_created_linea)
             VALUES (:a, :c, :g, :d, :h, :o, CURDATE())"
        );
        foreach ($lineas as $l) {
            $lineStmt->execute([
                ':a' => $asientoId,
                ':c' => $l['cuenta_linea'],
                ':g' => $l['glosa_linea'],
                ':d' => $l['debe_linea'],
                ':h' => $l['haber_linea'],
                ':o' => $l['orden_linea'],
            ]);
        }
        if ($weStartedTx) { $db->commit(); }
        return ['ok' => true, 'asiento_id' => $asientoId, 'numero' => $numero];
    } catch (Throwable $e) {
        if ($weStartedTx && $db->inTransaction()) { $db->rollBack(); }
        return ['error' => 'Error al insertar: ' . $e->getMessage()];
    }
}
