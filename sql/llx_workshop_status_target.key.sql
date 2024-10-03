ALTER TABLE llx_workshop_status_target ADD INDEX idx_workshop_status_target_odst (fk_workshopstatus);
ALTER TABLE llx_workshop_status_target ADD INDEX idx_workshop_status_target_odst_target (fk_usergroup);

ALTER TABLE llx_workshop_status_target ADD CONSTRAINT fk_workshop_status_target_odst FOREIGN KEY (fk_workshopstatus) REFERENCES llx_workshop_status (rowid);
ALTER TABLE llx_workshop_status_target ADD CONSTRAINT fk_workshop_status_target_odst_target FOREIGN KEY (fk_workshopstatus_target) REFERENCES llx_workshop_status (rowid);
