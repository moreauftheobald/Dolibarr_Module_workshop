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
 * \file    tpl/vehicule_activities.tpl.php
 * \ingroup workshop
 * \brief   Template : tableau des activités d'un véhicule (CRUD inline)
 *
 * Variables attendues dans le scope appelant :
 *   $object   Vehicule
 *   $form     Form
 *   $action   string   Action courante
 */

// Protection
if (!defined('NOTOKENRENEWAL')) {
	// This tpl is always included from a page that already defined the environment
}

$currentAction = GETPOST('action', 'alpha');
$editActId     = GETPOSTINT('act_id');

if ($currentAction == 'editActivity') {
	$actionForm = 'updateActivity';
} else {
	$actionForm = 'addActivity';
}

print '<form id="activityForm" method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="'.$actionForm.'">';
print '<input type="hidden" name="id" value="'.$object->id.'">';
if (!empty($editActId)) {
	print '<input type="hidden" name="act_id" value="'.$editActId.'">';
}

// Charger les types d'activité depuis le dictionnaire
$activityTypes = array();
$sqlTypes = "SELECT rowid, label FROM ".$db->prefix()."workshop_vehicule_c_vehicule_activity_type WHERE active = 1 ORDER BY label ASC";
$resTypes = $db->query($sqlTypes);
if ($resTypes) {
	while ($objType = $db->fetch_object($resTypes)) {
		$activityTypes[$objType->rowid] = $objType->label;
	}
	$db->free($resTypes);
}

print '<table class="border" width="100%">'."\n";
print '<tr class="liste_titre">';
print '<td align="center">'.$langs->trans('ActivityType').'</td>';
print '<td align="center">'.$langs->trans('DateStart').'</td>';
print '<td align="center">'.$langs->trans('DateEnd').'</td>';
print '<td align="center">'.$langs->trans('soc').'</td>';
print '<td></td>';
print '</tr>';

$ret = $object->getActivities('', '');
if ($ret == 0) {
	print '<tr><td align="center" colspan="5">'.$langs->trans('NoActivity').'</td></tr>';
} elseif ($ret > 0) {
	foreach ($object->activities as $activity) {
		if ($currentAction == 'editActivity' && $activity->id == $editActId) {
			print '<tr>';
			print '<td align="center">'.$form->selectarray('activity_type', $activityTypes, $activity->fk_type, 1).'</td>';
			print '<td align="center">'.$form->selectDate($activity->date_start, 'activityDate_start').'</td>';
			print '<td align="center">'.$form->selectDate($activity->date_end, 'activityDate_end').'</td>';
			print '<td align="center">'.$form->select_thirdparty_list($activity->fk_soc, 'socid', 's.client = 1', '', 0, 0, array(), '', 0, 0, '', 'style="width: 80%"').'</td>';
			print '<td align="center"><input class="button" type="submit" name="saveActivity" value="'.$langs->trans("Save").'"></td>';
			print '</tr>';
		} else {
			print '<tr>';
			print '<td align="center">'.($activity->fk_type > 0 ? dol_escape_htmltag($activity->getType()) : '').'</td>';
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

if ($currentAction !== 'editActivity' && $currentAction !== 'delActivity') {
	print '<tr id="newActivity">';
	print '<td align="center">'.$form->selectarray('activity_type', $activityTypes, GETPOSTINT('activity_type'), 1).'</td>';
	print '<td align="center">'.$form->selectDate('', 'activityDate_start').'</td>';
	print '<td align="center">'.$form->selectDate('', 'activityDate_end').'</td>';
	print '<td align="center">'.$object->showOutputField($object->fields['fk_soc'], 'fk_soc', $object->fk_soc).'</td>';
	print '<td align="center"><input class="button" type="submit" name="addActivity" value="'.$langs->trans("Add").'"></td>';
	print '</tr>';
}

print '</table>';
print '</form>';
?>
<script>
	$("#activityDate_start").addClass("quatrevingtpercent");
	$("#activityDate_end").addClass("quatrevingtpercent");
</script>
