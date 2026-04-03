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
 * \file       workshop/tpl/or_card_det_row.tpl.php
 * \ingroup    workshop
 * \brief      Ligne détail produit/service d'un job
 *
 * Variables attendues dans le scope appelant (or_card_job_row.tpl.php) :
 *   @var Operationorderdet       $det               L'objet ligne de détail courante
 *   @var string                  $trClass           Classe CSS oddeven de la ligne parente
 *   @var bool                    $canEditAtStatus
 *   @var Operationorder          $object
 *   @var Translate               $langs
 *   @var DoliDB                  $db
 *
 * Colonnes (8 au total, alignées sur la table jobs) :
 *   1+2  (colspan 2) : picto + ref + désignation sur la même ligne
 *   3+4  (colspan 2) : description
 *   5    (colspan 1) : emplacement de stock
 *   6    (colspan 1) : quantité × prix unitaire + badge remise
 *   7    (colspan 1) : total HT ligne
 *   8    (colspan 1) : boutons d'action
 */

// Protection contre l'appel direct
if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

// ── Produit : picto + ref + désignation ─────────────────────────────────────
$prodHtml = '';
if (!empty($det->fk_product)) {
	dol_include_once('/product/class/product.class.php');
	$prodObj = new Product($db);
	if ($prodObj->fetch((int) $det->fk_product) > 0) {
		$prodHtml = $prodObj->getNomUrl(1);
		if (!empty($det->label)) {
			$prodHtml .= ' '.dol_escape_htmltag($det->label);
		}
	}
}
if (empty($prodHtml)) {
	$typeIcon = ($det->product_type == Operationorderdet::TYPE_SERVICE)
		? '<i class="fa fa-tag opacitymedium" title="'.dol_escape_htmltag($langs->trans('Service')).'"></i>'
		: '<i class="fa fa-cogs opacitymedium" title="'.dol_escape_htmltag($langs->trans('Product')).'"></i>';
	$prodHtml = $typeIcon.' '.dol_escape_htmltag($det->label ?: '—');
}

// ── Emplacement de stock ─────────────────────────────────────────────────────
$whHtml = '';
if (!empty($det->fk_warehouse)) {
	dol_include_once('/product/stock/class/entrepot.class.php');
	$entrepot = new Entrepot($db);
	if ($entrepot->fetch((int) $det->fk_warehouse) > 0) {
		$whHtml = $entrepot->getNomUrl(1);
	}
}

// ── Quantité × prix unitaire + remise ────────────────────────────────────────
// Pour les produits physiques : affiche [qty_débitée / qty_prévue] × prix
if ($det->product_type == Operationorderdet::TYPE_PRODUCT) {
	$_qtyDebit = isset($detQtyDebitedMap[(int) $det->id]) ? (float) $detQtyDebitedMap[(int) $det->id] : 0.0;
	if ($_qtyDebit <= 0) {
		$_badgeCls = 'badge-status8';        // gris = rien débité
	} elseif ($_qtyDebit >= (float) $det->qty) {
		$_badgeCls = 'badge-status4';        // vert = tout débité
	} else {
		$_badgeCls = 'badge-status1';        // orange = partiel
	}
	$detQtyPrice  = '<span class="badge '.$_badgeCls.'" title="'.dol_escape_htmltag($langs->trans('QtyDebited')).'">'.price2num($_qtyDebit, 2).'</span>';
	$detQtyPrice .= '<span class="opacitymedium"> / '.price2num($det->qty, 2).'</span>';
	$detQtyPrice .= ' × '.price($det->price);
	unset($_qtyDebit, $_badgeCls);
} else {
	$detQtyPrice = price2num($det->qty, 2).' × '.price($det->price);
}
if ((float) $det->remise_percent > 0) {
	$detQtyPrice .= ' <span class="badge badge-status1">-'.price2num($det->remise_percent, 2).'%</span>';
}
?>

<tr class="<?php echo $trClass; ?> workshop-det-row">

	<!-- 1+2 : picto + ref + désignation sur la même ligne (colspan 2) -->
	<td class="workshop-jobs-col-type workshop-det-col-product" colspan="2" style="padding-left:2em">
		<small><?php echo $prodHtml; ?></small>
	</td>

	<!-- 3+4 : description (colspan 2) -->
	<td class="workshop-jobs-col-desc" colspan="2">
		<small><?php echo !empty($det->description) ? dol_htmlentitiesbr(dol_string_nohtmltag($det->description, 1)) : '<span class="opacitymedium">—</span>'; ?></small>
	</td>

	<!-- 5 : emplacement de stock -->
	<td class="workshop-jobs-col-mo">
		<small><?php echo $whHtml ?: '<span class="opacitymedium">—</span>'; ?></small>
	</td>

	<!-- 6 : quantité × prix + remise -->
	<td class="right workshop-jobs-col-time nowraponall">
		<small><?php echo $detQtyPrice; ?></small>
	</td>

	<!-- 7 : total HT ligne -->
	<td class="right workshop-jobs-col-billing nowraponall">
		<small><strong><?php echo price($det->total_ht); ?></strong></small>
	</td>

	<!-- 8 : boutons d'action -->
	<td class="right workshop-jobs-col-subcontracting nowraponall">
		<?php if ($canEditAtStatus) {
			$delDetUrl = $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=delete_det&detid='.(int) $det->id.'&token='.newToken();
			// Données pour pré-remplir le dialog d'édition
			$editLabel       = dol_escape_js(!empty($det->label) ? $det->label : '—');
			$editDescription = dol_escape_js((string) $det->description);
			$editQty         = price2num($det->qty, 2);
			$editPrice       = price2num($det->price, 'MU');
			$editRemise      = price2num($det->remise_percent, 2);
			?>
			<a class="reposition marginrightonly" href="#"
				onclick="workshopOpenEditDet(<?php echo (int) $det->id; ?>, '<?php echo $editLabel; ?>', '<?php echo $editDescription; ?>', '<?php echo $editQty; ?>', '<?php echo $editPrice; ?>', '<?php echo $editRemise; ?>'); return false;"
				title="<?php echo dol_escape_htmltag($langs->trans('Modify')); ?>">
				<?php echo img_picto($langs->trans('Modify'), 'edit'); ?>
			</a>
			<a class="reposition" href="<?php echo dol_escape_htmltag($delDetUrl); ?>"
				onclick="return confirm('<?php echo dol_escape_js($langs->trans('ConfirmDeleteDetLine')); ?>');">
				<?php echo img_picto($langs->trans('Delete'), 'delete'); ?>
			</a>
		<?php } ?>
	</td>

</tr>
