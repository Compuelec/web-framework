# Web - Estructura Base para CMS Dinámico

Esta carpeta contiene una estructura base para crear un sitio web público que consume datos de las tablas dinámicas del CMS a través de la API REST.

## 🚀 Inicio Rápido

### 1. Configuración

Copia `config.example.php` a `config.php` y completa los valores:

```php
'api' => [
    'base_url' => 'http://localhost/web-framework/api/',
    'key' => 'your-api-key-here'  // La misma API key de api/config.php
]
```

### 2. Ver Ejemplos

- **Home:** `http://localhost/web-framework/web/`
- **Ejemplo Tabla:** `http://localhost/web-framework/web/pages/example-table.php`
- **Ejemplo Lista:** `http://localhost/web-framework/web/pages/example-list.php`
- **Ejemplo Detalle:** `http://localhost/web-framework/web/pages/example-detail.php`

## 📁 Estructura

```
web/
├── config.php              # Configuración (crear desde config.example.php)
├── config.example.php      # Plantilla de configuración
├── index.php              # Página principal
├── controllers/
│   └── api.controller.php # Controlador para hacer requests a la API
├── views/
│   ├── template.php       # Template base HTML
│   └── assets/
│       ├── css/
│       │   └── style.css  # Estilos personalizados
│       └── js/
│           └── main.js     # JavaScript personalizado
└── pages/
    ├── example-table.php  # Ejemplo: Tabla de datos
    ├── example-list.php   # Ejemplo: Lista de datos
    └── example-detail.php # Ejemplo: Detalle de registro
```

## 📖 Uso Básico

### Obtener todos los registros

```php
require_once __DIR__ . '/controllers/api.controller.php';

$response = ApiController::getAll('table_name', '*', 'id_table', 'DESC', 0, 10);

if ($response->status == 200) {
    $data = $response->results;
    foreach ($data as $record) {
        // Mostrar datos...
    }
}
```

### Obtener un registro por ID

```php
$response = ApiController::getById('table_name', $recordId, 'id_table');

if ($response->status == 200 && !empty($response->results)) {
    $record = $response->results[0];
}
```

### Buscar registros

```php
$response = ApiController::search('table_name', 'title_column', 'search term');
```

## 📚 Documentación Completa

Para documentación detallada, consulta:
- **Guía Completa:** `.cursor/docs/WEB_GUIDE.md`
- **Ejemplos:** Revisa los archivos en `web/pages/example-*.php`

## 🔧 Métodos Disponibles del ApiController

- `getAll()` - Obtener todos los registros
- `getByFilter()` - Obtener registros filtrados
- `getById()` - Obtener un registro por ID
- `search()` - Buscar registros
- `getByRange()` - Obtener registros en un rango
- `create()` - Crear un nuevo registro
- `update()` - Actualizar un registro
- `delete()` - Eliminar un registro

## ⚠️ Importante

- **Nunca versiones `config.php`** (está en `.gitignore`)
- La API key debe ser la misma que en `api/config.php`
- Verifica que la URL de la API sea correcta

## 🆘 Troubleshooting

Si tienes problemas:
1. Verifica la configuración en `config.php`
2. Revisa los logs en `web/php_error_log`
3. Consulta la documentación en `.cursor/docs/WEB_GUIDE.md`

