CREATE TABLE llx_workshop_c_servicetype (
  rowid         integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime     DEFAULT NULL,
  tms           timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  code          varchar(20)  DEFAULT NULL,
  active        integer NOT NULL DEFAULT 1,
  label         varchar(255) DEFAULT NULL,
  fk_job_type   integer      DEFAULT NULL,
  prix_mo       double       DEFAULT 0,
  fk_soc        integer DEFAULT NULL,
  plannable      integer NOT NULL DEFAULT 0,
  tva_tx_mo      double  DEFAULT NULL,
  tva_tx_st      double  DEFAULT NULL,
  doc_obl       varchar(255) DEFAULT NULL
) ENGINE=innodb;
