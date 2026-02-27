ALTER TABLE llx_workshop_operationorder ADD UNIQUE INDEX uk_workshop_operationorder_ref (ref, entity);
ALTER TABLE llx_workshop_operationorder ADD INDEX idx_workshop_operationorder_fk_soc (fk_soc);
ALTER TABLE llx_workshop_operationorder ADD INDEX idx_workshop_operationorder_fk_vehicule (fk_vehicule);
ALTER TABLE llx_workshop_operationorder ADD INDEX idx_workshop_operationorder_status (status);

ALTER TABLE llx_workshop_operationorder ADD CONSTRAINT fk_workshop_operationorder_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
ALTER TABLE llx_workshop_operationorder ADD CONSTRAINT fk_workshop_operationorder_vehicule FOREIGN KEY (fk_vehicule) REFERENCES llx_workshop_vehicule (rowid);
