ALTER TABLE llx_workshop_vehicule_rental_proposal ADD INDEX idx_workshop_vehicule_rental_proposal_ref (ref);
ALTER TABLE llx_workshop_vehicule_rental_proposal ADD UNIQUE INDEX uk_workshop_vehicule_rental_proposal_date (month, year, fk_soc,entity);

ALTER TABLE llx_workshop_vehicule_rental_proposal ADD CONSTRAINT fk_workshop_vehicule_rental_proposal FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
