<?php

/**
 * Notifications AJAX Endpoint
 * Handles notification management
 */

require_once __DIR__ . '/session-init.php';

require_once "../controllers/curl.controller.php";
require_once "../controllers/template.controller.php";

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION["admin"])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'notifications' => [],
        'unread_count' => 0
    ]);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : 'get';
$token = isset($_POST['token']) ? $_POST['token'] : '';

try {
    switch ($action) {
        case 'get':
            // Get notifications for current user
            $url = "notifications?linkTo=id_admin_notification&equalTo=" . $_SESSION["admin"]->id_admin . 
                   "&orderBy=date_created_notification&orderMode=DESC&startAt=0&endAt=20";
            $method = "GET";
            $fields = array();
            
            $notifications = CurlController::request($url, $method, $fields);
            
            $notificationList = [];
            $unreadCount = 0;
            
            if ($notifications->status == 200 && isset($notifications->results)) {
                foreach ($notifications->results as $notification) {
                    $notificationList[] = $notification;
                    if ($notification->read_notification == 0) {
                        $unreadCount++;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $notificationList,
                'unread_count' => $unreadCount
            ]);
            break;
            
        case 'mark_read':
            $id = isset($_POST['id']) ? $_POST['id'] : '';
            
            if (empty($id)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'ID is required'
                ]);
                exit;
            }
            
            $url = "notifications?id=" . $id . "&nameId=id_notification&token=" . $token . "&table=admins&suffix=admin";
            $method = "PUT";
            $fields = "read_notification=1";
            
            $update = CurlController::request($url, $method, $fields);
            
            echo json_encode([
                'success' => $update->status == 200
            ]);
            break;
            
        case 'mark_all_read':
            // Mark all notifications as read for current user
            // Note: This would need a bulk update endpoint in the API
            // For now, we'll get all unread and update them individually
            $urlGet = "notifications?linkTo=id_admin_notification,read_notification&equalTo=" . 
                     $_SESSION["admin"]->id_admin . ",0&select=id_notification";
            $unread = CurlController::request($urlGet, $method, array());
            
            $updated = 0;
            if ($unread->status == 200 && isset($unread->results)) {
                foreach ($unread->results as $notification) {
                    $urlUpdate = "notifications?id=" . $notification->id_notification . 
                                "&nameId=id_notification&token=" . $token . "&table=admins&suffix=admin";
                    $update = CurlController::request($urlUpdate, "PUT", "read_notification=1");
                    if ($update->status == 200) {
                        $updated++;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'updated' => $updated
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

