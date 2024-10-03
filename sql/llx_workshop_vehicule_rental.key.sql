ALTER TABLE llx_workshop_vehicule_rental ADD CONSTRAINT fk_workshop_vehicule_rental_vehicule FOREIGN KEY (fk_vehicule) REFERENCES llx_workshop_vehicule (rowid);
ALTER TABLE llx_workshop_vehicule_rental ADD CONSTRAINT fk_workshop_vehicule_rental_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
ALTER TABLE llx_workshop_vehicule_rental ADD CONSTRAINT fk_workshop_vehicule_rental_proposaldet FOREIGN KEY (fk_proposaldet) REFERENCES llx_workshop_vehicule_rental_proposaldet (rowid);
