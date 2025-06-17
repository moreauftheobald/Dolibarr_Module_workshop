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
 *    	\file       operationorder_setup_service_type.php
 *      \ingroup    workshop
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
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

dol_include_once('/workshop/lib/workshop.lib.php');
dol_include_once('/workshop/class/servicetype.class.php');

// Load translation files required by the page
$langs->load("workshop@workshop");

$object = new ServiceType($db);

// Get parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$rowid = GETPOST('rowid', 'int');
$code = GETPOST('code', 'alpha');
$label = GETPOST('label', 'alpha');
$active = GETPOST('active', 'int');
$product_type = GETPOST('product_type', 'int');
$group_type = GETPOST('group_type', 'int');
$page = GETPOST('page', 'int');

if (!$user->hasRight("workshop", "operationorders", "readext")) {
	accessforbidden();
}

if (!isModEnabled("workshop")) accessforbidden();

$hookmanager->initHooks(array('vhsetupservice_type', 'globalcard')); // Note that conf->hooks_modules contains array


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
				if (!empty($rowid)) {
					$resfetch = $object->fetch($rowid);
					if ($resfetch < 0) {
						$error++;
						$errors[] = $object->error;
					}
				}
				if (empty($error)) {
					if ($action == 'confirmnew') {
						$object->entity = 0;
						$object->code = $code;
						$object->product_type = $product_type;
						$object->group_type = $group_type;
						$object->label = $label;
						$object->active = $active;
						$object->date_creation = dol_now();
						$res=$object->create($user);
					} elseif ($action == 'confirmedit') {
						$object->code = $code;
						$object->product_type = $product_type;
						$object->group_type = $group_type;
						$object->label = $label;
						$object->active = $active;
						$res = $object->update($user);
					} elseif ($action == 'confirmdelete') {
						$res = $object->delete($user);
					}
				}

				if ($res<0) {
					$error++;
					$errors[] = $object->error;
					$errors = array_merge($errors,$object->errors);
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
				unset($product_type);
				unset($group_type);
				unset($label);
				unset($active);
			}
		}
	}
}

$service_typearray = array();
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	// If $page is not defined, or '' or -1 or if we click on clear filters
	$page = 0;
}
$limit = 25;
$offset = $limit * $page;

$sql  = "SELECT p.rowid as rowid, p.code as code, p.product_type,p.group_type, p.label as label, p.active as active ";
$sql .= "FROM ".MAIN_DB_PREFIX. $object->table_element . " as p ";
$sql .= "WHERE 1=1";

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
		$service_type = new stdClass();
		$service_type->id = $obj->rowid;
		$service_type->code = $obj->code;
		$service_type->product_type = $obj->product_type;
		$service_type->group_type = $obj->group_type;
		$service_type->label = $obj->label;
		$service_type->active = $obj->active;
		$service_typearray[$service_type->id] = $service_type;

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
$title = $langs->trans('WorkshopSetupService_Type');
$help_url = '';
llxHeader('', $title, $help_url);

$head = workshopSetupPrepareHead();
print dol_get_fiche_head($head, 'service_type', $langs->Trans("WorkshopSetupService_Type"), -1, "fontawesome_fa-tools");
// Part to show record

$formconfirm = '';
if ($action=='delete' && !empty($rowid)) {
	$formquestion[] = array('type'=>'hidden','name'=>'rowid','value'=>$rowid);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('DeleteService_Type'), $langs->trans('DeleteService_TypeQuestion'), 'confirmdelete', $formquestion, 'yes', 1);
} elseif ($action =='new') {
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('code'), 'name'=>'code','value'=> $code);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('ProductType'), 'name'=>'product_type','values'=>$object->fields['product_type']['options']);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('GroupType'), 'name'=>'group_type','values'=>$object->fields['group_type']['options']);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('service_typelabel'), 'name'=>'label','value'=>$label);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('active'), 'name'=>'active','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>empty($active)?'1':$active);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('NewService_Type'), '', 'confirmnew', $formquestion, 'yes', 1, 0, 700);
} elseif ($action =='edit' && !empty($rowid)) {
	$dataedit = $service_typearray[$rowid];
	$formquestion[] = array('type'=>'hidden','name'=>'rowid','value'=>$rowid);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('code'), 'name'=>'code','value'=> $dataedit->code);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('ProductType'), 'name'=>'product_type','values'=>$object->fields['product_type']['options'],'default'=>$dataedit->product_type);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('GroupType'), 'name'=>'group_type','values'=>$object->fields['group_type']['options'],'default'=>$dataedit->group_type);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('service_typelabel'), 'name'=>'label','value'=>$dataedit->label);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('active'), 'name'=>'active','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>$dataedit->active);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('EditService_Type'), '', 'confirmedit', $formquestion, 'yes', 1, 0, 700);
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
$actionpathnew = dol_buildpath('/workshop/operationorder/param/operationorder_setup_service_type.php', 2). '?action=new';
$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $actionpathnew, '', $user->hasRight("workshop", "vehicule", "write"));
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], '', '', '', '', $num, $nbtotalofrecords, 'service', 0, $newcardbutton, '', $limit, 0, 0, 1);

print '<div class="fichecenter">';

print '<table class="border centpercent tableforfield liste">' . "\n";
print '<tr class="liste_titre">';
print '<th class="liste_titre">' . $langs->trans("code") . '</th>';
print '<th class="liste_titre">' . $langs->trans("label") . '</th>';
print '<th class="liste_titre">' . $langs->trans("ProductType") . '</th>';
print '<th class="liste_titre">' . $langs->trans("GroupType") . '</th>';
print '<th class="liste_titre">' . $langs->trans("active") . '</th>';
print '<th class="liste_titre">' . $langs->trans("action") . '</th>';
print '</tr>';
foreach ($service_typearray as $key=>$data) {
	print '<tr class="oddeven">';
	print '<td>' . $data->code . '</td>';
	print '<td>' . $data->label . '</td>';
	print '<td>'.$langs->trans($object->fields['product_type']['options'][$data->product_type]).'</td>';
	print '<td>'.$langs->trans($object->fields['group_type']['options'][$data->group_type]).'</td>';
	print '</td>';
	if ($data->active == 1) {
		$out = 'switch_on';
	} else {
		$out = 'switch_off';
	}
	print '<td><span>' . img_picto($langs->trans('off'), $out) . '</span></td>';

	$actionpath = dol_buildpath('/workshop/operationorder/param/operationorder_setup_service_type.php', 2) . '?rowid=' . $data->id . '&action=';
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
