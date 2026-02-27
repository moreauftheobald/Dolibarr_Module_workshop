<?php
/* Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
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
 * \file       or_list.php
 * \ingroup    workshop
 * \brief      Page to list Operation Orders (Ordres de Réparation)
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/workshop/class/operationorder.class.php');

if (!isModEnabled('workshop')) {
	accessforbidden();
}
if (!$user->hasRight('workshop', 'operationorders', 'read')) {
	accessforbidden();
}

$langs->loadLangs(array('workshop@workshop'));

// Get parameters
$action      = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view';
$massaction  = GETPOST('massaction', 'alpha');
$confirm     = GETPOST('confirm', 'alpha');
$cancel      = GETPOST('cancel', 'alpha');
$toselect    = GETPOST('toselect', 'array:int');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'orlist';
$backtopage  = GETPOST('backtopage', 'alpha');
$optioncss   = GETPOST('optioncss', 'aZ');
$mode        = GETPOST('mode', 'aZ');

// Pagination
$limit     = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset   = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize objects
$object      = new Operationorder($db);
$extrafields = new ExtraFields($db);
$form        = new Form($db);
$hookmanager->initHooks(array('orlist'));

// Fetch optional attributes
$extrafields->fetch_name_optionals_label($object->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Default sort order
if (!$sortfield) {
	reset($object->fields);
	$sortfield = "t.".key($object->fields);
}
if (!$sortorder) {
	$sortorder = "DESC";
}

// Initialize search criteria
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha') !== '') {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
	if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
		$search[$key.'_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_'.$key.'_dtstartmonth'), GETPOSTINT('search_'.$key.'_dtstartday'), GETPOSTINT('search_'.$key.'_dtstartyear'));
		$search[$key.'_dtend']   = dol_mktime(23, 59, 59, GETPOSTINT('search_'.$key.'_dtendmonth'), GETPOSTINT('search_'.$key.'_dtendday'), GETPOSTINT('search_'.$key.'_dtendyear'));
	}
}

// Build array of fields for columns
$tableprefix  = 't';
$arrayfields  = array();
foreach ($object->fields as $key => $val) {
	if (!empty($val['visible'])) {
		$visible = (int) dol_eval((string) $val['visible'], 1);
		$arrayfields[$tableprefix.'.'.$key] = array(
			'label'    => $val['label'],
			'checked'  => (($visible < 0) ? 0 : 1),
			'enabled'  => (abs($visible) != 3 && (bool) dol_eval($val['enabled'], 1)),
			'position' => $val['position'],
			'help'     => isset($val['help']) ? $val['help'] : '',
		);
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

$object->fields  = dol_sort_array($object->fields, 'position');
$arrayfields     = dol_sort_array($arrayfields, 'position');

// Permissions
$permissiontoread   = $user->hasRight('workshop', 'operationorders', 'read');
$permissiontoadd    = $user->hasRight('workshop', 'operationorders', 'write');
$permissiontodelete = $user->hasRight('workshop', 'operationorders', 'delete');


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action    = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array('arrayfields' => &$arrayfields);
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
			if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
				$search[$key.'_dtstart'] = '';
				$search[$key.'_dtend']   = '';
			}
		}
		$toselect             = array();
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = '';
	}

	// Mass delete
	if ($massaction == 'predelete') {
		if (!$permissiontodelete) {
			setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
		} elseif (!empty($toselect)) {
			$error = 0;
			$db->begin();
			foreach ($toselect as $toselectid) {
				$objecttmp = new Operationorder($db);
				$result    = $objecttmp->fetch($toselectid);
				if ($result > 0) {
					$result = $objecttmp->delete($user);
					if ($result <= 0) {
						$error++;
						setEventMessages($objecttmp->error, $objecttmp->errors, 'errors');
					}
				}
			}
			if (!$error) {
				$db->commit();
				setEventMessages($langs->trans('RecordsDeleted'), null, 'mesgs');
			} else {
				$db->rollback();
			}
		}
	}
}


/*
 * View
 */

$title    = $langs->trans('OperationOrders');
$help_url = '';

// Build and execute SQL
$sql  = 'SELECT';
$sql .= ' '.$object->getFieldList('t');
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? ", ef.".$key." as options_".$key : "");
	}
}
$parameters = array();
$reshook    = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object, $action);
$sql       .= $hookmanager->resPrint;
$sql        = preg_replace('/,\s*$/', '', $sql);

$sqlfields = $sql;

$sql .= ' FROM '.$db->prefix().$object->table_element.' as t';
if (isset($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= ' LEFT JOIN '.$db->prefix().$object->table_element.'_extrafields as ef ON (t.rowid = ef.fk_object)';
}
$parameters = array();
$reshook    = $hookmanager->executeHooks('printFieldListFrom', $parameters, $object, $action);
$sql       .= $hookmanager->resPrint;

$sql .= ' WHERE t.entity IN ('.getEntity('workshop', 1).')';

foreach ($search as $key => $val) {
	if (array_key_exists($key, $object->fields)) {
		if ($key == 'status' && $search[$key] == -1) {
			continue;
		}
		$mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
		if ((strpos($object->fields[$key]['type'], 'integer:') === 0) || (strpos($object->fields[$key]['type'], 'sellist:') === 0) || !empty($object->fields[$key]['arrayofkeyval'])) {
			if ($search[$key] == '-1' || ($search[$key] === '0' && (empty($object->fields[$key]['arrayofkeyval']) || !array_key_exists('0', $object->fields[$key]['arrayofkeyval'])))) {
				$search[$key] = '';
			}
			$mode_search = 2;
		}
		if (empty($object->fields[$key]['searchmulti'])) {
			if (!is_array($search[$key]) && $search[$key] != '') {
				$sql .= natural_search("t.".$db->escape($key), $search[$key], (($key == 'status') ? 2 : $mode_search));
			}
		} else {
			if (is_array($search[$key]) && !empty($search[$key])) {
				$sql .= natural_search("t.".$db->escape($key), implode(',', $search[$key]), (($key == 'status') ? 2 : $mode_search));
			}
		}
	} else {
		if (preg_match('/(_dtstart|_dtend)$/', $key) && $search[$key] != '') {
			$columnName = preg_replace('/(_dtstart|_dtend)$/', '', $key);
			if (isset($object->fields[$columnName]) && preg_match('/^(date|timestamp|datetime)/', $object->fields[$columnName]['type'])) {
				if (preg_match('/_dtstart$/', $key)) {
					$sql .= " AND t.".$db->sanitize($columnName)." >= '".$db->idate($search[$key])."'";
				}
				if (preg_match('/_dtend$/', $key)) {
					$sql .= " AND t.".$db->sanitize($columnName)." <= '".$db->idate($search[$key])."'";
				}
			}
		}
	}
}

// Add where from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
$parameters = array();
$reshook    = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action);
$sql       .= $hookmanager->resPrint;

// Count total records
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$sqlforcount = preg_replace('/GROUP BY .*$/', '', $sqlforcount);

	$resql = $db->query($sqlforcount);
	if ($resql) {
		$objforcount     = $db->fetch_object($resql);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
	} else {
		dol_print_error($db);
	}

	if (($page * $limit) > $nbtotalofrecords) {
		$page   = 0;
		$offset = 0;
	}
	$db->free($resql);
}

// Execute with limit
$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);


// Output page
llxHeader('', $title, $help_url, '', 0, 0, array(), array(), '', 'mod-workshop page-list bodyforlist');

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($mode)) {
	$param .= '&mode='.urlencode($mode);
}
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
if ($optioncss != '') {
	$param .= '&optioncss='.urlencode($optioncss);
}
foreach ($search as $key => $val) {
	if (is_array($search[$key])) {
		foreach ($search[$key] as $skey) {
			if ($skey != '') {
				$param .= '&search_'.$key.'[]='.urlencode($skey);
			}
		}
	} elseif (preg_match('/(_dtstart|_dtend)$/', $key) && !empty($val)) {
		$param .= '&search_'.$key.'month='.GETPOSTINT('search_'.$key.'month');
		$param .= '&search_'.$key.'day='.GETPOSTINT('search_'.$key.'day');
		$param .= '&search_'.$key.'year='.GETPOSTINT('search_'.$key.'year');
	} elseif ($search[$key] != '') {
		$param .= '&search_'.$key.'='.urlencode($search[$key]);
	}
}
// Extra fields param
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';
$parameters = array('param' => &$param);
$reshook    = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object, $action);
$param     .= $hookmanager->resPrint;

// Mass action buttons
$arrayofmassactions = array();
if (!empty($permissiontodelete)) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
}
if (GETPOSTINT('nomassaction') || in_array($massaction, array('presend', 'predelete'))) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">'."\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="mode" value="'.$mode.'">';

$newcardbutton  = '';
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewList'), '', 'fa fa-bars imgforviewmode', dol_escape_htmltag($_SERVER["PHP_SELF"]).'?mode=common'.preg_replace('/(&|\?)*mode=[^&]+/', '', $param), '', ((empty($mode) || $mode == 'common') ? 2 : 1), array('morecss' => 'reposition'));
$newcardbutton .= dolGetButtonTitleSeparator();
$newcardbutton .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/workshop/operationorder/or_card.php', 1).'?action=create&backtopage='.urlencode($_SERVER['PHP_SELF']), '', $permissiontoadd);

$varpage         = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column);
$selectedfields  = $htmlofselectarray;
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print_barre_liste($title, $page, dol_escape_htmltag($_SERVER["PHP_SELF"]), $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, $object->picto, 0, $newcardbutton, '', $limit, 0, 0, 1);

// Pre-mass action
$topicmail  = 'SendOperationOrderRef';
$modelmail  = 'operationorder';
$objecttmp  = new Operationorder($db);
$trackid    = 'or'.$object->id;
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

$moreforfilter = '';
$parameters    = array();
$reshook       = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object, $action);
if (empty($reshook)) {
	$moreforfilter .= $hookmanager->resPrint;
} else {
	$moreforfilter = $hookmanager->resPrint;
}
if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $moreforfilter;
	print '</div>';
}

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal noborder liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

// Search row
print '<tr class="liste_titre_filter">';
if ($conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">';
	$searchpicto = $form->showFilterButtons('left');
	print $searchpicto;
	print '</td>';
}
foreach ($object->fields as $key => $val) {
	$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('id', 'rowid', 'ref', 'status')) && $val['label'] != 'TechnicalID' && empty($val['arrayofkeyval'])) {
		$cssforfield .= ($cssforfield ? ' ' : '').'right';
	}
	if (!empty($arrayfields['t.'.$key]['checked'])) {
		print '<td class="liste_titre'.($cssforfield ? ' '.$cssforfield : '').($key == 'status' ? ' parentonrightofpage' : '').'">';
		if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
			print $form->selectarray('search_'.$key, $val['arrayofkeyval'], (isset($search[$key]) ? $search[$key] : ''), 1, 0, 0, '', 1, 0, 0, '', 'maxwidth100'.($key == 'status' ? ' search_status width100 onrightofpage' : ''), 1);
		} elseif ((strpos($val['type'], 'integer:') === 0) || (strpos($val['type'], 'sellist:') === 0)) {
			print $object->showInputField($val, $key, (isset($search[$key]) ? $search[$key] : ''), '', '', 'search_', $cssforfield.' maxwidth250', 1);
		} elseif (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
			print '<div class="nowrap">';
			print $form->selectDate($search[$key.'_dtstart'] ? $search[$key.'_dtstart'] : '', "search_".$key."_dtstart", 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
			print '</div>';
			print '<div class="nowrap">';
			print $form->selectDate($search[$key.'_dtend'] ? $search[$key.'_dtend'] : '', "search_".$key."_dtend", 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
			print '</div>';
		} else {
			print '<input type="text" class="flat maxwidth'.(in_array($val['type'], array('integer', 'price')) ? '50' : '75').'" name="search_'.$key.'" value="'.dol_escape_htmltag(isset($search[$key]) ? $search[$key] : '').'">';
		}
		print '</td>';
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';
$parameters = array('arrayfields' => $arrayfields);
$reshook    = $hookmanager->executeHooks('printFieldListOption', $parameters, $object, $action);
print $hookmanager->resPrint;
if (!$conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">';
	$searchpicto = $form->showFilterButtons();
	print $searchpicto;
	print '</td>';
}
print '</tr>'."\n";

$totalarray              = array();
$totalarray['nbfield']  = 0;

// Column headers row
print '<tr class="liste_titre">';
if ($conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, dol_escape_htmltag($_SERVER["PHP_SELF"]), '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
foreach ($object->fields as $key => $val) {
	$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('id', 'rowid', 'ref', 'status')) && $val['label'] != 'TechnicalID' && empty($val['arrayofkeyval'])) {
		$cssforfield .= ($cssforfield ? ' ' : '').'right';
	}
	$cssforfield = preg_replace('/small\s*/', '', $cssforfield);
	if (!empty($arrayfields['t.'.$key]['checked'])) {
		print getTitleFieldOfList($arrayfields['t.'.$key]['label'], 0, dol_escape_htmltag($_SERVER['PHP_SELF']), 't.'.$key, '', $param, ($cssforfield ? 'class="'.$cssforfield.'"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''), 0, (empty($val['helplist']) ? '' : $val['helplist']))."\n";
		$totalarray['nbfield']++;
	}
}
// Extra fields headers
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder, 'totalarray' => &$totalarray);
$reshook    = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object, $action);
print $hookmanager->resPrint;
if (!$conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, dol_escape_htmltag($_SERVER["PHP_SELF"]), '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
print '</tr>'."\n";

// Detect if fetch needed for extrafields computed
$needToFetchEachLine = 0;
if (isset($extrafields->attributes[$object->table_element]['computed']) && is_array($extrafields->attributes[$object->table_element]['computed']) && count($extrafields->attributes[$object->table_element]['computed']) > 0) {
	foreach ($extrafields->attributes[$object->table_element]['computed'] as $key => $val) {
		if (!is_null($val) && preg_match('/\$object/', $val)) {
			$needToFetchEachLine++;
		}
	}
}

// Loop on records
$i              = 0;
$savnbfield     = $totalarray['nbfield'];
$totalarray     = array();
$totalarray['nbfield'] = 0;
$imaxinloop     = ($limit ? min($num, $limit) : $num);

while ($i < $imaxinloop) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break;
	}

	$object->setVarsFromFetchObj($obj);

	print '<tr data-rowid="'.$object->id.'" class="oddeven">';

	if ($conf->main_checkbox_left_column) {
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction) {
			$selected = 0;
			if (in_array($object->id, $arrayofselected)) {
				$selected = 1;
			}
			print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	// Fields
	foreach ($object->fields as $key => $val) {
		$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
		if (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
			$cssforfield .= ($cssforfield ? ' ' : '').'center';
		} elseif ($key == 'status') {
			$cssforfield .= ($cssforfield ? ' ' : '').'center';
		}
		if (in_array($val['type'], array('timestamp'))) {
			$cssforfield .= ($cssforfield ? ' ' : '').'nowraponall';
		} elseif ($key == 'ref') {
			$cssforfield .= ($cssforfield ? ' ' : '').'nowraponall';
		}
		if (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('id', 'rowid', 'ref', 'status')) && empty($val['arrayofkeyval'])) {
			$cssforfield .= ($cssforfield ? ' ' : '').'right';
		}

		if (!empty($arrayfields['t.'.$key]['checked'])) {
			print '<td'.($cssforfield ? ' class="'.$cssforfield.((preg_match('/tdoverflow/', $cssforfield) && !in_array($val['type'], array('ip', 'url')) && !is_numeric($object->$key)) ? ' classfortooltip' : '').'"' : '');
			if (preg_match('/tdoverflow/', $cssforfield) && !in_array($val['type'], array('ip', 'url')) && !is_numeric($object->$key)) {
				print ' title="'.dol_escape_htmltag($object->$key).'"';
			}
			print '>';
			if ($key == 'ref') {
				print $object->getNomUrl(1);
			} elseif ($key == 'status') {
				print $object->getLibStatut(5);
			} elseif ($key == 'rowid') {
				print $object->showOutputField($val, $key, $object->id, '');
			} else {
				print $object->showOutputField($val, $key, $object->$key, '');
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
			if (!empty($val['isameasure']) && $val['isameasure'] == 1) {
				if (!$i) {
					$totalarray['pos'][$totalarray['nbfield']] = 't.'.$key;
				}
				if (!isset($totalarray['val'])) {
					$totalarray['val'] = array();
				}
				if (!isset($totalarray['val']['t.'.$key])) {
					$totalarray['val']['t.'.$key] = 0;
				}
				$totalarray['val']['t.'.$key] += $object->$key;
			}
		}
	}
	// Extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
	$parameters = array('arrayfields' => $arrayfields, 'object' => $object, 'obj' => $obj, 'i' => $i, 'totalarray' => &$totalarray);
	$reshook    = $hookmanager->executeHooks('printFieldListValue', $parameters, $object, $action);
	print $hookmanager->resPrint;

	if (empty($conf->main_checkbox_left_column)) {
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction) {
			$selected = 0;
			if (in_array($object->id, $arrayofselected)) {
				$selected = 1;
			}
			print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	print '</tr>'."\n";
	$i++;
}

// Total line
include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';

// No records found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

$db->free($resql);

$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook    = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object, $action);
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";
print '</form>'."\n";

llxFooter();
$db->close();
