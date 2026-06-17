-- Migration: Create workflows table for workflow system
-- This enables configurable state management per table/module
-- Date: 2026-01-02

CREATE TABLE IF NOT EXISTS workflows (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Structure of states_workflow (JSON array):
-- [
--   {"id": "draft", "label": "Borrador", "color": "#6c757d"},
--   {"id": "pending", "label": "Pendiente", "color": "#ffc107"},
--   {"id": "approved", "label": "Aprobado", "color": "#28a745"},
--   {"id": "rejected", "label": "Rechazado", "color": "#dc3545"}
-- ]

-- Structure of transitions_workflow (JSON array):
-- [
--   {
--     "id": "submit",
--     "from": ["draft"],
--     "to": "pending",
--     "label": "Enviar a revision",
--     "roles": ["admin", "editor"],
--     "require_comment": false
--   }
-- ]

-- Structure of settings_workflow (JSON object):
-- {
--   "initial_state": "draft",
--   "log_transitions": true
-- }
