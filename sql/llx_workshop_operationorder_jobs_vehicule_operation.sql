-- ============================================================
-- Table de liaison : Job ↔ VehiculeOperation (1 job → n opérations)
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_workshop_operationorder_jobs_vehicule_operation
(
    fk_job                integer NOT NULL,
    fk_vehicule_operation integer NOT NULL,
    PRIMARY KEY (fk_job, fk_vehicule_operation),
    INDEX idx_jvo_job  (fk_job),
    INDEX idx_jvo_vo   (fk_vehicule_operation)
) ENGINE=innodb;
