<?php
/**
 * Tests for the template-based page builder (tools/page-builder.php).
 */

require_once __DIR__ . '/../tools/page-builder.php';

echo "\nTemplate page builder\n";

it('normalizes a config with defaults', function() {
    $c = pb_normalizeConfig(['table' => 'products']);
    assertSame('product', $c['suffix']);
    assertSame('id_product', $c['idColumn']);
    assertSame('name_product', $c['titleColumn']);
    assertSame('products', $c['fileName']);
    assertSame('', $c['heading']);
    assertSame('', $c['template']);
});

it('rejects an invalid table', function() {
    $threw = false;
    try { pb_normalizeConfig(['table' => 'bad-table']); } catch (\InvalidArgumentException $e) { $threw = true; }
    assertTrue($threw);
});

it('rejects an invalid file name', function() {
    $threw = false;
    try { pb_normalizeConfig(['table' => 'products', 'fileName' => 'bad name']); } catch (\InvalidArgumentException $e) { $threw = true; }
    assertTrue($threw);
});

it('flags framework tables as system and user tables as custom', function() {
    assertTrue(pb_isSystemTable('admins'), 'admins is system');
    assertTrue(pb_isSystemTable('PAGES'), 'case-insensitive');
    assertTrue(pb_isSystemTable('page_seo'), 'plugin table is system');
    assertFalse(pb_isSystemTable('products'), 'user table is custom');
});

it('replaces a single field with the escaped value', function() {
    $out = pb_replaceFields('<h1>{{name}}</h1>', ['name' => 'Tom & Jerry']);
    assertSame('<h1>Tom &amp; Jerry</h1>', $out);
});

it('renders an unknown field as empty', function() {
    assertSame('<p></p>', pb_replaceFields('<p>{{nope}}</p>', ['name' => 'x']));
});

it('repeats the {{#cada}} block once per record', function() {
    $tpl = '<ul>{{#cada}}<li>{{name}}</li>{{/cada}}</ul>';
    $records = [['name' => 'A'], ['name' => 'B'], ['name' => 'C']];
    assertSame('<ul><li>A</li><li>B</li><li>C</li></ul>', pb_renderTemplate($tpl, $records, []));
});

it('expands an image gallery block over a JSON array field', function() {
    $row = ['imgs' => '["a.jpg","b.jpg"]'];
    $out = pb_replaceFields('{{#imagenes imgs}}<img src="{{url}}">{{/imagenes}}', $row);
    assertSame('<img src="a.jpg"><img src="b.jpg">', $out);
});

it('decodes URL-encoded image arrays in a gallery block', function() {
    $row = ['imgs' => urlencode('["a.jpg","b.jpg"]')];
    $out = pb_replaceFields('{{#imagenes imgs}}<img src="{{url}}">{{/imagenes}}', $row);
    assertSame('<img src="a.jpg"><img src="b.jpg">', $out);
});

it('image gallery handles spaced {{ url }} and $/backslash safely', function() {
    $row = ['imgs' => json_encode(['a$1.jpg', 'b\\x.jpg'])];
    $out = pb_replaceFields('{{#imagenes imgs}}<img src="{{ url }}">{{/imagenes}}', $row);
    assertSame('<img src="a$1.jpg"><img src="b\\x.jpg">', $out);
});

it('pb_imageUrls returns an empty list for non-array values', function() {
    assertSame([], pb_imageUrls(''));
    assertSame([], pb_imageUrls('not json'));
    assertSame(['x.jpg'], pb_imageUrls('["x.jpg"]'));
});

it('does not re-expand field values that contain {{...}}', function() {
    // A record value that happens to contain a tag must stay literal, not be
    // re-evaluated against the single record.
    $tpl     = '{{#cada}}<i>{{name}}</i>{{/cada}}';
    $records = [['name' => '{{price}}'], ['name' => 'B']];
    $out     = pb_renderTemplate($tpl, $records, ['price' => 'SECRET']);
    assertSame('<i>{{price}}</i><i>B</i>', $out);
});

it('uses the single record for tags outside a repeat block', function() {
    $tpl = '<h1>{{title}}</h1>{{#cada}}<li>{{title}}</li>{{/cada}}';
    $records = [['title' => 'First'], ['title' => 'Second']];
    $single  = pb_pickSingle($records, 'id', null);
    assertSame('<h1>First</h1><li>First</li><li>Second</li>', pb_renderTemplate($tpl, $records, $single));
});

it('pickSingle selects by id, else the first record', function() {
    $records = [['id' => 1, 'n' => 'a'], ['id' => 2, 'n' => 'b']];
    assertSame('b', pb_pickSingle($records, 'id', 2)['n']);
    assertSame('a', pb_pickSingle($records, 'id', null)['n']);
    assertSame('a', pb_pickSingle($records, 'id', 'missing')['n']);
    assertSame([], pb_pickSingle([], 'id', 1));
});

it('round-trips the config through generate/extract', function() {
    $cfg = [
        'table'   => 'products',
        'heading' => 'Catálogo',
        'template' => '<div>{{#cada}}{{name_product}}{{/cada}}</div>',
        'customCss' => '.x{color:red}',
    ];
    $src  = buildConfigurablePage($cfg);
    $back = pb_extractConfig($src);
    assertTrue(is_array($back), 'should extract a config');
    assertSame('Catálogo', $back['heading']);
    assertSame('<div>{{#cada}}{{name_product}}{{/cada}}</div>', $back['template']);
    assertSame('.x{color:red}', $back['customCss']);
});

it('renders a {{#form}} block, prefilled only in edit mode', function() {
    $tpl = '{{#form}}{{input dir}}{{textarea desc}}{{file foto}}{{submit Enviar}}{{/form}}';
    // Edit mode: 4th arg ($formRow) prefills the inputs.
    $edit = pb_renderTemplate($tpl, [], ['dir' => 'X'], ['dir' => 'Calle 1', 'desc' => 'd']);
    assertTrue(strpos($edit, '<form method="post"') !== false, 'wraps in a form');
    assertTrue(strpos($edit, 'name="dir" value="Calle 1"') !== false, 'text input prefilled in edit');
    assertTrue(strpos($edit, '<textarea') !== false && strpos($edit, 'name="desc"') !== false, 'textarea');
    assertTrue(strpos($edit, 'type="file"') !== false && strpos($edit, 'name="foto"') !== false, 'file input');
    assertTrue(strpos($edit, 'Enviar</button>') !== false, 'submit label');
    // Create mode: no $formRow → inputs are empty even if a display record exists.
    $create = pb_renderTemplate($tpl, [], ['dir' => 'Calle 1']);
    assertTrue(strpos($create, 'name="dir" value=""') !== false, 'create form is empty');
});

it('normalizes private flag and columns', function() {
    $c = pb_normalizeConfig(['table' => 'products', 'private' => 1, 'columns' => ['name_product', 'bad col']]);
    assertTrue($c['private'] === true);
    assertSame(['name_product'], $c['columns']);
});

it('normalizes access roles and users', function() {
    $c = pb_normalizeConfig(['table' => 'products', 'accessRoles' => ['empleado', '', 'rrhh'], 'accessUsers' => [5, '']]);
    assertSame(['empleado', 'rrhh'], $c['accessRoles']);
    assertSame(['5'], $c['accessUsers']);
});

it('generates valid PHP for a private page with a form', function() {
    $src = buildConfigurablePage([
        'table' => 'products', 'private' => true, 'columns' => ['name_product'],
        'template' => '{{#form}}{{input name_product}}{{submit}}{{/form}}',
    ]);
    $tmp = tempnam(sys_get_temp_dir(), 'pb');
    file_put_contents($tmp, $src);
    $out = []; $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    assertSame(0, $code, 'should be valid PHP: ' . implode("\n", $out));
    assertTrue(strpos($src, 'password_verify') !== false, 'embeds login check');
});

it('generates valid PHP that embeds the renderer', function() {
    $tmp = tempnam(sys_get_temp_dir(), 'pb');
    file_put_contents($tmp, buildConfigurablePage(['table' => 'products', 'template' => '{{#cada}}{{name_product}}{{/cada}}']));
    $out = []; $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    assertSame(0, $code, 'generated page should be valid PHP: ' . implode("\n", $out));
});

it('escapes record data but emits the template structure verbatim', function() {
    $src = buildConfigurablePage(['table' => 'products', 'template' => '<x-promo></x-promo>']);
    assertTrue(strpos($src, 'htmlspecialchars') !== false, 'values are escaped');
    assertTrue(strpos($src, 'wpb_render') !== false, 'renderer is embedded');
});
