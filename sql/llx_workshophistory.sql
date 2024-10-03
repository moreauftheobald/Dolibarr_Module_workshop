CREATE TABLE llx_workshophistory (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_workshop integer NOT NULL DEFAULT 0,
  fk_workshopdet integer DEFAULT 0,
  title varchar(255) DEFAULT NULL,
  description longtext DEFAULT NULL,
  fk_user_creat integer NOT NULL DEFAULT 0,
  entity integer NOT NULL DEFAULT 1
) ENGINE=innodb;
