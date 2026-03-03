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
 * \file    workshop/admin/setup_or.php
 * \ingroup workshop
 * \brief   Workshop admin page - Ordres de réparation tab.
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
$subtab     = GETPOST('subtab', 'aZ09') ?: 'general';
$error      = 0;

if (!$user->admin) {
	accessforbidden();
}

/*
 * Actions
 */

if ($action == 'update_use_or' && !empty($user->admin)) {
	// Save WORKSHOP_USE_OR
	$useOR = GETPOSTINT('WORKSHOP_USE_OR');
	$res = dolibarr_set_const($db, 'WORKSHOP_USE_OR', $useOR, 'chaine', 0, '', $conf->entity);
	if (!($res > 0)) {
		$error++;
	}
	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

if ($action == 'update_subtab' && !empty($user->admin)) {
	if ($subtab == 'general') {
		// Save WORKSHOP_MECHANIC_GROUP
		$mechanicGroupId = GETPOSTINT('WORKSHOP_MECHANIC_GROUP');
		$res = dolibarr_set_const($db, 'WORKSHOP_MECHANIC_GROUP', $mechanicGroupId, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
		if (!$error) {
			setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	}
}

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title    = "WorkshopSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-workshop page-admin_or');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = workshopAdminPrepareHead();
print dol_get_fiche_head($head, 'ordres_reparation', $langs->trans($title), -1, "workshop@workshop");

// --- Section 1: activation des Ordres de réparation (toujours visible) ---
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update_use_or">';
print '<input type="hidden" name="subtab" value="'.dol_escape_htmltag($subtab).'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>';
print $form->textwithpicto(
	$langs->trans('WORKSHOP_USE_OR'),
	$langs->trans('WORKSHOP_USE_ORTooltip')
);
print '</td>';
print '<td>';
$useOR = getDolGlobalInt('WORKSHOP_USE_OR');
print $form->selectyesno('WORKSHOP_USE_OR', $useOR, 1);
print '</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// --- Section 2: sous-onglets (uniquement si WORKSHOP_USE_OR est actif) ---
if (getDolGlobalInt('WORKSHOP_USE_OR')) {
	// Build sub-tabs
	$subhead = array();
	$sh = 0;
	$subhead[$sh][0] = $_SERVER["PHP_SELF"].'?subtab=general';
	$subhead[$sh][1] = $langs->trans('WorkshopORSubTabGeneral');
	$subhead[$sh][2] = 'general';
	$sh++;

	print dol_get_fiche_head($subhead, $subtab, '', 0, '');

	if ($subtab == 'general') {
		// Build mechanic group select
		$sqlGrp = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."usergroup WHERE entity IN (0, ".((int) $conf->entity).") ORDER BY nom";
		$resGrp  = $db->query($sqlGrp);
		$grpOpts = '<option value="0">--- '.$langs->trans('None').' ---</option>';
		if ($resGrp) {
			while ($objGrp = $db->fetch_object($resGrp)) {
				$sel = (getDolGlobalInt('WORKSHOP_MECHANIC_GROUP') == $objGrp->rowid) ? ' selected="selected"' : '';
				$grpOpts .= '<option value="'.$objGrp->rowid.'"'.$sel.'>'.dol_htmlentities($objGrp->nom).'</option>';
			}
		}

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_subtab">';
		print '<input type="hidden" name="subtab" value="general">';

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Parameter").'</td>';
		print '<td>'.$langs->trans("Value").'</td>';
		print '</tr>';

		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto(
			$langs->trans('WorkshopMechanicGroup'),
			$langs->trans('WorkshopMechanicGroupHelp')
		);
		print '</td>';
		print '<td>';
		print '<select name="WORKSHOP_MECHANIC_GROUP" id="WORKSHOP_MECHANIC_GROUP" class="flat">'.$grpOpts.'</select>';
		print '</td>';
		print '</tr>';

		print '</table>';

		print '<div class="tabsAction">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
		print '</div>';

		print '</form>';
	}

	print dol_get_fiche_end();
}

print dol_get_fiche_end();

llxFooter();
$db->close();
