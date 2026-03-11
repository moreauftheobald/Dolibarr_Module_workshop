--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--

--
-- Add fk_conducteur to operationorder if missing (table created before this column was added)
--
ALTER TABLE llx_workshop_operationorder ADD COLUMN fk_conducteur integer DEFAULT NULL;
