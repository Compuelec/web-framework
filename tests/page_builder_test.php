<?php
/**
 * Tests for the configurable page builder (tools/page-builder.php).
 */

require_once __DIR__ . '/../tools/page-builder.php';

echo "\nConfigurable page builder\n";

it('normalizes a config with defaults', function() {
    $c = pb_normalizeConfig(['table' => 'products']);
    assertSame('product', $c['suffix']);
    assertSame('id_product', $c['idColumn']);
    assertSame('name_product', $c['titleColumn']);
    assertSame('cards', $c['layout']);
    assertSame(3, $c['perRow']);
    assertSame('#0d6efd', $c['accent']);
    assertSame('products-detail', $c['detailFile']);
});

it('rejects an invalid table', function() {
    $threw = false;
    try { pb_normalizeConfig(['table' => 'bad-table']); } catch (\InvalidArgumentException $e) { $threw = true; }
    assertTrue($threw);
});

it('filters out unsafe column names', function() {
    $c = pb_normalizeConfig(['table' => 'products', 'columns' => ['name_product', 'bad col', '(evil)']]);
    assertSame(['name_product'], $c['columns']);
});

it('clamps layout, perRow and accent to safe values', function() {
    $c = pb_normalizeConfig(['table' => 'products', 'layout' => 'bogus', 'perRow' => 99, 'accent' => 'red; }']);
    assertSame('cards', $c['layout']);
    assertSame(3, $c['perRow']);
    assertSame('#0d6efd', $c['accent']);
});

it('keeps valid layout/perRow/accent', function() {
    $c = pb_normalizeConfig(['table' => 'products', 'layout' => 'table', 'perRow' => 4, 'accent' => '#abc']);
    assertSame('table', $c['layout']);
    assertSame(4, $c['perRow']);
    assertSame('#abc', $c['accent']);
});

it('round-trips the config through generate/extract', function() {
    $cfg = [
        'table' => 'products', 'heading' => 'Nuestros Productos',
        'columns' => ['name_product', 'price_product'], 'customCss' => '.x{}', 'accent' => '#e91e63',
    ];
    $src  = buildConfigurablePage($cfg);
    $back = pb_extractConfig($src);
    assertTrue(is_array($back), 'should extract a config');
    assertSame('Nuestros Productos', $back['heading']);
    assertSame(['name_product', 'price_product'], $back['columns']);
    assertSame('.x{}', $back['customCss']);
    assertSame('#e91e63', $back['accent']);
});

/**
 * Assert generated source is valid PHP.
 */
function assertValidPagePhp($src, $label) {
    $tmp = tempnam(sys_get_temp_dir(), 'pb');
    file_put_contents($tmp, $src);
    $out = []; $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    assertSame(0, $code, "{$label} should be valid PHP: " . implode("\n", $out));
}

it('generates valid PHP for every layout', function() {
    foreach (['cards', 'table', 'list'] as $layout) {
        $src = buildConfigurablePage(['table' => 'products', 'layout' => $layout, 'withDetail' => true]);
        assertValidPagePhp($src, "list/{$layout}");
    }
    assertValidPagePhp(buildConfigurableDetail(['table' => 'products']), 'detail');
});

it('flags framework tables as system and user tables as custom', function() {
    assertTrue(pb_isSystemTable('admins'), 'admins is system');
    assertTrue(pb_isSystemTable('PAGES'), 'case-insensitive');
    assertTrue(pb_isSystemTable('page_seo'), 'plugin table is system');
    assertTrue(pb_isSystemTable('workflows'), 'plugin table is system');
    assertFalse(pb_isSystemTable('products'), 'user table is custom');
    assertFalse(pb_isSystemTable('clientes'), 'user table is custom');
});

it('escapes record output but emits custom content verbatim', function() {
    $src = buildConfigurablePage(['table' => 'products', 'customHtml' => '<x-promo></x-promo>']);
    assertTrue(strpos($src, 'htmlspecialchars') !== false, 'data is escaped');
    assertTrue(strpos($src, "echo \$cfg['customHtml']") !== false, 'custom html is emitted from config');
});
