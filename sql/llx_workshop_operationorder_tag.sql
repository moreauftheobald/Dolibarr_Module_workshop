CREATE TABLE llx_workshop_operationorder_tag (
  rowid              integer  AUTO_INCREMENT PRIMARY KEY,
  fk_operationorder  integer  NOT NULL,
  fk_tag             integer  NOT NULL
) ENGINE=innodb;
