ALTER TABLE llx_workshopdet ADD INDEX idx_workshopdet_fk_operation_order (fk_operation_order);
ALTER TABLE llx_workshopdet ADD INDEX idx_workshopdet_fk_product (fk_product);

ALTER TABLE llx_workshopdet ADD CONSTRAINT fk_workshopdet_fk_operation_order FOREIGN KEY (fk_operation_order) REFERENCES llx_workshop (rowid);
ALTER TABLE llx_workshopdet ADD CONSTRAINT fk_workshopdet_fk_product FOREIGN KEY (fk_product) REFERENCES llx_product (rowid);
