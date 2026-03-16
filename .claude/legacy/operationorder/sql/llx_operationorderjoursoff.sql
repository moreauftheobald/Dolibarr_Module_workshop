CREATE TABLE llx_operationorderjoursoff (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  date datetime DEFAULT NULL,
  label longtext DEFAULT NULL,
  fk_user_author integer NOT NULL DEFAULT 0,
  entity integer NOT NULL DEFAULT 1
) ENGINE=innodb;
