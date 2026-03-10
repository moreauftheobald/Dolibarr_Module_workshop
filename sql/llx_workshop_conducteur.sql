CREATE TABLE llx_workshop_conducteur (
  rowid         integer      AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime     DEFAULT NULL,
  tms           timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  nom           varchar(100) NOT NULL DEFAULT '',
  prenom        varchar(100) NOT NULL DEFAULT '',
  fk_soc        integer      DEFAULT NULL,
  fk_user_creat integer      DEFAULT NULL,
  fk_user_modif integer      DEFAULT NULL
) ENGINE=innodb;
