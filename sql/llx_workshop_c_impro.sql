-- Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
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
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.

CREATE TABLE llx_workshop_c_impro (
  rowid         integer      AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime     DEFAULT NULL,
  tms           timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  code          varchar(64)  NOT NULL DEFAULT '',
  label         varchar(255) DEFAULT NULL,
  is_absence    integer      NOT NULL DEFAULT 0,
  active        integer      NOT NULL DEFAULT 1,
  entity        integer      NOT NULL DEFAULT 1
) ENGINE=innodb;
