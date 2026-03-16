<?php
require '../config.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
dol_include_once('/dolifleet/class/vehicule.class.php');

global $db, $conf, $langs, $user;

$langs->load('operationorder@operationorder');

$vhid = GETPOST('vhid', 'int');
$action = GETPOST('action');
$origin = GETPOST('origin');
$orid = GETPOST('orid');
$date = date("Y-m-d");

top_htmlhead('', '');

$vehicule = new Vehicule($db);
$vehicule->fetch($vhid);


if ($action == 'createorder') {
	$operation = GETPOST('fk_operation');
	$langs->load('operationorder@operationorder');

	$opeData = GETPOST('opeselected', 'array');

	if (!empty($opeData)) {
		dol_include_once('/operationorder/class/operationorder.class.php');
		$OR = new OperationOrder($db);
		$OR->fk_soc = $vehicule->fk_soc;
		$OR->date_operation_order = dol_now();
		$OR->fk_vehicule = $vehicule->id;

		$ret = $OR->save($user);

		//récup du code paramétré dans l'entité de l'OR traité
		$sql = "SELECT value as code FROM " . $db->prefix() . "const WHERE name ='OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT' AND entity = '" . $OR->entity . "'";
		$resql = $db->query($sql);

		if ($resql) {
			$obj = $db->fetch_object($resql);

			$sql = "SELECT rowid as id FROM " . $db->prefix() . "operationorder_status WHERE code ='" . $obj->code . "' AND entity = '" . $OR->entity . "'";
			$resql = $db->query($sql);
			if ($resql) {
				$obj = $db->fetch_object($resql);
				$fk_status = $obj->id;

				$OR->setStatus($user, $fk_status);
			}

			foreach ($opeData as $prodid) {
				// création de l'OR effectuée, maintenant on ajoute le service
				$prod = new Product($db);
				$retProd = $prod->fetch($prodid);
				if ($retProd > 0) {
					$tplanned = 0;
					if (!empty($prod->duration_value))
						$tplanned = $prod->duration_value * 60 * 60;
					$retadd = $OR->addline($prod->description, 1, $prod->price, 0, $prod->cost_price, $tplanned, 0, $prod->id);
					if ($retadd > 0) {
						$retrec = $OR->recurciveAddChildLines($retadd, $prod->id, 1);
					}
					setEventMessage($langs->trans('SucessORActionCreation'));
				} else {
					setEventMessage($langs->trans('ErrorORActionCreation'), 'errors');
				}
			}
		}
	}
	?>
	<script type="text/javascript">
		$(document).ready(function () {
			window.parent.$('#ordercreatedid').val('<?php print $ret; ?>');
			window.parent.$('#nonplanned').dialog('close');
			window.parent.$('#nonplanned').remove();
		});
	</script>
	<?php
} elseif ($action == 'adtoorder') {
	$orid = GETPOST('orid');
	$operations = GETPOST('opeselected', 'array');

	dol_include_once('/operationorder/class/operationorder.class.php');
	$OR = new OperationOrder($db);
	$retor = $OR->fetch($orid);

	if ($retor > 0 && !empty($operations)) {
		foreach ($operations as $prodid) {
			$prod = new Product($db);
			$retProd = $prod->fetch($prodid);

			if ($retProd > 0) {
				$tplanned = 0;
				if (!empty($prod->duration_value))
					$tplanned = $prod->duration_value * 60 * 60;
				$retadd = $OR->addline($prod->description, 1, $prod->price, 0, $prod->cost_price, $tplanned, 0, $prod->id);
				if ($retadd > 0) {
					$retrec = $OR->recurciveAddChildLines($retadd, $prod->id, 1);
				} else {
					setEventMessage($langs->trans('ErrorORActionCreation'), 'errors');
				}
			} else {
				setEventMessage($langs->trans('ErrorORActionCreation'), 'errors');
			}
		}
	} else {
		setEventMessage($langs->trans('ErrorORActionCreation'), 'errors');
	}
	?>
	<script type="text/javascript">
		$(document).ready(function () {
			window.parent.$('#nonplanned').dialog('close');
			window.parent.$('#nonplanned').remove();
		});
	</script>
	<?php
}

/*$nonplanned=array();
$sql = "SELECT op.fk_product FROM " . $db->prefix() . "dolifleet_vehicule_operation_np as op";
$sql .= " WHERE op.fk_vehicule=" . $vehicule->id;
$resql = $db->query($sql);
if (!$resql) {
	setEventMessages($db->lasterror, null, 'errors');
	print '<script type="text/javascript">
 			$(document).ready(function () {
 				window.parent.$(\'#elementtoplan\').dialog(\'close\');
 				window.parent.$(\'#elementtoplan\').remove();
 			});
		</script>';
} else {
	while ($obj = $db->fetch_object($resql)) {
		$nonplanned[]=$obj->fk_product;
	}
}*/

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="vhid" value="'.$vhid.'">';
if ($origin == 'vehicule') {
	print '<input type="hidden" name="action" value="createorder">';
} elseif ($origin == 'order') {
	print '<input type="hidden" name="orid" value="'.$orid.'">';
	print '<input type="hidden" name="action" value="adtoorder">';
}

print '<table id="opnonplanned" class="table" Style="width: 1150px;">';

print '<thead>';
print '<tr>';
print ' <th class="text-center" >' . $langs->trans('Immatriculation') . '</th>';
print ' <th class="text-center"  >' . $langs->trans('VIN') . '</th>';
print ' <th class="text-center" >' . $langs->trans('Code intervention') . '</th>';
print ' <th class="text-center" >' . $langs->trans('intervention') . '</th>';
print ' <th class="text-center" >' . $langs->trans('VehiculeOperationLastDateDone') . '</th>';
print ' <th class="text-center" >' . $langs->trans('VehiculeOperationLastKmDone') . '</th>';
print ' <th class="text-center" >' . $langs->trans('OR') . '</th>';
print ' <th class="text-center" >' . $langs->trans('Action') . '</th>';
print '</tr>';
print '</thead>';

print '<tbody>';

foreach ($nonplanned as $op) {
	$product = new Product($db);
	$result = $product->fetch($op);
	if ($result < 0) {
		setEventMessage($product->error, 'errors');
	}

	$sql = 'SELECT rep.rowid AS orid, rep.planned_date as dated , rep.km_on_creation as km';
	$sql .= ' FROM ' . $db->prefix() . 'operationorder AS rep';
	$sql .= ' INNER JOIN ' . $db->prefix() . 'operationorderdet AS det ON det.fk_operation_order = rep.rowid';
	//$sql .= ' INNER JOIN ' . $db->prefix() . 'operationorderaction AS ac on ac.fk_operationorder = rep.rowid';
	$sql .= ' WHERE rep.fk_vehicule = ' . $vhid;
	$sql .= ' AND det.fk_product = ' . $op;
	$sql .= ' ORDER BY rep.planned_date DESC';
	$sql .= ' LIMIT 1';

	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);

		print '<tr data-fk_ope="' . $product->id . '" data-date="' . $date . '">';

		if (!empty($vehicule)) {
			//Immat
			print ' <td>' . $vehicule->immatriculation . '</td>';
			//VIN
			print ' <td>' . $vehicule->getNomUrl(1) . '</td>';
		} else {
			print '<td><i class="fa fa-exclamation-triangle" style="color:orange" aria-hidden="true"></i>&nbsp;' . $langs->trans('MissingField') . '</td><td><i class="fa fa-exclamation-triangle" style="color:orange" aria-hidden="true"></i>&nbsp;' . $langs->trans('MissingField') . '</td>';
		}

		if (!empty($product)) {
			//Prod
			print ' <td>' . $product->getNomUrl(1) . '</td>';
			print ' <td>' . $product->label . '</td>';
		} else {
			print '<td><i class="fa fa-exclamation-triangle" style="color:orange" aria-hidden="true"></i>&nbsp;' . $langs->trans('MissingField') . '</td><td></td>';
		}


		print ' <td>' . $obj->dated . '</td>';

		print ' <td>' . $obj->km . ' km </td>';

		$ret='';
		if (!empty($obj->orid)) {
			dol_include_once('/operationorder/class/operationorder.class.php');
			$OR = new OperationOrder($db);
			$retor = $OR->fetch($obj->orid);
			$ret = $OR->getNomUrl(1);
		}


		print ' <td>' . $ret . '</td>';

		$action_url = '<input type="checkbox" name="opeselected[]" value="'.$product->id.'"/>';

		if (!empty($vehicule) && !empty($product->id))
			print ' <td>' . $action_url . '</td>';
		else {
			print '<td><i class="fa fa-times" aria-hidden="true" style="color:red;"></i></i>&nbsp;' . $langs->trans('CantCreate') . '</td>';
		}

		print '</tr>';
	} else {
		setEventMessage($db->lasterror, 'errors');
	}
}

print '</tbody>';
print '</table>';
print '<div class="center">';
print '<input type="submit" class="button" name="add" value="'.dol_escape_htmltag($langs->trans("Add")).'">';
print '</div>';
print '</form>';


?>
<script src="<?php print dol_buildpath('/operationorder/vendor/data-tables/datatables.min.js', 1); ?> "></script>
<script src="<?php print dol_buildpath('/operationorder/vendor/data-tables/jquery.dataTables.min.js', 1); ?>"></script>
<script type="text/javascript">
	$(document).ready(function () {
		$("#opnonplanned").DataTable({
			"pageLength": 10,
			"language": {
				"url": "<?php print dol_buildpath('/operationorder/vendor/data-tables/french.json', 1); ?>"
			},
			"autoWidth": true,
			"responsive": true
		});
	});
</script>

<?php
llxFooter();
?>
