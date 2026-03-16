-- Copyright (C) 2025		SuperAdmin					<test@cest.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


-- BEGIN MODULEBUILDER INDEXES
ALTER TABLE llx_operationorder_productdefaultwarehouse ADD INDEX idx_operationorder_productdefaultwarehouse_rowid (rowid);
ALTER TABLE llx_operationorder_productdefaultwarehouse ADD INDEX idx_operationorder_productdefaultwarehouse_fk_product (fk_product);
ALTER TABLE llx_operationorder_productdefaultwarehouse ADD INDEX idx_operationorder_productdefaultwarehouse_fk_default_warehouse (fk_default_warehouse);
ALTER TABLE llx_product_default_warehouse ADD UNIQUE(fk_product,entity,fk_default_warehouse);
-- END MODULEBUILDER INDEXES

--ALTER TABLE llx_operationorder_productdefaultwarehouse ADD UNIQUE INDEX uk_operationorder_productdefaultwarehouse_fieldxy(fieldx, fieldy);

--ALTER TABLE llx_operationorder_productdefaultwarehouse ADD CONSTRAINT llx_operationorder_productdefaultwarehouse_fk_field FOREIGN KEY (fk_field) REFERENCES llx_operationorder_myotherobject(rowid);
