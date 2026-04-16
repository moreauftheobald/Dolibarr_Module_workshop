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
dol_include_once('/workshop/class/Vehicule.class.php');
dol_include_once('/workshop/class/Tag.class.php');
dol_include_once('/workshop/class/OperationorderTag.class.php');

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
// 8. Caches et fonctions de vérification des valeurs satellites
// ============================================================================

// Caches : évitent les requêtes répétées pour les mêmes valeurs
// Clé = critère de comparaison, Valeur = rowid dans la table Workshop
$cacheStatus      = array(); // code (string)                    => new status rowid
$cacheConducteurs = array(); // UPPER(nom.prenom) (string)       => new conducteur rowid
$cacheVehicules   = array(); // vin (string)                     => new vehicule rowid
$cacheTags        = array(); // code (string)                    => new tag rowid

/**
 * Pré-charge le cache des statuts Workshop existants (par code).
 *
 * @param  DoliDB $db    Connexion base
 * @param  array  $cache Référence vers $cacheStatus
 * @return void
 */
function preloadCacheStatus($db, &$cache)
{
	$sql = "SELECT rowid, code FROM ".MAIN_DB_PREFIX."workshop_operationorder_status WHERE code IS NOT NULL AND code != ''";
	$res = $db->query($sql);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$cache[$obj->code] = (int) $obj->rowid;
		}
		$db->free($res);
	}
}

/**
 * Pré-charge le cache des conducteurs Workshop existants (par UPPER(nom.prenom)).
 *
 * @param  DoliDB $db    Connexion base
 * @param  array  $cache Référence vers $cacheConducteurs
 * @return void
 */
function preloadCacheConducteurs($db, &$cache)
{
	$sql = "SELECT rowid, nom, prenom FROM ".MAIN_DB_PREFIX."workshop_conducteur";
	$res = $db->query($sql);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$key = strtoupper(trim($obj->nom).'.'.trim($obj->prenom));
			$cache[$key] = (int) $obj->rowid;
		}
		$db->free($res);
	}
}

/**
 * Pré-charge le cache des véhicules Workshop existants (par VIN).
 *
 * @param  DoliDB $db    Connexion base
 * @param  array  $cache Référence vers $cacheVehicules
 * @return void
 */
function preloadCacheVehicules($db, &$cache)
{
	$sql = "SELECT rowid, vin FROM ".MAIN_DB_PREFIX."workshop_vehicule WHERE vin IS NOT NULL AND vin != ''";
	$res = $db->query($sql);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$cache[$obj->vin] = (int) $obj->rowid;
		}
		$db->free($res);
	}
}

/**
 * Pré-charge le cache des tags Workshop existants (par code).
 *
 * @param  DoliDB $db    Connexion base
 * @param  array  $cache Référence vers $cacheTags
 * @return void
 */
function preloadCacheTags($db, &$cache)
{
	$sql = "SELECT rowid, code FROM ".MAIN_DB_PREFIX."workshop_tag WHERE code IS NOT NULL AND code != ''";
	$res = $db->query($sql);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$cache[$obj->code] = (int) $obj->rowid;
		}
		$db->free($res);
	}
}


/**
 * Vérifie/crée le statut Workshop correspondant à un ancien statut OR.
 * Clé de comparaison : code du statut.
 *
 * @param  DoliDB $db           Connexion base
 * @param  User   $user         Utilisateur système
 * @param  int    $oldStatusId  Rowid du statut dans llx_operationorder_status
 * @param  array  $cache        Référence vers $cacheStatus
 * @param  bool   $verbose      Mode verbeux
 * @return int                  Rowid du statut Workshop, ou 0 si erreur/introuvable
 */
function ensureStatus($db, $user, $oldStatusId, &$cache, $verbose)
{
	if (empty($oldStatusId)) {
		return 0;
	}

	// Lire l'ancien statut pour récupérer son code
	$sql = "SELECT * FROM ".MAIN_DB_PREFIX."operationorder_status WHERE rowid = ".(int) $oldStatusId;
	$res = $db->query($sql);
	if (!$res || $db->num_rows($res) == 0) {
		out("       Statut : ancien id=".$oldStatusId." introuvable dans operationorder_status", 'warn');
		return 0;
	}
	$oldStatus = $db->fetch_object($res);
	$db->free($res);

	$code = trim($oldStatus->code);
	if (empty($code)) {
		out("       Statut : ancien id=".$oldStatusId." a un code vide", 'warn');
		return 0;
	}

	// Déjà en cache ?
	if (isset($cache[$code])) {
		out("       Statut : code=".$code." → existant (id=".$cache[$code].")", 'debug', $verbose);
		return $cache[$code];
	}

	// Créer le statut Workshop
	$newStatus = new WorkshopOperationOrderStatus($db);
	$newStatus->entity             = 0;
	$newStatus->code               = $code;
	$newStatus->label              = !empty($oldStatus->label) ? $oldStatus->label : $code;
	$newStatus->color              = !empty($oldStatus->color) ? $oldStatus->color : '#3c8dbc';
	$newStatus->rang               = (int) $oldStatus->rang;
	$newStatus->status             = !empty($oldStatus->status) ? (int) $oldStatus->status : 1;
	$newStatus->planable           = (int) $oldStatus->planable;
	$newStatus->clean_event        = (int) $oldStatus->clean_event;
	$newStatus->display_on_planning = (int) $oldStatus->display_on_planning;
	$newStatus->check_virtual_stock = (int) $oldStatus->check_virtual_stock;
	$newStatus->or_pointable       = (int) $oldStatus->or_pointable;
	$newStatus->save_date_cloture  = (int) $oldStatus->save_date_cloture;
	$newStatus->require_planned_date = (int) $oldStatus->require_planned_date;
	$newStatus->update_vehicule_info = (int) $oldStatus->update_vehicule_info;
	$newStatus->require_conf       = isset($oldStatus->require_conf) ? (int) $oldStatus->require_conf : 1;
	$newStatus->import_key         = 'mig-oo';

	$newId = $newStatus->create($user, 1);
	if ($newId > 0) {
		$cache[$code] = $newId;
		out("       Statut : code=".$code." → CRÉÉ (id=".$newId.")", 'ok');
		return $newId;
	}

	out("       Statut : code=".$code." → ERREUR création : ".implode(', ', $newStatus->errors), 'err');
	return 0;
}


/**
 * Vérifie/crée le conducteur Workshop correspondant à un ancien contact (socpeople).
 * Clé de comparaison : UPPER(nom.prenom).
 *
 * @param  DoliDB $db             Connexion base
 * @param  User   $user           Utilisateur système
 * @param  int    $oldContactId   Rowid du contact dans llx_socpeople (fk_conducteur de l'ancien OR)
 * @param  array  $cache          Référence vers $cacheConducteurs
 * @param  bool   $verbose        Mode verbeux
 * @return int                    Rowid du conducteur Workshop, ou 0 si non applicable
 */
function ensureConducteur($db, $user, $oldContactId, &$cache, $verbose)
{
	if (empty($oldContactId)) {
		return 0;
	}

	// Lire l'ancien contact
	$sql = "SELECT rowid, lastname, firstname, fk_soc FROM ".MAIN_DB_PREFIX."socpeople WHERE rowid = ".(int) $oldContactId;
	$res = $db->query($sql);
	if (!$res || $db->num_rows($res) == 0) {
		out("       Conducteur : ancien contact id=".$oldContactId." introuvable dans socpeople", 'warn');
		return 0;
	}
	$oldContact = $db->fetch_object($res);
	$db->free($res);

	$nom    = trim($oldContact->lastname);
	$prenom = trim($oldContact->firstname);
	$key    = strtoupper($nom.'.'.$prenom);

	// Déjà en cache ?
	if (isset($cache[$key])) {
		out("       Conducteur : ".$nom." ".$prenom." → existant (id=".$cache[$key].")", 'debug', $verbose);
		return $cache[$key];
	}

	// Créer le conducteur Workshop
	$cond = new Conducteur($db);
	$cond->nom    = $nom;
	$cond->prenom = !empty($prenom) ? $prenom : '-';
	$cond->fk_soc = !empty($oldContact->fk_soc) ? (int) $oldContact->fk_soc : null;

	$newId = $cond->create($user, 1);
	if ($newId > 0) {
		$cache[$key] = $newId;
		out("       Conducteur : ".$nom." ".$prenom." → CRÉÉ (id=".$newId.")", 'ok');
		return $newId;
	}

	out("       Conducteur : ".$nom." ".$prenom." → ERREUR création : ".implode(', ', $cond->errors), 'err');
	return 0;
}


/**
 * Vérifie l'existence du véhicule Workshop correspondant à un ancien véhicule dolifleet.
 * Clé de comparaison : VIN.
 * Si le véhicule n'existe pas dans Workshop, il est créé avec les données de base.
 *
 * @param  DoliDB $db              Connexion base
 * @param  User   $user            Utilisateur système
 * @param  int    $oldVehiculeId   Rowid du véhicule dans llx_dolifleet_vehicule
 * @param  array  $cache           Référence vers $cacheVehicules
 * @param  bool   $verbose         Mode verbeux
 * @return int                     Rowid du véhicule Workshop, ou 0 si erreur
 */
function ensureVehicule($db, $user, $oldVehiculeId, &$cache, $verbose)
{
	if (empty($oldVehiculeId)) {
		return 0;
	}

	// Lire l'ancien véhicule dolifleet
	$sql = "SELECT * FROM ".MAIN_DB_PREFIX."dolifleet_vehicule WHERE rowid = ".(int) $oldVehiculeId;
	$res = $db->query($sql);
	if (!$res || $db->num_rows($res) == 0) {
		out("       Véhicule : ancien id=".$oldVehiculeId." introuvable dans dolifleet_vehicule", 'warn');
		return 0;
	}
	$oldVeh = $db->fetch_object($res);
	$db->free($res);

	$vin = trim($oldVeh->vin);
	if (empty($vin)) {
		out("       Véhicule : ancien id=".$oldVehiculeId." a un VIN vide", 'warn');
		return 0;
	}

	// Déjà en cache ?
	if (isset($cache[$vin])) {
		out("       Véhicule : VIN=".$vin." → existant (id=".$cache[$vin].")", 'debug', $verbose);
		return $cache[$vin];
	}

	// Créer le véhicule Workshop avec les données de base
	$veh = new Vehicule($db);
	$veh->vin              = $vin;
	$veh->entity           = !empty($oldVeh->entity) ? (int) $oldVeh->entity : 1;
	$veh->status           = Vehicule::STATUS_ACTIVE;
	$veh->immatriculation  = !empty($oldVeh->immatriculation) ? trim($oldVeh->immatriculation) : '';
	$veh->date_immat       = !empty($oldVeh->date_immat) ? $oldVeh->date_immat : null;
	$veh->fk_soc           = !empty($oldVeh->fk_soc) ? (int) $oldVeh->fk_soc : null;
	$veh->modele           = !empty($oldVeh->modele) ? $oldVeh->modele : '';
	$veh->km               = !empty($oldVeh->km) ? (float) $oldVeh->km : 0;

	// fk_vehicule_type et fk_vehicule_mark : résolution varchar → integer FK
	$veh->fk_vehicule_type = resolveVehiculeDict($db, 'workshop_vehicule_c_vehicule_type', $oldVeh->fk_vehicule_type);
	$veh->fk_vehicule_mark = resolveVehiculeDict($db, 'workshop_vehicule_c_vehicule_mark', $oldVeh->fk_vehicule_mark);

	$veh->import_key = 'mig-veh-'.(int) $oldVeh->rowid;

	$newId = $veh->create($user, 1);
	if ($newId > 0) {
		$cache[$vin] = $newId;
		out("       Véhicule : VIN=".$vin." (".$veh->immatriculation.") → CRÉÉ (id=".$newId.")", 'ok');
		return $newId;
	}

	out("       Véhicule : VIN=".$vin." → ERREUR création : ".$veh->error.' '.implode(', ', $veh->errors), 'err');
	return 0;
}


/**
 * Résout un label/code varchar de l'ancien dolifleet vers un rowid de dictionnaire Workshop.
 * Cherche par label (case-insensitive). Crée l'entrée si elle n'existe pas.
 *
 * @param  DoliDB $db         Connexion base
 * @param  string $tableName  Nom de la table dictionnaire (sans préfixe), ex: 'workshop_vehicule_c_vehicule_type'
 * @param  string $oldValue   Valeur de l'ancien champ varchar (label ou code)
 * @return int                Rowid dans la table dictionnaire, ou 0 si vide
 */
function resolveVehiculeDict($db, $tableName, $oldValue)
{
	if (empty($oldValue) || trim($oldValue) === '' || trim($oldValue) === '0') {
		return 0;
	}

	$label = trim($oldValue);

	// Chercher par label (case-insensitive)
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$tableName;
	$sql .= " WHERE UPPER(label) = '".$db->escape(strtoupper($label))."'";
	$sql .= " LIMIT 1";
	$res = $db->query($sql);
	if ($res && $db->num_rows($res) > 0) {
		$obj = $db->fetch_object($res);
		return (int) $obj->rowid;
	}

	// Chercher par code
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$tableName;
	$sql .= " WHERE UPPER(code) = '".$db->escape(strtoupper($label))."'";
	$sql .= " LIMIT 1";
	$res = $db->query($sql);
	if ($res && $db->num_rows($res) > 0) {
		$obj = $db->fetch_object($res);
		return (int) $obj->rowid;
	}

	// Créer l'entrée
	$code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', substr($label, 0, 20)));
	$sql = "INSERT INTO ".MAIN_DB_PREFIX.$tableName." (code, label, active) VALUES ('".$db->escape($code)."', '".$db->escape($label)."', 1)";
	$db->query($sql);
	$newId = $db->last_insert_id(MAIN_DB_PREFIX.$tableName);
	return (int) $newId;
}


/**
 * Vérifie/crée le tag Workshop correspondant à la catégorie d'un ancien OR.
 * Clé de comparaison : code du tag.
 *
 * Le champ `categories` de l'ancien OR est un varchar qui stocke une clé entière
 * (arrayofkeyval : 0=depannage, 1=travaux exterieurs, 2=véhicule non présenté).
 * On convertit en code TAG majuscule.
 *
 * @param  DoliDB $db           Connexion base
 * @param  User   $user         Utilisateur système
 * @param  string $oldCategVal  Valeur du champ categories de l'ancien OR
 * @param  array  $cache        Référence vers $cacheTags
 * @param  bool   $verbose      Mode verbeux
 * @return int                  Rowid du tag Workshop, ou 0 si non applicable
 */
function ensureTag($db, $user, $oldCategVal, &$cache, $verbose)
{
	if ($oldCategVal === null || $oldCategVal === '') {
		return 0;
	}

	// Mapping des anciennes valeurs entières vers des codes/labels
	$categoriesMapping = array(
		'0' => array('code' => 'DEPANNAGE',              'label' => 'Dépannage'),
		'1' => array('code' => 'TRAVAUX_EXT',            'label' => 'Travaux extérieurs'),
		'2' => array('code' => 'VEHICULE_NON_PRESENTE',  'label' => 'Véhicule non présenté'),
	);

	$val = trim((string) $oldCategVal);

	// Si c'est une clé connue du mapping
	if (isset($categoriesMapping[$val])) {
		$code  = $categoriesMapping[$val]['code'];
		$label = $categoriesMapping[$val]['label'];
	} else {
		// Valeur inconnue : on fabrique un code à partir de la valeur
		$code  = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', substr($val, 0, 20)));
		$label = $val;
		if (empty($code)) {
			return 0;
		}
	}

	// Déjà en cache ?
	if (isset($cache[$code])) {
		out("       Tag : code=".$code." → existant (id=".$cache[$code].")", 'debug', $verbose);
		return $cache[$code];
	}

	// Créer le tag Workshop
	$tag = new Tag($db);
	$tag->code   = $code;
	$tag->label  = $label;
	$tag->color  = '#3c8dbc';
	$tag->active = 1;

	$newId = $tag->create($user, 1);
	if ($newId > 0) {
		$cache[$code] = $newId;
		out("       Tag : code=".$code." (".$label.") → CRÉÉ (id=".$newId.")", 'ok');
		return $newId;
	}

	out("       Tag : code=".$code." → ERREUR création : ".implode(', ', $tag->errors), 'err');
	return 0;
}


// ============================================================================
// 9. Comptage et lecture des anciens OR
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

// Pré-chargement des caches
out("Chargement des caches...", 'info');
preloadCacheStatus($db, $cacheStatus);
out("  Statuts Workshop en cache : ".count($cacheStatus), 'debug', $verbose);
preloadCacheConducteurs($db, $cacheConducteurs);
out("  Conducteurs Workshop en cache : ".count($cacheConducteurs), 'debug', $verbose);
preloadCacheVehicules($db, $cacheVehicules);
out("  Véhicules Workshop en cache : ".count($cacheVehicules), 'debug', $verbose);
preloadCacheTags($db, $cacheTags);
out("  Tags Workshop en cache : ".count($cacheTags), 'debug', $verbose);
print "\n";

// ============================================================================
// 10. Boucle principale : lecture des anciens OR
// ============================================================================

$sql  = "SELECT rowid, ref, ref_client, entity, fk_soc, fk_vehicule, fk_conducteur,";
$sql .= " status, date_creation, date_valid, planned_date,";
$sql .= " km_on_creation, note_public, note_private,";
$sql .= " fk_user_creat, fk_user_modif, fk_user_valid, fk_user_meca,";
$sql .= " model_pdf, last_main_doc,";
$sql .= " total_ht, total_ht_part, total_ht_mo, total_ht_service,";
$sql .= " total_ht_external, total_ht_reimbursement,";
$sql .= " time_planned_f, orcheck, import_key, categories";
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
	// Phase 0 — Vérifier/créer les valeurs satellites
	// =====================================================

	$satError = false;

	// 0a. Statut
	$newStatusId = ensureStatus($db, $user, $oldOR->status, $cacheStatus, $verbose);
	if (!empty($oldOR->status) && empty($newStatusId)) {
		out("       → ERREUR : impossible de résoudre le statut (ancien id=".$oldOR->status.")", 'err');
		$satError = true;
	}

	// 0b. Conducteur
	$newConducteurId = ensureConducteur($db, $user, $oldOR->fk_conducteur, $cacheConducteurs, $verbose);
	if (!empty($oldOR->fk_conducteur) && empty($newConducteurId)) {
		out("       → WARN : conducteur non résolu (ancien id=".$oldOR->fk_conducteur."), sera NULL", 'warn');
	}

	// 0c. Véhicule
	$newVehiculeId = ensureVehicule($db, $user, $oldOR->fk_vehicule, $cacheVehicules, $verbose);
	if (!empty($oldOR->fk_vehicule) && empty($newVehiculeId)) {
		out("       → ERREUR : impossible de résoudre le véhicule (ancien id=".$oldOR->fk_vehicule.")", 'err');
		$satError = true;
	}

	// 0d. Tag (catégorie)
	$newTagId = ensureTag($db, $user, $oldOR->categories, $cacheTags, $verbose);
	// Le tag est optionnel, pas bloquant si absent

	if ($satError) {
		out("       → OR ".$oldOR->ref." IGNORÉ (valeur satellite manquante)", 'err');
		$countErrors++;
		continue;
	}

	// =====================================================
	// TODO : Phase 1 — Créer l'OR Workshop + jobs + lignes
	// =====================================================
	// $newStatusId, $newConducteurId, $newVehiculeId, $newTagId
	// sont prêts à être utilisés pour la création de l'OR

	out("       → satellites OK (statut=".$newStatusId.", conducteur=".$newConducteurId.", vehicule=".$newVehiculeId.", tag=".$newTagId.")", 'debug', $verbose);
	out("       → migration entête/lignes non encore implémentée", 'warn');
	$countErrors++;
}

$db->free($resql);

// ============================================================================
// 11. Rapport final
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
