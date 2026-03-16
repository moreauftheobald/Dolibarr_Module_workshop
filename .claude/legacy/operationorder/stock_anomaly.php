<?php
/* Copyright (C) 2001-2006	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2017	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2014	Regis Houssin			<regis.houssin@inodbox.com>
 * Copyright (C) 2015		Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2018-2022	Ferran Marcet			<fmarcet@2byte.es>
 * Copyright (C) 2019-2024  Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/product/stock/movement_list.php
 *	\ingroup    stock
 *	\brief      Page to list stock movements
 */

// Load Dolibarr environment
require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/stock.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array('products', 'stocks', 'orders'));
if (isModEnabled('productbatch')) {
	$langs->load("productbatch");
}

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$confirm    = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel = GETPOST('cancel', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : str_replace('_', '', basename(dirname(__FILE__)).basename(__FILE__, '.php')); // To manage different context of search
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$backtopage = GETPOST("backtopage", "alpha");
$optioncss  = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$mode       = GETPOST('mode', 'aZ'); // The output mode ('list', 'kanban', 'hierarchy', 'calendar', ...)

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$msid = GETPOSTINT('msid');
$idproduct = GETPOST('idproduct', 'intcomma');
$product_id = GETPOST("product_id", 'intcomma');

$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search_ref = GETPOST('search_ref', 'alpha');
$search_product_ref = trim(GETPOST("search_product_ref"));
$search_product = trim(GETPOST("search_product"));
$search_qty = trim(GETPOST("search_qty"));

$type = GETPOSTINT("type");

// Load variable for pagination
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	// If $page is not defined, or '' or -1 or if we click on clear filters
	$page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (!$sortfield) {
	$sortfield = "p.rowid";
}
if (!$sortorder) {
	$sortorder = "DESC";
}

$pdluoid = GETPOSTINT('pdluoid');

// Initialize a technical objects
$object = new MouvementStock($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->stock->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array($contextpage)); 	// Note that conf->hooks_modules contains array of activated contexes

$formfile = new FormFile($db);

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

$arrayfields = array(
	'e.ref' => array('label' => "Warehouse", 'checked' => 1, 'position' => 100, 'enabled' => (!($id > 0))), // If we are on specific warehouse, we hide it
	'p.ref' => array('label' => "ProductRef", 'checked' => 1, 'css' => 'maxwidth100', 'position' => 3),
	'p.label' => array('label' => "ProductLabel", 'checked' => 1, 'position' => 5),
	'ps.reel' => array('label' => "Stock", 'checked' => 1, 'position' => 6),
	//'m.datec'=>array('label'=>"DateCreation", 'checked'=>0, 'position'=>500),
	//'m.tms'=>array('label'=>"DateModificationShort", 'checked'=>0, 'position'=>500)
);

include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

$tmpwarehouse = new Entrepot($db);
if ($id > 0 || !empty($ref)) {
	$tmpwarehouse->fetch($id, $ref);
	$id = $tmpwarehouse->id;
}


// Security check
//$result=restrictedArea($user, 'stock', $id, 'entrepot&stock');
$result = restrictedArea($user, 'stock');

// Security check
if (!$user->hasRight('stock', 'mouvement', 'lire')) {
	accessforbidden();
}

$uploaddir = $conf->stock->dir_output.'/movements';

$permissiontoread = $user->hasRight('stock', 'mouvement', 'lire');
$permissiontoadd = $user->hasRight('stock', 'mouvement', 'creer');
$permissiontodelete = $user->hasRight('stock', 'mouvement', 'creer'); // There is no deletion permission for stock movement as we should never delete

$usercanread = $user->hasRight('stock', 'mouvement', 'lire');
$usercancreate = $user->hasRight('stock', 'mouvement', 'creer');
$usercandelete = $user->hasRight('stock', 'mouvement', 'creer');

$error = 0;


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // Both test are required to be compatible with all browsers
		$search_ref = '';
		$search_product_ref = "";
		$search_product = "";
		$search_warehouse = "";
		$toselect = array();
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'MouvementStock';
	$objectlabel = 'MouvementStock';

	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}

$qty = 0;
$price = '0';
$entrepot = 0;

// Correct stock
if ($action == "correct_stock" && $permissiontoadd) {
	$product = new Product($db);
	if (!empty($product_id)) {
		$result = $product->fetch($product_id);
	}

	$error = 0;

	if (empty($product_id)) {
		$error++;
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Product")), null, 'errors');
		$action = 'correction';
	}
	if (!GETPOSTFLOAT("nbpiece")) {
		$error++;
		setEventMessages($langs->trans("ErrorFieldMustBeANumeric", $langs->transnoentitiesnoconv("NumberOfUnit")), null, 'errors');
		$action = 'correction';
	}

	if (!$error) {
		$origin_element = '';
		$origin_id = null;

		$result = $product->correct_stock(
			$user,
			$id,
			GETPOSTFLOAT("nbpiece"),
			GETPOSTINT("mouvement"),
			GETPOST("label", 'alphanohtml'),
			price2num(GETPOST('unitprice'), 'MT'),
			GETPOST('inventorycode', 'alphanohtml'),
			$origin_element,
			$origin_id,
			0,
			$extrafields
		); // We do not change value of stock for a correction

		if ($result > 0) {
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
			exit;
		} else {
			$error++;
			setEventMessages($product->error, $product->errors, 'errors');
			$action = 'correction';
		}
	}

	if (!$error) {
		$action = '';
	}
}

// Transfer stock from a warehouse to another warehouse
if ($action == "transfert_stock" && $permissiontoadd && !$cancel) {
	$error = 0;
	$product = new Product($db);
	if (!empty($product_id)) {
		$result = $product->fetch($product_id);
	}

	if (!(GETPOSTINT("id_entrepot_destination") > 0)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Warehouse")), null, 'errors');
		$error++;
		$action = 'transfert';
	}
	if (empty($product_id)) {
		$error++;
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Product")), null, 'errors');
		$action = 'transfert';
	}
	if (!GETPOSTFLOAT("nbpiece")) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("NumberOfUnit")), null, 'errors');
		$error++;
		$action = 'transfert';
	}
	if ($id == GETPOSTINT("id_entrepot_destination")) {
		setEventMessages($langs->trans("ErrorSrcAndTargetWarehouseMustDiffers"), null, 'errors');
		$error++;
		$action = 'transfert';
	}

	if (isModEnabled('productbatch')) {
		$product = new Product($db);
		$result = $product->fetch($product_id);

		if ($product->hasbatch() && !GETPOST("batch_number")) {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("batch_number")), null, 'errors');
			$error++;
			$action = 'transfert';
		}
	}

	if (!$error) {
		if ($id) {
			$warehouse = new Entrepot($db);
			$result = $warehouse->fetch($id);

			$db->begin();

			$product->load_stock('novirtual'); // Load array product->stock_warehouse

			// Define value of products moved
			$pricesrc = 0;
			if (isset($product->pmp)) {
				$pricesrc = $product->pmp;
			}
			$pricedest = $pricesrc;

			// Remove stock
			$result1 = $product->correct_stock(
				$user,
				$id,
				GETPOSTFLOAT("nbpiece"),
				1,
				GETPOST("label", 'alphanohtml'),
				$pricesrc,
				GETPOST('inventorycode', 'alphanohtml'),
				'',
				null,
				0,
				$extrafields
			);

			// Add stock
			$result2 = $product->correct_stock(
				$user,
				GETPOST("id_entrepot_destination"),
				GETPOSTFLOAT("nbpiece"),
				0,
				GETPOST("label", 'alphanohtml'),
				$pricedest,
				GETPOST('inventorycode', 'alphanohtml'),
				'',
				null,
				0,
				$extrafields
			);

			if (!$error && $result1 >= 0 && $result2 >= 0) {
				$db->commit();

				if ($backtopage) {
					header("Location: ".$backtopage);
					exit;
				} else {
					header("Location: movement_list.php?id=".$warehouse->id);
					exit;
				}
			} else {
				setEventMessages($product->error, $product->errors, 'errors');
				$db->rollback();
				$action = 'transfert';
			}
		}
	}
}

// reverse movement of stock
if ($action == 'confirm_reverse' && $confirm == "yes" && $permissiontoadd) {
	$toselect = array_map('intval', $toselect);

	$sql = "SELECT rowid, label, inventorycode, datem";
	$sql .= " FROM ".MAIN_DB_PREFIX."stock_mouvement";
	$sql .= " WHERE rowid IN (";
	foreach ($toselect as $id) {
		$sql .= ((int) $id).",";
	}
	$sql = rtrim($sql, ',');
	$sql .= ")";

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		$hasSuccess = false;
		$hasError = false;
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			$object->fetch($obj->rowid);
			$reverse = $object->reverseMouvement();
			if ($reverse < 0) {
				$hasError = true;
			} else {
				$hasSuccess = true;
			}
			$i++;
		}
		if ($hasError) {
			setEventMessages($langs->trans("WarningAlreadyReverse", $langs->transnoentities($idAlreadyReverse)), null, 'warnings');
		}
		if ($hasSuccess) {
			setEventMessages($langs->trans("ReverseConfirmed"), null);
		}
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
}

/*
 * View
 */

$form = new Form($db);
$formproduct = new FormProduct($db);
if (isModEnabled('project')) {
	$formproject = new FormProjets($db);
} else {
	$formproject = null;
}
$productlot = new Productlot($db);
$productstatic = new Product($db);
$warehousestatic = new Entrepot($db);

$userstatic = new User($db);

$now = dol_now();


// Build and execute select
// --------------------------------------------------------------------
$sql = "SELECT e.rowid as entrepot_id, e.ref as warehouse_ref, e.lieu, e.fk_parent, e.statut as status,
	ps.reel, ps.rowid as product_stock_id, ps.fk_product,
	p.ref as product_ref, p.label as product_label, p.tosell, p.tobuy";
// Add fields from hooks
$sqlfields = $sql; // $sql fields to remove for count total

$sql .= " FROM  " . $db->prefix() . "product_stock as ps";
$sql .= " JOIN " . $db->prefix() . "entrepot as e ON ps.fk_entrepot = e.rowid";
$sql .= " LEFT JOIN " . $db->prefix() . "product as p ON p.rowid = ps.fk_product";
$sql .= " JOIN (
				SELECT psa.fk_product
				FROM " . $db->prefix() . "product_stock as psa
				JOIN " . $db->prefix() . "entrepot ea  ON psa.fk_entrepot = ea.rowid
				WHERE psa.reel != 0 AND ea.entity IN (" . intval($conf->entity) . ")
				GROUP BY psa.fk_product
				HAVING COUNT(DISTINCT  psa.fk_entrepot) > 1
		) multi ON ps.fk_product = multi.fk_product";
$sql .= " WHERE ps.reel != 0";
$sql .= " AND e.entity IN (" . intval($conf->entity) . ")";

if (!empty($search_product_ref)) {
	$sql .= natural_search('p.ref', $search_product_ref);
}
if (!empty($search_product)) {
	$sql .= natural_search('p.label', $search_product);
}
if ($search_warehouse != '' && $search_warehouse != '-1') {
	$sql .= natural_search('e.rowid', $search_warehouse, 2);
}
if (!empty($product_id) && $product_id != '-1') {
	$sql .= natural_search('p.rowid', $product_id);
}

// Count total nb of records
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	/* The fast and low memory method to get and count full list converts the sql into a sql count */
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$sqlforcount = preg_replace('/GROUP BY .*$/', '', $sqlforcount);
	$resql = $db->query($sqlforcount);
	if ($resql) {
		$objforcount = $db->fetch_object($resql);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
	} else {
		dol_print_error($db);
	}

	if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller than the paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
	$db->free($resql);
}

// Complete request and execute it with limit
$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);


$product = new Product($db);
$warehouse = new Entrepot($db);

if ($idproduct > 0) {
	$product->fetch($idproduct);
}
if ($id > 0 || $ref) {
	$result = $warehouse->fetch($id, $ref);
	if ($result < 0) {
		dol_print_error($db);
	}
}


// Output page
// --------------------------------------------------------------------

$i = 0;
$help_url = 'EN:Module_Stocks_En|FR:Module_Stock|ES:M&oacute;dulo_Stocks';
$title = $langs->trans("StockAnomalies");


// Output page
// --------------------------------------------------------------------

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'bodyforlist mod-product page-stock_movement_list');

// Correct stock
if ($action == "correction") {
	include DOL_DOCUMENT_ROOT.'/product/stock/tpl/stockcorrection.tpl.php';
	print '<br>';
}

// Transfer of units
if ($action == "transfert") {
	include DOL_DOCUMENT_ROOT.'/product/stock/tpl/stocktransfer.tpl.php';
	print '<br>';
}


// Action bar
if ((empty($action) || $action == 'list') && $id > 0) {
	print "<div class=\"tabsAction\">\n";

	$parameters = array();
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $warehouse, $action); // Note that $action and $warehouse may have been
	// modified by hook
	if (empty($reshook)) {
		if ($user->hasRight('stock', 'mouvement', 'creer')) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=transfert&token='.newToken().'">'.$langs->trans("TransferStock").'</a>';
		}

		if ($user->hasRight('stock', 'mouvement', 'creer')) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=correction&token='.newToken().'">'.$langs->trans("CorrectStock").'</a>';
		}
	}

	print '</div><br>';
}

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($mode)) {
	$param .= '&mode='.urlencode($mode);
}
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
if ($id > 0) {
	$param .= '&id='.urlencode((string) ($id));
}
if ($search_product_ref) {
	$param .= '&search_product_ref='.urlencode($search_product_ref);
}
if ($search_product) {
	$param .= '&search_product='.urlencode($search_product);
}
if ($search_warehouse > 0) {
	$param .= '&search_warehouse='.urlencode($search_warehouse);
}
if ($idproduct > 0) {
	$param .= '&idproduct='.urlencode((string) ($idproduct));
}

// List of mass actions available
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="type" value="'.$type.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="page_y" value="">';
print '<input type="hidden" name="mode" value="'.$mode.'">';


$newcardbutton = '';
$massactionbutton = '';
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'movement', 0, '', '', $limit, 0, 0, 1);

// Add code for pre mass action (confirmation or email presend form)
$topicmail = "SendStockMovement";
$modelmail = "movementstock";
$objecttmp = new MouvementStock($db);
$trackid = 'mov'.$warehouse->id;
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';
if ($massaction == 'prereverse') {
	print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans("ConfirmMassReverse"), $langs->trans("ConfirmMassReverseQuestion", count($toselect)), "confirm_reverse", null, '', 0, 200, 500, 1, 'Yes');
}


$moreforfilter = '';

$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $warehouse, $action); // Note that $action and $warehouse may have been modified by hook
if (empty($reshook)) {
	$moreforfilter .= $hookmanager->resPrint;
} else {
	$moreforfilter = $hookmanager->resPrint;
}

if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $moreforfilter;
	print '</div>';
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you don't need reserved height for your table
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

// Fields title search
// --------------------------------------------------------------------
print '<tr class="liste_titre_filter">';
// Product Ref
print '<td class="liste_titre left">';
print '<input class="flat maxwidth75" type="text" name="search_product_ref" value="'.dol_escape_htmltag($search_product_ref).'">';
print '</td>';
// Product label
print '<td class="liste_titre left">';
print '<input class="flat maxwidth100" type="text" name="search_product" value="'.dol_escape_htmltag($search_product).'">';
print '</td>';
// Warehouse
print '<td class="liste_titre maxwidthonsmartphone left">';
//print '<input class="flat" type="text" size="8" name="search_warehouse" value="'.($search_warehouse).'">';
print '</td>';
// Qty
print '<td class="liste_titre maxwidthonsmartphone left">';
print '<input class="flat" type="text" size="8" name="search_qty" value="'.dol_escape_htmltag($search_qty).'">';
print '</td>';
print '<td class="liste_titre maxwidthonsmartphone right">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>'."\n";

$totalarray = array();
$totalarray['nbfield'] = 0;

// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';

print_liste_field_titre($arrayfields['p.ref']['label'], $_SERVER["PHP_SELF"], 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($arrayfields['p.label']['label'], $_SERVER["PHP_SELF"], 'p.label', '', $param, '', $sortfield, $sortorder);
// We are on a specific warehouse card, no filter on other should be possible
print_liste_field_titre($arrayfields['e.ref']['label'], $_SERVER["PHP_SELF"], "e.ref", "", $param, "", $sortfield, $sortorder);
print_liste_field_titre($arrayfields['ps.reel']['label'], $_SERVER["PHP_SELF"], "ps.reel", "", $param, "", $sortfield, $sortorder);
print '<td class="liste_titre maxwidthonsmartphone left"></td>';
// Action column

print '</tr>'."\n";


$arrayofuniqueproduct = array();


// Loop on record
// --------------------------------------------------------------------
$i = 0;
$savnbfield = $totalarray['nbfield'];
$totalarray = array();
$totalarray['nbfield'] = 0;
$imaxinloop = ($limit ? min($num, $limit) : $num);
while ($i < $imaxinloop) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break; // Should not happen
	}

	$productstatic->id = $obj->fk_product;
	$productstatic->ref = $obj->product_ref;
	$productstatic->label = $obj->product_label;
	$productstatic->status = $obj->tosell;
	$productstatic->status_buy = $obj->tobuy;

	$warehousestatic->id = $obj->entrepot_id;
	$warehousestatic->ref = $obj->warehouse_ref;
	$warehousestatic->label = $obj->warehouse_ref;
	$warehousestatic->lieu = $obj->lieu;
	$warehousestatic->fk_parent = $obj->fk_parent;
	$warehousestatic->statut = $obj->status;

	if (!empty($obj->fk_origin)) {
		$origin = $object->get_origin($obj->fk_origin, $obj->origintype);
	} else {
		$origin = '';
	}


	// Show here line of result
	$j = 0;
	print '<tr data-rowid="'.$warehouse->id.'" class="oddeven">';

	// Product ref
	print '<td class="nowraponall">';
	print $productstatic->getNomUrl(1, 'stock');
	print "</td>\n";
	// Product label
	print '<td class="tdoverflowmax150" title="'.dol_escape_htmltag($productstatic->label).'">';
	print $productstatic->label;
	print "</td>\n";
	// Warehouse
	print '<td class="tdoverflowmax150">';
	print $warehousestatic->getNomUrl(1);
	print "</td>\n";

	// Qty
	print '<td class="tdoverflowmax150">';
	print dol_escape_htmltag($obj->reel);
	print "</td>\n";

	// Movement
	print '<td class="tdoverflowmax150 right">';
	print '<a href="' . DOL_URL_ROOT . '/product/stock/product.php?dwid=' . $warehousestatic->id . '&id=' . $productstatic->id . '&nbpiece=' . intval($obj->reel) . '&action=transfert&token=' . newToken() . '&backtopage=' . urlencode($_SERVER["PHP_SELF"]) . '">';
	print img_picto($langs->trans("TransferStock"), 'add', 'class="hideonsmartphone pictofixedwidth" style="color: #a69944"');
	print $langs->trans("TransferStock");
	print "</a>";
	print "</td>\n";

	// Extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
	// Fields from hook
	$parameters = array('arrayfields' => $arrayfields, 'object' => $object, 'obj' => $obj, 'i' => $i, 'totalarray' => &$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	print '</tr>'."\n";

	$i++;
}

// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

$db->free($resql);

$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";


// End of page
llxFooter();
$db->close();
