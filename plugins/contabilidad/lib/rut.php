<?php
/**
 * RUT (Rol Único Tributario) — Chilean tax-id validation + formatting.
 *
 * Algoritmo módulo 11 oficial del SII:
 *   1. Recorrer los dígitos del cuerpo de derecha a izquierda multiplicando
 *      por la serie 2,3,4,5,6,7 (y reiniciando).
 *   2. Sumar todos los productos.
 *   3. resto = suma mod 11
 *   4. dv = 11 - resto
 *      - si dv == 11 → "0"
 *      - si dv == 10 → "K"
 *      - resto → ese mismo dígito
 *
 * Public API:
 *   rut_clean(string $raw): string  → "76.123.456-7" → "761234567"
 *   rut_format(string $raw): string → "761234567"   → "76.123.456-7"
 *   rut_dv(string $body): string    → calcula DV de un cuerpo numérico
 *   rut_is_valid(string $raw): bool → valida formato + DV
 */

if (defined('WPB_RUT_LIB_LOADED')) { return; }
define('WPB_RUT_LIB_LOADED', true);

/**
 * Strips dots, dashes and spaces. Leaves digits + optional trailing K.
 * The DV is always uppercase (the SII publishes RUTs that way).
 */
function rut_clean(string $raw): string {
    $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $raw));
    return $clean === null ? '' : $clean;
}

/**
 * Computes the verification digit from the body (the digits BEFORE the DV).
 * Returns the digit as a string ("0".."9" or "K"). Empty input → "".
 */
function rut_dv(string $body): string {
    $body = preg_replace('/\D/', '', $body);
    if ($body === '' || $body === null) { return ''; }
    $sum = 0;
    $multiplier = 2;
    for ($i = strlen($body) - 1; $i >= 0; $i--) {
        $sum += ((int)$body[$i]) * $multiplier;
        $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
    }
    $rest = $sum % 11;
    $dv   = 11 - $rest;
    if ($dv === 11) { return '0'; }
    if ($dv === 10) { return 'K'; }
    return (string)$dv;
}

/**
 * Valid when: 7-8 digit body + correct DV (computed via module 11).
 * Accepts RUTs with or without dots/dashes — caller doesn't need to
 * normalize first.
 */
function rut_is_valid(string $raw): bool {
    $clean = rut_clean($raw);
    // 8 chars min (7 body digits + DV), 9 max (8 body digits + DV)
    if (strlen($clean) < 8 || strlen($clean) > 9) { return false; }
    $body = substr($clean, 0, -1);
    $dv   = substr($clean, -1);
    if (!ctype_digit($body)) { return false; }
    return hash_equals(rut_dv($body), $dv);
}

/**
 * Pretty-prints a RUT: "76.123.456-7". Returns the input unchanged if it
 * doesn't look like a RUT (so render code can pass any user-entered string
 * without breaking the page).
 */
function rut_format(string $raw): string {
    $clean = rut_clean($raw);
    if (strlen($clean) < 2) { return $raw; }
    $body = substr($clean, 0, -1);
    $dv   = substr($clean, -1);
    if (!ctype_digit($body)) { return $raw; }
    // Insert dots every 3 chars from the right.
    $reversed = strrev($body);
    $groups   = str_split($reversed, 3);
    $bodyDot  = strrev(implode('.', $groups));
    return $bodyDot . '-' . $dv;
}
