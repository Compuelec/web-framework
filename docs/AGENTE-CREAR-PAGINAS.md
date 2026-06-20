# Guía para agentes de IA: crear sistemas y páginas

Este documento está dirigido a un **agente de IA** que debe construir
funcionalidades completas con el framework desde la **CLI**: una **sección de
datos** (tabla + administración CRUD) y/o una **página pública** (con su HTML, CSS
y JavaScript) que la muestre.

Con dos comandos el agente arma un sistema entero:

| Comando | Crea |
| --- | --- |
| `tools/make-table.php` | Una **sección de datos**: tabla MySQL + CRUD en el admin (gestionar registros, stock, etc.). |
| `tools/make-page.php` | Una **página pública** que lista/usa esos datos (precio, stock, carrito, formularios…). |

> Ejemplo end-to-end: "crea una sección de productos para manejar stock y una
> página que los liste con precio, stock y carrito" → `make-table.php` (tabla
> `productos`) + `make-page.php` (página `tienda` con carrito). Ver el ejemplo al
> final.

## Principio clave (lee esto primero)

Las páginas públicas son archivos en `web/pages/<nombre>.php`. Para que una página
**aparezca en el panel de administración** (CMS → **"Páginas Web"**) y se pueda
**editar** ahí después, **debe generarse con el motor del framework**, que incrusta
la configuración de la página como base64 en una línea:

```php
$wpbConfig = '<base64...>';
```

El CMS lista una página solo si encuentra esa línea. **No escribas el `.php` a
mano**: usa el comando CLI de abajo, que genera ese formato por ti. Así la página:

- aparece en la lista "Páginas creadas",
- es editable desde el CMS (HTML/CSS/JS por separado),
- queda con URL limpia (`/<nombre>`).

---

## Paso 1 — Crear la sección de datos (`tools/make-table.php`)

Crea la tabla MySQL **y** la registra en el admin como sección **Modular**, con
CRUD automático (crear/editar/eliminar registros). No requiere escribir archivos.

```bash
php tools/make-table.php config.json
# o JSON inline:
php tools/make-table.php '{"name":"productos","title":"Productos","icon":"bi bi-box-seam","fields":[{"name":"nombre","type":"text"},{"name":"precio","type":"money"},{"name":"stock","type":"int"},{"name":"imagen","type":"image"}]}'
```

Imprime un JSON con los **nombres reales de las columnas** (úsalos en la página):

```json
{ "table": "productos", "idColumn": "id_producto",
  "columns": ["nombre_producto","precio_producto","stock_producto","imagen_producto"] }
```

### Config de la sección

| Clave | Descripción |
| --- | --- |
| `name` **(req.)** | Nombre de la tabla y URL de la sección (`a-z 0-9 _`). |
| `title` | Título en el menú del admin (default: `name`). |
| `icon` | Clase de Bootstrap Icons, ej. `bi bi-box-seam`. |
| `fields` **(req.)** | Lista de campos `[{ "name", "type", "alias?", "visible?" }]`. |

El framework agrega solos la **PK** (`id_<suffix>`) y las fechas de creación/edición.
A cada campo le añade el sufijo de la tabla (`precio` → `precio_producto`).

### Tipos de campo (`type`)

`text`, `textarea`, `int`, `double`, `money`, `measure` (número + unidad),
`boolean`, `date`, `time`, `email`, `link`, `color`, `select`, `image`,
`multiimage`, `file`, `json`, `object`.

> `measure` muestra un número con su unidad en una sola celda y se guarda como
> `DOUBLE`. La unidad sale del *matrix* de la columna: una unidad literal (`kg`) o
> el nombre de una columna hermana con la unidad por fila (`unidad_insumo`).

Tras crearla, la sección aparece en el **menú del CMS** y el usuario puede cargar
registros (o cárgalos tú vía la API REST: `POST /api/<tabla>`).

### ¿Esta tabla necesita página pública?

**No siempre.** El Paso 2 (crear página) es **opcional**. Decide por cada tabla:

- **Tabla con página** → usa `make-page.php` para una vista pública (catálogo,
  listado, tienda…).
- **Tabla solo-datos** (sin página) → se gestiona en el admin y se consume desde
  una **aplicación web** (o app móvil) por la **API REST**. Muchas secciones son
  así: configuraciones, inventario, usuarios de la app, pedidos, etc.

Si el usuario no especifica, **pregunta** qué tablas requieren página y cuáles son
solo-datos.

### Consumir una sección solo-datos por la API

La tabla queda disponible de inmediato en la API dinámica (header
`Authorization: <api-key>`):

```text
GET    /api/<tabla>                          # listar
GET    /api/<tabla>?linkTo=col&equalTo=valor # filtrar
POST   /api/<tabla>                          # crear   (body JSON)
PUT    /api/<tabla>?id=5&nameId=id_<suffix>  # actualizar
DELETE /api/<tabla>?id=5&nameId=id_<suffix>  # eliminar
```

Detalles completos en **[API.md](API.md)**.

---

## Paso 2 — Crear la página pública (`tools/make-page.php`)

```bash
php tools/make-page.php config.json
# o JSON inline:
php tools/make-page.php '{"name":"contacto","heading":"Contacto","template":"<h1>Hola</h1>"}'
# o por stdin:
cat config.json | php tools/make-page.php
```

Imprime un JSON con el resultado (`success`, `file`, `slug`). Código de salida `0`
si todo bien, `1` si hubo error (el mensaje va a stderr).

El comando se encarga de: buscar la PK/columnas reales de la tabla (si se indica),
generar el `.php` con la config embebida, crear `web/config.php` y el header/footer
si faltan, y dejar la página lista.

### Esquema del JSON de configuración

| Clave | Tipo | Descripción |
| --- | --- | --- |
| `name` | string **(requerido)** | Nombre del archivo (`a-z 0-9 _ -`). La página será `web/pages/<name>.php` y su URL `/<name>`. |
| `heading` | string | Título (pestaña del navegador + nombre en la lista del CMS). |
| `template` | string | Tu **HTML** (admite las etiquetas de abajo). |
| `customCss` | string | CSS que se inyecta en la página. |
| `customJs` | string | JavaScript que se inyecta en la página. |
| `table` | string | Tabla **o vista (VIEW)** de datos a vincular. **Opcional**: si la omites, es una página **estática** (sin datos). Vincular una vista permite páginas basadas en datos curados/filtrados (p. ej. "solo productos activos"). |
| `metaTitle`, `metaDesc` | string | SEO (título y descripción para buscadores). |
| `ogTitle`, `ogType`, `ogDesc`, `ogImage` | string | Open Graph (al compartir en redes). |
| `private` | bool | Requiere login para verla (default `false`). |
| `accessRoles` | array | Roles permitidos (`rol_admin`) cuando es privada. |
| `accessUsers` | array | IDs de usuarios permitidos cuando es privada. |
| `isHome` | bool | Además marca esta página como **inicio** del sitio (raíz del dominio). |

---

## Etiquetas de plantilla (en `template`)

Solo aplican si vinculaste una `table`.

| Etiqueta | Qué hace |
| --- | --- |
| `{{campo}}` | Inserta el valor de una columna (escapado). Usa el **registro único** (el de `?id=` o el primero). |
| `{{#cada}} ... {{/cada}}` | Repite el HTML interior por **cada registro** de la tabla. |
| `{{#imagenes campo}}<img src="{{url}}">{{/imagenes}}` | Recorre un campo **multi-imagen** (JSON de URLs) y repite por cada imagen. |
| `{{#form}} ... {{/form}}` | Un **formulario** para crear/editar registros. |
| `{{input campo}}` / `{{textarea campo}}` / `{{file campo}}` | Campos del formulario (texto / área / archivo). |
| `{{submit Texto}}` | Botón de envío. |

> El formulario, **en páginas públicas, solo crea** registros. Editar (`?id=5`) solo
> está permitido en páginas privadas autorizadas (seguridad).

---

## Ejemplos

### 1) Página estática (sin datos) con CSS y JS

```json
{
  "name": "inicio",
  "heading": "Bienvenido",
  "template": "<section class=\"hero\"><h1>Mi Empresa</h1><button id=\"cta\">Contáctanos</button></section>",
  "customCss": ".hero{padding:5rem 1rem;text-align:center}.hero h1{font-size:3rem}",
  "customJs": "document.getElementById('cta').addEventListener('click',()=>alert('¡Gracias!'));",
  "isHome": true
}
```

### 2) Página con datos de una tabla (listado)

```json
{
  "name": "propiedades",
  "heading": "Propiedades",
  "table": "propiedades",
  "template": "<div class=\"row\">{{#cada}}<div class=\"col-md-4\"><div class=\"card\"><img src=\"{{imagen}}\" class=\"card-img-top\"><div class=\"card-body\"><h5>{{titulo}}</h5><p>{{precio}}</p></div></div></div>{{/cada}}</div>",
  "customCss": ".card{margin-bottom:1rem}"
}
```

### 3) Página privada con formulario (ej. solicitudes de RRHH)

```json
{
  "name": "solicitudes",
  "heading": "Solicitar permiso",
  "table": "solicitudes",
  "template": "<div class=\"container py-4\">{{#form}}<label>Motivo</label>{{textarea motivo}}<label>Fecha</label>{{input fecha}}{{submit Enviar}}{{/form}}</div>",
  "private": true,
  "accessRoles": ["empleado"]
}
```

---

## Cómo se ve en el admin

Tras ejecutar el comando, abre el CMS → **"Páginas Web"**. La página aparece en la
lista izquierda **"Páginas creadas"**. Al hacer clic se carga en el editor con su
**HTML, CSS y JS por separado** — el agente y un humano editan la misma página.

El **header y el footer** del sitio son compartidos (uno para todas las páginas) y
también se editan ahí (items fijados arriba de la lista); no los toques por página.

---

## URLs resultantes

Con el _document root_ del dominio apuntando a `web/`:

- `www.tu-dominio.cl/<name>` → la página (`web/pages/<name>.php`).
- `www.tu-dominio.cl/` → la página marcada con `isHome: true`.

En local (subcarpeta): `…/web/<name>`. Ver [GENERADOR-PAGINAS.md](GENERADOR-PAGINAS.md).

---

## Alternativa: API / AJAX del CMS

La creación por CLI es la vía recomendada para un agente. Existe también el endpoint
del CMS `cms/ajax/web-pages.ajax.php` (acción `generate`), pero **requiere sesión de
administrador y token CSRF**, por lo que solo es práctico desde el navegador del CMS.
La **API REST** (`api/`) opera sobre **tablas de datos**, no sobre las páginas
(las páginas son archivos). Por eso, para crear páginas programáticamente, usa la
CLI `tools/make-page.php`.

---

## Ejemplo completo: productos + tienda con carrito

**1. Crear la sección de datos** (tabla + CRUD en el admin):

```bash
php tools/make-table.php '{"name":"productos","title":"Productos","icon":"bi bi-box-seam","fields":[{"name":"nombre","type":"text"},{"name":"descripcion","type":"textarea"},{"name":"precio","type":"money"},{"name":"stock","type":"int"},{"name":"imagen","type":"image"}]}'
```

Devuelve las columnas: `nombre_producto`, `precio_producto`, `stock_producto`,
`imagen_producto` (úsalas en la plantilla).

**2. Crear la página pública** que los lista con precio, stock y **carrito**. El
carrito es JavaScript en `customJs` (estado en `localStorage`), y el stock 0
deshabilita el botón. Esqueleto:

```jsonc
{
  "name": "tienda",
  "heading": "Tienda",
  "table": "productos",
  "template": "<div class=\"grid\">{{#cada}}<article><img src=\"{{imagen_producto}}\"><h3>{{nombre_producto}}</h3><span class=\"price\" data-price=\"{{precio_producto}}\"></span><span class=\"stock\" data-stock=\"{{stock_producto}}\"></span><button class=\"add\" data-name=\"{{nombre_producto}}\" data-price=\"{{precio_producto}}\" data-stock=\"{{stock_producto}}\">Agregar</button></article>{{/cada}}</div>",
  "customCss": ".grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem} /* … */",
  "customJs": "var KEY='cart';function get(){return JSON.parse(localStorage.getItem(KEY)||'[]')}function add(n,p){var c=get();var i=c.find(x=>x.name===n);i?i.qty++:c.push({name:n,price:+p,qty:1});localStorage.setItem(KEY,JSON.stringify(c));} document.querySelectorAll('.add').forEach(b=>{if(+b.dataset.stock<=0){b.disabled=true;b.textContent='Sin stock';}else{b.onclick=()=>add(b.dataset.name,b.dataset.price);}});"
}
```

La página queda en `web/pages/tienda.php`, aparece en el admin y se ve en
`/tienda`. Los datos los gestiona el usuario en la sección **Productos** del CMS
(o el agente vía `POST /api/productos`).

---

## Reglas para el agente

1. Elige el caso según lo que pida el usuario:
   - **Datos + página pública**: `make-table.php` (sección) → `make-page.php` (página
     con esa `table`).
   - **Solo datos** (app web / sin página pública): solo `make-table.php`; la app
     consume la tabla por la **API REST**.
   - **Solo contenido** (landing, info): solo `make-page.php` sin `table`.

   Si no está claro, **pregunta** qué tablas necesitan página y cuáles son solo-datos.
2. **Siempre** genera con `tools/make-page.php` (nunca escribas el `.php` a mano), o
   la página no aparecerá en el admin ni será editable.
2. `name` en minúsculas, sin espacios ni acentos (`a-z 0-9 _ -`).
3. Si la página usa datos, indica `table` y usa las etiquetas; si es de contenido,
   omite `table`.
4. CSS va en `customCss`, JS en `customJs` (no metas `<style>`/`<script>` dentro de
   `template`; el motor los inyecta correctamente).
5. Para la página de inicio, usa `"isHome": true` (solo una a la vez).
