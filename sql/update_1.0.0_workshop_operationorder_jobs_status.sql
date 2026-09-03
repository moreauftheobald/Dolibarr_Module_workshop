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

-- Ajout du statut de job (rowid d'un llx_workshop_operationorder_status de type OR + Job)
-- sur les installations existantes. L'erreur "colonne déjà existante" est tolérée par run_sql().
ALTER TABLE llx_workshop_operationorder_jobs ADD COLUMN status integer DEFAULT NULL AFTER fk_operationorder;
