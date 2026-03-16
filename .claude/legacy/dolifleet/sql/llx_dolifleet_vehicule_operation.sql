CREATE TABLE llx_dolifleet_vehicule_operation (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_vehicule integer DEFAULT 0,
  fk_product integer DEFAULT 0,
  status integer NOT NULL DEFAULT 0,
  rang integer DEFAULT 0,
  km double DEFAULT 0,
  delai_from_last_op integer DEFAULT 0,
  date_done datetime DEFAULT NULL,
  km_done double DEFAULT 0,
  date_next datetime DEFAULT NULL,
  km_next double DEFAULT 0,
  on_time integer DEFAULT 0,
  or_next integer DEFAULT 0,
  date_due datetime DEFAULT NULL
)ENGINE=innodb;
