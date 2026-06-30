<?php
/**
 * Cierres de mes — bloqueo de meses ya declarados al SII.
 *
 * Una vez que el contador cierra un mes (ej. mayo 2026), todos los
 * comprobantes/asientos cuyas fechas caen en ese rango quedan
 * inmutables: no se pueden editar, no se pueden anular, no se puede
 * cargar uno nuevo retroactivo, no se puede generar un asiento nuevo
 * para una compra/venta de ese período.
 *
 * Modelo: la tabla cierres_mes tiene una fila por mes cerrado. La
 * existencia de la fila es la fuente de verdad — no hay un "estado",
 * un mes está cerrado si y solo si la fila existe.
 *
 * Public API:
 *   mes_esta_cerrado(PDO $db, string $fechaIso): bool
 *   meses_cerrados(PDO $db): array → [['mes', 'anio', 'fecha_cierre', 'usuario']]
 *   cerrar_mes(PDO $db, int $mes, int $anio, string $usuario, string $notas = ''): array
 *   reabrir_mes(PDO $db, int $mes, int $anio): array
 *
 * El guard usado por cargar-venta / cargar-compra / generar-asientos:
 *
 *   require_once __DIR__ . '/_lib/cierres.php';
 *   if (mes_esta_cerrado($db, $fecha)) {
 *       $errors[] = 'El mes de esa fecha está cerrado…';
 *   }
 */

if (defined('WPB_CIERRES_LIB_LOADED')) { return; }
define('WPB_CIERRES_LIB_LOADED', true);

/**
 * Recibe una fecha 'YYYY-MM-DD' y devuelve true si su mes-año tiene fila
 * en cierres_mes. Cualquier formato no parseable devuelve false (no
 * bloquea — al fin y al cabo es una validación de fecha, no un guard).
 */
function mes_esta_cerrado(PDO $db, string $fechaIso): bool {
    if (!preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $fechaIso, $m)) {
        return false;
    }
    $anio = (int)$m[1];
    $mes  = (int)$m[2];
    try {
        $stmt = $db->prepare(
            "SELECT 1 FROM cierres_mes WHERE mes_cierre = :m AND anio_cierre = :a LIMIT 1"
        );
        $stmt->execute([':m' => $mes, ':a' => $anio]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // Tabla puede no existir aún en una instalación antigua — no bloqueamos.
        return false;
    }
}

function meses_cerrados(PDO $db): array {
    try {
        $stmt = $db->query(
            "SELECT id_cierre, mes_cierre, anio_cierre, fecha_cierre, usuario_cierre, notas_cierre
             FROM cierres_mes
             ORDER BY anio_cierre DESC, mes_cierre DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Inserta la fila de cierre. Llave única (mes_cierre, anio_cierre) impide
 * duplicados. Retorna ['ok' => true, 'id' => N] o ['error' => '...'].
 */
function cerrar_mes(PDO $db, int $mes, int $anio, string $usuario, string $notas = ''): array {
    if ($mes < 1 || $mes > 12)             { return ['error' => 'Mes inválido']; }
    if ($anio < 2000 || $anio > 2099)      { return ['error' => 'Año inválido']; }
    try {
        $stmt = $db->prepare(
            "INSERT INTO cierres_mes
                (mes_cierre, anio_cierre, fecha_cierre, usuario_cierre, notas_cierre, date_created_cierre)
             VALUES (:m, :a, NOW(), :u, :n, CURDATE())"
        );
        $stmt->execute([':m' => $mes, ':a' => $anio, ':u' => $usuario, ':n' => $notas]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId()];
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'uniq_mes_anio') !== false) {
            return ['error' => 'Este mes ya estaba cerrado.'];
        }
        return ['error' => 'No se pudo cerrar el mes: ' . $e->getMessage()];
    }
}

function reabrir_mes(PDO $db, int $mes, int $anio): array {
    try {
        $stmt = $db->prepare(
            "DELETE FROM cierres_mes WHERE mes_cierre = :m AND anio_cierre = :a"
        );
        $stmt->execute([':m' => $mes, ':a' => $anio]);
        if ($stmt->rowCount() === 0) {
            return ['error' => 'Ese mes no estaba cerrado.'];
        }
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['error' => 'No se pudo reabrir el mes: ' . $e->getMessage()];
    }
}

/* =========================================================================
   Pre-cierre health check — todo lo que tiene que estar OK para que el
   contador pueda cerrar el mes sin riesgo. Devuelve la lista de problemas
   detectados para ese mes específico. Si el array está vacío, se puede
   cerrar.
   ========================================================================= */
function chequeo_precierre(PDO $db, int $mes, int $anio): array {
    $inicio = sprintf('%04d-%02d-01', $anio, $mes);
    $fin    = date('Y-m-t', strtotime($inicio));
    $problemas = [];

    try {
        // 1. Comprobantes sin asiento (orphans del mes)
        $orphV = (int)$db->prepare(
            "SELECT COUNT(*) FROM comprobantes_venta v
             LEFT JOIN asientos a ON a.origen_asiento='venta' AND a.origen_id_asiento=v.id_venta
             WHERE v.fecha_venta BETWEEN ? AND ?
               AND v.estado_venta != 'anulado' AND a.id_asiento IS NULL"
        )->execute([$inicio, $fin]) === false ? 0 : (int)$db->query(
            "SELECT COUNT(*) FROM comprobantes_venta v
             LEFT JOIN asientos a ON a.origen_asiento='venta' AND a.origen_id_asiento=v.id_venta
             WHERE v.fecha_venta BETWEEN '$inicio' AND '$fin'
               AND v.estado_venta != 'anulado' AND a.id_asiento IS NULL"
        )->fetchColumn();
        if ($orphV > 0) {
            $problemas[] = sprintf('Hay %d venta(s) sin asiento en %s. Generalas en /generar-asientos.', $orphV, $inicio);
        }

        $orphC = (int)$db->query(
            "SELECT COUNT(*) FROM comprobantes_compra c
             LEFT JOIN asientos a ON a.origen_asiento='compra' AND a.origen_id_asiento=c.id_compra
             WHERE c.fecha_compra BETWEEN '$inicio' AND '$fin'
               AND c.estado_compra != 'anulado' AND a.id_asiento IS NULL"
        )->fetchColumn();
        if ($orphC > 0) {
            $problemas[] = sprintf('Hay %d compra(s) sin asiento en %s.', $orphC, $inicio);
        }

        // 2. Sumas descuadradas del mes
        $badV = (int)$db->query(
            "SELECT COUNT(*) FROM comprobantes_venta
             WHERE fecha_venta BETWEEN '$inicio' AND '$fin'
               AND estado_venta != 'anulado'
               AND ABS((neto_venta+iva_venta+exento_venta)-total_venta) > 1"
        )->fetchColumn();
        if ($badV > 0) {
            $problemas[] = sprintf('Hay %d venta(s) con suma neto+IVA+exento ≠ total.', $badV);
        }
        $badC = (int)$db->query(
            "SELECT COUNT(*) FROM comprobantes_compra
             WHERE fecha_compra BETWEEN '$inicio' AND '$fin'
               AND estado_compra != 'anulado'
               AND ABS((neto_compra+iva_compra+exento_compra)-total_compra) > 1"
        )->fetchColumn();
        if ($badC > 0) {
            $problemas[] = sprintf('Hay %d compra(s) con suma descuadrada.', $badC);
        }

        // 3. Asientos descuadrados en el mes (defensa en profundidad)
        $badA = (int)$db->query(
            "SELECT COUNT(*) FROM asientos
             WHERE fecha_asiento BETWEEN '$inicio' AND '$fin'
               AND estado_asiento != 'anulado'
               AND ABS(total_debe_asiento - total_haber_asiento) > 1"
        )->fetchColumn();
        if ($badA > 0) {
            $problemas[] = sprintf('Hay %d asiento(s) con Debe ≠ Haber.', $badA);
        }
    } catch (Throwable $e) {
        $problemas[] = 'Error al validar: ' . $e->getMessage();
    }

    return $problemas;
}
