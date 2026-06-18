<?php
/**
 * Tests for the migration generator (tools/make-migration.php).
 * Requiring the file only defines its functions — the CLI entry point is
 * guarded so it does not run under the test harness.
 */

require_once __DIR__ . '/../tools/make-migration.php';

echo "\nMigration generator\n";

it('maps simple field types to SQL column types', function() {
    assertSame('VARCHAR(255)',  mig_mapType('string'));
    assertSame('TEXT',          mig_mapType('textarea'));
    assertSame('INT',           mig_mapType('int'));
    assertSame('DECIMAL(10,2)', mig_mapType('money'));
    assertSame('TINYINT(1)',    mig_mapType('bool'));
    assertSame('DATETIME',      mig_mapType('datetime'));
    assertNull(mig_mapType('not-a-type'));
});

it('parses a column spec into validated triples', function() {
    $cols = mig_parseColumns('name:string,price:money,active:bool');
    assertSame(3, count($cols));
    assertSame('name',          $cols[0]['name']);
    assertSame('DECIMAL(10,2)', $cols[1]['sql']);
    assertSame('TINYINT(1)',    $cols[2]['sql']);
});

it('rejects an unknown column type', function() {
    $threw = false;
    try { mig_parseColumns('foo:bogus'); } catch (\InvalidArgumentException $e) { $threw = true; }
    assertTrue($threw, 'should reject an unknown column type');
});

it('rejects an unsafe column name', function() {
    $threw = false;
    try { mig_parseColumns('bad name:string'); } catch (\InvalidArgumentException $e) { $threw = true; }
    assertTrue($threw, 'should reject an unsafe column name');
});

it('resolves a singular suffix and default date', function() {
    $o = mig_resolveOptions('products', ['date' => '2026-01-01']);
    assertSame('product', $o['suffix']);
    assertSame('2026-01-01', $o['date']);
});

it('rejects an unsafe table name', function() {
    $threw = false;
    try { mig_resolveOptions('bad-table', []); } catch (\InvalidArgumentException $e) { $threw = true; }
    assertTrue($threw, 'should reject an unsafe table name');
});

it('builds a conventional CREATE TABLE migration', function() {
    $sql = buildMigrationSource(mig_resolveOptions('products', [
        'columns' => 'name:string,active:bool',
        'date'    => '2026-06-18',
    ]));
    assertTrue(strpos($sql, 'CREATE TABLE IF NOT EXISTS `products`') !== false, 'has CREATE TABLE');
    assertTrue(strpos($sql, '`id_product` INT') !== false || strpos($sql, '`id_product`') !== false, 'has id_<suffix>');
    assertTrue(strpos($sql, '`name_product`') !== false, 'applies the suffix to columns');
    assertTrue((bool)preg_match('/`active_product`\s+TINYINT\(1\)\s+NOT NULL DEFAULT 0/', $sql), 'bool defaults to NOT NULL 0');
    assertTrue(strpos($sql, 'PRIMARY KEY (`id_product`)') !== false, 'has the primary key');
    assertTrue(strpos($sql, '`date_created_product`') !== false && strpos($sql, '`date_updated_product`') !== false, 'has timestamps');
    assertTrue(strpos($sql, '-- ROLLBACK:') !== false && strpos($sql, 'DROP TABLE IF EXISTS `products`') !== false, 'has a rollback comment');
});
