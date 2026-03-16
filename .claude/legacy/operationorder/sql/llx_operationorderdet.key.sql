ALTER TABLE llx_operationorderdet ADD INDEX idx_operationorderdet_fk_operation_order (fk_operation_order);
ALTER TABLE llx_operationorderdet ADD INDEX idx_operationorderdet_fk_product (fk_product);

ALTER TABLE llx_operationorderdet ADD CONSTRAINT fk_operationorderdet_fk_operation_order FOREIGN KEY (fk_operation_order) REFERENCES llx_operationorder (rowid);
ALTER TABLE llx_operationorderdet ADD CONSTRAINT fk_operationorderdet_fk_product FOREIGN KEY (fk_product) REFERENCES llx_product (rowid);
