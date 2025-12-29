# Plugin Payku - Guía de Implementación para Desarrolladores

Esta guía explica cómo implementar el plugin Payku en tus aplicaciones para procesar pagos online.

## 📋 Índice

1. [Configuración Inicial](#configuración-inicial)
2. [Procesar un Pago](#procesar-un-pago)
3. [Consultar Estado de Orden](#consultar-estado-de-orden)
4. [Múltiples Productos (Carrito)](#múltiples-productos-carrito)
5. [Manejo de Respuestas](#manejo-de-respuestas)
6. [Webhooks y Notificaciones](#webhooks-y-notificaciones)
7. [Ejemplos de Código](#ejemplos-de-código)

---

## 🔧 Configuración Inicial

### Requisitos Previos

1. El plugin debe estar instalado en `plugins/payku/`
2. Debe estar configurado desde el CMS en `/cms/payku`
3. Debes tener tu API Key del sistema (en `api/config.php`)

### Configuración del Plugin

El plugin se configura desde el CMS. Una vez configurado, está listo para usar desde la API.

---

## 💳 Procesar un Pago

### Endpoint

```
POST /api/payku
```

### Headers Requeridos

```
Authorization: tu-api-key
Content-Type: application/json
```

### Request Body

```json
{
  "order_id": "ORD-001",
  "email": "cliente@ejemplo.com",
  "amount": 10000,
  "currency": "CLP",
  "products": [
    {
      "quantity": 1,
      "name": "Producto ejemplo"
    }
  ]
}
```

### Parámetros

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `order_id` | string | Sí | ID único de la orden (máx. 255 caracteres, alfanumérico) |
| `email` | string | Sí | Email del cliente |
| `amount` | integer | Sí | Monto total en CLP (sin decimales) |
| `currency` | string | Sí | Moneda (solo "CLP" soportado) |
| `products` | array | Sí | Array de productos (ver sección de múltiples productos) |

### Respuesta Exitosa

```json
{
  "status": 200,
  "results": {
    "redirect_url": "https://app.payku.cl/...",
    "order_id": "ORD-001"
  }
}
```

### Manejo de Errores

```json
{
  "status": 400,
  "message": "Error description"
}
```

### Ejemplo de Implementación

#### JavaScript (Fetch API)

```javascript
async function procesarPago(orderData) {
  try {
    const response = await fetch('/api/payku', {
      method: 'POST',
      headers: {
        'Authorization': 'tu-api-key',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(orderData)
    });
    
    const data = await response.json();
    
    if (data.status === 200) {
      // Redirigir al usuario a Payku
      window.location.href = data.results.redirect_url;
    } else {
      console.error('Error:', data.message);
      alert('Error al procesar el pago: ' + data.message);
    }
  } catch (error) {
    console.error('Error de red:', error);
    alert('Error de conexión. Por favor, intenta nuevamente.');
  }
}

// Uso
procesarPago({
  order_id: 'ORD-' + Date.now(),
  email: 'cliente@ejemplo.com',
  amount: 10000,
  currency: 'CLP',
  products: [
    { quantity: 1, name: 'Producto ejemplo' }
  ]
});
```

#### PHP (cURL)

```php
<?php
function procesarPago($orderData, $apiKey) {
    $url = 'http://localhost/web-framework/api/payku';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data['status'] === 200) {
            // Redirigir al usuario
            header('Location: ' . $data['results']['redirect_url']);
            exit;
        }
    }
    
    return json_decode($response, true);
}

// Uso
$resultado = procesarPago([
    'order_id' => 'ORD-' . time(),
    'email' => 'cliente@ejemplo.com',
    'amount' => 10000,
    'currency' => 'CLP',
    'products' => [
        ['quantity' => 1, 'name' => 'Producto ejemplo']
    ]
], 'tu-api-key');
```

---

## 🔍 Consultar Estado de Orden

### Endpoint

```
GET /api/payku?order_id=ORD-001
```

### Headers Requeridos

```
Authorization: tu-api-key
```

### Respuesta Exitosa

```json
{
  "status": 200,
  "results": {
    "id_order": 1,
    "order_id": "ORD-001",
    "email": "cliente@ejemplo.com",
    "amount": "10000.00",
    "currency": "CLP",
    "status": "completed",
    "transaction_id": "9917670438143953",
    "payment_key": "trxa431b40c2da48b583",
    "transaction_key": "trxa431b40c2da48b583",
    "verification_key": "79cbd06de3dd60723babff8e3c131b21",
    "date_created": "2024-01-01 12:00:00",
    "date_updated": "2024-01-01 12:05:00"
  }
}
```

### Estados Posibles

- `pending`: Pago pendiente
- `completed`: Pago completado exitosamente
- `failed`: Pago fallido o rechazado
- `cancelled`: Pago cancelado

### Ejemplo de Implementación

```javascript
async function consultarEstado(orderId) {
  try {
    const response = await fetch(`/api/payku?order_id=${orderId}`, {
      headers: {
        'Authorization': 'tu-api-key'
      }
    });
    
    const data = await response.json();
    
    if (data.status === 200) {
      return data.results;
    }
    
    return null;
  } catch (error) {
    console.error('Error:', error);
    return null;
  }
}

// Uso
const orden = await consultarEstado('ORD-001');
if (orden) {
  console.log('Estado:', orden.status);
  console.log('Monto:', orden.amount);
}
```

---

## 🛒 Múltiples Productos (Carrito)

El plugin soporta múltiples productos en una sola orden. El formato del `subject` en Payku será: `"2 x Producto 1 - 1 x Producto 2"`.

### Ejemplo con Múltiples Productos

```json
{
  "order_id": "ORD-002",
  "email": "cliente@ejemplo.com",
  "amount": 25000,
  "currency": "CLP",
  "products": [
    {
      "quantity": 2,
      "name": "Producto A"
    },
    {
      "quantity": 1,
      "name": "Producto B"
    },
    {
      "quantity": 3,
      "name": "Producto C"
    }
  ]
}
```

**Nota**: El `amount` debe ser la suma total de todos los productos (cantidad × precio unitario).

### Ejemplo de Cálculo de Total

```javascript
function calcularTotal(productos) {
  return productos.reduce((total, producto) => {
    return total + (producto.quantity * producto.price);
  }, 0);
}

const productos = [
  { quantity: 2, name: 'Producto A', price: 5000 },
  { quantity: 1, name: 'Producto B', price: 10000 },
  { quantity: 3, name: 'Producto C', price: 2000 }
];

const total = calcularTotal(productos); // 25000

// Preparar datos para Payku
const orderData = {
  order_id: 'ORD-' + Date.now(),
  email: 'cliente@ejemplo.com',
  amount: total,
  currency: 'CLP',
  products: productos.map(p => ({
    quantity: p.quantity,
    name: p.name
  }))
};
```

---

## 📥 Manejo de Respuestas

### Flujo Completo de Pago

1. **Cliente inicia pago**: Tu aplicación llama a `/api/payku`
2. **Redirección**: Rediriges al cliente a `redirect_url`
3. **Cliente paga**: El cliente completa el pago en Payku
4. **Retorno**: Payku redirige al cliente a `result-payku.php`
5. **Webhook**: Payku notifica al sistema vía webhook (asíncrono)
6. **Verificación**: Tu aplicación puede consultar el estado

### Página de Resultado

Después del pago, Payku redirige a:
```
/plugins/payku/result-payku.php?order_id=ORD-001
```

Esta página muestra el estado del pago y tiene un botón "Volver" que redirige a `/web-framework/web/`.

### Verificar Estado Después del Pago

```javascript
// Después de que el usuario regresa de Payku
const urlParams = new URLSearchParams(window.location.search);
const orderId = urlParams.get('order_id');

if (orderId) {
  // Consultar estado inmediatamente
  consultarEstado(orderId).then(orden => {
    if (orden) {
      if (orden.status === 'completed') {
        // Pago exitoso
        mostrarMensajeExito();
      } else if (orden.status === 'failed') {
        // Pago fallido
        mostrarMensajeError();
      } else {
        // Pendiente - consultar nuevamente después de unos segundos
        setTimeout(() => consultarEstado(orderId), 3000);
      }
    }
  });
}
```

---

## 🔔 Webhooks y Notificaciones

El plugin maneja automáticamente los webhooks de Payku. No necesitas hacer nada adicional, pero es importante entender cómo funcionan.

### URL del Webhook

```
/plugins/payku/webhook-payku.php
```

### Configuración en Payku

Debes configurar esta URL en tu cuenta de Payku:
- Panel de desarrollo: https://des.payku.cl
- Panel de producción: https://app.payku.cl

### Qué Hace el Webhook

1. Recibe notificaciones de Payku cuando cambia el estado de un pago
2. Verifica la transacción con la API de Payku
3. Actualiza el estado en la base de datos
4. Guarda todos los datos de la transacción

### Verificación Manual

Si el webhook no está disponible (ej: localhost), el sistema intenta verificar el estado manualmente cuando el usuario regresa de Payku.

---

## 💻 Ejemplos de Código Completos

### Ejemplo 1: Procesar Pago Simple

```javascript
// HTML
<button onclick="procesarPago()">Pagar $10.000</button>

// JavaScript
async function procesarPago() {
  const orderData = {
    order_id: 'ORD-' + Date.now(),
    email: document.getElementById('email').value,
    amount: 10000,
    currency: 'CLP',
    products: [
      { quantity: 1, name: 'Producto de Ejemplo' }
    ]
  };
  
  try {
    const response = await fetch('/api/payku', {
      method: 'POST',
      headers: {
        'Authorization': 'tu-api-key',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(orderData)
    });
    
    const data = await response.json();
    
    if (data.status === 200) {
      window.location.href = data.results.redirect_url;
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    alert('Error de conexión');
  }
}
```

### Ejemplo 2: Carrito de Compras Completo

```javascript
class CarritoPayku {
  constructor(apiKey) {
    this.apiKey = apiKey;
    this.productos = [];
  }
  
  agregarProducto(producto) {
    this.productos.push(producto);
  }
  
  calcularTotal() {
    return this.productos.reduce((total, p) => {
      return total + (p.quantity * p.price);
    }, 0);
  }
  
  async procesarPago(email) {
    const orderData = {
      order_id: 'ORD-' + Date.now(),
      email: email,
      amount: this.calcularTotal(),
      currency: 'CLP',
      products: this.productos.map(p => ({
        quantity: p.quantity,
        name: p.name
      }))
    };
    
    const response = await fetch('/api/payku', {
      method: 'POST',
      headers: {
        'Authorization': this.apiKey,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(orderData)
    });
    
    const data = await response.json();
    
    if (data.status === 200) {
      // Guardar order_id en localStorage para verificación posterior
      localStorage.setItem('lastOrderId', orderData.order_id);
      window.location.href = data.results.redirect_url;
    } else {
      throw new Error(data.message || 'Error al procesar el pago');
    }
  }
}

// Uso
const carrito = new CarritoPayku('tu-api-key');
carrito.agregarProducto({ quantity: 2, name: 'Producto A', price: 5000 });
carrito.agregarProducto({ quantity: 1, name: 'Producto B', price: 10000 });

carrito.procesarPago('cliente@ejemplo.com')
  .catch(error => alert('Error: ' + error.message));
```

### Ejemplo 3: Verificar Estado con Polling

```javascript
async function verificarPago(orderId, maxIntentos = 10) {
  for (let i = 0; i < maxIntentos; i++) {
    const orden = await consultarEstado(orderId);
    
    if (orden) {
      if (orden.status === 'completed') {
        return { success: true, orden };
      } else if (orden.status === 'failed') {
        return { success: false, orden };
      }
    }
    
    // Esperar 2 segundos antes del siguiente intento
    await new Promise(resolve => setTimeout(resolve, 2000));
  }
  
  return { success: false, message: 'Timeout' };
}

// Uso
verificarPago('ORD-001').then(resultado => {
  if (resultado.success) {
    console.log('Pago completado:', resultado.orden);
  } else {
    console.log('Pago fallido o pendiente');
  }
});
```

---

## 🔒 Seguridad

### Buenas Prácticas

1. **Nunca expongas tu API Key** en el código del frontend
   - Usa un backend proxy para las llamadas a la API
   - O usa variables de entorno en el servidor

2. **Valida los datos** antes de enviarlos
   - Verifica que el email sea válido
   - Verifica que el monto sea positivo
   - Verifica que el order_id sea único

3. **Usa HTTPS** en producción
   - Todas las comunicaciones deben ser seguras

4. **Verifica el estado** después del pago
   - No confíes solo en la redirección
   - Consulta el estado desde tu servidor

---

## 📚 Recursos Adicionales

- **Documentación Payku**: https://docs.payku.com
- **Panel de Desarrollo**: https://des.payku.cl
- **Panel de Producción**: https://app.payku.cl

---

## 🐛 Solución de Problemas

### Error: "Plugin is not configured"
- Verifica que el plugin esté activado en `/cms/payku`
- Verifica que el token público esté configurado

### Error: "Invalid API key"
- Verifica que estés usando la API key correcta
- Verifica que la API key esté en el header `Authorization`

### El pago se crea pero no redirige
- Verifica que la URL de redirección sea correcta
- Verifica que el token público sea válido
- Revisa la consola del navegador para errores

### El estado no se actualiza
- Verifica que el webhook esté configurado en Payku
- Verifica que el webhook sea accesible públicamente
- Revisa los logs del servidor

---

## 📝 Notas Importantes

- El plugin solo acepta pagos en **CLP** (Pesos Chilenos)
- El `order_id` debe ser único y alfanumérico (puede incluir guiones y guiones bajos)
- El `amount` debe ser un número entero (sin decimales)
- Los productos se formatean automáticamente para Payku
- El sistema maneja automáticamente los webhooks y actualizaciones de estado

