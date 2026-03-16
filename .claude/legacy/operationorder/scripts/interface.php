<?php

$res = @include "../../main.inc.php"; // For root directory
if (!$res)
	$res = @include "../../../main.inc.php"; // For "custom" directory
if (!$res)
	die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once __DIR__ . '/../class/unitstools.class.php';
require_once __DIR__ . '/../lib/operationorder.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
dol_include_once('/operationorder/class/operationorder.class.php');
dol_include_once('/operationorder/class/operationordertasktime.class.php');
dol_include_once('/operationorder/class/operationorderbarcode.class.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db;
$hookmanager->initHooks(array('oorderinterface'));

/*
 * Action
 */
$data = $_POST;
$data['result'] = 0; // by default if no action result is false
$data['errorMsg'] = ''; // default message for errors
$data['warningMsg'] = ''; // default message for warnings
$data['msg'] = '';

// do action from GETPOST ...
if (GETPOST('action')) {
	$action = GETPOST('action');

	if ($action == "getPlannedOperationOrder") {
		// Parse the start/end parameters.
		// These are assumed to be ISO8601 strings with no time nor timeZone, like "2013-12-29".
		// Since no timeZone will be present, they will parsed as UTC.

		$timeZone = GETPOST('timeZone');
		$eventsType = GETPOST('eventsType');
		$fk_soc_external = GETPOST('fk_soc_external');
		$range_start = OO_parseFullCalendarDateTime(GETPOST('start'), $timeZone);
		$range_end = OO_parseFullCalendarDateTime(GETPOST('end'), $timeZone);

		if ($eventsType == 'dayOff') {
			$data = array();
			//_getJourOff($range_start->getTimestamp(), $range_end->getTimestamp());
		} elseif ($eventsType == 'dayFull') {
			$data = array();
			//_getJourFull($range_start, $range_end);
		} elseif ($eventsType == 'weekFull') {
			$data = array();
			//_getWeekFull($range_start, $range_end);
		} else {
			$data = _getOperationOrderEvents($range_start->getTimestamp(), $range_end->getTimestamp(), $eventsType, $fk_soc_external);
		}


		$parameters = array();
		$reshook = $hookmanager->executeHooks('jsonInterface', $parameters, $data, $action);    // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			// pas de gestion d'erreur pour l'instant pour cet action
		} elseif ($reshook > 0) {
			$data = $hookmanager->resArray;
		}

		print json_encode($data);
		exit;
	} elseif ($action == 'getBusinessHours') {
		$data['result'] = '';
		$data['errorMsg'] = '';

		$TDaysConvert = array('Mon' => 'lundi', 'Tue' => 'mardi', 'Wed' => 'mercredi', 'Thu' => 'jeudi', 'Fri' => 'vendredi', 'Sat' => 'samedi', 'Sun' => 'dimanche');

		$beginOfWeek = GETPOST('beginOfWeek');
		$endOfWeek = GETPOST('endOfWeek');

		$or = new OperationOrder($db);
		$data = $or->getOperationOrderUserPlanningSchedule($beginOfWeek, $endOfWeek);
		if (!is_array($data) && $data<0) {
			$errorData['result'] = $data;
			$errorData['errorMsg'] = $or->errors;
			print json_encode($errorData);
			exit;
		} else {
			foreach ($data as $date => $TSchedules) {
				$data[$TDaysConvert[date('D', $date)]] = $TSchedules;
				unset($data[$date]);
			}
		}

		print json_encode($data);
		exit;
	} elseif ($action == 'setOperationOrderlevelHierarchy') {
		if (!$user->hasRight("operationorder", "write")) {
			$data['result'] = -1; // by default if no action result is false
			$data['errorMsg'] = $langs->trans("ErrorForbidden"); // default message for errors
		} else {
			$data['result'] = _updateOperationOrderlevelHierarchy(GETPOST('operation-order-id'), $data['items'], 0, $data['errorMsg']);
			if ($data['result'] > 0) {
				setEventMessage($langs->transnoentities('Updated') . ' : ' . $data['result']);
				$data['msg'] = $langs->transnoentities('Updated') . ' : ' . $data['result'];
			}
		}
	} elseif ($action == 'statusRank') {
		require_once __DIR__ . '/../class/operationorderstatus.class.php';
		$data['msg'] = 'UpdateStatus';
		_statusRank($data);
	} elseif ($action == 'getProductInfos' && $user->hasRight("produit", "lire", "")) {
		include_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
		$productId = GETPOST('fk_product', 'int');
		$loadStockInfo = GETPOST('load_stock', 'int');

		$product = new Product($db);
		if (!empty($productId) && $product->fetch($productId) > 0) {
			$data['result'] = 1;
			$data['fk_default_warehouse'] = $product->fk_default_warehouse;
			$data['price'] = price($product->price);
			$data['duration_unit'] = $product->duration_unit;
			$data['duration_value'] = $product->duration_value;
			$data['is_job'] = $product->array_options['options_or_is_job'];
			$data['or_scan'] = $product->array_options['options_or_scan'];
			$data['oorder_available_for_supplier_order'] = $product->array_options['options_oorder_available_for_supplier_order'];
			$data['product_type'] = $product->type;

			$data['time_plannedhour'] = 0;
			$data['time_plannedmin'] = 0;


			if (!empty($product->duration_unit)) {
				$fk_duration_unit = UnitsTools::getUnitFromCode($product->duration_unit, 'short_label');
				if ($fk_duration_unit < 1) {
					$data['errorMsg'] .= (!empty($data['errorMsg']) ? '<br/>' : '') . $langs->transnoentities('UnitCodeNotFound', $product->duration_unit);
				}

				if (!empty($product->duration_value) && $fk_duration_unit > 0) {
					$fk_unit_hours = UnitsTools::getUnitFromCode('H', 'code');
					if ($fk_unit_hours > 0) {
						$durationHours = UnitsTools::unitConverteur($product->duration_value, $fk_duration_unit, $fk_unit_hours);

						$data['time_plannedhour'] = floor($durationHours);
						$data['time_plannedmin'] = round($durationHours - floor($durationHours), 2) * 60;
					} else {
						$data['errorMsg'] .= (!empty($data['errorMsg']) ? '<br/>' : '') . $langs->transnoentities('UnitCodeNotFound', 'H');
					}
				}
			}

			// pour les hooks avec multicompany et si besoin de faire de traitement en fonction de l'object
			$entity = 0;
			$element = GETPOST('element', 'aZ09');
			$element_id = GETPOST('element_id', 'int');
			$fromObject = false;

			$data['log'][] = 'test element : ' . $element . ' , element_id ' . $element_id;
			if (!empty($element) && !empty($element_id)) {
				$fromObject = OperationOrderObjectAutoLoad($element, $db);
				if ($fromObject && $fromObject->fetch($element_id) <= 0) {
					$data['log'][] = 'OperationOrderObjectAutoLoad fail';
					$fromObject = false;
				} else {
					$data['log'][] = 'OperationOrderObjectAutoLoad success';
				}
			}

			$data['stock_info'] = array();
			if (!empty($loadStockInfo)) {
				$resStock = $product->load_stock('warehouseopen');
				if ($resStock < 0) {
					$data['errorMsg'] .= $product->error;
				} else {
					$data['stock_info'] = $product->stock_warehouse;
				}
			}


			// Change view from hooks
			$data['log'][] = 'call hook';
			$parameters = array('data' => & $data, 'entity' => $entity, 'fromObject' => $fromObject);
			$reshook = $hookmanager->executeHooks('jsonInterface', $parameters, $product, $action);    // Note that $action and $object may have been modified by hook
			if ($reshook < 0) {
				$data['result'] = $reshook;
				$data['errorMsg'] = $hookmanager->error;
			} elseif ($reshook > 0) {
				$data = $hookmanager->resArray;
			}
		} else {
			$data['result'] = 0;
		}
	}
	if ($action == 'getTableDialogPlanable') $data['result'] = _getTableDialogPlanable($data['startTime'], $data['endTime'], $data['allDay'], $data['url'], '', '', $data['beginOfWeek'], $data['endOfWeek']);
	elseif ($action == 'updateOperationOrderAction') $data = _updateOperationOrderAction($data['startTime'], $data['endTime'], $data['fk_action'], $data['action'], $data['allDay']);
	elseif ($action == 'getScheduleInfos') $data['result'] = _getScheduleInfos($data['scheduleId'], $data['oOrder'], $data['det'], $data['minHour'], $data['maxHour']);
	elseif ($action == 'updateSchedule') $data['result'] = _updateSchedule($data['scheduleId'], $data['startTime'], $data['endTime'], $data['token']);
	elseif ($action == 'getCreateScheduleForm') $data['result'] = _getCreateScheduleForm($data['userid'], $data['date'], $data['minHour'], $data['maxHour'], $data['selectedHour'], $data['entity'], $data['token']);
	elseif ($action == 'createSchedule') $data['result'] = _createSchedule($data['fk_user'], $data['entity'], $data['fk_orDet'], $data['startTime'], $data['endTime'], $data['label'], $data['closeprevioustask']);
	elseif ($action == 'deleteSchedule') $data['result'] = _deleteSchedule($data['scheduleId'], $data['token']);
	elseif ($action == 'getProductInfo') $data = _getProductInfo(GETPOST('fk_operationOrder', 'int'), GETPOST('fk_product', 'int'));
	elseif ($action == "getSocOfVehicule") $data = _getSocOfVehicule(GETPOST('vehicule_id', 'int'));
}

echo json_encode($data);

/**
 * @param int $scheduleId scheduleId
 * @param string $token token
 * @return bool
 * @throws Exception
 */
function _deleteSchedule($scheduleId, $token)
{
	global $user, $db, $langs;

	if (!$user->hasRight("operationorder", "counter", "delete")) {
		setEventMessage($langs->trans('NotEnoughPermissions'), 'errors');
		return false;
	}

	$schedule = new OperationOrderTaskTime($db);
	$schedule->fetch($scheduleId);

	$db->begin();
	$out = false;
	$ret = $schedule->delete($user);
	if ($ret > 0) {
		if (!empty($schedule->fk_orDet)) {
			$det = new operationorderLine($db);
			$det->fetch($schedule->fk_orDet);

			$or = new OperationOrder($db);
			if (!empty($det->fk_operation_order)) {
				$or->fetch($det->fk_operation_order);
				$res = $or->updateline($det->id,
					$det->description,
					$det->qty,
					$det->price,
					$det->fk_warehouse,
					$det->pr,
					$det->time_planned,
					($det->time_spent - $schedule->task_duration),
					$det->fk_product,
					0,
					$det->date_start,
					$det->date_end,
					$det->type,
					$det->fk_parent_line,
					$det->label,
					$det->special_code,
					$det->array_options);
			}
		} else $out = true;

		if ($res > 0 || $out) {
			setEventMessage($langs->trans('RecordDeleted'));
			$db->commit();
			$out = true;
		} else setEventMessage($langs->trans('ErrorOrDetNotUpdated'), 'errors');
	}

	if (!$out) $db->rollback();

	return $out;
}

/**
 * @param int $fk_user user id
 * @param int $entity entioty
 * @param int $fk_orDet order det
 * @param int $startTime start time
 * @param int $endTime end time
 * @param string $label label
 * @param int $closeprevioustask do close
 * @return false|OperationOrderTaskTime
 * @throws Exception
 */
function _createSchedule($fk_user, $entity, $fk_orDet, $startTime, $endTime, $label, $closeprevioustask)
{
	global $langs, $db, $user, $conf;

	$out = false;
	$db->begin();

	if (!empty($startTime)) {
		//find existing current task running
		$counter = new OperationOrderTaskTime($db);
		$retCounter = $counter->fetchCourantCounter($fk_user);


		if (!empty($closeprevioustask) && $retCounter > 0) {
			if ($startTime < $counter->task_datehour_d) {
				$db->rollback();
				// TODO better error management than a setEventMessage() in an ajax call...
				setEventMessage($langs->trans('ErrorInvertedDates'), 'errors');
				return false;
			}

			$counter->task_datehour_f = $startTime;
			$counter->task_duration = $counter->task_datehour_f - $counter->task_datehour_d;
			$ret = $counter->update($user);
			// mise à jour du temps passé sur la ligne pointable
			$ordet = new operationorderLine($db);
			$ordet->fetch($counter->fk_orDet);
			$or = new OperationOrder($db);
			if (!empty($ordet->fk_operation_order)) {
				$or->fetch($ordet->fk_operation_order);
				$or->updateline($ordet->id,
					$ordet->description,
					$ordet->qty,
					$ordet->price,
					$ordet->fk_warehouse,
					$ordet->pr,
					$ordet->time_planned,
					($ordet->time_spent + $counter->task_duration),
					$ordet->fk_product,
					0,
					$ordet->date_start,
					$ordet->date_end,
					$ordet->type,
					$ordet->fk_parent_line,
					$ordet->label,
					$ordet->special_code,
					$ordet->array_options);
			}

			$remaining = $counter->remainingCountersForOR($ordet->id);
			// changement de statut de l'OR de la ligne
			if (!empty(getDolGlobalString("OPORDER_CHANGE_OR_STATUS_ON_STOP")) && !empty(getDolGlobalString("OPODER_STATUS_ON_STOP")) && !$remaining && !empty($or->id)) {
				$ret = $or->setStatus($user, getDolGlobalInt("OPODER_STATUS_ON_STOP"));
				if ($ret < 0) {
					$db->rollback();
					setEventMessages($or->error, $or->errors, 'errors');
					return false;
				}
			}
		}

		if (!empty($retCounter) && empty($closeprevioustask) && empty($endTime)) {
			setEventMessage($langs->trans('ErrorUpdateScheduleEndDateMissingCloseTask'), "errors");
		} else {
			$out = true;

			$schedule = new OperationOrderTaskTime($db);

			$schedule->label = $label;
			$schedule->fk_user = $fk_user;
			$schedule->entity = $entity;
			$schedule->fk_orDet = $fk_orDet;
			$schedule->task_datehour_d = $startTime;
			if (!empty($endTime)) {
				$schedule->task_datehour_f = $endTime;
				$schedule->task_duration = $endTime - $startTime;
				if ($schedule->task_duration < 0) {
					setEventMessage($langs->trans('ErrorInvertedDates'), 'errors');
					$out = false;
				}
			}


			if ($out) {
				$ret = $schedule->save($user);
				if ($ret > 0) {
					if (!empty($schedule->fk_orDet) && !empty($endTime)) {
						$det = new operationorderLine($db);
						$det->fetch($schedule->fk_orDet);

						$or = new OperationOrder($db);
						if (!empty($det->fk_operation_order)) {
							$or->fetch($det->fk_operation_order);
							$res = $or->updateline($det->id,
								$det->description,
								$det->qty,
								$det->price,
								$det->fk_warehouse,
								$det->pr,
								$det->time_planned,
								($det->time_spent + ((!empty($schedule->task_duration)) ? $schedule->task_duration : 0)),
								$det->fk_product,
								0,
								$det->date_start,
								$det->date_end,
								$det->type,
								$det->fk_parent_line,
								$det->label,
								$det->special_code,
								$det->array_options);
							if ($res < 0) {
								setEventMessages($or->error, $or->errors, 'errors');
								$out = false;
							}
						}
					}

					if ($res < 0 || !$out) {
						setEventMessage($langs->trans('ErrorOrDetNotUpdated'), 'errors');
					}
				} else {
					setEventMessage($langs->trans('ErrorUpdateSchedule'), "errors");
					$out = false;
				}
			}
		}

		if ($out) {
			setEventMessage($langs->trans('RecordSaved'));
			$db->commit();
		}
	} else {
		setEventMessage($langs->trans('ErrorUpdateScheduleStartDateMissing'), "errors");
	}

	if (!$out) $db->rollback();

	return $schedule;
}

/**
 * @param int $userid userid
 * @param string $date dadte
 * @param string $minHour min hour
 * @param string $maxHour max hour
 * @param string $selectedHour selected time
 * @param int $entity entity
 * @param string $token token
 * @return string
 * @throws Exception
 */
function _getCreateScheduleForm($userid, $date, $minHour, $maxHour, $selectedHour, $entity, $token)
{
	global $db, $langs, $conf;

	$out = '';
	$TOR = $TORDet = array();
	$or = new OperationOrder($db);
	$orDet = new operationorderLine($db);
	$schedule = new OperationOrderTaskTime($db);
	$form = new Form($db);
	$u = new User($db);

	$switchEntity = false;
	if ($entity != $conf->entity && !empty($entity)) {
		$switchEntity = true;

		$oldEntity = $conf->entity;
		$conf->entity = $entity;
		$conf->setValues($db);
	}

	$resUser = $u->fetch($userid);
	if ($resUser <= 0) {
		$out .= "utilisateur $userid non trouvé";
		return $out;
		exit;
	}
	$out .= $langs->trans('User') . ' : ' . $u->getNomUrl(1);
	$out .= '<br><input type="radio" checked class="selectInputMode" id="orinputmode" name="selectInputMode" value="orinputmode"><label for="orinputmode">OR</label>';
	$out .= '<input type="radio" id="improdinputmode" class="selectInputMode" name="selectInputMode" value="improdinputmode"><label for="improdinputmode">Improd</label>';
	$out .= "<br />";

	$counter = new OperationOrderTaskTime($db);
	$ret = $counter->fetchCourantCounter($u->id);
	if ($ret > 0) {
		$out .= '<input type="checkbox" checked id="closeprevious" name="closeprevious" value="' . $counter->id . '"><label for="closeprevious">' . $langs->trans('CloseCurrentTask') . '</label>';
	}
	$out .= "<input type='hidden' id='entity' name='entity' value='" . $conf->entity . "'/>";
	$out .= "<input type='hidden' id='fk_user' name='fk_user' value='" . $userid . "'/>";
	$out .= "<input type='hidden' id='token' name='token' value='" . $token . "'/>";


	$arrDate = explode('/', $date);
	$sqlDate = strtotime($arrDate[2] . '-' . $arrDate[1] . '-' . $arrDate[0] . ' 00:00:00');
	$sqlDate = date("Y-m-d 00:00:00", strtotime('-3 months', $sqlDate));

	// liste des OR qui ont un statut dont le champs "Afficher les ordres de réparation sur ce statut dans le planning" à 1
	// et qui on une date de création > date - 3 mois
	// de l'entité spécifiée
	$sql = "SELECT oo.rowid, oo.ref FROM " . $db->prefix() . "operationorder oo";
	$sql .= " LEFT JOIN " . $db->prefix() . "operationorder_status os ON os.rowid = oo.status";
	$sql .= " WHERE os.display_on_planning = 1";
	$sql .= " AND oo.entity = " . $conf->entity;
	$sql .= " AND oo.date_creation > '" . $db->idate($sqlDate) . "'";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$TOR[$obj->rowid] = $obj->ref;
		}
	}

	if (!empty($TOR)) {
		$out .= '<div id="ORMode">';
		$out .= $langs->trans('OperationOrder') . ' : ';
		$out .= $form->selectArray('oOrderId', $TOR, "", 1, 0, 0, '', 0, 0, 0, '', '', 1) . '<br />';

		// récupération des lignes d'OR concernées
		$sql = "SELECT ood.fk_operation_order, ood.rowid, ood.fk_parent_line, p.ref, p.label, p2.ref as parent_ref, p2.label as parent_label FROM " . $db->prefix() . "operationorderdet ood";
		$sql .= " LEFT JOIN " . $db->prefix() . "product p ON p.rowid = ood.fk_product";
		$sql .= " LEFT JOIN " . $db->prefix() . "product_extrafields pe ON pe.fk_object = p.rowid";
		$sql .= " LEFT JOIN " . $db->prefix() . "operationorderdet parentDet ON parentDet.rowid=ood.fk_parent_line";
		$sql .= " LEFT JOIN " . $db->prefix() . "product p2 ON p2.rowid = parentDet.fk_product";
		$sql .= " WHERE pe.or_scan = 1";
		$sql .= " AND ood.fk_operation_order IN ('" . implode("','", array_keys($TOR)) . "')";

		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$TORDet[$obj->fk_operation_order][$obj->rowid] = $obj->label . (!empty($obj->parent_label) ? " - " . $obj->parent_label : "");
			}
		}
		if (!empty($TORDet)) {
			// dans une div cachée, créer un select par OR avec ses lignes en option
			// au changement du champ OR on viendra copier le contenu du bon select dans le selectarray visible
			$out .= "<div id='orLines' style='display: none;'>";
			foreach ($TORDet as $or_id => $Tdet) {
				$out .= "<select id='" . $or_id . "_lines'>";
				$out .= "<option>&nbsp;</option>";
				foreach ($Tdet as $det_id => $label) {
					$out .= '<option value="' . $det_id . '" data-label="' . $label . '">' . $label . '</option>';
				}
				$out .= "</select><br />";
			}
			$out .= "</div>";
			$out .= $langs->trans('SearchIntoTasks') . " : ";
			$out .= $form->selectArray('fk_orDet', array(), "", 1, 0, 0, 'style="width:80%"', 0, 0, 0, '', '', 1) . '<br />';
			$out .= '</div>';

			$out .= '<div id="improdMode" style=\'display: none;\'>';
			$barcode = new OperationOrderBarCode($db);
			$TBarCodes = $barcode->fetchAll('', '', array('entity' => $conf->entity));
			$Timprod = array();
			if (!empty($TBarCodes) && is_array($TBarCodes)) {
				foreach ($TBarCodes as $improd) {
					$Timprod[$improd->label] = $improd->label;
				}
			} else {
				setEventMessages(null, $barcode->errors, 'errors');
			}
			$out .= $langs->trans('Improductif') . ' : ';
			$out .= $form->selectArray('improdType', $Timprod, "", 0, 0, 0, 'style="width:80%"', 0, 0, 0, '', '', 1) . '<br />';
			$out .= '</div>';


			$TFieldToDisplay = array('task_datehour_d', 'task_datehour_f');
			$T = array();

			// début du créneau à l'heure cliquée
			$schedule->task_datehour_d = strtotime($arrDate[2] . '-' . $arrDate[1] . '-' . $arrDate[0] . ' ' . $selectedHour . ':00');
			// fin du créneau 5 minutes plus tard
			$schedule->task_datehour_f = $schedule->task_datehour_d + 300;

			$out .= '<br /><div id="alert"></div>';
			$out .= '<input type="hidden" id="minHour" value="' . $minHour . '">';
			$out .= '<input type="hidden" id="token" value="' . currentToken() . '">';
			$out .= '<input type="hidden" id="maxHour" value="' . $maxHour . '">';
			$out .= '<input type="hidden" id="minDate" value="' . date("Y-m-d\T" . $minHour . ":00", $schedule->task_datehour_d) . '">';
			$out .= '<input type="hidden" id="maxDate" value="' . ((!empty($schedule->task_datehour_f)) ? date("Y-m-d\T" . $maxHour . ":00", $schedule->task_datehour_f) : date("Y-m-d\T" . $maxHour . ":00")) . '">';

			foreach ($schedule->fields as $fieldKey => $field) {
				if (!in_array($fieldKey, $TFieldToDisplay)) continue;

				//FHE : Add on Ticket 0000025
				if ($fieldKey == 'task_datehour_f') {
					$schedule->{$fieldKey} = '-1';
				}

				$T[$fieldKey] = $langs->trans($field['label']) . ' : ' . $schedule->showInputField($schedule->fields[$fieldKey], $fieldKey, $schedule->{$fieldKey});//$schedule->showOutputFieldQuick($fieldKey);
			}


			$out .= '<br />' . implode('<br />', $T) . '<br />';

			$out .= '<br /><div align="center"><button id="save" class="button">' . $langs->trans('Save') . '</button>&nbsp;<button id="cancel" class="button">' . $langs->trans('Cancel') . '</button></div>';

			$out .= '<script type="text/javascript">
				$(function(){
					$("#oOrderId").on("change", function(){
						let orId = $(this).val();
						$("#fk_orDet").html($("#"+orId+"_lines").html());
					});

					$(".selectInputMode").on("change", function(){
						if (($(this).val())=="orinputmode") {
					    	$("#ORMode").show();
					    	$("#improdMode").hide();
						}
						if (($(this).val())=="improdinputmode") {
						    $("#ORMode").hide();
					    	$("#improdMode").show();
						}

					});

					$("#closeprevious").change(function() {
						var ischecked= $(this).is(\':checked\');
						if(!ischecked && $("#task_datehour_f").val()=="") {
						    $("#task_datehour_fButtonNow").click();
						}
					});

					$("#cancel").on("click", function() {
						$("#schedulePopin").dialog("close")
					});

					$("#save").on("click", function() {
						var errorMsg ="";
						$("#alert")
							.hide()
							.css("color","#721c24")
							.css("background-color","#f8d7da")
							.css("border-color","#f5c6cb");

						let closeprevioustask = $("#closeprevious:checked").val();
						let fk_orDet = "";
						let labelAction = "";

						let minDate = new Date($("#minDate").val());
						let maxDate = new Date($("#maxDate").val());

						let task_datehour_dday=$("#task_datehour_dday").val();
						if (task_datehour_dday.toString().length==1) {
							task_datehour_dday = "0" + task_datehour_dday;
						}
						let task_datehour_dmonth=$("#task_datehour_dmonth").val();
						if (task_datehour_dmonth.toString().length==1) {
							task_datehour_dmonth = "0" + task_datehour_dmonth;
						}

						let startDate = new Date(
							$("#task_datehour_dyear").val()+"-"+
							task_datehour_dmonth+"-"+
							task_datehour_dday+"T"+
							$("#task_datehour_dhour").val()+":"+
							$("#task_datehour_dmin").val()+":00"
						);

						let task_datehour_fday=$("#task_datehour_fday").val();
						if (task_datehour_fday.toString().length==1) {
							task_datehour_fday = "0" + task_datehour_fday;
						}
						let task_datehour_fmonth=$("#task_datehour_fmonth").val();
						if (task_datehour_fmonth.toString().length==1) {
							task_datehour_fmonth = "0" + task_datehour_fmonth;
						}
						let endDate = new Date(
							$("#task_datehour_fyear").val()+"-"+
							task_datehour_fmonth+"-"+
							task_datehour_fday+"T"+
							$("#task_datehour_fhour").val()+":"+
							$("#task_datehour_fmin").val()+":00"
						);

						if ($(\'input[name="selectInputMode"]:checked\').val()=="orinputmode") {
							if ($("#oOrderId").val() <= 0)
								errorMsg += "<p>' . $langs->trans('ErrorNoORSelected') . '</p>";
							if ($("#fk_ordet").val() <= 0)
								errorMsg += "<p>' . $langs->trans('ErrorNoORLineSelected') . '</p>";

							fk_orDet=$("#fk_orDet").val();
							labelAction = $("#fk_orDet option:selected").attr("data-label");
						}

						if ($(\'input[name="selectInputMode"]:checked\').val()=="improdinputmode") {
							fk_orDet = 0;
							labelAction = $("#improdType").val();
						}

						if (startDate.getTime() > endDate.getTime())
							errorMsg += "<p>' . $langs->trans('ErrorInvertedDates') . '</p>";

						if (errorMsg.length) $("#alert").html(errorMsg).show();
						else
						{
							let endDateVal = endDate.getTime() / 1000;
							if (isNaN(endDateVal)) {
								endDateVal="";
							}
							let startDateVal = startDate.getTime() / 1000;
							if (isNaN(startDateVal)) {
								startDateVal="";
							}
							$.ajax({
								url:"' . dol_buildpath('/operationorder/scripts/interface.php', 1) . '",
								method:"POST",
								data: {
									action: "createSchedule",
									fk_user: $("#fk_user").val(),
									entity: $("#entity").val(),
									fk_orDet: fk_orDet,
									startTime: startDateVal,
									endTime: endDateVal,
									label: labelAction,
									closeprevioustask: closeprevioustask,
									token: "'.currentToken().'",
								}
							}).done(function(response){
								$("form[name=\'filters\']").submit();
//								$("#schedulePopin").dialog("close")
							});
						}
					});
				});
			</script>';
		}
	}

	if ($switchEntity) {
		$conf->entity = $oldEntity;
		$conf->setValues($db);
	}
	return $out;
}

/**
 * @param int $scheduleId schedul id
 * @param int $startTime strat time
 * @param int $endTime end time
 * @return bool
 * @throws Exception
 */
function _updateSchedule($scheduleId, $startTime, $endTime)
{
	global $db, $langs, $user;

	$out = true;
	if (!empty($endTime) && !empty($startTime)) {
		$schedule = new OperationOrderTaskTime($db);
		$ret = $schedule->fetch($scheduleId);
		if ($ret > 0) {
			$oldDuration = $schedule->task_duration;

			$schedule->task_datehour_d = $startTime;
			$schedule->task_datehour_f = $endTime;
			$schedule->task_duration = $endTime - $startTime;

			if ($schedule->task_duration < 0) {
				setEventMessage($langs->trans('ErrorInvertedDates'), 'errors');
				$out = false;
			}

			$db->begin();
			if ($out) {
				$ret = $schedule->save($user);
				if ($ret > 0) {
					if (!empty($schedule->fk_orDet)) {
						$addTime = $schedule->task_duration - $oldDuration;
						$det = new operationorderLine($db);
						$det->fetch($schedule->fk_orDet);

						$or = new OperationOrder($db);
						if (!empty($det->fk_operation_order)) {
							$or->fetch($det->fk_operation_order);
							$res = $or->updateline($det->id,
								$det->description,
								$det->qty,
								$det->price,
								$det->fk_warehouse,
								$det->pr,
								$det->time_planned,
								($det->time_spent + $addTime),
								$det->fk_product,
								0,
								$det->date_start,
								$det->date_end,
								$det->type,
								$det->fk_parent_line,
								$det->label,
								$det->special_code,
								$det->array_options);
						}
					} else {
						$out = true;
					}

					if (($res > 0) || $out) {
						setEventMessage($langs->trans('RecordSaved'));
						$db->commit();
						$out = true;
					} else {
						setEventMessage($langs->trans('ErrorOrDetNotUpdated'), "errors");
					}
				} else setEventMessage($langs->trans('ErrorUpdateSchedule'), "errors");
			}

			if (!$out) $db->rollback();
		}
	} else {
		setEventMessage($langs->trans('ErrorUpdateScheduleDateMissing'), "errors");
	}

	return $out;
}

/**
 * @param int $scheduleId sheculd id
 * @param int $fk_or or id
 * @param int $fk_ordet or det id
 * @param string $minHour min hour
 * @param string $maxHour max jour
 * @return int|string
 */
function _getScheduleInfos($scheduleId, $fk_or, $fk_ordet, $minHour, $maxHour)
{
	global $db, $langs, $user;

	$out = '';

	$schedule = new OperationOrderTaskTime($db);
	$or = new OperationOrder($db);
	$or->fetch($fk_or);
	$orDet = new operationorderLine($db);
	$orDet->fetch($fk_ordet);

	$ret = $schedule->fetch($scheduleId);
	if ($ret > 0) {
		// si le user a le droit de supprimer des saisies
		if ($user->hasRight("operationorder", "counter", "delete")) {
			$out .= '<button class="butActionDelete pull-right" id="del">' . $langs->trans("Delete") . '</button>';
			$out .= '<p class="question">' . $langs->trans('askDeleteCounter') . '</p>';
			$out .= '<div class="question"><button id="yes">' . $langs->trans('Yes') . '</button>&nbsp;<button id="no">' . $langs->trans('No') . '</button></div>';
			$out .= '<br />';
			$out .= '<script type="text/javascript">
				$(function (){
					$(".question").hide();

					$("#del").on("click", function(){$(".question").show();});
					$("#no").on("click", function(){$(".question").hide();});
					$("#yes").on("click", function (){
						$.ajax({
							url:"' . dol_buildpath('/operationorder/scripts/interface.php', 1) . '",
							method:"POST",
							data: {
								action: "deleteSchedule",
								scheduleId: ' . $schedule->id . ',
								token: "'.currentToken().'",
							}
						}).done(function(data){
							$("form[name=\'filters\']").submit();
//									$("#schedulePopin").dialog("close")
						});
					});
				});
			</script>';
		}
		if (!empty($or->id)) {
			$out .= $or->getNomUrl(1);
		}
		if (!empty($orDet->id)) {
			$TFieldToDisplay = array('fk_product', 'price', 'qty', 'time_planned', 'time_spent');

			foreach ($orDet->fields as $fieldKey => $field) {
				if (!in_array($fieldKey, $TFieldToDisplay)) continue;

				$T[$fieldKey] = $langs->trans($field['label']) . ' : ' . $orDet->showOutputFieldQuick($fieldKey);
			}

			$out .= '<br /><br />' . implode('<br />', $T);
		} else $out .= $schedule->label;

		$TFieldToDisplay = array('task_datehour_d', 'task_datehour_f');
		$T = array();

		$out .= '<br /><div id="alert"></div>';
		$out .= '<input type="hidden" id="minHour" value="' . $minHour . '">';
		$out .= '<input type="hidden" id="token" value="' . currentToken() . '">';
		$out .= '<input type="hidden" id="minDate" value="' . date("Y-m-d\T" . $minHour . ":00", $schedule->task_datehour_d) . '">';
		$out .= '<input type="hidden" id="maxHour" value="' . $maxHour . '">';
		$out .= '<input type="hidden" id="maxDate" value="' . ((!empty($schedule->task_datehour_f)) ? date("Y-m-d\T" . $maxHour . ":00", $schedule->task_datehour_f) : date("Y-m-d\T" . $maxHour . ":00")) . '">';

		foreach ($schedule->fields as $fieldKey => $field) {
			if (!in_array($fieldKey, $TFieldToDisplay)) continue;

			$T[$fieldKey] = $langs->trans($field['label']) . ' : ' . $schedule->showInputField($schedule->fields[$fieldKey], $fieldKey, $schedule->{$fieldKey});//$schedule->showOutputFieldQuick($fieldKey);
		}

		$out .= '<br />' . implode('<br />', $T) . '<br />';

		$out .= '<br /><div align="center">';
		if ($user->hasRight("operationorder", "counter", "update")) {
			$out .= '<button id="save" class="button">' . $langs->trans('Save') . '</button>&nbsp;';
		} else {
			$out .= '<button title="' . $langs->trans('NotEnoughPermissions') . '" class="button"  disabled>' . $langs->trans('Save') . '</button>&nbsp;';
		}
		$out .= '<button id="cancel" class="button">' . $langs->trans('Cancel') . '</button>';
		$out .= '</div>';

		$out .= '<script type="text/javascript">
					$(function() {
						$("#cancel").on("click", function() {
							$("#schedulePopin").dialog("close")
						});

						$("#save").on("click", function() {
							var errorMsg ="";
							$("#alert, .alert")
								.hide()
								.css("color","#721c24")
								.css("background-color","#f8d7da")
								.css("border-color","#f5c6cb");

							let minDate = new Date($("#minDate").val());
							let maxDate = new Date($("#maxDate").val());

							let task_datehour_dday=$("#task_datehour_dday").val();
							if (task_datehour_dday.toString().length==1) {
							    task_datehour_dday = "0" + task_datehour_dday;
							}
							let task_datehour_dmonth=$("#task_datehour_dhour").val();
							if (task_datehour_dmonth.toString().length==1) {
							    task_datehour_dmonth = "0" + task_datehour_dmonth;
							}
							let startDate = new Date(
								$("#task_datehour_dyear").val()+"-"+
								$("#task_datehour_dmonth").val()+"-"+
								task_datehour_dday+"T"+
								task_datehour_dmonth+":"+
								$("#task_datehour_dmin").val()+":00"
							);

							let task_datehour_fday=$("#task_datehour_fday").val();
							if (task_datehour_fday.toString().length==1) {
							    task_datehour_fday = "0" + task_datehour_fday;
							}
							let task_datehour_fmonth=$("#task_datehour_fmonth").val();
							if (task_datehour_fmonth.toString().length==1) {
							    task_datehour_fmonth = "0" + task_datehour_fmonth;
							}
							let endDate = new Date(
								$("#task_datehour_fyear").val()+"-"+
								task_datehour_fmonth+"-"+
								task_datehour_fday+"T"+
								$("#task_datehour_fhour").val()+":"+
								$("#task_datehour_fmin").val()+":00"
							);
							//console.log(minDate.getTime() >  startDate.getTime(), maxDate.getTime() < endDate.getTime());

							if (startDate.getTime() > endDate.getTime())
							{
								errorMsg += "<p>' . $langs->trans('ErrorInvertedDates') . '</p>";
							}

							/*
							//Remove test on min max time https://assistance.scopen.fr/view.php?id=56
							if (startDate.getTime() < minDate.getTime())
							{
								errorMsg += "<p>' . $langs->trans('ErrorDateToLow', date('d/m/Y ' . $minHour, $schedule->task_datehour_d)) . '</p>";
							}

							if (endDate.getTime() > maxDate.getTime())
							{
								errorMsg += "<p>' . $langs->trans('ErrorDateToHigh', ((!empty($schedule->task_datehour_f)) ? date('d/m/Y ' . $maxHour, $schedule->task_datehour_f) : date("Y-m-d\T" . $maxHour . ":00"))) . '</p>";
							}*/

							if (errorMsg.length) $("#alert").html(errorMsg).show();
							else
							{
							    let endDateVal = endDate.getTime() / 1000;
							    if (isNaN(endDateVal)) {
							        endDateVal="";
							    }
							    let startDateVal = startDate.getTime() / 1000;
							    if (isNaN(startDateVal)) {
							        startDateVal="";
							    }
								$.ajax({
									url:"' . dol_buildpath('/operationorder/scripts/interface.php', 1) . '",
									method:"POST",
									data: {
										action: "updateSchedule",
										scheduleId: ' . $schedule->id . ',
										startTime: startDateVal,
										endTime: endDateVal,
										token: "'.currentToken().'",
									}
								}).done(function(data){
							      $("form[name=\'filters\']").submit();
								  //$("#schedulePopin").dialog("close")
								});
							}
						});
					});</script>';
	} else $out = $langs->trans('ErrorFetchingCounter');

	return $out;
}

/**
 *  Retourne le tableau des OR plannifiables dans une boite de dialogue
 * @param int $startTime start time
 * @param int $endTime end time
 * @param int $allDay is all day
 * @param string $url url
 * @param int $id id
 * @param string $action action type
 * @param int $beginOfWeek beginOfWeek
 * @param int $endOfWeek endOfWeek
 * @return int|string
 */
function _getTableDialogPlanable($startTime, $endTime, $allDay, $url, $id = 'create-operation-order-action', $action = 'create-operation-order-action', $beginOfWeek = 0, $endOfWeek = 0)
{
	global $db, $langs, $hookmanager;

	$TPlanableOO = OperationOrder::getPlannableOperationOrder();
	$TPlanableOOOptions = array();
	if (!empty($TPlanableOO)) {
		foreach ($TPlanableOO as $key => $operationOrder) {
			$TPlanableOOOptions[$operationOrder->id] = $operationOrder->ref . ' ' . $operationOrder->thirdparty->name;
		}
	}

	$out = '<table id="create-operation-order-action" class="table" style="width:100%;" >';

	$out .= '<thead>';

	$out .= '<tr>';
	$out .= ' <th class="text-center" >' . $langs->trans('Ref') . '</th>';
	$out .= ' <th class="text-center" >' . $langs->trans('RefCustomer') . '</th>';
	$out .= ' <th class="text-center"  >' . $langs->trans('client') . '</th>';
	$out .= ' <th class="text-center" >' . $langs->trans('TimePlannedTheoretical') . '</th>';
	$out .= ' <th class="text-center" >' . $langs->trans('TimePlannedForced') . '</th>';
	$out .= ' <th class="text-center" >' . $langs->trans('Status') . '</th>';

	$parameters = array(
		'out' => & $out
	);
	$reshook = $hookmanager->executeHooks('addOperationorderPlannableTableTitle', $parameters, $object, $action);
	if ($reshook < 0) return -1;


	$out .= '</tr>';

	$out .= '</thead>';

	$out .= '<tbody>';

	foreach ($TPlanableOO as $operationOrder) {
		$out .= '<tr>';

		//ref OR
		$url = dol_buildpath('/operationorder/operationorder_planning.php', 3);
		$out .= ' <td data-order="' . $operationOrder->ref . '" data-search="' . $operationOrder->ref . '"  ><a href="' . $url . '?action=createOperationOrderAction&operationorder=' . $operationOrder->id . '&startTime=' . $startTime . '&endTime=' . $endTime . '&endOfWeek=' . $endOfWeek . '&beginOfWeek=' . $beginOfWeek . '">' . $operationOrder->ref . '</a></td>';

		//ref client
		$out .= ' <td data-order="' . $operationOrder->ref_client . '" data-search="' . $operationOrder->ref_client . '"  >' . $operationOrder->ref_client . '</td>';

		//Nom Client
		$soc = new Societe($db);
		$res = $soc->fetch($operationOrder->fk_soc);
		if ($res < 0) return -1;
		$out .= ' <td data-order="' . $soc->name . '" data-search="' . $soc->name . '"  >' . $soc->name . '</td>';

		//durée théorique et forcée
		$out .= ' <td>' . convertSecondToTime($operationOrder->time_planned_t) . '</td>';
		$out .= ' <td>' . convertSecondToTime($operationOrder->time_planned_f) . '</td>';

		$out .= ' <td>' . $operationOrder->getLibStatut() . '</td>';

		$parameters = array(
			'out' => & $out,
			'operationOrder' => $operationOrder
		);
		$reshook = $hookmanager->executeHooks('addOperationorderPlannableTableField', $parameters, $object, $action);
		if ($reshook < 0) return -1;

		$out .= '</tr>';
	}
	$out .= '</tbody>';

	$out .= '</table>';

	$out .= '<script src="' . dol_buildpath('/operationorder/vendor/data-tables/datatables.min.js', 3) . '"></script>';
	$out .= '<script src="' . dol_buildpath('/operationorder/vendor/data-tables/jquery.dataTables.min.js', 3) . '"></script>';

	$out .= '<script type="text/javascript" >
					$(document).ready(function(){

					    $("#create-operation-order-action").DataTable({
						"pageLength" : 10,
						"language": {
							"url": "' . dol_buildpath('/operationorder/vendor/data-tables/french.json', 3) . '"
						},
						responsive: true
					});

					});
			   </script>';

	return $out;
}

/**
 * Met à jour l'événement OR en fonction de l'action effectuée (drop ou resize)
 * @param timestamp $startTime start time
 * @param timestamp $endTime and time
 * @param int $fk_action oction id
 * @param string $action label action
 * @param int $allDay is all day
 * @return  int|array                1 if OK, 0 if KO, -1 if error
 */
function _updateOperationOrderAction($startTime, $endTime, $fk_action, $action, $allDay)
{
	global $db, $user, $langs;

	//  if (!$user->hasRight("operationorder","counter","update"))
	//  {
	//      setEventMessage($langs->trans('NotEnoughPermissions'), 'errors');
	//      return false;
	//  }

	dol_include_once('/operationorder/class/operationorder.class.php');
	dol_include_once('/operationorder/class/operationorderaction.class.php');

	$error = 0;
	$errors=array();

	$operationorder = new OperationOrder($db);

	if ($action == 'drop') {
		//si la date de début de l'événement est hors créneau, on ne fait rien
		if (!$operationorder->verifyScheduleInBusinessHours($startTime)) {
			return array('result'=>1);
		}

		$db->begin();
		$action_or = new OperationOrderAction($db);
		$res = $action_or->fetch($fk_action);

		if ($res <= 0) {
			$errors=array_merge($action_or->errors, $errors);
			$error++;
		}

		if (!$error) {
			$res = $operationorder->fetch($action_or->fk_operationorder);

			if ($res <= 0) {
				$errors=array_merge($operationorder->errors, $errors);
				$error++;
			}
		}

		if (empty($error)) {
			//on recalcule le temps plannifié
			$time_planned = $operationorder->calculatePlannedTimeEventByBusinessHours($startTime, $endTime);

			$action_or->dated = $startTime;
			$operationorder->time_planned_f = $time_planned;

			if (!empty($allDay)) $action_or->fullday = 1;
			$res = $action_or->save($user);

			if ($res > 0) {
				$or = new OperationOrder($db);
				$res = $or->fetch($action_or->fk_operationorder);
				if ($res > 0) {
					$or->planned_date = intval($action_or->dated);
					$resSave = $or->save($user);
					if ($resSave<0) {
						$errors=array_merge($action_or->errors, $errors);
						$error++;
					}
				}
			} else {
				$errors=array_merge($action_or->errors, $errors);
				$error++;
			}
		}
	} else {
		if ($allDay) {
			return array('result'=>1);
		}

		if (!empty($fk_action)) {
			$db->begin();

			$action_or = new OperationOrderAction($db);
			$res = $action_or->fetch($fk_action);

			if ($res) {
				$res = $operationorder->fetch($action_or->fk_operationorder);

				if ($res) {
					//si la date de fin du resize est différente de la date de fin de l'action or
					if ($endTime != $action_or->datef) {
						//on recalcule le temps plannifié
						$time_planned = $operationorder->calculatePlannedTimeEventByBusinessHours($startTime, $endTime);
						if ($time_planned < 0) {
							$errors=array_merge($operationorder->errors, $errors);
							$error++;
						}

						if (empty($error)) {
							$action_or->datef = $endTime;

							$res = $action_or->save($user);
							if ($res < 0) $error++;

							$operationorder->time_planned_f = $time_planned;
							$res = $operationorder->save($user);
							if ($res < 0) $error++;
						}
					}
				} else {
					$error++;
				}
			} else {
				$errors=array_merge($action_or->errors, $errors);
				$error++;
			}
		}
	}

	if (!$error) {
		$db->commit();
		return array('result'=>1);
	} else {
		$db->rollback();
		$errorData['result'] = -1;
		$errorData['errorMsg'] = $errors;
		return $errorData;
	}
}

/**
 * @param int $operationOrderId or id
 * @param array $TItem item array
 * @param int $parent parent id
 * @param string $errorMsg error message
 * @param int $updated is updated
 * @return int
 * @throws Exception
 */
function _updateOperationOrderlevelHierarchy($operationOrderId, $TItem, $parent = 0, &$errorMsg = '', &$updated = 0)
{
	global $db;

	if (!is_array($TItem)) {
		$errorMsg .= 'Error : invalid format' . "\n";
		return -1;
	}

	if (empty($TItem)) {
		return 0;
	}

	foreach ($TItem as $item) {
		if (empty($item['id'])) {
			$errorMsg .= 'Error : invalid format id missing : ' . $item['id'] . "\n";
			return -1;
		}

		$item['id'] = str_replace("item_", "", $item['id']);
		if (empty($item['id']) || !is_numeric($item['id'])) {
			$errorMsg .= 'Error : invalid format id' . "\n";
			return -1;
		}

		$item['id'] = intval($item['id']);
		if (!isset($item['order'])) {
			$errorMsg .= 'Error : invalid format order missing' . "\n";
			return -1;
		}

		$rank = intval($item['order']) + 1;
		if (!empty($item['children']) && is_array($item['children'])) {
			$res = _updateOperationOrderlevelHierarchy($operationOrderId, $item['children'], $item['id'], $errorMsg, $updated);
			if ($res < 0) {
				return -1;
			}
		}

		// Update request
		$sql = "UPDATE " . $db->prefix() . "operationorderdet SET";
		$sql .= " rang = " . intval($rank) . " ";
		$sql .= " ,fk_parent_line = " . intval($parent) . " ";
		$sql .= " WHERE rowid=" . $item['id'];
		$sql .= " AND fk_operation_order=" . $operationOrderId;
		//$sql .= " AND fk_parent_line = " . intval($parent); // Vu que le parent ne peut pas être modifié alors on a une erreur

		$db->begin();
		$resql = $db->query($sql);



		if ($resql > 0) {
			if ($parent>0) {
				$or = new OperationOrder($db);
				$res = $or->fetch($operationOrderId);
				if ($res<0) {
					setEventMessages($or->error, $or->errors, 'errors');
				}
			}


			$db->commit();
			$updated++;
		} else {
			dol_syslog(
			"updateOperationOrderlevelHierarchy '" . $sql,
			 LOG_ERR
			);
			$errorMsg .= 'Error : update data base' . "\n";
			$db->rollback();
			return -1;
		}
	}

	return $updated;
}

/**
 * @param array $data data to manage
 * @return void
 */
function _statusRank(&$data)
{
	global $langs;
	$TRowOrder = GETPOST('TRowOrder');
	if (is_array($TRowOrder) && !empty($TRowOrder)) {
		foreach ($TRowOrder as $rank => $value) {
			$rowid = intval($value);
			$rank = intval($rank);

			if ($rowid > 0) {
				OperationOrderStatus::updateRank($rowid, $rank);
			} else {
				$data['errorMsg'] = $langs->trans('StatusNotFound'); // default message for errors
			}
		}
		$data['result'] = 1;
		return;
	} else {
		$data['errorMsg'] = $langs->trans('StatusOrderListEmpty'); // default message for errors
	}
}


/**
 *  Retourne les événements OR sur une période donnée
 * @param int $start start
 * @param int $end end
 * @param string $agendaType type
 * @param int $fk_soc_external soc id
 * @return array
 */
function _getOperationOrderEvents($start = 0, $end = 0, $agendaType = 'orPlanned', $fk_soc_external = 0)
{

	global $db, $hookmanager, $langs, $user, $conf;

	dol_include_once('/operationorder/class/operationorder.class.php');
	dol_include_once('/operationorder/class/operationorderaction.class.php');
	dol_include_once('/operationorder/class/operationorderstatus.class.php');

	$vehiculeCacheImmat= [];

	$sOperationOrder = new OperationOrder($db); // a static usage of operation order class
	$sOperationOrderAction = new OperationOrderAction($db); // a static usage of OperationOrderAction class
	$sOperationOrderStatus = new OperationOrderStatus($db); // a static usage of OperationOrderStatus class


	$langs->loadLangs(array('operationorder@operationorder', 'orders', 'companies', 'bills', 'products', 'other'));

	$TRes = array();

	$sql = 'SELECT o.rowid id, oa.dated, oa.datef, oa.rowid actionid  FROM ' . $db->prefix() . $sOperationOrder->table_element . ' o ';
	$sql .= ' INNER JOIN ' . $db->prefix() . $sOperationOrderAction->table_element . ' oa ON (o.rowid = oa.fk_operationorder) ';
	//$sql.= ' JOIN '.$db->prefix().$sOperationOrderStatus->table_element.' os ON (o.status = s.rowid) ';

	$sql .= ' WHERE 1 = 1 ';

	if (!empty($fk_soc_external)) {
		$sql .= 'AND o.fk_soc=' . (int) $fk_soc_external;
	}

	if (!empty($start)) {
		$sql .= ' AND oa.dated <= \'' . date('Y-m-d H:i:s', $end) . '\'';
	}

	if (!empty($start)) {
		$sql .= ' AND oa.datef >= \'' . date('Y-m-d H:i:s', $start) . '\'';
	}

	$sql .= ' AND o.status IN ( SELECT s.rowid FROM ' . $db->prefix() . $sOperationOrderStatus->table_element . ' s WHERE  display_on_planning > 0 ) ';
	if (empty($fk_soc_external)) {
		$sql .= ' AND o.entity IN (' . getEntity('operationorder', 1) . ') ';
	}

	$resql = $db->query($sql);

	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$event = new fullCalendarEvent();
			$event->title = '';
			$event->msg = '';
			$operationOrder = new OperationOrder($db);
			$operationOrder->fetch($obj->id, false);
			$operationOrder->loadStatusObj();
			if (isModEnabled("stock") && empty($fk_soc_external) && !empty(getDolGlobalString("OPODER_DISPLAY_STOCK_ON_PLANNING"))) {
				$isStockAvailable = $operationOrder->isStockAvailable();
				if ($isStockAvailable === $operationOrder::OR_ONLY_PHYSICAL_STOCK_NOT_ENOUGH) {
					$event->title .= '<i class="fa fa-exclamation" aria-hidden="true" style="color:orange;"></i> &nbsp;';
					$event->msg .= '<i class="fa fa-exclamation" aria-hidden="true" style="color:orange;"></i> &nbsp;' . $langs->trans('OnlyVirtualStockIsEnough') . '<br/>';
				}
				if ($isStockAvailable === $operationOrder::OR_ALL_STOCK_NOT_ENOUGH) {
					$event->title .= '<i class="fa fa-exclamation" aria-hidden="true" style="color:red;"></i> &nbsp;';
					$event->msg .= '<i class="fa fa-exclamation" aria-hidden="true" style="color:red;"></i> &nbsp;' . $langs->trans('NotEnoughStock') . '<br/>';
				}
			}
			$event->title .= $operationOrder->ref;
			if (!empty($operationOrder->fk_vehicule)) {
				if (isset($vehiculeCacheImmat[$operationOrder->fk_vehicule])) {
					$event->title .= ' - '.$vehiculeCacheImmat[$operationOrder->fk_vehicule];
				} else {
					dol_include_once('/dolifleet/class/vehicule.class.php');
					$vehicule = new Vehicule($db);
					$resFetch = $vehicule->fetch($operationOrder->fk_vehicule);
					if ($resFetch>0) {
						if (!empty($vehicule->immatriculation)) {
							$event->title .= ' - '.$vehicule->immatriculation;
							$vehiculeCacheImmat[$operationOrder->fk_vehicule] = $vehicule->immatriculation;
						}
					} else {
						dol_syslog(__FILE__ . '::_getOperationOrderEvents Errors:' . join(',', array_merge([$vehicule->error], $vehicule->errors)), LOG_ERR);
					}
				}
			}


			$obj->dated = $db->jdate($obj->dated);
			$obj->datef = $db->jdate($obj->datef);

			if (empty($fk_soc_external)) {
				$event->url = dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $operationOrder->id;
			}
			$event->start = date('c', $obj->dated);
			$event->end = date('c', $obj->datef);

			$fullcalendar_scheduler_businessHours_week_end = !empty(getDolGlobalString("FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEK_END")) ? getDolGlobalString("FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEK_END")  : '18:00';
			$testDayEndDate = date("Y-m-d " . $fullcalendar_scheduler_businessHours_week_end . ":00", strtotime($event->end));
			if (date('d', strtotime($event->start)) != date('d', strtotime($event->end)) || strtotime($event->end) > strtotime($testDayEndDate)) {
				// obliger de réécrire les formats des dates pour afficher dans allDay
				// Note: This value is exclusive. For example, an event with the end of 2018-09-03 will appear to span through 2018-09-02 but end before the start of 2018-09-03. See how events are are parsed from a plain object for further details.
				$event->start = date("Y-m-d", strtotime($event->start));

				$addDay = 1;
				if (strtotime($event->end) > strtotime($testDayEndDate)) $addDay++;
				$event->end = date("Y-m-d", strtotime('+' . $addDay . ' day', strtotime($event->end)));
				$event->allDay = true;
			}

			$event->operationOrderId = $obj->id;
			$event->operationOrderActionId = $obj->actionid;
			$event->color = $operationOrder->objStatus->color;

			if ($db->jdate($obj->datef) < time() && empty($fk_soc_external)) {
				$event->color = OO_colorLighten($event->color, 10);
			}

			$T = array();

			$TFieldForTooltip = array('status', 'ref', 'fk_soc', 'planned_date', 'time_planned_t', 'time_planned_f');

			foreach ($operationOrder->fields as $fieldKey => $field) {
				if (!in_array($fieldKey, $TFieldForTooltip)) continue;

				$T[$fieldKey] = $langs->trans($field['label']) . ' : ' . $operationOrder->showOutputFieldQuick($fieldKey);
			}


			$T['datef'] = $langs->trans('DateEnd') . ' : ' . date('d/m/Y H:i:s', $obj->datef);

			$operationOrder->fetchLines();
			foreach ($operationOrder->lines as $line) {
				if ($line->product->type == 1 && empty($line->fk_parent_line)) {
					$objectMsg = '<strong>+'.$line->product->label;
					if (!empty($line->description)) {
						$text = preg_replace("/\r|\n/", "", $line->description);
						$text = dol_escape_htmltag($text, 0, 0, '', 0);
						$objectMsg .= ' - ' . substr($text, 0, 30);
						if (strlen($line->description) > 30) {
							$objectMsg .= '...';
						}
					}
					$objectMsg .= '</strong>';
					$T[] = $objectMsg;
				}
			}

			$event->msg .= implode('<br/>', $T);
			//Comment by FHE for perf enhancement
			//$ope_planned=$operationOrder->getTimePlannedT();
			$ope_planned = $operationOrder->time_planned_t;
			if ($ope_planned > 0) {
				$event->ope_planned = $ope_planned;
				//Comment by FHE for perf enhancement
				//$event->ope_spent = $operationOrder->getTimeSpent();
				//$event->ope_percent = round(($event->ope_spent / $event->ope_planned )*100);
			}

			$parameters = array(
				'sqlObj' => $obj,
				'operationOrder' => $operationOrder,
				'T' => $T
			);


			$reshook = $hookmanager->executeHooks('operationorderplanning', $parameters, $event, $agendaType);    // Note that $action and $object may have been modified by hook

			if ($reshook > 0) {
				$event = $hookmanager->resArray;
			}

			$TRes[] = $event;
		}
	} else {
		dol_print_error($db);
	}

	return $TRes;
}


/**
 * Retourne les jours fériées sur une période donnée
 * @param timestamp $start start
 * @param timestamp $end end
 * @return  array $TRes
 */
function _getJourOff($start = 0, $end = 0)
{

	global $db;

	dol_include_once('/operationorder/class/operationorderjoursoff.class.php');

	$dayOff = new OperationOrderJoursOff($db);

	$TFilter = array(
		array(
			'operator' => '>=',
			'value' => date('Y-m-d H:i:s', $start),
			'field' => 'date',
		),
		array(
			'operator' => '<=',
			'value' => date('Y-m-d H:i:s', $end),
			'field' => 'date',
		)
	);

	$TDayOff = $dayOff->fetchAll(0, false, $TFilter);

	$TRes = array();

	if (!empty($TDayOff)) {
		foreach ($TDayOff as $dayOff) {
			$event = new fullCalendarEvent();

			$event->title = $dayOff->label;

			$event->url = '';
			$event->start = date('c', $dayOff->date);
			// $event->end	= date('c', $dayOff->date);
			$event->allDay = true; // will make the time show
			$event->msg = '';
			$event->color = '#a3a3a3';

			if ($db->jdate($dayOff->date) < time()) {
				$event->color = OO_colorLighten($event->color, 10);
			}

			$TRes[] = $event;

			$eventbg = clone $event;
			$eventbg->rendering = 'background';
			$TRes[] = $eventbg;
		}
	}

	return $TRes;
}

/**
 * Retourne la liste d'événements par jour surchargés sur une période donnée
 * @param timestamp $start start
 * @param timestamp $end end
 * @return  array $TRes
 */
function _getJourFull($start = 0, $end = 0)
{

	global $conf;

	$TRes = array();

	$TDates = array();
	$TRes = array();

	$date_start_details = date_parse($start->format('Y-m-d'));
	$date_end_details = date_parse($end->format('Y-m-d'));

	$debut_date = mktime(0, 0, 0, $date_start_details['month'], $date_start_details['day'], $date_start_details['year']);
	$fin_date = mktime(0, 0, 0, $date_end_details['month'], $date_end_details['day'], $date_end_details['year']);

	for ($i = $debut_date; $i < $fin_date; $i += 86400) {
		$TDates[] = $i;
	}

	foreach ($TDates as $date) {
		$isfull = false;

		$res_TimePlanned = getTimePlannedByDate($date);         //temps plannifié par date
		$res_TimeUserCapacity = getTimeAvailableByDateByUsersCapacity($date);    //temps disponible en fonction de la capacité de chaque utilisateur


		//on calcule le pourcentage de temps plannifié par rapport au temps disponible
		$percentage = 0;
		if (!empty($res_TimeUserCapacity)) {
			$percentage = ($res_TimePlanned * 100) / $res_TimeUserCapacity;

			if ($percentage >= getDolGlobalString("OPERATION_ORDER_PERCENTAGECAPACITY_ALERTPLANNINGOR") ) $isfull = true;
		}

		if ($isfull) {
			$event = new fullCalendarEvent();

			$event->title = "";
			$event->start = date('c', $date);
			$event->end = date('c', $date + (60 * 60 * 24));
			$event->msg = '';
			$event->color = '#ff7f00';
			$event->rendering = 'background';

			$TRes[] = $event;
		}
	}

	return $TRes;
}

/**
 * Retourne un événement de surcharge pour le semaine donnée
 * @param timestamp $start start
 * @param timestamp $end end
 * @return  array $TRes
 */
function _getWeekFull($start = 0, $end = 0)
{

	global $conf;

	$TRes = array();

	$start = $start->getTimestamp();
	$end = $end->getTimestamp();

	$isfull = false;

	$res_TimePlanned = getTimePlannedByDate($start, 1);         //temps plannifié par date
	$res_TimeUserCapacity = getTimeAvailableByDateByUsersCapacity($start, 1);    //temps disponible en fonction de la capacité de chaque utilisateur

	//on calcule le pourcentage de temps plannifié par rapport au temps disponible
	if (!empty($res_TimeUserCapacity)) {
		$percentage = ($res_TimePlanned * 100) / $res_TimeUserCapacity;

		if (!empty(getDolGlobalString("OPERATION_ORDER_PERCENTAGECAPACITY_ALERTPLANNINGOR")) && $percentage >= getDolGlobalString("OPERATION_ORDER_PERCENTAGECAPACITY_ALERTPLANNINGOR") ) $isfull = true;
	}

	if ($isfull) {
		$event = new fullCalendarEvent();

		$event->title = "";
		$event->start = date('c', $start);
		$event->end = date('c', $end);
		$event->allDay = true; // will make the time show
		$event->msg = '';
		$event->color = '#ff7f00';

		$TRes[] = $event;
	}

	return $TRes;
}

/**
 * Get Product Info for and OR
 * @param int $fk_operationOrder or id
 * @param int $fk_product product id
 * @return array
 */
function _getProductInfo($fk_operationOrder, $fk_product)
{
	global $langs, $db;

	$data['result'] = 0; // by default if no action result is false
	$data['errorMsg'] = ''; // default message for errors
	$data['warningMsg'] = ''; // default message for errors
	$data['msg'] = '';

	$data['operationorders'] = array();

	if (empty($fk_operationOrder) || empty($fk_product)) {
		return $data;
	}

	// for static usage
	$operationOrder = new OperationOrder($db);
	$operationOrder->fetch($fk_operationOrder);
	$operationOrder->fetch_optionals();
	$operationOrderDet = new operationorderLine($db);

	$TStatusIn = array();
	if (!empty(getDolGlobalString("OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER"))) {
		$TStatusIn = array_map('intval', explode(',', getDolGlobalString("OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER")));
	}

	if (!empty($operationOrder->fk_vehicule)) {
		// Select
		$sql = "SELECT oo.rowid as id FROM " . $db->prefix() . $operationOrder->table_element . " oo";
		$sql .= " JOIN " . $db->prefix() . $operationOrderDet->table_element . " ood ON ( ood.fk_operation_order = oo.rowid )";
		$sql .= " JOIN " . $db->prefix() . "operationorder_extrafields ooe ON oo.rowid = ooe.fk_object";
		$sql .= " WHERE oo.rowid != " . intval($fk_operationOrder);
		$sql .= " AND oo.fk_vehicule = " . $operationOrder->fk_vehicule;
		if (!empty($TStatusIn)) {
			$sql .= " AND oo.status IN (" . implode(',', $TStatusIn) . ")";
		}
		$sql .= " AND ood.fk_product = " . intval($fk_product);
		$sql .= " GROUP BY oo.rowid";


		$resql = $db->query($sql);
		if ($resql) {
			$data['result'] = 1;
			while ($obj = $db->fetch_object($resql)) {
				$operationOrder = new OperationOrder($db);
				$operationOrder->fetch($obj->id);

				$odjData = new stdClass();
				$odjData->id = $operationOrder->id;
				$odjData->ref = $operationOrder->ref;
				$odjData->getNomUrl = $operationOrder->getNomUrl();
				$odjData->array_options = $operationOrder->array_options;

				$odjData->htmlAlert = $langs->trans('ThisProductWasFoundInAnotherOperationOrder') . ' : <br/>';
				$odjData->htmlAlert .= $odjData->getNomUrl . '<br/>';
				$odjData->htmlAlert .= dol_print_date($operationOrder->date_operation_order) . '<br/>';
				$odjData->htmlAlert .= $operationOrder->km_on_creation . 'Km';

				$data['operationorders'][] = $odjData;
			}
		}
	}
	return $data;
}

/**
 * Get Info societe
 * @param int $vehicule_id vehicule id
 * @return array
 */
function _getSocOfVehicule($vehicule_id)
{

	global $db,$conf, $langs;

	$data = array();
	$data['result'] = 0; // by default if no action result is false
	$data['errorMsg'] = ''; // default message for errors
	$data['msg'] = '';
	$data['societe'] = 0;

	$sql = "SELECT fk_soc FROM " . $db->prefix() . "dolifleet_vehicule WHERE rowid ='" . $vehicule_id . "'";
	$resql = $db->query($sql);
	if ($resql && !empty($vehicule_id)) {
		$obj = $db->fetch_object($resql);

		$societe = new Societe($db);
		if ($societe->fetch($obj->fk_soc) > 0) {
			$data['result'] = 1;

			$societeOut = new stdClass();
			$societeOut->id = $societe->id;
			$societeOut->name = $societe->name;

			$data['societe'] = $societeOut;

			if (class_exists('OperationOrder')) {
				// TK1766
				// Lors de la création d’un OR, l’outil doit alerter l’utilisateur si il existe déjà un OR non clôturer sur le véhicule
				$sql = "SELECT o.rowid, o.ref FROM " . $db->prefix() . "operationorder o ";
				$sql .= " WHERE  o.fk_vehicule =" . intval($vehicule_id);

				if (!empty(getDolGlobalString("OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER"))) {
					$TStatusSearch = explode(',', getDolGlobalString("OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER"));
					$TStatusSearch = array_map('intval', $TStatusSearch);

					$sql .= ' AND o.status IN (' . implode(',', $TStatusSearch) . ')';
				}

				$resql = $db->query($sql);
				if ($resql) {
					$num = $db->num_rows($resql);
					if ($num > 0) {
						if ($obj = $db->fetch_object($resql)) {
							$operationOrder = new OperationOrder($db);
							$operationOrder->id = $obj->rowid;
							$operationOrder->ref = $obj->ref;
							$data['warningMsg'] = $langs->transnoentities('NoClosedOperationOrderForThisVehiculeFound', $operationOrder->getNomUrl());
						}
					}
				} else {
					$data['errorMsg'] = 'OperationOrder test error';
				}
			}
		}
	} else {
		$data['errorMsg'] = $langs->trans('fkSocVehiculeError');
	}

	return $data;
}



// Class à but descriptive, de doc etc... elle remplace juste le new stdClass qui etait utilisé juste avant.

/**
 * Full Event Calendar Class
 */
class fullCalendarEvent
{

	public $title;
	public $url;

	/**
	 * @var string $start date c format
	 */
	public $start;

	/**
	 * @var string $start date c format
	 */
	public $end;
	public $msg = '';
	public $color;

	/**
	 * @var bool Determines if the event is shown in the “all-day” section of relevant views. In addition, if true the time text is not displayed with the event.
	 */
	public $allDay = false; // will make the time show

	/**
	 * @var string The rendering type of this event. Can be empty (normal rendering), "background", or "inverse-background"
	 */
	public $rendering = '';

	//
	//  id
	//  String. A unique identifier of an event. Useful for getEventById.
	//
	//  groupId
	//  String. Events that share a groupId will be dragged and resized together automatically.
	//
	//
	//  start
	//  Date object that obeys the current timeZone. When an event begins.
	//
	//  end
	//  Date object that obeys the current timeZone. When an event ends. It could be null if an end wasn’t specified.
	//
	//  Note: This value is exclusive. For example, an event with the end of 2018-09-03 will appear to span through 2018-09-02 but end before the start of 2018-09-03. See how events are are parsed from a plain object for further details.
	//
	//  title
	//  String. The text that will appear on an event.
	//
	//  url
	//  String. A URL that will be visited when this event is clicked by the user. For more information on controlling this behavior, see the eventClick callback.
	//
	//  classNames
	//  An array of strings like [ 'myclass1', myclass2' ]. Determines which HTML classNames will be attached to the rendered event.
	//
	//  editable
	//  Boolean (true or false) or null. The value overriding the editable setting for this specific event.
	//
	//  startEditable
	//  Boolean (true or false) or null. The value overriding the eventStartEditable setting for this specific event.
	//
	//  durationEditable
	//  Boolean (true or false) or null. The value overriding the eventDurationEditable setting for this specific event.
	//
	//  resourceEditable
	//  Boolean (true or false) or null. The value overriding the eventResourceEditable setting for this specific event.
	//
	//  rendering
	//  The rendering type of this event. Can be empty (normal rendering), "background", or "inverse-background"
	//
	//  overlap
	//  The value overriding the eventOverlap setting for this specific event. If false, prevents this event from being dragged/resized over other events. Also prevents other events from being dragged/resized over this event. Does not accept a function.
	//
	//  constraint
	//  The eventConstraint override for this specific event.
	//
	//  backgroundColor
	//  The eventBackgroundColor override for this specific event.
	//
	//  borderColor
	//  The eventBorderColor override for this specific event.
	//
	//  textColor
	//  The eventTextColor override for this specific event.
	//
	//  extendedProps
	//  A plain object holding miscellaneous other properties specified during parsing. Receives properties in the explicitly given extendedProps hash as well as other non-standard properties.
	//
	//  source
	//  A reference to the Event Source this event came from. If the event was added dynamically via addEvent, and the source parameter was not specified, this value will be null.
}
