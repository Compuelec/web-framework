# Sistema de Actualizaciones del Framework

Este documento explica cómo funciona el sistema de actualizaciones del framework, similar al sistema de actualizaciones de WordPress.

## 📋 Descripción General

El sistema de actualizaciones permite:
- ✅ Verificar automáticamente si hay nuevas versiones disponibles
- ✅ Notificar a los administradores cuando hay actualizaciones
- ✅ Instalar actualizaciones de forma segura con respaldo automático
- ✅ Ejecutar migraciones de base de datos automáticamente
- ✅ Mantener un historial de todas las actualizaciones realizadas

## 🔧 Configuración

### 1. Configurar el Servidor de Actualizaciones

Edita `cms/config.php` y agrega la configuración de actualizaciones:

```php
'updates' => [
    // URL del servidor de actualizaciones
    'server_url' => 'https://updates.tu-framework.com/api/check',
    
    // Habilitar verificación automática
    'auto_check' => true,
    
    // Intervalo de verificación en horas
    'check_interval' => 24
]
```

### 2. Archivo de Información de Actualizaciones Local

Para desarrollo o si no tienes un servidor remoto, puedes usar un archivo local:

**Archivo:** `updates/update-info.json`

```json
{
  "latest_version": "1.0.1",
  "update_available": true,
  "release_date": "2024-01-15",
  "changelog": {
    "1.0.1": {
      "version": "1.0.1",
      "release_date": "2024-01-15",
      "type": "patch",
      "changes": [
        "Fixed security vulnerability",
        "Improved performance"
      ],
      "breaking_changes": false,
      "requires_migration": false
    }
  },
  "download_url": "https://updates.tu-framework.com/downloads/1.0.1.zip",
  "checksum": "sha256:abc123...",
  "min_php_version": "7.4",
  "min_mysql_version": "5.7"
}
```

## 🚀 Uso del Sistema

### Verificar Actualizaciones Manualmente

1. Accede al CMS como administrador
2. Ve a la página "Actualizaciones" (debe estar creada en el sistema de páginas)
3. Haz clic en "Verificar Actualizaciones"

### Instalar una Actualización

1. Si hay una actualización disponible, verás una alerta
2. Revisa los cambios en el changelog
3. Haz clic en "Instalar Actualización"
4. El sistema automáticamente:
   - Creará un respaldo de la base de datos
   - Creará un respaldo de archivos críticos (config.php, VERSION)
   - Ejecutará las migraciones de base de datos necesarias
   - Actualizará los archivos del framework
   - Actualizará el número de versión

### Notificaciones

Los administradores verán una notificación en el navbar cuando hay actualizaciones disponibles.

## 📦 Estructura de Versiones

El framework usa [Semantic Versioning](https://semver.org/):

- **MAJOR.MINOR.PATCH** (ej: 1.0.0)
- **MAJOR**: Cambios incompatibles con versiones anteriores
- **MINOR**: Nuevas funcionalidades compatibles hacia atrás
- **PATCH**: Correcciones de bugs compatibles

## 🔄 Migraciones de Base de Datos

### Crear una Migración

1. Crea un archivo en `migrations/` con el formato:
   ```
   {version_desde}_to_{version_hasta}.sql
   ```
   
   Ejemplo: `1.0.0_to_1.0.1.sql`

2. Escribe las sentencias SQL necesarias:

```sql
-- Ejemplo: Agregar una nueva tabla
CREATE TABLE IF NOT EXISTS nueva_tabla (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ejemplo: Agregar una columna
ALTER TABLE tabla_existente 
ADD COLUMN nueva_columna VARCHAR(100) AFTER columna_existente;
```

3. Las migraciones se ejecutan automáticamente durante la actualización

### Buenas Prácticas para Migraciones

- ✅ Siempre usa `IF NOT EXISTS` o `IF EXISTS`
- ✅ No elimines datos sin documentarlo claramente
- ✅ Prueba las migraciones en desarrollo primero
- ✅ Documenta los cambios con comentarios SQL

## 🔐 Seguridad

### Respaldo Automático

Antes de cada actualización, el sistema crea automáticamente:

1. **Respaldo de Base de Datos**: Archivo SQL en `backups/backup_YYYY-MM-DD_HH-MM-SS/database.sql`
2. **Respaldo de Archivos Críticos**:
   - `api/config.php`
   - `cms/config.php`
   - `VERSION`

### Restaurar desde Respaldo

Si algo sale mal durante una actualización:

1. Restaura la base de datos desde el archivo SQL en `backups/`
2. Restaura los archivos `config.php` desde el respaldo
3. Restaura el archivo `VERSION` si es necesario

## 🌐 Servidor de Actualizaciones (Opcional)

Si quieres crear tu propio servidor de actualizaciones, debe responder a peticiones POST con:

**Request:**
```json
{
  "current_version": "1.0.0",
  "framework": "web-framework"
}
```

**Response:**
```json
{
  "latest_version": "1.0.1",
  "update_available": true,
  "changelog": {
    "1.0.1": {
      "version": "1.0.1",
      "release_date": "2024-01-15",
      "type": "patch",
      "changes": ["..."]
    }
  },
  "download_url": "https://...",
  "checksum": "sha256:..."
}
```

## 📝 Historial de Actualizaciones

El sistema mantiene un historial en la tabla `framework_updates` con:
- Versión anterior
- Versión nueva
- Estado (completed, failed, completed_with_warnings)
- Fecha y hora
- Notas

Puedes ver el historial en la página de Actualizaciones del CMS.

## ⚠️ Notas Importantes

1. **Siempre haz respaldo manual antes de actualizar en producción**
2. **Prueba las actualizaciones en desarrollo primero**
3. **Las actualizaciones mayores pueden tener cambios incompatibles**
4. **Revisa el changelog antes de instalar**
5. **Mantén tus archivos `config.php` seguros** (no se sobrescriben durante actualizaciones)

## 🔧 Personalización

### Agregar la Página de Actualizaciones al Menú

1. En el CMS, ve a "Páginas"
2. Crea una nueva página:
   - **Título**: "Actualizaciones"
   - **URL**: `updates`
   - **Tipo**: "Personalizada"
   - **Icono**: `bi-arrow-repeat`

3. El sistema cargará automáticamente `cms/views/pages/custom/updates/updates.php`

### Verificación Automática

Puedes agregar verificación automática en el inicio del CMS editando `cms/index.php` o `cms/views/template.php`:

```php
// Solo para superadmin y admin
if (isset($_SESSION["admin"]) && 
    ($_SESSION["admin"]->rol_admin == "superadmin" || 
     $_SESSION["admin"]->rol_admin == "admin")) {
    
    require_once __DIR__ . '/controllers/updates.controller.php';
    $updateCheck = UpdatesController::checkForUpdates();
    
    // Guardar en sesión para mostrar notificación
    if ($updateCheck['update_available']) {
        $_SESSION['update_available'] = true;
        $_SESSION['update_version'] = $updateCheck['latest_version'];
    }
}
```

## 🐛 Solución de Problemas

### Error: "Unable to connect to update server"

- Verifica que la URL del servidor sea correcta
- Verifica tu conexión a internet
- Usa el archivo local `updates/update-info.json` como alternativa

### Error: "Failed to create backup"

- Verifica permisos de escritura en `backups/`
- Verifica que `mysqldump` esté disponible en el servidor
- Verifica las credenciales de base de datos

### Error durante migración

- Revisa los logs del servidor
- Verifica la sintaxis SQL de la migración
- Restaura desde el respaldo si es necesario

## 📚 Archivos Relacionados

- `VERSION` - Versión actual del framework
- `core/version.php` - Clase de gestión de versiones
- `cms/controllers/updates.controller.php` - Controlador de actualizaciones
- `cms/ajax/updates.ajax.php` - Endpoint AJAX
- `cms/views/pages/custom/updates/updates.php` - Interfaz de usuario
- `migrations/` - Archivos de migración SQL
- `backups/` - Respaldos automáticos
- `updates/update-info.json` - Información de actualizaciones (local)

---

**Desarrollado para mantener el framework actualizado de forma segura y sencilla**
