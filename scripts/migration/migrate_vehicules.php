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
// Bootstrap Dolibarr CLI
// ============================================================
if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}

// Recherche du main.inc.php de Dolibarr
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp  = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i    = strlen($tmp) - 1;
$j    = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Erreur: impossible de charger main.inc.php de Dolibarr\n");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

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
// Étape 1 : Migration des dictionnaires (type, marque, contrat)
// On construit des tables de correspondance code → new_rowid
// ============================================================
mig_log("--- Étape 1 : Migration des dictionnaires ---");

/**
 * Migre un dictionnaire dolifleet vers workshop.
 * Les deux tables ont la même structure (code, entity, active, label).
 * On insère dans la cible si le code n'existe pas déjà (pour la même entity).
 *
 * @param  DoliDB $db            Connexion DB
 * @param  string $sourceTable   Nom complet table source (sans préfixe llx_)
 * @param  string $targetTable   Nom complet table cible (sans préfixe llx_)
 * @param  bool   $dryRun        Mode simulation
 * @return array                 Mapping [code][entity] => new_rowid
 */
function migrateDictionary(DoliDB $db, string $sourceTable, string $targetTable, bool $dryRun): array
{
	$mapping = array();

	// Charger les entrées cibles existantes pour éviter les doublons
	$sqlExisting = "SELECT rowid, code, entity FROM ".$db->prefix().$targetTable;
	$resExisting = $db->query($sqlExisting);
	$existing = array();
	if ($resExisting) {
		while ($obj = $db->fetch_object($resExisting)) {
			$existing[$obj->code][$obj->entity] = (int) $obj->rowid;
		}
		$db->free($resExisting);
	}

	// Lire les entrées sources
	$sqlSrc = "SELECT rowid, code, entity, active, label, date_creation FROM ".$db->prefix().$sourceTable;
	$resSrc = $db->query($sqlSrc);
	if (!$resSrc) {
		mig_log("Erreur lecture $sourceTable : ".$db->lasterror(), "ERROR");
		return $mapping;
	}

	$nbInserted = 0;
	$nbSkipped  = 0;

	while ($obj = $db->fetch_object($resSrc)) {
		$code   = $obj->code;
		$entity = (int) $obj->entity;

		// Déjà présent dans la cible ?
		if (isset($existing[$code][$entity])) {
			$mapping[$code][$entity] = $existing[$code][$entity];
			$nbSkipped++;
			continue;
		}

		if (!$dryRun) {
			$sqlIns = "INSERT INTO ".$db->prefix().$targetTable;
			$sqlIns .= " (code, entity, active, label, date_creation)";
			$sqlIns .= " VALUES (";
			$sqlIns .= "'".$db->escape($code)."'";
			$sqlIns .= ", ".$entity;
			$sqlIns .= ", ".(int) $obj->active;
			$sqlIns .= ", '".$db->escape($obj->label)."'";
			$sqlIns .= ", '".$db->idate($db->jdate($obj->date_creation))."'";
			$sqlIns .= ")";

			$resIns = $db->query($sqlIns);
			if ($resIns) {
				$newId = $db->last_insert_id($db->prefix().$targetTable);
				$mapping[$code][$entity] = (int) $newId;
				$nbInserted++;
			} else {
				mig_log("Erreur INSERT $targetTable code=$code : ".$db->lasterror(), "ERROR");
			}
		} else {
			// En dry-run, on simule un ID
			$mapping[$code][$entity] = -1;
			$nbInserted++;
		}
	}
	$db->free($resSrc);

	mig_log("  $targetTable : $nbInserted insérés, $nbSkipped déjà présents");

	return $mapping;
}

$mapType     = migrateDictionary($db, 'c_dolifleet_vehicule_type', 'workshop_vehicule_c_vehicule_type', $dryRun);
$mapMark     = migrateDictionary($db, 'c_dolifleet_vehicule_mark', 'workshop_vehicule_c_vehicule_mark', $dryRun);
$mapContract = migrateDictionary($db, 'c_dolifleet_contract_type', 'workshop_vehicule_c_contract_type', $dryRun);

// ============================================================
// Étape 2 : Parcours et migration des véhicules ligne par ligne
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

	// --- Résolution des FK dictionnaires (varchar code → integer rowid) ---
	$entity = (int) $veh->entity;

	$newFkType = 0;
	if (!empty($veh->fk_vehicule_type) && isset($mapType[$veh->fk_vehicule_type][$entity])) {
		$newFkType = $mapType[$veh->fk_vehicule_type][$entity];
	} elseif (!empty($veh->fk_vehicule_type) && isset($mapType[$veh->fk_vehicule_type])) {
		// Fallback : prendre le premier entity disponible
		$newFkType = reset($mapType[$veh->fk_vehicule_type]);
	}

	$newFkMark = 0;
	if (!empty($veh->fk_vehicule_mark) && isset($mapMark[$veh->fk_vehicule_mark][$entity])) {
		$newFkMark = $mapMark[$veh->fk_vehicule_mark][$entity];
	} elseif (!empty($veh->fk_vehicule_mark) && isset($mapMark[$veh->fk_vehicule_mark])) {
		$newFkMark = reset($mapMark[$veh->fk_vehicule_mark]);
	}

	$newFkContract = null;
	if (!empty($veh->fk_contract_type) && isset($mapContract[$veh->fk_contract_type][$entity])) {
		$newFkContract = $mapContract[$veh->fk_contract_type][$entity];
	} elseif (!empty($veh->fk_contract_type) && isset($mapContract[$veh->fk_contract_type])) {
		$newFkContract = reset($mapContract[$veh->fk_contract_type]);
	}

	// --- Troncature immatriculation (255 → 20 caractères) ---
	$immat = substr((string) $veh->immatriculation, 0, 20);
	if (strlen((string) $veh->immatriculation) > 20) {
		mig_log("  [WARN] Véhicule #$oldId : immatriculation tronquée ('".$veh->immatriculation."' → '$immat')");
	}

	// --- Warnings FK non résolues ---
	if (!empty($veh->fk_vehicule_type) && $newFkType == 0) {
		mig_log("  [WARN] Véhicule #$oldId : fk_vehicule_type='".$veh->fk_vehicule_type."' non trouvé dans le dictionnaire cible", "ERROR");
	}
	if (!empty($veh->fk_vehicule_mark) && $newFkMark == 0) {
		mig_log("  [WARN] Véhicule #$oldId : fk_vehicule_mark='".$veh->fk_vehicule_mark."' non trouvé dans le dictionnaire cible", "ERROR");
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
		$sqlIns .= ", ".($veh->date_immat !== null ? "'".$db->idate($db->jdate($veh->date_immat))."'" : "NULL");
		$sqlIns .= ", ".(int) $veh->fk_soc;
		$sqlIns .= ", ".($veh->date_customer_exploit !== null ? "'".$db->idate($db->jdate($veh->date_customer_exploit))."'" : "NULL");
		$sqlIns .= ", ".(float) $veh->km;
		$sqlIns .= ", ".($veh->km_date !== null ? "'".$db->idate($db->jdate($veh->km_date))."'" : "NULL");
		$sqlIns .= ", ".($newFkContract !== null ? (int) $newFkContract : "NULL");
		$sqlIns .= ", ".($veh->date_end_contract !== null ? "'".$db->idate($db->jdate($veh->date_end_contract))."'" : "NULL");
		$sqlIns .= ", ".($veh->atelier !== null ? (int) $veh->atelier : "NULL");
		$sqlIns .= ", ".($veh->carrosserie !== null ? "'".$db->escape($veh->carrosserie)."'" : "NULL");
		$sqlIns .= ", ".(int) $veh->dfol;
		$sqlIns .= ", ".(int) $veh->nb_pneu;
		$sqlIns .= ", ".($veh->dim_pneu !== null ? "'".$db->escape($veh->dim_pneu)."'" : "NULL");
		$sqlIns .= ", ".($veh->essieu !== null ? "'".$db->escape($veh->essieu)."'" : "NULL");
		$sqlIns .= ", ".($veh->type_custom !== null ? (int) $veh->type_custom : "NULL");
		$sqlIns .= ", ".($veh->coutm !== null ? (float) $veh->coutm : "NULL");
		$sqlIns .= ", ".($veh->date_fin_fin !== null ? "'".$db->idate($db->jdate($veh->date_fin_fin))."'" : "NULL");
		$sqlIns .= ", ".($veh->type_fin !== null ? "'".$db->escape($veh->type_fin)."'" : "NULL");
		$sqlIns .= ", ".($veh->date_fin_loc !== null ? "'".$db->idate($db->jdate($veh->date_fin_loc))."'" : "NULL");
		$sqlIns .= ", ".(int) $veh->exit_data;
		$sqlIns .= ", ".(int) $veh->age_veh;
		$sqlIns .= ", '".$db->escape($importKey)."'";
		$sqlIns .= ", ".($veh->date_creation !== null ? "'".$db->idate($db->jdate($veh->date_creation))."'" : "'".$db->idate(dol_now())."'");
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
