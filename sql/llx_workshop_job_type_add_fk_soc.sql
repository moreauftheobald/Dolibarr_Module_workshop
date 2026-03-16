-- Add default billing third-party to job type
-- Safe to run multiple times (errors on existing column are ignored by _load_tables)

ALTER TABLE llx_workshop_job_type ADD COLUMN fk_soc integer DEFAULT NULL;
