ALTER TABLE llx_operationordertasktime ADD INDEX idx_operationordertasktime_fk_user (fk_user);

ALTER TABLE llx_operationordertasktime ADD CONSTRAINT fk_operationordertasktime_fk_user FOREIGN KEY (fk_user) REFERENCES llx_user (rowid);
