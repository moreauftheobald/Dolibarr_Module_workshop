-- ----------------------------------------------------------------------------
-- Initialisation des champs date_start / date_end des OR à partir de date_planned
-- ----------------------------------------------------------------------------
-- À exécuter manuellement (phpMyAdmin) sur une base existante.
--
-- Règles :
--   1. Ne traite que les OR dont date_planned n'est pas vide.
--   2. date_start = date_planned
--   3. date_end   = date_planned + temps_immobilisation (en secondes)
--      Si temps_immobilisation est NULL ou 0 → date_end = date_start.
--
-- Le module utilise ensuite [date_start, date_end] pour afficher les barres
-- du Gantt dans le planning Atelier (workshop_planning.php).
-- ----------------------------------------------------------------------------

UPDATE llx_workshop_operationorder
SET
    date_start = date_planned,
    date_end   = CASE
                     WHEN temps_immobilisation IS NULL OR temps_immobilisation = 0
                         THEN date_planned
                     ELSE DATE_ADD(date_planned, INTERVAL temps_immobilisation SECOND)
                 END
WHERE date_planned IS NOT NULL;
