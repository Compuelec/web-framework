-- Migration: Add 2FA security fields to admins table
-- Adds expiration and attempt tracking for security codes
--
-- Run: ALTER TABLE admins ...
-- Rollback: ALTER TABLE admins DROP COLUMN scode_exp_admin, DROP COLUMN scode_attempts_admin;

ALTER TABLE admins
    ADD COLUMN scode_exp_admin INT NULL DEFAULT NULL COMMENT 'Security code expiration timestamp (Unix)',
    ADD COLUMN scode_attempts_admin TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Failed security code attempts counter';
