<?php
/**
 * Tests for the central Logger. Writes to a temporary file so they run
 * anywhere and never touch the real application log.
 */

echo "\nLogger\n";

$logTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'logger_test_' . getmypid() . '.log';
@unlink($logTmp);
Logger::setLogFile($logTmp);

it('writes a formatted ERROR line with JSON context', function() use ($logTmp) {
    Logger::error('something failed', ['code' => 42]);
    $content = (string)@file_get_contents($logTmp);
    assertTrue(strpos($content, 'ERROR: something failed') !== false, 'should contain level + message');
    assertTrue(strpos($content, '{"code":42}') !== false, 'should contain JSON context');
});

it('writes WARNING and INFO levels', function() use ($logTmp) {
    Logger::warning('a warning');
    Logger::info('an info');
    $content = (string)@file_get_contents($logTmp);
    assertTrue(strpos($content, 'WARNING: a warning') !== false);
    assertTrue(strpos($content, 'INFO: an info') !== false);
});

it('does not throw when context is empty', function() {
    Logger::error('no context here');
    assertTrue(true);
});

it('prefixes each line with a [timestamp]', function() use ($logTmp) {
    $firstLine = strtok((string)@file_get_contents($logTmp), "\n");
    assertTrue((bool)preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] /', $firstLine), 'line must start with [YYYY-MM-DD HH:MM:SS]');
});

// Cleanup and reset so later suites are unaffected.
@unlink($logTmp);
Logger::setLogFile(null);
