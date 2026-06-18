# Tests

Lightweight, zero-dependency test suite for the framework. No external
test framework is required (in line with the project's "framework propio"
philosophy) — just PHP and the Composer autoload in `api/vendor/`.

## Running

```bash
php tests/run.php
```

The runner exits with code `0` when everything passes and `1` on any
failure, so it can be wired into CI or a pre-push hook.

## What is covered

`tests/api_security_test.php` — the core API security defenses hardened
during the security audit (no database required):

- **`Connection::sanitizeIdentifier`** — table/column identifiers only allow `[a-zA-Z0-9_]`.
- **`Connection::sanitizeQualifiedIdentifier`** — relational identifiers only allow `*`, `col`, `table.col`, `table.*`.
- **`Connection::validIdentifierList`** — comma-separated identifier lists reject any unsafe item.
- **`Connection::sanitizeOrderMode`** — `ORDER BY` direction is restricted to `ASC`/`DESC`.
- **`Connection::internalWriteTables`** — the token-less (`token=no`) write allow-list is limited to CMS-internal tables.
- **`Connection::tokenValidate`** — JWT signature verification: tampered, forged (wrong secret), expired, and malformed tokens are all rejected.

`tests/logger_test.php` — the central `Logger` (`core/logger.php`): formatted
output with level + timestamp + JSON context, all log levels, and that it
never throws. Writes to a temp file so the real application log is untouched.

## Adding tests

Create `tests/<name>_test.php` and `require` it from `tests/run.php`.
Use the helpers exposed by the runner:

```php
it('does the thing', fn() => assertSame('expected', subjectUnderTest()));
// assertSame / assertTrue / assertFalse / assertNull
```

Keep new tests dependency-free where possible. Tests that need a live
database or HTTP server should be clearly marked and kept separate, since
the default suite is meant to run anywhere without setup.
