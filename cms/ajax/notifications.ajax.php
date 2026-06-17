<?php

/**
 * Notifications AJAX Endpoint
 * Handles notification management
 */

// Define constant to indicate session-init is being included
define('SESSION_INIT_INCLUDED', true);

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
            $urlGet = "notifications?linkTo=id_admin_notification,read_notification&equalTo=" .
                     $_SESSION["admin"]->id_admin . ",0&select=id_notification";
            $unread = CurlController::request($urlGet, "GET", array());
            
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
            
        case 'create':
            // Create a notification for a specific admin (or all admins if id_admin = 0)
            $targetAdminId = isset($_POST['id_admin'])   ? (int)$_POST['id_admin']   : (int)$_SESSION["admin"]->id_admin;
            $title         = isset($_POST['title'])      ? trim($_POST['title'])      : '';
            $message       = isset($_POST['message'])    ? trim($_POST['message'])    : '';
            $type          = isset($_POST['type'])       ? trim($_POST['type'])       : 'info';
            $icon          = isset($_POST['icon'])       ? trim($_POST['icon'])       : 'bi-info-circle';
            $url           = isset($_POST['url'])        ? trim($_POST['url'])        : '';

            if (empty($title) || empty($message)) {
                echo json_encode(['success' => false, 'error' => 'title and message are required']);
                exit;
            }

            $fields = http_build_query([
                'id_admin_notification'   => $targetAdminId,
                'title_notification'      => $title,
                'message_notification'    => $message,
                'type_notification'       => $type,
                'icon_notification'       => $icon,
                'url_notification'        => $url,
                'read_notification'       => 0,
            ]);

            $result = CurlController::request("notifications?token=" . $token . "&table=admins&suffix=admin", "POST", $fields);

            echo json_encode([
                'success' => isset($result->status) && $result->status == 200,
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
    error_log("Notifications AJAX error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}

