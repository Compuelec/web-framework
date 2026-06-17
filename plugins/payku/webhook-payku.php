<?php

/**
 * Payku Webhook Handler
 * Receives payment notifications from Payku and verifies them via API before updating order status.
 *
 * Security note: Payku does not provide HMAC signatures.
 * All success/completed events MUST be verified against the Payku API before updating order status.
 * Never trust the webhook payload alone for payment confirmation.
 */

if (!defined('DIR')) {
    define('DIR', dirname(__DIR__, 2));
}

require_once DIR . '/plugins/payku/lib/paykulib.php';
require_once DIR . '/plugins/payku/controllers/payku.controller.php';
require_once DIR . '/api/models/connection.php';

// Set headers
@ob_clean();
header('HTTP/1.1 200 OK');
header('Content-Type: text/plain');

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data)) {
    echo "OK";
    exit;
}

$order_id = $data['order'] ?? '';
$status = $data['status'] ?? '';
$transaction_id = $data['transaction_id'] ?? '';
$verification_key = $data['verification_key'] ?? '';
$payment_key = $data['payment_key'] ?? '';
$transaction_key = $data['transaction_key'] ?? '';

// Log incoming webhook data for debugging
error_log("Payku webhook received - Order: " . $order_id . ", Status: " . $status . ", Payment Key: " . $payment_key);
error_log("Payku webhook full data: " . json_encode($data));

// Validate order_id - can be alphanumeric (e.g.: TEST-20251229220220)
if (empty($order_id) || !preg_match('/^[a-zA-Z0-9\-_]+$/', $order_id)) {
    error_log("Payku webhook: Invalid order_id format: " . $order_id);
    echo "OK";
    exit;
}

// Get order from database
$order = PaykuPlugin::getOrder($order_id);

if (!$order) {
    error_log("Payku webhook: Order not found: " . $order_id);
    echo "OK";
    exit;
}

// Get plugin configuration
$config = PaykuPlugin::getConfig();
$payku = new Paykulib();
$payku->setUrl($config['platform_id']);
$payku->setPrivateToken($config['token_publico']);

$data_action = $payku->getEndpoint();
$order_total = (int)$order->amount;

// Handle different statuses
// Payku sends status as "success" when payment is completed
if ($status == "rejected" || $status == "failed") {
    error_log("Payku webhook: Payment rejected/failed for order: " . $order_id);
    PaykuPlugin::updateOrderStatus($order_id, 'failed', [
        'transaction_id' => $transaction_id,
        'payment_key' => $payment_key,
        'transaction_key' => $transaction_key,
        'verification_key' => $verification_key,
        'payku_response' => json_encode($data)
    ]);
} elseif ($status == "success" || $status == "completed") {
    // Security: never trust webhook payload alone for payment confirmation.
    // Always verify against Payku API before marking as completed.
    if (empty($payment_key)) {
        // Cannot verify without payment_key — leave in pending for manual review
        error_log("Payku webhook: Success status received but no payment_key for order: " . $order_id . ". Cannot verify. Order left in pending state.");
    } else {
        $token = $payku->getPrivateToken();
        $response = $payku->datosGet($payment_key, $token, $data_action);

        if (!$response || !isset($response->amount)) {
            // API call failed — do NOT mark as completed based on unverified webhook data
            error_log("Payku webhook: API verification call failed for order: " . $order_id . ". Order left in pending state.");
        } elseif ($response->amount != $order_total) {
            // Amount mismatch — possible partial payment fraud attempt
            error_log("Payku webhook: Amount mismatch for order: " . $order_id . ". Expected: " . $order_total . ", Received: " . $response->amount);
            PaykuPlugin::updateOrderStatus($order_id, 'failed', [
                'transaction_id' => $transaction_id,
                'payment_key' => $payment_key,
                'transaction_key' => $transaction_key,
                'verification_key' => $verification_key,
                'payku_response' => json_encode($data)
            ]);
        } else {
            // Use the API response status as authoritative — not the webhook payload
            $finalStatus = $response->status ?? '';

            if ($finalStatus == 'success' || $finalStatus == 'completed') {
                PaykuPlugin::updateOrderStatus($order_id, 'completed', [
                    'transaction_id' => $transaction_id ?? ($response->transaction_id ?? ''),
                    'payment_key' => $payment_key,
                    'transaction_key' => $transaction_key ?? ($response->transaction_key ?? ''),
                    'verification_key' => $verification_key ?? ($response->verification_key ?? ''),
                    'payku_response' => json_encode($response)
                ]);
                error_log("Payku webhook: Payment verified and completed for order: " . $order_id);
            } else {
                PaykuPlugin::updateOrderStatus($order_id, 'failed', [
                    'transaction_id' => $transaction_id,
                    'payment_key' => $payment_key,
                    'transaction_key' => $transaction_key,
                    'verification_key' => $verification_key,
                    'payku_response' => json_encode($response)
                ]);
                error_log("Payku webhook: API verification rejected order: " . $order_id . " - API status: " . $finalStatus);
            }
        }
    }
} else {
    error_log("Payku webhook: Unknown status for order: " . $order_id . " - Status: " . $status);
    PaykuPlugin::updateOrderStatus($order_id, 'pending', [
        'transaction_id' => $transaction_id,
        'payment_key' => $payment_key,
        'transaction_key' => $transaction_key,
        'verification_key' => $verification_key,
        'payku_response' => json_encode($data)
    ]);
}

echo "OK";
@ob_end_flush();
exit;

