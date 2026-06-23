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

ALTER TABLE llx_workshop_pointage ADD INDEX idx_workshop_pointage_user_date (fk_user, date_start);
ALTER TABLE llx_workshop_pointage ADD INDEX idx_workshop_pointage_job (fk_job);
ALTER TABLE llx_workshop_pointage ADD INDEX idx_workshop_pointage_open (fk_user, date_end);
ALTER TABLE llx_workshop_pointage ADD INDEX idx_workshop_pointage_or (fk_operationorder);

ALTER TABLE llx_workshop_pointage ADD CONSTRAINT fk_workshop_pointage_fk_user FOREIGN KEY (fk_user) REFERENCES llx_user (rowid);
