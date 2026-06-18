<?php
/**
 * Tests for the plugin generator (tools/make-plugin.php).
 * Requiring the file only defines its functions — the CLI entry point is
 * guarded so it does not run, and these tests do no filesystem writes into
 * the project (generated content is validated in temp files).
 */

require_once __DIR__ . '/../tools/make-plugin.php';

echo "\nPlugin generator\n";

it('validates kebab-case plugin names', function() {
    assertTrue(plugin_isValidName('my-plugin'));
    assertTrue(plugin_isValidName('payku'));
    assertFalse(plugin_isValidName('My_Plugin'));
    assertFalse(plugin_isValidName('-bad'));
    assertFalse(plugin_isValidName('a--b'));
});

it('derives a studly controller class name', function() {
    assertSame('MyPlugin', plugin_studly('my-plugin'));
    assertSame('RbacManager', plugin_studly('rbac-manager'));
});

it('resolves options with sensible defaults', function() {
    $o = plugin_resolveOptions('my-plugin', []);
    assertSame('MyPluginController', $o['class']);
    assertSame('My Plugin', $o['label']);
    assertSame('custom', $o['type']);
    assertSame('plugin_my_plugin', $o['table']);
});

it('rejects an invalid plugin name', function() {
    $threw = false;
    try { plugin_resolveOptions('Bad_Name', []); } catch (\InvalidArgumentException $e) { $threw = true; }
    assertTrue($threw, 'should reject a non-kebab-case name');
});

it('rejects an invalid plugin type', function() {
    $threw = false;
    try { plugin_resolveOptions('my-plugin', ['type' => 'bogus']); } catch (\InvalidArgumentException $e) { $threw = true; }
    assertTrue($threw, 'should reject an unknown type');
});

it('builds the full plugin file set', function() {
    $files = buildPluginFiles(plugin_resolveOptions('my-plugin', []));
    foreach (['my-plugin.php', 'config.php', 'controllers/my-plugin.controller.php', 'views/main.php', 'ajax.php', '.htaccess'] as $rel) {
        assertTrue(isset($files[$rel]), "should generate {$rel}");
    }
    assertTrue(strpos($files['controllers/my-plugin.controller.php'], 'class MyPluginController') !== false, 'controller has the class');
    assertTrue(strpos($files['ajax.php'], "isset(\$_SESSION['admin'])") !== false, 'ajax has a session guard');
    assertTrue(strpos($files['my-plugin.php'], 'MyPluginController::init();') !== false, 'entry calls init');
});

it('builds a valid registration snippet', function() {
    $snippet = buildPluginRegistration(plugin_resolveOptions('my-plugin', []));
    assertTrue(strpos($snippet, "register('my-plugin'") !== false, 'registers by name');
    assertTrue(strpos($snippet, "'type'        => 'custom'") !== false, 'includes the type');
});

it('generates syntactically valid PHP files', function() {
    $files = buildPluginFiles(plugin_resolveOptions('my-plugin', []));
    foreach (['my-plugin.php', 'config.php', 'controllers/my-plugin.controller.php', 'views/main.php', 'ajax.php'] as $rel) {
        $tmp = tempnam(sys_get_temp_dir(), 'plug');
        file_put_contents($tmp, $files[$rel]);
        $out = [];
        $code = 0;
        exec(PHP_BINARY . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        @unlink($tmp);
        assertSame(0, $code, "{$rel} should be valid PHP: " . implode("\n", $out));
    }
});
