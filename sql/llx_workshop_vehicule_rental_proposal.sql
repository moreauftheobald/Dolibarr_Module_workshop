CREATE TABLE llx_workshop_vehicule_rental_proposal (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  month integer DEFAULT 0,
  year integer DEFAULT 0,
  fk_soc integer NOT NULL DEFAULT 0,
  status integer NOT NULL DEFAULT 0,
  fk_first_valid integer DEFAULT 0,
  date_first_valid datetime DEFAULT NULL,
  fk_second_valid integer DEFAULT 0,
  date_second_valid datetime DEFAULT NULL,
  ref varchar(50) DEFAULT NULL,
  entity integer NOT NULL DEFAULT 1,
  model_pdf varchar(255) DEFAULT NULL
)ENGINE=innodb;
