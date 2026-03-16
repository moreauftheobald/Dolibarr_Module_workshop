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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
dol_include_once('/dolifleet/class/vehicule.class.php');
dol_include_once('/operationorder/class/operationorderstatus.class.php');
dol_include_once('/operationorder/class/webportaloperationorder.class.php');

/**
 *    Class to manage generation of HTML components
 *    Only common components for WebPortal must be here.
 *
 */
class OperationOrderFormCardWebPortal extends FormCardWebPortal
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

		if ($id <= 0) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		// load module libraries
		dol_include_once('/operationorder/class/webportal' . $elementEn . '.class.php');

		// Get parameters
		//$id = $id > 0 ? $id : GETPOST('id', 'int');
		$ref = GETPOST('ref', 'alpha');
		$action = GETPOST('action', 'aZ09');
		$confirm = GETPOST('confirm', 'alpha');
		$cancel = GETPOST('cancel', 'aZ09');
		$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'webportal' . $elementEn . 'card'; // To manage different context of search
		$backtopage = GETPOST('backtopage', 'alpha');  // if not set, a default page will be used
		$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha'); // if not set, $backtopage will be used
		$backtopagejsfields = GETPOST('backtopagejsfields', 'alpha');

		$formfile = new FormFile($this->db);
		$this->formfile = $formfile;
		// Initialize a technical objects
		$object = new WebPortalOperationorder($this->db);
		//$extrafields = new ExtraFields($db);
		$hookmanager->initHooks(array('webportal' . $elementEn . 'card', 'globalcard')); // Note that conf->hooks_modules contains array
		// Fetch optionals attributes and labels
		//$extrafields->fetch_name_optionals_label($object->table_element);
		//$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

		if (empty($id)) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		if (empty($action)) {
			$action = 'view';
		}

		$retFetch = $object->fetchWebOperationOrder($id);

		if ($retFetch < 0) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		if (empty($retFetch)) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}


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
		$this->id = (int) $id;
		$this->object = $object;
		$this->permissiontoread = $permissiontoread;
		$this->permissiontoadd = $permissiontoadd;
		$this->permissiontodelete = $permissiontodelete;
		$this->permissionnote = $permissionnote;
		$this->permissiondellink = $permissiondellink;
		$this->titleKey = $objectclass . 'CardTitle';
		$this->ref = $ref;
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
		if ($socid != $this->object->fk_soc) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}
		// initialize
		$object = $this->object;
		//$permissiontoread = $this->permissiontoread;

		// Part to show record
		$html .= '<article>';

		$formconfirm = '';

		// Call Hook formConfirm
		$parameters = array('formConfirm' => $formconfirm);
		$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $this->action); // Note that $action and $object may have been modified by hook
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

		// Common attributes
		$keyforbreak = '';
		$this->context = $context;
		$html .= $this->bodyView($keyforbreak);

		// Other attributes. Fields from hook formObjectOptions and Extrafields.
		//include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';
		//$html .= $this->footer();
		$html .= '</article>';
		return $html;
	}

	/**
	 * Html for body (view mode)
	 * @param	string	$keyforbreak	[=''] Key for break left block
	 * @return	string	Html for body
	 */
	protected function bodyView($keyforbreak = '')
	{
		global $langs, $conf;

		$html = '';

		// initialize
		$object = $this->object;

		$object->fields = dol_sort_array($object->fields, 'position');
		$conf->entity = $object->entity;
		$linkedOrderSupplierId = 0;
		$object->fetchObjectLinked($object->id, 'operationorder');
		if (array_key_exists('order_supplier', $object->linkedObjectsIds)) {
			$linkedOrderSupplierId = array_shift($object->linkedObjectsIds['order_supplier']);
		}

		$filename = null;
		$filedir = null;
		$arraycontactemail = null;
		if ($linkedOrderSupplierId > 0) {
			$orderSupplier = new CommandeFournisseur($this->db);
			$retFetchorderSupplier = $orderSupplier->fetch($linkedOrderSupplierId);
			if ($retFetchorderSupplier > 0) {
				$filename = dol_sanitizeFileName($orderSupplier->ref);
				$build_path_multientity='';
				if ($object->entity!==1) {
					$build_path_multientity='/' . $object->entity;
				}
				$filedir = DOL_DATA_ROOT . $build_path_multientity . '/fournisseur/commande/' . dol_sanitizeFileName($orderSupplier->ref);

				$supplier = new Societe($this->db);
				$resultFetchFourn=$supplier->fetch($orderSupplier->socid);
				if ($resultFetchFourn > 0 && !empty($supplier->email)) {
					$arraycontactemail [$supplier->email]= $supplier->email;
				}
				$arraycontact = $supplier->contact_array_objects();
				foreach ($arraycontact as $contact) {
					if (!empty($contact->email) && (int) $contact->statut === 1) {
						$arraycontactemail [$contact->email]= $contact->email;
					}
				}
			}
		}
		$this->fetchVehiculesInfo($object);
		// separate fields to show on the left and on the right
		$fieldShowList = array();
		foreach ($object->fields as $key => $val) {
			// discard if it's a hidden field on form
			if (abs($val['visible']) != 1 && abs($val['visible']) != 3 && abs($val['visible']) != 4 && abs($val['visible']) != 5) {
				continue;
			}

			if (array_key_exists('enabled', $val) && isset($val['enabled']) && !verifCond($val['enabled'])) {
				continue; // we don't want this field
			}

			if (!empty($val['showonheader'])) {
				continue; // already on header
			}

			$fieldShowList[$key] = $val;
		}

		$url = $this->context->getControllerUrl($this->context->controller, '', false);
		$html .= '<form method="POST" action="' . $url . '">';
		$html .= $this->context->getFormToken();
		$html .= '<input type="hidden" name="op_id" value="' . $object->id . '">';
		$html .= '<div class="grid">';
		$html .= '<div class="card-left">';

		unset($object->fields["ref"]);
		unset($object->fields["fk_soc"]);
		unset($object->fields["ref_client"]);
		unset($object->fields["fk_user_meca"]);
		unset($object->fields["date_creation"]);
		$keyforbreak = 'total_ht';

		foreach ($object->fields as $key => $val) {
			if (!array_key_exists($key, $fieldShowList)) {
				continue; // not to show
			}

			$value = $object->$key;

			$html .= '<div class="grid field_' . $key . '">';

			$html .= '<div class="' . (empty($val['tdcss']) ? '' : $val['tdcss']) . ' fieldname_' . $key;
			$html .= '">';
			$labeltoshow = '';
			$labeltoshow .= '<strong>' . $langs->trans($val['label']) . '</strong>';
			$html .= $labeltoshow;
			$html .= '</div>';

			$html .= '<div class="valuefield fieldname_' . $key;
			if (!empty($val['cssview'])) {
				$html .= ' ' . $val['cssview'];
			}
			$html .= '">';
			if ($key == 'lang') {
				$langs->load('languages');
				$labellang = ($value ? $langs->trans('Language_' . $value) : '');
				//$html .= picto_from_langcode($value, 'class="paddingrightonly saturatemedium opacitylow"');
				$html .= $labellang;
			} elseif ($key == 'fk_conducteur') {
				if ($this->action == 'modify_driver') {
					$driverArray = $this->fetchDriverList();

					$html .= $this->form->selectarray('fk_conducteur', $driverArray, $object->fk_conducteur, 1, 0, '', 0, '30%');
					$html .= ajax_combobox('fk_conducteur', array(), 0, 0, 'resolve', '-1');
					$html .= '<input type="hidden" id="action" name="action" value="validate_modify_driver"/>';

					$html .= '
						<br/>
						<br/>
						<div class="grid">
							<input type="submit" name="save" role="button" value="' . dol_escape_htmltag($langs->trans('Save')) . '" />
							<input type="submit" name="cancel" role="button" value="' . dol_escape_htmltag($langs->trans('Cancel')) . '" />
						</div>';
				} else {
					if (!empty($object->fk_conducteur)) {
						$conducteur = new Contact($this->db);
						$conducteur->fetch($object->fk_conducteur, false);

						$html .= ucwords($conducteur->firstname);
						$html .= ' ' . strtoupper($conducteur->lastname);
					}
					$html .= '<input type="hidden" id="action" name="action" value="modify_driver"/>';
					$html .= '&nbsp;<input type="submit" style="width:50%" name="modify" role="button" value="' . dol_escape_htmltag($langs->trans('ModifyDriver')) . '" />';
				}
			} elseif ($key == 'fk_vehicule') {
				if (!empty($object->fk_vehicule)) {
					$vehicule = new Vehicule($this->db);
					$vehicule->fetch($object->fk_vehicule, false);

					$url_card = $this->context->getControllerUrl('vehiculecard', ['vh_id' => $object->fk_vehicule]);
					$html .= '<a href="' . $url_card . '">' . $vehicule->vin . ' - ' . $vehicule->immatriculation . '</a>';
				}
			} else {
				$html .= $this->form->showOutputFieldForObject($object, $val, $key, $value, '', '', '', 0);
			}
			$html .= '</div>';

			$html .= '</div>';

			// fields on the right
			if ($key == $keyforbreak) {
				$html .= '</div>';
				$html .= '<div class="card-right">';
			}
		}

		if (!empty($filename) && !empty($filedir)) {
			$html .= '
				<div class="grid field_pdf">
					<div>
						<strong>' . $langs->trans('OrderSupplierPDF') . '</strong>
					</div>
					<div class="valuefield">';
			$html .= $this->getDocumentsLink('supplier_order', $filename, $filedir);
			$html .= '
					</div>
				</div>';
		}
		if (!empty($arraycontactemail)) {
			$html .= '
				<div class="grid field_pdf">
					<div>
						<strong>' . $langs->trans('EmailSupplier') . '</strong>
					</div>
					<div class="valuefield">
						<ul>';
			foreach ($arraycontactemail as $email) {
				$html .= '<li>' . dol_escape_htmltag($email) . '</li>';
			}
			$html .= '
						</ul>
					</div>
				</div>';
		}

		$html .= '</div>';
		$html .= '</div><br><br>';
		$html .= '</form>';
		$html .= '<div>' . $this->printLines($object) . '</div>';

		// files
		$build_path_multientity='';
		if ($object->entity!==1) {
			$build_path_multientity='/' . $object->entity;
		}
		$filedir = DOL_DATA_ROOT . $build_path_multientity ."/operationorder/";
		$filelist = dol_dir_list($filedir . '/' . $object->ref, 'files');
		$html .= '
			<div class="grid field_pdf">
				<div class="card-left">
					<div>
						<strong>' . $langs->trans('OrFiles') . '</strong>
					</div>
					<div class="valuefield">';
		if (!empty($filelist) && !empty($filedir)) {
			$html .= '<ul>';
			foreach ($filelist as $file) {
				if (dol_mimetype($file['fullname']) !== 'application/pdf') {
					continue;
				}
				$html .= '<li>';
				$html .= $this->getDocumentsLink(
					'operationorder',
					'/' . dol_sanitizeFileName($object->ref),
					$filedir . dol_sanitizeFileName($object->ref),
					$file['name'],
					'',
					'operationorderdocument'
				);

				$html .= '</li>';
			}

			$html .= '</ul>';
		}
		$html .= '
				</div>
			</div>
			<style>
				.fileuploadform input {
					width: 40% !important;
				}
			</style>
			<div class="card-right fileuploadform">';
		$url_file = $this->context->getControllerUrl($this->context->controller, ['op_id' => $this->object->id], false);
		$html .= $this->formfile->form_attach_new_file($url_file, '', 0,  0, 1, 10, $object, '', 1, '', 0, 'formuserfile', '.pdf', '', 0, 0, 1, 1);
		$html .= '
			</div>

		</div>';

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
						<div><strong>' . $langs->trans("Ref") . ' : ' . dol_escape_htmltag($object->ref) . '</strong></div>
						<div><strong>' . $langs->trans("RefCustomer") . ' : ' . dol_escape_htmltag($object->ref_client) . '</strong></div>
						<div><strong>' . $langs->trans("ThirdParty") . ' : ' . dol_escape_htmltag($context->logged_thirdparty->ref) . '</strong></div>
					</div>
				</div>
				<div class="header-card-right-block inline-block" style="width: 24%;">';

		// show status
		$status = new OperationOrderStatus($this->db);
		$res = $status->fetchDefault($object->status, 0);
		if ($res > 0) {
			$html .= $status->getBadge();
		} else {
			$html .= $status->getStaticNomUrl($object->status);
		}
		$html .= '</div>';
		// Right block - end

		$html .= '</header>';

		return $html;
	}

	/**
	 * Html for header
	 *
	 * @param	Context	$context	Context object
	 * @return	string
	 */
	protected function printLines($object)
	{
		global $langs;

		if (empty($object->lines)) {
			return '';
		}

		$TLineQtyUsed = $object->getAlreadyUsedQtyLines();
		$TLastLinesByProduct = $object->getLastLinesByProduct();
		$html = '
			<table id="webportal-operationorder-line-list" role="grid">
				<thead>
					<tr class="">
						<th class="wrapcolumntitle  maxwidthsearch" scope="col"></th>
						<th class="wrapcolumntitle  maxwidthsearch" scope="col" title="' . $langs->trans('Ref') . '">
							' . $langs->trans('Ref') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch" scope="col">
							' . $langs->trans('Qty') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch" scope="col">
							' . $langs->trans('Label') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch" scope="col">
							' . $langs->trans('Description') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch" scope="col">
							' . $langs->trans('TotalHT') . '
						</th>
					</tr>
				</thead>
				<tbody>

			';

		foreach ($object->lines as $line) {
			// QTY ORDERED
			//Repris d'interface manager
			$qtyUsed = $line->getQtyUsed($TLineQtyUsed, $TLastLinesByProduct);
			if ($qtyUsed > $line->qty) {
				$textClass = "text-danger paddingrightonly";
				$iconInfo = '<i class="fa fa-caret-up"></i>';
			} elseif ($qtyUsed < 0) {
				$textClass = "text-danger paddingrightonly";
				$iconInfo = '<i class="fa fa-bolt"></i>';
			} else {
				$textClass = "";
				$iconInfo = "";
			}
			$outQty = '
				<div class="operation-order-sortable-list__item__title__col -qty-ordered">';
			if (!empty($qtyUsed)) {
				$outQty .= '<span class="' . $textClass . 'classfortooltip" title="' . $langs->trans("QtyUsed") . '" >
					' . $iconInfo . '<i class="fas fa-box-open"></i>' . $qtyUsed . '</span> / ';
			}
			if (empty($line->product->array_options['options_or_is_job'])) {
				$outQty .= '
					<span class=" classfortooltip" title="' . $langs->trans("QtyPlanned") . '" >
						<i class="fas fa-box-open"></i>' . $line->qty . '
					</span>';
			} else {
				$outQty .= '
					<span class=" classfortooltip" title="' . $langs->trans($line->fields['fk_c_operationorder_type']['label']) . '" >
						' . $line->showOutputField(
						$line->fields['fk_c_operationorder_type'], 'fk_c_operationorder_type',
						$line->fk_c_operationorder_type) . '
					</span>';
			}
			$outQty .= '</div>';
			$productRef = $line->product->ref;
			$html .= '
				<tr class="oddeven">
					<td></td>
					<td>
						' . $productRef . '
					</td>
					<td>
						' . $outQty . '
					</td>
					<td>
						' . $line->label . '
					</td>
					<td>
						' . dolPrintLabel($line->description) . '
					</td>
					<td>
						' . $line->total_ht . '
					</td>
				</tr>';
		}

		$html .= '
			</tbody>
		</table>';
		return $html;
	}

	private function fetchVehiculesInfo(&$object)
	{
		if (empty($object->fk_vehicule)) {
			return;
		}


		$object->fields += [
			'fk_vehicule_type' => array(
				'type'	   => 'sellist:c_dolifleet_vehicule_type:label:rowid::active=1',
				'label'	   => 'vehiculeType',
				'visible'  => 1,
				'notnull'  => 1,
				'default'  => 0,
				'enabled'  => 1,
				'position' => 6040,
				'index'	   => 1,
			),
			'fk_vehicule_mark' => array(
				'type'	   => 'sellist:c_dolifleet_vehicule_mark:label:rowid::active=1',
				'label'	   => 'vehiculeMark',
				'visible'  => 1,
				'notnull'  => 1,
				'default'  => 0,
				'enabled'  => 1,
				'position' => 6050,
				'index'	   => 1,
			),
			'date_immat'	   => array(
				'type'		=> 'date',
				'label'		=> 'immatriculation_date',
				'enabled'	=> 1,
				'visible'	=> 1,
				'notnull'	=> 1,
				'default'	=> 0,
				'position'	=> 6070,
				'searchall' => 1,
			),
			'fk_contract_type' => array(
				'type'	   => 'sellist:c_dolifleet_contract_type:label:rowid::(active=1)',
				'label'	   => 'contractType',
				'visible'  => 1,
				'enabled'  => 1,
				'position' => 120,
				'index'	   => 1,
			),
			'linkedvh'		   => array(
				'type'	   => 'varchar',
				'label'	   => 'linkedvh',
				'visible'  => 1,
				'enabled'  => 1,
				'position' => 120,
				'index'	   => 1,
			),
		];

		$vehicule = new Vehicule($this->db);
		$vehicule->fetch($object->fk_vehicule, false);
		$object->fk_vehicule_type = $vehicule->fk_vehicule_type;
		$object->fk_vehicule_mark = $vehicule->fk_vehicule_mark;
		$object->fk_contract_type = $vehicule->fk_contract_type;
		$object->date_immat = $vehicule->date_immat;
		$object->linkedvh = $this->getorlinkedHV($vehicule);
	}

	public function getorlinkedHV($vehicule)
	{
		$sql = 'SELECT IF(fk_target = ' . $vehicule->id . ',fk_source,fk_target) as linked FROM ' . $this->db->prefix() . 'dolifleet_vehicule_link ';
		$sql .= 'WHERE (fk_source = ' . $vehicule->id . ' OR fk_target = ' . $vehicule->id . ') ORDER BY date_start DESC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog($this->db->lasterror(), 'LOG_ERR');
			return '';
		}

		$num = $this->db->num_rows($resql);
		if ($num <= 0) {
			return '';
		}

		$obj = $this->db->fetch_object($resql);
		$vh = new Vehicule($this->db);
		$ret = $vh->fetch($obj->linked);
		if ($ret < 0) {
			dol_syslog(implode(',', array_merge($vh->errors, [$vh->error])), 'LOG_ERR');
			return '';
		}

		if ($ret == 0) {
			return '';
		}

		$url_card = $this->context->getControllerUrl('vehiculecard', ['vh_id' => $vh->id]);
		$html = '<a href="' . $url_card . '">' . $vh->vin . ' - ' . $vh->immatriculation . '</a>';
		return $html;
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
		$fk_conducteur = GETPOST('fk_conducteur', 'int');
		$backtopage = $this->backtopage;
		$backtopageforcancel = $this->backtopageforcancel;
		$cancel = $this->cancel;
		$elementEn = $this->elementEn;
		$id = $this->id;

		$context = Context::getInstance();
		if (empty($backtopage) || ($cancel && empty($id))) {
			if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
				$backtopage = $context->getControllerUrl($elementEn . 'card', ['op_id' => $id]);
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



		if (GETPOST('sendit', 'alpha')) {

			$this->context = $context;

			$this->saveFile();

			$urlList = $context->getControllerUrl('operationordercard', ['op_id' => $this->object->id]);
			header("Location: " . $urlList);
			exit;

		} elseif ($action == 'validate_modify_driver') {
			$this->object->fk_conducteur = $fk_conducteur;

			$retUpdate = $this->object->update($context->logged_user);
			if ($retUpdate < 0) {
				$context->setEventMessages($this->object->error, $this->object->errors, 'errors');
				return;
			}

			$urlList = $context->getControllerUrl('operationordercard', ['op_id' => $this->object->id]);
			header("Location: " . $urlList);
			exit;
		}


	}

	/**
	 * Show a Document icon with link(s)
	 * You may want to call this into a div like this:
	 * print '<div class="inline-block valignmiddle">'.$formfile->getDocumentsLink($element_doc, $filename, $filedir).'</div>';
	 *
	 * @param string $modulepart 'propal', 'facture', 'facture_fourn', ...
	 * @param string $modulesubdir Sub-directory to scan (Example: '0/1/10', 'FA/DD/MM/YY/9999'). Use '' if file is not into subdir of module.
	 * @param string $filedir Full path to directory to scan
	 * @param string $filter Filter filenames on this regex string (Example: '\.pdf$')
	 * @param string $morecss Add more css to the download picto
	 * @param int<0,1> $allfiles 0=Only generated docs, 1=All files
	 * @return    string                Output string with HTML link of documents (might be empty string). This also fill the array ->infofiles
	 */
	public function getDocumentsLink($modulepart, $modulesubdir, $filedir, $filter = '', $morecss = '', $controler='commandefourndocument')
	{
		global $conf;
		include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		$out = '';

		$context = Context::getInstance();
		if (!$context) {
			return '';
		}

		$this->infofiles = array('nboffiles' => 0, 'extensions' => array(), 'files' => array());

		$file_list = dol_dir_list($filedir, 'files', 0, '', '\.meta$|\.png$'); // We also discard .meta and .png preview
		// For ajax treatment
		$out .= '<!-- html.formwebportal::getDocumentsLink -->' . "\n";
		if (!empty($file_list)) {
			$tmpout = '';

			// Loop on each file found
			$found = 0;
			$i = 0;
			foreach ($file_list as $file) {
				$i++;
				if ($filter && !preg_match('/' . $filter . '/i', $file["name"])) {
					continue; // Discard this. It does not match provided filter.
				}

				$found++;
				// Define relative path for download link (depends on module)
				$relativepath = $file["name"]; // Cas general
				if ($modulesubdir) {
					$relativepath = $modulesubdir . "/" . $file["name"]; // Cas propal, facture...
				}
				$url = $context->getControllerUrl($controler) . '&modulepart=' . $modulepart . '&entity=' . $conf->entity . '&file=' . urlencode($relativepath) . '&soc_id=' . $context->logged_thirdparty->id;
				$tmpout .= '<a href="' . $url . '"' . ($morecss ? ' class="' . $morecss . '"' : '') . ' role="downloadlink"';
				$mime = dol_mimetype($relativepath, '', 0);
				if (preg_match('/text/', $mime)) {
					$tmpout .= ' target="_blank" rel="noopener noreferrer"';
				}
				$tmpout .= '>';
				$tmpout .= img_mime($relativepath);
				$tmpout .= mb_strimwidth($file["name"], 0, 30, "...");
				$tmpout .= '</a>';
			}

			if ($found) {
				$out .= $tmpout;
			}
		}

		return $out;
	}

	/**
	 * Do actions
	 *
	 * @return	void
	 */
	public function saveFile()
	{
		global $langs, $conf;
		$id = GETPOST('op_id');

		include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		$name = $_FILES['userfile']['name'];
		if (is_array($_FILES['userfile']['tmp_name'])) {
			$userfiles = $_FILES['userfile']['tmp_name'];
		} else {
			$userfiles = array($_FILES['userfile']['tmp_name']);
		}

		if (dol_mimetype($_FILES['userfile']['name']) !== 'application/pdf') {
			$this->context->setEventMessages('OnlyPdfCanBeLoadedHere', null, 'errors');
			return;
		}

		$error = 0;
		foreach ($userfiles as $key => $userfile) {
			if (empty($_FILES['userfile']['tmp_name'][$key])) {
				$error++;
				if ($_FILES['userfile']['error'][$key] == 1 || $_FILES['userfile']['error'][$key] == 2) {
					$this->context->setEventMessages($langs->trans('ErrorFileSizeTooLarge'), null, 'errors');
				} else {
					$this->context->setEventMessages($langs->trans("ErrorFieldRequired",
						$langs->transnoentitiesnoconv("File")), null, 'errors');
				}
			}
			if (preg_match('/__.*__/', $_FILES['userfile']['name'][$key])) {
				$error++;
				$this->context->setEventMessages($langs->trans('ErrorWrongFileName'), null, 'errors');
			}
		}
		$op = new OperationOrder($this->db);
		$op->fetch($id);
		$build_path_multientity='';
		if ($op->entity!==1) {
			$build_path_multientity='/' . $op->entity;
		}
		$upload_dir = DOL_DATA_ROOT . $build_path_multientity . '/operationorder/' .$op->ref;
		if (!$error) {
			$result = dol_add_file_process(
				$upload_dir, 0, 1, 'userfile', $name, null, '', 0, $op
			);
			if ($result <= 0) {
				$this->context->setEventMessages($langs->transnoentitiesnoconv("SomethingWrongHappenedDuringFileLoad"),
					null, 'errors');
			}
		}
	}
}
