CREATE TABLE llx_dolifleet_vehicule_link (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  date_start datetime DEFAULT NULL,
  date_end datetime DEFAULT NULL,
  fk_source integer DEFAULT 0,
  fk_target integer DEFAULT 0,
  fk_soc_vehicule_source integer NOT NULL DEFAULT 0,
  fk_soc_vehicule_target integer NOT NULL DEFAULT 0
) ENGINE=innodb;
