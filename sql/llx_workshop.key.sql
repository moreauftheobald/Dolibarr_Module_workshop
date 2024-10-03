ALTER TABLE llx_workshop ADD UNIQUE INDEX uk_workshop(ref);
ALTER TABLE llx_workshop ADD INDEX idx_workshop_fk_soc(fk_soc);
ALTER TABLE llx_workshop ADD INDEX idx_workshop_fk_vehicule(fk_vehicule);

ALTER TABLE llx_workshop ADD CONSTRAINT fk_workshop_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
