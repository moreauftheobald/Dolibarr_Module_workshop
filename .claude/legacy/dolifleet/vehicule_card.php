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

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
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
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
dol_include_once('dolifleet/class/vehicule.class.php');
dol_include_once('dolifleet/class/vehiculeLink.class.php');
dol_include_once('/dolifleet/class/dictionaryVehiculeActivityType.class.php');
dol_include_once('dolifleet/lib/dolifleet.lib.php');

if (empty($user->hasRight("dolifleet", "read"))) accessforbidden();

$langs->load('dolifleet@dolifleet');

$action = GETPOST('action', 'aZ09');
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$vin = GETPOST('vin', 'alpha');

$cancel    =  GETPOST('cancel', 'alpha');
$confirm   =  GETPOST('confirm', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'vehiculecard';   // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');

$object = new Vehicule($db);

if (!empty($id) || !empty($ref)) $object->fetch($id, $ref);
if (!empty($vin)) $object->fetchByVin($vin);

$hookmanager->initHooks(array($contextpage, 'globalcard'));


if ($object->isextrafieldmanaged) {
	$extrafields = new ExtraFields($db);

	$extralabels = $extrafields->fetch_name_optionals_label($object->table_element);
	$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');
}


/*
 * Actions
 */

$parameters = array('id' => $id, 'ref' => $ref);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

// Si vide alors le comportement n'est pas remplacé
if (empty($reshook)) {
	if ($cancel) {
		if (!empty($backtopage)) {
			header("Location: " . $backtopage);
			exit;
		}
		$action = '';
	}

	// For object linked
	include DOL_DOCUMENT_ROOT . '/core/actions_dellink.inc.php';        // Must be include, not include_once

	$error = 0;
	switch ($action) {
		case 'add':
		case 'update':
			// Set standard attributes from POST using $fields definition
			foreach ($object->fields as $key => $val) {
				if (in_array($key, array('rowid', 'entity', 'import_key', 'date_creation', 'tms'))) {
					continue;
				}
				if (!GETPOSTISSET($key) && !in_array($val['type'], array('date', 'datetime'))) {
					continue;
				}
				if (preg_match('/^(date|datetime)/', $val['type'])) {
					$object->$key = dol_mktime(
						GETPOSTINT($key.'hour'), GETPOSTINT($key.'min'), GETPOSTINT($key.'sec'),
						GETPOSTINT($key.'month'), GETPOSTINT($key.'day'), GETPOSTINT($key.'year'),
						'tzuserrel'
					);
				} elseif (preg_match('/^(chkbxlst|checkbox)/', $val['type'])) {
					$object->$key = implode(',', GETPOST($key, 'array'));
				} elseif ($val['type'] == 'price' || $val['type'] == 'double') {
					$object->$key = price2num(GETPOST($key, 'alphanohtml'));
				} elseif (preg_match('/^integer/', $val['type']) || preg_match('/^sellist/', $val['type'])) {
					$object->$key = GETPOSTINT($key);
				} elseif ($val['type'] == 'html') {
					$object->$key = GETPOST($key, 'restricthtml');
				} else {
					$object->$key = GETPOST($key, 'alphanohtml');
				}
			}
			// Override dim_pneu multiselect (handled by chkbxlst above, but also via dim_pneu_multiselect)
			if (GETPOSTISSET('dim_pneu')) {
				$object->dim_pneu = implode(',', GETPOST('dim_pneu', 'array'));
			}
			if ($object->isextrafieldmanaged) {
				$ret = $extrafields->setOptionalsFromPost($extralabels, $object);
				if ($ret < 0) $error++;
			}

			if ($error > 0) {
				$action = 'edit';
				break;
			}

			$res = $object->save($user);

			if ($res < 0) {
				setEventMessages(null, $object->errors, 'errors');
				if (empty($object->id)) $action = 'create';
				else $action = 'edit';
				break;
			} else {
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}
		case 'update_extras':

			$object->oldcopy = dol_clone($object);

			// Fill array 'array_options' with data from update form
			$ret = $extrafields->setOptionalsFromPost($extralabels, $object, GETPOST('attribute', 'none'));
			if ($ret < 0) $error++;

			if (!$error) {
				$result = $object->insertExtraFields('DOLIFLEET_MODIFY');
				if ($result < 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					$error++;
				}
			}

			if ($error) $action = 'edit_extras';
			else {
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}
			break;
		case 'confirm_clone':
			$object->cloneObject($user);

			header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
			exit;

		case 'confirm_modif':
		case 'confirm_reopen':
			if (!empty($user->hasRight("dolifleet", "write"))) $object->setDraft($user);

			break;
		case 'confirm_validate':
			if (!empty($user->hasRight("dolifleet", "write"))) $object->setValid($user);

			header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
			exit;

		case 'confirm_delete':
			if (!empty($user->hasRight("dolifleet", "delete"))) $object->delete($user);

			header('Location: ' . dol_buildpath('/dolifleet/vehicule_list.php', 1));
			exit;

		// link from llx_element_element
		case 'dellink':
			$object->deleteObjectLinked(null, '', null, '', GETPOST('dellinkid'));
			header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
			exit;

		case 'addActivity':
			$type = GETPOST('activityTypes', 'int');
			$date_start = dol_mktime(0, 0, 0, GETPOST('activityDate_startmonth'), GETPOST('activityDate_startday'), GETPOST('activityDate_startyear'));
			$date_end = dol_mktime(23, 59, 59, GETPOST('activityDate_endmonth'), GETPOST('activityDate_endday'), GETPOST('activityDate_endyear'));

			if ($date_end < $date_start) $date_end = dol_mktime(23, 59, 59, GETPOST('activityDate_startmonth'), GETPOST('activityDate_startday'), GETPOST('activityDate_startyear'));

			$ret = $object->addActivity($type, $date_start, $date_end);
			if ($ret < 0) {
				setEventMessages($langs->trans($object->error), null, 'errors');
				break;
			} else {
				setEventMessages($langs->trans('ActivityAdded'), null);
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}

		case 'confirm_delActivity':
			$activityId = GETPOST('act_id', 'int');

			$ret = $object->delActivity($user, $activityId);
			if ($ret < 0) {
				setEventMessages($object->error, null, 'errors');
			}

			header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
			exit;

		case 'addVehiculeLink':
			$veh_id = GETPOST('linkVehicule_id', 'int');
			if (empty($veh_id) || $veh_id == '-1') {
				setEventMessages($langs->trans('ErrNoVehiculeToLink'), null, 'errors');
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}

			$date_start = dol_mktime(0, 0, 0, GETPOST('linkDate_startmonth'), GETPOST('linkDate_startday'), GETPOST('linkDate_startyear'));
			$date_end = dol_mktime(23, 59, 59, GETPOST('linkDate_endmonth'), GETPOST('linkDate_endday'), GETPOST('linkDate_endyear'));

			if ($date_end < $date_start) $date_end = dol_mktime(23, 59, 59, GETPOST('linkDate_startmonth'), GETPOST('linkDate_startday'), GETPOST('linkDate_startyear'));

			$ret = $object->addLink($veh_id, $date_start, $date_end);

			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}

		case 'confirm_unlinkVehicule':
			$veh_id = GETPOST('linkVehicule_id', 'int');

			$ret = $object->delLink($veh_id);
			if ($ret < 0) {
				setEventMessages($langs->trans('ErrVehiculeUnlink'), null, 'errors');
				break;
			} else {
				setEventMessages($langs->trans('VehiculeUnlinked'), null);
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}

		case 'addVehiculeOperation':
			$productid = GETPOST('productid', 'int');
			$km = GETPOST('km', 'int');
			$delay = GETPOST('delay', 'int');
			$date_done = dol_mktime(0, 0, 0,
				GETPOST('date_donemonth', 'int'),
				GETPOST('date_doneday', 'int'),
				GETPOST('date_doneyear', 'int'));
			$km_done = GETPOST('km_done', 'int');

			$ret = $object->addOperation($productid, $km, $delay, $date_done, $km_done);
			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}

		case 'confirm_delOperation':
			$ope_id = GETPOST('ope_id', 'int');

			$ret = $object->delOperation($ope_id);
			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				setEventMessages($langs->trans('operationDeleted'), null);
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}

		case 'updateOperation':
			$ope_id = GETPOST('ope_id', 'int');
			$productid = GETPOST('productid', 'int');
			$km = GETPOST('km', 'int');
			$delay = GETPOST('delay', 'int');
			$date_done = dol_mktime(0, 0, 0,
				GETPOST('date_donemonth', 'int'),
				GETPOST('date_doneday', 'int'),
				GETPOST('date_doneyear', 'int'));
			$km_done = GETPOST('km_done', 'int');

			$ret = $object->updateOperation($ope_id, $productid, $km, $delay, $date_done, $km_done);
			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}

		case 'confirm_cloneOperations':
			$sourceVehiculeId = GETPOSTINT('source_vehicule_id');
			if (empty($sourceVehiculeId) || $sourceVehiculeId < 1) {
				setEventMessages($langs->trans('ErrNoVehiculeToCloneFrom'), null, 'errors');
				break;
			}

			dol_include_once('/dolifleet/class/vehiculeOperation.class.php');

			$sourceVehicule = new Vehicule($db);
			$sourceVehicule->fetch($sourceVehiculeId);
			$sourceVehicule->getOperations();

			if (empty($sourceVehicule->operations)) {
				setEventMessages($langs->trans('ErrNoOperationsToClone'), null, 'warnings');
				break;
			}

			$error = 0;
			$nbCloned = 0;
			$dateDoneDefault = !empty($object->date_customer_exploit) ? $object->date_customer_exploit : dol_now();

			foreach ($sourceVehicule->operations as $srcOpe) {
				$ret = $object->addOperation(
					$srcOpe->fk_product,
					$srcOpe->km,
					$srcOpe->delai_from_last_op,
					$dateDoneDefault,
					1
				);
				if ($ret < 0) {
					$error++;
					setEventMessages('', $object->errors, 'errors');
				} else {
					$nbCloned++;
				}
			}

			if ($nbCloned > 0) {
				setEventMessages($langs->trans('CloneOperationsSuccess', $nbCloned), null);
			}
			if ($error == 0) {
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}
			break;

		case 'updateActivity':

			$act_id = GETPOST('act_id', 'int');
			$act_type = GETPOST('activityTypes', 'int');
			$date_start = dol_mktime(0, 0, 0,
				GETPOST('activityDate_startmonth', 'int'),
				GETPOST('activityDate_startday', 'int'),
				GETPOST('activityDate_startyear', 'int'));
			$date_end = dol_mktime(0, 0, 0,
				GETPOST('activityDate_endmonth', 'int'),
				GETPOST('activityDate_endday', 'int'),
				GETPOST('activityDate_endyear', 'int'));

			$soc_id = GETPOST('socid', 'int');

			$ret = $object->updateActivity($act_id, $act_type, $date_start, $date_end, $soc_id);

			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				header('Location: ' . dol_buildpath('/dolifleet/vehicule_card.php', 1) . '?id=' . $object->id);
				exit;
			}
	}
}


/**
 * View
 */
$form = new Form($db);

$keyforbreak = 'fk_contract_type';

$title = $langs->trans('doliFleet');
llxHeader('', $title);

if ($action == 'create') {
	print load_fiche_titre($langs->trans('NewdoliFleet'), '', 'dolifleet@dolifleet');

	print '<form method="POST" action="' . dol_escape_htmltag($_SERVER["PHP_SELF"]) . '">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';

	print dol_get_fiche_head(array(), '');

	print '<table class="border centpercent">' . "\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';

	print '</table>' . "\n";

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" name="add" value="' . dol_escape_htmltag($langs->trans('Create')) . '">';
	print '&nbsp; ';
	print '<input type="' . ($backtopage ? "submit" : "button") . '" class="button" name="cancel" value="' . dol_escape_htmltag($langs->trans('Cancel')) . '"' . ($backtopage ? '' : ' onclick="javascript:history.go(-1)"') . '>';    // Cancel for create does not post form if we don't know the backtopage
	print '</div>';

	print '</form>';
} else {
	if (empty($object->id)) {
		$langs->load('errors');
		print $langs->trans('ErrorRecordNotFound');
	} else {
		if (!empty($object->id) && $action === 'edit') {
			print '<form method="POST" action="' . dol_escape_htmltag($_SERVER["PHP_SELF"]) . '">';
			print '<input type="hidden" name="token" value="' . newToken() . '">';
			print '<input type="hidden" name="action" value="update">';
			print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
			print '<input type="hidden" name="id" value="' . $object->id . '">';

			$head = vehicule_prepare_head($object);
			$picto = 'dolifleet@dolifleet';
			print dol_get_fiche_head($head, 'card', $langs->trans('doliFleet'), 0, $picto);

			print '<table class="border centpercent">' . "\n";

			// Common attributes
			include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_edit.tpl.php';

			// Other attributes
			include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_edit.tpl.php';

			print '</table>';

			print dol_get_fiche_end();

			print '<div class="center"><input type="submit" class="button" name="save" value="' . $langs->trans('Save') . '">';
			print ' &nbsp; <input type="submit" class="button" name="cancel" value="' . $langs->trans('Cancel') . '">';
			print '</div>';

			print '</form>';
		} elseif ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
			$head = vehicule_prepare_head($object);
			$picto = 'dolifleet@dolifleet';
			print dol_get_fiche_head($head, 'card', $langs->trans('doliFleet'), -1, $picto);

			$formconfirm = getFormConfirmdoliFleetVehicule($form, $object, $action);
			if (!empty($formconfirm)) print $formconfirm;

			printBannerVehicleCard($object);

			print '<div class="fichecenter">';

			print '<div class="fichehalfleft">'; // Auto close by commonfields_view.tpl.php
			print '<div class="underbanner clearboth"></div>';
			print '<table class="border tableforfield" width="100%">' . "\n";

			// Common attributes
			include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_view.tpl.php';

			// Other attributes
			include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';
			print '</table>';

			print '</div>'; // Fin fichehalfright & ficheaddleft
			print '</div>'; // Fin fichecenter

			print '<div class="clearboth"></div><br />';

			print '<div class="tabsAction">' . "\n";
			$parameters = array();
			$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);    // Note that $action and $object may have been modified by hook
			if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

			if (empty($reshook)) {
				if ($object->status == 1) {
					print '<div class="inline-block divButAction"><a class="butAction" href="' . dol_buildpath('/operationorder/operationorder_card.php', 1) . '?action=create&mainmenu=commercial&fk_soc=' . $object->fk_soc . '&fk_vehicule=' . $object->id . '">' . $langs->trans("CreateOperationOrderFromVehicule") . '</a></div>' . "\n";
					print '<input type="hidden" name="ordercreatedid" id="ordercreatedid" />';
				}
				// Modify
				if (!empty($user->hasRight("dolifleet", "write"))) {
					// Modify
					print '<div class="inline-block divButAction"><a class="butAction" href="' . dol_escape_htmltag($_SERVER["PHP_SELF"]) . '?id=' . $object->id . '&amp;action=edit">' . $langs->trans("doliFleetModify") . '</a></div>' . "\n";

					// Activer
					if ($object->status === Vehicule::STATUS_DRAFT) print '<div class="inline-block divButAction"><a class="butAction" href="' . dol_escape_htmltag($_SERVER["PHP_SELF"]) . '?id=' . $object->id . '&amp;action=valid">' . $langs->trans('doliFleetActivate') . '</a></div>' . "\n";

					// Désactiver
					if ($object->status === Vehicule::STATUS_ACTIVE) print '<div class="inline-block divButAction"><a class="butAction" href="' . dol_escape_htmltag($_SERVER["PHP_SELF"]) . '?id=' . $object->id . '&amp;action=modif">' . $langs->trans('doliFleetUnactivate') . '</a></div>' . "\n";
				} else {
					// Modify
					if ($object->status !== Vehicule::STATUS_ACTIVE) print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans("doliFleetModify") . '</a></div>' . "\n";

					// Clone
					//                  print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans("doliFleetClone").'</a></div>'."\n";

					// Activer
					if ($object->status === Vehicule::STATUS_DRAFT) print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans('doliFleetActivate') . '</a></div>' . "\n";

					// Désactiver
					if ($object->status === Vehicule::STATUS_ACTIVE) print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans('doliFleetUnactivate') . '</a></div>' . "\n";
				}

				if (!empty($user->hasRight("dolifleet", "delete"))) {
					print '<div class="inline-block divButAction"><a class="butActionDelete" href="' . dol_escape_htmltag($_SERVER["PHP_SELF"]) . '?id=' . $object->id . '&amp;action=delete">' . $langs->trans("doliFleetDelete") . '</a></div>' . "\n";
				} else {
					print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans("doliFleetDelete") . '</a></div>' . "\n";
				}
			}
			print '</div>' . "\n";

			print '<div class="fichecenter">';
			print '<div class="fichehalfleft">';

			print '<div class="underbanner clearboth"></div>';

			// Activités véhicule
			printVehiculeActivities($object/*, true*/);
			print '</div>'; // fin fichehalfleft
			print '<div class="fichehalfright">';

			// véhicules liés
			printLinkedVehicules($object/*, true*/);

			print '</div>'; // fin fichehalfright
			print '</div>'; // fin fichehalfcenter


			print '<div class="fichecenter">';
			print '<div class="underbanner clearboth"></div>';

			// Opérations
			printVehiculeOperations($object);

			print '</div>';    // fin fichecenter
			print dol_get_fiche_end(-1);
		}
	}
}


llxFooter();
$db->close();
