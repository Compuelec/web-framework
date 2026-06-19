# Generador de Páginas Web

El **Generador de Páginas Web** (CMS → "Páginas Web") permite crear páginas
públicas a partir de tus tablas **escribiendo tu propio HTML** e insertando los
datos donde quieras, con vista previa en vivo. Las páginas pueden ser
interactivas (formularios para crear/editar) y protegidas por login.

> Está pensado para personas no técnicas: eliges una tabla, escribes/ajustas tu
> HTML con ayuda de botones, y ves el resultado al instante.

---

## Cómo funciona

1. **Elige una tabla** de datos. Solo se muestran tus tablas (las del framework
   se ocultan).
2. **Escribe tu HTML** en el editor (con resaltado de sintaxis y autocompletado).
3. **Inserta datos** haciendo clic en los chips de campos o usando los botones.
4. Mira la **vista previa en vivo** a la derecha (con datos reales).
5. **Crear página** → se genera en `web/pages/<nombre>.php`.

Las páginas creadas aparecen en la lista lateral; puedes **editarlas** (cargan
su configuración) o **eliminarlas**.

---

## Etiquetas de la plantilla

| Etiqueta | Qué hace |
| --- | --- |
| `{{campo}}` | Muestra el valor de una columna (escapado). |
| `{{#cada}} ... {{/cada}}` | Repite el HTML interior por **cada registro**. |
| `{{#imagenes campo}}<img src="{{url}}">{{/imagenes}}` | Recorre un campo **multi-imagen** (arreglo JSON) y repite por cada imagen. |
| `{{#form}} ... {{/form}}` | Un **formulario** para crear/editar registros. |
| `{{input campo}}` | Campo de texto del formulario. |
| `{{textarea campo}}` | Área de texto. |
| `{{file campo}}` | Subida de archivo/imagen. |
| `{{submit Texto}}` | Botón de envío. |

Fuera de un bloque `{{#cada}}`, las etiquetas usan el **registro único**: el de
`?id=` en la URL, o el primero de la tabla.

### Chips inteligentes

Al elegir una tabla, cada columna aparece como un **chip**. Al hacer clic:

- Campo de **imagen** → inserta `<img src="{{campo}}">`.
- Campo **multi-imagen** → inserta el bucle `{{#imagenes campo}}...{{/imagenes}}`.
- Otros campos → insertan `{{campo}}`.

### Ejemplo

```html
<div class="row">
  {{#cada}}
    <div class="col-md-4">
      <div class="card">
        <img src="{{imagen_destacada}}" class="card-img-top">
        <div class="card-body">
          <h5>{{nombre}}</h5>
          <p>{{descripcion}}</p>
          <div class="d-flex gap-1">
            {{#imagenes galeria}}<img src="{{url}}" width="60">{{/imagenes}}
          </div>
        </div>
      </div>
    </div>
  {{/cada}}
</div>
```

---

## Formularios (crear / editar + subir archivos)

El botón **"Insertar formulario"** arma un bloque `{{#form}}` con un campo por
cada columna de la tabla (campos de archivo para las imágenes).

Comportamiento de la página generada al enviar el formulario:

- **Sin `?id`** → **crea** un registro nuevo (formulario vacío).
- **Con `?id=5`** (solo en páginas privadas autorizadas) → **edita** ese registro
  (campos prellenados).
- Los archivos subidos se guardan en `web/uploads/` y se almacena su URL.

> Por seguridad, las páginas **públicas solo crean** registros (no pueden editar
> registros existentes vía `?id`).

---

## Control de acceso (Pública / Privada)

Cada página tiene un selector de acceso:

- **Pública** — abierta, sin login.
- **Privada (con login)** — pide iniciar sesión. Reusa los **usuarios (`admins`)
  existentes**: el visitante entra con el mismo email/contraseña del CMS.

Al marcar **Privada** puedes restringir el acceso:

- **Grupos / roles** — uno o varios roles (el `rol_admin` de tus usuarios, p. ej.
  `empleado`, `rrhh`).
- **Usuarios específicos** — usuarios puntuales por email.
- Si no marcas nada → cualquier usuario logueado entra.

Tras el login, la página verifica el rol/usuario; si no está autorizado muestra
"No tienes permiso para ver esta página". Incluye enlace de "Cerrar sesión".

### Ejemplo: portal de solicitudes (RRHH)

1. Crea usuarios (admins) con rol **`empleado`** (gestión de usuarios del CMS).
2. Crea una tabla `solicitudes` (con los campos que necesites).
3. En el generador: elige `solicitudes` → **Insertar formulario** → marca
   **Privada** → en Grupos elige **`empleado`** → **Crear página**.
4. El empleado entra, inicia sesión, llena el formulario y la solicitud se
   **guarda en la base de datos**.

---

## SEO y redes sociales

Cada página tiene una sección **"SEO y redes sociales"** (acordeón) para:

- **Meta Title** y **Meta Description** (lo que ve Google).
- **Open Graph**: OG Title, OG Type, OG Description y OG Image (lo que se muestra
  al compartir el enlace en redes).

La página generada emite estos *meta tags* automáticamente. (Antes el SEO vivía
en el modal de secciones del CMS; ahora es parte de cada página web, que es a
quien le corresponde.)

## Editores de código

Los campos **HTML / CSS / JavaScript** usan editores **CodeMirror** con
resaltado de sintaxis, números de línea, auto-cierre de etiquetas y
**autocompletado** (mientras escribes o con `Ctrl-Espacio`).

---

## Crear páginas por CLI / agentes de IA

También puedes crear páginas **desde la línea de comandos** (útil para automatizar
o para un agente de IA) con `php tools/make-page.php <config.json>`. Generan el mismo
formato, así que **aparecen en esta lista** y se editan igual. Ver
[AGENTE-CREAR-PAGINAS.md](AGENTE-CREAR-PAGINAS.md).

## Requisito: `web/config.php`

Las páginas públicas cargan datos a través de la API usando `web/config.php`. El
generador lo **crea automáticamente** la primera vez (reusando la configuración
de la API del CMS). Si por permisos no pudiera, ejecuta
[`sudo ./setup.sh`](INSTALACION.md#setup-automático). Ver
[Instalación](INSTALACION.md).
