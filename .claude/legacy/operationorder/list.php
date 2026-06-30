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

require 'config.php';
dol_include_once('operationorder/class/operationorder.class.php');

if (!$user->hasRight("operationorder", "read")) accessforbidden();

$langs->load('abricot@abricot');
$langs->load('operationorder@operationorder');

$massaction = GETPOST('massaction', 'alpha');
$confirmmassaction = GETPOST('confirmmassaction', 'alpha');
$toselect = GETPOST('toselect', 'array');
$search_by=GETPOST('search_by', 'alpha');
$total_ht_mo_min = GETPOST('search_total_ht_mo_min', 'alpha');
$total_ht_mo_max = GETPOST('search_total_ht_mo_max', 'alpha');
$total_ht_part_min = GETPOST('search_total_ht_part_min', 'alpha');
$total_ht_part_max = GETPOST('search_total_ht_part_max', 'alpha');
$total_ht_external_min = GETPOST('search_total_ht_external_min', 'alpha');
$total_ht_external_max = GETPOST('search_total_ht_external_max', 'alpha');
$total_ht_reimbursement_min = GETPOST('search_total_ht_reimbursement_min', 'alpha');
$total_ht_reimbursement_max = GETPOST('search_total_ht_reimbursement_max', 'alpha');
$total_ht_min = GETPOST('search_total_ht_min', 'alpha');
$total_ht_max = GETPOST('search_total_ht_max', 'alpha');
$search_km_on_creation_min = GETPOST('search_km_on_creation_min', 'int');
$search_km_on_creation_max = GETPOST('search_km_on_creation_max', 'int');
$search_tag = GETPOST('Listview_operationorder_search_categories', 'alpha');
$planned_date = GETPOST('Listview_operationorder_search_planned_date_start', 'none');
$search_meca = GETPOST('Listview_operationorder_search_fk_user_meca', 'int');
$search_fk_conducteur = GETPOST('Listview_operationorder_search_fk_conducteur', 'int');
$closing_date = GETPOST('Listview_operationorder_search_date_cloture_start', 'none');
$fk_vehicule_id=GETPOST('fk_vehicule_id', 'int');
$origin = GETPOST('origin', 'alpha');
$originid = GETPOST('originid', 'int');


$search_planned_date_null = 0;
if ($planned_date && empty($_POST['Listview_operationorder_search_planned_date_end']) && empty($_POST['Listview_operationorder_search_planned_date_startday']) && empty($_POST['Listview_operationorder_search_planned_date_startmonth']) && empty($_POST['Listview_operationorder_search_planned_date_startyear'])) {
	$search_planned_date_null = 1;
}

$search_closing_date_null = 0;
if ($closing_date && empty($_POST['Listview_operationorder_search_date_cloture_end']) && empty($_POST['Listview_operationorder_search_date_cloture_startday']) && empty($_POST['Listview_operationorder_search_date_cloture_startmonth']) && empty($_POST['Listview_operationorder_search_date_cloture_startyear'])) {
	$search_closing_date_null = 1;
}

$sall=GETPOST('search_all');
if (!empty($sall)) {
	$_GET['Listview_operationorder_search_ref'] = $sall;
}


$addFilterStatus='';
$search_overshootMultiStatus = GETPOST('search_status', 'array');
if (!empty($search_overshootMultiStatus)) {
	$addFilterStatus='&';
	foreach ($search_overshootMultiStatus as $key=>$item) {
		$addFilterStatus .= 'search_status[]=' . $item.'&';
	}
}
$object = new OperationOrder($db);

$hookmanager->initHooks(array('operationorderlist'));

if ($object->isextrafieldmanaged) {
	$extrafields = new ExtraFields($db);
	$extralabels = $extrafields->fetch_name_optionals_label($object->table_element);
}

$object->fields['categories']['visible']=1;

/*
 * Actions
 */

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (!empty($confirmmassaction) && $massaction != 'presend' && $massaction != 'confirm_presend') {
	if ($massaction == 'delete' && !empty($toselect) && $user->hasRight("operationorder", "delete")) {
		foreach ($toselect as $deleteId) {
			$objectToDelete = new OperationOrder($db);
			$res = $objectToDelete->fetch($deleteId);
			if ($res>0) {
				if ($objectToDelete->delete($user)<0) {
					setEventMessage($langs->trans('OperationOrderDeleteError', $objectToDelete->ref), 'errors');
				}
			} else {
				setEventMessage($langs->trans('OperationOrderNotFound'), 'warnings');
			}
		}

		header('Location: '.dol_buildpath('/operationorder/list.php', 1));
		exit;
	}

	$massaction = '';
}


if (empty($reshook)) {
	// do action from GETPOST ...
}


/*
 * View
 */

llxHeader('', $langs->trans('OperationOrderList'), '', '');
$urlorigin = '';
if ($origin=='operationorder' && !empty($originid)) {
	$urlorigin = '&origin='.$origin.'&originid='.$originid.'&fk_vehicule_id=' . $fk_vehicule_id;
	dol_include_once('operationorder/lib/operationorder.lib.php');
	$ORHEAD =  new OperationOrder($db);
	$ORHEAD->fetch($originid, true, '');

	$head = operationorder_prepare_head($ORHEAD);
	$picto = 'operationorder@operationorder';
	print dol_get_fiche_head($head, 'list', $langs->trans('OperationOrder'), -1, $picto);
	$linkback = '<a href="'.dol_buildpath('/operationorder/list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';
	$morehtmlref='<div class="refidno">';
	// Thirdparty
	$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $ORHEAD->thirdparty->getNomUrl(1);
	$morehtmlref.='</div>';
	dol_banner_tab($ORHEAD, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	print '<div class="underbanner clearboth"></div>';
}

if ($origin=='vehicule' && !empty($originid)) {
	$urlorigin = '&origin='.$origin.'&originid='.$originid;
	dol_include_once('dolifleet/lib/dolifleet.lib.php');
	dol_include_once('dolifleet/class/vehicule.class.php');
	$VHHEAD = new Vehicule($db);
	$VHHEAD->fetch($originid, true, '');
	$head = vehicule_prepare_head($VHHEAD);
	$picto = 'dolifleet@dolifleet';
	print dol_get_fiche_head($head, 'list', $langs->trans('doliFleet'), 0, $picto);
	printBannerVehicleCard($VHHEAD);
	print '<div class="underbanner clearboth"></div>';
	print '</div>';
}

$keys = array_keys($object->fields);
$fieldList = 't.'.implode(', t.', $keys);
if (!empty($object->isextrafieldmanaged)) {
	$keys = array_keys($extralabels);
	if (!empty($keys)) {
		$fieldList .= ', et.' . implode(', et.', $keys);
	}
}

$listViewName = 'operationorder';
$inputPrefix  = 'Listview_'.$listViewName.'_search_';

// Search value
$search_overshootStatus = GETPOST($inputPrefix.'overshootstatus', 'int');
if (GETPOSTISSET('button_removefilter_x')) {
	$search_overshootStatus = '';
	$search_tag = '';
	$search_type = '';
	$search_meca = '';
	$total_ht_mo_min ='';
	$total_ht_mo_max = '';
	$total_ht_part_min = '';
	$total_ht_part_max = '';
	$total_ht_external_min = '';
	$total_ht_external_max = '';
	$total_ht_reimbursement_min ='';
	$total_ht_reimbursement_max = '';
	$total_ht_min = '';
	$total_ht_max = '';
	$planned_date = '';
	$search_planned_date_null = '';
	$search_closing_date_null = '';
	$search_fk_conducteur = '';
	$search_km_on_creation_min = '';
	$search_km_on_creation_max = '';
	$closing_date = '';
	$planned_date = '';
}

$ARRAY_EMPTY_SEL['-2'] = '(vide)';

$TAG=array();
$sqltag = 'SELECT DISTINCT label,code,color ';
$sqltag.= 'FROM '. $db->prefix() . 'c_operationorder_tag ';
$sqltag.= 'WHERE active = 1';

$resqltag = $db->query($sqltag);
if ($resqltag) {
	while ($obj = $db->fetch_object($resqltag)) {
		$TAG[$obj->code] = $obj->label;
		$col[$obj->code] = $obj->color;
	}
}
if (!empty($TAG)) {
	$TAG = array_merge($ARRAY_EMPTY_SEL, $TAG);
}

$TYPE=array();
$sqltype = 'SELECT DISTINCT label,code ';
$sqltype.= 'FROM '. $db->prefix() . 'c_operationorder_type ';
$sqltype.= 'WHERE active = 1';
$resqltype = $db->query($sqltype);
if ($resqltype) {
	while ($obj = $db->fetch_object($resqltype)) {
		$TYPE[$obj->code] = $obj->label;
	}
}
if (empty($TYPE)) {
	$TYPE = $ARRAY_EMPTY_SEL + $TYPE;
}

$MECA = array();
$sqlmeca = 'SELECT u.rowid as rowid, concat(u.firstname, " ", u.lastname) AS name  ';
$sqlmeca.= 'FROM '. $db->prefix() . 'usergroup_user AS g ';
$sqlmeca.= 'INNER JOIN '. $db->prefix() . 'user as u ON u.rowid = g.fk_user ';
if ($conf->entity <>1) {
	$sqlmeca.= 'WHERE g.entity = ' . $conf->entity . ' AND g.fk_usergroup = ' . getDolGlobalInt("OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING");
} else {
	$sqlmeca.= 'WHERE g.fk_usergroup = ' . getDolGlobalInt("OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING");
}
$resqlmeca = $db->query($sqlmeca);
if ($resqlmeca) {
	while ($obj = $db->fetch_object($resqlmeca)) {
		$MECA[$obj->rowid] = $obj->name;
	}
}
if (empty($MECA)) {
	$MECA = $ARRAY_EMPTY_SEL + $MECA;
}

$DRIVER=array();
$sqldriver = 'SELECT DISTINCT s.rowid as rowid, CONCAT(s.lastname, " ", s.firstname) as name ';
$sqldriver.= 'FROM '. $db->prefix() . 'socpeople AS s ';
$sqldriver.= 'INNER JOIN '. $db->prefix() . 'socpeople_extrafields AS ef ON ef.fk_object = s.rowid ';
$sqldriver.= 'WHERE s.statut = 1 and ef.driver = 1';
$resqldriver = $db->query($sqldriver);
if ($resqldriver) {
	while ($obj = $db->fetch_object($resqldriver)) {
		$DRIVER[$obj->rowid] = $obj->name;
	}
}
if (empty($DRIVER)) {
	$DRIVER = $ARRAY_EMPTY_SEL + $DRIVER;
}
$sql = 'SELECT '.$fieldList;

// Add fields from hooks
$parameters=array('sql' => $sql);
$reshook=$hookmanager->executeHooks('printFieldListSelect', $parameters, $object);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

// overshootStatus
$sqlSub = ' (SELECT (SUM(subsel.time_spent) - SUM(subsel.time_planned)) ';
$sqlSub.= ' FROM '.$db->prefix().'operationorderdet subsel ';
$sqlSub.= ' WHERE subsel.fk_operation_order = t.rowid ) as overshootstatus ';
$sql.= ' ,'.$sqlSub;

$sql.= ' FROM '.$db->prefix().'operationorder t ';

if (!empty($object->isextrafieldmanaged)) {
	$sql.= ' LEFT JOIN '.$db->prefix().'operationorder_extrafields et ON (et.fk_object = t.rowid)';
}

$sql.= ' LEFT JOIN '.$db->prefix().'societe s ON (s.rowid = t.fk_soc)';
$sql.= ' LEFT JOIN '.$db->prefix().'operationorder_status ost ON (ost.rowid = t.status)';
$sql.= ' LEFT JOIN '.$db->prefix().'dolifleet_vehicule v ON v.rowid = t.fk_vehicule';
$parameters=array('sql' => $sql);
$reshook=$hookmanager->executeHooks('printFieldListJoin', $parameters, $object);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

$sql.= ' WHERE  t.entity IN ('.getEntity('operationorder', 1).')';
//if ($type == 'mine') $sql.= ' AND t.fk_user = '.$user->id;

if (!empty($search_overshootMultiStatus) && count($search_overshootMultiStatus)>0) {
	$sql.= ' AND ost.code IN (\''.implode('\',\'', $search_overshootMultiStatus).'\')';
}
if (array_key_exists($search_tag, $TAG) && $search_tag <> -2 && $search_tag <> -1) {
	$sql.= ' AND t.categories LIKE (\'%'.$search_tag.'%\')';
} elseif ($search_tag == -2) {
	$sql.= ' AND (t.categories IS NULL OR t.categories = "")' ;
}
//if (array_key_exists($search_type, $TYPE) && $search_type <> -2 && $search_type <> -1) {
//	$sql.= ' AND ctype.code =\''.$search_type.'\'';
//} elseif ($search_type == -2) {
//	$sql.= ' AND (ctype.code IS NULL OR ctype.code = "" )' ;
//}
if ($search_planned_date_null == 1) {
	$sql.= ' AND ( t.planned_date IS NULL )' ;
}
if ($search_closing_date_null == 1) {
	$sql.= ' AND ( t.date_cloture IS NULL )' ;
}
if (array_key_exists($search_meca, $MECA) && $search_meca <> -2 && $search_meca <> -1) {
	$sql.= ' AND t.fk_user_meca =\''.$search_meca.'\'';
} elseif ($search_meca == -2) {
	$sql.= ' AND (t.fk_user_meca IS NULL OR t.fk_user_meca = "" )' ;
}
if (!empty($total_ht_min)) {
	$sql.= ' AND  t.total_ht >= ' .$total_ht_min;
}
if (!empty($total_ht_max)) {
	$sql.= ' AND  t.total_ht <= ' .$total_ht_max;
}

if (!empty($total_ht_mo_min)) {
	$sql.= ' AND  t.total_ht_mo >= ' .$total_ht_mo_min;
}
if (!empty($total_ht_mo_max)) {
	$sql.= ' AND  t.total_ht_mo <= ' .$total_ht_mo_max;
}

if (!empty($total_ht_part_min)) {
	$sql.= ' AND  t.total_ht_part >= ' .$total_ht_part_min;
}
if (!empty($total_ht_part_max)) {
	$sql.= ' AND  t.total_ht_part <= ' .$total_ht_part_max;
}

if (!empty($total_ht_external_min)) {
	$sql.= ' AND  t.total_ht_external >= ' .$total_ht_external_min;
}
if (!empty($total_ht_external_max)) {
	$sql.= ' AND  t.total_ht_external <= ' .$total_ht_external_max;
}

if (!empty($total_ht_reimbursement_min)) {
	$sql.= ' AND  t.total_ht_reimbursement >= ' .$total_ht_reimbursement_min;
}
if (!empty($total_ht_reimbursement_max)) {
	$sql.= ' AND  t.total_ht_reimbursement <= ' .$total_ht_reimbursement_max;
}

if (array_key_exists($search_fk_conducteur, $DRIVER) && $search_fk_conducteur <> -2 && $search_fk_conducteur <> -1) {
	$sql .= ' AND  t.fk_conducteur =\'' . $search_fk_conducteur . '\'';
}
//} elseif ($search_type == -2) {
//	$sql.= ' AND ( t.fk_conducteur IS NULL OR ctype.code = "" )' ;
//}

if (!empty($search_km_on_creation_min)) {
	$sql.= ' AND  t.km_on_creation >= ' .$search_km_on_creation_min;
}
if (!empty($search_km_on_creation_max)) {
	$sql.= ' AND  t.km_on_creation <= ' .$search_km_on_creation_max;
}
if ($origin=='vehicule' && !empty($originid)) {
	$sql.= ' AND  v.rowid = ' .$originid;
}

if ($origin=='operationorder' && !empty($fk_vehicule_id)) {
	$sql.= ' AND  v.rowid = ' .$fk_vehicule_id;
}


if (!empty($search_overshootStatus) && $search_overshootStatus > 0) {
	$sqlSub = ' (SELECT (SUM(sub.time_spent) - SUM(sub.time_planned)) ';
	$sqlSub.= ' FROM '.$db->prefix().'operationorderdet sub ';
	$sqlSub.= ' WHERE sub.fk_operation_order = t.rowid ) ';

	if (intval($search_overshootStatus) === 2) {
		$sqlSub.= ' >= 0 ';
	} else {
		$sqlSub.= ' < 0 ';
	}

	$sql.= ' AND '.$sqlSub;
}

// Add where from hooks
$parameters=array('sql' => $sql);
$reshook=$hookmanager->executeHooks('printFieldListWhere', $parameters, $object);    // Note that $action and $object may have been modified by hook

$formcore = new TFormCore($_SERVER['PHP_SELF'], 'form_list_operationorder', 'POST');
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
$nbLine = GETPOST('limit');
if (empty($nbLine)) $nbLine = !empty($user->conf->MAIN_SIZE_LISTE_LIMIT) ? $user->conf->MAIN_SIZE_LISTE_LIMIT : getDolGlobalString("MAIN_SIZE_LISTE_LIMIT");

// TODO : add this to a OperationOrderStatus method
// prepare status cache
$statusStatic = new OperationOrderStatus($db);
$TStatusList = $statusStatic->fetchAll(0, false, array('status' => 1, 'entity' => getEntity('operationorder')));
$TStatusSearchList = array(); // for search form
if (!empty($TStatusList)) {
	foreach ($TStatusList as $status) {
		if (!isset($TStatusSearchList[$status->code])) {
			$TStatusSearchList[$status->code] = $status->label;
		}
	}
}
$htmlName = 'overshootstatus';
$selectArray = array(
	2 => $langs->trans('overshootStatus_Over'),
	1 => $langs->trans('overshootStatus_inTime'),
);

$formOvershootStatus = $form->selectarray($inputPrefix.$htmlName, $selectArray, $search_overshootStatus, 1);
$formOvershootMultiStatus = $form->multiselectarray('search_status', $TStatusSearchList, $search_overshootMultiStatus);

$TMassactions = array();
if ($user->hasRight("operationorder", "delete")) $TMassactions['delete']  = $langs->trans('Delete');

// List configuration
$listViewConfig = array(
	'view_type' => 'list' // default = [list], [raw], [chart]
	,'allow-fields-select' => true
	,'limit'=>array(
		'nbLine' => $nbLine
	)
	,'list' => array(
		'title' => $langs->trans('OperationOrderList')
		,'image' => 'title_generic.png'
		,'picto_precedent' => '<'
		,'picto_suivant' => '>'
		,'noheader' => 0
		,'messageNothing' => $langs->trans('NoOperationOrder')
		,'picto_search' => img_picto('', 'search.png', '', 0)
		,'massactions'=> $TMassactions
		,'param_url' => $addFilterStatus .$urlorigin
	)
	,'subQuery' => array()
	,'link' => array()
	,'type' => array(
		'date_creation' => 'date' // [datetime], [hour], [money], [number], [integer]
		,'planned_date' => 'date' // [datetime], [hour], [money], [number], [integer]
		,'date_operation_order' => 'date'
		,'date_cloture' => 'date'
	)
	,'search' => array(
		'ref' => array('search_type' => true, 'table' => 't', 'field' => 'ref')
		,'ref_client' => array('search_type' => true, 'table' => 't', 'field' => 'ref_client')
		,'fk_soc' => array('search_type' => true, 'table' => 's', 'field' => array('nom','name_alias'))
		,'fk_vehicule' => array('search_type' => true, 'table' => 'v', 'field' => array('vin','immatriculation'))
		,'date_creation' => array('search_type' => 'calendars', 'allow_is_null' => false, 'table' => 't')
		,'date_operation_order' => array('search_type' => 'calendars', 'allow_is_null' => false, 'table' => 't')
		,'date_cloture' => array('search_type' => 'calendars', 'table' => 't',)
		,'fk_user_meca' => array('search_type' => $MECA,  'no-auto-sql-search'=>1,)
		,'total_ht_mo' => array('search_type' => 'override', 'override'=> _getMinMax('search_total_ht_mo'))
		,'total_ht_part' => array('search_type' => 'override', 'override'=> _getMinMax('search_total_ht_part'))
		,'total_ht_external' => array('search_type' => 'override', 'override'=> _getMinMax('search_total_ht_external'))
		,'total_ht_reimbursement' => array('search_type' => 'override', 'override'=> _getMinMax('search_total_ht_reimbursement'))
		,'total_ht' => array('search_type' => 'override', 'override'=> _getMinMax('search_total_ht'))

		,'fk_conducteur' => array('search_type' => $DRIVER, 'no-auto-sql-search'=>1,)
		,'km_on_creation'=> array('search_type' => 'override', 'override'=> _getMinMax('search_km_on_creation'))
		,'categories' => array('search_type' => $TAG, 'no-auto-sql-search'=>1,)
		,'status' => array('search_type' => 'override', 'no-auto-sql-search'=>1, 'override' => $formOvershootMultiStatus) // select html, la clé = le status de l'objet, 'to_translate' à true si nécessaire
		,'overshootstatus' => array('search_type' => 'override', 'no-auto-sql-search'=>1, 'override' => $formOvershootStatus)
		,'planned_date' => array('search_type' => 'calendars', 'table' => 't')
	)
	,'translate' => array()
	,'hide' => array(
		'rowid',// important : rowid doit exister dans la query sql pour les checkbox de massaction
	)

	,'title'=>array (
		'ref' => $langs->trans($object->fields['ref']['label']),
		'ref_client' => $langs->trans($object->fields['ref_client']['label']),
		'fk_soc' => $langs->trans($object->fields['fk_soc']['label']),
		'fk_vehicule' => $langs->trans($object->fields['fk_vehicule']['label']),
		'overshootstatus' => $langs->trans('overshootStatus')
	)
	,'eval'=>array(
		'overshootstatus' => '_getOvershootStatus(\'@rowid@\')'
		,'categories' => '_gettags(\'@categories@\')'
	)
	, 'sortfield'=> 'date_creation', 'sortorder' => 'desc'
);

foreach ($object->fields as $key => $field) {
	// visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create).
	// Using a negative value means field is not shown by default on list but can be selected for viewing)

	if ($key == 'fk_project' && empty(isModEnabled("projet"))) {
		$field['enabled'] = 0;
	}

	if (!empty($field['enabled']) && !isset($listViewConfig['title'][$key]) && !empty($field['visible']) && in_array($field['visible'], array(1, 2, 4, 5)) ) {
		$listViewConfig['title'][$key] = $langs->trans($field['label']);
	}

	if (!isset($listViewConfig['hide'][$key]) && (empty($field['visible']) || $field['visible'] <= -1)) {
		$listViewConfig['hide'][] = $key;
	}

	if (!isset($listViewConfig['eval'][$key])) {
		$listViewConfig['eval'][$key] = '_getObjectOutputField(\''.$key.'\', \'@rowid@\', \'@val@\')';
	}
}

// Extrafields
if (!empty($object->isextrafieldmanaged) && !empty($extralabels)) {
	if (is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label']) > 0) {
		foreach ($extrafields->attributes[$object->table_element]['label'] as $key=>$label) {
			$enabled = 1;

			// skip separation
			if ($extrafields->attributes[$object->table_element]['type'][$key] == 'separate') {
				continue;
			}

			// skip hidden
			if (!empty($extrafields->attributes[$object->table_element]['hidden'][$key])) {
				continue;
			}

			$visibility = 1;
			if ($visibility && isset($extrafields->attributes[$object->table_element]['list'][$key])) {
				$visibility = dol_eval($extrafields->attributes[$object->table_element]['list'][$key], 1);
			}

			$perms = 1;
			if ($perms && isset($extrafields->attributes[$object->table_element]['perms'][$key])) {
				$perms = dol_eval($extrafields->attributes[$object->table_element]['perms'][$key], 1);
			}

			if (abs($visibility) != 1 && abs($visibility) != 2 && abs($visibility) != 5) continue; // <> -1 and <> 1 and <> 3 = not visible on forms, only on list

			if (empty($perms)) continue;

			// Load language if required
			if (!empty($extrafields->attributes[$object->table_element]['langfile'][$key])) $langs->load($extrafields->attributes[$object->table_element]['langfile'][$key]);

			$labeltoshow = $langs->trans($label);
			//if (!empty($extrafields->attributes[$object->table_element]['help'][$key])) $labeltoshow = $form->textwithpicto($labeltoshow, $extrafields->attributes[$object->table_element]['help'][$key]);

			$listKeyName = "options_".$key;

			if ($visibility<0) {
				$listViewConfig['hide'][] = $listKeyName;
			}

			$listViewConfig['title'][$listKeyName] = $labeltoshow;
			$listViewConfig['eval'][$listKeyName] = '_getObjectExtrafieldOutputField(\''.$key.'\', \'@rowid@\', \'@val@\')';

			// Search value
			$searchValue = GETPOST($inputPrefix.$listKeyName);
			if (GETPOSTISSET('button_removefilter_x')) {
				$searchValue = '';
			}

			$listViewConfig['search'][$listKeyName] = array(
				'search_type' => 'override',
				'table' => array('et', 'et'),
				'field' => array($key),
				'override' => $extrafields->showInputField($key, $searchValue, '', '', $inputPrefix, 0, $object->id, $object->table_element)
			);

			if (in_array($extrafields->attributes[$object->table_element]['type'][$key], array('link'))) {
				$listViewConfig['operator'][$listKeyName] = '=';
			}
		}
	}
}

// Multicompagny
if (!empty(isModEnabled("multicompany"))) {
	$listViewConfig['title']['entity'] = $langs->trans('Entity');
	$listViewConfig['eval']['entity'] = '_getEntity(\'@entity@\')';

	$aMulticompany = new ActionsMulticompany($db);

	$selected = GETPOST('Listview_operationorder_search_entity');
	if (empty($selected)) {
		$selected = -1;
	}

	$listViewConfig['search']['entity'] = array(
		'search_type' => 'override',
		'table' => array('t', 't'),
		'field' => array('entity'),
		'override' => $aMulticompany->select_entities($selected, 'Listview_operationorder_search_entity', '', false, false, 1, false, '', 'minwidth200imp', true, true)
	);
}

// Keep status as last col
if (isset($listViewConfig['title']['status'])) { unset($listViewConfig['title']['status']); }
$listViewConfig['title']['status'] = $langs->trans($object->fields['status']['label']);


$r = new Listview($db, 'operationorder');

// Change view from hooks
$parameters=array('listViewConfig' => $listViewConfig);
$reshook=$hookmanager->executeHooks('listViewConfig', $parameters, $r);    // Note that $action and $object may have been modified by hook
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
if ($reshook>0) {
	$listViewConfig = $hookmanager->resArray;
}
print '<input type="hidden" name="fk_vehicule_id" value="'.GETPOST('fk_vehicule_id').'"/>';
print '<input type="hidden" name="origin" value="'.GETPOST('origin').'"/>';
print '<input type="hidden" name="originid" value="'.GETPOST('originid').'"/>';
//print $sql;
echo $r->render($sql, $listViewConfig);
$addUrl=array();
if (!empty($_POST)) {
	$TExclude = array('token','massaction','button_search_x');
	foreach ($_POST as $key => $v) {
		if (!in_array($key, $TExclude)
			&& preg_match('/search/', $key)
			&& !empty($v)
			&& $v != -1
		) {
			if (is_array($v)) {
				foreach ($v as $item) $addUrl[]=$key.'[]='.$item;
			} else {
				$addUrl[]=$key.'='.$v;
			}
		}
	}
}

?>
<script>
	// var url =
	let url = '<?php echo DOL_URL_ROOT?>/bookmarks/card.php?action=create&url='
	url+="<?php echo urlencode($_SERVER['PHP_SELF'].'?'.implode("&", $addUrl)); ?>"
	$('#boxbookmark option[value="newbookmark"]').attr('rel', url);
	console.log(url)
</script>
<?php

$parameters=array('sql'=>$sql);
$reshook=$hookmanager->executeHooks('printFieldListFooter', $parameters, $object);    // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

$formcore->end_form();

llxFooter('');
$db->close();

function _getObjectIntputField($key, $val = '')
{
	global $db;
	dol_include_once('operationorder/class/operationorder.class.php');
	$operationOrder = new OperationOrder($db);
	return $operationOrder->showInputField($operationOrder->fields[$key], $val, $key, '', '', '', '', 1);
}

function _getObjectOutputField($key, $fk_operationOrder = 0, $val = '')
{
	$operationOrder = getOperationOrderFromCache($fk_operationOrder);
	if (!$operationOrder) {return 'error';}

	return $operationOrder->showOutputFieldQuick($key);
}

function _getOvershootStatus($fk_operationOrder = 0)
{
	$operationOrder = getOperationOrderFromCache($fk_operationOrder);
	if (!$operationOrder) {return 'error';}

	return $operationOrder->getOvershootStatus();
}

function getOperationOrderFromCache($fk_operationOrder)
{
	global $db, $TOperationOrderCache;


	if (empty($TOperationOrderCache[$fk_operationOrder])) {
		$operationOrder = new OperationOrder($db);
		if ($operationOrder->fetch($fk_operationOrder, false) <= 0) {
			return false;
		}

		$TOperationOrderCache[$fk_operationOrder] = $operationOrder;
	} else {
		$operationOrder = $TOperationOrderCache[$fk_operationOrder];
	}

	return $operationOrder;
}


function _getObjectExtrafieldOutputField($key, $fk_operationOrder = 0)
{
	global $extrafields;

	$operationOrder = getOperationOrderFromCache($fk_operationOrder);
	if (!$operationOrder) {return 'error';}

	$value = $operationOrder->array_options["options_".$key];

	return  $extrafields->showOutputField($key, $value, '', $operationOrder->table_element);
}

function _getEntity($val = '')
{
	global $db, $TEntityCache;

	if (empty($val)) {
		return '';
	}
	$val = intval($val);

	if (empty($TEntityCache[$val])) {
		$daoMulticompany = new DaoMulticompany($db);
		if ($daoMulticompany->fetch(intval($val)) <= 0) {
			return '';
		}

		$TEntityCache[$val] = $daoMulticompany;
	} else {
		$daoMulticompany = $TEntityCache[$val];
	}

	return  htmlentities($daoMulticompany->name);
}

Function _getMinMax($key)
{
	$out = '<input type="text" name="'.$key.'_min" id="'.$key.'_min" value="'.GETPOST($key.'_min', 'int').'" size="10" />';
	$out.= '<input type="text" name="'.$key.'_max" id="'.$key.'_max" value="'.GETPOST($key.'_max', 'int').'" size="10" />';
	return $out;
}
Function _gettags($val)
{
	global $db;
	$TAG=$col=[];
	$selected = explode(',', $val);
	$sqltag = "SELECT DISTINCT label,code,color ";
	$sqltag.= "FROM llx_c_operationorder_tag ";
	$sqltag.= "WHERE active = 1";
	$resql = $db->query($sqltag);
	if ($resql) {
		$num=$db->num_rows($resql);
		if ($num>0) {
			while ($obj = $db->fetch_object($resql)) {
				$TAG[$obj->code] = $obj->label;
				$col[$obj->code] = $obj->color;
			}
		}
	}
	$res = '';
	if (!empty($selected)) {
		foreach ($selected as $sel) {
			if (!empty($sel)) {
				$res .= dolGetBadge($TAG[$sel], '', $col[$sel]);
			}
		}
	}
	return $res;
}
