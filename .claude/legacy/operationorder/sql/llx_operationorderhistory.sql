CREATE TABLE llx_operationorderhistory (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_operationorder integer NOT NULL DEFAULT 0,
  fk_operationorderdet integer DEFAULT 0,
  title varchar(255) DEFAULT NULL,
  description longtext DEFAULT NULL,
  fk_user_creat integer NOT NULL DEFAULT 0,
  entity integer NOT NULL DEFAULT 1
) ENGINE=innodb;
