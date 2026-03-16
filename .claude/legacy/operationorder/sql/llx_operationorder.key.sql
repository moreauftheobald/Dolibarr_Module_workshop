ALTER TABLE llx_operationorder ADD UNIQUE INDEX uk_operationorder(ref);
ALTER TABLE llx_operationorder ADD INDEX idx_operationorder_fk_soc(fk_soc);
ALTER TABLE llx_operationorder ADD INDEX idx_operationorder_fk_vehicule(fk_vehicule);

ALTER TABLE llx_operationorder ADD CONSTRAINT fk_operationorder_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
