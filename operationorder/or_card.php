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
 * \file       operationorder/or_card.php
 * \ingroup    workshop
 * \brief      Création et affichage (vue) d'un Ordre de Réparation (OR)
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
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
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/workshop/class/operationorder.class.php');
dol_include_once('//workshop/class/vehicule.class.php');
dol_include_once('/workshop/class/Conducteur.class.php');
dol_include_once('/workshop/class/Tag.class.php');
dol_include_once('/workshop/class/OperationorderTag.class.php');
dol_include_once('/workshop/lib/workshop_operationorder.lib.php');
dol_include_once('/workshop/lib/workshop.lib.php');
dol_include_once('/workshop/lib/workshop_or_card.lib.php');
dol_include_once('/workshop/class/operationorder_jobs.class.php');
dol_include_once('/workshop/class/operationorderdet.class.php');
dol_include_once('/workshop/class/servicetype.class.php');
dol_include_once('/workshop/class/workshopjobtype.class.php');
dol_include_once('/workshop/class/vehiculeOperation.class.php');
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';

if (!isModEnabled('workshop')) {
	accessforbidden();
}
if (!$user->hasRight('workshop', 'operationorders', 'read')) {
	accessforbidden();
}

$langs->loadLangs(array('workshop@workshop'));

$action      = GETPOST('action', 'aZ09');
$confirm     = GETPOST('confirm', 'alpha');
$cancel      = GETPOST('cancel', 'alpha');
$id          = GETPOSTINT('id');
$backtopage  = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

$object      = new Operationorder($db);
$extrafields = new ExtraFields($db);
$form        = new Form($db);
$formfile    = new FormFile($db);
$formactions = new FormActions($db);

$extrafields->fetch_name_optionals_label($object->table_element);
$hookmanager->initHooks(array('orcard', 'globalcard'));

$permissiontoadd = $user->hasRight('workshop', 'operationorders', 'write');

// En mode vue : chargement de l'objet depuis la base
if ($id > 0) {
	$ret = $object->fetch($id);
	if ($ret <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
	// Pour un OR existant : droit d'écriture dépendant du statut courant
	$permissiontoadd = $object->canWrite($user);
	$permissiontoread = $object->canRead($user);
	if (!$permissiontoread) {
		accessforbidden();
	}
}

// Fonctions helpers orCardState* → voir lib/workshop_or_card.lib.php

/*
 * Actions
 */

if ($cancel) {
	if ($id > 0) {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
	} elseif (!empty($backtopageforcancel)) {
		header('Location: '.$backtopageforcancel);
	} elseif (!empty($backtopage)) {
		header('Location: '.$backtopage);
	} else {
		header('Location: '.$_SERVER['PHP_SELF']);
	}
	exit;
}

$parameters = array();
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {

	// ── Création d'un conducteur à la volée ─────────────────────────────────
	if ($action == 'confirm_new_conducteur' && $confirm == 'yes' && $permissiontoadd) {
		$error = 0;

		$conducteur         = new Conducteur($db);
		$conducteur->nom    = GETPOST('conducteur_nom', 'alphanohtml');
		$conducteur->prenom = GETPOST('conducteur_prenom', 'alphanohtml');
		$conducteur->fk_soc = GETPOSTINT('conducteur_fk_soc') ?: null;

		if (empty(trim((string) $conducteur->nom))) {
			setEventMessages($langs->trans('ErrConducteurNomRequired'), null, 'errors');
			$error++;
		}
		if (empty(trim((string) $conducteur->prenom))) {
			setEventMessages($langs->trans('ErrConducteurPrenomRequired'), null, 'errors');
			$error++;
		}

		if (!$error) {
			$newId = $conducteur->create($user);
			if ($newId > 0) {
				setEventMessage($langs->trans('ConducteurCreated'));
				header('Location: '.orCardRestoreUrl($_SERVER['PHP_SELF'], array('fk_conducteur' => $newId)));
				exit;
			} else {
				setEventMessages($conducteur->error, $conducteur->errors, 'errors');
			}
		}
		$action = 'new_conducteur';
	}

	// ── Création d'un tag à la volée ────────────────────────────────────────
	if ($action == 'confirm_new_tag' && $confirm == 'yes' && $permissiontoadd) {
		$error = 0;

		$tag         = new Tag($db);
		$tag->code   = GETPOST('tag_code', 'alphanohtml');
		$tag->label  = GETPOST('tag_label', 'alphanohtml');
		$rawColor    = GETPOST('tag_color', 'nohtml');
		$palette     = getWorkshopColorPalette();
		$tag->color  = isset($palette[$rawColor]) ? $rawColor : '';
		$tag->active = 1;

		if (empty(trim((string) $tag->code))) {
			setEventMessages($langs->trans('MissingCode'), null, 'errors');
			$error++;
		}
		if (empty(trim((string) $tag->label))) {
			setEventMessages($langs->trans('MissingLabel'), null, 'errors');
			$error++;
		}

		if (!$error) {
			$newId = $tag->create($user);
			if ($newId > 0) {
				setEventMessage($langs->trans('TagCreated'));
				// Add the new tag to the current selection
				$fk_tags    = array_filter(array_map('intval', (array) GETPOST('fk_tags', 'array:int')));
				$fk_tags[]  = $newId;
				header('Location: '.orCardRestoreUrl($_SERVER['PHP_SELF'], array('fk_tags' => $fk_tags)));
				exit;
			} else {
				setEventMessages($tag->error, $tag->errors, 'errors');
			}
		}
		$action = 'new_tag';
	}

	// ── Création de l'OR ────────────────────────────────────────────────────
	if ($action == 'add' && $permissiontoadd) {
		$error = 0;

		$object->entity =$conf->entity;
		$object->fk_vehicule   = GETPOSTINT('fk_vehicule');
		$object->km            = GETPOST('km', 'alpha');
		$object->fk_conducteur = GETPOSTINT('fk_conducteur');
		$object->fk_user_creat = $user->id;

		// Statut défini dans le paramètre admin, fallback STATUS_DRAFT
		$statusOnCreate = getDolGlobalInt('WORKSHOP_OR_STATUS_ON_CREATE');
		$object->status = $statusOnCreate > 0 ? $statusOnCreate : Operationorder::STATUS_DRAFT;

		// Totaux à 0
		$object->total_ht          = 0;
		$object->total_ht_part     = 0;
		$object->total_ht_mo       = 0;
		$object->total_ht_service  = 0;
		$object->total_ht_external = 0;
		$object->total_ht_refund   = 0;

		// Dates de planification et clôture à NULL, dates techniques à NULL
		$object->date_planned = null;
		$object->date_valid   = null;
		$object->date_start   = null;
		$object->date_end     = null;

		// Dériver le tiers depuis le véhicule sélectionné
		if ($object->fk_vehicule > 0) {
			$vehicule = new Vehicule($db);
			if ($vehicule->fetch($object->fk_vehicule) > 0) {
				$object->fk_soc = (int) $vehicule->fk_soc;
			}
		}

		if (empty($object->fk_vehicule)) {
			setEventMessages($langs->trans('ErrInvalidFkVehicule'), null, 'errors');
			$error++;
		}
		if (empty($object->fk_soc)) {
			setEventMessages($langs->trans('ErrInvalidSocid'), null, 'errors');
			$error++;
		}

		// Référence via le module de numérotation configuré dans l'admin
		if (!$error) {
			$nextRef = $object->getNextNumRef();
			if (!empty($nextRef)) {
				$object->ref = $nextRef;
			}
		}

		$ret = $extrafields->setOptionalsFromPost(null, $object, '@GETPOSTISSET');
		if ($ret < 0) {
			$error++;
		}

		if (!$error) {
			$id = $object->create($user);
			if ($id > 0) {
				$tagIds = GETPOST('fk_tags', 'array:int');
				if (!empty($tagIds)) {
					$orTag = new OperationorderTag($db);
					foreach ($tagIds as $tagId) {
						$orTag->addTag($id, (int) $tagId, $user);
					}
				}
				header("Location: ".$_SERVER['PHP_SELF'].'?id='.$id);
				exit;
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
				$action = 'create';
			}
		} else {
			$action = 'create';
		}
	}

	// ── Mode vue : sauvegarde des éditions en ligne ──────────────────────────

	// ── Sauvegarde des champs simples (inline edit) ────────────────────
	$inlineSaveActions = array(
		'save_ref_client'    => array('field' => 'ref_client',    'getpost' => array('ref_client', 'alphanohtml')),
		'save_date_or'       => array('field' => 'date_valid',    'type' => 'date', 'dateprefix' => 'date_or'),
		'save_date_planned'  => array('field' => 'date_planned',  'type' => 'date', 'dateprefix' => 'date_planned'),
		'save_date_start'    => array('field' => 'date_start',    'type' => 'date', 'dateprefix' => 'date_start'),
		'save_date_end'      => array('field' => 'date_end',      'type' => 'date', 'dateprefix' => 'date_end'),
		'save_fk_conducteur' => array('field' => 'fk_conducteur', 'getpost' => array('fk_conducteur', 'int'), 'nullifempty' => true),
		'save_km'            => array('field' => 'km',            'type' => 'price', 'getpost' => array('km', 'alpha')),
		'save_check_or'      => array('field' => 'check_or',     'getpost' => array('check_or', 'int'), 'perm' => 'admin'),
	);

	foreach ($inlineSaveActions as $actionName => $cfg) {
		if ($action !== $actionName || $id <= 0) {
			continue;
		}
		// Vérification des droits
		$hasPerm = !empty($cfg['perm']) ? ($cfg['perm'] === 'admin' ? $user->admin : $permissiontoadd) : $permissiontoadd;
		if (!$hasPerm) {
			continue;
		}
		// Lecture de la valeur depuis POST
		if (!empty($cfg['type']) && $cfg['type'] === 'date') {
			$dp = $cfg['dateprefix'];
			$val = dol_mktime(GETPOSTINT($dp.'hour'), GETPOSTINT($dp.'min'), 0, GETPOSTINT($dp.'month'), GETPOSTINT($dp.'day'), GETPOSTINT($dp.'year'));
		} elseif (!empty($cfg['type']) && $cfg['type'] === 'price') {
			$val = price2num(GETPOST($cfg['getpost'][0], $cfg['getpost'][1]));
		} else {
			$val = GETPOST($cfg['getpost'][0], $cfg['getpost'][1]);
		}
		if (!empty($cfg['nullifempty']) && empty($val)) {
			$val = null;
		}
		$object->{$cfg['field']} = $val;
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	// ── Sauvegarde fk_soc (avec sync remise tiers) ──────────────────
	if ($action == 'save_fk_soc' && $id > 0 && $permissiontoadd) {
		$object->fk_soc = GETPOSTINT('fk_soc');
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			$object->syncThirdPartyDiscount($user);
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	// ── Sauvegarde fk_vehicule (re-sync tiers + remise) ─────────────
	if ($action == 'save_fk_vehicule' && $id > 0 && $permissiontoadd) {
		$object->fk_vehicule = GETPOSTINT('fk_vehicule');
		if ($object->fk_vehicule > 0) {
			$vehicule = new Vehicule($db);
			if ($vehicule->fetch($object->fk_vehicule) > 0) {
				$object->fk_soc = (int) $vehicule->fk_soc;
			}
		}
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			$object->syncThirdPartyDiscount($user);
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	// ── Création d'un Job ────────────────────────────────────────────────
	if ($action == 'confirm_add_job' && $confirm == 'yes' && $id > 0 && $permissiontoadd) {
		$error = 0;

		$fk_service_type     = GETPOSTINT('job_fk_service_type');
		$_voRaw              = isset($_GET['job_vehicule_operations']) ? $_GET['job_vehicule_operations'] : (isset($_POST['job_vehicule_operations']) ? $_POST['job_vehicule_operations'] : '');
		$voIds               = is_array($_voRaw) ? array_values(array_filter(array_map('intval', $_voRaw))) : array_values(array_filter(array_map('intval', explode(',', (string) $_voRaw))));
		$description         = GETPOST('job_description', 'restricthtml');
		$qty_mo                = (float) price2num(str_replace(',', '.', GETPOST('job_qty_mo', 'alpha')), 'MU');
		$remise_percent        = (float) price2num(str_replace(',', '.', GETPOST('job_remise_percent', 'alpha')), 'MU');

		if ($fk_service_type <= 0) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('ServiceType')), null, 'errors');
			$error++;
		}
		if ($qty_mo < 0) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('QtyMO')), null, 'errors');
			$error++;
		}

		if (!$error) {
			$serviceType = new ServiceType($db);
			if ($serviceType->fetch($fk_service_type) <= 0) {
				setEventMessages($langs->trans('RecordNotFound'), null, 'errors');
				$error++;
			}
		}

		if (!$error) {
			// Billing third-party : from service type, or fallback to OR's third-party
			$fk_soc_job = !empty($serviceType->fk_soc) ? (int) $serviceType->fk_soc : null;
			// Legacy fallback: if still empty, try the old fk_job_type
			if (empty($fk_soc_job) && !empty($serviceType->fk_job_type)) {
				$jobType = new WorkshopJobType($db);
				if ($jobType->fetch((int) $serviceType->fk_job_type) > 0 && !empty($jobType->fk_soc)) {
					$fk_soc_job = (int) $jobType->fk_soc;
				}
			}

			// Default remise from OR's third-party when not explicitly provided
			if ($remise_percent == 0 && $object->fk_soc > 0) {
				$socDefault = new Societe($db);
				if ($socDefault->fetch($object->fk_soc) > 0 && $socDefault->remise_percent > 0) {
					$remise_percent = (float) $socDefault->remise_percent;
				}
			}

			// Determine next rang
			$sqlRang = 'SELECT MAX(rang) as maxrang FROM '.MAIN_DB_PREFIX.'workshop_operationorder_jobs';
			$sqlRang .= ' WHERE fk_operationorder = '.(int) $id;
			$resRang = $db->query($sqlRang);
			$nextRang = 1;
			if ($resRang && ($objRang = $db->fetch_object($resRang))) {
				$nextRang = max(1, (int) $objRang->maxrang + 1);
			}

			$prixMO        = (float) $serviceType->prix_mo;
			$remiseFactor  = 1 - max(0.0, min(100.0, $remise_percent)) / 100;
			$totalHtMO     = (float) price2num($qty_mo * $prixMO * $remiseFactor, 'MT');

			$job                    = new Operationorder_jobs($db);
			$job->fk_operationorder = (int) $id;
			$job->fk_service_type   = (int) $fk_service_type;
			$job->fk_job_type       = !empty($serviceType->fk_job_type) ? (int) $serviceType->fk_job_type : null;
			$job->label             = $serviceType->label;
			$job->description       = $description;
			$job->qty_mo            = $qty_mo;
			$job->prix_mo           = $prixMO;
			$job->remise_percent    = $remise_percent;
			$job->tva_tx_mo         = isset($serviceType->tva_tx_mo) ? (float) $serviceType->tva_tx_mo : null;
			$job->tva_tx_st         = isset($serviceType->tva_tx_st) ? (float) $serviceType->tva_tx_st : null;
			$job->fk_soc     = $fk_soc_job;
			$job->time_spent = 0;
			$job->rang                  = $nextRang;
			$job->total_ht_mo           = $totalHtMO;
			$job->total_ht_part         = 0;
			$job->total_ht_service      = 0;
			$job->total_ht_external     = 0;
			$job->total_ht_refund       = 0;
			$job->total_ht              = $totalHtMO;

			$newJobId = $job->create($user);
			if ($newJobId > 0) {
				if (!empty($voIds)) {
					$job->linkMaintenanceOperations($voIds);
				}
				$object->updateTotals($user);
				setEventMessage($langs->trans('JobAdded'));
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'#job_'.$newJobId);
				exit;
			} else {
				setEventMessages($job->error, $job->errors, 'errors');
			}
		}
		$action = 'add_job'; // réouvre le dialog en cas d'erreur
	}

	// ── Suppression d'un Job ──────────────────────────────────────────────
	if ($action == 'delete_job' && $id > 0 && $permissiontoadd) {
		$jobid = GETPOSTINT('jobid');
		$job   = new Operationorder_jobs($db);
		if ($job->fetch($jobid) > 0 && (int) $job->fk_operationorder === (int) $id) {
			if ($job->delete($user) < 0) {
				setEventMessages($job->error, $job->errors, 'errors');
			} else {
				$object->updateTotals($user);
				setEventMessage($langs->trans('JobDeleted'));
			}
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	// ── Ajout d'une ligne produit/service dans un Job ────────────────────
	if ($action == 'confirm_add_det' && $id > 0 && $permissiontoadd) {
		$error          = 0;
		$jobid          = GETPOSTINT('jobid');
		$fk_product     = GETPOSTINT('det_fk_product');
		$qty            = (float) price2num(str_replace(',', '.', GETPOST('det_qty', 'alpha')), 'MU');
		$price          = (float) price2num(str_replace(',', '.', GETPOST('det_price', 'alpha')), 'MU');
		$remise_percent = (float) price2num(str_replace(',', '.', GETPOST('det_remise_percent', 'alpha')), 'MU');
		$fk_warehouse   = GETPOST('det_fk_warehouse', 'alphanohtml');
		$description    = GETPOST('det_description', 'restricthtml');

		if ($jobid <= 0 || $fk_product <= 0) {
			setEventMessages($langs->trans('ErrorFieldRequired'), null, 'errors');
			$error++;
		}

		if (!$error) {
			$detJob = new Operationorder_jobs($db);
			if ($detJob->fetch($jobid) <= 0 || (int) $detJob->fk_operationorder !== (int) $id) {
				setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
				$error++;
			}
		}

		if (!$error) {
			require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
			$product = new Product($db);
			$product_type = Operationorderdet::TYPE_PRODUCT;
			$product_label = '';
			if ($product->fetch($fk_product) > 0) {
				$product_type  = (int) $product->type; // 0=product, 1=service
				$product_label = $product->label;
			}

			$sqlRang  = 'SELECT MAX(rang) as maxrang FROM '.MAIN_DB_PREFIX.'workshop_operationorderdet';
			$sqlRang .= ' WHERE fk_operationorder_jobs = '.((int) $jobid);
			$resRang  = $db->query($sqlRang);
			$objRang  = $resRang ? $db->fetch_object($resRang) : null;
			$nextRang = ($objRang && $objRang->maxrang !== null) ? (int) $objRang->maxrang + 1 : 1;

			$det                       = new Operationorderdet($db);
			$det->fk_operationorder_jobs = $jobid;
			$det->fk_product           = $fk_product;
			$det->label                = $product_label;
			$det->description          = $description;
			$det->product_type         = $product_type;
			$det->qty                  = $qty > 0 ? $qty : 1;
			$det->price                = $price;
			$det->remise_percent       = $remise_percent;
			$det->fk_warehouse         = $fk_warehouse;
			$det->rang                 = $nextRang;
			$det->fk_user_creat        = $user->id;

			if ($det->create($user) > 0) {
				$detJob->updateTotals($user);
				$object->updateTotals($user);
				setEventMessage($langs->trans('DetLineAdded'));
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'#job_'.$jobid);
				exit;
			} else {
				setEventMessages($det->error, $det->errors, 'errors');
			}
		}
		$action = 'add_det_details'; // réouvre le dialog détails en cas d'erreur
	}

	// ── Modification d'une ligne produit/service ─────────────────────────
	if ($action == 'confirm_edit_det' && $id > 0 && $permissiontoadd) {
		$detid          = GETPOSTINT('detid');
		$qty            = (float) price2num(str_replace(',', '.', GETPOST('det_qty', 'alpha')), 'MU');
		$price          = (float) price2num(str_replace(',', '.', GETPOST('det_price', 'alpha')), 'MU');
		$remise_percent = (float) price2num(str_replace(',', '.', GETPOST('det_remise_percent', 'alpha')), 'MU');
		$description    = GETPOST('det_description', 'restricthtml');
		$fk_warehouse   = GETPOSTINT('det_fk_warehouse');

		$det = new Operationorderdet($db);
		if ($det->fetch($detid) > 0) {
			$detJob = new Operationorder_jobs($db);
			if ($detJob->fetch((int) $det->fk_operationorder_jobs) > 0 && (int) $detJob->fk_operationorder === (int) $id) {
				$det->qty            = $qty > 0 ? $qty : $det->qty;
				$det->price          = $price;
				$det->remise_percent = $remise_percent;
				$det->description    = $description;
				$det->fk_warehouse   = $fk_warehouse > 0 ? $fk_warehouse : null;
				$det->total_ht       = (float) price2num($qty * $price * (1 - $remise_percent / 100), 'MT');

				if ($det->update($user) >= 0) {
					$detJob->updateTotals($user);
					$object->updateTotals($user);
					setEventMessage($langs->trans('DetLineUpdated'));
					header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'#job_'.(int) $detJob->id);
					exit;
				} else {
					setEventMessages($det->error, $det->errors, 'errors');
				}
			}
		} else {
			setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
		}
		$action = 'edit_det'; // réouvre le dialog en cas d'erreur
	}

	// ── Suppression d'une ligne produit/service ───────────────────────────
	if ($action == 'delete_det' && $id > 0 && $permissiontoadd) {
		$detid = GETPOSTINT('detid');
		$det   = new Operationorderdet($db);
		if ($det->fetch($detid) > 0) {
			$detJobId = (int) $det->fk_operationorder_jobs;
			if ($det->delete($user) >= 0) {
				$detJob2 = new Operationorder_jobs($db);
				if ($detJob2->fetch($detJobId) > 0 && (int) $detJob2->fk_operationorder === (int) $id) {
					$detJob2->updateTotals($user);
					$object->updateTotals($user);
				}
				setEventMessage($langs->trans('DetLineDeleted'));
			} else {
				setEventMessages($det->error, $det->errors, 'errors');
			}
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	// ── Modification d'un Job ────────────────────────────────────────────
	if ($action == 'confirm_edit_job' && $confirm == 'yes' && $id > 0 && $permissiontoadd) {
		$error           = 0;
		$editJobId       = GETPOSTINT('jobid');
		$fk_service_type = GETPOSTINT('job_fk_service_type');
		$_voRaw          = isset($_GET['job_vehicule_operations']) ? $_GET['job_vehicule_operations'] : (isset($_POST['job_vehicule_operations']) ? $_POST['job_vehicule_operations'] : '');
		$voIds           = is_array($_voRaw) ? array_values(array_filter(array_map('intval', $_voRaw))) : array_values(array_filter(array_map('intval', explode(',', (string) $_voRaw))));
		$description     = GETPOST('job_description', 'restricthtml');
		$qty_mo          = (float) price2num(str_replace(',', '.', GETPOST('job_qty_mo', 'alpha')), 'MU');
		$remise_percent  = (float) price2num(str_replace(',', '.', GETPOST('job_remise_percent', 'alpha')), 'MU');

		if ($editJobId <= 0 || $fk_service_type <= 0) {
			setEventMessages($langs->trans('ErrorFieldRequired'), null, 'errors');
			$error++;
		}

		if (!$error) {
			$job = new Operationorder_jobs($db);
			if ($job->fetch($editJobId) <= 0 || (int) $job->fk_operationorder !== (int) $id) {
				setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
				$error++;
			}
		}

		if (!$error) {
			$serviceType = new ServiceType($db);
			if ($serviceType->fetch($fk_service_type) <= 0) {
				setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
				$error++;
			}
		}

		if (!$error) {
			// Billing third-party from service type, with legacy fallback
			$fk_soc_job = !empty($serviceType->fk_soc) ? (int) $serviceType->fk_soc : null;
			if (empty($fk_soc_job) && !empty($serviceType->fk_job_type)) {
				$jobType = new WorkshopJobType($db);
				if ($jobType->fetch((int) $serviceType->fk_job_type) > 0 && !empty($jobType->fk_soc)) {
					$fk_soc_job = (int) $jobType->fk_soc;
				}
			}

			$job->fk_service_type = (int) $fk_service_type;
			$job->fk_job_type     = !empty($serviceType->fk_job_type) ? (int) $serviceType->fk_job_type : null;
			$job->label           = $serviceType->label;
			$job->description     = $description;
			$job->qty_mo          = $qty_mo;
			$job->prix_mo         = (float) $serviceType->prix_mo;
			$job->remise_percent  = $remise_percent;
			$job->tva_tx_mo       = isset($serviceType->tva_tx_mo) ? (float) $serviceType->tva_tx_mo : null;
			$job->tva_tx_st       = isset($serviceType->tva_tx_st) ? (float) $serviceType->tva_tx_st : null;
			$job->fk_soc          = $fk_soc_job;

			$job->computeMO();
			$job->total_ht = $job->total_ht_mo
				+ (float) $job->total_ht_part
				+ (float) $job->total_ht_service
				+ (float) $job->total_ht_external
				+ (float) $job->total_ht_refund;

			if ($job->update($user) < 0) {
				setEventMessages($job->error, $job->errors, 'errors');
				$error++;
			}
		}

		if (!$error) {
			$job->unlinkAllMaintenanceOperations();
			if (!empty($voIds)) {
				$job->linkMaintenanceOperations($voIds);
			}
			$object->updateTotals($user);
			setEventMessage($langs->trans('JobUpdated'));
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'#job_'.$editJobId);
			exit;
		}
		$action = 'edit_job'; // réouvre le dialog en cas d'erreur
	}

	// ── Création d'une commande fournisseur (sous-traitance d'un job) ────────
	if ($action == 'confirm_subcontracting' && $id > 0 && $permissiontoadd) {
		$error        = 0;
		$jobid        = GETPOSTINT('jobid');
		$scFkSoc      = GETPOSTINT('sc_fk_soc');
		$scAmount     = (float) price2num(str_replace(',', '.', GETPOST('sc_amount', 'alpha')), 'MT');
		// formconfirm soumet en GET : le multi-select arrive en comma-separated (ex: "1,2,3")
		$scOpsRaw     = GETPOST('sc_maintenance_ops', 'alpha');
		$scOpsIds     = !empty($scOpsRaw) ? array_values(array_filter(array_map('intval', explode(',', $scOpsRaw)))) : array();
		$scDescription = GETPOST('sc_description', 'restricthtml');

		if ($jobid <= 0) {
			setEventMessages($langs->trans('ErrorFieldRequired').' (jobid)', null, 'errors');
			$error++;
		}
		if ($scFkSoc <= 0) {
			setEventMessages($langs->trans('ErrorFieldRequired').' ('.$langs->trans('Supplier').')', null, 'errors');
			$error++;
		}

		if (!$error) {
			$job = new Operationorder_jobs($db);
			if ($job->fetch($jobid) <= 0 || (int) $job->fk_operationorder !== (int) $id) {
				setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
				$error++;
			}
		}

		if (!$error) {
			// ── Note publique : infos du véhicule ────────────────────────────
			$noteLines = array();
			if (!empty($object->fk_vehicule)) {
				$vehicule = new Vehicule($db);
				if ($vehicule->fetch((int) $object->fk_vehicule) > 0) {
					if (!empty($vehicule->immatriculation)) {
						$noteLines[] = $langs->trans('immatriculation').' : '.dol_escape_htmltag($vehicule->immatriculation);
					}
					if (!empty($vehicule->vin)) {
						$noteLines[] = $langs->trans('VIN').' : '.dol_escape_htmltag($vehicule->vin);
					}
					if (!empty($vehicule->fk_vehicule_mark)) {
						$sqlMark  = 'SELECT label FROM '.MAIN_DB_PREFIX.'workshop_vehicule_c_vehicule_mark';
						$sqlMark .= ' WHERE rowid = '.(int) $vehicule->fk_vehicule_mark;
						$resMark  = $db->query($sqlMark);
						if ($resMark && ($oMark = $db->fetch_object($resMark))) {
							$noteLines[] = $langs->trans('vehiculeMark').' : '.dol_escape_htmltag($oMark->label);
							$db->free($resMark);
						}
					}
					if (!empty($vehicule->modele)) {
						$noteLines[] = $langs->trans('modele').' : '.dol_escape_htmltag($vehicule->modele);
					}
					if (!empty($vehicule->fk_contract_type)) {
						$sqlCt  = 'SELECT label FROM '.MAIN_DB_PREFIX.'workshop_vehicule_c_contract_type';
						$sqlCt .= ' WHERE rowid = '.(int) $vehicule->fk_contract_type;
						$resCt  = $db->query($sqlCt);
						if ($resCt && ($oCt = $db->fetch_object($resCt))) {
							$contractLine = $langs->trans('contractType').' : '.dol_escape_htmltag($oCt->label);
							if (!empty($vehicule->date_end_contract)) {
								$contractLine .= ' ('.$langs->trans('date_end_contract').' : '.dol_print_date($vehicule->date_end_contract, 'day').')';
							}
							$noteLines[] = $contractLine;
							$db->free($resCt);
						}
					}
				}
			}
			$notePublic = implode('<br>', $noteLines);

			// ── Description de la ligne : ops sélectionnées + note ──────────
			$descParts = array();
			if (!empty($scOpsIds)) {
				$opLabels = array();
				foreach ($scOpsIds as $voId) {
					$sqlOp  = 'SELECT cmo.code, cmo.label AS op_label';
					$sqlOp .= ' FROM '.MAIN_DB_PREFIX.'workshop_vehicule_operation vo';
					$sqlOp .= ' LEFT JOIN '.MAIN_DB_PREFIX.'workshop_vehicule_c_maintenance_operation cmo';
					$sqlOp .= '   ON cmo.rowid = vo.fk_maintenance_operation';
					$sqlOp .= ' WHERE vo.rowid = '.(int) $voId;
					$resOp = $db->query($sqlOp);
					if ($resOp && ($oOp = $db->fetch_object($resOp))) {
						$opLabels[] = dol_escape_htmltag($oOp->code.' — '.$oOp->op_label);
						$db->free($resOp);
					}
				}
				if (!empty($opLabels)) {
					$descParts[] = implode('<br>', $opLabels);
				}
			}
			if (!empty($scDescription)) {
				$descParts[] = $scDescription;
			}
			$lineDesc = implode('<br>', $descParts);

			// ── Création de la commande fournisseur ──────────────────────────
			$supplierOrder               = new CommandeFournisseur($db);
			$supplierOrder->socid        = $scFkSoc;
			$supplierOrder->ref_supplier = '';
			$supplierOrder->note_public  = $notePublic;

			$newOrderId = $supplierOrder->create($user);
			if ($newOrderId <= 0) {
				$error++;
				setEventMessages($supplierOrder->error, $supplierOrder->errors, 'errors');
			}

			if (!$error) {
				// Ligne de service libre
				// Signature Dolibarr 21 : addline($desc, $pu_ht, $qty, $txtva,
				//   $txlocaltax1, $txlocaltax2, $fk_product, $fk_prod_fourn_price,
				//   $ref_supplier, $remise_percent, $price_base_type, $pu_ttc, $type, ...)
				$resLine = $supplierOrder->addline(
					$lineDesc,  // desc
					$scAmount,  // pu_ht
					1,          // qty
					0,          // txtva
					0,          // txlocaltax1
					0,          // txlocaltax2
					0,          // fk_product (ligne libre)
					0,          // fk_prod_fourn_price
					'',         // ref_supplier
					0,          // remise_percent
					'HT',       // price_base_type
					0,          // pu_ttc
					1           // type = service
				);
				if ($resLine <= 0) {
					$error++;
					setEventMessages($supplierOrder->error, $supplierOrder->errors, 'errors');
				}
			}

			if (!$error) {
				// Lien job ↔ commande fournisseur (raw SQL : job n'est pas un objet natif Dolibarr)
				$sqlLink  = "INSERT INTO ".MAIN_DB_PREFIX."element_element";
				$sqlLink .= " (fk_source, sourcetype, fk_target, targettype)";
				$sqlLink .= " VALUES (".(int) $job->id.", '".$db->escape($job->element)."'";
				$sqlLink .= ", ".(int) $supplierOrder->id.", 'order_supplier')";
				if (!$db->query($sqlLink)) {
					dol_syslog(__METHOD__.' element_element link job error: '.$db->lasterror(), LOG_WARNING);
				}

				// Lien OR ↔ commande fournisseur (via add_object_linked standard Dolibarr)
				// getElementType() retourne 'workshop_operationorder' (format module_element)
				// utilisé par fetchObjectLinked + showLinkedObjectBlock pour la résolution
				$resLink = $supplierOrder->add_object_linked($object->getElementType(), $object->id);
				if ($resLink < 0) {
					dol_syslog(__METHOD__.' add_object_linked OR error: '.$supplierOrder->error, LOG_WARNING);
				}

				// Validation de la commande
				$resValid = $supplierOrder->valid($user);
				if ($resValid < 0) {
					$error++;
					setEventMessages($supplierOrder->error, $supplierOrder->errors, 'errors');
				}
			}

			if (!$error) {
				setEventMessage($langs->trans('SubcontractingOrderCreated').' : '.$supplierOrder->getNomUrl(1));
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
				exit;
			}
		}
	}

	// ── Transition de statut ─────────────────────────────────────────────
	if ($action == 'setStatus' && $id > 0 && $permissiontoadd) {
		$fk_status = GETPOSTINT('fk_status');
		$res = $object->setStatus($user, $fk_status);
		if ($res < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			setEventMessage($langs->trans('StatusUpdated'));
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
}

// ── Génération / suppression de document PDF ─────────────────────────────
if ($id > 0 && $object->id > 0) {
	$upload_dir  = (isset($conf->workshop->multidir_output[$object->entity]) ? $conf->workshop->multidir_output[$object->entity] : $conf->workshop->dir_output).'/operationorder/'.(int) $object->id;
	$permissiontocreate = $permissiontoadd;
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';
}


/*
 * View
 */

// ═══════════════════════════════════════════════════════════════════════════
// MODE VUE : affichage de la fiche OR existante
// ═══════════════════════════════════════════════════════════════════════════
if ($id > 0) {

	llxHeader('', $object->ref.' — '.$langs->trans('OperationOrder'), '', '', 0, 0, array(), array(dol_buildpath('/workshop/css/or_card.css', 1)), '', 'mod-workshop page-orcard');


	$head = operationorderPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('OperationOrder'), -1, 'fa-tools', 0, '', '', 0, '', 1);

	$linkback = '<a href="'.dol_buildpath('/workshop/operationorder/or_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';

	// ── Construction du morehtmlref (lignes sous le numéro OR dans le bandeau)
	$morehtmlref = $object->getBanner($action, $permissiontoadd);

	// ── Bandeau ──────────────────────────────────────────────────────────────
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', $object->getLibStatut(4));

	// ── Chargement du véhicule pour la colonne droite ─────────────────────
	// ismultientitymanaged désactivé temporairement : le véhicule peut être
	// dans une entité partagée différente de l'OR (cross-entity sharing).
	$vehiculeObj = null;
	if ($object->fk_vehicule > 0) {
		$vehiculeObj = new Vehicule($db);
		$vehiculeObj->ismultientitymanaged = 0;
		$ret = $vehiculeObj->fetch($object->fk_vehicule);
		$vehiculeObj->ismultientitymanaged = 1;
		if ($ret <= 0) {
			$vehiculeObj = null;
		}
	}

	// ── Labels check_or ───────────────────────────────────────────────────
	$checkOrLabels = array(
		0 => $langs->trans('CheckORNonVerifie'),
		1 => $langs->trans('CheckOREnCours'),
		2 => $langs->trans('CheckORVerifieOK'),
		3 => $langs->trans('CheckORVerifieReserves'),
	);
	$checkOrBadgeTypes = array(
		0 => 'secondary',
		1 => 'warning',
		2 => 'success',
		3 => 'info',
	);

	// ── Layout deux colonnes ───────────────────────────────────────────────
	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';

	// ── Colonne gauche : informations de l'OR ─────────────────────────────
	print '<table class="border centpercent tableforfield">'."\n";

	// ── Date création (calculée !) ────────────────────────────────────────
	print '<tr><td class="titlefield">'.$langs->trans('DateCreation').'</td>';
	print '<td>';
	print dol_print_date($object->date_creation, 'dayhour');
	print '</td></tr>'."\n";

	// ── Date OR (date_valid) — éditable * ─────────────────────────────────
	print '<tr><td>';
	print $form->editfieldkey($langs->trans('DateOR'), 'date_or', $object->date_valid, $object, $permissiontoadd);
	print '</td><td>';
	print orCardInlineEditTd(
		$action, 'date_or', 'save_date_or', $object->id,
		$form->selectDate($object->date_valid ?: -1, 'date_or', 1, 1, 0, '', 1, 1),
		$form->editfieldval($langs->trans('DateOR'), 'date_or', $object->date_valid, $object, $permissiontoadd, 'dayhour'),
		$permissiontoadd
	);
	print '</td></tr>'."\n";

	// ── Date début — éditable (jour) ──────────────────────────────────────
	print '<tr><td>';
	print $form->editfieldkey($langs->trans('DateStart'), 'date_start', $object->date_start, $object, $permissiontoadd);
	print '</td><td>';
	print orCardInlineEditTd(
		$action, 'date_start', 'save_date_start', $object->id,
		$form->selectDate($object->date_start ?: -1, 'date_start', 0, 0, 1, '', 1, 1),
		$form->editfieldval($langs->trans('DateStart'), 'date_start', $object->date_start, $object, $permissiontoadd, 'day'),
		$permissiontoadd
	);
	print '</td></tr>'."\n";

	// ── Date fin — éditable (jour) ────────────────────────────────────────
	print '<tr><td>';
	print $form->editfieldkey($langs->trans('DateEnd'), 'date_end', $object->date_end, $object, $permissiontoadd);
	print '</td><td>';
	print orCardInlineEditTd(
		$action, 'date_end', 'save_date_end', $object->id,
		$form->selectDate($object->date_end ?: -1, 'date_end', 0, 0, 1, '', 1, 1),
		$form->editfieldval($langs->trans('DateEnd'), 'date_end', $object->date_end, $object, $permissiontoadd, 'day'),
		$permissiontoadd
	);
	print '</td></tr>'."\n";

	// ── Mécanicien (calculé !) ────────────────────────────────────────────
	print '<tr><td class="titlefield">'.$langs->trans('Mechanic').'</td>';
	print '<td>';
	if ($object->fk_user_assign > 0) {
		$mechUser = new User($db);
		if ($mechUser->fetch($object->fk_user_assign) > 0) {
			print $mechUser->getNomUrl(1);
		} else {
			print $object->fk_user_assign;
		}
	} else {
		print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
	}
	print '</td></tr>'."\n";

	// ── Totaux HT (calculés !) ────────────────────────────────────────────
	print '<tr><td class="titlefield">'.$langs->trans('TotalHTMO').'</td>';
	print '<td>'.price($object->total_ht_mo).'</td></tr>'."\n";

	print '<tr><td class="titlefield">'.$langs->trans('TotalHTPart').'</td>';
	print '<td>'.price($object->total_ht_part).'</td></tr>'."\n";

	print '<tr><td class="titlefield">'.$langs->trans('TotalHTService').'</td>';
	print '<td>'.price($object->total_ht_service).'</td></tr>'."\n";

	print '<tr><td class="titlefield">'.$langs->trans('TotalHTExternal').'</td>';
	print '<td>'.price($object->total_ht_external).'</td></tr>'."\n";

	print '<tr><td class="titlefield">'.$langs->trans('TotalHTRefund').'</td>';
	print '<td>'.price($object->total_ht_refund).'</td></tr>'."\n";

	print '<tr><td class="titlefield"><strong>'.$langs->trans('TotalHT').'</strong></td>';
	print '<td><strong>'.price($object->total_ht).'</strong></td></tr>'."\n";

	print '</table>'."\n";
	print '</div>'; // fichehalfleft

	// ── Colonne droite : informations du véhicule ─────────────────────────
	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">'."\n";

	// ── Statut du Check OR (++ : éditable par admin uniquement) ───────────
	print '<tr><td class="titlefield">'.$langs->trans('CheckOR');
	if ($user->admin) {
		print ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=editcheck_or">'.img_edit().'</a>';
	}
	print '</td><td>';
	$checkOrVal = (int) $object->check_or;
	$checkOrLabel = isset($checkOrLabels[$checkOrVal]) ? $checkOrLabels[$checkOrVal] : $checkOrLabels[0];
	$checkOrBadge = isset($checkOrBadgeTypes[$checkOrVal]) ? $checkOrBadgeTypes[$checkOrVal] : 'secondary';
	print orCardInlineEditTd(
		$action, 'check_or', 'save_check_or', $object->id,
		$form->selectarray('check_or', $checkOrLabels, (int) $object->check_or, 0),
		dolGetBadge($checkOrLabel, '', $checkOrBadge),
		$user->admin
	);
	print '</td></tr>'."\n";

	if ($vehiculeObj) {

		// ── Opérations disponibles (calculé !) ───────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('OperationsDisponibles').'</td>';
		print '<td>';
		$vehiculeObj->getOperations();
		if (!empty($vehiculeObj->operations)) {
			$opLabels = array();
			dol_include_once('/workshop/class/vehiculemaintenanceoperation.class.php');
			foreach ($vehiculeObj->operations as $ope) {
				if (!empty($ope->fk_maintenance_operation)) {
					$maintOpe = new VehiculeMaintenanceOperation($db);
					if ($maintOpe->fetch($ope->fk_maintenance_operation) > 0) {
						$opLabels[] = dol_escape_htmltag($maintOpe->label);
					}
				}
			}
			if ($opLabels) {
				print implode(', ', $opLabels);
			} else {
				print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
			}
		} else {
			print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
		}
		print '</td></tr>'."\n";

		// ── Date première mise en circulation (calculé !) ─────────────────
		print '<tr><td class="titlefield">'.$langs->trans('immatriculation_date').'</td>';
		print '<td>';
		print dol_print_date($vehiculeObj->date_immat, 'day');
		print '</td></tr>'."\n";

		// ── Type de véhicule (calculé !) ──────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('vehiculeType').'</td>';
		print '<td>';
		print $vehiculeObj->showOutputField($vehiculeObj->fields['fk_vehicule_type'], 'fk_vehicule_type', $vehiculeObj->fk_vehicule_type, '');
		print '</td></tr>'."\n";

		// ── Marque du véhicule (calculé !) ────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('vehiculeMark').'</td>';
		print '<td>';
		print $vehiculeObj->showOutputField($vehiculeObj->fields['fk_vehicule_mark'], 'fk_vehicule_mark', $vehiculeObj->fk_vehicule_mark, '');
		print '</td></tr>'."\n";

		// ── Modèle (calculé !) ────────────────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('modele').'</td>';
		print '<td>';
		print dol_escape_htmltag((string) $vehiculeObj->modele);
		print '</td></tr>'."\n";

		// ── Type de contrat (calculé !) ───────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('contractType').'</td>';
		print '<td>';
		print $vehiculeObj->showOutputField($vehiculeObj->fields['fk_contract_type'], 'fk_contract_type', $vehiculeObj->fk_contract_type, '');
		print '</td></tr>'."\n";

		// ── Véhicule lié (calculé !) ──────────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('VehiculeLie').'</td>';
		print '<td>';
		$vehiculeObj->getLinkedVehicules(); // stocke dans $vehiculeObj->linkedVehicules (void)
		if (is_array($vehiculeObj->linkedVehicules) && !empty($vehiculeObj->linkedVehicules)) {
			$lv = reset($vehiculeObj->linkedVehicules);
			$linkedVeh = new Vehicule($db);
			$linkedVeh->ismultientitymanaged = 0;
			$retLinked = $linkedVeh->fetch($lv->fk_other_vehicule);
			$linkedVeh->ismultientitymanaged = 1;
			if ($retLinked > 0) {
				print $linkedVeh->getNomUrl(1);
			}
		} else {
			print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
		}
		print '</td></tr>'."\n";

	} else {
		print '<tr><td colspan="2"><span class="opacitymedium">'.$langs->trans('NoVehiculeLinked').'</span></td></tr>'."\n";
	}

	// ── Conducteur (depuis l'OR) — éditable * ────────────────────────────
	print '<tr><td class="titlefield">';
	print $form->editfieldkey($langs->trans('Conducteur'), 'fk_conducteur', $object->fk_conducteur, $object, $permissiontoadd);
	print '</td><td>';
	// Build display HTML for conducteur
	$conducteurDisplay = '';
	if ($object->fk_conducteur > 0) {
		$conducteurObj = new Conducteur($db);
		if ($conducteurObj->fetch($object->fk_conducteur) > 0) {
			$conducteurDisplay = $conducteurObj->getNomUrl(1);
		} else {
			$conducteurDisplay = (string) $object->fk_conducteur;
		}
	} else {
		$conducteurDisplay = '<span class="opacitymedium">'.$langs->trans('None').'</span>';
	}
	print orCardInlineEditTd(
		$action, 'fk_conducteur', 'save_fk_conducteur', $object->id,
		$object->showInputField($object->fields['fk_conducteur'], 'fk_conducteur', $object->fk_conducteur, '', '', '', 'maxwidth300'),
		$conducteurDisplay,
		$permissiontoadd
	);
	print '</td></tr>'."\n";

	// ── Kilométrage à la création de l'OR — éditable * ───────────────────
	print '<tr><td class="titlefield">';
	print $form->editfieldkey($langs->trans('KmCreationOR'), 'km', $object->km, $object, $permissiontoadd);
	print '</td><td>';
	$kmDisplay = (!empty($object->km) ? dol_escape_htmltag(number_format((float) $object->km, 0, ',', ' ')).' km' : '<span class="opacitymedium">'.$langs->trans('NA').'</span>');
	print orCardInlineEditTd(
		$action, 'km', 'save_km', $object->id,
		'<input type="text" name="km" class="maxwidth100" value="'.dol_escape_htmltag((string) $object->km).'">',
		$kmDisplay,
		$permissiontoadd
	);
	print '</td></tr>'."\n";

	// ── Date de clôture (calculé !) ───────────────────────────────────────
	print '<tr><td class="titlefield">'.$langs->trans('DateCloture').'</td>';
	print '<td>';
	if ($object->date_end) {
		print dol_print_date($object->date_end, 'dayhour');
	} else {
		print '<span class="opacitymedium">'.$langs->trans('NA').'</span>';
	}
	print '</td></tr>'."\n";

	print '</table>'."\n";
	print '</div>'; // fichehalfright
	print '</div>'; // fichecenter

	print dol_get_fiche_end();

	// ═══════════════════════════════════════════════════════════════════════
	// BOUTONS D'ACTION
	// ═══════════════════════════════════════════════════════════════════════

	// ── Chargement des constantes admin ───────────────────────────────────
	$statusBeforeCheck   = getDolGlobalInt('WORKSHOP_OR_STATUT_BEFORE_CHECK');
	$statusAfterCheck    = getDolGlobalInt('WORKSHOP_OR_STATUT_AFTER_CHECK');
	$statusForInvoice    = getDolGlobalInt('WORKSHOP_OR_STATUT_FOR_INVOICE');
	$statusAfterInvoice  = getDolGlobalInt('WORKSHOP_OR_STATUT_AFTER_INVOICE');
	$orderableStatuses   = array_filter(array_map('intval', explode(',', getDolGlobalString('WORKSHOP_OR_ORDERABLE_STATUS'))));

	$currentStatus       = $object->loadStatus();
	$canEditAtStatus     = $currentStatus ? $currentStatus->userCan($user, 'edit') : (bool) $permissiontoadd;
	$isCurrentOrderable  = in_array((int) $object->status, $orderableStatuses);
	$isCurrentForInvoice = ($statusForInvoice > 0 && (int) $object->status === $statusForInvoice);
	$orHasCharges        = ((float) $object->total_ht != 0);

	// ── Rangée 1 : transitions de statut ──────────────────────────────────
	print '<div class="tabsAction">'."\n";

	if ($currentStatus && is_array($currentStatus->TStatusAllowed) && !empty($currentStatus->TStatusAllowed)) {
		foreach ($currentStatus->TStatusAllowed as $targetStatusId) {
			$targetStatusId = (int) $targetStatusId;

			// Règle Check OR : remplacer le statut AFTER_CHECK si check_or insuffisant
			$isAfterCheckTarget = ($statusBeforeCheck > 0
				&& (int) $object->status === $statusBeforeCheck
				&& $statusAfterCheck > 0
				&& $targetStatusId === $statusAfterCheck);

			if ($isAfterCheckTarget && (int) $object->check_or <= 1) {
				// Afficher bouton "Check OR" bloquant
				print '<a class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans('CheckORRequiredTooltip')).'">';
				print img_picto('', 'fa-clipboard-check', 'class="paddingright"').$langs->trans('CheckOR');
				print '</a>'."\n";
				continue;
			}

			// Règle facturation : masquer le statut AFTER_INVOICE si total_ht > 0
			$isAfterInvoiceTarget = ($isCurrentForInvoice && $statusAfterInvoice > 0 && $targetStatusId === $statusAfterInvoice);
			if ($isAfterInvoiceTarget && $orHasCharges) {
				continue; // bouton masqué — passage via "Facturer l'OR"
			}

			$targetStatus = new WorkshopOperationOrderStatus($db);
			if ($targetStatus->fetch($targetStatusId) <= 0) {
				continue;
			}

			$canChange     = $targetStatus->userCan($user, 'changeToThisStatus') && $permissiontoadd;
			$needsPlanning = !empty($targetStatus->require_planned_date) && empty($object->date_planned);
			$isActive      = $canChange && !$needsPlanning;

			if ($isActive) {
				$btnStyle = !empty($targetStatus->color) ? ' style="background-color:'.dol_escape_htmltag($targetStatus->color).';border-color:'.dol_escape_htmltag($targetStatus->color).';"' : '';
				print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline-block;margin:2px;">'."\n";
				print '<input type="hidden" name="token" value="'.newToken().'">'."\n";
				print '<input type="hidden" name="action" value="setStatus">'."\n";
				print '<input type="hidden" name="id" value="'.$object->id.'">'."\n";
				print '<input type="hidden" name="fk_status" value="'.$targetStatusId.'">'."\n";
				print '<input type="submit" class="butAction"'.$btnStyle.' value="'.dol_escape_htmltag($targetStatus->label).'">'."\n";
				print '</form>'."\n";
			} else {
				$tooltip = !$canChange ? $langs->trans('NotEnoughPermissions') : $langs->trans('PlanningDateRequired');
				print '<a class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($tooltip).'">';
				print dol_escape_htmltag($targetStatus->label);
				print '</a>'."\n";
			}
		}
	}

	// ── Bouton "Facturer l'OR" (uniquement si statut = WORKSHOP_OR_STATUT_FOR_INVOICE et total_ht > 0) ──
	if ($isCurrentForInvoice && $orHasCharges) {
		$canInvoice = $canEditAtStatus && isModEnabled('facture') && $user->hasRight('facture', 'creer');
		$invoiceUrl = dol_buildpath('/workshop/operationorder/or_invoice.php', 1).'?id='.$object->id;
		if ($canInvoice) {
			print '<a class="butAction" href="'.dol_escape_htmltag($invoiceUrl).'">';
			print img_picto('', 'bill', 'class="paddingright"').$langs->trans('FacturerOR');
			print '</a>'."\n";
		} else {
			$tooltip = !$canEditAtStatus ? $langs->trans('NotEnoughPermissions') : $langs->trans('NoRightToCreateInvoice');
			print '<a class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($tooltip).'">';
			print img_picto('', 'bill', 'class="paddingright"').$langs->trans('FacturerOR');
			print '</a>'."\n";
		}
	}

	// Ajouter un JOB
	$addJobUrl = $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=add_job&token='.newToken();
	if ($canEditAtStatus) {
		print '<a class="butAction" href="'.dol_escape_htmltag($addJobUrl).'">';
		print img_picto('', 'fa-plus-circle', 'class="paddingright"').$langs->trans('AddJob');
		print '</a>'."\n";
	} else {
		print '<a class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans('NotEnoughPermissions')).'">';
		print img_picto('', 'fa-plus-circle', 'class="paddingright"').$langs->trans('AddJob');
		print '</a>'."\n";
	}

	// Commande Pièces + Débit Pièce (uniquement si statut dans WORKSHOP_OR_ORDERABLE_STATUS)
	if ($isCurrentOrderable) {
		$commandeUrl = dol_buildpath('/workshop/operationorder/or_commande_pieces.php', 1).'?id='.$object->id;
		if ($canEditAtStatus) {
			print '<a class="butAction" href="'.dol_escape_htmltag($commandeUrl).'">';
			print img_picto('', 'fa-shopping-cart', 'class="paddingright"').$langs->trans('CommandePieces');
			print '</a>'."\n";
		} else {
			print '<a class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans('NotEnoughPermissions')).'">';
			print img_picto('', 'fa-shopping-cart', 'class="paddingright"').$langs->trans('CommandePieces');
			print '</a>'."\n";
		}

		$debitUrl = dol_buildpath('/workshop/operationorder/or_pieces.php', 1).'?id='.$object->id;
		if ($canEditAtStatus) {
			print '<a class="butAction" href="'.dol_escape_htmltag($debitUrl).'">';
			print img_picto('', 'fa-boxes', 'class="paddingright"').$langs->trans('DebitPiece');
			print '</a>'."\n";
		} else {
			print '<a class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans('NotEnoughPermissions')).'">';
			print img_picto('', 'fa-boxes', 'class="paddingright"').$langs->trans('DebitPiece');
			print '</a>'."\n";
		}
	}

	// Découper l'OR
	$splitUrl = dol_buildpath('/workshop/operationorder/or_split.php', 1).'?id='.$object->id;
	if ($canEditAtStatus) {
		print '<a class="butAction" href="'.dol_escape_htmltag($splitUrl).'">';
		print img_picto('', 'fa-cut', 'class="paddingright"').$langs->trans('SplitOR');
		print '</a>'."\n";
	} else {
		print '<a class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans('NotEnoughPermissions')).'">';
		print img_picto('', 'fa-cut', 'class="paddingright"').$langs->trans('SplitOR');
		print '</a>'."\n";
	}

	// Supprimer — droit global de suppression ET droit d'écriture sur le statut courant
	$permissiontodelete = $user->hasRight('workshop', 'operationorders', 'delete') && $object->canWrite($user);
	if ($permissiontodelete) {
		$deleteUrl = $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken();
		print '<a class="butActionDelete" href="'.dol_escape_htmltag($deleteUrl).'">';
		print img_picto('', 'delete', 'class="paddingright"').$langs->trans('Delete');
		print '</a>'."\n";
	} else {
		print '<a class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans('NotEnoughPermissions')).'">';
		print img_picto('', 'delete', 'class="paddingright"').$langs->trans('Delete');
		print '</a>'."\n";
	}

	print '</div>'."\n";


	// ── Dialog : Ajouter un Job ───────────────────────────────────────────
	if ($action === 'add_job' || $action === 'confirm_add_job') {
		// Remise par défaut depuis le tiers de l'OR
		$defaultRemise = 0;
		if ($object->fk_soc > 0) {
			$socDefObj = new Societe($db);
			if ($socDefObj->fetch($object->fk_soc) > 0 && $socDefObj->remise_percent > 0) {
				$defaultRemise = (float) $socDefObj->remise_percent;
			}
		}

		$sqlST  = 'SELECT rowid, label, prix_mo, tva_tx_mo, tva_tx_st FROM '.MAIN_DB_PREFIX.'workshop_c_servicetype';
		$sqlST .= ' WHERE active = 1 ORDER BY label ASC';
		$resST  = $db->query($sqlST);

		$htmlSTSelect  = '<select name="job_fk_service_type" id="job_fk_service_type" class="flat maxwidth300" required>';
		$htmlSTSelect .= '<option value="0">— '.$langs->trans('SelectServiceType').' —</option>';
		if ($resST) {
			while ($objST = $db->fetch_object($resST)) {
				$selected = (GETPOSTINT('job_fk_service_type') == $objST->rowid) ? ' selected' : '';
				$stLabel  = $objST->label.($objST->prix_mo > 0 ? ' ('.price2num($objST->prix_mo, 2).' €/h)' : '');
				$tvaMoInfo = $objST->tva_tx_mo !== null ? ' TVA MO:'.price2num($objST->tva_tx_mo, 2).'%' : '';
				$tvaStInfo = $objST->tva_tx_st !== null ? ' TVA ST:'.price2num($objST->tva_tx_st, 2).'%' : '';
				$htmlSTSelect .= '<option value="'.(int) $objST->rowid.'"'.$selected
					.' data-tva-mo="'.dol_escape_htmltag((string) $objST->tva_tx_mo).'"'
					.' data-tva-st="'.dol_escape_htmltag((string) $objST->tva_tx_st).'"'
					.'>'.dol_escape_htmltag($stLabel.$tvaMoInfo.$tvaStInfo).'</option>';
			}
			$db->free($resST);
		}
		$htmlSTSelect .= '</select>';

		$remiseVal = GETPOST('job_remise_percent', 'alpha') !== '' ? GETPOST('job_remise_percent', 'alpha') : price2num($defaultRemise, 2);

		// ── Sélecteur multiple (optionnel) : opérations de maintenance du véhicule ──
		$htmlVOSelect = '';
		if ($object->fk_vehicule > 0) {
			$_voRaw        = isset($_GET['job_vehicule_operations']) ? $_GET['job_vehicule_operations'] : (isset($_POST['job_vehicule_operations']) ? $_POST['job_vehicule_operations'] : '');
			$selectedVoIds = is_array($_voRaw) ? array_values(array_filter(array_map('intval', $_voRaw))) : array_values(array_filter(array_map('intval', explode(',', (string) $_voRaw))));
			$sqlVO  = 'SELECT vo.rowid, cmo.code, cmo.label AS op_label, vo.date_next';
			$sqlVO .= ' FROM '.MAIN_DB_PREFIX.'workshop_vehicule_operation vo';
			$sqlVO .= ' LEFT JOIN '.MAIN_DB_PREFIX.'workshop_vehicule_c_maintenance_operation cmo';
			$sqlVO .= '   ON cmo.rowid = vo.fk_maintenance_operation';
			$sqlVO .= ' WHERE vo.fk_vehicule = '.((int) $object->fk_vehicule);
			$sqlVO .= ' AND vo.status != '.WorkshopVehiculeOperation::STATUS_DONE;
			$sqlVO .= ' ORDER BY cmo.code ASC';
			$resVO = $db->query($sqlVO);
			if ($resVO && $db->num_rows($resVO) > 0) {
				$voOptions = array();
				while ($objVO = $db->fetch_object($resVO)) {
					$optLabel = $objVO->code.' — '.$objVO->op_label;
					if (!empty($objVO->date_next)) {
						$optLabel .= ' ('.dol_print_date($db->jdate($objVO->date_next), 'day').')';
					}
					$voOptions[(string)(int) $objVO->rowid] = dol_escape_htmltag($optLabel);
				}
				$htmlVOSelect = $form->multiselectarray('job_vehicule_operations', $voOptions, array_map('strval', $selectedVoIds), 0, 0, 'flat minwidth260', 0, 0);
				$db->free($resVO);
			}
		}

		$fq = array(
			array(
				'type'  => 'other',
				'label' => $langs->trans('ServiceType').' <span class="fieldrequired">*</span>',
				'name'  => 'job_fk_service_type',
				'value' => $htmlSTSelect,
			),
		);
		if (!empty($htmlVOSelect)) {
			$fq[] = array(
				'type'  => 'other',
				'label' => $langs->trans('MaintenanceOperations'),
				'name'  => 'job_vehicule_operations',
				'value' => $htmlVOSelect,
			);
		}
		$fq = array_merge($fq, array(
			array(
				'type'  => 'textarea',
				'label' => $langs->trans('Description'),
				'name'  => 'job_description',
				'value' => GETPOST('job_description', 'restricthtml'),
				'moreattr' => 'style=width:99%'
			),
			array(
				'type'  => 'text',
				'label' => $langs->trans('QtyMO').' <span class="fieldrequired">*</span> ('.$langs->trans('Hours').')',
				'name'  => 'job_qty_mo',
				'value' => GETPOST('job_qty_mo', 'alpha') ?: '1.00',
				'size'  => 10,
			),
			array(
				'type'  => 'text',
				'label' => $langs->trans('RemiseMO').' (%)',
				'name'  => 'job_remise_percent',
				'value' => $remiseVal,
				'size'  => 8,
			),
		));
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.(int) $object->id,
			$langs->trans('AddJob'),
			'',
			'confirm_add_job',
			$fq,
			'yes',
			1,
			550,
			750,
			0,
			$langs->trans('Add'),
			$langs->trans('Cancel')
		);
	}

	// ── Dialog : Modifier un Job ──────────────────────────────────────────────
	if (($action === 'edit_job' || $action === 'confirm_edit_job') && $id > 0 && $canEditAtStatus) {
		$editJobId = GETPOSTINT('jobid');
		$editJob   = new Operationorder_jobs($db);

		if ($editJobId > 0 && $editJob->fetch($editJobId) > 0 && (int) $editJob->fk_operationorder === (int) $id) {
			// ServiceType select pre-selecting current value
			$sqlST  = 'SELECT rowid, label, prix_mo, tva_tx_mo, tva_tx_st FROM '.MAIN_DB_PREFIX.'workshop_c_servicetype';
			$sqlST .= ' WHERE active = 1 ORDER BY label ASC';
			$resST  = $db->query($sqlST);

			$curSTId = ($action === 'confirm_edit_job') ? GETPOSTINT('job_fk_service_type') : (int) $editJob->fk_service_type;
			$htmlEditSTSelect  = '<select name="job_fk_service_type" id="job_fk_service_type" class="flat maxwidth300" required>';
			$htmlEditSTSelect .= '<option value="0">— '.$langs->trans('SelectServiceType').' —</option>';
			if ($resST) {
				while ($objST = $db->fetch_object($resST)) {
					$selected = ($curSTId == $objST->rowid) ? ' selected' : '';
					$stLabel  = $objST->label.($objST->prix_mo > 0 ? ' ('.price2num($objST->prix_mo, 2).' €/h)' : '');
					$tvaMoInfo = $objST->tva_tx_mo !== null ? ' TVA MO:'.price2num($objST->tva_tx_mo, 2).'%' : '';
					$tvaStInfo = $objST->tva_tx_st !== null ? ' TVA ST:'.price2num($objST->tva_tx_st, 2).'%' : '';
					$htmlEditSTSelect .= '<option value="'.(int) $objST->rowid.'"'.$selected
						.' data-tva-mo="'.dol_escape_htmltag((string) $objST->tva_tx_mo).'"'
						.' data-tva-st="'.dol_escape_htmltag((string) $objST->tva_tx_st).'"'
						.'>'.dol_escape_htmltag($stLabel.$tvaMoInfo.$tvaStInfo).'</option>';
				}
				$db->free($resST);
			}
			$htmlEditSTSelect .= '</select>';

			// Pre-filled values (from POST on re-open after error, else from DB)
			$curQtyMO  = ($action === 'confirm_edit_job') ? GETPOST('job_qty_mo', 'alpha') : price2num($editJob->qty_mo, 2);
			$curRemise = ($action === 'confirm_edit_job') ? GETPOST('job_remise_percent', 'alpha') : price2num($editJob->remise_percent, 2);
			$curDesc   = ($action === 'confirm_edit_job') ? GETPOST('job_description', 'restricthtml') : $editJob->description;

			// Maintenance operations select (same as add_job)
			$htmlEditVOSelect = '';
			if ($object->fk_vehicule > 0) {
				if ($action === 'confirm_edit_job') {
					$_voRaw   = isset($_GET['job_vehicule_operations']) ? $_GET['job_vehicule_operations'] : (isset($_POST['job_vehicule_operations']) ? $_POST['job_vehicule_operations'] : '');
					$curVoIds = is_array($_voRaw) ? array_values(array_filter(array_map('intval', $_voRaw))) : array_values(array_filter(array_map('intval', explode(',', (string) $_voRaw))));
				} else {
					$curVoIds = $editJob->fetchLinkedMaintenanceOperationIds();
					if (!is_array($curVoIds)) {
						$curVoIds = array();
					}
				}
				$sqlVO  = 'SELECT vo.rowid, cmo.code, cmo.label AS op_label, vo.date_next';
				$sqlVO .= ' FROM '.MAIN_DB_PREFIX.'workshop_vehicule_operation vo';
				$sqlVO .= ' LEFT JOIN '.MAIN_DB_PREFIX.'workshop_vehicule_c_maintenance_operation cmo';
				$sqlVO .= '   ON cmo.rowid = vo.fk_maintenance_operation';
				$sqlVO .= ' WHERE vo.fk_vehicule = '.((int) $object->fk_vehicule);
				$sqlVO .= ' AND vo.status != '.WorkshopVehiculeOperation::STATUS_DONE;
				$sqlVO .= ' ORDER BY cmo.code ASC';
				$resVO = $db->query($sqlVO);
				if ($resVO && $db->num_rows($resVO) > 0) {
					$voEditOptions = array();
					while ($objVO = $db->fetch_object($resVO)) {
						$optLabel = $objVO->code.' — '.$objVO->op_label;
						if (!empty($objVO->date_next)) {
							$optLabel .= ' ('.dol_print_date($db->jdate($objVO->date_next), 'day').')';
						}
						$voEditOptions[(string)(int) $objVO->rowid] = dol_escape_htmltag($optLabel);
					}
					$htmlEditVOSelect = $form->multiselectarray('job_vehicule_operations', $voEditOptions, array_map('strval', $curVoIds), 0, 0, 'flat minwidth260', 0, 0);
					$db->free($resVO);
				}
			}

			$fqEdit = array(
				array(
					'type'  => 'hidden',
					'name'  => 'jobid',
					'value' => (string) $editJobId,
				),
				array(
					'type'  => 'other',
					'label' => $langs->trans('ServiceType').' <span class="fieldrequired">*</span>',
					'name'  => 'job_fk_service_type',
					'value' => $htmlEditSTSelect,
				),
			);
			if (!empty($htmlEditVOSelect)) {
				$fqEdit[] = array(
					'type'  => 'other',
					'label' => $langs->trans('MaintenanceOperations'),
					'name'  => 'job_vehicule_operations',
					'value' => $htmlEditVOSelect,
				);
			}
			$fqEdit = array_merge($fqEdit, array(
				array(
					'type'  => 'textarea',
					'label' => $langs->trans('Description'),
					'name'  => 'job_description',
					'value' => $curDesc,
					'moreattr' => 'style=width:99%'
				),
				array(
					'type'  => 'text',
					'label' => $langs->trans('QtyMO').' <span class="fieldrequired">*</span> ('.$langs->trans('Hours').')',
					'name'  => 'job_qty_mo',
					'value' => $curQtyMO ?: '1.00',
					'size'  => 10,
				),
				array(
					'type'  => 'text',
					'label' => $langs->trans('RemiseMO').' (%)',
					'name'  => 'job_remise_percent',
					'value' => $curRemise,
					'size'  => 8,
				),
			));
			print $form->formconfirm(
				$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&jobid='.(int) $editJobId,
				$langs->trans('EditJob'),
				'',
				'confirm_edit_job',
				$fqEdit,
				'yes',
				1,
				550,
				750,
				0,
				$langs->trans('Save'),
				$langs->trans('Cancel')
			);
		}
	}

	// ── Dialog étape 1 : Sélection du produit/service (ajout) ────────────
	if ($action === 'add_det' && $id > 0 && $canEditAtStatus) {
		$detJobId = GETPOSTINT('jobid');
		$htmlProdSelect = $form->select_produits(GETPOSTINT('det_fk_product'), 'det_fk_product', '', 0, 0, 1, 2, '', 0, array(), 0, '1', 1, 'maxwidth400', 0, '', null, 1);

		$fqProd = array(
			array('type' => 'hidden', 'name' => 'jobid', 'value' => (string) $detJobId),
			array(
				'type'  => 'other',
				'label' => $langs->trans('Product').' <span class="fieldrequired">*</span>',
				'name'  => 'det_fk_product',
				'value' => $htmlProdSelect,
			),
		);

		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&token='.newToken(),
			$langs->trans('AddDetLine').' — '.$langs->trans('SelectProduct'),
			'',
			'add_det_details',
			$fqProd,
			'yes',
			1,
			0,
			600,
			0,
			$langs->trans('Next'),
			$langs->trans('Cancel')
		);
	}

	// ── Dialog étape 2 : Détails d'une ligne (ajout après sélection / modification) ──
	if (in_array($action, array('add_det_details', 'edit_det', 'confirm_add_det', 'confirm_edit_det')) && $id > 0 && $canEditAtStatus) {
		$isEditDet = in_array($action, array('edit_det', 'confirm_edit_det'));

		// ── Charger les données selon le contexte ──
		$detFkProduct   = 0;
		$detJobId       = 0;
		$detId          = 0;
		$detDescription = '';
		$detQty         = '1';
		$detPrice       = '0';
		$detRemise      = '0';
		$detFkWarehouse = 0;
		$_showDialog    = true;

		if ($isEditDet) {
			$detId  = GETPOSTINT('detid');
			$detObj = new Operationorderdet($db);
			if ($detObj->fetch($detId) > 0) {
				$detFkProduct = (int) $detObj->fk_product;
				$detJobId     = (int) $detObj->fk_operationorder_jobs;
				// Sur erreur (confirm=yes dans l'URL) : reprendre les saisies utilisateur
				$isRetry = (GETPOST('confirm', 'alpha') === 'yes');
				if ($isRetry) {
					$detDescription = GETPOST('det_description', 'restricthtml');
					$detQty         = GETPOST('det_qty', 'alpha');
					$detPrice       = GETPOST('det_price', 'alpha');
					$detRemise      = GETPOST('det_remise_percent', 'alpha');
					$detFkWarehouse = GETPOSTINT('det_fk_warehouse');
				} else {
					$detDescription = (string) $detObj->description;
					$detQty         = price2num($detObj->qty, 2);
					$detPrice       = price2num($detObj->price, 'MU');
					$detRemise      = price2num($detObj->remise_percent, 2);
					$detFkWarehouse = (int) $detObj->fk_warehouse;
				}
			} else {
				setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
				$_showDialog = false;
			}
		} else {
			$detFkProduct   = GETPOSTINT('det_fk_product');
			$detJobId       = GETPOSTINT('jobid');
			$detDescription = GETPOST('det_description', 'restricthtml');
			$detQty         = GETPOST('det_qty', 'alpha') ?: '1';
			$detPrice       = GETPOST('det_price', 'alpha');
			$detRemise      = GETPOST('det_remise_percent', 'alpha');
			if ($detRemise === '' || $detRemise === null) {
				$detRemise = '0';
			}
			$detFkWarehouse = GETPOSTINT('det_fk_warehouse');
		}

		if ($_showDialog) {
			// Charger le produit pour label, type et pré-remplissage (ajout)
			require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
			$prodTmp        = new Product($db);
			$detProductType = -1;
			$prodLabel      = '';
			if ($detFkProduct > 0 && $prodTmp->fetch($detFkProduct) > 0) {
				$detProductType = (int) $prodTmp->type;
				$prodLabel = $prodTmp->getNomUrl(1).' '.dol_escape_htmltag($prodTmp->label);
				// Pour l'ajout : pré-remplir depuis le produit si pas encore de valeurs
				if (!$isEditDet) {
					if ($detDescription === '' || $detDescription === null) {
						$detDescription = dol_string_nohtmltag($prodTmp->description ?? '', 0);
					}
					if ($detPrice === '' || $detPrice === null) {
						$detPrice = price2num($prodTmp->price, 'MU');
					}
				}
			}
			if ($detPrice === '' || $detPrice === null) {
				$detPrice = '0';
			}

			// ── Construction des formquestions ──
			$fqDet = array();
			if ($isEditDet) {
				$fqDet[] = array('type' => 'hidden', 'name' => 'detid', 'value' => (string) $detId);
			} else {
				$fqDet[] = array('type' => 'hidden', 'name' => 'jobid', 'value' => (string) $detJobId);
				$fqDet[] = array('type' => 'hidden', 'name' => 'det_fk_product', 'value' => (string) $detFkProduct);
			}

			// Produit (lecture seule — pas de 'name' pour éviter un $('#') invalide dans le JS formconfirm)
			$fqDet[] = array(
				'type'  => 'other',
				'label' => $langs->trans('Product'),
				'value' => $prodLabel ?: '<span class="opacitymedium">—</span>',
			);

			$fqDet[] = array(
				'type'     => 'textarea',
				'label'    => $langs->trans('Description'),
				'name'     => 'det_description',
				'value'    => $detDescription,
				'moreattr' => 'rows="6" style="width:99%"',
			);
			$fqDet[] = array(
				'type'  => 'text',
				'label' => $langs->trans('Qty').' <span class="fieldrequired">*</span>',
				'name'  => 'det_qty',
				'value' => $detQty,
				'size'  => 10,
			);
			$fqDet[] = array(
				'type'  => 'text',
				'label' => $langs->trans('UnitPriceHT'),
				'name'  => 'det_price',
				'value' => $detPrice,
				'size'  => 10,
			);
			$fqDet[] = array(
				'type'  => 'text',
				'label' => $langs->trans('Discount').' (%)',
				'name'  => 'det_remise_percent',
				'value' => $detRemise,
				'size'  => 8,
			);

			// Sélecteur entrepôt (produits physiques uniquement)
			if ($detFkProduct > 0 && $detProductType === 0) {
				$sqlWh  = "SELECT e.rowid, e.ref, COALESCE(ps.reel, 0) as qty, p.fk_default_warehouse";
				$sqlWh .= " FROM ".MAIN_DB_PREFIX."entrepot e";
				$sqlWh .= " LEFT JOIN ".MAIN_DB_PREFIX."product_stock ps ON (ps.fk_entrepot = e.rowid AND ps.fk_product = ".(int) $detFkProduct.")";
				$sqlWh .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON (p.rowid = ".(int) $detFkProduct.")";
				$sqlWh .= " WHERE e.entity IN (".getEntity('stock').")";
				$sqlWh .= " AND e.statut >= 0";
				$sqlWh .= " ORDER BY CASE WHEN e.rowid = p.fk_default_warehouse THEN 0 ELSE 1 END ASC,";
				$sqlWh .= " CASE WHEN COALESCE(ps.reel, 0) > 0 THEN 0 ELSE 1 END ASC, e.ref ASC";
				$resWh = $db->query($sqlWh);

				$htmlWhSelect = '<select name="det_fk_warehouse" id="det_fk_warehouse" class="flat maxwidth400">';
				$htmlWhSelect .= '<option value="">&nbsp;</option>';
				if ($resWh) {
					while ($objWh = $db->fetch_object($resWh)) {
						$whLabel = dol_escape_htmltag($objWh->ref);
						if ((float) $objWh->qty > 0) {
							$whLabel .= ' ('.price2num($objWh->qty, 2).')';
						}
						$sel = '';
						if ($detFkWarehouse > 0 && (int) $objWh->rowid === $detFkWarehouse) {
							$sel = ' selected';
						} elseif ($detFkWarehouse <= 0 && (int) $objWh->fk_default_warehouse > 0 && (int) $objWh->rowid === (int) $objWh->fk_default_warehouse) {
							$sel = ' selected';
						}
						$htmlWhSelect .= '<option value="'.(int) $objWh->rowid.'"'.$sel.'>'.$whLabel.'</option>';
					}
					$db->free($resWh);
				}
				$htmlWhSelect .= '</select>';

				$fqDet[] = array(
					'type'  => 'other',
					'label' => $langs->trans('StockLocation'),
					'name'  => 'det_fk_warehouse',
					'value' => $htmlWhSelect,
				);
			}

			// ── Paramètres du formconfirm ──
			$confirmAction = $isEditDet ? 'confirm_edit_det' : 'confirm_add_det';
			$confirmTitle  = $isEditDet ? $langs->trans('ModifyDetLine') : $langs->trans('AddDetLine');
			$confirmPageUrl = $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&token='.newToken();
			if ($isEditDet) {
				$confirmPageUrl .= '&detid='.(int) $detId;
			} else {
				$confirmPageUrl .= '&jobid='.(int) $detJobId.'&det_fk_product='.(int) $detFkProduct;
			}

			print $form->formconfirm(
				$confirmPageUrl,
				$confirmTitle,
				'',
				$confirmAction,
				$fqDet,
				'yes',
				1,
				550,
				700,
				0,
				$langs->trans('Save'),
				$langs->trans('Cancel')
			);
		}
	}

	// ── Dialog : Sous-traitance d'un Job ─────────────────────────────────
	if (($action === 'subcontracting_job' || $action === 'confirm_subcontracting') && $id > 0 && $canEditAtStatus) {
		$scJobId = GETPOSTINT('jobid');

		// Sélecteur fournisseur (type 'other' : le select_company génère un <select id="sc_fk_soc">)
		$htmlSupplier = $form->select_company(GETPOSTINT('sc_fk_soc'), 'sc_fk_soc', 's.fournisseur = 1', 1, 1, 0, array(), '', false, 'maxwidth400');

		// Opérations de maintenance du véhicule
		$scVoOptions = array();
		if (!empty($object->fk_vehicule)) {
			$sqlVo  = 'SELECT vo.rowid, cmo.code, cmo.label AS op_label, vo.date_next';
			$sqlVo .= ' FROM '.MAIN_DB_PREFIX.'workshop_vehicule_operation vo';
			$sqlVo .= ' LEFT JOIN '.MAIN_DB_PREFIX.'workshop_vehicule_c_maintenance_operation cmo';
			$sqlVo .= '   ON cmo.rowid = vo.fk_maintenance_operation';
			$sqlVo .= ' WHERE vo.fk_vehicule = '.(int) $object->fk_vehicule;
			$sqlVo .= ' AND vo.status != '.WorkshopVehiculeOperation::STATUS_DONE;
			$sqlVo .= ' ORDER BY cmo.code ASC';
			$resVo = $db->query($sqlVo);
			if ($resVo) {
				while ($oVo = $db->fetch_object($resVo)) {
					$optLabel = $oVo->code.' — '.$oVo->op_label;
					if (!empty($oVo->date_next)) {
						$optLabel .= ' ('.dol_print_date($db->jdate($oVo->date_next), 'day').')';
					}
					$scVoOptions[(string)(int) $oVo->rowid] = dol_escape_htmltag($optLabel);
				}
				$db->free($resVo);
			}
		}

		// Pré-sélection : opérations déjà liées à ce job
		$preSelectedOps = array();
		if ($scJobId > 0) {
			$tmpJob = new Operationorder_jobs($db);
			if ($tmpJob->fetch($scJobId) > 0) {
				$linkedIds = $tmpJob->fetchLinkedMaintenanceOperationIds();
				$preSelectedOps = is_array($linkedIds) ? array_map('strval', $linkedIds) : array();
			}
		}

		// Construire le HTML du multiselect (ou message si vide)
		$htmlOpsSelect = '';
		if (!empty($scVoOptions)) {
			$htmlOpsSelect = $form->multiselectarray('sc_maintenance_ops', $scVoOptions, $preSelectedOps, 0, 0, 'flat', 0, '100%', '', '', $langs->trans('SelectMaintenanceOps'));
			$htmlOpsSelect .= '<br><span class="opacitymedium small">'.$langs->trans('SubcontractingOpsHint').'</span>';
		} else {
			$htmlOpsSelect = '<span class="opacitymedium small">'.$langs->trans('NoMaintenanceOpsForVehicle').'</span>';
		}

		$fqSc = array(
			array('type' => 'hidden', 'name' => 'jobid', 'value' => (string) $scJobId),
			array(
				'type'  => 'other',
				'label' => $langs->trans('Supplier').' <span class="fieldrequired">*</span>',
				'name'  => 'sc_fk_soc',
				'value' => $htmlSupplier,
			),
			array(
				'type'  => 'text',
				'label' => $langs->trans('SubcontractingAmount').' <span class="fieldrequired">*</span>',
				'name'  => 'sc_amount',
				'value' => GETPOST('sc_amount', 'alpha') ?: '0',
				'size'  => 15,
			),
			array(
				'type'  => 'other',
				'label' => $langs->trans('MaintenanceOperationsLinked'),
				'name'  => 'sc_maintenance_ops',
				'value' => $htmlOpsSelect,
			),
			array(
				'type'     => 'textarea',
				'label'    => $langs->trans('Description'),
				'name'     => 'sc_description',
				'value'    => GETPOST('sc_description', 'restricthtml'),
				'moreattr' => 'rows="4" style="width:99%"',
			),
		);

		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&jobid='.(int) $scJobId.'&token='.newToken(),
			$langs->trans('SubcontractingJob'),
			'',
			'confirm_subcontracting',
			$fqSc,
			'yes',
			1,
			500,
			640,
			0,
			$langs->trans('Save'),
			$langs->trans('Cancel')
		);
	}

	// ═══════════════════════════════════════════════════════════════════════
	// SECTION JOBS
	// ═══════════════════════════════════════════════════════════════════════

	print '<div class="workshop-jobs-section fichecenter">'."\n";
	print '<div class="fichehalfleft" style="width:100%">'."\n";
	print '<div class="div-table-responsive-no-min">'."\n";

	// ── Chargement des jobs ─────────────────────────────────────────────
	$jobsObj  = new Operationorder_jobs($db);
	$jobsList = $jobsObj->fetchAllByOperationorder($object->id);
	if (!is_array($jobsList)) {
		$jobsList = array();
	}

	// Pré-chargement du cache des types de service (évite N requêtes dans le TPL)
	$serviceTypeCache = array();
	foreach ($jobsList as $j) {
		if (!empty($j->fk_service_type) && !isset($serviceTypeCache[$j->fk_service_type])) {
			$st = new ServiceType($db);
			if ($st->fetch((int) $j->fk_service_type) > 0) {
				$serviceTypeCache[$j->fk_service_type] = $st;
			}
		}
	}

	// Rafraîchissement du montant sous-traitance de chaque job à chaque affichage,
	// puis mise à jour des totaux de l'OR.
	foreach ($jobsList as $j) {
		$j->refreshExternalAmount($user);
	}
	$object->updateTotals($user);

	// Pré-chargement des commandes fournisseurs liées par job (pour affichage dans le TPL)
	// jobId → array of CommandeFournisseur
	$jobLinkedOrdersCache = array();
	if (!empty($jobsList)) {
		$allJobIds = array_map(function ($j) { return (int) $j->id; }, $jobsList);
		$sqlJLO  = 'SELECT ee.fk_source AS job_id, ee.fk_target AS order_id';
		$sqlJLO .= ' FROM '.MAIN_DB_PREFIX.'element_element ee';
		$sqlJLO .= ' WHERE ee.fk_source IN ('.implode(',', $allJobIds).')';
		$sqlJLO .= " AND ee.sourcetype = 'operationorder_jobs'";
		$sqlJLO .= " AND ee.targettype = 'order_supplier'";
		$resJLO = $db->query($sqlJLO);
		if ($resJLO) {
			while ($oJLO = $db->fetch_object($resJLO)) {
				$cfObj = new CommandeFournisseur($db);
				if ($cfObj->fetch((int) $oJLO->order_id) > 0) {
					$jobLinkedOrdersCache[(int) $oJLO->job_id][] = $cfObj;
				}
			}
			$db->free($resJLO);
		}
	}

	// Pré-chargement des quantités débitées par ligne det (TYPE_PRODUCT uniquement)
	// Résultat : $detQtyDebitedMap[det_id] = qty_debited (net sorti = valeurs négatives inversées)
	$detQtyDebitedMap  = array();
	$_detElemType      = 'operationorderdet@workshop'; // format classname@modulename pour get_origin()
	$_sqlQtyDebit      = 'SELECT d.rowid, COALESCE(-SUM(sm.value), 0) as qty_debited';
	$_sqlQtyDebit     .= ' FROM '.MAIN_DB_PREFIX.'workshop_operationorderdet d';
	$_sqlQtyDebit     .= ' INNER JOIN '.MAIN_DB_PREFIX.'workshop_operationorder_jobs j ON j.rowid = d.fk_operationorder_jobs';
	$_sqlQtyDebit     .= ' LEFT JOIN '.MAIN_DB_PREFIX.'stock_mouvement sm';
	$_sqlQtyDebit     .= "   ON sm.fk_origin = d.rowid AND sm.origintype IN ('".$db->escape($_detElemType)."', 'workshop_operationorderdet')";
	$_sqlQtyDebit     .= ' WHERE j.fk_operationorder = '.((int) $object->id);
	$_sqlQtyDebit     .= ' AND d.product_type = '.((int) Operationorderdet::TYPE_PRODUCT);
	$_sqlQtyDebit     .= ' GROUP BY d.rowid';
	$_resQtyDebit = $db->query($_sqlQtyDebit);
	if ($_resQtyDebit) {
		while ($_oQD = $db->fetch_object($_resQtyDebit)) {
			$detQtyDebitedMap[(int) $_oQD->rowid] = (float) $_oQD->qty_debited;
		}
		$db->free($_resQtyDebit);
	}
	unset($_detElemType, $_sqlQtyDebit, $_resQtyDebit, $_oQD);

	include dol_buildpath('/workshop/tpl/or_card_jobs_table.tpl.php', 0);

	print '</div>'."\n"; // div-table-responsive
	print '</div>'."\n"; // fichehalfleft

	print '</div>'."\n"; // workshop-jobs-section


	// ── Fichiers joints / Objets liés / Derniers événements ─────────────────
	print '<div class="fichecenter"><div class="fichehalfleft">'."\n";
	print '<a name="builddoc"></a>'."\n";

	// Fichiers joints
	$modulesubdir = 'operationorder/'.(int) $object->id;
	$filedir    = (isset($conf->workshop->multidir_output[$object->entity]) ? $conf->workshop->multidir_output[$object->entity] : $conf->workshop->dir_output).'/'.$modulesubdir;
	$urlsource  = $_SERVER['PHP_SELF'].'?id='.$object->id;
	$genallowed = $user->hasRight('workshop', 'operationorders', 'read');
	$delallowed = $user->hasRight('workshop', 'operationorders', 'write');

	print $formfile->showdocuments('workshop', $modulesubdir, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', '', '', $object);

	// Objets liés
	$object->fetchObjectLinked();
	$form->showLinkedObjectBlock($object, '');

	print '</div><div class="fichehalfright">'."\n";

	// Les derniers événements
	$formactions->showactions($object, $object->element, $object->fk_soc, 1);

	print '</div></div>'."\n";


	// JS — fichier externe (mode création helpers, dialog resize, color swatches)
	print '<script src="'.dol_escape_htmltag(dol_buildpath('/workshop/js/or_card.js', 1)).'"></script>'."\n";


	llxFooter();
	$db->close();
	exit;
}


// ═══════════════════════════════════════════════════════════════════════════
// MODE CRÉATION : formulaire de saisie d'un nouvel OR
// ═══════════════════════════════════════════════════════════════════════════

llxHeader('', $langs->trans('NewOperationOrders'), '', '', 0, 0, array(), array(dol_buildpath('/workshop/css/or_card.css', 1)), '', 'mod-workshop page-orcard');

print load_fiche_titre($langs->trans('NewOperationOrders'), '', 'fa-tools');

// ── Dialogs formconfirm (hors du formulaire principal) ──────────────────────

// Dialog : Nouveau conducteur
if ($action == 'new_conducteur' || $action == 'confirm_new_conducteur') {
	// L'état du formulaire OR est passé dans l'URL (GET) — pas de hidden dans formquestion
	$pageUrl = $_SERVER['PHP_SELF'].orCardStatePageUrl();
	$fq = array(
		array(
			'type'  => 'text',
			'label' => $langs->trans('ConducteurNom').' <span class="fieldrequired">*</span>',
			'name'  => 'conducteur_nom',
			'value' => GETPOST('conducteur_nom', 'alphanohtml'),
		),
		array(
			'type'  => 'text',
			'label' => $langs->trans('ConducteurPrenom').' <span class="fieldrequired">*</span>',
			'name'  => 'conducteur_prenom',
			'value' => GETPOST('conducteur_prenom', 'alphanohtml'),
		),
		array(
			'type'  => 'other',
			'label' => $langs->trans('ConducteurSociete'),
			'name'  => 'conducteur_fk_soc',
			'value' => $form->select_company(GETPOSTINT('conducteur_fk_soc'), 'conducteur_fk_soc', '', $langs->trans('SelectThirdParty'), 1, 0, array(), 0, 'minwidth200'),
		),
	);
	print $form->formconfirm(
		$pageUrl,
		$langs->trans('NewConducteur'),
		'',
		'confirm_new_conducteur',
		$fq,
		'yes',
		1,
		250,
		500,
		0,
		$langs->trans('Create'),
		$langs->trans('Cancel')
	);
}

// Dialog : Nouveau tag
if ($action == 'new_tag' || $action == 'confirm_new_tag') {
	// L'état du formulaire OR est passé dans l'URL (GET) — pas de hidden dans formquestion
	$pageUrl = $_SERVER['PHP_SELF'].orCardStatePageUrl();
	$fq = array(
		array(
			'type'  => 'text',
			'label' => $langs->trans('Code').' <span class="fieldrequired">*</span>',
			'name'  => 'tag_code',
			'value' => GETPOST('tag_code', 'alphanohtml'),
		),
		array(
			'type'  => 'text',
			'label' => $langs->trans('TagLibelle').' <span class="fieldrequired">*</span>',
			'name'  => 'tag_label',
			'value' => GETPOST('tag_label', 'alphanohtml'),
		),
		(function () use ($langs) {
			$palette  = getWorkshopColorPalette();
			$rawColor = GETPOST('tag_color', 'nohtml');
			$selected = isset($palette[$rawColor]) ? $rawColor : '#3c7dc4';
			if (!isset($palette[$selected])) {
				$selected = key($palette);
			}
			return array(
				'type'    => 'select',
				'label'   => $langs->trans('TagCouleur'),
				'name'    => 'tag_color',
				'values'  => $palette,
				'default' => $selected,
			);
		})(),
	);
	print $form->formconfirm(
		$pageUrl,
		$langs->trans('NewTag'),
		'',
		'confirm_new_tag',
		$fq,
		'yes',
		1,
		220,
		480,
		0,
		$langs->trans('Create'),
		$langs->trans('Cancel')
	);
}

// ── Formulaire principal de création ────────────────────────────────────────

print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">'."\n";
print '<input type="hidden" name="action" value="add">'."\n";
if ($backtopage) {
	print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">'."\n";
}

print dol_get_fiche_head(array(), '', $langs->trans('OperationOrder'), -1, 'fa-tools');

print '<table class="border centpercent tableforfieldcreate">'."\n";

// ── Véhicule (obligatoire) ───────────────────────────────────────────────────
// Affichage : VIN + immatriculation (les deux ont showoncombobox=1)
// Recherche : VIN, immatriculation (les deux ont searchall=1)
// Le tiers (fk_soc) est déduit automatiquement lors de la création.
print '<tr>';
print '<td class="titlefieldcreate fieldrequired">'.$langs->trans('Vehicule').'</td>';
print '<td>';
print $object->showInputField($object->fields['fk_vehicule'], 'fk_vehicule', GETPOSTINT('fk_vehicule'), '', '', '', 'maxwidth500');
print '</td>';
print '</tr>'."\n";

// ── Kilométrage ──────────────────────────────────────────────────────────────
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('Km').'</td>';
print '<td>';
print $object->showInputField($object->fields['km'], 'km', GETPOST('km', 'alpha'), '', '', '', 'maxwidth100');
print '</td>';
print '</tr>'."\n";

// ── Conducteur + bouton "+" ──────────────────────────────────────────────────
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('Conducteur').'</td>';
print '<td>';
print $object->showInputField($object->fields['fk_conducteur'], 'fk_conducteur', GETPOSTINT('fk_conducteur'), '', '', '', 'maxwidth500');
print ' <a href="#" class="butActionNew" onclick="orCardOpenNewConducteur(); return false;" title="'.dol_escape_htmltag($langs->trans('NewConducteur')).'">';
print '<span class="fa fa-plus-circle valignmiddle"></span>';
print '</a>';
print '</td>';
print '</tr>'."\n";

// ── Tag(s) + bouton "+" ──────────────────────────────────────────────────────
$tagObj  = new Tag($db);
$allTags = $tagObj->getAllActiveTags();

print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('Tags').'</td>';
print '<td>';
if (is_array($allTags) && !empty($allTags)) {
	$tagArray       = array();
	foreach ($allTags as $tid => $tag) {
		$tagArray[$tid] = $tag->label;
	}
	$selectedTagIds = GETPOST('fk_tags', 'array:int');
	print $form->multiselectarray('fk_tags', $tagArray, $selectedTagIds, 0, 0, 'minwidth300 widthcentpercent', 0, 0, '', '', 1);
} else {
	print '<span class="opacitymedium">'.$langs->trans('NoTagDefined').'</span>';
}
print ' <a href="#" class="butActionNew" onclick="orCardOpenNewTag(); return false;" title="'.dol_escape_htmltag($langs->trans('NewTag')).'">';
print '<span class="fa fa-plus-circle valignmiddle"></span>';
print '</a>';
print '</td>';
print '</tr>'."\n";

print '</table>'."\n";

print dol_get_fiche_end();

print $form->buttonsSaveCancel("Create");

print '</form>'."\n";

// Variables JS injectées pour or_card.js (mode création)
print '<script>'."\n";
print 'window.workshopOrCardSelf = '.json_encode($_SERVER['PHP_SELF']).';'."\n";
print '</script>'."\n";
print '<script src="'.dol_escape_htmltag(dol_buildpath('/workshop/js/or_card.js', 1)).'"></script>'."\n";

llxFooter();
$db->close();
