CREATE TABLE llx_operationorder_status_target (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_operationorderstatus integer DEFAULT 0,
  fk_operationorderstatus_target integer DEFAULT 0
) ENGINE=innodb;
