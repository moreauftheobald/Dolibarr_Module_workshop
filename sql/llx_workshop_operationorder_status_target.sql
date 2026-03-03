-- Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
--
-- Transitions autorisées entre statuts — centralisées sur entity 0 (partagées par toutes les entités)
-- Définit quels statuts sont accessibles depuis un statut donné
CREATE TABLE llx_workshop_operationorder_status_target (
  rowid                               integer AUTO_INCREMENT PRIMARY KEY,
  date_creation                       datetime DEFAULT NULL,
  tms                                 timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_workshop_operationorderstatus    integer NOT NULL,
  fk_workshop_operationorderstatus_target integer NOT NULL
) ENGINE=innodb;
