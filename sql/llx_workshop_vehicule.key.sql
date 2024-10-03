ALTER TABLE llx_workshop_vehicule ADD UNIQUE INDEX uk_workshop_vehicule_vin (vin);
ALTER TABLE llx_workshop_vehicule ADD UNIQUE INDEX uk_workshop_vehicule_immatriculation (immatriculation);

ALTER TABLE llx_workshop_vehicule ADD CONSTRAINT fk_workshop_vehicule_vehicule_type FOREIGN KEY (fk_vehicule_type) REFERENCES llx_workshop_vehicule_c_vehicule_type (rowid);
ALTER TABLE llx_workshop_vehicule ADD CONSTRAINT fk_workshop_vehicule_vehicule_mark FOREIGN KEY (fk_vehicule_mark) REFERENCES llx_workshop_vehicule_c_vehicule_mark (rowid);
ALTER TABLE llx_workshop_vehicule ADD CONSTRAINT fk_workshop_vehicule_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
ALTER TABLE llx_workshop_vehicule ADD CONSTRAINT fk_workshop_vehicule_contract_type FOREIGN KEY (fk_contract_type) REFERENCES llx_workshop_vehicule_c_contract_type (rowid);
