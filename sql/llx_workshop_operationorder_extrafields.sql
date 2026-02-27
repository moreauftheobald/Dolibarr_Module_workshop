CREATE TABLE llx_workshop_operationorder_extrafields (
  rowid       integer AUTO_INCREMENT PRIMARY KEY,
  tms         timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_object   integer NOT NULL
) ENGINE=innodb;
