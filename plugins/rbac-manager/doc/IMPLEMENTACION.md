# RBAC Manager — Documentación de uso e implementación

## ¿Qué es RBAC?

**Role-Based Access Control** es un sistema que permite controlar qué partes del CMS puede ver y usar cada administrador, según el rol que se le asigne.

En lugar de darle acceso total a todos los admins, puedes crear roles como "Ventas", "Soporte" o "Editor de Contenido", definir exactamente qué pueden hacer en cada página, y asignar ese rol a los admins correspondientes.

---

## Conceptos clave

### Tipos de admin (`rol_admin`)

El framework maneja tres tipos base que **no se pueden cambiar**:

| Tipo | Acceso |
|---|---|
| `superadmin` | Acceso total. Ignora cualquier configuración RBAC. |
| `admin` | Acceso total. Ignora cualquier configuración RBAC. |
| `editor` | Acceso restringido. Aquí es donde entra RBAC. |

### Roles RBAC

Los roles son grupos de permisos que se crean desde **Roles y Permisos** en el CMS. Un rol define qué puede hacer un `editor` en cada página del sistema.

### Permisos por página

Cada rol define 4 acciones por página:

| Acción | Qué controla |
|---|---|
| **Leer** | Puede ver la página en el menú y acceder a ella |
| **Crear** | Puede agregar nuevos registros |
| **Editar** | Puede modificar registros existentes |
| **Eliminar** | Puede borrar registros |

---

## Cómo configurar roles desde el CMS

### Paso 1 — Crear un rol

1. Ir a **Admins → Roles y Permisos**
2. Clic en **Nuevo Rol**
3. Ingresar nombre (ej: `Ventas`) y descripción opcional
4. En la matriz de permisos, marcar qué puede hacer este rol en cada página
5. Clic en **Guardar Rol**

### Paso 2 — Asignar el rol a un admin

1. Ir a la tab **Asignaciones**
2. Buscar el admin (debe ser de tipo `editor`)
3. Seleccionar el rol en el dropdown y clic en ✓

> **Nota:** Asignar un rol a un admin de tipo `superadmin` o `admin` no tiene efecto — ellos siempre tienen acceso total.

### Paso 3 — Crear el admin editor

Si el admin aún no existe, créalo desde la página **Administradores** con tipo `editor`. Luego asígnale el rol en la tab Asignaciones.

---

## Cómo usar RBAC en el código PHP

El plugin expone un método estático que puedes usar en cualquier controlador, vista o AJAX del CMS.

### Verificar si el admin actual puede hacer algo

```php
// Incluir el controlador (solo si no fue incluido antes)
require_once __DIR__ . '/path/to/plugins/rbac-manager/controllers/rbac-manager.controller.php';

// Verificar permiso
if (RBACManagerController::can('create', 'clientes')) {
    // El admin puede crear registros en la página "clientes"
}

if (RBACManagerController::can('delete', 'pedidos')) {
    // El admin puede eliminar en la página "pedidos"
}
```

### Acciones disponibles

```php
RBACManagerController::can('read',   'url_pagina'); // Ver la página
RBACManagerController::can('create', 'url_pagina'); // Crear registros
RBACManagerController::can('update', 'url_pagina'); // Editar registros
RBACManagerController::can('delete', 'url_pagina'); // Eliminar registros
```

El primer parámetro es la acción, el segundo es el `url_page` de la página tal como está registrada en la base de datos.

### Comportamiento según tipo de admin

```php
RBACManagerController::can('delete', 'clientes');
// superadmin → true  (siempre)
// admin      → true  (siempre)
// editor con rol RBAC → según la matriz del rol asignado
// editor sin rol RBAC → true para todas las acciones excepto leer
//                       (usa el sistema anterior de permissions_admin)
```

---

## Casos de uso prácticos

### Ocultar botón de eliminar según permisos

En cualquier vista PHP del CMS:

```php
<?php if (RBACManagerController::can('delete', 'clientes')): ?>
    <button class="btn btn-danger btn-sm deleteCli">Eliminar</button>
<?php endif ?>
```

### Proteger un AJAX endpoint

En un archivo `mi-modulo.ajax.php`:

```php
require_once __DIR__ . '/../../plugins/rbac-manager/controllers/rbac-manager.controller.php';

if ($_POST['action'] === 'delete') {
    if (!RBACManagerController::can('delete', 'clientes')) {
        echo json_encode(['success' => false, 'error' => 'Sin permisos para eliminar']);
        exit;
    }
    // proceder con el delete...
}
```

### Proteger un controlador

En un controlador del CMS:

```php
public function deleteRecord() {
    require_once __DIR__ . '/../../plugins/rbac-manager/controllers/rbac-manager.controller.php';

    if (!RBACManagerController::can('delete', 'pedidos')) {
        echo '<script>fncToastr("error", "No tienes permisos para eliminar");</script>';
        return;
    }
    // lógica de delete...
}
```

---

## Cómo funciona internamente

```
Admin hace request
       │
       ▼
template.php verifica acceso a la página
       │
       ├── rol = superadmin/admin ──────────────────→ ACCESO TOTAL
       │
       ├── rol = editor + id_role_admin asignado ──→ Carga permisos del rol
       │         │                                    desde tabla `roles`
       │         └── url_page en permisos con read=1 → ACCESO
       │             url_page sin read ────────────→ 404
       │
       └── rol = editor sin id_role_admin ──────────→ Sistema anterior
                                                       (permissions_admin JSON)
```

Los permisos del rol se cargan **una sola vez por sesión** y se cachean en `$_SESSION['_rbac_permissions']`. Si cambias los permisos de un rol, el admin afectado los verá actualizados en su próximo login.

---

## Tablas en la base de datos

### `roles`

| Columna | Tipo | Descripción |
|---|---|---|
| `id_role` | INT | PK autoincremental |
| `name_role` | VARCHAR(100) | Nombre único del rol |
| `description_role` | VARCHAR(255) | Descripción opcional |
| `permissions_role` | TEXT | JSON con permisos por página |
| `date_created_role` | DATE | Fecha de creación |

**Estructura del JSON `permissions_role`:**

```json
{
    "clientes": {
        "read": 1,
        "create": 1,
        "update": 1,
        "delete": 0
    },
    "pedidos": {
        "read": 1,
        "create": 0,
        "update": 0,
        "delete": 0
    }
}
```

### Columna agregada a `admins`

| Columna | Tipo | Descripción |
|---|---|---|
| `id_role_admin` | INT NULL | FK a `roles.id_role`. NULL = sin rol RBAC |

---

## Preguntas frecuentes

**¿Puedo asignar múltiples roles a un mismo admin?**
No, actualmente cada admin tiene máximo un rol RBAC. Si necesitas combinar permisos, crea un rol que los incluya todos.

**¿Qué pasa si un admin tiene rol RBAC pero intenta acceder a una página que no está en sus permisos?**
Ve la página 404 del CMS. El acceso es bloqueado en `template.php`.

**¿El sistema RBAC reemplaza el campo `permissions_admin`?**
No lo elimina. Si un editor tiene `id_role_admin` asignado, se usa RBAC. Si no tiene rol asignado, sigue funcionando el sistema anterior con `permissions_admin`.

**¿Cómo invalido la caché de permisos si cambio un rol?**
La caché es por sesión (`$_SESSION['_rbac_permissions']`). Se invalida automáticamente cuando el admin cierra sesión y vuelve a entrar. Para invalidarla en código:

```php
RBACManagerController::clearSessionCache();
```

**¿Puedo usar RBAC en el frontend público (`web/`)?**
No, RBAC solo aplica al panel de administración (`cms/`). El frontend usa su propia lógica de acceso vía la API.
