<?php

/**
 * Permissions diagnostics & best-effort repair.
 *
 * Validates the directories the application needs to write to, and tries to
 * fix what PHP is allowed to fix:
 *   - creates missing directories (owned by the web-server user -> writable),
 *   - chmods directories that PHP already owns.
 *
 * PHP cannot change ownership (chown requires root), so for directories owned
 * by another user it reports a clear, copy-pasteable command instead. On most
 * shared hosting the web user already owns the files, so the auto-create path
 * is usually enough; the ownership split is mainly a local XAMPP quirk.
 */
class Permissions {

    /**
     * The directories the application writes to.
     *
     * @return array<int,array{path:string,label:string,create:bool}>
     */
    public static function requiredPaths() {
        $root = dirname(__DIR__);
        return [
            ['path' => $root . '/web/pages',               'label' => 'Páginas web generadas',  'create' => true],
            ['path' => $root . '/cms/views/assets/files',   'label' => 'Archivos subidos',        'create' => true],
            ['path' => $root . '/logs',                     'label' => 'Registros (logs)',        'create' => true],
            ['path' => $root . '/api/tmp',                  'label' => 'Temporales de la API',    'create' => true],
            ['path' => $root . '/packages',                 'label' => 'Paquetes exportados',     'create' => true],
            ['path' => $root . '/web',                      'label' => 'Sitemap (web/sitemap.xml)', 'create' => false],
        ];
    }

    /**
     * Run a full diagnosis. When $fix is true, attempts a best-effort repair
     * of each path before reporting its status.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function diagnose($fix = false) {
        $root = dirname(__DIR__);
        $results = [];

        foreach (self::requiredPaths() as $req) {
            $fixed = false;
            if ($fix) {
                $fixed = self::attemptFix($req['path'], $req['create']);
            }

            $exists   = is_dir($req['path']);
            $writable = $exists && is_writable($req['path']);

            $results[] = [
                'label'    => $req['label'],
                'path'     => self::relative($req['path'], $root),
                'exists'   => $exists,
                'writable' => $writable,
                'owner'    => self::owner($req['path']),
                'webUser'  => self::webUser(),
                'fixed'    => $fixed,
                'command'  => $writable ? null : self::fixCommand($req['path']),
            ];
        }

        return $results;
    }

    /**
     * Best-effort repair of a single path. Returns true if it ends up writable.
     */
    public static function attemptFix($path, $create = false) {
        if (!is_dir($path) && $create) {
            @mkdir($path, 0775, true);
        }
        if (is_dir($path) && !is_writable($path)) {
            // Only succeeds when PHP owns the directory.
            @chmod($path, 0775);
        }
        return is_dir($path) && is_writable($path);
    }

    /**
     * The OS user the web server / PHP process runs as.
     */
    public static function webUser() {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            if (is_array($info) && !empty($info['name'])) {
                return $info['name'];
            }
        }
        $env = getenv('USER') ?: getenv('USERNAME');
        return $env ?: 'el-usuario-del-servidor-web';
    }

    /**
     * The owner name of a path (or null if it does not exist).
     */
    public static function owner($path) {
        if (!file_exists($path)) {
            return null;
        }
        $uid = @fileowner($path);
        if ($uid !== false && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid($uid);
            if (is_array($info) && !empty($info['name'])) {
                return $info['name'];
            }
        }
        return $uid === false ? null : (string) $uid;
    }

    /**
     * A copy-pasteable command to make a path writable by the web user.
     */
    public static function fixCommand($path) {
        return 'sudo chown -R ' . self::webUser() . ':staff ' . escapeshellarg($path)
             . ' && sudo chmod -R 775 ' . escapeshellarg($path);
    }

    /**
     * Render a path relative to the project root for display.
     */
    private static function relative($path, $root) {
        if (strpos($path, $root) === 0) {
            return ltrim(substr($path, strlen($root)), '/\\');
        }
        return $path;
    }
}
