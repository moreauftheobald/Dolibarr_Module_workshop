ALTER TABLE llx_operationorderaction ADD INDEX idx_operationorderaction_date (dated,datef);
ALTER TABLE llx_operationorderaction ADD UNIQUE INDEX uk_operationorderaction_fk_operationorder (fk_operationorder);
ALTER TABLE llx_operationorderaction ADD CONSTRAINT fk_operationorderaction_fk_operationorder FOREIGN KEY (fk_operationorder) REFERENCES llx_operationorder (rowid);
