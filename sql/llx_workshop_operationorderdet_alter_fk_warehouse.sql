-- Change fk_warehouse from varchar(255) to integer FK to llx_entrepot
ALTER TABLE llx_workshop_operationorderdet
  MODIFY COLUMN fk_warehouse integer DEFAULT NULL;
