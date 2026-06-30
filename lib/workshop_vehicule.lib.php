<?php
/*
 * Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/workshop_vehicule.lib.php
 * \ingroup workshop
 * \brief   Library files with common functions for Vehicule
 */

/**
 * Prepare array of tabs for Vehicule
 *
 * @param	Vehicule	$object		Vehicule
 * @return 	array					Array of tabs
 */
function vehiculePrepareHead($object)
{
	global $db, $langs, $conf, $user;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/vehicule/vehicule_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Vehicule");
	$head[$h][2] = 'card';
	$h++;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->workshop->multidir_output[isset($object->entity) ? $object->entity : $conf->entity]."/vehicule/".dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);
	$head[$h][0] = dol_buildpath("/workshop/vehicule/vehicule_document.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Documents');
	if (($nbFiles + $nbLinks) > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
	}
	$head[$h][2] = 'document';
	$h++;


	if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
		$nbNote = 0;
		if (!empty($object->note_private)) {
			$nbNote++;
		}
		if (!empty($object->note_public)) {
			$nbNote++;
		}
		$head[$h][0] = dolBuildUrl(dol_buildpath('/workshop/vehicule/vehicule_note.php', 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans('Notes');
		if ($nbNote > 0) {
			$head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
		}
		$head[$h][2] = 'note';
		$h++;
	}

	if ($user->hasRight('workshop', 'operationorders', 'read')) {
		$nbOperationOrder = getNbORVehicle($object->id);
		$head[$h][0] = dol_buildpath('/workshop/operationorder/or_list.php?origin=vehicule&originid='.$object->id, 1);
		$head[$h][1] = $langs->trans('ORListHisto').'<span class="badge marginleftonlyshort">'.max($nbOperationOrder, 0).'</span>';
		$head[$h][2] = 'list';
		$h++;
	}

	// Show more tabs from modules
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'vehicule@workshop');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'vehicule@workshop', 'remove');

	return $head;
}


/**
 * Prepare array of tabs for Vehicule Setup screen
 * @return    array                    Array of tabs
 */
function VhSetupPrepareHead(): array
{
	global $langs, $conf;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_marque.php", 1);
	$head[$h][1] = $langs->trans("VhSetupMarque");
	$head[$h][2] = 'marque';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_type.php", 1);
	$head[$h][1] = $langs->trans("VhSetupType");
	$head[$h][2] = 'type';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_typect.php", 1);
	$head[$h][1] = $langs->trans("VhSetupTypeCt");
	$head[$h][2] = 'typect';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_pneu.php", 1);
	$head[$h][1] = $langs->trans("VhSetupPneu");
	$head[$h][2] = 'pneu';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopvehiculesetup@workshop');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopvehiculesetup@workshop', 'remove');

	return $head;
}


/**
 * Build form confirmations for vehicule actions
 *
 * @param Form     $form   Form object
 * @param Vehicule $object Vehicule object
 * @param string   $action Triggered action
 * @return string
 */
function getFormConfirmWorkshopVehicule($form, $object, $action)
{
	global $langs, $user, $db;

	$formconfirm = '';

	// Déclaration data-driven des confirmations simples
	// Format : action => [right, titleKey, bodyKey, confirmAction, extraParams (query string)]
	$confirmDefs = array(
		'valid'          => array('write',  'ConfirmActivateWorkshopVehiculeTitle', 'ConfirmActivateWorkshopVehiculeBody', 'confirm_validate',    ''),
		'modif'          => array('write',  'ConfirmReopenWorkshopVehiculeTitle',   'ConfirmReopenWorkshopVehiculeBody',   'confirm_modif',       ''),
		'delete'         => array('delete', 'ConfirmDeleteWorkshopVehiculeTitle',   'ConfirmDeleteWorkshopVehiculeBody',   'confirm_delete',      ''),
		'clone'          => array('write',  'ConfirmCloneWorkshopVehiculeTitle',    'ConfirmCloneWorkshopVehiculeBody',    'confirm_clone',       ''),
		'delActivity'    => array('write',  'ConfirmDeleteWorkshopVehiculeTitle',   'ConfirmDelActivityWorkshopVehiculeBody',  'confirm_delActivity',    '&act_id='.GETPOSTINT('act_id')),
		'unlinkVehicule' => array('write',  'ConfirmUnlinkVehiculeWorkshopTitle',   'ConfirmUnlinkVehiculeWorkshopBody',  'confirm_unlinkVehicule', '&linkVehicule_id='.GETPOSTINT('linkVehicule_id')),
		'delOperation'   => array('write',  'ConfirmDeleteWorkshopVehiculeTitle',   'ConfirmDelOperationWorkshopBody',    'confirm_delOperation',   '&ope_id='.GETPOSTINT('ope_id')),
	);

	if (isset($confirmDefs[$action])) {
		list($right, $titleKey, $bodyKey, $confirmAction, $extraParams) = $confirmDefs[$action];
		if ($user->hasRight('workshop', 'vehicule', $right)) {
			$url  = dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.$extraParams;
			$body = $langs->trans($bodyKey, $object->immatriculation);
			$formconfirm = $form->formconfirm($url, $langs->trans($titleKey), $body, $confirmAction, '', 0, 1);
		}
	} elseif ($action === 'cloneOperations' && $user->hasRight('workshop', 'vehicule', 'write')) {
		// Cas spécial : sélection du véhicule source
		$sql = "SELECT v.rowid, v.vin, v.immatriculation, vm.label as marque";
		$sql .= " FROM ".$db->prefix()."workshop_vehicule as v";
		$sql .= " LEFT JOIN ".$db->prefix()."workshop_vehicule_c_vehicule_mark as vm ON vm.rowid = v.fk_vehicule_mark";
		$sql .= " WHERE v.status = 1";
		$sql .= " AND v.fk_vehicule_type = ".((int) $object->fk_vehicule_type);
		$sql .= " AND v.rowid <> ".((int) $object->id);
		$sql .= " ORDER BY v.immatriculation ASC";
		$resql = $db->query($sql);
		$TVehicles = array();
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$TVehicles[$obj->rowid] = $obj->vin.' - '.$obj->immatriculation.' - '.$obj->marque;
			}
		}
		$formquestion = array(
			array('type' => 'select', 'name' => 'source_vehicule_id', 'label' => $langs->trans('CloneOperationsSourceVehicle'), 'values' => $TVehicles, 'default' => '')
		);
		$formconfirm = $form->formconfirm(
			dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id,
			$langs->trans('CloneOperationsFromVehicleTitle'),
			$langs->trans('CloneOperationsFromVehicleBody'),
			'confirm_cloneOperations',
			$formquestion,
			'yes', 1, 0, 700
		);
	}

	return $formconfirm;
}


/**
 * Print vehicule activities section
 *
 * @param Vehicule $object   Vehicule object
 * @param bool     $fromcard Whether called from card
 */
function printVehiculeActivities($object, $fromcard = false)
{
	global $langs, $db, $form;

	print load_fiche_titre($langs->trans('VehiculeActivities'), '', '');
	include dol_buildpath('/workshop/tpl/vehicule_activities.tpl.php', 0);
}


/**
 * Print linked vehicules section
 *
 * @param Vehicule $object   Vehicule object
 * @param bool     $fromcard Whether called from card
 */
function printLinkedVehicules($object, $fromcard = false)
{
	global $langs, $db, $form;

	print load_fiche_titre($langs->trans('LinkedVehicules'), '', '');
	include dol_buildpath('/workshop/tpl/vehicule_links.tpl.php', 0);
}


/**
 * Build a select array of maintenance operations, optionally filtered by vehicule type/mark
 *
 * @param  object $db             Database connector
 * @param  int    $fk_type        Filter by vehicule type (0 = all)
 * @param  int    $fk_mark        Filter by vehicule mark (0 = all)
 * @return array                  Array [rowid => 'code - label']
 */
function getMaintenanceOperationSelectArray($db, $fk_type = 0, $fk_mark = 0)
{
	$sql = "SELECT rowid, code, label FROM ".$db->prefix()."workshop_vehicule_c_maintenance_operation";
	$sql .= " WHERE active = 1";
	if (!empty($fk_type)) {
		$sql .= " AND (fk_vehicule_type IS NULL OR fk_vehicule_type = ".(int) $fk_type.")";
	}
	if (!empty($fk_mark)) {
		$sql .= " AND (fk_vehicule_mark IS NULL OR fk_vehicule_mark = ".(int) $fk_mark.")";
	}
	$sql .= " ORDER BY code ASC";

	$result = array();
	$resql  = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$result[$obj->rowid] = $obj->code.' - '.$obj->label;
		}
		$db->free($resql);
	}
	return $result;
}

function printVehiculeOperations($object)
{
	global $langs, $form, $user, $db;

	print load_fiche_titre($langs->trans('VehiculeOperations'), '', '');
	include dol_buildpath('/workshop/tpl/vehicule_operations.tpl.php', 0);
}


/**
 * Count operation orders linked to a vehicule
 *
 * @param int $idvehicle    Vehicule ID
 * @param int $checkEntity  Whether to check entity (1 = yes, 0 = no)
 * @return int              Number of operation orders, or -1 on error
 */
function getNbORVehicle($idvehicle, $checkEntity = 1)
{
	global $db;

	if (!isModEnabled('workshop')) {
		return 0;
	}

	$sql = 'SELECT COUNT(o.rowid) as nb FROM '.$db->prefix().'workshop_operationorder as o';
	$sql .= ' WHERE o.fk_vehicule = '.(int) $idvehicle;
	if ($checkEntity) {
		$sql .= ' AND o.entity IN ('.getEntity('workshop_operationorder').')';
	}
	$resql = $db->query($sql);

	if ($resql) {
		$obj = $db->fetch_object($resql);
		return (int) $obj->nb;
	}

	return -1;
}
