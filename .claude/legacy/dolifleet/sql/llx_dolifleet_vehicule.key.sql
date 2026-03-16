ALTER TABLE llx_dolifleet_vehicule ADD UNIQUE INDEX uk_dolifleet_vehicule_vin (vin);
ALTER TABLE llx_dolifleet_vehicule ADD UNIQUE INDEX uk_dolifleet_vehicule_immatriculation (immatriculation);

ALTER TABLE llx_dolifleet_vehicule ADD CONSTRAINT fk_dolifleet_vehicule_vehicule_type FOREIGN KEY (fk_vehicule_type) REFERENCES llx_c_dolifleet_vehicule_type (rowid);
ALTER TABLE llx_dolifleet_vehicule ADD CONSTRAINT fk_dolifleet_vehicule_vehicule_mark FOREIGN KEY (fk_vehicule_mark) REFERENCES llx_c_dolifleet_vehicule_mark (rowid);
ALTER TABLE llx_dolifleet_vehicule ADD CONSTRAINT fk_dolifleet_vehicule_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
ALTER TABLE llx_dolifleet_vehicule ADD CONSTRAINT fk_dolifleet_vehicule_contract_type FOREIGN KEY (fk_contract_type) REFERENCES llx_c_dolifleet_contract_type (rowid);

