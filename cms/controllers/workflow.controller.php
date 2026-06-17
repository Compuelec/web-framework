<?php

/**
 * Workflow Controller
 * Handles workflow state management, transitions, and configuration
 */

require_once __DIR__ . "/install.controller.php";

class WorkflowController {

    private static $tableChecked = false;

    /**
     * Ensure workflows table exists (auto-migration)
     * Creates the table if it doesn't exist
     */
    private static function ensureTableExists() {
        if (self::$tableChecked) {
            return;
        }

        try {
            $link = InstallController::connect();

            // Check if table exists
            $checkSql = "SHOW TABLES LIKE 'workflows'";
            $result = $link->query($checkSql);

            if ($result->rowCount() == 0) {
                // Create table
                $createSql = "CREATE TABLE workflows (
                    id_workflow INT NOT NULL AUTO_INCREMENT,
                    id_module_workflow INT NOT NULL,
                    title_workflow VARCHAR(255) NOT NULL,
                    states_workflow TEXT NOT NULL,
                    transitions_workflow TEXT NOT NULL,
                    settings_workflow TEXT NULL DEFAULT NULL,
                    date_created_workflow DATE NULL DEFAULT NULL,
                    date_updated_workflow TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_workflow),
                    UNIQUE KEY unique_module_workflow (id_module_workflow),
                    INDEX idx_module (id_module_workflow)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $link->exec($createSql);
                error_log("WorkflowController: Created workflows table automatically");
            }

            self::$tableChecked = true;
        } catch (PDOException $e) {
            error_log("WorkflowController::ensureTableExists error: " . $e->getMessage());
        }
    }

    /**
     * Get workflow configuration for a module
     * @param int $moduleId Module ID
     * @return object|null Workflow configuration or null if not found
     */
    public static function getWorkflow($moduleId) {
        try {
            self::ensureTableExists();
            $link = InstallController::connect();

            $sql = "SELECT * FROM workflows WHERE id_module_workflow = :module_id LIMIT 1";
            $stmt = $link->prepare($sql);
            $stmt->execute([':module_id' => $moduleId]);

            $result = $stmt->fetch(PDO::FETCH_OBJ);

            if ($result) {
                $result->states = json_decode($result->states_workflow);
                $result->transitions = json_decode($result->transitions_workflow);
                $result->settings = json_decode($result->settings_workflow);
            }

            return $result;
        } catch (PDOException $e) {
            error_log("WorkflowController::getWorkflow error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Save workflow configuration for a module
     * @param int $moduleId Module ID
     * @param array $states Array of state definitions
     * @param array $transitions Array of transition definitions
     * @param array|null $settings Optional settings
     * @return bool Success status
     */
    public static function saveWorkflow($moduleId, $states, $transitions, $settings = null) {
        try {
            $link = InstallController::connect();

            // Check if workflow exists
            $checkSql = "SELECT id_workflow FROM workflows WHERE id_module_workflow = :module_id";
            $checkStmt = $link->prepare($checkSql);
            $checkStmt->execute([':module_id' => $moduleId]);
            $exists = $checkStmt->fetch();

            if ($exists) {
                // Update existing
                $sql = "UPDATE workflows SET
                        states_workflow = :states,
                        transitions_workflow = :transitions,
                        settings_workflow = :settings
                        WHERE id_module_workflow = :module_id";
            } else {
                // Insert new
                $sql = "INSERT INTO workflows
                        (id_module_workflow, title_workflow, states_workflow, transitions_workflow, settings_workflow, date_created_workflow)
                        VALUES (:module_id, :title, :states, :transitions, :settings, :date_created)";
            }

            $stmt = $link->prepare($sql);
            $params = [
                ':module_id' => $moduleId,
                ':states' => json_encode($states),
                ':transitions' => json_encode($transitions),
                ':settings' => json_encode($settings)
            ];

            if (!$exists) {
                $params[':title'] = 'Workflow for module ' . $moduleId;
                $params[':date_created'] = date('Y-m-d');
            }

            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("WorkflowController::saveWorkflow error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get allowed transitions for current state and user role
     * @param object $workflow Workflow configuration
     * @param string $currentState Current state ID
     * @param string $userRole User's role
     * @return array Array of allowed transitions
     */
    public static function getAllowedTransitions($workflow, $currentState, $userRole) {
        if (!$workflow || !$workflow->transitions) {
            return [];
        }

        $allowed = [];
        foreach ($workflow->transitions as $transition) {
            // Check if current state is in "from" states
            $fromStates = is_array($transition->from) ? $transition->from : [$transition->from];
            if (!in_array($currentState, $fromStates)) {
                continue;
            }

            // Check role permission (empty roles = all allowed)
            if (!empty($transition->roles)) {
                $allowedRoles = is_array($transition->roles) ? $transition->roles : [$transition->roles];
                if (!in_array($userRole, $allowedRoles) && !in_array('*', $allowedRoles)) {
                    continue;
                }
            }

            $allowed[] = $transition;
        }

        return $allowed;
    }

    /**
     * Execute a workflow transition
     * @param string $table Table name
     * @param string $suffix Table suffix
     * @param int $recordId Record ID
     * @param string $transitionId Transition ID
     * @param string|null $comment Optional comment
     * @param string $userRole User's role
     * @return array Result with success status and message
     */
    public static function executeTransition($table, $suffix, $recordId, $transitionId, $comment = null, $userRole = 'admin') {
        try {
            $link = InstallController::connect();

            // Get module info and workflow column
            $sqlModule = "SELECT m.id_module, c.title_column
                          FROM modules m
                          JOIN columns c ON c.id_module_column = m.id_module
                          WHERE m.title_module = :table AND c.type_column = 'workflow'
                          LIMIT 1";
            $stmtModule = $link->prepare($sqlModule);
            $stmtModule->execute([':table' => $table]);
            $moduleInfo = $stmtModule->fetch(PDO::FETCH_OBJ);

            if (!$moduleInfo) {
                return ['success' => false, 'error' => 'Workflow not found for this table'];
            }

            // Get workflow configuration
            $workflow = self::getWorkflow($moduleInfo->id_module);
            if (!$workflow) {
                return ['success' => false, 'error' => 'Workflow configuration not found'];
            }

            // Get current record state
            $workflowColumn = $moduleInfo->title_column;
            $idColumn = 'id_' . $suffix;
            $sqlRecord = "SELECT `$workflowColumn` FROM `$table` WHERE `$idColumn` = :record_id";
            $stmtRecord = $link->prepare($sqlRecord);
            $stmtRecord->execute([':record_id' => $recordId]);
            $record = $stmtRecord->fetch(PDO::FETCH_OBJ);

            if (!$record) {
                return ['success' => false, 'error' => 'Record not found'];
            }

            $currentState = $record->$workflowColumn;

            // Find the transition
            $transition = null;
            foreach ($workflow->transitions as $t) {
                if ($t->id === $transitionId) {
                    $transition = $t;
                    break;
                }
            }

            if (!$transition) {
                return ['success' => false, 'error' => 'Transition not found'];
            }

            // Validate transition is allowed from current state
            $fromStates = is_array($transition->from) ? $transition->from : [$transition->from];
            if (!in_array($currentState, $fromStates)) {
                return ['success' => false, 'error' => 'Transition not allowed from current state'];
            }

            // Check role permission
            if (!empty($transition->roles)) {
                $allowedRoles = is_array($transition->roles) ? $transition->roles : [$transition->roles];
                if (!in_array($userRole, $allowedRoles) && !in_array('*', $allowedRoles)) {
                    return ['success' => false, 'error' => 'You do not have permission for this transition'];
                }
            }

            // Check if comment is required
            if (isset($transition->require_comment) && $transition->require_comment && empty($comment)) {
                return ['success' => false, 'error' => 'Comment is required for this transition'];
            }

            // Execute the transition
            $newState = $transition->to;
            $sqlUpdate = "UPDATE `$table` SET `$workflowColumn` = :new_state WHERE `$idColumn` = :record_id";
            $stmtUpdate = $link->prepare($sqlUpdate);
            $stmtUpdate->execute([':new_state' => $newState, ':record_id' => $recordId]);

            // Log the transition in activity_logs
            if (function_exists('logActivity')) {
                $description = "Workflow transition: {$currentState} -> {$newState}";
                if ($comment) {
                    $description .= " | Comment: $comment";
                }
                logActivity('workflow_transition', $table, $recordId, $description);
            }

            return [
                'success' => true,
                'previous_state' => $currentState,
                'new_state' => $newState,
                'transition' => $transition
            ];
        } catch (PDOException $e) {
            error_log("WorkflowController::executeTransition error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get state info by ID
     * @param object $workflow Workflow configuration
     * @param string $stateId State ID
     * @return object|null State info or null if not found
     */
    public static function getStateInfo($workflow, $stateId) {
        if (!$workflow || !$workflow->states) {
            return null;
        }

        foreach ($workflow->states as $state) {
            if ($state->id === $stateId) {
                return $state;
            }
        }

        return null;
    }

    /**
     * Get default workflow configuration
     * @return array Default states and transitions
     */
    public static function getDefaultWorkflow() {
        return [
            'states' => [
                ['id' => 'draft', 'label' => 'Borrador', 'color' => '#6c757d'],
                ['id' => 'pending', 'label' => 'Pendiente', 'color' => '#ffc107'],
                ['id' => 'approved', 'label' => 'Aprobado', 'color' => '#28a745'],
                ['id' => 'rejected', 'label' => 'Rechazado', 'color' => '#dc3545']
            ],
            'transitions' => [
                [
                    'id' => 'submit',
                    'from' => ['draft'],
                    'to' => 'pending',
                    'label' => 'Enviar a revision',
                    'roles' => ['*'],
                    'require_comment' => false
                ],
                [
                    'id' => 'approve',
                    'from' => ['pending'],
                    'to' => 'approved',
                    'label' => 'Aprobar',
                    'roles' => ['superadmin', 'admin'],
                    'require_comment' => false
                ],
                [
                    'id' => 'reject',
                    'from' => ['pending'],
                    'to' => 'rejected',
                    'label' => 'Rechazar',
                    'roles' => ['superadmin', 'admin'],
                    'require_comment' => true
                ],
                [
                    'id' => 'reopen',
                    'from' => ['rejected'],
                    'to' => 'draft',
                    'label' => 'Reabrir',
                    'roles' => ['*'],
                    'require_comment' => false
                ]
            ],
            'settings' => [
                'initial_state' => 'draft',
                'log_transitions' => true
            ]
        ];
    }
}
