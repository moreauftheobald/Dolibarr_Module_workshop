<?php

$res=@include "../../main.inc.php";						// For root directory
if (! $res) $res=@include "../../../main.inc.php";			// For "custom" directory

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once '../lib/operationorder.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');

$langs->load("admin");
$langs->load('supplierorderfromorder@supplierorderfromorder');

global $db;

// Security check
if (! $user->admin) accessforbidden();

$action=GETPOST('action');
$id=GETPOST('id');

/*
 * Action
 */
if (preg_match('/set_(.*)/', $action, $reg)) {
	$code=$reg[1];

	$value = GETPOST($code);

	if ($code == 'SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER') {
		if (is_array($value)) {
			if (in_array(-1, $value) && count($value) > 1) {
				unset($value[array_search(-1, $value)]);
			}
			$TCategories = array_map('intval', $value);
		} elseif ($value > 0) {
			$TCategories = array(intval($value));
		} else {
			$TCategories = array(-1);
		}

		$value = serialize($TCategories);
	}

	if (dolibarr_set_const($db, $code, $value, 'chaine', 0, '', $conf->entity) > 0) {
		if ($code=='SOFO_USE_DELIVERY_TIME' && GETPOST($code) == 1) {
			dolibarr_set_const($db, 'FOURN_PRODUCT_AVAILABILITY', 1);
		}

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

$page_name = "SupplierOrderFromOrderForOr";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
	. $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = operationorderAdminPrepareHead();
print dol_get_fiche_head(
	$head,
	'supplierorderfromorderforor',
	$langs->trans("Module104088Name"),
	-1,
	"operationorder@operationorder"
);


if (!function_exists('setup_print_title')) {
	print '<div class="error" >'.$langs->trans('AbricotNeedUpdate').' : <a href="http://wiki.atm-consulting.fr/index.php/Accueil#Abricot" target="_blank"><i class="fa fa-info"></i> Wiki</a></div>';
	exit;
}

print '<table class="noborder" width="100%">';

setup_print_title("Parameters");

setup_print_on_off('SOFOR_CREATE_NEW_SUPPLIER_ODER_ANY_TIME');

print '</table>';

// Footer
llxFooter();
// Close database handler
$db->close();
