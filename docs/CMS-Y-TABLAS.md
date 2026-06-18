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
Código, Relaciones, Orden, y multimedia:

- **Imagen** — una imagen. Se sube **directo** con el botón "Agregar imagen" y se
  muestra una miniatura (con opción de quitar). Se guarda la URL.
- **Múltiples Imágenes** — varias imágenes en un mismo registro. Seleccionas
  varias a la vez, se muestran como miniaturas con botón de quitar, y se guardan
  como un **arreglo JSON** de URLs. Puedes fijar un **máximo de imágenes** por
  columna (campo opcional que aparece al elegir este tipo).
- **Video / Archivo** — se eligen desde el gestor de archivos.

## Gestión de datos

Cada tabla genera automáticamente su formulario y su listado en el CMS. En el
listado, los campos de imagen/galería muestran miniaturas.

## Usuarios y roles

Los usuarios son los `admins`, cada uno con un **rol** (`rol_admin`, p. ej.
`superadmin`, `admin`, `editor`, o roles personalizados como `empleado`). Los
roles funcionan como **grupos** para restringir el acceso a páginas públicas
privadas — ver [Generador de Páginas → Control de acceso](GENERADOR-PAGINAS.md#control-de-acceso-pública--privada).
