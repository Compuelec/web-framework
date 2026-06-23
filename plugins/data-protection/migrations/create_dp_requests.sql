-- ARCOP request log for the data-protection plugin (auto-created by the controller).
CREATE TABLE IF NOT EXISTS dp_requests (
    id_request INT NOT NULL AUTO_INCREMENT,
    type_request VARCHAR(20) NOT NULL,            -- access|rectification|cancellation|opposition|portability|blocking
    subject_request TEXT NULL,                    -- subject identifier (email/RUT/name)
    channel_request VARCHAR(40) NULL,             -- web|email|presencial
    status_request VARCHAR(20) NOT NULL DEFAULT 'pending',
    notes_request TEXT NULL,
    handler_request INT NULL,                     -- admin id
    due_request DATE NULL,                        -- legal response deadline
    date_created_request DATETIME NULL,
    date_updated_request TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_request)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
