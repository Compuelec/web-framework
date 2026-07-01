<?php
/**
 * Contabilidad PyMe — plugin controller.
 *
 * Responsabilidades:
 *   - Bootstrap del plugin (init) — se llama una vez por request.
 *   - Gestión del lifecycle: install / uninstall / isInstalled.
 *   - Acceso a la configuración (getConfig).
 *   - Hooks para integración con otros plugins:
 *       registerAsientoOrigen() → RRHH declara "remuneracion" y su recipe.
 *       getAsientoOrigenes()    → devuelve el catálogo para audit/UI.
 *
 * Diseño:
 *   - No abre PDO en init(): el bootstrap corre en cada request y no
 *     queremos costo de handshake si el request no toca la DB.
 *   - install/uninstall llaman a un único SQL file idempotente (más
 *     fácil de auditar y versionar que N INSERTs esparcidos en PHP).
 *   - Los hooks se guardan en un array estático (memoria de request).
 *     Si necesitáramos persistencia entre requests, moveríamos a DB.
 */

require_once __DIR__ . '/../../../api/models/connection.php';

class ContabilidadController {

    /** @var array|null Cached config. */
    private static $config = null;

    /** @var array<string,array> Registered asiento origen types (see hooks below). */
    private static $asientoOrigenes = [];

    /* =====================================================================
       Bootstrap
       ===================================================================== */

    /**
     * Called once per request from the plugin entry point. Cheap: just
     * loads config into memory and registers the built-in asiento
     * origenes. Do NOT open DB connections here — pages open their own.
     */
    public static function init() {
        self::loadConfig();
        self::registerBuiltinOrigenes();
    }

    /* =====================================================================
       Config
       ===================================================================== */

    /**
     * Returns the plugin config (whole array) or a specific key. Cached.
     *
     * Usage:
     *   $ivaRate = ContabilidadController::getConfig('accounting.iva_rate');
     *   $roles   = ContabilidadController::getConfig('access.roles_contador');
     *
     * Dot-notation navigates nested arrays.
     */
    public static function getConfig($key = null) {
        self::loadConfig();
        if ($key === null) {
            return self::$config;
        }
        $parts = explode('.', $key);
        $node = self::$config;
        foreach ($parts as $p) {
            if (!is_array($node) || !array_key_exists($p, $node)) {
                return null;
            }
            $node = $node[$p];
        }
        return $node;
    }

    private static function loadConfig() {
        if (self::$config !== null) { return; }
        $path = __DIR__ . '/../config.php';
        self::$config = file_exists($path) ? require $path : [];
    }

    /* =====================================================================
       Lifecycle — install / uninstall
       ===================================================================== */

    /**
     * Installs the plugin: schema, seed del plan de cuentas mínimo,
     * metadata del CMS (modules/columns/pages) para que los CRUDs
     * aparezcan en el sidebar. Idempotente — es seguro correrlo N veces.
     *
     * Returns:
     *   [ok => bool, message => string, applied => string[]]
     */
    public static function install(): array {
        $db = Connection::connect();
        if (!$db) {
            return ['ok' => false, 'message' => 'No pude conectar a la base de datos.', 'applied' => []];
        }
        $applied = [];
        $sqlDir = __DIR__ . '/../sql';

        // Fase 1 — SQL puro (idempotente vía CREATE IF NOT EXISTS + INSERT IGNORE).
        $sqlFiles = [
            'install-01-schema.sql' => 'Schema de tablas contables',
            'install-02-seed.sql'   => 'Plan de cuentas mínimo (chileno)',
        ];
        foreach ($sqlFiles as $file => $label) {
            $path = $sqlDir . '/' . $file;
            if (!file_exists($path)) { continue; }
            try {
                self::execSqlFile($db, $path);
                $applied[] = $label;
            } catch (Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Falló ' . $file . ': ' . $e->getMessage(),
                    'applied' => $applied,
                ];
            }
        }

        // Fase 2 — CMS metadata (imperativo, con upsert por suffix/url).
        // Los IDs se generan al vuelo; no asumimos IDs específicos como
        // hacían los dumps del playground.
        try {
            self::installCmsMetadata($db);
            $applied[] = 'Metadata del CMS (módulos, columnas, páginas)';
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Falló instalación de metadata CMS: ' . $e->getMessage(),
                'applied' => $applied,
            ];
        }

        return ['ok' => true, 'message' => 'Contabilidad PyMe instalada.', 'applied' => $applied];
    }

    /**
     * Crea/refresca los módulos, columnas y páginas del CMS con upsert.
     * Cada módulo se identifica por `suffix_module` (único en la práctica).
     * Cada columna se identifica por (id_module_column, title_column).
     * Cada página se identifica por `url_page`.
     */
    private static function installCmsMetadata(PDO $db): void {
        // Módulos: (suffix, table_name, page_title, page_url, page_icon).
        // El page_title/url/icon determinan la entrada del sidebar CMS.
        $modules = [
            ['cuenta',    'plan_cuentas',        'Plan de Cuentas',        'plan-cuentas',        'bi-book'],
            ['categoria', 'categorias_gasto',    'Categorías de Gasto',    'categorias-gasto',    'bi-tag'],
            ['cliente',   'clientes',            'Clientes',               'clientes',            'bi-people'],
            ['proveedor', 'proveedores',         'Proveedores',            'proveedores',         'bi-truck'],
            ['venta',     'comprobantes_venta',  'Comprobantes de Venta',  'comprobantes-venta',  'bi-receipt'],
            ['compra',    'comprobantes_compra', 'Comprobantes de Compra', 'comprobantes-compra', 'bi-cart'],
            ['asiento',   'asientos',            'Asientos Contables',     'asientos',            'bi-journal-text'],
            ['linea',     'asiento_lineas',      'Líneas de Asiento',      'asiento-lineas',      'bi-list-columns'],
            ['pago',      'pagos',               'Pagos a Proveedores',    'pagos',               'bi-arrow-up-circle'],
            ['cobro',     'cobros',              'Cobros de Clientes',     'cobros',              'bi-arrow-down-circle'],
            ['cierre',    'cierres_mes',         'Cierres de Mes',         'cierres-mes',         'bi-lock'],
        ];

        // Columnas por módulo (suffix → array de definiciones).
        $columnDefs = self::cmsColumnDefinitions();

        foreach ($modules as $order => $m) {
            [$suffix, $table, $pageTitle, $pageUrl, $pageIcon] = $m;

            // Upsert de la página primero (la referencia el módulo por id_page_module).
            $pageId = self::upsertCmsPage($db, $pageTitle, $pageUrl, $pageIcon, $order + 10);

            // Upsert del módulo tipo `tables` apuntando a esa página.
            $moduleId = self::upsertCmsModule($db, $table, $suffix, $pageId);

            // Columnas del módulo.
            $cols = $columnDefs[$suffix] ?? [];
            foreach ($cols as $col) {
                self::upsertCmsColumn($db, $moduleId, $col);
            }
        }
    }

    /**
     * Definiciones de columnas por suffix de módulo. Cada entrada:
     *   [title (nombre físico), alias, type, matrix (para select/etc), visible]
     */
    private static function cmsColumnDefinitions(): array {
        return [
            'cuenta' => [
                ['codigo_cuenta',    'Código',    'text',   '',                                                      1],
                ['nombre_cuenta',    'Nombre',    'text',   '',                                                      1],
                ['tipo_cuenta',      'Tipo',      'select', 'activo,pasivo,patrimonio,ingreso,gasto,costo',           1],
                ['naturaleza_cuenta','Naturaleza','select', 'deudora,acreedora',                                      1],
                ['nivel_cuenta',     'Nivel',     'int',    '',                                                      1],
                ['activa_cuenta',    'Activa',    'boolean','',                                                      1],
            ],
            'categoria' => [
                ['nombre_categoria', 'Nombre', 'text', '', 1],
                ['cuenta_categoria', 'Cuenta', 'select_relation:plan_cuentas.id_cuenta.nombre_cuenta', '', 1],
            ],
            'cliente' => [
                ['razon_social_cliente','Razón Social','text','',1],
                ['rut_cliente',         'RUT',         'text','',1],
                ['giro_cliente',        'Giro',        'text','',1],
                ['email_cliente',       'Email',       'email','',1],
                ['telefono_cliente',    'Teléfono',    'text','',1],
            ],
            'proveedor' => [
                ['razon_social_proveedor','Razón Social','text','',1],
                ['rut_proveedor',         'RUT',         'text','',1],
                ['giro_proveedor',        'Giro',        'text','',1],
                ['email_proveedor',       'Email',       'email','',1],
                ['telefono_proveedor',    'Teléfono',    'text','',1],
            ],
            'venta' => [
                ['tipo_documento_venta','Tipo Doc','select','factura_afecta,factura_exenta,boleta,nota_credito,nota_debito',1],
                ['folio_venta',    'Folio',    'int',   '', 1],
                ['fecha_venta',    'Fecha',    'date',  '', 1],
                ['cliente_venta',  'Cliente',  'select_relation:clientes.id_cliente.razon_social_cliente', '', 1],
                ['neto_venta',     'Neto',     'money', '', 1],
                ['iva_venta',      'IVA',      'money', '', 1],
                ['exento_venta',   'Exento',   'money', '', 1],
                ['total_venta',    'Total',    'money', '', 1],
                ['estado_venta',   'Estado',   'select','emitido,pagado,anulado',1],
                ['glosa_venta',    'Glosa',    'textarea','', 1],
            ],
            'compra' => [
                ['tipo_documento_compra','Tipo Doc','select','factura_afecta,factura_exenta,boleta,boleta_honorarios,nota_credito',1],
                ['folio_compra',    'Folio',    'int',   '', 1],
                ['fecha_compra',    'Fecha',    'date',  '', 1],
                ['proveedor_compra','Proveedor','select_relation:proveedores.id_proveedor.razon_social_proveedor','', 1],
                ['categoria_compra','Categoría','select_relation:categorias_gasto.id_categoria.nombre_categoria','', 1],
                ['neto_compra',     'Neto',     'money', '', 1],
                ['iva_compra',      'IVA',      'money', '', 1],
                ['exento_compra',   'Exento',   'money', '', 1],
                ['retencion_compra','Retención','money', '', 1],
                ['total_compra',    'Total',    'money', '', 1],
                ['estado_compra',   'Estado',   'select','registrado,pagado,anulado',1],
                ['glosa_compra',    'Glosa',    'textarea','', 1],
            ],
            'asiento' => [
                ['numero_asiento',       'N°',      'int',    '', 1],
                ['fecha_asiento',        'Fecha',   'date',   '', 1],
                ['glosa_asiento',        'Glosa',   'text',   '', 1],
                ['origen_asiento',       'Origen',  'text',   '', 1],
                ['origen_id_asiento',    'Origen id','int',   '', 1],
                ['total_debe_asiento',   'Debe',    'money',  '', 1],
                ['total_haber_asiento',  'Haber',   'money',  '', 1],
                ['estado_asiento',       'Estado',  'select', 'borrador,validado,anulado', 1],
            ],
            'linea' => [
                ['asiento_linea', 'Asiento', 'select_relation:asientos.id_asiento.numero_asiento', '', 1],
                ['cuenta_linea',  'Cuenta',  'select_relation:plan_cuentas.id_cuenta.nombre_cuenta', '', 1],
                ['glosa_linea',   'Glosa',   'text',  '', 1],
                ['debe_linea',    'Debe',    'money', '', 1],
                ['haber_linea',   'Haber',   'money', '', 1],
                ['orden_linea',   'Orden',   'int',   '', 1],
            ],
            'pago' => [
                ['fecha_pago',      'Fecha',      'date',  '', 1],
                ['compra_pago',     'Compra',     'select_relation:comprobantes_compra.id_compra.folio_compra','', 1],
                ['proveedor_pago',  'Proveedor',  'select_relation:proveedores.id_proveedor.razon_social_proveedor','', 1],
                ['medio_pago',      'Medio',      'select','caja,banco',1],
                ['monto_pago',      'Monto',      'money', '', 1],
                ['glosa_pago',      'Glosa',      'text',  '', 1],
                ['estado_pago',     'Estado',     'select','registrado,anulado',1],
            ],
            'cobro' => [
                ['fecha_cobro',      'Fecha',      'date',  '', 1],
                ['venta_cobro',      'Venta',      'select_relation:comprobantes_venta.id_venta.folio_venta','', 1],
                ['cliente_cobro',    'Cliente',    'select_relation:clientes.id_cliente.razon_social_cliente','', 1],
                ['medio_cobro',      'Medio',      'select','caja,banco,payku',1],
                ['monto_cobro',      'Monto',      'money', '', 1],
                ['glosa_cobro',      'Glosa',      'text',  '', 1],
                ['estado_cobro',     'Estado',     'select','registrado,anulado',1],
            ],
            'cierre' => [
                ['mes_cierre',      'Mes',       'int',      '', 1],
                ['anio_cierre',     'Año',       'int',      '', 1],
                ['fecha_cierre',    'Cerrado',   'datetime', '', 1],
                ['usuario_cierre',  'Usuario',   'text',     '', 1],
                ['notas_cierre',    'Notas',     'textarea', '', 1],
            ],
        ];
    }

    private static function upsertCmsPage(PDO $db, string $title, string $url, string $icon, int $order): int {
        $sel = $db->prepare("SELECT id_page FROM pages WHERE url_page = :u LIMIT 1");
        $sel->execute([':u' => $url]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->prepare("UPDATE pages SET title_page=:t, icon_page=:i, order_page=:o WHERE id_page=:id")
               ->execute([':t' => $title, ':i' => $icon, ':o' => $order, ':id' => $row['id_page']]);
            return (int)$row['id_page'];
        }
        $ins = $db->prepare(
            "INSERT INTO pages (title_page, url_page, icon_page, type_page, parent_page, order_page, date_created_page)
             VALUES (:t, :u, :i, 'modules', 0, :o, CURDATE())"
        );
        $ins->execute([':t' => $title, ':u' => $url, ':i' => $icon, ':o' => $order]);
        return (int)$db->lastInsertId();
    }

    private static function upsertCmsModule(PDO $db, string $tableName, string $suffix, int $pageId): int {
        $sel = $db->prepare("SELECT id_module FROM modules WHERE suffix_module = :s LIMIT 1");
        $sel->execute([':s' => $suffix]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->prepare(
                "UPDATE modules SET id_page_module=:pid, title_module=:t, type_module='tables' WHERE id_module=:id"
            )->execute([':pid' => $pageId, ':t' => $tableName, ':id' => $row['id_module']]);
            return (int)$row['id_module'];
        }
        $ins = $db->prepare(
            "INSERT INTO modules
                (id_page_module, type_module, title_module, suffix_module, content_module, width_module, editable_module, date_created_module)
             VALUES (:pid, 'tables', :t, :s, NULL, 100, 0, CURDATE())"
        );
        $ins->execute([':pid' => $pageId, ':t' => $tableName, ':s' => $suffix]);
        return (int)$db->lastInsertId();
    }

    /**
     * Quita del CMS las páginas + módulos + columnas del plugin,
     * detectando por `url_page` y `suffix_module`. NO toca las tablas de
     * datos (eso es responsabilidad de uninstall(dropData=true)).
     */
    private static function uninstallCmsMetadata(PDO $db): void {
        $urls = [
            'plan-cuentas', 'categorias-gasto', 'clientes', 'proveedores',
            'comprobantes-venta', 'comprobantes-compra', 'asientos',
            'asiento-lineas', 'pagos', 'cobros', 'cierres-mes',
        ];
        $suffixes = [
            'cuenta', 'categoria', 'cliente', 'proveedor', 'venta',
            'compra', 'asiento', 'linea', 'pago', 'cobro', 'cierre',
        ];

        // Borra columnas → módulos → páginas en orden inverso a FKs
        // lógicos. `columns` usa `id_module_column` que apunta a
        // `modules.id_module`, así que primero resolvemos los IDs.
        $placeholders = implode(',', array_fill(0, count($suffixes), '?'));
        $mods = $db->prepare("SELECT id_module FROM modules WHERE suffix_module IN ($placeholders)");
        $mods->execute($suffixes);
        $moduleIds = array_column($mods->fetchAll(PDO::FETCH_ASSOC), 'id_module');

        if ($moduleIds) {
            $ph = implode(',', array_fill(0, count($moduleIds), '?'));
            $db->prepare("DELETE FROM `columns` WHERE id_module_column IN ($ph)")->execute($moduleIds);
            $db->prepare("DELETE FROM modules  WHERE id_module        IN ($ph)")->execute($moduleIds);
        }

        $urlPh = implode(',', array_fill(0, count($urls), '?'));
        $db->prepare("DELETE FROM pages WHERE url_page IN ($urlPh)")->execute($urls);
    }

    private static function upsertCmsColumn(PDO $db, int $moduleId, array $col): void {
        [$title, $alias, $type, $matrix, $visible] = $col;
        $sel = $db->prepare(
            "SELECT id_column FROM `columns` WHERE id_module_column = :m AND title_column = :t LIMIT 1"
        );
        $sel->execute([':m' => $moduleId, ':t' => $title]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->prepare(
                "UPDATE `columns`
                    SET alias_column=:a, type_column=:ty, matrix_column=:mx, visible_column=:v
                  WHERE id_column=:id"
            )->execute([
                ':a' => $alias, ':ty' => $type, ':mx' => $matrix, ':v' => $visible,
                ':id' => $row['id_column'],
            ]);
            return;
        }
        $db->prepare(
            "INSERT INTO `columns`
                (id_module_column, title_column, alias_column, type_column, matrix_column, visible_column, date_created_column)
             VALUES (:m, :t, :a, :ty, :mx, :v, CURDATE())"
        )->execute([
            ':m' => $moduleId, ':t' => $title, ':a' => $alias,
            ':ty' => $type, ':mx' => $matrix, ':v' => $visible,
        ]);
    }

    /**
     * Uninstala el plugin: quita metadata del CMS y opcionalmente
     * DROP TABLE de las tablas contables. Por defecto NO borra datos
     * (esas tablas son la contabilidad de la empresa!). Se pasa
     * `dropData=true` explícitamente para hacerlo.
     */
    public static function uninstall(bool $dropData = false): array {
        $db = Connection::connect();
        if (!$db) {
            return ['ok' => false, 'message' => 'No pude conectar a la base de datos.', 'applied' => []];
        }
        $applied = [];
        $sqlDir = __DIR__ . '/../sql';

        // Fase 1 — CMS metadata (imperativo, mismo mapping que el install).
        try {
            self::uninstallCmsMetadata($db);
            $applied[] = 'Metadata del CMS';
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Falló uninstall CMS: ' . $e->getMessage(), 'applied' => $applied];
        }

        // Fase 2 — Solo borra tablas de datos si se pidió explícitamente.
        if ($dropData) {
            $path = $sqlDir . '/uninstall-02-schema.sql';
            if (file_exists($path)) {
                try {
                    self::execSqlFile($db, $path);
                    $applied[] = 'Schema de tablas contables (DROP)';
                } catch (Throwable $e) {
                    return ['ok' => false, 'message' => 'Falló DROP schema: ' . $e->getMessage(), 'applied' => $applied];
                }
            }
        }

        return [
            'ok' => true,
            'message' => $dropData
                ? 'Plugin desinstalado y datos borrados.'
                : 'Plugin desinstalado (datos conservados; usa dropData=true para borrarlos).',
            'applied' => $applied,
        ];
    }

    /**
     * Chequea si el plugin ya está instalado buscando la tabla `asientos`.
     * Suficiente como heuristic; una detección más fina revisaría también
     * las páginas del CMS.
     */
    public static function isInstalled(): bool {
        $db = Connection::connect();
        if (!$db) { return false; }
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'asientos'");
            return $stmt && $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Devuelve versión + estado. Útil para pantallas de config/admin.
     */
    public static function getStatus(): array {
        return [
            'name'      => 'Contabilidad PyMe',
            'version'   => '1.0.0',
            'installed' => self::isInstalled(),
            'config'    => self::getConfig(),
            'origenes'  => array_keys(self::$asientoOrigenes),
        ];
    }

    /* =====================================================================
       Hooks — extensibility para otros plugins (RRHH, Facturación, etc.)
       ===================================================================== */

    /**
     * Permite a otros plugins declarar tipos de asiento y proveer un
     * compilador de líneas. El plugin de RRHH llamará:
     *
     *   ContabilidadController::registerAsientoOrigen('remuneracion', [
     *       'label'    => 'Remuneración de personal',
     *       'compile'  => [RRHH::class, 'compileAsientoRemuneracion'],
     *   ]);
     *
     * Luego el motor común (`insertarAsiento`) puede aceptar `origen =
     * 'remuneracion'` y usar el compilador registrado.
     */
    public static function registerAsientoOrigen(string $origen, array $spec): void {
        if (!isset($spec['label']) || !isset($spec['compile'])) {
            throw new InvalidArgumentException(
                "registerAsientoOrigen('$origen'): spec debe incluir 'label' y 'compile'."
            );
        }
        self::$asientoOrigenes[$origen] = $spec;
    }

    public static function getAsientoOrigenes(): array {
        return self::$asientoOrigenes;
    }

    /**
     * Los tipos que la propia contabilidad genera. Los "extras" (rrhh,
     * facturacion, etc.) los agregan otros plugins via
     * registerAsientoOrigen().
     */
    private static function registerBuiltinOrigenes(): void {
        self::$asientoOrigenes = [
            'venta'   => ['label' => 'Venta',   'builtin' => true],
            'compra'  => ['label' => 'Compra',  'builtin' => true],
            'pago'    => ['label' => 'Pago',    'builtin' => true],
            'cobro'   => ['label' => 'Cobro',   'builtin' => true],
            'manual'  => ['label' => 'Ajuste manual', 'builtin' => true],
        ];
    }

    /* =====================================================================
       Utilidades internas
       ===================================================================== */

    /**
     * Ejecuta un archivo SQL usando el cliente `mariadb` del contenedor si
     * está disponible (soporta DELIMITER y comandos multi-statement bien).
     * Fallback a PDO::exec() para entornos sin CLI.
     */
    private static function execSqlFile(PDO $db, string $path): void {
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') { return; }
        // Ejecuta statement por statement para que un error en uno no
        // deje el estado a la mitad. Split por ";" respetando strings +
        // ignorando comentarios `-- ...` de línea y `/* ... */` de bloque.
        $stmts = self::splitSqlStatements($sql);
        foreach ($stmts as $s) {
            $s = trim($s);
            if ($s === '') { continue; }
            $db->exec($s);
        }
    }

    /**
     * Split por `;` respetando: comillas simples/dobles/backticks,
     * comentarios `-- ...\n` de línea, y comentarios `/* ... *\/` de
     * bloque. Suficiente para los install scripts (no usamos DELIMITER,
     * triggers ni stored procs).
     */
    private static function splitSqlStatements(string $sql): array {
        $out = [];
        $buf = '';
        $inSingle = $inDouble = $inBacktick = false;
        $inLineComment = $inBlockComment = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';
            $prev = $i > 0 ? $sql[$i - 1] : '';

            // Cierre de comentarios.
            if ($inLineComment) {
                if ($c === "\n") { $inLineComment = false; $buf .= $c; }
                continue;
            }
            if ($inBlockComment) {
                if ($c === '*' && $next === '/') { $inBlockComment = false; $i++; }
                continue;
            }

            // Fuera de strings, detectar apertura de comentarios.
            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($c === '-' && $next === '-') { $inLineComment = true; $i++; continue; }
                if ($c === '/' && $next === '*') { $inBlockComment = true; $i++; continue; }
            }

            // Toggle de quotes (ignorando escape con backslash).
            if ($c === "'"  && $prev !== '\\' && !$inDouble && !$inBacktick) { $inSingle   = !$inSingle; }
            if ($c === '"'  && $prev !== '\\' && !$inSingle && !$inBacktick) { $inDouble   = !$inDouble; }
            if ($c === '`'  &&                  !$inSingle && !$inDouble)    { $inBacktick = !$inBacktick; }

            // Separador de statement.
            if ($c === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $out[] = $buf; $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') { $out[] = $buf; }
        return $out;
    }
}
