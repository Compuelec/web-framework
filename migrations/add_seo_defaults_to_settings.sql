-- Migration: Add SEO default settings
-- Description: Seeds three global SEO fallback keys into cms_settings
-- Date: 2026-05-22

INSERT IGNORE INTO `cms_settings` (`key_setting`, `value_setting`) VALUES
    ('seo_default_title',       '%page_title%'),
    ('seo_default_description', ''),
    ('seo_canonical_base_url',  '');

-- ROLLBACK:
-- DELETE FROM `cms_settings`
--   WHERE `key_setting` IN ('seo_default_title', 'seo_default_description', 'seo_canonical_base_url');
