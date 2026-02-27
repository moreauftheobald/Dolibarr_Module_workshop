CREATE TABLE llx_workshop_c_servicetype (
  rowid         integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime     DEFAULT NULL,
  tms           timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  code          varchar(20)  DEFAULT NULL,
  active        integer NOT NULL DEFAULT 1,
  label         varchar(255) DEFAULT NULL,
  group_type    integer      DEFAULT 0,
  prix_mo       double       DEFAULT 0
) ENGINE=innodb;
