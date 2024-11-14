CREATE TABLE llx_workshopaction (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  dated datetime DEFAULT NULL,
  datef datetime DEFAULT NULL,
  note_private longtext DEFAULT NULL,
  fullday varchar(255) DEFAULT NULL,
  fk_workshop integer NOT NULL DEFAULT 0,
  fk_user_author integer NOT NULL DEFAULT 0,
  entity integer NOT NULL DEFAULT 1
) ENGINE=innodb;
