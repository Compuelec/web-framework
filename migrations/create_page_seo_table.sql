-- Migration: Create page_seo table
-- Description: Per-page SEO metadata (slug, meta tags, Open Graph fields)
-- Date: 2026-05-22

CREATE TABLE IF NOT EXISTS `page_seo` (
    `id_seo`            INT           NOT NULL AUTO_INCREMENT,
    `id_page_seo`       INT           NOT NULL,
    `slug_seo`          VARCHAR(200)  NOT NULL,
    `meta_title_seo`    VARCHAR(60)   NULL DEFAULT NULL,
    `meta_desc_seo`     VARCHAR(160)  NULL DEFAULT NULL,
    `og_title_seo`      VARCHAR(100)  NULL DEFAULT NULL,
    `og_desc_seo`       VARCHAR(200)  NULL DEFAULT NULL,
    `og_image_seo`      VARCHAR(500)  NULL DEFAULT NULL,
    `og_type_seo`       VARCHAR(50)   NOT NULL DEFAULT 'website',
    `date_created_seo`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated_seo`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_seo`),
    UNIQUE KEY `uk_slug` (`slug_seo`),
    UNIQUE KEY `uk_page` (`id_page_seo`),
    INDEX      `idx_page` (`id_page_seo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ROLLBACK:
-- DROP TABLE IF EXISTS `page_seo`;
