<?php
/* Copyright (C) 2020 ATM Consulting <support@atm-consulting.fr>
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
 * 	\file		admin/operationorder.php
 * 	\ingroup	operationorder
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include '../../main.inc.php'; // From htdocs directory
if (! $res) {
	$res = @include '../../../main.inc.php'; // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formsetup.class.php';
require_once '../lib/operationorder.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');
dol_include_once('operationorder/class/operationorder.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';

// Translations
$langs->loadLangs(array('operationorder@operationorder', 'admin', 'other','accountancy'));

// Access control
if (! $user->admin && !$user->hasRight("operationorder", "status", "write")) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');
$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'operationorder';

/*
 * Actions
 */


if (preg_match('/set_(.*)/', $action, $reg)) {
	$code=$reg[1];

	$theValue = GETPOST($code);
	if (is_array($theValue)) {
		$theValue = implode(',', $theValue);
	}

	if (dolibarr_set_const($db, $code, $theValue, 'chaine', 0, '', $conf->entity) > 0) {
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

if ($action == 'updateMask') {
	$maskconstorder = GETPOST('maskconstOperationOrder', 'alpha');
	$maskorder = GETPOST('maskOperationOrder', 'alpha');

	if ($maskconstorder) $res = dolibarr_set_const($db, $maskconstorder, $maskorder, 'chaine', 0, '', $conf->entity);

	if (!$res > 0) $error++;

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
} elseif ($action == 'specimen') {
	$modele = GETPOST('module', 'alpha');

	$operationorder = new OperationOrder($db);
	$operationorder->initAsSpecimen();

	// Search template files
	$file = ''; $classname = ''; $filefound = 0;
	$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir."core/modules/operationorder/doc/pdf_".$modele.".modules.php", 0);
		if (file_exists($file)) {
			$filefound = 1;
			$classname = "pdf_".$modele;
			break;
		}
	}

	if ($filefound) {
		require_once $file;

		$module = new $classname($db);

		if ($module->write_file($operationorder, $langs) > 0) {
			header("Location: ".DOL_URL_ROOT."/document.php?modulepart=operationorder&file=SPECIMEN.pdf");
			return;
		} else {
			setEventMessages($module->error, null, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	} else {
		setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
}

if ($action == 'set') {
	$ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		if (getDolGlobalString("OPERATIONORDER_ADDON_PDF")  == "$value") dolibarr_del_const($db, 'OPERATIONORDER_ADDON_PDF', $conf->entity);
	}
}
// Set default model
elseif ($action == 'setdoc') {
	if (dolibarr_set_const($db, "OPERATIONORDER_ADDON_PDF", $value, 'chaine', 0, '', $conf->entity)) {
		// La constante qui a ete lue en avant du nouveau set
		// on passe donc par une variable pour avoir un affichage coherent
		$conf->global->OPERATIONORDER_ADDON_PDF = $value;
	}

	// On active le modele
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		$ret = addDocumentModel($value, $type, $label, $scandir);
	}
} elseif ($action == 'setmod') {
	// TODO Verifier si module numerotation choisi peut etre active
	// par appel methode canBeActivated

	dolibarr_set_const($db, "OPERATIONORDER_ADDON", $value, 'chaine', 0, '', $conf->entity);
}
/*
 * View
 */

$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

$page_name = "OperationOrderSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
	. $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = operationorderAdminPrepareHead();
print dol_get_fiche_head(
	$head,
	'settings',
	$langs->trans("Module104088Name"),
	-1,
	"operationorder@operationorder"
);

// Setup page goes here
$form=new Form($db);
$var=false;

$sql = "SELECT code, label ";
$sql.= "FROM llx_operationorder_status ";
$sql.= "WHERE status = 1 " ;
$sql.= "AND entity = " . $conf->entity;

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$TOR[$obj->code] = $obj->label;
	}
}


if (!function_exists('setup_print_title')) {
	print '<div class="error" >'.$langs->trans('AbricotNeedUpdate').' : <a href="http://wiki.atm-consulting.fr/index.php/Accueil#Abricot" target="_blank"><i class="fa fa-info"></i> Wiki</a></div>';
	exit;
}

print '<table class="noborder" width="100%">';

setup_print_title("Parameters");

setup_print_on_off('OPODER_SUPPLIER_ORDER_LIMITED_TO_SERVICE');


$confKey = 'OPERATIONORDER_ORDERABLE_STATUS';
$customInputHtml = $form->multiselectarray($confKey, $TOR, explode(',', getDolGlobalString($confKey)));
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);


setup_print_on_off('OPODER_SUPPLIER_ORDER_AUTO_VALIDATE');

$confKey = 'OPERATION_ORDER_DEPAN_PRODUCT';
setup_print_input_form_part(
	$confKey,
	$langs->trans($confKey),
	'',
	array(),
	$form->select_produits(
		getDolGlobalString($confKey),
		$confKey,
		1,
		0,
		0,
		1,
		2,
		'',
		0,
		array(),
		0,
		'1',
		0,
		'',
		0,
		'',
		null,
		1
	)
);

$confKey = 'OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING';
setup_print_input_form_part($confKey, $langs->trans('OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING'), '', array(), $form->select_dolgroups(getDolGlobalString("OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING"), 'OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING', 1));

setup_print_on_off('OPODER_DISPLAY_STOCK_ON_PLANNING', false, '', 'OPODER_DISPLAY_STOCK_ON_PLANNING_help');

setup_print_on_off('OPODER_CANT_EXCEED_SENT_QTY', false, '', 'OPODER_CANT_EXCEED_SENT_QTY_help');

setup_print_on_off('OPODER_ADD_PRODUCT_IN_OR_IF_MISSING');
setup_print_on_off('OORDER_HIDE_TIME_SPENT_IF_CHILD');
setup_print_on_off('OORDER_HIDE_TIME_PLANNED_IF_CHILD');

$formother = new FormOther($db);
$confKey = 'OPERATION_ORDER_PERCENTAGECAPACITY_ALERTPLANNINGOR';
$customInputHtml = $formother->select_percent(getDolGlobalString("OPERATION_ORDER_PERCENTAGECAPACITY_ALERTPLANNINGOR"), 'OPERATION_ORDER_PERCENTAGECAPACITY_ALERTPLANNINGOR');
setup_print_input_form_part($confKey, $langs->trans('OPERATION_ORDER_PERCENTAGECAPACITY_ALERTPLANNINGOR'), '', array(), $customInputHtml);

$confKey = 'OPERATIONORDER_COEF_REFACT_PRESTA_EXT';
setup_print_input_form_part($confKey, $langs->trans($confKey));

setup_print_title("Planning");

setup_print_input_form_part('OPERATIONORDER_PLANNING_REFRESH_RATE', $langs->trans('OPERATIONORDER_PLANNING_REFRESH_RATE'));

setup_print_title("OpererationOrderJobSettings");

$confKey = 'OPERATIONORDER_AUTO_CLOSE_POINTAGE_TIME';
setup_print_input_form_part($confKey, $langs->trans($confKey));

$confKey = 'OPERATIONORDER_AUTO_CLOSE_EMAIL';
setup_print_input_form_part($confKey, $langs->trans($confKey));

$confKey = 'OPERATIONORDER_AUTO_RESCHEDUL_OR_STATUS';
$customInputHtml = $form->multiselectarray($confKey, $TOR, explode(',', getDolGlobalString($confKey)));
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

setup_print_title("LeftMenuOperationOrderORPlanning");

if (empty(getDolGlobalString("OR_ACTIVITYPLANNING_IMPROD_COLOR"))) dolibarr_set_const($db, 'OR_ACTIVITYPLANNING_IMPROD_COLOR', '4f93d6');
if (empty(getDolGlobalString("OR_ACTIVITYPLANNING_INTIME_COLOR"))) dolibarr_set_const($db, 'OR_ACTIVITYPLANNING_INTIME_COLOR', '008000');
if (empty(getDolGlobalString("OR_ACTIVITYPLANNING_LATE_COLOR"))) dolibarr_set_const($db, 'OR_ACTIVITYPLANNING_LATE_COLOR', 'ff0000');
if (empty(getDolGlobalString("OR_ACTIVITYPLANNING_INPROGRESS_COLOR"))) dolibarr_set_const($db, 'OR_ACTIVITYPLANNING_INPROGRESS_COLOR', 'ff00ff');

$formother = new FormOther($db);
$confKey = 'OR_ACTIVITYPLANNING_IMPROD_COLOR';
$customInputHtml = $formother->selectColor(getDolGlobalString($confKey), $confKey, $confKey);
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

$formother = new FormOther($db);
$confKey = 'OR_ACTIVITYPLANNING_INTIME_COLOR';
$customInputHtml = $formother->selectColor(getDolGlobalString($confKey), $confKey, $confKey);
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

$formother = new FormOther($db);
$confKey = 'OR_ACTIVITYPLANNING_LATE_COLOR';
$customInputHtml = $formother->selectColor(getDolGlobalString($confKey), $confKey, $confKey);
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

$formother = new FormOther($db);
$confKey = 'OR_ACTIVITYPLANNING_INPROGRESS_COLOR';
$customInputHtml = $formother->selectColor(getDolGlobalString($confKey), $confKey, $confKey);
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

$sql = "SELECT DISTINCT label,code ";
$sql.= "FROM llx_operationorderbarcode";


$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$IMPROR[$obj->label] = $obj->label;
	}
}

setup_print_title("workedtimeformsetup");

$formother = new Form($db);
$confKey = 'OPERATIONORDER_EXCLUDE_IMPRO';
$customInputHtml =$formother->multiselectarray($confKey, $IMPROR, explode(',', getDolGlobalString($confKey)));
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

setup_print_title("efficiencyformsetup");

$formother = new Form($db);
$confKey = 'OPERATIONORDER_EXCLUDE_EFF';
$customInputHtml =$formother->multiselectarray($confKey, $IMPROR, explode(',', getDolGlobalString($confKey)));
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

setup_print_title("checkorsetup");
$formother = new Form($db);
$confKey = 'OPERATIONORDER_STATUT_BEFORE_CHECK';
$customInputHtml =$formother->selectArray($confKey, $TOR, getDolGlobalString($confKey));
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

$formother = new Form($db);
$confKey = 'OPERATIONORDER_STATUT_AFTER_CHECK';
$customInputHtml =$formother->selectArray($confKey, $TOR, getDolGlobalString($confKey));
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

$confKey = 'OPERATIONORDER_MO_COEF_MIN';
setup_print_input_form_part($confKey, $langs->trans($confKey));
$confKey = 'OPERATIONORDER_MO_COEF_MAX';
setup_print_input_form_part($confKey, $langs->trans($confKey));
$confKey = 'OPERATIONORDER_PT_COEF_MIN';
setup_print_input_form_part($confKey, $langs->trans($confKey));
$confKey = 'OPERATIONORDER_PT_COEF_MAX';
setup_print_input_form_part($confKey, $langs->trans($confKey));


dol_include_once('operationorder/class/operationorderstatus.class.php');
$confKey = 'OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER';
if (class_exists('OperationOrderStatus')) {
	$customInputHtml = OperationOrderStatus::formSelectStatus($confKey, explode(',', getDolGlobalString($confKey)), 0, true, 0, array('entity' => $conf->entity));
	setup_print_input_form_part($confKey, $langs->trans('DoSearchOpenedOperationOrderOnThisStatus'), '', array(), $customInputHtml);
} else {
	setup_print_input_form_part($confKey, $langs->trans('DoSearchOpenedOperationOrderOnThisStatus'));
}

if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
	setup_print_title("PricePolicies");
	setup_print_input_form_part('OPERATIONORDER_PERCENT_PRICE_UP_PMP', $langs->trans('OPERATIONORDER_PERCENT_PRICE_UP_PMP'));
}

setup_print_title("invoiceORStatus");
$formother = new Form($db);
$confKey = 'OPERATIONORDER_STATUT_FOR_INVOICE';
$customInputHtml =$formother->selectArray($confKey, $TOR, getDolGlobalString($confKey));
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

$formother = new Form($db);
$confKey = 'OPERATIONORDER_STATUT_AFTER_INVOICE';
$customInputHtml =$formother->selectArray($confKey, $TOR, getDolGlobalString($confKey));
setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);


setup_print_title("AccountancyExport");
$formother = new Form($db);
$confKey = 'OPERATIONORDER_CODE_JOUNAL_VT';
setup_print_input_form_part($confKey, $langs->trans($confKey));

if (!isModEnabled('accounting')) {
	$confKey = 'ACCOUNTING_ACCOUNT_CUSTOMER';
	setup_print_input_form_part($confKey, $langs->trans($confKey));
	$confKey = 'ACCOUNTING_ACCOUNT_CUSTOMER_INTRAGP';
	setup_print_input_form_part($confKey, $langs->trans($confKey));
	//  $confKey = 'ACCOUNTING_ACCOUNT_SUPPLIER';
	//  setup_print_input_form_part($confKey, $langs->trans($confKey));

	$confKey = 'ACCOUNTING_PRODUCT_SOLD_ACCOUNT';
	setup_print_input_form_part($confKey, $langs->trans($confKey));
	$confKey = 'ACCOUNTING_PRODUCT_SOLD_INTRA_ACCOUNT';
	setup_print_input_form_part($confKey, $langs->trans($confKey));
	$confKey = 'ACCOUNTING_PRODUCT_SOLD_EXPORT_ACCOUNT';
	setup_print_input_form_part($confKey, $langs->trans($confKey));

	//  $confKey = 'ACCOUNTING_PRODUCT_BUY_ACCOUNT';
	//  setup_print_input_form_part($confKey, $langs->trans($confKey));
	//  $confKey = 'ACCOUNTING_PRODUCT_BUY_INTRA_ACCOUNT';
	//  setup_print_input_form_part($confKey, $langs->trans($confKey));
	//  $confKey = 'ACCOUNTING_PRODUCT_BUY_EXPORT_ACCOUNT';
	//  setup_print_input_form_part($confKey, $langs->trans($confKey));

	$confKey = 'ACCOUNTING_SERVICE_SOLD_ACCOUNT';
	setup_print_input_form_part($confKey, $langs->trans($confKey));
	$confKey = 'ACCOUNTING_SERVICE_SOLD_INTRA_ACCOUNT';
	setup_print_input_form_part($confKey, $langs->trans($confKey));
	$confKey = 'ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT';
	setup_print_input_form_part($confKey, $langs->trans($confKey));

	//  $confKey = 'ACCOUNTING_SERVICE_BUY_ACCOUNT';
	//  setup_print_input_form_part($confKey, $langs->trans($confKey));
	//  $confKey = 'ACCOUNTING_SERVICE_BUY_INTRA_ACCOUNT';
	//  setup_print_input_form_part($confKey, $langs->trans($confKey));
	//  $confKey = 'ACCOUNTING_SERVICE_BUY_EXPORT_ACCOUNT';
	//  setup_print_input_form_part($confKey, $langs->trans($confKey));

	$confKey = 'ACCOUNTING_VAT_SOLD_ACCOUNT';
	setup_print_input_form_part($confKey, $langs->trans($confKey));

	//  $confKey = 'ACCOUNTING_VAT_BUY_ACCOUNT';
	//  setup_print_input_form_part($confKey, $langs->trans($confKey));
}
print '</table>';



print load_fiche_titre($langs->trans("OrdersNumberingModules"), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Name").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td class="nowrap">'.$langs->trans("Example").'</td>';
print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
print '<td class="center" width="16">'.$langs->trans("ShortInfo").'</td>';
print '</tr>'."\n";

clearstatcache();

foreach ($dirmodels as $reldir) {
	$dir = dol_buildpath($reldir."core/modules/operationorder/");
	if (is_dir($dir)) {
		$handle = opendir($dir);
		if (is_resource($handle)) {
			while (($file = readdir($handle)) !== false) {
				if (substr($file, 0, 19) == 'mod_operationorder_' && substr($file, dol_strlen($file) - 3, 3) == 'php') {
					$file = substr($file, 0, dol_strlen($file) - 4);

					require_once $dir.$file.'.php';

					$module = new $file($db);

					// Show modules according to features level
					if ($module->version == 'development' && getDolGlobalInt("MAIN_FEATURES_LEVEL")  < 2) continue;
					if ($module->version == 'experimental' && getDolGlobalInt("MAIN_FEATURES_LEVEL")  < 1) continue;

					if ($module->isEnabled()) {
						print '<tr class="oddeven"><td>'.$module->name."</td><td>\n";
						print $module->info();
						print '</td>';

						// Show example of numbering model
						print '<td class="nowrap">';
						$tmp = $module->getExample();
						if (preg_match('/^Error/', $tmp)) print '<div class="error">'.$langs->trans($tmp).'</div>';
						elseif ($tmp == 'NotConfigured') print $langs->trans($tmp);
						else print $tmp;
						print '</td>'."\n";

						print '<td class="center">';
						if (getDolGlobalString("OPERATIONORDER_ADDON")  == $file) {
							print img_picto($langs->trans("Activated"), 'switch_on');
						} else {
							print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&amp;value='.$file.'&token='.newToken().'">';
							print img_picto($langs->trans("Disabled"), 'switch_off');
							print '</a>';
						}
						print '</td>';

						$operationorder = new OperationOrder($db);
						$operationorder->initAsSpecimen();

						// Info
						$htmltooltip = '';
						$htmltooltip .= ''.$langs->trans("Version").': <b>'.$module->getVersion().'</b><br>';
						$operationorder->type = 0;
						$nextval = $module->getNextValue($operationorder);
						if ("$nextval" != $langs->trans("NotAvailable")) {  // Keep " on nextval
							$htmltooltip .= ''.$langs->trans("NextValue").': ';
							if ($nextval) {
								if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured')
									$nextval = $langs->trans($nextval);
								$htmltooltip .= $nextval.'<br>';
							} else {
								$htmltooltip .= $langs->trans($module->error).'<br>';
							}
						}

						print '<td class="center">';
						print $form->textwithpicto('', $htmltooltip, 1, 0);
						print '</td>';

						print "</tr>\n";
					}
				}
			}
			closedir($handle);
		}
	}
}
print "</table><br>\n";


/*
 * Document templates generators
 */

print load_fiche_titre($langs->trans("OrdersModelModule"), '', '');

// Load array def with activated templates
$def = array();
$sql = "SELECT nom";
$sql .= " FROM ".$db->prefix()."document_model";
$sql .= " WHERE type = '".$type."'";
$sql .= " AND entity = ".$conf->entity;
$resql = $db->query($sql);
if ($resql) {
	$i = 0;
	$num_rows = $db->num_rows($resql);
	while ($i < $num_rows) {
		$array = $db->fetch_array($resql);
		array_push($def, $array[0]);
		$i++;
	}
} else {
	dol_print_error($db);
}

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Name").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
print '<td class="center" width="60">'.$langs->trans("Default").'</td>';
print '<td class="center" width="32">'.$langs->trans("ShortInfo").'</td>';
print '<td class="center" width="32">'.$langs->trans("Preview").'</td>';
print "</tr>\n";

clearstatcache();
$dirmodels=array_merge(array('/'), (array) $conf->modules_parts['models']);
$activatedModels = array();

foreach ($dirmodels as $reldir) {
	foreach (array('','/doc') as $valdir) {
		$dir = dol_buildpath($reldir."core/modules/operationorder".$valdir);

		if (is_dir($dir)) {
			$handle=opendir($dir);
			if (is_resource($handle)) {
				while (($file = readdir($handle))!==false) {
					$filelist[]=$file;
				}
				closedir($handle);
				arsort($filelist);

				foreach ($filelist as $file) {
					if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file)) {
						if (file_exists($dir.'/'.$file)) {
							$name = substr($file, 4, dol_strlen($file) -16);
							$classname = substr($file, 0, dol_strlen($file) -12);
							require_once $dir.'/'.$file;
							$module = new $classname($db);

							$modulequalified=1;
							if ($module->version == 'development'  && getDolGlobalInt("MAIN_FEATURES_LEVEL")  < 2) $modulequalified=0;
							if ($module->version == 'experimental' && getDolGlobalInt("MAIN_FEATURES_LEVEL")  < 1) $modulequalified=0;

							if ($modulequalified) {
								print '<tr class="oddeven"><td width="100">';
								print (empty($module->name)?$name:$module->name);
								print "</td><td>\n";
								if (method_exists($module, 'info')) print $module->info($langs);
								else print $module->description;
								print '</td>';

								// Active
								if (in_array($name, $def)) {
									print '<td class="center">'."\n";
									print '<a href="'.$_SERVER["PHP_SELF"].'?action=del&value='.$name.'">';
									print img_picto($langs->trans("Enabled"), 'switch_on');
									print '</a>';
									print '</td>';
								} else {
									print '<td class="center">'."\n";
									print '<a href="'.$_SERVER["PHP_SELF"].'?action=set&value='.$name.'&scan_dir='.$module->scandir.'&label='.urlencode($module->name).'&token='.newtoken().'">'.img_picto($langs->trans("SetAsDefault"), 'switch_off').'</a>';
									print "</td>";
								}

								// Defaut
								print '<td class="center">';
								if (getDolGlobalString("OPERATIONORDER_ADDON_PDF")  == $name) {
									print img_picto($langs->trans("Default"), 'on');
								} else {
									print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&value='.$name.'&scan_dir='.$module->scandir.'&label='.urlencode($module->name).'&token='.newtoken().'" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("SetAsDefault"), 'off').'</a>';
								}
								print '</td>';

								// Info
								$htmltooltip =    ''.$langs->trans("Name").': '.$module->name;
								$htmltooltip.='<br>'.$langs->trans("Type").': '.($module->type?$module->type:$langs->trans("Unknown"));
								if ($module->type == 'pdf') {
									$htmltooltip.='<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
								}
								$htmltooltip.='<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
								$htmltooltip.='<br>'.$langs->trans("Logo").': '.yn($module->option_logo, 1, 1);
								$htmltooltip.='<br>'.$langs->trans("MultiLanguage").': '.yn($module->option_multilang, 1, 1);
								$htmltooltip.='<br>'.$langs->trans("WatermarkOnDraftInvoices").': '.yn($module->option_draft_watermark, 1, 1);


								print '<td class="center">';
								print $form->textwithpicto('', $htmltooltip, 1, 0);
								print '</td>';

								// Preview
								print '<td class="center">';
								if ($module->type == 'pdf') {
									print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&module='.$name.'">'.img_object($langs->trans("Preview"), 'bill').'</a>';
								} else {
									print img_object($langs->trans("PreviewNotAvailable"), 'generic');
								}
								print '</td>';

								print "</tr>\n";
							}
						}
					}
				}
			}
		}
	}
}
print '</table>';
print "<br>";


print dol_get_fiche_end(-1);

llxFooter();

$db->close();
