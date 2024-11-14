
CREATE TABLE llx_workshop_status_usergroup_rights (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_workshopstatus integer DEFAULT 0,
  code varchar(255) DEFAULT NULL,
  fk_usergroup integer DEFAULT 0
) ENGINE=innodb;
