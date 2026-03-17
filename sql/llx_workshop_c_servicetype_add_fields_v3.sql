-- Fusion ServiceType + JobType : ajout des champs de job_type dans c_servicetype
-- + taux TVA MO et sous-traitance
-- Safe to run multiple times (errors on duplicate columns are ignored by _load_tables)

ALTER TABLE llx_workshop_c_servicetype ADD COLUMN fk_soc        integer DEFAULT NULL;
ALTER TABLE llx_workshop_c_servicetype ADD COLUMN plannable      integer NOT NULL DEFAULT 0;
ALTER TABLE llx_workshop_c_servicetype ADD COLUMN tva_tx_mo      double  DEFAULT NULL;
ALTER TABLE llx_workshop_c_servicetype ADD COLUMN tva_tx_st      double  DEFAULT NULL;
