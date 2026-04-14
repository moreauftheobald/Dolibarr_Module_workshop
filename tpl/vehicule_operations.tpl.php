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
 * \file    tpl/vehicule_operations.tpl.php
 * \ingroup workshop
 * \brief   Template : tableau des opérations de maintenance d'un véhicule
 *
 * Variables attendues dans le scope appelant :
 *   $object   Vehicule
 *   $form     Form
 *   $user     User
 *   $db       DoliDB
 */

// Charger la classe OR si le module est actif
if (isModEnabled('workshop')) {
	dol_include_once('/workshop/class/operationorder.class.php');
}

$currentAction = GETPOST('action', 'alpha');
$editOpeId     = GETPOSTINT('ope_id');
$showOrCol     = isModEnabled('workshop');
$nbCols        = $showOrCol ? 10 : 9;

if ($currentAction == 'editOperation') {
	$actionForm = 'updateOperation';
} else {
	$actionForm = 'addVehiculeOperation';
}

print '<form id="vehiculeOperationsForm" method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="'.$actionForm.'">';
print '<input type="hidden" name="id" value="'.$object->id.'">';
if (!empty($editOpeId)) {
	print '<input type="hidden" name="ope_id" value="'.$editOpeId.'">';
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
if ($showOrCol) {
	print '<td align="center">'.$langs->trans('VehiculeOperationNextOR').'</td>';
}
print '<td align="center"></td>';
print '</tr>';

$res = $object->getOperations();
if ($res < 0) {
	setEventMessages($object->error, null, 'errors');
}
if (empty($object->operations)) {
	print '<tr><td align="center" colspan="'.$nbCols.'">'.$langs->trans('NoOperation').'</td></tr>';
} else {
	foreach ($object->operations as $operation) {
		// Cellules communes (identiques en lecture et en édition)
		$onTimeTd = '<td align="center">'.(!empty($operation->on_time) ? dolGetBadge($langs->trans('VehiculeOperationOnTime'), '', 'danger') : '').'</td>';
		$orNextTd = '';
		if ($showOrCol) {
			$orNextTd = '<td align="center">';
			if (!empty($operation->or_next) && class_exists('Operationorder')) {
				$operationorder = new Operationorder($object->db);
				if ($operationorder->fetch($operation->or_next, false) > 0) {
					$orNextTd .= $operationorder->getNomUrl(0);
				}
			}
			$orNextTd .= '</td>';
		}

		print '<tr>';
		if ($currentAction == 'editOperation' && $operation->id == $editOpeId) {
			print '<td align="center">';
			$mainOpeArray = getMaintenanceOperationSelectArray($db, $object->fk_vehicule_type, $object->fk_vehicule_mark);
			print $form->selectarray('fk_maintenance_operation', $mainOpeArray, $operation->fk_maintenance_operation, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
			print '</td>';
			print '<td align="center"><input class="quatrevingtpercent" type="number" name="km" id="km" step="1" value="'.$operation->km.'"></td>';
			print '<td align="center"><input class="soixantepercent" type="number" name="delay" id="delay" step="1" value="'.$operation->delai_from_last_op.'">&nbsp;'.$langs->trans('Months').'</td>';
			print '<td align="center">'.$form->selectDate($operation->date_done, 'date_done').'</td>';
			print '<td align="center"><input class="quatrevingtpercent" type="number" name="km_done" id="km_done" step="1" value="'.$operation->km_done.'"></td>';
			print '<td align="center">'.$operation->date_next.'</td>';
			print '<td align="center">'.$operation->km_next.'</td>';
			print $onTimeTd;
			print $orNextTd;
			print '<td align="center"><input class="button quatrevingtpercent" type="submit" name="saveOperation" value="'.$langs->trans("Save").'"></td>';
		} else {
			print '<td align="left">'.$operation->getName().'</td>';
			print '<td align="center">'.(!empty($operation->km) ? price2num($operation->km) : '').'</td>';
			print '<td align="center">'.(!empty($operation->delai_from_last_op) ? $operation->delai_from_last_op.' '.$langs->trans('Months') : '').'</td>';
			print '<td align="center">'.(!empty($operation->date_done) ? dol_print_date($operation->date_done, "%d/%m/%Y") : '').'</td>';
			print '<td align="center">'.(!empty($operation->km_done) ? $operation->km_done : '').'</td>';
			print '<td align="center">'.(!empty($operation->date_next) ? dol_print_date($operation->date_next, "%d/%m/%Y") : '').'</td>';
			print '<td align="center">'.$operation->km_next.'</td>';
			print $onTimeTd;
			print $orNextTd;
			print '<td align="center">';
			print '<a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&action=editOperation&ope_id='.$operation->id.'&token='.newToken().'">'.img_edit().'</a>';
			print '<a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'&action=delOperation&ope_id='.$operation->id.'&token='.newToken().'">'.img_delete().'</a>';
			print '</td>';
		}
		print '</tr>';
	}
}

if ($currentAction !== 'editOperation' && $currentAction !== 'delOperation') {
	$date_done = dol_mktime(0, 0, 0, GETPOSTINT('date_donemonth'), GETPOSTINT('date_doneday'), GETPOSTINT('date_doneyear'));
	print '<tr>';
	print '<td align="center">';
	$mainOpeArray = getMaintenanceOperationSelectArray($db, $object->fk_vehicule_type, $object->fk_vehicule_mark);
	print $form->selectarray('fk_maintenance_operation', $mainOpeArray, GETPOSTINT('fk_maintenance_operation'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
	print '</td>';
	print '<td align="center"><input class="quatrevingtpercent" type="number" name="km" id="km" step="1" value="'.GETPOST('km').'"></td>';
	print '<td align="center"><input class="soixantepercent" type="number" name="delay" id="delay" step="1" value="'.GETPOST('delay').'">&nbsp;'.$langs->trans('Months').'</td>';
	print '<td align="center">'.$form->selectDate($date_done, 'date_done').'</td>';
	print '<td align="center"><input class="quatrevingtpercent" type="number" name="km_done" id="km_done" step="1" value="'.GETPOSTINT('km_done').'"></td>';
	print '<td align="center" colspan="'.($showOrCol ? 4 : 3).'"><input class="button quatrevingtpercent" type="submit" name="addOperation" value="'.$langs->trans("Add").'"></td>';
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
