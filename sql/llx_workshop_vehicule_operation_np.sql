CREATE TABLE llx_workshop_vehicule_operation_np (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_vehicule integer NOT NULL DEFAULT 0,
  fk_product integer DEFAULT 0
) ENGINE=innodb;
