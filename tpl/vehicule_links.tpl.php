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
 * \file    tpl/vehicule_links.tpl.php
 * \ingroup workshop
 * \brief   Template : tableau des véhicules liés (motrice/remorque)
 *
 * Variables attendues dans le scope appelant :
 *   $object   Vehicule
 *   $form     Form
 *   $db       DoliDB
 */

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

// Construire la liste de véhicules linkables (type complémentaire, même tiers)
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
$linkableVehicules = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$linkableVehicules[$obj->rowid] = $obj->label.' - '.$obj->immatriculation;
	}
}

print '<tr>';
print '<td align="center">'.$form->selectarray('linkVehicule_id', $linkableVehicules, GETPOST('linkVehicule_id'), 1, 0, 0, '', 0, 0, 0, '', '', 1).'</td>';
print '<td align="center">'.$form->selectDate('', 'linkDate_start').'</td>';
print '<td align="center">'.$form->selectDate('', 'linkDate_end').'</td>';
print '<td align="center"><input class="button" type="submit" name="linkVehicule" value="'.$langs->trans("Add").'"></td>';
print '</tr>';

print '</table>';
print '</form>';
