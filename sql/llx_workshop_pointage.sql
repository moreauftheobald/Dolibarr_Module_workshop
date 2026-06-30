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

CREATE TABLE llx_workshop_pointage (
  rowid             integer      AUTO_INCREMENT PRIMARY KEY,
  date_creation     datetime     DEFAULT NULL,
  tms               timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user           integer      NOT NULL,
  fk_job            integer      DEFAULT NULL,
  fk_operationorder integer      DEFAULT NULL,
  type              varchar(32)  NOT NULL DEFAULT 'job',
  impro_code        varchar(64)  DEFAULT NULL,
  date_start        datetime     NOT NULL,
  date_end          datetime     DEFAULT NULL,
  note              varchar(255) DEFAULT NULL,
  fk_user_creat     integer      NOT NULL DEFAULT 0,
  fk_user_modif     integer      DEFAULT NULL,
  entity            integer      NOT NULL DEFAULT 1
) ENGINE=innodb;
