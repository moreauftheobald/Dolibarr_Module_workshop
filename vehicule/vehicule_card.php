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
 *    \file       vehicule/vehicule_card.php
 *    \ingroup    workshop
 *    \brief      Page to create/edit/view vehicule
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/workshop/class/Vehicule.class.php');
dol_include_once('/workshop/class/vehiculeLink.class.php');
dol_include_once('/workshop/lib/workshop_vehicule.lib.php');

if (!isModEnabled("workshop")) {
	accessforbidden();
}
if (!$user->hasRight('workshop', 'vehicule', 'read')) {
	accessforbidden();
}

$langs->load('workshop@workshop');

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$vin = GETPOST('vin', 'alpha');

$cancel = GETPOST('cancel', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'vehiculecard';
$backtopage = GETPOST('backtopage', 'alpha');

$object = new Vehicule($db);

if (!empty($id) || !empty($ref)) {
	$object->fetch($id, $ref);
}
if (!empty($vin)) {
	$object->fetchByVin($vin);
}

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
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	if ($cancel) {
		if (!empty($backtopage)) {
			header("Location: ".$backtopage);
			exit;
		}
		$action = '';
	}

	// For object linked
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

	$error = 0;
	switch ($action) {
		case 'add':
		case 'update':
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
			if (GETPOSTISSET('dim_pneu')) {
				$object->dim_pneu = implode(',', GETPOST('dim_pneu', 'array'));
			}
			if ($object->isextrafieldmanaged) {
				$ret = $extrafields->setOptionalsFromPost($extralabels, $object);
				if ($ret < 0) {
					$error++;
				}
			}
			if ($error > 0) {
				$action = 'edit';
				break;
			}
			$res = $object->save($user);
			if ($res < 0) {
				setEventMessages(null, $object->errors, 'errors');
				if (empty($object->id)) {
					$action = 'create';
				} else {
					$action = 'edit';
				}
				break;
			} else {
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}

		case 'update_extras':
			$object->oldcopy = dol_clone($object);
			$ret = $extrafields->setOptionalsFromPost($extralabels, $object, GETPOST('attribute', 'none'));
			if ($ret < 0) {
				$error++;
			}
			if (!$error) {
				$result = $object->insertExtraFields('WORKSHOP_MODIFY');
				if ($result < 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					$error++;
				}
			}
			if ($error) {
				$action = 'edit_extras';
			} else {
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}
			break;

		case 'confirm_clone':
			$object->cloneObject($user);
			header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
			exit;

		case 'confirm_modif':
		case 'confirm_reopen':
			if ($user->hasRight('workshop', 'vehicule', 'write')) {
				$object->setDraft($user);
			}
			break;

		case 'confirm_validate':
			if ($user->hasRight('workshop', 'vehicule', 'write')) {
				$object->setValid($user);
			}
			header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
			exit;

		case 'confirm_delete':
			if ($user->hasRight('workshop', 'vehicule', 'delete')) {
				$object->delete($user);
			}
			header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_list.php', 1));
			exit;

		case 'dellink':
			$object->deleteObjectLinked(null, '', null, '', GETPOST('dellinkid'));
			header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
			exit;

		case 'addActivity':
			$date_start = dol_mktime(0, 0, 0, GETPOST('activityDate_startmonth'), GETPOST('activityDate_startday'), GETPOST('activityDate_startyear'));
			$date_end = dol_mktime(23, 59, 59, GETPOST('activityDate_endmonth'), GETPOST('activityDate_endday'), GETPOST('activityDate_endyear'));
			if ($date_end < $date_start) {
				$date_end = dol_mktime(23, 59, 59, GETPOST('activityDate_startmonth'), GETPOST('activityDate_startday'), GETPOST('activityDate_startyear'));
			}
			$activityType = GETPOSTINT('activity_type');
			$ret = $object->addActivity($activityType, $date_start, $date_end);
			if ($ret < 0) {
				setEventMessages($langs->trans($object->error), null, 'errors');
				break;
			} else {
				setEventMessages($langs->trans('ActivityAdded'), null);
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}

		case 'confirm_delActivity':
			$activityId = GETPOSTINT('act_id');
			$ret = $object->delActivity($user, $activityId);
			if ($ret < 0) {
				setEventMessages($object->error, null, 'errors');
			}
			header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
			exit;

		case 'updateActivity':
			$act_id = GETPOSTINT('act_id');
			$date_start = dol_mktime(0, 0, 0,
				GETPOSTINT('activityDate_startmonth'),
				GETPOSTINT('activityDate_startday'),
				GETPOSTINT('activityDate_startyear'));
			$date_end = dol_mktime(0, 0, 0,
				GETPOSTINT('activityDate_endmonth'),
				GETPOSTINT('activityDate_endday'),
				GETPOSTINT('activityDate_endyear'));
			$soc_id = GETPOSTINT('socid');
			$activityType = GETPOSTINT('activity_type');
			$ret = $object->updateActivity($act_id, $activityType, $date_start, $date_end, $soc_id);
			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}

		case 'addVehiculeLink':
			$veh_id = GETPOSTINT('linkVehicule_id');
			if (empty($veh_id) || $veh_id == '-1') {
				setEventMessages($langs->trans('ErrNoVehiculeToLink'), null, 'errors');
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}
			$date_start = dol_mktime(0, 0, 0, GETPOST('linkDate_startmonth'), GETPOST('linkDate_startday'), GETPOST('linkDate_startyear'));
			$date_end = dol_mktime(23, 59, 59, GETPOST('linkDate_endmonth'), GETPOST('linkDate_endday'), GETPOST('linkDate_endyear'));
			if ($date_end < $date_start) {
				$date_end = dol_mktime(23, 59, 59, GETPOST('linkDate_startmonth'), GETPOST('linkDate_startday'), GETPOST('linkDate_startyear'));
			}
			$ret = $object->addLink($veh_id, $date_start, $date_end);
			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}

		case 'confirm_unlinkVehicule':
			$veh_id = GETPOSTINT('linkVehicule_id');
			$ret = $object->delLink($veh_id);
			if ($ret < 0) {
				setEventMessages($langs->trans('ErrVehiculeUnlink'), null, 'errors');
				break;
			} else {
				setEventMessages($langs->trans('VehiculeUnlinked'), null);
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}

		case 'addVehiculeOperation':
			$fk_maintenance_operation = GETPOSTINT('fk_maintenance_operation');
			$km = GETPOSTINT('km');
			$delay = GETPOSTINT('delay');
			$date_done = dol_mktime(0, 0, 0,
				GETPOSTINT('date_donemonth'),
				GETPOSTINT('date_doneday'),
				GETPOSTINT('date_doneyear'));
			$km_done = GETPOSTINT('km_done');
			$ret = $object->addOperation($fk_maintenance_operation, $km, $delay, $date_done, $km_done);
			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}

		case 'confirm_delOperation':
			$ope_id = GETPOSTINT('ope_id');
			$ret = $object->delOperation($ope_id);
			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				setEventMessages($langs->trans('operationDeleted'), null);
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}

		case 'updateOperation':
			$ope_id = GETPOSTINT('ope_id');
			$fk_maintenance_operation = GETPOSTINT('fk_maintenance_operation');
			$km = GETPOSTINT('km');
			$delay = GETPOSTINT('delay');
			$date_done = dol_mktime(0, 0, 0,
				GETPOSTINT('date_donemonth'),
				GETPOSTINT('date_doneday'),
				GETPOSTINT('date_doneyear'));
			$km_done = GETPOSTINT('km_done');
			$ret = $object->updateOperation($ope_id, $fk_maintenance_operation, $km, $delay, $date_done, $km_done);
			if ($ret < 0) {
				setEventMessages('', $object->errors, "errors");
				break;
			} else {
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}

		case 'confirm_cloneOperations':
			$sourceVehiculeId = GETPOSTINT('source_vehicule_id');
			if (empty($sourceVehiculeId) || $sourceVehiculeId < 1) {
				setEventMessages($langs->trans('ErrNoVehiculeToCloneFrom'), null, 'errors');
				break;
			}
			dol_include_once('/workshop/class/vehiculeOperation.class.php');
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
					$srcOpe->fk_maintenance_operation,
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
				header('Location: '.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$object->id);
				exit;
			}
			break;
	}
}


/*
 * View
 */
$form = new Form($db);

$title = $langs->trans('Vehicule');
llxHeader('', $title);

if ($action == 'create') {
	print load_fiche_titre($langs->trans('NewVehicule'), '', $object->picto);

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';

	print dol_get_fiche_head(array(), '');
	print '<table class="border centpercent">'."\n";
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';
	print '</table>'."\n";
	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" name="add" value="'.dol_escape_htmltag($langs->trans('Create')).'">';
	print '&nbsp; ';
	print '<input type="'.($backtopage ? "submit" : "button").'" class="button" name="cancel" value="'.dol_escape_htmltag($langs->trans('Cancel')).'"'.($backtopage ? '' : ' onclick="javascript:history.go(-1)"').'>';
	print '</div>';
	print '</form>';
} else {
	if (empty($object->id)) {
		$langs->load('errors');
		print $langs->trans('ErrorRecordNotFound');
	} else {
		if (!empty($object->id) && $action === 'edit') {
			print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="update">';
			print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
			print '<input type="hidden" name="id" value="'.$object->id.'">';

			$head = vehiculePrepareHead($object);
			print dol_get_fiche_head($head, 'card', $langs->trans('Vehicule'), 0, $object->picto);
			print '<table class="border centpercent">'."\n";
			include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';
			include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';
			print '</table>';
			print dol_get_fiche_end();

			print '<div class="center"><input type="submit" class="button" name="save" value="'.$langs->trans('Save').'">';
			print ' &nbsp; <input type="submit" class="button" name="cancel" value="'.$langs->trans('Cancel').'">';
			print '</div>';
			print '</form>';
		} elseif ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
			$head = vehiculePrepareHead($object);
			print dol_get_fiche_head($head, 'card', $langs->trans('Vehicule'), -1, $object->picto, 0, '', '', 0, '', 1);

			$formconfirm = getFormConfirmWorkshopVehicule($form, $object, $action);
			if (!empty($formconfirm)) {
				print $formconfirm;
			}

			// Banner
			$linkback = '<a href="'.dol_buildpath('/workshop/vehicule/vehicule_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';

			// Build morehtmlref (marque, type, tiers)
			$morehtmlref = '<div class="refidno">';
			$morehtmlref .= $langs->trans('immatriculation').': <b>'.dol_escape_htmltag($object->immatriculation).'</b>';

			dol_include_once('/workshop/class/vehiculemark.class.php');
			$markDict  = new VehiculeMark($db);
			$markLabel = $markDict->getValueFromId($object->fk_vehicule_mark);
			if (!empty($markLabel)) {
				$morehtmlref .= ' &mdash; '.$langs->trans('vehiculeMark').': <b>'.dol_escape_htmltag($markLabel).'</b>';
			}

			dol_include_once('/workshop/class/vehiculetype.class.php');
			$typeDict  = new VehiculeType($db);
			$typeLabel = $typeDict->getValueFromId($object->fk_vehicule_type);
			if (!empty($typeLabel)) {
				$morehtmlref .= ' &mdash; '.$langs->trans('vehiculeType').': <b>'.dol_escape_htmltag($typeLabel).'</b>';
			}

			if (!empty($object->fk_soc)) {
				$object->fetch_thirdparty();
				if (!empty($object->thirdparty)) {
					$morehtmlref .= '<br>'.$object->thirdparty->getNomUrl(1);
				}
			}
			$morehtmlref .= '</div>';

			dol_banner_tab($object, 'vin', $linkback, 1, 'vin', 'vin', $morehtmlref, '', 0, '', '');

			$fieldsBackup = $object->fields;

			// Colonne gauche : champs de position < 120 (jusqu'à km_date)
			$object->fields = array_filter($fieldsBackup, function ($f) { return (int) $f['position'] < 120; });
			print '<div class="fichecenter">';
			print '<div class="fichehalfleft">';
			print '<div class="underbanner clearboth"></div>';
			print '<table class="border tableforfield" width="100%">'."\n";
			include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';
			print '</table>';
			print '</div>'; // fin fichehalfleft

			// Colonne droite : champs de position >= 120 (à partir de type de contrat) + champs extra
			$object->fields = array_filter($fieldsBackup, function ($f) { return (int) $f['position'] >= 120; });
			print '<div class="fichehalfright">';
			print '<div class="underbanner clearboth"></div>';
			print '<table class="border tableforfield" width="100%">'."\n";
			include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';
			include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';
			print '</table>';
			print '</div>'; // fin fichehalfright

			print '</div>'; // fin fichecenter

			$object->fields = $fieldsBackup;

			print '<div class="clearboth"></div><br />';

			// Action buttons
			print '<div class="tabsAction">'."\n";
			$parameters = array();
			$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
			if ($reshook < 0) {
				setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
			}
			if (empty($reshook)) {
				if ($object->status == Vehicule::STATUS_ACTIVE && getDolGlobalInt('WORKSHOP_USE_OR')) {
					if ($user->hasRight('workshop', 'operationorders', 'write')) {
						print '<div class="inline-block divButAction"><a class="butAction" href="'.dol_buildpath('/workshop/operationorder/or_card.php', 1).'?action=create&fk_vehicule='.$object->id.'&fk_soc='.$object->fk_soc.'">'.$langs->trans("CreateOperationOrderFromVehicule").'</a></div>'."\n";
					} else {
						print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans("CreateOperationOrderFromVehicule").'</a></div>'."\n";
					}
				}
				if ($user->hasRight('workshop', 'vehicule', 'write')) {
					print '<div class="inline-block divButAction"><a class="butAction" href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'&amp;action=edit">'.$langs->trans("Modify").'</a></div>'."\n";
					if ($object->status === Vehicule::STATUS_DRAFT) {
						print '<div class="inline-block divButAction"><a class="butAction" href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'&amp;action=valid">'.$langs->trans('Activate').'</a></div>'."\n";
					}
					if ($object->status === Vehicule::STATUS_ACTIVE) {
						print '<div class="inline-block divButAction"><a class="butAction" href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'&amp;action=modif">'.$langs->trans('Disactivate').'</a></div>'."\n";
					}
					print '<div class="inline-block divButAction"><a class="butAction" href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'&amp;action=clone&token='.newToken().'">'.$langs->trans("ToClone").'</a></div>'."\n";
				} else {
					if ($object->status !== Vehicule::STATUS_ACTIVE) {
						print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans("Modify").'</a></div>'."\n";
					}
					if ($object->status === Vehicule::STATUS_DRAFT) {
						print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans('Activate').'</a></div>'."\n";
					}
					if ($object->status === Vehicule::STATUS_ACTIVE) {
						print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans('Disactivate').'</a></div>'."\n";
					}
				}
				if ($user->hasRight('workshop', 'vehicule', 'delete')) {
					print '<div class="inline-block divButAction"><a class="butActionDelete" href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'&amp;action=delete">'.$langs->trans("Delete").'</a></div>'."\n";
				} else {
					print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans("Delete").'</a></div>'."\n";
				}
			}
			print '</div>'."\n";

			print '<div class="fichecenter">';
			print '<div class="fichehalfleft">';
			print '<div class="underbanner clearboth"></div>';

			// Activities
			printVehiculeActivities($object);

			print '</div>'; // fin fichehalfleft
			print '<div class="fichehalfright">';

			// Linked vehicules
			printLinkedVehicules($object);

			print '</div>'; // fin fichehalfright
			print '</div>'; // fin fichecenter

			print '<div class="fichecenter">';
			print '<div class="underbanner clearboth"></div>';

			// Operations
			printVehiculeOperations($object);

			print '</div>'; // fin fichecenter

			print dol_get_fiche_end(-1);
		}
	}
}


llxFooter();
$db->close();
