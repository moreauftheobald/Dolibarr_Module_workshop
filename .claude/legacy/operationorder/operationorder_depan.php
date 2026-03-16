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

require 'config.php';
dol_include_once('/core/lib/functions.lib.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/product/class/product.class.php');
dol_include_once('/fourn/class/fournisseur.commande.class.php');
dol_include_once('operationorder/class/operationorder.class.php');
dol_include_once('operationorder/class/operationorderaction.class.php');
dol_include_once('operationorder/lib/operationorder.lib.php');
dol_include_once('/core/lib/date.lib.php');
dol_include_once('dolifleet/class/vehicule.class.php');
dol_include_once('dolifleet/class/dictionaryContractType.class.php');

global $mysoc,$db,$langs,$hookmanager,$conf;

if (!$user->hasRight("operationorder", "read")) accessforbidden();

$langs->loadLangs(array('operationorder@operationorder', 'orders', 'companies', 'bills', 'products', 'other','supplier'));

$action = GETPOST('action', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$action_veh = GETPOST('action_veh', 'alpha');
$fk_product = GETPOST('fk_product');
$product_desc = (GETPOST('desc') ? GETPOST('desc') : '');
$product_desc = preg_replace("/[\r\n]+/", "\n", $product_desc);
$price = GETPOST('subprice');

$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'operationorderdepancard';   // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');

$object = new OperationOrder($db);

$result = restrictedArea($user, $object->element, '', $object->table_element.'&'.$object->element);

$hookmanager->initHooks(array($contextpage, 'globalcard'));

if ($object->isextrafieldmanaged) {
	$extrafields = new ExtraFields($db);
	$extralabels = $extrafields->fetch_name_optionals_label($object->table_element);
	$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');
}

$usercanread = $user->hasRight("operationorder", "read");
$usercancreate = $permissionnote = $permissiontoedit = $permissiontoadd = $permissiondellink = $object->userCan($user, 'edit'); // Used by the include of actions_setnotes.inc.php

/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

// Si vide alors le comportement n'est pas remplacé
if (empty($reshook)) {
	if ($cancel) {
		if (! empty($backtopage)) {
			header("Location: ".$backtopage);
			exit;
		}
		$action='';
	}

	// For object linked
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';		// Must be include, not include_once

	$error = 0;
	switch ($action) {
		case 'confirm_create_vehicule':
			$vehicule = new Vehicule($db);
			$vehicule->setValues($_REQUEST);
			$vehicule->date_end_contract=null;
			$vehicule->status=$vehicule::STATUS_ACTIVE;
			$res = $vehicule->save($user);

			if ($res < 0) {
				setEventMessage($vehicule->errors, 'errors');
				$action = 'create';
				$action_veh= 'create';
				break;
			} else {
				header('Location: ' . $_SERVER["PHP_SELF"] . '?action=create&fk_vehicule=' . $vehicule->id.'&fk_soc=' . $vehicule->fk_soc.'&km_on_creation='.$vehicule->km);
				exit;
			}
		case 'add':
			if (!empty(GETPOST('fk_vehicule'))) {
				$vehicule = new Vehicule($db);
				$res = $vehicule->fetch(GETPOST('fk_vehicule'), false);
				$vehicule->fetch_thirdparty($vehicule->fk_soc);
				if ($res>0) {
					$dictCT = New dictionaryContractType($object->db);
					$dictCT->fetch($vehicule->fk_contract_type);
				}
			}
			if (!empty(isModEnabled("multicurrency"))) {
				require_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';
				$object->fk_multicurrency = MultiCurrency::getIdFromCode($object->db, $conf->currency);
				$object->multicurrency_code = $conf->currency;
			}
			if (empty($fk_product) || $fk_product=='-1') {
				setEventMessage($langs->trans('ErrorFieldRequired', 'Operation'), 'errors');
				$error++;
			}
			if (!GETPOSTISSET('supplier_fk_soc') || empty(GETPOST('supplier_fk_soc'))) {
				setEventMessage($langs->trans('ErrorFieldRequired', 'Supplier'), 'errors');
				$error++;
			}
			if (!GETPOSTISSET('subprice') || empty(GETPOST('subprice'))) {
				setEventMessage($langs->trans('ErrorFieldRequired', 'UnitPrice'), 'errors');
				$error++;
			}
			if (!GETPOSTISSET('desc') || empty(GETPOST('desc'))) {
				setEventMessage($langs->trans('ErrorFieldRequired', 'Description'), 'errors');
				$error++;
			}

			$object->setValues($_REQUEST); // Set standard attributes
			if (isset($_REQUEST['categories'])) {
				$object->categories = implode(',', $_REQUEST['categories']);
			}
			if ($object->isextrafieldmanaged) {
				$ret = $extrafields->setOptionalsFromPost($extralabels, $object);
				if ($ret < 0) $error++;
			}

			if ($error > 0) {
				$action = 'create';
				break;
			}

			$res = $object->save($user);

			if ($res <= 0) {
				setEventMessage($object->errors, 'errors');
				if (empty($object->id)) $action = 'create';
				else $action = 'edit';
			} else {
				$langs->load('errors');
				$error = 0;
				$product = new Product($db);
				$res = $product->fetch($fk_product);
				if ($res<=0) {
					setEventMessage($object->errors, 'errors');
					if (empty($object->id)) $action = 'create';
					header('Location: '.dol_buildpath('/operationorder/operationorder_depan.php', 1).'?id='.$object->id);
					exit;
				} else {
					try {
						$lineid = addLineAndChildToOR(
							$object,
							 $fk_product,
							 1,
							 $price,
							 $product->type,
							 $product_desc,
							 '',
							 0,
							 0,
							 0,
							 0,
							 null,
							 0,
							 null,
							 null,
							 $product->label,
							 false
						);
					} catch (Exception $ex) {
						setEventMessage($ex->getMessage(), 'errors');
						header('Location: '.dol_buildpath('/operationorder/operationorder_depan.php', 1).'?id='.$object->id);
						exit;
					}

					$stat=array();
					$sql = "SELECT rowid FROM " .$db->prefix() . "operationorder_status WHERE CODE='PLANNED' AND entity = " .$object->entity;
					$resql = $db->query($sql);
					if ($resql) {
						$obj = $db->fetch_object($resql);
						$Stat['PLANNED'] = $obj->rowid;
					}

					$sql = "SELECT rowid FROM " .$db->prefix() . "operationorder_status WHERE CODE='EXT' AND entity = " .$object->entity;
					$resql = $db->query($sql);
					if ($resql) {
						$obj = $db->fetch_object($resql);
						$Stat['EXT'] = $obj->rowid;
					}
					$resultSetStatus = $object->setStatus($user, $Stat['PLANNED']);
					if ($resultSetStatus<0) {
						setEventMessages($object->error, $object->errors, 'errors');
					}
					//                  if (!empty($object->planned_date)) {
					//                      $res = $object->createOperationOrderAction($object->planned_date, null, null);
					//                      if ($res<0) {
					//
					//                      }
					//                  }

					if ($object->planned_date<=dol_now()) {
						$object->setStatus($user, $Stat['EXT']);
					}

					if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
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

					$error = 0;

					$supplierOrder = new CommandeFournisseur($object->db);
					$supplierOrder->ref_supplier = $object->ref;
					$supplierOrder->socid = GETPOST('supplier_fk_soc');

					$supplierOrder->model_pdf = 'cornas';

					$supplierOrderLine = new CommandeFournisseurLigne($object->db);

					$supplierOrderLine->product_type =$product->type;
					$supplierOrderLine->fk_product = $fk_product;
					$supplierOrderLine->pu_ht = $price;
					$supplierOrderLine->subprice = $price;
					$supplierOrderLine->qty = 1;
					$supplierOrderLine->tva_tx = $product->tva_tx;
					$supplierOrderLine->desc = $product_desc;
					$supplierOrder->lines[] = $supplierOrderLine;

					// create new supplier order
					$resSupplierOrder = $supplierOrder->create($user);
					if ($resSupplierOrder>0) {
						$supplierOrder->add_object_linked('operationorder', $object->id); // and link to object to be displayed un document
						$supplierOrder->add_object_linked('operationorderdet', $object->lines[0]->id);// and link to line origin for user interface

						if (!empty(getDolGlobalString("OPODER_SUPPLIER_ORDER_AUTO_VALIDATE"))) {
							$supplierOrder->valid($user);
							$supplierOrder->approve($user, 0, 0);
						}

						$supplierOrder->array_options['options_supplier_order_type'] = '1';
						if ($supplierOrder->insertExtraFields() < 0) {
							setEventMessage($supplierOrder->error, 'errors');
						}

						if (is_object($vehicule)) {
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

							$supplierOrder->update_note($supplierOrder->note_public . $noteAppend, '_public');
						}
						if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
							// Define output language
							$outputlangs = $langs;
							$newlang = '';
							if (getDolGlobalString("MAIN_MULTILANGS")  && empty($newlang) && GETPOST('lang_id', 'aZ09'))
								$newlang = GETPOST('lang_id', 'aZ09');
							if (getDolGlobalString("MAIN_MULTILANGS")  && empty($newlang))
								$newlang = $object->thirdparty->default_lang;
							if (!empty($newlang)) {
								$outputlangs = new Translate("", $conf);
								$outputlangs->setDefaultLang($newlang);
							}

							$ret = $supplierOrder->fetch($supplierOrder->id); // Reload to get new records
						}

						$supplierOrder->model_pdf = 'cornas';

						$sql = 'UPDATE ' . $db->prefix() . $supplierOrder->table_element . ' SET model_pdf=\'' . $supplierOrder->model_pdf . '\' WHERE rowid=' . $supplierOrder->id;
						$resql = $object->db->query($sql);
						$supplierOrder->generateDocument($supplierOrder->model_pdf, $outputlangs, 0, 0, 0);

						$url = dol_buildpath('/operationorder/operationorder_card.php', 1).'?id='.$object->id;
						header('Location: ' . $url);
						exit;
					} else {
						$error++;
						setEventMessage($langs->trans('SupplierOrderSaveError').' : '.$supplierOrder->error, 'errors');
						if (!empty($supplierOrder->errors)) {
							setEventMessage($supplierOrder->errors, 'errors');
						}
					}
				}

				header('Location: '.dol_buildpath('/operationorder/operationorder_card.php', 1).'?id='.$object->id);
				exit;
			}
	}
}

/**
 * View
 */
$form = new Form($db);

$title=$langs->trans('NewOperationOrderDepan');
$arrayofjs = '';
$arrayofcss = array(
	'/operationorder/css/operation-order-card.css.php',
	'/operationorder/css/animate.css'
);
llxHeader('', $title, '', '', 0, 0, $arrayofjs, $arrayofcss);

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

print load_fiche_titre($langs->trans('NewOperationOrderDepan'), '', 'operationorder@operationorder');
$object->fields['fk_vehicule']['position']=1;
$object->fields['fk_soc']['position']=2;
$object->fields['fk_c_operationorder_type']['position']=3;
$object->fields['km_on_creation']['position']=4;
$object->fields['date_operation_order']['position']=5;
$object->fields['planned_date']['position']=6;
$object->fields['time_planned_f']['visible']=0;
$object->fields['fk_user_meca']['visible']=0;
$object->fields['fk_conducteur']['position']=9;
$object->fields['ref_client']['visible']=0;
$object->fields['fk_project']['visible']=0;
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="add">';
print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';

print '<table class="border centpercent">'."\n";

// Common attributes
include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_add.tpl.php';

// Other attributes
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';

//Affichage de la liste de fournisseurs

$supplierOrder = new CommandeFournisseur($object->db);
$supplierOrder->fields['fk_soc']['label']='Supplier';

print '<tr class="trcommonfield_categories"><td class="titlefield">';
print '<table class="nobordernopadding centpercent">';
print '<tr>';
print '<td class="titlefield fieldname_fk_soc fieldrequired">';
print $langs->trans('Supplier');
print '</tr></table>';
print '</td>';
print '<td class="valuefield fieldname_fk_soc">';
print $supplierOrder->showInputField($supplierOrder->fields['fk_soc'], 'fk_soc', GETPOST('supplier_fk_soc'), '', '', 'supplier_', '', 1);
print '</td>';
print '</tr>';

$produit = array();
$sql = "SELECT DISTINCT  p.rowid as rowid, p.label as label ";
$sql.= "FROM " .$db->prefix() . "product as p ";
$sql.= "INNER JOIN " .$db->prefix() . "product_extrafields as ef ON ef.fk_object = p.rowid ";
$sql.= "WHERE p.fk_product_type = 1 AND ef.oorder_available_for_supplier_order = 1 AND p.tobuy =1";

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$produit[$obj->rowid] = $obj->label;
	}
}

print '<tr class="trcommonfield_categories"><td class="titlefield">';
print '<table class="nobordernopadding centpercent">';
print '<tr>';
print '<td class="titlefield fieldname_fk_soc fieldrequired">';
print $langs->trans('Operation');
print '</tr></table>';
print '</td>';
print '<td class="valuefield fieldname_fk_soc">';
print $form->selectArray('fk_product', $produit, $fk_product, 1);
print '</td>';
print '</tr>';

$supplierOrderLine = new CommandeFournisseurLigne($object->db);
// Bon les champs sont pas définis... mais ils le serons un jour non ?
$supplierOrderLine->fields=array(
	'subprice' => array ( 'type' => 'real', 'label' => 'UnitPrice', 'enabled' => 1, 'position' => 35, 'notnull' => 1, 'required' => 1, 'visible' => 1  ),
	'desc' => array ( 'type' => 'html', 'label' => 'Description', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 3  ),
);
foreach ($supplierOrderLine->fields as $key=>$val) {
	print getFieldCardOutputByOperationOrder($supplierOrderLine, $key, '', '', '', '', 1, '');
}

print '</table>'."\n";

print '<div class="center">';
print '<input type="submit" class="button" name="add" value="'.dol_escape_htmltag($langs->trans('Create')).'">';
print '&nbsp; ';
print '<input type="'.($backtopage?"submit":"button").'" class="button" name="cancel" value="'.dol_escape_htmltag($langs->trans('Cancel')).'"'.($backtopage?'':' onclick="javascript:history.go(-1)"').'>';	// Cancel for create does not post form if we don't know the backtopage
print '</div>';

print '</form>';

llxFooter();
include dol_buildpath('/operationorder/scripts/operationorder_depan.scripts.php');
$db->close();
