#!/usr/bin/env php
<?php
/* Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    script/migrate_operationorder.php
 * \ingroup workshop
 * \brief   Migration des données de l'ancien module operationorder vers workshop.
 *
 * Traitement OR par OR :
 *   Phase 0 — Données de référence (statuts, service types, conducteurs)
 *   Phase 1 — Pour chaque ancien OR : entête → jobs → lignes → liens
 *
 * Usage :
 *   php migrate_operationorder.php [--dry-run] [--limit=N] [--offset=N] [-v|--verbose]
 *
 * Options :
 *   --dry-run   Simule sans écrire en base (rollback systématique)
 *   --limit=N   Ne traiter que N ordres de réparation
 *   --offset=N  Commencer à partir du Nème OR (ORDER BY rowid ASC)
 *   -v          Mode verbeux (détail de chaque ligne traitée)
 */

// ============================================================================
// 1. Vérification CLI
// ============================================================================

if (php_sapi_name() !== 'cli') {
	die("Ce script doit être exécuté en ligne de commande.\n");
}

// ============================================================================
// 2. Bootstrap Dolibarr
// ============================================================================

// Désactiver tout ce qui est inutile en CLI
if (!defined('NOTOKENRENEWAL'))        define('NOTOKENRENEWAL', 1);
if (!defined('NOREQUIREMENU'))         define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML'))         define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX'))         define('NOREQUIREAJAX', 1);
if (!defined('NOLOGIN'))               define('NOLOGIN', 1);       // Pas de session, on utilisera un user système
if (!defined('NOSESSION'))             define('NOSESSION', 1);

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

// ============================================================================
// 3. Chargement des classes Workshop
// ============================================================================

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/workshop/class/operationorder.class.php');
dol_include_once('/workshop/class/operationorder_jobs.class.php');
dol_include_once('/workshop/class/operationorderdet.class.php');
dol_include_once('/workshop/class/workshopoperationorderstatus.class.php');
dol_include_once('/workshop/class/Conducteur.class.php');
dol_include_once('/workshop/class/servicetype.class.php');

// ============================================================================
// 4. Parsing des arguments CLI
// ============================================================================

$dryRun  = in_array('--dry-run', $argv);
$verbose = in_array('-v', $argv) || in_array('--verbose', $argv);
$limit   = 0;
$offset  = 0;

foreach ($argv as $arg) {
	if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
		$limit = (int) $m[1];
	}
	if (preg_match('/^--offset=(\d+)$/', $arg, $m)) {
		$offset = (int) $m[1];
	}
}

// ============================================================================
// 5. Initialisation de l'utilisateur système
// ============================================================================

$user = new User($db);
$user->fetch(1); // Utilisateur admin (rowid = 1)
if (empty($user->id)) {
	die("Erreur : impossible de charger l'utilisateur système (rowid=1).\n");
}

// ============================================================================
// 6. Vérification des prérequis
// ============================================================================

/**
 * Vérifie qu'une table existe en base
 *
 * @param  DoliDB $db    Connexion base
 * @param  string $table Nom de la table SANS préfixe (ex: 'operationorder')
 * @return bool
 */
function tableExists($db, $table)
{
	$sql = "SHOW TABLES LIKE '".$db->escape($db->prefix().$table)."'";
	$res = $db->query($sql);
	return ($res && $db->num_rows($res) > 0);
}

// Tables sources (ancien module)
$requiredOldTables = array(
	'operationorder',
	'operationorderdet',
	'operationorder_status',
);

foreach ($requiredOldTables as $t) {
	if (!tableExists($db, $t)) {
		die("Erreur : la table source ".MAIN_DB_PREFIX.$t." n'existe pas. L'ancien module operationorder est-il installé ?\n");
	}
}

// Tables destination (nouveau module workshop)
$requiredNewTables = array(
	'workshop_operationorder',
	'workshop_operationorder_jobs',
	'workshop_operationorderdet',
	'workshop_operationorder_status',
);

foreach ($requiredNewTables as $t) {
	if (!tableExists($db, $t)) {
		die("Erreur : la table destination ".MAIN_DB_PREFIX.$t." n'existe pas. Le module workshop est-il installé ?\n");
	}
}

// ============================================================================
// 7. Fonctions utilitaires
// ============================================================================

/**
 * Affiche un message sur la sortie standard
 *
 * @param string $msg     Message
 * @param string $level   Niveau : 'info', 'ok', 'warn', 'err', 'debug'
 * @param bool   $verbose Si true, les messages 'debug' sont affichés
 * @return void
 */
function out($msg, $level = 'info', $verbose = false)
{
	if ($level === 'debug' && !$verbose) {
		return;
	}

	$prefixes = array(
		'info'  => '   ',
		'ok'    => ' ✓ ',
		'warn'  => ' ⚠ ',
		'err'   => ' ✗ ',
		'debug' => ' · ',
	);

	$prefix = isset($prefixes[$level]) ? $prefixes[$level] : '   ';
	fwrite(STDOUT, $prefix.$msg."\n");
}


// ============================================================================
// 8. Comptage et lecture des anciens OR
// ============================================================================

print "\n";
print "==========================================================\n";
print "  MIGRATION operationorder → workshop\n";
print "==========================================================\n";

if ($dryRun) {
	print "  MODE DRY-RUN : aucune écriture en base\n";
}
print "\n";

// Compter le total des anciens OR
$sqlCount = "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."operationorder";
$resCount = $db->query($sqlCount);
$objCount = $db->fetch_object($resCount);
$totalOldOR = (int) $objCount->nb;

print "Anciens OR trouvés : ".$totalOldOR."\n";

if ($totalOldOR === 0) {
	print "Rien à migrer.\n";
	exit(0);
}

// Compter les OR déjà migrés
$sqlMigrated = "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."workshop_operationorder";
$sqlMigrated .= " WHERE import_key LIKE 'mig-or-%'";
$resMigrated = $db->query($sqlMigrated);
$objMigrated = $db->fetch_object($resMigrated);
$totalAlreadyMigrated = (int) $objMigrated->nb;

print "OR déjà migrés :    ".$totalAlreadyMigrated."\n";
print "\n";

// ============================================================================
// 9. Boucle principale : lecture des anciens OR
// ============================================================================

$sql  = "SELECT rowid, ref, ref_client, entity, fk_soc, fk_vehicule, fk_conducteur,";
$sql .= " status, date_creation, date_valid, planned_date,";
$sql .= " km_on_creation, note_public, note_private,";
$sql .= " fk_user_creat, fk_user_modif, fk_user_valid, fk_user_meca,";
$sql .= " model_pdf, last_main_doc,";
$sql .= " total_ht, total_ht_part, total_ht_mo, total_ht_service,";
$sql .= " total_ht_external, total_ht_reimbursement,";
$sql .= " time_planned_f, orcheck, import_key";
$sql .= " FROM ".MAIN_DB_PREFIX."operationorder";
$sql .= " ORDER BY rowid ASC";

if ($limit > 0) {
	$sql .= " LIMIT ".(int) $limit;
	if ($offset > 0) {
		$sql .= " OFFSET ".(int) $offset;
	}
} elseif ($offset > 0) {
	// Offset sans limit : on prend tout à partir de l'offset
	$sql .= " LIMIT 999999999 OFFSET ".(int) $offset;
}

$resql = $db->query($sql);
if (!$resql) {
	die("Erreur SQL lecture des anciens OR : ".$db->lasterror()."\n");
}

$totalToProcess = $db->num_rows($resql);
print "OR à traiter dans cette passe : ".$totalToProcess."\n";
print "----------------------------------------------------------\n\n";

$countMigrated = 0;
$countSkipped  = 0;
$countErrors   = 0;
$i = 0;

while ($oldOR = $db->fetch_object($resql)) {
	$i++;
	$progress = "[".$i."/".$totalToProcess."]";

	// --- Vérification : cet OR a-t-il déjà été migré ? ---
	$importKey = 'mig-or-'.(int) $oldOR->rowid;

	$sqlCheck  = "SELECT rowid FROM ".MAIN_DB_PREFIX."workshop_operationorder";
	$sqlCheck .= " WHERE import_key = '".$db->escape($importKey)."'";
	$resCheck = $db->query($sqlCheck);

	if ($resCheck && $db->num_rows($resCheck) > 0) {
		$objExisting = $db->fetch_object($resCheck);
		out($progress." OR ".$oldOR->ref." (id=".$oldOR->rowid.") → déjà migré (workshop id=".$objExisting->rowid."), skip", 'debug', $verbose);
		$countSkipped++;
		$db->free($resCheck);
		continue;
	}
	$db->free($resCheck);

	// --- Cet OR n'a pas encore été migré → à traiter ---
	out($progress." OR ".$oldOR->ref." (id=".$oldOR->rowid.", entity=".$oldOR->entity.") → à migrer", 'info');

	// =====================================================
	// TODO : Phase 0 — Vérifier/créer les valeurs satellites
	// TODO : Phase 1 — Créer l'OR Workshop + jobs + lignes
	// =====================================================

	// Placeholder — sera remplacé par migrateOneOR()
	out("       → migration non encore implémentée", 'warn');
	$countErrors++;
}

$db->free($resql);

// ============================================================================
// 10. Rapport final
// ============================================================================

print "\n";
print "==========================================================\n";
print "  RAPPORT DE MIGRATION\n";
print "==========================================================\n";
print "  Traités :   ".$i."\n";
print "  Migrés :    ".$countMigrated."\n";
print "  Skippés :   ".$countSkipped." (déjà migrés)\n";
print "  Erreurs :   ".$countErrors."\n";
print "==========================================================\n";
print "\n";

if ($dryRun) {
	print "Mode dry-run : aucune modification n'a été persistée.\n\n";
}

exit($countErrors > 0 ? 1 : 0);
