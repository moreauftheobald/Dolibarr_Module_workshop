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
 *      \file       admin/vehicule_extrafields.php
 *		\ingroup    dolifleet
 *		\brief      Page to setup extra fields of dolifleet
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

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once '../lib/dolifleet.lib.php';
require_once '../class/vehicule.class.php';

$langs->loadLangs(array('dolifleet@dolifleet', 'admin'));

// Access control
if (!$user->admin) {
	accessforbidden();
}

$extrafields = new ExtraFields($db);
$form = new Form($db);

$dolifleet = new Vehicule($db);
$elementtype = $dolifleet->table_element;

// Parameters
$action = GETPOST('action', 'aZ09');
$attrname = GETPOST('attrname', 'alpha');
$type = GETPOST('type', 'alphanohtml');

/*
 * Actions
 */
require DOL_DOCUMENT_ROOT.'/core/actions_extrafields.inc.php';

/*
 * View
 */
$textobject = $langs->transnoentitiesnoconv('doliFleet');
$help_url = '';
$page_name = 'ExtraFields';

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
	. $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = dolifleetAdminPrepareHead();
print dol_get_fiche_head(
	$head,
	'extrafields',
	$langs->trans("Module104087Name"),
	-1,
	"dolifleet@dolifleet"
);

print load_fiche_titre($langs->trans("ExtraFields"), '', '');

// List of existing extrafields
require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_view.tpl.php';

// Buttons
if ($action != 'create' && $action != 'edit') {
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?action=create">'.$langs->trans("NewAttribute").'</a>';
	print '</div>';
}

// Create or edit extrafield
if ($action == 'create') {
	print load_fiche_titre($langs->trans("NewAttribute"));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_add.tpl.php';
}
if ($action == 'edit' && !empty($attrname)) {
	print load_fiche_titre($langs->trans("FieldEdition", $attrname));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_edit.tpl.php';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
