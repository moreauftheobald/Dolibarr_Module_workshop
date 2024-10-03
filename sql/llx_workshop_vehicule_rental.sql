CREATE TABLE llx_workshop_vehicule_rental (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_vehicule integer DEFAULT 0,
  date_start datetime DEFAULT NULL,
  date_end datetime DEFAULT NULL,
  total_ht double DEFAULT 0,
  fk_soc integer DEFAULT 0,
  fk_proposaldet integer DEFAULT 0
) ENGINE=innodb;
