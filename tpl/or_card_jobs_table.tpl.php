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
 * \file       workshop/tpl/or_card_jobs_table.tpl.php
 * \ingroup    workshop
 * \brief      Tableau des jobs d'un OR (entête + boucle sur les lignes job)
 *
 * Variables attendues dans le scope appelant :
 *   @var Operationorder          $object
 *   @var array                   $jobsList          Liste d'objets Operationorder_jobs
 *   @var array                   $serviceTypeCache  Map fk_service_type → ServiceType
 *   @var bool                    $canEditAtStatus   L'utilisateur peut modifier à ce statut
 *   @var Translate               $langs
 *   @var DoliDB                  $db
 */

// Protection contre l'appel direct
if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}
?>
<table class="noborder allwidth workshop-jobs-table">

	<!-- En-tête — 8 colonnes -->
	<tr class="liste_titre workshop-jobs-table__header">
		<th class="wrapcolumntitle workshop-jobs-col-type"><?php echo $langs->trans('ServiceType'); ?></th>
		<th class="workshop-jobs-col-label"><?php echo $langs->trans('Label'); ?></th>
		<th class="workshop-jobs-col-desc"><?php echo $langs->trans('Description'); ?></th>
		<th class="right workshop-jobs-col-mo nowraponall" title="<?php echo dol_escape_htmltag($langs->trans('QtyMOMoRemise')); ?>">
			<i class="fa fa-wrench valignmiddle"></i>
		</th>
		<th class="right workshop-jobs-col-time" title="<?php echo dol_escape_htmltag($langs->trans('TimeSpent')).' / '.$langs->trans('QtyMO'); ?>">
			<i class="fa fa-stopwatch valignmiddle" title="<?php echo dol_escape_htmltag($langs->trans('TimeSpent')); ?>"></i>
		</th>
		<th class="workshop-jobs-col-billing"><?php echo $langs->trans('BillingThirdParty'); ?></th>
		<th class="workshop-jobs-col-subcontracting"><?php echo $langs->trans('SubcontractingJob'); ?></th>
		<th class="workshop-jobs-col-actions">&nbsp;</th>
	</tr>

<?php if (empty($jobsList)) { ?>
	<tr class="oddeven">
		<td colspan="8" class="opacitymedium center"><?php echo $langs->trans('NoJobYet'); ?></td>
	</tr>
<?php } else {
	$ii = 0;
	foreach ($jobsList as $job) {
		$trClass = ($ii % 2 == 0) ? 'oddeven' : 'oddeven';
		$ii++;
		include dol_buildpath('/workshop/tpl/or_card_job_row.tpl.php', 0);
	}
} ?>

</table>
