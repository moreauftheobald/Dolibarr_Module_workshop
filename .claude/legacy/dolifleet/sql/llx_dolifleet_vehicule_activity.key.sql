ALTER TABLE llx_dolifleet_vehicule_activity ADD UNIQUE INDEX uk_dolifleet_vehicule_activity_soc_type_vh (fk_vehicule,fk_type,fk_soc);

ALTER TABLE llx_dolifleet_vehicule_activity ADD CONSTRAINT fk_dolifleet_vehicule_activity_vehicule FOREIGN KEY (fk_vehicule) REFERENCES llx_c_dolifleet_vehicule (rowid);
ALTER TABLE llx_dolifleet_vehicule_activity ADD CONSTRAINT fk_dolifleet_vehicule_activity_type FOREIGN KEY (fk_type) REFERENCES llx_c_dolifleet_activity_type (rowid);
ALTER TABLE llx_dolifleet_vehicule_activity ADD CONSTRAINT fk_dolifleet_vehicule_activity_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);



