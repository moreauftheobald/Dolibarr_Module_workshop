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
 * \file       workshop/tpl/or_card_job_row.tpl.php
 * \ingroup    workshop
 * \brief      Bloc d'un job : ligne job + lignes détail produit + bouton ajout + ligne totaux
 *
 * Variables attendues dans le scope appelant (or_card_jobs_table.tpl.php) :
 *   @var Operationorder_jobs     $job               L'objet job courant
 *   @var string                  $trClass           Classe CSS oddeven de la ligne
 *   @var array                   $serviceTypeCache  Map fk_service_type → ServiceType
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

// ── Calculs d'affichage spécifiques à ce job ─────────────────────────────────

// Badge type de service
$serviceTypeBadge = '';
if (!empty($job->fk_service_type) && isset($serviceTypeCache[$job->fk_service_type])) {
	$st = $serviceTypeCache[$job->fk_service_type];
	$serviceTypeBadge = '<span class="badge badge-status4 badge-status">'.dol_escape_htmltag($st->label).'</span>';
}

// Tiers de facturation
$billingHtml = '';
if (!empty($job->fk_soc)) {
	$socBill = new Societe($db);
	if ($socBill->fetch((int) $job->fk_soc) > 0) {
		$billingHtml = $socBill->getNomUrl(1);
	}
}

// MO : qty × taux + remise + TVA
$prixMO = (float) $job->prix_mo;
$qtyMO  = (float) $job->qty_mo;
$remise = (float) $job->remise_percent;
$moHtml = price2num($qtyMO, 2).'h × '.price($prixMO);
if ($remise > 0) {
	$moHtml .= ' <span class="badge badge-status1" title="'.dol_escape_htmltag($langs->trans('RemiseMO')).'">-'.price2num($remise, 2).'%</span>';
}
$tvaInfoParts = array();
if ($job->tva_tx_mo !== null && $job->tva_tx_mo !== '') {
	$tvaInfoParts[] = 'MO '.price2num($job->tva_tx_mo, 2).'%';
}
if ($job->tva_tx_st !== null && $job->tva_tx_st !== '') {
	$tvaInfoParts[] = 'ST '.price2num($job->tva_tx_st, 2).'%';
}
if (!empty($tvaInfoParts)) {
	$moHtml .= '<br><span class="opacitymedium small">TVA: '.implode(' / ', $tvaInfoParts).'</span>';
}

// Temps pointé vs prévu
$tsTotal   = (float) $job->time_spent;
$tsH       = (int) floor($tsTotal / 3600);
$tsM       = (int) floor(($tsTotal % 3600) / 60);
$tsDisplay = $tsH.'h'.($tsM > 0 ? str_pad((string) $tsM, 2, '0', STR_PAD_LEFT) : '');
$qtyH      = price2num($qtyMO, 2);
$timeHtml  = '<span title="'.dol_escape_htmltag($langs->trans('TimeSpent')).'">'.dol_escape_htmltag($tsDisplay).'</span>'
	.'<span class="opacitymedium"> / '.$qtyH.' h</span>';
?>

<!-- Ligne job -->
<tr class="<?php echo $trClass; ?> workshop-job-row" id="job_<?php echo (int) $job->id; ?>">
	<td class="workshop-jobs-col-type">
		<?php
		echo $serviceTypeBadge ?: '<span class="opacitymedium">—</span>';
		$linkedVoIds = $job->fetchLinkedMaintenanceOperationIds();
		if (is_array($linkedVoIds) && !empty($linkedVoIds)) {
			foreach ($linkedVoIds as $voId) {
				$voItem = new WorkshopVehiculeOperation($db);
				if ($voItem->fetch($voId) > 0) {
					echo '<br><small class="opacitymedium"><i class="fa fa-calendar-check"></i> '.$voItem->getName().'</small>';
				}
			}
		}
		?>
	</td>
	<td class="workshop-jobs-col-label"><strong><?php echo dol_escape_htmltag((string) $job->label); ?></strong></td>
	<td class="workshop-jobs-col-desc">
		<?php echo !empty($job->description) ? dol_htmlentitiesbr(dol_string_nohtmltag($job->description, 1)) : '<span class="opacitymedium">—</span>'; ?>
	</td>
	<td class="right workshop-jobs-col-mo nowraponall"><?php echo $moHtml; ?></td>
	<td class="right workshop-jobs-col-time nowraponall"><?php echo $timeHtml; ?></td>
	<td class="workshop-jobs-col-billing"><?php echo $billingHtml ?: '<span class="opacitymedium">—</span>'; ?></td>
	<td class="workshop-jobs-col-subcontracting">
		<?php
		if (!empty($jobLinkedOrdersCache[$job->id])) {
			foreach ($jobLinkedOrdersCache[$job->id] as $cfOrd) {
				echo $cfOrd->getNomUrl(1).' '.$cfOrd->getLibStatut(5).'<br>';
			}
		} else {
			echo '<span class="opacitymedium">—</span>';
		}
		?>
	</td>
	<td class="right workshop-jobs-col-actions nowraponall">
		<?php
		// Pastille ronde de la couleur du statut du job (survol = libellé)
		$jobStatusObj = (!empty($job->status) && isset($jobStatusCache[$job->status])) ? $jobStatusCache[$job->status] : null;
		if ($jobStatusObj) {
			echo '<span class="workshop-job-status-dot" style="background-color:'
				.dol_escape_htmltag($jobStatusObj->color ?: '#3c8dbc').'" title="'
				.dol_escape_htmltag($jobStatusObj->label).'"></span>';
		}
		?>
		<?php if ($canEditAtStatus) {
			$editUrl = $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=edit_job&jobid='.(int) $job->id;
			$delUrl  = $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=delete_job&jobid='.(int) $job->id.'&token='.newToken();
			?>
			<?php $scUrl = $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=subcontracting_job&jobid='.(int) $job->id.'&token='.newToken(); ?>
			<a class="reposition marginrightonly" href="<?php echo dol_escape_htmltag($scUrl); ?>"
				title="<?php echo dol_escape_htmltag($langs->trans('SubcontractingJob')); ?>">
				<?php echo img_picto($langs->trans('SubcontractingJob'), 'fa-handshake'); ?>
			</a>
			<a class="reposition marginrightonly" href="<?php echo dol_escape_htmltag($editUrl); ?>">
				<?php echo img_picto($langs->trans('Modify'), 'edit'); ?>
			</a>
			<a class="reposition" href="<?php echo dol_escape_htmltag($delUrl); ?>"
				onclick="return confirm('<?php echo dol_escape_js($langs->trans('ConfirmDeleteJob')); ?>');">
				<?php echo img_picto($langs->trans('Delete'), 'delete'); ?>
			</a>
		<?php } ?>
	</td>
</tr>

<?php
// ── Lignes de détail produits/services ───────────────────────────────────────
$detObj  = new Operationorderdet($db);
$detList = $detObj->fetchAllByJob($job->id);
if (is_array($detList) && !empty($detList)) {
	foreach ($detList as $det) {
		include dol_buildpath('/workshop/tpl/or_card_det_row.tpl.php', 0);
	}
}
?>

<?php if ($canEditAtStatus) {
	$addDetUrl = $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=add_det&jobid='.(int) $job->id.'&token='.newToken();
?>
<!-- Bouton ajout d'une ligne -->
<tr class="<?php echo $trClass; ?> workshop-det-add-row">
	<td colspan="8" style="padding-left:1.5em;padding-top:2px;padding-bottom:2px">
		<a href="<?php echo dol_escape_htmltag($addDetUrl); ?>" class="small">
			<?php echo img_picto($langs->trans('AddDetLine'), 'add', 'class="valignmiddle paddingright"'); ?>
			<?php echo dol_escape_htmltag($langs->trans('AddDetLine')); ?>
		</a>
	</td>
</tr>
<?php } ?>

<!-- Ligne totaux du job -->
<?php
$totItems = array(
	array('fa-wrench',          $langs->trans('TotalHTMO'),       (float) $job->total_ht_mo),
	array('fa-cogs',            $langs->trans('TotalHTPart'),     (float) $job->total_ht_part),
	array('fa-tag',             $langs->trans('TotalHTService'),  (float) $job->total_ht_service),
	array('fa-handshake',       $langs->trans('TotalHTExternal'), (float) $job->total_ht_external),
	array('fa-undo',            $langs->trans('TotalHTRefund'),   (float) $job->total_ht_refund),
	array('fa-calculator bold', $langs->trans('TotalHT'),         (float) $job->total_ht),
);
?>
<tr class="<?php echo $trClass; ?> workshop-job-totals-row">
	<td colspan="8" class="workshop-job-totals-bar">
		<div class="workshop-job-totals-inner">
			<?php foreach ($totItems as $idx => $item) {
				[$faClass, $lbl, $val] = $item;
				$isGrand = ($idx === 5);
				?>
			<span class="workshop-job-total-item<?php echo $isGrand ? ' workshop-job-total-item--grand' : ''; ?>"
				title="<?php echo dol_escape_htmltag($lbl); ?>">
				<i class="fa <?php echo dol_escape_htmltag($faClass); ?> valignmiddle" aria-hidden="true"></i>
				<?php echo $isGrand ? '<strong>'.price($val).'</strong>' : price($val); ?>
			</span>
			<?php } ?>
		</div>
	</td>
</tr>
