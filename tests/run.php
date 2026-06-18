<?php
/**
 * Lightweight zero-dependency test runner for the Web Framework.
 *
 * Usage:  php tests/run.php
 *
 * Exits with code 0 when all tests pass, 1 otherwise (CI-friendly).
 * No external framework is used, in line with the project's
 * "framework propio, sin dependencias externas" philosophy.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$GLOBALS['__tests'] = ['pass' => 0, 'fail' => 0, 'failures' => []];

/**
 * Register and run a single test case.
 */
function it($name, callable $fn) {
    try {
        $fn();
        $GLOBALS['__tests']['pass']++;
        echo "  \xE2\x9C\x93 $name\n";
    } catch (\Throwable $e) {
        $GLOBALS['__tests']['fail']++;
        $GLOBALS['__tests']['failures'][] = $name . ' — ' . $e->getMessage();
        echo "  \xE2\x9C\x97 $name — " . $e->getMessage() . "\n";
    }
}

function assertSame($expected, $actual, $msg = '') {
    if ($expected !== $actual) {
        throw new \Exception(($msg ? $msg . ': ' : '')
            . 'expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}
function assertTrue($v, $msg = '')  { assertSame(true, $v, $msg); }
function assertFalse($v, $msg = '') { assertSame(false, $v, $msg); }
function assertNull($v, $msg = '')  { assertSame(null, $v, $msg); }

// Code under test (loads the Composer autoloader + models).
require_once __DIR__ . '/../api/models/connection.php';

// Test suites.
require __DIR__ . '/api_security_test.php';

// Summary.
$t = $GLOBALS['__tests'];
$total = $t['pass'] + $t['fail'];
echo "\n" . str_repeat('-', 52) . "\n";
echo "Tests: {$total}   Passed: {$t['pass']}   Failed: {$t['fail']}\n";

if ($t['fail'] > 0) {
    echo "\nFAILURES:\n";
    foreach ($t['failures'] as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "All tests passed.\n";
exit(0);
