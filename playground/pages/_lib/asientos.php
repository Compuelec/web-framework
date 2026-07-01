<?php
/**
 * Backwards-compat shim → plugins/contabilidad/lib/asientos.php
 *
 * La lógica canónica vive en el plugin desde que el playground se empaquetó.
 * Este archivo se copia a web/pages/_lib/ por install.sh para preservar el
 * import path de las páginas legacy (require_once __DIR__ . '/_lib/…').
 */
require_once __DIR__ . '/../../../plugins/contabilidad/lib/asientos.php';
