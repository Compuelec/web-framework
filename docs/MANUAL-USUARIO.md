# Manual de Usuario — Web Framework

> **Qué es esto:** una guía práctica, de principio a fin, para entender el potencial de este
> framework y **construir aplicaciones web reales** con él, sin tener que leer el código fuente.
>
> Está pensado para dos perfiles:
> - **Administrador / creador de aplicaciones** → secciones 1 a 7 (todo desde la interfaz del CMS).
> - **Desarrollador** → secciones 8 a 12 (API, frontend público, plugins).
>
> **Versión:** 1.1.0 · **Stack:** PHP 7.4+ · MySQL 5.7+ · Apache · Bootstrap 5 · jQuery

---

## Índice

1. [¿Qué es este framework y para qué sirve?](#1-qué-es-este-framework-y-para-qué-sirve)
2. [Conceptos clave (vocabulario)](#2-conceptos-clave-vocabulario)
3. [Instalación y primer arranque](#3-instalación-y-primer-arranque)
4. [Tu primera aplicación en 10 minutos](#4-tu-primera-aplicación-en-10-minutos)
5. [Guía completa del CMS Builder](#5-guía-completa-del-cms-builder)
6. [Tipos de campo (referencia)](#6-tipos-de-campo-referencia)
7. [Usuarios, roles y permisos](#7-usuarios-roles-y-permisos)
8. [La API REST dinámica](#8-la-api-rest-dinámica)
9. [El frontend público](#9-el-frontend-público)
10. [SEO y sitemap](#10-seo-y-sitemap)
11. [Plugins: usar y crear](#11-plugins-usar-y-crear)
12. [Empaquetar, desplegar y actualizar](#12-empaquetar-desplegar-y-actualizar)
13. [Recetas y patrones comunes](#13-recetas-y-patrones-comunes)
14. [Solución de problemas](#14-solución-de-problemas)

---

## 1. ¿Qué es este framework y para qué sirve?

Es una **base reutilizable para construir aplicaciones web** sin escribir el código repetitivo de
siempre (formularios, CRUD, login, paneles). Combina tres piezas que trabajan juntas:

```
┌──────────────────────────────────────────────────────────────┐
│                      TU APLICACIÓN                            │
│                                                              │
│   CMS Builder (cms/)          Frontend público (web/)        │
│   Panel de administración     Sitio que ven tus visitantes   │
│   • Crea tablas               • Páginas con SEO              │
│   • Genera formularios        • Consume datos de la API     │
│   • Gestiona contenido        • Renderiza listados/detalle  │
│            │                          │                      │
│            └──────────┬───────────────┘                      │
│                       ▼                                       │
│             API REST dinámica (api/)                         │
│             CRUD sobre cualquier tabla MySQL                 │
│                       │                                       │
│                       ▼                                       │
│                   Base de datos MySQL                        │
└──────────────────────────────────────────────────────────────┘
```

**La idea central:** defines tus datos **una vez** (creando una tabla desde el panel) y obtienes
automáticamente: el formulario para cargarlos, la tabla para gestionarlos, y una API REST para
consumirlos desde el sitio público o desde apps externas.

**Casos de uso típicos:** catálogos de productos, sistemas de reservas, paneles internos (CRM/ERP
ligeros), blogs/portales con SEO, backoffices con pagos (vía plugin Payku), cualquier app
data-driven.

---

## 2. Conceptos clave (vocabulario)

| Término | Qué es |
|---------|--------|
| **Página** | Una entrada del menú del CMS. Puede ser de tipo `page` (contenido), `menu` (agrupa subpáginas) o `custom` (página a medida). |
| **Módulo** | Un componente dentro de una página. El más común es el módulo *tabla*, que da un CRUD completo sobre una tabla de BD. |
| **Columna** | Un campo de un módulo/tabla. Cada columna tiene un **tipo** (texto, fecha, imagen, relación…) que define cómo se ve y valida. |
| **Sufijo (suffix)** | Convención de nombres: una tabla `productos` con sufijo `producto` tiene columnas `id_producto`, `name_producto`, etc. Es lo que conecta tabla ↔ API. |
| **Tipo de página `custom`** | Página con código propio en `cms/views/pages/custom/<url>/`. Así están hechas Archivos, Apariencia, Dashboard, etc. |
| **API Key** | Clave única que protege la API. El CMS y el frontend la usan para hablar con `api/`. |
| **Plugin** | Extensión autocontenida en `plugins/<nombre>/`, registrada en `plugins-registry.php`. |

---

## 3. Instalación y primer arranque

### 3.1 Requisitos
- Apache con `mod_rewrite` activo (XAMPP/WAMP/LAMP), PHP 7.4+, MySQL 5.7+ / MariaDB 10.2+.
- Extensiones PHP: `PDO`, `PDO_MySQL`, `JSON`, `cURL`, `OpenSSL`.
- Composer (para dependencias de `api/`).

### 3.2 Pasos

```bash
# 1. Dependencias de la API
cd api && composer install && cd ..

# 2. Configuración de la API
cp api/config.example.php api/config.php
#    Edita api/config.php: credenciales de BD, 'api.key', 'jwt.secret', 'password.salt'

# 3. Configuración del CMS
cp cms/config.example.php cms/config.php
#    Edita cms/config.php: credenciales de BD, 'api.base_url', 'api.key' (la MISMA del paso 2)

# 4. Configuración del frontend público
cp web/config.example.php web/config.php
#    Edita web/config.php: 'api.base_url', 'api.key', datos del sitio
```

> ⚠️ **Importante:** la `api.key` debe ser **idéntica** en `api/config.php`, `cms/config.php` y
> `web/config.php`. Es lo que les permite hablar entre sí. Nunca subas estos `config.php` al repo.

### 3.3 Instalador del CMS

1. Abre `http://localhost/<tu-proyecto>/cms/`.
2. Si no hay tablas, aparece el **instalador** (`/cms/install`). Detecta el dominio y la URL de la API.
3. Crea el **superadmin inicial** (email + contraseña).
4. El instalador crea automáticamente las tablas base: `admins`, `pages`, `modules`, `columns`,
   `folders`, `files`, `activity_logs`, `workflows`, `page_seo`.

Listo: ya puedes entrar al panel.

### 3.4 Permisos de archivos (si hay errores de escritura)

```bash
# El usuario de Apache (en XAMPP/macOS suele ser 'daemon') necesita escribir en uploads:
sudo chown -R daemon:admin <ruta-del-proyecto>
sudo find <ruta-del-proyecto> -type d -exec chmod 775 {} +
sudo find <ruta-del-proyecto> -type f -exec chmod 664 {} +
```

---

## 4. Tu primera aplicación en 10 minutos

Vamos a construir un **catálogo de productos** de punta a punta.

### Paso 1 — Crear la página
En el CMS, ve a **Administración → Páginas → Nueva página**:
- **Título:** `Productos`
- **URL:** `productos`
- **Ícono:** elige uno (Bootstrap Icons)
- **Tipo:** `page`

### Paso 2 — Crear el módulo (la tabla)
Dentro de la página *Productos*, **Nuevo módulo**:
- **Título del módulo:** `productos` (será el nombre de la tabla en BD)
- **Sufijo:** `producto`
- **Tipo:** `tables`
- **Ancho:** `100`

### Paso 3 — Definir las columnas
Agrega columnas al módulo (cada una genera una columna real en la tabla):

| Título | Tipo | Alias | Visible |
|--------|------|-------|---------|
| `name` | `text` | Nombre | Sí |
| `price` | `money` | Precio | Sí |
| `description` | `textarea` | Descripción | No |
| `image` | `image` | Foto | Sí |
| `active` | `boolean` | Activo | Sí |

Al guardar, el framework **crea la tabla `productos`** con `id_producto`, `name_producto`,
`price_producto`, etc., y genera el formulario automáticamente.

### Paso 4 — Cargar datos
Entra a la página *Productos* → **Nuevo** → completa el formulario (el campo `image` abre el gestor
de archivos para elegir/subir una foto) → **Guardar**. Repite con varios productos.

### Paso 5 — Verlos por la API
Tus datos ya están disponibles vía REST:
```http
GET /api/productos
Authorization: <tu-api-key>
```

### Paso 6 — Mostrarlos en el sitio público
Crea `web/pages/productos.php` (puedes copiar `web/pages/example-list.php` como base):

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controllers/api.controller.php';

$pageTitle = 'Productos';
$response  = ApiController::getAll('productos', '*', 'id_producto', 'ASC', 0, 50);
$products  = $response->status == 200 ? $response->results : [];

ob_start(); ?>
<div class="container my-5">
  <h1>Productos</h1>
  <div class="row">
    <?php foreach ($products as $p): ?>
      <div class="col-md-4 mb-4">
        <div class="card h-100">
          <img src="<?= htmlspecialchars($p->image_producto, ENT_QUOTES) ?>" class="card-img-top">
          <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($p->name_producto, ENT_QUOTES) ?></h5>
            <p class="fw-bold">$<?= htmlspecialchars($p->price_producto, ENT_QUOTES) ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../views/template.php';
```

Abre `http://localhost/<tu-proyecto>/web/pages/productos.php` → **tu catálogo está online.**

> 🎉 En 6 pasos creaste: tabla en BD + formulario de carga + panel de gestión + API REST + página
> pública. Ese es el potencial del framework: **defines los datos una vez y lo demás es automático.**

---

## 5. Guía completa del CMS Builder

URL base del panel: `http://localhost/<tu-proyecto>/cms/`

### 5.1 Páginas (`/cms/` → Administración → Páginas)
- **Tipos:** `page` (contenido con módulos), `menu` (agrupa subpáginas en un desplegable), `custom` (código a medida).
- **Jerarquía:** asigna una *página padre* para crear submenús colapsables en el sidebar.
- **Orden:** el campo de orden permite reordenar el menú por arrastre.
- **SEO:** cada página tiene un panel SEO (ver §10).

### 5.2 Módulos
Componentes dentro de una página. El tipo `tables` da el CRUD dinámico; `graphics` muestra gráficos/KPIs.
Propiedades útiles: `width` (ancho %), `editable` (permite editar o sólo ver).

### 5.3 Tablas dinámicas (el corazón del CMS)
Cada módulo tipo `tables` te da, sin código:
- **Listado** con búsqueda, filtros, ordenamiento y paginación (vía AJAX).
- **Formulario** generado a partir de las columnas y sus tipos.
- **Acciones** por fila: editar / eliminar.
- **Exportación** a CSV/Excel.
- **Condiciones de campo:** muestra u oculta campos según el valor de otro (ej.: si `tipo = empresa`, mostrar `razón social`). Se define en la columna como reglas JSON.

### 5.4 Gestor de archivos (`/cms/archivos`)
- Organiza archivos en **carpetas** con límite de tamaño configurable.
- Subida por *drag & drop*, búsqueda, vistas grid/lista, filtros por tipo.
- Los campos `image`/`video`/`file` de los formularios abren este gestor para seleccionar archivo.

### 5.5 Dashboard (`/cms/`)
Página de inicio con métricas y gráficos. Con el plugin **dashboard-manager** puedes añadir widgets
arrastrables: métricas (count/sum/avg), gráficos, registros recientes, KPIs, accesos rápidos y HTML libre.

### 5.6 Apariencia (`/cms/apariencia`)
Personaliza colores del panel (primario, sidebar, activos), fuente y fondo. Por admin.

### 5.7 Búsqueda global, Activity logs, Notificaciones
- **Búsqueda global:** barra superior que busca en páginas, módulos y datos (respetando permisos).
- **Activity logs** (`/cms/activity_logs`): auditoría de quién creó/editó/borró qué, cuándo, IP y user-agent.
- **Notificaciones:** sistema de avisos en la UI.

### 5.8 Workflows (`/cms/workflow-manager`)
Define **estados y transiciones** para los registros de una tabla (ej.: `borrador → revisión → publicado`).
Se usa con una columna de tipo `workflow`.

---

## 6. Tipos de campo (referencia)

Al crear una columna eliges su **tipo**, que determina el widget del formulario y la validación:

| Categoría | Tipos |
|-----------|-------|
| Texto | `text`, `textarea`, `email`, `password` |
| Números | `int`, `double`, `money` |
| Fecha/hora | `date`, `time`, `datetime`, `timestamp` |
| Booleano | `boolean` |
| Selección | `select` (opciones predefinidas en *matrix*), `color` |
| Estructurados | `array`, `object`, `json` |
| Multimedia | `image`, `video`, `file`, `link` |
| Avanzados | `relations` (vínculo a otra tabla), `code` (editor WYSIWYG Summernote / CodeMirror), `workflow` (estados), `chatgpt` (integración OpenAI), `order` (ordenamiento) |

**Notas:**
- `select` y `relations` usan el campo *matrix* de la columna para definir opciones o la tabla relacionada.
- `code` renderiza un editor enriquecido; el contenido se guarda como texto largo.
- `password` se hashea con bcrypt al guardar.

---

## 7. Usuarios, roles y permisos

### 7.1 Roles base
- **superadmin** — acceso total: admins, páginas, módulos, temas, packaging, updates, RBAC.
- **admin** — acceso completo a módulos, páginas y archivos.
- **editor** — acceso restringido según permisos por página.

### 7.2 Crear un usuario
Administración → Admins → Nuevo: email, contraseña (se hashea con bcrypt), rol, estado (activo/inactivo)
y personalización visual (color, ícono, fondo).

### 7.3 RBAC granular (`/cms/rbac-manager`, plugin)
Permite asignar permisos **por página y acción** (leer/crear/editar/eliminar). Superadmin y admin
hacen *bypass*; los demás roles aplican las reglas RBAC. Útil para equipos con responsabilidades distintas.

### 7.4 Sesión y seguridad de login
- Login en `/cms/login`; la sesión es **única por dominio/usuario** con token y expiración.
- El CMS incluye **2FA con expiración** y límite de intentos en el flujo de autenticación.

---

## 8. La API REST dinámica

La API expone CRUD sobre **cualquier tabla** sin definir rutas. Punto de entrada: `/api/`.

### 8.1 Autenticación
- Header `Authorization: <api-key>` en cada petición.
- Tablas en `config['api']['public_access_tables']` se pueden leer **sin** API key (úsalo sólo para datos públicos).

### 8.2 GET — leer
```http
GET /api/<tabla>                                   # todos los registros
GET /api/<tabla>?select=id_x,name_x                # columnas específicas
GET /api/<tabla>?linkTo=col&equalTo=valor          # filtro WHERE col = valor
GET /api/<tabla>?linkTo=a,b&equalTo=1,activo       # múltiples filtros (AND)
GET /api/<tabla>?linkTo=name&search=iphone         # búsqueda LIKE
GET /api/<tabla>?orderBy=col&orderMode=DESC        # ordenar
GET /api/<tabla>?startAt=0&endAt=10                # paginar (LIMIT)
GET /api/<tabla>?linkTo=price&between1=100&between2=500   # rango BETWEEN
```
Respuesta estándar:
```json
{ "status": 200, "total": 3, "results": [ { "id_x": 1, "name_x": "Alice" } ] }
```

### 8.3 POST — crear
```http
POST /api/<tabla>?token=no
Content-Type: application/x-www-form-urlencoded

name_x=John&email_x=john@example.com
```

### 8.4 PUT — actualizar
```http
PUT /api/<tabla>?id=42&nameId=id_x&token=no
Content-Type: application/x-www-form-urlencoded

name_x=John Updated
```

### 8.5 DELETE — eliminar
```http
DELETE /api/<tabla>?id=42&nameId=id_x&token=no
```

### 8.6 Usuarios y JWT (registro/login)
```http
POST /api/usuarios?register=true&suffix=usuario   # crea usuario + token (bcrypt)
POST /api/usuarios?login=true&suffix=usuario      # login con rate-limit (5–10 intentos)
```
El login devuelve un JWT. Las operaciones protegidas se invocan con `?token=<jwt>&table=usuarios&suffix=usuario`.

> ⚠️ **Nota de seguridad (ver auditoría):** hoy la firma del JWT no se verifica en el backend y el modo
> `token=no` permite escribir con sólo la API key. Trátalo como pendiente de endurecer antes de exponer
> la API a internet. Usa **siempre HTTPS** en producción.

### 8.7 Buenas prácticas de la API
- Usa `select` para traer sólo las columnas que necesitas.
- Pagina con `startAt`/`endAt` en listados grandes.
- Mantén la API key fuera del código cliente público (el frontend la usa server-side vía cURL, no desde el navegador).

---

## 9. El frontend público

Vive en `web/`. Renderiza el sitio que ven los visitantes y consume datos de la API.

### 9.1 Cómo se arma una página
Patrón de toda página en `web/pages/*.php`:
1. Carga `web/config.php` y `web/controllers/api.controller.php`.
2. Pide datos con `ApiController::getAll()` / `getByFilter()` / `getById()` / `search()` / `getByRange()`.
3. Genera el HTML del contenido en `$pageContent` (con `ob_start()`/`ob_get_clean()`).
4. Incluye el layout: `include __DIR__ . '/../views/template.php';`.

`ApiController` (en `web/controllers/api.controller.php`) habla con la API por **cURL** usando la API key
de `web/config.php`. Métodos disponibles: `getAll`, `getByFilter`, `getById`, `search`, `getByRange`,
`create`, `update`, `delete`. Todos devuelven `{status, results, total, message}`.

### 9.2 El layout (`web/views/template.php`)
Variables que puedes inyectar antes del `include`:
- `$pageTitle`, `$pageDescription` — título y meta description.
- `$pageContent` — el HTML principal.
- `$seoMeta`, `$seoSettings` — datos SEO (ver §10).
- `$additionalCSS`, `$additionalJS` — arrays de URLs extra.

El template trae Bootstrap, navbar y footer, y **escapa la salida** con `htmlspecialchars(..., ENT_QUOTES)`.
Escapa **siempre** los datos que imprimas tú también.

### 9.3 Páginas por slug
`web/page.php?slug=<slug>` busca en `page_seo` por `slug_seo`, carga su SEO y renderiza con el template.
Así conectas una página creada en el CMS con su URL pública amigable.

---

## 10. SEO y sitemap

### 10.1 SEO por página
Cada página del CMS tiene un **panel SEO** (`cms/views/modules/seo-panel.php`) con:
- `slug` (URL amigable, se autogenera desde el título; sólo minúsculas, dígitos y guiones, único).
- `meta title` y `meta description` (con contador de caracteres).
- **Open Graph:** `og:title`, `og:description`, `og:image` (elegible desde el gestor de archivos), `og:type`.

### 10.2 Cascada de valores
El frontend resuelve los metadatos con *fallback* en 3 niveles:
1. Valor específico de la página (`page_seo`).
2. Valor por defecto global (`cms_settings`, configurable en `/cms/propiedades`).
3. Valor hardcodeado del sitio.

### 10.3 Sitemap
`web/sitemap.php` genera `sitemap.xml` dinámicamente desde `page_seo` (sólo páginas activas), lo cachea en
disco con escritura atómica y se regenera tras cada guardado de SEO o con `?regenerate=1`.

---

## 11. Plugins: usar y crear

### 11.1 Plugins incluidos
| Plugin | Tipo | Qué aporta |
|--------|------|-----------|
| **payku** | payment | Pasarela de pagos Payku (checkout + webhook que valida el monto). |
| **workflow-manager** | system | Editor visual de estados/transiciones para workflows. |
| **dashboard-manager** | system | Widgets arrastrables para el dashboard. |
| **rbac-manager** | system | Permisos granulares por página/acción. |

### 11.2 Cómo funcionan
`plugins/plugins-registry.php` mantiene el registro central; `plugins/plugins-loader.php` escania
`plugins/`, carga el archivo principal de cada plugin registrado y los integra como páginas `custom` del CMS.

### 11.3 Crear un plugin nuevo (receta)

**1. Estructura mínima:**
```
plugins/mi-plugin/
├── mi-plugin.php                       # punto de entrada
├── config.php                          # configuración
├── controllers/
│   └── mi-plugin.controller.php        # lógica (crea su tabla en __construct)
├── views/main.php                      # UI (opcional)
├── assets/{css,js}/                    # estilos/scripts (opcional)
├── ajax.php                            # handlers AJAX (con guard de sesión)
└── .htaccess                           # protege config.php
```

**2. Registrar** en `plugins/plugins-registry.php`:
```php
PluginsRegistry::register('mi-plugin', [
    'url' => 'mi-plugin', 'name' => 'Mi Plugin',
    'description' => '...', 'icon' => 'bi-star',
    'type' => 'custom', 'version' => '1.0.0', 'author' => 'Tu Nombre'
]);
```

**3. Punto de entrada** `mi-plugin.php`:
```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/controllers/mi-plugin.controller.php';
MiPluginController::init();
```

**4. AJAX seguro** `ajax.php` (¡siempre con guard de sesión!):
```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['admin'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
// ... despacho de acciones ...
```

> 💡 Usa la skill `/create-plugin` del repo para generar el andamiaje siguiendo el patrón.
> **Importante:** no versiones el `config.php` del plugin si lleva credenciales.

---

## 12. Empaquetar, desplegar y actualizar

### 12.1 Empaquetar (`/cms/packaging`)
Genera un `.zip`/`.tar.gz` con todo el proyecto + un `database.sql` (volcado completo), excluyendo logs y
temporales. Queda en `/packages/`.

### 12.2 Desplegar en otro servidor
1. Sube y extrae el paquete.
2. Configura `cms/config.php`, `api/config.php`, `web/config.php` con las credenciales del nuevo servidor.
3. Entra a `/cms/install` → detecta `database.sql` y **restaura la BD automáticamente**.

### 12.3 Actualizar (`/cms/updates`)
Comprueba *releases* en GitHub (configurable: `github_owner`, `github_repo`, token opcional) o en un servidor
de updates propio. Muestra versión actual vs. disponible, changelog, y aplica la actualización. Mantiene
historial en `update_history`.

> Si una actualización falla, casi siempre es por **permisos de archivo**. Revisa §3.4.

---

## 13. Recetas y patrones comunes

**Relación entre tablas (foreign key):** usa una columna de tipo `relations` apuntando a la tabla destino.
La convención de nombres es `id_<sufijoDestino>_<sufijoOrigen>` (ej.: en `pedidos` con sufijo `pedido`,
la FK a `usuarios`/`usuario` es `id_usuario_pedido`).

**Listado público filtrado:**
```php
$response = ApiController::getByFilter('productos', 'active_producto', '1', '*', 'price_producto', 'ASC');
```

**Detalle por slug:** combina `web/page.php?slug=...` con un `getByFilter` sobre tu tabla.

**Campo condicional:** en la columna, define reglas JSON:
```json
{ "rules": [{ "field": "tipo", "operator": "equals", "value": "empresa" }], "operator": "and" }
```

**Exportar datos:** botón *Exportar* en cualquier tabla → CSV/Excel.

**Cobrar con Payku:** activa el plugin `payku`, configura sus credenciales y dispara el checkout desde tu
flujo; el webhook valida el pago contra la API de Payku antes de marcarlo como pagado.

---

## 14. Solución de problemas

| Síntoma | Causa probable | Solución |
|---------|----------------|----------|
| "No autorizado" en la API | API key distinta entre configs | Iguala `api.key` en `api/`, `cms/` y `web/` |
| Subidas de archivo fallan | Permisos en `cms/views/assets/files/` | `chmod 775` + dueño = usuario de Apache (§3.4) |
| El CMS muestra el instalador otra vez | Faltan tablas o config de BD incorrecta | Revisa `cms/config.php` y la conexión MySQL |
| Páginas públicas en blanco | API no responde o key inválida | Verifica `web/config.php` y que `api/` esté accesible |
| Update falla | Permisos de escritura | Ajusta permisos (§3.4) y reintenta |
| Sitemap vacío | No hay páginas con SEO activo | Crea SEO por página y/o `?regenerate=1` |

---

## Recursos relacionados

- **`docs/ANALISIS-Y-AUDITORIA.md`** — bugs conocidos, hallazgos de seguridad y mejoras pendientes. **Léelo antes de exponer la app a internet.**
- **`README.md`** — referencia rápida de la API e instalación.
- **`.cursor/docs/`** — documentación técnica orientada a desarrollo (API, autenticación, módulos, plugins…).
- **Skills del repo** (`/create-controller`, `/create-migration`, `/create-plugin`) — generadores de código que siguen las convenciones del proyecto.

> **Recordatorio de seguridad:** en producción usa **siempre HTTPS**, no versiones los `config.php`, y
> aplica primero las correcciones del *Bloque 1* del informe de auditoría (capa AJAX del CMS).
