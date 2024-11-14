CREATE TABLE llx_workshop_status_target (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_workshopstatus integer DEFAULT 0,
  fk_workshopstatus_target integer DEFAULT 0
) ENGINE=innodb;
