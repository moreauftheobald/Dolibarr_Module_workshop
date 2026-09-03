<?php
/* Copyright (C) 2024 SuperAdmin <test@test.com>
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
 * \file    workshop/admin/setup_partage.php
 * \ingroup workshop
 * \brief   Workshop admin page - Partage entre entités tab.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
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
if (!$res) {
	die("Include of main fails");
}

global $langs, $user;

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
dol_include_once('/workshop/lib/workshop.lib.php');

$langs->loadLangs(array("admin", "workshop@workshop"));

$backtopage = GETPOST('backtopage', 'alpha');
$action     = GETPOST('action', 'aZ09');

if (!$user->admin) {
	accessforbidden();
}

// ---------------------------------------------------------------------------
// Element names used for multicompany sharing
// (follows the same pattern as DoliFleet multicompany_sharing.php)
// ---------------------------------------------------------------------------
// Vehicles
$elementVehicule    = 'vehicule';

// Operation Orders
$elementOR          = 'operationorder';

// Constant names for the sharing enable toggles (stored globally, entity=0)
$constVehiculeSharingEnabled = 'MULTICOMPANY_'.strtoupper($elementVehicule).'_SHARING_ENABLED';
$constORSharingEnabled       = 'MULTICOMPANY_'.strtoupper($elementOR).'_SHARING_ENABLED';

// ---------------------------------------------------------------------------
// Keep element-alias constants in sync so the multicompany module recognises
// both naming conventions (selectForForms vs selectForFormsList).
// ---------------------------------------------------------------------------
if (isModEnabled("multicompany") && !empty(getDolGlobalString("MULTICOMPANY_SHARINGS_ENABLED"))) {
	$vehSharingVal = getDolGlobalString($constVehiculeSharingEnabled);
	dolibarr_set_const($db, 'MULTICOMPANY_'.strtoupper($elementVehicule).'_SHARING_ENABLED', $vehSharingVal, 'chaine', 0, '', 0);

	$orSharingVal = getDolGlobalString($constORSharingEnabled);
	dolibarr_set_const($db, 'MULTICOMPANY_'.strtoupper($elementOR).'_SHARING_ENABLED', $orSharingVal, 'chaine', 0, '', 0);

	dolibarr_set_const($db, 'MULTICOMPANY_WORKSHOP_SHARING_ENABLED', (int)($vehSharingVal || $orSharingVal), 'chaine', 0, '', 0);

}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
if ($action == 'save_vehicule_sharing') {
	$shareData = GETPOST('multicompany-workshop-vehicule', 'array');

	$dao = new DaoMulticompany($db);
	$dao->getEntities();

	// First clear all existing sharings for this element
	if ($conf->entity == 1) {
		foreach ($dao->entities as $entity) {
			$entity->options['sharings'][$elementVehicule]    = array();
			$dao->options['sharings']['workshop'] = array();
			$entity->update($entity->id, $user);
		}
	} else {
		$dao->fetch($conf->entity);
		if ($dao->id > 0) {
			$dao->options['sharings'][$elementVehicule]    = array();
			$dao->options['sharings']['workshop'] = array();
			$dao->update($dao->id, $user);
		}
	}

	// Then set new sharings from POST
	if (!empty($shareData)) {
		foreach ($shareData as $entityId => $shared) {
			if (is_array($shared)) {
				$shared = array_map('intval', $shared);
				if ($dao->fetch($entityId) > 0) {
					$dao->options['sharings'][$elementVehicule]    = $shared;
					if ($dao->update($entityId, $user) < 1) {
						setEventMessages($langs->trans('Error'), null, 'errors');
					}
				}
			}
		}
	}

	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

if ($action == 'save_or_sharing' && getDolGlobalInt('WORKSHOP_USE_OR')) {
	$shareData = GETPOST('multicompany-workshop-or', 'array');

	$dao = new DaoMulticompany($db);
	$dao->getEntities();

	// Clear existing
	if ($conf->entity == 1) {
		foreach ($dao->entities as $entity) {
			$entity->options['sharings'][$elementOR]    = array();
			$dao->options['sharings']['workshop'] = array();
			$entity->update($entity->id, $user);
		}
	} else {
		$dao->fetch($conf->entity);
		if ($dao->id > 0) {
			$dao->options['sharings'][$elementOR]    = array();
			$dao->options['sharings']['workshop'] = array();
			$dao->update($dao->id, $user);
		}
	}

	// Set new sharings from POST
	if (!empty($shareData)) {
		foreach ($shareData as $entityId => $shared) {
			if (is_array($shared)) {
				$shared = array_map('intval', $shared);
				if ($dao->fetch($entityId) > 0) {
					$dao->options['sharings'][$elementOR]    = $shared;
					if ($dao->update($entityId, $user) < 1) {
						setEventMessages($langs->trans('Error'), null, 'errors');
					}
				}
			}
		}
	}

	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

if (in_array($action, ['save_vehicule_sharing','save_or_sharing'])) {

	$dao = new DaoMulticompany($db);
	$dao->getEntities();


	if (!empty($shareData)) {
		foreach ($shareData as $entityId => $shared) {
			$shared = array_map('intval', $shared);
			if ($dao->fetch($entityId) > 0) {
				$shared = array_unique(array_merge(
					(array) ($dao->options['sharings'][$elementOR] ?? array()),
					(array) ($dao->options['sharings'][$elementVehicule] ?? array())
				));
				$dao->options['sharings']['workshop'] = $shared;
				if ($dao->update($entityId, $user) < 1) {
					setEventMessages($langs->trans('Error'), null, 'errors');
				}
			}
		}
	}
}



// ---------------------------------------------------------------------------
// Extra assets needed for the multiselect widget (from multicompany module)
// ---------------------------------------------------------------------------
$extrajs  = array();
$extracss = array();
if (isModEnabled("multicompany") && !empty(getDolGlobalString("MULTICOMPANY_SHARINGS_ENABLED"))) {
	$extrajs  = array('/multicompany/inc/multiselect/js/ui.multiselect.js');
	$extracss = array('/multicompany/inc/multiselect/css/ui.multiselect.css');
}

// ---------------------------------------------------------------------------
// View
// ---------------------------------------------------------------------------
$help_url = '';
$title    = "WorkshopSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, $extrajs, $extracss, '', 'mod-workshop page-admin_partage');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = workshopAdminPrepareHead();
print dol_get_fiche_head($head, 'partage_entites', $langs->trans($title), -1, "workshop@workshop");

if (!isModEnabled("multicompany") || empty(getDolGlobalString("MULTICOMPANY_SHARINGS_ENABLED"))) {
	// Multicompany not enabled or sharing not activated
	print info_admin($langs->trans("WorkshopMulticompanySharingNotEnabled"), 0, 0, 1);
} else {
	$langs->loadLangs(array('languages', 'multicompany@multicompany'));

	// -----------------------------------------------------------------------
	// Section 1: Partage des véhicules (always visible)
	// -----------------------------------------------------------------------
	print '<div class="div-table-responsive-no-min">';
	print '<h3 class="liste_titre_bydiv">'.$langs->trans("WorkshopVehiculeSharing").'</h3>';

	// Enable / disable toggle
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Multicompany").'</td>';
	print '<td class="center"></td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("WorkshopActivateVehiculeSharing").'</td>';
	print '<td class="center">'.ajax_constantonoff($constVehiculeSharingEnabled, array(), 0,0,0,1).'</td>';
	print '</tr>';
	print '</table>';

	// Entity configuration table (only when sharing is enabled)
	if (!empty(getDolGlobalString($constVehiculeSharingEnabled))) {
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_vehicule_sharing">';
		if ($backtopage) {
			print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
		}

		print '<br>';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("MulticompanyConfiguration").'</td>';
		print '<td class="center">'.$langs->trans("ShareWith").'</td>';
		print '</tr>';

		$dao = new DaoMulticompany($db);
		$dao->getEntities();

		if (is_array($dao->entities)) {
			foreach ($dao->entities as $entity) {
				if (intval($conf->entity) === 1 || intval($conf->entity) === intval($entity->id)) {
					print '<tr class="oddeven">';
					print '<td>'.dol_htmlentities($entity->name).' <em>('.dol_htmlentities($entity->label).')</em></td>';
					print '<td class="center">'.workshopMultiselectEntities(
						'multicompany-workshop-vehicule['.$entity->id.']',
						$entity,
						'',
						$elementVehicule
					).'</td>';
					print '</tr>';
				}
			}

			print '<tr>';
			print '<td colspan="2" class="right">';
			print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans("Modify")).'">';
			print '</td>';
			print '</tr>';
		}

		print '</table>';
		print '</form>';
	}

	print '</div>';

	// -----------------------------------------------------------------------
	// Section 2: Partage des ordres de réparation
	// (only visible if WORKSHOP_USE_OR is active)
	// -----------------------------------------------------------------------
	if (getDolGlobalInt('WORKSHOP_USE_OR')) {
		print '<br>';
		print '<div class="div-table-responsive-no-min">';
		print '<h3 class="liste_titre_bydiv">'.$langs->trans("WorkshopORSharing").'</h3>';

		// Enable / disable toggle
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Multicompany").'</td>';
		print '<td class="center"></td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans("WorkshopActivateORSharing").'</td>';
		print '<td class="center">'.ajax_constantonoff($constORSharingEnabled, array(), 0,0,0,1).'</td>';
		print '</tr>';
		print '</table>';

		// Entity configuration table (only when OR sharing is enabled)
		if (!empty(getDolGlobalString($constORSharingEnabled))) {
			print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="save_or_sharing">';
			if ($backtopage) {
				print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
			}

			print '<br>';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans("MulticompanyConfiguration").'</td>';
			print '<td class="center">'.$langs->trans("ShareWith").'</td>';
			print '</tr>';

			$dao = new DaoMulticompany($db);
			$dao->getEntities();

			if (is_array($dao->entities)) {
				foreach ($dao->entities as $entity) {
					if (intval($conf->entity) === 1 || intval($conf->entity) === intval($entity->id)) {
						print '<tr class="oddeven">';
						print '<td>'.dol_htmlentities($entity->name).' <em>('.dol_htmlentities($entity->label).')</em></td>';
						print '<td class="center">'.workshopMultiselectEntities(
							'multicompany-workshop-or['.$entity->id.']',
							$entity,
							'',
							$elementOR
						).'</td>';
						print '</tr>';
					}
				}

				print '<tr>';
				print '<td colspan="2" class="right">';
				print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans("Modify")).'">';
				print '</td>';
				print '</tr>';
			}

			print '</table>';
			print '</form>';
		}

		print '</div>';
	}

	// Initialise the jQuery multiselect widget
	print '<script type="text/javascript">';
	print '$(document).ready(function () {';
	print '    $.extend($.ui.multiselect.locale, {';
	print '        addAll:\''.$langs->transnoentities("AddAll").'\',';
	print '        removeAll:\''.$langs->transnoentities("RemoveAll").'\',';
	print '        itemsCount:\''.$langs->transnoentities("ItemsCount").'\'';
	print '    });';
	print '    $(function(){';
	print '        $(".multiselect").multiselect({sortable: false, searchable: false});';
	print '    });';
	print '});';
	print '</script>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();

// ---------------------------------------------------------------------------
// Helper: render a multiselect list of entities for sharing configuration.
// Adapted from DoliFleet multicompany_sharing.php _multiselect_entities().
//
// @param string        $htmlname       HTML name/id for the <select>
// @param DaoMulticompany $current      Current entity being configured
// @param string        $option         Extra HTML attributes for <select>
// @param string        $sharingElement Element key to look up in sharings[]
// @return string
// ---------------------------------------------------------------------------
function workshopMultiselectEntities($htmlname, $current, $option = '', $sharingElement = '')
{
	global $conf, $langs, $db;

	$dao = new DaoMulticompany($db);
	$dao->getEntities();

	$sharingElement = !empty($sharingElement) ? $sharingElement : $htmlname;

	$return  = '<select id="'.dol_escape_htmltag($htmlname).'" class="multiselect" multiple="multiple"';
	$return .= ' name="'.dol_escape_htmltag($htmlname).'[]"';
	if ($option) {
		$return .= ' '.dol_escape_htmltag($option);
	}
	$return .= ' style="overflow: auto;">';

	if (is_array($dao->entities)) {
		$return .= '<option></option>';
		foreach ($dao->entities as $entity) {
			if (is_object($current) && $current->id != $entity->id && $entity->active == 1) {
				$return .= '<option value="'.(int) $entity->id.'"';
				if (
					isset($current->options['sharings'][$sharingElement])
					&& is_array($current->options['sharings'][$sharingElement])
					&& in_array($entity->id, $current->options['sharings'][$sharingElement])
				) {
					$return .= ' selected="selected"';
				}
				$return .= '>';
				$return .= dol_htmlentities($entity->label);
				if (empty($entity->visible)) {
					$return .= ' ('.$langs->trans('Hidden').')';
				}
				$return .= '</option>';
			}
		}
	}

	$return .= '</select>';

	return $return;
}
