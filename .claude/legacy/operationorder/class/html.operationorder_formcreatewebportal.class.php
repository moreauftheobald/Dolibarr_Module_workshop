<?php

/* Copyright (C) 2023-2024 	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2023-2024	Lionel Vessiller		<lvessiller@easya.solutions>
 * Copyright (C) 2023-2024	Patrice Andreani		<pandreani@easya.solutions>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
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


require_once DOL_DOCUMENT_ROOT . '/webportal/class/html.formcardwebportal.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societeaccount.class.php';
dol_include_once('/dolifleet/class/vehicule.class.php');
dol_include_once('/dolifleet/class/dictionaryContractType.class.php');
dol_include_once('/operationorder/class/operationorderstatus.class.php');
dol_include_once('/operationorder/lib/operationorder.lib.php');
dol_include_once('/operationorder/class/webportaloperationorder.class.php');

/**
 *    Class to manage generation of HTML components
 *    Only common components for WebPortal must be here.
 *
 */
class OperationOrderFormCreateWebPortal extends FormCardWebPortal
{

	private $context;

	/**
	 * Init
	 *
	 * @param	string	$elementEn				Element (english) : "member" (for adherent), "partnership"
	 * @param	int		$id						[=0] ID element
	 * @param	int		$permissiontoread		[=0] Permission to read (0 : access forbidden by default)
	 * @param	int		$permissiontoadd		[=0] Permission to add (0 : access forbidden by default), used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
	 * @param	int		$permissiontodelete		[=0] Permission to delete (0 : access forbidden by default)
	 * @param	int		$permissionnote			[=0] Permission to note (0 : access forbidden by default)
	 * @param	int		$permissiondellink		[=0] Permission to delete links (0 : access forbidden by default)
	 * @return	void
	 */
	public function init(
		$elementEn, $id = 0, $permissiontoread = 0, $permissiontoadd = 0, $permissiontodelete = 0, $permissionnote = 0, $permissiondellink =
		0
	) {
		global $hookmanager, $langs;
		$_SESSION['dol_tz_string'] = 'Europe/Paris';

		$elementEnUpper = strtoupper($elementEn);
		$objectclass = 'WebPortal' . ucfirst($elementEn);

		// Load translation files required by the page
		$langs->loadLangs(array('website', 'other', 'companies', 'operationorder@operationorder'));

		// load module libraries
		dol_include_once('/operationorder/class/webportal' . $elementEn . '.class.php');

		// Get parameters
		//$id = $id > 0 ? $id : GETPOST('id', 'int');
		$ref = GETPOST('ref', 'alpha');
		$action = GETPOST('action', 'aZ09');
		$confirm = GETPOST('confirm', 'alpha');
		$cancel = GETPOST('cancel', 'aZ09');
		$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'webportal' . $elementEn . 'form'; // To manage different context of search
		$backtopage = GETPOST('backtopage', 'alpha');  // if not set, a default page will be used
		$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha'); // if not set, $backtopage will be used
		$backtopagejsfields = GETPOST('backtopagejsfields', 'alpha');

		// Initialize a technical objects
		$object = new WebPortalOperationorder($this->db);
		//$extrafields = new ExtraFields($db);
		$hookmanager->initHooks(array('webportal' . $elementEn . 'form', 'globalcard')); // Note that conf->hooks_modules contains array
		// Fetch optionals attributes and labels
		//$extrafields->fetch_name_optionals_label($object->table_element);
		//$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

		$action = 'create';

		$retFetch = $object->fetchWebOperationOrder($id);

		// Security check (enable the most restrictive one)
		if (!isModEnabled('webportal')) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}
		if (!$permissiontoread) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		// set form card
		$this->action = $action;
		$this->backtopage = $backtopage;
		$this->backtopageforcancel = $backtopageforcancel;
		$this->backtopagejsfields = $backtopagejsfields;
		$this->cancel = $cancel;
		$this->elementEn = $elementEn;
		$this->object = $object;
		$this->permissiontoread = $permissiontoread;
		$this->permissiontoadd = $permissiontoadd;
		$this->permissiontodelete = $permissiontodelete;
		$this->permissionnote = $permissionnote;
		$this->permissiondellink = $permissiondellink;
		$this->titleKey = $objectclass . 'FormTitle';
	}

	/**
	 * Card for an element in the page context
	 *
	 * @param	Context		$context	Context object
	 * @return	string		Html output
	 */
	public function elementCard($context)
	{
		global $hookmanager, $langs;

		$html = '<!-- elementCard -->';

		$socid = (int) $context->logged_thirdparty->id;

		// initialize
		$action = $this->action;
		$backtopage = $this->backtopage;
		$backtopageforcancel = $this->backtopageforcancel;
		//$backtopagejsfields = $this->backtopagejsfields;
		//$elementEn = $this->elementEn;
		$id = $this->id;
		$object = $this->object;
		//$permissiontoread = $this->permissiontoread;
		$permissiontoadd = $this->permissiontoadd;
		$ref = $this->ref;
		$titleKey = $this->titleKey;
		$title = $langs->trans($titleKey);
		$this->context = $context;
		// Part to show record
		$html .= '<article>';

		$formconfirm = '';

		// Call Hook formConfirm
		$parameters = array('formConfirm' => $formconfirm);
		$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if (empty($reshook)) {
			$formconfirm .= $hookmanager->resPrint;
		} elseif ($reshook > 0) {
			$formconfirm = $hookmanager->resPrint;
		}

		// Print form confirm
		$html .= $formconfirm;

		// Object card
		// ------------------------------------------------------------
		$html .= $this->header($context);

		$url_file = $context->getControllerUrl($context->controller, '', false);
		$html .= '<form method="POST" action="' . $url_file . '">';
		$html .= $context->getFormToken();
		$html .= '<input type="hidden" name="action" value="create">';
		$html .= '<input type="hidden" name="id" value="' . $object->id . '">';
		if ($backtopage) {
			$html .= '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
		}
		if ($backtopageforcancel) {
			$html .= '<input type="hidden" name="backtopageforcancel" value="' . $backtopageforcancel . '">';
		}
		// Common attributes

		$html .= $this->bodyCreate();
		// Save and Cancel buttons
		$html .= '
			<br>
			<div class="grid">
				<div class="center">
					<input type="submit" name="save" role="button" value="' . dol_escape_htmltag($langs->trans('Save')) . '" />
					<input type="submit" name="cancel" role="button" value="' . dol_escape_htmltag($langs->trans('Cancel')) . '" />
				</div>
			</div>';

		// Other attributes. Fields from hook formObjectOptions and Extrafields.
		//include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';
		//$html .= $this->footer();
		$html .= '</article>';
		return $html;
	}

	/**
	 * Html for header
	 *
	 * @param	Context	$context	Context object
	 * @return	string
	 */
	protected function header($context)
	{
		global $langs;

		$html = '';

		// initialize
		$object = $this->object;

		$html .= '
			<!-- html.formcardwebportal.class.php -->
			<header>
				<div class="header-card-left-block inline-block" style="width: 75%;">
					<div class="header-card-main-information inline-block valignmiddle">
						<div><strong>' . $langs->trans("ThirdParty") . ' : ' . dol_escape_htmltag($context->logged_thirdparty->ref) . '</strong></div>
					</div>
				</div>
			</header>';

		return $html;
	}

	/**
	 *  Html for body (create mode)
	 *
	 * @return	string
	 */
	protected function bodyCreate()
	{
		global $langs;

		// initialize
		$object = $this->object;

		$productArray = $this->fetchProductList();
		$vehiculesArray = $this->fetchVehiculesList();
		$supplierArray = $this->fetchSupplierList();
		$driverArray = $this->fetchDriverList();
		// Enhance with select2
		include_once DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php';

		[$valueDateOperationOrder, $valueTimeOperationOrder] = $this->getDateTimeValuesFromPost('date_operation_order');
		[$valueDatePlanned, $valueTimePlanned] = $this->getDateTimeValuesFromPost('planned_date');

		$km_on_creation = GETPOST('km_on_creation', 'int');
		$fk_conducteur = GETPOST('fk_conducteur');
		$fk_vehicule = GETPOST('fk_vehicule');
		$fk_supplier = GETPOST('fk_supplier');
		$operations = GETPOST('operations', 'array');
		$subprice = GETPOST('subprice');
		$desc = GETPOST('desc');

		ob_start();
		include dol_buildpath('/custom/operationorder/core/tpl/operationorder_depan_webportalform.tpl.php', 0);
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Do actions
	 *
	 * @return	void
	 */
	public function doActions()
	{
		global $langs;

		// initialize
		$action = GETPOST('action');
		$backtopage = $this->backtopage;
		$backtopageforcancel = $this->backtopageforcancel;
		$cancel = $this->cancel;
		$elementEn = $this->elementEn;
		$id = $this->id;
		$object = new OperationOrder($this->db);
		//$permissiontoread = $this->permissiontoread;
		$permissiontoadd = $this->permissiontoadd;

		$error = 0;

		$context = Context::getInstance();

		$backurlforlist = $context->getControllerUrl('default');
		$noback = 1;

		if (empty($backtopage) || ($cancel && empty($id))) {
			if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
				$backtopage = $context->getControllerUrl($elementEn . 'form');
			}
		}

		// Action to cancel record
		if ($cancel) {
			if (!empty($backtopageforcancel)) {
				header("Location: " . $backtopageforcancel);
				exit;
			} elseif (!empty($backtopage)) {
				header("Location: " . $backtopage);
				exit;
			}
			$action = '';
		}

		$this->object = $object;
		$this->action = $action;
		// Action to update record
		if ($action !== 'create') {
			return;
		}

		$date_operation_order = $this->getDatePost("date_operation_order");
		if (empty($date_operation_order)) {
			$context->setEventMessage($langs->trans('MissingDateOperationOrder'), 'errors');
			return;
		}

		$planned_date = $this->getDatePost("planned_date");

		$fk_conducteur = GETPOST('fk_conducteur', 'int');
		$fk_vehicule = GETPOST('fk_vehicule', 'int');
		if (empty($fk_vehicule) || $fk_vehicule <= 0) {
			$context->setEventMessage($langs->trans('MissingVehicule'), 'errors');
			return;
		}

		$fk_supplier = GETPOST('fk_supplier', 'int');
		if (empty($fk_supplier) || $fk_supplier <= 0) {
			$context->setEventMessage($langs->trans('MissingSupplier'), 'errors');
			return;
		}

		$operations = GETPOST('operations', 'array');
		if (empty($operations)) {
			$context->setEventMessage($langs->trans('MissingOperations'), 'errors');
			return;
		}

		$km_on_creation = GETPOST('km_on_creation', 'int');
		if (empty($km_on_creation) || $km_on_creation <= 0) {
			$context->setEventMessage($langs->trans('MissingKmOnCreation'), 'errors');
			return;
		}

		$desc = GETPOST('desc');
		if (empty($desc)) {
			$context->setEventMessage($langs->trans('MissingDesc'), 'errors');
			return;
		}

		global $conf;

		$vehicule = new Vehicule($this->db);
		$retFetchVehicule = $vehicule->fetch($fk_vehicule);
		if ($retFetchVehicule < 0) {
			$context->setEventMessages($vehicule->error, $vehicule->errors, 'errors');
			return -1;
		}

		$entity = $this->checkAtelier($vehicule->atelier);
		$conf->entity = $entity;
		$mysoc = new Societe($this->db);
		$mysoc->setMysoc($conf);

		$object->fk_soc = $context->logged_thirdparty->id;
		$object->date_operation_order = $date_operation_order;
		$object->planned_date = $planned_date;
		$object->fk_vehicule = $fk_vehicule;
		$object->fk_conducteur = $fk_conducteur;
		$object->km_on_creation = $km_on_creation;
		$object->entity = $entity;

		$this->db->begin();
		$res = $object->create($context->logged_user);

		if ($res < 0) {
			$context->setEventMessages($object->error, $object->errors, 'errors');
			$this->db->rollback();
			return;
		}

		if (empty(getDolGlobalString('OPERATION_ORDER_DEPAN_PRODUCT'))) {
			$context->setEventMessage($langs->trans('MissingConf'), 'errors');
			$this->db->rollback();
			return;
		}

		foreach ($operations as $operation) {
			try {
				addLineAndChildToOR(
					$object, $operation, 1, 0, 1
				);
			} catch (Exception $ex) {
				$context->setEventMessage($ex->getMessage(), 'errors');
				$this->db->rollback();
				return;
			}
		}

		if (GETPOSTISSET('subprice') && !empty(GETPOST('subprice', 'int'))) {
			$subprice = GETPOST('subprice', 'int');
		} else {
			$depan_product = new Product($this->db);
			$retFetch = $depan_product->fetch(getDolGlobalInt('OPERATION_ORDER_DEPAN_PRODUCT'));
			if ($retFetch < 0) {
				$context->setEventMessages($depan_product->error, $depan_product->errors, 'errors');
				$this->db->rollback();
				return;
			}
			$subprice = $depan_product->price;
		}

		try {
			addLineAndChildToOR(
				$object, getDolGlobalInt('OPERATION_ORDER_DEPAN_PRODUCT'), 1, $subprice, 1, $desc
			);
		} catch (Exception $ex) {
			$context->setEventMessage($ex->getMessage(), 'errors');
			$this->db->rollback();
			return;
		}

		$stat = array();

		$sql = "SELECT rowid FROM " . $this->db->prefix() . "operationorder_status WHERE CODE='PLANNED' AND entity = " . $object->entity;
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$Stat['PLANNED'] = $obj->rowid;
		}

		$resultSetStatus = $object->setStatus($context->logged_user, $Stat['PLANNED'], 0,'OPERATIONORDER_STATUS_CHANGE', true);
		if ($resultSetStatus < 0) {
			$context->setEventMessages($object->error, $object->errors, 'errors');
			$this->db->rollback();
			return;
		}

		$sql = "SELECT rowid FROM " . $this->db->prefix() . "operationorder_status WHERE CODE='EXT' AND entity = " . $object->entity;
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$Stat['EXT'] = $obj->rowid;
		}

		if (!empty($object->planned_date) && $object->planned_date <= dol_now()) {
			$resultSetStatus = $object->setStatus($context->logged_user, $Stat['EXT']);
			if ($resultSetStatus < 0) {
				$context->setEventMessages($object->error, $object->errors, 'errors');
				$this->db->rollback();
				return;
			}
		}

		if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
			$outputlangs = $langs;
			$object->generateDocument($object->modelpdf, $outputlangs, 0, 0, 0);
		}

		$retCreate = $this->createOrderSupplierLinked($context, $object, $vehicule, $fk_supplier, $entity);
		if ($retCreate < 0) {
			$this->db->rollback();
			return;
		}

		$this->db->commit();
		$urlList = $context->getControllerUrl('operationordercard', ['op_id' => $object->id]);
		header("Location: " . $urlList);
		exit;
	}

	protected function createOrderSupplierLinked($context, $object, $vehicule, $fk_supplier, $entity)
	{
		global $langs, $conf, $mysoc;

		$websiteaccount = new SocieteAccount($this->db);
		$result = $websiteaccount->fetch($_SESSION["webportal_logged_thirdparty_account_id"]);
		if ($result < 0) {
			$context->setEventMessage($langs->trans('MissingLoggedSocieteAccount'), 'errors');
			return -1;
		}

		$dictCT = New dictionaryContractType($object->db);
		$dictCT->fetch($vehicule->fk_contract_type);

		$supplierOrder = new CommandeFournisseur($this->db);
		$supplierOrder->ref_supplier = $object->ref;
		$supplierOrder->socid = $fk_supplier;
		$supplierOrder->entity = $entity;

		$supplierOrder->model_pdf = 'cornas';

		foreach ($object->lines as $line) {
			$supplierOrderLine = new CommandeFournisseurLigne($object->db);
			$supplierOrderLine->product_type = 1;
			$supplierOrderLine->fk_product = $line->fk_product;
			$supplierOrderLine->pu_ht = $line->price;
			$supplierOrderLine->subprice = $line->price;
			$supplierOrderLine->qty = 1;
			$supplierOrderLine->tva_tx = $line->tva_tx;
			$supplierOrderLine->desc = $line->description;
			$supplierOrder->lines[] = $supplierOrderLine;
		}

		$sql = 'SELECT value
			FROM ' . $this->db->prefix() . 'const
			WHERE name="COMMANDE_FOURNISSEUR_ORCHIDEE_MASK" AND entity=' . intval($object->entity);

		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			$conf->global->COMMANDE_FOURNISSEUR_ORCHIDEE_MASK = $obj->value;
		}

		// create new supplier order
		$resSupplierOrder = $supplierOrder->create($context->logged_user);
		if ($resSupplierOrder < 0) {
			$context->setEventMessages(
				$langs->trans('SupplierOrderSaveError') . ' : ' . $supplierOrder->error, $supplierOrder->errors,
				'errors');
			return -1;
		}

		$supplierOrder->add_object_linked('operationorder', $object->id); // and link to object to be displayed un document
		foreach ($object->lines as $line) {
			$supplierOrder->add_object_linked('operationorderdet', $line->id); // and link to line origin for user interface
		}

		if (!empty(getDolGlobalString("OPODER_SUPPLIER_ORDER_AUTO_VALIDATE"))) {
			$context->logged_user->rights->fournisseur = new stdClass();
			$context->logged_user->rights->fournisseur->commande = new stdClass();
			$context->logged_user->rights->fournisseur->commande->creer = 1;
			$context->logged_user->rights->fournisseur->commande->approuver = 1;
			$context->logged_user->rights->fournisseur->supplier_order_advance = new stdClass();
			$context->logged_user->rights->fournisseur->supplier_order_advance->validate = 1;
			$retValid = $supplierOrder->valid($context->logged_user);
			if ($retValid < 0) {
				$context->setEventMessages($supplierOrder->error, $supplierOrder->errors, 'errors');
				return -1;
			}

			$retApprove = $supplierOrder->approve($context->logged_user, 0, 0);
			if ($retApprove < 0) {
				$context->setEventMessages($supplierOrder->error, $supplierOrder->errors, 'errors');
				return -1;
			}
		}

		$supplierOrder->array_options['options_supplier_order_type'] = '1';
		if ($supplierOrder->insertExtraFields() < 0) {
			$context->setEventMessage($supplierOrder->error, 'errors');
			return -1;
		}

		if (is_object($vehicule)) {
			$noteAppend = '';
			if (!empty($supplierOrder->note_public)) {
				$noteAppend .= '<br/>';
			}

			$noteAppend .= $vehicule->getSupplierOrderPublicNote($object);

			if (!empty($context->logged_thirdparty->phone) || !empty($context->logged_thirdparty->phone_mobile)) {
				if (!empty($context->logged_thirdparty->phone) && !empty($context->logged_user->user_mobile)) {
					$noteAppend .= ' ( ' . $context->logged_thirdparty->phone . ' / ' . $context->logged_thirdparty->phone_mobile . ' )';
				} elseif (!empty($context->logged_thirdparty->phone) && empty($context->logged_user->user_mobile)) {
					$noteAppend .= ' ( ' . $context->logged_thirdparty->phone . ' )';
				} elseif (empty($context->logged_thirdparty->phone) && !empty($context->logged_thirdparty->phone_mobile)) {
					$noteAppend .= ' ( ' . $context->logged_thirdparty->phone_mobile . ' )';
				}
			}
			$noteAppend .= ' par : ' . $websiteaccount->login;

			$supplierOrder->update_note($supplierOrder->note_public . $noteAppend, '_public');
		}

		if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
			$ret = $supplierOrder->fetch($supplierOrder->id); // Reload to get new records
		}

		$supplierOrder->model_pdf = 'cornas';

		$sql = 'UPDATE ' . $this->db->prefix() . $supplierOrder->table_element . '
			SET model_pdf="' . $supplierOrder->model_pdf . '"
			WHERE rowid=' . $supplierOrder->id;

		$resql = $object->db->query($sql);
		if (!$resql) {
			$context->setEventMessage($this->db->lasterror(), 'errors');
		}
		$this->setMysoc($conf);
		$build_path_multientity='';
		if ($object->entity!==1) {
			$build_path_multientity='/' . $object->entity;
		}
		$conf->fournisseur->commande->dir_output = DOL_DATA_ROOT . $build_path_multientity . '/fournisseur/commande';
		$supplierOrder->generateDocument($supplierOrder->model_pdf, $langs, 0, 0, 0);

		return 1;
	}

	protected function fetchVehiculesList()
	{
		$arrayVehicules = [];
		$sql = '
			SELECT rowid, CONCAT(vin, " - ", immatriculation) as label
			FROM ' . $this->db->prefix() . 'dolifleet_vehicule
			WHERE fk_soc=' . intval($this->context->logged_thirdparty->id) . '
			AND status = 1';

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$arrayVehicules[$obj->rowid] = $obj->label;
			}
		}

		return $arrayVehicules;
	}

	protected function fetchSupplierList()
	{
		$arraySupplier = [];
		$sql = '
			SELECT rowid, nom, name_alias, zip, town
			FROM ' . $this->db->prefix() . 'societe
			WHERE fournisseur=1
			AND status = 1';

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$arraySupplier[$obj->rowid] = $obj->nom;
				if (!empty($obj->name_alias)) {
					$arraySupplier[$obj->rowid] .= ' - ' .$obj->name_alias;
				}
				$arraySupplier[$obj->rowid] .= ' ('.$obj->zip.' '.$obj->town.')';
			}
		}

		return $arraySupplier;
	}

	protected function getDateTimeValuesFromPost($key)
	{
		$valueDate = '';
		if (GETPOSTISSET($key) && GETPOSTISSET($key . '_time') && !empty(GETPOST($key . '_time'))) {
			$valueDate = GETPOST($key . 'year');
			$valueDate .= '-' . GETPOST($key . 'month');
			$valueDate .= '-' . GETPOST($key . 'day');
		}

		$valueTime = '';
		if (GETPOSTISSET($key . '_time') && !empty(GETPOST($key . '_time'))) {
			$valueTime = GETPOST($key . '_time');
		}

		return [$valueDate, $valueTime];
	}

	protected function fetchDriverList()
	{
		$arrayDriver = [];
		$sql = '
			SELECT s.rowid, CONCAT(s.firstname, " - ", s.lastname) as label
			FROM ' . $this->db->prefix() . 'socpeople as s
			LEFT JOIN ' . $this->db->prefix() . 'socpeople_extrafields as se ON s.rowid = se.fk_object
			WHERE s.fk_soc=' . intval($this->context->logged_thirdparty->id) . '
			AND s.statut = 1 AND se.driver=1';

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$arrayDriver[$obj->rowid] = $obj->label;
			}
		}

		return $arrayDriver;
	}

	protected function fetchProductList()
	{
		$productArray = array();
		$sql = "SELECT DISTINCT  p.rowid as rowid, p.label as label ";
		$sql .= "FROM " . $this->db->prefix() . "product as p ";
		$sql .= "INNER JOIN " . $this->db->prefix() . "product_extrafields as ef ON ef.fk_object = p.rowid ";
		$sql .= "WHERE p.fk_product_type = 1 AND ef.oorder_available_for_supplier_order = 1 AND p.tobuy =1";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$productArray[$obj->rowid] = $obj->label;
			}
		}

		return $productArray;
	}

	protected function checkAtelier($atelier)
	{
		if (empty($atelier)) {
			return 1;
		}

		$sql = 'SELECT rowid
			FROM ' . $this->db->prefix() . 'entity
			WHERE rowid =' . intval($atelier) . ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			return 1;
		}
		$num = $this->db->num_rows($resql);
		if (empty($num)) {
			return 1;
		}

		return intval($atelier);
	}

	protected function getDatePost($key)
	{
		$postDate = GETPOST($key, 'alphanohtml');
		// extract date YYYY-MM-DD for year, month and day
		$dateArr = explode('/', $postDate);
		$dateYear = 0;
		$dateMonth = 0;
		$dateDay = 0;
		if (count($dateArr) == 3) {
			$dateDay = (int) $dateArr[0];
			$dateMonth = (int) $dateArr[1];
			$dateYear = (int) $dateArr[2];
		}
		// extract time HH:ii:ss for hours, minutes and seconds
		$postTime = GETPOST($key . '_time', 'alphanohtml');
		$timeArr = explode(':', $postTime);
		$timeHours = 12;
		$timeMinutes = 0;
		$timeSeconds = 0;
		if (!empty($timeArr)) {
			if (isset($timeArr[0])) {
				$timeHours = (int) $timeArr[0];
			}
			if (isset($timeArr[1])) {
				$timeMinutes = (int) $timeArr[1];
			}
			if (isset($timeArr[2])) {
				$timeSeconds = (int) $timeArr[2];
			}
		}

		return dol_mktime($timeHours, $timeMinutes, $timeSeconds, $dateMonth, $dateDay, $dateYear);
	}


	/**
	 * 	Set properties with value into $conf
	 *
	 * 	@param	Conf	$conf		Conf object (possibility to use another entity)
	 * 	@return	void
	 */
	public function setMysoc(Conf $conf)
	{
		global $langs, $mysoc;
		if ($conf->entity == 1) {
			return;
		}
		$sql = 'SELECT value, name
		FROM ' . $this->db->prefix() . 'const
		WHERE entity=' . intval($conf->entity) . ' AND name LIKE "%MAIN_INFO%"';

		$resql = $this->db->query($sql);
		if (!$resql || $this->db->num_rows($resql) == 0) {
			return;
		}

		$array_val = [];
		while ($obj = $this->db->fetch_object($resql)) {
			$array_val[$obj->name] = $obj->value;
		}

		$mysoc->id = 0;
		$mysoc->entity = $conf->entity;
		$mysoc->name = $array_val['MAIN_INFO_SOCIETE_NOM'];
		$mysoc->nom = $mysoc->name; // deprecated
		$mysoc->address = $array_val['MAIN_INFO_SOCIETE_ADDRESS'];
		$mysoc->zip = $array_val['MAIN_INFO_SOCIETE_ZIP'];
		$mysoc->town = $array_val['MAIN_INFO_SOCIETE_TOWN'];
		$mysoc->region_code = $array_val['MAIN_INFO_SOCIETE_REGION'];

		$mysoc->socialobject = $array_val['MAIN_INFO_SOCIETE_OBJECT'];

		$mysoc->note_private = $array_val['MAIN_INFO_SOCIETE_NOTE'];

		// We define country_id, country_code and country
		$country_id = 0;
		$country_code = $country_label = '';
		if ($array_val['MAIN_INFO_SOCIETE_COUNTRY']) {
			$tmp = explode(':', $array_val['MAIN_INFO_SOCIETE_COUNTRY']);
			$country_id =  (is_numeric($tmp[0])) ? (int) $tmp[0] : 0;
			if (!empty($tmp[1])) {   // If $conf->global->MAIN_INFO_SOCIETE_COUNTRY is "id:code:label"
				$country_code = $tmp[1];
				$country_label = $tmp[2];
			} else {
				// For backward compatibility
				dol_syslog("Your country setup use an old syntax. Reedit it using setup area.", LOG_WARNING);
				include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
				$country_code = getCountry($country_id, '2', $this->db); // This need a SQL request, but it's the old feature that should not be used anymore
				$country_label = getCountry($country_id, '', $this->db); // This need a SQL request, but it's the old feature that should not be used anymore
			}
		}
		$mysoc->country_id = $country_id;
		$mysoc->country_code = $country_code;
		$mysoc->country = $country_label;
		if (is_object($langs)) {
			$mysoc->country = ($langs->trans('Country'.$country_code) != 'Country'.$country_code) ? $langs->trans('Country'.$country_code) : $country_label;
		}

		//TODO This could be replicated for region but function `getRegion` didn't exist, so I didn't added it.
		// We define state_id, state_code and state
		$state_id = 0;
		$state_code = '';
		$state_label = '';
		if ($array_val['MAIN_INFO_SOCIETE_STATE']) {
			$tmp = explode(':',$array_val['MAIN_INFO_SOCIETE_STATE']);
			$state_id = (int) $tmp[0];
			if (!empty($tmp[1])) {   // If $conf->global->MAIN_INFO_SOCIETE_STATE is "id:code:label"
				$state_code = $tmp[1];
				$state_label = $tmp[2];
			} else { // For backward compatibility
				dol_syslog("Your setup of State has an old syntax (entity=".$conf->entity."). Go in Home - Setup - Organization then Save should remove this error.", LOG_ERR);
				include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
				$state_code = getState($state_id, '2', $this->db); // This need a SQL request, but it's the old feature that should not be used anymore
				$state_label = getState($state_id, '0', $this->db); // This need a SQL request, but it's the old feature that should not be used anymore
			}
		}
		$mysoc->state_id = $state_id;
		$mysoc->state_code = $state_code;
		$mysoc->state = $state_label;
		if (is_object($langs)) {
			$mysoc->state = ($langs->trans('State'.$state_code) != 'State'.$state_code) ? $langs->trans('State'.$state_code) : $state_label;
		}

		$mysoc->phone = $array_val['MAIN_INFO_SOCIETE_TEL'];
		$mysoc->phone_mobile = $array_val['MAIN_INFO_SOCIETE_MOBILE'];
		$mysoc->fax = $array_val['MAIN_INFO_SOCIETE_FAX'];
		$mysoc->url = $array_val['MAIN_INFO_SOCIETE_WEB'];

		// Social networks
		$facebook_url = $array_val['MAIN_INFO_SOCIETE_FACEBOOK_URL'];
		$twitter_url = $array_val['MAIN_INFO_SOCIETE_TWITTER_URL'];
		$linkedin_url = $array_val['MAIN_INFO_SOCIETE_LINKEDIN_URL'];
		$instagram_url = $array_val['MAIN_INFO_SOCIETE_INSTAGRAM_URL'];
		$youtube_url = $array_val['MAIN_INFO_SOCIETE_YOUTUBE_URL'];
		$github_url = $array_val['MAIN_INFO_SOCIETE_GITHUB_URL'];
		$mysoc->socialnetworks = array();
		if (!empty($facebook_url)) {
			$mysoc->socialnetworks['facebook'] = $facebook_url;
		}
		if (!empty($twitter_url)) {
			$mysoc->socialnetworks['twitter'] = $twitter_url;
		}
		if (!empty($linkedin_url)) {
			$mysoc->socialnetworks['linkedin'] = $linkedin_url;
		}
		if (!empty($instagram_url)) {
			$mysoc->socialnetworks['instagram'] = $instagram_url;
		}
		if (!empty($youtube_url)) {
			$mysoc->socialnetworks['youtube'] = $youtube_url;
		}
		if (!empty($github_url)) {
			$mysoc->socialnetworks['github'] = $github_url;
		}

		// Id prof generiques
		$mysoc->idprof1 = $array_val['MAIN_INFO_SIREN'];
		$mysoc->idprof2 = $array_val['MAIN_INFO_SIRET'];
		$mysoc->idprof3 = $array_val['MAIN_INFO_APE'];
		$mysoc->idprof4 = $array_val['MAIN_INFO_RCS'];
		$mysoc->idprof5 = $array_val['MAIN_INFO_PROFID5'];
		$mysoc->idprof6 = $array_val['MAIN_INFO_PROFID6'];
		$mysoc->idprof7 = $array_val['MAIN_INFO_PROFID7'];
		$mysoc->idprof8 = $array_val['MAIN_INFO_PROFID8'];
		$mysoc->idprof9 = $array_val['MAIN_INFO_PROFID9'];
		$mysoc->idprof10 = $array_val['MAIN_INFO_PROFID10'];
		$mysoc->tva_intra = $array_val['MAIN_INFO_TVAINTRA']; // VAT number, not necessarily INTRA.
		$mysoc->managers = $array_val['MAIN_INFO_SOCIETE_MANAGERS'];
		$mysoc->capital = is_numeric($array_val['MAIN_INFO_CAPITAL']) ? (float) price2num($array_val['MAIN_INFO_CAPITAL']) : 0;
		$mysoc->forme_juridique_code = $array_val['MAIN_INFO_SOCIETE_FORME_JURIDIQUE'];
		$mysoc->email = $array_val['MAIN_INFO_SOCIETE_MAIL'];
		$mysoc->logo = $array_val['MAIN_INFO_SOCIETE_LOGO'];
		$mysoc->logo_small = $array_val['MAIN_INFO_SOCIETE_LOGO_SMALL'];
		$mysoc->logo_mini = $array_val['MAIN_INFO_SOCIETE_LOGO_MINI'];
		$mysoc->logo_squarred = $array_val['MAIN_INFO_SOCIETE_LOGO_SQUARRED'];
		$mysoc->logo_squarred_small = $array_val['MAIN_INFO_SOCIETE_LOGO_SQUARRED_SMALL'];
		$mysoc->logo_squarred_mini = $array_val['MAIN_INFO_SOCIETE_LOGO_SQUARRED_MINI'];

		$mysoc->termsofsale = $array_val['MAIN_INFO_SOCIETE_TERMSOFSALE'];
	}

}
