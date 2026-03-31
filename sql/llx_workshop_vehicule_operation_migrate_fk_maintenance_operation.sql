-- Migration: remplacement de fk_product par fk_maintenance_operation
-- dans llx_workshop_vehicule_operation
--
-- ÉTAPE 1 : ajouter la nouvelle colonne (sans supprimer fk_product)
ALTER TABLE llx_workshop_vehicule_operation
    ADD COLUMN fk_maintenance_operation integer DEFAULT NULL AFTER fk_vehicule;

-- ÉTAPE 2 : supprimer l'ancienne contrainte unique et la clé étrangère produit
ALTER TABLE llx_workshop_vehicule_operation
    DROP INDEX uk_vehicule_operation_veh_prod;

ALTER TABLE llx_workshop_vehicule_operation
    DROP FOREIGN KEY fk_workshop_vehicule_operation_product;

-- ÉTAPE 3 : ajouter la nouvelle contrainte unique
ALTER TABLE llx_workshop_vehicule_operation
    ADD UNIQUE uk_vehicule_operation_veh_ope (fk_vehicule, fk_maintenance_operation);

-- ÉTAPE 4 : ajouter la clé étrangère vers le dictionnaire
ALTER TABLE llx_workshop_vehicule_operation
    ADD CONSTRAINT fk_workshop_vehicule_operation_maintenance_operation
        FOREIGN KEY (fk_maintenance_operation)
        REFERENCES llx_workshop_vehicule_c_maintenance_operation (rowid);

-- ÉTAPE 5 : (EXÉCUTER APRÈS CONVERSION DES DONNÉES)
-- Supprimer l'ancienne colonne fk_product une fois les données migrées
-- ALTER TABLE llx_workshop_vehicule_operation DROP COLUMN fk_product;
