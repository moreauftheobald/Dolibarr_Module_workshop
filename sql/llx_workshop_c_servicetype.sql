-- Copyright (C) 2025		SuperAdmin					<test@test.com>
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


CREATE TABLE llx_workshop_c_servicetype(
   rowid integer AUTO_INCREMENT PRIMARY KEY,
   date_creation datetime DEFAULT NULL,
   tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   code varchar(20) DEFAULT NULL,
   product_type integer DEFAULT NULL,
   group_type integer DEFAULT NULL,
   entity integer NOT NULL DEFAULT 1,
   active integer NOT NULL DEFAULT 0,
   label varchar(255) DEFAULT NULL
) ENGINE=innodb;
