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
 */

// Protection contre l'appel direct
if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

// ── Calculs d'affichage ──────────────────────────────────────────────────────

$detProductIcon = ($det->product_type == Operationorderdet::TYPE_SERVICE)
	? '<i class="fa fa-tag opacitymedium" title="'.dol_escape_htmltag($langs->trans('Service')).'"></i>'
	: '<i class="fa fa-cogs opacitymedium" title="'.dol_escape_htmltag($langs->trans('Product')).'"></i>';

$detQtyPrice = price2num($det->qty, 2).' × '.price($det->price);
if ((float) $det->remise_percent > 0) {
	$detQtyPrice .= ' <span class="badge badge-status1">-'.price2num($det->remise_percent, 2).'%</span>';
}

$whHtml = '';
if (!empty($det->fk_warehouse)) {
	dol_include_once('/product/stock/class/entrepot.class.php');
	$entrepot = new Entrepot($db);
	if ($entrepot->fetch((int) $det->fk_warehouse) > 0) {
		$whHtml = $entrepot->getNomUrl(1);
	}
}
?>

<tr class="<?php echo $trClass; ?> workshop-det-row">
	<td class="workshop-jobs-col-type" style="padding-left:1.5em"><?php echo $detProductIcon; ?></td>
	<td class="workshop-jobs-col-label"><small><?php echo dol_escape_htmltag($det->label); ?></small></td>
	<td class="workshop-jobs-col-desc"><small>
		<?php echo !empty($det->description) ? dol_htmlentitiesbr(dol_string_nohtmltag($det->description, 1)) : '<span class="opacitymedium">—</span>'; ?>
	</small></td>
	<td class="right workshop-jobs-col-mo nowraponall"><small><?php echo $detQtyPrice; ?></small></td>
	<td class="right workshop-jobs-col-time nowraponall"><small><strong><?php echo price($det->total_ht); ?></strong></small></td>
	<td class="workshop-jobs-col-billing"><small><?php echo $whHtml ?: '<span class="opacitymedium">—</span>'; ?></small></td>
	<td class="right workshop-jobs-col-actions nowraponall">
		<?php if ($canEditAtStatus) {
			$delDetUrl = $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=delete_det&detid='.(int) $det->id.'&token='.newToken();
			?>
			<a class="reposition" href="<?php echo dol_escape_htmltag($delDetUrl); ?>"
				onclick="return confirm('<?php echo dol_escape_js($langs->trans('ConfirmDeleteDetLine')); ?>');">
				<?php echo img_picto($langs->trans('Delete'), 'delete'); ?>
			</a>
		<?php } ?>
	</td>
</tr>
