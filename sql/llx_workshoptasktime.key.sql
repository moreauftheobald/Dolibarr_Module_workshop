ALTER TABLE llx_workshoptasktime ADD INDEX idx_workshoptasktime_fk_user (fk_user);

ALTER TABLE llx_workshoptasktime ADD CONSTRAINT fk_workshoptasktime_fk_user FOREIGN KEY (fk_user) REFERENCES llx_user (rowid);
