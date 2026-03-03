-- Statuts des ordres de réparation — index et contraintes
ALTER TABLE llx_workshop_operationorder_status ADD UNIQUE INDEX uk_workshop_operationorder_status_code (code);
ALTER TABLE llx_workshop_operationorder_status ADD INDEX idx_workshop_operationorder_status_status (status);
