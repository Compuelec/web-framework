<?php

/**
 * Payku Payment Result Page
 * Handles return from Payku after payment
 */

if (!defined('DIR')) {
    define('DIR', dirname(__DIR__, 2));
}

require_once DIR . '/plugins/payku/controllers/payku.controller.php';

$order_id = $_GET['order_id'] ?? '';

// Validate order_id - can be alphanumeric (e.g.: TEST-20251229215213)
if (empty($order_id) || !preg_match('/^[a-zA-Z0-9\-_]+$/', $order_id)) {
    die('ID de orden inválido: ' . htmlspecialchars($order_id));
}

// Get order status
$order = PaykuPlugin::getOrder($order_id);

if (!$order) {
    // Order not found - show error page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Orden no encontrada - Payku</title>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .error { color: #d32f2f; }
            .info { color: #666; margin-top: 20px; }
        </style>
    </head>
    <body>
        <h1 class="error">Orden no encontrada</h1>
        <p>No se pudo encontrar la orden con ID: <strong><?php echo htmlspecialchars($order_id); ?></strong></p>
        <p class="info">Es posible que la orden aún no haya sido procesada por Payku. Por favor, espera unos momentos e intenta nuevamente.</p>
        <p class="info">Si el problema persiste, contacta al soporte.</p>
    </body>
    </html>
    <?php
    exit;
}

// Also try to extract transaction_key and verification_key from saved payku_response if available
if (!empty($order->payku_response)) {
    $savedResponse = json_decode($order->payku_response, true);
    if (is_array($savedResponse)) {
        // Try to extract keys from root level first
        $savedTransactionKey = $savedResponse['transaction_key'] ?? $savedResponse['transactionKey'] ?? $savedResponse['key'] ?? null;
        $savedVerificationKey = $savedResponse['verification_key'] ?? $savedResponse['verificationKey'] ?? $savedResponse['verification'] ?? null;
        
        // IMPORTANTE: Verificar dentro del objeto payment (estructura anidada)
        if (isset($savedResponse['payment']) && is_array($savedResponse['payment'])) {
            $payment = $savedResponse['payment'];
            $savedTransactionKey = $savedTransactionKey ?? $payment['transaction_key'] ?? null;
            $savedVerificationKey = $savedVerificationKey ?? $payment['verification_key'] ?? null;
        }
        
        // If found and not in DB, update
        if (($savedTransactionKey && empty($order->transaction_key)) || ($savedVerificationKey && empty($order->verification_key))) {
            PaykuPlugin::updateOrderStatus($order_id, $order->status ?? 'pending', [
                'transaction_id' => $order->transaction_id,
                'payment_key' => $order->payment_key,
                'transaction_key' => $savedTransactionKey ?? $order->transaction_key,
                'verification_key' => $savedVerificationKey ?? $order->verification_key,
                'payku_response' => $order->payku_response
            ]);
            // Recargar orden
            $order = PaykuPlugin::getOrder($order_id);
        }
    }
}

// If order is still pending and has a payment_key, try to verify status manually
// This is useful when webhook is not accessible (e.g.: localhost)
if (($order->status == 'pending' || empty($order->status)) && !empty($order->payment_key)) {
    error_log("Payku result: Order is pending, attempting manual verification for order: " . $order_id);
    
    // Get plugin configuration
    $config = PaykuPlugin::getConfig();
    $payku = new Paykulib();
    $payku->setUrl($config['platform_id']);
    $payku->setPrivateToken(trim($config['token_publico']));
    
    // Get payment status from Payku API
    $data_action = $payku->getEndpoint();
    $token = $payku->getPrivateToken();
    $payment_key = $order->payment_key;
    
    try {
        $response = $payku->datosGet($payment_key, $token, $data_action);
        
        // Handle both object and array responses
        $apiStatus = null;
        $apiAmount = 0;
        
        if (is_object($response)) {
            $apiStatus = $response->status ?? null;
            $apiAmount = $response->amount ?? 0;
        } elseif (is_array($response)) {
            $apiStatus = $response['status'] ?? null;
            $apiAmount = $response['amount'] ?? 0;
        }
        
        // Write debug information to file for easier access (only if debug is enabled)
        $debugEnabled = $config['debug_enabled'] ?? false;
        if ($debugEnabled) {
            $debugFile = dirname(__DIR__, 2) . '/plugins/payku/debug.log';
            $debugContent = date('Y-m-d H:i:s') . " - Verifying order: $order_id\n";
            $debugContent .= "Payment key: $payment_key\n";
            
            if (is_object($response)) {
                $debugContent .= "Response type: object\n";
                $debugContent .= "Status: " . ($apiStatus ?? 'NULL') . "\n";
                $debugContent .= "Amount: $apiAmount\n";
                $debugContent .= "Full response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
            } elseif (is_array($response)) {
                $debugContent .= "Response type: array\n";
                $debugContent .= "Status: " . ($apiStatus ?? 'NULL') . "\n";
                $debugContent .= "Amount: $apiAmount\n";
                $debugContent .= "All fields: " . implode(', ', array_keys($response)) . "\n";
                $debugContent .= "Full response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
            } else {
                $debugContent .= "Response type: " . gettype($response) . "\n";
                $debugContent .= "Response value: " . var_export($response, true) . "\n";
            }
            
            // Write debug file
            @file_put_contents($debugFile, $debugContent . "\n---\n", FILE_APPEND);
        }
        
        // Initialize hasPaymentData
        $hasPaymentData = false;
        
        if ($response && $apiStatus) {
            $orderAmount = (int)$order->amount;
            
            error_log("Payku result: API response for order " . $order_id . " - Status: " . $apiStatus . ", Amount: " . $apiAmount);
            
            // Check if payment data is available (not empty)
            // Payment data is available when payment object/array exists and has content
            $hasPaymentData = false;
            if (is_object($response)) {
                if (isset($response->payment)) {
                    if (is_object($response->payment)) {
                        // Objeto con propiedades significa que existen datos de pago
                        $hasPaymentData = count(get_object_vars($response->payment)) > 0;
                    } elseif (is_array($response->payment)) {
                        // Array con elementos significa que existen datos de pago
                        $hasPaymentData = count($response->payment) > 0;
                    }
                }
            } elseif (is_array($response)) {
                if (isset($response['payment'])) {
                    if (is_array($response['payment'])) {
                        // Array con elementos significa que existen datos de pago
                        $hasPaymentData = count($response['payment']) > 0;
                    } elseif (is_object($response['payment'])) {
                        // Objeto con propiedades significa que existen datos de pago
                        $hasPaymentData = count(get_object_vars($response['payment'])) > 0;
                    }
                }
            }
            
            // If status is still pending and payment data is empty, wait a bit and retry
            if ($apiStatus == 'pending' && !$hasPaymentData) {
                error_log("Payku result: Order still pending with no payment data, waiting 2 seconds and retrying...");
                sleep(2); // Wait 2 seconds for Payku to process
                
                // Retry once
                $response = $payku->datosGet($payment_key, $token, $data_action);
                if ($response) {
                    if (is_object($response)) {
                        $apiStatus = $response->status ?? null;
                        $apiAmount = $response->amount ?? 0;
                        if (isset($response->payment)) {
                            if (is_object($response->payment)) {
                                $hasPaymentData = count(get_object_vars($response->payment)) > 0;
                            } elseif (is_array($response->payment)) {
                                $hasPaymentData = count($response->payment) > 0;
                            }
                        }
                    } elseif (is_array($response)) {
                        $apiStatus = $response['status'] ?? null;
                        $apiAmount = $response['amount'] ?? 0;
                        if (isset($response['payment'])) {
                            if (is_array($response['payment'])) {
                                $hasPaymentData = count($response['payment']) > 0;
                            } elseif (is_object($response['payment'])) {
                                $hasPaymentData = count(get_object_vars($response['payment'])) > 0;
                            }
                        }
                    }
                    error_log("Payku result: Retry - Status: " . ($apiStatus ?? 'NULL') . ", Has payment data: " . ($hasPaymentData ? 'YES' : 'NO'));
                }
            }
            
            // Log full response to see what fields are available
            error_log("Payku result: Full API response for order " . $order_id . ": " . json_encode($response));
            
            // Debug: Show all available fields in response
            if (is_object($response)) {
                $allFields = get_object_vars($response);
                error_log("Payku result: All available fields in response: " . implode(', ', array_keys($allFields)));
                foreach ($allFields as $key => $value) {
                    if (is_scalar($value)) {
                        error_log("Payku result: Field '$key' = " . $value);
                    } else {
                        error_log("Payku result: Field '$key' = " . json_encode($value));
                    }
                }
            } elseif (is_array($response)) {
                error_log("Payku result: All available fields in response: " . implode(', ', array_keys($response)));
                foreach ($response as $key => $value) {
                    if (is_scalar($value)) {
                        error_log("Payku result: Field '$key' = " . $value);
                    } else {
                        error_log("Payku result: Field '$key' = " . json_encode($value));
                    }
                }
            }
            
            // Update order based on API response
            // Only process if we have payment data or status is success/completed
            if (($apiStatus == 'success' || $apiStatus == 'completed') && $hasPaymentData) {
                if ($apiAmount == $orderAmount || $apiAmount == 0) {
                    // Payment successful - extract all available fields from response
                    // Convert to array for easier access
                    $responseArray = is_object($response) ? json_decode(json_encode($response), true) : (is_array($response) ? $response : []);
                    
                    // Extract from root level first
                    $transaction_id = $responseArray['transaction_id'] ?? $responseArray['id'] ?? 
                                     (is_object($response) ? ($response->transaction_id ?? $response->id ?? null) : null);
                    
                    $transaction_key = $responseArray['transaction_key'] ?? 
                                     (is_object($response) ? ($response->transaction_key ?? null) : null);
                    
                    $verification_key = $responseArray['verification_key'] ?? 
                                       (is_object($response) ? ($response->verification_key ?? null) : null);
                    
                    // IMPORTANT: Check inside payment object (nested structure)
                    if (isset($responseArray['payment']) && is_array($responseArray['payment'])) {
                        $payment = $responseArray['payment'];
                        $transaction_id = $transaction_id ?? $payment['transaction_id'] ?? null;
                        $transaction_key = $transaction_key ?? $payment['transaction_key'] ?? null;
                        $verification_key = $verification_key ?? $payment['verification_key'] ?? null;
                    } elseif (is_object($response) && isset($response->payment)) {
                        // Handle object response with payment property
                        $payment = is_object($response->payment) ? json_decode(json_encode($response->payment), true) : $response->payment;
                        if (is_array($payment)) {
                            $transaction_id = $transaction_id ?? $payment['transaction_id'] ?? null;
                            $transaction_key = $transaction_key ?? $payment['transaction_key'] ?? null;
                            $verification_key = $verification_key ?? $payment['verification_key'] ?? null;
                        } elseif (is_object($response->payment)) {
                            $transaction_id = $transaction_id ?? $response->payment->transaction_id ?? null;
                            $transaction_key = $transaction_key ?? $response->payment->transaction_key ?? null;
                            $verification_key = $verification_key ?? $response->payment->verification_key ?? null;
                        }
                    }
                    
                    // Also check nested objects/arrays in data
                    if (isset($responseArray['data']) && is_array($responseArray['data'])) {
                        $transaction_key = $transaction_key ?? $responseArray['data']['transaction_key'] ?? $responseArray['data']['transactionKey'] ?? null;
                        $verification_key = $verification_key ?? $responseArray['data']['verification_key'] ?? $responseArray['data']['verificationKey'] ?? null;
                    }
                    
                    // Log extracted values
                    error_log("Payku result: Extracted values - transaction_id: " . ($transaction_id ?? 'NULL') . ", transaction_key: " . ($transaction_key ?? 'NULL') . ", verification_key: " . ($verification_key ?? 'NULL'));
                    
                    // Payment successful
                    // Use payment_key as transaction_id if transaction_id is not available
                    if (empty($transaction_id) && !empty($payment_key)) {
                        $transaction_id = $payment_key;
                    }
                    
                    PaykuPlugin::updateOrderStatus($order_id, 'completed', [
                        'transaction_id' => $transaction_id,
                        'payment_key' => $payment_key,
                        'transaction_key' => $transaction_key,
                        'verification_key' => $verification_key,
                        'payku_response' => json_encode($response)
                    ]);
                    
                    // Log what was actually saved
                    error_log("Payku result: Saved to DB - transaction_id: " . ($transaction_id ?? 'NULL') . ", transaction_key: " . ($transaction_key ?? 'NULL') . ", verification_key: " . ($verification_key ?? 'NULL'));
                    error_log("Payku result: Order " . $order_id . " updated to completed");
                    // Reload order to get updated data
                    $order = PaykuPlugin::getOrder($order_id);
                } else {
                    error_log("Payku result: Amount mismatch for order " . $order_id . " - Expected: " . $orderAmount . ", Received: " . $apiAmount);
                }
            } elseif ($apiStatus == 'rejected' || $apiStatus == 'failed') {
                    // Extract fields for failed status as well
                    // Convert to array for easier access
                    $responseArray = is_object($response) ? json_decode(json_encode($response), true) : (is_array($response) ? $response : []);
                    
                    // Extract from root level first
                $transaction_id = $responseArray['transaction_id'] ?? $responseArray['id'] ?? 
                                 (is_object($response) ? ($response->transaction_id ?? $response->id ?? null) : null);
                
                $transaction_key = $responseArray['transaction_key'] ?? 
                                 (is_object($response) ? ($response->transaction_key ?? null) : null);
                
                $verification_key = $responseArray['verification_key'] ?? 
                                   (is_object($response) ? ($response->verification_key ?? null) : null);
                
                // IMPORTANT: Check inside payment object (nested structure)
                if (isset($responseArray['payment']) && is_array($responseArray['payment'])) {
                    $payment = $responseArray['payment'];
                    $transaction_id = $transaction_id ?? $payment['transaction_id'] ?? null;
                    $transaction_key = $transaction_key ?? $payment['transaction_key'] ?? null;
                    $verification_key = $verification_key ?? $payment['verification_key'] ?? null;
                } elseif (is_object($response) && isset($response->payment)) {
                    // Handle object response with payment property
                    $payment = is_object($response->payment) ? json_decode(json_encode($response->payment), true) : $response->payment;
                    if (is_array($payment)) {
                        $transaction_id = $transaction_id ?? $payment['transaction_id'] ?? null;
                        $transaction_key = $transaction_key ?? $payment['transaction_key'] ?? null;
                        $verification_key = $verification_key ?? $payment['verification_key'] ?? null;
                    } elseif (is_object($response->payment)) {
                        $transaction_id = $transaction_id ?? $response->payment->transaction_id ?? null;
                        $transaction_key = $transaction_key ?? $response->payment->transaction_key ?? null;
                        $verification_key = $verification_key ?? $response->payment->verification_key ?? null;
                    }
                }
                
                // Use payment_key as transaction_id if transaction_id is not available
                if (empty($transaction_id) && !empty($payment_key)) {
                    $transaction_id = $payment_key;
                }
                
                PaykuPlugin::updateOrderStatus($order_id, 'failed', [
                    'transaction_id' => $transaction_id,
                    'payment_key' => $payment_key,
                    'transaction_key' => $transaction_key,
                    'verification_key' => $verification_key,
                    'payku_response' => json_encode($response)
                ]);
                
                error_log("Payku result: Saved to DB (failed) - transaction_id: " . ($transaction_id ?? 'NULL') . ", transaction_key: " . ($transaction_key ?? 'NULL') . ", verification_key: " . ($verification_key ?? 'NULL'));
                error_log("Payku result: Order " . $order_id . " updated to failed");
                // Reload order to get updated data
                $order = PaykuPlugin::getOrder($order_id);
            }
        } else {
            error_log("Payku result: Could not get payment status from API for order: " . $order_id);
        }
    } catch (Exception $e) {
        error_log("Payku result: Exception while verifying payment: " . $e->getMessage());
    }
}

// Get order status
$status = $order->status ?? 'pending';
$amount = $order->amount ?? 0;
$currency = $order->currency ?? 'CLP';
$email = $order->email ?? '';
$transaction_id = $order->transaction_id ?? '';
$payment_key = $order->payment_key ?? '';
$transaction_key = $order->transaction_key ?? '';
$verification_key = $order->verification_key ?? '';

// Debug: Log current order state
error_log("Payku result: Current order state - status: " . $status . ", transaction_key: " . ($transaction_key ?: 'NULL') . ", verification_key: " . ($verification_key ?: 'NULL'));

// Determinar mensaje y color del estado
$statusMessages = [
    'completed' => ['mensaje' => '¡Pago Completado Exitosamente!', 'color' => 'success', 'icon' => '✓'],
    'failed' => ['mensaje' => 'Pago Fallido', 'color' => 'danger', 'icon' => '✗'],
    'pending' => ['mensaje' => 'Pago Pendiente', 'color' => 'warning', 'icon' => '⏳'],
    'cancelled' => ['mensaje' => 'Pago Cancelado', 'color' => 'secondary', 'icon' => '⊘']
];

$statusInfo = $statusMessages[$status] ?? $statusMessages['pending'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado del Pago - Payku</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .result-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        .status-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .order-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .btn-home {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="result-card text-center">
        <div class="status-icon text-<?php echo $statusInfo['color']; ?>">
            <?php echo $statusInfo['icon']; ?>
        </div>
        <h1 class="mb-3 text-<?php echo $statusInfo['color']; ?>">
            <?php echo $statusInfo['mensaje']; ?>
        </h1>
        
        <div class="order-details text-start">
            <h5 class="mb-3"><i class="bi bi-receipt"></i> Detalles de la Orden</h5>
            
            <div class="detail-row">
                <strong>ID de Orden:</strong>
                <span><?php echo htmlspecialchars($order_id); ?></span>
            </div>
            
            <?php if ($transaction_id): ?>
            <div class="detail-row">
                <strong>ID de Transacción:</strong>
                <span><?php echo htmlspecialchars($transaction_id); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($payment_key): ?>
            <div class="detail-row">
                <strong>Clave de Pago:</strong>
                <span class="text-muted small"><?php echo htmlspecialchars($payment_key); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($transaction_key): ?>
            <div class="detail-row">
                <strong>Clave de Transacción:</strong>
                <span class="text-muted small"><?php echo htmlspecialchars($transaction_key); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($verification_key): ?>
            <div class="detail-row">
                <strong>Clave de Verificación:</strong>
                <span class="text-muted small"><?php echo htmlspecialchars($verification_key); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="detail-row">
                <strong>Email:</strong>
                <span><?php echo htmlspecialchars($email); ?></span>
            </div>
            
            <div class="detail-row">
                <strong>Monto:</strong>
                <span class="fw-bold">$<?php echo number_format($amount, 0, ',', '.'); ?> <?php echo htmlspecialchars($currency); ?></span>
            </div>
            
            <div class="detail-row">
                <strong>Estado:</strong>
                <span class="badge bg-<?php echo $statusInfo['color']; ?>"><?php echo strtoupper($status); ?></span>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="/web-framework/web/" class="btn btn-primary btn-lg btn-home">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
        
        <?php if ($status == 'completed'): ?>
        <div class="alert alert-success mt-4">
            <i class="bi bi-check-circle"></i> Tu pago ha sido procesado exitosamente. Recibirás un correo de confirmación.
        </div>
        <?php elseif ($status == 'pending'): ?>
        <div class="alert alert-warning mt-4">
            <i class="bi bi-clock"></i> Tu pago está siendo procesado. Te notificaremos cuando se complete.
        </div>
        <?php elseif ($status == 'failed'): ?>
        <div class="alert alert-danger mt-4">
            <i class="bi bi-x-circle"></i> El pago no pudo ser procesado. Por favor, intenta nuevamente.
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
exit;

