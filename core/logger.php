<?php

/**
 * Central Logger
 *
 * Lightweight, dependency-free logging utility. Writes structured lines to a
 * project-local, web-denied log file (logs/app-YYYY-MM-DD.log) and NEVER
 * throws — it falls back to PHP's error_log() on any failure — so it is safe
 * to call from request handlers and catch blocks.
 *
 * Usage:
 *   require_once __DIR__ . '/../../core/logger.php';
 *   Logger::error('Theme settings AJAX error', ['exception' => $e->getMessage()]);
 */
class Logger {

    const ERROR   = 'ERROR';
    const WARNING = 'WARNING';
    const INFO    = 'INFO';
    const DEBUG   = 'DEBUG';

    /** @var string|null Resolved (or explicitly overridden) log file path. */
    private static $logFile = null;

    public static function error($message, array $context = [])   { self::log(self::ERROR,   $message, $context); }
    public static function warning($message, array $context = []) { self::log(self::WARNING, $message, $context); }
    public static function info($message, array $context = [])    { self::log(self::INFO,    $message, $context); }
    public static function debug($message, array $context = [])   { self::log(self::DEBUG,   $message, $context); }

    /**
     * Write a single log line. Never throws.
     */
    public static function log($level, $message, array $context = []) {
        $line = self::format($level, $message, $context);
        $file = self::resolveLogFile();

        // error_log() does not throw. Mode 3 appends to $file; if that fails
        // (or no writable file is available) fall back to the default log.
        if ($file === null || @error_log($line . PHP_EOL, 3, $file) === false) {
            @error_log($line);
        }
    }

    /**
     * Override the destination log file (used by tests, or to centralize logs).
     */
    public static function setLogFile($path) {
        self::$logFile = $path;
    }

    /**
     * Format a structured, single-line log entry.
     */
    private static function format($level, $message, array $context) {
        $timestamp = date('Y-m-d H:i:s');
        $ctx = '';
        if (!empty($context)) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $ctx = ' ' . ($encoded !== false ? $encoded : '[uencodable context]');
        }
        return sprintf('[%s] %s: %s%s', $timestamp, strtoupper((string)$level), (string)$message, $ctx);
    }

    /**
     * Resolve a project-local, web-denied log file. Returns null when no
     * writable location is available so the caller falls back to error_log().
     */
    private static function resolveLogFile() {
        // An explicit override (e.g. from tests) always wins and is cached.
        if (self::$logFile !== null) {
            return self::$logFile;
        }

        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($dir)) {
            // 0755 (not 0700) so the web server and CLI runner, which may run
            // as different users, can both use the directory; web access is
            // still denied by the .htaccess below.
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return null;
            }
            // Deny direct web access to the log directory.
            @file_put_contents($dir . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\n");
        }

        if (!is_writable($dir)) {
            return null;
        }

        // Resolve the daily file fresh on every call (NOT cached) so that
        // long-running processes roll over to the next day's log file.
        return $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
    }
}
