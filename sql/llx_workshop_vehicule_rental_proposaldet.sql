CREATE TABLE llx_workshop_vehicule_rental_proposaldet (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_vehicule integer DEFAULT 0,
  fk_rental_proposal integer DEFAULT 0,
  total_ht double DEFAULT 0,
  description longtext DEFAULT NULL
)ENGINE=innodb;
