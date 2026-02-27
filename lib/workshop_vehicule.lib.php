<?php
/* Copyright (C) 2020 ATM Consulting <support@atm-consulting.fr>
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

	if (isModEnabled("operationorder") && $user->hasRight('operationorder', 'read')) {
		$nbOperationOrder = getNbORVehicle($object->id);
		$head[$h][0] = dol_buildpath('operationorder/list.php?&origin=vehicule&originid='.$object->id, 1);
		$head[$h][1] = $langs->trans('ORListHisto').'<span class="badge marginleftonlyshort">'.max($nbOperationOrder, 0).'</span>';
		$head[$h][2] = 'list';
		$h++;

		$nbOperationOrder = getNbORVehicle($object->id, 0);
		$langs->load('operationorder@operationorder');
		$head[$h][0] = dol_buildpath('operationorder/vsr.php?origin=vehicule&originid='.$object->id, 1);
		$head[$h][1] = $langs->trans('ORListVSR').'<span class="badge marginleftonlyshort">'.max($nbOperationOrder, 0).'</span>';
		$head[$h][2] = 'vsr';
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

	if ($action === 'valid' && $user->hasRight('workshop', 'vehicule', 'write')) {
		$body = $langs->trans('ConfirmActivateWorkshopVehiculeBody', $object->immatriculation);
		$formconfirm = $form->formconfirm(dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id, $langs->trans('ConfirmActivateWorkshopVehiculeTitle'), $body, 'confirm_validate', '', 0, 1);
	} elseif ($action === 'modif' && $user->hasRight('workshop', 'vehicule', 'write')) {
		$body = $langs->trans('ConfirmReopenWorkshopVehiculeBody', $object->immatriculation);
		$formconfirm = $form->formconfirm(dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id, $langs->trans('ConfirmReopenWorkshopVehiculeTitle'), $body, 'confirm_modif', '', 0, 1);
	} elseif ($action === 'delete' && $user->hasRight('workshop', 'vehicule', 'delete')) {
		$body = $langs->trans('ConfirmDeleteWorkshopVehiculeBody');
		$formconfirm = $form->formconfirm(dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id, $langs->trans('ConfirmDeleteWorkshopVehiculeTitle'), $body, 'confirm_delete', '', 0, 1);
	} elseif ($action === 'clone' && $user->hasRight('workshop', 'vehicule', 'write')) {
		$body = $langs->trans('ConfirmCloneWorkshopVehiculeBody', $object->immatriculation);
		$formconfirm = $form->formconfirm(dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id, $langs->trans('ConfirmCloneWorkshopVehiculeTitle'), $body, 'confirm_clone', '', 0, 1);
	} elseif ($action === 'delActivity' && $user->hasRight('workshop', 'vehicule', 'write')) {
		$body = $langs->trans('ConfirmDelActivityWorkshopVehiculeBody');
		$formconfirm = $form->formconfirm(dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&act_id='.GETPOST('act_id'), $langs->trans('ConfirmDeleteWorkshopVehiculeTitle'), $body, 'confirm_delActivity', '', 0, 1);
	} elseif ($action === 'unlinkVehicule' && $user->hasRight('workshop', 'vehicule', 'write')) {
		$body = $langs->trans('ConfirmUnlinkVehiculeWorkshopBody');
		$formconfirm = $form->formconfirm(dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&linkVehicule_id='.GETPOST('linkVehicule_id'), $langs->trans('ConfirmUnlinkVehiculeWorkshopTitle'), $body, 'confirm_unlinkVehicule', '', 0, 1);
	} elseif ($action === 'delOperation' && $user->hasRight('workshop', 'vehicule', 'write')) {
		$body = $langs->trans('ConfirmDelOperationWorkshopBody');
		$formconfirm = $form->formconfirm(dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&ope_id='.GETPOST('ope_id'), $langs->trans('ConfirmDeleteWorkshopVehiculeTitle'), $body, 'confirm_delOperation', '', 0, 1);
	} elseif ($action === 'cloneOperations' && $user->hasRight('workshop', 'vehicule', 'write')) {
		// Build vehicle list of same type (VIN - Immat - Marque)
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

	if (GETPOST('action', 'alpha') == 'editActivity') {
		$actionForm = 'updateActivity';
	} else {
		$actionForm = 'addActivity';
	}

	print '<form id="activityForm" method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$actionForm.'">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if (!empty(GETPOST('act_id', 'int'))) {
		print '<input type="hidden" name="act_id" value="'.GETPOST('act_id', 'int').'">';
	}

	print '<table class="border" width="100%">'."\n";
	print '<tr class="liste_titre">
					<td align="center">'.$langs->trans('DateStart').'</td>
					<td align="center">'.$langs->trans('DateEnd').'</td>
					<td align="center">'.$langs->trans('soc').'</td>
					<td></td>
					</tr>';

	$date_start = $date_end = '';
	if ($fromcard) {
		$date_start = dol_now();
		$date_end = strtotime("+3 month", $date_start);
	}

	$ret = $object->getActivities($date_start, $date_end);
	if ($ret == 0) {
		print '<tr><td align="center" colspan="4">'.$langs->trans('NoActivity').'</td></tr>';
	} elseif ($ret > 0) {
		foreach ($object->activities as $activity) {
			if (GETPOST('action', 'alpha') == 'editActivity'
				&& $activity->id == GETPOST('act_id', 'int')) {
				print '<tr>';
				print '<td align="center">'.$form->selectDate($activity->date_start, 'activityDate_start').'</td>';
				print '<td align="center">'.$form->selectDate($activity->date_end, 'activityDate_end').'</td>';
				print '<td align="center">'.$form->select_thirdparty_list($activity->fk_soc, 'socid', 's.client = 1', '', 0, 0, array(), '', 0, 0, '', 'style="width: 80%"').'</td>';
				print '<td align="center"><input class="button" type="submit" name="saveActivity" value="'.$langs->trans("Save").'"></td>';
				print '</tr>';
			} else {
				print '<tr>';
				print '<td align="center">'.dol_print_date($activity->date_start, "%d/%m/%Y").'</td>';
				print '<td align="center">'.(!empty($activity->date_end) ? dol_print_date($activity->date_end, "%d/%m/%Y") : '').'</td>';
				print '<td align="center">'.$activity->showOutputField($activity->fields['fk_soc'], 'fk_soc', $activity->fk_soc).'</td>';
				print '<td align="center">';
				print '<a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&action=editActivity&act_id='.$activity->id.'&token='.newToken().'">'.img_edit().'</a>';
				print '<a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&action=delActivity&act_id='.$activity->id.'&token='.newToken().'">'.img_delete().'</a>';
				print '</td>';
				print '</tr>';
			}
		}
	}

	if (GETPOST('action', 'alpha') !== 'editActivity'
		&& GETPOST('action', 'alpha') !== 'delActivity') {
		// Nouvelle ligne activité
		print '<tr id="newActivity">';
		print '<td align="center">';
		print $form->selectDate('', 'activityDate_start');
		print '</td>';
		print '<td align="center">';
		print $form->selectDate('', 'activityDate_end');
		print '</td>';
		print '<td align="center">';
		print $object->showOutputField($object->fields['fk_soc'], 'fk_soc', $object->fk_soc);
		print '</td>';
		print '<td align="center">';
		print '<input class="button" type="submit" name="addActivity" value="'.$langs->trans("Add").'">';
		print '</td>';
		print '</tr>';
	}

	print '</table>';
	print '</form>';
	?>
	<script>
		$("#activityDate_start").addClass("quatrevingtpercent");
		$("#activityDate_end").addClass("quatrevingtpercent");
	</script>
	<?php
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

	print '<form id="vehiculeLinkedForm" method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="addVehiculeLink">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';

	print '<table class="border" width="100%">'."\n";
	print '<tr class="liste_titre">';
	print '<td align="center">Immatriculation</td>';
	print '<td align="center">'.$langs->trans('DateStart').'</td>';
	print '<td align="center">'.$langs->trans('DateEnd').'</td>';
	print '<td align="center"></td>';
	print '</tr>';

	$object->getLinkedVehicules();
	if (empty($object->linkedVehicules)) {
		print '<tr><td align="center" colspan="4">'.$langs->trans('NoLinkedVehicule').'</td></tr>';
	} else {
		foreach ($object->linkedVehicules as $vehiculelink) {
			$veh = new Vehicule($db);
			$veh->fetch($vehiculelink->fk_other_vehicule);
			print '<tr>';
			print '<td align="center">'.$veh->getLinkUrl(0, '', 'immatriculation').'</td>';
			print '<td align="center">'.dol_print_date($vehiculelink->date_start, "%d/%m/%Y").'</td>';
			print '<td align="center">'.(!empty($vehiculelink->date_end) ? dol_print_date($vehiculelink->date_end, "%d/%m/%Y") : '').'</td>';
			print '<td align="center"><a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&action=unlinkVehicule&linkVehicule_id='.$vehiculelink->id.'&token='.newToken().'"><span class="fas fa-unlink"></span></a></td>';
			print '</tr>';
		}
	}

	// New link row
	print '<tr>';
	$sql = "SELECT v.rowid, v.immatriculation, vt.label FROM ".$db->prefix()."workshop_vehicule as v";
	$sql .= " LEFT JOIN ".$db->prefix()."workshop_vehicule_c_vehicule_type as vt ON vt.rowid = v.fk_vehicule_type";
	$sql .= " WHERE v.status = 1";
	$tmpMotriceTypes = getDolGlobalString("WORKSHOP_MOTRICE_TYPES");
	$WORKSHOP_MOTRICE_TYPES = !empty($tmpMotriceTypes) ? @unserialize($tmpMotriceTypes) : false;
	if (is_array($WORKSHOP_MOTRICE_TYPES) && !empty($WORKSHOP_MOTRICE_TYPES)) {
		$sanitizedTypes = array_map('intval', $WORKSHOP_MOTRICE_TYPES);
		if (in_array($object->fk_vehicule_type, $sanitizedTypes)) {
			$sql .= " AND v.fk_vehicule_type NOT IN (".implode(', ', $sanitizedTypes).")";
		} else {
			$sql .= " AND v.fk_vehicule_type IN (".implode(', ', $sanitizedTypes).")";
		}
	} else {
		$sql .= " AND v.fk_vehicule_type <> ".((int) $object->fk_vehicule_type);
	}
	$sql .= " AND v.fk_soc = ".((int) $object->fk_soc);
	$resql = $db->query($sql);
	$Tab = array();
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$Tab[$obj->rowid] = $obj->label.' - '.$obj->immatriculation;
		}
	}

	print '<td align="center">';
	print $form->selectarray('linkVehicule_id', $Tab, GETPOST('linkVehicule_id'), 1, 0, 0, '', 0, 0, 0, '', '', 1);
	print '</td>';
	print '<td align="center">';
	print $form->selectDate('', 'linkDate_start');
	print '</td>';
	print '<td align="center">';
	print $form->selectDate('', 'linkDate_end');
	print '</td>';
	print '<td align="center">';
	print '<input class="button" type="submit" name="linkVehicule" value="'.$langs->trans("Add").'">';
	print '</td>';
	print '</tr>';

	print '</table>';
	print '</form>';
}


/**
 * Print vehicule operations (maintenance schedule)
 *
 * @param Vehicule $object Vehicule object
 */
function printVehiculeOperations($object)
{
	global $langs, $form, $user;

	print load_fiche_titre($langs->trans('VehiculeOperations'), '', '');

	if (GETPOST('action', 'alpha') == 'editOperation') {
		$actionForm = 'updateOperation';
	} else {
		$actionForm = 'addVehiculeOperation';
	}

	print '<form id="vehiculeOperationsForm" method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$actionForm.'">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if (!empty(GETPOST('ope_id', 'int'))) {
		print '<input type="hidden" name="ope_id" value="'.GETPOST('ope_id', 'int').'">';
	}

	print '<table class="border" width="100%">'."\n";
	print '<tr class="liste_titre">';
	print '<td align="center">'.$langs->trans('VehiculeOperation').'</td>';
	print '<td align="center">'.$langs->trans('KM').'</td>';
	print '<td align="center">'.$langs->trans('VehiculeOperationDelay').'</td>';
	print '<td align="center">'.$langs->trans('VehiculeOperationLastDateDone').'</td>';
	print '<td align="center">'.$langs->trans('VehiculeOperationLastKmDone').'</td>';
	print '<td align="center">'.$langs->trans('VehiculeOperationDateNext').'</td>';
	print '<td align="center">'.$langs->trans('VehiculeOperationKmNext').'</td>';
	print '<td align="center">'.$langs->trans('VehiculeOperationOnTime').'</td>';
	if (isModEnabled('operationorder')) {
		print '<td align="center">'.$langs->trans('VehiculeOperationNextOR').'</td>';
	}
	print '<td align="center"></td>';
	print '</tr>';

	$res = $object->getOperations();
	if ($res < 0) {
		setEventMessages($object->error, null, 'errors');
	}
	if (empty($object->operations)) {
		print '<tr><td align="center" colspan="'.(isModEnabled('operationorder') ? 10 : 9).'">'.$langs->trans('NoOperation').'</td></tr>';
	} else {
		foreach ($object->operations as $operation) {
			print '<tr>';
			if (GETPOST('action', 'alpha') == 'editOperation'
				&& $operation->id == GETPOST('ope_id', 'int')) {
				print '<td align="center">';
				print $form->select_produits($operation->fk_product, 'productid', 1, 0, 0, 1, 2, '', 0);
				print '</td>';
				print '<td align="center">';
				print '<input class="quatrevingtpercent" type="number" name="km" id="km" step="1" value="'.$operation->km.'">';
				print '</td>';
				print '<td align="center">';
				print '<input class="soixantepercent" type="number" name="delay" id="delay" step="1" value="'.$operation->delai_from_last_op.'">&nbsp;'.$langs->trans('Months');
				print '</td>';
				print '<td align="center">';
				print $form->selectDate($operation->date_done, 'date_done');
				print '</td>';
				print '<td align="center"><input class="quatrevingtpercent" type="number" name="km_done" id="km_done" step="1" value="'.$operation->km_done.'"></td>';
				print '<td align="center">'.$operation->date_next.'</td>';
				print '<td align="center">'.$operation->km_next.'</td>';
				print '<td align="center">'.(!empty($operation->on_time) ? dolGetBadge($langs->trans('VehiculeOperationOnTime'), '', 'danger') : '').'</td>';
				if (isModEnabled('operationorder')) {
					print '<td align="center">';
					if (!empty($operation->or_next) && class_exists('OperationOrder')) {
						$operationorder = new OperationOrder($object->db);
						if ($operationorder->fetch($operation->or_next, false) > 0) {
							print $operationorder->getNomUrl(0);
						}
					}
					print '</td>';
				}
				print '<td align="center">';
				print '<input class="button quatrevingtpercent" type="submit" name="saveOperation" value="'.$langs->trans("Save").'">';
				print '</td>';
			} else {
				print '<td align="left">'.$operation->getName().'</td>';
				print '<td align="center">'.(!empty($operation->km) ? price2num($operation->km) : '').'</td>';
				print '<td align="center">'.(!empty($operation->delai_from_last_op) ? $operation->delai_from_last_op.' '.$langs->trans('Months') : '').'</td>';
				print '<td align="center">';
				if (!empty($operation->date_done)) {
					print dol_print_date($operation->date_done, "%d/%m/%Y");
				}
				print '</td>';
				print '<td align="center">'.(!empty($operation->km_done) ? $operation->km_done : '').'</td>';
				print '<td align="center">';
				if (!empty($operation->date_next)) {
					print dol_print_date($operation->date_next, "%d/%m/%Y");
				}
				print '</td>';
				print '<td align="center">'.$operation->km_next.'</td>';
				print '<td align="center">'.(!empty($operation->on_time) ? dolGetBadge($langs->trans('VehiculeOperationOnTime'), '', 'danger') : '').'</td>';
				if (isModEnabled('operationorder')) {
					print '<td align="center">';
					if (!empty($operation->or_next) && class_exists('OperationOrder')) {
						$operationorder = new OperationOrder($object->db);
						if ($operationorder->fetch($operation->or_next, false) > 0) {
							print $operationorder->getNomUrl(0);
						}
					}
					print '</td>';
				}
				print '<td align="center">';
				print '<a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&action=editOperation&ope_id='.$operation->id.'&token='.newToken().'">'.img_edit().'</a>';
				print '<a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&action=delOperation&ope_id='.$operation->id.'&token='.newToken().'">'.img_delete().'</a>';
				print '</td>';
			}
			print '</tr>';
		}
	}

	if (GETPOST('action', 'alpha') !== 'editOperation'
		&& GETPOST('action', 'alpha') !== 'delOperation') {
		// New operation line
		print '<tr>';
		print '<td align="center">';
		print $form->select_produits(GETPOST('productid'), 'productid', 1, 0, 0, 1, 2, '', 0);
		print '</td>';
		print '<td align="center">';
		print '<input class="quatrevingtpercent" type="number" name="km" id="km" step="1" value="'.GETPOST('km').'">';
		print '</td>';
		print '<td align="center">';
		print '<input class="soixantepercent" type="number" name="delay" id="delay" step="1" value="'.GETPOST('delay').'">&nbsp;'.$langs->trans('Months');
		print '</td>';
		print '<td align="center">';
		$date_done = dol_mktime(0, 0, 0,
			GETPOST('date_donemonth', 'int'),
			GETPOST('date_doneday', 'int'),
			GETPOST('date_doneyear', 'int'));
		print $form->selectDate($date_done, 'date_done');
		print '</td>';
		print '<td align="center"><input class="quatrevingtpercent" type="number" name="km_done" id="km_done" step="1" value="'.GETPOST('km_done', 'int').'"></td>';
		print '<td align="center" colspan="'.(isModEnabled('operationorder') ? 4 : 3).'">';
		print '<input class="button quatrevingtpercent" type="submit" name="addOperation" value="'.$langs->trans("Add").'">';
		print '</td>';
		print '</tr>';
	}

	print '</table>';
	print '</form>';

	// Clone operations button
	if ($user->hasRight('workshop', 'vehicule', 'write')) {
		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&action=cloneOperations&token='.newToken().'">'.$langs->trans("CloneOperationsFromVehicle").'</a>';
		print '</div>';
	}

	?>
	<script>
		$("#search_productid").removeClass("minwidth100");
		$("#search_productid").addClass("quatrevingtpercent");
	</script>
	<?php
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

	if (!isModEnabled('operationorder')) {
		return 0;
	}

	$sql = 'SELECT COUNT(o.rowid) as nb FROM '.$db->prefix().'operationorder as o ';
	$sql .= ' WHERE o.fk_vehicule = '.(int) $idvehicle;
	if ($checkEntity) {
		$sql .= ' AND o.entity IN ('.getEntity('operationorder').')';
	}
	$resql = $db->query($sql);

	if ($resql) {
		$obj = $db->fetch_object($resql);
		return (int) $obj->nb;
	}

	return -1;
}
