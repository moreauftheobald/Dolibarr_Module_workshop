CREATE TABLE llx_workshoptasktime (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  label varchar(255) DEFAULT NULL,
  task_datehour_d datetime DEFAULT NULL,
  task_datehour_f varchar(255) DEFAULT NULL,
  task_duration integer DEFAULT 0,
  fk_user integer NOT NULL DEFAULT 0,
  fk_orDet integer NOT NULL DEFAULT 0,
  entity integer NOT NULL DEFAULT 0
) ENGINE=innodb;
