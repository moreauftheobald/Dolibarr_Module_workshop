CREATE TABLE llx_c_operationorder_tag (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  code varchar(30) DEFAULT NULL,
  label varchar(255) DEFAULT NULL,
  color varchar(255) DEFAULT NULL,
  position integer DEFAULT 0,
  active integer DEFAULT 0,
  entity integer DEFAULT 0
) ENGINE=innodb;
