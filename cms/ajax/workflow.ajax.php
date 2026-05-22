<?php

/**
 * Workflow AJAX Handler
 * Handles AJAX requests for workflow operations
 */

session_start();

require_once "../controllers/workflow.controller.php";
require_once "../controllers/curl.controller.php";

// Include activity log helper if available
$activityLogPath = __DIR__ . "/../../core/activity_log.php";
if (file_exists($activityLogPath)) {
    require_once $activityLogPath;
}

class WorkflowAjax {

    /**
     * Execute a workflow transition
     */
    public function executeTransition() {
        $table = $_POST['table'] ?? '';
        $suffix = $_POST['suffix'] ?? '';
        $recordId = $_POST['record_id'] ?? 0;
        $transitionId = $_POST['transition_id'] ?? '';
        $comment = $_POST['comment'] ?? null;
        $token = $_POST['token'] ?? '';

        // Validate required fields
        if (empty($table) || empty($suffix) || empty($recordId) || empty($transitionId)) {
            $this->sendResponse(['success' => false, 'error' => 'Missing required fields']);
            return;
        }

        // Validate token
        if (!$this->validateToken($token)) {
            $this->sendResponse(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        // Get user role from session
        $userRole = isset($_SESSION['admin']) ? $_SESSION['admin']->rol_admin : 'guest';

        // Execute transition
        $result = WorkflowController::executeTransition($table, $suffix, $recordId, $transitionId, $comment, $userRole);

        $this->sendResponse($result);
    }

    /**
     * Get workflow configuration for a module
     */
    public function getWorkflowConfig() {
        $moduleId = $_POST['module_id'] ?? 0;

        if (empty($moduleId)) {
            $this->sendResponse(['success' => false, 'error' => 'Module ID required']);
            return;
        }

        $workflow = WorkflowController::getWorkflow($moduleId);

        if ($workflow) {
            $this->sendResponse([
                'success' => true,
                'workflow' => $workflow
            ]);
        } else {
            // Return default workflow if none exists
            $default = WorkflowController::getDefaultWorkflow();
            $this->sendResponse([
                'success' => true,
                'workflow' => null,
                'default' => $default
            ]);
        }
    }

    /**
     * Save workflow configuration
     */
    public function saveWorkflowConfig() {
        $moduleId = $_POST['module_id'] ?? 0;
        $states = json_decode($_POST['states'] ?? '[]', true);
        $transitions = json_decode($_POST['transitions'] ?? '[]', true);
        $settings = json_decode($_POST['settings'] ?? '{}', true);
        $token = $_POST['token'] ?? '';

        // Validate required fields
        if (empty($moduleId)) {
            $this->sendResponse(['success' => false, 'error' => 'Module ID required']);
            return;
        }

        // Validate token
        if (!$this->validateToken($token)) {
            $this->sendResponse(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        // Validate states and transitions
        if (empty($states)) {
            $this->sendResponse(['success' => false, 'error' => 'At least one state is required']);
            return;
        }

        $result = WorkflowController::saveWorkflow($moduleId, $states, $transitions, $settings);

        $this->sendResponse(['success' => $result]);
    }

    /**
     * Get allowed transitions for a record
     */
    public function getAllowedTransitions() {
        $moduleId = $_POST['module_id'] ?? 0;
        $currentState = $_POST['current_state'] ?? '';

        if (empty($moduleId)) {
            $this->sendResponse(['success' => false, 'error' => 'Module ID required']);
            return;
        }

        $workflow = WorkflowController::getWorkflow($moduleId);
        if (!$workflow) {
            $this->sendResponse(['success' => false, 'error' => 'Workflow not found']);
            return;
        }

        $userRole = isset($_SESSION['admin']) ? $_SESSION['admin']->rol_admin : 'guest';
        $transitions = WorkflowController::getAllowedTransitions($workflow, $currentState, $userRole);

        $this->sendResponse([
            'success' => true,
            'transitions' => $transitions
        ]);
    }

    /**
     * Validate session token
     * @param string $token Token to validate
     * @return bool True if valid
     */
    private function validateToken($token) {
        if (empty($token)) {
            return false;
        }

        // Check if admin is in session
        if (!isset($_SESSION['admin']) || !isset($_SESSION['admin']->token_admin)) {
            return false;
        }

        // Compare tokens
        return $token === $_SESSION['admin']->token_admin;
    }

    /**
     * Send JSON response
     * @param array $data Response data
     */
    private function sendResponse($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}

// Route requests
if (isset($_POST['action'])) {
    $ajax = new WorkflowAjax();

    switch ($_POST['action']) {
        case 'executeTransition':
            $ajax->executeTransition();
            break;
        case 'getWorkflowConfig':
            $ajax->getWorkflowConfig();
            break;
        case 'saveWorkflowConfig':
            $ajax->saveWorkflowConfig();
            break;
        case 'getAllowedTransitions':
            $ajax->getAllowedTransitions();
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
}
