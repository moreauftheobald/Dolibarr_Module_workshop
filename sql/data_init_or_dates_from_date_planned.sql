-- ----------------------------------------------------------------------------
-- Initialisation des champs date_start / date_end des OR à partir de date_planned
-- ----------------------------------------------------------------------------
-- À exécuter manuellement (phpMyAdmin) sur une base existante.
--
-- Règles :
--   1. Ne traite que les OR dont date_planned n'est pas vide.
--   2. date_start = date_planned, snappée à 00:00:00 du jour
--   3. date_end   = (date_planned + temps_immobilisation secondes), snappée à 23:59:59 du jour
--      Si temps_immobilisation est NULL ou 0 -> date_end = date_start (même jour, 23:59:59)
--
-- Le module utilise ensuite [date_start, date_end] pour afficher les barres
-- du Gantt dans le planning Atelier (workshop_planning.php).
-- ----------------------------------------------------------------------------

UPDATE llx_workshop_operationorder
SET
    date_start = DATE_FORMAT(date_planned, '%Y-%m-%d 00:00:00'),
    date_end   = CASE
                     WHEN temps_immobilisation IS NULL OR temps_immobilisation = 0
                         THEN DATE_FORMAT(date_planned, '%Y-%m-%d 23:59:59')
                     ELSE DATE_FORMAT(
                              DATE_ADD(date_planned, INTERVAL temps_immobilisation SECOND),
                              '%Y-%m-%d 23:59:59'
                          )
                 END
WHERE date_planned IS NOT NULL;
