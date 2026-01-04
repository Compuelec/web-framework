<?php

/**
 * Workflow Manager Controller
 * Handles workflow configuration operations
 */

// Load required controllers
require_once __DIR__ . '/../../../cms/controllers/install.controller.php';

class WorkflowManagerController {

    private $link;

    public function __construct() {
        $this->link = InstallController::connect();
        $this->ensureTableExists();
    }

    /**
     * Ensure the workflows table exists
     */
    private function ensureTableExists() {
        try {
            // Check if table exists
            $stmt = $this->link->query("SHOW TABLES LIKE 'workflows'");
            if ($stmt->rowCount() === 0) {
                // Create the table
                $sql = "CREATE TABLE workflows (
                    id_workflow INT NOT NULL AUTO_INCREMENT,
                    id_module_workflow INT NOT NULL,
                    title_workflow VARCHAR(255) NOT NULL,
                    states_workflow TEXT NOT NULL,
                    transitions_workflow TEXT NOT NULL,
                    settings_workflow TEXT NULL DEFAULT NULL,
                    date_created_workflow DATE NULL DEFAULT NULL,
                    date_updated_workflow TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_workflow),
                    UNIQUE KEY (id_module_workflow)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $this->link->exec($sql);
            }
        } catch (PDOException $e) {
            // Log error but don't fail - table might already exist
            error_log("WorkflowManager: Error ensuring table exists: " . $e->getMessage());
        }
    }

    /**
     * Get all modules that have workflow columns
     */
    public function getModulesWithWorkflow() {
        try {
            $sql = "SELECT DISTINCT m.id_module, m.title_module, m.suffix_module as alias_module, c.title_column as workflow_column
                    FROM modules m
                    INNER JOIN columns c ON c.id_module_column = m.id_module
                    WHERE c.type_column = 'workflow'
                    ORDER BY m.suffix_module";

            $stmt = $this->link->prepare($sql);
            $stmt->execute();

            $modules = $stmt->fetchAll(PDO::FETCH_OBJ);

            return ['success' => true, 'modules' => $modules];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get workflow configuration for a module
     */
    public function getWorkflow($moduleId) {
        try {
            if (empty($moduleId)) {
                return ['success' => false, 'error' => 'Module ID required'];
            }

            $sql = "SELECT * FROM workflows WHERE id_module_workflow = :module_id LIMIT 1";
            $stmt = $this->link->prepare($sql);
            $stmt->execute([':module_id' => $moduleId]);

            $workflow = $stmt->fetch(PDO::FETCH_OBJ);

            if ($workflow) {
                $workflow->states = json_decode($workflow->states_workflow);
                $workflow->transitions = json_decode($workflow->transitions_workflow);
                $workflow->settings = json_decode($workflow->settings_workflow);
            } else {
                // Return default workflow structure
                $workflow = $this->getDefaultWorkflow();
            }

            return ['success' => true, 'workflow' => $workflow];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Save workflow configuration
     */
    public function saveWorkflow($data) {
        try {
            $moduleId = $data['module_id'] ?? 0;
            $states = json_decode($data['states'] ?? '[]', true);
            $transitions = json_decode($data['transitions'] ?? '[]', true);
            $settings = json_decode($data['settings'] ?? '{}', true);

            if (empty($moduleId)) {
                return ['success' => false, 'error' => 'Module ID required'];
            }

            if (empty($states)) {
                return ['success' => false, 'error' => 'At least one state is required'];
            }

            // Validate states have required fields
            foreach ($states as $state) {
                if (empty($state['id']) || empty($state['label'])) {
                    return ['success' => false, 'error' => 'All states must have id and label'];
                }
            }

            // Check if workflow exists
            $checkSql = "SELECT id_workflow FROM workflows WHERE id_module_workflow = :module_id";
            $checkStmt = $this->link->prepare($checkSql);
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

            $stmt = $this->link->prepare($sql);
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

            $result = $stmt->execute($params);

            return ['success' => $result];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all available roles
     */
    public function getRoles() {
        try {
            $sql = "SELECT DISTINCT rol_admin FROM admins ORDER BY rol_admin";
            $stmt = $this->link->prepare($sql);
            $stmt->execute();

            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Add wildcard option
            array_unshift($roles, '*');

            return ['success' => true, 'roles' => $roles];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get default workflow structure
     */
    private function getDefaultWorkflow() {
        return (object) [
            'states' => [
                (object) ['id' => 'draft', 'label' => 'Borrador', 'color' => '#6c757d'],
                (object) ['id' => 'pending', 'label' => 'Pendiente', 'color' => '#ffc107'],
                (object) ['id' => 'approved', 'label' => 'Aprobado', 'color' => '#28a745'],
                (object) ['id' => 'rejected', 'label' => 'Rechazado', 'color' => '#dc3545']
            ],
            'transitions' => [
                (object) [
                    'id' => 'submit',
                    'from' => ['draft'],
                    'to' => 'pending',
                    'label' => 'Enviar a revision',
                    'roles' => ['*'],
                    'require_comment' => false
                ],
                (object) [
                    'id' => 'approve',
                    'from' => ['pending'],
                    'to' => 'approved',
                    'label' => 'Aprobar',
                    'roles' => ['superadmin', 'admin'],
                    'require_comment' => false
                ],
                (object) [
                    'id' => 'reject',
                    'from' => ['pending'],
                    'to' => 'rejected',
                    'label' => 'Rechazar',
                    'roles' => ['superadmin', 'admin'],
                    'require_comment' => true
                ],
                (object) [
                    'id' => 'reopen',
                    'from' => ['rejected'],
                    'to' => 'draft',
                    'label' => 'Reabrir',
                    'roles' => ['*'],
                    'require_comment' => false
                ]
            ],
            'settings' => (object) [
                'initial_state' => 'draft',
                'log_transitions' => true
            ]
        ];
    }
}
