ALTER TABLE llx_workshop_vehicule_rental_matrix ADD UNIQUE matrix_unicity (fk_soc, fk_c_type_vh, fk_c_mark_vh, delay);

ALTER TABLE llx_workshop_vehicule_rental_matrix ADD CONSTRAINT fk_workshop_vehicule_rental_matrix_vh_type FOREIGN KEY (fk_c_type_vh) REFERENCES llx_workshop_vehicule_c_vehicule_type (rowid);
ALTER TABLE llx_workshop_vehicule_rental_matrix ADD CONSTRAINT fk_workshop_vehicule_rental_matrix_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
ALTER TABLE llx_workshop_vehicule_rental_matrix ADD CONSTRAINT fk_workshop_vehicule_rental_matrix_vh_mark FOREIGN KEY (fk_c_mark_vh) REFERENCES llx_workshop_vehicule_c_vehicule_mark (rowid);
