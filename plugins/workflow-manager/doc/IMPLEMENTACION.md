# Plugin Workflow Manager — Documentación de uso e implementación

## ¿Qué hace este plugin?

Permite definir **máquinas de estado** para cualquier módulo del CMS. En lugar de tener un campo de estado libre, el Workflow Manager controla exactamente qué estados existen, cómo se transita entre ellos, y qué roles tienen permiso de hacer cada transición.

**Ejemplo:** Un módulo de "Pedidos" puede tener los estados: Borrador → Enviado → Aprobado / Rechazado. Solo el rol `admin` puede aprobar, cualquiera puede enviar.

---

## Estructura de archivos

```
plugins/workflow-manager/
├── workflow-manager.php                # Entry point (solo registro)
├── config.php                          # Configuración del plugin
├── config.example.php                  # Plantilla de configuración
├── controllers/
│   └── workflow-manager.controller.php # Lógica: estados, transiciones, validaciones
├── views/
│   └── main.php                        # UI del editor de workflows
├── assets/
│   ├── css/workflow-manager.css
│   └── js/workflow-manager.js
└── ajax.php                            # Endpoints AJAX (solo superadmin)
```

---

## Requisito previo: columna de tipo `workflow`

Para que un módulo aparezca en el Workflow Manager, debe tener al menos una columna de tipo **workflow** en su definición.

**Cómo agregar la columna:**
1. Ir al módulo en el CMS
2. Clic en el ícono de configuración del módulo
3. Agregar columna → Tipo: `workflow`
4. Nombre sugerido: `status`, `estado`, `workflow`

Una vez agregada, el módulo aparece en la lista del Workflow Manager.

---

## Configurar un workflow desde el CMS

### Paso 1 — Abrir el Workflow Manager

**CMS → Admins → Workflow Manager** (o la URL que tengas configurada).

### Paso 2 — Seleccionar el módulo

En el panel izquierdo aparecen todos los módulos que tienen una columna de tipo `workflow`. Clic en el módulo que quieras configurar.

### Paso 3 — Definir los estados

Cada estado tiene:
- **ID**: identificador interno en minúsculas sin espacios (ej: `draft`, `pending`, `approved`)
- **Etiqueta**: texto visible al usuario (ej: `Borrador`, `Pendiente`, `Aprobado`)
- **Color**: color del badge que muestra el estado

**Estados por defecto sugeridos:**

| ID | Etiqueta | Color |
|---|---|---|
| `draft` | Borrador | `#6c757d` (gris) |
| `pending` | Pendiente | `#ffc107` (amarillo) |
| `approved` | Aprobado | `#28a745` (verde) |
| `rejected` | Rechazado | `#dc3545` (rojo) |

### Paso 4 — Definir las transiciones

Cada transición define un cambio de estado posible:
- **Desde**: estado(s) de origen (puede ser uno o varios)
- **Hacia**: estado de destino
- **Etiqueta**: texto del botón que verá el usuario (ej: `Enviar a revisión`)
- **Roles permitidos**: qué roles de admin pueden ejecutar esta transición (`*` = todos)
- **Requiere comentario**: si el usuario debe escribir un motivo al transitar

### Paso 5 — Configuración general

- **Estado inicial**: estado que se asigna al crear un registro nuevo
- **Registrar transiciones**: guarda un historial de cada cambio de estado

### Paso 6 — Guardar

Clic en **Guardar Workflow**. La configuración se almacena en la tabla `workflows`.

---

## Tabla en base de datos

La tabla `workflows` se crea automáticamente.

| Columna | Tipo | Descripción |
|---|---|---|
| `id_workflow` | INT PK | Autoincremental |
| `id_module_workflow` | INT UNIQUE | FK al módulo (id_module) |
| `title_workflow` | VARCHAR(255) | Título descriptivo |
| `states_workflow` | TEXT | JSON con definición de estados |
| `transitions_workflow` | TEXT | JSON con definición de transiciones |
| `settings_workflow` | TEXT | JSON con configuración general |
| `date_created_workflow` | DATE | Fecha de creación |
| `date_updated_workflow` | TIMESTAMP | Última actualización |

### Estructura del JSON `states_workflow`

```json
[
    { "id": "draft",    "label": "Borrador",  "color": "#6c757d" },
    { "id": "pending",  "label": "Pendiente", "color": "#ffc107" },
    { "id": "approved", "label": "Aprobado",  "color": "#28a745" },
    { "id": "rejected", "label": "Rechazado", "color": "#dc3545" }
]
```

### Estructura del JSON `transitions_workflow`

```json
[
    {
        "id":               "submit",
        "from":             ["draft"],
        "to":               "pending",
        "label":            "Enviar a revisión",
        "roles":            ["*"],
        "require_comment":  false
    },
    {
        "id":               "approve",
        "from":             ["pending"],
        "to":               "approved",
        "label":            "Aprobar",
        "roles":            ["superadmin", "admin"],
        "require_comment":  false
    },
    {
        "id":               "reject",
        "from":             ["pending"],
        "to":               "rejected",
        "label":            "Rechazar",
        "roles":            ["superadmin", "admin"],
        "require_comment":  true
    }
]
```

### Estructura del JSON `settings_workflow`

```json
{
    "initial_state":    "draft",
    "log_transitions":  true
}
```

---

## Cómo funciona el workflow en los registros

Cuando un módulo tiene columna `workflow`, el CMS muestra automáticamente:
1. El estado actual del registro como un badge de color
2. Los botones de transición disponibles según el rol del admin

El usuario solo ve las transiciones permitidas desde el estado actual y para su rol. Si una transición requiere comentario, aparece un campo de texto obligatorio antes de confirmar.

---

## Usar el workflow desde el controlador PHP

El framework incluye `workflow.controller.php` en `cms/controllers/` que maneja las transiciones.

### Verificar si una transición es permitida

```php
require_once __DIR__ . '/workflow.controller.php';

$workflowCtrl = new WorkflowController();

$canTransit = $workflowCtrl->canTransition(
    $moduleId,      // ID del módulo
    $recordId,      // ID del registro
    'approve',      // ID de la transición a ejecutar
    $adminRole      // Rol del admin actual
);

if ($canTransit) {
    // Proceder con la transición
}
```

### Ejecutar una transición

```php
$result = $workflowCtrl->executeTransition(
    $moduleId,
    $recordId,
    'approve',          // ID de la transición
    $adminRole,
    'Aprobado por revisión manual'  // Comentario (opcional/requerido según config)
);

if ($result['success']) {
    echo 'Estado actualizado a: ' . $result['new_state'];
} else {
    echo 'Error: ' . $result['error'];
}
```

### Obtener el estado actual de un registro

```php
$state = $workflowCtrl->getCurrentState($moduleId, $recordId);
// Retorna el ID del estado actual (ej: 'pending')
```

### Obtener las transiciones disponibles para el estado actual

```php
$transitions = $workflowCtrl->getAvailableTransitions(
    $moduleId,
    $currentState,
    $adminRole
);
// Retorna array de objetos con las transiciones permitidas
```

---

## Ejemplo de workflow completo: módulo "Solicitudes"

**Escenario:** Los editores crean solicitudes, los admins las aprueban o rechazan.

### 1. Crear el módulo "Solicitudes" en el CMS

Columnas sugeridas:
- `titulo_solicitud` (text)
- `descripcion_solicitud` (textarea)
- `status_solicitud` (workflow) ← esta activa el Workflow Manager

### 2. Configurar el workflow

**Estados:**

| ID | Etiqueta | Color |
|---|---|---|
| `nueva` | Nueva | `#007bff` |
| `en_revision` | En Revisión | `#ffc107` |
| `aprobada` | Aprobada | `#28a745` |
| `rechazada` | Rechazada | `#dc3545` |

**Transiciones:**

| ID | Desde | Hacia | Roles | Comentario |
|---|---|---|---|---|
| `enviar` | nueva | en_revision | * | No |
| `aprobar` | en_revision | aprobada | admin, superadmin | No |
| `rechazar` | en_revision | rechazada | admin, superadmin | Sí |
| `reabrir` | rechazada | nueva | * | No |

**Estado inicial:** `nueva`

### 3. Resultado

- Un editor crea una solicitud → estado automático: `Nueva`
- El editor puede clicar **Enviar a Revisión** → estado: `En Revisión`
- El admin ve el botón **Aprobar** o **Rechazar**
- Si rechaza, debe escribir el motivo
- El editor puede reabrir solicitudes rechazadas

---

## AJAX endpoints

El plugin expone estos endpoints en `ajax.php` (solo accesible para `superadmin`):

| Acción | Descripción |
|---|---|
| `get_modules` | Lista módulos con columna de tipo workflow |
| `get_workflow` | Obtiene la configuración de un módulo |
| `save_workflow` | Guarda estados, transiciones y settings |
| `get_roles` | Lista los roles disponibles |

---

## Preguntas frecuentes

**¿Puedo tener más de un workflow por módulo?**
No, actualmente cada módulo soporta un único workflow (relación 1:1 por `id_module_workflow`).

**¿Qué pasa si el registro no tiene estado asignado?**
Se muestra como sin estado. El CMS no asigna el estado inicial automáticamente al crear — debe hacerse desde el controlador o configurar el campo con un valor por defecto igual al `initial_state`.

**¿Se puede usar `*` para que todos los roles hagan una transición?**
Sí. En el campo roles, `*` significa "cualquier rol", incluyendo superadmin, admin y editor.

**¿Los estados se guardan como texto o como ID?**
Se guarda el **ID** del estado (ej: `approved`) en el campo del registro. La etiqueta y el color se obtienen en tiempo real del JSON de configuración del workflow.
