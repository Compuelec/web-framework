# Arquitectura

Framework PHP (sin framework externo) con arquitectura MVC. Tres aplicaciones
principales + sistema de plugins.

## Componentes

- **API RESTful** (`/api/`) — backend que da operaciones CRUD dinámicas sobre
  cualquier tabla. No requiere definir modelos ni rutas por tabla. Autenticación
  por API Key + JWT. Ver [API.md](API.md).
- **CMS Builder** (`/cms/`) — panel de administración: crea tablas/módulos,
  gestiona datos, archivos, usuarios y páginas. Ver
  [CMS y Tablas](CMS-Y-TABLAS.md).
- **Frontend público** (`/web/`) — el sitio público; sirve las páginas generadas
  por el [Generador de Páginas](GENERADOR-PAGINAS.md).
- **Plugins** (`/plugins/`) — funcionalidades autocontenidas (pagos, workflows,
  dashboard…).

## Estructura del proyecto

```
web-framework/
├── api/                    # API RESTful
│   ├── config.example.php  # plantilla (config.php no se versiona)
│   ├── controllers/        # controladores
│   ├── models/             # modelos (connection, etc.)
│   ├── routes/             # enrutamiento dinámico
│   └── vendor/             # dependencias Composer (firebase/php-jwt…)
│
├── cms/                    # CMS Builder
│   ├── index.php           # entry point
│   ├── config.example.php
│   ├── controllers/        # *.controller.php
│   ├── views/              # template + módulos + assets (CSS/JS/plugins)
│   ├── ajax/               # endpoints AJAX
│   └── extensions/         # dependencias (composer)
│
├── web/                    # Frontend público
│   ├── config.example.php
│   ├── controllers/        # api.controller.php (cliente de la API)
│   ├── views/              # template público
│   ├── pages/              # páginas generadas (+ example-*.php)
│   └── uploads/            # archivos subidos desde formularios públicos
│
├── core/                   # núcleo (version, logger, permissions)
├── plugins/                # sistema de plugins
├── tools/                  # generadores CLI + page-builder + setup
├── tests/                  # suite de tests (sin dependencias)
├── migrations/             # scripts SQL
├── setup.sh                # instalación/restauración
└── docs/                   # esta documentación
```

## Stack tecnológico

**Backend**: PHP nativo (MVC), MySQL, Composer, Firebase JWT, PHPMailer (ejemplo).

**Frontend (CMS)**: Bootstrap 5, jQuery, Chart.js, Summernote (WYSIWYG),
CodeMirror (editor de código), Select2, DateRangePicker, Feather/Bootstrap Icons.

## Convenciones

- **Comentarios de código en inglés** (regla del proyecto). La documentación de
  usuario va en español.
- Controladores: `nombre.controller.php`; servicios: `nombre.service.php`.
- Configuración: `config.example.php` (plantilla) + `config.php` (real, ignorado).
- Cambios de esquema en `migrations/` como `.sql`.

Más detalles para desarrollar en [Desarrollo](DESARROLLO.md).
