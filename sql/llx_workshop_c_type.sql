CREATE TABLE llx_workshop_c_type (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  code varchar(30) DEFAULT NULL,
  label varchar(255) DEFAULT NULL,
  fk_soc integer DEFAULT NULL,
  position varchar(255) DEFAULT NULL,
  active varchar(255) DEFAULT NULL,
  entity varchar(255) DEFAULT NULL,
  blocked_status_code varchar(255) DEFAULT NULL
) ENGINE=innodb;
