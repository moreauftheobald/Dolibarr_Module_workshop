CREATE TABLE llx_workshop_vehicule_c_maintenance_operation (
  rowid         integer      AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime     DEFAULT NULL,
  tms           timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  code          varchar(20)  NOT NULL DEFAULT '',
  entity        integer      NOT NULL DEFAULT 1,
  active        integer      NOT NULL DEFAULT 1,
  label         varchar(255) DEFAULT NULL,
  fk_vehicule_type integer   DEFAULT NULL,
  fk_vehicule_mark integer   DEFAULT NULL
) ENGINE=innodb;
