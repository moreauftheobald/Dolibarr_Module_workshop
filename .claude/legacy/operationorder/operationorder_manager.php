<?php
require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/barcode.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/modules/barcode/doc/phpbarcode.modules.php';
$barcodeGen = new modPhpbarcode;

if (!($user->admin || $user->hasRight("operationorder", "manager", "read"))) {
		accessforbidden();
}

$langs->load("operationorder@operationorder");
$hookmanager->initHooks(array('OOmanagercard'));


$arraycss[]=dol_buildpath('/operationorder/vendor/bootstrap/css/bootstrap.min.css', 1);
$arraycss[]=dol_buildpath('/operationorder/css/manager.css', 1);
$arrayjs[]=dol_buildpath('/operationorder/vendor/bootstrap/js/bootstrap.min.js', 1);
$arrayjs[]=dol_buildpath('/core/js/lib_head.js.php', 1);

top_htmlhead('', $langs->trans('OperationOrder'), 0, 0, $arrayjs, $arraycss);
?>
<body>
<div class="container-fluid">
	<div id="HeaderBar" class="row">
		<div class="col-md-3">
			<form name="control" id="control" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
				<input type="text" id="masterInput" placeholder="saisie manuelle" class="form-control"/>
			</form>
		</div>
		<div id="userList" class="col-md-9">
			<span>user1</span>
			<span>user2</span>
		</div>
	</div>

	<div id="infosBar" class="row">
		<div class="col-md-12">
			<p>
				<label>Utilisateur courant&nbsp;:&nbsp;</label><span id="infoUser"></span><br />
				<label>OR Courant&nbsp;:&nbsp;</label><span id="infoOR"></span><br />
				<label>Tâche Courante&nbsp;:&nbsp;</label><span id="infoTask"></span><br />
			</p>
			<div id="responseMessageSuccess" style="display:none" class="alert alert-success"></div>
			<div id="responseMessageError" style="display:none" class="alert alert-danger alert-dismissible show"></div>
		</div>
	</div>

	<div id="centerBar" class="row">
		<table id="orList">
			<thead>
			<tr class="table-header">
				<th>Client</th>
				<th>RefOR</th>
				<?php if (isModEnabled("dolifleet")) { ?>
					<th>Immat</th>
				<?php } ?>
				<th>Code Barre</th>
			</tr>
			</thead>
			<tbody>

			</tbody>
		</table>
		<table id="actionList">
			<tr class="table-header">
				<th>Action</th>
				<th>Code</th>
			</tr>
		</table>
		<!--<div class="col-md-6">

		</div>
		<div class="col-md-6">

		</div>-->
	</div>
	<div id="ORLines" class="row">
		<table id="tableLines">
			<thead>
				<tr>
					<th>Ref Article</th>
					<th>Qty</th>
					<th>Action</th>
					<th>Code Barre</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>Vid-05-112</td>
					<td>1</td>
					<td>Start</td>
					<td>Code barre</td>
				</tr>
			</tbody>
		</table>
	</div>

</div>
<div id="dialogforpopup" style="display: none;"></div>
<script src="js/manager.js.php" type="text/javascript"></script>

<?php

print '<script src="'.DOL_URL_ROOT.'/core/js/lib_foot.js.php?lang='.$langs->defaultlang.'"></script>'."\n";

$parameters=array();
$reshook = $hookmanager->executeHooks('formObjectOptionsEnd', $parameters, $object, $action);

?>

</body>
</html>
