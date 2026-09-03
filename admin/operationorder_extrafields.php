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
 *      \file       admin/operationorder_extrafields.php
 *		\ingroup    workshop
 *		\brief      Page to setup extra fields of operationorder
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

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once '../lib/workshop.lib.php';

// Load translation files required by the page
$langs->loadLangs(array('workshop@workshop', 'admin'));

$extrafields = new ExtraFields($db);
$form = new Form($db);

// List of supported format
$type2label = ExtraFields::getListOfTypesLabels();

$action = GETPOST('action', 'aZ09');
$attrname = GETPOST('attrname', 'alpha');
$elementtype = 'workshop_operationorder'; //Must be the $table_element of the class that manage extrafield
$title = "WorkshopSetup";
$subtab = GETPOST('subtab', 'aZ09') ?: 'general';

if (!$user->admin) {
	accessforbidden();
}


/*
 * Actions
 */
if (getDolGlobalInt('WORKSHOP_USE_OR')) {
	require DOL_DOCUMENT_ROOT . '/core/actions_extrafields.inc.php';
}

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

/*
 * View
 */

$textobject = $langs->transnoentitiesnoconv("OperationOrder");

$help_url = '';
$page_name = "WorkshopSetup";

llxHeader('', $langs->trans("WorkshopSetup"), $help_url, '', 0, 0, '', '', '', 'mod-workshop page-admin_extrafields');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

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
if (getDolGlobalInt('WORKSHOP_USE_OR')) {
	$subhead = workshopORAdminPrepareHead();

	print dol_get_fiche_head($subhead, 'extrafields', '', -1, '');

	require DOL_DOCUMENT_ROOT . '/core/tpl/admin_extrafields_view.tpl.php';

// Buttons
	if ((float)DOL_VERSION < 17) {    // On v17+, the "New Attribute" button is included into tpl.
		if ($action != 'create' && $action != 'edit') {
			print '<div class="tabsAction">';
			print '<a class="butAction reposition" href="' . $_SERVER["PHP_SELF"] . '?action=create">' . $langs->trans("NewAttribute") . '</a>';
			print "</div>";
		}
	}


	/*
	 * Creation of an optional field
	 */
	if ($action == 'create') {
		print '<br><div id="newattrib"></div>';
		print load_fiche_titre($langs->trans('NewAttribute'));

		require DOL_DOCUMENT_ROOT . '/core/tpl/admin_extrafields_add.tpl.php';
	}

	/*
	 * Edition of an optional field
	 */
	if ($action == 'edit' && !empty($attrname)) {
		print "<br>";
		print load_fiche_titre($langs->trans("FieldEdition", $attrname));

		require DOL_DOCUMENT_ROOT . '/core/tpl/admin_extrafields_edit.tpl.php';
	}

	print dol_get_fiche_end();
}
print dol_get_fiche_end();


// End of page
llxFooter();
$db->close();
