ALTER TABLE llx_workshop_vehicule_operation ADD UNIQUE uk_vehicule_operation_veh_prod (fk_vehicule,fk_product);

ALTER TABLE llx_workshop_vehicule_operation ADD CONSTRAINT fk_workshop_vehicule_operation_vehicule FOREIGN KEY (fk_vehicule) REFERENCES llx_workshop_vehicule (rowid);
ALTER TABLE llx_workshop_vehicule_operation ADD CONSTRAINT fk_workshop_vehicule_operation_product FOREIGN KEY (fk_product) REFERENCES llx_procduct (rowid);
