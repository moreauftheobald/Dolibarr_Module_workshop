-- Add temps_immobilisation and check_or fields to llx_workshop_operationorder
-- Safe to run multiple times (errors on duplicate columns are ignored by _load_tables)

ALTER TABLE llx_workshop_operationorder ADD COLUMN temps_immobilisation double DEFAULT 0;
ALTER TABLE llx_workshop_operationorder ADD COLUMN check_or integer DEFAULT 0;
