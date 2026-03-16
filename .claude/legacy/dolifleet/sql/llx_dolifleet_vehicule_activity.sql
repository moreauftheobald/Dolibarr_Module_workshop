CREATE TABLE llx_dolifleet_vehicule_activity (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_vehicule integer DEFAULT 0,
  fk_type varchar(255) DEFAULT NULL,
  date_start datetime DEFAULT NULL,
  date_end datetime DEFAULT NULL,
  fk_soc integer NOT NULL DEFAULT 0
)ENGINE=innodb;
