# Desarrollo

## Tests

Suite de tests **sin dependencias externas** (runner propio, sin PHPUnit):

```bash
php tests/run.php
```

Cubre seguridad de la API, logger, generadores y el motor del page-builder
(plantillas, formularios, control de acceso). Devuelve código de salida 0/1
(apto para CI).

## Generadores CLI (`tools/`)

Scaffolders que reutilizan funciones puras (testeadas) como backend:

```bash
php tools/make-web-page.php <tabla> [opciones]   # página web (lista/detalle)
php tools/make-migration.php <nombre> --date YYYY-MM-DD [columnas]
php tools/make-plugin.php <nombre-kebab>         # esqueleto de plugin
```

- `tools/page-builder.php` — motor del Generador de Páginas (plantillas,
  formularios, login/acceso). Usado por el CMS y testeado.
- `tools/setup.php` — creación de config/directorios (lo llama `setup.sh`).

## Logger

`core/logger.php` — logger central que **nunca lanza excepciones** (cae a
`error_log`). Escribe en `logs/` (denegado por web, archivo diario).

```php
Logger::error('mensaje', ['contexto' => 1]);
Logger::warning(...); Logger::info(...); Logger::debug(...);
```

## Permisos / diagnóstico

`core/permissions.php` + la página **"Estado del Sistema"** del CMS diagnostican
y reparan (en lo posible) los directorios escribibles.

## Convenciones

- **Comentarios de código en inglés** (obligatorio). Docs de usuario en español.
- Controladores: `nombre.controller.php`. Servicios: `nombre.service.php`.
- Migraciones descriptivas en `migrations/` (`add_*.sql`, `create_*.sql`).
- Mantén `config.example.php` actualizado al añadir configuración.

## Plugins

Cada plugin es autocontenido en `plugins/nombre-plugin/` (controllers, views,
assets, config). Se registran en `plugins/plugins-registry.php`. Sigue la
estructura de los plugins existentes (payku, workflow-manager).

## Contribución

1. Crea una rama para tu cambio.
2. Asegúrate de que `php tests/run.php` pase.
3. Abre un Pull Request a `development`.
