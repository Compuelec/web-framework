-- =============================================
-- RBAC System Migration
-- Adds roles table and links admins to roles
-- =============================================

-- Create roles table
CREATE TABLE IF NOT EXISTS `roles` (
    `id_role`           INT          NOT NULL AUTO_INCREMENT,
    `name_role`         VARCHAR(100) NOT NULL,
    `description_role`  VARCHAR(255) NULL     DEFAULT NULL,
    `permissions_role`  TEXT         NULL     DEFAULT NULL COMMENT 'JSON: {page_url: {read:1, create:1, update:1, delete:1}}',
    `date_created_role` DATE         NULL     DEFAULT NULL,
    PRIMARY KEY (`id_role`),
    UNIQUE KEY `unique_name_role` (`name_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add role_id column to admins table
ALTER TABLE `admins`
    ADD COLUMN `id_role_admin` INT NULL DEFAULT NULL
        COMMENT 'FK to roles table. When set, RBAC permissions override permissions_admin'
    AFTER `permissions_admin`;

-- =============================================
-- ROLLBACK
-- =============================================
-- ALTER TABLE `admins` DROP COLUMN `id_role_admin`;
-- DROP TABLE IF EXISTS `roles`;
