<?php
/**
 * Tests for the Permissions diagnostics helper.
 */

require_once __DIR__ . '/../core/permissions.php';

echo "\nPermissions\n";

it('lists the required writable directories', function() {
    $paths = Permissions::requiredPaths();
    assertTrue(is_array($paths) && count($paths) > 0, 'should be a non-empty array');
    foreach ($paths as $p) {
        assertTrue(isset($p['path'], $p['label'], $p['create']), 'each entry has path/label/create');
    }
});

it('reports the web-server user', function() {
    $user = Permissions::webUser();
    assertTrue(is_string($user) && $user !== '', 'should return a non-empty user name');
});

it('creates a missing directory during attemptFix', function() {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'perm_test_' . getmypid() . '_' . mt_rand(1000, 9999);
    @rmdir($dir);
    assertFalse(is_dir($dir), 'precondition: dir does not exist');

    $ok = Permissions::attemptFix($dir, true);
    assertTrue($ok, 'attemptFix should create and make the dir writable');
    assertTrue(is_dir($dir) && is_writable($dir), 'dir should now exist and be writable');

    @rmdir($dir);
});

it('does not create a directory when create is false', function() {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'perm_nocreate_' . getmypid() . '_' . mt_rand(1000, 9999);
    @rmdir($dir);
    Permissions::attemptFix($dir, false);
    assertFalse(is_dir($dir), 'should not have been created');
});

it('diagnoses with structured results', function() {
    $results = Permissions::diagnose(false);
    assertTrue(is_array($results) && count($results) > 0, 'should return results');
    $first = $results[0];
    foreach (['label', 'path', 'exists', 'writable', 'owner', 'webUser', 'command'] as $k) {
        assertTrue(array_key_exists($k, $first), "result should include '$k'");
    }
});

it('builds a fix command with chown and chmod', function() {
    $cmd = Permissions::fixCommand('/some/path');
    assertTrue(strpos($cmd, 'chown') !== false, 'mentions chown');
    assertTrue(strpos($cmd, 'chmod') !== false, 'mentions chmod');
});
