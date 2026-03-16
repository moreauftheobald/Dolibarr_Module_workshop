-- Replace static group_type column with a FK to llx_workshop_job_type
-- Safe to run multiple times (errors on existing column are ignored by _load_tables)

ALTER TABLE llx_workshop_c_servicetype ADD COLUMN fk_job_type integer DEFAULT NULL;
