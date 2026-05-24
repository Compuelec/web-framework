-- Migration: Add conditions_column to columns table
-- This enables conditional field visibility based on other field values
-- Date: 2026-01-02

ALTER TABLE columns ADD COLUMN conditions_column TEXT NULL DEFAULT NULL AFTER matrix_column;

-- Structure of conditions_column (JSON):
-- {
--   "operator": "and",  -- "and" or "or"
--   "rules": [
--     {"field": "field_name", "operator": "equals", "value": "some_value"},
--     {"field": "other_field", "operator": "not_empty", "value": null}
--   ]
-- }
--
-- Supported operators:
-- - equals: field value equals the specified value
-- - not_equals: field value does not equal the specified value
-- - empty: field value is empty or null
-- - not_empty: field value is not empty
