<?php
/**
 * Contabilidad PyMe — plugin entry point.
 *
 * Contabilidad chilena con doble partida sobre el web-framework. Cierra
 * el ciclo completo: ventas/compras/pagos/cobros, asientos automáticos,
 * libros SII, F29 y cierre de mes. Payku opcional para cobros online.
 *
 * Arquitectura:
 *
 *   - Este archivo es el bootstrap: llama a ContabilidadController::init()
 *     una vez por request (via plugins-loader.php).
 *
 *   - install() / uninstall() gestionan schema + fixtures + CMS metadata.
 *     Son idempotentes: correr install() dos veces no rompe nada.
 *
 *   - Las páginas del contador (dashboard, cargar-*, libros, f29, etc.)
 *     viven en web/pages/ y siguen ahí — no las movemos al plugin
 *     porque el framework las sirve por URL pública. El plugin las
 *     "adopta" via install() y las quita en uninstall().
 *
 *   - lib/ contiene la lógica compartida (asientos, sii, rut, cierres,
 *     auth). Las páginas hacen require_once a estos libs.
 *
 *   - Hooks para integraciones futuras (RRHH generará asientos de
 *     remuneraciones): ContabilidadController::registerAsientoOrigen()
 *     permite a otros plugins declarar tipos de asiento y su receta.
 */

if (!defined('DIR')) {
    define('DIR', dirname(__DIR__, 2));
}

require_once __DIR__ . '/controllers/contabilidad.controller.php';

ContabilidadController::init();
