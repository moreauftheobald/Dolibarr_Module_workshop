<?php
require '../config.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
dol_include_once('/dolifleet/class/vehiculeOperation.class.php');
dol_include_once('/operationorder/class/operationorder.class.php');
dol_include_once('/dolifleet/class/vehicule.class.php');


global $db, $conf, $langs, $user;

$langs->loadLangs(array('operationorder@operationorder'));

$vhid = GETPOST('vhid', 'int');
$action = GETPOST('action');
$origin = GETPOST('origin');
$orid = GETPOST('orid');
$date = date("Y-m-d");
$fk_product = GETPOST('fk_product', 'int');
$ope_id=GETPOST('ope_id', 'int');

top_htmlhead('', '');

if ($action == 'createorder' && !empty($vhid)) {
	$langs->load('operationorder@operationorder');

	$opeData = GETPOST('opeselected', 'array');

	if (!empty($opeData)) {
		$veh = new Vehicule($db);
		$ret = $veh->fetch($vhid);

		$OR = new OperationOrder($db);
		$OR->fk_soc = $veh->fk_soc;
		$OR->date_operation_order = dol_now();
		$OR->fk_vehicule = $veh->id;

		$ret = $OR->save($user);

		//récup du code paramétré dans l'entité de l'OR traité
		$sql = "SELECT value as code FROM " . $db->prefix() . "const WHERE name ='OPODER_STATUS_ON_CLONE' AND entity = '" . $OR->entity . "'";
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
		}

		if ($ret > 0) {
			foreach ($opeData as $dt) {
				$fk_product = explode('#', $dt)[0];
				$ope_id = explode('#', $dt)[1];

				// création de l'OR effectuée, maintenant on ajoute le service
				$prod = new Product($db);
				$retProd = $prod->fetch($fk_product);
				if ($retProd > 0) {
					$tplanned = 0;
					if (!empty($prod->duration_value)) $tplanned = $prod->duration_value * 60 * 60;
					$retadd = $OR->addline($prod->description, 1, $prod->price, 0, $prod->cost_price, $tplanned, 0, $prod->id);
					if ($retadd > 0) {
						$retrec = $OR->recurciveAddChildLines($retadd, $prod->id, 1);
					}
				}

				$vehope = new dolifleetVehiculeOperation($db);
				$vehope->fetch($ope_id);
				$vehope->or_next = $OR->id;
				$vehope->update($user);

				setEventMessage($langs->trans('SucessORActionCreation'));
			}
		}
	}

	?>
	<script type="text/javascript">
		$(document).ready(function () {
			window.parent.$('#ordercreatedid').val('<?php print $ret; ?>');
			window.parent.$('#elementtoplan').dialog('close');
			window.parent.$('#elementtoplan').remove();
		});
	</script>
	<?php
} elseif ($action == 'adtoorder') {
	$orid = GETPOST('orid');
	$opeData = GETPOST('opeselected', 'array');

	$OR = new OperationOrder($db);
	$retor = $OR->fetch($orid);

	if ($retor > 0 && !empty($opeData)) {
		foreach ($opeData as $dt) {
			$fk_product = explode('#', $dt)[0];
			$ope_id = explode('#', $dt)[1];

			$prod = new Product($db);
			$retProd = $prod->fetch($fk_product);

			if ($retProd > 0) {
				$tplanned = 0;
				if (!empty($prod->duration_value)) $tplanned = $prod->duration_value * 60 * 60;
				$retadd = $OR->addline($prod->description, 1, $prod->price, 0, $prod->cost_price, $tplanned, 0, $prod->id);
				if ($retadd > 0) {
					$retrec = $OR->recurciveAddChildLines($retadd, $prod->id, 1);

					$vehope = new dolifleetVehiculeOperation($db);
					$vehope->fetch($ope_id);
					$vehope->or_next = $OR->id;
					$vehope->update($user);
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
			window.parent.$('#elementtoplan').dialog('close');
			window.parent.$('#elementtoplan').remove();
		});
	</script>
	<?php
}

$sql = "SELECT vh.rowid as vehid, prod.rowid as prodrowid, op.date_next, op.date_done,
				op.km_next, op.km_done, op.on_time, op.rowid as opid";
$sql .= " FROM " . $db->prefix() . "dolifleet_vehicule AS vh";
$sql .= " INNER JOIN " . $db->prefix() . "dolifleet_vehicule_operation AS op ON op.fk_vehicule = vh.rowid";
$sql .= " INNER JOIN " . $db->prefix() . "product AS prod ON op.fk_product=prod.rowid";
$sql .= " WHERE vh.rowid=" . $vhid;
$sql .= " AND (op.or_next IS NULL OR op.or_next=0) ";
$sql .= " AND op.date_next IS NOT NULL";
$sql .= " AND op.date_next < '".$db->idate(dol_time_plus_duree(dol_now(), (int) getDolGlobalInt("THEO_NB_MONTH_CHECKING_VEHICULE_BY_ANTICIPATION"), 'm'))."'" ;

$resql = $db->query($sql);
if (!$resql) {
	setEventMessages($db->lasterror, null, 'errors');
	print '<script type="text/javascript">
 			$(document).ready(function () {
 				window.parent.$(\'#elementtoplan\').dialog(\'close\');
 				window.parent.$(\'#elementtoplan\').remove();
 			});
		</script>';
}

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="vhid" value="'.$vhid.'">';
if ($origin == 'vehicule') {
	print '<input type="hidden" name="action" value="createorder">';
} elseif ($origin == 'order') {
	print '<input type="hidden" name="orid" value="'.$orid.'">';
	print '<input type="hidden" name="action" value="adtoorder">';
}


print '<table id="orToCreate" class="table" Style="width: 1150px;">';

print '<thead>';
print '<tr>';
print ' <th class="text-center" >' . $langs->trans('Immatriculation') . '</th>';
print ' <th class="text-center"  >' . $langs->trans('VIN') . '</th>';
print ' <th class="text-center" >' . $langs->trans('Code intervention') . '</th>';
print ' <th class="text-center" >' . $langs->trans('intervention') . '</th>';
print ' <th class="text-center" >' . $langs->trans('DatePrev') . '</th>';
print ' <th class="text-center" >' . $langs->trans('VehiculeOperationKmNext') . '</th>';
print ' <th class="text-center" >' . $langs->trans('VehiculeOperationLastDateDone') . '</th>';
print ' <th class="text-center" >' . $langs->trans('VehiculeOperationLastKmDone') . '</th>';
print ' <th class="text-center" >' . $langs->trans('Action') . '</th>';
print '</tr>';
print '</thead>';

print '<tbody>';

while ($obj = $db->fetch_object($resql)) {
	$vehicule = new Vehicule($db);
	$vehicule->fetch($obj->vehid);

	$product = new Product($db);
	$product->fetch($obj->prodrowid);


	print '<tr data-fk_actioncomm="' . $action->id . '" data-date="' . $date . '">';
	print ' <td>' . $vehicule->immatriculation . '</td>';
	print ' <td>' . $vehicule->getNomUrl(1) . '</td>';
	print ' <td>' . $product->getNomUrl(1) . '</td>';
	print ' <td>' . $product->label . '</td>';
	print ' <td>' . dol_print_date($obj->date_next) . '</td>';
	print ' <td>' . $obj->km_next . ' km </td>';
	print ' <td>' . dol_print_date($obj->date_done) . '</td>';
	print ' <td>' . $obj->km_done . ' km </td>';

	$action_url = '<input type="checkbox" name="opeselected[]" value="'.$product->id.'#'.$obj->opid.'"/>';


	if (!empty($vehicule->id) && !empty($product->id)) print ' <td>' . $action_url . '</td>';
	else {
		print '<td><i class="fa fa-times" aria-hidden="true" style="color:red;"></i></i>&nbsp;' . $langs->trans('CantCreate') . '</td>';
	}

	print '</tr>';
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
		$("#orToCreate").DataTable({
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
