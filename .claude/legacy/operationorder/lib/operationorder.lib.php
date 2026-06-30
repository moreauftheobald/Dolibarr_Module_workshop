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

/**
 *    \file        lib/operationorder.lib.php
 *    \ingroup    operationorder
 *    \brief        This file is an example module library
 *                Put some comments here
 */

/**
 * @return array
 */
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
dol_include_once('operationorder/class/operationorderuserplanning.class.php');
dol_include_once('/operationorder/class/operationorderjoursoff.class.php');
dol_include_once('/operationorder/class/usergroupoperationorder.class.php');
dol_include_once('/dolifleet/lib/dolifleet.lib.php');
if (isModEnabled('absence')) dol_include_once('/absence/class/absence.class.php');

/**
 * List tab on Admin Setup
 * @return array
 */
function operationorderAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load('operationorder@operationorder');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/operationorder/admin/operationorder_setup.php", 1);
	$head[$h][1] = $langs->trans("Parameters");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/admin/operationorder_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$head[$h][2] = 'extrafields';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/admin/operationorderstatus_extrafields.php", 1);
	$head[$h][1] = $langs->trans("OperationOrderStatusExtrafieldPage");
	$head[$h][2] = 'status_extrafields';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/admin/operationorderstatus_setup.php", 1);
	$head[$h][1] = $langs->trans("OperationOrderStatusAdmin");
	$head[$h][2] = 'settingsStatus';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/admin/operationorderjoursoff_setup.php", 1);
	$head[$h][1] = $langs->trans("oojoursOff");
	$head[$h][2] = 'oojoursOff';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/admin/fullcalendar_setup.php", 1);
	$head[$h][1] = $langs->trans("Planning");
	$head[$h][2] = 'fullcalendar';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/admin/barcode_setup.php", 1);
	$head[$h][1] = $langs->trans("BarCode");
	$head[$h][2] = 'barcode';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/admin/supplierorderfromorderforor_setup.php", 1);
	$head[$h][1] = $langs->trans("SupplierOrderFromOrderForOr");
	$head[$h][2] = 'supplierorderfromorderforor';
	$h++;

	if (!empty(isModEnabled("multicompany"))) {
		$head[$h][0] = dol_buildpath("/operationorder/admin/multicompany_sharing.php", 1);
		$head[$h][1] = $langs->trans("multicompanySharing");
		$head[$h][2] = 'multicompanySharing';
		$h++;
	}

	$head[$h][0] = dol_buildpath("/operationorder/admin/operationorder_about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@operationorder:/operationorder/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@operationorder:/operationorder/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'operationorder');

	return $head;
}


/**
 * Return array of tabs to used on pages for third parties cards.
 *
 * @param OperationOrder $object Object company shown
 * @return    array                Array of tabs
 */
function operationorder_prepare_head(OperationOrder $object)
{
	global $db, $langs, $conf;

	$langs->load("operationorder@operationorder");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/operationorder/operationorder_card.php", 1) . '?id=' . $object->id;
	$head[$h][1] = $langs->trans("OperationOrderCard");
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/operationorder_pointage.php", 1) . '?id=' . $object->id;
	$head[$h][1] = $langs->trans("OperationOrderPointages");
	$head[$h][2] = 'pointage';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/operationorder_debit.php", 1) . '?id=' . $object->id;
	$head[$h][1] = $langs->trans("OperationOrderDebit");
	$head[$h][2] = 'debit';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/operationorder_history.php", 1) . '?id=' . $object->id;
	$head[$h][1] = $langs->trans("OperationOrderHistory");
	$head[$h][2] = 'history';
	$h++;

	$nbOperationOrder = getNbORVehicle($object->fk_vehicule);
	$head[$h][0] = dol_buildpath('operationorder/list.php?fk_vehicule_id=' . $object->fk_vehicule . '&origin=operationorder&originid=' . $object->id, 1);
	$head[$h][1] = $langs->trans('ORListHisto') . '<span class="badge marginleftonlyshort">' . ($nbOperationOrder >= 0 ? $nbOperationOrder : 0) . '</span>';
	$head[$h][2] = 'list';
	$h++;

	if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
		$nbNote = 0;
		if (!empty($object->note_private)) $nbNote++;
		if (!empty($object->note_public)) $nbNote++;
		$head[$h][0] = dol_buildpath('/operationorder/note.php', 1) . '?id=' . $object->id;
		$head[$h][1] = $langs->trans('Notes');
		if ($nbNote > 0) $head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbNote . '</span>';
		$head[$h][2] = 'note';
		$h++;
	}

	require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT . '/core/class/link.class.php';
	$upload_dir = $conf->operationorder->multidir_output[$object->entity] . '/' . dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);
	$head[$h][0] = dol_buildpath("/operationorder/document.php", 1) . '?id=' . $object->id;
	$head[$h][1] = $langs->trans('Documents');
	if (($nbFiles + $nbLinks) > 0) $head[$h][1] .= '<span class="badge marginleftonlyshort">' . ($nbFiles + $nbLinks) . '</span>';
	$head[$h][2] = 'document';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'operationorder@operationorder');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'operationorder@operationorder', 'remove');

	return $head;
}

/**
 * @param Form $form Form object
 * @param OperationOrder $object OperationOrder object
 * @param string $action Triggered action
 * @return string
 */
function getFormConfirmOperationOrder($form, $object, $action)
{
	global $langs, $user;

	$formconfirm = '';

	if ($action === 'setStatus' && $user->hasRight("operationorder", "write", "")) {
		$fk_status = GETPOST('fk_status', 'int');

		if (!empty($fk_status)) {
			// vérification des droits
			$statusAllowed = new OperationOrderStatus($object->db);
			$res = $statusAllowed->fetch($fk_status);
			if ($res > 0 && $statusAllowed->userCan($user, 'changeToThisStatus')) {
				$body = $langs->trans('ConfirmValidateOperationOrderStatusBody', $object->ref, $statusAllowed->label);
				$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id . '&fk_status=' . $fk_status, $langs->trans('ConfirmValidateOperationOrderStatusTitle', $statusAllowed->label), $body, 'confirm_setStatus', '', 0, 1);
			} else {
				setEventMessage($langs->trans('SetStatusStatusNotAllowed'), 'errors');
			}
		} else {
			setEventMessage($langs->trans('SetStatusStatusNotAllowed'), 'errors');
		}
	} elseif ($action === 'close' && $user->hasRight("operationorder", "write", "")) {
		$body = $langs->trans('ConfirmCloseOperationOrderBody');
		$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmCloseOperationOrderTitle'), $body, 'confirm_close', '', 0, 1);
	} elseif ($action === 'modify' && $user->hasRight("operationorder", "write", "")) {
		$body = $langs->trans('ConfirmModifyOperationOrderBody', $object->ref);
		$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmModifyOperationOrderTitle'), $body, 'confirm_modify', '', 0, 1);
	} elseif ($action === 'delete' && $user->hasRight("operationorder", "write")) {
		$body = $langs->trans('ConfirmDeleteOperationOrderBody');
		$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmDeleteOperationOrderTitle'), $body, 'confirm_delete', '', 0, 1);
	} elseif ($action === 'clone' && $user->hasRight("operationorder", "write")) {
		$body = $langs->trans('ConfirmCloneOperationOrderBody', $object->ref);
		$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmCloneOperationOrderTitle'), $body, 'confirm_clone', '', 0, 1);
	} elseif ($action == 'ask_deleteline') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&lineid=' . GETPOST('lineid'), $langs->trans('DeleteProductLine'), $langs->trans('ConfirmDeleteProductLine'), 'confirm_deleteline', '', 0, 1);
	} elseif ($action == 'create_propal_from_or') {
		$formquestion = array(
			array('type' => 'other', 'name' => 'propal_soc_id', 'label' => $langs->trans("SelectThirdParty"), 'value' => $form->select_company(GETPOSTISSET('propal_soc_id') ? GETPOST('propal_soc_id', 'int') : $object->fk_soc, 'propal_soc_id', '(s.client:=:1 OR s.client:=:2 OR s.client:=:3)'))
		);
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ConfirmCreatePropal'), '', 'confirm_create_propal_from_or', $formquestion, 0, 1);
	} elseif ($action == 'create_invoice_from_or') {
		$formquestion = array(
			array('type' => 'other', 'name' => 'invoice_soc_id', 'label' => $langs->trans("SelectThirdParty"), 'value' => $form->select_company(GETPOSTISSET('invoice_soc_id') ? GETPOST('invoice_soc_id', 'int') : $object->fk_soc, 'invoice_soc_id', '(s.client:=:1 OR s.client:=:2 OR s.client:=:3)'))
		);
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ConfirmCreateInvoice'), '', 'confirm_create_invoice_from_or', $formquestion, 0, 1);
	} elseif ($action == 'split') {
		$chkbx = array();
		foreach ($object->lines as $line) {
			if (empty($line->fk_parent_line) && !empty($line->product)) {
				$formquestion[] = array('type' => 'checkbox',
					'name' => 'line_id' . $line->id,
					'label' => $line->product->ref . ' - ' . $line->product->label . (!empty($line->description) ? '<br>' . $line->description : ''),
					'value' => '0',
					'tdclass' => '" style="border-bottom: 1px solid;');
			}
		}
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('OperationOrderSplit'), '', 'confirm_split_or', $formquestion, 0, 1, 600, 600);
	}

	return $formconfirm;
}


/**
 * Return array of tabs to used on pages for third parties cards.
 *
 * @param OperationOrderStatus $object Object company shown
 * @return    array                Array of tabs
 */
function operationOrderStatusPrepareHead(OperationOrderStatus $object)
{
	global $db, $langs, $conf;

	$langs->load("operationorder@operationorder");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/operationorder/operationorderstatus_card.php", 1) . '?id=' . $object->id;
	$head[$h][1] = $langs->trans("OperationOrderStatusCard");
	$head[$h][2] = 'card';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'operationorderstatus@operationorder');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'operationorderstatus@operationorder', 'remove');

	return $head;
}


/**
 * @param Form $form Form object
 * @param OperationOrder $object OperationOrder object
 * @param string $action Triggered action
 * @return string
 */
function getFormConfirmOperationOrderStatus($form, $object, $action)
{
	global $langs, $user;

	$formconfirm = '';

	if ($action === 'valid' && $user->hasRight("operationorder", "write", "")) {
		$body = $langs->trans('ConfirmValidateOperationOrderBody', $object->getRef());
		$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmValidateOperationOrderTitle'), $body, 'confirm_validate', '', 0, 1);
	} elseif ($action === 'modify' && $user->hasRight("operationorder", "write", "")) {
		$body = $langs->trans('ConfirmModifyOperationOrderBody', $object->ref);
		$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmModifyOperationOrderTitle'), $body, 'confirm_modify', '', 0, 1);
	} elseif ($action === 'delete' && $user->hasRight("operationorder", "write", "")) {
		$body = $langs->trans('ConfirmDeleteOperationOrderBody');
		$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmDeleteOperationOrderTitle'), $body, 'confirm_delete', '', 0, 1);
	}


	return $formconfirm;
}


/**
 * Return an object
 *
 * @param string $objecttype Type of object ('invoice', 'order', 'expedition_bon', 'myobject@mymodule', ...)
 * @param Database $db Current DB pointer
 * @return    Commonobject            object id/type
 */
function OperationOrderObjectAutoLoad($objecttype, &$db)
{
	global $conf, $langs;

	$ret = -1;
	$regs = array();

	// Parse $objecttype (ex: project_task)
	$module = $myobject = $objecttype;

	// If we ask an resource form external module (instead of default path)
	if (preg_match('/^([^@]+)@([^@]+)$/i', $objecttype, $regs)) {
		$myobject = $regs[1];
		$module = $regs[2];
	}


	if (preg_match('/^([^_]+)_([^_]+)/i', $objecttype, $regs)) {
		$module = $regs[1];
		$myobject = $regs[2];
	}

	// Generic case for $classpath
	$classpath = $module . '/class';

	// Special cases, to work with non standard path
	if ($objecttype == 'facture' || $objecttype == 'invoice') {
		$classpath = 'compta/facture/class';
		$module = 'facture';
		$myobject = 'facture';
	} elseif ($objecttype == 'commande' || $objecttype == 'order') {
		$classpath = 'commande/class';
		$module = 'commande';
		$myobject = 'commande';
	} elseif ($objecttype == 'propal') {
		$classpath = 'comm/propal/class';
	} elseif ($objecttype == 'supplier_proposal') {
		$classpath = 'supplier_proposal/class';
	} elseif ($objecttype == 'shipping') {
		$classpath = 'expedition/class';
		$myobject = 'expedition';
		$module = 'expedition_bon';
	} elseif ($objecttype == 'delivery') {
		$classpath = 'livraison/class';
		$myobject = 'livraison';
		$module = 'livraison_bon';
	} elseif ($objecttype == 'contract') {
		$classpath = 'contrat/class';
		$module = 'contrat';
		$myobject = 'contrat';
	} elseif ($objecttype == 'member') {
		$classpath = 'adherents/class';
		$module = 'adherent';
		$myobject = 'adherent';
	} elseif ($objecttype == 'cabinetmed_cons') {
		$classpath = 'cabinetmed/class';
		$module = 'cabinetmed';
		$myobject = 'cabinetmedcons';
	} elseif ($objecttype == 'fichinter') {
		$classpath = 'fichinter/class';
		$module = 'ficheinter';
		$myobject = 'fichinter';
	} elseif ($objecttype == 'task') {
		$classpath = 'projet/class';
		$module = 'projet';
		$myobject = 'task';
	} elseif ($objecttype == 'stock') {
		$classpath = 'product/stock/class';
		$module = 'stock';
		$myobject = 'stock';
	} elseif ($objecttype == 'inventory') {
		$classpath = 'product/inventory/class';
		$module = 'stock';
		$myobject = 'inventory';
	} elseif ($objecttype == 'mo') {
		$classpath = 'mrp/class';
		$module = 'mrp';
		$myobject = 'mo';
	}

	// Generic case for $classfile and $classname
	$classfile = strtolower($myobject);
	$classname = ucfirst($myobject);

	if ($objecttype == 'invoice_supplier') {
		$classfile = 'fournisseur.facture';
		$classname = 'FactureFournisseur';
		$classpath = 'fourn/class';
		$module = 'fournisseur';
	} elseif ($objecttype == 'order_supplier') {
		$classfile = 'fournisseur.commande';
		$classname = 'CommandeFournisseur';
		$classpath = 'fourn/class';
		$module = 'fournisseur';
	} elseif ($objecttype == 'stock') {
		$classpath = 'product/stock/class';
		$classfile = 'entrepot';
		$classname = 'Entrepot';
	}

	if (!empty($conf->$module->enabled)) {
		$res = dol_include_once('/' . $classpath . '/' . $classfile . '.class.php');
		if ($res) {
			if (class_exists($classname)) {
				return new $classname($db);
			}
		}
	}
	return $ret;
}

/**
 * Return HTML form for OR
 * @param OperationOrder $object Operation Order
 * @param operationorderLine $line Operation Order Det
 * @param bool $showSubmitBtn show submit button
 * @param string $actionURL  URL to submit
 * @return string
 */
function displayFormFieldsByOperationOrder($object, $line = false, $showSubmitBtn = true, $actionURL = false)
{
	global $langs, $db, $form, $hookmanager, $conf;

	$outForm = '';

	if ($line && $line->id > 0) {
		$action = 'edit';
	} else {
		$action = 'create';
		$line = new operationorderLine($db);
		$line->fk_operation_order = $object->id;
		// set default values
		$line->qty = '';
		$line->price = '';
	}

	if ($actionURL) {
		$actionURL = $_SERVER["PHP_SELF"] . '?id=' . $object->id;

		// Ancors
		$actionURL .= ($action == 'create') ? '#addline' : '#item_' . $line->id;
	} else {
		$actionURL = '';
	}

	$parameters = array(
		'actionUrl' => & $actionURL,
		'line' => & $line
	);

	$reshook = $hookmanager->executeHooks('displayFormFieldsByOperationOrder', $parameters, $object, $action);

	if ($reshook > 0) {
		$outForm = $hookmanager->resPrint;
	} else {
		$outForm .= ($action == 'create') ? '<a name="addline" ></a>' : '';


		$outForm .= '<form name="addproduct" action="' . $actionURL . '" method="POST">' . "\n";
		$outForm .= '<input type="hidden" name="token" value="' . $_SESSION ['newtoken'] . '">' . "\n";
		$outForm .= '<input type="hidden" name="id" value="' . $object->id . '">' . "\n";
		$outForm .= '<input type="hidden" name="fk_parent_line" value="' . intval($line->fk_parent_line) . '">' . "\n";
		$outForm .= '<input type="hidden" name="mode" value="">' . "\n";

		if ($action == 'edit') {
			$outForm .= '<input type="hidden" name="action" value="updateline">' . "\n";
			$outForm .= '<input type="hidden" name="save" value="1">' . "\n";
			$outForm .= '<input type="hidden" name="editline" value="' . $line->id . '">' . "\n";
			$outForm .= '<input type="hidden" name="lineid" value="' . $line->id . '">' . "\n";
			if (empty($line->product)) $line->fetch_product();
			if (!empty($line->product->duration_value)) {
				$line->product->duration_value = floatval($line->product->duration_value);
				$remainder = fmod($line->product->duration_value, 1);
				$minutes = 60 * $remainder;
				$hours = (int) $line->product->duration_value;
				$outForm .= '<input type="hidden" id="unitaire_timehour" value="' . $hours . '">' . "\n";
				$outForm .= '<input type="hidden" id="unitaire_timemin" value="' . $minutes . '">' . "\n";
			}
		} else {
			$outForm .= '<input type="hidden" name="action" value="addline">' . "\n";
		}

		$line->fields = dol_sort_array($line->fields, 'position');


		$outForm .= '<table class="table-full">';
		if (!empty(getDolGlobalString("OORDER_HIDE_TIME_PLANNED_IF_CHILD")) || !empty(getDolGlobalString("OORDER_HIDE_TIME_SPENT_IF_CHILD"))) {
			$TChildLines = $line->fetch_all_children_lines();
		}

		// Display each line fields
		foreach ($line->fields as $key => $val) {
			$outputTime = true;
			if (!empty(getDolGlobalString("OORDER_HIDE_TIME_SPENT_IF_CHILD")) && $key == 'time_spent' && count($TChildLines) > 0) {
				$outForm .= getFieldCardOutputByOperationOrder($line, $key, '', '', '', '', 1, array(), 'hideobject');
				$outputTime = false;
			}
			if (!empty(getDolGlobalString("OORDER_HIDE_TIME_PLANNED_IF_CHILD")) && $key == 'time_planned' && count($TChildLines) > 0) {
				$outForm .= getFieldCardOutputByOperationOrder($line, $key, '', '', '', '', 1, array(), 'hideobject');
				$outputTime = false;
			}
			if ($outputTime) {
				$outForm .= getFieldCardOutputByOperationOrder($line, $key);
			}
		}

		if ($showSubmitBtn) {
			$outForm .= '<tr>';
			$outForm .= '	<td colspan="2"><hr/></td>';
			$outForm .= '</tr>';

			$outForm .= '<tr>';
			$outForm .= '	<td>';
			$outForm .= '	</td>';
			$outForm .= '	<td>';
			if ($action == 'create') {
				$outForm .= '<button type="submit" class="button" >' . $langs->trans('Add') . '</button>';
			} else {
				$outForm .= '<button type="submit" class="button" >' . $langs->trans('Save') . '</button>';
			}
			$outForm .= '	<button type="reset" class="button" >' . $langs->trans('Reset') . '</button>';
			$outForm .= '	</td>';
			$outForm .= '</tr>';
		}

		$outForm .= '</table>';

		$outForm .= '</form>';
	}

	return $outForm;
}

/**
 * Return HTML string to show a field into a page
 * Code very similar with showOutputField of extra fields
 *
 * @param CommonObject $object Array of properties of field to show
 * @param string $key Key of attribute
 * @param string $moreparam To add more parametes on html input tag
 * @param string $keysuffix Prefix string to add into name and id of field (can be used to avoid duplicate names)
 * @param string $keyprefix Suffix string to add into name and id of field (can be used to avoid duplicate names)
 * @param mixed $morecss Value for css to define size. May also be a numeric.
 * @param int $nonewbutton Force to not show the new button on field that are links to object
 * @param array $params param
 * @param string $trClass class Tr
 * @return string
 */
function getFieldCardOutputByOperationOrder($object, $key, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '', $nonewbutton = 1, $params = array(), $trClass = '')
{

	global $langs, $form, $db, $user;

	$val = $object->fields[$key];

	// Discard if extrafield is a hidden field on form
	if (abs($val['visible']) != 1 && abs($val['visible']) != 3) return;

	$mode = 'edit'; // edit or view

	// for some case if you need to change display mode
	if ($key == 'xxxxxx') {
		$mode = 'view';
	}

	if (array_key_exists('enabled', $val) && isset($val['enabled']) && !verifCond($val['enabled'])) return;    // We don't want this field

	$outForm = '<tr id="field_' . $key . '" class="' . $trClass . '">';
	$outForm .= '<td';
	$outForm .= ' class="titlefieldcreate';
	if (array_key_exists('notnull', $val) && $val['notnull'] > 0) $outForm .= ' fieldrequired';
	if ($val['type'] == 'text' || $val['type'] == 'html') $outForm .= ' tdtop';
	$outForm .= '"';
	$outForm .= '>';

	if (!empty($val['help'])) $outForm .= $form->textwithpicto($langs->trans($val['label']), $langs->trans($val['help']));
	elseif (isModEnabled('cataloguefournisseurexterne')
				&& $key=='fk_product'
				&& isset($object->element)
				&& $object->element=='operationorderdet'
				&& empty($object->{$key})) {
				//$outForm .= '<span style="width:80%">'.$langs->trans($val['label']).'</span>';
				dol_include_once('/cataloguefournisseurexterne/class/actions_cataloguefournisseurexterne.class.php');
				$hookCatalogue = new ActionsCatalogueFournisseurExterne($db);
				$hookCatalogue->getButton($user, $langs, array('currentcontext'=>'operationordercard'), 'fk_product');
				$outForm .= '<span style="display:inline-block;width:70%">';
				$outForm .= $langs->trans($val['label']);
				$outForm .= '</span>';
				$outForm .= '<span>';
				$outForm .= $hookCatalogue->resprints;
				$outForm .= '</span>';
	} else {
		$outForm .= $langs->trans($val['label']);
	}

	$outForm .= '</td>';

	$outForm .= '<td>';

	// Load value from object
	$value = '';
	if (isset($object->{$key})) {
		$value = $object->{$key};
	}

	if (GETPOSTISSET($key)) {
		if (in_array($val['type'], array('int', 'integer'))) $value = GETPOST($key, 'int');
		elseif ($val['type'] == 'text' || $val['type'] == 'html') $value = GETPOST($key, 'none');
		else $value = GETPOST($key, 'alpha');
	}

	if (!empty($val['fieldCallBack']) && is_callable($val['fieldCallBack'])) {
		$outForm .= call_user_func($val['fieldCallBack'], $object, $val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton, $params);
	} else {
		if ($mode == 'edit') {
			$outForm .= $object->showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
		} else {
			$outForm .= $object->showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
		}
	}

	$outForm .= '</td>';

	$outForm .= '</tr>';

	return $outForm;
}

/**
 * Add line and reccursive child to an OR
 *
 * @param OperationOrder $object Object
 * @param int $fk_product Product Id
 * @param int $qty Qty
 * @param int $price Price
 * @param int $type product Type
 * @param string $product_desc product desc
 * @param mixed $predef product predef
 * @param string $time_plannedhour time planned hour
 * @param string $time_plannedmin time planned min
 * @param string $time_spenthour time spent hour
 * @param string $time_spentmin time spent min
 * @param string $fk_warehouse Id Warehouse
 * @param string $pc Cost
 * @param int $date_start date Start
 * @param int $date_end date End
 * @param string $product_label Product Label
 * @param bool $dontUpdateObj Do Not update
 * @param int $parent_line parent line Id
 * @param int $fk_c_operationorder_type Operation Type
 * @param float $remise_percent Discount percent
 * @return int > 0 if OK, < 0 if KO
 * @throws Exception
 */
function addLineAndChildToOR(
	$object,
	$fk_product,
	$qty,
	$price,
	$type,
	$product_desc = '',
	$predef = '',
	$time_plannedhour = 0,
	$time_plannedmin = 0,
	$time_spenthour = 0,
	$time_spentmin = 0,
	$fk_warehouse = '',
	$pc = '',
	$date_start = '',
	$date_end = '',
	$product_label = '',
	$dontUpdateObj = false,
	$parent_line = '',
	$fk_c_operationorder_type = '',
	$remise_percent = 0
) {

	global $langs, $db, $conf;

	$error = 0;

	$qty = price2num($qty);
	$time_planned = $time_plannedhour * 60 * 60 + $time_plannedmin * 60; // store in seconds
	$time_spent = $time_spenthour * 60 * 60 + $time_spentmin * 60;

	// Extrafields
	$extrafields = new ExtraFields($db);
	$extralabelsline = $extrafields->fetch_name_optionals_label($object->table_element_line);
	$array_options = $extrafields->getOptionalsFromPost($object->table_element_line, $predef);
	// Unset extrafield
	if (is_array($extralabelsline)) {
		// Get extra fields
		foreach ($extralabelsline as $key => $value) {
			unset($_POST["options_" . $key]);
		}
	}

	if (empty($fk_product) && $type < 0) {
		throw new Exception($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Type')));
	}
	if (empty($fk_product)) {
		throw new Exception($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Product')));
	}

	$prod = new Product($db);
	$prod->fetch($fk_product);

	if ($qty == '' && empty($prod->array_options['options_or_is_job']) && empty($prod->array_options['options_oorder_available_for_supplier_order'])) {
		throw new Exception($langs->trans('ErrorFieldRequired111', $langs->transnoentitiesnoconv('Qty')));
	}
	if (!empty($prod->array_options['options_or_is_job']) && empty($fk_c_operationorder_type)) {
		throw new Exception($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('LineOperationOrderType')));
	}
	if (!empty($prod->array_options['options_or_is_job']) || !empty($prod->array_options['options_oorder_available_for_supplier_order'])) {
		$qty = 1;
	}

	if (empty($prod->id) || $prod->id < 0) {
		throw new Exception(implode("<br>", array_merge($object->errors, [$object->error])));
	}

	$label = (($product_label && $product_label != $prod->label) ? $product_label : '');

	$desc = '';

	// Define output language
	if (!empty(getDolGlobalString("MAIN_MULTILANGS")) && !empty(getDolGlobalString("PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE"))) {
		$outputlangs = $langs;
		$newlang = '';
		if (empty($newlang) && GETPOST('lang_id', 'aZ09')) {
			$newlang = GETPOST('lang_id', 'aZ09');
		}

		if (empty($newlang)) {
			$newlang = $object->thirdparty->default_lang;
		}

		if (!empty($newlang)) {
			$outputlangs = new Translate("", $conf);
			$outputlangs->setDefaultLang($newlang);
		}

		$desc = (!empty($prod->multilangs [$outputlangs->defaultlang] ["description"])) ? $prod->multilangs [$outputlangs->defaultlang] ["description"]
				: $prod->description;
	} else {
		$desc = $prod->description;
	}

	if (!empty($product_desc) && !empty(getDolGlobalString("MAIN_NO_CONCAT_DESCRIPTION"))) {
		$desc = $product_desc;
	} else {
		$desc = dol_concatdesc(
			$desc,
			$product_desc,
			'',
			!empty(getDolGlobalString("MAIN_CHANGE_ORDER_CONCAT_DESCRIPTION")));
	}

	$type = $prod->type;

	$desc = dol_htmlcleanlastbr($desc);

	$info_bits = 0;

	// Insert line
	$result = $object->addline(
		$desc, $qty, $price, $fk_warehouse, $prod->pmp, $time_planned, $time_spent, $fk_product, $info_bits,
		$date_start, $date_end, $type, -1, 0, $parent_line, $label, $array_options, '', 0, $dontUpdateObj,
		$fk_c_operationorder_type, $remise_percent
	);

	if ($result <= 0) {
		throw new Exception(implode("<br>", array_merge($object->errors, [$object->error])));
	}

	$recusiveAddResult = $object->recurciveAddChildLines($result, $fk_product, $qty, $dontUpdateObj, $remise_percent);

	if ($recusiveAddResult < 0) {
		$error++;
		$errors = [$langs->trans('ErrorsOccuredDuringLineChildrenInsert') . '<br>code error: ' . $recusiveAddResult . '<br>' . $object->error];
		if (!empty($object->errors)) {
			$errors += $object->errors;
		}
		throw new Exception(implode("<br>", $errors));
	}

	$ret = $object->fetch($object->id); // Reload to get new records

	if ($ret < 0) {
		throw new Exception(implode("<br>", array_merge($object->errors, [$object->error])));
	}

	return $ret;
}

/** Parses a string into a DateTime object, optionally forced into the given timezone.
 * @param string $string string
 * @param DateTimeZone $timezone time Zone
 * @return DateTime
 * @throws Exception
 */
function OO_parseFullCalendarDateTime($string, $timezone = null)
{
	if (strpos($string, ' ') !== false) $string = str_replace(' ', '+', $string);
	$date = new DateTime($string);
	if ($timezone) {
		// If our timezone was ignored above, force it.
		$date->setTimezone($timezone);
	}
	return $date;
}


/**
 * @param string $hex color in hex
 * @param integer $percent 0 to 100
 * @return string
 */
function OO_colorDarker($hex, $percent)
{
	$steps = intval(255 * $percent / 100) * -1;
	return OO_colorAdjustBrightness($hex, $steps);
}

/**
 * @param string $hex color in hex
 * @param integer $percent 0 to 100
 * @return string
 */
function OO_colorLighten($hex, $percent)
{
	$steps = intval(255 * $percent / 100);
	return OO_colorAdjustBrightness($hex, $steps);
}


/**
 * @param string $hex color in hex
 * @param integer $steps Steps should be between -255 and 255. Negative = darker, positive = lighter
 * @return string
 */
function OO_colorAdjustBrightness($hex, $steps)
{
	// Steps should be between -255 and 255. Negative = darker, positive = lighter
	$steps = max(-255, min(255, $steps));
	// Normalize into a six character long hex string
	$hex = str_replace('#', '', $hex);
	if (strlen($hex) == 3) {
		$hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
	}
	// Split into three parts: R, G and B
	$color_parts = str_split($hex, 2);
	$return = '#';
	foreach ($color_parts as $color) {
		$color = hexdec($color); // Convert to decimal
		$color = max(0, min(255, $color + $steps)); // Adjust color
		$return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT); // Make two char hex code
	}
	return $return;
}

/**
 * Return default timeshift
 * @return array|array[]
 */
function getTHoraire()
{
	global $conf;

	$THoraire = array(
		'defaultHours' => array(
			'boundings' => array(
				array(
					'startHour' => "08:00",
					'endHour' => "17:00"
				)
			)

		)
	);

	$TConfDayOfWeek = array(
		'MAIN_INFO_OPENINGHOURS_MONDAY',
		'MAIN_INFO_OPENINGHOURS_TUESDAY',
		'MAIN_INFO_OPENINGHOURS_WEDNESDAY',
		'MAIN_INFO_OPENINGHOURS_THURSDAY',
		'MAIN_INFO_OPENINGHOURS_FRIDAY',
		'MAIN_INFO_OPENINGHOURS_SATURDAY',
		'MAIN_INFO_OPENINGHOURS_SUNDAY'
	);

	for ($i = 1; $i < 8; $i++) {
		if (!empty(getDolGlobalString($TConfDayOfWeek[$i - 1]))) {
			$confTest = trim($conf->global->{$TConfDayOfWeek[$i - 1]});
			$plages = explode(' ', $confTest);
			if (is_array($plages) && !empty($plages)) {
				if (count($plages) > 1) {
					foreach ($plages as $str) {
						$boundings = explode('-', $str);
						if (count($boundings) != 2) continue;

						if (strpos($boundings[0], 'h')) $boundings[0] = preg_replace('/h/', ':', $boundings[0]);
						if (!strpos($boundings[0], ':')) $boundings[0] = $boundings[0] . ':00';

						if (strpos($boundings[1], 'h')) $boundings[1] = preg_replace('/h/', ':', $boundings[1]);
						if (!strpos($boundings[1], ':')) $boundings[1] = $boundings[1] . ':00';

						$THoraire[$i]['boundings'][] = array(
							'startHour' => $boundings[0],
							'endHour' => $boundings[1]
						);
					}
				} else {
					$boundings = explode('-', $plages[0]);
					if (count($boundings) != 2) continue;

					if (strpos($boundings[0], 'h')) $boundings[0] = preg_replace('/h/', ':', $boundings[0]);
					if (!strpos($boundings[0], ':')) $boundings[0] = $boundings[0] . ':00';

					if (strpos($boundings[1], 'h')) $boundings[1] = preg_replace('/h/', ':', $boundings[1]);
					if (!strpos($boundings[1], ':')) $boundings[1] = $boundings[1] . ':00';

					$THoraire[$i]['boundings'][] = array(
						'startHour' => $boundings[0],
						'endHour' => $boundings[1]
					);
				}
			}
		}
	}

	return $THoraire;
}

/**
 * Renvoie l'html qui permet l'affichage du planning utilisateur
 * @param object $object Object
 * @param string $object_type "user" ou "usergroup"
 * @param string $action action
 * @param integer $usercanmodify 1 can update else no
 * @return string $out
 */
function getOperationOrderUserPlanningToDisplay($object, $object_type, $action = '', $usercanmodify = 0)
{

	global $langs, $db;

	$error = 0;
	$TDays = array('Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi', 'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi', 'Sunday' => 'dimanche');

	$userplanning = new OperationOrderUserPlanning($db);
	$res = $userplanning->fetchByObject($object->id, $object_type);
	if ($res < 0) $error++;

	if (!$error) {
		$out = '';


		if ($action != 'edit') {
			$out .= '<table width="100%" class="liste noborder nobottom">';
			$out .= '<tr class="liste_titre">';
			$out .= '<td>&nbsp</td>';
			$out .= '<td class="center">' . $langs->trans('MorningD') . '</td>';
			$out .= '<td class="center">' . $langs->trans('MorningF') . '</td>';
			$out .= '<td class="center">' . $langs->trans('AfternoonD') . '</td>';
			$out .= '<td class="center">' . $langs->trans('AfternoonF') . '</td>';
			$out .= '</tr>';

			foreach ($TDays as $key => $value) {
				$out .= '<tr>';

				//Title
				$out .= '<td>' . $langs->trans($key) . '</td>';

				//MorningD
				$field = '' . $value . '_heuredam';
				$out .= '<td class="center">' . $userplanning->$field . '</td>';

				//MorningF
				$field = '' . $value . '_heurefam';
				$out .= '<td class="center">' . $userplanning->$field . '</td>';

				//AfternoonD
				$field = '' . $value . '_heuredpm';
				$out .= '<td class="center">' . $userplanning->$field . '</td>';

				//AfternoonF
				$field = '' . $value . '_heurefpm';
				$out .= '<td class="center">' . $userplanning->$field . '</td>';


				$out .= '</tr>';
			}

			$out .= '</table>';

			$out .= '<div class = "center">';

			if ($usercanmodify) $out .= '<a class="butAction" href = "' . $_SERVER['PHP_SELF'] . '?objectid=' . $object->id . '&objecttype=' . $object_type . '&action=edit">' . $langs->trans('Modify') . '</a>';
			if (empty($userplanning->active)) {
				if ($usercanmodify) $out .= '<a class="butAction" href = "' . $_SERVER['PHP_SELF'] . '?objectid=' . $object->id . '&objecttype=' . $object_type . '&action=activate&token='.newToken().'">' . $langs->trans('Activate') . '</a>';
			} else {
				if ($usercanmodify) $out .= '<a class="butAction" href = "' . $_SERVER['PHP_SELF'] . '?objectid=' . $object->id . '&objecttype=' . $object_type . '&action=disable&token='.newToken().'">' . $langs->trans('Disable') . '</a>';
			}

			$out .= '</div>';
		} else {
			$out .= '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"';
			$out .= '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
			$out .= '<input type="hidden" name="action" value="save">';
			$out .= '<input type="hidden" name="objectid" value="' . $object->id . '">';
			$out .= '<input type="hidden" name="objecttype" value="' . $object_type . '">';
			$out .= '<input type="hidden" name="token" value="' . newToken() . '">';

			$out .= '<table width="100%" class="liste noborder nobottom">';
			$out .= '<tr class="liste_titre">';
			$out .= '<td>&nbsp</td>';
			$out .= '<td>' . $langs->trans('MorningD') . '</td>';
			$out .= '<td>' . $langs->trans('MorningF') . '</td>';
			$out .= '<td>' . $langs->trans('AfternoonD') . '</td>';
			$out .= '<td>' . $langs->trans('AfternoonF') . '</td>';
			$out .= '</tr>';

			foreach ($TDays as $key => $value) {
				$out .= '<tr class="oddeven">';

				//Title
				$out .= '<td>' . $langs->trans($key) . '</td>';

				$form = new TFormCore($_SERVER['PHP_SELF'], 'form1', 'POST');

				//MorningD
				$field = '' . $value . '_heuredam';
				$out .= '<td>' . $form->timepicker('', $field, $userplanning->$field, 5, 5, '', 'text', 'H:i', '12:00am', '12:00pm') . '</td>';

				//MorningF
				$field = '' . $value . '_heurefam';
				$out .= '<td>' . $form->timepicker('', $field, $userplanning->$field, 5, 5, '', 'text', 'H:i', '12:00am', '12:00pm') . '</td>';

				//AfternoonD
				$field = '' . $value . '_heuredpm';
				$out .= '<td>' . $form->timepicker('', $field, $userplanning->$field, 5, 5, '', 'text', 'H:i', '12:00pm', '12:00am') . '</td>';

				//AfternoonF
				$field = '' . $value . '_heurefpm';
				$out .= '<td>' . $form->timepicker('', $field, $userplanning->$field, 5, 5, '', 'text', 'H:i', '12:00pm', '12:00am') . '</td>';


				$out .= '</tr>';
			}

			$out .= '</table>';

			$out .= '<div class="center"><input type="submit" class="button" name="save" value="' . $langs->trans('Save') . '">';
			$out .= '</div>';
		}
	}

	if (!$error) return $out;
	else return -1;
}

/**
 * Trie le tableau des créneaux disponibles du planning
 * @param array $TBusinessHours business hour
 * @return array $TBusinessHours
 */
function sortBusinessHours($TBusinessHours)
{

	ksort($TBusinessHours);

	foreach ($TBusinessHours as &$TSchedules) {
		if (!empty($TSchedules)) usort($TSchedules, 'compareHours');
	}

	return $TBusinessHours;
}

/**
 * @param array $a comp 1
 * @param array $b comp 2
 * @return int
 */
function compareHours($a, $b)
{

	return ($a['min'] < $b['min']) ? -1 : 1;
}


/**
 * Renvoie les dates d'une semaine (du lundi au dimanche) qui contient la date donnée
 * @param timestamp $datetime date Time
 * @return array $TDates
 */
function getWeekRange($datetime)
{
	require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
	$date = new DateTime();
	$date = $date->setTimestamp($datetime);
	$i = 0;
	$TDates = array();

	$firstDayOfWeek = dol_get_first_day_week($date->format('d'), $date->format('m'), $date->format('Y'));
	$TDates[] = mktime(0, 0, 0, $firstDayOfWeek['first_month'], $firstDayOfWeek['first_day'], $firstDayOfWeek['first_year']);
	$nextDay = dol_get_next_day($firstDayOfWeek['first_day'], $firstDayOfWeek['first_month'], $firstDayOfWeek['first_year']);

	while ($i < 6) {
		$TDates[] = mktime(0, 0, 0, $nextDay['month'], $nextDay['day'], $nextDay['year']);

		$nextDay = dol_get_next_day($nextDay['day'], $nextDay['month'], $nextDay['year']);

		$i++;
	}

	return $TDates;
}


/**
 * Retourne le temps événement OR planifié total d'une journée (tiens compte du temps théorique)
 * Si "$forWeek" = true alors donne le temps de la semaine où se situe la date donnée
 * @param timestamp $date_timestamp time stamp
 * @param boolean $forWeek true for get weeks
 * @return int (seconds)
 */
function getTimePlannedByDate($date_timestamp, $forWeek = false)
{

	global $db;

	$error = 0;
	$nb_seconds_total = 0;
	$TDates = array();

	if ($forWeek) $TDates = getWeekRange($date_timestamp);
	else $TDates[] = $date_timestamp;

	foreach ($TDates as $date_timestamp) {
		$date = date('Y-m-d', $date_timestamp);

		//on récupère tous les événement OR planifiés sur la journée
		$sql = "SELECT rowid as id FROM " . $db->prefix() . "operationorderaction WHERE dated <= '" . $date . " 23:59:59' AND datef >= '" . $date . " 00:00:00'";
		$resql = $db->query($sql);

		if ($resql) {
			//tant qu'on a un événement OR
			while ($obj = $db->fetch_object($resql)) {
				$or_action = new OperationOrderAction($db);
				$res = $or_action->fetch($obj->id);

				//si on trouve l'OR associé à cet événement
				if ($res < 0) $error++;


				if (!$error) {
					$operationOrder = new OperationOrder($db);
					$res = $operationOrder->fetch($or_action->fk_operationorder, false);

					if ($res < 0) $error++;
				}

				//on calcule le nombre de secondes plannifiées
				if (!$error) {
					//nombre de jours disponibles (tient compte des jours off et des absences) entre le début de l'événement or et la fin
					$TDays = daysAvailableBetween($or_action->dated, $or_action->datef);
					$nbDays = count($TDays);

					//si le jour de la semaine sur lequel on boucle est un jour disponible de l'or, alors on prend son temps plannifié théorique et on le divise par le nombre de jours sur lesquels s'étend l'événement or
					if (in_array($date_timestamp, $TDays)) {
						$nb_seconds_total += round($operationOrder->time_planned_t / $nbDays);
					}
				}
			}
		} else {
			$error++;
		}
	}

	if (!$error) return $nb_seconds_total;
	else return -1;
}

/**
 * Retourne le temps disponible total d'une journée en fonction des plannings de chaque utilisateur et de leurs capacités
 * Si "$forWeek" = true alors donne le temps de la semaine où se situe la date donnée
 * @param timestamp $date_timestamp time stamp
 * @param boolean $forWeek true for get week
 * @return int (seconds)
 */
function getTimeAvailableByDateByUsersCapacity($date_timestamp, $forWeek = false)
{
	global $db, $conf;

	$nb_seconds_total = 0;
	$TDays = array('Mon' => 'lundi', 'Tue' => 'mardi', 'Wed' => 'mercredi', 'Thu' => 'jeudi', 'Fri' => 'vendredi', 'Sat' => 'samedi', 'Sun' => 'dimanche');
	$TDates = array();

	if ($forWeek) {
		$TDates = getWeekRange($date_timestamp);
	} else {
		$TDates[] = $date_timestamp;
	}

	//usergroup paramétré
	$fk_groupuser = getDolGlobalString("OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING");
	if (!$fk_groupuser) {
		return 0;
	}

	foreach ($TDates as $date_timestamp) {
		$day = date('D', $date_timestamp);
		$day = $TDays[$day];

		$usergroup = new UserGroupOperationOrder($db);
		$res = $usergroup->fetch($fk_groupuser);
		$TUsers = $usergroup->listUsersForGroup();

		//jourOff
		$jourOff = new OperationOrderJoursOff($db);
		$currentDate = date('Y-m-d H:i:s', $date_timestamp);
		$res = $jourOff->isOff($currentDate);
		if ($res) break;

		foreach ($TUsers as $user) {
			$nb_seconds_user = 0;
			$absencefullday = false;
			$absenceam = false;
			$absencepm = false;

			$user->fetch_optionals();
			$efficienty_user = !empty($user->array_options['options_efficiency']) ? $user->array_options['options_efficiency'] : 100;

			$userplanning = new OperationOrderUserPlanning($db);
			$res = $userplanning->fetchByObject($user->id, 'user');

			if ($res > 0 && $userplanning->active) {
				//absence
				if (isModEnabled("absence")) {
					$PDOdb = new TPDOdb;
					$absence = new TRH_Absence($db);

					$TPlanning = $absence->requetePlanningAbsence2($PDOdb, '', $user->id, date('Y-m-d', $date_timestamp), date('Y-m-d', $date_timestamp));

					foreach ($TPlanning as $t_current => $TAbsence) {
						foreach ($TAbsence as $fk_user => $TRH_absenceDay) {
							foreach ($TRH_absenceDay as $absence) {
								if (!($absence->isPresence)) {
									if (!empty($absence) && $absence->ddMoment == 'matin' && $absence->dfMoment == 'apresmidi') {
										$absencefullday = true;
									} elseif (!empty($absence) && $absence->ddMoment == 'matin' && $absence->dfMoment == 'matin') {
										$absenceam = true;
									} elseif (!empty($absence) && $absence->ddMoment == 'apresmidi' && $absence->dfMoment == 'apresmidi') {
										$absencepm = true;
									}
								} else {
									$hourMorningStart = date('H:i', strtotime($absence->date_hourStart));
									$hourMorningEnd = date('H:i', strtotime($absence->date_hourMorningEnd));
									$hourAfternoonStart = date('H:i', strtotime($absence->date_hourAfternoonStart));
									$hourAfternoonEnd = date('H:i', strtotime($absence->date_hourEnd));
									if ($userplanning->{$day . '_heuredam'} > $hourMorningStart || empty($userplanning->{$day . '_heurefpm'})) $userplanning->{$day . '_heuredam'} = $hourMorningStart;
									if ($userplanning->{$day . '_heurefam'} < $hourMorningEnd || empty($userplanning->{$day . '_heurefpm'})) $userplanning->{$day . '_heurefam'} = $hourMorningEnd;
									if ($userplanning->{$day . '_heuredpm'} > $hourAfternoonStart || empty($userplanning->{$day . '_heurefpm'})) $userplanning->{$day . '_heuredpm'} = $hourAfternoonStart;
									if ($userplanning->{$day . '_heurefpm'} > $hourAfternoonEnd || empty($userplanning->{$day . '_heurefpm'})) $userplanning->{$day . '_heurefpm'} = $hourAfternoonEnd;
								}
							}
						}
					}
				}

				if (!$absencefullday && !$absenceam) {
					//matin
					$start = new DateTime($userplanning->{$day . '_heuredam'});
					$end = new DateTime($userplanning->{$day . '_heurefam'});
					$diff = $start->diff($end);
					$diffStr = $diff->format('%H:%I');
					$THoursMin = explode(':', $diffStr);

					$nb_seconds_user += convertTime2Seconds($THoursMin[0], $THoursMin[1]);
				}

				if (!$absencefullday && !$absencepm) {
					//après-midi
					$start = new DateTime($userplanning->{$day . '_heuredpm'});
					$end = new DateTime($userplanning->{$day . '_heurefpm'});
					$diff = $start->diff($end);
					$diffStr = $diff->format('%H:%I');
					$THoursMin = explode(':', $diffStr);

					$nb_seconds_user += convertTime2Seconds($THoursMin[0], $THoursMin[1]);
				}
			} else {
				$res = $userplanning->fetchByObject($usergroup->id, 'usergroup');

				if ($res > 0 && $userplanning->active) {
					//matin
					$start = new DateTime($userplanning->{$day . '_heuredam'});
					$end = new DateTime($userplanning->{$day . '_heurefam'});
					$diff = $start->diff($end);
					$diffStr = $diff->format('%H:%I');
					$THoursMin = explode(':', $diffStr);

					$nb_seconds_user += convertTime2Seconds($THoursMin[0], $THoursMin[1]);

					//après-midi
					$start = new DateTime($userplanning->{$day . '_heuredpm'});
					$end = new DateTime($userplanning->{$day . '_heurefpm'});
					$diff = $start->diff($end);
					$diffStr = $diff->format('%H:%I');
					$THoursMin = explode(':', $diffStr);

					$nb_seconds_user += convertTime2Seconds($THoursMin[0], $THoursMin[1]);
				} else {
					//config par défaut
					//semaine
					if ($day == 'lundi' || $day == 'mardi' || $day == 'mercredi' || $day == 'jeudi' || $day == 'vendredi') {
						$start = new DateTime(getDolGlobalString("FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEK_START"));
						$end = new DateTime(getDolGlobalString("FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEK_END"));
						$diff = $start->diff($end);
						$diffStr = $diff->format('%H:%I');
						$THoursMin = explode(':', $diffStr);

						$nb_seconds_user += convertTime2Seconds($THoursMin[0], $THoursMin[1]);
					} else {
						//week-end
						$start = new DateTime(getDolGlobalString("FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEKEND_START"));
						$end = new DateTime(getDolGlobalString("FULLCALENDARSCHEDULER_BUSINESSHOURS_WEEKEND_END"));
						$diff = $start->diff($end);
						$diffStr = $diff->format('%H:%I');
						$THoursMin = explode(':', $diffStr);

						$nb_seconds_user += convertTime2Seconds($THoursMin[0], $THoursMin[1]);
					}
				}
			}

			$nb_seconds_user = $nb_seconds_user * ($efficienty_user / 100);
			$nb_seconds_total += $nb_seconds_user;
		}
	}

	return $nb_seconds_total;
}

/**
 * Is this day already fully book
 * @param int $date Time Stamp
 * @return bool
 */
function isJourFull($date)
{
	global $conf;

	$isfull = false;

	$timeStamp = new DateTime();
	$timeStamp->setTimestamp($date);
	$timeStamp->setTime(0, 0, 0);
	$timeStamp = $timeStamp->getTimestamp();

	$res_TimePlanned = getTimePlannedByDate($timeStamp);
	//temps plannifié par date
	$res_TimeUserCapacity = getTimeAvailableByDateByUsersCapacity($timeStamp);    //temps disponible en fonction de la capacité de chaque utilisateur
	//on calcule le pourcentage de temps plannifié par rapport au temps disponible
	$percentage = 0;
	if (!empty($res_TimeUserCapacity)) {
		$percentage = ($res_TimePlanned * 100) / $res_TimeUserCapacity;
		if ($percentage >= getDolGlobalString("OPERATION_ORDER_PERCENTAGECAPACITY_ALERTPLANNINGOR") ) $isfull = true;
	}
	return $isfull;
}

/**
 * Nb open day between dates
 * @param int $dated date start
 * @param int $datef date end
 * @return array
 */
function daysAvailableBetween($dated, $datef)
{

	global $db;

	$dateStart = new DateTime();
	$dateStart->setTimestamp($dated);

	$dateEnd = new DateTime();
	$dateEnd->setTimestamp($datef);

	//Dates de la semaine en cours
	$TDates = array();

	$date_start_details = date_parse($dateStart->format('Y-m-d'));
	$date_end_details = date_parse($dateEnd->format('Y-m-d'));

	$debut_date = mktime(0, 0, 0, $date_start_details['month'], $date_start_details['day'], $date_start_details['year']);
	$fin_date = mktime(0, 0, 0, $date_end_details['month'], $date_end_details['day'], $date_end_details['year']);

	for ($i = $debut_date; $i <= $fin_date; $i += 86400) {
		$TDates[] = $i;
	}

	$operationOrder = new OperationOrder($db);
	$TBusinessHours = $operationOrder->getNextSchedules($dated);

	$TDays = array();

	if (!empty($TBusinessHours) && !empty($TDates)) {
		foreach ($TDates as $date) {
			foreach ($TBusinessHours as $TSchedule) {
				if ($TSchedule['date'] == $date && !in_array($date, $TDays)) {
					$TDays[] = $date;
				}
			}
		}
	}

	return $TDays;
}

/**
 * create default schedule
 * @param int $entity entity
 * @return array
 * @throws Exception
 */
function initSchedule($entity = 1)
{
	global $conf, $db, $langs;

	$TSchedules = array();
	$userGroup = new UserGroupOperationOrder($db);
	$changeEntity = $entity != 1;

	if ($changeEntity) {
		$oldEntity = $conf->entity;
		$conf->entity = $entity;
		$conf->setValues($db);
	}

	$retgroup = $userGroup->fetch(getDolGlobalString("OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING"));
	if ($retgroup > 0) {
		$userList = $userGroup->listUsersForGroup();
		if (!empty($userList)) {
			foreach ($userList as $u) {
				$TSchedules[$u->id] = new stdClass;
				$TSchedules[$u->id]->title = $u->getFullName($langs);
				$TSchedules[$u->id]->schedule = array();
				$TSchedules[$u->id]->userId = $u->id;
			}
		}
	}

	if ($changeEntity) {
		$conf->entity = $oldEntity;
		$conf->setValues($db);
	}
	return $TSchedules;
}

/**
 * Get Counter planning
 * @param array $TSchedules array of timeshift
 * @param int $date date time
 * @param int $entity entity
 * @return mixed
 * @throws Exception
 */
function getCountersForPlanning($TSchedules, $date, $entity = 1)
{
	global $conf, $db, $langs, $hookmanager;

	dol_include_once('/operationorder/class/operationorder.class.php');
	dol_include_once('/operationorder/class/operationordertasktime.class.php');

	$TOr = $TOrDet = array();
	$userGroup = new UserGroupOperationOrder($db);

	$oldEntity = $conf->entity;
	$conf->entity = $entity;
	$conf->setValues($db);

	$retgroup = $userGroup->fetch(getDolGlobalString("OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING"));

	if ($retgroup > 0) {
		$userList = $userGroup->listUsersForGroup();
		if (!empty($userList)) {
			foreach ($userList as $u) {
				if (!isset($TSchedules[$u->id])) {
					$TSchedules[$u->id] = new stdClass;
					$TSchedules[$u->id]->title = $u->getFullName($langs);
					$TSchedules[$u->id]->schedule = array();
				}

				$sql = "SELECT rowid FROM " . $db->prefix() . "operationordertasktime";
				$sql .= " WHERE fk_user = " . $u->id;
				$sql .= " AND task_datehour_d < '" . date("Y-m-d 23:59:59", $date) . "'";
				$sql .= " AND (task_datehour_f > '" . date("Y-m-d 00:00:00", $date) . "' OR task_datehour_f IS NULL)";
				$sql .= " AND entity = " . $entity;

				$resql = $db->query($sql);
				if ($resql && $db->num_rows($resql)) {
					while ($obj = $db->fetch_object($resql)) {
						$class = 'improd';

						$tt = new OperationOrderTaskTime($db);
						$res = $tt->fetch($obj->rowid);
						if ($res > 0) {
							$inProgress = 0;

							if (empty($tt->task_datehour_f)) {
								$tt->task_datehour_f = dol_now();
								$inProgress = 1;
							}

							$label = $tt->label;
							$title = $tt->label . '<br />' . date("H:i", $tt->task_datehour_d) . ' - ' . date("H:i", $tt->task_datehour_f);

							if (!empty($tt->fk_orDet)) {
								if (!array_key_exists($tt->fk_orDet, $TOrDet)) {
									$det = new operationorderLine($db);
									$ret = $det->fetch($tt->fk_orDet);
									if ($ret > 0) {
										$TOrDet[$tt->fk_orDet] = $det;
									}
								}

								if (array_key_exists($tt->fk_orDet, $TOrDet)) {
									if (!array_key_exists($TOrDet[$tt->fk_orDet]->fk_operation_order, $TOr)) {
										$OR = new OperationOrder($db);
										$OR->fetch($TOrDet[$tt->fk_orDet]->fk_operation_order);
										$TOr[$TOrDet[$tt->fk_orDet]->fk_operation_order] = $OR;
									}

									if (array_key_exists($TOrDet[$tt->fk_orDet]->fk_operation_order, $TOr)) {
										$label = $TOr[$TOrDet[$tt->fk_orDet]->fk_operation_order]->ref . ' - ' . $tt->label;

										$T = array();

										$TFieldForTooltip = array('status', 'ref', 'ref_client', 'fk_soc', 'planned_date', 'time_planned_t', 'time_planned_f');

										foreach ($TOr[$TOrDet[$tt->fk_orDet]->fk_operation_order]->fields as $fieldKey => $field) {
											if (!in_array($fieldKey, $TFieldForTooltip)) continue;

											$T[$fieldKey] = $langs->trans($field['label']) . ' : ' . $TOr[$TOrDet[$tt->fk_orDet]->fk_operation_order]->showOutputFieldQuick($fieldKey);
										}

										if (empty($inProgress)) {
											$T['datef'] = $langs->trans('DateEnd') . ' : ' . date('d/m/Y H:i:s', (int) $TOr[$TOrDet[$tt->fk_orDet]->fk_operation_order]->planned_date + (!empty($TOr[$TOrDet[$tt->fk_orDet]->fk_operation_order]->time_planned_f) ? (int) $TOr[$TOrDet[$tt->fk_orDet]->fk_operation_order]->time_planned_f : (int) $TOr[$TOrDet[$tt->fk_orDet]->fk_operation_order]->time_planned_t));
										}
										$title .= '<br/><br/>' . implode('<br/>', $T);

										if (is_object($hookmanager)) {
											$parameters = array(
												'operationOrder' => $TOr[$TOrDet[$tt->fk_orDet]->fk_operation_order]
											);

											$reshook = $hookmanager->executeHooks('operationorderORplanningMoreTooltip', $parameters);    // Note that $action and $object may have been modified by hook

											if ($reshook > 0) {
												$title = $hookmanager->resPrint;
											} elseif (empty($reshook)) {
												$title .= $hookmanager->resPrint;
											}
										}
									}

									if (!$inProgress) {
										$class = "in-time";
										if ($TOrDet[$tt->fk_orDet]->time_spent > $TOrDet[$tt->fk_orDet]->time_planned) {
											$class = "late";
										}
									} else {
										$class = 'inprogress';
									}
								}
							}

							$tempTT = new stdClass;
							$tempTT->start = date("H:i", $tt->task_datehour_d);
							$tempTT->end = date("H:i", $tt->task_datehour_f);
							$tempTT->text = $label;
							$tempTT->data = new stdClass;
							$tempTT->data->title = $title;
							$tempTT->data->class = $class;
							$tempTT->data->counterID = $tt->id;
							$tempTT->data->fk_user = $u->id;
							if (!empty($tt->fk_orDet)) {
								$tempTT->data->fk_orDet = $TOrDet[$tt->fk_orDet]->id;
								$tempTT->data->fk_or = $TOrDet[$tt->fk_orDet]->fk_operation_order;
							}

							$TSchedules[$u->id]->schedule[] = $tempTT;
						}
					}
				}
			}
		}
	}

	$conf->entity = $oldEntity;
	$conf->setValues($db);

	return $TSchedules;
}

/**
 * Return workshop efficiency by period
 *
 * @param int $entity entity
 * @param string $periode lastweek = last closed week, lastmonth = last closed month, month = current month week = current week, today = today, yeserday = yesterdy
 * @return float efficiency
 */
function get_efficacite($entity, $periode)
{
	global $conf, $db;
	dol_include_once('/core/lib/date.lib.php');
	dol_include_once('/core/lib/functions.lib.php');
	$date = dol_getdate(dol_now());
	switch ($periode) {
		case 'lastweek':
			$date_next = dol_get_prev_week_lltrucks($date['mday'], $date['mon'], $date['year']);
			$date_deb = dol_mktime(00, 00, 00, $date_next['month'], $date_next['day'], $date_next['year']);
			$date_fin = dol_mktime(23, 59, 59, $date_next['lmonth'], $date_next['lday'], $date_next['lyear']);
			break;

		case 'lastmonth':
			$date_next = dol_get_prev_month($date['mon'], $date['year']);
			$date_deb = dol_mktime(00, 00, 00, $date_next['month'], 1, $date_next['year']);
			$date_fin = dol_mktime(00, 00, 00, $date['mon'], 1, $date['year']);
			$date_fin = $date_fin - 1;
			break;

		case 'month':
			$date_next = dol_getdate(dol_get_last_day($date['year'], $date['mon']));
			$date_deb = dol_mktime(00, 00, 00, $date['mon'], 1, $date['year']);
			$date_fin = dol_mktime(23, 59, 59, $date_next['mon'], $date_next['mday'], $date_next['year']);
			break;

		case 'week':
			$date_next = dol_get_first_day_week($date['mday'], $date['mon'], $date['year']);
			$date_deb = dol_mktime(00, 00, 00, $date_next['first_month'], $date_next['first_day'], $date_next['first_year']);
			$date_fin = dol_time_plus_duree($date_deb, 7, 'd');
			$date_fin = $date_fin - 1;
			break;

		case 'today':
			$date_deb = dol_mktime(00, 00, 00, $date['mon'], $date['mday'], $date['year']);
			$date_fin = dol_mktime(23, 59, 59, $date['mon'], $date['mday'], $date['year']);
			break;

		case 'yesterday':
			$date_next = dol_get_prev_day($date['mday'], $date['mon'], $date['year']);
			$date_deb = dol_mktime(00, 00, 00, $date_next['month'], $date_next['day'], $date_next['year']);
			$date_fin = dol_mktime(23, 59, 59, $date_next['month'], $date_next['day'], $date_next['year']);
			break;
	}

	//pr�paration de la requ�te avec un filtre qui r�cup�re les donn�es s�l�ctionn�es dans le tableau OPERATIONORDER_EXCLUDE_IMPRO
	$filter = '';
	$filter_array = explode(',', getDolGlobalString("OPERATIONORDER_EXCLUDE_IMPRO"));
	if (!empty($filter_array)) {
		$filter = '\'' . implode('\',\'', $filter_array) . '\'';
	}
	$event = "Main d'oeuvre";
	$sql = 'SELECT';
	$sql .= ' SUM(IF(op.label = "' . $event . '",IF(op.task_duration IS NULL,TIME_TO_SEC(TIMEDIFF(NOW(),op.task_datehour_d)),op.task_duration)/3600,0)) as "' . $event . '",';
	$sql .= ' SUM(IF(op.task_duration IS NULL,TIME_TO_SEC(TIMEDIFF(NOW(),op.task_datehour_d)),op.task_duration))/3600 as "dispo"';
	$sql .= ' FROM ' . $db->prefix() . 'operationordertasktime as op';
	$sql .= " WHERE op.task_datehour_d BETWEEN '" . dol_print_date($date_deb, '%Y-%m-%d %H:%M:%S') . "' AND '" . dol_print_date($date_fin, '%Y-%m-%d %H:%M:%S') . "'";
	if ($entity > 1) {
		$sql .= ' AND op.entity =' . $entity;
	}
	if (!empty($filter)) {
		$sql .= ' AND op.label NOT IN (' . $filter . ')';
	}

	$resql = $db->query($sql);

	if ($resql) {
		$object = $db->fetch_object($resql);
		if ((int) $object->dispo !== 0) {
			return round(($object->$event / $object->dispo) * 100, 1, PHP_ROUND_HALF_UP);
		} else {
			return 0;
		}
	} else {
		return -1;
	}
}

/**
 * Return workshop efficiency by period
 * @param int $day day
 * @param int $month month
 * @param int $year year
 * @return array with prev week first day and last day.
 */
function dol_get_prev_week_lltrucks($day, $month, $year)
{
	$tmparray = dol_get_first_day_week($day, $month, $year);

	$time = dol_mktime(0, 0, 0, $tmparray['prev_month'], $tmparray['prev_day'], $tmparray['prev_year'], 1, 0);
	$tmparray = dol_getdate($time);
	$rep['day'] = $tmparray['mday'];
	$rep['month'] = $tmparray['mon'];
	$rep['year'] = $tmparray['year'];

	$time += 24 * 60 * 60 * 6;
	$tmparray = dol_getdate($time, true);
	$rep['lday'] = $tmparray['mday'];
	$rep['lmonth'] = $tmparray['mon'];
	$rep['lyear'] = $tmparray['year'];

	return $rep;
}

/**
 * Create Supplier Order from OR
 * @param OperationOrder $object Operation Order
 * @return int
 */
function orderpiece($object)
{

	global $conf, $db, $user;

	if (is_object($object) && $object->element = 'operationorder') {
		//construction du tableau de toutes les commandes de pieces liées a l'OR
		$commande = array();
		$res = $object->fetchObjectLinked();
		if (is_array($object->linkedObjects) && array_key_exists('order_supplier', $object->linkedObjects) && count($object->linkedObjects['order_supplier']) > 0) {
			$i = 0;
			foreach ($object->linkedObjects['order_supplier'] as $order) {
				$commande[] = array_values($object->linkedObjects['order_supplier'])[$i];
				$i++;
			}
		}

		//Construction du tableau des produits deja commandés:
		$alreadyordered = array();
		if (count($commande) > 0) {
			foreach ($commande as $supplierorder) {
				foreach ($supplierorder->lines as $suporderline) {
					$alreadyordered[$suporderline->fk_product] = $alreadyordered[$suporderline->fk_product] + $suporderline->qty;
				}
			}
		}

		//on construit le tableau des produits commmandable
		//TODO est-ce que transfert de stock à déja été fait
		$orderableproduct = array();
		foreach ($object->lines as $line) {
			if ($line->product->type == 0) {
				if (!empty($line->fk_warehouse)) {
					$line->product->load_stock();
					$stock_reel = $line->product->stock_reel;
				} else {
					$stock_reel = 0;
				}
				if ($stock_reel < $line->qty) {
					$orderableproduct[] = $line;
				}
			}
		}


		//construction du tableau des produits à commander
		$toorder = array();
		foreach ($orderableproduct as $produit) {
			$qtytoorder = $produit->qty - $alreadyordered[$produit->fk_product];
			if ($qtytoorder > 0) {
				$toorder[$produit->fk_product]['line'] = $produit;
				$toorder[$produit->fk_product]['qty'] = $qtytoorder;
				$toorder[$produit->fk_product]['product'] = $produit->product;
				$toorder[$produit->fk_product]['origin'] = $produit->element;
				$toorder[$produit->fk_product]['originid'] = $produit->id;
			}
		}

		if (count($toorder) > 0) {
			require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';

			//recherche du fk_soc a utiliser pour la commande
			$sql = "SELECT oo.fk_soc as fk_soc, oo.fk_entity AS fk_entity FROM " . $db->prefix() . "thirdparty_entity AS oo";
			$sql .= " WHERE oo.entity =" . $object->entity;
			$sql .= " AND oo.fk_entity <> " . $object->entity;

			$resql = $db->query($sql);
			$num = $db->num_rows($resql);

			if ($resql && $num > 0) {
				$obj = $db->fetch_object($resql);
				$fk_soc = $obj->fk_soc;
			} else {
				return -3;
			}

			$order = new CommandeFournisseur($db);
			$sql = "SELECT rowid FROM " . $db->prefix() . "commande_fournisseur";
			$sql .= " WHERE fk_soc = " . $fk_soc;
			$sql .= " AND ref_supplier = '" . $object->ref;
			$sql .= "' AND entity =" . $conf->entity;
			$sql .= " AND fk_statut = 0";

			$resql = $db->query($sql);
			$num = $db->num_rows($resql);
			$res = 0;
			if ($resql && $num > 0) {
				$obj = $db->fetch_object($resql);
				$res = $order->fetch($obj->rowid);
			}
			if ($res <= 0) {
				$order->socid = $fk_soc;
				$order->fk_soc = $fk_soc;
				$order->ref_supplier = $object->ref;
				$res = $order->create($user, 1);

				if ($res > 0) {
					$order->fetch($res);
					$order->add_object_linked($object->element, $object->id);
				} else {
					return -4;
				}
			}

			foreach ($toorder as $toorderline) {
				$product = $toorderline['product'];
				$res = $product->get_buyprice(0, 0, $product->id, 'none', $fk_soc);

				if ($product->fourn_qty <> 0) {
					$qty = ceil($toorderline['qty'] / $product->fourn_qty) * $product->fourn_qty;
				} else {
					$qty = $toorderline['qty'];
				}
				$res = $order->addline('',
					$product->fourn_pu,
					$qty,
					$product->vatrate_supplier,
					0.0,
					0.0,
					$product->id,
					$product->product_fourn_price_id,
					$product->ref_supplier,
					$product->remise_percent,
					$product->fourn_price_base_type,
					0.0,
					$product->type
				);
				if ($res > 0) {
					$toorderline['line']->add_object_linked($order->element, $order->id);
				}
			}
			return $order->id;
		} else {
			return -2;
		}
	} else {
		return -1;
	}
}


/**
 *	Show header of a report For Export
 *
 *	@param	string				$reportname     Name of report
 *	@param 	string				$period         Period of report
 *	@param 	string				$periodlink     Link to switch period
 *	@param 	string				$description    Description
 *	@param 	integer	            $builddate      Date generation
 *	@param 	string				$exportlink     Link for export or ''
 *	@param	array				$moreparam		Array with list of params to add into form
 *	@param	string				$calcmode		Calculation mode
 *  @param  string              $varlink        Add a variable into the address of the page
 *  @param  string              $varlink        Add a variable into the address of the page
 *  @param  bool                $export_available	allow export button
 *	@return	void
 */
function reportHeaderExportCompta($reportname, $period, $periodlink, $description, $builddate, $exportlink = '', $moreparam = array(), $calcmode = '', $varlink = '', $export_available = true)
{
	global $langs;

	print "\n\n<!-- start banner of report -->\n";

	if (!empty($varlink)) {
		$varlink = '?' . $varlink;
	}

	$head = array();

	$h = 0;
	$head[$h][0] = $_SERVER["PHP_SELF"] . $varlink;
	$head[$h][1] = $langs->trans("Report");
	$head[$h][2] = 'report';

	print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . $varlink . '">' . "\n";
	print '<input type="hidden" name="token" value="' . newToken() . '">' . "\n";

	print dol_get_fiche_head($head, 'report');

	foreach ($moreparam as $key => $value) {
		print '<input type="hidden" name="' . $key . '" value="' . $value . '">' . "\n";
	}

	print '<table class="border tableforfield centpercent">' . "\n";

	$variante = ($periodlink || $exportlink);

	// Ligne de titre
	print '<tr>';
	print '<td width="150">' . $langs->trans("ReportName") . '</td>';
	print '<td>';
	print $reportname;
	print '</td>';
	if ($variante) {
		print '<td></td>';
	}
	print '</tr>' . "\n";

	// Calculation mode
	if ($calcmode) {
		print '<tr>';
		print '<td width="150">' . $langs->trans("CalculationMode") . '</td>';
		print '<td>';
		print $calcmode;
		if ($variante) {
			print '<td></td>';
		}
		print '</td>';
		print '</tr>' . "\n";
	}

	// Ligne de la periode d'analyse du rapport
	print '<tr>';
	print '<td>' . $langs->trans("ReportPeriod") . '</td>';
	print '<td>';
	if ($period) {
		print $period;
	}
	if ($variante) {
		print '<td class="nowraponall">' . $periodlink . '</td>';
	}
	print '</td>';
	print '</tr>' . "\n";

	// Ligne de description
	print '<tr>';
	print '<td>' . $langs->trans("ReportDescription") . '</td>';
	print '<td>' . $description . '</td>';
	if ($variante) {
		print '<td></td>';
	}
	print '</tr>' . "\n";

	// Ligne d'export
	print '<tr>';
	print '<td>' . $langs->trans("GeneratedOn") . '</td>';
	print '<td>';
	print dol_print_date($builddate, 'dayhour');
	print '</td>';
	if ($variante) {
		print '<td>' . ($exportlink ? $langs->trans("Export") . ': ' . $exportlink : '') . '</td>';
	}
	print '</tr>' . "\n";

	print '</table>' . "\n";

	print dol_get_fiche_end();

	print '<div class="center"><input type="submit" class="button" name="refresh" value="' . $langs->trans("Refresh") . '">';
	if ($export_available) {
		print '<input type="submit" class="button" name="exportcsv" value="' . $langs->trans("Export") . '"></div>';
	}

	print '</form>';
	print '<br>';

	print "\n<!-- end banner of report -->\n\n";
}

/**
 * Prepare array of tabs for Or Setup screen
 * @return    array                    Array of tabs
 */
function OrSetupPrepareHead(): array
{
	global $langs, $conf;

	$langs->load("operationorder@operationorder");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/operationorder/param/or_setup_job.php", 1);
	$head[$h][1] = $langs->trans("ParamOrSetupJob");
	$head[$h][2] = 'job';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/param/or_setup_mo.php", 1);
	$head[$h][1] = $langs->trans("ParamOrSetupMo");
	$head[$h][2] = 'mo';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/param/or_setup_st.php", 1);
	$head[$h][1] = $langs->trans("ParamOrSetupSt");
	$head[$h][2] = 'st';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/param/or_setup_serv.php", 1);
	$head[$h][1] = $langs->trans("ParamOrSetupServ");
	$head[$h][2] = 'sv';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/param/or_setup_jobtype.php", 1);
	$head[$h][1] = $langs->trans("ParamOrSetupJobType");
	$head[$h][2] = 'jobtype';
	$h++;

	$head[$h][0] = dol_buildpath("/operationorder/param/or_setup_ortag.php", 1);
	$head[$h][1] = $langs->trans("ParamOrSetupOrTag");
	$head[$h][2] = 'ortag';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'operationordersetup@operationorder');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'operationordersetup@operationorder', 'remove');

	return $head;
}

function calcFinTotalAmountByType(Facture $object)
{

	$resArray=array(
		'total_ht_reimbursement'=>0,
		'total_ht_external' => 0,
		'total_ht_mo' => 0,
		'total_ht_part' => 0,
		'total_ht_service' => 0,
	);
	if (empty($object->id)) {
		return  $resArray;
	}

	if (empty($object->lines)) $object->fetch_lines();
	if (!empty($object->lines)) {
		foreach ($object->lines as $line) {
			if (!empty($line->fk_product)) {
				if (empty($line->product)) $line->fetch_product();
				if (empty($line->product->array_options)) $line->product->fetch_optionals();
				if ((int) $line->product->hasFatherOrChild(1) == 0) {
					if ($line->product->type == Product::TYPE_PRODUCT) {
						$resArray['total_ht_part'] += $line->total_ht;
					} elseif ($line->product->type == Product::TYPE_SERVICE) {
						if (!empty($line->product->array_options['options_oorder_available_for_supplier_order'])) {
							$resArray['total_ht_external'] += $line->total_ht;
						} elseif (!empty($line->product->array_options['options_or_scan'])) {
							$resArray['total_ht_mo'] += $line->total_ht;
						} elseif (!empty($line->product->array_options['options_oorder_ventilation_produit'])) {
							$resArray['total_ht_reimbursement'] += $line->total_ht;
						} elseif (empty($line->product->array_options['options_or_is_job'])) {
							$resArray['total_ht_service'] += $line->total_ht;
						}
					}
				}
			}
		}
	}
	return $resArray;
}
