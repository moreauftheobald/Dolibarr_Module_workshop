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
 *    	\file       vh_setup_typect.php
 *      \ingroup    dolifleet
 *      \brief      Page to create/edit/view vehicule Contract type
 */

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

dol_include_once('/dolifleet/lib/dolifleet.lib.php');

// Load translation files required by the page
$langs->load("dolifleet@dolifleet");

// Get parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$rowid = GETPOST('rowid', 'int');
$code = GETPOST('code', 'alpha');
$label = GETPOST('label', 'alpha');
$active = GETPOST('active', 'int');
$page = GETPOST('page', 'int');

if (!$user->hasRight('dolifleet', 'write')) {
	accessforbidden();
}

if (empty($conf->dolifleet->enabled)) accessforbidden();

$hookmanager->initHooks(array('dolifleetparam', 'globalcard')); // Note that conf->hooks_modules contains array


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
			}
			if (empty($error)) {
				if ($action == 'confirmnew') {
					$sql = "INSERT INTO " . MAIN_DB_PREFIX . "c_dolifleet_contract_type (entity,code, label, active, date_creation) VALUES (";
					$sql .= "'" . $conf->entity . "',";
					$sql .= "'" . $code . "',";
					$sql .= "'" . $label . "',";
					$sql .= "'" . $active . "',";
					$sql .= "'" . $db->idate(dol_now()) . "')";
				} elseif ($action == 'confirmedit') {
					$sql = "UPDATE " . MAIN_DB_PREFIX . "c_dolifleet_contract_type SET ";
					$sql .= "code = '" . $code . "', ";
					$sql .= "label = '" . $label . "', ";
					$sql .= "active = '" . $active . "' ";
					$sql .= "WHERE rowid = " . $rowid;
				} elseif ($action == 'confirmdelete') {
					$sql = "DELETE FROM " . MAIN_DB_PREFIX . "c_dolifleet_contract_type WHERE rowid = " . $rowid;
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
				unset($active);
			}
		}
	}
}

$typectarray = array();
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	// If $page is not defined, or '' or -1 or if we click on clear filters
	$page = 0;
}
$limit = 25;
$offset = $limit * $page;

$sql  = "SELECT p.rowid as rowid, p.code as code, p.label as label, p.active as active ";
$sql .= "FROM ".MAIN_DB_PREFIX."c_dolifleet_contract_type as p ";
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
		$typect = new stdClass();
		$typect->id = $obj->rowid;
		$typect->code = $obj->code;
		$typect->label = $obj->label;
		$typect->active = $obj->active;
		$typectarray[$typect->id] = $typect;

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
$title = $langs->trans('DolifleetSetupTypeCt');
$help_url = '';
llxHeader('', $title, $help_url);

$head = VhSetupPrepareHead();
print dol_get_fiche_head($head, 'typect', $langs->trans("DolifleetSetupTypeCt"), -1, "fontawesome_fa-tools");
// Part to show record

$formconfirm = '';
if ($action=='delete' && !empty($rowid)) {
	$formquestion[] = array('type'=>'hidden','name'=>'rowid','value'=>$rowid);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('DeleteTypect'), $langs->trans('DeleteTypectQuestion'), 'confirmdelete', $formquestion, 'yes', 1);
} elseif ($action =='new') {
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('code'), 'name'=>'code','value'=> $code);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('typectlabel'), 'name'=>'label','value'=>$label);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('active'), 'name'=>'active','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>empty($active)?'1':$active);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('NewTypect'), '', 'confirmnew', $formquestion, 'yes', 1, 0, 700);
} elseif ($action =='edit' && !empty($rowid)) {
	$dataedit = $typectarray[$rowid];
	$formquestion[] = array('type'=>'hidden','name'=>'rowid','value'=>$rowid);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('code'), 'name'=>'code','value'=> $dataedit->code);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('typectlabel'), 'name'=>'label','value'=>$dataedit->label);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('active'), 'name'=>'active','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>$dataedit->active);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('EditTypect'), '', 'confirmedit', $formquestion, 'yes', 1, 0, 700);
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
$actionpathnew = dol_buildpath('/dolifleet/param/vh_setup_typect.php', 2). '?action=new';
$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $actionpathnew, '', $user->hasRight('dolifleet', 'write'));
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], '', '', '', '', $num, $nbtotalofrecords, 'service', 0, $newcardbutton, '', $limit, 0, 0, 1);

print '<div class="fichecenter">';

print '<table class="border centpercent tableforfield liste">' . "\n";
print '<tr class="liste_titre">';
print '<th class="liste_titre">' . $langs->trans("code") . '</th>';
print '<th class="liste_titre">' . $langs->trans("typectlabel") . '</th>';
print '<th class="liste_titre">' . $langs->trans("active") . '</th>';
print '<th class="liste_titre">' . $langs->trans("action") . '</th>';
print '</tr>';
foreach ($typectarray as $key=>$data) {
	print '<tr class="oddeven">';
	print '<td>' . $data->code . '</td>';
	print '<td>' . $data->label . '</td>';
	if ($data->active == 1) {
		$out = 'switch_on';
	} else {
		$out = 'switch_off';
	}
	print '<td><span>' . img_picto($langs->trans('off'), $out) . '</span></td>';

	$actionpath = dol_buildpath('/dolifleet/param/vh_setup_typect.php', 2) . '?rowid=' . $data->id . '&action=';
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

