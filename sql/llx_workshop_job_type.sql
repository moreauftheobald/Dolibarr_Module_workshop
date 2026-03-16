CREATE TABLE llx_workshop_job_type (
  rowid         integer      AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime     DEFAULT NULL,
  tms           timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  code          varchar(20)  NOT NULL DEFAULT '',
  label         varchar(255) NOT NULL DEFAULT '',
  plannable     integer      NOT NULL DEFAULT 0,
  active        integer      NOT NULL DEFAULT 1,
  fk_user_creat integer      DEFAULT NULL,
  fk_user_modif integer      DEFAULT NULL
) ENGINE=innodb;
