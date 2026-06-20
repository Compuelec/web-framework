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
| --- | --- |
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

### 3.4 Setup automático (recomendado)

Tras instalar o restaurar un respaldo, ejecuta el setup **una vez**. Crea los
`config.php` faltantes (incluido un `web/config.php` funcional derivado de
`cms/config.php`), crea los directorios escribibles y ajusta dueño y permisos:

```bash
sudo ./setup.sh
# En Linux indica el usuario del servidor si no es www-data:  sudo ./setup.sh apache
```

Es idempotente (no pisa configs existentes). Esto evita el problema de "la página
pública no carga datos" (cuando falta `web/config.php`) y los problemas de
permisos. Detalle en [INSTALACION.md](INSTALACION.md).

> El Generador de Páginas también **crea `web/config.php` solo** la primera vez,
> así que normalmente no tendrás que tocar nada.

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
| --- | --- | --- | --- |
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

### Paso 6 — Mostrarlos en el sitio público (sin código)
Usa el **Generador de Páginas Web** del CMS (menú **Páginas Web**), sin escribir PHP:

1. Elige la tabla **`productos`**.
2. Haz clic en **"Repetir por cada registro"** y escribe/ajusta tu HTML usando los
   **chips de campos** (al hacer clic en el campo `image` inserta un `<img>`):

   ```html
   <div class="row">
     {{#cada}}
       <div class="col-md-4 mb-4">
         <div class="card h-100">
           <img src="{{image_producto}}" class="card-img-top">
           <div class="card-body">
             <h5>{{name_producto}}</h5>
             <p class="fw-bold">${{price_producto}}</p>
           </div>
         </div>
       </div>
     {{/cada}}
   </div>
   ```

3. Mira la **vista previa en vivo** a la derecha → **Crear página**.

Abre la página (botón **"Ver página"**) → **tu catálogo está online.** Más adelante
puedes editarla, agregar **formularios** (crear/editar) y **protegerla con login**:
ver [Generador de Páginas](GENERADOR-PAGINAS.md).

> 🎉 En 6 pasos creaste: tabla en BD + formulario de carga + panel de gestión + API REST + página
> pública **sin escribir código**. Ese es el potencial del framework: **defines los datos una vez y
> lo demás es automático.**

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
- **Optimización automática de imágenes:** al subir, las imágenes (JPG/PNG) se
  **comprimen y convierten a WebP** (mucho más livianas; mantienen la transparencia
  de los PNG sin fondo) y se reducen si superan 1920px. Si el servidor no soporta
  WebP, la imagen se sube sin cambios (no falla).

### 5.5 Dashboard (`/cms/`)
Página de inicio con métricas y gráficos. Con el plugin **dashboard-manager** puedes añadir widgets
arrastrables: métricas (count/sum/avg), gráficos, registros recientes, KPIs, accesos rápidos y HTML libre.

### 5.6 Apariencia (`/cms/apariencia`)
Personaliza la **identidad y los colores** del panel — todo se aplica **en vivo** al
guardar (sin recargar):

- **Marca / Identidad:** nombre del dashboard, **logo** (se sube directo y, si hay,
  reemplaza el texto en el menú) y **símbolo / ícono** (con selector de íconos en
  grilla). Antes vivían en el perfil; ahora se configuran aquí.
- **Colores del tema:** color primario (el acento de todo el panel), fondo del
  sidebar, ítems activos, con **paletas predefinidas**.

> El **nombre, logo, símbolo y color** del dashboard se configuran aquí (ya no en
> el perfil del superadmin).

> El **formato regional** de los listados (moneda y formato de fechas) se ajusta en
> el bloque opcional `localization` de `cms/config.php`, no aquí. Ver
> [Instalación](INSTALACION.md#3-archivos-de-configuración).

### 5.6.1 Secciones vs. Páginas Web
- **Agregar Sección** (menú lateral) crea un **ítem del menú del CMS** (una tabla,
  un enlace, etc.). *No* es una página pública.
- **Páginas Web** (`/cms/web-pages`) es el **Generador de Páginas públicas**
  (catálogos, landings…). El **SEO/Open Graph** de cada página se configura ahí.
  Ver **[Generador de Páginas](GENERADOR-PAGINAS.md)**.

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
| --- | --- |
| Texto | `text`, `textarea`, `email`, `password` |
| Números | `int`, `double`, `money` |
| Fecha/hora | `date`, `time`, `datetime`, `timestamp` |
| Booleano | `boolean` |
| Selección | `select` (opciones predefinidas en *matrix*), `color` |
| Estructurados | `array`, `object`, `json` |
| Multimedia | `image`, `multiimage` (varias imágenes), `video`, `file`, `link` |
| Medida | `measure` (número + unidad en una sola celda) |
| Avanzados | `relations` (vínculo a otra tabla), `code` (editor WYSIWYG Summernote / CodeMirror), `workflow` (estados), `chatgpt` (integración OpenAI), `order` (ordenamiento) |

**Notas:**
- `image` — una imagen: se **sube directo** con el botón "Agregar imagen" y se ve una miniatura.
- `multiimage` — **varias imágenes** en un registro: seleccionas varias a la vez, se muestran como
  miniaturas (con quitar) y se guardan como arreglo JSON. Puedes fijar un **máximo** por columna.
- `measure` ("Medida (número + unidad)") — muestra un número junto a su unidad en **una sola celda**
  (p. ej. `15 Kilogramo`). Se guarda como `DOUBLE`, así que sigue siendo numérico (sumas y cálculos
  funcionan). La unidad sale del campo *matrix*: una **unidad literal** (`kg`) o el **nombre de una
  columna hermana** que guarda la unidad por fila (p. ej. `unidad_insumo`). En el listado recorta los
  decimales sobrantes (`15.00` → `15`, `2.50` → `2.5`) y añade la unidad.
- `select` y `relations` usan el campo *matrix* de la columna para definir opciones o la tabla relacionada.
  Una `relations` a una **tabla-módulo** muestra por defecto su **segunda columna**, o la que indiques con
  `"tabla:columna"`. Si apunta a la tabla del núcleo **`admins`** (matrix `"admins"`) tiene trato especial:
  muestra siempre el **nombre** del usuario/cajero (o su email), nunca el id ni una columna sensible.
- En el listado, `money` se muestra con la **moneda** configurada, `select` como **píldoras de color**
  y las fechas en formato legible — todo según el bloque opcional `localization` de `cms/config.php`
  (ver §3 / [Instalación](INSTALACION.md#3-archivos-de-configuración)).
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

> 💡 **La forma recomendada de crear páginas públicas es el Generador de Páginas
> Web** (CMS → "Páginas Web"): visual, sin código, con vista previa en vivo,
> galerías de imágenes, formularios (crear/editar) y login por rol/usuario. Ver
> **[Generador de Páginas](GENERADOR-PAGINAS.md)**. Lo que sigue describe cómo
> funcionan las páginas por debajo, útil para casos avanzados o a medida.
>
> El selector de tablas del generador también lista las **vistas (VIEWs)** de la
> base de datos, así que una página puede vincularse a una vista curada/filtrada
> (p. ej. "solo productos activos").

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

El template trae Bootstrap y **escapa la salida** con `htmlspecialchars(..., ENT_QUOTES)`.
El **header y el footer** salen de los partials compartidos (ver §9.4). Escapa
**siempre** los datos que imprimas tú también.

### 9.3 Páginas por slug y URLs limpias
`web/page.php?slug=<slug>` resuelve una URL amigable: si existe una página generada
`web/pages/<slug>.php`, la sirve directo; si no, busca el SEO en `page_seo`. Con el
`.htaccess`, las páginas quedan en **`/<slug>`** (sin `/pages/` ni `.php`).

### 9.4 Header, footer y página de inicio (compartidos)
- **Header y Footer:** uno solo para **todo el sitio**, editable desde el Generador
  de Páginas (items fijados arriba de la lista, con su HTML/CSS/JS). No se pueden
  borrar. Ver [Generador de Páginas](GENERADOR-PAGINAS.md).
- **Página de inicio:** en el Generador, marca una página con **"Usar como página de
  inicio"** y la raíz del dominio (`www.tudominio.cl/`) abrirá esa página.

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
| --- | --- | --- |
| **payku** | payment | Pasarela de pagos Payku (checkout + webhook que valida el monto). |
| **workflow-manager** | system | Editor visual de estados/transiciones para workflows. |
| **dashboard-manager** | system | Widgets arrastrables para el dashboard. |
| **rbac-manager** | system | Permisos granulares por página/acción. |
| **pos-manager** | system | POS de cajero configurable: venta con descuento de stock atómico. |

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
4. **Cambio de dominio automático:** el instalador detecta el dominio del paquete
   (ej. `http://localhost/proyectos-web/web-framework`) y el dominio actual
   (ej. `https://www.tudominio.cl`), y **reescribe todas las URLs en la base de
   datos** — incluidas las de **imágenes** y enlaces guardados en cualquier tabla
   (productos, propiedades, logo, SEO, etc.). No tienes que hacerlo a mano.

> Ejemplo: una imagen `http://localhost/proyectos-web/web-framework/cms/views/assets/files/x.webp`
> queda como `https://www.tudominio.cl/cms/views/assets/files/x.webp`.

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
| --- | --- | --- |
| "No autorizado" en la API | API key distinta entre configs | Iguala `api.key` en `api/`, `cms/` y `web/` |
| Subidas de archivo fallan | Permisos en `cms/views/assets/files/` | Ejecuta `sudo ./setup.sh` (§3.4) o usa **CMS → Estado del Sistema** |
| El CMS muestra el instalador otra vez | Faltan tablas o config de BD incorrecta | Revisa `cms/config.php` y la conexión MySQL |
| Página generada **solo muestra el título** (sin datos) | Falta `web/config.php` o la API no responde | Ejecuta `sudo ./setup.sh`; vuelve a guardar la página en el Generador |
| Páginas públicas en blanco | API no responde o key inválida | Verifica `web/config.php` y que `api/` esté accesible |
| Update falla | Permisos de escritura | Ejecuta `sudo ./setup.sh` (§3.4) y reintenta |
| Sitemap vacío | No hay páginas con SEO activo | Crea SEO por página y/o `?regenerate=1` |

---

## Recursos relacionados

- **[../README.md](../README.md)** — índice general y referencia rápida.
- **[GENERADOR-PAGINAS.md](GENERADOR-PAGINAS.md)** — el Generador de Páginas Web en detalle (tags, formularios, login/acceso).
- **[AGENTE-CREAR-PAGINAS.md](AGENTE-CREAR-PAGINAS.md)** — crear secciones y páginas por **CLI** (para automatizar o con un agente de IA): `tools/make-table.php` (tabla + admin) y `tools/make-page.php` (página).
- **[INSTALACION.md](INSTALACION.md)** — instalación, `setup.sh`, permisos y troubleshooting.
- **[API.md](API.md)**, **[CMS-Y-TABLAS.md](CMS-Y-TABLAS.md)**, **[ARQUITECTURA.md](ARQUITECTURA.md)**, **[SEGURIDAD.md](SEGURIDAD.md)**, **[DESARROLLO.md](DESARROLLO.md)**.
- **Generadores CLI** (`tools/make-controller`, `make-migration`, `make-plugin`) — siguen las convenciones del proyecto.

> **Recordatorio de seguridad:** en producción usa **siempre HTTPS**, no versiones los `config.php`, y
> aplica primero las correcciones del *Bloque 1* del informe de auditoría (capa AJAX del CMS).
