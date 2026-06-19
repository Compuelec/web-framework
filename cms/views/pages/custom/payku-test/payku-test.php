<?php

/**
 * Payku Test Page
 * Accessible from browser to test payments
 */

// Calculate paths
// Current file: cms/views/pages/custom/payku-test/payku-test.php
$cmsDir = dirname(__DIR__, 4);
$projectRoot = dirname($cmsDir);

// Ensure DIR is defined for plugin loading
if (!defined('DIR')) {
    define('DIR', $cmsDir);
}

// Load plugin controller directly
$pluginControllerPath = $projectRoot . '/plugins/payku/controllers/payku.controller.php';
if (!file_exists($pluginControllerPath)) {
    die('Error: No se pudo encontrar el controlador del plugin Payku. Ruta buscada: ' . $pluginControllerPath);
}

// Load plugin controller
require_once $pluginControllerPath;

// Get plugin configuration directly from plugin
$config = PaykuPlugin::getConfig();

// Handle form submission
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_payment'])) {
    // Validate configuration
    if (!$config['enabled']) {
        $error = 'El plugin no está activado. Ve a /payku para activarlo.';
    } elseif (empty(trim($config['token_publico'] ?? ''))) {
        $error = 'El token público no está configurado. Ve a /payku para configurarlo.';
    } else {
        // Prepare test data - handle multiple products
        $products = [];
        $totalAmount = 0;
        
        // Check if products array is sent
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            foreach ($_POST['products'] as $product) {
                if (!empty($product['name']) && !empty($product['quantity']) && !empty($product['price'])) {
                    $quantity = intval($product['quantity']);
                    $price = intval($product['price']);
                    $products[] = [
                        'quantity' => $quantity,
                        'name' => trim($product['name'])
                    ];
                    $totalAmount += $quantity * $price;
                }
            }
        }
        
        // Fallback to single product if no products array
        if (empty($products)) {
            $products = [
                [
                    'quantity' => 1,
                    'name' => $_POST['product_name'] ?? 'Producto de Prueba'
                ]
            ];
            $totalAmount = intval($_POST['amount'] ?? 1000);
        }
        
        // Ensure we have at least one product
        if (empty($products)) {
            $error = 'Debe agregar al menos un producto al carrito.';
        } else {
            $orderData = [
                'order_id' => 'TEST-' . date('YmdHis'),
                'email' => $_POST['email'] ?? 'test@ejemplo.com',
                'amount' => $totalAmount,
                'currency' => 'CLP',
                'products' => $products
            ];
        }
        
        // Process payment if we have valid data
        if (empty($error) && !empty($orderData)) {
            try {
                $result = PaykuPlugin::processPayment($orderData);
                
                if (!$result['success']) {
                    $error = $result['error'] ?? 'Error desconocido al crear el pago';
                    
                    // Include debug information if available
                    if (isset($result['debug'])) {
                        $error .= '<br><br><small><strong>Información de depuración:</strong><br>';
                        $error .= '<pre style="font-size: 11px; max-height: 200px; overflow: auto;">';
                        $error .= htmlspecialchars(print_r($result['debug'], true));
                        $error .= '</pre></small>';
                    }
                }
            } catch (Exception $e) {
                $error = 'Excepción al procesar el pago: ' . $e->getMessage();
                error_log('Payku test page exception: ' . $e->getMessage());
                error_log('Payku test page trace: ' . $e->getTraceAsString());
            }
        }
        
        // Process payment
        try {
            $result = PaykuPlugin::processPayment($orderData);
            
            if (!$result['success']) {
                $error = $result['error'] ?? 'Error desconocido al crear el pago';
                
                // Include debug information if available
                if (isset($result['debug'])) {
                    $error .= '<br><br><small><strong>Información de depuración:</strong><br>';
                    $error .= '<pre style="font-size: 11px; max-height: 200px; overflow: auto;">';
                    $error .= htmlspecialchars(print_r($result['debug'], true));
                    $error .= '</pre></small>';
                }
            }
        } catch (Exception $e) {
            $error = 'Excepción al procesar el pago: ' . $e->getMessage();
            error_log('Payku test page exception: ' . $e->getMessage());
            error_log('Payku test page trace: ' . $e->getTraceAsString());
        }
    }
}

?>

<div class="container-fluid p-4">
    <div class="card rounded shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">
                <i class="bi bi-credit-card mr-2"></i>
                Prueba de Pago Payku
            </h3>
        </div>
        <div class="card-body">
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> 
                    <div><?php echo $error; ?></div>
                    <?php if (isset($result['debug'])): ?>
                        <details class="mt-3">
                            <summary style="cursor: pointer; color: #721c24;"><strong>Ver detalles técnicos</strong></summary>
                            <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px; font-size: 11px; max-height: 300px; overflow: auto;"><?php echo htmlspecialchars(print_r($result['debug'], true)); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($result && $result['success']): ?>
                <div class="alert alert-success">
                    <h5><strong>✅ ¡Pago creado exitosamente!</strong></h5>
                    <p><strong>Order ID:</strong> <?php echo htmlspecialchars($result['order_id'] ?? 'N/A'); ?></p>
                    <p><strong>URL de redirección:</strong></p>
                    <div class="mb-3">
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($result['redirect_url']); ?>" readonly onclick="this.select();">
                    </div>
                    <a href="<?php echo htmlspecialchars($result['redirect_url']); ?>" target="_blank" class="btn btn-primary">
                        <i class="bi bi-box-arrow-up-right"></i> Abrir en nueva pestaña
                    </a>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">💳 Tarjetas de Prueba</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Para probar un pago exitoso, usa:</strong></p>
                        <ul>
                            <li><strong>Visa:</strong> 4051885600446623</li>
                            <li><strong>Mastercard:</strong> 5186059559590888</li>
                            <li><strong>CVV:</strong> 123</li>
                            <li><strong>Fecha:</strong> 12/25 (o cualquier fecha futura)</li>
                            <li><strong>RUT:</strong> 11.111.111-1</li>
                            <li><strong>Clave:</strong> 123456</li>
                        </ul>
                        <p class="mb-0"><small>Para probar rechazo, usa CVV incorrecto (999)</small></p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="?order_id=<?php echo urlencode($result['order_id'] ?? ''); ?>" class="btn btn-secondary">
                        <i class="bi bi-search"></i> Consultar Estado de la Orden
                    </a>
                </div>
            <?php else: ?>
                
                <!-- Configuration Status -->
                <div class="mb-4">
                    <h5>Estado de la Configuración</h5>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Plugin Activado
                            <?php if ($config['enabled']): ?>
                                <span class="badge bg-success">Sí</span>
                            <?php else: ?>
                                <span class="badge bg-danger">No</span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Token Público
                            <?php if (!empty(trim($config['token_publico'] ?? ''))): ?>
                                <span class="badge bg-success">Configurado</span>
                            <?php else: ?>
                                <span class="badge bg-danger">No configurado</span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Plataforma
                            <span class="badge bg-<?php echo $config['platform_id'] === 'TEST' ? 'info' : 'warning'; ?>">
                                <?php echo htmlspecialchars($config['platform_id']); ?>
                            </span>
                        </li>
                    </ul>
                </div>
                
                <?php if (!$config['enabled'] || empty(trim($config['token_publico'] ?? ''))): ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Configuración incompleta</strong>
                        <p class="mb-0">Ve a <a href="/cms/payku">/cms/payku</a> para configurar el plugin antes de probar.</p>
                    </div>
                <?php else: ?>
                    
                    <!-- Test Form with Shopping Cart -->
                    <form method="POST" id="paymentForm">
                        <input type="hidden" name="test_payment" value="1">
                        
                        <div class="form-group mb-3">
                            <label for="email"><strong>Email</strong></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="test@ejemplo.com" required>
                        </div>
                        
                        <!-- Shopping Cart -->
                        <div class="card mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-cart"></i> Carrito de Compras</h5>
                                <button type="button" class="btn btn-sm btn-success" id="addProductBtn">
                                    <i class="bi bi-plus-circle"></i> Agregar Producto
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="productsContainer">
                                    <!-- Products will be added here dynamically -->
                                </div>
                                
                                <div class="mt-3 pt-3 border-top">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Total:</h5>
                                        <h4 class="mb-0 text-primary" id="totalAmount">$0 CLP</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-credit-card"></i> Crear Pago de Prueba
                        </button>
                    </form>
                    
                    <script>
                    // Shopping Cart Management
                    let productCounter = 0;
                    
                    // Add initial product
                    function addProduct(productData = null) {
                        productCounter++;
                        const productId = 'product_' + productCounter;
                        
                        const productHtml = `
                            <div class="product-item mb-3 p-3 border rounded" data-product-id="${productId}">
                                <div class="row align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label small">Nombre del Producto</label>
                                        <input type="text" class="form-control product-name" 
                                               name="products[${productId}][name]" 
                                               placeholder="Ej: Producto de Prueba" 
                                               value="${productData ? productData.name : ''}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Cantidad</label>
                                        <input type="number" class="form-control product-quantity" 
                                               name="products[${productId}][quantity]" 
                                               min="1" value="${productData ? productData.quantity : 1}" 
                                               required onchange="calculateTotal()">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Precio Unitario (CLP)</label>
                                        <input type="number" class="form-control product-price" 
                                               name="products[${productId}][price]" 
                                               min="1" value="${productData ? productData.price : 1000}" 
                                               required onchange="calculateTotal()">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger btn-sm remove-product" 
                                                onclick="removeProduct('${productId}')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <small class="text-muted">
                                            Subtotal: <span class="product-subtotal">$0</span> CLP
                                        </small>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        const container = document.getElementById('productsContainer');
                        container.insertAdjacentHTML('beforeend', productHtml);
                        
                        // Update subtotal for this product
                        updateProductSubtotal(productId);
                        
                        // Calculate total
                        calculateTotal();
                    }
                    
                    function removeProduct(productId) {
                        const productItem = document.querySelector(`[data-product-id="${productId}"]`);
                        if (productItem) {
                            productItem.remove();
                            calculateTotal();
                        }
                    }
                    
                    function updateProductSubtotal(productId) {
                        const productItem = document.querySelector(`[data-product-id="${productId}"]`);
                        if (productItem) {
                            const quantity = parseInt(productItem.querySelector('.product-quantity').value) || 0;
                            const price = parseInt(productItem.querySelector('.product-price').value) || 0;
                            const subtotal = quantity * price;
                            
                            productItem.querySelector('.product-subtotal').textContent = 
                                '$' + subtotal.toLocaleString('es-CL');
                        }
                    }
                    
                    function calculateTotal() {
                        let total = 0;
                        const productItems = document.querySelectorAll('.product-item');
                        
                        productItems.forEach(item => {
                            const quantity = parseInt(item.querySelector('.product-quantity').value) || 0;
                            const price = parseInt(item.querySelector('.product-price').value) || 0;
                            const subtotal = quantity * price;
                            total += subtotal;
                            
                            // Update individual subtotal
                            const productId = item.getAttribute('data-product-id');
                            updateProductSubtotal(productId);
                        });
                        
                        document.getElementById('totalAmount').textContent = 
                            '$' + total.toLocaleString('es-CL') + ' CLP';
                    }
                    
                    // Event listeners
                    document.getElementById('addProductBtn').addEventListener('click', function() {
                        addProduct();
                    });
                    
                    // Calculate total when inputs change
                    document.addEventListener('input', function(e) {
                        if (e.target.classList.contains('product-quantity') || 
                            e.target.classList.contains('product-price')) {
                            calculateTotal();
                        }
                    });
                    
                    // Add first product on page load
                    document.addEventListener('DOMContentLoaded', function() {
                        addProduct({ name: 'Producto de Prueba', quantity: 1, price: 1000 });
                    });
                    </script>
                    
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Check Order Status -->
            <?php if (isset($_GET['order_id'])): ?>
                <?php
                $orderId = $_GET['order_id'];
                // Get order
                $order = PaykuPlugin::getOrder($orderId);
                ?>
                <div class="card mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Estado de la Orden: <?php echo htmlspecialchars($orderId); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($order): ?>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Order ID</th>
                                    <td><?php echo htmlspecialchars($order->order_id); ?></td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td><?php echo htmlspecialchars($order->email); ?></td>
                                </tr>
                                <tr>
                                    <th>Monto</th>
                                    <td>$<?php echo number_format($order->amount, 0, ',', '.'); ?> <?php echo htmlspecialchars($order->currency); ?></td>
                                </tr>
                                <tr>
                                    <th>Estado</th>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                            'pending' => 'warning'
                                        ];
                                        $class = $statusClass[$order->status] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $class; ?>">
                                            <?php echo htmlspecialchars($order->status); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if ($order->transaction_id): ?>
                                <tr>
                                    <th>Transaction ID</th>
                                    <td><?php echo htmlspecialchars($order->transaction_id); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Fecha de Creación</th>
                                    <td><?php echo htmlspecialchars($order->date_created); ?></td>
                                </tr>
                                <tr>
                                    <th>Última Actualización</th>
                                    <td><?php echo htmlspecialchars($order->date_updated); ?></td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                Orden no encontrada
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Information Card -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle"></i> Información
                    </h5>
                </div>
                <div class="card-body">
                    <p><strong>Esta página te permite probar el sistema de pagos Payku.</strong></p>
                    <ul>
                        <li>Configura el plugin en <a href="/cms/payku">/cms/payku</a></li>
                        <li>Crea un pago de prueba usando el formulario</li>
                        <li>Completa el pago en Payku usando una tarjeta de prueba</li>
                        <li>Verifica el estado de la orden</li>
                    </ul>
                    <p class="mb-0"><small>Para más información, consulta <code>plugins/payku/GUIA_PRUEBAS.md</code></small></p>
                </div>
            </div>
            
        </div>
    </div>
</div>

