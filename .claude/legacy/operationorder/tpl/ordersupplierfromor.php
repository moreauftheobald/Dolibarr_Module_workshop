<?php
/*
 * Copyright (C) 2013   Cédric Salvador    <csalvador@gpcsolutions.fr>
 * Copyright (C) 2014-2015   ATM Consulting   <support@atm-consulting.fr>
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
 *  \file       htdocs/product/stock/replenish.php
 *  \ingroup    produit
 *  \brief      Page to list stocks to replenish
 */

$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT . "/fourn/class/fournisseur.class.php";
require_once DOL_DOCUMENT_ROOT . "/commande/class/commande.class.php";
dol_include_once('operationorder/class/operationorder.class.php');


$prod = new Product($db);
$supplierOrder = new CommandeFournisseur($db);
$or = new OperationOrder($db);

$langs->load("products");
$langs->load("stocks");
$langs->load("orders");
$langs->load("operationorder@operationorder");

// Security check
if ($user->societe_id) {
	$socid = $user->societe_id;
}
restrictedArea($user, 'produit', $prod, 'product&product', '', '');
$result = restrictedArea($user, 'fournisseur', $supplierOrder, 'commande_fournisseur', 'commande');

//checks if a product has been ordered

$action = GETPOST('action', 'alpha');
$orId = GETPOST('orid', 'int');

if (!empty($orId)) {
	$res = $or->fetch($orId);
	if ($res < 0) {
		setEventMessage($or->error, $or->errors, 'errors');
	}
}
$title = $langs->trans('ProductsToOrder');
top_htmlhead('', $title);

/*
 * Actions
 */
if ($action == 'create-order') {
	$error=0;
	$createOrder = GETPOST('createOrder', 'array');
	$productBySupplier = array();
	if (!empty($createOrder)) {
		require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
		require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.product.class.php';
		foreach ($createOrder as $prodId) {
			$prodFournId = GETPOST('fournprodid' . $prodId, 'int');

			$prodFourn = new ProductFournisseur($db);
			if (!empty($prodFournId)) {
				$resPF = $prodFourn->fetch_product_fournisseur_price($prodFournId);
				if ($resPF < 0) {
					setEventMessages($prodFourn->error, $prodFourn->errors, 'errors');
				} elseif (!empty($prodFourn->fourn_id)) {
					$productBySupplier[$prodFourn->fourn_id][$prodFourn->fk_product]['productfourn'] = $prodFourn;
					$productBySupplier[$prodFourn->fourn_id][$prodFourn->fk_product]['qty'] = GETPOST('tobuy' . $prodId, 'int');
					$productBySupplier[$prodFourn->fourn_id][$prodFourn->fk_product]['lineid'] = GETPOST('lineid' . $prodId, 'int');
				}
			} else {
				$error++;
				setEventMessage($langs->trans('CannotFindFournPriceFor', $prodFournId), 'error');
			}
		}

		if (!empty($productBySupplier)) {
			foreach ($productBySupplier as $supplierId => $productData) {
				$order = new CommandeFournisseur($db);
				$res = 0;
				if (empty(getDolGlobalInt('SOFOR_CREATE_NEW_SUPPLIER_ODER_ANY_TIME'))) {
					$sql = "SELECT rowid FROM " . $db->prefix() . $order->table_element;
					$sql .= " WHERE fk_soc = " . (int) $supplierId;
					$sql .= " AND ref_supplier = '" . $or->ref . "'";
					$sql .= "' AND entity =" . $conf->entity;
					$sql .= " AND fk_statut = 0";

					$resql = $db->query($sql);
					$num = $db->num_rows($resql);

					if ($resql && $num > 0) {
						$obj = $db->fetch_object($resql);
						$res = $order->fetch($obj->rowid);
						if ($res < 0) {
							$error++;
							setEventMessages($order->error, $order->errors, 'errors');
							break;
						}
					}
				}

				if ($res <= 0) {
					$order->socid = $supplierId;
					$order->fk_soc = $supplierId;
					$order->ref_supplier = $or->ref;
					$res = $order->create($user, 1);

					if ($res > 0) {
						$order->fetch($res);
						$order->add_object_linked($or->element, $or->id);
					} else {
						$error++;
						setEventMessage('ErrorCreateSupplierOrder', 'errors');
					}
				}
				if (empty($error)) {
					foreach ($productData as $fournProdId => $prodDetail) {
						$product = $prodDetail['productfourn'];
						$res = $product->get_buyprice($fournProdId, 0, $product->fk_product, 'none');

						if ($product->fourn_qty <> 0) {
							$qty = ceil($prodDetail['qty'] / $product->fourn_qty) * $product->fourn_qty;
						} else {
							$qty = $prodDetail['qty'];
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
							$orline = new operationorderLine($db);
							$orline->fetch($prodDetail['lineid']);
							$orline->add_object_linked($order->element, $order->id);
						} else {
							$error++;
							setEventMessages($order->error, $order->errors, 'errors');
							break;
						}
					}
				}
			}
		}
	}
	if (empty($error)) {
		$action='closedialog';
	}
}


/*
 * View
 */
if (!empty($or->id)) {
	$orderableProduct = $or->getOrabledProductFormOR();
	if (!is_array($orderableProduct) && $orderableProduct < 0) {
		setEventMessages($this->error, $this->errors, 'errors');
	}

	$form = new Form($db);

	if (!empty($orderableProduct)) {
		print '<form action="' . $_SERVER['PHP_SELF'] . '" method="post" name="supploerordercreation">' .
			'<input type="hidden" name="orid" value="' . $or->id . '">' .
			'<input type="hidden" name="token" value="' . newToken() . '">' .
			'<input type="hidden" name="action" value="create-order">' .

			'<input type="hidden" name="linecount" value="' . (count($orderableProduct)) . '">' .
			'<table class="liste">';


		$param = '';
		// Lignes des titres
		print '<tr class="liste_titre_filter">' .
			'<th class="liste_titre"><input type="checkbox" onClick="toggle(this)" /></th>';
		print_liste_field_titre(
			$langs->trans('Ref'),
		);
		print_liste_field_titre(
			$langs->trans('Label'),
		);
		print_liste_field_titre(
			$langs->trans('PhysicalStock'),
		);
		print_liste_field_titre(
			$langs->trans('Ordered'),
		);
		print_liste_field_titre(
			$langs->trans('QtyToOrder'),
		);
		print_liste_field_titre(
			$langs->trans('Supplier'),
			'', '', '', '', '', '', '', 'right '
		);

		print '</tr>';


		$prod = new Product($db);

		foreach ($orderableProduct as $prodId => $prodToOrderData) {
			print '<tr class="oddeven" data-productid="' . $prodId . '">
						<td>
							<input type="checkbox" class="checkProductToOrder" name="createOrder[]" value="' . $prodId . '">';
			print '<input type="hidden" name="lineid' . $prodId . '" value="' . $prodToOrderData['line']->id . '">';
			print '</td>
                 <td style="height:35px;" class="nowrap">' .
				$prodToOrderData['product']->getNomUrl(1) .
				'</td>' .
				'<td>' . $prodToOrderData['product']->label . '</td>';


			print '<td>' . $prodToOrderData['line']->product->stock_reel . '</td>';
			print '<td>' . $prodToOrderData['alreadyorderedqty'] . '</td>';


			print '<td><input type="text" name="tobuy' . $prodId . '" value="' . $prodToOrderData['qty'] . '" size="4"></td>';
			print '<td class="right" data-info="fourn-price" >';
			print $form->select_product_fourn_price($prodId, 'fournprodid' . $prodId);
			print '</td>';

			print '</tr>';
		}

		print '</table>';


		print '<div class="tabsAction">' . "\n";
		print '<input type="submit" class="butAction" value="' . $langs->trans("GenerateSupplierOrder") . '">';
		print '</div>';
		print '</form>';


		print '
 		<script type="text/javascript">
			 function toggle(source)
			 {
				 if($(source).is(":checked")){
					$(".checkProductToOrder").prop("checked",true);
				 } else {
					 $(".checkProductToOrder").prop("checked",false);
				 }
			 }
		 </script>';


		print dol_get_fiche_end();
	} else {
		print '<div class="">' . "\n";
		print $langs->trans('noproducttoorder');
		print '</div>';
	}
}

if ($action=='closedialog') {
	print '
	<script type="text/javascript">
		$(document).ready(function () {
			window.parent.$("#divSupplierOrderPiece").dialog("close");
			window.parent.$("#divSupplierOrderPiece").remove();
		});
	</script>
	';
}

llxFooter();
$db->close();
