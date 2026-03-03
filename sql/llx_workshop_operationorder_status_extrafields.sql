-- Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
--
-- Extra fields table for workshop operation order status
CREATE TABLE llx_workshop_operationorder_status_extrafields (
  rowid     integer AUTO_INCREMENT PRIMARY KEY,
  tms       timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_object integer NOT NULL
) ENGINE=innodb;
