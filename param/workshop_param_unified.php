<?php
/* Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
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
 * \file    param/workshop_param_unified.php
 * \ingroup workshop
 * \brief   Unified parameter management page (one tab per registered object).
 *
 * Displays one tab per dictionary object registered in getWorkshopParamObjects()
 * (lib/workshop.lib.php). CRUD operations use the object's class methods.
 *
 * To add a new parameter object, add an entry to getWorkshopParamObjects().
 * No other file needs to be modified.
 *
 * URL parameters:
 *   tab     = object key (e.g. 'marque', 'type', ...)
 *   context = 'vehicule' | 'atelier' | '' (filters visible tabs)
 *   action  = 'new' | 'edit' | 'delete' | 'confirmnew' | 'confirmedit' | 'confirmdelete'
 *   rowid   = integer id of the object to edit/delete
 *   page    = page number for pagination
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp  = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i    = strlen($tmp) - 1;
$j    = strlen($tmp2) - 1;
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
if (!$res && file_exists("../main.inc.php"))     { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))   { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

dol_include_once('/workshop/lib/workshop.lib.php');

$langs->load("workshop@workshop");

// Access control
if (!$user->hasRight("workshop", "vehicule", "readext")) {
	accessforbidden();
}
if (!isModEnabled("workshop")) {
	accessforbidden();
}

// ─── Parameters ──────────────────────────────────────────────────────────────

$context = GETPOST('context', 'aZ09');  // 'vehicule' | 'atelier' | ''
$action  = GETPOST('action',  'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$rowid   = GETPOST('rowid',   'int');
$page    = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST('page', 'int');

if (empty($page) || $page < 0
	|| GETPOST('button_search', 'alpha')
	|| GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$limit  = 25;
$offset = $limit * $page;

// ─── Object registry ─────────────────────────────────────────────────────────

$allObjects = getWorkshopParamObjects();

// Filter objects by context
$contextObjects = array();
foreach ($allObjects as $key => $config) {
	if (empty($context)
		|| $config['context'] === 'both'
		|| $config['context'] === $context) {
		$contextObjects[$key] = $config;
	}
}

if (empty($contextObjects)) {
	dol_print_error(null, $langs->trans('WorkshopNoObjectsForContext', $context));
	exit;
}

// Resolve active tab
$tab = GETPOST('tab', 'aZ09');
if (empty($tab) || !isset($contextObjects[$tab])) {
	reset($contextObjects);
	$tab = key($contextObjects);
}

$objConfig = $contextObjects[$tab];

// Load class
dol_include_once($objConfig['class_file']);
/** @var dictionary $object */
$object = new $objConfig['class_name']($db);

// Collect POST values for the registered fields
$postValues = array();
foreach ($objConfig['fields'] as $fieldName => $fieldConfig) {
	if ($fieldConfig['type'] === 'related_select'
		|| $fieldConfig['type'] === 'societe'
		|| $fieldConfig['type'] === 'select') {
		$postValues[$fieldName] = GETPOST($fieldName, 'int');
	} else {
		// 'text', 'color', 'other' → alpha (allows # for hex colors)
		$postValues[$fieldName] = GETPOST($fieldName, 'alpha');
	}
}

$hookmanager->initHooks(array('workshopparamunified', 'globalcard'));

// ─── Actions ─────────────────────────────────────────────────────────────────

$parameters = array();
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$error  = 0;
	$errors = array();

	if ($confirm === 'yes'
		&& in_array($action, array('confirmnew', 'confirmedit', 'confirmdelete'))) {

		// Validate required fields (not needed for delete)
		if ($action !== 'confirmdelete') {
			foreach ($objConfig['fields'] as $fieldName => $fieldConfig) {
				if (!empty($fieldConfig['required']) && $postValues[$fieldName] === '') {
					$error++;
					$errors[] = $langs->trans('ErrorFieldRequired', $langs->trans($fieldConfig['label']));
				}
			}
		}

		if (!$error) {
			// Fetch existing record for edit / delete
			if (!empty($rowid) && $action !== 'confirmnew') {
				$resfetch = $object->fetch($rowid);
				if ($resfetch < 0) {
					$error++;
					$errors[] = $object->error;
				}
			}
		}

		if (!$error) {
			if ($action === 'confirmnew' || $action === 'confirmedit') {
				foreach ($objConfig['fields'] as $fieldName => $fieldConfig) {
					$object->$fieldName = $postValues[$fieldName];
				}
			}

			if ($action === 'confirmnew') {
				$object->entity        = 0;
				$object->date_creation = dol_now();
				$res = $object->create($user);
			} elseif ($action === 'confirmedit') {
				$res = $object->update($user);
			} elseif ($action === 'confirmdelete') {
				$res = $object->delete($user);
			}

			if ($res < 0) {
				$error++;
				$errors[] = $object->error;
				$errors   = array_merge($errors, $object->errors);
			}
		}

		if ($error > 0) {
			$msgKey = ($action === 'confirmnew')    ? 'CreateErrors'
				: (($action === 'confirmedit') ? 'UpdateErrors' : 'DeleteErrors');
			setEventMessages($msgKey, $errors, 'errors');
		} else {
			$msgKey = ($action === 'confirmnew')    ? 'CreateSucces'
				: (($action === 'confirmedit') ? 'UpdateSucces' : 'DeleteSucces');
			setEventMessage($msgKey);
			// Reset transient state after success
			$action  = '';
			$confirm = '';
			$rowid   = 0;
			$postValues = array();
		}
	}
}

// ─── Load list data ───────────────────────────────────────────────────────────

// Count total records for pagination
$sqlCount  = 'SELECT COUNT(*) as nb FROM '.$db->prefix().$object->table_element;
$sqlCount .= $object->ismultientitymanaged
	? ' WHERE entity IN ('.getEntity('workshop').')'
	: ' WHERE 1=1';
$resCount = $db->query($sqlCount);
$nbtotalofrecords = 0;
if ($resCount) {
	$objCount         = $db->fetch_object($resCount);
	$nbtotalofrecords = (int) $objCount->nb;
}

// Reset page if out of bounds
if (($page * $limit) > $nbtotalofrecords) {
	$page   = 0;
	$offset = 0;
}

$sortField   = !empty($objConfig['sort_field']) ? $objConfig['sort_field'] : 'label';
$objectslist = $object->fetchAll('ASC', $sortField, $limit + 1, $offset);
if (!is_array($objectslist)) {
	$objectslist = array();
}

$num = count($objectslist);

// ─── View ────────────────────────────────────────────────────────────────────

$form  = new Form($db);
$title = $langs->trans($objConfig['tab_label']);

llxHeader('', $title, '');

$head = workshopUnifiedParamPrepareHead($context);
print dol_get_fiche_head($head, $tab, $langs->trans('WorkshopUnifiedParamTitle'), -1, 'fontawesome_fa-tools');

// Build the URL fragment that must survive across all navigation links
$tabParam = 'tab='.urlencode($tab);
if (!empty($context)) {
	$tabParam .= '&context='.urlencode($context);
}

// ─── Form confirms ───────────────────────────────────────────────────────────

$formconfirm = '';

if ($action === 'delete' && !empty($rowid)) {
	// tab, context et rowid passés dans l'URL — pas de hidden dans formquestion
	$pageUrl     = $_SERVER["PHP_SELF"].'?tab='.urlencode($tab).'&context='.urlencode($context).'&rowid='.(int) $rowid;
	$formconfirm = $form->formconfirm(
		$pageUrl,
		$langs->trans('Delete'),
		$langs->trans('ConfirmDelete'),
		'confirmdelete',
		array(),
		'yes',
		1
	);
} elseif ($action === 'new') {
	// tab et context passés dans l'URL — pas de hidden dans formquestion
	$pageUrl      = $_SERVER["PHP_SELF"].'?tab='.urlencode($tab).'&context='.urlencode($context);
	$formquestion = array();
	foreach ($objConfig['fields'] as $fieldName => $fieldConfig) {
		$q = workshopBuildParamFormQuestion($fieldName, $fieldConfig, null, $db, $langs);
		if (!empty($q)) {
			$formquestion[] = $q;
		}
	}
	$formconfirm = $form->formconfirm(
		$pageUrl,
		$langs->trans('New'),
		'',
		'confirmnew',
		$formquestion,
		'yes',
		1,
		0,
		700
	);
} elseif ($action === 'edit' && !empty($rowid)) {
	// tab, context et rowid passés dans l'URL — pas de hidden dans formquestion
	$pageUrl  = $_SERVER["PHP_SELF"].'?tab='.urlencode($tab).'&context='.urlencode($context).'&rowid='.(int) $rowid;
	// Load the record to edit (may already be in $objectslist)
	$dataEdit = isset($objectslist[$rowid]) ? $objectslist[$rowid] : null;
	if ($dataEdit === null) {
		$dataEdit = new $objConfig['class_name']($db);
		$dataEdit->fetch($rowid);
	}
	$formquestion = array();
	foreach ($objConfig['fields'] as $fieldName => $fieldConfig) {
		$q = workshopBuildParamFormQuestion($fieldName, $fieldConfig, $dataEdit, $db, $langs);
		if (!empty($q)) {
			$formquestion[] = $q;
		}
	}
	$formconfirm = $form->formconfirm(
		$pageUrl,
		$langs->trans('Edit'),
		'',
		'confirmedit',
		$formquestion,
		'yes',
		1,
		0,
		700
	);
}

// Hook formConfirm
$parameters = array('formConfirm' => $formconfirm);
$reshook    = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action);
if (empty($reshook)) {
	$formconfirm .= $hookmanager->resPrint;
} elseif ($reshook > 0) {
	$formconfirm = $hookmanager->resPrint;
}

print $formconfirm;

// ─── List bar ────────────────────────────────────────────────────────────────

$newcardbutton = '';
if ($user->hasRight("workshop", "vehicule", "write")) {
	$newurl        = dol_buildpath('/workshop/param/workshop_param_unified.php', 2).'?'.$tabParam.'&action=new';
	$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $newurl);
}

print_barre_liste(
	$title,
	$page,
	$_SERVER["PHP_SELF"],
	$tabParam,
	'',
	'',
	'',
	$num,
	$nbtotalofrecords,
	'service',
	0,
	$newcardbutton,
	'',
	$limit,
	0,
	0,
	1
);

// ─── Data table ──────────────────────────────────────────────────────────────

print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield liste">'."\n";

// Header row
print '<tr class="liste_titre">';
foreach ($objConfig['fields'] as $fieldName => $fieldConfig) {
	print '<th class="liste_titre">'.$langs->trans($fieldConfig['label']).'</th>';
}
print '<th class="liste_titre">'.$langs->trans('action').'</th>';
print '</tr>';

// Data rows
$i = 0;
foreach ($objectslist as $data) {
	if ($i >= $limit) {
		break;
	}
	print '<tr class="oddeven">';
	foreach ($objConfig['fields'] as $fieldName => $fieldConfig) {
		print '<td>';
		print workshopRenderParamFieldValue($fieldName, $fieldConfig, $data, $db, $langs);
		print '</td>';
	}

	// Action buttons
	$actionbase = dol_buildpath('/workshop/param/workshop_param_unified.php', 2)
		.'?'.$tabParam.'&rowid='.$data->id.'&action=';

	$actionHtml = '<a href="'.dol_escape_htmltag($actionbase.'edit').'">'
		.'<span class="fas fa-pen" title="'.$langs->trans('Edit').'"></span></a>';

	if ($user->admin) {
		$actionHtml .= '&nbsp;&nbsp;';
		$actionHtml .= '<a href="'.dol_escape_htmltag($actionbase.'delete&token='.newToken()).'">'
			.'<span class="fas fa-trash-alt" title="'.$langs->trans('Delete').'"></span></a>';
	}
	print '<td>'.$actionHtml.'</td>';
	print '</tr>';
	$i++;
}

print '</table>';
print '</div>'."\n";

// Hooks – additional action buttons
$parameters = array();
$reshook    = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
	print '<div class="tabsAction">'."\n";
	print '</div>'."\n";
}

// End of page
llxFooter();
$db->close();
