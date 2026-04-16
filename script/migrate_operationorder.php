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
	'c_operationorder_type',
	'product_extrafields',
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
	'workshop_c_servicetype',
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
$cacheTags         = array(); // code (string)                    => new tag rowid
$cacheServiceTypes = array(); // code (string)                    => new servicetype rowid

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
 * Pré-charge le cache des ServiceType Workshop existants (par code).
 *
 * @param  DoliDB $db    Connexion base
 * @param  array  $cache Référence vers $cacheServiceTypes
 * @return void
 */
function preloadCacheServiceTypes($db, &$cache)
{
	$sql = "SELECT rowid, code FROM ".MAIN_DB_PREFIX."workshop_c_servicetype WHERE code IS NOT NULL AND code != ''";
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


/**
 * Vérifie/crée le ServiceType Workshop correspondant à un ancien type d'opération.
 * Clé de comparaison : code du type.
 *
 * Si $oldTypeId est vide/0, retourne le ServiceType "MECA_GEN" (créé si nécessaire).
 *
 * @param  DoliDB $db          Connexion base
 * @param  User   $user        Utilisateur système
 * @param  int    $oldTypeId   Rowid dans llx_c_operationorder_type (ou 0/NULL pour MECA_GEN)
 * @param  array  $cache       Référence vers $cacheServiceTypes
 * @param  bool   $verbose     Mode verbeux
 * @return int                 Rowid du ServiceType Workshop, ou 0 si erreur
 */
function ensureServiceType($db, $user, $oldTypeId, &$cache, $verbose)
{
	// --- Cas par défaut : Mécanique Générale ---
	if (empty($oldTypeId)) {
		$code = 'MECA_GEN';
		if (isset($cache[$code])) {
			out("       ServiceType : code=".$code." → existant (id=".$cache[$code].")", 'debug', $verbose);
			return $cache[$code];
		}

		$st = new ServiceType($db);
		$st->code      = 'MECA_GEN';
		$st->label     = 'Mécanique Générale';
		$st->prix_mo   = 0;
		$st->tva_tx_mo = 20;
		$st->tva_tx_st = 20;
		$st->plannable = 0;
		$st->active    = 1;

		$newId = $st->create($user, 1);
		if ($newId > 0) {
			$cache[$code] = $newId;
			out("       ServiceType : code=MECA_GEN → CRÉÉ (id=".$newId.")", 'ok');
			return $newId;
		}
		out("       ServiceType : MECA_GEN → ERREUR création : ".$st->error.' '.implode(', ', $st->errors), 'err');
		return 0;
	}

	// --- Lecture de l'ancien type ---
	$sql = "SELECT * FROM ".MAIN_DB_PREFIX."c_operationorder_type WHERE rowid = ".(int) $oldTypeId;
	$res = $db->query($sql);
	if (!$res || $db->num_rows($res) == 0) {
		out("       ServiceType : ancien id=".$oldTypeId." introuvable → fallback MECA_GEN", 'warn');
		return ensureServiceType($db, $user, 0, $cache, $verbose);
	}
	$oldType = $db->fetch_object($res);
	$db->free($res);

	$code = trim($oldType->code);
	if (empty($code)) {
		// Fabriquer un code à partir du label
		$code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', substr(trim($oldType->label), 0, 20)));
	}
	if (empty($code)) {
		out("       ServiceType : ancien id=".$oldTypeId." a un code ET label vides → fallback MECA_GEN", 'warn');
		return ensureServiceType($db, $user, 0, $cache, $verbose);
	}

	// Déjà en cache ?
	if (isset($cache[$code])) {
		out("       ServiceType : code=".$code." → existant (id=".$cache[$code].")", 'debug', $verbose);
		return $cache[$code];
	}

	// Créer le ServiceType Workshop
	$st = new ServiceType($db);
	$st->code      = $code;
	$st->label     = !empty($oldType->label) ? $oldType->label : $code;
	$st->fk_soc    = !empty($oldType->fk_soc) ? (int) $oldType->fk_soc : null;
	$st->prix_mo   = 0;
	$st->tva_tx_mo = 20;
	$st->tva_tx_st = 20;
	$st->plannable = 0;
	$st->active    = 1;

	$newId = $st->create($user, 1);
	if ($newId > 0) {
		$cache[$code] = $newId;
		out("       ServiceType : code=".$code." (".$st->label.") → CRÉÉ (id=".$newId.")", 'ok');
		return $newId;
	}

	out("       ServiceType : code=".$code." → ERREUR création : ".$st->error.' '.implode(', ', $st->errors), 'err');
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
preloadCacheServiceTypes($db, $cacheServiceTypes);
out("  ServiceTypes Workshop en cache : ".count($cacheServiceTypes), 'debug', $verbose);
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
$errorDetails  = array(); // Tableau des OR en erreur : array('old_id' => ..., 'ref' => ..., 'reason' => ...)
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
		$reason = "Valeur satellite manquante (statut=".$oldOR->status.", vehicule=".$oldOR->fk_vehicule.")";
		out("       → OR ".$oldOR->ref." IGNORÉ : ".$reason, 'err');
		$errorDetails[] = array('old_id' => $oldOR->rowid, 'ref' => $oldOR->ref, 'reason' => $reason);
		$countErrors++;
		continue;
	}

	out("       → satellites OK (statut=".$newStatusId.", conducteur=".$newConducteurId.", vehicule=".$newVehiculeId.", tag=".$newTagId.")", 'debug', $verbose);

	// =====================================================
	// Phase 1a — Créer l'entête de l'OR Workshop
	// =====================================================

	$db->begin();

	$or = new Operationorder($db);
	$or->ref                 = $oldOR->ref;
	$or->ref_client          = $oldOR->ref_client;
	$or->entity              = (int) $oldOR->entity;
	$or->fk_soc              = (int) $oldOR->fk_soc;
	$or->fk_vehicule         = $newVehiculeId;
	$or->fk_conducteur       = !empty($newConducteurId) ? $newConducteurId : null;
	$or->status              = $newStatusId;
	$or->date_planned        = $oldOR->planned_date;
	$or->date_valid          = $oldOR->date_valid;
	$or->date_start          = null;
	$or->date_end            = null;
	$or->km                  = !empty($oldOR->km_on_creation) ? (float) $oldOR->km_on_creation : 0;
	$or->fk_user_assign      = !empty($oldOR->fk_user_meca) ? (int) $oldOR->fk_user_meca : null;
	$or->temps_immobilisation = !empty($oldOR->time_planned_f) ? (float) $oldOR->time_planned_f : null;
	$or->check_or            = isset($oldOR->orcheck) ? (int) $oldOR->orcheck : 0;
	$or->note_public         = $oldOR->note_public;
	$or->note_private        = $oldOR->note_private;
	$or->model_pdf           = $oldOR->model_pdf;
	$or->last_main_doc       = $oldOR->last_main_doc;
	$or->fk_user_creat       = !empty($oldOR->fk_user_creat) ? (int) $oldOR->fk_user_creat : $user->id;
	$or->fk_user_modif       = !empty($oldOR->fk_user_modif) ? (int) $oldOR->fk_user_modif : null;
	$or->fk_user_valid       = !empty($oldOR->fk_user_valid) ? (int) $oldOR->fk_user_valid : null;
	$or->import_key          = $importKey;

	// Totaux à 0 — seront recalculés après migration des jobs/lignes
	$or->total_ht            = 0;
	$or->total_ht_part       = 0;
	$or->total_ht_mo         = 0;
	$or->total_ht_service    = 0;
	$or->total_ht_external   = 0;
	$or->total_ht_refund     = 0;

	$newORId = $or->create($user, 1); // notrigger=1

	if ($newORId <= 0) {
		$db->rollback();
		$reason = "Erreur createCommon : ".$or->error.' '.implode(', ', $or->errors);
		out("       → ERREUR création OR : ".$reason, 'err');
		$errorDetails[] = array('old_id' => $oldOR->rowid, 'ref' => $oldOR->ref, 'reason' => $reason);
		$countErrors++;
		continue;
	}

	// --- Corrections post-création ---
	// createCommon force entity=$conf->entity, date_creation=dol_now() et ref=(PROVxxx)
	// On rétablit les valeurs d'origine
	$sqlFix = "UPDATE ".MAIN_DB_PREFIX."workshop_operationorder SET";
	$sqlFix .= " ref = '".$db->escape($oldOR->ref)."'";
	$sqlFix .= ", entity = ".(int) $oldOR->entity;
	$sqlFix .= ", date_creation = ".($oldOR->date_creation ? "'".$db->escape($oldOR->date_creation)."'" : "NULL");
	$sqlFix .= " WHERE rowid = ".(int) $newORId;
	$resFix = $db->query($sqlFix);
	if (!$resFix) {
		$db->rollback();
		$reason = "Erreur correction ref/entity/date_creation : ".$db->lasterror();
		out("       → ERREUR post-correction OR : ".$reason, 'err');
		$errorDetails[] = array('old_id' => $oldOR->rowid, 'ref' => $oldOR->ref, 'reason' => $reason);
		$countErrors++;
		continue;
	}
	// Mettre à jour l'objet en mémoire aussi
	$or->ref    = $oldOR->ref;
	$or->entity = (int) $oldOR->entity;

	// --- Lien tag (catégorie) ---
	if (!empty($newTagId)) {
		$orTag = new OperationorderTag($db);
		$tagResult = $orTag->addTag($newORId, $newTagId, $user);
		if ($tagResult < 0) {
			out("       → WARN lien tag : ".$orTag->error, 'warn');
		} else {
			out("       Tag lié à l'OR (tag_id=".$newTagId.")", 'debug', $verbose);
		}
	}

	// =====================================================
	// Phase 1b — Créer les jobs + lignes de l'OR
	// =====================================================

	// 1b.1 : Lire toutes les anciennes lignes de cet OR avec extrafields produit
	$sqlDet  = "SELECT od.*,";
	$sqlDet .= " COALESCE(pex.or_is_job, 0) AS ex_or_is_job,";
	$sqlDet .= " COALESCE(pex.or_scan, 0) AS ex_or_scan,";
	$sqlDet .= " COALESCE(pex.oorder_available_for_supplier_order, 0) AS ex_or_st";
	$sqlDet .= " FROM ".MAIN_DB_PREFIX."operationorderdet od";
	$sqlDet .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = od.fk_product";
	$sqlDet .= " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields pex ON pex.fk_object = p.rowid";
	$sqlDet .= " WHERE od.fk_operation_order = ".(int) $oldOR->rowid;
	$sqlDet .= " ORDER BY od.rang ASC, od.rowid ASC";

	$resDet = $db->query($sqlDet);
	if (!$resDet) {
		$db->rollback();
		$reason = "Erreur lecture anciennes lignes : ".$db->lasterror();
		out("       → ".$reason, 'err');
		$errorDetails[] = array('old_id' => $oldOR->rowid, 'ref' => $oldOR->ref, 'reason' => $reason);
		$countErrors++;
		continue;
	}

	$allLines = array();
	while ($line = $db->fetch_object($resDet)) {
		$allLines[] = $line;
	}
	$db->free($resDet);

	// 1b.2 : Classifier les lignes
	$jobLines     = array(); // Lignes qui SONT des jobs (or_is_job=1, pas enfant)
	$moLines      = array(); // Main d'œuvre (or_scan=1)
	$stLines      = array(); // Sous-traitance (oorder_available_for_supplier_order=1)
	$regularLines = array(); // Produits/services normaux

	foreach ($allLines as $line) {
		$isJob = ((int) $line->ex_or_is_job === 1) && (empty($line->fk_parent_line) || (int) $line->fk_parent_line === 0);
		$isMO  = ((int) $line->ex_or_scan === 1) && !$isJob;
		$isST  = ((int) $line->ex_or_st === 1) && !$isJob && !$isMO;

		if ($isJob) {
			$jobLines[] = $line;
		} elseif ($isMO) {
			$moLines[] = $line;
		} elseif ($isST) {
			$stLines[] = $line;
		} else {
			$regularLines[] = $line;
		}
	}

	out("       Lignes : ".count($allLines)." total (".count($jobLines)." jobs, ".count($moLines)." MO, ".count($stLines)." ST, ".count($regularLines)." régulières)", 'debug', $verbose);

	// 1b.3 : Ensemble des rowid des anciennes lignes-jobs (pour résolution des parents)
	$jobRowids = array();
	foreach ($jobLines as $jl) {
		$jobRowids[] = (int) $jl->rowid;
	}

	// 1b.4 : Agrégation MO par job parent
	// Pour les OR créés avant le 01/01/2026, la quantité MO = time_spent (secondes) / 3600
	// Pour les OR plus récents, la quantité MO = qty de la ligne
	// Le prix horaire MO vient du champ price de la ligne MO
	$isOldOR = (!empty($oldOR->date_creation) && $oldOR->date_creation < '2026-01-01');

	// prix_mo = SUM(price * qty) / SUM(qty) → moyenne pondérée du taux horaire
	$moAgg = array();       // old_parent_rowid => array('qty' => float, 'price_x_qty' => float, 'descriptions' => array())
	$orphanMoAgg = array('qty' => 0, 'price_x_qty' => 0, 'descriptions' => array());

	foreach ($moLines as $ml) {
		// Quantité : time_spent en heures centièmes pour les anciens OR, sinon qty
		$moQty   = $isOldOR ? ((float) $ml->time_spent / 3600) : (float) $ml->qty;
		$moPrice = (float) $ml->price;

		$parentId = (int) $ml->fk_parent_line;
		if ($parentId > 0 && in_array($parentId, $jobRowids)) {
			if (!isset($moAgg[$parentId])) {
				$moAgg[$parentId] = array('qty' => 0, 'price_x_qty' => 0, 'descriptions' => array());
			}
			$moAgg[$parentId]['qty']         += $moQty;
			$moAgg[$parentId]['price_x_qty'] += $moPrice * $moQty;
			if (!empty(trim($ml->description))) {
				$moAgg[$parentId]['descriptions'][] = $ml->description;
			}
		} else {
			$orphanMoAgg['qty']         += $moQty;
			$orphanMoAgg['price_x_qty'] += $moPrice * $moQty;
			if (!empty(trim($ml->description))) {
				$orphanMoAgg['descriptions'][] = $ml->description;
			}
		}
	}

	// 1b.5 : Agrégation ST par job parent
	$stAgg = array();       // old_parent_rowid => array('total_ht' => float, 'descriptions' => array())
	$orphanStAgg = array('total_ht' => 0, 'descriptions' => array());

	foreach ($stLines as $sl) {
		$parentId = (int) $sl->fk_parent_line;
		if ($parentId > 0 && in_array($parentId, $jobRowids)) {
			if (!isset($stAgg[$parentId])) {
				$stAgg[$parentId] = array('total_ht' => 0, 'descriptions' => array());
			}
			$stAgg[$parentId]['total_ht'] += (float) $sl->total_ht;
			if (!empty(trim($sl->description))) {
				$stAgg[$parentId]['descriptions'][] = $sl->description;
			}
		} else {
			$orphanStAgg['total_ht'] += (float) $sl->total_ht;
			if (!empty(trim($sl->description))) {
				$orphanStAgg['descriptions'][] = $sl->description;
			}
		}
	}

	// 1b.6 : Identifier les lignes régulières orphelines vs rattachées
	$orphanRegularLines   = array();
	$parentedRegularLines = array(); // old_parent_rowid => array(lines)
	foreach ($regularLines as $rl) {
		$parentId = (int) $rl->fk_parent_line;
		if ($parentId > 0 && in_array($parentId, $jobRowids)) {
			if (!isset($parentedRegularLines[$parentId])) {
				$parentedRegularLines[$parentId] = array();
			}
			$parentedRegularLines[$parentId][] = $rl;
		} else {
			$orphanRegularLines[] = $rl;
		}
	}

	// 1b.7 : Déterminer si un job catch-all est nécessaire
	$needCatchAll = !empty($allLines) && (
		empty($jobLines)
		|| !empty($orphanRegularLines)
		|| $orphanMoAgg['qty'] > 0
		|| $orphanMoAgg['price_x_qty'] != 0
		|| $orphanStAgg['total_ht'] != 0
	);

	// 1b.8 : Créer les jobs issus des lignes or_is_job=1
	$jobMapping = array(); // old_det_rowid => Operationorder_jobs object (avec ->id renseigné)
	$allNewJobs = array(); // tous les nouveaux jobs créés
	$phaseError = false;

	foreach ($jobLines as $jl) {
		// Résolution du ServiceType
		$newServiceTypeId = ensureServiceType($db, $user, $jl->fk_c_operationorder_type, $cacheServiceTypes, $verbose);

		// Construction de la description : originale + MO + ST concaténées
		$descParts = array();
		if (!empty(trim($jl->description))) {
			$descParts[] = $jl->description;
		}
		if (isset($moAgg[(int) $jl->rowid]) && !empty($moAgg[(int) $jl->rowid]['descriptions'])) {
			$descParts = array_merge($descParts, $moAgg[(int) $jl->rowid]['descriptions']);
		}
		if (isset($stAgg[(int) $jl->rowid]) && !empty($stAgg[(int) $jl->rowid]['descriptions'])) {
			$descParts = array_merge($descParts, $stAgg[(int) $jl->rowid]['descriptions']);
		}

		// Résolution du fk_soc depuis le ServiceType
		$jobFkSoc = null;
		if ($newServiceTypeId > 0) {
			$sqlST = "SELECT fk_soc FROM ".MAIN_DB_PREFIX."workshop_c_servicetype WHERE rowid = ".(int) $newServiceTypeId;
			$resST = $db->query($sqlST);
			if ($resST && $db->num_rows($resST) > 0) {
				$objST = $db->fetch_object($resST);
				$jobFkSoc = !empty($objST->fk_soc) ? (int) $objST->fk_soc : null;
			}
		}

		// MO agrégée pour ce job : prix_mo = SUM(price*qty) / SUM(qty)
		$jobQtyMo      = isset($moAgg[(int) $jl->rowid]) ? $moAgg[(int) $jl->rowid]['qty'] : 0;
		$jobPriceXQty  = isset($moAgg[(int) $jl->rowid]) ? $moAgg[(int) $jl->rowid]['price_x_qty'] : 0;
		$jobPrixMo     = ($jobQtyMo > 0) ? $jobPriceXQty / $jobQtyMo : 0;

		// ST agrégée pour ce job
		$jobTotalExt = isset($stAgg[(int) $jl->rowid]) ? $stAgg[(int) $jl->rowid]['total_ht'] : 0;

		$job = new Operationorder_jobs($db);
		$job->fk_operationorder = $newORId;
		$job->label             = !empty($jl->label) ? $jl->label : 'Job';
		$job->description       = !empty($descParts) ? implode("\n", $descParts) : null;
		$job->fk_service_type   = $newServiceTypeId > 0 ? $newServiceTypeId : null;
		$job->fk_job_type       = null;
		$job->fk_soc            = $jobFkSoc;
		$job->fk_user_assign    = null;
		$job->qty_mo            = $jobQtyMo;
		$job->prix_mo           = $jobPrixMo;
		$job->remise_percent    = 0;
		$job->tva_tx_mo         = 20;
		$job->tva_tx_st         = 20;
		$job->rang              = (int) $jl->rang;
		$job->time_planned      = (int) $jl->time_planned;
		$job->time_spent        = (int) $jl->time_spent;
		$job->total_ht          = 0;
		$job->total_ht_part     = 0;
		$job->total_ht_mo       = 0;
		$job->total_ht_service  = 0;
		$job->total_ht_external = $jobTotalExt;
		$job->total_ht_refund   = 0;
		$job->fk_user_creat     = !empty($jl->fk_user_creat) ? (int) $jl->fk_user_creat : $user->id;
		$job->fk_user_modif     = !empty($jl->fk_user_modif) ? (int) $jl->fk_user_modif : null;
		$job->import_key        = 'mig-job-'.(int) $jl->rowid;

		$newJobId = $job->create($user, 1);
		if ($newJobId <= 0) {
			$reason = "Erreur création job (ancien det rowid=".$jl->rowid.") : ".$job->error.' '.implode(', ', $job->errors);
			out("       → ".$reason, 'err');
			$phaseError = true;
			break;
		}

		$jobMapping[(int) $jl->rowid] = $job;
		$allNewJobs[] = $job;
		out("       Job créé : \"".$job->label."\" (new id=".$newJobId.", ancien det=".$jl->rowid.")", 'debug', $verbose);
	}

	if ($phaseError) {
		$db->rollback();
		$errorDetails[] = array('old_id' => $oldOR->rowid, 'ref' => $oldOR->ref, 'reason' => 'Erreur création des jobs');
		$countErrors++;
		continue;
	}

	// 1b.9 : Créer le job catch-all "Mécanique Générale" si nécessaire
	$catchAllJob = null;
	if ($needCatchAll) {
		$mecaGenSTId = ensureServiceType($db, $user, 0, $cacheServiceTypes, $verbose);

		// Description depuis MO/ST orphelines
		$catchDescParts = array();
		if (!empty($orphanMoAgg['descriptions'])) {
			$catchDescParts = array_merge($catchDescParts, $orphanMoAgg['descriptions']);
		}
		if (!empty($orphanStAgg['descriptions'])) {
			$catchDescParts = array_merge($catchDescParts, $orphanStAgg['descriptions']);
		}

		$catchAllJob = new Operationorder_jobs($db);
		$catchAllJob->fk_operationorder = $newORId;
		$catchAllJob->label             = 'Mécanique Générale';
		$catchAllJob->description       = !empty($catchDescParts) ? implode("\n", $catchDescParts) : null;
		$catchAllJob->fk_service_type   = $mecaGenSTId > 0 ? $mecaGenSTId : null;
		$catchAllJob->fk_job_type       = null;
		$catchAllJob->fk_soc            = null;
		$catchAllJob->fk_user_assign    = null;
		$catchAllJob->qty_mo            = $orphanMoAgg['qty'];
		$catchAllJob->prix_mo           = ($orphanMoAgg['qty'] > 0) ? $orphanMoAgg['price_x_qty'] / $orphanMoAgg['qty'] : 0;
		$catchAllJob->remise_percent    = 0;
		$catchAllJob->tva_tx_mo         = 20;
		$catchAllJob->tva_tx_st         = 20;
		$catchAllJob->rang              = 999;
		$catchAllJob->time_planned      = 0;
		$catchAllJob->time_spent        = 0;
		$catchAllJob->total_ht          = 0;
		$catchAllJob->total_ht_part     = 0;
		$catchAllJob->total_ht_mo       = 0;
		$catchAllJob->total_ht_service  = 0;
		$catchAllJob->total_ht_external = $orphanStAgg['total_ht'];
		$catchAllJob->total_ht_refund   = 0;
		$catchAllJob->fk_user_creat     = !empty($oldOR->fk_user_creat) ? (int) $oldOR->fk_user_creat : $user->id;
		$catchAllJob->import_key        = 'mig-catchall-'.$newORId;

		$newCatchAllId = $catchAllJob->create($user, 1);
		if ($newCatchAllId <= 0) {
			$reason = "Erreur création job catch-all : ".$catchAllJob->error.' '.implode(', ', $catchAllJob->errors);
			out("       → ".$reason, 'err');
			$db->rollback();
			$errorDetails[] = array('old_id' => $oldOR->rowid, 'ref' => $oldOR->ref, 'reason' => $reason);
			$countErrors++;
			continue;
		}

		$allNewJobs[] = $catchAllJob;
		out("       Job catch-all \"Mécanique Générale\" créé (new id=".$newCatchAllId.")", 'debug', $verbose);
	}

	// 1b.10 : Créer les lignes de détail (Operationorderdet) pour les lignes régulières
	foreach ($regularLines as $rl) {
		// Déterminer le job parent
		$parentId = (int) $rl->fk_parent_line;
		$targetJob = null;
		if ($parentId > 0 && isset($jobMapping[$parentId])) {
			$targetJob = $jobMapping[$parentId];
		} elseif ($catchAllJob) {
			$targetJob = $catchAllJob;
		}

		if (!$targetJob) {
			out("       → WARN : ligne orpheline sans catch-all (det rowid=".$rl->rowid."), ignorée", 'warn');
			continue;
		}

		$det = new Operationorderdet($db);
		$det->fk_operationorder_jobs = $targetJob->id;
		$det->fk_product       = !empty($rl->fk_product) ? (int) $rl->fk_product : null;
		$det->label            = $rl->label;
		$det->description      = $rl->description;
		$det->product_type     = (int) $rl->product_type;
		$det->qty              = (float) $rl->qty;
		$det->price            = (float) $rl->price;
		$det->remise_percent   = (float) $rl->remise_percent;
		$det->pc               = $rl->pc;
		$det->pr               = !empty($rl->pr) ? (float) $rl->pr : null;
		$det->fk_warehouse     = (!empty($rl->fk_warehouse) && $rl->fk_warehouse !== '0' && $rl->fk_warehouse !== '') ? (int) $rl->fk_warehouse : null;
		$det->info_bits        = (int) $rl->info_bits;
		$det->rang             = (int) $rl->rang;
		$det->fk_user_creat    = !empty($rl->fk_user_creat) ? (int) $rl->fk_user_creat : $user->id;
		$det->fk_user_modif    = !empty($rl->fk_user_modif) ? (int) $rl->fk_user_modif : null;
		$det->import_key       = (string) $rl->rowid;

		// create() appelle computeTotals() qui recalcule total_ht, total_ht_part/service/refund
		$newDetId = $det->create($user, 1);
		if ($newDetId <= 0) {
			$reason = "Erreur création det (ancien det rowid=".$rl->rowid.") : ".$det->error.' '.implode(', ', $det->errors);
			out("       → ".$reason, 'err');
			$phaseError = true;
			break;
		}

		out("       Det créée : id=".$newDetId." (ancien det=".$rl->rowid.", job=".$targetJob->id.")", 'debug', $verbose);
	}

	if ($phaseError) {
		$db->rollback();
		$errorDetails[] = array('old_id' => $oldOR->rowid, 'ref' => $oldOR->ref, 'reason' => 'Erreur création des lignes de détail');
		$countErrors++;
		continue;
	}

	// 1b.11 : Migrer les liens element_element pour les lignes ST
	foreach ($stLines as $sl) {
		// Déterminer le job cible
		$parentId = (int) $sl->fk_parent_line;
		$targetJob = null;
		if ($parentId > 0 && isset($jobMapping[$parentId])) {
			$targetJob = $jobMapping[$parentId];
		} elseif ($catchAllJob) {
			$targetJob = $catchAllJob;
		}
		if (!$targetJob) {
			continue;
		}

		// Liens operationorderdet → order_supplier (source = ancienne det)
		$sqlLink  = "SELECT fk_target FROM ".MAIN_DB_PREFIX."element_element";
		$sqlLink .= " WHERE fk_source = ".(int) $sl->rowid;
		$sqlLink .= " AND sourcetype = 'operationorderdet' AND targettype = 'order_supplier'";
		$resLink = $db->query($sqlLink);
		if ($resLink) {
			while ($objLink = $db->fetch_object($resLink)) {
				$sqlIns = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."element_element (fk_source, sourcetype, fk_target, targettype)";
				$sqlIns .= " VALUES (".(int) $targetJob->id.", 'operationorder_jobs', ".(int) $objLink->fk_target.", 'order_supplier')";
				$db->query($sqlIns);
			}
			$db->free($resLink);
		}

		// Liens order_supplier → operationorderdet (target = ancienne det)
		$sqlLink2  = "SELECT fk_source FROM ".MAIN_DB_PREFIX."element_element";
		$sqlLink2 .= " WHERE fk_target = ".(int) $sl->rowid;
		$sqlLink2 .= " AND targettype = 'operationorderdet' AND sourcetype = 'order_supplier'";
		$resLink2 = $db->query($sqlLink2);
		if ($resLink2) {
			while ($objLink2 = $db->fetch_object($resLink2)) {
				$sqlIns2 = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."element_element (fk_source, sourcetype, fk_target, targettype)";
				$sqlIns2 .= " VALUES (".(int) $objLink2->fk_source.", 'order_supplier', ".(int) $targetJob->id.", 'operationorder_jobs')";
				$db->query($sqlIns2);
			}
			$db->free($resLink2);
		}
	}

	// 1b.12 : Migrer les liens element_element au niveau OR
	// Dans le nouveau module, add_object_linked utilise getElementType() = 'workshop_operationorder'
	// Les anciens liens utilisent sourcetype/targettype = 'operationorder'
	// On les duplique en remplaçant 'operationorder' par 'workshop_operationorder'
	$sqlOrLink  = "SELECT rowid, fk_source, sourcetype, fk_target, targettype";
	$sqlOrLink .= " FROM ".MAIN_DB_PREFIX."element_element";
	$sqlOrLink .= " WHERE (fk_source = ".(int) $oldOR->rowid." AND sourcetype = 'operationorder')";
	$sqlOrLink .= " OR (fk_target = ".(int) $oldOR->rowid." AND targettype = 'operationorder')";
	$resOrLink = $db->query($sqlOrLink);
	if ($resOrLink) {
		while ($objOrLink = $db->fetch_object($resOrLink)) {
			if ($objOrLink->sourcetype === 'operationorder' && (int) $objOrLink->fk_source === (int) $oldOR->rowid) {
				$sqlInsOr = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."element_element (fk_source, sourcetype, fk_target, targettype)";
				$sqlInsOr .= " VALUES (".(int) $newORId.", 'workshop_operationorder', ".(int) $objOrLink->fk_target.", '".$db->escape($objOrLink->targettype)."')";
				$db->query($sqlInsOr);
			}
			if ($objOrLink->targettype === 'operationorder' && (int) $objOrLink->fk_target === (int) $oldOR->rowid) {
				$sqlInsOr = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."element_element (fk_source, sourcetype, fk_target, targettype)";
				$sqlInsOr .= " VALUES (".(int) $objOrLink->fk_source.", '".$db->escape($objOrLink->sourcetype)."', ".(int) $newORId.", 'workshop_operationorder')";
				$db->query($sqlInsOr);
			}
		}
		$db->free($resOrLink);
	}

	// 1b.13 : Recalcul des totaux — jobs puis OR
	$jobCount = count($allNewJobs);
	foreach ($allNewJobs as $job) {
		$job->updateTotals($user);
	}
	$or->updateTotals($user);

	out("       Totaux recalculés (".$jobCount." jobs, ".count($regularLines)." det)", 'debug', $verbose);

	$db->commit();

	out("       → OR migré (new id=".$newORId.", ".$jobCount." jobs, ".count($regularLines)." det)", 'ok');
	$countMigrated++;
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

if (!empty($errorDetails)) {
	print "\n";
	print "  OR EN ERREUR (ancien rowid → ref → raison) :\n";
	print "  ----------------------------------------------------------\n";
	foreach ($errorDetails as $err) {
		print "  id=".$err['old_id']." | ref=".$err['ref']." | ".$err['reason']."\n";
	}
	print "  ----------------------------------------------------------\n";
	// Liste condensée des rowid pour requête SQL facile
	$errorIds = array_column($errorDetails, 'old_id');
	print "\n  Ancien rowid en erreur (copier-coller SQL) :\n";
	print "  ".implode(', ', $errorIds)."\n";
}

print "\n";

if ($dryRun) {
	print "Mode dry-run : aucune modification n'a été persistée.\n\n";
}

exit($countErrors > 0 ? 1 : 0);
