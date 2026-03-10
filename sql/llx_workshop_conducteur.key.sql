ALTER TABLE llx_workshop_conducteur ADD INDEX idx_workshop_conducteur_nom (nom);
ALTER TABLE llx_workshop_conducteur ADD INDEX idx_workshop_conducteur_fk_soc (fk_soc);

ALTER TABLE llx_workshop_conducteur ADD CONSTRAINT fk_workshop_conducteur_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
ALTER TABLE llx_workshop_conducteur ADD CONSTRAINT fk_workshop_conducteur_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user (rowid);
