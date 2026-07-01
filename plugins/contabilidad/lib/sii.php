<?php
/**
 * SII — códigos y formato de los libros tributarios chilenos.
 *
 * No es un libro electrónico (XML firmado para envío directo al SII).
 * Es la planilla CSV que el contador descarga, revisa, y sube manualmente
 * al portal del SII o importa en su software de gestión (Defontana,
 * Nubox, Softland, etc.). Layout estándar de la industria.
 *
 * Códigos de tipo de documento (Resolución Exenta 80 SII):
 *    30  Factura
 *    33  Factura electrónica afecta
 *    34  Factura electrónica exenta
 *    39  Boleta
 *    41  Boleta exenta
 *    43  Liquidación factura
 *    46  Factura de compra
 *    52  Guía de despacho
 *    56  Nota de débito
 *    61  Nota de crédito
 *    110 Factura de exportación
 *    111 Nota de débito exportación
 *    112 Nota de crédito exportación
 *
 * El sistema de la app maneja una nomenclatura amigable; este lib traduce.
 *
 * Public API:
 *   sii_codigo_documento(string $tipoInterno): string
 *     "factura_afecta"   → "33"
 *     "factura_exenta"   → "34"
 *     "boleta"           → "39"
 *     "boleta_honorarios"→ "8801" (boleta de honorarios electrónica)
 *     "nota_credito"     → "61"
 *     "nota_debito"      → "56"
 *
 *   sii_csv_libro_ventas(array $rows): string  → CSV string completo (con BOM)
 *   sii_csv_libro_compras(array $rows): string → idem
 *
 *   Cada $rows es lo que `libro-ventas.php` / `libro-compras.php` ya
 *   produce (con razón social + RUT del cliente/proveedor JOINeados).
 */

if (defined('WPB_SII_LIB_LOADED')) { return; }
define('WPB_SII_LIB_LOADED', true);

function sii_codigo_documento(string $tipoInterno): string {
    switch ($tipoInterno) {
        case 'factura_afecta':    return '33';
        case 'factura_exenta':    return '34';
        case 'boleta':            return '39';
        case 'boleta_honorarios': return '8801'; // BHE
        case 'nota_credito':      return '61';
        case 'nota_debito':       return '56';
        default:                  return '0';
    }
}

/**
 * Formatea un número chileno: sin decimales, sin separador de miles.
 * El SII espera enteros (pesos) salvo dólares (no aplicamos acá).
 */
function sii_money($n): string {
    return (string)(int)round((float)$n);
}

/**
 * Convierte el body de un RUT al formato sin puntos y con guión:
 * "76.123.456-0" → "76123456-0". El portal del SII acepta varias formas;
 * esta es la más segura.
 */
function sii_rut(string $raw): string {
    $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $raw));
    if (strlen($clean) < 2) { return $raw; }
    return substr($clean, 0, -1) . '-' . substr($clean, -1);
}

/**
 * Escapa un campo CSV según RFC 4180. Si el valor contiene `,`, `"`, `;`
 * o newline, se envuelve en comillas dobles y los `"` internos se duplican.
 */
function sii_csv_field($value): string {
    $s = (string)$value;
    if (preg_match('/[,";\r\n]/', $s)) {
        $s = '"' . str_replace('"', '""', $s) . '"';
    }
    return $s;
}

function sii_csv_row(array $cells): string {
    return implode(';', array_map('sii_csv_field', $cells)) . "\r\n";
}

/**
 * Libro de Ventas en CSV. El layout estándar que aceptan tanto el portal
 * MIPYME del SII como Defontana/Nubox/Softland. Separador `;` (CL), CRLF,
 * BOM UTF-8 para que Excel abra los acentos.
 *
 * Columnas:
 *   1. Tipo Doc (código SII)
 *   2. Folio
 *   3. Fecha Emisión (YYYY-MM-DD)
 *   4. RUT Cliente
 *   5. Razón Social
 *   6. Monto Exento
 *   7. Monto Neto Afecto
 *   8. Monto IVA
 *   9. Monto Total
 *   10. Glosa / Detalle
 *   11. Estado (emitido / anulado)
 */
function sii_csv_libro_ventas(array $rows): string {
    $out = "\xEF\xBB\xBF"; // BOM
    $out .= sii_csv_row([
        'Tipo Doc',
        'Folio',
        'Fecha Emisión',
        'RUT Cliente',
        'Razón Social',
        'Monto Exento',
        'Monto Neto Afecto',
        'Monto IVA',
        'Monto Total',
        'Glosa',
        'Estado',
    ]);
    foreach ($rows as $v) {
        $out .= sii_csv_row([
            sii_codigo_documento((string)($v['tipo_documento_venta'] ?? '')),
            (string)($v['folio_venta'] ?? ''),
            (string)($v['fecha_venta'] ?? ''),
            sii_rut((string)($v['rut_cliente'] ?? '')),
            (string)($v['razon_social_cliente'] ?? ''),
            sii_money($v['exento_venta'] ?? 0),
            sii_money($v['neto_venta']   ?? 0),
            sii_money($v['iva_venta']    ?? 0),
            sii_money($v['total_venta']  ?? 0),
            (string)($v['glosa_venta'] ?? ''),
            (string)($v['estado_venta'] ?? ''),
        ]);
    }
    return $out;
}

/**
 * Libro de Compras en CSV. Layout análogo al de ventas con dos columnas
 * adicionales relevantes en compras:
 *   - Retención honorarios (BHE)
 *   - IVA No Recuperable (vacío en nuestra app porque no diferenciamos)
 */
function sii_csv_libro_compras(array $rows): string {
    $out = "\xEF\xBB\xBF"; // BOM
    $out .= sii_csv_row([
        'Tipo Doc',
        'Folio',
        'Fecha Emisión',
        'RUT Proveedor',
        'Razón Social',
        'Monto Exento',
        'Monto Neto Afecto',
        'Monto IVA',
        'Monto IVA No Recuperable',
        'Retención',
        'Monto Total',
        'Glosa',
        'Estado',
    ]);
    foreach ($rows as $c) {
        $out .= sii_csv_row([
            sii_codigo_documento((string)($c['tipo_documento_compra'] ?? '')),
            (string)($c['folio_compra'] ?? ''),
            (string)($c['fecha_compra'] ?? ''),
            sii_rut((string)($c['rut_proveedor'] ?? '')),
            (string)($c['razon_social_proveedor'] ?? ''),
            sii_money($c['exento_compra'] ?? 0),
            sii_money($c['neto_compra']   ?? 0),
            sii_money($c['iva_compra']    ?? 0),
            '0',  // IVA No Recuperable — la app no diferencia (todo es recuperable)
            sii_money($c['retencion_compra'] ?? 0),
            sii_money($c['total_compra'] ?? 0),
            (string)($c['glosa_compra'] ?? ''),
            (string)($c['estado_compra'] ?? ''),
        ]);
    }
    return $out;
}
