<?php
/* Copyright (C) 2020 ATM Consulting <support@atm-consulting.fr>
 * Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
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
 *    \file       vehicule/vehicule_operation_list.php
 *    \ingroup    workshop
 *    \brief      Page to list vehicule operations (maintenance schedule)
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
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/workshop/class/vehicule.class.php');
dol_include_once('/workshop/class/vehiculeOperation.class.php');
if (isModEnabled('operationorder')) {
	dol_include_once('operationorder/class/operationorder.class.php');
}

if (!$user->hasRight('workshop', 'vehicule', 'read')) {
	accessforbidden();
}

$langs->loadLangs(array("workshop@workshop", "products"));

// Get parameters
$action = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view';
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$toselect = GETPOST('toselect', 'array:int');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'vehiculeoperationlist';
$backtopage = GETPOST('backtopage', 'alpha');
$optioncss = GETPOST('optioncss', 'aZ');
$mode = GETPOST('mode', 'aZ');

// Load variable for pagination
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (!$sortfield) {
	$sortfield = 'o.date_next';
}
if (!$sortorder) {
	$sortorder = 'ASC';
}

// Initialize technical objects
$object = new Vehicule($db);
$operation = new WorkshopVehiculeOperation($db);
$form = new Form($db);
$hookmanager->initHooks(array('vehiculeoperationlist'));

// Initialize search criteria
$search = array();
$search['t_vin'] = GETPOST('search_t_vin', 'alpha');
$search['t_immatriculation'] = GETPOST('search_t_immatriculation', 'alpha');
$search['t_fk_vehicule_type'] = GETPOST('search_t_fk_vehicule_type', 'alpha');
$search['t_fk_vehicule_mark'] = GETPOST('search_t_fk_vehicule_mark', 'alpha');
$search['t_fk_soc'] = GETPOSTINT('search_t_fk_soc');
$search['t_fk_contract_type'] = GETPOST('search_t_fk_contract_type', 'alpha');
$search['t_km'] = GETPOST('search_t_km', 'alpha');
$search['o_fk_product'] = GETPOST('search_o_fk_product', 'array');
$search['o_km'] = GETPOST('search_o_km', 'alpha');
$search['o_delai_from_last_op'] = GETPOST('search_o_delai_from_last_op', 'alpha');
$search['o_km_done'] = GETPOST('search_o_km_done', 'alpha');
$search['o_km_next'] = GETPOST('search_o_km_next', 'alpha');
$search['o_on_time'] = GETPOST('search_o_on_time', 'alpha');
$search['o_or_next'] = GETPOST('search_o_or_next', 'alpha');
$search['p_label'] = GETPOST('search_p_label', 'alpha');

// Date search fields
$search['t_date_immat_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_t_date_immat_dtstartmonth'), GETPOSTINT('search_t_date_immat_dtstartday'), GETPOSTINT('search_t_date_immat_dtstartyear'));
$search['t_date_immat_dtend'] = dol_mktime(23, 59, 59, GETPOSTINT('search_t_date_immat_dtendmonth'), GETPOSTINT('search_t_date_immat_dtendday'), GETPOSTINT('search_t_date_immat_dtendyear'));
$search['t_date_customer_exploit_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_t_date_customer_exploit_dtstartmonth'), GETPOSTINT('search_t_date_customer_exploit_dtstartday'), GETPOSTINT('search_t_date_customer_exploit_dtstartyear'));
$search['t_date_customer_exploit_dtend'] = dol_mktime(23, 59, 59, GETPOSTINT('search_t_date_customer_exploit_dtendmonth'), GETPOSTINT('search_t_date_customer_exploit_dtendday'), GETPOSTINT('search_t_date_customer_exploit_dtendyear'));
$search['t_km_date_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_t_km_date_dtstartmonth'), GETPOSTINT('search_t_km_date_dtstartday'), GETPOSTINT('search_t_km_date_dtstartyear'));
$search['t_km_date_dtend'] = dol_mktime(23, 59, 59, GETPOSTINT('search_t_km_date_dtendmonth'), GETPOSTINT('search_t_km_date_dtendday'), GETPOSTINT('search_t_km_date_dtendyear'));
$search['t_date_end_contract_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_t_date_end_contract_dtstartmonth'), GETPOSTINT('search_t_date_end_contract_dtstartday'), GETPOSTINT('search_t_date_end_contract_dtstartyear'));
$search['t_date_end_contract_dtend'] = dol_mktime(23, 59, 59, GETPOSTINT('search_t_date_end_contract_dtendmonth'), GETPOSTINT('search_t_date_end_contract_dtendday'), GETPOSTINT('search_t_date_end_contract_dtendyear'));
$search['o_date_done_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_o_date_done_dtstartmonth'), GETPOSTINT('search_o_date_done_dtstartday'), GETPOSTINT('search_o_date_done_dtstartyear'));
$search['o_date_done_dtend'] = dol_mktime(23, 59, 59, GETPOSTINT('search_o_date_done_dtendmonth'), GETPOSTINT('search_o_date_done_dtendday'), GETPOSTINT('search_o_date_done_dtendyear'));
$search['o_date_next_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_o_date_next_dtstartmonth'), GETPOSTINT('search_o_date_next_dtstartday'), GETPOSTINT('search_o_date_next_dtstartyear'));
$search['o_date_next_dtend'] = dol_mktime(23, 59, 59, GETPOSTINT('search_o_date_next_dtendmonth'), GETPOSTINT('search_o_date_next_dtendday'), GETPOSTINT('search_o_date_next_dtendyear'));
$search['o_date_due_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_o_date_due_dtstartmonth'), GETPOSTINT('search_o_date_due_dtstartday'), GETPOSTINT('search_o_date_due_dtstartyear'));
$search['o_date_due_dtend'] = dol_mktime(23, 59, 59, GETPOSTINT('search_o_date_due_dtendmonth'), GETPOSTINT('search_o_date_due_dtendday'), GETPOSTINT('search_o_date_due_dtendyear'));

// Build list of available products for the multiselect filter
$sql_prod = 'SELECT op.fk_product, p.label';
$sql_prod .= ' FROM '.$db->prefix().'workshop_vehicule_operation AS op';
$sql_prod .= ' INNER JOIN '.$db->prefix().'product as p ON p.rowid = op.fk_product';
$sql_prod .= ' INNER JOIN '.$db->prefix().'workshop_vehicule as v ON v.rowid = op.fk_vehicule';
$sql_prod .= ' WHERE v.status = 1 AND p.tosell = 1 AND p.tobuy = 1';
$sql_prod .= ' GROUP BY op.fk_product, p.label';
$prod_array = array();
$resql_prod = $db->query($sql_prod);
if ($resql_prod) {
	while ($obj = $db->fetch_object($resql_prod)) {
		$prod_array[$obj->fk_product] = $obj->label;
	}
}

// Definition of array of fields for columns
$arrayfields = array(
	't.vin' => array('label' => 'VIN', 'checked' => 1, 'position' => 10),
	't.fk_vehicule_type' => array('label' => 'vehiculeType', 'checked' => 1, 'position' => 20),
	't.fk_vehicule_mark' => array('label' => 'vehiculeMark', 'checked' => 1, 'position' => 30),
	't.immatriculation' => array('label' => 'immatriculation', 'checked' => 1, 'position' => 40),
	't.date_immat' => array('label' => 'immatriculation_date_short', 'checked' => 0, 'position' => 50),
	't.fk_soc' => array('label' => 'ThirdParty', 'checked' => 1, 'position' => 60),
	't.date_customer_exploit' => array('label' => 'date_customer_exploit', 'checked' => 0, 'position' => 70),
	't.km' => array('label' => 'kilometrage', 'checked' => 1, 'position' => 80),
	't.km_date' => array('label' => 'km_date', 'checked' => 0, 'position' => 90),
	't.fk_contract_type' => array('label' => 'contractType', 'checked' => 0, 'position' => 100),
	't.date_end_contract' => array('label' => 'date_end_contract', 'checked' => 0, 'position' => 110),
	't.status' => array('label' => 'Status', 'checked' => 1, 'position' => 115),
	'o.fk_product' => array('label' => 'VehiculeOperation', 'checked' => 1, 'position' => 120),
	'p.label' => array('label' => 'libelle', 'checked' => 1, 'position' => 125),
	'o.km' => array('label' => 'km', 'checked' => 1, 'position' => 130),
	'o.delai_from_last_op' => array('label' => 'VehiculeOperationDelay', 'checked' => 1, 'position' => 140),
	'o.date_done' => array('label' => 'VehiculeOperationLastDateDone', 'checked' => 1, 'position' => 150),
	'o.km_done' => array('label' => 'VehiculeOperationLastKmDone', 'checked' => 1, 'position' => 160),
	'o.date_next' => array('label' => 'VehiculeOperationDateNext', 'checked' => 1, 'position' => 170),
	'o.date_due' => array('label' => 'VehiculeOperationDateDue', 'checked' => 1, 'position' => 180),
	'o.km_next' => array('label' => 'VehiculeOperationKmNext', 'checked' => 1, 'position' => 190),
	'o.on_time' => array('label' => 'VehiculeOperationOnTime', 'checked' => 1, 'position' => 200),
);
if (isModEnabled('operationorder')) {
	$arrayfields['o.or_next'] = array('label' => 'VehiculeOperationNextOR', 'checked' => 1, 'position' => 210);
}
$arrayfields = dol_sort_array($arrayfields, 'position');


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
		foreach ($search as $key => $val) {
			$search[$key] = '';
		}
		$toselect = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = '';
	}
}


/*
 * View
 */

$title = $langs->trans('VehiculeOperationList');
$help_url = '';

// Build and execute select
$sql = 'SELECT t.rowid as t_rowid, t.vin, t.fk_vehicule_type, t.fk_vehicule_mark, t.immatriculation,';
$sql .= ' t.date_immat, t.fk_soc, t.date_customer_exploit, t.km as t_km, t.km_date,';
$sql .= ' t.fk_contract_type, t.date_end_contract, t.status as t_status,';
$sql .= ' o.rowid as o_rowid, o.fk_product, o.km as o_km, o.delai_from_last_op,';
$sql .= ' o.date_done, o.km_done, o.date_next, o.date_due, o.km_next, o.on_time, o.or_next,';
$sql .= ' p.label as p_label';

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;

$sqlfields = $sql;

$sql .= ' FROM '.$db->prefix().'workshop_vehicule as t';
$sql .= ' INNER JOIN '.$db->prefix().'workshop_vehicule_operation as o ON o.fk_vehicule = t.rowid';
$sql .= ' LEFT JOIN '.$db->prefix().'workshop_vehicule_extrafields as te ON te.fk_object = t.rowid';
$sql .= ' INNER JOIN '.$db->prefix().'product as p ON o.fk_product = p.rowid';

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;

$sql .= ' WHERE 1 = 1';
$sql .= ' AND t.entity IN ('.getEntity('workshop', 1).')';
$sql .= ' AND t.status = 1';

if ($conf->entity != 1) {
	$sql .= " AND o.date_next < '".$db->idate(dol_time_plus_duree(dol_now(), (int) getDolGlobalInt("THEO_NB_MONTH_CHECKING_VEHICULE_BY_ANTICIPATION"), 'm'))."'";
	$sql .= ' AND t.atelier IN ('.$conf->entity.')';
}

// Search filters
if (!empty($search['t_vin'])) {
	$sql .= natural_search('t.vin', $search['t_vin']);
}
if (!empty($search['t_immatriculation'])) {
	$sql .= natural_search('t.immatriculation', $search['t_immatriculation']);
}
if (!empty($search['t_fk_vehicule_type'])) {
	$sql .= natural_search('t.fk_vehicule_type', $search['t_fk_vehicule_type'], 2);
}
if (!empty($search['t_fk_vehicule_mark'])) {
	$sql .= natural_search('t.fk_vehicule_mark', $search['t_fk_vehicule_mark'], 2);
}
if (!empty($search['t_fk_soc']) && $search['t_fk_soc'] > 0) {
	$sql .= ' AND t.fk_soc = '.((int) $search['t_fk_soc']);
}
if (!empty($search['t_fk_contract_type'])) {
	$sql .= natural_search('t.fk_contract_type', $search['t_fk_contract_type'], 2);
}
if ($search['t_km'] !== '' && $search['t_km'] !== null) {
	$sql .= natural_search('t.km', $search['t_km'], 1);
}
if (!empty($search['o_fk_product']) && is_array($search['o_fk_product'])) {
	$cleanIds = array_map('intval', $search['o_fk_product']);
	$sql .= ' AND o.fk_product IN ('.implode(',', $cleanIds).')';
}
if ($search['o_km'] !== '' && $search['o_km'] !== null) {
	$sql .= natural_search('o.km', $search['o_km'], 1);
}
if ($search['o_delai_from_last_op'] !== '' && $search['o_delai_from_last_op'] !== null) {
	$sql .= natural_search('o.delai_from_last_op', $search['o_delai_from_last_op'], 1);
}
if ($search['o_km_done'] !== '' && $search['o_km_done'] !== null) {
	$sql .= natural_search('o.km_done', $search['o_km_done'], 1);
}
if ($search['o_km_next'] !== '' && $search['o_km_next'] !== null) {
	$sql .= natural_search('o.km_next', $search['o_km_next'], 1);
}
if (!empty($search['o_on_time'])) {
	$sql .= natural_search('o.on_time', $search['o_on_time'], 2);
}
if ($search['o_or_next'] !== '' && $search['o_or_next'] !== null) {
	if ($search['o_or_next'] == 1) {
		$sql .= ' AND o.or_next > 0 AND o.or_next IS NOT NULL';
	} elseif ($search['o_or_next'] === '0') {
		$sql .= ' AND (o.or_next IS NULL OR o.or_next = 0)';
	}
}
if (!empty($search['p_label'])) {
	$sql .= natural_search('p.label', $search['p_label']);
}

// Date range filters
$dateFields = array(
	't_date_immat' => 't.date_immat',
	't_date_customer_exploit' => 't.date_customer_exploit',
	't_km_date' => 't.km_date',
	't_date_end_contract' => 't.date_end_contract',
	'o_date_done' => 'o.date_done',
	'o_date_next' => 'o.date_next',
	'o_date_due' => 'o.date_due',
);
foreach ($dateFields as $searchKey => $sqlField) {
	if (!empty($search[$searchKey.'_dtstart'])) {
		$sql .= " AND ".$sqlField." >= '".$db->idate($search[$searchKey.'_dtstart'])."'";
	}
	if (!empty($search[$searchKey.'_dtend'])) {
		$sql .= " AND ".$sqlField." <= '".$db->idate($search[$searchKey.'_dtend'])."'";
	}
}

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;

// Count total nb of records
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$sqlforcount = preg_replace('/GROUP BY .*$/', '', $sqlforcount);

	$resql = $db->query($sqlforcount);
	if ($resql) {
		$objforcount = $db->fetch_object($resql);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
	} else {
		dol_print_error($db);
	}

	if (($page * $limit) > $nbtotalofrecords) {
		$page = 0;
		$offset = 0;
	}
	$db->free($resql);
}

// Complete request and execute it with limit
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
llxHeader('', $title, $help_url, '', 0, 0, array(), array(), '', 'mod-workshop page-operationlist bodyforlist');

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
	if (is_array($val)) {
		foreach ($val as $skey) {
			if ($skey != '') {
				$param .= '&search_'.$key.'[]='.urlencode($skey);
			}
		}
	} elseif (preg_match('/(_dtstart|_dtend)$/', $key) && !empty($val)) {
		$param .= '&search_'.$key.'month='.GETPOSTINT('search_'.$key.'month');
		$param .= '&search_'.$key.'day='.GETPOSTINT('search_'.$key.'day');
		$param .= '&search_'.$key.'year='.GETPOSTINT('search_'.$key.'year');
	} elseif ($val != '') {
		$param .= '&search_'.$key.'='.urlencode($val);
	}
}
$parameters = array('param' => &$param);
$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object, $action);
$param .= $hookmanager->resPrint;

$arrayofmassactions = array();
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

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column);
$selectedfields = $htmlofselectarray;
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print_barre_liste($title, $page, dol_escape_htmltag($_SERVER["PHP_SELF"]), $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'object_generic', 0, '', '', $limit, 0, 0, 1);

$moreforfilter = '';
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object, $action);
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

// Fields title search
print '<tr class="liste_titre_filter">';
if ($conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">';
	print $form->showFilterButtons('left');
	print '</td>';
}
foreach ($arrayfields as $key => $val) {
	if (empty($val['checked'])) {
		continue;
	}
	print '<td class="liste_titre">';
	if ($key == 't.vin') {
		print '<input type="text" class="flat maxwidth75" name="search_t_vin" value="'.dol_escape_htmltag($search['t_vin']).'">';
	} elseif ($key == 't.fk_vehicule_type') {
		print $object->showInputField($object->fields['fk_vehicule_type'], 'fk_vehicule_type', $search['t_fk_vehicule_type'], '', '', 'search_t_', 'maxwidth150', 1);
	} elseif ($key == 't.fk_vehicule_mark') {
		print $object->showInputField($object->fields['fk_vehicule_mark'], 'fk_vehicule_mark', $search['t_fk_vehicule_mark'], '', '', 'search_t_', 'maxwidth150', 1);
	} elseif ($key == 't.immatriculation') {
		print '<input type="text" class="flat maxwidth75" name="search_t_immatriculation" value="'.dol_escape_htmltag($search['t_immatriculation']).'">';
	} elseif ($key == 't.fk_soc') {
		print $form->select_company($search['t_fk_soc'], 'search_t_fk_soc', '', 1, 0, 0, array(), 0, 'maxwidth200');
	} elseif ($key == 't.fk_contract_type') {
		print $object->showInputField($object->fields['fk_contract_type'], 'fk_contract_type', $search['t_fk_contract_type'], '', '', 'search_t_', 'maxwidth150', 1);
	} elseif ($key == 't.km') {
		print '<input type="text" class="flat maxwidth50" name="search_t_km" value="'.dol_escape_htmltag($search['t_km']).'">';
	} elseif ($key == 'o.fk_product') {
		print $form->multiselectarray('search_o_fk_product', $prod_array, (is_array($search['o_fk_product']) ? $search['o_fk_product'] : array()), '', 0, '', 0, '100%');
	} elseif ($key == 'p.label') {
		print '<input type="text" class="flat maxwidth75" name="search_p_label" value="'.dol_escape_htmltag($search['p_label']).'">';
	} elseif ($key == 'o.km') {
		print '<input type="text" class="flat maxwidth50" name="search_o_km" value="'.dol_escape_htmltag($search['o_km']).'">';
	} elseif ($key == 'o.delai_from_last_op') {
		print '<input type="text" class="flat maxwidth50" name="search_o_delai_from_last_op" value="'.dol_escape_htmltag($search['o_delai_from_last_op']).'">';
	} elseif ($key == 'o.km_done') {
		print '<input type="text" class="flat maxwidth50" name="search_o_km_done" value="'.dol_escape_htmltag($search['o_km_done']).'">';
	} elseif ($key == 'o.km_next') {
		print '<input type="text" class="flat maxwidth50" name="search_o_km_next" value="'.dol_escape_htmltag($search['o_km_next']).'">';
	} elseif ($key == 'o.on_time') {
		print $form->selectarray('search_o_on_time', array('1' => $langs->trans('VehiculeOperationOnTime')), $search['o_on_time'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
	} elseif ($key == 'o.or_next') {
		print $form->selectarray('search_o_or_next', array(0 => $langs->trans('Empty'), 1 => $langs->trans('NotEmpty')), $search['o_or_next'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
	} elseif (in_array($key, array('t.date_immat', 't.date_customer_exploit', 't.km_date', 't.date_end_contract'))) {
		$searchKeyMap = array(
			't.date_immat' => 't_date_immat',
			't.date_customer_exploit' => 't_date_customer_exploit',
			't.km_date' => 't_km_date',
			't.date_end_contract' => 't_date_end_contract',
		);
		$sk = $searchKeyMap[$key];
		print '<div class="nowrap">';
		print $form->selectDate($search[$sk.'_dtstart'] ? $search[$sk.'_dtstart'] : '', 'search_'.$sk.'_dtstart', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
		print '</div>';
		print '<div class="nowrap">';
		print $form->selectDate($search[$sk.'_dtend'] ? $search[$sk.'_dtend'] : '', 'search_'.$sk.'_dtend', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
		print '</div>';
	} elseif (in_array($key, array('o.date_done', 'o.date_next', 'o.date_due'))) {
		$searchKeyMap = array(
			'o.date_done' => 'o_date_done',
			'o.date_next' => 'o_date_next',
			'o.date_due' => 'o_date_due',
		);
		$sk = $searchKeyMap[$key];
		print '<div class="nowrap">';
		print $form->selectDate($search[$sk.'_dtstart'] ? $search[$sk.'_dtstart'] : '', 'search_'.$sk.'_dtstart', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
		print '</div>';
		print '<div class="nowrap">';
		print $form->selectDate($search[$sk.'_dtend'] ? $search[$sk.'_dtend'] : '', 'search_'.$sk.'_dtend', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
		print '</div>';
	} elseif ($key == 't.status') {
		// Status is always active=1, no filter
	}
	print '</td>';
}
if (!$conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">';
	print $form->showFilterButtons();
	print '</td>';
}
print '</tr>'."\n";

$totalarray = array();
$totalarray['nbfield'] = 0;

// Fields title label
print '<tr class="liste_titre">';
if ($conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, dol_escape_htmltag($_SERVER["PHP_SELF"]), '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
foreach ($arrayfields as $key => $val) {
	if (empty($val['checked'])) {
		continue;
	}
	$cssforfield = '';
	if (in_array($key, array('t.date_immat', 't.date_customer_exploit', 't.km_date', 't.date_end_contract', 'o.date_done', 'o.date_next', 'o.date_due', 't.status'))) {
		$cssforfield .= 'center';
	} elseif (in_array($key, array('t.km', 'o.km', 'o.km_done', 'o.km_next', 'o.delai_from_last_op'))) {
		$cssforfield .= 'right';
	}
	print getTitleFieldOfList($langs->trans($val['label']), 0, dol_escape_htmltag($_SERVER['PHP_SELF']), $key, '', $param, ($cssforfield ? 'class="'.$cssforfield.'"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''))."\n";
	$totalarray['nbfield']++;
}
$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder, 'totalarray' => &$totalarray);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object, $action);
print $hookmanager->resPrint;
if (!$conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, dol_escape_htmltag($_SERVER["PHP_SELF"]), '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
print '</tr>'."\n";


// Loop on record
$i = 0;
$savnbfield = $totalarray['nbfield'];
$totalarray = array();
$totalarray['nbfield'] = 0;
$imaxinloop = ($limit ? min($num, $limit) : $num);

$cacheThirdparties = array();
$cacheProducts = array();

while ($i < $imaxinloop) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break;
	}

	print '<tr data-rowid="'.$obj->t_rowid.'" class="oddeven">';

	if ($conf->main_checkbox_left_column) {
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction) {
			$selected = 0;
			if (in_array($obj->o_rowid, $arrayofselected)) {
				$selected = 1;
			}
			print '<input id="cb'.$obj->o_rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->o_rowid.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	foreach ($arrayfields as $key => $val) {
		if (empty($val['checked'])) {
			continue;
		}

		$cssforfield = '';
		if (in_array($key, array('t.date_immat', 't.date_customer_exploit', 't.km_date', 't.date_end_contract', 'o.date_done', 'o.date_next', 'o.date_due', 't.status'))) {
			$cssforfield = 'center';
		} elseif (in_array($key, array('t.km', 'o.km', 'o.km_done', 'o.km_next', 'o.delai_from_last_op'))) {
			$cssforfield = 'right';
		} elseif (in_array($key, array('t.fk_soc'))) {
			$cssforfield = 'tdoverflowmax150';
		}

		print '<td'.($cssforfield ? ' class="'.$cssforfield.'"' : '').'>';

		if ($key == 't.vin') {
			$vehiculeTmp = new Vehicule($db);
			$vehiculeTmp->id = $obj->t_rowid;
			$vehiculeTmp->vin = $obj->vin;
			$vehiculeTmp->immatriculation = $obj->immatriculation;
			$vehiculeTmp->status = $obj->t_status;
			print $vehiculeTmp->getNomUrl(1);
		} elseif ($key == 't.fk_vehicule_type') {
			print $object->showOutputField($object->fields['fk_vehicule_type'], 'fk_vehicule_type', $obj->fk_vehicule_type, '');
		} elseif ($key == 't.fk_vehicule_mark') {
			print $object->showOutputField($object->fields['fk_vehicule_mark'], 'fk_vehicule_mark', $obj->fk_vehicule_mark, '');
		} elseif ($key == 't.immatriculation') {
			print dol_escape_htmltag($obj->immatriculation);
		} elseif ($key == 't.date_immat') {
			print dol_print_date($db->jdate($obj->date_immat), 'day');
		} elseif ($key == 't.fk_soc') {
			if ($obj->fk_soc > 0) {
				if (!isset($cacheThirdparties[$obj->fk_soc])) {
					$soc = new Societe($db);
					$soc->fetch($obj->fk_soc);
					$cacheThirdparties[$obj->fk_soc] = $soc;
				}
				print $cacheThirdparties[$obj->fk_soc]->getNomUrl(1);
			}
		} elseif ($key == 't.date_customer_exploit') {
			print dol_print_date($db->jdate($obj->date_customer_exploit), 'day');
		} elseif ($key == 't.km') {
			print $obj->t_km;
		} elseif ($key == 't.km_date') {
			print dol_print_date($db->jdate($obj->km_date), 'day');
		} elseif ($key == 't.fk_contract_type') {
			print $object->showOutputField($object->fields['fk_contract_type'], 'fk_contract_type', $obj->fk_contract_type, '');
		} elseif ($key == 't.date_end_contract') {
			print dol_print_date($db->jdate($obj->date_end_contract), 'day');
		} elseif ($key == 't.status') {
			print Vehicule::LibStatut($obj->t_status, 5);
		} elseif ($key == 'o.fk_product') {
			if ($obj->fk_product > 0) {
				if (!isset($cacheProducts[$obj->fk_product])) {
					$prod = new Product($db);
					$prod->fetch($obj->fk_product);
					$cacheProducts[$obj->fk_product] = $prod;
				}
				print $cacheProducts[$obj->fk_product]->getNomUrl(1);
			}
		} elseif ($key == 'p.label') {
			print dol_escape_htmltag($obj->p_label);
		} elseif ($key == 'o.km') {
			print $obj->o_km;
		} elseif ($key == 'o.delai_from_last_op') {
			print $obj->delai_from_last_op;
		} elseif ($key == 'o.date_done') {
			print dol_print_date($db->jdate($obj->date_done), 'day');
		} elseif ($key == 'o.km_done') {
			print $obj->km_done;
		} elseif ($key == 'o.date_next') {
			print dol_print_date($db->jdate($obj->date_next), 'day');
		} elseif ($key == 'o.date_due') {
			print dol_print_date($db->jdate($obj->date_due), 'day');
		} elseif ($key == 'o.km_next') {
			print $obj->km_next;
		} elseif ($key == 'o.on_time') {
			if (!empty($obj->on_time)) {
				print dolGetBadge($langs->trans('VehiculeOperationOnTime'), '', 'danger');
			}
		} elseif ($key == 'o.or_next') {
			if (!empty($obj->or_next) && isModEnabled('operationorder') && class_exists('OperationOrder')) {
				$or = new OperationOrder($db);
				if ($or->fetch($obj->or_next, false) > 0) {
					print $or->getNomUrl(1);
				}
			}
		}

		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	$parameters = array('arrayfields' => $arrayfields, 'object' => $object, 'obj' => $obj, 'i' => $i, 'totalarray' => &$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object, $action);
	print $hookmanager->resPrint;

	if (empty($conf->main_checkbox_left_column)) {
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction) {
			$selected = 0;
			if (in_array($obj->o_rowid, $arrayofselected)) {
				$selected = 1;
			}
			print '<input id="cb'.$obj->o_rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->o_rowid.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	print '</tr>'."\n";

	$i++;
}

// If no record found
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
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object, $action);
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";

// End of page
llxFooter();
$db->close();
