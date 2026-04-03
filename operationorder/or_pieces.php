<?php
/* Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
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
 * \file        operationorder/or_pieces.php
 * \ingroup     workshop
 * \brief       Onglet de débit des pièces d'un Ordre de Réparation
 *
 * Affiche toutes les lignes produit (TYPE_PRODUCT) issues des jobs de l'OR,
 * avec la quantité prévue, la quantité déjà débitée et un formulaire pour
 * réaliser de nouveaux mouvements de stock (livraison / retour).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
dol_include_once('/workshop/class/operationorder.class.php');
dol_include_once('/workshop/class/operationorder_jobs.class.php');
dol_include_once('/workshop/class/operationorderdet.class.php');
dol_include_once('/workshop/lib/workshop_operationorder.lib.php');

$langs->loadLangs(array('workshop@workshop', 'stocks', 'products'));

$action = GETPOST('action', 'aZ09');
$id     = GETPOSTINT('id');

$object      = new Operationorder($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('orpieces', 'globalcard'));

$extrafields->fetch_name_optionals_label($object->table_element);

// Chargement de l'objet
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Droits
$permissiontoread = $user->hasRight('workshop', 'operationorders', 'read');
$permissiontoadd  = $user->hasRight('workshop', 'operationorders', 'write');

// Sécurité
if (!isModEnabled('workshop')) {
	accessforbidden();
}
if (!$permissiontoread) {
	accessforbidden();
}
if (empty($object->id)) {
	accessforbidden();
}

// Type d'élément det pour les mouvements de stock
// Format attendu par MouvementStock::get_origin() dans la case default :
// "classname@modulename" → dol_include_once('/workshop/class/operationorderdet.class.php')
$detElementType = 'operationorderdet@workshop';

/*
 * Actions
 */

if ($action === 'debit-stock' && $permissiontoadd) {
	$entrepots = GETPOST('entrepot', 'array');
	$debits    = GETPOST('debit', 'array');
	$error     = 0;

	$db->begin();

	foreach ($debits as $detId => $qtyStr) {
		$qtyStr   = trim((string) $qtyStr);
		if ($qtyStr === '' || !is_numeric(str_replace(',', '.', $qtyStr))) {
			continue;
		}
		$qtyDebit = (float) price2num(str_replace(',', '.', $qtyStr), 'MU');
		if ($qtyDebit == 0.0) {
			continue;
		}

		$entrepotId = (int) ($entrepots[$detId] ?? 0);
		if ($entrepotId <= 0) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Warehouse')), null, 'errors');
			$error++;
			continue;
		}

		// Charger et vérifier la ligne det
		$det = new Operationorderdet($db);
		if ($det->fetch((int) $detId) <= 0) {
			$error++;
			continue;
		}

		// Vérifier que la ligne appartient bien à cet OR
		$detJob = new Operationorder_jobs($db);
		if ($detJob->fetch((int) $det->fk_operationorder_jobs) <= 0) {
			$error++;
			continue;
		}
		if ((int) $detJob->fk_operationorder !== (int) $object->id) {
			$error++;
			continue;
		}

		if (empty($det->fk_product)) {
			$error++;
			continue;
		}

		$mvt              = new MouvementStock($db);
		$mvt->origin_type = 'operationorderdet@workshop'; // format classname@modulename pour get_origin()
		$mvt->origin_id   = (int) $det->id;         // → fk_origin dans llx_stock_mouvement
		$label = 'Débit sur '.$object->ref;

		if ($qtyDebit > 0) {
			// Débit = sortie de stock (livraison)
			$result = $mvt->livraison(
				$user,
				(int) $det->fk_product,
				$entrepotId,
				$qtyDebit,
				0,
				$label
			);
		} else {
			// Négatif = retour en stock (réception)
			$result = $mvt->reception(
				$user,
				(int) $det->fk_product,
				$entrepotId,
				abs($qtyDebit),
				0,
				$label
			);
		}

		if ($result < 0) {
			$error++;
			setEventMessages($mvt->error, $mvt->errors, 'errors');
		}
	}

	if (!$error) {
		$db->commit();
		setEventMessage($langs->trans('StockMovementsCreated'));
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	} else {
		$db->rollback();
	}
}

/*
 * Vue
 */

$form        = new Form($db);
$formproduct = new FormProduct($db);

llxHeader('', $langs->trans('ORDebitPieces'), '', '', 0, 0, '', '', '', 'mod-workshop page-or_pieces');

$head = operationorderPrepareHead($object);

print dol_get_fiche_head($head, 'pieces', $langs->trans('ORDebitPieces'), -1, 'fa-tools', 0, '', '', 0, '', 1);

$linkback = '<a href="'.dol_buildpath('/workshop/operationorder/or_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '');

// ── Tableau des lignes produits à débiter ─────────────────────────────────────

$sqlLines  = 'SELECT d.rowid as det_id, d.fk_product, d.label, d.qty, d.fk_warehouse,';
$sqlLines .= ' j.rowid as job_id, j.label as job_label,';
$sqlLines .= ' COALESCE(-SUM(sm.value), 0) as qty_debited';
$sqlLines .= ' FROM '.MAIN_DB_PREFIX.'workshop_operationorderdet d';
$sqlLines .= ' INNER JOIN '.MAIN_DB_PREFIX.'workshop_operationorder_jobs j ON j.rowid = d.fk_operationorder_jobs';
$sqlLines .= ' LEFT JOIN '.MAIN_DB_PREFIX.'stock_mouvement sm';
$sqlLines .= "   ON sm.fk_origin = d.rowid AND sm.origintype IN ('".$db->escape($detElementType)."', 'workshop_operationorderdet')";
$sqlLines .= ' WHERE j.fk_operationorder = '.((int) $object->id);
$sqlLines .= ' AND d.product_type = '.((int) Operationorderdet::TYPE_PRODUCT);
$sqlLines .= ' GROUP BY d.rowid, j.rowid, j.label, j.rang';
$sqlLines .= ' ORDER BY j.rang ASC, d.rowid ASC';

$resLines = $db->query($sqlLines);

print '<div class="fichecenter">';
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="debit-stock">';

print '<table class="noborder allwidth">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Product').'</td>';
print '<td>'.$langs->trans('Job').'</td>';
print '<td class="right">'.$langs->trans('QtyPlanned').'</td>';
print '<td class="right">'.$langs->trans('QtyDebited').'</td>';
print '<td>'.$langs->trans('Warehouse').'</td>';
print '<td class="right">'.$langs->trans('QtyToDebit').'</td>';
print '</tr>';

$nbLines = 0;
if ($resLines) {
	while ($lineObj = $db->fetch_object($resLines)) {
		$nbLines++;

		// Lien produit
		$prodHtml = '';
		if (!empty($lineObj->fk_product)) {
			$prodObj = new Product($db);
			if ($prodObj->fetch((int) $lineObj->fk_product) > 0) {
				$prodHtml = $prodObj->getNomUrl(1);
				if (!empty($lineObj->label)) {
					$prodHtml .= ' — '.dol_escape_htmltag($lineObj->label);
				}
			}
		}
		if (empty($prodHtml)) {
			$prodHtml = dol_escape_htmltag($lineObj->label ?: '—');
		}

		$qtyDebited = (float) $lineObj->qty_debited;
		$qtyPlanned = (float) $lineObj->qty;
		$remaining  = max(0, $qtyPlanned - $qtyDebited);

		// Badge couleur selon avancement
		if ($qtyDebited <= 0) {
			$badgeClass = 'badge-status0';
		} elseif ($qtyDebited >= $qtyPlanned) {
			$badgeClass = 'badge-status4';
		} else {
			$badgeClass = 'badge-status1';
		}

		$defaultWh = !empty($lineObj->fk_warehouse) ? (int) $lineObj->fk_warehouse : 0;

		print '<tr class="oddeven">';
		print '<td>'.$prodHtml.'</td>';
		print '<td>'.dol_escape_htmltag((string) $lineObj->job_label).'</td>';
		print '<td class="right">'.price2num($qtyPlanned, 2).'</td>';
		print '<td class="right"><span class="badge '.$badgeClass.'">'.price2num($qtyDebited, 2).'</span></td>';
		print '<td>';
		print $formproduct->selectWarehouses($defaultWh, 'entrepot['.(int) $lineObj->det_id.']', '', 1, 0, (int) $lineObj->fk_product, '', 1);
		print '</td>';
		print '<td class="right">';
		print '<input type="text" name="debit['.(int) $lineObj->det_id.']" class="flat width75 right"';
		print ' value="'.($remaining > 0 ? price2num($remaining, 2) : '0').'">';
		print '</td>';
		print '</tr>';
	}
	$db->free($resLines);
}

if ($nbLines === 0) {
	print '<tr><td colspan="6" class="opacitymedium center">'.$langs->trans('NoProductLinesToDebit').'</td></tr>';
}

print '</table>';

if ($nbLines > 0 && $permissiontoadd) {
	print '<div class="tabsAction">';
	print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans('DebitStock')).'">';
	print '</div>';
}

print '</form>';
print '</div>';

// ── Historique des mouvements de stock ────────────────────────────────────────

print '<div class="fichecenter">';
print load_fiche_titre($langs->trans('StockMovements'), '', 'stock');

// Sous-requête : rowids des lignes det appartenant à cet OR
$sqlDetIds  = 'SELECT d.rowid FROM '.MAIN_DB_PREFIX.'workshop_operationorderdet d';
$sqlDetIds .= ' INNER JOIN '.MAIN_DB_PREFIX.'workshop_operationorder_jobs j ON j.rowid = d.fk_operationorder_jobs';
$sqlDetIds .= ' WHERE j.fk_operationorder = '.((int) $object->id);
$sqlDetIds .= ' AND d.product_type = '.((int) Operationorderdet::TYPE_PRODUCT);

$sqlHisto  = 'SELECT sm.rowid, sm.datem, sm.fk_product, p.ref as product_ref, p.label as product_label,';
$sqlHisto .= ' sm.fk_entrepot, e.lieu as entrepot_label,';
$sqlHisto .= ' sm.fk_user_author, sm.label as mvt_label, sm.value, sm.fk_origin';
$sqlHisto .= ' FROM '.MAIN_DB_PREFIX.'stock_mouvement sm';
$sqlHisto .= ' INNER JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid = sm.fk_product';
$sqlHisto .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entrepot e ON e.rowid = sm.fk_entrepot';
$sqlHisto .= " WHERE sm.origintype IN ('".$db->escape($detElementType)."', 'workshop_operationorderdet')";
$sqlHisto .= ' AND sm.fk_origin IN ('.$sqlDetIds.')';
$sqlHisto .= ' ORDER BY sm.datem DESC';

$resHisto = $db->query($sqlHisto);

print '<table class="noborder allwidth">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Date').'</td>';
print '<td>'.$langs->trans('Product').'</td>';
print '<td>'.$langs->trans('Job').'</td>';
print '<td>'.$langs->trans('Warehouse').'</td>';
print '<td>'.$langs->trans('AuthorRequest').'</td>';
print '<td>'.$langs->trans('Label').'</td>';
print '<td class="right">'.$langs->trans('Qty').'</td>';
print '</tr>';

$nbHisto = 0;
if ($resHisto) {
	while ($h = $db->fetch_object($resHisto)) {
		$nbHisto++;

		$qtyVal  = (float) $h->value;
		$qtyHtml = $qtyVal < 0
			? '<span class="stockmovementexit">'.price2num($qtyVal, 2).'</span>'
			: '<span class="stockmovemententry">+'.price2num($qtyVal, 2).'</span>';

		// Lien produit
		$prodObj = new Product($db);
		if ($prodObj->fetch((int) $h->fk_product) > 0) {
			$prodHistoHtml = $prodObj->getNomUrl(1);
		} else {
			$prodHistoHtml = dol_escape_htmltag($h->product_ref.' — '.$h->product_label);
		}

		// Entrepôt
		if (!empty($h->fk_entrepot)) {
			$entrepotObj = new Entrepot($db);
			if ($entrepotObj->fetch((int) $h->fk_entrepot) > 0) {
				$whHtml = $entrepotObj->getNomUrl(1);
			} else {
				$whHtml = dol_escape_htmltag($h->entrepot_label);
			}
		} else {
			$whHtml = '—';
		}

		// Auteur
		$userObj = new User($db);
		$userHtml = '';
		if (!empty($h->fk_user_author) && $userObj->fetch((int) $h->fk_user_author) > 0) {
			$userHtml = $userObj->getNomUrl(1);
		}

		// Libellé det + job via fk_origin
		$detJobLabel = '';
		$detObj2 = new Operationorderdet($db);
		if (!empty($h->fk_origin) && $detObj2->fetch((int) $h->fk_origin) > 0) {
			$detJob2 = new Operationorder_jobs($db);
			if ($detJob2->fetch((int) $detObj2->fk_operationorder_jobs) > 0) {
				$detJobLabel = dol_escape_htmltag((string) $detJob2->label);
			}
		}

		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($db->jdate($h->datem), 'dayhour').'</td>';
		print '<td>'.$prodHistoHtml.'</td>';
		print '<td>'.$detJobLabel.'</td>';
		print '<td>'.$whHtml.'</td>';
		print '<td>'.$userHtml.'</td>';
		print '<td>'.dol_escape_htmltag((string) $h->mvt_label).'</td>';
		print '<td class="right">'.$qtyHtml.'</td>';
		print '</tr>';
	}
	$db->free($resHisto);
} else {
	dol_syslog('or_pieces.php: history query failed: '.$db->lasterror(), LOG_ERR);
}

if ($nbHisto === 0) {
	print '<tr><td colspan="7" class="opacitymedium center">'.$langs->trans('NoStockMovements').'</td></tr>';
}

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
