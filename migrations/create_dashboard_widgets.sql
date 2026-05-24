-- Migration: Create dashboard_widgets table
-- Description: Stores per-admin widget layout for the configurable dashboard

CREATE TABLE IF NOT EXISTS `dashboard_widgets` (
    `id_widget`           INT          NOT NULL AUTO_INCREMENT,
    `id_admin_widget`     INT          NOT NULL DEFAULT 0,
    `type_widget`         VARCHAR(50)  NOT NULL DEFAULT 'metric',
    `title_widget`        TEXT         NULL,
    `config_widget`       TEXT         NULL,
    `position_widget`     INT          NULL DEFAULT 0,
    `width_widget`        VARCHAR(20)  NULL DEFAULT 'col-md-4',
    `refresh_widget`      INT          NULL DEFAULT 0,
    `date_created_widget` DATE         NULL,
    PRIMARY KEY (`id_widget`),
    INDEX `idx_admin_position` (`id_admin_widget`, `position_widget`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ROLLBACK:
-- DROP TABLE IF EXISTS `dashboard_widgets`;
