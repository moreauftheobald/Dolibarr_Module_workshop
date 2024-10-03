CREATE TABLE llx_workshop_vehicule_rental_matrix (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_soc integer NOT NULL DEFAULT 0,
  fk_c_type_vh varchar(255) DEFAULT NULL,
  fk_c_mark_vh varchar(255) DEFAULT NULL,
  delay integer DEFAULT 0,
  amount_ht double DEFAULT 0
)ENGINE=innodb;
