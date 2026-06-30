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
 * \file    class/actions_operationorder.class.php
 * \ingroup operationorder
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */
require_once dol_buildpath('/operationorder/class/productdefaultwarehouse.class.php');
require_once dol_buildpath('operationorder/controllers/operationorderlist.controller.class.php');
require_once dol_buildpath('operationorder/controllers/operationordercard.controller.class.php');
require_once dol_buildpath('operationorder/controllers/operationorderform.controller.class.php');
require_once dol_buildpath('operationorder/controllers/driverlist.controller.class.php');
require_once dol_buildpath('operationorder/controllers/vsrlist.controller.class.php');
require_once dol_buildpath('operationorder/controllers/driverform.controller.class.php');
require_once dol_buildpath('operationorder/controllers/vehiculelist.controller.class.php');
require_once dol_buildpath('operationorder/controllers/commandefourndocument.controller.class.php');
require_once dol_buildpath('operationorder/controllers/operationorderdocument.controller.class.php');
require_once dol_buildpath('operationorder/controllers/vehiculecard.controller.class.php');
require_once dol_buildpath('operationorder/controllers/opbyvehiculelist.controller.class.php');
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

/**
 * Class ActionsOperationOrder
 */
class ActionsOperationOrder
{
	/**
	 * @var DoliDb        Database handler (result of a new DoliDB)
	 */
	public $db;

	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param array         $parameters     Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;
		$error = 0; // Error counter
		$resprints = '';
		$results = array();
		$replace = 0;
		$errors = array();

		if (in_array('ordersuppliercard', explode(':', $parameters['context']))) {
			/*
			 * Ajout du element/element entre operation order et commande fourn
			 * Voir la partie form post dans formObjectOptions
			 */
			if ($action == "add") {
				include_once __DIR__ . '/operationorder.class.php';
				$operationOrder = new OperationOrder($object->db);
				// origin et originid n'est pas géré en dehors de certains elements, il faut donc le gérer à part pour opération order
				$origin = GETPOST('operation_order_origin', 'alpha');
				$originid = GETPOST('operation_order_originid', 'int'); // For backward compatibility

				// Add form element to bypass origin and origin id from operation order
				if (!empty($origin) && !empty($originid) && $origin == $operationOrder->element) {
					// if operation order exist set it for trigger
					if ($operationOrder->fetch($originid) > 0) {
						$object->linked_objects[$operationOrder->element] = $originid;
					}
				}
			}
			if ($action == 'presend') {
				$res = $object->fetchObjectLinked();
				if ($res < 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					return -1;
				}

				if (is_array($object->linkedObjects) && array_key_exists('operationorder', $object->linkedObjects) && count($object->linkedObjects['operationorder']) > 0) {
					$operationorder = reset($object->linkedObjects['operationorder']);
					$langs->load('operationorder@operationorder');
					dol_include_once('/dolifleet/class/vehicule.class.php');
					$veh = new Vehicule($this->db);
					$resultFetch = $veh->fetch($operationorder->fk_vehicule);
					if ($resultFetch < 0) {
						setEventMessages($veh->error, $veh->errors, 'errors');
						return -1;
					}
					$_POST['subject'] = $langs->trans('GOPSend', $object->ref, $operationorder->ref, $veh->immatriculation);
				}
			}
			if ($action == '') {
				$res = $object->fetchObjectLinked();
				if ($res < 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					return -1;
				}
				if (is_array($object->linkedObjects)
					&& array_key_exists('operationorder', $object->linkedObjects)
					&& count($object->linkedObjects['operationorder']) > 0
					&& count($object->lines) == 0) {
					$operationorder = reset($object->linkedObjects['operationorder']);
					$result = $object->delete($user);
					if ($result < 0) {
						setEventMessages($object->error, $object->errors, 'errors');
					} else {
						setEventMessages('SupplierORderDeleteRedirectOR', null, 'mesgs');
						header('Location: ' . dol_buildpath('/operationorder/operationorder_card.php', 2) . '?id=' . $operationorder->id);
						exit;
					}
				}
			}
		}
		if (in_array('propalcard', explode(':', $parameters['context']))) {
			if ($object->status == 2
				&& $action=='confirmcreateor'
				&& GETPOST('confirm', 'alpha')=='yes') {
				$error=0;
				if ((empty(GETPOST('options_fk_vehicule', 'int'))
				|| GETPOST('options_fk_vehicule', 'int')==-1)) {
					setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Type")), 'errors');
					$action = 'createor';
					$error++;
				}

				if (empty(GETPOST('km_on_creation', 'int'))) {
					setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("km_on_creation")), 'errors');
					$action = 'createor';
					$error++;
				}

				if (empty(GETPOST('ref_client', 'alpha'))) {
					setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("RefCustomer")), 'errors');
					$action = 'createor';
					$error++;
				}

				if ((!empty(GETPOST('job_selected', 'int'))
					&& GETPOST('job_selected', 'int')!=='-1')
					&& (empty(GETPOST('fk_c_operationorder_type_frmconfirm', 'int'))
						|| GETPOST('fk_c_operationorder_type_frmconfirm', 'int')==-1)
				) {
					setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("LineOperationOrderType")), 'errors');
					$action = 'createor';
					$error++;
				}

				if (empty($error)) {
					include_once __DIR__ . '/operationorder.class.php';
					dol_include_once('/operationorder/lib/operationorder.lib.php');
					$operationOrder = new OperationOrder($this->db);
					$operationOrder->fk_vehicule = GETPOST('options_fk_vehicule', 'int');
					$operationOrder->fk_soc = $object->socid;
					$operationOrder->km_on_creation = GETPOST('km_on_creation', 'int');
					$operationOrder->ref_client = GETPOST('ref_client', 'int');
					$operationOrder->date_operation_order=dol_now();
					$resultCreaOr = $operationOrder->save($user);
					if ($resultCreaOr<0) {
						setEventMessages($operationOrder->error, $operationOrder->errors, 'errors');
						$action = 'createor';
						$error++;
					} else {
						//fk_c_operationorder_type_frmconfirm GETPOST('k_c_operationorder_type_frmconfirm', 'int')
						//job_selected
						$fk_product = GETPOST('job_selected', 'int');
						$resAddLineJob=0;
						if (!empty($fk_product)	&& $fk_product!==-1) {
							$prd = new Product($this->db);
							$resultFetch = $prd->fetch($fk_product, '', '', '', 1, 1, 1);
							if ($resultFetch > 0) {
								$resAddLineJob= $operationOrder->addline($prd->description,
									1,
									0,
									null,
									0,
									0,
									0, $fk_product,
									0,
									0,
									0,
									$prd->type,
									-1,
									0,
									0,
									$prd->label,
									0,
									'',
									0,
									true,
									GETPOST('fk_c_operationorder_type_frmconfirm', 'int'),
								0);
								if ($resAddLineJob<0) {
									setEventMessages($operationOrder->error, $operationOrder->errors, 'errors');
									$action = 'createor';
									$error++;
								}
							}
						}


						foreach ($object->lines as $line) {
							$prod = new Product($this->db);
							$retProd = $prod->fetch($line->fk_product);
							$tplanned = 0;
							if (!empty($prod->duration_value) && $prod->type==1) {
								$tplanned = $prod->duration_value * 60 * 60 * $line->qty;
							}
							if ($retProd > 0) {
								$retadd = $operationOrder->addline($line->desc,
									$line->qty,
									$line->subprice,
									$prod->fk_default_warehouse,
									$prod->cost_price,
									$tplanned,
									0,
									$prod->id,
									0,
									0,
									0,
									$prod->type,
									-1,
									0,
									$resAddLineJob,
									$prod->label,
									0,
									'',
									0,
									true,
									GETPOST('k_c_operationorder_type_frmconfirm', 'int'),
								0
									);
								if ($retadd < 1) {
									setEventMessages($langs->trans('OperationOrderAddlineError'), $operationOrder->errors, 'errors');
								}
							}
						}

						$operationOrder->add_object_linked($object->element, $object->id);

						$resUpd = $operationOrder->update($user);

						if ($resUpd < 0) {
							setEventMessages($operationOrder->error, $operationOrder->errors, 'errors');
						} else {
							header('Location: ' . dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $operationOrder->id);
							exit;
						}
					}
				}
			}
		}
		if (in_array('invoicecard', explode(':', $parameters['context']))) {
			if ($action=='confirm_valid'
				&& GETPOST('confirm', 'alpha') == 'yes'
				&& $object->type == Facture::TYPE_CREDIT_NOTE
				&& GETPOST('free_or_confirm', 'alpha')=='on') {
				$sql = "UPDATE ".$this->db->prefix().$object->table_element." SET ref_ext = CONCAT(COALESCE(ref_ext,''),'_confirm_free_or') WHERE rowid = ".$object->id;
				$resql=$this->db->query($sql);
				if (!$resql) {
					setEventMessage($this->db->lasterror(), 'errors');
					return -1;
				}
			}
		}

		$arrayController = [
			'vsrlist',
			'vehiculelist',
			'vehiculecard',
			'driverlist',
			'driverform',
			'operationorderlist',
			'operationordercard',
			'operationorderform',
			'opbyvehiculelist',
			'commandefourndocument',
			'operationorderdocument'
		];
		if (isset($parameters['controller']) && in_array($object->controller, $arrayController)) {
			global $langs;
			$langs->loadLangs(['operationorder@operationorder', 'dolifleet@dolifleet']);
			switch ($object->controller) {
				case 'operationorderlist':
					$object->addControllerDefinition(
						'operationorderlist',
						dol_buildpath('/operationorder/controllers/operationorderlist.controller.class.php'),
						'OperationOrderListController'
					);
					$object->controllerInstance = new OperationOrderListController();
					break;
				case 'operationordercard':
					$object->addControllerDefinition(
						'operationordercard',
						dol_buildpath('/operationorder/controllers/operationordercard.controller.class.php'),
						'OperationOrderCardController'
					);
					$object->controllerInstance = new OperationOrderCardController();
					break;
				case 'operationorderform':
					$object->addControllerDefinition(
						'operationorderform',
						dol_buildpath('/operationorder/controllers/operationorderform.controller.class.php'),
						'OperationOrderFormController'
					);
					$object->controllerInstance = new OperationOrderFormController();
					break;
				case 'driverlist':
					$object->addControllerDefinition(
						'driverlist', dol_buildpath('/operationorder/controllers/driverlist.controller.class.php'),
						'DriverListController'
					);
					$object->controllerInstance = new DriverListController();
					break;
				case 'driverform':
					$object->addControllerDefinition(
						'driverform', dol_buildpath('/operationorder/controllers/driverform.controller.class.php'),
						'DriverFormController'
					);
					$object->controllerInstance = new DriverFormController();
					break;
				case 'vsrlist':
					$object->addControllerDefinition(
						'vsrlist', dol_buildpath('/operationorder/controllers/vsrlist.controller.class.php'),
						'VSRListController'
					);
					$object->controllerInstance = new VSRListController();
					break;
				case 'vehiculelist':
					$object->addControllerDefinition(
						'vehiculelist', dol_buildpath('/operationorder/controllers/vehiculelist.controller.class.php'),
						'VehiculeListController'
					);
					$object->controllerInstance = new VehiculeListController();
					break;
				case 'vehiculecard':
					$object->addControllerDefinition(
						'vehiculecard', dol_buildpath('/operationorder/controllers/vehiculecard.controller.class.php'),
						'VehiculeCardController'
					);
					$object->controllerInstance = new VehiculeCardController();
					break;
				case 'opbyvehiculelist':
					$object->addControllerDefinition(
						'opbyvehiculelist', dol_buildpath('/operationorder/controllers/opbyvehiculelist.controller.class.php'),
						'OPByVehiculeListController'
					);
					$object->controllerInstance = new OPByVehiculeListController();
					break;
				case 'commandefourndocument':
					$object->addControllerDefinition(
						'commandefourndocument', dol_buildpath('/operationorder/controllers/commandefourndocument.controller.class.php'),
						'CommandeFournDocumentController'
					);
					$object->controllerInstance = new CommandeFournDocumentController();
					break;
				case 'operationorderdocument':
					$object->addControllerDefinition(
						'operationorderdocument', dol_buildpath('/operationorder/controllers/operationorderdocument.controller.class.php'),
						'OperationOrderDocumentController'
					);
					$object->controllerInstance = new OperationOrderDocumentController();
					break;
			}

			$object->setControllerFound();

			$object->controllerInstance->action();
			return 0;
		}

		// retours
		if (!$error) {
			$this->results = $results;
			$this->resprints = $resprints;

			return $replace; // 0 or return 1 to replace standard code
		} else {
			array_merge($this->errors, $errors);
			return -1;
		}
	}

	/**
	 * Execute action viewDictionaryFieldlist
	 *
	 * @param array $parameters Array of parameters
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action 'add', 'update', 'view'
	 * @return    int                            <0 if KO,
	 *                                        =0 if OK but we want to process standard actions too,
	 *                                            >0 if OK and we want to replace standard actions.
	 */
	public function viewDictionaryFieldlist($parameters, &$object, &$action)
	{
		global $db, $langs;
		if ($parameters['tabname'] !== $db->prefix() . 'c_operationorder_type') {
			return 0;
		} else {
			$langs->load('operationorder@operationorder');

			$sql = '
			SELECT nom
			FROM ' . $db->prefix() . 'societe
			WHERE rowid = ' . intval($object->fk_soc) . '  LIMIT 1';

			$resql = $db->query($sql);

			if (!$resql) {
				setEventMessage($db->lasterror(), "error");
				return 0;
			}

			$soc = $db->fetch_object($resql);

			$html = '
			<td class="">
				' . $object->code . '
			</td>
			<td class="">
				' . $object->label . '
			</td>
			<td class="">
				' . (!empty($object->blocked_status_code) ? $object->blocked_status_code : '') . '
			</td>
			<td class="">
				' . $soc->nom . '
			</td>
			<td class="right">
				' . (!empty($object->position) ? $object->position : '') . '
			</td>';

			print $html;
			return 1;
		}
	}

	/**
	 * Execute action createDictionaryFieldlist
	 *
	 * @param array $parameters Array of parameters
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action 'add', 'update', 'view'
	 * @return    int                            <0 if KO,
	 *                                        =0 if OK but we want to process standard actions too,
	 *                                            >0 if OK and we want to replace standard actions.
	 */
	public function createDictionaryFieldlist($parameters, &$object, &$action)
	{
		global $db;
		if ($parameters['tabname'] !== $db->prefix() . 'c_operationorder_type') {
			return 0;
		} else {
			print $this->_editOrTypeDict($parameters, $object, $action);
			return 1;
		}
	}

	/**
	 * Execute action editDictionaryFieldlist
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function editDictionaryFieldlist($parameters, &$object, &$action)
	{
		global $db;
		if ($parameters['tabname'] !== $db->prefix() . 'c_operationorder_type') {
			return 0;
		} else {
			print $this->_editOrTypeDict($parameters, $object, $action);
			return 1;
		}
	}

	/**
	 * Execute action editDictionaryFieldlist
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function tabContentViewProduct($parameters, &$object, &$action)
	{
		global $db, $langs, $conf;
		if ($action != 'view') {
			return 0;
		}

		if (!in_array('productcard', explode(':', $parameters['context']))) {
			return 0;
		}

		if ($object->id <= 0) {
			return -1;
		}

		print '<script>
            $(document).ready(function () {
                let leftCol = $(".fichehalfleft");
                var tr = leftCol.find("tr").filter(function() {
                    return $(this).find("td:first").text().trim() === "' . $langs->transnoentities("DefaultWarehouse") . '";
                });
                tr.hide();
            });
        </script>';
	}

	/**
	 * Execute action editDictionaryFieldlist
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function formObjectOptionsProduct($parameters, &$object, &$action)
	{
		global $db, $conf;
		if ($action != 'view') {
			return 0;
		}

		if (!in_array('productcard', explode(':', $parameters['context']))) {
			return 0;
		}

		if ($object->id <= 0) {
			return -1;
		}

		if (intval($object->type) === Product::TYPE_SERVICE) {
			return 0;
		}

		$dProductWarehouse = new ProductDefaultWarehouse($db);

		$retFetch = $dProductWarehouse->fetch($object->id, $conf->entity);
		if ($retFetch < 0) {
			$this->error = $dProductWarehouse->error;
			$this->errors += $dProductWarehouse->errors;
			return $retFetch;
		}

		if (!empty($dProductWarehouse->fk_default_warehouse)) {
			$fk_default_warehouse = $dProductWarehouse->fk_default_warehouse;
		} else {
			$fk_default_warehouse = $dProductWarehouse->getDefaultWarehouseForEntity($conf->entity);
		}

		if ($fk_default_warehouse < 0) {
			$this->error = $dProductWarehouse->error;
			$this->errors += $dProductWarehouse->errors;
			return $fk_default_warehouse;
		}

		$warehouse = new Entrepot($db);
		$retFetchWarehouse = $warehouse->fetch($fk_default_warehouse);
		if ($retFetchWarehouse < 0) {
			$this->error = $warehouse->error;
			$this->errors += $warehouse->errors;
			return -1;
		}

		$object->setExtraField('fk_default_warehouse', $warehouse->getNomUrl(1));
	}

	/**
	 * Execute return HTML action editDictionaryFieldlist
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	private  function _editOrTypeDict($parameters, $object, $action)
	{

		global $db;
		$defaultCode = null;
			$defaultLabel = null;
			$defaultBStatusCode = 0;
			$defaultSoc = null;
			$defaultPos = 0;
		if (!empty($object)) {
			$defaultCode = $object->code;
			$defaultLabel = $object->label;
			$defaultBStatusCode = (empty($object->blocked_status_code)?0:$object->blocked_status_code);
			$defaultSoc = (!empty($object->fk_soc) ? $object->fk_soc : '');
			$defaultPos = (empty($object->position)?0:$object->position);
		}

			$html = '
			<td class="">
				<input type="text" class="flat maxwidth100"  maxlength="30" value="' . $defaultCode . '" name="code">
			</td>';

			$html .= '
			<td class="">
				<input type="text" class="flat quatrevingtpercent"  maxlength="255" value="' . $defaultLabel . '" name="label">
			</td>';

			$html .= '
			<td class="">
				<input type="text" class="flat"  maxlength="255" value="' . $defaultBStatusCode . '" name="blocked_status_code">
			</td>';

			$html .= '<td class="">';
			$form = new Form($db);
			$html .= $form->selectForForms('Societe:societe/class/societe.class.php:0:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))', 'fk_soc', $defaultSoc, 1, '', '', '', '');
			$html .= '</td>';

			$html .= '
			<td class="right">
				<input type="text" class="flat maxwidth50 right" value="' . $defaultPos . '" name="position">
			</td>';

			return $html;
	}


	/**
	 * Overloading the formObjectOptions function : replacing the parent's function with the one below
	 *
	 * @param array         $parameters     Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		$error = 0; // Error counter
		$resprints = '';
		$results = array();
		$replace = 0;
		$errors = array();

		if (in_array('ordersuppliercard', explode(':', $parameters['context']))) {
			/*
			 * Ajout du element/element entre operation order et commande fourn
			 * Voir la partie ajout dans doActions
			 */

			$resprints .= "\n" . '<!-- BEGIN OperationOrder formObjectOptions -->' . "\n";
			// rend possible de passer une commande fournisseur depuis un OR. Cette commande fournisseur est lié à l’OR

			include_once __DIR__ . '/operationorder.class.php';
			$operationOrder = new OperationOrder($object->db);
			// origin et originid n'est pas géré en dehors de certains elements, il faut donc le gérer à part pour opération order
			$origin = GETPOST('operation_order_origin', 'alpha');
			$originid = GETPOST('operation_order_originid', 'int');

			// Add form element to bypass origin and origin id from operation order
			if (!empty($origin) && !empty($originid)) {
				if ($origin == $operationOrder->element) {
					$resprints .= '<input type="hidden" name="operation_order_origin" value="' . $operationOrder->element . '" >' . "\n";
					$resprints .= '<input type="hidden" name="operation_order_originid" value="' . $originid . '" >' . "\n";
				}
			}

			$resprints .= '<!-- END OperationOrder formObjectOptions -->' . "\n";
		} elseif (in_array('productcard', explode(':', $parameters['context']))) {
			return $this->formObjectOptionsProduct($parameters, $object, $action, $hookmanager);
		}

		if (!$error) {
			$this->results = $results;
			$this->resprints = $resprints;

			return $replace; // 0 or return 1 to replace standard code
		} else {
			array_merge($this->errors, $errors);
			return -1;
		}
	}

	/**
	 * @param array $parameters parameters
	 * @param Object $object Object to use hooks on
	 * @param string $action Action code on calling page ('create', 'edit', 'view', 'add', 'update', 'delete'...)
	 * @param object $hookmanager class instance
	 * @return int
	 */
	public function formConfirm($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user, $conf;

		$contextArray = explode(':', $parameters['context']);
		if (in_array('propalcard', $contextArray)) {
			if ($object->status == 2) {
				if ($action == 'createor') {
					$formquestion = array();

					$extrafields = new ExtraFields($this->db);
					// fetch optionals attributes and labels
					$extrafields->fetch_name_optionals_label($object->table_element);

					$formquestion[] = array('type'=>'other',
						'label'=>$extrafields->attributes[$object->table_element]['label']['fk_vehicule'],
						'name'=>'options_fk_vehicule',
						'tdclass' =>'fieldrequired',
						'value'=>$extrafields->showInputField('fk_vehicule', $object->array_options['options_fk_vehicule'], '', '', '', '', $object->id, $object->table_element));


					$formquestion[] = array('type' => 'text',
							'label' => $langs->trans('km_on_creation'),
							'name' => 'km_on_creation',
							'tdclass' =>'fieldrequired'
							);

					$formquestion[] = array('type' => 'hidden',
							'name' => 'id',
							'value' =>$object->id
							);

					$formquestion[] = array('type' => 'text',
							'label' => $langs->trans('RefCustomer'),
							'name' => 'ref_client',
							'value'=>$object->ref_client,
							'tdclass' =>'fieldrequired'
							);


					$sqlJob  = "SELECT p.rowid as rowid, p.ref as ref, p.label as label, p.tosell as tosell, p.tobuy as tobuy, ef.doc_obl ";
					$sqlJob .= "FROM ".$this->db->prefix()."product as p ";
					$sqlJob .= "INNER JOIN ".$this->db->prefix()."product_extrafields as ef ON p.rowid = ef.fk_object ";
					$sqlJob .= "WHERE p.entity IN (".getEntity('product').") AND p.fk_product_type=1 AND ef.or_is_job =1 ";
					$this->db->query($sqlJob);
					$nbtotalofrecords = 0;
					$jobs=[];
					$resqlJob = $this->db->query($sqlJob);
					if ($resqlJob) {
						$nbtotalofrecords = $this->db->num_rows($resqlJob);
						while ($objJob = $this->db->fetch_object($resqlJob)) {
							$jobs[$objJob->rowid]=$objJob->ref;
						}
					} else {
						setEventMessage($langs->trans("Error").$this->db->lasterror(), 'errors');
					}

					$sqlJobType  = "SELECT p.rowid as rowid, p.code as code, p.label as label, p.fk_soc as fk_soc, p.active as active ";
					$sqlJobType .= "FROM ".$this->db->prefix()."c_operationorder_type as p ";
					$sqlJobType .= "WHERE p.entity IN (".getEntity('product').")";
					$sqlJobType .= " AND p.active=1";
					$this->db->query($sqlJob);
					$jobsTypes=[];
					$resqlJobType = $this->db->query($sqlJobType);
					if ($resqlJobType) {
						$nbtotalofrecords += $this->db->num_rows($resqlJobType);
						while ($objJobType = $this->db->fetch_object($resqlJobType)) {
							$jobsTypes[$objJobType->rowid]=$objJobType->code. ' - '. $objJobType->label;
						}
					} else {
						setEventMessage($langs->trans("Error").$this->db->lasterror(), 'errors');
					}

					if ($nbtotalofrecords>0) {
						$formquestion[] = array('type' => 'select',
							'label' => $langs->trans('SelectJob'),
							'name' => 'job_selected',
							'values' => $jobs,
							'default'=>GETPOST('job_selected', 'int')
						);


						$formquestion[] = array('type' => 'select',
							'label' => $langs->trans('LineOperationOrderType'),
							'name' => 'fk_c_operationorder_type_frmconfirm',
							'values' => $jobsTypes,
							'tdclass' =>'jobTypeOpeSelect',
							'default'=>GETPOST('fk_c_operationorder_type_frmconfirm', 'int')
							);

						$outjs = '
						<script>
							$(document).ready(function() {

								$("#job_selected").change(function() {
									if ($(this).val() == "-1") {
										$(".jobTypeOpeSelect").hide();
										$(".fk_c_operationorder_type_frmconfirm").hide();
										$("#fk_c_operationorder_type_frmconfirm").val("-1");
									} else {
										$(".jobTypeOpeSelect").show();
										$(".fk_c_operationorder_type_frmconfirm").show();
									}
								});
								$("#job_selected").change();
							});
						</script>';
						$formquestion[] = array('type'=>'other','label'=>'', 'value'=>$outjs);
					}


					$form = new Form($this->db);
					//$langs->trans('ConfirmCreationORVehicles', $object->ref)
					$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('createor'), '', 'confirmcreateor', $formquestion, '', 1);

					$this->resprints = $formconfirm;

					return 1;
				}
			}
		}
		if (in_array('invoicecard', $contextArray)) {
			if ($object->type == Facture::TYPE_CREDIT_NOTE && $action=="valid") {
				$object->fetchObjectLinked();
				if (is_array($object->linkedObjectsIds) && !empty($object->linkedObjectsIds)) {
					if (isset($object->linkedObjectsIds['operationorder']) && count($object->linkedObjectsIds['operationorder'])>0) {
						$soc = new Societe($this->db);
						$soc->fetch($object->socid);


						//Copy past from valid action from facture/card.php (no other way)
						// TODO: on change version check if it's the same
						$objectref = substr($object->ref, 1, 4);
						if ($objectref == 'PROV') {
							$savdate = $object->date;
							if (!empty(getDolGlobalString("FAC_FORCE_DATE_VALIDATION"))) {
								$object->date = dol_now();
								$object->date_lim_reglement = $object->calculate_date_lim_reglement();
							}
							$numref = $object->getNextNumRef($soc);
						} else {
							$numref = $object->ref;
						}

						$text = $langs->trans('ConfirmValidateBill', $numref);
						if (isModEnabled('notification')) {
							require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
							$notify = new Notify($this->db);
							$text .= '<br>';
							$text .= $notify->confirmMessage('BILL_VALIDATE', $object->socid, $object);
						}
						$formquestion = array();

						if ($object->type != Facture::TYPE_DEPOSIT && !empty(getDolGlobalString("STOCK_CALCULATE_ON_BILL"))) {
							$qualified_for_stock_change = 0;
							if (empty(getDolGlobalString("STOCK_SUPPORTS_SERVICES"))) {
								$qualified_for_stock_change = $object->hasProductsOrServices(2);
							} else {
								$qualified_for_stock_change = $object->hasProductsOrServices(1);
							}

							if ($qualified_for_stock_change) {
								$langs->load("stocks");
								require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
								require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
								$formproduct = new FormProduct($this->db);
								$warehouse = new Entrepot($this->db);
								$warehouse_array = $warehouse->list_array();
								if (count($warehouse_array) == 1) {
									$label = $object->type == Facture::TYPE_CREDIT_NOTE ? $langs->trans("WarehouseForStockIncrease", current($warehouse_array)) : $langs->trans("WarehouseForStockDecrease", current($warehouse_array));
									$value = '<input type="hidden" id="idwarehouse" name="idwarehouse" value="'.key($warehouse_array).'">';
								} else {
									$label = $object->type == Facture::TYPE_CREDIT_NOTE ? $langs->trans("SelectWarehouseForStockIncrease") : $langs->trans("SelectWarehouseForStockDecrease");
									$value = $formproduct->selectWarehouses(GETPOST('idwarehouse') ?GETPOST('idwarehouse') : 'ifone', 'idwarehouse', '', 1);
								}
								$formquestion = array(
									// 'text' => $langs->trans("ConfirmClone"),
									// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' =>
									// 1),
									// array('type' => 'checkbox', 'name' => 'update_prices', 'label' => $langs->trans("PuttingPricesUpToDate"), 'value'
									// => 1),
									array('type' => 'other', 'name' => 'idwarehouse', 'label' => $label, 'value' => $value));
							}
						}
						if ($object->type != Facture::TYPE_CREDIT_NOTE && $object->total_ttc < 0) { 		// Can happen only if getDolGlobalString("FACTURE_ENABLE_NEGATIVE")  is on
							$text .= '<br>'.img_warning().' '.$langs->trans("ErrorInvoiceOfThisTypeMustBePositive");
						}

						// mandatoryPeriod
						$nbMandated = 0;
						foreach ($object->lines as $line) {
							$res = $line->fetch_product();
							if ($res  > 0  ) {
								if ($line->product->isService() && $line->product->isMandatoryPeriod() && (empty($line->date_start) || empty($line->date_end) )) {
									$nbMandated++;
									break;
								}
							}
						}
						if ($nbMandated > 0 ) $text .= '<div><span class="clearboth nowraponall warning">'.$langs->trans("mandatoryPeriodNeedTobeSetMsgValidate").'</span></div>';

						$formquestion[] = ['type' => 'checkbox', 'name' => 'free_or_confirm', 'label' => $langs->trans('FreeORConfirm'), 'value' => true];

						$form = new Form($this->db);
						$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?facid='.$object->id, $langs->trans('ValidateBill'), $text, 'confirm_valid', $formquestion, (($object->type != Facture::TYPE_CREDIT_NOTE && $object->total_ttc < 0) ? "no" : "yes"), 2);

						$this->resprints = $formconfirm;

						return 1;
					}
				}
			}
		}
		return 0;
	}

	/**
	 * Overloading the loadvirtualstock function : replacing the parent's function with the one below
	 *
	 * @param array         $parameters     Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadvirtualstock($parameters, &$object, &$action, $hookmanager)
	{
		//On écrase le stock virtuel
		if (in_array('productdao', explode(':', $parameters['context']))) {
			dol_include_once('/operationorder/class/operationorder.class.php');
			$ooStatus = new operationorderLine($this->db);
			$ooStatus->product = $object;
			$object->stock_theorique -= $ooStatus->loadOperationOrderQty();
		}
	}

	/**
	 * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
	 *
	 * @param array         $parameters     Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs,$user;

		if (in_array('stockproductcard', explode(':', $parameters['context']))) {
			global $langs, $conf, $db, $user;
			dol_include_once('/operationorder/class/operationorder.class.php');
			$langs->load('operationorder@operationorder');
			$ooStatus = new operationorderLine($this->db);
			$ooStatus->product = $object;
			?>
			<script type="text/javascript">
				$(document).ready(function () {
					let tdVirtual = $('td:contains("<?php echo $langs->trans('VirtualStock'); ?>")').next();
					let content = tdVirtual.find('.classfortooltip').attr('title') + "<br/>" + "<?php echo $langs->trans('ProductQtyInOperationOrder') . ' : ' . $ooStatus->loadOperationOrderQty(); ?>";
					tdVirtual.find('.classfortooltip').attr('title', content);
				});
			</script>
			<?php
		}
		if (in_array('propalcard', explode(':', $parameters['context']))) {
			if ($object->status == 2) {
				$actionUrl = $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=createor';
				print dolGetButtonAction($langs->trans("createor"), '', 'default', $actionUrl, '', $user->hasRight('operationorder', 'write'));
			}
			//$this->resprint = 1;
		}
		if (in_array('invoicecard', explode(':', $parameters['context']))) {
			if ($object->type == Facture::TYPE_STANDARD && $object->statut > Facture::STATUS_DRAFT) {
				?>
			<script type="text/javascript">
				$(document).ready(function () {
					let currenthref=$("a[href^='/compta/facture/card.php?']"+
						"[href*='&action=create']" +
						"[href*='&type=2']" +
						"[href*='&fac_avoir=']").attr("href");
					$("a[href^='/compta/facture/card.php?']"+
						"[href*='&action=create']" +
						"[href*='&type=2']" +
						"[href*='&fac_avoir=']").attr("href",currenthref+"&ref_client=<?php echo $object->ref_client; ?>");
				});
			</script>
				<?php
			}
		}
		return 0;
	}

	/**
	 * Overloading the moreHtmlRef function : replacing the parent's function with the one below
	 *
	 * @param array         $parameters     Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function llxFooter($parameters, &$object, &$action)
	{
		if ($this->shouldDisplayStockTableInOtherEntity($parameters, $object, $action)) {
			return $this->displayStockTableInOtherEntity($object);
		}

		return 0;
	}

	public function shouldDisplayStockTableInOtherEntity($parameters, &$object, &$action)
	{
		if ($action !== '') {
			return false;
		}

		if (!in_array('stockproductcard', explode(':', $parameters['context']))) {
			return false;
		}

		if ($object->id <= 0) {
			return false;
		}

		if (intval($object->type) === Product::TYPE_SERVICE) {
			return false;
		}

		return true;
	}

	public function displayStockTableInOtherEntity($object)
	{
		global $conf, $langs, $form, $db, $usercancreadprice;

		$sql = "SELECT e.rowid, e.entity, e.ref, e.lieu, e.fk_parent, e.statut as status, ps.reel, ps.rowid as product_stock_id, p.pmp";
		$sql .= " FROM " . $db->prefix() . "entrepot as e,";
		$sql .= " " . $db->prefix() . "product_stock as ps";
		$sql .= " LEFT JOIN " . $db->prefix() . "product as p ON p.rowid = ps.fk_product";
		$sql .= " WHERE ps.reel != 0";
		$sql .= " AND ps.fk_entrepot = e.rowid";
		$sql .= " AND e.entity NOT IN (" . intval($conf->entity) . ")";
		$sql .= " AND ps.fk_product = " . ((int) $object->id);
		$sql .= " ORDER BY e.ref";

		$totalvalue = $totalvaluesell = 0;
		$totalwithpmp = 0;

		$resql = $db->query($sql);
		if (!$resql) {
			dol_print_error($db);
		}
		$num = intval($db->num_rows($resql));

		if ($num === 0) {
			return 0;
		}

		/*
		 * Stock detail (by warehouse). May go down into batch details.
		 */
		$this->resprints .= load_fiche_titre($langs->trans('StockInOtherEntity'), '', 'entity');

		$this->resprints .= '<div class="div-table-responsive">';
		$this->resprints .= '<table class="noborder centpercent">';

		$this->resprints .= '<tr class="liste_titre">';
		$this->resprints .= '<td>' . $langs->trans("Entity") . '</td>';
		$this->resprints .= '<td colspan="4">' . $langs->trans("Warehouse") . '</td>';
		$this->resprints .= '<td class="right">' . $langs->trans("NumberOfUnit") . '</td>';
		$this->resprints .= '<td class="right">' . $form->textwithpicto(
				$langs->trans("AverageUnitPricePMPShort"), $langs->trans("AverageUnitPricePMPDesc")) . '</td>';
		$this->resprints .= '<td class="right">' . $langs->trans("EstimatedStockValueShort") . '</td>';
		$this->resprints .= '<td class="right">' . $langs->trans("SellPriceMin") . '</td>';
		$this->resprints .= '<td class="right">' . $langs->trans("EstimatedStockValueSellShort") . '</td>';
		$this->resprints .= '<td></td>';
		$this->resprints .= '<td></td>';
		$this->resprints .= '</tr>';

		$total = $totalwithpmp;
		$i = 0;
		while ($i < $num) {
			$i++;

			$obj = $db->fetch_object($resql);
			$this->printLineStockByEntity(
				$object,
				$obj,
				$usercancreadprice,
				$total,
				$totalwithpmp,
				$totalvaluesell,
				$totalvalue
			);
		}

		// Total line
		$this->resprints .= '<tr class="liste_total"><td class="left liste_total" colspan="4">' . $langs->trans("Total") . ':</td><td></td>';
		$this->resprints .= '<td class="liste_total right">' . price2num($total, 'MS') . '</td>';
		$this->resprints .= '<td class="liste_total right">';

		if ($usercancreadprice) {
			$this->resprints .= ($totalwithpmp ? price(price2num($totalvalue / $totalwithpmp, 'MU')) : '&nbsp;'); // This value may have rounding errors
		}

		$this->resprints .= '</td>';
		// Value purchase
		$this->resprints .= '<td class="liste_total right">';

		if ($usercancreadprice) {
			$this->resprints .= $totalvalue ? price(price2num($totalvalue, 'MT'), 1) : '&nbsp;';
		}

		$this->resprints .= '</td>';
		$this->resprints .= '<td class="liste_total right">';

		if ($total) {
			$this->resprints .= '<span class="valignmiddle">';
			if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
				$this->resprints .= $form->textwithpicto('', $langs->trans("Variable"));
			} elseif ($usercancreadprice) {
				$this->resprints .= price($totalvaluesell / $total, 1);
			}
			$this->resprints .= '</span>';
		}

		$this->resprints .= '</td>';
		// Value to sell
		$this->resprints .= '<td class="liste_total right amount">';
		$this->resprints .= '<span class="valignmiddle">';
		if (!getDolGlobalString('PRODUIT_MULTIPRICES') && $usercancreadprice) {
			$this->resprints .= price(price2num($totalvaluesell, 'MT'), 1);
		} else {
			$this->resprints .= $form->textwithpicto('', $langs->trans("Variable"));
		}
		$this->resprints .= '</span>';
		$this->resprints .= '</td>';
		$this->resprints .= '<td></td>';
		$this->resprints .= '<td></td>';
		$this->resprints .= "</tr>";

		$this->resprints .= "</table>";
		$this->resprints .= '</div>';

		return 0;
	}

	public function printLineStockByEntity($object, $obj, $usercancreadprice, &$total, &$totalwithpmp, &$totalvaluesell, &$totalvalue)
	{
		global $db, $conf, $form, $langs;

		$entrepotstatic = new Entrepot($db);
		$entrepotstatic->id = $obj->rowid;
		$entrepotstatic->ref = $obj->ref;
		$entrepotstatic->label = $obj->ref;
		$entrepotstatic->lieu = $obj->lieu;
		$entrepotstatic->fk_parent = $obj->fk_parent;
		$entrepotstatic->statut = $obj->status;
		$entrepotstatic->status = $obj->status;

		$entity = new ActionsMulticompany($db);
		$entity->getInfo($obj->entity);
		$entitypicto = '<div class="refidno multicompany-entity-card-container">';
		$entitypicto .= '<span class="fa fa-globe"></span><span class="multiselect-selected-title-text">' . $entity->label . '</span>';
		$entitypicto .= '</div>';
		$stock_real = price2num($obj->reel, 'MS');
		$this->resprints .= '<tr class="oddeven">';

		// Warehouse
		$this->resprints .= '<td>' . $entitypicto . '</td>';
		$this->resprints .= '<td colspan="4">';
		$this->resprints .= $entrepotstatic->getNomUrl(1);

		if (!empty($conf->use_javascript_ajax) && isModEnabled('productbatch') && $object->hasbatch()) {
			$this->resprints .= '<a class="collapse_batch marginleftonly" id="ent' . $entrepotstatic->id . '" href="#">';
			$this->resprints .= (!getDolGlobalString('STOCK_SHOW_ALL_BATCH_BY_DEFAULT') ? '(+)' : '(-)');
			$this->resprints .= '</a>';
		}

		$this->resprints .= '</td>';

		$this->resprints .= '<td class="right">' . $stock_real . ($stock_real < 0 ? ' ' . img_warning() : '') . '</td>';

		// PMP
		$pricepmp = price2num($object->pmp) ? price2num($object->pmp, 'MU') : '';
		$this->resprints .= '<td class="right nowraponall">' . $pricepmp . '</td>';

		// Value purchase
		if ($usercancreadprice) {
			$pricepmpreal = price2num($object->pmp) ? price(price2num($object->pmp * $obj->reel, 'MT')) : '';
			$this->resprints .= '<td class="right amount nowraponall">' . $pricepmpreal . '</td>';
		} else {
			$this->resprints .= '<td class="right amount nowraponall"></td>';
		}

		// Sell price
		$minsellprice = null;
		$maxsellprice = null;
		$this->resprints .= '<td class="right nowraponall">';

		if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
			foreach ($object->multiprices as $priceforlevel) {
				if (is_numeric($priceforlevel)) {
					if (is_null($maxsellprice) || $priceforlevel > $maxsellprice) {
						$maxsellprice = $priceforlevel;
					}
					if (is_null($minsellprice) || $priceforlevel < $minsellprice) {
						$minsellprice = $priceforlevel;
					}
				}
			}
			$this->resprints .= '<span class="valignmiddle">';
			if ($usercancreadprice) {
				if ($minsellprice != $maxsellprice) {
					$this->resprints .= price(price2num($minsellprice, 'MU'), 1);
					$this->resprints .= ' - ' . price(price2num($maxsellprice, 'MU'), 1);
				} else {
					$this->resprints .= price(price2num($minsellprice, 'MU'), 1);
				}
			}
			$this->resprints .= '</span>';
			$this->resprints .= $form->textwithpicto('', $langs->trans("Variable"));
		} elseif ($usercancreadprice) {
			$this->resprints .= price(price2num($object->price, 'MU'), 1);
		}

		$this->resprints .= '</td>';

		// Value sell
		$this->resprints .= '<td class="right amount nowraponall">';

		if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
			$this->resprints .= '<span class="valignmiddle">';
			if ($usercancreadprice) {
				if ($minsellprice != $maxsellprice) {
					$this->resprints .= price(price2num($minsellprice * $obj->reel, 'MT'), 1);
					$this->resprints .= ' - ' . price(price2num($maxsellprice * $obj->reel, 'MT'), 1);
				} else {
					$this->resprints .= price(price2num($minsellprice * $obj->reel, 'MT'), 1);
				}
			}
			$this->resprints .= '</span>';
			$this->resprints .= $form->textwithpicto('', $langs->trans("Variable"));
		} else {
			if ($usercancreadprice) {
				$this->resprints .= price(price2num($object->price * $obj->reel, 'MT'), 1);
			}
		}

		$this->resprints .= '</td>';
		$this->resprints .= '<td></td>';
		$this->resprints .= '<td></td>';
		$this->resprints .= '</tr>';
		$total += $obj->reel;

		if (price2num($object->pmp)) {
			$totalwithpmp += $obj->reel;
		}

		$totalvalue += ($object->pmp * $obj->reel);
		$totalvaluesell += ($object->price * $obj->reel);
	}

	/**
	 * Overloading the addSearchEntry function : replacing the parent's function with the one below
	 *
	 * @param array         $parameters     Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addSearchEntry($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user, $db;
		$langs->load('operationorder@operationorder');

		dol_include_once('/operationorder/core/modules/modOperationOrder.class.php');
		$modOperationOrder = new modOperationOrder($db);

		if (empty(getDolGlobalString("OR_HIDE_QUICK_SEARCH")) && $user->hasRight("operationorder", "read")) {
			$str_search_driver = '&Listview_operationorder_search_ref=' . urlencode($parameters['search_boxvalue']);

			$arrayresult['searchintor'] = array(
				'position' => $modOperationOrder->numero,
				'text' => img_object('', 'operationorder@operationorder') . ' ' . $langs->trans('OR'),
				'url' => dol_buildpath('/operationorder/list.php', 1) . '?search_by=Listview_operationorder_search_ref' . $str_search_driver
			);
		}

		$this->results = $arrayresult;

		return 0;
	}

	/**
	 * Overloading the addMoreProductStat function : replacing the parent's function with the one below
	 *
	 * @param array         $parameters     Hook metadatas (context, etc...)
	 * @param Product $product Product
	 * @param int $nblines nb lines
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreProductStat($parameters, &$product, &$nblines)
	{
		global $conf, $user, $langs;
		$langs->load('operationorder@operationorder');

		if (!empty(isModEnabled("operationorder")) && $user->hasRight("operationorder", "read")) {
			dol_include_once('/operationorder/class/operationorder.class.php');
			$OR = new OperationOrder($this->db);
			$OR->load_stats_operationorder($parameters['socid'], $product->id);
			$url = dol_buildpath('/operationorder/stats/operationorder.php', 3);
			$product->stats_or = $OR->stats_or;
			$nblines++;
			$this->resprints = '<tr><td>';
			$this->resprints .= '<a href="' . $url . '?id=' . $product->id . '">' . img_object('', 'operationorder@operationorder', 'class="paddingright"') . $langs->trans("operationorder") . '</a>';
			$this->resprints .= '</td><td class="right">';
			$this->resprints .= $product->stats_or['customers'];
			$this->resprints .= '</td><td class="right">';
			$this->resprints .= $product->stats_or['nb'];
			$this->resprints .= '</td><td class="right">';
			$this->resprints .= $product->stats_or['qty'];
			$this->resprints .= '</td>';
			$this->resprints .= '</tr>';
		}
		return 1;
	}

	/**
	 * Overloading the showLinkToObjectBlock function : replacing the parent's function with the one below
	 *
	 * @param array         $parameters     Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function showLinkToObjectBlock($parameters, &$object, &$action, $hookmanager)
	{

		$context = explode(':', $parameters['context']);

		if (in_array('ordercard', $context)
			|| in_array('invoicecard', $context)) {
			foreach ($parameters['possiblelinks'] as $objType=>$data) {
				$this->results[$objType]=array('enabled'=>false);
			}
			print '
					<script type="text/javascript">
						 $(document).ready(function() {
							$("a[href*=\'action=dellink\'][href*=\'dellinkid=\'].reposition").hide();
						 });
					 </script>';
			return 1;
		}
	}

	/**
	 * Overloading the selectWarehouses function : replacing the parent's function with the one below
	 *
	 * @param array         $parameters     Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function selectWarehouses($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$contextArray = explode(':', $parameters['context']);

		$context = ['productcard', 'operationordercard', 'ordersupplierdispatch', 'receptioncard'];
		if (!array_intersect($context, $contextArray)) {
			return 0;
		}

		if (in_array('productcard', $contextArray)) {
			$fk_product = GETPOSTISSET('id') ? GETPOSTINT('id'): 0;
		} else {
			if (empty($parameters['fk_product'])) {
				return 0;
			}
			$fk_product = $parameters['fk_product'];
		}


		$dProductWarehouse = new ProductDefaultWarehouse($this->db);

		if ($fk_product > 0) {
			$defaultWarehouse = $dProductWarehouse->fetch($fk_product, $conf->entity);
			if ($defaultWarehouse < 0) {
				$this->error = $dProductWarehouse->error;
				$this->errors += $dProductWarehouse->errors;
				return -1;
			}
		}

		$object->cache_warehouses = [];

		$retLoad = $object->loadWarehouses(
			$fk_product, '', $parameters['filterstatus'], true, $parameters['exclude'], $parameters['stockMin'],
			'ps.reel DESC'
		);

		if ($retLoad < 0) {
			$this->error = $object->error;
			$this->errors += $object->errors;
			return -1;
		}

		$nbofwarehouses = count($object->cache_warehouses);
		$out = '';
		if ($conf->use_javascript_ajax && !$parameters['forcecombo']) {
			include_once DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php';
			$comboenhancement = ajax_combobox($parameters['htmlname'], $parameters['events']);
			$out .= $comboenhancement;
		}

		$selected = -1;
		if (in_array('operationordercard', $contextArray) && GETPOSTISSET('lineid')) {
			$operationOrderLine = new operationorderLine($this->db);
			$retFetch = $operationOrderLine->fetch(GETPOST('lineid'));
			if ($retFetch < 0) {
				$this->error = $operationOrderLine->error;
				$this->errors += $operationOrderLine->errors;
				return -1;
			}
			$selected = $operationOrderLine->fk_warehouse > 0 ? $operationOrderLine->fk_warehouse: -1;
		}
		if ($selected == -1 && strpos($parameters['htmlname'], 'search_') !== 0) {
			if (!empty($dProductWarehouse->fk_default_warehouse)) {
				$selected = $dProductWarehouse->fk_default_warehouse;
			} else {
				$selected = $dProductWarehouse->getDefaultWarehouseForEntity($conf->entity);
				if ($selected < 0) {
					$this->error = $dProductWarehouse->error;
					return -1;
				} elseif ($selected == 0) {
					$selected = -1;
				}
			}
		}

		if (array_key_exists($dProductWarehouse->fk_default_warehouse, $object->cache_warehouses)) {
			$value = $object->cache_warehouses[$dProductWarehouse->fk_default_warehouse];
			unset($object->cache_warehouses[$dProductWarehouse->fk_default_warehouse]);
			$object->cache_warehouses = [$dProductWarehouse->fk_default_warehouse => $value] + $object->cache_warehouses;
		}

		$showstock = 1;
		$out .= '<!-- selectWarehouses -->';
		$out .= '<select class="flat' . ($parameters['morecss'] ? ' ' . $parameters['morecss'] : '') . '"' . ($parameters['disabled'] ? ' disabled' : '');
		$out .= ' id="' . $parameters['htmlname'] . '" name="' . ($parameters['htmlname'] . ($parameters['disabled'] ? '_disabled'  : '')) . '"';
		//$out .= ' placeholder="todo"'; 	// placeholder for select2 must be added by setting the id+placeholder js param when calling select2
		$out .= '>';
		if ($parameters['empty']) {
			$out .= '<option value="-1">' . ($parameters['empty_label'] ? $parameters['empty_label'] : '&nbsp;') . '</option>';
		}
		foreach ($object->cache_warehouses as $id => $arraytypes) {
			$label = '';
			if ($parameters['showfullpath']) {
				$label .= $arraytypes['full_label'];
			} else {
				$label .= $arraytypes['label'];
			}
			if ($fk_product && $showstock > 0 && ($arraytypes['stock'] != 0 || ($showstock > 0))) {
				if ($arraytypes['stock'] <= 0) {
					$label .= ' <span class="text-warning">(' . $langs->trans("Stock") . ':' . $arraytypes['stock'] . ')</span>';
				} else {
					$label .= ' <span class="opacitymedium">(' . $langs->trans("Stock") . ':' . $arraytypes['stock'] . ')</span>';
				}
			}

			if ($id == $dProductWarehouse->fk_default_warehouse) {
				$label .= ' <span class="opacitymedium">' . $langs->trans("DefaultWarehouseChosen") . '</span>';
			}


			$out .= '<option value="' . $id . '"';
			if (is_array($selected)) {
				if (in_array($id, $selected)) {
					$out .= ' selected';
				}
			} else {
				if ($selected == $id || (!empty($selected) && preg_match('/^ifone/', $selected) && $nbofwarehouses == 1)) {
					$out .= ' selected';
				}
			}
			$out .= ' data-html="' . dol_escape_htmltag($label) . '"';
			$out .= '>';
			$out .= $label;
			$out .= '</option>';
		}

		$out .= '</select>';
		if ($parameters['disabled']) {
			$out .= '<input type="hidden" name="' . $parameters['htmlname'] . '" value="' . (($selected > 0) ? $selected : '') . '">';
		}

		if (in_array('receptioncard', $contextArray)) {
			$out .= '</br><span>' . $langs->trans('SaveDefaultWarehouse') . '</span>&nbsp;';
			$out .= '<input type="checkbox" name="select_default_warehouse_' . $parameters['htmlname'] . '" checked="1">';
		}

		$this->resprints = $out;
		return 1;
	}

	/**
	 * @param array $parameters parameters
	 * @param Object $object Object to use hooks on
	 * @param string $action Action code on calling page ('create', 'edit', 'view', 'add', 'update', 'delete'...)
	 * @param object $hookmanager class instance
	 * @return int
	 **/
	public function addmoduletoeamailcollectorjoinpiece($parameters, $object, &$action, $hookmanager)
	{
		$arrayobject = array();
		$arrayobject = $parameters['arrayobject'];
		$arrayobject['operationorder'] =  array('table' => 'operationorder','fields' => array('ref'),'class' => '/operationorder/class/operationorder.class.php','object' => 'OperationOrder');
		$this->results = $arrayobject;
		return 1;
	}

	/**
	 * @param array $parameters parameters
	 * @param Object $object Object to use hooks on
	 * @param string $action Action code on calling page ('create', 'edit', 'view', 'add', 'update', 'delete'...)
	 * @param object $hookmanager class instance
	 * @return int
	 **/
	public function printPageView($parameters, $object, &$action, $hookmanager)
	{
		global $langs, $conf;
		$langs->load('operationorder@operationorder');

		if (
			isset($parameters['controller'])
			&& isset($parameters['currentcontext'])
			&& $parameters['currentcontext']=='webportalpage'
		) {
			print '
				<script type="text/javascript">
					$(document).ready(function() {
						let divicon = $("<div>");
						divicon.addClass("home-links-card__icon");

						let article1 = $("<article>");
						article1.addClass("home-links-card");
						article1.addClass("--operationorder-list");
						article1.append(divicon);
						let link_article1 = $("<a>");
						link_article1.addClass("home-links-card__link");
						link_article1.attr("href","' . $object->getControllerUrl('operationorderlist') . '");
						link_article1.attr("title","' . $langs->trans('WebPortalOperationOrderListMenu') . '");
						link_article1.html("' . $langs->trans('WebPortalOperationOrderListMenu') . '");
						article1.append(link_article1);
						$("div.home-links-grid.grid").append(article1);

						let article2 = $("<article>");
						article2.addClass("home-links-card");
						article2.addClass("--operationorder-list");
						article2.append(divicon);
						let link_article2 = $("<a>");
						link_article2.addClass("home-links-card__link");
						link_article2.attr("href","' . $object->getControllerUrl('operationorderform') . '");
						link_article2.attr("title","' . $langs->trans('WebPortalOperationOrderFormMenu') . '");
						link_article2.html("' . $langs->trans('WebPortalOperationOrderFormMenu') . '");
						article2.append(link_article2);
						$("div.home-links-grid.grid").append(article2);

						let article3 = $("<article>");
						article3.addClass("home-links-card");
						article3.addClass("--driver-list");
						article3.append(divicon);
						let link_article3 = $("<a>");
						link_article3.addClass("home-links-card__link");
						link_article3.attr("href","' . $object->getControllerUrl('driverlist') . '");
						link_article3.attr("title","' . $langs->trans('WebPortalDriverFormMenu') . '");
						link_article3.html("' . $langs->trans('WebPortalDriverFormMenu') . '");
						article3.append(link_article3);
						$("div.home-links-grid.grid").append(article3);

						let article4 = $("<article>");
						article4.addClass("home-links-card");
						article4.addClass("--driver-list");
						article4.append(divicon);
						let link_article4 = $("<a>");
						link_article4.addClass("home-links-card__link");
						link_article4.attr("href","' . $object->getControllerUrl('vsrlist') . '");
						link_article4.attr("title","' . $langs->trans('WebPortalVSRFormMenu') . '");
						link_article4.html("' . $langs->trans('WebPortalVSRFormMenu') . '");
						article4.append(link_article4);
						$("div.home-links-grid.grid").append(article4);

						let article5 = $("<article>");
						article5.addClass("home-links-card");
						article5.addClass("--driver-list");
						article5.append(divicon);
						let link_article5 = $("<a>");
						link_article5.addClass("home-links-card__link");
						link_article5.attr("href","' . $object->getControllerUrl('vehiculelist') . '");
						link_article5.attr("title","' . $langs->trans('WebPortalVehiculeListMenu') . '");
						link_article5.html("' . $langs->trans('WebPortalVehiculeListMenu') . '");
						article5.append(link_article5);
						$("div.home-links-grid.grid").append(article5);

						let article6 = $("<article>");
						article6.addClass("home-links-card");
						article6.addClass("--opbyvehicule-list");
						article6.append(divicon);
						let link_article6 = $("<a>");
						link_article6.addClass("home-links-card__link");
						link_article6.attr("href","' . $object->getControllerUrl('opbyvehiculelist') . '");
						link_article6.attr("title","' . $langs->trans('WebPortalOPByVehiculeListMenu') . '");
						link_article6.html("' . $langs->trans('WebPortalOPByVehiculeListMenu') . '");
						article6.append(link_article6);
						$("div.home-links-grid.grid").append(article6);

						$(".container").css("max-width", "90%");
					})
				</script>
			 ';
			return 0;
		}
	}

	/**
	 * @param array $parameters parameters
	 * @param Object $object Object to use hooks on
	 * @param string $action Action code on calling page ('create', 'edit', 'view', 'add', 'update', 'delete'...)
	 * @param object $hookmanager class instance
	 * @return int
	 **/
	public function printTopMenu($parameters, $object, &$action, $hookmanager)
	{
		global $langs;
		$langs->load('operationorder@operationorder');

		if (
			isset($parameters['controller'])
			&& isset($parameters['currentcontext'])
			&& $parameters['currentcontext']=='webportalpage'
		) {
			$this->results['operationorder_list'] = array(
				'id' => 'operationorder_list',
				'rank' => 10,
				'url' => $object->getControllerUrl('operationorderlist'),
				'name' => $langs->trans('WebPortalOperationOrderListMenu'),
				//'group' => 'administrative' // group identifier for the group if necessary
			);
			$this->results['operationorder_form'] = array(
				'id' => 'operationorder_form',
				'rank' => 11,
				'url' => $object->getControllerUrl('operationorderform'),
				'name' => $langs->trans('WebPortalOperationOrderFormMenu'),
				//'group' => 'administrative' // group identifier for the group if necessary
			);
			$this->results['driver_list'] = array(
				'id'   => 'driver_list',
				'rank' => 12,
				'url'  => $object->getControllerUrl('driverlist'),
				'name' => $langs->trans('WebPortalDriverListMenu'),
				//'group' => 'administrative' // group identifier for the group if necessary
			);
			$this->results['vsr_list'] = array(
				'id'   => 'vsr_list',
				'rank' => 13,
				'url'  => $object->getControllerUrl('vsrlist'),
				'name' => $langs->trans('WebPortalVSRFormMenu'),
				//'group' => 'administrative' // group identifier for the group if necessary
			);
			$this->results['vehicule_list'] = array(
				'id' => 'vehicule_list',
				'rank' => 14,
				'url' => $object->getControllerUrl('vehiculelist'),
				'name' => $langs->trans('WebPortalVehiculeListMenu'),
				//'group' => 'administrative' // group identifier for the group if necessary
			);
			$this->results['opbyvehicule_list'] = array(
				'id' => 'opbyvehicule_list',
				'rank' => 14,
				'url' => $object->getControllerUrl('opbyvehiculelist'),
				'name' => $langs->trans('WebPortalOPByVehiculeListMenu'),
				//'group' => 'administrative' // group identifier for the group if necessary
			);
			return 0;
		}
	}
}
