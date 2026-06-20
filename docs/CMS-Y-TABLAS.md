# CMS Builder, tablas y campos

El CMS Builder (`/cms/`) te permite construir y gestionar tu aplicación sin
escribir código repetitivo.

## Qué puedes hacer

1. **Crear tablas dinámicamente** (módulos) desde la interfaz.
2. **Gestionar datos** (CRUD completo) desde el panel.
3. **Crear páginas públicas** sin código (ver
   [Generador de Páginas](GENERADOR-PAGINAS.md)).
4. **Gestionar archivos** multimedia.
5. **Configurar usuarios** con roles y permisos.

## Crear una tabla (módulo)

Desde el CMS creas un módulo y defines sus **columnas**. Cada columna tiene un
título, alias, tipo y visibilidad. Al guardar, el sistema crea la tabla en MySQL
con las columnas indicadas.

### Tipos de campo

Texto, Área de texto, Número entero/decimal, Booleano, Selección, Arreglo,
Objeto, JSON, Fecha/Hora/Fecha-Hora, Color, Dinero, Email, Contraseña, Enlace,
Código, Relaciones, Orden, **Medida (número + unidad)**, y multimedia:

- **Imagen** — una imagen. Se sube **directo** con el botón "Agregar imagen" y se
  muestra una miniatura (con opción de quitar). Se guarda la URL.
- **Múltiples Imágenes** — varias imágenes en un mismo registro. Seleccionas
  varias a la vez, se muestran como miniaturas con botón de quitar, y se guardan
  como un **arreglo JSON** de URLs. Puedes fijar un **máximo de imágenes** por
  columna (campo opcional que aparece al elegir este tipo).
- **Video / Archivo** — se eligen desde el gestor de archivos.
- **Medida (número + unidad)** — muestra un valor numérico junto a su unidad en
  **una sola celda** (p. ej. `15 Kilogramo` en vez de dos columnas). Se guarda
  como `DOUBLE`, así que sigue siendo numérico (sumas, triggers y cálculos siguen
  funcionando). La unidad sale del `matrix_column` de la columna, que puede ser
  una **unidad literal** (`"kg"`) o el **nombre de una columna hermana** que
  guarda la unidad por fila (p. ej. `unidad_insumo`), para que cada fila muestre
  su propia unidad. El mismo campo de parámetro/matrix usado para "máximo de
  imágenes" captura aquí la unidad. En el listado se recortan los decimales
  insignificantes (`15.00` → `15`, `2.50` → `2.5`) y se añade la unidad.

### Formato del listado

Algunas columnas se muestran **formateadas** en el listado, según el bloque
opcional `localization` de `cms/config.php` (los valores por defecto preservan el
comportamiento anterior):

- **Dinero** (`money`) — con la moneda configurada (símbolo, decimales y
  separadores).
- **Selección** (`select`) — como **píldoras/badges de color** (un color estable
  por valor).
- **Fecha / Fecha-Hora / Hora** (`date`/`datetime`/`time`) — en un formato
  legible y configurable (si no se puede interpretar, se muestra el valor crudo).

Ver el detalle de configuración en [Instalación](INSTALACION.md#3-archivos-de-configuración).

### Relaciones

Una columna `relations` apunta a otra tabla mediante su `matrix_column`. Además
del caso normal (un módulo de "tablas"), admite:

- **Tabla destino `admins`** — el `matrix_column` puede ser `"admins"` (tabla del
  núcleo). Resuelve el admin relacionado (cajero/usuario) y muestra su **nombre**
  (título, o el email como respaldo) en vez del id, enlazado al gestor de admins.
  Usa un select acotado, así que nunca expone el hash de la contraseña.
- **Columna a mostrar** — el `matrix_column` puede ser `"tabla"` (muestra la
  **segunda columna** de la fila relacionada, comportamiento previo) o
  `"tabla:columna"` para mostrar una columna específica de esa fila (p. ej. la
  fecha de una venta). Es retrocompatible: sin `:` se mantiene el comportamiento
  anterior.

## Gestión de datos

Cada tabla genera automáticamente su formulario y su listado en el CMS. En el
listado, los campos de imagen/galería muestran miniaturas.

## Usuarios y roles

Los usuarios son los `admins`, cada uno con un **rol** (`rol_admin`, p. ej.
`superadmin`, `admin`, `editor`, o roles personalizados como `empleado`). Los
roles funcionan como **grupos** para restringir el acceso a páginas públicas
privadas — ver [Generador de Páginas → Control de acceso](GENERADOR-PAGINAS.md#control-de-acceso-pública--privada).
