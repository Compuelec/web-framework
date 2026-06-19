-- Migration: Create CMS settings table
-- Description: Key-value store for global CMS configuration (theme, preferences)
-- Date: 2026-03-30

CREATE TABLE IF NOT EXISTS `cms_settings` (
    `id_setting`          INT          NOT NULL AUTO_INCREMENT,
    `key_setting`         VARCHAR(100) NOT NULL,
    `value_setting`       TEXT         NULL,
    `date_updated_setting` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_setting`),
    UNIQUE KEY `uk_key` (`key_setting`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default theme
INSERT IGNORE INTO `cms_settings` (`key_setting`, `value_setting`) VALUES
    ('theme_primary',         '#6c5ffc'),
    ('theme_sidebar_bg',      '#ffffff'),
    ('theme_active_bg',       '#eff6ff'),
    ('theme_active_color',    '#1e40af'),
    ('theme_active_border',   '#3b82f6');

-- Rollback:
-- DROP TABLE IF EXISTS `cms_settings`;
