ALTER TABLE llx_workshop_operationorder_tag ADD UNIQUE INDEX uk_workshop_operationorder_tag (fk_operationorder, fk_tag);
ALTER TABLE llx_workshop_operationorder_tag ADD INDEX idx_workshop_operationorder_tag_fk_tag (fk_tag);

ALTER TABLE llx_workshop_operationorder_tag ADD CONSTRAINT fk_workshop_or_tag_operationorder FOREIGN KEY (fk_operationorder) REFERENCES llx_workshop_operationorder (rowid);
ALTER TABLE llx_workshop_operationorder_tag ADD CONSTRAINT fk_workshop_or_tag_tag FOREIGN KEY (fk_tag) REFERENCES llx_workshop_tag (rowid);
