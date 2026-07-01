<?php
/**
 * Contabilidad PyMe — configuración del plugin.
 *
 * Éste es el archivo por defecto. Para overrides locales (por instancia),
 * copiá a `config.local.php` (git-ignored) y edita ahí — el controller lo
 * detecta y le da precedencia. Para overrides globales por la instancia
 * (multi-tenant en el futuro), migraremos a una tabla `contabilidad_settings`
 * similar a lo que hace pos-manager con `pos_settings`.
 *
 * Cualquier valor de este archivo es leíble con:
 *   ContabilidadController::getConfig('accounting.iva_rate')     → 0.19
 *   ContabilidadController::getConfig('access.roles_contador')   → ['contador']
 *
 * NOTA de seguridad: los nombres de tablas y códigos de cuenta se
 * interpolan en SQL, así que solo pueden ser identificadores
 * alfanuméricos + puntos. No los expongas a input de usuario sin
 * validar contra ^[a-zA-Z0-9_.]+$.
 */

return [
    'plugin' => [
        'name'    => 'Contabilidad PyMe',
        'version' => '1.0.0',
        'enabled' => true,
    ],

    /* ---------- País / normativa fiscal ------------------------------ */
    'country' => [
        'code'     => 'CL',           // ISO 3166-1 alpha-2
        'currency' => 'CLP',          // ISO 4217
        // Adaptador tributario (libro de ventas/compras + F29). Hoy solo
        // 'sii-cl' está implementado. AR/MX/CO ⇒ agregar en lib/sii*.php
        // y cambiar acá.
        'tax_adapter' => 'sii-cl',
    ],

    /* ---------- Tasas fiscales --------------------------------------- */
    'accounting' => [
        'iva_rate'                    => 0.19,   // 19% Chile
        'retencion_honorarios_rate'   => 0.10,   // 10% BHE Chile 2026
        'redondeo_decimales'          => 0,      // pesos chilenos = enteros
        'balance_tolerance'           => 0.01,   // Σ Debe = Σ Haber ±$0.01
    ],

    /* ---------- Códigos de cuenta (plan de cuentas mínimo CL) --------
       Si querés customizar tu plan de cuentas, cambia estos codes por
       los que uses vos. Las páginas usan el code para buscar el id_cuenta
       (via cuentaPorCodigo()), no hardcodean IDs. */
    'cuentas' => [
        'caja'                  => '1.1.01',
        'banco'                 => '1.1.02',
        'clientes'              => '1.1.03',
        'iva_credito'           => '1.1.04',
        'proveedores'           => '2.1.01',
        'iva_debito'            => '2.1.02',
        'retencion_honorarios'  => '2.1.04',
        'ventas_afectas'        => '4.1.01',
        'ventas_exentas'        => '4.1.02',
    ],

    /* ---------- Control de acceso a las páginas del contador --------- */
    'access' => [
        // Roles del admin que pueden CARGAR (mutaciones): ventas, compras,
        // pagos, cobros, cierres de mes.
        'roles_contador' => ['contador'],
        // Roles que pueden solo LEER (dashboards, libros, F29 sin editar).
        'roles_lectura'  => ['lectura'],
        // superadmin/admin siempre pueden todo (bypass).
    ],

    /* ---------- Integración Payku (opcional) -------------------------
       Si el plugin de Payku está instalado y esta flag está en true,
       /libro-ventas muestra un botón "Generar link de pago" por venta.
       El link se guarda en comprobantes_venta.link_pago_venta.

       Si Payku no está enchufado, el sistema opera igual (contador carga
       cobros manuales desde /cargar-cobro). */
    'payku' => [
        'integration_enabled' => true,
        // Cuenta contra la cual se acredita un cobro Payku. Por defecto
        // Banco (1.1.02). Si querés separar comisiones, crea "1.1.05
        // Cuenta Payku" y cambia acá.
        'cuenta_ingreso'      => '1.1.02',
    ],

    /* ---------- SII (planillas para el portal) ----------------------- */
    'sii' => [
        // Separador CSV: `;` para portal chileno; `,` si tu contador usa
        // Excel en inglés y prefiere coma.
        'csv_separator' => ';',
        // BOM UTF-8 al inicio del CSV para que Excel abra acentos bien.
        'csv_bom'       => true,
    ],

    /* ---------- Cierre de mes ---------------------------------------- */
    'cierres' => [
        // Si está en true, requiere aprobación del contador (con nota)
        // para reabrir un mes cerrado. Sino, cualquier admin lo reabre.
        'reapertura_con_nota' => true,
        // Días de gracia después del fin de mes durante los cuales el
        // sistema recuerda "hay mes por cerrar". 15 = mitad de mes
        // siguiente (fecha típica de F29).
        'recordatorio_dias'   => 15,
    ],

    /* ---------- Hooks para plugins que extienden ---------------------
       Otros plugins (RRHH, Facturación, etc.) pueden declarar sus
       propios tipos de asiento via
       ContabilidadController::registerAsientoOrigen('remuneracion', [...]).

       Se listan acá los tipos autorizados a nivel config; si un plugin
       intenta registrar un tipo que no está en la whitelist, el
       controller lo rechaza (evita polución del catálogo). */
    'hooks' => [
        'allowed_origenes_externos' => [
            'remuneracion',  // futuro plugin RRHH
            'produccion',    // futuro plugin de fabricación
            'ajuste_stock',  // futuro plugin de inventario
        ],
    ],
];
