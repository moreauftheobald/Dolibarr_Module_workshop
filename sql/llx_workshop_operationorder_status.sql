-- Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
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
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.

-- Statuts des ordres de réparation — toujours sur entity = 0 (partagé entre toutes les entités)
CREATE TABLE llx_workshop_operationorder_status (
  rowid         integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms           timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  entity        integer NOT NULL DEFAULT 0,
  code          varchar(128) NOT NULL DEFAULT '',
  label         varchar(128) NOT NULL DEFAULT '',
  color         varchar(16) NOT NULL DEFAULT '#3c8dbc',
  rang          integer DEFAULT 0,
  status        smallint NOT NULL DEFAULT 1,
  planable      tinyint(1) DEFAULT 0,
  import_key    varchar(14) DEFAULT NULL
) ENGINE=innodb;
