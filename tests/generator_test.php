<?php
/**
 * Tests for the web page generator (tools/make-web-page.php).
 * Requiring the file only defines its functions — the CLI entry point is
 * guarded so it does not run under the test harness.
 */

require_once __DIR__ . '/../tools/make-web-page.php';

echo "\nWeb page generator\n";

it('derives a singular suffix from a plural table', function() {
    assertSame('product',  mwp_deriveSuffix('products'));
    assertSame('category', mwp_deriveSuffix('categories'));
    assertSame('class',    mwp_deriveSuffix('class')); // trailing 'ss' is not stripped
});

it('resolves default columns from the table', function() {
    $o = mwp_resolveOptions('products', []);
    assertSame('id_product',   $o['idColumn']);
    assertSame('name_product', $o['titleColumn']);
    assertSame('Products',     $o['titlePlural']);
});

it('honors explicit column overrides', function() {
    $o = mwp_resolveOptions('orders', ['suffix' => 'order', 'title' => 'ref_order', 'id' => 'pk_order']);
    assertSame('pk_order',  $o['idColumn']);
    assertSame('ref_order', $o['titleColumn']);
});

it('rejects an unsafe table name', function() {
    $threw = false;
    try {
        mwp_resolveOptions('bad; DROP TABLE x', []);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assertTrue($threw, 'should reject an unsafe table name');
});

it('resolves list and detail file names', function() {
    $o = mwp_resolveOptions('products', []);
    assertSame('products', $o['fileName']);
    assertSame('products-detail', $o['detailFile']);

    $named = mwp_resolveOptions('products', ['name' => 'catalog']);
    assertSame('catalog', $named['fileName']);
    assertSame('catalog-detail', $named['detailFile']);
});

/**
 * Assert a generated source is syntactically valid PHP (php -l).
 */
function assertValidPhp($src, $label) {
    $tmp = tempnam(sys_get_temp_dir(), 'genpage');
    file_put_contents($tmp, $src);
    $out = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    assertSame(0, $code, "{$label} should pass php -l: " . implode("\n", $out));
}

it('generates a valid, escaped list page that links to the detail page', function() {
    $src = buildWebPageSource(mwp_resolveOptions('products', []));
    assertTrue(strpos($src, "= 'products';") !== false, 'should embed the table name');
    assertTrue(strpos($src, 'htmlspecialchars') !== false, 'output must be escaped');
    assertTrue(strpos($src, "views/template.php") !== false, 'should include the shared template');
    assertTrue(strpos($src, "'products-detail'") !== false, 'should reference the detail page');
    assertTrue(strpos($src, 'View details') !== false, 'should link to the detail page');
    assertValidPhp($src, 'list page');
});

it('generates a valid detail page that fetches by id and shows all fields', function() {
    $src = buildDetailPageSource(mwp_resolveOptions('products', []));
    assertTrue(strpos($src, 'ApiController::getById') !== false, 'should fetch one record by id');
    assertTrue(strpos($src, 'foreach ($record as $field => $value)') !== false, 'should iterate every field');
    assertTrue(strpos($src, 'Back to list') !== false, 'should link back to the list');
    assertTrue(strpos($src, 'htmlspecialchars') !== false, 'output must be escaped');
    assertValidPhp($src, 'detail page');
});
