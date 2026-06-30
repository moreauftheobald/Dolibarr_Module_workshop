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
global $form;
require 'config.php';
dol_include_once('operationorder/class/operationorder.class.php');
dol_include_once('operationorder/lib/operationorder.lib.php');
dol_include_once('product/class/product.class.php');
dol_include_once('product/stock/class/entrepot.class.php');
dol_include_once('product/stock/class/mouvementstock.class.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

if (!$user->hasRight("operationorder", "read")) accessforbidden();

$langs->load('abricot@abricot');
$langs->load('operationorder@operationorder');

$id = GETPOST('id', 'int');
$search_fk_entrepot = GETPOST('search_fk_entrepot', 'int');
$search_fk_user_author = GETPOST('search_fk_user_author', 'int');
$operationOrder = new OperationOrder($db);
$formproduct = new FormProduct($db);

if (!empty($id)) {
	$operationOrder->fetch($id, true);
	//$result = restrictedArea($user, $operationOrder->element, $id, $operationOrder->table_element . '&' . $operationOrder->element);
}
$hookmanager->initHooks(array('operationorderdebitlist'));

/*
 * View
 */

llxHeader('', $langs->trans('OperationOrderdebitList'), '', '');

if ($operationOrder->id > 0) {
	$head = operationorder_prepare_head($operationOrder);
	$picto = 'operationorder@operationorder';
	print dol_get_fiche_head($head, 'debit', $langs->trans('debit'), -1, $picto);
	$linkback = '<a href="'.dol_buildpath('/operationorder/list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';
	$morehtmlref='<div class="refidno">';
	// Ref customer
	$morehtmlref.=$form->editfieldkey("RefCustomer", 'ref_client', $operationOrder->ref_client, $operationOrder, 0, 'string', '', 0, 1);
	$morehtmlref.=$form->editfieldval("RefCustomer", 'ref_client', $operationOrder->ref_client, $operationOrder, 0, 'string', '', null, null, '', 1);
	// Thirdparty
	$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $operationOrder->thirdparty->getNomUrl(1);
	// Project
	if (! empty(isModEnabled("projet"))) {
		$langs->load("projects");
		$morehtmlref.='<br>'.$langs->trans('Project') . ' ';

		if (! empty($object->fk_project)) {
			$proj = new Project($db);
			$proj->fetch($operationOrder->fk_project);
			$morehtmlref.='<a href="'.DOL_URL_ROOT.'/projet/card.php?id=' . $operationOrder->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
			$morehtmlref.=$proj->ref;
			$morehtmlref.='</a>';
		} else {
			$morehtmlref.='';
		}
	}
	$morehtmlref.='</div>';
	dol_banner_tab($operationOrder, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	print '<div class="underbanner clearboth"></div>';
}

$sql = 'SELECT m.rowid as rowid, m.datem as datem , m.fk_product as fk_product, p.label as product_label, m.fk_entrepot as fk_entrepot, m.fk_user_author as fk_user_author, m.label as label, m.type_mouvement as type_mouvement, m.value as value ';
$sql.= 'FROM '.$db->prefix().'stock_mouvement AS m ';
$sql.= 'INNER JOIN '.$db->prefix().'product AS p on p.rowid = m.fk_product ';
$sql.= 'WHERE  m.origintype="operationorder@operationorder" ';
if ($operationOrder->id > 0) $sql.= ' AND m.fk_origin = '.$operationOrder->id;
$resql = $db->query($sql);
if ($resql) {
	$prodlist = array();
	$authorlist = array();
	while ($obj = $db->fetch_object($resql)) {
		$prodlist[$obj->fk_product] = $obj->product_label;
		$authorlist[$obj->fk_user_author] = $obj->fk_user_author;
	}
}
if (!empty($search_fk_entrepot) && $search_fk_entrepot > 0) $sql.= ' AND m.fk_entrepot = '.$search_fk_entrepot;
if (!empty($search_fk_user_author) && $search_fk_user_author > 0) $sql.= ' AND m.fk_user_author = '.$search_fk_user_author;


$resql = $db->query($sql);
if ($resql) {
	$prodlist = array();
	$authorlist = array();
	while ($obj = $db->fetch_object($resql)) {
		$prodlist[$obj->fk_product] = $obj->product_label;
		$authorlist[$obj->fk_user_author] = $obj->fk_user_author;
	}
}
$moutypearray[0] = $langs->trans('StockIncreaseAfterCorrectTransfer');
$moutypearray[1] = $langs->trans('StockDecreaseAfterCorrectTransfer');
$moutypearray[2] = $langs->trans('StockDecrease');
$moutypearray[3] = $langs->trans('StockIncrease');

$formcore = new TFormCore($_SERVER['PHP_SELF'], 'form_list_operationorderdebit', 'POST');

$nbLine = GETPOST('limit');
if (empty($nbLine)) $nbLine = !empty($user->conf->MAIN_SIZE_LISTE_LIMIT) ? $user->conf->MAIN_SIZE_LISTE_LIMIT : getDolGlobalString("MAIN_SIZE_LISTE_LIMIT");


// List configuration
$listViewConfig = array(
	'view_type' => 'list' // default = [list], [raw], [chart]
	,'allow-fields-select' => true
	,'limit'=>array(
		'nbLine' => $nbLine
	)
	,'list' => array(
		'title' => $langs->trans('OperationOrderdebitList')
		,'image' => 'title_generic.png'
		,'picto_precedent' => '<'
		,'picto_suivant' => '>'
		,'noheader' => 0
		,'messageNothing' => $langs->trans('NoOperationOrderdebit')
		,'picto_search' => img_picto('', 'search.png', '', 0)
		,'massactions'=>array(

		)
		,'param_url' => '&id='.GETPOST('id')
	)
	,'subQuery' => array()
	,'link' => array()
	,'type' => array(
		'datem' => 'datetime',
		'value'=>'number'// [datetime], [hour], [money], [number], [integer]
	)
	,'search' => array(
		'datem' => array('search_type' => 'calendars', 'allow_is_null' => false, 'table' => 'm')
		,'fk_product' => array('search_type' => $prodlist)
		,'product_label' => array('search_type' => true, 'table' => 'p', 'field' => 'label')
		,'fk_entrepot' => array('search_type' => 'override', 'override'=> $formproduct->selectWarehouses($search_fk_entrepot, 'search_fk_entrepot', '', 1))
		,'fk_user_author' => array('search_type' => 'override', 'override'=> $form->select_dolusers($search_fk_user_author, 'search_fk_user_author', 1, '', 0, $authorlist))
		,'label' => array('search_type' => true, 'table' => 'm', 'field' => 'label')
		,'type_mouvement' => array('search_type' => $moutypearray)
		,'value' => array('search_type' => true, 'table' => 'm', 'field' => 'value')
	)
	,'translate' => array()
	,'hide' => array(
		'rowid' // important : rowid doit exister dans la query sql pour les checkbox de massaction
	)
	,'title'=>array (
		'datem' => $langs->trans('datem'),
		'fk_product' => $langs->trans('fk_product'),
		'product_label' => $langs->trans('product_label'),
		'fk_entrepot' => $langs->trans('fk_entrepot'),
		'fk_user_author' => $langs->trans('fk_user_author'),
		'label' => $langs->trans('mlabel'),
		'type_mouvement' => $langs->trans('type_mouvement'),
		'value' => $langs->trans('qtyvalue')
	)
	,'eval'=>array(
		'fk_product' => '_getProductNomURL(\'@fk_product@\')',
		'fk_entrepot' => '_getWharehouseURL(\'@fk_entrepot@\')',
		'fk_user_author' => '_getUserNomURL(\'@fk_user_author@\')',
		'type_mouvement' => '_getMouvemementPicto(\'@rowid@\')',
		'value' => ' _getqty(\'@value@\')',
	)
	,'sortfield'=>'datem'
	,'sortorder'=>'DESC'
);

$r = new Listview($db, 'operationorderdebit');

// Change view from hooks
$parameters=array('listViewConfig' => $listViewConfig);

print '<input type="hidden" name="id" value="'.GETPOST('id').'"/>';
echo $r->render($sql, $listViewConfig);

$parameters=array('sql'=>$sql);
$reshook=$hookmanager->executeHooks('printFieldListFooter', $parameters, $object);    // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

$formcore->end_form();

llxFooter('');
$db->close();

function _getUserNomURL($fk_user)
{
	global $db;
	if (!empty($fk_user)) {
		$userNomUrl = new User($db);
		$userNomUrl->fetch($fk_user);
		return $userNomUrl->getNomUrl();
	} else return '';
}

function _getProductNomURL($fk_product)
{
	global $db;
	if (!empty($fk_product)) {
		$productNomUrl = new Product($db);
		$productNomUrl->fetch($fk_product);
		return $productNomUrl->getNomUrl();
	} else return 'error';
}

function _getWharehouseURL($fk_entrepot)
{
	global $db;
	if (!empty($fk_entrepot)) {
		$entrepotNomUrl = new Entrepot($db);
		$entrepotNomUrl->fetch($fk_entrepot);
		return $entrepotNomUrl->getNomUrl();
	} else return 'error';
}

function _getMouvemementPicto($id)
{
	global $db;
	if (!empty($id)) {
		$getMouvemementPicto = new MouvementStock($db);
		$getMouvemementPicto->fetch($id);
		return $getMouvemementPicto->getTypeMovement();
	} else return 'error';
}

function _getqty($qty)
{
	if (!empty($qty)) {
		if ($qty<0) return '<span class="stockmovementexit">-' . abs($qty) . '</span>';
		else return '<span class="stockmovemententry">+' . abs($qty) . '</span>';
	} else return '0';
}
