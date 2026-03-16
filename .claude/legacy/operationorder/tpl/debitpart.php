<?php
require '../config.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
dol_include_once('operationorder/class/operationorder.class.php');
dol_include_once('operationorder/class/operationorderaction.class.php');
dol_include_once('operationorder/lib/operationorder.lib.php');
dol_include_once('/product/class/html.formproduct.class.php');
global $db, $conf, $langs, $user;

$langs->load("operationorder@operationorder");

$orid = GETPOST('orid', 'int');
$action = GETPOST('action', 'alpha');
$lineid = GETPOST('lineid');
$change = 0;

top_htmlhead('', '');

$lineupdated = new operationorderLine($db);
$object = new OperationOrder($db);
$formproduct = new FormProduct($db);
$oOHistory = new OperationOrderHistory($db);
$form = new Form($db);

$res = 0;
if (!empty($orid)) {
	$res = $object->fetch($orid, true);
}

if ($res > 0) {
	// Action si supression d'une ligne de piece
	if ($action == 'deletline-piece') {
		$res = $lineupdated->fetch($lineid);
		if ($res > 0) {
			$fk_parent = $lineupdated->fk_parent_line;
			$result = $object->removeChild($user, 'operationorderLine', $lineid);
			if ($result > 0) {
				if (!empty($fk_parent)) {
					$res = $lineupdated->fetch($fk_parent);
					if ($res > 0) {
						$child = $lineupdated->fetch_all_children_lines();
						if (count($child) == 0) {
							$object->removeChild($user, 'operationorderLine', $fk_parent);
						}
					}
				}
				unset($_POST['action'], $_POST['lineid']);
				unset($lineid, $action);
				setEventMessages('Ligne piece Supprimée', array());
				$change = 1;
			} else {
				unset($_POST['action'], $_POST['lineid']);
				unset($lineid, $action);
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}
	}

	//Action de débit des pièces du Stock.
	if ($action == 'debit-stock') {
		$entrepot = GETPOST('entrepot', 'array');
		$debit = GETPOST('debit', 'array');
		//$parents = GETPOST('parents', 'array');
		$err = 0;
		foreach ($entrepot as $idline => $wh) {
			if ($wh > 0 && !empty($debit[$idline])) {
				require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
				$mvt = new MouvementStock($db);
				//$mvt->origin = $object;
				$mvt->origin_type = $object->origin_type;
				$mvt->origin_id = $object->id;
				$res = $lineupdated->fetch($idline);
				if ($res > 0) {
					if ($debit[$idline] > 0) {
						$result = $mvt->livraison(
							$user,
							$lineupdated->product->id,
							$wh,
							abs($debit[$idline]),
							0,
							$langs->trans('ORDebit') . ' ' . $object->ref
						);
					} else {
						$result = $mvt->reception(
							$user,
							$lineupdated->product->id,
							$wh,
							abs($debit[$idline]),
							0,
							$langs->trans('ORCredit') . ' ' . $object->ref
						);
					}
					if ($result > 0) {
						$oOHistory->stockMvt($object, $lineupdated->product, $debit[$idline] * -1);
					} else {
						$err++;
					}
				} else {
					$err++;
				}
			}
		}

		if ($err == 0) {
			unset($_POST['debit'], $_POST['entrepot'], $_POST['action']);
			unset($debit, $entrepot, $action);
			setEventMessages('Mise a jour faite', array());
			$change = 1;
		} else {
			unset($_POST['debit'], $_POST['entrepot'], $_POST['action']);
			unset($debit, $entrepot, $action);
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	// SI l'objet a été modifié par les actions, on le recharge.
	if ($change == 1) {
		$ret = $object->fetch($object->id);
		$object->setTimePlannedT();
	}

	dol_sort_array($object->lines, 'rang', 'asc');
	print '<div class="fichecenter">';

	// Section centrale / Bas --> Tableau des pieces
	print load_fiche_titre($langs->trans("controlepart"), '', 'title_setup');

	print '<form method="post" action="debitpart.php">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="orid" value="' . $orid . '">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td style="width:38%">' . $langs->trans("Product") . '</td>';
	print '<td style="width:15%">' . $langs->trans("qtyprev") . '</td>';
	print '<td style="width:15%">' . $langs->trans("qtydebit") . '</td>';
	print '<td style="width:15%">' . $langs->trans("mouvstock") . '</td>';
	print '<td style="width:15%">' . $langs->trans("emplacement") . '</td>';
	print '<td style="width:15%">' . $langs->trans("operation liée") . '</td>';
	print '<td></td>';
	print "</tr>\n";

	$ORlines=$object->lines[0]->fetchAll(0, true, array("fk_operation_order"=>$object->id));
	$selarray = array();
	foreach ($ORlines as $keyORline => $valueORline) {
		if ($valueORline->product->type ==1 && empty($valueORline->product->array_options['options_oorder_available_for_supplier_order'])&&empty($valueORline->product->array_options['options_or_scan'])) {
			$selarray[$valueORline->id] = $valueORline->product->ref . ' - ' . $valueORline->product->label;
		}
	}

	foreach ($object->lines as $line) {
		if ($line->product->type == 0) {
			$wh = new Entrepot($db);
			$wh->fetch($line->product->fk_default_warehouse);
			$TLineQtyUsed = $object->getAlreadyUsedQtyLines();
			$TLastLinesByProduct = $object->getLastLinesByProduct();
			$qtyUsed = price($line->getQtyUsed($TLineQtyUsed, $TLastLinesByProduct));
			$qtyadjust = 0;
			if ($qtyUsed < $line->qty) {
				$qtyadjust = ($line->qty - $qtyUsed);
			}
			print '<tr class="oddeven">';
			print '<td>' . $line->product->getNomUrl(1) . ' - ' . $line->product->label . '</td>';
			print '<td>' . price($line->qty) . '</td>';
			print '<td>' . $qtyUsed . '</td>';
			print '<td>';
			print '<input class="right" style="width: 50px" type="text" name="debit[' . $line->id . ']" value="' . $qtyadjust . '">';
			print '</td>';
			print '<td>';
			print $formproduct->selectWarehouses(!empty($line->product->fk_default_warehouse) ? $line->product->fk_default_warehouse : (!empty($line->fk_warehouse) ? $line->fk_warehouse : ''), "entrepot[" . $line->id . "]", '', 1, 0, $line->fk_product, '', 1, 0, null, 'csswarehouse', array(), 0, 0);
			if (!empty($wh->id)) {
				print '<script type="text/javascript">';
				print '$(document).ready(function() {';
				print "var opt = document.createElement('option'); ";
				print "opt.value = '" . $wh->id . "';";
				print "opt.innerHTML = '" . $wh->label . " (default)';";
				print "document.getElementById('entrepot[" . $line->id . "]').appendChild(opt);";
				print '});';
			}
			print '</script>';
			print '</td>';
			print '<td>';
			print $form->selectarray('parents['. $line->id . ']', $selarray, $line->fk_parent_line, 1, 0, 0, '', 0, 0, 1, '', '', 1);
			print '</td>';
			print '<td>';
			if ($qtyUsed == 0) {
				print '<a class="reposition editfielda" href="' . $_SERVER['PHP_SELF'] . '?lineid=' . $line->id . '&action=deletline-piece&orid=' . $orid . '">' . img_delete() . '</a>';
			}
			print '</td>';
			print '</tr>';
		}
	}
	print '</table>';
	print '<div class="tabsAction">' . "\n";
	print '<input type="submit" class="button" value="' . $langs->trans("Modify") . '" formaction="debitpart.php?action=debit-stock">';
	print '</div>';
	print '</form>';

	print '</div>';
}
llxFooter();
