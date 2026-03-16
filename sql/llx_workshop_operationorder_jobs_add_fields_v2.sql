-- Add fk_job_type and fk_soc fields to llx_workshop_operationorder_jobs
-- Safe to run multiple times (errors on duplicate columns are ignored by _load_tables)

ALTER TABLE llx_workshop_operationorder_jobs ADD COLUMN fk_job_type integer DEFAULT NULL;
ALTER TABLE llx_workshop_operationorder_jobs ADD COLUMN fk_soc integer DEFAULT NULL;
