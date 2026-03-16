ALTER TABLE llx_dolifleet_vehicule_link ADD CONSTRAINT fk_dolifleet_vehicule_link_source FOREIGN KEY (fk_source) REFERENCES llx_dolifleet_vehicule (rowid);
ALTER TABLE llx_dolifleet_vehicule_link ADD CONSTRAINT fk_dolifleet_vehicule_link_target FOREIGN KEY (fk_target) REFERENCES llx_dolifleet_vehicule (rowid);
ALTER TABLE llx_dolifleet_vehicule_link ADD CONSTRAINT fk_dolifleet_vehicule_link_soc_vehicule_source FOREIGN KEY (fk_soc_vehicule_source) REFERENCES llx_societe (rowid);
ALTER TABLE llx_dolifleet_vehicule_link ADD CONSTRAINT fk_dolifleet_vehicule_link_soc_vehicule_target FOREIGN KEY (fk_soc_vehicule_target) REFERENCES llx_societe (rowid);
