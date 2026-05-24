-- Migration: Add indexes on frequently searched columns
-- These columns appear in WHERE/ORDER BY clauses from the API and CMS controllers.
-- Run this after creating the base tables.
--
-- Rollback:
-- DROP INDEX idx_admins_email ON admins;
-- DROP INDEX idx_admins_scode ON admins;
-- DROP INDEX idx_admins_token ON admins;
-- DROP INDEX idx_admins_rol ON admins;
-- DROP INDEX idx_pages_url ON pages;
-- DROP INDEX idx_pages_type ON pages;
-- DROP INDEX idx_modules_title ON modules;

-- admins table
ALTER TABLE admins
    ADD INDEX idx_admins_email  (email_admin),
    ADD INDEX idx_admins_scode  (scode_admin),
    ADD INDEX idx_admins_token  (token_admin(64)),
    ADD INDEX idx_admins_rol    (rol_admin);

-- pages table
ALTER TABLE pages
    ADD INDEX idx_pages_url    (url_page),
    ADD INDEX idx_pages_type   (type_page);

-- modules table
ALTER TABLE modules
    ADD INDEX idx_modules_title (title_module);
