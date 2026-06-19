<?php

/**
 * Activity Log Helper
 * 
 * Simple helper function to log activities throughout the application
 */

require_once __DIR__ . '/../cms/controllers/activity_logs.controller.php';

/**
 * Log an activity
 * 
 * @param string $action Action performed (e.g., 'login', 'create', 'update', 'delete')
 * @param string $entity Entity affected (e.g., 'admin', 'page', 'module')
 * @param int|null $entityId ID of the affected entity
 * @param string|null $description Additional description
 * @return bool True on success, false on failure
 */
function logActivity($action, $entity, $entityId = null, $description = null) {
	return ActivityLogsController::log($action, $entity, $entityId, $description);
}
