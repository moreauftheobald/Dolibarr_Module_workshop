-- Ajout des champs TVA et remise sur la MO sur les jobs d'OR
-- Safe to run multiple times (errors on duplicate columns are ignored by _load_tables)

ALTER TABLE llx_workshop_operationorder_jobs ADD COLUMN tva_tx_mo      double  DEFAULT NULL;
ALTER TABLE llx_workshop_operationorder_jobs ADD COLUMN tva_tx_st      double  DEFAULT NULL;
ALTER TABLE llx_workshop_operationorder_jobs ADD COLUMN remise_percent double  NOT NULL DEFAULT 0;
