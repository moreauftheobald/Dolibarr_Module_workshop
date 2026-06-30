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
dol_include_once('operationorder/lib/operationorder.lib.php');
dol_include_once('operationorder/class/operationordertasktime.class.php');
dol_include_once('product/class/product.class.php');

if (!$user->hasRight("operationorder", "read")) accessforbidden();

$langs->load('abricot@abricot');
$langs->load('operationorder@operationorder');

$id = GETPOST('id', 'int');
$ref = GETPOST('ref');
$action = GETPOST('action', 'alpha');
$timeid = GETPOST('timeid', 'int');

$operationOrder = new OperationOrder($db);

if (!empty($id) || !empty($ref)) {
	$operationOrder->fetch($id, true, $ref);
//	$result = restrictedArea($user, $operationOrder->element, $id, $operationOrder->table_element . '&' . $operationOrder->element);
}
$hookmanager->initHooks(array('operationorderpointagelist'));

/*
 * Action
 */
if (!empty($timeid)) {
	if ($action == 'move_time') {
		$opetasktime = new OperationOrderTaskTime($db);
		$res = $opetasktime->fetch($timeid);
		if ($res < 0) {
			setEventMessages($opetasktime->error, $opetasktime->errors, 'errors');
		} else {
			$opetasktime->fk_orDet=GETPOST('newtimelineid', 'int');
			$resultUpd = $opetasktime->update($user);
			if ($resultUpd < 0) {
				setEventMessages($opetasktime->error, $opetasktime->errors, 'errors');
			} else {
				$line_and_total_time =array();

				$sql = 'SELECT d.rowid, SUM(p.task_duration) as duration ';
				$sql .= ' FROM ' . $db->prefix() . 'operationorderdet AS d ';
				$sql .= ' INNER JOIN ' . $db->prefix() . 'operationordertasktime as p on p.fk_orDet = d.rowid ';
				$sql .= ' WHERE  p.entity IN (' . getEntity('operationorder', 1) . ')';
				$sql .= ' AND d.fk_operation_order = ' . $operationOrder->id;
				$sql .= ' GROUP BY d.rowid';

				$resql = $db->query($sql);
				if (!$resql) {
					setEventMessages($db->lasterror, 'errors');
				} else {
					while ($objptime = $db->fetch_object($resql)) {
						$line_and_total_time[$objptime->rowid] = $objptime->duration;
					}
				}
				if (!empty($line_and_total_time)) {
					foreach ($line_and_total_time as $lineidordet=>$duration) {
						$ordet = new operationorderLine($db);
						$ordet->fetch($lineidordet);
						$resultUpdLine = $operationOrder->updateline($ordet->id,
							$ordet->description,
							$ordet->qty,
							$ordet->price,
							$ordet->fk_warehouse,
							$ordet->pr,
							$ordet->time_planned,
							$duration,
							$ordet->fk_product,
							0,
							$ordet->date_start,
							$ordet->date_end,
							$ordet->type,
							$ordet->fk_parent_line,
							$ordet->label,
							$ordet->special_code,
							$ordet->array_options);
						if ($resultUpdLine<0) {
							setEventMessages($operationOrder->error, $operationOrder->errors, 'errors');
						}
					}

					foreach ($operationOrder->lines as $opedet) {
						$ordet = new operationorderLine($db);
						$ordet->fetch($opedet->id);
						if (!array_key_exists($ordet->id, $line_and_total_time) ) {
							$resultUpdLine = $operationOrder->updateline($ordet->id,
								$ordet->description,
								$ordet->qty,
								$ordet->price,
								$ordet->fk_warehouse,
								$ordet->pr,
								$ordet->time_planned,
								0,
								$ordet->fk_product,
								0,
								$ordet->date_start,
								$ordet->date_end,
								$ordet->type,
								$ordet->fk_parent_line,
								$ordet->label,
								$ordet->special_code,
								$ordet->array_options);
							if ($resultUpdLine < 0) {
								setEventMessages($operationOrder->error, $operationOrder->errors, 'errors');
							}
						}
					}

					$operationOrder->fetch($operationOrder->id);
					$resultUpd = $operationOrder->update($user);

					if ($resultUpd < 0) {
						setEventMessages($opetasktime->error, $opetasktime->errors, 'errors');
					} else {
						header('Location: ' . dol_buildpath('/operationorder/operationorder_pointage.php', 1) . '?id=' . $id);
						exit;
					}
				}
			}
		}
	}

	if ($action == 'split_time') {
		$opetasktime = new OperationOrderTaskTime($db);
		$res = $opetasktime->fetch($timeid);
		if ($res < 0) {
			setEventMessages($opetasktime->error, $opetasktime->errors, 'errors');
		}
		$durationtouse = ((int) GETPOST('timespent_durationhour', 'int') * 3600 + (int) GETPOST('timespent_durationmin', 'int') * 60);
		if ($durationtouse >= $opetasktime->task_duration) {
			setEventMessages($langs->trans('CannotSplitMoreThanActualTime'), null, 'errors');
			$action = 'split';
			$_POST['action'] = $action;
		} else {
			$origin_en_time = $opetasktime->task_datehour_f;
			$timeend = dol_time_plus_duree($opetasktime->task_datehour_d, GETPOST('timespent_durationhour', 'int'), 'h');
			$timeend = dol_time_plus_duree($timeend, GETPOST('timespent_durationmin', 'int'), 'i');
			$opetasktime->task_datehour_f = $timeend;
			$opetasktime->task_duration = $opetasktime->task_datehour_f - $opetasktime->task_datehour_d;
			$resultUpd = $opetasktime->update($user);
			if ($resultUpd < 0) {
				setEventMessages($opetasktime->error, $opetasktime->errors, 'errors');
			} else {
				$opetasktime->cloneObject($user);
				$opetasktime->task_datehour_d = $timeend + 1;
				$opetasktime->task_datehour_f = $origin_en_time;
				$opetasktime->task_duration = $opetasktime->task_datehour_f - $opetasktime->task_datehour_d;
				$resultUpd = $opetasktime->update($user);
				if ($resultUpd < 0) {
					setEventMessages($opetasktime->error, $opetasktime->errors, 'errors');
				} else {
					header('Location: ' . dol_buildpath('/operationorder/operationorder_pointage.php', 1) . '?id=' . $id);
					exit;
				}
			}
		}
	}
}


/*
 * View
 */

llxHeader('', $langs->trans('OperationOrderPointageList'), '', '');

//$type = GETPOST('type');
//if (!$user->hasRight("operationorder","all","read")) $type = 'mine';

if ($operationOrder->id > 0) {
	$head = operationorder_prepare_head($operationOrder);
	$picto = 'operationorder@operationorder';
	print dol_get_fiche_head($head, 'pointage', $langs->trans('Pointages'), -1, $picto);
	$linkback = '<a href="' . dol_buildpath('/operationorder/list.php', 1) . '?restore_lastsearch_values=1' . (!empty($socid) ? '&socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';
	$morehtmlref = '<div class="refidno">';
	// Ref customer
	$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $operationOrder->ref_client, $operationOrder, 0, 'string', '', 0, 1);
	$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $operationOrder->ref_client, $operationOrder, 0, 'string', '', null, null, '', 1);
	// Thirdparty
	$morehtmlref .= '<br>' . $langs->trans('ThirdParty') . ' : ' . $operationOrder->thirdparty->getNomUrl(1);
	// Project
	if (!empty(isModEnabled("projet"))) {
		$langs->load("projects");
		$morehtmlref .= '<br>' . $langs->trans('Project') . ' ';

		if (!empty($object->fk_project)) {
			$proj = new Project($db);
			$proj->fetch($operationOrder->fk_project);
			$morehtmlref .= '<a href="' . DOL_URL_ROOT . '/projet/card.php?id=' . $operationOrder->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
			$morehtmlref .= $proj->ref;
			$morehtmlref .= '</a>';
		} else {
			$morehtmlref .= '';
		}
	}
	$morehtmlref .= '</div>';
	dol_banner_tab($operationOrder, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	print '<div class="underbanner clearboth"></div>';
}

$sql = 'SELECT p.rowid, p.fk_user as fk_user,p.task_datehour_d as date_d,p.task_datehour_f as date_f  ,(p.task_duration/3600) as duration, d.fk_product as fk_product, d.description as description,';
$sql .= ' d.rowid as drowid, d.fk_operation_order';
$sql .= ' FROM ' . $db->prefix() . 'operationorderdet AS d ';
$sql .= ' INNER JOIN ' . $db->prefix() . 'operationordertasktime as p on p.fk_orDet = d.rowid ';
$sql .= ' WHERE  p.entity IN (' . getEntity('operationorder', 1) . ')';
if ($operationOrder->id > 0) $sql .= ' AND d.fk_operation_order = ' . $operationOrder->id;

$formcore = new TFormCore($_SERVER['PHP_SELF'], 'form_list_operationorderpointage', 'POST');

$nbLine = GETPOST('limit');
if (empty($nbLine)) $nbLine = !empty($user->conf->MAIN_SIZE_LISTE_LIMIT) ? $user->conf->MAIN_SIZE_LISTE_LIMIT : getDolGlobalInt("MAIN_SIZE_LISTE_LIMIT");

// List configuration
$listViewConfig = array(
	'view_type' => 'list' // default = [list], [raw], [chart]
, 'allow-fields-select' => true
, 'limit' => array(
		'nbLine' => $nbLine
	)
, 'list' => array(
		'title' => $langs->trans('OperationOrderPointageList')
	, 'image' => 'title_generic.png'
	, 'picto_precedent' => '<'
	, 'picto_suivant' => '>'
	, 'noheader' => 0
	, 'messageNothing' => $langs->trans('NoOperationOrderPointage')
	, 'picto_search' => img_picto('', 'search.png', '', 0)
	, 'massactions' => array()
	, 'param_url' => '&id=' . GETPOST('id')
	)
, 'subQuery' => array()
, 'link' => array()
, 'type' => array(
		'date_d' => 'datetime',
		'date_f' => 'datetime',
		'duration' => 'money'// [datetime], [hour], [money], [number], [integer]
	)
, 'translate' => array()
, 'hide' => array(
		'rowid' // important : rowid doit exister dans la query sql pour les checkbox de massaction
	)
, 'title' => array(
		'fk_user' => $langs->trans('Mecanicien'),
		'fk_product' => $langs->trans('Operation'),
		'description' => $langs->trans('Description'),
		'date_d' => $langs->trans('Debut'),
		'date_f' => $langs->trans('fin'),
		'duration' => $langs->trans('Duree'),
		'planning' => $langs->trans('planninglink'),
		'action' => $langs->trans('Action')
	)
, 'eval' => array(
		'fk_user' => '_getUserNomURL(\'@fk_user@\')',
		'fk_product' => '_getProductNomURL(\'@fk_product@\')',
		'planning' => '_getUPlanningURL(\'@date_d@\')',
		'action' => '_getActionsBtn(\'@rowid@\',\'@fk_operation_order@\',\'@drowid@\')',
	)
, 'sortfield' => 'date_d'
, 'sortorder' => 'DESC'
);
if (!empty($operationOrder->id)) {
	unset($listViewConfig['title']['fk_operationorder']);
	unset($listViewConfig['search']['fk_operationorder']);
	unset($listViewConfig['eval']['fk_operationorder']);
}


$r = new Listview($db, 'operationorderPointage');

// Change view from hooks
$parameters = array('listViewConfig' => $listViewConfig);

print '<input type="hidden" name="id" value="' . GETPOST('id') . '"/>';
echo $r->render($sql, $listViewConfig);

$parameters = array('sql' => $sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object);    // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

$formcore->end_form();

llxFooter('');
$db->close();

/**
 * @param int $fk_user fk_user
 */
function _getUserNomURL($fk_user)
{
	global $db;
	if (!empty($fk_user)) {
		$userNomUrl = new User($db);
		$userNomUrl->fetch($fk_user);
		return $userNomUrl->getNomUrl();
	} else return '';
}

/**
 * @param int $date_d $date_d
 */
function _getUPlanningURL($date_d)
{
	global $db;
	if (!empty($date_d)) {
		$dt_select = $db->jdate($date_d);
		$day = date('j', $dt_select);
		$month = date('n', $dt_select);
		$year = date('Y', $dt_select);
		$url = dol_buildpath('/operationorder/operationorder_orplanning.php', 3) . '?dateday=' . $day . '&datemonth=' . $month . '&dateyear=' . $year . '&date=' . $day . '/' . $month . '/' . $year;
		$linkstart = '<a href="' . $url . '">Planning</a>';
		return $linkstart;
	} else return '';
}

/**
 * @param int $fk_product $fk_product
 */
function _getProductNomURL($fk_product)
{
	global $db;
	if (!empty($fk_product)) {
		$productNomUrl = new Product($db);
		$productNomUrl->fetch($fk_product);
		return $productNomUrl->getNomUrl();
	} else return 'error';
}

/**
 * @param int $detail_id $detail_id
 * @param int $id $id
 * @param int $line_id $line_id
 */
function _getActionsBtn($detail_id, $id, $line_id)
{
	global $langs, $user, $db;

	$action = GETPOST('action');

	$actionUrl = $_SERVER["PHP_SELF"] . '?id=' . $id . '&amp;timeid=' . $detail_id;

	$HTML = '';
	$HTML .= '<a name="timeid_' . $detail_id . '"/>';
	if (empty($action)) {
		$operationordertasktime = new OperationOrderTaskTime($db);
		$resFetch = $operationordertasktime->fetch($detail_id);
		if ($resFetch < 0) {
			setEventMessages($operationordertasktime->error, $operationordertasktime->errors, 'errors');
		} elseif (!empty($operationordertasktime->task_datehour_f)) {
			$HTML .= dolGetButtonAction($langs->trans('Split'), '', 'default', $actionUrl . '&action=split#timeid_' . $detail_id, '', $user->hasRight("operationorder", "write"));
		}

		$HTML .= dolGetButtonAction($langs->trans('MoveTime'), '', 'default', $actionUrl . '&action=move#timeid_' . $detail_id, '', $user->hasRight("operationorder", "write"));
	} elseif ($action == 'split' && GETPOST('timeid', 'int') == $detail_id) {
		$form = new Form($db);
		$HTML = '<form name="split_time" action="' . $actionUrl . '" method="POST">' . "\n";
		$HTML .= '<input type="hidden" name="token" value="' . newToken() . '">' . "\n";
		$HTML .= '<input type="hidden" name="id" value="' . $id . '">' . "\n";
		$HTML .= '<input type="hidden" name="timeid" value="' . $detail_id . '">' . "\n";
		$HTML .= '<input type="hidden" name="action" value="split_time">' . "\n";

		$HTML .= '<table class="table-full">';
		$HTML .= '<tr>';
		$HTML .= '<td>';
		$HTML .= $langs->trans('TimeToSlpit');
		$HTML .= '</td>';
		$HTML .= '<td>';
		$durationtouse = ((int) GETPOST('timespent_durationhour', 'int') * 3600 + (int) GETPOST('timespent_durationmin', 'int') * 60);
		$HTML .= $form->select_duration('timespent_duration', $durationtouse, 0, 'text', 0, 1);
		$HTML .= '</td>';
		$HTML .= '</tr>';


		$HTML .= '</table>';
		//TODO replace at 16.0 with something like this
		$HTML .= '<input type="submit" class="button" name="bouton" value="' . $langs->trans('Split') . '">';
		$HTML .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		$HTML .= dolGetButtonAction($langs->trans('Cancel'), '', 'default', $_SERVER["PHP_SELF"] . '?id=' . $id, '', 1);
		$HTML .= '</form>';
	} elseif ($action == 'move' && GETPOST('timeid', 'int') == $detail_id) {
		$form = new Form($db);
		$HTML = '<form name="move_time" action="' . $actionUrl . '" method="POST">' . "\n";
		$HTML .= '<input type="hidden" name="token" value="' . newToken() . '">' . "\n";
		$HTML .= '<input type="hidden" name="id" value="' . $id . '">' . "\n";
		$HTML .= '<input type="hidden" name="timeid" value="' . $detail_id . '">' . "\n";
		$HTML .= '<input type="hidden" name="action" value="move_time">' . "\n";

		$HTML .= '<table class="table-full">';
		$HTML .= '<tr>';
		$HTML .= '<td>';
		$HTML .= $langs->trans('LineToMoveTime');
		$HTML .= '</td>';
		$HTML .= '<td>';
		$operationorder = new OperationOrder($db);
		$resFetch = $operationorder->fetch($id);
		if ($resFetch < 0) {
			setEventMessages($operationorder->error, $operationorder->errors, 'errors');
		} else {
			$parentline = array();
			foreach ($operationorder->lines as $line) {
				$parentline[$line->id] = $line;
				if (!empty($line->product->id)
					&& $line->product->type == $line->product::TYPE_SERVICE
					&& !empty($line->product->array_options['options_or_scan'])
					&& $line->id != $line_id) {
					$data[$line->id] = $line->product->ref . ' - ' . $line->product->label;
					if (!empty($line->fk_parent_line)) {
						$data[$line->id] .= ' (' . $parentline[$line->fk_parent_line]->product->label . ')';
					}
				}
			}
		}

		$HTML .= $form->selectarray('newtimelineid', $data);
		$HTML .= '</td>';
		$HTML .= '</tr>';

		$HTML .= '</table>';
		//TODO replace at 16.0 with something like this
		$HTML .= '<input type="submit" class="button" value="' . $langs->trans('MoveTime') . '">';
		$HTML .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		$HTML .= dolGetButtonAction($langs->trans('Cancel'), '', 'default', $_SERVER["PHP_SELF"] . '?id=' . $id, '', 1);
		$HTML .= '</form>';
	}
	return $HTML;
}
