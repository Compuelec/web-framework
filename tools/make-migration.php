<?php
/**
 * Migration generator
 *
 * Scaffolds a `CREATE TABLE` SQL migration in migrations/ following the
 * project's conventions: the suffix column naming (id_<suffix>,
 * <col>_<suffix>), aligned types, created/updated timestamps, and a
 * commented ROLLBACK. This is the CLI counterpart of the create-migration
 * workflow.
 *
 * Usage:
 *   php tools/make-migration.php <table> [options]
 *
 * Options:
 *   --suffix=<s>     Column suffix (default: derived from <table>)
 *   --columns=<csv>  Comma list of "name:type" (e.g. "name:string,price:money,active:bool")
 *   --name=<file>    Output file name without extension (default: create_<table>_table)
 *   --date=<Y-m-d>   Date stamp in the header (default: today)
 *   --force          Overwrite the output file if it already exists
 *   --stdout         Print the migration to stdout instead of writing a file
 *
 * Supported column types:
 *   string|text, textarea|longtext, int, double, money, bool|boolean,
 *   date, datetime, time, email
 *
 * Example:
 *   php tools/make-migration.php products --suffix=product \
 *       --columns="name:string,price:money,active:bool,description:textarea"
 */

/**
 * Map a simple field type to a MySQL column type.
 */
function mig_mapType($type) {
    switch (strtolower($type)) {
        case 'string':
        case 'text':
        case 'varchar':
        case 'email':    return 'VARCHAR(255)';
        case 'textarea':
        case 'longtext': return 'TEXT';
        case 'int':
        case 'integer':  return 'INT';
        case 'double':
        case 'float':    return 'DOUBLE';
        case 'money':
        case 'decimal':  return 'DECIMAL(10,2)';
        case 'bool':
        case 'boolean':  return 'TINYINT(1)';
        case 'date':     return 'DATE';
        case 'datetime': return 'DATETIME';
        case 'time':     return 'TIME';
        default:         return null; // unknown
    }
}

/**
 * Validate a value is a safe identifier-style token.
 */
function mig_isIdentifier($name) {
    return is_string($name) && preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
}

/**
 * Derive a singular suffix from a table name (small heuristic).
 */
function mig_deriveSuffix($table) {
    if (substr($table, -3) === 'ies') {
        return substr($table, 0, -3) . 'y';
    }
    if (substr($table, -1) === 's' && substr($table, -2) !== 'ss') {
        return substr($table, 0, -1);
    }
    return $table;
}

/**
 * Parse a "name:type,name:type" column spec into validated [name, type, sql]
 * triples. Throws InvalidArgumentException on bad input.
 */
function mig_parseColumns($spec) {
    $columns = [];
    if ($spec === null || $spec === '') {
        return $columns;
    }
    foreach (explode(',', $spec) as $pair) {
        $pair = trim($pair);
        if ($pair === '') {
            continue;
        }
        $parts = explode(':', $pair, 2);
        $name  = trim($parts[0]);
        $type  = isset($parts[1]) ? trim($parts[1]) : 'string';

        if (!mig_isIdentifier($name)) {
            throw new InvalidArgumentException("Invalid column name: '{$name}'.");
        }
        $sql = mig_mapType($type);
        if ($sql === null) {
            throw new InvalidArgumentException("Unknown column type: '{$type}' (for column '{$name}').");
        }
        $columns[] = ['name' => $name, 'type' => strtolower($type), 'sql' => $sql];
    }
    return $columns;
}

/**
 * Resolve CLI options into a generator option array. Throws on bad input.
 */
function mig_resolveOptions($table, array $flags) {
    if (!mig_isIdentifier($table)) {
        throw new InvalidArgumentException("Invalid table name: '{$table}'.");
    }
    $suffix = $flags['suffix'] ?? mig_deriveSuffix($table);
    if (!mig_isIdentifier($suffix)) {
        throw new InvalidArgumentException("Invalid suffix: '{$suffix}'.");
    }
    return [
        'table'       => $table,
        'suffix'      => $suffix,
        'columns'     => mig_parseColumns($flags['columns'] ?? ''),
        'date'        => isset($flags['date']) && is_string($flags['date']) ? $flags['date'] : date('Y-m-d'),
        'description' => "Create the {$table} table",
    ];
}

/**
 * Build the SQL source of a CREATE TABLE migration. Pure function (no I/O).
 *
 * @param array $opts table, suffix, columns, date, description
 * @return string
 */
function buildMigrationSource(array $opts) {
    $table  = $opts['table'];
    $suffix = $opts['suffix'];
    $date   = $opts['date'];
    $desc   = $opts['description'];

    $idCol      = 'id_' . $suffix;
    $createdCol = 'date_created_' . $suffix;
    $updatedCol = 'date_updated_' . $suffix;

    // Column definitions (id first, user columns, then timestamps).
    $defs = [];
    $defs[] = [$idCol, 'INT', 'NOT NULL AUTO_INCREMENT'];

    foreach ($opts['columns'] as $col) {
        $name = $col['name'] . '_' . $suffix;
        $null = ($col['type'] === 'bool' || $col['type'] === 'boolean')
            ? 'NOT NULL DEFAULT 0'
            : 'NULL DEFAULT NULL';
        $defs[] = [$name, $col['sql'], $null];
    }

    $defs[] = [$createdCol, 'DATETIME', 'NOT NULL DEFAULT CURRENT_TIMESTAMP'];
    $defs[] = [$updatedCol, 'DATETIME', 'NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'];

    // Align the column names and types for readability.
    $nameWidth = 0;
    $typeWidth = 0;
    foreach ($defs as $d) {
        $nameWidth = max($nameWidth, strlen($d[0]) + 2); // + backticks
        $typeWidth = max($typeWidth, strlen($d[1]));
    }

    $lines = [];
    foreach ($defs as $d) {
        $lines[] = sprintf(
            "    %-{$nameWidth}s %-{$typeWidth}s %s",
            '`' . $d[0] . '`',
            $d[1],
            $d[2]
        );
    }
    $columnsSql = implode(",\n", $lines);

    return <<<SQL
-- Migration: Create {$table} table
-- Description: {$desc}
-- Date: {$date}

CREATE TABLE IF NOT EXISTS `{$table}` (
{$columnsSql},
    PRIMARY KEY (`{$idCol}`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ROLLBACK:
-- DROP TABLE IF EXISTS `{$table}`;

SQL;
}

// ---------------------------------------------------------------------------
// CLI entry point (skipped when this file is required from tests).
// ---------------------------------------------------------------------------
if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {

    $args  = array_slice($argv, 1);
    $table = null;
    $flags = [];

    foreach ($args as $arg) {
        if (strpos($arg, '--') === 0) {
            $kv = explode('=', substr($arg, 2), 2);
            $flags[$kv[0]] = $kv[1] ?? true;
        } elseif ($table === null) {
            $table = $arg;
        }
    }

    if ($table === null || isset($flags['help'])) {
        fwrite(STDOUT, "Usage: php tools/make-migration.php <table> [--suffix= --columns=\"name:type,...\" --name= --date= --force --stdout]\n");
        exit($table === null ? 1 : 0);
    }

    try {
        $opts = mig_resolveOptions($table, $flags);
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }

    $sql = buildMigrationSource($opts);

    if (isset($flags['stdout'])) {
        fwrite(STDOUT, $sql);
        exit(0);
    }

    $fileName = isset($flags['name']) && is_string($flags['name']) ? $flags['name'] : ('create_' . $table . '_table');
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fileName)) {
        fwrite(STDERR, "Error: invalid output file name '{$fileName}'.\n");
        exit(1);
    }

    $target = __DIR__ . '/../migrations/' . $fileName . '.sql';
    if (file_exists($target) && !isset($flags['force'])) {
        fwrite(STDERR, "Error: {$target} already exists. Use --force to overwrite.\n");
        exit(1);
    }

    if (@file_put_contents($target, $sql) === false) {
        fwrite(STDERR, "Error: could not write {$target} (check permissions).\n");
        exit(1);
    }

    fwrite(STDOUT, "Created migrations/{$fileName}.sql for table '{$table}'.\n");
    fwrite(STDOUT, "Review it, then apply with run_migration.php or your migration process.\n");
    exit(0);
}
