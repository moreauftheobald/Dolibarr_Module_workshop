<?php
/* Copyright (C) 2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
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
 *    \file       thtimesheet_card.php
 *        \ingroup    theobald
 *        \brief      Page to create/edit/view thtimesheet
 */

//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');				// Do not create database handler $db
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');				// Do not load object $user
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');				// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');				// Do not load object $langs
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');		// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');		// Do not check injection attack on POST parameters
//if (! defined('NOCSRFCHECK'))              define('NOCSRFCHECK', '1');				// Do not check CSRF attack (test on referer + on token).
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');				// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');				// Do not check style html tag into posted data
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');				// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');				// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');       	  	// Do not load ajax.lib.php library
//if (! defined("NOLOGIN"))                  define("NOLOGIN", '1');					// If this page is public (can be called outside logged session). This include the NOIPCHECK too.
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');					// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined("MAIN_LANG_DEFAULT"))        define('MAIN_LANG_DEFAULT', 'auto');					// Force lang to a particular value
//if (! defined("MAIN_AUTHENTICATION_MODE")) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined("NOREDIRECTBYMAINTOLOGIN"))  define('NOREDIRECTBYMAINTOLOGIN', 1);		// The main.inc.php does not make a redirect if not logged, instead show simple error message
//if (! defined("FORCECSP"))                 define('FORCECSP', 'none');				// Disable all Content Security Policies
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');		// Force use of CSRF protection with tokens even for GET
//if (! defined('NOBROWSERNOTIF'))     		 define('NOBROWSERNOTIF', '1');				// Disable browser notification
//if (! defined('NOSESSION'))     		     define('NOSESSION', '1');				    // Disable session

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
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
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
dol_include_once('/operationorder/lib/operationorder.lib.php');

// Load translation files required by the page
$langs->load("operationorder@operationorder");

// Get parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$rowid = GETPOST('rowid', 'int');
$code = GETPOST('code', 'alpha');
$label = GETPOST('label', 'alpha');
$color = GETPOST('color', 'alpha');
$active = GETPOST('active', 'int');
$page = GETPOST('page', 'int');

if (!$user->hasRight('produit', 'read')) {
	accessforbidden();
}

if (empty(isModEnabled("operationorder"))) accessforbidden();

$hookmanager->initHooks(array('operationorderparam', 'globalcard')); // Note that conf->hooks_modules contains array


$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$error = 0;
	$errors=array();
	$msg ='';
	$sql ='';
	if ($confirm=='yes') {
		if (($action == 'confirmnew') || ($action == 'confirmedit' && !empty($rowid)) || ($action == 'confirmdelete' && !empty($rowid))) {
			if (($action == 'confirmnew') || ($action == 'confirmedit')) {
				if (empty($code)) {
					$error++;
					$errors[] = $langs->trans("MissingCode");
				}
				if (empty($label)) {
					$error++;
					$errors[] = $langs->trans("Missinglabel");
				}
				if (empty($color)) {
					$error++;
					$errors[] = $langs->trans("Missingcolor");
				}
			}
			if (empty($error)) {
				if ($action == 'confirmnew') {
					$sql = "INSERT INTO " . $db->prefix() . "c_operationorder_tag (entity,code, label, color, active, date_creation) VALUES (";
					$sql .= "'" . $conf->entity . "',";
					$sql .= "'" . $code . "',";
					$sql .= "'" . $label . "',";
					$sql .= "'" . $color . "',";
					$sql .= "'" . $active . "',";
					$sql .= "'" . $db->idate(dol_now()) . "')";
				} elseif ($action == 'confirmedit') {
					$sql = "UPDATE " . $db->prefix() . "c_operationorder_tag SET ";
					$sql .= "code = '" . $code . "', ";
					$sql .= "label = '" . $label . "', ";
					$sql .= "color = '" . $color . "', ";
					$sql .= "active = '" . $active . "' ";
					$sql .= "WHERE rowid = " . $rowid;
				} elseif ($action == 'confirmdelete') {
					$sql = "DELETE FROM " . $db->prefix() . "c_operationorder_tag WHERE rowid = " . $rowid;
				}
				$res = $db->query($sql);
				if (!$res) {
					$error++;
					if ($db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
						$errors[] = "ErrorRefAlreadyExists";
					} else {
						$errors[] = $db->lasterror();
					}
				}
			}
			if ($error > 0) {
				if ($action == 'confirmnew') {
					$msg='CreateErrors';
				} elseif ($action == 'confirmedit') {
					$msg='UpdateErrors';
				} elseif ($action == 'confirmdelete') {
					$msg='DeleteErrors';
				}
				setEventMessages($msg, $errors, 'errors');
			} else {
				if ($action == 'confirmnew') {
					$msg='CreateSucces';
				} elseif ($action == 'confirmedit') {
					$msg='UpdateSucces';
				} elseif ($action == 'confirmdelete') {
					$msg='DeleteSucces';
				}
				setEventMessage($msg);
				unset($action);
				unset($confirm);
				unset($cancel);
				unset($rowid);
				unset($code);
				unset($label);
				unset($color);
				unset($active);
			}
		}
	}
}

$ortagarray = array();
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	// If $page is not defined, or '' or -1 or if we click on clear filters
	$page = 0;
}
$limit = 25;
$offset = $limit * $page;

$sql  = "SELECT p.rowid as rowid, p.code as code, p.label as label, p.color as color, p.active as active ";
$sql .= "FROM ".$db->prefix()."c_operationorder_tag as p ";
$sql .= "WHERE p.entity IN (".getEntity('product').")";

$nbtotalofrecords = 0;
$resql = $db->query($sql);
if ($resql) {
	$nbtotalofrecords = $db->num_rows($resql);
	if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller than the paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
	$db->free($resql);
}

if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$num=0;
$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i = 0;
	while ($i < ($limit ? min($limit, $num) : $num)) {
		$obj = $db->fetch_object($resql);
		$ortag = new stdClass();
		$ortag->id = $obj->rowid;
		$ortag->code = $obj->code;
		$ortag->label = $obj->label;
		$ortag->color = $obj->color;
		$ortag->active = $obj->active;
		$ortagarray[$ortag->id] = $ortag;

		$i++;
	}
	$db->free($resql);
}

/*
 * View
 *
 * Put here all code to build page
 */

$form = new Form($db);
$product_static = new Product($db);

$title = $langs->trans('ParamOrSetupOrTag');
$help_url = '';
llxHeader('', $title, $help_url);

$head = OrSetupPrepareHead();
print dol_get_fiche_head($head, 'ortag', $langs->trans("ParamOrSetupOrTag"), -1, "fontawesome_fa-tools");
// Part to show record

$arraycolor = array(
	'#FFFFFF'=>'Blanc',
	'#C0C0C0'=>'Argent',
	'#808080'=>'Gris',
	'#000000'=>'Noir',
	'#FF0000'=>'Rouge',
	'#897943'=>'Marron',
	'#FFFF00'=>'Jaune',
	'#808000'=>'Olive',
	'#00FF00'=>'Lime',
	'#008000'=>'Vert',
	'#00FFFF'=>'Turquoise',
	'#008080'=>'Vert eau',
	'#0000FF'=>'Bleu',
	'#000080'=>'Bleu Marine',
	'#FF00FF'=>'Fuchsia',
	'#800080'=>'Violet'
);

$formconfirm = '';
if ($action=='delete' && !empty($rowid)) {
	$formquestion[] = array('type'=>'hidden','name'=>'rowid','value'=>$rowid);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('DeleteOrTag'), $langs->trans('DeleteOrTagQuestion'), 'confirmdelete', $formquestion, 'yes', 1);
} elseif ($action =='new') {
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('code'), 'name'=>'code','value'=> $code);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('ortaglabel'), 'name'=>'label','value'=>$label);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('color'), 'name'=>'color','values'=>$arraycolor, 'default'=>$color);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('active'), 'name'=>'active','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>empty($active)?'1':$active);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('NewOrTag'), '', 'confirmnew', $formquestion, 'yes', 1, 0, 700);
} elseif ($action =='edit' && !empty($rowid)) {
	$dataedit = $ortagarray[$rowid];
	$formquestion[] = array('type'=>'hidden','name'=>'rowid','value'=>$rowid);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('code'), 'name'=>'code','value'=> $dataedit->code);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('ortaglabel'), 'name'=>'label','value'=>$dataedit->label);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('color'), 'name'=>'color','values'=>$arraycolor, 'default'=>$dataedit->color);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('active'), 'name'=>'active','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>$dataedit->active);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('EditOrTag'), '', 'confirmedit', $formquestion, 'yes', 1, 0, 700);
}

// Call Hook formConfirm
$parameters = array('formConfirm' => $formconfirm);
$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
if (empty($reshook)) {
	$formconfirm .= $hookmanager->resPrint;
} elseif ($reshook > 0) {
	$formconfirm = $hookmanager->resPrint;
}

// Print form confirm
print $formconfirm;
$actionpathnew = dol_buildpath('/operationorder/param/or_setup_ortag.php', 3). '?action=new';
$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $actionpathnew, '', $user->hasRight('produit', 'creer'));
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], '', '', '', '', $num, $nbtotalofrecords, 'service', 0, $newcardbutton, '', $limit, 0, 0, 1);

print '<div class="fichecenter">';

print '<table class="border centpercent tableforfield liste">' . "\n";
print '<tr class="liste_titre">';
print '<th class="liste_titre">' . $langs->trans("code") . '</th>';
print '<th class="liste_titre">' . $langs->trans("ortaglabel") . '</th>';
print '<th class="liste_titre">' . $langs->trans("color") . '</th>';
print '<th class="liste_titre">' . $langs->trans("active") . '</th>';
print '<th class="liste_titre">' . $langs->trans("action") . '</th>';
print '</tr>';
foreach ($ortagarray as $key=>$data) {
	print '<tr class="oddeven">';
	print '<td>' . $data->code . '</td>';
	print '<td>' . $data->label . '</td>';
	print '<td>';
	$color = colorArrayToHex(colorStringToArray($data->color, array()), '');
	print '<input type="text" class="colorthumb" disabled="disabled" style="padding: 1px; margin-top: 0; margin-bottom: 0; background-color: #'.$color.'" value="'.$color.'">';
	print '</td>';
	if ($data->active == 1) {
		$out = 'switch_on';
	} else {
		$out = 'switch_off';
	}
	print '<td><span>' . img_picto($langs->trans('off'), $out) . '</span></td>';

	$actionpath = dol_buildpath('/operationorder/param/or_setup_ortag.php', 3) . '?rowid=' . $data->id . '&action=';
	$action  = '<a href="' . $actionpath . 'edit"><span class="fas fa-pen" title="' . $langs->trans('Edit') . '"></span></a>';
	if ($user->admin) {
		$action .= '&nbsp &nbsp';
		$action .= '<a href="' . $actionpath . 'delete&token='.newToken().'"><span class="fas fa-trash-alt" title="' . $langs->trans('Delete') . '"></span></a>';
	}
	print '<td>' . $action . '</td>';
	print '</tr>';
}

print '</table>';

print '</div>' . "\n";

// Buttons for actions
$parameters = array();
$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	print '<div class="tabsAction">' . "\n";

	print '</div>' . "\n";
}
// End of page
llxFooter();
$db->close();
