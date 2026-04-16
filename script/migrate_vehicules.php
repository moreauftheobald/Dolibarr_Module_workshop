#!/usr/bin/env php
<?php
/* Copyright (C) 2024-2026 T-SERVICES <contact@theobald-groupe.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * Script de migration des véhicules dolifleet → workshop
 *
 * Usage : php migrate_vehicules.php [--dry-run] [--limit=N]
 *
 * --dry-run   Simule la migration sans écrire en base
 * --limit=N   Limite le nombre de véhicules traités (utile pour les tests)
 */

// ============================================================
// Vérification CLI
// ============================================================
if (php_sapi_name() !== 'cli') {
	die("Ce script doit être exécuté en ligne de commande.\n");
}

// ============================================================
// Bootstrap Dolibarr CLI
// ============================================================
if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOREQUIREMENU'))   define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML'))   define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX'))   define('NOREQUIREAJAX', 1);
if (!defined('NOLOGIN'))         define('NOLOGIN', 1);
if (!defined('NOSESSION'))       define('NOSESSION', 1);

// Recherche du master.inc.php — le module peut être dans htdocs/custom/workshop/ ou htdocs/workshop/
$masterPaths = array(
	dirname(__FILE__).'/../../../master.inc.php',         // custom/workshop/script/ → htdocs/
	dirname(__FILE__).'/../../../../master.inc.php',      // custom/workshop/script/migration/ → htdocs/
	dirname(__FILE__).'/../../master.inc.php',            // workshop/script/ → htdocs/ (si directement dans htdocs)
);

$masterFound = false;
foreach ($masterPaths as $path) {
	if (file_exists($path)) {
		require_once $path;
		$masterFound = true;
		break;
	}
}

if (!$masterFound) {
	die("Erreur : impossible de trouver master.inc.php. Vérifiez l'emplacement du script.\n");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

global $db, $conf;

// ============================================================
// Parsing des arguments CLI
// ============================================================
$dryRun = false;
$limit  = 0;

foreach ($argv as $arg) {
	if ($arg === '--dry-run') {
		$dryRun = true;
	}
	if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
		$limit = (int) $m[1];
	}
}

// ============================================================
// Helpers d'affichage
// ============================================================
function mig_log(string $msg, string $level = 'INFO'): void
{
	$ts = date('Y-m-d H:i:s');
	print "[$ts][$level] $msg\n";
	dol_syslog("migrate_vehicules: $msg", $level === 'ERROR' ? LOG_ERR : LOG_INFO);
}

// ============================================================
// Vérification des pré-requis
// ============================================================
mig_log("=== Démarrage migration véhicules dolifleet → workshop ===");
if ($dryRun) {
	mig_log("Mode DRY-RUN activé — aucune écriture en base");
}

// Vérifie que la table source existe
$sqlCheck = "SHOW TABLES LIKE 'llx_dolifleet_vehicule'";
$resCheck = $db->query($sqlCheck);
if (!$resCheck || $db->num_rows($resCheck) == 0) {
	mig_log("Table source llx_dolifleet_vehicule introuvable — abandon", "ERROR");
	exit(1);
}

// ============================================================
// Étape 1 : Migration en masse des dictionnaires satellites
//
// On conserve les rowid d'origine pour simplifier les migrations
// suivantes (pas besoin de table de mapping).
//
// Tables migrées :
//   llx_c_dolifleet_vehicule_type         → llx_workshop_vehicule_c_vehicule_type
//   llx_c_dolifleet_vehicule_mark         → llx_workshop_vehicule_c_vehicule_mark
//   llx_c_dolifleet_contract_type         → llx_workshop_vehicule_c_contract_type
//   llx_c_dolifleet_vehicule_activity_type→ llx_workshop_vehicule_c_vehicule_activity_type
//   llx_c_dolifleet_vehicule_dimpneu      → llx_workshop_vehicule_c_vehicule_dimpneu
// ============================================================
mig_log("--- Étape 1 : Migration des dictionnaires satellites (en masse, conservation des rowid) ---");

/**
 * Migre un dictionnaire dolifleet vers workshop en conservant les rowid.
 * Les deux tables ont la même structure de base (rowid, code, entity, active, label, date_creation, tms).
 * On insère avec le rowid d'origine. Les entrées déjà présentes (même rowid) sont ignorées (idempotence).
 *
 * @param  DoliDB $db            Connexion DB
 * @param  string $sourceTable   Nom table source sans préfixe llx_ (ex: 'c_dolifleet_vehicule_type')
 * @param  string $targetTable   Nom table cible sans préfixe llx_ (ex: 'workshop_vehicule_c_vehicule_type')
 * @param  bool   $dryRun        Mode simulation
 * @param  array  $extraColumns  Colonnes supplémentaires à copier (ex: ['fk_vehicule_mark'] pour contract_type)
 * @return void
 */
function migrateDictionary(DoliDB $db, string $sourceTable, string $targetTable, bool $dryRun, array $extraColumns = array()): void
{
	// Charger les rowid déjà présents dans la cible
	$existingIds = array();
	$sqlExisting = "SELECT rowid FROM ".$db->prefix().$targetTable;
	$resExisting = $db->query($sqlExisting);
	if ($resExisting) {
		while ($obj = $db->fetch_object($resExisting)) {
			$existingIds[(int) $obj->rowid] = true;
		}
		$db->free($resExisting);
	}

	// Lire toutes les entrées sources
	$sqlSrc = "SELECT * FROM ".$db->prefix().$sourceTable." ORDER BY rowid ASC";
	$resSrc = $db->query($sqlSrc);
	if (!$resSrc) {
		mig_log("  ERREUR lecture $sourceTable : ".$db->lasterror(), "ERROR");
		return;
	}

	$nbInserted = 0;
	$nbSkipped  = 0;

	while ($obj = $db->fetch_object($resSrc)) {
		$oldId = (int) $obj->rowid;

		// Déjà présent dans la cible (même rowid) → skip
		if (isset($existingIds[$oldId])) {
			$nbSkipped++;
			continue;
		}

		if (!$dryRun) {
			// Colonnes de base communes à tous les dictionnaires
			$cols = "rowid, code, entity, active, label, date_creation";
			$vals = $oldId;
			$vals .= ", ".($obj->code !== null ? "'".$db->escape($obj->code)."'" : "NULL");
			$vals .= ", ".(int) $obj->entity;
			$vals .= ", ".(int) $obj->active;
			$vals .= ", ".($obj->label !== null ? "'".$db->escape($obj->label)."'" : "NULL");
			$vals .= ", ".($obj->date_creation !== null ? "'".$db->escape($obj->date_creation)."'" : "NULL");

			// Colonnes supplémentaires (ex: fk_vehicule_mark pour contract_type)
			foreach ($extraColumns as $col) {
				$cols .= ", ".$col;
				$colVal = isset($obj->$col) ? $obj->$col : null;
				$vals .= ", ".($colVal !== null ? "'".$db->escape($colVal)."'" : "NULL");
			}

			$sqlIns = "INSERT INTO ".$db->prefix().$targetTable." (".$cols.") VALUES (".$vals.")";
			$resIns = $db->query($sqlIns);
			if ($resIns) {
				$nbInserted++;
			} else {
				mig_log("  ERREUR INSERT $targetTable rowid=$oldId : ".$db->lasterror(), "ERROR");
			}
		} else {
			$nbInserted++;
		}
	}
	$db->free($resSrc);

	mig_log("  $sourceTable → $targetTable : $nbInserted insérés, $nbSkipped déjà présents");
}

// --- Migration des 5 dictionnaires ---
migrateDictionary($db, 'c_dolifleet_vehicule_type', 'workshop_vehicule_c_vehicule_type', $dryRun);
migrateDictionary($db, 'c_dolifleet_vehicule_mark', 'workshop_vehicule_c_vehicule_mark', $dryRun);
migrateDictionary($db, 'c_dolifleet_contract_type', 'workshop_vehicule_c_contract_type', $dryRun, array('fk_vehicule_mark'));
migrateDictionary($db, 'c_dolifleet_vehicule_activity_type', 'workshop_vehicule_c_vehicule_activity_type', $dryRun);
migrateDictionary($db, 'c_dolifleet_vehicule_dimpneu', 'workshop_vehicule_c_vehicule_dimpneu', $dryRun);

// ============================================================
// Étape 2 : Parcours et migration des véhicules ligne par ligne
//
// Les FK dictionnaires (fk_vehicule_type, fk_vehicule_mark,
// fk_contract_type) dans dolifleet sont des varchar qui stockent
// le ROWID du dictionnaire source (ex: '3', '5').
// Puisqu'on a conservé les rowid à l'étape 1, on peut directement
// caster en integer sans table de mapping.
// ============================================================
mig_log("--- Étape 2 : Migration des véhicules ---");

$sqlVeh = "SELECT * FROM ".$db->prefix()."dolifleet_vehicule";
$sqlVeh .= " ORDER BY rowid ASC";
if ($limit > 0) {
	$sqlVeh .= " LIMIT ".$limit;
}

$resVeh = $db->query($sqlVeh);
if (!$resVeh) {
	mig_log("Erreur lecture dolifleet_vehicule : ".$db->lasterror(), "ERROR");
	exit(1);
}

$nbTotal    = $db->num_rows($resVeh);
$nbMigrated = 0;
$nbSkipped  = 0;
$nbErrors   = 0;

mig_log("$nbTotal véhicule(s) à traiter");

while ($veh = $db->fetch_object($resVeh)) {
	$oldId = (int) $veh->rowid;
	$vin   = $veh->vin;

	// --- Vérifier si déjà migré (idempotence via import_key) ---
	$importKey = 'mig_df_'.$oldId;
	$sqlExists = "SELECT rowid FROM ".$db->prefix()."workshop_vehicule";
	$sqlExists .= " WHERE import_key = '".$db->escape($importKey)."'";
	$resExists = $db->query($sqlExists);
	if ($resExists && $db->num_rows($resExists) > 0) {
		mig_log("  [SKIP] Véhicule #$oldId (VIN=$vin) déjà migré");
		$nbSkipped++;
		continue;
	}

	// --- Résolution des FK dictionnaires (varchar → integer, rowid conservés) ---
	$newFkType     = !empty($veh->fk_vehicule_type) ? (int) $veh->fk_vehicule_type : 0;
	$newFkMark     = !empty($veh->fk_vehicule_mark) ? (int) $veh->fk_vehicule_mark : 0;
	$newFkContract = !empty($veh->fk_contract_type) ? (int) $veh->fk_contract_type : null;

	// --- Troncature immatriculation (255 → 20 caractères) ---
	$immat = substr((string) $veh->immatriculation, 0, 20);
	if (strlen((string) $veh->immatriculation) > 20) {
		mig_log("  [WARN] Véhicule #$oldId : immatriculation tronquée ('".$veh->immatriculation."' → '$immat')");
	}

	// --- Construction de l'INSERT ---
	if (!$dryRun) {
		$sqlIns = "INSERT INTO ".$db->prefix()."workshop_vehicule (";
		$sqlIns .= "vin, entity, status, fk_vehicule_type, fk_vehicule_mark, modele,";
		$sqlIns .= " immatriculation, date_immat, fk_soc, date_customer_exploit,";
		$sqlIns .= " km, km_date, fk_contract_type, date_end_contract, atelier,";
		$sqlIns .= " carrosserie, dfol, nb_pneu, dim_pneu, essieu,";
		$sqlIns .= " type_custom, coutm, date_fin_fin, type_fin,";
		$sqlIns .= " date_fin_loc, exit_data, age_veh,";
		$sqlIns .= " import_key, date_creation";
		$sqlIns .= ") VALUES (";
		$sqlIns .= "'".$db->escape((string) $veh->vin)."'";
		$sqlIns .= ", ".(int) $veh->entity;
		$sqlIns .= ", ".(int) $veh->status;
		$sqlIns .= ", ".(int) $newFkType;
		$sqlIns .= ", ".(int) $newFkMark;
		$sqlIns .= ", ".($veh->modele !== null ? "'".$db->escape($veh->modele)."'" : "NULL");
		$sqlIns .= ", '".$db->escape($immat)."'";
		$sqlIns .= ", ".($veh->date_immat !== null ? "'".$db->escape($veh->date_immat)."'" : "NULL");
		$sqlIns .= ", ".(int) $veh->fk_soc;
		$sqlIns .= ", ".($veh->date_customer_exploit !== null ? "'".$db->escape($veh->date_customer_exploit)."'" : "NULL");
		$sqlIns .= ", ".(float) $veh->km;
		$sqlIns .= ", ".($veh->km_date !== null ? "'".$db->escape($veh->km_date)."'" : "NULL");
		$sqlIns .= ", ".($newFkContract !== null ? (int) $newFkContract : "NULL");
		$sqlIns .= ", ".($veh->date_end_contract !== null ? "'".$db->escape($veh->date_end_contract)."'" : "NULL");
		$sqlIns .= ", ".($veh->atelier !== null ? (int) $veh->atelier : "NULL");
		$sqlIns .= ", ".($veh->carrosserie !== null ? "'".$db->escape($veh->carrosserie)."'" : "NULL");
		$sqlIns .= ", ".(int) $veh->dfol;
		$sqlIns .= ", ".(int) $veh->nb_pneu;
		$sqlIns .= ", ".($veh->dim_pneu !== null ? "'".$db->escape($veh->dim_pneu)."'" : "NULL");
		$sqlIns .= ", ".($veh->essieu !== null ? "'".$db->escape($veh->essieu)."'" : "NULL");
		$sqlIns .= ", ".($veh->type_custom !== null ? (int) $veh->type_custom : "NULL");
		$sqlIns .= ", ".($veh->coutm !== null ? (float) $veh->coutm : "NULL");
		$sqlIns .= ", ".($veh->date_fin_fin !== null ? "'".$db->escape($veh->date_fin_fin)."'" : "NULL");
		$sqlIns .= ", ".($veh->type_fin !== null ? "'".$db->escape($veh->type_fin)."'" : "NULL");
		$sqlIns .= ", ".($veh->date_fin_loc !== null ? "'".$db->escape($veh->date_fin_loc)."'" : "NULL");
		$sqlIns .= ", ".(int) $veh->exit_data;
		$sqlIns .= ", ".(int) $veh->age_veh;
		$sqlIns .= ", '".$db->escape($importKey)."'";
		$sqlIns .= ", ".($veh->date_creation !== null ? "'".$db->escape($veh->date_creation)."'" : "'".$db->idate(dol_now())."'");
		$sqlIns .= ")";

		$resIns = $db->query($sqlIns);
		if (!$resIns) {
			mig_log("  [ERREUR] Véhicule #$oldId (VIN=$vin) : ".$db->lasterror(), "ERROR");
			$nbErrors++;
			continue;
		}

		$newId = $db->last_insert_id($db->prefix()."workshop_vehicule");
		mig_log("  [OK] Véhicule #$oldId → #$newId (VIN=$vin, immat=$immat)");
		$nbMigrated++;

		// TODO : Étape 3 — Migration des activités (llx_dolifleet_vehicule_activity → llx_workshop_vehicule_activity)
		// TODO : Étape 4 — Migration des opérations (llx_dolifleet_vehicule_operation → llx_workshop_vehicule_operation)
		// TODO : Étape 5 — Migration des extrafields
	} else {
		mig_log("  [DRY-RUN] Véhicule #$oldId (VIN=$vin, immat=$immat) serait migré");
		$nbMigrated++;
	}
}
$db->free($resVeh);

// ============================================================
// Résumé
// ============================================================
mig_log("=== Migration terminée ===");
mig_log("  Total traités : $nbTotal");
mig_log("  Migrés        : $nbMigrated");
mig_log("  Déjà présents : $nbSkipped");
mig_log("  Erreurs       : $nbErrors");

if ($nbErrors > 0) {
	exit(1);
}

exit(0);
