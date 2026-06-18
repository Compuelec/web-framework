# Uso de la API

La API funciona de forma **dinámica** sobre cualquier tabla: no necesitas
definir rutas ni modelos.

## Autenticación

Todas las peticiones (salvo tablas de acceso público) requieren el header:

```http
Authorization: tu-api-key-aqui
```

## Endpoints

### GET — obtener datos

```http
GET /api/{tabla}                                # todos los registros
GET /api/{tabla}?linkTo=columna&equalTo=valor   # filtrar por columna
GET /api/{tabla}?rel=tabla1,tabla2              # incluir relaciones (JOIN)
GET /api/{tabla}?search=columna:valor          # buscar
GET /api/{tabla}?orderBy=columna&orderMode=ASC # ordenar
```

### POST — crear

```http
POST /api/{tabla}
Content-Type: application/x-www-form-urlencoded

campo1=valor1&campo2=valor2
```

### PUT — actualizar

```http
PUT /api/{tabla}?id=123&nameId=id_tabla

campo1=nuevo_valor1
```

### DELETE — eliminar

```http
DELETE /api/{tabla}?id=123&nameId=id_tabla
```

## Ejemplo (JavaScript)

```javascript
// Obtener todos los productos
fetch('http://localhost/tu-proyecto/api/products', {
  headers: { 'Authorization': 'tu-api-key' }
})
  .then(r => r.json())
  .then(data => console.log(data));

// Crear un producto
fetch('http://localhost/tu-proyecto/api/products', {
  method: 'POST',
  headers: {
    'Authorization': 'tu-api-key',
    'Content-Type': 'application/x-www-form-urlencoded'
  },
  body: 'name_product=Producto 1&price_product=99.99&stock_product=100'
})
  .then(r => r.json())
  .then(data => console.log(data));
```

## Desde PHP (frontend público)

El frontend usa `web/controllers/api.controller.php`, que lee `web/config.php`
(API base_url + key):

```php
ApiController::getAll('products', '*', 'id_product', 'DESC', 0, 10);
ApiController::getById('products', 5, 'id_product');
ApiController::getByFilter('products', 'status_product', 'active');
ApiController::create('products', ['name_product' => 'X']);
ApiController::update('products', 5, ['name_product' => 'Y'], 'id_product');
ApiController::delete('products', 5, 'id_product');
```
