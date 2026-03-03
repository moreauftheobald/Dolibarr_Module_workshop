-- Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
--
-- Droits des groupes d'utilisateurs par statut ET par entité
-- Permet de définir, pour chaque entité, quels groupes peuvent :
--   read              : consulter les ordres de réparation à ce statut
--   edit              : modifier les ordres de réparation à ce statut
--   changeToThisStatus: passer un ordre de réparation à ce statut
--
-- Les statuts sont partagés (entity=0) mais les droits sont définis par entité.
CREATE TABLE llx_workshop_operationorder_status_usergroup_rights (
  rowid                            integer AUTO_INCREMENT PRIMARY KEY,
  date_creation                    datetime DEFAULT NULL,
  tms                              timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_workshop_operationorderstatus integer NOT NULL,
  code                             varchar(64) NOT NULL DEFAULT '',
  fk_usergroup                     integer NOT NULL,
  entity                           integer NOT NULL DEFAULT 1
) ENGINE=innodb;
