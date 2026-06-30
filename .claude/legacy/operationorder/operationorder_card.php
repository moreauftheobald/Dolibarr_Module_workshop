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

require 'config.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcontract.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
dol_include_once('operationorder/class/operationorder.class.php');
dol_include_once('operationorder/class/operationorderaction.class.php');
dol_include_once('operationorder/lib/operationorder.lib.php');
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
dol_include_once('dolifleet/class/vehicule.class.php');
dol_include_once('dolifleet/class/dictionaryContractType.class.php');


global $mysoc;

if (!$user->hasRight("operationorder", "read")) accessforbidden();

$langs->loadLangs(array('operationorder@operationorder', 'orders', 'companies', 'bills', 'products', 'other'));

$action = GETPOST('action', 'alpha');
$action_veh = GETPOST('action_veh', 'alpha');
$action_driver = GETPOST('action_driver', 'alpha');
$id = GETPOST('id', 'int');
$ref = GETPOST('ref');
$lineid = GETPOST('lineid');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');

$time_plannedhour = intval(GETPOST('time_plannedhour', 'int'));
$time_plannedmin = intval(GETPOST('time_plannedmin', 'int'));
$time_spenthour = intval(GETPOST('time_spenthour', 'int'));
$time_spentmin = intval(GETPOST('time_spentmin', 'int'));

$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'operationordercard';   // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');


$object = new OperationOrder($db);

if (!empty($id) || !empty($ref)) {
	$object->fetch($id, true, $ref);
	if ($action!=='update' && $action!=='edit' ) {
		$resTotCalc = $object->calcTotal($user);
		if ($resTotCalc < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	if (!empty($object->fk_vehicule)) {
		$vehicule = new Vehicule($db);
		$res = $vehicule->fetch($object->fk_vehicule, false);
		$vehicule->fetch_thirdparty($vehicule->fk_soc);
		if ($res > 0) {
			$dictCT = new dictionaryContractType($object->db);
			$dictCT->fetch($vehicule->fk_contract_type);
		}
	}
}

//$result = restrictedArea($user, $object->element, $object);


$status = new Operationorderstatus($db);
$res = $status->fetchDefault($object->status, $object->entity);
if ($res < 0) {
	setEventMessage($langs->trans('ErrorLoadingStatus'), 'errors');
}


$hookmanager->initHooks(array($contextpage, 'globalcard'));


if ($object->isextrafieldmanaged) {
	$extrafields = new ExtraFields($db);
	$extralabels = $extrafields->fetch_name_optionals_label($object->table_element);
	$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');
}

$usercanread = $user->hasRight("operationorder", "read");
$usercancreate = $permissionnote = $permissiontoedit = $permissiontoadd = $object->userCan($user, 'edit'); // Used by the include of actions_setnotes.inc.php
$permissiondellink = false;

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
		case 'confirm_create_vehicule':
			$vehicule = new Vehicule($db);
			$vehicule->setValues($_REQUEST);
			$vehicule->date_end_contract=null;
			$vehicule->status=$vehicule::STATUS_ACTIVE;
			$res = $vehicule->save($user);

			if ($res < 0) {
				setEventMessages($vehicule->error, $vehicule->errors, 'errors');
				$action = 'create';
				$action_veh= 'create';
				break;
			} else {
				header('Location: ' . $_SERVER["PHP_SELF"] . '?action=create&fk_vehicule=' . $vehicule->id.'&fk_soc=' . $vehicule->fk_soc.'&km_on_creation='.$vehicule->km);
				exit;
			}
		case 'confirm_create_driver_on_existing':
			$vehicule = new Vehicule($db);
			$retFetch = $vehicule->fetch($object->fk_vehicule);
			if ($retFetch < 0) {
				setEventMessages($vehicule->error, $vehicule->errors, 'errors');
				$action_driver = 'create';
				break;
			}

			$contact = new Contact($db);
			$contact->lastname = GETPOST('lastname', 'alpha');
			$contact->firstname = GETPOST('firstname', 'alpha');
			$contact->poste = GETPOST('poste', 'alpha');
			$contact->civility_id = GETPOST('civility', 'alpha');
			$contact->email = GETPOST('email', 'alpha');
			$contact->phone_mobile = GETPOST('phone_mobile', 'alpha');
			$contact->socid = $vehicule->fk_soc;
			$contact->setExtraField('driver', 1);
			$res = $contact->create($user);

			if ($res < 0) {
				setEventMessage($contact->errors, 'errors');
				$action = 'view';
				break;
			}
			$object->fk_conducteur = $contact->id;
			$retUpdate = $object->update($user);
			if ($res < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$action = 'view';
				break;
			}
			header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		case 'confirm_split_or':
			$lines_to_move = array();
			foreach ($object->lines as $line) {
				if (!empty(GETPOST('line_id' . $line->id))) {
					$lines_to_move[] = $line;
					$TNestedChilds = $line->fetch_all_children_lines($line->id, true, true);
					if (!is_array($TNestedChilds) && $TNestedChilds < 0) {
						setEventMessages($line->error, $line->errors, 'errors');
						$action = '';
						break;
					}
					if (!empty($TNestedChilds)) {
						foreach ($TNestedChilds as $child) {
							$lines_to_move[] = $child;
						}
					}
				}
			}

			if (!empty($lines_to_move)) {
				$origin_or_id = $object->id;
				$origin_or_status = $object->status;
				//Avoid clone of lines in this case
				$object->lines = array();
				$object->withChild = false;
				$resultClone = $object->cloneObject($user);
				if ($resultClone < 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					$action = '';
					break;
				}

				$lineIdToMove = array();
				foreach ($lines_to_move as $line) {
					$lineIdToMove[] = $line->id;
					$line->fk_operation_order = $resultClone;
					$resultUpd = $line->update($user);
					if ($result < 0) {
						setEventMessages($line->error, $line->errors, 'errors');
						$action = '';
						break;
					}

					//Update stock mouvement to the new OR
					$sql_stkmvt = " UPDATE " . $db->prefix() . "stock_mouvement";
					$sql_stkmvt .= " SET fk_origin=" . (int) $resultClone;
					$sql_stkmvt .= " , label='" . $db->escape($langs->trans('ORDebit') . " " . $object->ref) . "'";
					$sql_stkmvt .= " WHERE origintype='" . $object->element . '@' . $object->element . "'";
					$sql_stkmvt .= " AND fk_origin=" . (int) $origin_or_id;
					$sql_stkmvt .= " AND fk_product=" . (int) $line->fk_product;
					$resql = $db->query($sql_stkmvt);
					if (!$resql) {
						setEventMessages($db->lasterror, array('req2'), 'errors');
						$action = '';
						break;
					}
				}
				if (!empty($lineIdToMove)) {
					//Update element_element
					$sql_element = " UPDATE " . $db->prefix() . "element_element SET fk_target=" . (int) $resultClone . " WHERE rowid IN (
									SELECT rowid FROM " . $db->prefix() . "element_element WHERE fk_target IN (
									SELECT fk_target as idobj FROM " . $db->prefix() . "element_element WHERE sourcetype = 'operationorderdet'
									AND targettype = 'order_supplier' AND fk_source IN (" . join(',', $lineIdToMove) . ")
									UNION
									SELECT fk_source as idobj FROM " . $db->prefix() . "element_element WHERE targettype = 'operationorderdet'
									AND sourcetype = 'order_supplier' AND fk_target IN (" . join(',', $lineIdToMove) . "))
									AND sourcetype = 'order_supplier' AND targettype='operationorder')";


					$resql = $db->query($sql_element);

					if (!$resql) {
						setEventMessages($db->lasterror, array('req2'), 'errors');
						$action = '';
					}

					//Update element_element
					$sql_element = " UPDATE " . $db->prefix() . "element_element SET fk_source=" . (int) $resultClone . " WHERE rowid IN (
									SELECT rowid FROM " . $db->prefix() . "element_element WHERE fk_target IN (
									SELECT fk_target as idobj FROM " . $db->prefix() . "element_element WHERE sourcetype = 'operationorderdet'
									AND targettype = 'order_supplier' AND fk_source IN (" . join(',', $lineIdToMove) . ")
									UNION
									SELECT fk_source as idobj FROM " . $db->prefix() . "element_element WHERE targettype = 'operationorderdet'
									AND sourcetype = 'order_supplier' AND fk_target IN (" . join(',', $lineIdToMove) . "))
									AND targettype = 'order_supplier' AND sourcetype='operationorder')";

					$resql = $db->query($sql_element);

					if (!$resql) {
						setEventMessages($db->lasterror, null, 'errors');
						$action = '';
					}
				}

				$cloneOR = new OperationOrder($db);
				$cloneOR->fetch($resultClone);
				$cloneOR->setStatus($user, $origin_or_status);
			}

			$resultObjLinked = $cloneOR->add_object_linked('operationorder', $origin_or_id);
			if ($resultObjLinked < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$action = '';
				break;
			}

			$resultUpd = $cloneOR->update($user);
			if ($resultUpd < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$action = '';
				break;
			}

			$object->withChild = true;
			$object->fetch($origin_or_id);
			$resultUpd = $object->update($user);
			if ($resultUpd < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$action = '';
				break;
			}

			setEventMessages($langs->trans('ORSplitOK'), null);
			header('Location: ' . dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $resultClone);
			exit;

			break;
		case 'update_attribute':

			$attribute = GETPOST('attribute');

			if (!empty($object->userCan($user, 'edit'))) {
				$values = array();

				if ($attribute == 'date_operation_order') {
					$object->date_operation_order = dol_mktime(GETPOST('date_operation_orderhour'), GETPOST('date_operation_ordermin'), 0, GETPOST('date_operation_ordermonth'), GETPOST('date_operation_orderday'), GETPOST('date_operation_orderyear'));
				} elseif ($attribute == 'planned_date') {
					$object->planned_date = dol_mktime(GETPOST('planned_datehour'), GETPOST('planned_datemin'), 0, GETPOST('planned_datemonth'), GETPOST('planned_dateday'), GETPOST('planned_dateyear'));
					if (isJourFull($object->planned_date)) {
						setEventMessage('OverloadReachedForThisDay', 'errors');
					}
				} elseif ($attribute == 'categories') {
					$object->categories = implode(',', GETPOST('categories', 'array'));
				} else {
					$value = GETPOST($attribute);
					$values[$attribute] = $value;
					$object->setValues($values);
				}

				$object->save($user);

				header('Location: ' . dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $object->id);
				exit;
			}

			break;
		case 'add':
			if (!empty(isModEnabled("multicurrency"))) {
				require_once DOL_DOCUMENT_ROOT . '/multicurrency/class/multicurrency.class.php';
				$object->fk_multicurrency = MultiCurrency::getIdFromCode($object->db, $conf->currency);
				$object->multicurrency_code = $conf->currency;
			}
		case 'update':

			$object->setValues($_REQUEST); // Set standard attributes
			if (empty(GETPOST('ref_client', 'alpha'))) {
				$object->ref_client=null;
			}
			if (isset($_REQUEST['categories'])) {
				$object->categories = implode(',', $_REQUEST['categories']);
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
			if ($res <= 0) {
				setEventMessage($object->errors, 'errors');
				if (empty($object->id)) $action = 'create';
				else $action = 'edit';
				break;
			} else {
				header('Location: ' . dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $object->id);
				exit;
			}
		case 'update_extras':

			$object->oldcopy = dol_clone($object);
			// Fill array 'array_options' with data from update form
			$ret = $extrafields->setOptionalsFromPost($extralabels, $object, GETPOST('attribute', 'none'));
			if ($ret < 0) $error++;

			if (!$error) {
				$result = $object->insertExtraFields('OPERATIONORDER_MODIFY');
				if ($result < 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					$error++;
				}
			}

			if ($error) $action = 'edit_extras';
			else {
				$oOHistory = new OperationOrderHistory($object->db);
				$oOHistory->compareAndSaveDiff($object->oldcopy, $object);
				header('Location: ' . dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $object->id);
				exit;
			}
			break;
		case 'confirm_clone':
			if ($user->hasRight("operationorder", "write")) {
				$newid = $object->cloneObject($user);
				if ($newid > 0) {
					setEventMessage('OperationOrderCloned');
					header('Location: ' . dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $object->id);
					exit;
				} else {
					setEventMessage('OperationOrderCloneError', 'errors');
				}
			}

		case 'confirm_setStatus':
			/** @var  $object OperationOrder */
			$fk_status = GETPOST('fk_status', 'int');
			if (!empty($fk_status)) {
				// vérification des droits
				if ($object->setStatus($user, $fk_status) > 0) {
					setEventMessage($langs->trans('StatusChanged'));
				} else {
					setEventMessages($object->error, $object->errors, "errors");
				}
			}

			header('Location: ' . dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $object->id);
			exit;

			break;

		case 'confirm_delete':
			if ($user->hasRight("operationorder", "delete")) {
				$result = $object->delete($user);
				if ($result<=0) {
					setEventMessages($object->error, $object->errors, "errors");
				} else {
					header('Location: ' . dol_buildpath('/operationorder/list.php', 1));
					exit;
				}
			}

		case 'addline':
			if ($usercancreate) {
				$langs->load('errors');
				$error = 0;

				$langs->load('errors');
				$error = 0;

				// Set if we used free entry or predefined product
				$fk_product = GETPOST('fk_product');

				$addLine = true;
				$cleanEntry = false;
				if (!empty($fk_product)) {
					//Find if it's a product and already into the operation order
					$prd = new Product($db);
					$resultFetch = $prd->fetch($fk_product, '', '', '', 1, 1, 1);
					if ($resultFetch < 0) {
						setEventMessages($prd->error, $prd->errors, 'errors');
						$addLine = false;
					} elseif (!empty($prd->id) && $prd->type == $prd::TYPE_PRODUCT) {
						foreach ($object->lines as $line) {
							if ($line->fk_product == $prd->id) {
								$line->qty += (int) GETPOST('qty', 'int');
								$resultUpd = $object->updateline($line->id, $line->description, $line->qty, $line->price, $line->fk_warehouse, $prd->pmp, $line->time_planned, $line->time_spent, $line->fk_product, $line->info_bits, '', '', $line->product_type, $line->fk_parent_line, '', 0, $line->array_options, 0, $line->fk_c_operationorder_type, $line->remise_percent);
								if ($resultUpd < 0) {
									setEventMessages($object->error, $object->errors, 'errors');
								} else {
									$cleanEntry = true;
								}
								$addLine = false;
								break;
							}
						}
					}
				}

				if ($addLine) {
					$parent_line = GETPOST('fk_parent_line', 'int');
					if ($parent_line == -1) {
						$parent_line = null;
					}
					$product_desc = (GETPOST('description') ? GETPOST('description') : '');
					$product_desc = preg_replace("/[\r\n]+/", "\n", $product_desc);
					$time_plannedhour = intval(GETPOST('time_plannedhour', 'int'));
					$time_plannedmin = intval(GETPOST('time_plannedmin', 'int'));
					$time_spenthour = intval(GETPOST('time_spenthour', 'int'));
					$time_spentmin = intval(GETPOST('time_spentmin', 'int'));
					$qty = GETPOST('qty');
					$price = GETPOST('price');
					$remise_percent = GETPOST('remise_percent');
					$fk_warehouse = GETPOST('fk_warehouse');

					$pc = GETPOST('pc');
					$date_start = dol_mktime(GETPOST('date_starthour'), GETPOST('date_startmin'), GETPOST('date_startsec'), GETPOST('date_startmonth'), GETPOST('date_startday'), GETPOST('date_startyear'));
					$date_end = dol_mktime(GETPOST('date_endhour'), GETPOST('date_endmin'), GETPOST('date_endsec'), GETPOST('date_endmonth'), GETPOST('date_endday'), GETPOST('date_endyear'));
					$label = (GETPOST('product_label') ? GETPOST('product_label') : '');
					$fk_c_operationorder_type = null;
					if (GETPOST('fk_c_operationorder_type', 'int') !== '-1') {
						$fk_c_operationorder_type = GETPOST('fk_c_operationorder_type', 'int');
					}
					try {
						$ret = addLineAndChildToOR(
							$object,
							$fk_product,
							$qty,
							$price,
							$prd->type,
							$product_desc,
							'',
							$time_plannedhour,
							$time_plannedmin,
							$time_spenthour,
							$time_spentmin,
							$fk_warehouse,
							$pc,
							$date_start,
							$date_end,
							$label,
							true,
							$parent_line,
							$fk_c_operationorder_type,
							$remise_percent
						);
					} catch (Exception $ex) {
						$ret = -1;
						setEventMessage($ex->getMessage(), 'errors');
					}

					if ($ret > 0) {
						//Pour update une seule fois à la fin plutôt que d'update à chaque ajout recursif de ligne (TK12165)
						$resUpd = $object->update($user);

						if ($resUpd < 0) {
							setEventMessages($object->error, $object->errors, 'errors');
						} else {
							if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
								// Define output language
								$outputlangs = $langs;
								$newlang = GETPOST('lang_id', 'alpha');
								if (!empty(getDolGlobalString("MAIN_MULTILANGS")) && empty($newlang))
									$newlang = $object->thirdparty->default_lang;
								if (!empty($newlang)) {
									$outputlangs = new Translate("", $conf);
									$outputlangs->setDefaultLang($newlang);
								}

								$object->generateDocument($object->modelpdf, $outputlangs, 0, 0, 0);
							}

							$url = dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $object->id;
							if (!empty($prod->array_options['options_oorder_available_for_supplier_order'])) {
								// action to open dialog box
								$url .= '&action=dialog-supplier-order&lineid=' . $result;
								$url .= '#item_' . $line->id;
							} else {
								$url .= "#addline";
							}


							setEventMessage($langs->trans('OperationOrderLineAdded'));
							header('Location: ' . $url);
							exit;
						}
					} else {
						$error++;
					}
				}

				if ($cleanEntry) {
					unset($_POST['prod_entry_mode']);
					unset($_POST['fk_product']);
					unset($_POST['qty']);
					unset($_POST['type']);
					unset($_POST['product_ref']);
					unset($_POST['product_label']);
					unset($_POST['product_desc']);
					unset($_POST['dp_desc']);
					unset($_POST['idprod']);
					unset($_POST['search_fk_product']);
					unset($_POST['fk_parent_line']);
					unset($_POST['fk_c_operationorder_type']);
					unset($_POST['remise_percent']);
					unset($_POST['price']);

					unset($_POST['date_starthour']);
					unset($_POST['date_startmin']);
					unset($_POST['date_startsec']);
					unset($_POST['date_startday']);
					unset($_POST['date_startmonth']);
					unset($_POST['date_startyear']);
					unset($_POST['date_endhour']);
					unset($_POST['date_endmin']);
					unset($_POST['date_endsec']);
					unset($_POST['date_endday']);
					unset($_POST['date_endmonth']);
					unset($_POST['date_endyear']);
				}
			}

			break;
		case 'confirm_deleteline':
			// Remove a product line
			if ($confirm == 'yes' && $usercancreate) {
				$result = $object->deleteline($user, $lineid);
				if ($result) {
					// Define output language
					$outputlangs = $langs;
					$newlang = '';
					if (getDolGlobalInt("MAIN_MULTILANGS")  && empty($newlang) && GETPOST('lang_id', 'aZ09'))
						$newlang = GETPOST('lang_id', 'aZ09');
					if (getDolGlobalInt("MAIN_MULTILANGS")  && empty($newlang))
						$newlang = $object->thirdparty->default_lang;
					if (!empty($newlang)) {
						$outputlangs = new Translate("", $conf);
						$outputlangs->setDefaultLang($newlang);
					}
					if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
						$ret = $object->fetch($object->id); // Reload to get new records
						$object->generateDocument($object->modelpdf, $outputlangs, 0, 0, 0);
					}
					$object->setTimePlannedT();

					header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
					exit;
				} else {
					setEventMessages($object->error, $object->errors, 'errors');
				}
			}
			break;

		case 'create-supplier-order':
			$langs->load('supplier');
			$error = 0;
			$objPrefix = 'order_';
			$linePrefix = 'orderline_';

			// check && prepare order
			$supplierOrder = new CommandeFournisseur($object->db);
			$TSupplierOrderFields = array('fk_soc');
			$supplierOrder->ref_supplier = $object->ref;

			// Auto set values
			foreach ($TSupplierOrderFields as $key) {
				$val = $supplierOrder->fields[$key];

				$objKey = $key;
				if ($key = 'fk_soc') {
					$objKey = 'socid'; // for compatibility
				}

				$supplierOrder->{$objKey} = GETPOST($objPrefix . $key);

				// test empty value
				if (!empty($val['notnull']) && empty($supplierOrder->{$objKey})) {
					setEventMessage($langs->trans('supplierFieldMissing') . ' : ' . $langs->trans($val['label']), 'warnings');
					$error++;
				}
			}


			// Check & prepare line
			//Add same product as the product/service on the current line
			//$TSupplierOrderLineFields = array('product_type', 'subprice', 'qty', 'desc', 'tva_tx');
			$TSupplierOrderLineFields = array('desc');

			$supplierOrderLine = new CommandeFournisseurLigne($object->db);
			// Auto set values
			foreach ($TSupplierOrderLineFields as $key) {
				$supplierOrderLine->{$key} = GETPOST($linePrefix . $key);
				// test empty value
				if (empty($supplierOrderLine->{$key}) && !is_numeric($supplierOrderLine->{$key})) {
					setEventMessage($langs->trans('supplierFieldMissing') . ' : ' . $langs->trans($linePrefix . $key), 'warnings');
					$error++;
				}
			}
			$lineid = GETPOST('lineid');
			$ligne = new operationorderLine($db);
			$ligne->fetch($lineid);

			$supplierOrderLine->product_type = $ligne->product_type;
			$supplierOrderLine->fk_product = $ligne->fk_product;
			$supplierOrderLine->ref = $ligne->ref;
			$supplierOrderLine->pu_ht = $ligne->price;
			$supplierOrderLine->price = $ligne->price;
			$supplierOrderLine->subprice = $ligne->price;
			$supplierOrderLine->qty = $ligne->qty;
			$supplierOrderLine->tva_tx = $ligne->product->tva_tx;
			$supplierOrder->lines[] = $supplierOrderLine;

			if (empty($error)) {
				// create new supplier order
				$resSupplierOrder = $supplierOrder->create($user);
				if ($resSupplierOrder > 0) {
					$supplierOrder->add_object_linked('operationorder', $object->id); // and link to object to be displayed un document
					$supplierOrder->add_object_linked('operationorderdet', $lineid);// and link to line origin for user interface

					if (!empty(getDolGlobalString("OPODER_SUPPLIER_ORDER_AUTO_VALIDATE"))) {
						$supplierOrder->valid($user);
						$supplierOrder->approve($user, 0, 0);
					}
					if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
						// Define output language
						$outputlangs = $langs;
						$newlang = '';
						if (getDolGlobalInt("MAIN_MULTILANGS")  && empty($newlang) && GETPOST('lang_id', 'aZ09'))
							$newlang = GETPOST('lang_id', 'aZ09');
						if (getDolGlobalInt("MAIN_MULTILANGS")  && empty($newlang))
							$newlang = $object->thirdparty->default_lang;
						if (!empty($newlang)) {
							$outputlangs = new Translate("", $conf);
							$outputlangs->setDefaultLang($newlang);
						}

						$ret = $supplierOrder->fetch($supplierOrder->id); // Reload to get new records
					}
					$supplierOrder->array_options['options_supplier_order_type'] = '1';
					if ($supplierOrder->insertExtraFields() < 0) {
						setEventMessage($supplierOrder->error, 'errors');
					}

					if (is_object($vehicule)) {
						// Lors de création de la commande fournisseur, ajouter dans la note publique de la commande fournisseur :
						// le VIN, Immat du véhicule concerné et le numéro de l'OR
						$noteAppend = '';
						if (!empty($supplierOrder->note_public)) {
							$noteAppend .= '<br/>';
						}

						$noteAppend .= $vehicule->getSupplierOrderPublicNote($object);

						//Informations Emetteur (contact qui créé la commande)
						$noteAppend .= '<br/>' . $langs->trans('CheckTransmitter') . ' : ' . $user->firstname . ' ' . $user->lastname;
						if (!empty($user->office_phone) || !empty($user->user_mobile)) {
							if (!empty($user->office_phone) && !empty($user->user_mobile)) {
								$noteAppend .= ' ( ' . $user->office_phone . ' / ' . $user->user_mobile . ' )';
							} elseif (!empty($user->office_phone) && empty($user->user_mobile)) {
								$noteAppend .= ' ( ' . $user->office_phone . ' )';
							} elseif (empty($user->office_phone) && !empty($user->user_mobile)) {
								$noteAppend .= ' ( ' . $user->user_mobile . ' )';
							}
						}
						$resappend = $supplierOrder->update_note($supplierOrder->note_public . $noteAppend, '_public');

						$supplierOrder->model_pdf = 'cornas';

						$sql = 'UPDATE ' . $db->prefix() . $supplierOrder->table_element . ' SET model_pdf=\'' . $supplierOrder->model_pdf . '\' WHERE rowid=' . $supplierOrder->id;
						$resql = $object->db->query($sql);
					}

					$supplierOrder->generateDocument($supplierOrder->model_pdf, $outputlangs, 0, 0, 0);
					setEventMessage($langs->trans('SupplierOrderCreated') . ' : ' . $supplierOrder->getNomUrl(1));

					$url = dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $object->id;
					$url .= '#item_' . $lineid;
					header('Location: ' . $url);
					exit;
				} else {
					$error++;
					setEventMessage($langs->trans('SupplierOrderSaveError') . ' : ' . $supplierOrder->error, 'errors');
					if (!empty($supplierOrder->errors)) {
						setEventMessage($supplierOrder->errors, 'errors');
					}
				}
			}

			if ($error > 0) {
				$url = dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $object->id;
				$url .= '&action=dialog-supplier-order&lineid=' . $lineid;
				$url .= '#item_' . $lineid;
				header('Location: ' . $url);
				exit;
			}
			break;
	}

	/*
	 *  Update a line
	 */

	if ($action == 'updateline' && $usercancreate && GETPOSTISSET('save')) {
		$updateLineResult = false;

		// Clean parameters
		$date_start = '';
		$date_end = '';
		$date_start = dol_mktime(GETPOST('date_starthour'), GETPOST('date_startmin'), GETPOST('date_startsec'), GETPOST('date_startmonth'), GETPOST('date_startday'), GETPOST('date_startyear'));
		$date_end = dol_mktime(GETPOST('date_endhour'), GETPOST('date_endmin'), GETPOST('date_endsec'), GETPOST('date_endmonth'), GETPOST('date_endday'), GETPOST('date_endyear'));
		$description = dol_htmlcleanlastbr(GETPOST('description', 'none'));
		$fk_c_operationorder_type = null;
		if (GETPOST('fk_c_operationorder_type', 'int') !== '-1') {
			$fk_c_operationorder_type = GETPOST('fk_c_operationorder_type', 'int');
		}
		$remise_percent = GETPOST('remise_percent');
		$fk_warehouse = GETPOST('fk_warehouse');
		//$pc = GETPOST('pc');

		$price = GETPOST('price');

		$time_plannedhour = GETPOST('time_plannedhour', 'int');
		$time_plannedmin = GETPOST('time_plannedmin', 'int');
		$time_spenthour = GETPOST('time_spenthour', 'int');
		$time_spentmin = GETPOST('time_spentmin', 'int');

		$time_planned = doubleval($time_plannedhour) * 60 * 60 + doubleval($time_plannedmin) * 60; // store in seconds
		$time_spent = doubleval($time_spenthour) * 60 * 60 + doubleval($time_spentmin) * 60;

		// Define info_bits
		$info_bits = 0;

		// Extrafields Lines
		$extralabelsline = $extrafields->fetch_name_optionals_label($object->table_element_line);
		$array_options = $extrafields->getOptionalsFromPost($object->table_element_line);
		// Unset extrafield POST Data
		if (is_array($extralabelsline)) {
			foreach ($extralabelsline as $key => $value) {
				unset($_POST["options_" . $key]);
			}
		}

		// Define special_code for special lines
		$special_code = GETPOST('special_code');
		if (!GETPOST('qty')) $special_code = 3;

		// Check minimum price
		$productid = GETPOST('fk_product', 'int');
		$pr = 0;
		if (!empty($productid)) {
			$product = new Product($db);
			$product->fetch($productid);

			$pr = (empty($product->pmp)?$product->cost_price:$product->pmp);

			$type = $product->type;

			$label = ((GETPOST('update_label') && GETPOST('product_label')) ? GETPOST('product_label') : '');
		} else {
			$type = GETPOST('type');
			$label = (GETPOST('product_label') ? GETPOST('product_label') : '');

			// Check parameters
			if (GETPOST('type') < 0) {
				setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Type")), null, 'errors');
				$error++;
			}
		}
		$parent_line = GETPOST('fk_parent_line');
		if ($parent_line == -1) {
			$parent_line = null;
		}

		if (!$error) {
			$result = $object->updateline(GETPOST('lineid'), $description, GETPOST('qty'), $price, $fk_warehouse, $pr, $time_planned, $time_spent, $productid, $info_bits, $date_start, $date_end, $type, $parent_line, $label, $special_code, $array_options, 0, $fk_c_operationorder_type, $remise_percent);

			if ($price < $object->line->product->price_min && !$user->admin) setEventMessage($langs->trans('ErrorPriceLineORMin'), 'warnings');

			if ($result >= 0) {
				$updateLineResult = true;

				//if objectkinked, we update it
				$lineid = GETPOST('lineid');
				$lineupdated = new operationorderLine($db);
				$lineupdated->fetch($lineid);
				if (!array_key_exists('operationorderdet', $conf->modules)) {
					$conf->modules['operationorderdet'] = 1;
				}
				$lineupdated->fetchObjectLinked();
				if (!empty($lineupdated->linkedObjects['order_supplier'])) {
					$supplieroder = array_values($lineupdated->linkedObjects['order_supplier'])[0];
					foreach ($supplieroder->lines as $supplierOrderLine) {
						if ($supplierOrderLine->fk_product == $lineupdated->fk_product) {
							$oldstatus = $supplieroder->statut;
							$res = 1;
							if ($oldstatus > 0) {
								$res = $supplieroder->setStatus($user, 0);
								$res = $supplieroder->fetch($supplieroder->id);
							}
							if ($res > 0) {
								$res = $supplieroder->updateline($supplierOrderLine->id, $supplierOrderLine->desc, $lineupdated->price, $lineupdated->qty, 0, $lineupdated->product->tva_tx, 0, 0, 'HT', 0, $supplierOrderLine->product_type, 0, '', '', $supplierOrderLine->array_options);
								if ($res > 0) {
									$res = $supplieroder->update_note(dol_html_entity_decode($supplieroder->note_private . '<br/>' . $description, ENT_QUOTES), '_private');
								}
							}
							if ($oldstatus > 0) {
								$res = $supplieroder->setStatus($user, $oldstatus);
								$res = $supplieroder->fetch($supplieroder->id);
							}
							if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
								// Define output language
								$outputlangs = $langs;
								$newlang = '';
								if (getDolGlobalInt("MAIN_MULTILANGS")  && empty($newlang) && GETPOST('lang_id', 'aZ09'))
									$newlang = GETPOST('lang_id', 'aZ09');
								if (getDolGlobalInt("MAIN_MULTILANGS")  && empty($newlang))
									$newlang = $object->thirdparty->default_lang;
								if (!empty($newlang)) {
									$outputlangs = new Translate("", $conf);
									$outputlangs->setDefaultLang($newlang);
								}

								$ret = $supplieroder->fetch($supplieroder->id); // Reload to get new records
								$supplieroder->generateDocument($supplieroder->modelpdf, $outputlangs);
							}
						}
					}
				}


				if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
					// Define output language
					$outputlangs = $langs;
					$newlang = '';
					if (getDolGlobalInt("MAIN_MULTILANGS")  && empty($newlang) && GETPOST('lang_id', 'aZ09'))
						$newlang = GETPOST('lang_id', 'aZ09');
					if (getDolGlobalInt("MAIN_MULTILANGS")  && empty($newlang))
						$newlang = $object->thirdparty->default_lang;
					if (!empty($newlang)) {
						$outputlangs = new Translate("", $conf);
						$outputlangs->setDefaultLang($newlang);
					}

					$ret = $object->fetch($object->id); // Reload to get new records
					$object->generateDocument($object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
				}

				unset($_POST['qty']);
				unset($_POST['type']);
				unset($_POST['productid']);
				unset($_POST['product_ref']);
				unset($_POST['product_label']);
				unset($_POST['product_desc']);

				unset($_POST['date_starthour']);
				unset($_POST['date_startmin']);
				unset($_POST['date_startsec']);
				unset($_POST['date_startday']);
				unset($_POST['date_startmonth']);
				unset($_POST['date_startyear']);
				unset($_POST['date_endhour']);
				unset($_POST['date_endmin']);
				unset($_POST['date_endsec']);
				unset($_POST['date_endday']);
				unset($_POST['date_endmonth']);
				unset($_POST['date_endyear']);


				header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#item_' . GETPOST('lineid', 'int'));
				exit;
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}


		if (!$updateLineResult) {
			// Pour reaffichage de la fiche en cours d'edition
			header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=editline&lineid=5' . GETPOST('lineid'));
			exit();
		}
	} elseif ($action == 'updateline' && $usercancreate && GETPOST('cancel', 'alpha') == $langs->trans('Cancel')) {
		header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id); // Pour reaffichage de la fiche en cours d'edition
		exit;
	} elseif ($action == 'classin' && $usercancreate) {
		// Link to a project
		$object->setProject(GETPOST('projectid', 'int'));
	} elseif ($action == 'setref_client' && $usercancreate) {
		// Positionne ref commande client
		$object->ref_client = GETPOST('ref_client');
		$result = $object->update($user);
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} elseif ($action == 'confirm_create_propal_from_or' && empty($object->orcheck)) {
		$fk_soc_propal = GETPOST('propal_soc_id', 'int');
		$error = 0;
		if (!empty($fk_soc_propal) && $fk_soc_propal !== -1 && !empty($vehicule->id)) {
			$cust = new Societe($db);
			$resultFetch = $cust->fetch($fk_soc_propal);
			if ($resultFetch < 0) {
				setEventMessages($cust->error, $cust->errors, 'errors');
				$error++;
			}
			if (empty($error)) {
				$propal = new Propal($db);
				$propal->socid = $cust->id;
				$propal->ref_client = $object->ref;
				$propal->date = dol_now();
				$propal->model_pdf = getDolGlobalString('PROPALE_ADDON_PDF');
				$propal->duree_validite = getDolGlobalInt('PROPALE_VALIDITY_DURATION');
				$propal->cond_reglement_id = empty($cust->cond_reglement_id) ? (int) getDolGlobalInt('MAIN_DEFAULT_PAYMENT_TERM_ID') : $cust->cond_reglement_id;
				$propal->mode_reglement_id = empty($cust->mode_reglement_id) ? (int) getDolGlobalInt('MAIN_DEFAULT_PAYMENT_TYPE_ID') : $cust->mode_reglement_id;
				$propal->array_options['options_fk_vehicule'] = $vehicule->id;
				$propal->linked_objects[$object->element] = [$object->id];

				$propal->note_public = '<br/>' . $langs->trans('ORRef') . ' : ' . $object->ref;
				$input = 'vin';
				$propal->note_public .= '<br/>' . $langs->trans($vehicule->fields[$input]['label']) . ' : ' . $vehicule->showOutputFieldQuick($input);
				$input = 'immatriculation';
				$propal->note_public .= '<br/>' . $langs->trans($vehicule->fields[$input]['label']) . ' : ' . $vehicule->showOutputFieldQuick($input);

				foreach ($object->lines as $line) {
					if (!empty($line->fk_parent_line)
						|| empty($line->fk_c_operationorder_type)) {
						if (!empty($line->time_spent)) {
							$qty = round($line->time_spent / 3600, 2);
						} else {
							$qty = $line->qty;
						}
						$line->fetch_product();
						$linePropal = new PropaleLigne($db);
						$linePropal->desc = $line->description;
						$linePropal->subprice = $line->price;
						$linePropal->qty =$qty;
						$linePropal->tva_tx = get_default_tva($mysoc, $cust, $line->fk_product);
						$linePropal->fk_product = $line->fk_product;
						$linePropal->product_type = $line->product_type;
						$linePropal->rang = -1;
						$linePropal->remise_percent = ($line->remise_percent>$cust->remise_percent) ? $line->remise_percent : $cust->remise_percent;
						$linePropal->pa_ht = (empty($line->product->pmp) ? $line->product->cost_price : $line->product->pmp);
						$propal->lines[] = $linePropal;
					}
				}


				$resCreate=$propal->create($user);
				if ($resCreate<0) {
					setEventMessages($propal->error, $propal->errors, 'errors');
				} else {
					setEventMessage($langs->transnoentities("CreateSuccessPropal", $propal->getNomUrl(0)));
				}
			}
		}
	} elseif ($action == 'confirm_create_invoice_from_or' && $object->orcheck == 2) {
		$fk_soc_invoice = GETPOST('invoice_soc_id', 'int');
		$error = 0;
		if (!empty($fk_soc_invoice) && $fk_soc_invoice !== -1 && !empty($vehicule->id)) {
			$cust = new Societe($db);
			$resultFetch = $cust->fetch($fk_soc_invoice);
			if ($resultFetch < 0) {
				setEventMessages($cust->error, $cust->errors, 'errors');
				$error++;
			}
			$db->begin();
			$cacheSupplierWarrantyToInvoiceByJobType = [];
			$lineParentInvoiceToCustomer = [];

			if (empty($error)) {
				$invoiceSocToCreate = [];
				$invoiceSocToCreate[$cust->id] = $cust;

				foreach ($object->lines as $line) {
					$line->fetchObjectLinked();
					if (!empty($line->linkedObjects)) {
						foreach ($line->linkedObjects as $keyObjecttype => $objecttype) {
							if ($keyObjecttype == 'facture') {
								foreach ($objecttype as $objLinkedId => $invoiceLinked) {
									if ($invoiceLinked->socid == $fk_soc_invoice) {
										unset($invoiceSocToCreate[$cust->id]);
										break 2;
									}
								}
							}
						}
					}
				}

				foreach ($object->lines as $idLine => $line) {
					if (empty($line->fk_parent_line) && !empty($line->fk_c_operationorder_type)) {
						$line->fetchObjectLinked();
						if (!empty($line->linkedObjects)) {
							foreach ($line->linkedObjects as $keyObjecttype => $objecttype) {
								if ($keyObjecttype == 'facture') {
									continue 2;
								}
							}
						}

						if (!array_key_exists($line->fk_c_operationorder_type, $cacheSupplierWarrantyToInvoiceByJobType)) {
							$socSupplierId = $line->getValueFrom('c_operationorder_type', $line->fk_c_operationorder_type, 'fk_soc');
							if ($socSupplierId !== false && !empty($socSupplierId) && (int) $socSupplierId > 0) {
								$cacheSupplierWarrantyToInvoiceByJobType[$line->fk_c_operationorder_type] = $socSupplierId;
								$lineParentInvoiceToCustomer[$line->id] = $socSupplierId;
							} else {
								$lineParentInvoiceToCustomer[$line->id] = $fk_soc_invoice;
							}
						}

						if (array_key_exists($line->fk_c_operationorder_type, $cacheSupplierWarrantyToInvoiceByJobType)) {
							$supplierWarranty = new Societe($db);
							$resultFetch = $supplierWarranty->fetch($cacheSupplierWarrantyToInvoiceByJobType[$line->fk_c_operationorder_type]);
							if ($resultFetch < 0) {
								setEventMessages($supplierWarranty->error, $supplierWarranty->errors, 'errors');
								$error++;
							} else {
								$invoiceSocToCreate[$socSupplierId] = $supplierWarranty;
								$lineParentInvoiceToCustomer[$line->id] = $supplierWarranty->id;
							}
						}
					}
				}
			}

			$invoiceSocCreated = [];
			if (empty($error)) {
				// on créer les facture pour le client final et toutes les factures de garantie
				// Fk_soc de garantie trouvé dans le dico de type de jobs
				foreach ($invoiceSocToCreate as $socId => $socToInvoice) {
					$invoice = new Facture($db);
					$invoice->socid = $socId;
					$invoice->fetch_thirdparty($socId);
					$invoice->date = dol_now();
					$invoice->ref_customer = $object->ref;
					$invoice->note_public = dol_concatdesc($invoice->note_public, $langs->trans("ORCloturedate") . ' ' . dol_print_date($object->date_cloture, "daytext"));
					$invoice->note_public = dol_concatdesc($invoice->note_public, $object->note_public);
					$invoice->cond_reglement_id = empty($socToInvoice->cond_reglement_id) ? (int) getDolGlobalString("MAIN_DEFAULT_PAYMENT_TERM_ID")  : $socToInvoice->cond_reglement_id;
					$invoice->mode_reglement_id = empty($socToInvoice->mode_reglement_id) ? (int) getDolGlobalString("MAIN_DEFAULT_PAYMENT_TYPE_ID")  : $socToInvoice->mode_reglement_id;
					$invoice->array_options['options_immatriculation'] = $vehicule->immatriculation;
					$invoice->array_options['options_vin'] = $vehicule->vin;
					$invoice->array_options['options_km_on_creation'] = $object->km_on_creation;
					$invoice->array_options['options_mentionsurfacture'] = $invoice->thirdparty->array_options['options_mentionsurfacture'];

					$note ='';
					/*if (!empty($object->ref_client)) {
						$note .= "\n" . 'Ref Client: ' . $object->ref_client;
					}
					if (!empty($vehicule->immatriculation)) {
						$note .= 'Immatriculation: ' . $vehicule->immatriculation;
					}
					if (!empty($vehicule->vin)) {
						$note .="\n" . 'VIN: ' . $vehicule->vin;
					}
					if (!empty($object->km_on_creation)) {
						$note .= "\n" . 'Km a la creation: ' . $object->km_on_creation;
					}
					if (!empty($note)) {
						$note .= "\n" . '----------' . "\n";
					}*/
					$invoice->note_public = dol_concatdesc($note, $object->note_public);

					$invoice->linked_objects[$object->element] = $object->id;

					$resultCreate = $invoice->create($user);
					if ($resultCreate < 0) {
						setEventMessages($invoice->error, $invoice->errors, 'errors');
						$error++;
					} else {
						$invoiceSocCreated[$socId] = $invoice;
					}
				}
			}

			$invoiceCustomer = $invoiceSocCreated[$fk_soc_invoice];
			if (empty($error) && !empty($invoiceSocCreated)) {
				$parentLineIdParentLine = [];
				foreach ($object->lines as $idLine => $line) {
					//Pour toutes les ligne de Job (Cad sans parent avec un job type)
					//On creer ces lignes de jobs dans la facture client et celles des tiers de garantie
					if (empty($line->fk_parent_line)
						&& !empty($line->fk_c_operationorder_type)) {
						if (!empty($invoiceCustomer)) {
							$line->fetch_product();
							$resultAddLine = $invoiceCustomer->addline(
								$line->description,
								0, // subprice
								isModEnabled('subtotal') ? 1 : 0, // quantity
								get_default_tva($mysoc, $cust, $line->fk_product), // vat rate
								0, // localtax1_tx
								0, // localtax2_tx
								isModEnabled('subtotal') ? 0 : $line->fk_product, // fk_product
								($line->remise_percent>$cust->remise_percent) ? $line->remise_percent : $cust->remise_percent, // remise_percent
								0, // date_start
								0, // date_end
								0,
								0, // info_bits
								0,
								'HT',
								0,
								isModEnabled('subtotal') ? 9 : $line->product_type,
								1,
								isModEnabled('subtotal') ? '104777' : '',
								$object->element,
								$object->id,
								0,
								0,
								(empty($line->product->pmp)?$line->product->cost_price:$line->product->pmp),
								isModEnabled('subtotal') ? $line->product->label : null,
							);
							if ($resultAddLine < 0) {
								setEventMessages($invoiceCustomer->error, $invoiceCustomer->errors, 'errors');
								$error++;
							} else {
								$parentLineIdParentLine[$line->id][$invoiceCustomer->socid] = $resultAddLine;
							}
						}

						if (isset($cacheSupplierWarrantyToInvoiceByJobType[$line->fk_c_operationorder_type])
							&& isset($invoiceSocCreated[$cacheSupplierWarrantyToInvoiceByJobType[$line->fk_c_operationorder_type]])
							&& $cacheSupplierWarrantyToInvoiceByJobType[$line->fk_c_operationorder_type] !== $invoiceCustomer->socid) {
							$invoiceToWarranty = $invoiceSocCreated[$cacheSupplierWarrantyToInvoiceByJobType[$line->fk_c_operationorder_type]];
							$line->fetch_product();
							$resultAddLine = $invoiceToWarranty->addline(
								$line->description,
								0, // subprice
								isModEnabled('subtotal') ? 1 : 0, // quantity
								get_default_tva($mysoc, $cust, $line->fk_product), // vat rate
								0, // localtax1_tx
								0, // localtax2_tx
								isModEnabled('subtotal') ? 0 : $line->fk_product, // fk_product
								($line->remise_percent>$cust->remise_percent) ? $line->remise_percent : $cust->remise_percent, // remise_percent
								0, // date_start
								0, // date_end
								0,
								0, // info_bits
								0,
								'HT',
								0,
								isModEnabled('subtotal') ? 9 : $line->product_type, // product_type
								1,
								isModEnabled('subtotal') ? '104777' : '',
								$object->element,
								$object->id,
								0,
								0,
								(empty($line->product->pmp)?$line->product->cost_price:$line->product->pmp),
								isModEnabled('subtotal') ? $line->product->label : null,
							);
							if ($resultAddLine < 0) {
								setEventMessages($invoiceToWarranty->error, $invoiceToWarranty->errors, 'errors');
								$error++;
							} else {
								// and link to line origin for user interface
								$invoiceToWarranty->add_object_linked('operationorderdet', $line->id);
								$parentLineIdParentLine[$line->id][$invoiceToWarranty->socid] = $resultAddLine;
							}
						} elseif (!empty($invoiceCustomer) && $lineParentInvoiceToCustomer[$line->id] == $invoiceCustomer->socid) {
							$invoiceCustomer->add_object_linked('operationorderdet', $line->id);
						}
					}
				}

				foreach ($object->lines as $idLine => $line) {
					//Pour toutes les ligne qui ont un parent ou qui ne sont pas des job
					if (!empty($line->fk_parent_line)
						|| empty($line->fk_c_operationorder_type)) {
							$qty = $line->qty;
						if (!empty($line->fk_parent_line)) {
							// Pour chaque facture créer que l'on récupère avec la tableau des ligne créer
							// ci dessus on a ajoute les lignes avec leurs bons parents
							// Mais si c'est pour le client final : pas de prix et pas de qty
							if (isset($parentLineIdParentLine[$line->fk_parent_line])) {
								foreach ($parentLineIdParentLine[$line->fk_parent_line] as $factSocId => $lineCreatedId) {
									$fact = $invoiceSocCreated[$factSocId];
									$toBill = $lineParentInvoiceToCustomer[$line->fk_parent_line] == $fact->socid;
									$line->fetch_product();
									$resultAddLine = $fact->addline(
										$line->description,
										($toBill) ? $line->price : 0, // subprice
										(float) $qty, // quantity
										get_default_tva($mysoc, $cust, $line->fk_product), // vat rate
										0, // localtax1_tx
										0, // localtax2_tx
										$line->fk_product, // fk_product
										($line->remise_percent>$cust->remise_percent) ? $line->remise_percent : $cust->remise_percent, // remise_percent
										0, // date_start
										0, // date_end
										0,
										0, // info_bits
										0,
										'HT',
										0,
										$line->product_type, // product_type
										1,
										'',
										$object->element,
										$object->id,
										$parentLineIdParentLine[$line->fk_parent_line][$factSocId],
										0,
										($toBill) ? (empty($line->product->pmp)?$line->product->cost_price:$line->product->pmp) : 0
									);
									if ($resultAddLine < 0) {
										setEventMessages($fact->error, $fact->errors, 'errors');
										$error++;
									}
								}
							}
						} else {
							if (!empty($invoiceCustomer)) {
								$line->fetch_product();
								//C'est une ligne toute seule (sans parent sans job type) => dans la facture client
								$resultAddLine = $invoiceCustomer->addline(
									$line->description,
									$line->price, // subprice
									$qty, // quantity
									get_default_tva($mysoc, $cust, $line->fk_product), // vat rate
									0, // localtax1_tx
									0, // localtax2_tx
									$line->fk_product, // fk_product
									($line->remise_percent>$cust->remise_percent) ? $line->remise_percent : $cust->remise_percent, // remise_percent
									0, // date_start
									0, // date_end
									0,
									0, // info_bits
									0,
									'HT',
									0,
									$line->product_type, // product_type
									-1,
									'',
									$object->element,
									$object->id,
									0,
									0,
									(empty($line->product->pmp)?$line->product->cost_price:$line->product->pmp)
								);
								if ($resultAddLine < 0) {
									setEventMessages($invoiceCustomer->error, $invoiceCustomer->errors, 'errors');
									$error++;
								} else {
									$invoiceCustomer->add_object_linked('operationorderdet', $line->id);
								}
							}
						}
					}
				}
			}

			if (empty($error) && !empty($invoiceSocCreated)) {
				if (getDolGlobalString('OPERATIONORDER_STATUT_AFTER_INVOICE')) {
					$statusInvoiced = new OperationOrderStatus($db);
					$res = $statusInvoiced->fetchAll(0, false, array('code' => getDolGlobalString('OPERATIONORDER_STATUT_AFTER_INVOICE'), 'entity' => $conf->entity));
					if (!empty($res)) {
						$resultSetStatus = $object->setStatus($user, reset($res)->id);
						if ($resultSetStatus < 0) {
							setEventMessages($object->error, $object->errors, 'errors');
							$error++;
						}
					} else {
						setEventMessage('StatusNotFound', 'errors');
						$error++;
					}
				}
			}

			if (empty($error) && !empty($invoiceSocCreated)) {
				setEventMessage('InvoiceCreated');
			}

			if (empty($error)) {
				$db->commit();
				header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
				exit;
			} else {
				setEventMessage($error, 'errors');
				$db->rollback();
			}
		} else {
			setEventMessages(null, 'Missing Parameters', 'errors');
		}
	}
	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT . '/core/actions_printing.inc.php';

	// Actions to build doc
	$upload_dir = $conf->operationorder->multidir_output[$object->entity];
	$permissiontoadd = $user->hasRight("operationorder", "read");
	include DOL_DOCUMENT_ROOT . '/core/actions_builddoc.inc.php';
}


/**
 * View
 */
$form = new Form($db);
$formfile = new FormFile($db);
$formproject = new FormProjets($db);
$formcontrat = new FormContract($db);

$title = $langs->trans('OperationOrder');
$arrayofjs = '';
$arrayofcss = array(
	'/operationorder/css/operation-order-card.css.php',
	'/operationorder/css/animate.css'
);
llxHeader('', $title, '', '', 0, 0, $arrayofjs, $arrayofcss);

if ($action == 'create') {
	print load_fiche_titre($langs->trans('NewOperationOrder'), '', 'operationorder@operationorder');
	$object->fields['fk_vehicule']['position'] = 1;
	$object->fields['fk_soc']['position'] = 2;
	$object->fields['km_on_creation']['position'] = 4;
	$object->fields['date_operation_order']['position'] = 5;
	$object->fields['planned_date']['position'] = 6;
	$object->fields['time_planned_f']['position'] = 7;
	$object->fields['fk_user_meca']['position'] = 8;
	$object->fields['fk_conducteur']['position'] = 9;
	$object->fields['ref']['position'] = 10;
	print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';

	print dol_get_fiche_head(array(), '');

	if ($action_veh=="create") {
		$vehicule = new Vehicule($db);
		$formquestion[] = array('type'=>'other','name'=>'vin', 'label'=>$langs->trans($vehicule->fields['vin']['label']), 'tdclass'=>'fieldrequired', 'value'=>$vehicule->showInputField($vehicule->fields['vin'], 'vin', GETPOST('vin', 'alpha')));
		$formquestion[] = array('type'=>'other','name'=>'fk_vehicule_type','label'=>$langs->trans($vehicule->fields['fk_vehicule_type']['label']), 'tdclass'=>'fieldrequired','value'=>$vehicule->showInputField($vehicule->fields['fk_vehicule_type'], 'fk_vehicule_type', GETPOST('fk_vehicule_type', 'int')));
		$formquestion[] = array('type'=>'other','name'=>'fk_vehicule_mark','label'=>$langs->trans($vehicule->fields['fk_vehicule_mark']['label']), 'tdclass'=>'fieldrequired','value'=>$vehicule->showInputField($vehicule->fields['fk_vehicule_mark'], 'fk_vehicule_mark', GETPOST('fk_vehicule_mark', 'int')));
		$formquestion[] = array('type'=>'other','name'=>'modele','label'=>$langs->trans($vehicule->fields['modele']['label']), 'value'=>$vehicule->showInputField($vehicule->fields['modele'], 'modele', GETPOST('modele', 'alpha')));
		$formquestion[] = array('type'=>'other','name'=>'immatriculation','label'=>$langs->trans($vehicule->fields['immatriculation']['label']), 'tdclass'=>'fieldrequired','value'=>$vehicule->showInputField($vehicule->fields['immatriculation'], 'immatriculation', GETPOST('immatriculation', 'alpha')));
		$formquestion[] = array('type'=>'other','name'=>'date_immat','label'=>$langs->trans($vehicule->fields['date_immat']['label']), 'tdclass'=>'fieldrequired','value'=>$vehicule->showInputField($vehicule->fields['date_immat'], 'date_immat', GETPOST('date_immat', 'alpha')));
		$formquestion[] = array('type'=>'other','name'=>'fk_soc','label'=>$langs->trans($vehicule->fields['fk_soc']['label']), 'tdclass'=>'fieldrequired','value'=>$vehicule->showInputField($vehicule->fields['fk_soc'], 'fk_soc', GETPOST('fk_soc', 'int')));
		$formquestion[] = array('type'=>'other','name'=>'km','label'=>$langs->trans($vehicule->fields['km']['label']), 'tdclass'=>'fieldrequired','value'=>$vehicule->showInputField($vehicule->fields['km'], 'km', GETPOST('km', 'int')));
		print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('NewdoliFleet'), '', 'confirm_create_vehicule', $formquestion, 'yes', 1, 0, 700);
	}

	print '<table class="border centpercent">' . "\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';
	//affichage des tags de l'OR
	$key = 'categories';
	$val = $object->fields[$key];
	$sql = "SELECT DISTINCT label,code,color ";
	$sql .= "FROM llx_c_operationorder_tag ";
	$sql .= "WHERE active = 1";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$TAG[$obj->code] = $obj->label;
		}
	}

	print '<tr class="trcommonfield_categories"><td class="titlefield">';
	print '<table class="nobordernopadding centpercent">';
	print '<tr>';
	print '<td class="titlefield fieldname_' . $key . '">';
	if (!empty($val['help'])) print $form->textwithpicto($langs->trans($val['label']), $langs->trans($val['help']));
	else print $langs->trans($val['label']);
	print '</tr></table>';
	print '</td>';
	print '<td class="valuefield fieldname_' . $key . '">';
	print $form->multiselectarray($key, $TAG, explode(',', $object->{$key}));
	print '</td>';
	print '</tr>';

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
			print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
			print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
			print '<input type="hidden" name="action" value="update">';
			print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
			print '<input type="hidden" name="id" value="' . $object->id . '">';

			$head = operationorder_prepare_head($object);
			$picto = 'operationorder@operationorder';
			print dol_get_fiche_head($head, 'card', $langs->trans('OperationOrder'), 0, $picto);

			if ($conf->entity > 1) {
				unset($object->fields['notetheobald']);
			}

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
			if ($action_driver=="create") {
				require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
				$contact = new Contact($db);
				$formcompany = new FormCompany($db);
				$formquestion[] = array(
					'type' => 'text',
					'name' => 'lastname',
					'label' => $langs->trans('Lastname'),
					'enabled' => 1,
					'tdclass' => 'fieldrequired',
					'visible' => 1,
					'position' => 45
				);
				$formquestion[] = array(
					'type' => 'text',
					'name' => 'firstname',
					'label' => $langs->trans('Firstname'),
					'enabled' => 1,
					'tdclass' => 'fieldrequired',
					'visible' => 1,
					'position' => 50
				);
				$formquestion[] = array(
					'type' => 'text',
					'name' => 'poste',
					'label' => $langs->trans('PostOrFunction'),
					'enabled' => 1,
					'tdclass' => 'fieldrequired',
					'visible' => -1,
					'position' => 52
				);
				$formquestion[] = array(
					'type' => 'other',
					'name' => 'civility',
					'label' => $langs->trans('Civility'),
					'enabled' => 1,
					'tdclass' => 'fieldrequired',
					'visible' => 3,
					'position' => 60,
					'value' => $formcompany->select_civility(0, 'civility', 'maxwidth150', 1)
				);
				$formquestion[] = array(
					'type' => 'text',
					'name' => 'phone_mobile',
					'label' => $langs->trans('PhoneMobile'),
					'enabled' => 1,
					'tdclass' => 'fieldrequired',
					'visible' => 1,
					'position' => 100,
					'searchall' => 1
				);
				$formquestion[] = array(
					'type' => 'text',
					'name' => 'email',
					'label' => $langs->trans('Email'),
					'enabled' => 1,
					'tdclass' => 'fieldrequired',
					'visible' => 1,
					'position' => 110,
					'searchall' => 1
				);
				print $form->formconfirm(
					$_SERVER["PHP_SELF"] . '?id=' . $object->id,
					$langs->trans('NewDriver'),
					'',
					'confirm_create_driver_on_existing',
					$formquestion,
					'yes',
					1,
					0,
					700
				);
			}

			$object->fields['fk_soc']['visible'] = 2;
			$head = operationorder_prepare_head($object);
			$picto = 'operationorder@operationorder';
			print dol_get_fiche_head($head, 'card', $langs->trans('OperationOrder'), -1, $picto);

			$formconfirm = getFormConfirmOperationOrder($form, $object, $action);
			if (!empty($formconfirm)) print $formconfirm;


			$linkback = '<a href="' . dol_buildpath('/operationorder/list.php', 1) . '?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';

			$morehtmlref = '<div class="refidno">';

			// Ref bis
			$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $object->userCan($user, 'edit'), 'string', '', 0, 1);
			$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, $object->userCan($user, 'edit'), 'string', '', null, null, '', 1);

			// Thirdparty
			$morehtmlref .= '<br>' . $langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1);

			$morehtmlref .= '</div>';


			$morehtmlstatus = ''; //$object->getLibStatut(2); // pas besoin fait doublon
			dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, '', $morehtmlstatus);

			print '<div class="fichecenter">';

			print '<div class="fichehalfleft">'; // Auto close by commonfields_view.tpl.php
			print '<div class="underbanner clearboth"></div>';
			print '<table class="border tableforfield" width="100%">' . "\n";

			// Affichage Badge Check OR
			$permok = $usercancreate;
			$key = 'orcheck';
			$val = $object->fields['orcheck'];
			print '<tr class="trcommonfield_orcheck"><td class="titlefield">';
			print '<table class="nobordernopadding centpercent">';
			print '<tr>';
			print '<td class="titlefield fieldname_orcheck">';
			if (!empty($val['help'])) print $form->textwithpicto($langs->trans($val['label']), $langs->trans($val['help']));
			else print $langs->trans($val['label']);
			if ($user->admin && $permok && empty($val['noteditable']) && ($action != 'edit_attribute' || GETPOST('attribute') != 'orcheck')) {
				print '<td class="right"><a class="reposition editfielda" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=edit_attribute&attribute=orcheck&ignorecollapsesetup=1">' . img_edit() . '</a></td>';
			}
			print '</tr></table>';

			print '</td>';

			print '<td class="valuefield fieldname_orcheck">';
			if ($action == 'edit_attribute' && $permok && GETPOST('attribute', 'alpha') == 'orcheck') {
				print '<form enctype="multipart/form-data" action="' . $_SERVER["PHP_SELF"] . '" method="post" name="formextra">';
				print '<input type="hidden" name="action" value="update_attribute">';
				print '<input type="hidden" name="attribute" value="orcheck">';
				print '<input type="hidden" name="token" value="' . newToken() . '">';
				print '<input type="hidden" name="id" value="' . $object->id . '">';
				$object->fields['orcheck']['visible'] = 1;
				print $object->showInputField($val, 'orcheck', $object->orcheck, '', '', '', 0, $object->id, $object->table_element);
				$object->fields['orcheck']['visible'] = 0;
				print '<input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('Modify')) . '">';
				print '</form>';
			} else {
				if ($object->orcheck == 0) {
					$statusType = 'status0';
					$statusLabel = $langs->trans('ORchecknotdone');
					$statusLabelShort = $langs->trans('ORchecknotdone');
				} elseif ($object->orcheck == 1) {
					$statusType = 'status8';
					$statusLabel = $langs->trans('ORcheckfailled');
					$statusLabelShort = $langs->trans('ORcheckfailled');
				} elseif ($object->orcheck == 2) {
					$statusType = 'status4';
					$statusLabel = $langs->trans('ORcheckPassed');
					$statusLabelShort = $langs->trans('ORcheckPassed');
				}
				$params = array(
					'css' => 'badge-status',
				);

				print  dolGetBadge($statusLabel, '', $statusType, '', '', $params);
			}


			print '</td>';
			print '</tr>';
			$keyforbreak = 'fk_vehicule';
			// Common attributes
			include dol_buildpath('/operationorder/core/tpl/commonfields_view.tpl.php');

			// Other attributes
			include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

			//affichage des tags de l'OR
			$permok = $usercancreate;
			$key = 'categories';
			$val = $object->fields[$key];
			$sql = "SELECT DISTINCT label,code,color ";
			$sql .= "FROM llx_c_operationorder_tag ";
			$sql .= "WHERE active = 1";

			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$TAG[$obj->code] = $obj->label;
					$col[$obj->code] = $obj->color;
				}
			}

			print '<tr class="trcommonfield_categories"><td class="titlefield">';
			print '<table class="nobordernopadding centpercent">';
			print '<tr>';
			print '<td class="titlefield fieldname_' . $key . '">';
			if (!empty($val['help'])) print $form->textwithpicto($langs->trans($val['label']), $langs->trans($val['help']));
			else print $langs->trans($val['label']);
			if ($permok && empty($val['noteditable']) && ($action != 'edit_attribute' || GETPOST('attribute') != 'categories')) {
				print '<td class="right"><a class="reposition editfielda" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=edit_attribute&attribute=categories&ignorecollapsesetup=1">' . img_edit() . '</a></td>';
			}
			print '</tr></table>';
			print '</td>';

			print '<td class="valuefield fieldname_' . $key . '">';
			if ($action == 'edit_attribute' && $permok && GETPOST('attribute', 'none') == 'categories') {
				print '<form enctype="multipart/form-data" action="' . $_SERVER["PHP_SELF"] . '" method="post" name="formextra">';
				print '<input type="hidden" name="action" value="update_attribute">';
				print '<input type="hidden" name="attribute" value="' . $key . '">';
				print '<input type="hidden" name="token" value="' . newToken() . '">';
				print '<input type="hidden" name="id" value="' . $object->id . '">';
				print $form->multiselectarray($key, $TAG, explode(',', $object->{$key}));
				print '<input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('Modify')) . '">';
				print '</form>';
			} elseif (!empty($key) && !empty($object->{$key})) {
				$selected = explode(',', $object->{$key});
				foreach ($selected as $sel) {
					print  dolGetBadge($TAG[$sel], '', $col[$sel]);
				}
			}
			print '</td>';
			print '</tr>';

			print '</table>';

			print '</div></div>'; // Fin fichehalfright & ficheaddleft
			print '</div>'; // Fin fichecenter

			print '<div class="clearboth"></div><br />';

			/*
			 * Lines
			 */

			// JS nested
			$TNested = $object->fetch_all_children_nested();
			print '<div id="ajaxResults" ></div>';
			print '<div id="nestedLines" >';
			print _displaySortableNestedItems($TNested, 'sortableLists', true, $object->planned_date, $object);
			print '</div>';
			print '<script src="' . dol_buildpath('operationorder/js/jquery-sortable-lists.min.js', 1) . '" ></script>';
			print '<link rel="stylesheet" href="' . dol_buildpath('operationorder/css/sortable.css', 1) . '" >';

			if ($action == 'dialog-supplier-order' && !empty($lineid) && $user->hasRight("fournisseur", "commande", "creer")) {
				print _displayDialogSupplierOrder($lineid);
			}

			//$object->calcNeedAndDoRevertChangeInvoiceStatus($user);
			// ADD FORM
			if ($action != 'editline' && $object->userCan($user, 'edit')) {
				print '<div class="add-line-form-wrap" >';
				print '<div class="add-line-form-title" >';
				print $langs->trans("AddOperationOrderLine");
				print '</div>';
				print '<div class="add-line-form-body" >';
				print displayFormFieldsByOperationOrder($object);
				print '</div>';
				if ($action == 'addline' && $error >0) {
					print '<script>';
					print ' $(document).ready(function () {$("#fk_product").trigger("change");})';
					print '</script>';
				}
				print '</div>';
			} elseif ($action == 'editline' && $object->userCan($user, 'edit')) {
				$lineid = GETPOST('lineid', 'int');
				if (!empty($lineid)) {
					$line = new operationorderLine($db);
					$res = $line->fetch($lineid);

					print '<div id="dialog-form-edit" style="display: none;" >';
					print '<div id="edit-item_' . $line->id . '" class="edit-line-form-wrap" title="' . $line->ref . '" >';
					print '<div class="edit-line-form-body" >';
					if ($res > 0) {
						print displayFormFieldsByOperationOrder($object, $line, 0, true);
					} else {
						print $langs->trans('LineNotFound');
					}
					print '</div>';
					print '</div>';
					print '</div>';
				}
			}

			print '<div class="tabsAction">' . "\n";
			if ($action != 'editline') {
				$parameters = array();
				$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);    // Note that $action and $object may have been modified by hook
				if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				$actionUrl = $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;&token=' . newToken() . '&amp;action=';
				if (empty($reshook)) {
					$orderOK = explode(',', getDolGlobalString("OPERATIONORDER_ORDERABLE_STATUS"));
					if (in_array($object->objStatus->code, $orderOK) && $usercancreate) {
						if ($user->hasRight('fournisseur', 'commande', 'creer')) {
							print '<div class="inline-block divButAction"><a href="javascript:supplierorderpiece()" class="butAction">' . $langs->trans('Commandepiece') . '</a></div>';
						}
						print '<div class="inline-block divButAction"><a href="javascript:debitpart()" class="butAction">' . $langs->trans('debit-part') . '</a></div>';
					}

					// status disponible
					if (!empty($status->TStatusAllowed)) {
						foreach ($status->TStatusAllowed as $fk_status) {
							$statusAllowed = new OperationOrderStatus($db);
							$res = $statusAllowed->fetch($fk_status);
							if ($res > 0) {
								$userCan = $object->checkNegativeProductVentilation($statusAllowed->code) ? $statusAllowed->userCan($user, 'changeToThisStatus') : false;
								$act = 'setStatus';
								if (empty($statusAllowed->array_options['options_require_conf'])) {
									$act = 'confirm_setStatus';
								}
								if ($object->objStatus->code == getDolGlobalString("OPERATIONORDER_STATUT_BEFORE_CHECK")  && $object->orcheck < 2 && $statusAllowed->code == getDolGlobalString("OPERATIONORDER_STATUT_AFTER_CHECK") ) {
									print '<div class="inline-block divButAction"><a href="javascript:orcheck()" class="butAction">' . $langs->trans('orcheck') . '</a></div>';
								} elseif ($userCan) {
									if ($statusAllowed->code != 'CLOSED' || $object->total_ht==0) {
										print dolGetButtonAction($statusAllowed->label, '', 'default', $actionUrl . $act . '&fk_status=' . $fk_status, '', $object->userCan($user, 'edit'));
									}
								}
							}
						}
					}

					// Create Invoice
					if (empty($object->orcheck) && !empty($TNested)) {
						print dolGetButtonAction($langs->trans("CreatePropal"), '', 'default', $actionUrl . 'create_propal_from_or', '', $user->hasRight('propal', 'creer'));
					}

					// Create Invoice
					if ($object->orcheck == 2 && getDolGlobalString('OPERATIONORDER_STATUT_FOR_INVOICE') == $object->objStatus->code) {
						print dolGetButtonAction($langs->trans("CreateBill"), '', 'default', $actionUrl . 'create_invoice_from_or', '', $user->hasRight('facture', 'creer'));
					}

					// modifiy
					print dolGetButtonAction($langs->trans("OperationOrderModify"), '', 'default', $actionUrl . 'edit', '', $object->userCan($user, 'edit'));

					// Clone
					//print dolGetButtonAction($langs->trans("OperationOrderClone"), '', 'default', $actionUrl . 'clone', '', $user->hasRight("operationorder","write"));

					// Split
					print dolGetButtonAction($langs->trans("OperationOrderSplit"), '', 'default', $actionUrl . 'split', '', $object->userCan($user, 'edit'));

					print dolGetButtonAction($langs->trans("OperationOrderDelete"), '', 'danger', $actionUrl . 'delete', '', $user->hasRight("operationorder", "delete"));
				}
			}
			print '</div>' . "\n";

			print '<div class="fichecenter"><div class="fichehalfleft">';
			print '<a name="builddoc"></a>'; // ancre

			// Documents generes
			$filename = dol_sanitizeFileName($object->ref);
			$filedir = $conf->operationorder->multidir_output[$object->entity] . '/' . dol_sanitizeFileName($object->ref);
			$urlsource = $_SERVER['PHP_SELF'] . '?id=' . $object->id;
			$genallowed = $usercanread;
			$delallowed = $usercancreate;

			print $formfile->showdocuments('operationorder', $filename, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $object->thirdparty->default_lang, '', $object);
			$somethingshown = $formfile->numoffiles;

			// Show links to link elements
			//$linktoelem = $form->showLinkToObjectBlock($object, null, array($object->element));
			$somethingshown = $form->showLinkedObjectBlock($object, '');
			print '<script type="text/javascript">
							 $(document).ready(function() {
								$("a[href*=\'action=dellink\'][href*=\'dellinkid=\'].reposition").hide();
							 });
					 </script>';

			print '</div><div class="fichehalfright"><div class="ficheaddleft">';

			// List of actions on element
			include_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
			$formactions = new FormActions($db);
			$somethingshown = $formactions->showactions($object, $object->element, $object->socid, 1);

			print '</div></div></div>';

			print dol_get_fiche_end(-1);
		}
	}
}


llxFooter();
include dol_buildpath('/operationorder/scripts/operationorder_card.scripts.php');
$db->close();

/**
 * @param int $lineid line Id
 * @return void
 */
function _displayDialogSupplierOrder($lineid)
{
	global $langs, $db, $user, $conf, $object, $form;

	$line = new operationorderLine($object->db);
	$res = $line->fetch($lineid);

	print '<div id="dialog-supplier-order" style="display: none;" >';
	print '<div id="dialog-supplier-order-item_' . $lineid . '" class="dialog-supplier-order-form-wrap" title="' . $line->ref . '" >';
	print '<div class="dialog-supplier-order-form-body" >';
	if ($res > 0) {
		// here the form


		// Ancors
		$actionUrl = '#item_' . $line->id;
		$outForm = '<form name="create-supplier-order-form" action="' . $_SERVER["PHP_SELF"] . $actionUrl . '" method="POST">' . "\n";
		$outForm .= '<input type="hidden" name="token" value="' . newToken() . '">' . "\n";
		$outForm .= '<input type="hidden" name="id" value="' . $object->id . '">' . "\n";
		$outForm .= '<input type="hidden" name="lineid" value="' . $line->id . '">' . "\n";
		$outForm .= '<input type="hidden" name="action" value="create-supplier-order">' . "\n";

		$outForm .= '<table class="table-full">';

		// Cette partie permet une evolution du formulaire de creation de commandes fournisseur
		$supplierOrder = new CommandeFournisseur($object->db);
		$supplierOrder->fields['fk_soc']['label'] = 'Supplier';
		$TSupplierOrderFields = array('fk_soc');
		foreach ($TSupplierOrderFields as $key) {
			$outForm .= getFieldCardOutputByOperationOrder($supplierOrder, $key, '', '', 'order_');
		}

		$supplierOrderLine = new CommandeFournisseurLigne($object->db);
		// Bon les champs sont pas définis... mais ils le serons un jour non ?
		$supplierOrderLine->fields = array(
			//'fk_product' => array ( 'type' => 'integer:Product:product/class/product.class.php:1', 'required' => 1, 'label' => 'Product', 'enabled' => 1, 'position' => 35, 'notnull' => -1, 'visible' => -1, 'index' => 1  ),
			'subprice' => array('type' => 'real', 'label' => 'UnitPrice', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'required' => 1, 'visible' => 1),
			'desc' => array('type' => 'html', 'label' => 'Description', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 3),
			'qty' => array('type' => 'real', 'required' => 1, 'label' => 'Qty', 'enabled' => 1, 'position' => 45, 'notnull' => 1, 'visible' => 1, 'isameasure' => '1', 'css' => 'maxwidth75imp'),
			'product_type' => array('type' => 'select', 'required' => 1, 'label' => 'ProductType', 'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 1, 'arrayofkeyval' => array('0' => "Product", '1' => "Service")),
			'tva_tx' => array('type' => 'real', 'required' => 1, 'label' => 'TVA', 'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 1, 'fieldCallBack' => '_showVatField'),
		);

		if (!empty(getDolGlobalString("OPODER_SUPPLIER_ORDER_LIMITED_TO_SERVICE"))) {
			$supplierOrderLine->fields['product_type']['visible'] = 0;
			$outForm .= '<input type="hidden" name="orderline_product_type" value="1">' . "\n";
		}

		//$TSupplierOrderLineFields = array('product_type', 'subprice', 'tva_tx', 'qty', 'desc');
		//Add same product as the poduct/service on the current line
		$TSupplierOrderLineFields = array('desc');

		$params = array(
			'operationorderLine' => $line
		);

		foreach ($TSupplierOrderLineFields as $key) {
			$outForm .= getFieldCardOutputByOperationOrder($supplierOrderLine, $key, '', '', 'orderline_', '', '', $params);
		}

		$outForm .= '</table>';
		$outForm .= '</form>';


		print $outForm;
	} else {
		print $langs->trans('LineNotFound');
	}
	print '</div>';
	print '</div>';
	print '</div>';


	// Creation Supplier Order
	print '
	<script type="text/javascript">
	$(function()
	{
		var cardUrl = "' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '";
		var itemHash = "#item_' . $line->id . '";

		var dialogBox = jQuery("#dialog-supplier-order");
		var width = $(window).width();
		var height = $(window).height();
		if(width > 700){ width = 700; }
		if(height > 600){ height = 600; }
		//console.log(height);
		dialogBox.dialog({
            autoOpen: true,
            resizable: true,
            //		height: height,
            title: "' . dol_escape_js($langs->transnoentitiesnoconv('CreateSupplierOrder')) . '",
            width: width,
            modal: true,
            buttons: {
                "' . $langs->transnoentitiesnoconv('Create') . '": function() {
                    dialogBox.find("form").submit();
                },
                "' . $langs->transnoentitiesnoconv('Cancel') . '": function() {
                    dialogBox.dialog( "close" );
                }
            },
            close: function( event, ui ) {
                window.location.replace(cardUrl + itemHash);
            },
            open: function(){
                // center dialog verticaly on open
                $([document.documentElement, document.body]).animate({
                    scrollTop: $("#dialog-supplier-order").offset().top - 50 - $("#id-top").height()
                }, 300);
            }
		});

		dialogBox.dialog( "open" );

	});
	</script>';
}

/**
 * Return display of HTML lines
 * @param array $TNested array of child line
 * @param string $htmlId HTML Id
 * @param bool $open is open
 * @param string $planned_date planned start date
 * @param OperationOrder|null $or current OR
 * @return string
 */
function _displaySortableNestedItems($TNested, $htmlId = '', $open = true, $planned_date = '', OperationOrder $or = null)
{
	global $langs, $user, $db, $extrafields, $conf;
	if (!empty($TNested) && is_array($TNested)) {
		$TLineQtyUsed = $or->getAlreadyUsedQtyLines();
		$TLastLinesByProduct = $or->getLastLinesByProduct();
		$out = '<ul id="' . $htmlId . '" class="operation-order-sortable-list" >';
		foreach ($TNested as $k => $v) {
			$line = $v['object'];
			/**
			 * @var $line operationorderLine
			 */

			if (empty($line->id)) $line->id = $line->rowid;

			$class = '';
			if ($open) {
				$class .= 'sortableListsClosed';
			}

			// Product
			$label = $line->description;
			$line->product_label = '';
			$availableForSupplierOrder = false;
			$product = new Product($line->db);
			if ($line->fk_product > 0) {
				$product->fetch($line->fk_product);
				$product->ref = $line->ref; //can change ref in hook
				$product->label = $line->label; //can change label in hook
				$label = $product->getNomUrl(1) . ' - ' . $product->label;

				if (!empty($product->array_options['options_oorder_available_for_supplier_order'])) {
					$availableForSupplierOrder = true;
				}

				$line->product_label = $product->label;
				$line->stock_reel = $product->stock_reel;
				$line->stock_theorique = $product->stock_theorique;
			}

			$out .= '<li id="item_' . $line->id . '" class="operation-order-sortable-list__item ' . $class . '" ';
			$out .= ' data-id="' . $line->id . '" ';
			$out .= ' data-ref="' . dol_escape_htmltag($line->ref) . '" ';
			$out .= ' data-rank="' . dol_escape_htmltag($line->rang) . '" ';
			$out .= ' data-parent="' . intval($line->fk_parent_line) . '" ';
			$out .= ' data-product_type="' . intval($product->type) . '" ';
			$out .= ' data-is_job="' . intval($product->array_options['options_or_is_job']) . '" ';
			$out .= '>';
			$out .= '<div class="operation-order-sortable-list__item__title">';
			$out .= '	<div class="operation-order-sortable-list__item__title__flex">';

			// DESCRIPTION
			$out .= '		<div class="operation-order-sortable-list__item__title__col -description">';
			$out .= '			<div class="line-description-label">' . $label . '</div>';
			// Add description
			if ($line->fk_product > 0 && !empty(getDolGlobalString("PRODUIT_DESC_IN_FORM"))) {
				if (!empty($line->description) && $line->description != $line->product_label) {
					$out .= '	<div class="line-description">' . dol_htmlentitiesbr($line->description) . '</div>';
				}
			}
			$out .= '		</div>';

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
			$out .= '		<div class="operation-order-sortable-list__item__title__col -qty-ordered">';
			if (!empty($qtyUsed)) $out .= '<span class="' . $textClass . 'classfortooltip" title="' . $langs->trans("QtyUsed") . '" >' . $iconInfo . '<i class="fas fa-box-open"></i>' . $qtyUsed . '</span> / ';
			if (empty($product->array_options['options_or_is_job'])) {
				$out .= '		<span class=" classfortooltip" title="' . $langs->trans("QtyPlanned") . '" ><i class="fas fa-box-open"></i>' . $line->qty . '</span>';
			} else {
				$out .= '		<span class=" classfortooltip" title="' . $langs->trans($line->fields['fk_c_operationorder_type']['label']) . '" >' . $line->showOutputField($line->fields['fk_c_operationorder_type'], 'fk_c_operationorder_type', $line->fk_c_operationorder_type) . '</span>';
			}
			$out .= '		</div>';


			// TIME SPENT AND PLANNED
			$out .= '		<div class="operation-order-sortable-list__item__title__col -time-spent">';
			$canHasChild = $product->hasFatherOrChild(1);
			if (!empty($product->type) &&
				(!empty($product->array_options['options_or_scan']) || $canHasChild)) {
				$out .= '			<i class="far fa-hourglass"></i> ';

				$hoursSpendClass = '';
				if ((int) ($line->time_planned) < (int) ($line->time_spent)) {
					$hoursSpendClass = 'badge badge-danger';
				}

				$out .= '			<span class="classfortooltip ' . $hoursSpendClass . '"  title="' . $langs->trans("HoursSpent") . '">';
				if (!empty($line->time_spent)) {
					$out .= convertSecondToTime((int) ($line->time_spent));
				} else {
					$out .= ' -- ';
				}

				$out .= '			</span>';
				$out .= ' / ';
				$out .= '			<span class="classfortooltip"  title="' . $langs->trans("HoursPlanned") . '">';
				if (!empty($line->time_planned)) {
					$out .= convertSecondToTime((int) ($line->time_planned));
				} else {
					$out .= ' -- ';
				}
				$out .= '			</span>';
			}

			$out .= '		</div>';

			// ECART
			$out .= '		<div class="operation-order-sortable-list__item__title__col -difference">';
			$out .= '		<span class="' . $textClass . ' classfortooltip paddingrightonly" title="' . $langs->trans('TimeDifference') . '" >';
			if (!empty($product->type) &&
				(!empty($product->array_options['options_or_scan']) || $canHasChild)) {
				if (!empty($line->time_planned) && !empty($line->time_spent)) {
					$ecart = intval($line->time_planned) - intval($line->time_spent);
					$sign = '';
					if ($ecart > 0) {
						$textClass = "text-success";
						$iconClass = "fa-caret-down";
						$sign = '-';
					} elseif ($ecart == 0) {
						$textClass = "text-warning";
						$iconClass = "fa-caret-left";
					} else {
						$textClass = "text-danger";
						$iconClass = "fa-caret-up";
						$sign = '+';
					}

					$out .= '<i class="fa ' . $iconClass . '"></i> ' . $sign . dol_print_date(abs($ecart), '%H:%M', true);
				} else {
					$out .= ' -- ';
				}
			}
			$out .= '</span></div>';

			if (empty($product->array_options['options_or_is_job'])) {
				// EMPLACEMENT
				$out .= '		<div class="operation-order-sortable-list__item__title__col -stock-status">';
				if (empty($product->array_options['options_or_is_job'])) {
					$out .= $line->showOutputFieldQuick('fk_warehouse');
				}
				$out .= '		</div>';

				// STOCK
				$out .= '		<div class="operation-order-sortable-list__item__title__col -stock-status">';
				$out .= $line->stockStatus('', '', array('planned_date' => $planned_date, 'fk_warehouse' => $line->fk_warehouse), 'warehouseopen');
				// display object linked on line
				if (!array_key_exists('operationorderdet', $conf->modules)) {
					$conf->modules['operationorderdet'] = 1;
				}
				$line->fetchObjectLinked();
				if (!empty($line->linkedObjects)) {
					$out .= '		<div class="operation-order-det-element-element">';
					foreach ($line->linkedObjects as $keyObjecttype => $objecttype) {
						if ($keyObjecttype !== 'facture') {
							foreach ($objecttype as $linkedObject) {
								if (is_callable(array($linkedObject, 'getNomUrl'))) {
									$out .= $linkedObject->getNomUrl(1) . ' ';
								}
								if (is_callable(array($linkedObject, 'getLibStatut'))) {
									$out .= $linkedObject->getLibStatut(3) . ' ';
								}
							}
						}
					}
					$out .= '		</div>';
				}

				// Display creation supplyer order link
				if ($availableForSupplierOrder && empty($line->linkedObjects['order_supplier'])) {
					// action to open dialog box
					$url = dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $or->id;
					$url .= '&action=dialog-supplier-order&lineid=' . $line->id;
					$url .= '#item_' . $line->id;


					$out .= '		<div class="operation-order-det-element-element-action-btn">';
					$out .= '           <a class="button-xs" href="' . $url . '" ><i class="fa fa-plus"></i> ' . $langs->trans('CreateSupplierOrder') . '</a>';
					$out .= '		</div>';
				}

				$out .= '		</div>';
			}

			if (empty($product->array_options['options_or_is_job'])) {
				// PU HT
				$out .= '		<div class="operation-order-sortable-list__item__title__col -unit-price">';
				$out .= price($line->price) . '&nbsp;' . $langs->trans('HT');
				$out .= '		</div>';


				$out .= '		<div class="operation-order-sortable-list__item__title__col -remise_percent">';
				$out .= '<span class="classfortooltip" title="' . $langs->trans($line->fields['remise_percent']['label']) . '" >&nbsp;';
				$out .= price($line->remise_percent, 0, $langs, 1, -1, 2) . '&nbsp;%';
				$out .= '</span>';
				$out .= '</div>';


				if (!empty($line->linkedObjects)) {
					$out .= '<div class="operation-order-sortable-list__item__title__col -det-element-element">';
					foreach ($line->linkedObjects as $keyObjecttype => $objecttype) {
						if ($keyObjecttype == 'facture') {
							foreach ($objecttype as $linkedObject) {
								if (is_callable(array($linkedObject, 'getNomUrl'))) {
									$out .= $linkedObject->getNomUrl(1) . ' ';
								}
								if (is_callable(array($linkedObject, 'getLibStatut'))) {
									$out .= $linkedObject->getLibStatut(3) . ' ';
								}
							}
						}
					}
					$out .= '		</div>';
				}
			} else {
				$out .= '		<div class="operation-order-sortable-list__item__title__col -total_ht_mo">
									<span class="classfortooltip" title="' . $langs->trans($line->fields['total_ht_mo']['label']) . '" ><i class="fas fa-hands"></i>&nbsp;';
				$out .= price($line->total_ht_mo) . '&nbsp;' . $langs->trans('HT');
				$out .= '		</span></div>';

				$out .= '		<div class="operation-order-sortable-list__item__title__col -total_ht_part">
									<span class="classfortooltip" title="' . $langs->trans($line->fields['total_ht_part']['label']) . '" ><i class="fas fa-puzzle-piece"></i>&nbsp;';
				$out .= price($line->total_ht_part) . '&nbsp;' . $langs->trans('HT');
				$out .= '		</span></div>';

				$out .= '		<div class="operation-order-sortable-list__item__title__col -total_ht_external">
							<span class="classfortooltip" title="' . $langs->trans($line->fields['total_ht_external']['label']) . '" ><i class="fas fa-external-link-square-alt"></i>&nbsp;';
				$out .= price($line->total_ht_external) . '&nbsp;' . $langs->trans('HT');
				$out .= '		</span></div>';

				$out .= '		<div class="operation-order-sortable-list__item__title__col -total_ht_reimbursement">
							<span class="classfortooltip" title="' . $langs->trans($line->fields['total_ht_reimbursement']['label']) . '" ><i class="fas fa-money-bill-alt"></i>&nbsp;';
				$out .= price($line->total_ht_reimbursement) . '&nbsp;' . $langs->trans('HT');
				$out .= '		</span></div>';


				if (!array_key_exists('operationorderdet', $conf->modules)) {
					$conf->modules['operationorderdet'] = 1;
				}
				$line->fetchObjectLinked();
				if (!empty($line->linkedObjects)) {
					$out .= '<div class="operation-order-sortable-list__item__title__col -det-element-element">';
					foreach ($line->linkedObjects as $keyObjecttype => $objecttype) {
						foreach ($objecttype as $linkedObject) {
							if (is_callable(array($linkedObject, 'getNomUrl'))) {
								$out .= $linkedObject->getNomUrl(1) . ' ';
							}
							if (is_callable(array($linkedObject, 'getLibStatut'))) {
								$out .= $linkedObject->getLibStatut(3) . ' ';
							}
						}
					}
					$out .= '		</div>';
				}
			}

			// TOTAL HT
			$out .= '		<div class="operation-order-sortable-list__item__title__col -total-price">
				<span class="classfortooltip" title="' . $langs->trans($line->fields['total_ht']['label']) . '" >&nbsp;';
			$out .= price($line->total_ht) . '&nbsp;' . $langs->trans('HT');
			$out .= '		</span></div>';


			// ACTIONS
			$out .= '		<div class="operation-order-sortable-list__item__title__col -action">';
			if ($or->userCan($user, 'edit')) {
				$linked = false;
				/*$openp_id = 0;
				$sql = "SELECT op.rowid FROM " . $db->prefix() . "dolifleet_vehicule_operation_np as op";
				$sql .= " WHERE op.fk_vehicule=" . $or->fk_vehicule;
				$sql .= " AND op.fk_product=" . $line->fk_product;
				$resql = $or->db->query($sql);
				if (!$resql) {
					setEventMessages($or->db->lasterror, null, 'errors');
				} else {
					$linked = $or->db->num_rows($resql);
					if ($linked) {
						$objopenp = $or->db->fetch_object($resql);
						$openp_id = $objopenp->rowid;
					}
				}*/

				$editUrl = dol_buildpath('operationorder/operationorder_card.php', 1) . '?id=' . $line->fk_operation_order . '&amp;action=editline&amp;lineid=' . $line->id;

				//#item_'.$line->id.'
				$out .= '<a href="' . $editUrl . '" class="classfortooltip operation-order-sortable-list__item__title__button -edit-btn" title="' . $langs->trans("Edit") . '" data-id="' . $line->id . '">';
				$out .= '<i class="fas fa-pencil-alt"></i>';
				$out .= '</a>';

				$deleteUrl = dol_buildpath('operationorder/operationorder_card.php', 1) . '?id=' . $line->fk_operation_order . '&amp;action=ask_deleteline&amp;lineid=' . $line->id;

				$out .= '<a href="' . $deleteUrl . '" class="classfortooltip operation-order-sortable-list__item__title__button  -delete-btn"  title="' . $langs->trans("Delete") . '"  data-id="' . $line->id . '">';
				$out .= '<i class="fa fa-trash "></i>';
				$out .= '</a>';

				// Handler icon
				$out .= '<span class="operation-order-sortable-list__item__title__button handle move"><i title="' . $langs->trans("Move") . '" class="fa fa-th"></i></span>';
			}

			$out .= '		</div>';

			$out .= '	</div>';

			$out .= '</div>';
			$out .= _displaySortableNestedItems($v['children'], '', $open, $planned_date, $or);
			$out .= '</li>';
		}
		$out .= '</ul>';
		return $out;
	} else {
		return '';
	}
}

/**
 * Return HTML string to put an input field into a page
 * Code very similar with showInputField of extra fields
 *
 * @param CommonObject $object Current Object
 * @param array $val Array of properties for field to show (used only if ->fields not defined)
 * @param string $key Key of attribute
 * @param string $value Preselected value to show (for date type it must be in timestamp format, for amount or price it must be a php numeric value)
 * @param string $moreparam To add more parameters on html input tag
 * @param string $keysuffix Prefix string to add into name and id of field (can be used to avoid duplicate names)
 * @param string $keyprefix Suffix string to add into name and id of field (can be used to avoid duplicate names)
 * @param string|int $morecss Value for css to define style/length of field. May also be a numeric.
 * @param int $nonewbutton Force to not show the new button on field that are links to object
 * @param array $params Array of params
 * @return string
 */
function _showVatField($object, $val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = 0, $nonewbutton = 0, $params = array())
{
	global $form, $mysoc, $conf;

	$tva_tx = !empty($params['operationorderLine']->tva_tx) ? $params['operationorderLine']->tva_tx : '20'; // TODO : add module default value selection to replace 20
	$tva_tx = empty($value) ? $tva_tx : $value;
	return $form->load_tva($keyprefix . $key . $keysuffix, $tva_tx, $mysoc);
}
