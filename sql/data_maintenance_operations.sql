-- ============================================================================
-- Dictionnaire des opérations de maintenance véhicule
-- Table : llx_workshop_vehicule_c_maintenance_operation
--
-- Généré à partir de l'analyse des données dolifleet (fk_product → llx_product)
-- croisées avec les types et marques de véhicules.
--
-- Règle : fk_vehicule_type = NULL → tous types
--         fk_vehicule_mark = NULL → toutes marques
--
-- Colonne "Anciens product_id" en commentaire pour traçabilité migration
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Opérations universelles (tous types, toutes marques)
-- ---------------------------------------------------------------------------

-- product_id: 20492 (ref 27500)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (1, 'CTRL-LIMIT', 1, 1, 'Contrôle du limiteur de vitesse', NULL, NULL);

-- product_id: 20493 (ref 38307)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (2, 'CTRL-TACHY', 1, 1, 'Contrôle du tachygraphe', NULL, NULL);

-- product_id: 20490 (ref 98259)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (3, 'CT', 1, 1, 'Contrôle Technique', NULL, NULL);

-- product_id: 20491 (ref 99202), 4295 (ref 992022)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (4, 'CTRL-EXTINCT', 1, 1, 'Contrôle Annuel Extincteur', NULL, NULL);

-- product_id: 4292 (ref TM-CONT-TRT) — utilisé sur type 1+2
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (5, 'CTRL-ANN-TRT', 1, 1, 'Contrôles annuels tracteur', NULL, NULL);

-- ---------------------------------------------------------------------------
-- 2. Opérations tracteur+porteur, toutes marques (type=NULL, mark=NULL)
--    Maintenance moteur/mécanique commune aux types 1 et 2
-- ---------------------------------------------------------------------------

-- product_id: 20476 (ref 17554)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (6, 'VID-MOT', 1, 1, 'Vidange Moteur, filtre à carburant et séparateur', NULL, NULL);

-- product_id: 20487 (ref 17523)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (7, 'VID-BV', 1, 1, 'Remplacement de l''huile et du filtre boîte de vitesse', NULL, NULL);

-- product_id: 20488 (ref 17527)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (8, 'VID-PONT', 1, 1, 'Vidange huile de pont', NULL, NULL);

-- product_id: 20486 (ref 21414)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (9, 'REG-SOUP-INJ', 1, 1, 'Réglage soupapes et injecteurs-pompes', NULL, NULL);

-- product_id: 20477 (ref 25627)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (10, 'FILT-AIR', 1, 1, 'Remplacement Filtre à air', NULL, NULL);

-- product_id: 20478 (ref 26010)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (11, 'LIQ-REFROID', 1, 1, 'Remplacement du liquide de refroidissement', NULL, NULL);

-- product_id: 20479 (ref 56166)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (12, 'DESSIC-AIR', 1, 1, 'Remplacement de la cartouche du dessiccateur d''air', NULL, NULL);

-- product_id: 20481 (ref 25477)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (13, 'FILT-DPF', 1, 1, 'Remplacement de l''insert du filtre à particules DPF', NULL, NULL);

-- product_id: 20480 (ref 23414)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (14, 'FILT-RESERV', 1, 1, 'Remplacement du filtre pour aération de réservoir', NULL, NULL);

-- product_id: 20483 (ref 26307)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (15, 'COURR-VENT', 1, 1, 'Remplacement des courroie et tendeurs de ventilateur', NULL, NULL);

-- product_id: 20482 (ref 32123)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (16, 'COURR-ALTER', 1, 1, 'Remplacement des courroie et tendeurs d''alternateur', NULL, NULL);

-- product_id: 20484 (ref 32130)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (17, 'CHG-ALTER', 1, 1, 'Changement de l''alternateur', NULL, NULL);

-- product_id: 20485 (ref 32204)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (18, 'CHG-REGUL', 1, 1, 'Changement du régulateur de charge', NULL, NULL);

-- product_id: 20489 (ref 87208)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (19, 'FILT-CLIM', 1, 1, 'Remplacement du filtre à air pour climatiseur', NULL, NULL);

-- product_id: 20475 (ref 17746) — uniquement type 1/mark 2, mais opération générique
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (20, 'SERV-COMPLET', 1, 1, 'Service complet', NULL, NULL);

-- product_id: 4547 (ref 1752806) — uniquement type 1/mark 2
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (21, 'VID-RETARD', 1, 1, 'Retardeur vidange', NULL, NULL);

-- ---------------------------------------------------------------------------
-- 3. Opérations spécifiques IVECO (type=1 Tracteur, mark=11 IVECO)
-- ---------------------------------------------------------------------------

-- product_id: 21205 (ref (2) ENT_INT_IVECO), 21206 (ref ENT_INT_IVECO)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (22, 'ENT-INT-IVECO', 1, 1, 'Entretien intermédiaire IVECO', 1, 11);

-- product_id: 21220 (ref EO_VID_IVECO)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (23, 'ENT-150K-IVECO', 1, 1, 'Entretien des 150 000 kms IVECO', 1, 11);

-- product_id: 20928 (ref CA-ROLFO-PT)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (24, 'CA-ROLFO-PT', 1, 1, 'Campagne fixation poteau', 1, 11);

-- ---------------------------------------------------------------------------
-- 4. Opérations spécifiques Mercedes-Benz (type=1 Tracteur, mark=7)
-- ---------------------------------------------------------------------------

-- product_id: 20560 (ref VS-MB)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (25, 'VID-MOT-MB', 1, 1, 'Vidange moteur Mercedes', 1, 7);

-- product_id: 20561 (ref V-LR-MB)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (26, 'VID-LR-MB', 1, 1, 'Vidange liquide de refroidissement Mercedes', 1, 7);

-- product_id: 20516 (ref BAT-MERCO)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (27, 'BAT-MB', 1, 1, 'Remplacement des batteries Mercedes', 1, 7);

-- product_id: 21043 (ref CONT-ETHYLOTEST)
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (28, 'CTRL-ETHYL', 1, 1, 'Contrôle Annuel Ethylotest', 1, 7);

-- ---------------------------------------------------------------------------
-- 5. Opérations Porteur (type=2)
-- ---------------------------------------------------------------------------

-- product_id: 5092 (ref TM-CONT-PRT) — toutes marques porteur
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (29, 'CTRL-ANN-PRT', 1, 1, 'Contrôles annuels porteur', 2, NULL);

-- product_id: 4531 (ref 2141400) — porteur mark 2
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (30, 'REG-SOUP-PRT', 1, 1, 'Soupapes et injecteurs-pompes porteur', 2, 2);

-- product_id: 4572 (ref 2547700) — porteur mark 2
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (31, 'DPF-PRT-1', 1, 1, 'DPF remplacement insert filtre porteur (var. 1)', 2, 2);

-- product_id: 4573 (ref 2547701) — porteur mark 2
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (32, 'DPF-PRT-2', 1, 1, 'DPF remplacement insert filtre porteur (var. 2)', 2, 2);

-- ---------------------------------------------------------------------------
-- 6. Opérations Remorque toutes marques (type=3, mark=NULL)
-- ---------------------------------------------------------------------------

-- product_id: 20289 (ref CS-TRRQ) — marks 3,8,9
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (33, 'CS-TRRQ', 1, 1, 'Check Sécurité Tracteur+Remorque', 3, NULL);

-- product_id: 5088 (ref TM-CONT-RQ) — marks 3,8,9,17-23
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (34, 'CTRL-ANN-RQ', 1, 1, 'Contrôles annuels remorque', 3, NULL);

-- product_id: 5089 (ref CT-R) — marks 3,8
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (35, 'CT-R', 1, 1, 'Contrôle Technique Remorque', 3, NULL);

-- product_id: 20208 (ref ROLFO_M1) — marks 3,8
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (36, 'CTRL-ROLFO-M1', 1, 1, 'Contrôle équipement Rolfo M1', 3, NULL);

-- product_id: 20553 (ref VERIF-CS-RQ) — marks 3,8,9
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (37, 'VERIF-CS-RQ', 1, 1, 'Vérification état et quantité des cales et sangles', 3, NULL);

-- product_id: 19840 (ref lohr_V1) — marks 3,9
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (38, 'LOHR-V1', 1, 1, 'Visite Obligatoire Lohr V1', 3, NULL);

-- product_id: 19841 (ref lohr_V2) — marks 3,9
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (39, 'LOHR-V2', 1, 1, 'Visite Obligatoire Lohr V2', 3, NULL);

-- product_id: 19842 (ref lohr_V3) — marks 3,9
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (40, 'LOHR-V3', 1, 1, 'Visite Obligatoire Lohr V3', 3, NULL);

-- ---------------------------------------------------------------------------
-- 7. Opérations Remorque spécifiques à une marque
-- ---------------------------------------------------------------------------

-- product_id: 20496 (ref FEUX-PLATSUP-RQ) — mark 3 uniquement
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (41, 'FEUX-PLAT-RQ', 1, 1, 'Pose feux additionnel', 3, 3);

-- product_id: 21117 (ref PR-RQ) — mark 3 uniquement
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (42, 'MONT-ROULEAU', 1, 1, 'Montage rouleau', 3, 3);

-- product_id: 20570 (ref CA-BPW) — mark 8 uniquement
INSERT INTO llx_workshop_vehicule_c_maintenance_operation (rowid, code, entity, active, label, fk_vehicule_type, fk_vehicule_mark) VALUES (43, 'CA-BPW', 1, 1, 'Campagne Etrier BPW', 3, 8);
