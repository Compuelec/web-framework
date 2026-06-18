# Guía para agentes de IA: crear páginas web

Este documento está dirigido a un **agente de IA** que debe crear páginas públicas
del sitio (con su HTML, CSS y JavaScript) usando la **CLI** o la **API** del
framework.

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

## Método recomendado: CLI `tools/make-page.php`

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
| `table` | string | Tabla de datos a vincular. **Opcional**: si la omites, es una página **estática** (sin datos). |
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

## Reglas para el agente

1. **Siempre** genera con `tools/make-page.php` (nunca escribas el `.php` a mano), o
   la página no aparecerá en el admin ni será editable.
2. `name` en minúsculas, sin espacios ni acentos (`a-z 0-9 _ -`).
3. Si la página usa datos, indica `table` y usa las etiquetas; si es de contenido,
   omite `table`.
4. CSS va en `customCss`, JS en `customJs` (no metas `<style>`/`<script>` dentro de
   `template`; el motor los inyecta correctamente).
5. Para la página de inicio, usa `"isHome": true` (solo una a la vez).
