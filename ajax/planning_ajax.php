<?php
/* Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
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

/**
 * \file    ajax/planning_ajax.php
 * \ingroup workshop
 * \brief   Centralized AJAX endpoint for the workshop mechanics planning.
 *          All responses are JSON. CSRF is enforced by main.inc.php for POST.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}

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
if (!$res) {
	die("Include of main fails");
}

dol_include_once('/workshop/lib/workshop_planning.lib.php');
dol_include_once('/workshop/class/workshoppointage.class.php');
dol_include_once('/workshop/class/workshopimpro.class.php');

$langs->loadLangs(array('workshop@workshop'));

/**
 * Emit a JSON response and stop.
 *
 * @param array $payload Data to encode
 * @return void
 */
function wp_json($payload)
{
	top_httphead('application/json');
	print json_encode($payload);
	exit;
}

/**
 * Convert a 'YYYY-MM-DD' + 'HH:MM' pair into a unix timestamp, or false.
 *
 * @param  string $date Date 'YYYY-MM-DD'
 * @param  string $time Time 'HH:MM'
 * @return int|false
 */
function wp_build_ts($date, $time)
{
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{1,2}:\d{2}$/', $time)) {
		return false;
	}
	return strtotime($date.' '.$time.':00');
}

/**
 * Clamp a percentage to the [0,100] range.
 *
 * @param  float $pct Value
 * @return float
 */
function wp_clamp_pct($pct)
{
	return max(0.0, min(100.0, $pct));
}

$action = GETPOST('action', 'aZ09');

// Permissions: read for data, write for mutations.
$canRead  = $user->hasRight('workshop', 'workshopmecanicsplanning', 'read');
$canWrite = $user->hasRight('workshop', 'workshopmecanicsplanning', 'write');

if (!$canRead) {
	http_response_code(403);
	wp_json(array('success' => false, 'error' => $langs->trans('NotEnoughPermissions')));
}

$entity = (int) $conf->entity;

// ---------------------------------------------------------------------------
// get_planning_day — data for the mechanics day grid
// ---------------------------------------------------------------------------
if ($action === 'get_planning_day') {
	$date = GETPOST('date', 'alpha');
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
		$date = date('Y-m-d');
	}
	$date_ts   = strtotime($date);
	$day_start = strtotime($date.' 00:00:00');
	$day_end   = strtotime($date.' 23:59:59');

	$range   = workshop_get_day_slot_range($db, $date_ts, $entity);
	$slotmin = $range['min'];
	$slotmax = $range['max'];
	$rngstart = strtotime($date.' '.$slotmin.':00');
	$rngend   = strtotime($date.' '.$slotmax.':00');
	$rngtotal = max(1, $rngend - $rngstart);

	// Improductive code → label / is_absence map.
	$impro       = new WorkshopImpro($db);
	$improlist   = $impro->fetchAll('ASC', 'code', 0);
	$improlabels = array();
	$improabsence = array();
	if (is_array($improlist)) {
		foreach ($improlist as $ic) {
			$improlabels[$ic->code]  = $ic->label;
			$improabsence[$ic->code] = (int) $ic->is_absence;
		}
	}
	$improlabels[WorkshopImpro::CODE_FIN_JOURNEE] = $langs->trans('WorkshopImproFinJournee');
	$improlabels[WorkshopImpro::CODE_ANNULATION]  = $langs->trans('WorkshopImproAnnulation');

	$mechanics = workshop_get_mechanics($db, $entity);

	$out = array();
	foreach ($mechanics as $uid => $m) {
		$row = array(
			'id'        => $uid,
			'name'      => $m->fullname,
			'planned'   => array(),
			'pointages' => array(),
		);

		// --- Planned blocks: jobs assigned to this mechanic overlapping the day ---
		$sqlp  = 'SELECT j.rowid, j.label, j.fk_operationorder, j.date_start, j.date_end, o.ref';
		$sqlp .= ' FROM '.$db->prefix().'workshop_operationorder_jobs as j';
		$sqlp .= ' INNER JOIN '.$db->prefix().'workshop_operationorder as o ON o.rowid = j.fk_operationorder';
		$sqlp .= ' WHERE j.fk_user_assign = '.((int) $uid);
		$sqlp .= ' AND o.entity IN ('.getEntity('workshop').')';
		$sqlp .= " AND j.date_start IS NOT NULL";
		$sqlp .= " AND j.date_start <= '".$db->idate($day_end)."'";
		$sqlp .= " AND (j.date_end IS NULL OR j.date_end >= '".$db->idate($day_start)."')";
		$sqlp .= ' ORDER BY j.date_start ASC';
		$resp = $db->query($sqlp);
		if ($resp) {
			while ($o = $db->fetch_object($resp)) {
				$st = $db->jdate($o->date_start);
				$en = !empty($o->date_end) ? $db->jdate($o->date_end) : ($st + 3600);
				$left  = wp_clamp_pct(($st - $rngstart) / $rngtotal * 100);
				$right = wp_clamp_pct(($en - $rngstart) / $rngtotal * 100);
				$row['planned'][] = array(
					'job_id'    => (int) $o->rowid,
					'or_id'     => (int) $o->fk_operationorder,
					'or_ref'    => $o->ref,
					'label'     => $o->ref.' · '.$o->label,
					'start'     => dol_print_date($st, '%H:%M'),
					'duration_hours' => round(max(0.25, ($en - $st) / 3600), 2),
					'left_pct'  => round($left, 2),
					'width_pct' => round(max(1.5, $right - $left), 2),
				);
			}
			$db->free($resp);
		}

		// --- Real pointages of the mechanic for the day ---
		$pt   = new WorkshopPointage($db);
		$ptls = $pt->fetchByUserAndPeriod($uid, $day_start, $day_end);
		if (is_array($ptls)) {
			foreach ($ptls as $p) {
				$st = (int) $p->date_start;
				$inprogress = empty($p->date_end);
				$en = $inprogress ? min($rngend, dol_now()) : (int) $p->date_end;
				if ($en < $st) {
					$en = $st;
				}
				$left  = wp_clamp_pct(($st - $rngstart) / $rngtotal * 100);
				$right = wp_clamp_pct(($en - $rngstart) / $rngtotal * 100);

				$classes = array('wp-block');
				$label   = '';
				if ($p->type === WorkshopPointage::TYPE_IMPRO) {
					$isabs = isset($improabsence[$p->impro_code]) ? $improabsence[$p->impro_code] : 0;
					$classes[] = $isabs ? 'wp-block--absence' : 'wp-block--impro';
					$label = isset($improlabels[$p->impro_code]) ? $improlabels[$p->impro_code] : $p->impro_code;
				} else {
					$classes[] = 'wp-block--job';
					$label = '';
					if (!empty($p->fk_job)) {
						$sqll  = 'SELECT j.label, o.ref FROM '.$db->prefix().'workshop_operationorder_jobs as j';
						$sqll .= ' LEFT JOIN '.$db->prefix().'workshop_operationorder as o ON o.rowid = j.fk_operationorder';
						$sqll .= ' WHERE j.rowid = '.((int) $p->fk_job);
						$resl = $db->query($sqll);
						if ($resl && ($jl = $db->fetch_object($resl))) {
							$label = ($jl->ref ? $jl->ref.' · ' : '').$jl->label;
						}
					}
				}
				if ($inprogress) {
					$classes[] = 'wp-block--inprogress';
				}

				$row['pointages'][] = array(
					'id'         => (int) $p->id,
					'type'       => $p->type,
					'job_id'     => (int) $p->fk_job,
					'or_id'      => (int) $p->fk_operationorder,
					'impro_code' => $p->impro_code,
					'label'      => $label,
					'start'      => dol_print_date($st, '%H:%M'),
					'end'        => $inprogress ? '' : dol_print_date((int) $p->date_end, '%H:%M'),
					'left_pct'   => round($left, 2),
					'width_pct'  => round(max(1.5, $right - $left), 2),
					'classes'    => implode(' ', $classes),
					'inprogress' => $inprogress ? 1 : 0,
				);
			}
		}

		$out[] = $row;
	}

	// --- Unassigned jobs (no mechanic) belonging to plannable ORs ---
	$unassigned = array();
	$sqlu  = 'SELECT j.rowid, j.label, j.fk_operationorder, o.ref';
	$sqlu .= ' FROM '.$db->prefix().'workshop_operationorder_jobs as j';
	$sqlu .= ' INNER JOIN '.$db->prefix().'workshop_operationorder as o ON o.rowid = j.fk_operationorder';
	$sqlu .= ' INNER JOIN '.$db->prefix().'workshop_operationorder_status as s ON s.rowid = o.status AND s.display_on_planning = 1';
	$sqlu .= ' WHERE (j.fk_user_assign IS NULL OR j.fk_user_assign = 0)';
	$sqlu .= ' AND o.entity IN ('.getEntity('workshop').')';
	$sqlu .= ' AND s.rowid = '.getDolGlobalInt('WORKSHOP_OR_STATUS_ON_PLANNED');
	$sqlu .= ' ORDER BY o.ref ASC, j.rang ASC';
	$sqlu .= $db->plimit(100);
	$resu = $db->query($sqlu);
	if ($resu) {
		while ($o = $db->fetch_object($resu)) {
			$unassigned[] = array(
				'job_id' => (int) $o->rowid,
				'or_id'  => (int) $o->fk_operationorder,
				'or_ref' => $o->ref,
				'label'  => $o->ref.' · '.$o->label,
			);
		}
		$db->free($resu);
	}

	wp_json(array(
		'success'    => true,
		'date'       => $date,
		'slot_min'   => $slotmin,
		'slot_max'   => $slotmax,
		'mechanics'  => $out,
		'unassigned' => $unassigned,
	));
}

// ---------------------------------------------------------------------------
// Mutations below require write permission.
// ---------------------------------------------------------------------------
if (in_array($action, array('create_pointage', 'update_pointage', 'delete_pointage', 'assign_job'), true)) {
	if (!$canWrite) {
		http_response_code(403);
		wp_json(array('success' => false, 'error' => $langs->trans('NotEnoughPermissions')));
	}
}

// ---------------------------------------------------------------------------
// create_pointage
// ---------------------------------------------------------------------------
if ($action === 'create_pointage') {
	$fk_user = GETPOSTINT('fk_user');
	$date    = GETPOST('date', 'alpha');
	$type    = GETPOST('p_type', 'aZ09');
	$start   = GETPOST('start', 'alpha');
	$end     = GETPOST('end', 'alpha');
	$note    = GETPOST('note', 'alphanohtml');

	if ($fk_user <= 0) {
		wp_json(array('success' => false, 'error' => $langs->trans('WorkshopMechanic')));
	}
	$ts_start = wp_build_ts($date, $start);
	if ($ts_start === false) {
		wp_json(array('success' => false, 'error' => $langs->trans('WorkshopErrorInvalidDates')));
	}

	$pt          = new WorkshopPointage($db);
	$pt->fk_user = $fk_user;
	$pt->note    = $note;

	if ($type === WorkshopPointage::TYPE_IMPRO) {
		$code = GETPOST('impro_code', 'alphanohtml');

		// Reserved codes: special handling, no row of type 'impro' created here.
		if ($code === WorkshopImpro::CODE_FIN_JOURNEE) {
			$r = $pt->endDay($user, $ts_start);
			if ($r < 0) {
				wp_json(array('success' => false, 'error' => $pt->error));
			}
			wp_json(array('success' => true, 'reserved' => 'fin_journee'));
		}
		if ($code === WorkshopImpro::CODE_ANNULATION) {
			$delay = getDolGlobalInt('WORKSHOP_IMPRO_CANCEL_DELAY', 15);
			$r = $pt->cancelLast($user, $delay);
			if ($r < 0) {
				wp_json(array('success' => false, 'error' => $pt->error));
			}
			wp_json(array('success' => true, 'reserved' => 'annulation', 'cancelled' => ($r > 0) ? 1 : 0));
		}

		$pt->type       = WorkshopPointage::TYPE_IMPRO;
		$pt->impro_code = $code;
	} else {
		$fk_job = GETPOSTINT('fk_job');
		$pt->type   = WorkshopPointage::TYPE_JOB;
		$pt->fk_job = $fk_job ?: null;
		if ($fk_job > 0) {
			$sqlj  = 'SELECT j.fk_operationorder FROM '.$db->prefix().'workshop_operationorder_jobs as j';
			$sqlj .= ' INNER JOIN '.$db->prefix().'workshop_operationorder as o ON o.rowid = j.fk_operationorder';
			$sqlj .= ' WHERE j.rowid = '.((int) $fk_job);
			$sqlj .= ' AND o.entity IN ('.getEntity('workshop').')';
			$resj = $db->query($sqlj);
			if ($resj && ($oj = $db->fetch_object($resj))) {
				$pt->fk_operationorder = (int) $oj->fk_operationorder;
			} else {
				wp_json(array('success' => false, 'error' => $langs->trans('ErrorRecordNotFound')));
			}
		}
	}

	$ts_end = ($end !== '') ? wp_build_ts($date, $end) : false;

	if ($ts_end !== false) {
		// Closed historical block → plain create, no auto-close of the open one.
		if ($ts_end < $ts_start) {
			wp_json(array('success' => false, 'error' => $langs->trans('WorkshopErrorInvalidDates')));
		}
		$pt->date_start = $ts_start;
		$pt->date_end   = $ts_end;
		$db->begin();
		if ($pt->create($user) < 0) {
			$db->rollback();
			wp_json(array('success' => false, 'error' => $pt->error));
		}
		if (!empty($pt->fk_job)) {
			$pt->recomputeJobTimeSpent((int) $pt->fk_job);
		}
		$db->commit();
	} else {
		// Open block → applies rule 1 (auto-close previous open).
		$newid = $pt->addPointage($user, $ts_start);
		if ($newid < 0) {
			wp_json(array('success' => false, 'error' => $pt->error));
		}
	}

	wp_json(array('success' => true, 'id' => (int) $pt->id));
}

// ---------------------------------------------------------------------------
// update_pointage
// ---------------------------------------------------------------------------
if ($action === 'update_pointage') {
	$id = GETPOSTINT('pointage_id');
	$pt = new WorkshopPointage($db);
	if ($id <= 0 || $pt->fetch($id) <= 0) {
		wp_json(array('success' => false, 'error' => $langs->trans('NotFound')));
	}

	$date  = GETPOST('date', 'alpha');
	$start = GETPOST('start', 'alpha');
	$end   = GETPOST('end', 'alpha');
	$type  = GETPOST('p_type', 'aZ09');
	$note  = GETPOST('note', 'alphanohtml');

	$ts_start = wp_build_ts($date, $start);
	if ($ts_start === false) {
		wp_json(array('success' => false, 'error' => $langs->trans('WorkshopErrorInvalidDates')));
	}
	$ts_end = ($end !== '') ? wp_build_ts($date, $end) : false;
	if ($ts_end !== false && $ts_end < $ts_start) {
		wp_json(array('success' => false, 'error' => $langs->trans('WorkshopErrorInvalidDates')));
	}

	$oldjob = (int) $pt->fk_job;

	$pt->date_start = $ts_start;
	$pt->date_end   = ($ts_end !== false) ? $ts_end : null;
	$pt->note       = $note;

	if ($type === WorkshopPointage::TYPE_IMPRO) {
		$pt->type       = WorkshopPointage::TYPE_IMPRO;
		$pt->impro_code = GETPOST('impro_code', 'alphanohtml');
		$pt->fk_job     = null;
		$pt->fk_operationorder = null;
	} else {
		$fk_job = GETPOSTINT('fk_job');
		$pt->type       = WorkshopPointage::TYPE_JOB;
		$pt->impro_code = null;
		$pt->fk_job     = $fk_job ?: null;
		if ($fk_job > 0) {
			$sqlj  = 'SELECT j.fk_operationorder FROM '.$db->prefix().'workshop_operationorder_jobs as j';
			$sqlj .= ' INNER JOIN '.$db->prefix().'workshop_operationorder as o ON o.rowid = j.fk_operationorder';
			$sqlj .= ' WHERE j.rowid = '.((int) $fk_job);
			$sqlj .= ' AND o.entity IN ('.getEntity('workshop').')';
			$resj = $db->query($sqlj);
			if ($resj && ($oj = $db->fetch_object($resj))) {
				$pt->fk_operationorder = (int) $oj->fk_operationorder;
			} else {
				wp_json(array('success' => false, 'error' => $langs->trans('ErrorRecordNotFound')));
			}
		}
	}

	$db->begin();
	if ($pt->update($user) < 0) {
		$db->rollback();
		wp_json(array('success' => false, 'error' => $pt->error));
	}
	if (!empty($oldjob)) {
		$pt->recomputeJobTimeSpent($oldjob);
	}
	if (!empty($pt->fk_job) && (int) $pt->fk_job !== $oldjob) {
		$pt->recomputeJobTimeSpent((int) $pt->fk_job);
	}
	$db->commit();

	wp_json(array('success' => true, 'id' => $id));
}

// ---------------------------------------------------------------------------
// delete_pointage
// ---------------------------------------------------------------------------
if ($action === 'delete_pointage') {
	$id = GETPOSTINT('pointage_id');
	$pt = new WorkshopPointage($db);
	if ($id <= 0 || $pt->fetch($id) <= 0) {
		wp_json(array('success' => false, 'error' => $langs->trans('NotFound')));
	}
	$fkjob = (int) $pt->fk_job;
	$db->begin();
	if ($pt->delete($user) < 0) {
		$db->rollback();
		wp_json(array('success' => false, 'error' => $pt->error));
	}
	if (!empty($fkjob)) {
		$pt->recomputeJobTimeSpent($fkjob);
	}
	$db->commit();

	wp_json(array('success' => true));
}

// ---------------------------------------------------------------------------
// assign_job — drag & drop a job onto a mechanic (planned slot)
// Updates ONLY fk_user_assign / date_start / date_end (no billing impact).
// ---------------------------------------------------------------------------
if ($action === 'assign_job') {
	$fk_job   = GETPOSTINT('fk_job');
	$fk_user  = GETPOSTINT('fk_user');
	$date     = GETPOST('date', 'alpha');
	$start    = GETPOST('start', 'alpha');
	$duration = (float) price2num(GETPOST('duration', 'alpha')); // hours

	if ($fk_job <= 0 || $fk_user <= 0) {
		wp_json(array('success' => false, 'error' => $langs->trans('ErrorBadParameters')));
	}

	// The job's parent OR carries the status-based permission (WorkshopOperationOrderStatus).
	// 'workshopmecanicsplanning:write', checked above, only gates the planning feature itself,
	// not whether this user may edit THIS OR at its current status.
	dol_include_once('/workshop/class/operationorder.class.php');
	dol_include_once('/workshop/class/operationorder_jobs.class.php');

	$job = new Operationorder_jobs($db);
	if ($job->fetch($fk_job) <= 0 || empty($job->fk_operationorder)) {
		wp_json(array('success' => false, 'error' => $langs->trans('ErrorRecordNotFound')));
	}

	$or = new Operationorder($db);
	if ($or->fetch($job->fk_operationorder) <= 0 || (int) $or->entity !== (int) $conf->entity) {
		wp_json(array('success' => false, 'error' => $langs->trans('ErrorRecordNotFound')));
	}
	if (!$or->canWrite($user)) {
		http_response_code(403);
		wp_json(array('success' => false, 'error' => $langs->trans('NotEnoughPermissions')));
	}

	$ts_start = wp_build_ts($date, $start);
	if ($ts_start === false) {
		wp_json(array('success' => false, 'error' => $langs->trans('WorkshopErrorInvalidDates')));
	}
	if ($duration <= 0) {
		$duration = 1;
	}
	$ts_end = $ts_start + (int) round($duration * 3600);

	$sqlu  = 'UPDATE '.$db->prefix().'workshop_operationorder_jobs';
	$sqlu .= ' SET fk_user_assign = '.((int) $fk_user);
	$sqlu .= ", date_start = '".$db->idate($ts_start)."'";
	$sqlu .= ", date_end = '".$db->idate($ts_end)."'";
	$sqlu .= ' WHERE rowid = '.((int) $fk_job);

	if (!$db->query($sqlu)) {
		wp_json(array('success' => false, 'error' => $db->lasterror()));
	}
	dol_syslog('planning_ajax assign_job job='.$fk_job.' user='.$fk_user, LOG_DEBUG);

	wp_json(array('success' => true));
}

// ---------------------------------------------------------------------------
// search_or — autocomplete repair orders by ref
// ---------------------------------------------------------------------------
if ($action === 'search_or') {
	$term = GETPOST('term', 'alphanohtml');
	$list = array();
	$sql  = 'SELECT o.rowid, o.ref FROM '.$db->prefix().'workshop_operationorder as o';
	$sql .= ' WHERE o.entity IN ('.getEntity('workshop').')';
	if ($term !== '') {
		$sql .= " AND o.ref LIKE '%".$db->escape($db->escapeforlike($term))."%'";
	}
	$sql .= ' ORDER BY o.ref DESC';
	$sql .= $db->plimit(20);
	$resql = $db->query($sql);
	if ($resql) {
		while ($o = $db->fetch_object($resql)) {
			$list[] = array('id' => (int) $o->rowid, 'ref' => $o->ref);
		}
		$db->free($resql);
	}
	wp_json(array('success' => true, 'results' => $list));
}

// ---------------------------------------------------------------------------
// get_jobs_for_or — jobs belonging to a given OR
// ---------------------------------------------------------------------------
if ($action === 'get_jobs_for_or') {
	$or_id = GETPOSTINT('or_id');
	$list  = array();
	if ($or_id > 0) {
		$sql  = 'SELECT j.rowid, j.label FROM '.$db->prefix().'workshop_operationorder_jobs as j';
		$sql .= ' INNER JOIN '.$db->prefix().'workshop_operationorder as o ON o.rowid = j.fk_operationorder';
		$sql .= ' WHERE j.fk_operationorder = '.((int) $or_id);
		$sql .= ' AND o.entity IN ('.getEntity('workshop').')';
		$sql .= ' ORDER BY j.rang ASC';
		$resql = $db->query($sql);
		if ($resql) {
			while ($o = $db->fetch_object($resql)) {
				$list[] = array('id' => (int) $o->rowid, 'label' => $o->label);
			}
			$db->free($resql);
		}
	}
	wp_json(array('success' => true, 'results' => $list));
}

// ---------------------------------------------------------------------------
// get_planning_week — weekly recap grid (mechanics x days)
// ---------------------------------------------------------------------------
if ($action === 'get_planning_week') {
	$date = GETPOST('date', 'alpha');
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
		$date = date('Y-m-d');
	}
	$date_ts = strtotime($date);
	$dow     = (int) date('N', $date_ts); // 1=Mon..7=Sun
	$mon_ts  = $date_ts - ($dow - 1) * 86400;

	// Display Monday..Saturday (6 days)
	$days       = array();
	$day_labels = array();
	for ($d = 0; $d < 6; $d++) {
		$ts = $mon_ts + $d * 86400;
		$days[]       = date('Y-m-d', $ts);
		$day_labels[] = dol_print_date($ts, '%a %d');
	}
	$week_lo = date('Y-m-d', $mon_ts) . ' 00:00:00';
	$week_hi = date('Y-m-d', $mon_ts + 5 * 86400) . ' 23:59:59';

	// Improductive absence flags
	$impro        = new WorkshopImpro($db);
	$improlist    = $impro->fetchAll('ASC', 'code', 0);
	$improlabels  = array();
	$improabsence = array();
	if (is_array($improlist)) {
		foreach ($improlist as $ic) {
			$improlabels[$ic->code]  = $ic->label;
			$improabsence[$ic->code] = (int) $ic->is_absence;
		}
	}
	$improlabels[WorkshopImpro::CODE_FIN_JOURNEE] = $langs->trans('WorkshopImproFinJournee');
	$improlabels[WorkshopImpro::CODE_ANNULATION]  = $langs->trans('WorkshopImproAnnulation');

	$mechanics = workshop_get_mechanics($db, $entity);
	$nb_mec    = count($mechanics);

	// Init per-mechanic per-day buckets + charge accumulators
	$out      = array();
	$idxByUid = array();
	foreach ($mechanics as $uid => $m) {
		$idxByUid[$uid] = count($out);
		$daymap = array();
		foreach ($days as $dd) { $daymap[$dd] = array(); }
		$out[] = array('id' => $uid, 'name' => $m->fullname, 'days' => $daymap);
	}
	$charge_sec = array();
	foreach ($days as $dd) { $charge_sec[$dd] = 0; }

	// Load all pointages of the week, resolve OR ref
	$sqlw  = 'SELECT p.fk_user, p.type, p.impro_code, p.date_start, p.date_end, o.ref';
	$sqlw .= ' FROM '.$db->prefix().'workshop_pointage as p';
	$sqlw .= ' LEFT JOIN '.$db->prefix().'workshop_operationorder_jobs as j ON j.rowid = p.fk_job';
	$sqlw .= ' LEFT JOIN '.$db->prefix().'workshop_operationorder as o ON o.rowid = j.fk_operationorder';
	$sqlw .= ' WHERE p.entity = '.((int) $entity);
	$sqlw .= " AND p.date_start >= '".$db->escape($week_lo)."' AND p.date_start <= '".$db->escape($week_hi)."'";
	$sqlw .= ' ORDER BY p.fk_user, p.date_start';
	$resw = $db->query($sqlw);
	$seen = array(); // dedup uid|day|key
	if ($resw) {
		while ($p = $db->fetch_object($resw)) {
			$uid = (int) $p->fk_user;
			if (!isset($idxByUid[$uid])) { continue; }
			$dd = date('Y-m-d', $db->jdate($p->date_start));
			if (!isset($charge_sec[$dd])) { continue; }

			// Accumulate charge (closed entries only)
			if (!empty($p->date_end)) {
				$charge_sec[$dd] += max(0, $db->jdate($p->date_end) - $db->jdate($p->date_start));
			}

			if ($p->type === WorkshopPointage::TYPE_IMPRO) {
				$isabs = isset($improabsence[$p->impro_code]) ? $improabsence[$p->impro_code] : 0;
				$lbl   = isset($improlabels[$p->impro_code]) ? $improlabels[$p->impro_code] : $p->impro_code;
				$key   = ($isabs ? 'abs:' : 'imp:').$p->impro_code;
				$cls   = $isabs ? 'wp-week-tag--absent' : 'wp-week-tag--impro';
			} else {
				$lbl = $p->ref ?: $langs->trans('WorkshopJob');
				$key = 'job:'.$lbl;
				$cls = 'wp-week-tag--job';
			}
			$dedup = $uid.'|'.$dd.'|'.$key;
			if (isset($seen[$dedup])) { continue; }
			$seen[$dedup] = 1;
			$out[$idxByUid[$uid]]['days'][$dd][] = array('label' => $lbl, 'cls' => $cls);
		}
		$db->free($resw);
	}

	// Charge percentage per day
	$charges = array();
	foreach ($days as $dd) {
		$rng   = workshop_get_day_slot_range($db, strtotime($dd), $entity);
		$day_h = (strtotime('2000-01-01 '.$rng['max']) - strtotime('2000-01-01 '.$rng['min'])) / 3600;
		$cap   = $nb_mec * $day_h * 3600;
		$charges[$dd] = $cap > 0 ? min(100, (int) round($charge_sec[$dd] / $cap * 100)) : 0;
	}

	wp_json(array(
		'success'    => true,
		'week_start' => date('Y-m-d', $mon_ts),
		'days'       => $days,
		'day_labels' => $day_labels,
		'mechanics'  => $out,
		'charges'    => $charges,
		'today'      => date('Y-m-d'),
	));
}

// ---------------------------------------------------------------------------
// search_thirdparty — autocomplete companies by name
// ---------------------------------------------------------------------------
if ($action === 'search_thirdparty') {
	$term = GETPOST('term', 'alphanohtml');
	$list = array();
	$sql  = 'SELECT rowid, nom FROM '.$db->prefix().'societe';
	$sql .= ' WHERE entity IN ('.getEntity('societe').')';
	if ($term !== '') {
		$sql .= " AND nom LIKE '%".$db->escape($db->escapeforlike($term))."%'";
	}
	$sql .= ' ORDER BY nom ASC';
	$sql .= $db->plimit(20);
	$resql = $db->query($sql);
	if ($resql) {
		while ($o = $db->fetch_object($resql)) {
			$list[] = array('id' => (int) $o->rowid, 'name' => $o->nom);
		}
		$db->free($resql);
	}
	wp_json(array('success' => true, 'results' => $list));
}

// ---------------------------------------------------------------------------
// get_vehicules_for_soc — vehicles of a given third party
// ---------------------------------------------------------------------------
if ($action === 'get_vehicules_for_soc') {
	$fk_soc = GETPOSTINT('fk_soc');
	$list   = array();
	if ($fk_soc > 0) {
		$sql  = 'SELECT rowid, immatriculation FROM '.$db->prefix().'workshop_vehicule';
		$sql .= ' WHERE fk_soc = '.((int) $fk_soc);
		$sql .= ' AND entity IN ('.getEntity('workshop').')';
		$sql .= ' ORDER BY immatriculation ASC';
		$resql = $db->query($sql);
		if ($resql) {
			while ($o = $db->fetch_object($resql)) {
				$list[] = array('id' => (int) $o->rowid, 'label' => $o->immatriculation);
			}
			$db->free($resql);
		}
	}
	wp_json(array('success' => true, 'results' => $list));
}

// ---------------------------------------------------------------------------
// create_or_quick — quick repair order creation from the planning
// ---------------------------------------------------------------------------
if ($action === 'create_or_quick') {
	if (!$user->hasRight('workshop', 'operationorders', 'write')) {
		http_response_code(403);
		wp_json(array('success' => false, 'error' => $langs->trans('NotEnoughPermissions')));
	}

	dol_include_once('/workshop/class/operationorder.class.php');
	dol_include_once('/workshop/class/operationorder_jobs.class.php');
	dol_include_once('//workshop/class/vehicule.class.php');

	$fk_soc      = GETPOSTINT('fk_soc');
	$fk_vehicule = GETPOSTINT('fk_vehicule');
	$descr       = GETPOST('description', 'alphanohtml');
	$fk_user_a   = GETPOSTINT('fk_user_assign');
	$date        = GETPOST('date', 'alpha');
	$start       = GETPOST('start', 'alpha');

	// Derive third party from the vehicle when provided
	if ($fk_vehicule > 0) {
		$veh = new Vehicule($db);
		if ($veh->fetch($fk_vehicule) <= 0 || (int) $veh->entity !== (int) $conf->entity) {
			wp_json(array('success' => false, 'error' => $langs->trans('ErrorRecordNotFound')));
		}
		if (!empty($veh->fk_soc)) {
			$fk_soc = (int) $veh->fk_soc;
		}
	}
	if ($fk_soc <= 0) {
		wp_json(array('success' => false, 'error' => $langs->trans('ErrInvalidSocid')));
	}
	// The third party may have been supplied directly (no vehicle) — check it belongs to the current entity.
	$sqlsoc = 'SELECT rowid FROM '.$db->prefix().'societe';
	$sqlsoc .= ' WHERE rowid = '.((int) $fk_soc);
	$sqlsoc .= ' AND entity IN ('.getEntity('societe').')';
	$ressoc = $db->query($sqlsoc);
	if (!$ressoc || !$db->fetch_object($ressoc)) {
		wp_json(array('success' => false, 'error' => $langs->trans('ErrInvalidSocid')));
	}

	$db->begin();

	$or = new Operationorder($db);
	$or->entity        = $conf->entity;
	$or->fk_soc        = $fk_soc;
	$or->fk_vehicule   = $fk_vehicule ?: null;
	$or->fk_user_creat = $user->id;
	// Statut à la création : toujours celui paramétré dans l'administration du
	// module (onglet "statuts"), jamais une valeur codée en dur.
	$or->status = getDolGlobalInt('WORKSHOP_OR_STATUS_ON_CREATE');
	$or->total_ht          = 0;
	$or->total_ht_part     = 0;
	$or->total_ht_mo       = 0;
	$or->total_ht_service  = 0;
	$or->total_ht_external = 0;
	$or->total_ht_refund   = 0;
	$or->date_planned = null;
	$or->date_start   = null;
	$or->date_end     = null;
	$nextRef = $or->getNextNumRef();
	if (!empty($nextRef)) {
		$or->ref = $nextRef;
	}

	$or_id = $or->create($user);
	if ($or_id <= 0) {
		$db->rollback();
		wp_json(array('success' => false, 'error' => $or->error ?: $langs->trans('Error')));
	}

	// Optional first job (carries the description and the immediate planning)
	if (trim($descr) !== '' || $fk_user_a > 0) {
		$job = new Operationorder_jobs($db);
		$job->fk_operationorder = $or_id;
		$job->label             = (trim($descr) !== '') ? $descr : $langs->trans('WorkshopJob');
		$job->rang              = 1;
		if ($fk_user_a > 0) {
			$job->fk_user_assign = $fk_user_a;
			$ts = wp_build_ts($date, $start);
			if ($ts !== false) {
				$job->date_start = $ts;
				$job->date_end   = $ts + 3600;
			}
		}
		if ($job->create($user) <= 0) {
			$db->rollback();
			wp_json(array('success' => false, 'error' => $job->error ?: $langs->trans('Error')));
		}
	}

	$db->commit();

	$or_url = dol_buildpath('/workshop/operationorder/or_card.php', 1).'?id='.((int) $or_id);
	wp_json(array('success' => true, 'or_id' => (int) $or_id, 'url' => $or_url));
}

// Unknown action
http_response_code(400);
wp_json(array('success' => false, 'error' => 'Unknown action'));
