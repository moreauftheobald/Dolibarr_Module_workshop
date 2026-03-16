<?php
/* Copyright (C) 2024 SuperAdmin <test@cest.com>
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
 * \file    core/triggers/interface_99_modCatalogueFournisseurExterne_CatalogueFournisseurExterneTriggers.class.php
 * \ingroup cataloguefournisseurexterne
 * \brief   Example trigger.
 *
 * Put detailed description here.
 *
 * \remarks You can create other triggers by copying this one.
 * - File name should be either:
 *      - interface_99_modCatalogueFournisseurExterne_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMytrigger
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/operationorder/class/productdefaultwarehouse.class.php');
dol_include_once('/multicompany/class/dao_multicompany.class.php');

/**
 *  Class of triggers for CatalogueFournisseurExterne module
 */
class InterfaceSaveProductDefaultWarehouse extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "product";
		$this->description = "SaveProductDefaultWarehouse triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.0';
		$this->picto = 'operationorder@operationorder';
		$this->errors = [];
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		global $conf;
		if (!isModEnabled('operationorder')) {
			return 0; // If module is not enabled, we do nothing
		}

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id, LOG_DEBUG
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		}

		// Or you can execute some code here
		switch ($action) {
			// Users
			//case 'USER_CREATE':
			//case 'USER_MODIFY':
			//case 'USER_NEW_PASSWORD':
			//case 'USER_ENABLEDISABLE':
			//case 'USER_DELETE':

			// Actions
			//case 'ACTION_MODIFY':
			//case 'ACTION_CREATE':
			//case 'ACTION_DELETE':

			// Groups
			//case 'USERGROUP_CREATE':
			//case 'USERGROUP_MODIFY':
			//case 'USERGROUP_DELETE':

			// Companies
			//case 'COMPANY_CREATE':
			//case 'COMPANY_MODIFY':
			//case 'COMPANY_DELETE':

			// Contacts
			//case 'CONTACT_CREATE':
			//case 'CONTACT_MODIFY':
			//case 'CONTACT_DELETE':
			//case 'CONTACT_ENABLEDISABLE':

			// Products
			case 'PRODUCT_DELETE':
				$retDelete = $this->autoDeleteProductDefaultWarehouseForAllEntitiesIfExists($object->id);
				if ($retDelete < 0) {
					return $retDelete;
				}
				break;
			case 'PRODUCT_CREATE':
			case 'PRODUCT_MODIFY':
				if (empty($object->type)) {
					$dProductWarehouse = new ProductDefaultWarehouse($this->db);
					$defaultWarehouse = $dProductWarehouse->fetch($object->id, $conf->entity);
					$defaultWarehouseForEntity = $dProductWarehouse->getDefaultWarehouseForEntity($conf->entity);

					$fk_warehouse = 0;
					if (intval(GETPOST('fk_default_warehouse', 'int')) > 0) {
						$fk_warehouse = intval(GETPOST('fk_default_warehouse', 'int'));
					} elseif ($defaultWarehouse > 0) {
						$fk_warehouse = intval($defaultWarehouse);
					} elseif ($defaultWarehouseForEntity > 0) {
						$fk_warehouse = intval($defaultWarehouseForEntity);
					}

					if (empty($fk_warehouse)) {
						$this->error = $langs->trans('WarehouseIsMandatory');
						$this->errors[] = $langs->trans('WarehouseIsMandatory');
						return -1;
					}

					$retCreate = $this->autoCreateOrUpdateProductDefaultWarehouseForAllEntities($object->id, $fk_warehouse);

					if ($retCreate < 0) {
						return $retCreate;
					}
				}
				break;
			//case 'PRODUCT_PRICE_MODIFY':
			//case 'PRODUCT_SET_MULTILANGS':
			//case 'PRODUCT_DEL_MULTILANGS':

			//Stock mouvement
			//case 'STOCK_MOVEMENT':

			//MYECMDIR
			//case 'MYECMDIR_CREATE':
			//case 'MYECMDIR_MODIFY':
			//case 'MYECMDIR_DELETE':

			// Sales orders
			//case 'ORDER_CREATE':
			//case 'ORDER_MODIFY':
			//case 'ORDER_VALIDATE':
			//case 'ORDER_DELETE':
			//case 'ORDER_CANCEL':
			//case 'ORDER_SENTBYMAIL':
			//case 'ORDER_CLASSIFY_BILLED':
			//case 'ORDER_SETDRAFT':
			//case 'LINEORDER_INSERT':
			//case 'LINEORDER_UPDATE':
			//case 'LINEORDER_DELETE':

			// Supplier orders
			//case 'ORDER_SUPPLIER_CREATE':
			//case 'ORDER_SUPPLIER_MODIFY':
			//case 'ORDER_SUPPLIER_VALIDATE':
			//case 'ORDER_SUPPLIER_DELETE':
			//case 'ORDER_SUPPLIER_APPROVE':
			//case 'ORDER_SUPPLIER_REFUSE':
			//case 'ORDER_SUPPLIER_CANCEL':

			//case 'ORDER_SUPPLIER_RECEIVE':
			//case 'LINEORDER_SUPPLIER_DISPATCH':
			//case 'LINEORDER_SUPPLIER_CREATE':
			//case 'LINEORDER_SUPPLIER_UPDATE':
			//case 'LINEORDER_SUPPLIER_DELETE':

			// Proposals
			//case 'PROPAL_CREATE':
			//case 'PROPAL_MODIFY':
			//case 'PROPAL_VALIDATE':
			//case 'PROPAL_SENTBYMAIL':
			//case 'PROPAL_CLOSE_SIGNED':
			//case 'PROPAL_CLOSE_REFUSED':
			//case 'PROPAL_DELETE':
			//case 'LINEPROPAL_INSERT':
			//case 'LINEPROPAL_UPDATE':
			//case 'LINEPROPAL_DELETE':

			// SupplierProposal
			//case 'SUPPLIER_PROPOSAL_CREATE':
			//case 'SUPPLIER_PROPOSAL_MODIFY':
			//case 'SUPPLIER_PROPOSAL_VALIDATE':
			//case 'SUPPLIER_PROPOSAL_SENTBYMAIL':
			//case 'SUPPLIER_PROPOSAL_CLOSE_SIGNED':
			//case 'SUPPLIER_PROPOSAL_CLOSE_REFUSED':
			//case 'SUPPLIER_PROPOSAL_DELETE':
			//case 'LINESUPPLIER_PROPOSAL_INSERT':
			//case 'LINESUPPLIER_PROPOSAL_UPDATE':
			//case 'LINESUPPLIER_PROPOSAL_DELETE':

			// Contracts
			//case 'CONTRACT_CREATE':
			//case 'CONTRACT_MODIFY':
			//case 'CONTRACT_ACTIVATE':
			//case 'CONTRACT_CANCEL':
			//case 'CONTRACT_CLOSE':
			//case 'CONTRACT_DELETE':
			//case 'LINECONTRACT_INSERT':
			//case 'LINECONTRACT_UPDATE':
			//case 'LINECONTRACT_DELETE':

			// Bills
			//case 'BILL_CREATE':
			//case 'BILL_MODIFY':
			//case 'BILL_VALIDATE':
			//case 'BILL_UNVALIDATE':
			//case 'BILL_SENTBYMAIL':
			//case 'BILL_CANCEL':
			//case 'BILL_DELETE':
			//case 'BILL_PAYED':
			//case 'LINEBILL_INSERT':
			//case 'LINEBILL_UPDATE':
			//case 'LINEBILL_DELETE':

			//Supplier Bill
			//case 'BILL_SUPPLIER_CREATE':
			//case 'BILL_SUPPLIER_UPDATE':
			//case 'BILL_SUPPLIER_DELETE':
			//case 'BILL_SUPPLIER_PAYED':
			//case 'BILL_SUPPLIER_UNPAYED':
			//case 'BILL_SUPPLIER_VALIDATE':
			//case 'BILL_SUPPLIER_UNVALIDATE':
			//case 'LINEBILL_SUPPLIER_CREATE':
			//case 'LINEBILL_SUPPLIER_UPDATE':
			//case 'LINEBILL_SUPPLIER_DELETE':

			// Payments
			//case 'PAYMENT_CUSTOMER_CREATE':
			//case 'PAYMENT_SUPPLIER_CREATE':
			//case 'PAYMENT_ADD_TO_BANK':
			//case 'PAYMENT_DELETE':

			// Online
			//case 'PAYMENT_PAYBOX_OK':
			//case 'PAYMENT_PAYPAL_OK':
			//case 'PAYMENT_STRIPE_OK':

			// Donation
			//case 'DON_CREATE':
			//case 'DON_UPDATE':
			//case 'DON_DELETE':

			// Interventions
			//case 'FICHINTER_CREATE':
			//case 'FICHINTER_MODIFY':
			//case 'FICHINTER_VALIDATE':
			//case 'FICHINTER_DELETE':
			//case 'LINEFICHINTER_CREATE':
			//case 'LINEFICHINTER_UPDATE':
			//case 'LINEFICHINTER_DELETE':

			// Members
			//case 'MEMBER_CREATE':
			//case 'MEMBER_VALIDATE':
			//case 'MEMBER_SUBSCRIPTION':
			//case 'MEMBER_MODIFY':
			//case 'MEMBER_NEW_PASSWORD':
			//case 'MEMBER_RESILIATE':
			//case 'MEMBER_DELETE':

			// Categories
			//case 'CATEGORY_CREATE':
			//case 'CATEGORY_MODIFY':
			//case 'CATEGORY_DELETE':
			//case 'CATEGORY_SET_MULTILANGS':

			// Projects
			//case 'PROJECT_CREATE':
			//case 'PROJECT_MODIFY':
			//case 'PROJECT_DELETE':

			// Project tasks
			//case 'TASK_CREATE':
			//case 'TASK_MODIFY':
			//case 'TASK_DELETE':

			// Task time spent
			//case 'TASK_TIMESPENT_CREATE':
			//case 'TASK_TIMESPENT_MODIFY':
			//case 'TASK_TIMESPENT_DELETE':
			//case 'PROJECT_ADD_CONTACT':
			//case 'PROJECT_DELETE_CONTACT':
			//case 'PROJECT_DELETE_RESOURCE':

			// Shipping
			//case 'SHIPPING_CREATE':
			//case 'SHIPPING_MODIFY':
			//case 'SHIPPING_VALIDATE':
			//case 'SHIPPING_SENTBYMAIL':
			//case 'SHIPPING_BILLED':
			//case 'SHIPPING_CLOSED':
			//case 'SHIPPING_REOPEN':
			//case 'SHIPPING_DELETE':
			case 'RECEPTION_CREATE':
				$retSave = $this->saveDefaultWarehouseFromReception();
				if ($retSave < 0) {
					return $retSave;
				}
				break;
			// and more...

			default:
				dol_syslog("Trigger '".$this->name."' for action '".$action."' launched by ".__FILE__.". id=".$object->id);
				break;
		}

		return 0;
	}

	/**
	 * Auto Create Or Update Product Default Warehouse For All Entities
	 * @global type $conf
	 * @global type $user
	 * @param type $fk_product to change
	 * @param type $fk_current_default_warehouse Current change to select instead default on this entities
	 * @return int
	 */
	private function autoCreateOrUpdateProductDefaultWarehouseForAllEntities($fk_product, $fk_current_default_warehouse)
	{
		global $conf, $user;
		$dao = new DaoMulticompany($this->db);
		$dao->getEntities();
		foreach ($dao->entities as $entity) {
			$dProductWarehouse = new ProductDefaultWarehouse($this->db);
			$defaultWarehouse = $dProductWarehouse->fetch($fk_product, $entity->id);
			if ($defaultWarehouse < 0) {
				$this->error = $dProductWarehouse->error;
				$this->errors += $dProductWarehouse->errors;
				return $defaultWarehouse;
			} elseif (!empty($defaultWarehouse)) {
				if ($conf->entity != $entity->id) {
					continue;
				}
				$dProductWarehouse->fk_default_warehouse = $fk_current_default_warehouse;
				$retUpdate = $dProductWarehouse->update($user);
				if ($retUpdate < 0) {
					$this->error = $dProductWarehouse->error;
					$this->errors += $dProductWarehouse->errors;
					return $retUpdate;
				}
			} else {
				$dProductWarehouse->entity = $entity->id;
				$dProductWarehouse->fk_product = $fk_product;
				if ($conf->entity == $entity->id) {
					$dProductWarehouse->fk_default_warehouse = $fk_current_default_warehouse;
				} else {
					$dProductWarehouse->fk_default_warehouse = $dProductWarehouse->getDefaultWarehouseForEntity($entity->id);
				}


				if ($dProductWarehouse->fk_default_warehouse <= 0) {
					continue;
				}

				$retCreate = $dProductWarehouse->create($user);
				if ($retCreate < 0) {
					$this->error = $dProductWarehouse->error;
					$this->errors += $dProductWarehouse->errors;
					return $retCreate;
				}
			}
		}
		return 1;
	}

	/**
	 * Auto Delete Product Default Warehouse For All Entities If Exists
	 * @global type $user
	 * @param type $fk_product to delete
	 * @return type
	 */
	private function autoDeleteProductDefaultWarehouseForAllEntitiesIfExists($fk_product)
	{
		global $user;
		$dao = new DaoMulticompany($this->db);
		$dao->getEntities();
		foreach ($dao->entities as $entity) {
			$dProductWarehouse = new ProductDefaultWarehouse($this->db);
			$defaultWarehouse = $dProductWarehouse->fetch($fk_product, $entity->id);
			if ($defaultWarehouse < 0) {
				$this->error = $dProductWarehouse->error;
				$this->errors += $dProductWarehouse->errors;
				return $defaultWarehouse;
			} elseif (empty($defaultWarehouse)) {
				continue;
			}

			$retDelete = $dProductWarehouse->delete($user);
			if ($retDelete < 0) {
				$this->error = $dProductWarehouse->error;
				$this->errors += $dProductWarehouse->errors;
				return $retDelete;
			}
		}
	}

	/**
	 * Save Product Default Warehouse on reception creation if checkbox if checked
	 * @global type $user
	 * @param type $fk_product to delete
	 * @return type
	 */
	private function saveDefaultWarehouseFromReception()
	{
		global $user, $conf;
		$warehouseProducts = [];
		foreach ($_POST as $key => $value) {
			// Vérifie si la clé commence par 'productid' et se termine par un nombre
			$matches = [];
			if (preg_match('/^productid(\d+)$/', $key, $matches)) {
				$index = $matches[1];
				$productIdKey = 'productid' . $index;
				$entKey = 'entl' . $index;
				$selectKey = 'select_default_warehouse_entl' . $index;

				// Vérifie si la clé 'select_default_warehouse_entlXX' existe et est égale à 'on'
				if (GETPOST($selectKey) !== null && GETPOST($selectKey) === 'on') {
					$warehouseProducts[GETPOST($productIdKey, 'int')] = GETPOST($entKey, 'int');
				}
			}
		}

		foreach ($warehouseProducts as $fk_product => $fk_warehouse) {
			$productDefaultWarehouse = new ProductDefaultWarehouse($this->db);
			$retFetch = $productDefaultWarehouse->fetch($fk_product, $conf->entity);
			if ($retFetch < 0) {
				$this->error = $productDefaultWarehouse->error;
				$this->errors += $productDefaultWarehouse->errors;
				return -1;
			}

			$productDefaultWarehouse->fk_default_warehouse = $fk_warehouse;
			if (empty($retFetch)) {
				$productDefaultWarehouse->entity = $conf->entity;
				$productDefaultWarehouse->fk_product = $fk_product;
				$retCreate = $productDefaultWarehouse->create($user);
				if ($retCreate < 0) {
					$this->error = $productDefaultWarehouse->error;
					$this->errors += $productDefaultWarehouse->errors;
					return $retCreate;
				}
			} else {
				$retUpdate = $productDefaultWarehouse->update($user);
				if ($retUpdate < 0) {
					$this->error = $productDefaultWarehouse->error;
					$this->errors += $productDefaultWarehouse->errors;
					return $retUpdate;
				}
			}
		}
		return 0;
	}
}
