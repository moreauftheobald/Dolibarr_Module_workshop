<?php

// Libraries
require '../config.php';

// Translations
$langs->load('operationorder@operationorder');


// Appel des class

dol_include_once('core/class/html.form.class.php');
dol_include_once('user/class/user.class.php');
dol_include_once('fourn/class/fournisseur.commande.class.php');

/*
 * Actions
 */

llxHeader();

_fiche1();
_fiche2();

/*
 * View
 */

$action=GETPOST("action");
if ($action=='search1') {
	//cf1 = source (client CFXX), cf2 = target (Fournisseur CF00)
	$sql= 'SELECT cf1.rowid as rowid1, cf2.rowid as rowid2, cf1.ref as refC, cf2.rowid, cf2.ref as refF, cf1.fk_statut as statut ';
	$sql.= ' FROM `'.$db->prefix().'commande_fournisseur` as cf1';
	$sql.= ' INNER JOIN `'.$db->prefix().'element_element` as el1 on el1.fk_target = cf1.rowid  and el1.targettype = "order_supplier" and el1.sourcetype="commande"';
	$sql.= ' INNER JOIN `'.$db->prefix().'element_element` as el2 on el2.fk_source = el1.fk_source AND el2.sourcetype="commande"  and el2.targettype ="order_supplier"';
	$sql.= ' INNER JOIN `'.$db->prefix().'commande_fournisseur` as cf2 on cf2.rowid = el2.fk_target';
	$sql .= ' WHERE cf1.entity = '.$conf->entity . ' AND cf2.entity = 1';
	$sql .= ' AND cf2.rowid = "'. GETPOST("order").'"';
	$sql .= ' AND cf1.fk_statut IN (3,4) AND cf2.fk_statut IN (3,4)';
	$resql=$db->query($sql);

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'">';
	print '<thead>';
	print '<tr class="liste_titre_filter" >';
	print ' <th class="liste_titre" >' . $langs->trans('refFournisseur') . '</th>';
	print ' <th class="liste_titre" >' . $langs->trans('refClient') . '</th>';
	print ' <th class="liste_titre" >' . $langs->trans('statut') . '</th>';
	print '</tr>';
	print '</thead>';

	print '<tbody>';
	while ($object = $db->fetch_object($resql)) {
		//affichage statut et bulle commande client CFXX
		$commandeC = new CommandeFournisseur($db);
		$commandeC->fetch($object->rowid1);
		$NomUrl = $commandeC->getNomUrl(1);

		print '<tr >';
		print ' <td  align="left"> ' . $object->refF. '</td>';
		print ' <td  align="left"> ' . $NomUrl. '</td>';
		print ' <td  align="left"> ' . $commandeC->getLibStatut(2) . '</td>';
		print '</tr>';
	}
}

if ($action=='search2') {
	$tableau = GETPOST("product");
	$produit = "";
	foreach ($tableau as $val) {
		$produit .= $val.",";
	}
	$produit = substr($produit, 0, -1);

	$sql= 'SELECT  cfdet.rowid as id, p.ref as refP, p.label as product, cf1.ref as ref1, cf2.ref as refF, cf1.rowid as rowid1, cf2.rowid as rowid2';
	$sql.= ' FROM `'.$db->prefix().'commande_fournisseur` as cf1';
	$sql.= ' INNER JOIN `'.$db->prefix().'element_element` as el1 on el1.fk_target = cf1.rowid  and el1.targettype = "order_supplier" and el1.sourcetype="commande"';
	$sql.= ' INNER JOIN `'.$db->prefix().'element_element` as el2 on el2.fk_source = el1.fk_source AND el2.sourcetype="commande"  and el2.targettype ="order_supplier"';
	$sql.= ' INNER JOIN `'.$db->prefix().'commande_fournisseur` as cf2 on cf2.rowid = el2.fk_target';
	$sql.= ' INNER JOIN `'.$db->prefix().'commande_fournisseurdet` as cfdet on cfdet.fk_commande= cf2.rowid ';
	$sql.= ' INNER JOIN `'.$db->prefix().'product` as p on p.rowid=cfdet.fk_product';
	$sql .= ' WHERE cf1.entity = '.$conf->entity;
	$sql .= ' AND cf1.fk_statut IN (3,4) ';
	$sql .= ' AND p.rowid IN ('.$produit.')';
	$sql .= ' ORDER BY p.label';
	$resql=$db->query($sql);

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'">';
	print '<thead>';
	print '<tr class="liste_titre_filter" >';
	print ' <th class="liste_titre" >' . $langs->trans('ref') . '</th>';
	print ' <th class="liste_titre" >' . $langs->trans('label') . '</th>';
	print ' <th class="liste_titre" >' . $langs->trans('refFournisseur') . '</th>';
	print ' <th class="liste_titre" >' . $langs->trans('refClient') . '</th>';
	print ' <th class="liste_titre" >' . $langs->trans('statut') . '</th>';
	print '</tr>';
	print '</thead>';

	print '<tbody>';
	while ($object = $db->fetch_object($resql)) {
		//affichage statut et bulle commande client CFXX
		$commande = new CommandeFournisseur($db);
		$commande->fetch($object->rowid1);

		print '<tr >';
		print ' <td  align="left"> ' . $object->refP. '</td>';
		print ' <td  align="left"> ' . $object->product. '</td>';
		print ' <td  align="left"> ' . $object->refF. '</td>';
		print ' <td  align="left"> ' . $commande->getNomUrl(1). '</td>';
		print ' <td  align="left"> ' . $commande->getLibStatut(2) . '</td>';
		print '</tr>';
	}
}


llxFooter();

//---------------------------

function _fiche1()
{
	global $db,$langs,$conf;

	//cf1 = source (commandefourn),  cf2 = target (order_supplier).
	$sql= 'SELECT cf1.rowid as idC, cf1.ref as refC, cf2.rowid as idF, cf2.ref as refF';
	$sql.= ' FROM `'.$db->prefix().'commande_fournisseur` as cf1';
	$sql.= ' INNER JOIN `'.$db->prefix().'element_element` as el1 on el1.fk_target = cf1.rowid  and el1.targettype = "order_supplier" and el1.sourcetype="commande"';
	$sql.= ' INNER JOIN `'.$db->prefix().'element_element` as el2 on el2.fk_source = el1.fk_source AND el2.sourcetype="commande"  and el2.targettype ="order_supplier"';
	$sql.= ' INNER JOIN `'.$db->prefix().'commande_fournisseur` as cf2 on cf2.rowid = el2.fk_target';
	$sql .= ' WHERE cf1.entity = '.$conf->entity . ' AND cf2.entity = 1';
	$sql .= ' AND cf2.fk_statut IN (3,4) AND cf1.fk_statut IN (3,4)';
	$sql .= ' ORDER BY cf2.ref';
	$resql=$db->query($sql);

	$orderlist = array();
	if ($resql) {
		while ( $object = $db->fetch_object($resql) ) {
			$orderlist[$object->idF] = $object->refF;
		}
	}
	//Ent�te page
	$page_name = "Commande";
	print load_fiche_titre($langs->trans($page_name), $linkback, "generic");

	print '<form role="form" autocomplete="off" class="form" method="post"  action="'.$_SERVER['PHP_SELF'].'" >';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	$form = new Form($db);
	print '<label for="order">'.$langs->transnoentities('OrderNumber').'</label>';
	print $form->selectarray('order', $orderlist, GETPOST('order'), 0, 0, 0, '', 0, 0, 0, '', '', 1);
	print '<button type="submit" class="btn btn-success btn-strong" name="action" value="search1" >'.$langs->transnoentities('OrderBtnSubmitSearch').'</button>';
	print '</form>';
}


function _fiche2()
{
	global $db,$langs,$conf;

	$sql= 'SELECT p.rowid as id, cfdet.fk_commande, p.barcode as barcode, cfdet.fk_product, p.label as product, cf.ref, p.ref as ref';
	$sql.= ' FROM `'.$db->prefix().'commande_fournisseurdet` as cfdet';
	$sql.= ' INNER JOIN `'.$db->prefix().'product` as p on p.rowid=cfdet.fk_product';
	$sql.= ' INNER JOIN `'.$db->prefix().'commande_fournisseur` as cf on cf.rowid = cfdet.fk_commande';
	$sql .= ' WHERE cf.entity = '.$conf->entity;
	$sql .= ' AND cf.fk_statut IN (3,4) AND p.fk_product_type = 0';
	$sql .= ' ORDER BY p.label';
	$resql=$db->query($sql);

	$productlist = array();
	if ($resql) {
		while ( $object = $db->fetch_object($resql) ) {
			$productlist[$object->id] = $object->ref ."-".$object->product;
		}
	}

	print '<form role="form" autocomplete="off" class="form" method="post"  action="'.$_SERVER['PHP_SELF'].'" >';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	$form = new Form($db);
	print '<label for="order">'.$langs->transnoentities('Product').'</label>';
	print $form->multiselectarray('product', $productlist, GETPOST('product'));
	print '<button type="submit" class="btn btn-success btn-strong" name="action" value="search2" >'.$langs->transnoentities('ProductBtnSubmitSearch').'</button>';
	print '</form>';
}
