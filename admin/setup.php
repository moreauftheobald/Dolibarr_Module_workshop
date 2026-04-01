<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2024 SuperAdmin <test@test.com>
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
 * \file    workshop/admin/setup.php
 * \ingroup workshop
 * \brief   Workshop admin page - Véhicules tab.
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
require_once '../lib/workshop.lib.php';

$langs->loadLangs(array("admin", "workshop@workshop"));

$hookmanager->initHooks(array('workshopsetup', 'globalsetup'));

$action     = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$error      = 0;

if (!$user->admin) {
	accessforbidden();
}

/*
 * Actions
 */

if ($action == 'update' && !empty($user->admin)) {
	// Save WORKSHOP_TRACTOR_VEHICLE_TYPES (multi-select → comma-separated string)
	$tractorTypes = GETPOST('WORKSHOP_TRACTOR_VEHICLE_TYPES', 'array');
	$tractorTypesStr = '';
	if (is_array($tractorTypes) && !empty($tractorTypes)) {
		$tractorTypesStr = implode(',', array_map('intval', $tractorTypes));
	}
	$res = dolibarr_set_const($db, 'WORKSHOP_TRACTOR_VEHICLE_TYPES', $tractorTypesStr, 'chaine', 0, '', $conf->entity);
	if (!($res > 0)) {
		$error++;
	}

	// Save WORKSHOP_OPERATION_SEARCH_DELAY_MONTHS (integer)
	$delayMonths = GETPOSTINT('WORKSHOP_OPERATION_SEARCH_DELAY_MONTHS');
	if ($delayMonths < 0) {
		$delayMonths = 0;
	}
	$res = dolibarr_set_const($db, 'WORKSHOP_OPERATION_SEARCH_DELAY_MONTHS', $delayMonths, 'chaine', 0, '', $conf->entity);
	if (!($res > 0)) {
		$error++;
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title    = "WorkshopSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-workshop page-admin');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = workshopAdminPrepareHead();
print dol_get_fiche_head($head, 'vehicules', $langs->trans($title), -1, "workshop@workshop");

// Build vehicle types multi-select
$currentTractorTypes = array_filter(explode(',', getDolGlobalString('WORKSHOP_TRACTOR_VEHICLE_TYPES')));
$sqlTypes = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."workshop_vehicule_c_vehicule_type WHERE active = 1 ORDER BY label";
$resTypes = $db->query($sqlTypes);
$tractorTypesArray = array();
if ($resTypes) {
	while ($objType = $db->fetch_object($resTypes)) {
		$tractorTypesArray[(string) $objType->rowid] = dol_htmlentities($objType->label);
	}
}
$selectHtml = $form->multiselectarray('WORKSHOP_TRACTOR_VEHICLE_TYPES', $tractorTypesArray, $currentTractorTypes, 0, 0, 'flat minwidth250', 0, 0);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Row 1: Tractor vehicle types
print '<tr class="oddeven">';
print '<td>';
print $form->textwithpicto(
	$langs->trans('WorkshopTractorVehicleTypes'),
	$langs->trans('WorkshopTractorVehicleTypesHelp')
);
print '</td>';
print '<td>';
print $selectHtml;
print '</td>';
print '</tr>';

// Row 2: Operation search delay months
print '<tr class="oddeven">';
print '<td>';
print $form->textwithpicto(
	$langs->trans('WorkshopOperationSearchDelayMonths'),
	$langs->trans('WorkshopOperationSearchDelayMonthsHelp')
);
print '</td>';
print '<td>';
print '<input type="number" name="WORKSHOP_OPERATION_SEARCH_DELAY_MONTHS" id="WORKSHOP_OPERATION_SEARCH_DELAY_MONTHS" class="flat" min="0" style="width:80px;" value="'.dol_escape_htmltag((string) getDolGlobalInt('WORKSHOP_OPERATION_SEARCH_DELAY_MONTHS')).'">';
print '</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
