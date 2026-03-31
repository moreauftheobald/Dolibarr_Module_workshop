-- Table de liaison : un Job peut être rattaché à N opérations de maintenance véhicule
CREATE TABLE IF NOT EXISTS llx_workshop_operationorder_jobs_maintenance (
    fk_job                integer NOT NULL,
    fk_vehicule_operation integer NOT NULL,
    PRIMARY KEY (fk_job, fk_vehicule_operation),
    INDEX idx_jm_job (fk_job),
    INDEX idx_jm_vo  (fk_vehicule_operation)
) ENGINE=innodb;
