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
 * \file       operationorder/or_card.php
 * \ingroup    workshop
 * \brief      Page to create/edit/view an Operation Order (Ordre de Réparation)
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
dol_include_once('/workshop/class/operationorder.class.php');
dol_include_once('/workshop/lib/workshop_operationorder.lib.php');

if (!isModEnabled('workshop')) {
	accessforbidden();
}
if (!$user->hasRight('workshop', 'operationorders', 'read')) {
	accessforbidden();
}

$langs->loadLangs(array('workshop@workshop'));

$action      = GETPOST('action', 'aZ09');
$confirm     = GETPOST('confirm', 'alpha');
$cancel      = GETPOST('cancel', 'alpha');
$id          = GETPOSTINT('id');
$ref         = GETPOST('ref', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'orcard';
$backtopage  = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

$object      = new Operationorder($db);
$extrafields = new ExtraFields($db);
$form        = new Form($db);

$extrafields->fetch_name_optionals_label($object->table_element);
$hookmanager->initHooks(array('orcard', 'globalcard'));

if ($id > 0 || !empty($ref)) {
	$object->fetch($id, $ref);
}

// Permissions
$permissiontoread    = $user->hasRight('workshop', 'operationorders', 'read');
$permissiontoadd     = $user->hasRight('workshop', 'operationorders', 'write');
$permissiontodelete  = $user->hasRight('workshop', 'operationorders', 'delete');
$permissiontovalidate = $user->hasRight('workshop', 'operationorders', 'write');


/*
 * Actions
 */

if ($cancel) {
	if (!empty($backtopageforcancel)) {
		header("Location: ".$backtopageforcancel);
		exit;
	} elseif (!empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}
	$action = '';
}

$parameters = array();
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Create
	if ($action == 'add' && $permissiontoadd) {
		$object->ref         = GETPOST('ref', 'alpha');
		$object->ref_client  = GETPOST('ref_client', 'alpha');
		$object->fk_soc      = GETPOSTINT('fk_soc');
		$object->fk_vehicule = GETPOSTINT('fk_vehicule');
		$object->km          = GETPOST('km', 'alpha');
		$object->date_planned = dol_mktime(GETPOSTINT('date_plannedhour'), GETPOSTINT('date_plannedmin'), 0, GETPOSTINT('date_plannedmonth'), GETPOSTINT('date_plannedday'), GETPOSTINT('date_plannedyear'));
		$object->fk_user_assign = GETPOSTINT('fk_user_assign');
		$object->note_public  = GETPOST('note_public', 'restricthtml');
		$object->note_private = GETPOST('note_private', 'restricthtml');
		$object->status       = Operationorder::STATUS_DRAFT;
		$object->fk_user_creat = $user->id;

		// Fill array_options with extra fields
		$ret = $extrafields->setOptionalsFromPost(null, $object, '@GETPOSTISSET');
		if ($ret < 0) {
			$error++;
		}

		if (empty($object->fk_soc)) {
			setEventMessages($langs->trans('ErrInvalidSocid'), null, 'errors');
			$action = 'create';
		} elseif (empty($object->fk_vehicule)) {
			setEventMessages($langs->trans('ErrInvalidFkVehicule'), null, 'errors');
			$action = 'create';
		} else {
			$id = $object->create($user);
			if ($id > 0) {
				header("Location: ".$_SERVER['PHP_SELF'].'?id='.$id);
				exit;
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
				$action = 'create';
			}
		}
	}

	// Edit/Save
	if ($action == 'update' && $permissiontoadd) {
		$object->ref_client     = GETPOST('ref_client', 'alpha');
		$object->fk_soc         = GETPOSTINT('fk_soc');
		$object->fk_vehicule    = GETPOSTINT('fk_vehicule');
		$object->km             = GETPOST('km', 'alpha');
		$object->date_planned   = dol_mktime(GETPOSTINT('date_plannedhour'), GETPOSTINT('date_plannedmin'), 0, GETPOSTINT('date_plannedmonth'), GETPOSTINT('date_plannedday'), GETPOSTINT('date_plannedyear'));
		$object->date_start     = dol_mktime(GETPOSTINT('date_starthour'), GETPOSTINT('date_startmin'), 0, GETPOSTINT('date_startmonth'), GETPOSTINT('date_startday'), GETPOSTINT('date_startyear'));
		$object->date_end       = dol_mktime(GETPOSTINT('date_endhour'), GETPOSTINT('date_endmin'), 0, GETPOSTINT('date_endmonth'), GETPOSTINT('date_endday'), GETPOSTINT('date_endyear'));
		$object->fk_user_assign = GETPOSTINT('fk_user_assign');
		$object->note_public    = GETPOST('note_public', 'restricthtml');
		$object->note_private   = GETPOST('note_private', 'restricthtml');

		$ret = $extrafields->setOptionalsFromPost(null, $object, '@GETPOSTISSET');
		if ($ret < 0) {
			$error++;
		}

		$result = $object->update($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			$action = 'view';
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'edit';
		}
	}

	// Delete
	if ($action == 'confirm_delete' && $confirm == 'yes' && $permissiontodelete) {
		$result = $object->delete($user);
		if ($result > 0) {
			header("Location: ".dol_buildpath('/workshop/or_list.php', 1));
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	// Status transitions
	if ($action == 'confirm_open' && $confirm == 'yes' && $permissiontovalidate) {
		$result = $object->setOpen($user);
		if ($result >= 0) {
			header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
	}
	if ($action == 'confirm_done' && $confirm == 'yes' && $permissiontoadd) {
		$result = $object->setDone($user);
		if ($result >= 0) {
			header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
	}
	if ($action == 'confirm_close' && $confirm == 'yes' && $permissiontoadd) {
		$result = $object->setClosed($user);
		if ($result >= 0) {
			header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
	}
	if ($action == 'confirm_reopen' && $confirm == 'yes' && $permissiontoadd) {
		$result = $object->setDraft($user);
		if ($result >= 0) {
			header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
	}
	if ($action == 'confirm_cancel' && $confirm == 'yes' && $permissiontoadd) {
		$result = $object->cancel($user);
		if ($result >= 0) {
			header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
	}
}


/*
 * View
 */

$title = ($action == 'create') ? $langs->trans('NewOperationOrders') : $langs->trans('OperationOrder').' '.$object->ref;

llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'mod-workshop page-orcard');

$head = array();
if ($object->id > 0) {
	$head = operationorderPrepareHead($object);
}

if ($action == 'create') {
	// ========== CREATE FORM ==========
	print load_fiche_titre($langs->trans('NewOperationOrders'), '', 'fa-tools');

	print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">'."\n";
	print '<input type="hidden" name="token" value="'.newToken().'">'."\n";
	print '<input type="hidden" name="action" value="add">'."\n";
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">'."\n";
	}

	print dol_get_fiche_head($head, 'card', $langs->trans('OperationOrder'), -1, 'fa-tools');

	print '<table class="border centpercent tableforfieldcreate">'."\n";

	// Ref
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Ref').'</td>';
	print '<td><input type="text" name="ref" class="flat maxwidth200" value="(PROV)"></td></tr>';

	// Ref client
	print '<tr><td>'.$langs->trans('RefClient').'</td>';
	print '<td><input type="text" name="ref_client" class="flat maxwidth200" value="'.dol_escape_htmltag(GETPOST('ref_client', 'alpha')).'"></td></tr>';

	// Third party
	print '<tr><td class="fieldrequired">'.$langs->trans('ThirdParty').'</td><td>';
	$formcompany = new FormCompany($db);
	print $form->select_company(GETPOSTINT('fk_soc'), 'fk_soc', '', 1, 0, 0, array(), 0, 'maxwidth500 widthcentpercentminusxx');
	print '</td></tr>';

	// Vehicle
	print '<tr><td class="fieldrequired">'.$langs->trans('Vehicule').'</td><td>';
	print $object->showInputField($object->fields['fk_vehicule'], 'fk_vehicule', GETPOSTINT('fk_vehicule'), '', '', '', 'maxwidth500');
	print '</td></tr>';

	// Km
	print '<tr><td>'.$langs->trans('Km').'</td>';
	print '<td><input type="text" name="km" class="flat maxwidth100" value="'.dol_escape_htmltag(GETPOST('km', 'alpha')).'"></td></tr>';

	// Date planned
	print '<tr><td>'.$langs->trans('DatePlanned').'</td><td>';
	print $form->selectDate('', 'date_planned', 1, 1, 0, '', 1, 1, 0, '', '', '', '', 1, '', '', 'auto');
	print '</td></tr>';

	// Assigned user
	print '<tr><td>'.$langs->trans('AssignedTo').'</td><td>';
	print $form->select_dolusers(GETPOSTINT('fk_user_assign'), 'fk_user_assign', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth500');
	print '</td></tr>';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");
	print '</form>'."\n";
} elseif ($object->id > 0) {
	// ========== VIEW / EDIT FORM ==========
	print dol_get_fiche_head($head, 'card', $langs->trans('OperationOrder'), -1, 'fa-tools');

	// Object confirmation dialogs
	if ($action == 'delete') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('DeleteOR'),
			$langs->trans('ConfirmDeleteOR'),
			'confirm_delete',
			'',
			0,
			1
		);
	}
	if ($action == 'open') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('OpenOR'),
			$langs->trans('ConfirmOpenOR'),
			'confirm_open',
			'',
			0,
			1
		);
	}
	if ($action == 'done') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('DoneOR'),
			$langs->trans('ConfirmDoneOR'),
			'confirm_done',
			'',
			0,
			1
		);
	}
	if ($action == 'close') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('CloseOR'),
			$langs->trans('ConfirmCloseOR'),
			'confirm_close',
			'',
			0,
			1
		);
	}
	if ($action == 'reopen') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('ReOpenOR'),
			$langs->trans('ConfirmReOpenOR'),
			'confirm_reopen',
			'',
			0,
			1
		);
	}
	if ($action == 'cancel') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('CancelOR'),
			$langs->trans('ConfirmCancelOR'),
			'confirm_cancel',
			'',
			0,
			1
		);
	}

	// ---- Object header banner ----
	$linkback = '<a href="'.dol_buildpath('/workshop/or_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';

	$morehtmlref = '<div class="refidno">';
	if (!empty($object->ref_client)) {
		$morehtmlref .= $langs->trans('RefClient').': '.$object->ref_client;
	}
	$morehtmlref .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';

	print '<table class="border centpercent tableforfield">'."\n";

	// Status (only in view mode)
	if ($action != 'edit') {
		print '<tr><td class="titlefield">'.$langs->trans('Status').'</td>';
		print '<td>'.$object->getLibStatut(4).'</td></tr>';
	}

	// Third party
	print '<tr><td'.($action == 'edit' ? ' class="fieldrequired"' : '').'>'.$langs->trans('ThirdParty').'</td><td>';
	if ($action == 'edit') {
		print $form->select_company($object->fk_soc, 'fk_soc', '', 1, 0, 0, array(), 0, 'maxwidth500 widthcentpercentminusxx');
	} else {
		if ($object->fk_soc > 0) {
			$soc = new Societe($db);
			$soc->fetch($object->fk_soc);
			print $soc->getNomUrl(1);
		}
	}
	print '</td></tr>';

	// Vehicle
	print '<tr><td'.($action == 'edit' ? ' class="fieldrequired"' : '').'>'.$langs->trans('Vehicule').'</td><td>';
	if ($action == 'edit') {
		print $object->showInputField($object->fields['fk_vehicule'], 'fk_vehicule', $object->fk_vehicule, '', '', '', 'maxwidth500');
	} else {
		if ($object->fk_vehicule > 0) {
			dol_include_once('/workshop/class/Vehicule.class.php');
			$vehicule = new Vehicule($db);
			$vehicule->fetch($object->fk_vehicule);
			print $vehicule->getNomUrl(1);
		}
	}
	print '</td></tr>';

	// Km
	print '<tr><td>'.$langs->trans('Km').'</td><td>';
	if ($action == 'edit') {
		print '<input type="text" name="km" class="flat maxwidth100" value="'.dol_escape_htmltag($object->km).'">';
	} else {
		print dol_escape_htmltag($object->km);
	}
	print '</td></tr>';

	// Assigned user
	print '<tr><td>'.$langs->trans('AssignedTo').'</td><td>';
	if ($action == 'edit') {
		print $form->select_dolusers($object->fk_user_assign, 'fk_user_assign', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth500');
	} else {
		if ($object->fk_user_assign > 0) {
			$userassign = new User($db);
			$userassign->fetch($object->fk_user_assign);
			print $userassign->getNomUrl(1);
		}
	}
	print '</td></tr>';

	print '</table>'."\n";
	print '</div>';

	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">'."\n";

	// Date planned
	print '<tr><td class="titlefield">'.$langs->trans('DatePlanned').'</td><td>';
	if ($action == 'edit') {
		print $form->selectDate($object->date_planned, 'date_planned', 1, 1, 0, '', 1, 1, 0, '', '', '', '', 1, '', '', 'auto');
	} else {
		print dol_print_date($object->date_planned, 'dayhour');
	}
	print '</td></tr>';

	// Date start
	print '<tr><td>'.$langs->trans('DateStart').'</td><td>';
	if ($action == 'edit') {
		print $form->selectDate($object->date_start, 'date_start', 1, 1, 1, '', 1, 1, 0, '', '', '', '', 1, '', '', 'auto');
	} else {
		print dol_print_date($object->date_start, 'dayhour');
	}
	print '</td></tr>';

	// Date end
	print '<tr><td>'.$langs->trans('DateEnd').'</td><td>';
	if ($action == 'edit') {
		print $form->selectDate($object->date_end, 'date_end', 1, 1, 1, '', 1, 1, 0, '', '', '', '', 1, '', '', 'auto');
	} else {
		print dol_print_date($object->date_end, 'dayhour');
	}
	print '</td></tr>';

	// Totals
	print '<tr><td>'.$langs->trans('TotalHT').'</td>';
	print '<td class="right">'.price($object->total_ht).'</td></tr>';

	print '<tr><td>'.$langs->trans('TotalHTPart').'</td>';
	print '<td class="right">'.price($object->total_ht_part).'</td></tr>';

	print '<tr><td>'.$langs->trans('TotalHTMO').'</td>';
	print '<td class="right">'.price($object->total_ht_mo).'</td></tr>';

	print '</table>'."\n";
	print '</div>';
	print '</div>'; // fichecenter

	print dol_get_fiche_end();

	// ---- Action buttons ----
	if ($action == 'edit') {
		print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="POST">'."\n";
		print '<input type="hidden" name="token" value="'.newToken().'">'."\n";
		print '<input type="hidden" name="action" value="update">'."\n";
		print '<input type="hidden" name="id" value="'.$object->id.'">'."\n";
		print $form->buttonsSaveCancel();
		print '</form>'."\n";
	} else {
		// Standard action buttons
		print "\n".'<div class="tabsAction">'."\n";

		// Edit
		if ($permissiontoadd && $object->status != Operationorder::STATUS_CLOSED && $object->status != Operationorder::STATUS_CANCELLED) {
			print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);
		}

		// Open (Draft -> Open)
		if ($object->status == Operationorder::STATUS_DRAFT && $permissiontovalidate) {
			print dolGetButtonAction($langs->trans('OpenOR'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=open&token='.newToken(), '', $permissiontovalidate);
		}

		// Done (Open -> Done)
		if ($object->status == Operationorder::STATUS_OPEN && $permissiontoadd) {
			print dolGetButtonAction($langs->trans('DoneOR'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=done&token='.newToken(), '', $permissiontoadd);
		}

		// Close (Done -> Closed)
		if ($object->status == Operationorder::STATUS_DONE && $permissiontoadd) {
			print dolGetButtonAction($langs->trans('CloseOR'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=close&token='.newToken(), '', $permissiontoadd);
		}

		// Reopen (any non-draft -> Draft)
		if (in_array($object->status, array(Operationorder::STATUS_OPEN, Operationorder::STATUS_DONE, Operationorder::STATUS_CLOSED)) && $permissiontoadd) {
			print dolGetButtonAction($langs->trans('ReOpenOR'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reopen&token='.newToken(), '', $permissiontoadd);
		}

		// Cancel
		if ($object->status != Operationorder::STATUS_CANCELLED && $object->status != Operationorder::STATUS_CLOSED && $permissiontoadd) {
			print dolGetButtonAction($langs->trans('CancelOR'), '', 'danger', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=cancel&token='.newToken(), '', $permissiontoadd);
		}

		// Delete
		if ($permissiontodelete) {
			print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete);
		}

		print '</div>'."\n";

		// ---- Jobs/Lines section ----
		print '<div class="div-table-responsive-no-min">'."\n";
		print '<table class="noborder centpercent">'."\n";
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Ref').'</td>';
		print '<td>'.$langs->trans('Label').'</td>';
		print '<td class="right">'.$langs->trans('TotalHT').'</td>';
		print '</tr>'."\n";

		if (!empty($object->lines)) {
			foreach ($object->lines as $line) {
				print '<tr class="oddeven">';
				print '<td>'.dol_escape_htmltag($line->ref ?? '').'</td>';
				print '<td>'.dol_escape_htmltag($line->label).'</td>';
				print '<td class="right">'.price($line->total_ht).'</td>';
				print '</tr>'."\n";
			}
		} else {
			print '<tr><td colspan="3"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		}

		print '</table>'."\n";
		print '</div>'."\n";
	}
}

llxFooter();
$db->close();
