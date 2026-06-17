-- Migration from version 1.0.0 to 1.0.1
-- Adds activity logs system

-- Create activity_logs table if it doesn't exist
CREATE TABLE IF NOT EXISTS activity_logs ( 
    id_log INT NOT NULL AUTO_INCREMENT,
    action_log TEXT NULL DEFAULT NULL,
    entity_log TEXT NULL DEFAULT NULL,
    entity_id_log INT NULL DEFAULT NULL,
    description_log TEXT NULL DEFAULT NULL,
    admin_id_log INT NULL DEFAULT NULL,
    ip_address_log TEXT NULL DEFAULT NULL,
    user_agent_log TEXT NULL DEFAULT NULL,
    date_created_log DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id_log),
    INDEX idx_admin_id (admin_id_log),
    INDEX idx_entity (entity_log(50)),
    INDEX idx_action (action_log(50)),
    INDEX idx_date_created (date_created_log)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
