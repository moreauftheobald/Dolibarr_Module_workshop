<?php
require '../config.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
dol_include_once('operationorder/class/operationorder.class.php');
dol_include_once('operationorder/class/operationorderaction.class.php');
dol_include_once('operationorder/lib/operationorder.lib.php');
dol_include_once('/operationorder/class/productdefaultwarehouse.class.php');
dol_include_once('/product/class/html.formproduct.class.php');
global $db, $conf, $langs, $user;

$langs->load("operationorder@operationorder");

$orid = GETPOST('orid', 'int');
$action = GETPOST('action', 'alpha');
$lineid = GETPOST('lineid');
$qty = GETPOST('fact', 'array');
$prev_values = GETPOST('prev', 'array');
$change = 0;
$entitydefault = New ProductDefaultWarehouse ($db);
$defaultwharehouseentity = $entitydefault->getDefaultWarehouseForEntity($conf->entity);

top_htmlhead('', '');

if ($action == 'cancel') {
	?>
	<script type="text/javascript">
		$(document).ready(function () {
			window.parent.$('#orcheck').dialog('close');
			window.parent.$('#orcheck').remove();
		});
	</script>
	<?php
}

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
	// Mise a jour du Statut Check OR Si celui ci n'a jamais été tenté
	if ($object->orcheck == 0) {
		$res = $object->setOrCheck(1);
	}

	// Action Si la main d'oeuvre est ajustée
	if ($action == 'editline') {
		$err = 0;
		foreach ($qty as $idline => $nb) {
			if ($prev_values[$idline] !== $nb) {
				$res = $lineupdated->fetch($idline);
				$oldlineupdated = $lineupdated;
				if ($res) {
					$result = $object->updateline($idline, $lineupdated->description, $nb, $lineupdated->price, $lineupdated->fk_warehouse, $lineupdated->pr, $nb * 3600,
						$lineupdated->time_spent, $lineupdated->fk_product, $lineupdated->info_bits, $lineupdated->date_start, $lineupdated->date_end, $lineupdated->type,
						$lineupdated->fk_parent_line, $lineupdated->product->label, $lineupdated->special_code, $lineupdated->array_options);
					if ($result < 0) {
						$err++;
					}
				} else {
					$err++;
				}
			}
		}
		if ($err == 0) {
			unset($_POST['fact'], $_POST['prev'], $_POST['action'], $_POST['lineid']);
			unset($action, $lineid, $qty, $prev_values);
			setEventMessages('Mise a jour faite', array());
			$change = 1;
		} else {
			unset($_POST['fact'], $_POST['prev'], $_POST['action'], $_POST['lineid']);
			unset($action, $lineid, $qty, $prev_values);
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	// Action si supression d'une ligne de main d'oeuvre -> on supprime tout le bloc parent
	if ($action == 'deletline') {
		$res = $lineupdated->fetch($lineid);
		if ($res > 0) {
			$fk_parent = $lineupdated->fk_parent_line;
			$result = $object->removeChild($user, 'operationorderLine', $lineid);
			if ($result > 0) {
				unset($_POST['fact'], $_POST['prev'], $_POST['action'], $_POST['lineid']);
				unset($lineid, $qty, $prev_values);
				setEventMessages('Ligne MO Supprimée', array());
				$change = 1;
			} else {
				unset($_POST['fact'], $_POST['prev'], $_POST['action'], $_POST['lineid']);
				unset($lineid, $qty, $prev_values);
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}
	}

	// Action si supression d'une ligne de piece
	if ($action == 'deletline-piece') {
		$res = $lineupdated->fetch($lineid);
		if ($res > 0) {
			$fk_parent = $lineupdated->fk_parent_line;
			$result = $object->removeChild($user, 'operationorderLine', $lineid);
			if ($result > 0) {
				unset($_POST['fact'], $_POST['prev'], $_POST['action'], $_POST['lineid']);
				unset($lineid, $qty, $prev_values);
				setEventMessages('Ligne piece Supprimée', array());
				$change = 1;
			} else {
				unset($_POST['fact'], $_POST['prev'], $_POST['action'], $_POST['lineid']);
				unset($lineid, $qty, $prev_values);
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}
	}

	//Si l'action de supression d'une ligne, créé un bloc vide, on le supprimme.
	if ($action == 'deletline-piece' || $action == 'deletline') {
		if (!empty($fk_parent)) {
			$res = $lineupdated->fetch($fk_parent);
			if ($res > 0) {
				$child = $lineupdated->fetch_all_children_lines();
				if (count($child) == 0) {
					$object->removeChild($user, 'operationorderLine', $fk_parent);
				}
			}
		}
		unset($_POST['action']);
		unset($action);
	}
	if ($action == 'job_valid') {
		$orline = New OperationOrderLine($db);
		$res = $orline->fetch(GETPOST('orlineid','int'));
		if ($res > 0) {
			$orline->fk_c_operationorder_type = GETPOST('fk_c_operationorder_type','int');
			$resupdate = $orline->update($user,1);
			if (!$resupdate) {
				setEventMessages($orline->error, $orline->errors, 'errors');
			}
		} else {
			setEventMessages($orline->error, $orline->errors, 'errors');
		}
	}

	//Action de débit des pièces du Stock.
	if ($action == 'debit-stock') {
		foreach ($qty as $idline => $nb) {
			$res = $lineupdated->fetch($idline);
			if ($res > 0) {
				if ($qty[$idline] != $lineupdated->qty) {
					$lineupdated->qty = $qty[$idline];
					//$resUpdLine = $lineupdated->calcAmountLine($user);
					$resUpdLine = $lineupdated->update($user);
					if ($resUpdLine < 0) {
						$err++;
					}
				}
			} else {
				$err++;
			}
		}

		if (empty($err)) {
			$entrepot = GETPOST('entrepot', 'array');
			$debit = GETPOST('debit', 'array');
			$parents = GETPOST('parents', 'array');
			$err = 0;
			foreach ($entrepot as $idline => $wh) {
				if ($wh > 0 && !empty($debit[$idline])) {
					require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
					$mvt = new MouvementStock($db);
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
							if ($wh <> $lineupdated->fk_warehouse) {
								$lineupdated->fk_warehouse = $wh;
								$lineupdated->update($user);
							}
							$oOHistory->stockMvt($object, $lineupdated->product, $debit[$idline] * -1);
						} else {
							$err++;
						}
					} else {
						$err++;
					}
				}
			}
			foreach ($parents as $idline => $par) {
				if (!empty($par)) {
					$res = $lineupdated->fetch($idline);
					if ($res > 0) {
						if ($par == -1) {
							$fk_par = null;
						} else {
							$fk_par = $par;
						}
						if ($fk_par <> $lineupdated->fk_parent_line) {
							$lineupdated->fk_parent_line = $fk_par;
							$lineupdated->update($user);
						}
					} else {
						$err++;
					}
				}
			}
		}
		if ($err == 0) {
			unset($_POST['debit'], $_POST['entrepot'], $_POST['action'], $_POST['parents']);
			unset($debit, $entrepot, $action, $parents);
			setEventMessages('Mise a jour faite', array());
			$change = 1;
		} else {
			unset($_POST['debit'], $_POST['entrepot'], $_POST['action'], $_POST['parents']);
			unset($debit, $entrepot, $action, $parents);
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	//Validation Finale du Check OR
	if ($action == 'valid') {
		$object->setOrCheck(2);
		$now = dol_now();

		$sql = "INSERT INTO llx_operationorderhistory(date_creation,fk_operationorder,title, description, fk_user_creat, entity) VALUES ('";
		$sql .= $object->db->idate($now) . "'," . $object->id . ", 'Check OR',''" . $user->id . "," . $object->entity . ")";

		$object->db->query($sql);

		?>
		<script type="text/javascript">
			$(document).ready(function () {
				window.parent.$('#orcheck').dialog('close');
				window.parent.$('#orcheck').remove();
			});
		</script>
		<?php
	}

	// SI l'objet a été modifié par les actions, on le recharge.
	if ($change == 1) {
		$ret = $object->fetch($object->id);
		$object->setTimePlannedT();
		if (empty(getDolGlobalString("MAIN_DISABLE_PDF_AUTOUPDATE"))) {
			$object->generateDocument($object->modelpdf, $langs, 0, 0, 0);
		}
	}
	dol_sort_array($object->lines, 'rang', 'asc');
	print '<div class="fichecenter"><div class="fichehalfleft">';

	//section Gauche / Haut --> Tableau de main d'oeuvre
	print load_fiche_titre($langs->trans("controleMO"), '', 'title_setup');
	print '<table class="noborder centpercent">';
	print '<form method="post" action="orcheck.php">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="orid" value="' . $orid . '">';
	print '<tr class="liste_titre">';
	print '<td style="width:68%">' . $langs->trans("operation") . '</td>';
	print '<td style="width:10%">' . $langs->trans("hprevues") . '</td>';
	print '<td style="width:10%">' . $langs->trans("hpointée") . '</td>';
	print '<td style="width:10%">' . $langs->trans("facture") . '</td>';
	print '<td>' . $langs->trans("OpNotDone") . '</td>';
	print "</tr>\n";
	$totplaned = 0;
	$totspent = 0;
	$totfact = 0;
	$nb_mo = 0;

	foreach ($object->lines as $line) {
		if ($line->product->array_options['options_or_scan']) {
			$nb_mo++;
			if (!empty($line->fk_parent_line)) {
				foreach ($object->lines as $linep) {
					if ($linep->id == $line->fk_parent_line) $desc = $linep->product->label;
				}
			} else {
				$desc = $line->product->label . ' - ' . $line->description;
			}
			print '<tr class="oddeven">';
			print '<td>' . $desc . '</td>';
			print '<td>' . price(($line->time_planned) / 3600) . '</td>';
			print '<td>' . round($line->time_spent / 3600, 2) . '</td>';
			print '<td><input class="right" style="width: 50px" type="text" name="fact[' . $line->id . ']" value="' . $line->qty . '">';
			print '<input type="hidden" name="prev[' . $line->id . ']" value="' . $line->qty . '"></td>';
			print '<td class="right"><a class="reposition editfielda" href="' . $_SERVER['PHP_SELF'] . '?lineid=' . $line->id . '&action=deletline&orid=' . $orid . '&token=' .  newToken() . '">' . img_delete() . '</a></td>';
			print '</tr>';
			$totplaned += $line->time_planned / 3600;
			$totspent += $line->time_spent / 3600;
			//$totfact += ($line->total_ht / $line->product->price);
			$totfact += ($line->qty);

		}
	}
	$mo_stat = 0;
	$mo_stat_label = 'MO a controler';

	if ($nb_mo > 0 && $totspent > 0) {
		$coef_mo = round((($totfact - $totspent) / $totspent) * 100, 2);
	} else {
		$coef_mo = 0;
		$mo_stat = 1;
		$mo_stat_label = 'Check OR MO OK';
	}

	if (getDolGlobalInt("OPERATIONORDER_MO_COEF_MIN")  < $coef_mo && $coef_mo < getDolGlobalInt("OPERATIONORDER_MO_COEF_MAX")  && $nb_mo > 0) {
		$mo_stat = 1;
		$mo_stat_label = 'Check OR MO OK';
	}
	print '<tr class="liste_titre">';
	print '<td colspan="3">Ecart MO faite / MO facturée =' . $coef_mo . '% ' . $mo_stat_label . '</td>';
	print '<td colspan="2"><input type="submit" class="button" value="' . $langs->trans("Modify") . '" formaction="orcheck.php?action=editline"></td>';
	print "</tr>\n";
	print '</form>';
	print '</table>';
	print '<br>';

	print '</div><div class="fichehalfright">';

	//section Gauche / Haut --> Tableau des travaux externes
	print load_fiche_titre($langs->trans("controlext"), '', 'title_setup');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans("Product") . '</td>';
	print '<td>' . $langs->trans("desc") . '</td>';
	print '<td>' . $langs->trans("totalivoiced") . '</td>';
	print '<td>' . $langs->trans("ordered") . '</td>';
	print '<td>' . $langs->trans("orderedto") . '</td>';
	print "</tr>\n";
	$ext_stat = 0;
	$nb_ext = 0;
	foreach ($object->lines as $line) {
		if ($line->product->array_options['options_oorder_available_for_supplier_order']) {
			$line->fetchObjectLinked();
			if (!empty($line->linkedObjects['order_supplier'])) {
				$supplieroder = array_values($line->linkedObjects['order_supplier'])[0];
				$supplieroder->fetch_thirdparty();
			}
			$nb_ext++;
			print '<form method="post" action="orcheck.php">';
			print '<input type="hidden" name="token" value="' . newToken() . '">';
			print '<input type="hidden" name="orid" value="' . $orid . '">';
			print '<input type="hidden" name="lineid" value="' . $line->id . '">';
			print '<tr class="oddeven">';
			print '<td>' . $line->product->label . '</td>';
			print '<td>' . $line->description . '</td>';
			print '<td>' . price($line->total_ht) . '</td>';
			if (empty($supplieroder)) {
				$ext_stat = 0;
				print '<td colspan ="2"> Veuillez emettre le bon de commande avant de cloturer cet OR</td>';
			} else {
				$ext_stat++;
				print '<td>' . $supplieroder->getNomUrl(1) . ' ' . $supplieroder->getLibStatut(3) . '</td>';
				print '<td>' . $supplieroder->thirdparty->getNomUrl(1) . '</td>';
			}
			print '</tr>';
			print '</form>';
		}
	}

	print '</table>';

	print '</div>';
	print '<br>';

	// Section centrale / Bas --> Tableau des pieces
	print load_fiche_titre($langs->trans("controlepart"), '', 'title_setup');

	print '<form method="post" action="orcheck.php">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="orid" value="' . $orid . '">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td style="width:38%">' . $langs->trans("Product") . '</td>';
	print '<td style="width:15%">' . $langs->trans("qtyprev") . '</td>';
	print '<td style="width:15%">' . $langs->trans("qtydebit") . '</td>';
	print '<td style="width:15%">' . $langs->trans("qtytoinvoice") . '</td>';
	print '<td style="width:15%">' . $langs->trans("mouvstock") . '</td>';
	print '<td style="width:15%">' . $langs->trans("emplacement") . '</td>';
	print '<td style="width:15%">' . $langs->trans("operation liée") . '</td>';
	print '<td></td>';
	print "</tr>\n";

	$pt_stat = 0;
	$nb_part = 0;
	$ORlines = $object->lines[0]->fetchAll(0, true, array("fk_operation_order" => $object->id));
	$selarray = array();
	foreach ($ORlines as $keyORline => $valueORline) {
		if ($valueORline->product->type == 1
			&& empty($valueORline->product->array_options['options_oorder_available_for_supplier_order'])
			&& empty($valueORline->product->array_options['options_or_scan'])) {
			$selarray[$valueORline->id] = $valueORline->product->ref . ' - ' . $valueORline->product->label;
		}
	}
	foreach ($object->lines as $line) {
		if ($line->product->type == 0) {
			if (empty($line->fk_warehouse)) {
				if(!empty($prod->fk_default_warehouse)) {
					$line->fk_warehouse = $prod->fk_default_warehouse;
				} else {
					$line->fk_warehouse = $defaultwharehouseentity ;
				}
			}
			if (!empty($line->fk_warehouse)) {
				$wh = new Entrepot($db);
				$wh->fetch($line->fk_warehouse);
				$TLineQtyUsed = $object->getAlreadyUsedQtyLines();
				$TLastLinesByProduct = $object->getLastLinesByProduct();
				$qtyUsed = price($line->getQtyUsed($TLineQtyUsed, $TLastLinesByProduct));
				$coef_part = round(($qtyUsed - $line->qty) * 100, 2);
				$qtyadjust = 0;
				if ($qtyUsed < $line->qty) {
					$qtyadjust = ($line->qty - $qtyUsed);
				}

				$nb_part++;

				print '<tr class="oddeven">';
				print '<td>' . $line->product->getNomUrl(1) . ' - ' . $line->product->label . '</td>';
				print '<td>' . price($line->qty) . '</td>';
				print '<td>' . $qtyUsed . '</td>';
				print '<td><input class="right" style="width: 50px" type="text" name="fact[' . $line->id . ']" value="' . $line->qty . '"></td>';
				if (getDolGlobalInt("OPERATIONORDER_PT_COEF_MIN")  <= $coef_part && $coef_part <= getDolGlobalInt("OPERATIONORDER_PT_COEF_MAX") ) {
					//                  print '<td colspan="2"> controle piece OK </td>';
					//                  print '<td>';
					//                  print $form->selectarray('parents[' . $line->id . ']', $selarray, $line->fk_parent_line, 1, 0, 0, '', 0, 0, 0, '', '', 1);
					//                  print '</td>';
					$pt_stat++;
				}

				print '<td>';
				print '<input class="right" style="width: 50px" type="text" name="debit[' . $line->id . ']" value="' . $qtyadjust . '">';
				print '</td>';
				print '<td>';
				print $formproduct->selectWarehouses(!empty($line->product->fk_default_warehouse) ? $line->product->fk_default_warehouse : (!empty($line->fk_warehouse) ? $line->fk_warehouse : ''), "entrepot[" . $line->id . "]", 'warehouseopen', 1, 0, $line->fk_product, '', 1, 0, null, 'csswarehouse', array(), 0, 0);
				print '<script type="text/javascript">';
				print '$(document).ready(function() {';
				print "var opt = document.createElement('option'); ";
				print "opt.value = '" . $wh->id . "';";
				print "opt.innerHTML = '" . $wh->label . " (default)';";
				print "document.getElementById('entrepot[" . $line->id . "]').appendChild(opt);";
				print '});';
				print '</script>';
				print '</td>';
				print '<td>';
				print $form->selectarray('parents[' . $line->id . ']', $selarray, $line->fk_parent_line, 1, 0, 0, '', 0, 0, 0, '', '', 1);
				print '</td>';
				print '<td>';
				if ($qtyUsed == 0) {
					print '<a class="reposition editfielda" href="' . $_SERVER['PHP_SELF'] . '?lineid=' . $line->id . '&action=deletline-piece&orid=' . $orid . '&token=' .  newToken() . '">' . img_delete() . '</a>';
				}
				print '</td>';
				print '</tr>';
			}
		}
	}
	print '<tr class="liste_titre">';
	print '<td colspan="4"></td>';
	print '<td colspan="4"><input type="submit" class="button" value="' . $langs->trans("Modify") . '" formaction="orcheck.php?action=debit-stock"></td>';
	print "</tr>\n";
	print '</table>';
	print '</form>';

	print '</div>';

	// Section centrale / Bas --> Tableau des pieces
	$checkjob = true;
	print load_fiche_titre($langs->trans("controlejob"), '', 'title_setup');

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td style="width:38%">' . $langs->trans("Job") . '</td>';
	print '<td style="width:15%">' . $langs->trans("description") . '</td>';
	print '<td style="width:15%">' . $langs->trans("type") . '</td>';
	print '<td></td>';
	print "</tr>\n";

	$ORlines = $object->lines[0]->fetchAll(0, true, array("fk_operation_order" => $object->id));
	foreach ($ORlines as $keyORline => $valueORline) {
		if ($valueORline->product->type == 1
			&&!empty($valueORline->product->array_options['options_or_is_job'])){
			if($valueORline->fk_c_operationorder_type < 1){
				$checkjob = false;
			}
			print '<form method="post" action="orcheck.php">';
			print '<input type="hidden" name="token" value="' . newToken() . '">';
			print '<input type="hidden" name="orid" value="' . $orid . '">';
			print '<tr class="oddeven">';
			print '<td style="width:20%">' . $valueORline->product->ref . ' - ' . $valueORline->product->label . '</td>';
			print '<td style="width:50%">' . $valueORline->description . '</td>';
			print '<td style="width:20%">' . $valueORline->showInputField($valueORline->fields['fk_c_operationorder_type'], 'fk_c_operationorder_type', $valueORline->fk_c_operationorder_type) .  '</td>';
			print '<td style="width:10%"><input type="submit" class="button" value="' . $langs->trans("Modify") . '" formaction="orcheck.php?action=job_valid&orlineid=' . $valueORline->id . '"></td>';
			print '</form>';
		}
	}
	print '</table>';

	print '</div>';
	if ($mo_stat == 1 && $pt_stat == $nb_part && $ext_stat == $nb_ext || !empty($user->hasRight('operationorder', 'checkor', 'admin'))) {
		if($checkjob) {
			$chekor = 1;
		}else{
			$chekor = 0;
		}
	} else {
		$chekor = 0;
	}

	$actionUrl = $_SERVER["PHP_SELF"] . '?orid=' . $orid . '&amp;action=';

	print '<div class="tabsAction">' . "\n";
	print dolGetButtonAction($langs->trans("Cancel"), '', 'default', $actionUrl . 'cancel', '', 1);
	print dolGetButtonAction($langs->trans("valid"), '', 'default', $actionUrl . 'valid', '', $chekor == 1);
	print '</div>' . "\n";
}
llxFooter();
?>
