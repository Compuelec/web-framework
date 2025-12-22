# 📦 Sistema de Empaquetado e Instalación

Este sistema permite empaquetar todo el proyecto para desplegarlo en un servidor de producción, con un instalador automático que detecta el dominio y actualiza todas las configuraciones.

## 🚀 Uso del Sistema de Empaquetado

### 1. Crear el Paquete

Ejecuta el script de empaquetado desde la línea de comandos:

```bash
php package.php
```

Este script:
- ✅ Excluye archivos sensibles (config.php, backups, logs, etc.)
- ✅ Incluye archivos de ejemplo de configuración
- ✅ Crea un archivo ZIP en el directorio `packages/`
- ✅ Genera un archivo `INSTALL.md` con instrucciones

### 2. Subir a Servidor

1. Sube el archivo ZIP a tu servidor
2. Descomprime el archivo en el directorio web (ej: `public_html`, `www`, etc.)
3. Asegúrate de que PHP tenga permisos de escritura en:
   - `cms/config.php` (se creará automáticamente)
   - `api/config.php` (se creará automáticamente)
   - Directorios de archivos subidos

### 3. Instalación Automática

1. Accede a: `http://tu-dominio.com/cms/install`
2. El sistema detectará automáticamente:
   - ✅ Tu dominio actual
   - ✅ La URL base del proyecto
   - ✅ Las URLs de CMS y API

3. Completa el formulario de instalación:
   - **Configuración de Base de Datos**: Host, nombre, usuario, contraseña
   - **Información del Administrador**: Email y contraseña
   - **Configuración del Dashboard**: Nombre, icono, color, tipografía

4. El instalador automáticamente:
   - ✅ Actualiza las rutas de `localhost` a tu dominio
   - ✅ Genera nuevas API keys y JWT secrets
   - ✅ Crea los archivos `config.php` necesarios
   - ✅ Crea las tablas de la base de datos
   - ✅ Crea el usuario administrador

## 🔧 Características del Sistema

### Detección Automática de Dominio

El sistema detecta automáticamente:
- Protocolo (http/https)
- Host/Dominio
- Ruta base del proyecto
- URLs completas de CMS y API

### Actualización de Configuraciones

El sistema actualiza automáticamente:
- `cms/config.php`: Configuración del CMS con nuevas rutas
- `api/config.php`: Configuración de la API con nuevas rutas
- Genera nuevas claves de seguridad (API keys, JWT secrets, password salts)

### Archivos Excluidos del Paquete

El script de empaquetado excluye automáticamente:
- Archivos de configuración sensibles (`config.php`)
- Directorios de dependencias (`vendor/`, `node_modules/`)
- Archivos de respaldo (`backups/`, `*.bak`, `*.backup`)
- Archivos temporales y logs (`*.log`, `*.tmp`)
- Archivos del sistema (`.DS_Store`, `Thumbs.db`)
- Archivos de control de versiones (`.git/`, `.cursor/`)

### Archivos Incluidos

Se incluyen:
- Todos los archivos fuente del proyecto
- Archivos de ejemplo de configuración (`config.example.php`)
- Scripts y controladores
- Vistas y assets
- Documentación

## 📝 Notas Importantes

1. **Permisos de Escritura**: Asegúrate de que PHP tenga permisos de escritura en los directorios necesarios antes de instalar.

2. **Base de Datos**: La base de datos debe existir o el usuario debe tener permisos para crearla.

3. **PHP Version**: Requiere PHP 7.4 o superior.

4. **Extensiones PHP**: Asegúrate de tener habilitadas:
   - `pdo_mysql`
   - `zip` (para el script de empaquetado)
   - `curl` (para peticiones API)

5. **Seguridad**: Después de la instalación, verifica que los archivos `config.php` tengan permisos adecuados (no accesibles públicamente).

## 🔄 Actualización Manual de Rutas (Opcional)

Si necesitas actualizar las rutas manualmente después de la instalación, puedes usar el controlador `PathUpdaterController`:

```php
require_once 'cms/controllers/path-updater.controller.php';

// Detectar dominio
$domainInfo = PathUpdaterController::detectDomain();

// Actualizar configuración del CMS
// NOTA: Los valores de base de datos deben venir de tu configuración, no estar hardcodeados
$dbConfig = [
    'host' => 'tu_host',      // Ejemplo: 'localhost' o '127.0.0.1'
    'name' => 'tu_database',   // Ejemplo: 'mi_base_datos'
    'user' => 'tu_usuario',    // Ejemplo: 'root' o tu usuario de BD
    'pass' => 'tu_password'     // Tu contraseña de base de datos
];
PathUpdaterController::updateCmsConfig($domainInfo, $dbConfig);

// Actualizar configuración de la API
PathUpdaterController::updateApiConfig($dbConfig);
```

## 🐛 Solución de Problemas

### Error: "No se pudo escribir el archivo de configuración"
- Verifica los permisos de escritura en los directorios `cms/` y `api/`
- Asegúrate de que los archivos `config.example.php` existan

### Error: "No se pudo conectar a la base de datos"
- Verifica las credenciales de la base de datos
- Asegúrate de que la base de datos exista
- Verifica que el usuario tenga permisos suficientes

### Las rutas no se actualizan correctamente
- Verifica que el servidor esté configurado correctamente
- Asegúrate de que `$_SERVER['HTTP_HOST']` esté disponible
- Revisa los logs del servidor para más detalles

## 📞 Soporte

Si encuentras problemas durante la instalación o el empaquetado, verifica:
1. Los logs del servidor
2. Los permisos de archivos y directorios
3. La configuración de PHP
4. La conectividad con la base de datos

