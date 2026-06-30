<?php
/*
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/dolifleet.php
 * 	\ingroup	dolifleet
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
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

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once '../lib/dolifleet.lib.php';

// Translations
$langs->loadLangs(array('dolifleet@dolifleet', 'admin', 'other'));

// Access control
if (! $user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');
$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/', $action, $reg)) {
	$code=$reg[1];
	if ($code == "DOLIFLEET_MOTRICE_TYPES") {
		if (dolibarr_set_const($db, $code, serialize(GETPOST($code)), 'chaine', 0, '', $conf->entity) > 0) {
			header("Location: ".$_SERVER["PHP_SELF"]);
			exit;
		} else {
			dol_print_error($db);
		}
	} elseif (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0) {
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/', $action, $reg)) {
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0) {
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		dol_print_error($db);
	}
}

/*
 * View
 */
$page_name = "doliFleetSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
	. $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = dolifleetAdminPrepareHead();
print dol_get_fiche_head(
	$head,
	'settings',
	$langs->trans("Module104087Name"),
	-1,
	"dolifleet@dolifleet"
);

// Setup page goes here
$form=new Form($db);
$var=false;
print '<table class="noborder" width="100%">';


print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>';
print '<td></td>';
print '<td></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('DOLIFLEET_MOTRICE_TYPES').'</td>';
print '<td></td>';
print '<td><form action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set_DOLIFLEET_MOTRICE_TYPES">';
dol_include_once('/dolifleet/class/dictionaryVehiculeType.class.php');
$dict = new dictionaryVehiculeType($db);
$TType = $dict->getAllActiveArray('label');
$tmpMotriceTypes = getDolGlobalString("DOLIFLEET_MOTRICE_TYPES");
$selectedMotriceTypes = !empty($tmpMotriceTypes) ? @unserialize($tmpMotriceTypes) : array();
if (!is_array($selectedMotriceTypes)) $selectedMotriceTypes = array();
print $form->multiselectarray('DOLIFLEET_MOTRICE_TYPES', $TType, $selectedMotriceTypes);
print '<input class="butAction" type="submit" value="'.$langs->trans('Save').'">';
print '</form></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('DOLIFLEET_DELAY_SEARCH_OPERATIONS').'</td>';
print '<td></td>';
print '<td><form action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set_DOLIFLEET_DELAY_SEARCH_OPERATIONS">';
print '<input type="text" name="DOLIFLEET_DELAY_SEARCH_OPERATIONS" value="'.getDolGlobalString("DOLIFLEET_DELAY_SEARCH_OPERATIONS").'" size="5">';
print '<input class="butAction" type="submit" value="'.$langs->trans('Save').'">';
print '</form></td>';
print '</tr>';

print '</table>';

print dol_get_fiche_end(-1);

llxFooter();

$db->close();
