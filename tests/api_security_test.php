<?php
/**
 * Security-focused tests for the dynamic API.
 *
 * These cover the SQL-identifier sanitizers and the JWT signature
 * verification — the core defenses hardened across the security audit.
 * They require no database connection (pure logic + cryptographic checks),
 * so they run anywhere `php` and the Composer autoload are available.
 */

echo "\nConnection::sanitizeIdentifier\n";
it('accepts a plain identifier',       fn() => assertSame('users', Connection::sanitizeIdentifier('users')));
it('accepts digits and underscores',   fn() => assertSame('id_user_1', Connection::sanitizeIdentifier('id_user_1')));
it('rejects a hyphen',                 fn() => assertNull(Connection::sanitizeIdentifier('a-b')));
it('rejects a space',                  fn() => assertNull(Connection::sanitizeIdentifier('a b')));
it('rejects injection characters',     fn() => assertNull(Connection::sanitizeIdentifier('a;DROP TABLE x')));
it('rejects an empty string',          fn() => assertNull(Connection::sanitizeIdentifier('')));
it('rejects a bare star',              fn() => assertNull(Connection::sanitizeIdentifier('*')));

echo "\nConnection::sanitizeQualifiedIdentifier\n";
it('accepts a star',                   fn() => assertSame('*', Connection::sanitizeQualifiedIdentifier('*')));
it('accepts a column',                 fn() => assertSame('name_user', Connection::sanitizeQualifiedIdentifier('name_user')));
it('accepts table.column',             fn() => assertSame('users.id_user', Connection::sanitizeQualifiedIdentifier('users.id_user')));
it('accepts table.star',               fn() => assertSame('users.*', Connection::sanitizeQualifiedIdentifier('users.*')));
it('trims surrounding whitespace',     fn() => assertSame('users.id', Connection::sanitizeQualifiedIdentifier('  users.id  ')));
it('rejects a subquery',               fn() => assertNull(Connection::sanitizeQualifiedIdentifier('(SELECT password FROM admins)')));
it('rejects an OR injection',          fn() => assertNull(Connection::sanitizeQualifiedIdentifier('a OR 1=1')));
it('rejects multiple dots',            fn() => assertNull(Connection::sanitizeQualifiedIdentifier('a.b.c')));
it('rejects a backtick',               fn() => assertNull(Connection::sanitizeQualifiedIdentifier('`users`')));

echo "\nConnection::validIdentifierList\n";
it('accepts a comma list',             fn() => assertTrue(Connection::validIdentifierList('a,b,c')));
it('accepts a qualified list',         fn() => assertTrue(Connection::validIdentifierList('users.id_user,orders.id_order')));
it('accepts a single star',            fn() => assertTrue(Connection::validIdentifierList('*')));
it('rejects when any item is unsafe',  fn() => assertFalse(Connection::validIdentifierList('a,(SELECT 1)')));
it('rejects a trailing injection',     fn() => assertFalse(Connection::validIdentifierList('id; DROP TABLE x')));
it('rejects an empty list',            fn() => assertFalse(Connection::validIdentifierList('')));
it('rejects null',                     fn() => assertFalse(Connection::validIdentifierList(null)));

echo "\nConnection::sanitizeOrderMode\n";
it('accepts ASC',                      fn() => assertSame('ASC', Connection::sanitizeOrderMode('ASC')));
it('normalizes lowercase asc',         fn() => assertSame('ASC', Connection::sanitizeOrderMode('asc')));
it('accepts DESC',                     fn() => assertSame('DESC', Connection::sanitizeOrderMode('DESC')));
it('rejects an arbitrary value',       fn() => assertNull(Connection::sanitizeOrderMode('RANDOM()')));
it('rejects an empty value',           fn() => assertNull(Connection::sanitizeOrderMode('')));

echo "\nConnection::internalWriteTables\n";
it('returns a non-empty list of safe identifiers', function() {
    // Asserts the security invariant rather than the exact default list, so
    // the test stays deterministic even if a local config customizes it.
    $tables = Connection::internalWriteTables();
    assertTrue(is_array($tables) && count($tables) > 0, 'should be a non-empty array');
    foreach ($tables as $tbl) {
        assertSame($tbl, Connection::sanitizeIdentifier($tbl), "allow-list entry '$tbl' must be a safe identifier");
    }
});
it('excludes business tables (token-less writes stay CMS-internal)', function() {
    $tables = Connection::internalWriteTables();
    assertFalse(in_array('files', $tables, true), "must not allow token-less writes to 'files'");
    assertFalse(in_array('users', $tables, true), "must not allow token-less writes to 'users'");
});

echo "\nConnection::tokenValidate (JWT signature verification)\n";
$secret = Connection::getConfig()['jwt']['secret'] ?? '';

it('rejects a token signed with the wrong secret', function() {
    $forged = \Firebase\JWT\JWT::encode(['iat' => time(), 'exp' => time() + 9999, 'data' => ['id' => 1]], 'definitely-not-the-secret');
    assertSame('no-auth', Connection::tokenValidate($forged, 'admins', 'admin'));
});
it('reports an expired token as expired', function() use ($secret) {
    $expired = \Firebase\JWT\JWT::encode(['iat' => time() - 100000, 'exp' => time() - 50000, 'data' => ['id' => 1]], $secret);
    assertSame('expired', Connection::tokenValidate($expired, 'admins', 'admin'));
});
it('rejects a tampered token', function() use ($secret) {
    $valid = \Firebase\JWT\JWT::encode(['iat' => time(), 'exp' => time() + 9999, 'data' => ['id' => 1]], $secret);
    // Flip only the last character so the token keeps its length/structure
    // and decoding reaches (and fails at) signature verification.
    $tampered = substr($valid, 0, -1) . (substr($valid, -1) === 'A' ? 'B' : 'A');
    assertSame('no-auth', Connection::tokenValidate($tampered, 'admins', 'admin'));
});
it('rejects a non-JWT string',         fn() => assertSame('no-auth', Connection::tokenValidate('not-a-jwt', 'admins', 'admin')));
it('rejects an empty token',           fn() => assertSame('no-auth', Connection::tokenValidate('', 'admins', 'admin')));
