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

it('generates valid, escaped PHP that uses the shared template', function() {
    $src = buildWebPageSource(mwp_resolveOptions('products', []));
    assertTrue(strpos($src, "= 'products';") !== false, 'should embed the table name');
    assertTrue(strpos($src, 'htmlspecialchars') !== false, 'output must be escaped');
    assertTrue(strpos($src, "views/template.php") !== false, 'should include the shared template');

    // The generated source must itself be syntactically valid PHP.
    // Use the tempnam() path directly (php -l checks any extension) so no
    // orphan temp file is left behind.
    $tmp = tempnam(sys_get_temp_dir(), 'genpage');
    file_put_contents($tmp, $src);
    $out = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    assertSame(0, $code, 'generated source should pass php -l: ' . implode("\n", $out));
});
