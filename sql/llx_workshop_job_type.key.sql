ALTER TABLE llx_workshop_job_type ADD UNIQUE INDEX uk_workshop_job_type_code (code);
ALTER TABLE llx_workshop_job_type ADD INDEX idx_workshop_job_type_active (active);
ALTER TABLE llx_workshop_job_type ADD INDEX idx_workshop_job_type_plannable (plannable);
