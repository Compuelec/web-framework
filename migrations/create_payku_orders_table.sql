-- Migration: Create Payku Orders Table
-- Description: Creates the payku_orders table for storing payment transactions
-- Date: 2024-01-01

CREATE TABLE IF NOT EXISTS payku_orders (
    id_order INT NOT NULL AUTO_INCREMENT,
    order_id VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'CLP',
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    transaction_id VARCHAR(255) NULL,
    payment_key VARCHAR(255) NULL,
    transaction_key VARCHAR(255) NULL,
    verification_key VARCHAR(255) NULL,
    payku_response TEXT NULL,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_order),
    UNIQUE KEY unique_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_date_created (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

