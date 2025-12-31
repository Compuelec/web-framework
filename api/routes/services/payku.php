<?php

/**
 * Servicio de Pagos Payku
 * Endpoint API para procesar pagos
 */

require_once __DIR__ . "/../../models/connection.php";
require_once __DIR__ . "/../../../plugins/payku/controllers/payku.controller.php";

// Validate API key
$headers = function_exists("getallheaders") ? getallheaders() : [];
$authorization = $headers["Authorization"] ?? ($_SERVER["HTTP_AUTHORIZATION"] ?? ($_SERVER["REDIRECT_HTTP_AUTHORIZATION"] ?? null));

if (!$authorization || $authorization != Connection::apikey()) {
    $json = array(
        'status' => 400,
        "results" => "No estás autorizado para realizar esta petición"
    );
    http_response_code($json["status"]);
    echo json_encode($json);
    exit;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method == "POST") {
    // Process payment
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validate required fields
    if (empty($data['order_id']) || empty($data['email']) || empty($data['amount'])) {
        $json = array(
            'status' => 400,
            'results' => 'Faltan campos requeridos: order_id, email, amount'
        );
        http_response_code($json["status"]);
        echo json_encode($json);
        exit;
    }
    
    // Prepare order data
    $orderData = [
        'order_id' => $data['order_id'],
        'email' => $data['email'],
        'amount' => $data['amount'],
        'currency' => $data['currency'] ?? 'CLP',
        'products' => $data['products'] ?? []
    ];
    
    // Process payment
    $result = PaykuPlugin::processPayment($orderData);
    
    if ($result['success']) {
        $json = array(
            'status' => 200,
            'results' => [
                'redirect_url' => $result['redirect_url'],
                'order_id' => $orderData['order_id']
            ]
        );
    } else {
        $json = array(
            'status' => 400,
            'results' => $result['error'] ?? 'Error al procesar el pago'
        );
        http_response_code(400);
    }
    
    echo json_encode($json);
    
} else if ($method == "GET") {
    // Get order status
    $order_id = $_GET['order_id'] ?? null;
    
    if (empty($order_id)) {
        $json = array(
            'status' => 400,
            'results' => 'El parámetro order_id es requerido'
        );
        http_response_code($json["status"]);
        echo json_encode($json);
        exit;
    }
    
    $order = PaykuPlugin::getOrder($order_id);
    
    if ($order) {
        $json = array(
            'status' => 200,
            'results' => $order
        );
    } else {
        $json = array(
            'status' => 404,
            'results' => 'Orden no encontrada'
        );
        http_response_code(404);
    }
    
    echo json_encode($json);
    
} else {
    $json = array(
        'status' => 405,
        'results' => 'Método no permitido'
    );
    http_response_code(405);
    echo json_encode($json);
}

