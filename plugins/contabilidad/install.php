<?php
/**
 * Contabilidad PyMe — CLI installer.
 *
 * Ejecuta install() / uninstall() del plugin desde línea de comando.
 * Útil para bootstrapping (fresh install) y para CI/tests.
 *
 * Uso:
 *   php plugins/contabilidad/install.php install
 *   php plugins/contabilidad/install.php uninstall           # conserva datos
 *   php plugins/contabilidad/install.php uninstall --drop    # borra datos
 *   php plugins/contabilidad/install.php status              # muestra info
 *
 * También se puede llamar programáticamente:
 *   require_once __DIR__ . '/plugins/contabilidad/contabilidad.php';
 *   ContabilidadController::install();
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("install.php solo se ejecuta desde CLI.\n");
}

require_once __DIR__ . '/contabilidad.php';

$cmd  = $argv[1] ?? 'status';
$flags = array_slice($argv, 2);
$drop = in_array('--drop', $flags, true);

function report(array $res) {
    echo ($res['ok'] ?? false ? "✓ " : "✗ "), $res['message'] ?? '', "\n";
    foreach ($res['applied'] ?? [] as $step) {
        echo "  · $step\n";
    }
}

switch ($cmd) {
    case 'install':
        echo "Instalando Contabilidad PyMe…\n";
        report(ContabilidadController::install());
        break;

    case 'uninstall':
        echo "Desinstalando Contabilidad PyMe" . ($drop ? " (con DROP de datos)" : "") . "…\n";
        report(ContabilidadController::uninstall($drop));
        break;

    case 'status':
        $s = ContabilidadController::getStatus();
        echo "Plugin:     ", $s['name'], " v", $s['version'], "\n";
        echo "Instalado:  ", ($s['installed'] ? 'sí' : 'no'), "\n";
        echo "Orígenes de asiento registrados:\n";
        foreach ($s['origenes'] as $o) { echo "  · $o\n"; }
        echo "Config resumida:\n";
        echo "  país:     ", $s['config']['country']['code'] ?? '?', " (", $s['config']['country']['currency'] ?? '?', ")\n";
        echo "  IVA:      ", (($s['config']['accounting']['iva_rate'] ?? 0) * 100), "%\n";
        echo "  Payku:    ", (($s['config']['payku']['integration_enabled'] ?? false) ? 'habilitado' : 'off'), "\n";
        break;

    default:
        echo "Uso: php install.php <install|uninstall|status> [--drop]\n";
        exit(1);
}
