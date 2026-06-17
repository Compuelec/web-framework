-- Migration: Add notifications table
-- Description: Creates notifications table for the CMS notification system
-- Date: 2024-01-XX

CREATE TABLE IF NOT EXISTS notifications (
    id_notification INT NOT NULL AUTO_INCREMENT,
    id_admin_notification INT NULL DEFAULT NULL,
    title_notification TEXT NULL DEFAULT NULL,
    message_notification TEXT NULL DEFAULT NULL,
    type_notification VARCHAR(50) NULL DEFAULT 'info',
    icon_notification VARCHAR(100) NULL DEFAULT 'bi-info-circle',
    url_notification TEXT NULL DEFAULT NULL,
    read_notification INT NULL DEFAULT '0',
    date_created_notification TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_updated_notification TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_notification),
    INDEX idx_admin (id_admin_notification),
    INDEX idx_read (read_notification),
    INDEX idx_created (date_created_notification)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

