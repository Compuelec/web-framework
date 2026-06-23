-- Visual configuration: which tables/columns hold personal data
-- (auto-created by the controller; here for documentation).
CREATE TABLE IF NOT EXISTS dp_datasets (
    id_dataset INT NOT NULL AUTO_INCREMENT,
    table_dataset VARCHAR(64) NOT NULL,
    label_dataset VARCHAR(160) NULL,
    pk_dataset VARCHAR(64) NULL,                   -- unique key column
    subject_keys_dataset TEXT NULL,                -- JSON array: columns that identify a subject (email/RUT)
    fields_dataset TEXT NULL,                      -- JSON array: personal-data columns
    sensitive_dataset TEXT NULL,                   -- JSON array: sensitive columns
    anonymize_dataset TEXT NULL,                   -- JSON object {column: null|redact|hash}
    purpose_dataset TEXT NULL,
    legal_basis_dataset VARCHAR(160) NULL,
    retention_days_dataset INT NULL,
    date_created_dataset DATETIME NULL,
    date_updated_dataset TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_dataset),
    UNIQUE KEY uq_table_dataset (table_dataset)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
