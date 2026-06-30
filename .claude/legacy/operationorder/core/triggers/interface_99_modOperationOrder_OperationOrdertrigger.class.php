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
 *    \file        core/triggers/interface_99_modMyodule_OperationOrdertrigger.class.php
 *    \ingroup    operationorder
 *    \brief        Sample trigger
 *    \remarks    You can create other triggers by copying this one
 *                - File name should be either:
 *                    interface_99_modOperationorder_Mytrigger.class.php
 *                    interface_99_all_Mytrigger.class.php
 *                - The file must stay in core/triggers
 *                - The class name must be InterfaceMytrigger
 *                - The constructor method must be named InterfaceMytrigger
 *                - The name property name must be Mytrigger
 */
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

/**
 * Trigger class
 */
class InterfaceOperationOrdertrigger extends DolibarrTriggers
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
		$this->family = "demo";
		$this->description = "Triggers of this module are empty functions.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'operationorder@operationorder';
	}

	/**
	 * Trigger name
	 *
	 * @return        string    Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return        string    Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

	/**
	 * Trigger version
	 *
	 * @return        string    Version of trigger file
	 */
	public function getVersion()
	{
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development') {
			return $langs->trans("Development");
		} elseif ($this->version == 'experimental')
			return $langs->trans("Experimental");
		elseif ($this->version == 'dolibarr')
			return DOL_VERSION;
		elseif ($this->version)
			return $this->version;
		else {
			return $langs->trans("Unknown");
		}
	}

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
	 *
	 * @param string $action code
	 * @param Object $object object
	 * @param User $user user
	 * @param Translate $langs langs
	 * @param conf $conf conf
	 * @return int <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		//For 8.0 remove warning
		$result = $this->run_trigger($action, $object, $user, $langs, $conf);
		return $result;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "run_trigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string $action Event action code
	 * @param Object $object Object
	 * @param User $user Object user
	 * @param Translate $langs Object langs
	 * @param conf $conf Object conf
	 * @return        int                        <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function run_trigger($action, $object, $user, $langs, $conf)
	{
		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action
		// Users
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		}

		switch ($action) {
			case 'LINEORDER_SUPPLIER_DELETE':
				$this->delete_link_between_lineorder_and_OR($object);
				if (!empty($this->errors)) {
					return -1;
				}
				break;
			case 'ORDER_SUPPLIER_SENTBYMAIL':
				if ($object->fk_statut < 3) {
					$object->commande($user, dol_now(), 3, 'Auto Set statut "send by mail", by mail sent');
				}
				break;

			case 'BILL_UNVALIDATE':
			case 'BILL_VALIDATE':
				$this->action_on_bill_validate_or_unvalidate($object, $action);
				if (!empty($this->errors)) {
					return -1;
				}
				break;

			case 'BILL_DELETE':
				$this->action_on_bill_delete($object);
				if (!empty($this->errors)) {
					return -1;
				}
				break;

			case 'LINEBILL_INSERT':
			case 'LINEBILL_MODIFY':
			case 'LINEBILL_DELETE':
				require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/factureligne.class.php';
				require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
				$this->action_on_bill_line_change($user, $object);
				if (!empty($this->errors)) {
					return -1;
				}
				break;

			case 'BILL_SUPPLIER_VALIDATE':
				$this->action_on_bill_supplier_validate($object, $action);
				if (!empty($this->errors)) {
					return -1;
				}
				break;

			case 'STOCK_MOVEMENT':
				if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
					$this->update_selling_price($object, $user);
					if (!empty($this->errors)) {
						return -1;
					}
				}
				break;
		}
		return 0;
	}
	private function update_selling_price($object, $user)
	{
		global $conf;
		$percent_price = price2num(getDolGlobalString('OPERATIONORDER_PERCENT_PRICE_UP_PMP'));
		if (!empty($percent_price)) {
			//find current PMP for the entity
			$sql = "SELECT p.pmp FROM ".$this->db->prefix()."product_perentity as p
					WHERE p.entity = ".(int) $conf->entity." AND p.fk_product=".(int) $object->product_id;

			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror;
				return -1;
			}
			while ($obj = $this->db->fetch_object($resql)) {
				if (!empty($obj->pmp)) {
					$new_selling_price=$obj->pmp*(1+($percent_price/100));
					$product = new Product($this->db);
					$result = $product->fetch($object->product_id);
					if ($result<0) {
						$this->errors = array_merge($product->errors, $this->errors);
						$this->errors[] = $product->error;
						return -1;
					}
					if ($new_selling_price!==$object->price) {
						$res = $product->updatePrice($new_selling_price, $product->price_base_type, $user, $product->tva_tx, $obj->pmp, 1, 0, 0, 0, [], $product->default_vat_code, 1);
					}
				}
			}
		}
	}

	private function delete_link_between_lineorder_and_OR($object)
	{
		require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
		$cmdFourn = new CommandeFournisseur($this->db);

		// Links between objects are stored in table element_element
		$sql = 'SELECT rowid, fk_source, sourcetype, fk_target, targettype';
		$sql .= ' FROM ' . $this->db->prefix() . 'element_element';
		$sql .= " WHERE ";
		$sql .= "(fk_source = " . ((int) $object->fk_commande) . " AND sourcetype = '" . $this->db->escape($cmdFourn->element) . "') OR ";
		$sql .= "(fk_target = " . ((int) $object->fk_commande) . " AND targettype = '" . $this->db->escape($cmdFourn->element) . "')";

		$operationOrderDet = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror;
			return;
		}

		$num = $this->db->num_rows($resql);
		if ($num == 0) {
			return;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			if ($obj->sourcetype == 'operationorderdet') {
				$operationOrderDet[$obj->fk_source] = $obj->rowid;
			}
			if ($obj->targettype == 'operationorderdet') {
				$operationOrderDet[$obj->fk_target] = $obj->rowid;
			}
		}

		if (empty($operationOrderDet)) {
			return;
		}

		foreach ($operationOrderDet as $detId => $idElemnt) {
			$sql = 'SELECT rowid FROM ' . $this->db->prefix() . 'operationorderdet';
			$sql .= " where rowid=" . (int) $detId;
			$sql .= " and fk_product=" . (int) $object->fk_product;

			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->db->lasterror;
				continue;
			}

			$num = $this->db->num_rows($resql);
			if ($num == 0) {
				continue;
			}

			$sqldel = 'DELETE FROM ' . $this->db->prefix() . 'element_element WHERE rowid=' . (int) $idElemnt;
			$resqlDel = $this->db->query($sqldel);
			if (!$resqlDel) {
				$this->errors[] = $this->db->lasterror;
			}
		}
	}

	private function action_on_bill_validate_or_unvalidate($object, $action)
	{
		$object->fetchObjectLinked();

		if (empty($object->linkedObjects)) {
			return;
		}

		foreach ($object->linkedObjects as $keyObjecttype => $objecttype) {
			if ($keyObjecttype == 'operationorder') {
				$this->action_on_bill_if_linked_to_operation_order($object, $objecttype, $action);
				break;
			}

			// If invoice is linked to an Order, this mean Warehouse source have been set on orderdet and transfer on facture det
			// But on facture det extrafield is hidden
			if ($keyObjecttype == 'commande') {
				$this->action_on_bill_if_linked_to_order($object, $objecttype, $action);
				break;
			}
		}
	}

	public function action_on_bill_line_change($user, FactureLigne $object)
	{
		$facture= new Facture($this->db);
		$facture->fetch($object->fk_facture);

		dol_include_once('/operationorder/lib/operationorder.lib.php');

		$resArray=calcFinTotalAmountByType($facture);
		$facture->array_options['total_ht_reimbursement'] = $resArray['total_ht_reimbursement'];
		$facture->array_options['total_ht_external'] = $resArray['total_ht_external'];
		$facture->array_options['total_ht_mo'] = $resArray['total_ht_mo'];
		$facture->array_options['total_ht_part'] = $resArray['total_ht_part'];
		$facture->array_options['total_ht_service'] = $resArray['total_ht_service'];

		$result = $facture->insertExtraFields();
		if ($result<0) {
			$this->errors= array_merge($this->errors, $facture->errors);
		}
		return $result;
	}

	private function action_on_bill_delete($object)
	{
		global $conf, $user, $langs;
		/**
		 * @var $object Facture
		 */
		$object->fetchObjectLinked();

		if ($object->type == $object::TYPE_STANDARD && !empty($object->linkedObjects)) {
			dol_include_once('/operationorder/class/operationorder.class.php');
			dol_include_once('/operationorder/class/operationorderstatus.class.php');
			foreach ($object->linkedObjects as $keyObjecttype => $objecttype) {
				if ($keyObjecttype !== 'operationorder') {
					continue;
				}
				foreach ($objecttype as $orId => $orData) {
					$orToTest = new OperationOrder($this->db);
					$resFetch = $orToTest->fetch($orData->id);
					if ($resFetch < 0) {
						$this->errors = array_merge($orToTest->errors, $this->errors);
						return -1;
					}
					if (empty($resFetch)) {
						continue;
					}

					if (empty(getDolGlobalString('OPERATIONORDER_STATUT_AFTER_INVOICE')) || $orToTest->objStatus->code !== getDolGlobalString('OPERATIONORDER_STATUT_AFTER_INVOICE')
						|| empty(getDolGlobalString('OPERATIONORDER_STATUT_FOR_INVOICE'))
					) {
						continue;
					}
					$statusInvoicable = new OperationOrderStatus($this->db);
					$res = $statusInvoicable->fetchAll(
						0, false,
						array('code' => getDolGlobalString('OPERATIONORDER_STATUT_FOR_INVOICE'), 'entity' => $conf->entity));
					if (empty($res)) {
						continue;
					}

					$resultSetStatus = $orToTest->setStatus($user, reset($res)->id);
					if ($resultSetStatus < 0) {
						$this->errors = array_merge($orToTest->errors, $this->errors);
						return -1;
					}

					return $resultSetStatus;
				}
			}
		}

		if ((int) $object->status !== $object::STATUS_DRAFT && !$user->admin) {
			$this->errors[] = $langs->trans('CannotDeleteValidatedInvoice');
			return -1;
		}

		return 0;
	}

	private function action_on_bill_if_linked_to_operation_order($object, $objecttype, $action)
	{
		global $user, $conf;
		foreach ($object->lines as $line) {
			if (!empty($line->fk_product) && !empty((float) $line->subprice) && !empty($line->qty) && empty($line->fk_product_type)) {
				dol_include_once('/product/stock/class/mouvementstock.class.php');
				$mvt = new MouvementStock($this->db);
				$mvt->setOrigin($object->element, $object->id);

				$resultMvt = 0;
				//Decrement Stock for each lin with price from Conf Operation order
				if (($object->type == $object::TYPE_STANDARD && $action == 'BILL_VALIDATE') ||
					($object->type == $object::TYPE_CREDIT_NOTE && $action == 'BILL_UNVALIDATE')) {
					$resultMvt = $mvt->livraison(
						$user, $line->fk_product, getDolGlobalInt('OPERATIONORDER_WAREHOUSE_ENCOURS_ATELIER'),
						$line->qty, 0, reset($objecttype)->ref
					);
				}

				//Increment Stock for each lin with price from Conf Operation order
				if (($object->type == $object::TYPE_CREDIT_NOTE && $action == 'BILL_VALIDATE') ||
					($object->type == $object::TYPE_STANDARD && $action == 'BILL_UNVALIDATE')) {
					$resultMvt = $mvt->reception(
						$user, $line->fk_product, getDolGlobalInt('OPERATIONORDER_WAREHOUSE_ENCOURS_ATELIER'),
						$line->qty, $line->subprice, reset($objecttype)->ref
					);
				}

				if ($resultMvt < 0) {
					$this->errors[] = $mvt->error;
					$this->errors = array_merge($this->errors, $mvt->errors);
					return $resultMvt;
				}
			}
		}

		// Only for Credit Note
		// delete link in element element avec les lignes issuent de la facture de l'avoir et changer le status de l'OR
		if ($object->type !== $object::TYPE_CREDIT_NOTE || $action !== 'BILL_VALIDATE' || empty($object->fk_facture_source)) {
			return 0;
		}

		$sql = "SELECT ref_ext FROM " . $this->db->prefix() . $object->table_element . " where rowid = " . (int) $object->id;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror;
			return -1;
		}

		$objtestFreeOR = $this->db->fetch_object($resql);
		if (strpos($objtestFreeOR->ref_ext, '_confirm_free_or') !== false) {
			require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
			dol_include_once('/operationorder/class/operationorder.class.php');
			dol_include_once('/operationorder/class/operationorderstatus.class.php');
			$factSource = new Facture($this->db);
			$resFetchFact = $factSource->fetch($object->fk_facture_source);
			if ($resFetchFact > 0) {
				if (!array_key_exists('operationorderdet', $conf->modules)) {
					$conf->modules['operationorderdet'] = 1;
				}

				$factSource->fetchObjectLinked();

				if (!empty($factSource->linkedObjects)) {
					foreach ($factSource->linkedObjects as $keyObjecttype => $objecttype) {
						if ($keyObjecttype !== 'operationorderdet') {
							continue;
						}
						foreach ($objecttype as $orDetId => $orDetData) {
							$orToDetTest = new OperationOrderDet($this->db);
							$resFetch = $orToDetTest->fetch($orDetData->id);
							if ($resFetch < 0) {
								$this->errors = array_merge($orToDetTest->errors, $this->errors);
								return -1;
							}
							if (empty($resFetch)) {
								continue;
							}

							$orToTest = new OperationOrder($this->db);
							$resFetch = $orToTest->fetch($orToDetTest->fk_operation_order);
							if ($resFetch > 0 && getDolGlobalString('OPERATIONORDER_STATUT_AFTER_INVOICE') && $orToTest->objStatus->code
								== getDolGlobalString('OPERATIONORDER_STATUT_AFTER_INVOICE') && getDolGlobalString('OPERATIONORDER_STATUT_FOR_INVOICE')
							) {
								$statusInvoicable = new OperationOrderStatus($this->db);
								$res = $statusInvoicable->fetchAll(
									0, false,
									array('code' => getDolGlobalString('OPERATIONORDER_STATUT_FOR_INVOICE'), 'entity' => $conf->entity));
								if (!empty($res)) {
									$resultSetStatus = $orToTest->setStatus($user, reset($res)->id);
									if ($resultSetStatus < 0) {
										$this->errors = array_merge($orToTest->errors, $this->errors);
										return -1;
									}
								}
							}
						}
					}
				}

				$sqlDelLink = "DELETE FROM " . $this->db->prefix() . "element_element";
				$sqlDelLink .= " WHERE (targettype='facture' AND fk_target=" . (int) $factSource->id;
				$sqlDelLink .= " AND sourcetype='operationorderdet') ";
				$sqlDelLink .= " OR (sourcetype='facture' AND fk_source=" . (int) $factSource->id;
				$sqlDelLink .= " AND targettype='operationorderdet') ";
				$resql = $this->db->query($sqlDelLink);
				if (!$resql) {
					$this->errors[] = $this->db->lasterror;
					return -1;
				}
			}
		}

		$new_ref_ext = str_replace('_confirm_free_or', '', $objtestFreeOR->ref_ext);
		if (empty($new_ref_ext)) {
			$new_ref_ext = "NULL";
		} else {
			$new_ref_ext = "'" . $this->db->escape($new_ref_ext) . "'";
		}

		$sql = "UPDATE " . $this->db->prefix() . $object->table_element . " SET ref_ext=" . $new_ref_ext . " WHERE rowid = " . (int) $object->id;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror;
			return -1;
		}
	}

	private function action_on_bill_if_linked_to_order($object, $objecttype, $action)
	{
		global $user, $langs;
		foreach ($object->lines as $line) {
			if (!empty($line->fk_product) && !empty((float) $line->subprice) && !empty($line->qty) && empty($line->fk_product_type)) {
				if (!isset($line->array_options['options_oorder_fk_warehouse']) || empty($line->array_options['options_oorder_fk_warehouse'])) {
					$this->errors[] = $langs->trans('YourInvoiceFromOrderIsNotCorrect');
					return -1;
				}

				dol_include_once('/product/stock/class/mouvementstock.class.php');
				$mvt = new MouvementStock($this->db);
				$mvt->setOrigin($object->element, $object->id);

				$resultMvt = 0;
				//Decrement Stock for each lin with price from Conf Operation order
				if (($object->type == $object::TYPE_STANDARD && $action == 'BILL_VALIDATE') ||
					($object->type == $object::TYPE_CREDIT_NOTE && $action == 'BILL_UNVALIDATE')) {
					$resultMvt = $mvt->livraison(
						$user, $line->fk_product, $line->array_options['options_oorder_fk_warehouse'], $line->qty, 0,
						reset($objecttype)->ref . '/' . $object->ref
					);
				}

				//Increment Stock for each lin with price from Conf Operation order
				if (($object->type == $object::TYPE_CREDIT_NOTE && $action == 'BILL_VALIDATE') ||
					($object->type == $object::TYPE_STANDARD && $action == 'BILL_UNVALIDATE')) {
					$resultMvt = $mvt->reception(
						$user, $line->fk_product, $line->array_options['options_oorder_fk_warehouse'], $line->qty,
						$line->subprice, reset($objecttype)->ref . '/' . $object->ref
					);
				}
			}

			if ($resultMvt < 0) {
				$this->errors[] = $mvt->error;
				$this->errors = array_merge($this->errors, $mvt->errors);
				return $resultMvt;
			}
		}
	}

	public function action_on_bill_supplier_validate($object, $action)
	{
		global $user, $conf, $langs;
		dol_syslog(
			"Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		);

		dol_include_once('operationorder/class/operationorder.class.php');
		require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
		require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
		require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.product.class.php';

		if (!empty($object->thirdparty)) {
			$object->fetch_thirdparty();
		}

		if (empty($object->linkedObjects['order_supplier'])) {
			return;
		}

		$spplierorder = array_values($object->linkedObjects['order_supplier'])[0];
		if (!array_key_exists('operationorderdet', $conf->modules)) {
			$conf->modules['operationorderdet'] = 1;
		}

		$spplierorder->fetchObjectLinked();
		if (empty($spplierorder->linkedObjects['operationorderdet'])) {
			return;
		}

		$morehtmlref = '<div class="refidno" data-info="supplierinvoice">';
		$morehtmlref .= $langs->trans('invoice') . ' : ' . $object->getNomUrl(2) . ' ' . $object->ref_supplier;
		$morehtmlref .= '<br>' . $langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1);
		$morehtmlref .= '</div>';

		// mise à jour de la ligne de l'OR
		$opeationorderdet = array_values($spplierorder->linkedObjects['operationorderdet'])[0];
		dol_include_once('operationorder/class/operationorder.class.php');
		$operationorder = new OperationOrder($object->db);
		$operationorder->fetch($opeationorderdet->fk_operation_order);

		$posDesc = strpos(
			$opeationorderdet->description, '<div class="refidno" data-info="supplierinvoice">');
		if ($posDesc !== false) {
			$posDescFin = strpos($opeationorderdet->description, '</a></div>', $posDesc);
			if ($posDescFin !== false) {
				$descToReplace = substr($opeationorderdet->description, $posDesc, $posDescFin);
				$opeationorderdet->description = str_replace(
					$descToReplace, $morehtmlref, $opeationorderdet->description);
			}
		} else {
			$opeationorderdet->description .= $morehtmlref;
		}

		$res = $operationorder->updateline(
			$opeationorderdet->id,
			$opeationorderdet->description,
			$opeationorderdet->qty,
			$object->total_ht * (float) getDolGlobalString('OPERATIONORDER_COEF_REFACT_PRESTA_EXT'),
			$opeationorderdet->fk_warehouse,
			$object->total_ht,
			$opeationorderdet->time_planned,
			$opeationorderdet->time_spent,
			$opeationorderdet->fk_product,
			$opeationorderdet->info_bits,
			$opeationorderdet->date_start,
			$opeationorderdet->date_end,
			$opeationorderdet->type,
			$opeationorderdet->fk_parent_line,
			$opeationorderdet->label,
			$opeationorderdet->special_code,
			$opeationorderdet->array_options,
			1
		);
		if ($res < 0) {
			$this->errors = array_merge($this->errors, $operationorder->errors);
			return -1;
		}
		$operationorder->calcTotal($user);

		return $res;
	}
}
