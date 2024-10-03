ALTER TABLE llx_workshopaction ADD INDEX idx_workshopaction_date (dated,datef);
ALTER TABLE llx_workshopaction ADD UNIQUE INDEX uk_workshopaction_fk_workshop (fk_workshop);
ALTER TABLE llx_workshopaction ADD CONSTRAINT fk_workshopaction_fk_workshop FOREIGN KEY (fk_workshop) REFERENCES llx_workshop (rowid);
