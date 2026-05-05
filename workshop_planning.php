<?php
/* Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    workshop/workshop_planning.php
 * \ingroup workshop
 * \brief   Workshop planning view - three display modes:
 *            - atelier:   JSGantt Gantt chart showing planned repair orders
 *                         on a weekly timeline (Mon–Sun).
 *            - pointages: FullCalendar timeGrid showing user time entries,
 *                         filterable by user.
 *            - journee:   Custom day table with one column per user showing
 *                         their time entries for a specific day.
 *
 * Business hours (slot min/max times) are computed as the union of the
 * workshop-level planning and all configured group plannings:
 * min(all heuredam) → slot start, max(all heurefpm) → slot end.
 */

// ---------------------------------------------------------------------------
// Dolibarr environment bootstrap
// ---------------------------------------------------------------------------
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
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
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------
require_once DOL_DOCUMENT_ROOT . '/user/class/usergroup.class.php';
require_once 'lib/workshop.lib.php';
dol_include_once('/workshop/class/workshopplanning.class.php');

$langs->loadLangs(array('workshop@workshop', 'users'));

// ---------------------------------------------------------------------------
// Permissions
// ---------------------------------------------------------------------------
$canSeeAtelier   = $user->hasRight('workshop', 'workshopplanning', 'read');
$canSeePointages = $user->hasRight('workshop', 'workshopmecanicsplanning', 'read');

if (!$canSeeAtelier && !$canSeePointages) {
	accessforbidden();
}

$hookmanager->initHooks(array('workshopplanningview', 'globalcard'));

// ---------------------------------------------------------------------------
// Parameters
// ---------------------------------------------------------------------------
$mode           = GETPOST('mode', 'aZ09');
$date_str       = GETPOST('date', 'alpha');
$fk_user_filter = GETPOSTINT('fk_user');

// Validate mode: only expose modes the user has rights for
$valid_modes = array();
if ($canSeeAtelier) {
	$valid_modes[] = 'atelier';
}
if ($canSeePointages) {
	$valid_modes[] = 'pointages';
	$valid_modes[] = 'journee';
}
if (!in_array($mode, $valid_modes)) {
	$mode = $valid_modes[0];
}

// Validate date: must be YYYY-MM-DD, default to today
if (empty($date_str) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
	$date_str = date('Y-m-d');
}

$baseUrl = dol_buildpath('/workshop/workshop_planning.php', 1);

// ---------------------------------------------------------------------------
// Action handler – schedule an OR (move to "planned" status with new dates)
// POST: action=plan_or, or_id, date_start_in, date_end_in
// ---------------------------------------------------------------------------
$action = GETPOST('action', 'aZ09');
if ($action === 'plan_or' && $user->hasRight('workshop', 'workshopplanning', 'write')) {
	$or_id         = GETPOSTINT('or_id');
	$date_start_in = GETPOST('date_start_in', 'alpha');
	$date_end_in   = GETPOST('date_end_in', 'alpha');
	$new_status    = getDolGlobalInt('WORKSHOP_OR_STATUS_ON_PLANNED');

	if ($or_id <= 0 || empty($date_start_in) || empty($date_end_in)) {
		setEventMessages($langs->trans('ErrorBadValueForParameter', 'or_id/date_start/date_end'), null, 'errors');
	} elseif ($new_status <= 0) {
		setEventMessages($langs->trans('WorkshopErrorPlannedStatusNotConfigured'), null, 'errors');
	} else {
		$ts_start = strtotime($date_start_in);
		$ts_end   = strtotime($date_end_in);
		if ($ts_start === false || $ts_end === false || $ts_end < $ts_start) {
			setEventMessages($langs->trans('WorkshopErrorInvalidDates'), null, 'errors');
		} else {
			dol_include_once('/workshop/class/operationorder.class.php');
			$or = new Operationorder($db);
			if ($or->fetch($or_id) > 0) {
				// Date-only storage: snap both to 00:00:00 of the chosen day
				$or->date_start = mktime(0, 0, 0, (int) date('n', $ts_start), (int) date('j', $ts_start), (int) date('Y', $ts_start));
				$or->date_end   = mktime(0, 0, 0, (int) date('n', $ts_end),   (int) date('j', $ts_end),   (int) date('Y', $ts_end));
				$db->begin();
				$res_upd = $or->update($user);
				if ($res_upd > 0) {
					$res_sts = $or->setStatus($user, $new_status);
					if ($res_sts > 0) {
						$db->commit();
						setEventMessages($langs->trans('WorkshopORScheduled', $or->ref), null);
						header('Location: ' . $baseUrl . '?mode=atelier&date=' . urlencode($date_str));
						exit;
					} else {
						$db->rollback();
						setEventMessages($or->error, $or->errors, 'errors');
					}
				} else {
					$db->rollback();
					setEventMessages($or->error, $or->errors, 'errors');
				}
			} else {
				setEventMessages($langs->trans('NotFound'), null, 'errors');
			}
		}
	}
}

// ---------------------------------------------------------------------------
// Load planning groups (configured in WORKSHOP_OR_PLANNING_GROUPS)
// ---------------------------------------------------------------------------
$planning_groups    = array();
$planning_group_ids = array_filter(explode(',', getDolGlobalString('WORKSHOP_OR_PLANNING_GROUPS')));
foreach ($planning_group_ids as $gid) {
	$grp = new Usergroup($db);
	if ($grp->fetch((int) $gid) > 0) {
		$planning_groups[(int) $gid] = $grp;
	}
}

// ---------------------------------------------------------------------------
// Load active users for all planning groups
// ---------------------------------------------------------------------------
$all_users      = array(); // uid => stdClass (firstname, lastname, login, fk_usergroup)
$users_by_group = array(); // gid => array(uid => stdClass)

if (!empty($planning_group_ids)) {
	$gid_list = implode(',', array_map('intval', $planning_group_ids));
	$sqlU  = 'SELECT u.rowid, u.firstname, u.lastname, u.login, ugu.fk_usergroup';
	$sqlU .= ' FROM ' . MAIN_DB_PREFIX . 'user u';
	$sqlU .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'usergroup_user ugu ON ugu.fk_user = u.rowid';
	$sqlU .= ' WHERE ugu.fk_usergroup IN (' . $gid_list . ')';
	$sqlU .= ' AND ugu.entity IN (0, ' . (int) $conf->entity . ')';
	$sqlU .= ' AND u.statut = 1';
	$sqlU .= ' ORDER BY u.lastname ASC, u.firstname ASC';
	$resU = $db->query($sqlU);
	if ($resU) {
		while ($obj = $db->fetch_object($resU)) {
			$uid = (int) $obj->rowid;
			$gid = (int) $obj->fk_usergroup;
			if (!isset($all_users[$uid])) {
				$all_users[$uid] = $obj;
			}
			$users_by_group[$gid][$uid] = $obj;
		}
	}
}

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Build FullCalendar 5 businessHours from the workshop-level planning and
 * all configured group plannings.
 *
 * For each day of the week:
 *   startTime = min(all non-null heuredam values)
 *   endTime   = max(all non-null heurefpm values)
 *
 * Times are stored as 'HH:MM'; FullCalendar 5 expects 'HH:MM:SS'.
 *
 * @param  DoliDB $db        Database handle
 * @param  int[]  $group_ids IDs of the planning groups to include
 * @return array             FullCalendar businessHours entries
 */
function workshopComputeBusinessHours($db, $group_ids, $user_ids = array())
{
	global $conf;

	// French day key => FullCalendar day-of-week (0=Sun, 1=Mon, ..., 6=Sat)
	$days = array(
		'lundi'    => 1,
		'mardi'    => 2,
		'mercredi' => 3,
		'jeudi'    => 4,
		'vendredi' => 5,
		'samedi'   => 6,
		'dimanche' => 0,
	);

	// Always include the workshop-level record (fk_object=0, object_type='workshop')
	$conditions = array("(fk_object = 0 AND object_type = 'workshop')");
	foreach ($group_ids as $gid) {
		$conditions[] = "(fk_object = " . (int) $gid . " AND object_type = 'usergroup')";
	}
	// Include individual user plannings so that mechanics with earlier/later
	// schedules than the global workshop extend the slot range accordingly.
	foreach ($user_ids as $uid) {
		$conditions[] = "(fk_object = " . (int) $uid . " AND object_type = 'user')";
	}

	$sql  = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'workshop_planning';
	$sql .= ' WHERE entity = ' . (int) $conf->entity;
	$sql .= ' AND (' . implode(' OR ', $conditions) . ')';

	$res       = $db->query($sql);
	$plannings = array();
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$plannings[] = $obj;
		}
	}

	$business_hours = array();
	foreach ($days as $dayKey => $fcDay) {
		$starts = array();
		$ends   = array();
		foreach ($plannings as $p) {
			$dam = $p->{$dayKey . '_heuredam'};
			$fpm = $p->{$dayKey . '_heurefpm'};
			if (!empty($dam)) {
				$starts[] = $dam;
			}
			if (!empty($fpm)) {
				$ends[] = $fpm;
			}
		}
		if (empty($starts) || empty($ends)) {
			continue;
		}
		sort($starts);
		rsort($ends);
		$business_hours[] = array(
			'daysOfWeek' => array($fcDay),
			'startTime'  => $starts[0] . ':00', // FC5 requires HH:MM:SS
			'endTime'    => $ends[0]   . ':00',
		);
	}

	return $business_hours;
}

/**
 * Compute the overall calendar slot range from the businessHours array.
 * Returns the earliest startTime and latest endTime across all days.
 * Falls back to 07:00:00–19:00:00 when businessHours is empty.
 *
 * @param  array $business_hours FullCalendar businessHours array
 * @return array                 array('start'=>'HH:MM:SS', 'end'=>'HH:MM:SS')
 */
function workshopGetDayRange($business_hours)
{
	if (empty($business_hours)) {
		return array('start' => '07:00:00', 'end' => '19:00:00');
	}
	$starts = array_column($business_hours, 'startTime');
	$ends   = array_column($business_hours, 'endTime');
	sort($starts);
	rsort($ends);

	// Apply ±1 hour buffer so that overtime slots before/after normal hours
	// are visible on the calendar.
	$base     = '2000-01-01 ';
	$start_ts = strtotime($base . substr($starts[0], 0, 5)) - 3600;
	$end_ts   = strtotime($base . substr($ends[0],   0, 5)) + 3600;
	$midnight = strtotime($base . '00:00:00');
	$eod      = $midnight + 86400; // equivalent of 24:00

	$start_ts = max($start_ts, $midnight);
	$end_ts   = min($end_ts, $eod);

	$start_str = date('H:i:s', $start_ts);
	$end_str   = ($end_ts >= $eod) ? '24:00:00' : date('H:i:s', $end_ts);

	return array('start' => $start_str, 'end' => $end_str);
}

/**
 * Generate a list of time slots between $start and $end with $interval minutes
 * spacing.  Used to build the rows of the "Mode Journée" table.
 *
 * @param  string $start            Start time in 'HH:MM' or 'HH:MM:SS'
 * @param  string $end              End time in 'HH:MM' or 'HH:MM:SS'
 * @param  int    $interval_minutes Slot duration in minutes (default 30)
 * @return string[]                 Array of 'HH:MM' strings
 */
function workshopGetTimeSlots($start, $end, $interval_minutes = 30)
{
	$slots      = array();
	$base       = '2000-01-01 ';
	$current_ts = strtotime($base . substr($start, 0, 5));
	$end_ts     = strtotime($base . substr($end, 0, 5));
	while ($current_ts < $end_ts) {
		$slots[]    = date('H:i', $current_ts);
		$current_ts += $interval_minutes * 60;
	}
	return $slots;
}

// ---------------------------------------------------------------------------
// Compute business hours and calendar slot range
// ---------------------------------------------------------------------------
$business_hours = workshopComputeBusinessHours($db, array_keys($planning_groups), array_keys($all_users));
$day_range      = workshopGetDayRange($business_hours);
$slot_min       = $day_range['start'];
$slot_max       = $day_range['end'];

// Days of the week (FC convention: 0=Sun…6=Sat) that have no planning entry
// are hidden from the calendar view.
$days_with_planning = array();
foreach ($business_hours as $bh) {
	foreach ($bh['daysOfWeek'] as $dow) {
		$days_with_planning[$dow] = true;
	}
}
$hidden_days = array_values(array_diff(array(0, 1, 2, 3, 4, 5, 6), array_keys($days_with_planning)));

// ---------------------------------------------------------------------------
// Week / day navigation
// ---------------------------------------------------------------------------
$date_ts    = strtotime($date_str);
$dow        = (int) date('N', $date_ts); // 1=Mon … 7=Sun (ISO-8601)
$week_start = date('Y-m-d', $date_ts - ($dow - 1) * 86400);

// Prev / Next targets differ per mode
if ($mode === 'journee') {
	$prev_date = date('Y-m-d', $date_ts - 86400);
	$next_date = date('Y-m-d', $date_ts + 86400);
} elseif ($mode === 'atelier') {
	// Atelier: navigate week by week (4-week view slides by 1 week)
	$prev_date = date('Y-m-d', strtotime($week_start) - 7 * 86400);
	$next_date = date('Y-m-d', strtotime($week_start) + 7 * 86400);
} else {
	$prev_date = date('Y-m-d', strtotime($week_start) - 7 * 86400);
	$next_date = date('Y-m-d', strtotime($week_start) + 7 * 86400);
}

// Period end dates for display labels
$week_end = date('Y-m-d', strtotime($week_start) + 6 * 86400);
// Atelier mode: request 2 weeks (S, S+1) — JSGantt adds ~1 week padding
// on each side, giving a natural 4-week display (S-1, S, S+1, S+2)
$period_end = date('Y-m-d', strtotime($week_start) + 13 * 86400);

// Time slots only needed for the day view
$time_slots = array();
if ($mode === 'journee') {
	$time_slots = workshopGetTimeSlots($slot_min, $slot_max, 30);
}

/*
 * View
 */
$title = $langs->trans('WorkshopPlanningView');

$TIncludeCSS = array();
$TIncludeJS  = array();

if ($mode === 'atelier') {
	// JSGantt – included in Dolibarr core
	$TIncludeCSS[] = '/includes/jsgantt/jsgantt.css';
	$TIncludeJS[]  = '/includes/jsgantt/jsgantt.js';
	$TIncludeJS[]  = '/projet/jsgantt_language.js.php?lang=' . $langs->defaultlang;
} else {
	// FullCalendar 5 for pointages/journee modes
	$TIncludeCSS[] = 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css';
	$TIncludeJS[]  = 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js';
	$TIncludeJS[]  = 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js';
}

llxHeader('', $title, '', '', 0, 0, $TIncludeJS, $TIncludeCSS, '', 'mod-workshop page-planning');

print load_fiche_titre($title, '', 'fa-calendar-alt');

// ---------------------------------------------------------------------------
// Mode tabs  (Atelier | Pointages | Journée)
// ---------------------------------------------------------------------------
$head = array();
$h    = 0;

if ($canSeeAtelier) {
	$head[$h][0] = $baseUrl . '?mode=atelier&date=' . urlencode($date_str);
	$head[$h][1] = $langs->trans('WorkshopPlanningModeAtelier');
	$head[$h][2] = 'atelier';
	$h++;
}
if ($canSeePointages) {
	$head[$h][0] = $baseUrl . '?mode=pointages&date=' . urlencode($date_str);
	$head[$h][1] = $langs->trans('WorkshopPlanningModePointages');
	$head[$h][2] = 'pointages';
	$h++;

	$head[$h][0] = $baseUrl . '?mode=journee&date=' . urlencode($date_str);
	$head[$h][1] = $langs->trans('WorkshopPlanningModeJournee');
	$head[$h][2] = 'journee';
	$h++;
}

print dol_get_fiche_head($head, $mode, '', -1, 'fa-calendar-alt');

// Warning when no planning groups are configured
if (empty($planning_groups)) {
	print info_admin($langs->trans('WorkshopNoGroupDefined'), 0, 0, 'warning');
}

// ---------------------------------------------------------------------------
// Navigation bar
// ---------------------------------------------------------------------------
// Base URL fragment shared by Prev and Next links
$nav_base = $baseUrl . '?mode=' . urlencode($mode);
if ($fk_user_filter > 0 && $mode === 'pointages') {
	$nav_base .= '&fk_user=' . (int) $fk_user_filter;
}

print '<div class="workshop-planning-nav" style="display:flex;align-items:center;gap:8px;margin:12px 0 8px 0;flex-wrap:wrap;">';

// Prev button
print '<a class="butAction" style="padding:4px 12px;min-width:auto;" href="' . dol_escape_htmltag($nav_base . '&date=' . $prev_date) . '">';
print img_picto($langs->trans('Previous'), 'fa-chevron-left');
print '</a>';

// Period label (different format per mode)
if ($mode === 'atelier') {
	// "Semaine du xx/xx/xxxx au xx/xx/xxxx" (4-week period)
	$period_start_label = dol_print_date(strtotime($week_start), 'day');
	$period_end_label   = dol_print_date(strtotime($period_end), 'day');
	print '<span style="font-weight:bold;margin:0 4px;">';
	print $langs->trans('WorkshopPlanningWeekFromTo', $period_start_label, $period_end_label);
	print '</span>';
} elseif ($mode === 'journee') {
	print '<span style="font-weight:bold;margin:0 4px;">';
	print $langs->trans('WorkshopPlanningDayOf') . ' ' . dol_print_date($date_ts, 'day');
	print '</span>';
} else {
	print '<span style="font-weight:bold;margin:0 4px;">';
	print $langs->trans('WorkshopPlanningWeekOf') . ' ' . dol_print_date(strtotime($week_start), 'day');
	print '</span>';
}

// Next button
print '<a class="butAction" style="padding:4px 12px;min-width:auto;" href="' . dol_escape_htmltag($nav_base . '&date=' . $next_date) . '">';
print img_picto($langs->trans('Next'), 'fa-chevron-right');
print '</a>';

// "Now" button (all modes)
$today_str = date('Y-m-d');
print '<a class="butAction" style="padding:4px 12px;min-width:auto;margin-left:4px;" href="' . dol_escape_htmltag($nav_base . '&date=' . $today_str) . '">';
print $langs->trans('WorkshopPlanningNow');
print '</a>';

// Date picker (submits form on change)
print '<form method="GET" action="' . dol_escape_htmltag($baseUrl) . '" style="margin:0;margin-left:8px;">';
print '<input type="hidden" name="mode" value="' . dol_escape_htmltag($mode) . '">';
if ($fk_user_filter > 0 && $mode === 'pointages') {
	print '<input type="hidden" name="fk_user" value="' . (int) $fk_user_filter . '">';
}
print '<input type="date" name="date" value="' . dol_escape_htmltag($date_str) . '" class="flat" style="padding:3px 6px;" onchange="this.form.submit();">';
print '</form>';

// "Planifier" button (atelier mode + write right)
if ($mode === 'atelier' && $user->hasRight('workshop', 'workshopplanning', 'write')) {
	print '<a class="butAction" href="javascript:void(0);" onclick="wsOpenPlanModal();" style="padding:4px 12px;min-width:auto;margin-left:16px;">';
	print img_picto('', 'fa-calendar-plus') . ' ' . $langs->trans('WorkshopPlanORAction');
	print '</a>';
}

// User filter (Mode Pointages only)
if ($mode === 'pointages' && !empty($all_users)) {
	$js_redirect_base = dol_escape_js($baseUrl . '?mode=pointages&date=' . $date_str . '&fk_user=');
	print '<span style="margin-left:16px;display:flex;align-items:center;gap:6px;">';
	print '<label for="ws_user_filter" style="margin:0;">' . $langs->trans('WorkshopPlanningFilterUser') . '</label>';
	print '<select id="ws_user_filter" class="flat" onchange="window.location.href=\'' . $js_redirect_base . '\'+this.value;">';
	print '<option value="0">' . $langs->trans('All') . '</option>';
	foreach ($all_users as $uid => $usr) {
		$sel = ($uid === $fk_user_filter) ? ' selected' : '';
		print '<option value="' . $uid . '"' . $sel . '>' . dol_escape_htmltag(dolGetFirstLastname($usr->firstname, $usr->lastname)) . '</option>';
	}
	print '</select>';
	print '</span>';
}

print '</div>'; // .workshop-planning-nav

// ---------------------------------------------------------------------------
// Main content – switches on $mode
// ---------------------------------------------------------------------------

if ($mode === 'journee') {
	// -----------------------------------------------------------------------
	// Mode Journée – custom table: one column per user, one row per time slot
	// -----------------------------------------------------------------------
	if (empty($all_users)) {
		print '<p class="opacitymedium">' . $langs->trans('WorkshopPlanningNoUsers') . '</p>';
	} else {
		print '<div class="div-table-responsive" style="overflow-x:auto;margin-top:4px;">';
		print '<table class="noborder workshop-day-table" style="border-collapse:collapse;min-width:100%;">';

		// Header row: "Heure" + one column per user
		print '<tr class="liste_titre">';
		print '<th style="width:55px;min-width:55px;text-align:center;">' . $langs->trans('Hour') . '</th>';
		foreach ($all_users as $uid => $usr) {
			print '<th class="center" style="min-width:130px;">';
			print dol_escape_htmltag(dolGetFirstLastname($usr->firstname, $usr->lastname));
			print '</th>';
		}
		print '</tr>';

		// One row per 30-minute time slot
		$slot_idx = 0;
		foreach ($time_slots as $slot) {
			$row_class = ($slot_idx % 2 === 0) ? 'even' : 'odd';
			print '<tr class="oddeven ' . $row_class . '" style="height:28px;">';

			// Time label cell
			print '<td class="center" style="font-size:0.82em;font-weight:bold;white-space:nowrap;background-color:var(--colorbackgrey, #f4f4f4);border-right:1px solid #ddd;">';
			print dol_escape_htmltag($slot);
			print '</td>';

			// One data cell per user (content filled by AJAX in a future iteration)
			foreach ($all_users as $uid => $usr) {
				print '<td class="center workshop-day-cell"';
				print ' data-user="' . $uid . '"';
				print ' data-slot="' . dol_escape_htmltag($slot) . '"';
				print ' data-date="' . dol_escape_htmltag($date_str) . '"';
				print ' style="border:1px solid #ebebeb;"></td>';
			}

			print '</tr>';
			$slot_idx++;
		}

		// Empty state when no slots could be computed
		if (empty($time_slots)) {
			$colspan = count($all_users) + 1;
			print '<tr><td colspan="' . $colspan . '" class="center opacitymedium" style="padding:20px;">';
			print $langs->trans('WorkshopPlanningNoTimeSlots');
			print '</td></tr>';
		}

		print '</table>';
		print '</div>';
	}

} elseif ($mode === 'atelier') {
	// -----------------------------------------------------------------------
	// Mode Atelier – JSGantt Gantt chart showing planned repair orders
	// -----------------------------------------------------------------------

	// Date format for JSGantt input (matches Dolibarr date output)
	$dateformatinput = 'yyyy-mm-dd';

	// -----------------------------------------------------------------------
	// Load OR data for the visible period (week_start ± 1 week pad on each side)
	// -----------------------------------------------------------------------
	$visible_start = date('Y-m-d', strtotime($week_start)  - 7 * 86400);
	$visible_end   = date('Y-m-d', strtotime($period_end)  + 7 * 86400);

	$sql  = 'SELECT o.rowid, o.ref, o.date_start, o.date_end, o.status,';
	$sql .= ' s.color AS status_color, s.label AS status_label,';
	$sql .= ' v.immatriculation,';
	$sql .= ' soc.nom AS soc_name';
	$sql .= ' FROM ' . MAIN_DB_PREFIX . 'workshop_operationorder o';
	$sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'workshop_operationorder_status s ON s.rowid = o.status AND s.display_on_planning = 1';
	$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'workshop_vehicule v ON v.rowid = o.fk_vehicule';
	$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe soc ON soc.rowid = o.fk_soc';
	$sql .= ' WHERE o.entity IN (' . getEntity('workshop') . ')';
	$sql .= ' AND o.date_start IS NOT NULL AND o.date_end IS NOT NULL';
	// Either date_start or date_end must fall inside the displayed interval
	$sql .= " AND ((o.date_start BETWEEN '" . $db->escape($visible_start) . " 00:00:00' AND '" . $db->escape($visible_end) . " 23:59:59')";
	$sql .= "  OR  (o.date_end   BETWEEN '" . $db->escape($visible_start) . " 00:00:00' AND '" . $db->escape($visible_end) . " 23:59:59'))";
	// Newest OR first (highest date_start at top of the Gantt)
	$sql .= ' ORDER BY o.date_start DESC, v.immatriculation ASC';

	$gantt_or_rows       = array();
	$gantt_status_colors = array(); // status_id => '#rrggbb'
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$gantt_or_rows[] = $obj;
			if (!empty($obj->status) && !empty($obj->status_color)) {
				$gantt_status_colors[(int) $obj->status] = $obj->status_color;
			}
		}
		$db->free($resql);
	} else {
		dol_syslog('workshop_planning atelier SQL error: ' . $db->lasterror(), LOG_ERR);
	}

	// Load job descriptions for all visible ORs (for tooltips)
	$gantt_or_jobs = array(); // or_id => array of job labels
	if (!empty($gantt_or_rows)) {
		$or_ids = array_map(function ($r) { return (int) $r->rowid; }, $gantt_or_rows);
		$sql_jobs  = 'SELECT fk_operationorder, label FROM ' . MAIN_DB_PREFIX . 'workshop_operationorder_jobs';
		$sql_jobs .= ' WHERE fk_operationorder IN (' . implode(',', $or_ids) . ')';
		$sql_jobs .= ' ORDER BY rang ASC, rowid ASC';
		$resql_jobs = $db->query($sql_jobs);
		if ($resql_jobs) {
			while ($jobj = $db->fetch_object($resql_jobs)) {
				$gantt_or_jobs[(int) $jobj->fk_operationorder][] = (string) $jobj->label;
			}
			$db->free($resql_jobs);
		}
	}

	// CSS: narrow task name column, ellipsis on long names + per-status bar colors
	print '<style type="text/css">' . "\n";
	print '  #GanttChartDIV .gmainleft  { width: 250px !important; min-width: 200px; max-width: 300px; }' . "\n";
	print '  #GanttChartDIV .gname      { max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }' . "\n";
	// Match JSGantt default task bar metrics so the bar is actually visible
	// (passing a custom itemClass replaces .gtaskblue / .gtaskred entirely)
	print '  #GanttChartDIV [class^="wsorstatus-"] { height: 13px; opacity: 0.9; margin-top: 1px; border: 1px solid rgba(0,0,0,0.2); cursor: pointer; }' . "\n";
	foreach ($gantt_status_colors as $stid => $col) {
		$col_safe = preg_match('/^#[0-9a-fA-F]{3,8}$/', $col) ? $col : '#3c8dbc';
		print '  #GanttChartDIV .wsorstatus-' . (int) $stid . ' { background-color: ' . $col_safe . ' !important; }' . "\n";
	}
	print '</style>' . "\n";

	print '<div style="margin-top:4px;">' . "\n";
	print '<div style="position:relative;" class="gantt" id="GanttChartDIV"></div>' . "\n";
	print '</div>' . "\n";

	print '<script type="text/javascript">' . "\n";
	print 'document.addEventListener(\'DOMContentLoaded\', function () {' . "\n";
	print '  var ganttEl = document.getElementById(\'GanttChartDIV\');' . "\n";
	print '  if (!ganttEl) { return; }' . "\n";
	print "\n";

	// Initialize JSGantt chart
	print '  var g = new JSGantt.GanttChart(ganttEl, \'day\');' . "\n";
	print "\n";

	// Date format configuration
	print '  g.setDateInputFormat(\'' . dol_escape_js($dateformatinput) . '\');' . "\n";
	print '  g.setDateTaskTableDisplayFormat(\'dd/mm/yyyy\');' . "\n";
	print '  g.setDateTaskDisplayFormat(\'dd mon yyyy\');' . "\n";
	print '  g.setDayMajorDateDisplayFormat(\'dd mon\');' . "\n";
	print "\n";

	// Display options – keep only task name column, hide all others
	print '  g.setShowRes(0);' . "\n";
	print '  g.setShowDur(0);' . "\n";
	print '  g.setShowComp(0);' . "\n";
	print '  g.setShowStartDate(0);' . "\n";
	print '  g.setShowEndDate(0);' . "\n";
	print '  g.setShowTaskInfoLink(0);' . "\n";
	print '  g.setFormatArr("day");' . "\n";
	print '  g.setCaptionType(\'Caption\');' . "\n";
	print '  g.setUseFade(0);' . "\n";
	print '  g.setUseToolTip(0);' . "\n";
	print "\n";
	// Calculate dayColWidth so 28 displayed days fill the available width
	// (JSGantt adds ~1 week padding on each side: 2 requested weeks → 4 displayed)
	print '  var availW = (jQuery(".fiche").width() || document.body.clientWidth) - 250 - 80;' . "\n";
	print '  var nbDays = 28;' . "\n";
	print '  var dayW = Math.max(Math.floor(availW / nbDays), 18);' . "\n";
	print '  g.setDayColWidth(dayW);' . "\n";
	print '  g.setUseSort(0);' . "\n";
	print "\n";
	// Visible range: 2 weeks (S, S+1) — JSGantt pads ±1 week → displays S-1..S+2
	print '  g.setMinDate(\'' . dol_escape_js($week_start) . '\');' . "\n";
	print '  g.setMaxDate(\'' . dol_escape_js($period_end) . '\');' . "\n";
	print '  g.setScrollTo(\'' . dol_escape_js($week_start) . '\');' . "\n";

	// Language – uses the jsgantt_language.js.php bridge from Dolibarr core
	print '  if (typeof vLangs !== \'undefined\') {' . "\n";
	print '    g.addLang(vLang, vLangs);' . "\n";
	print '    g.setLang(vLang);' . "\n";
	print '  }' . "\n";
	print "\n";

	// -----------------------------------------------------------------------
	// Output one TaskItem per loaded OR (or a placeholder if none found)
	// -----------------------------------------------------------------------
	if (empty($gantt_or_rows)) {
		print '  g.AddTaskItem(new JSGantt.TaskItem(' . "\n";
		print '    0,' . "\n";
		print '    \'' . dol_escape_js($langs->trans('WorkshopPlanningNoOR')) . '\',' . "\n";
		print '    \'' . dol_escape_js($week_start) . '\',' . "\n";
		print '    \'' . dol_escape_js($period_end) . '\',' . "\n";
		print '    \'ggroupblack\',' . "\n";
		print '    \'\', 0, \'\', 0, 0, 0, 1, \'\', \'\', \'\',' . "\n";
		print '    g' . "\n";
		print '  ));' . "\n";
	} else {
		$or_card_url = dol_buildpath('/workshop/operationorder/or_card.php', 1);
		$task_seq = 1;
		$tooltip_html_array = array();
		foreach ($gantt_or_rows as $or) {
			$or_id     = (int) $or->rowid;
			$or_ref    = (string) ($or->ref ?? '');
			$immat     = trim((string) ($or->immatriculation ?? ''));
			$soc       = trim((string) ($or->soc_name ?? ''));
			$start_str = date('Y-m-d', strtotime($or->date_start));
			$end_str   = date('Y-m-d', strtotime($or->date_end) + 86400);
			$css_class = 'wsorstatus-' . (int) $or->status;
			$name      = $immat !== '' ? $immat . ' - ' . $or_ref : $or_ref;
			$link      = $or_card_url . '?id=' . $or_id;

			// Tooltip HTML
			$tt  = '<b>' . dol_escape_htmltag($or_ref) . '</b><br>';
			$tt .= dol_escape_htmltag($langs->trans('Customer')) . ' : ' . dol_escape_htmltag($soc ?: '-') . '<br>';
			$tt .= dol_escape_htmltag($langs->trans('Immatriculation')) . ' : ' . dol_escape_htmltag($immat ?: '-') . '<br>';
			$tt .= dol_escape_htmltag($langs->trans('Status')) . ' : ' . dol_escape_htmltag((string) ($or->status_label ?? '')) . '<br>';
			$tt .= dol_print_date(strtotime($or->date_start), 'day') . ' &#8594; ' . dol_print_date(strtotime($or->date_end), 'day');
			if (!empty($gantt_or_jobs[$or_id])) {
				$tt .= '<hr style="margin:4px 0;border:0;border-top:1px solid #ccc;">';
				foreach ($gantt_or_jobs[$or_id] as $job_label) {
					$tt .= dol_escape_htmltag($job_label) . '<br>';
				}
			}
			$tooltip_html_array[] = $tt;

			print '  g.AddTaskItem(new JSGantt.TaskItem(' . "\n";
			print '    ' . ($task_seq++) . ',' . "\n";
			print '    \'' . dol_escape_js($name) . '\',' . "\n";
			print '    \'' . dol_escape_js($start_str) . '\',' . "\n";
			print '    \'' . dol_escape_js($end_str)   . '\',' . "\n";
			print '    \'' . dol_escape_js($css_class) . '\',' . "\n";
			print '    \'' . dol_escape_js($link) . '\',' . "\n";
			print '    0,' . "\n";
			print '    \'\',' . "\n";
			print '    0,' . "\n";
			print '    0,' . "\n";
			print '    0,' . "\n";
			print '    1,' . "\n";
			print '    \'\',' . "\n";
			print '    \'\',' . "\n";
			print '    \'\',' . "\n";
			print '    g' . "\n";
			print '  ));' . "\n";
		}
	}
	print "\n";

	// Draw the chart
	print '  g.Draw(250 + (nbDays * dayW) + 20);' . "\n";
	print "\n";

	// Custom tooltip – debug DOM then attach events
	if (!empty($tooltip_html_array)) {
		print '  var wsTT = [' . "\n";
		foreach ($tooltip_html_array as $i => $html) {
			print '    ' . ($i > 0 ? ',' : '') . '\'' . dol_escape_js($html) . '\'' . "\n";
		}
		print '  ];' . "\n";
		print '  console.log("WS_TOOLTIP: wsTT has " + wsTT.length + " entries");' . "\n";
		print '  console.log("WS_TOOLTIP: IDs in gantt:", Array.from(ganttEl.querySelectorAll("[id]")).slice(0,20).map(function(e){return e.id+"("+e.tagName+"."+e.className.substring(0,20)+")";}));' . "\n";
		print '  console.log("WS_TOOLTIP: elements with wsorstatus:", ganttEl.querySelectorAll("[class*=wsorstatus]").length);' . "\n";
		print '  console.log("WS_TOOLTIP: gtaskbar elements:", ganttEl.querySelectorAll(".gtaskbar,.gtaskbarcontainer,.gtaskcomplete").length);' . "\n";
		print "\n";
		print '  var ttEl = document.createElement("div");' . "\n";
		print '  ttEl.style.cssText = "display:none;position:fixed;z-index:99999;background:#fff;border:1px solid #999;border-radius:4px;padding:8px 10px;box-shadow:2px 2px 8px rgba(0,0,0,.25);max-width:360px;font-size:12px;line-height:1.6;";' . "\n";
		print '  document.body.appendChild(ttEl);' . "\n";
		print "\n";
		// Event delegation on entire gantt chart
		print '  ganttEl.addEventListener("mouseover", function(ev) {' . "\n";
		print '    var t = ev.target;' . "\n";
		print '    var cls = t.className || "";' . "\n";
		print '    if (cls.indexOf("wsorstatus-") === -1) {' . "\n";
		print '      t = t.closest ? t.closest("[class*=wsorstatus-]") : null;' . "\n";
		print '    }' . "\n";
		print '    if (!t) return;' . "\n";
		// Find which row this bar is in by counting previous sibling rows
		print '    var row = t.closest("tr");' . "\n";
		print '    if (!row) return;' . "\n";
		print '    var tbody = row.parentElement;' . "\n";
		print '    var rows = tbody.querySelectorAll("tr");' . "\n";
		print '    var idx = -1;' . "\n";
		print '    for (var i = 0; i < rows.length; i++) {' . "\n";
		print '      if (rows[i].querySelector("[class*=wsorstatus-]")) {' . "\n";
		print '        idx++;' . "\n";
		print '        if (rows[i] === row) break;' . "\n";
		print '      }' . "\n";
		print '    }' . "\n";
		print '    if (idx >= 0 && idx < wsTT.length) {' . "\n";
		print '      ttEl.innerHTML = wsTT[idx];' . "\n";
		print '      ttEl.style.display = "block";' . "\n";
		print '      ttEl.style.left = (ev.clientX + 14) + "px";' . "\n";
		print '      ttEl.style.top = (ev.clientY - 10) + "px";' . "\n";
		print '    }' . "\n";
		print '  });' . "\n";
		print '  ganttEl.addEventListener("mousemove", function(ev) {' . "\n";
		print '    if (ttEl.style.display === "block") {' . "\n";
		print '      ttEl.style.left = (ev.clientX + 14) + "px";' . "\n";
		print '      ttEl.style.top = (ev.clientY - 10) + "px";' . "\n";
		print '    }' . "\n";
		print '  });' . "\n";
		print '  ganttEl.addEventListener("mouseout", function(ev) {' . "\n";
		print '    var to = ev.relatedTarget;' . "\n";
		print '    if (!to || (!to.closest || !to.closest("[class*=wsorstatus-]"))) {' . "\n";
		print '      ttEl.style.display = "none";' . "\n";
		print '    }' . "\n";
		print '  });' . "\n";
	}

	print '});' . "\n";
	print '</script>' . "\n";

	// -----------------------------------------------------------------------
	// "Planifier" feature – modals to schedule OR (status_create -> status_planned)
	// -----------------------------------------------------------------------
	if ($user->hasRight('workshop', 'workshopplanning', 'write')) {
		// Load all OR currently at the "create" status, ready to be scheduled
		$or_to_plan       = array();
		$status_create_id = getDolGlobalInt('WORKSHOP_OR_STATUS_ON_CREATE');
		if ($status_create_id > 0) {
			$sql_pl  = 'SELECT o.rowid, o.ref, o.date_planned, v.immatriculation, soc.nom AS soc_name';
			$sql_pl .= ' FROM ' . MAIN_DB_PREFIX . 'workshop_operationorder o';
			$sql_pl .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'workshop_vehicule v ON v.rowid = o.fk_vehicule';
			$sql_pl .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe soc ON soc.rowid = o.fk_soc';
			$sql_pl .= ' WHERE o.entity IN (' . getEntity('workshop') . ')';
			$sql_pl .= ' AND o.status = ' . (int) $status_create_id;
			$sql_pl .= ' ORDER BY o.date_planned ASC, o.ref ASC';
			$resql_pl = $db->query($sql_pl);
			if ($resql_pl) {
				while ($obj = $db->fetch_object($resql_pl)) {
					$or_to_plan[] = $obj;
				}
				$db->free($resql_pl);
			}
		}

		$default_dt = date('Y-m-d', dol_now());
		$post_url   = $baseUrl . '?mode=atelier&date=' . urlencode($date_str);

		// Modal CSS + JS
		print '<style type="text/css">' . "\n";
		print '  .ws-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: none; align-items: center; justify-content: center; }' . "\n";
		print '  .ws-modal.is-open { display: flex; }' . "\n";
		print '  .ws-modal-content { background: #fff; border-radius: 8px; padding: 20px; min-width: 600px; max-width: 90vw; max-height: 85vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }' . "\n";
		print '  .ws-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #e0e0e0; padding-bottom: 8px; }' . "\n";
		print '  .ws-modal-header h3 { margin: 0; }' . "\n";
		print '  .ws-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; line-height: 1; padding: 0 8px; }' . "\n";
		print '  .ws-modal-table { width: 100%; border-collapse: collapse; }' . "\n";
		print '  .ws-modal-table th, .ws-modal-table td { padding: 6px 8px; border-bottom: 1px solid #eee; text-align: left; }' . "\n";
		print '  .ws-modal-table tr:hover td { background-color: #f7f7f7; }' . "\n";
		print '  .ws-modal-form-row { display: flex; align-items: center; gap: 12px; margin: 8px 0; }' . "\n";
		print '  .ws-modal-form-row label { min-width: 110px; font-weight: bold; }' . "\n";
		print '  .ws-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }' . "\n";
		print '</style>' . "\n";

		// Modal #1 – list of OR to schedule
		print '<div id="wsPlanModal" class="ws-modal" onclick="if(event.target===this)wsClosePlanModal();">' . "\n";
		print '  <div class="ws-modal-content">' . "\n";
		print '    <div class="ws-modal-header">' . "\n";
		print '      <h3>' . dol_escape_htmltag($langs->trans('WorkshopPlanORChooseTitle')) . '</h3>' . "\n";
		print '      <button type="button" class="ws-modal-close" onclick="wsClosePlanModal();">&times;</button>' . "\n";
		print '    </div>' . "\n";

		if (empty($or_to_plan)) {
			print '    <p class="opacitymedium">' . dol_escape_htmltag($langs->trans('WorkshopPlanORNoneToPlan')) . '</p>' . "\n";
		} else {
			print '    <table class="ws-modal-table">' . "\n";
			print '      <thead><tr>';
			print '<th>' . dol_escape_htmltag($langs->trans('Ref')) . '</th>';
			print '<th>' . dol_escape_htmltag($langs->trans('immatriculation')) . '</th>';
			print '<th>' . dol_escape_htmltag($langs->trans('ThirdParty')) . '</th>';
			print '<th>' . dol_escape_htmltag($langs->trans('DatePlanned')) . '</th>';
			print '<th></th>';
			print '</tr></thead>' . "\n";
			print '      <tbody>' . "\n";
			foreach ($or_to_plan as $or) {
				$dp_label = !empty($or->date_planned) ? dol_print_date(strtotime($or->date_planned), 'day') : '-';
				print '        <tr>';
				print '<td>' . dol_escape_htmltag($or->ref) . '</td>';
				print '<td>' . dol_escape_htmltag($or->immatriculation) . '</td>';
				print '<td>' . dol_escape_htmltag($or->soc_name) . '</td>';
				print '<td>' . dol_escape_htmltag($dp_label) . '</td>';
				print '<td><button type="button" class="butAction" style="padding:2px 10px;min-width:auto;"';
				print ' onclick="wsOpenDateModal(' . (int) $or->rowid . ', \'' . dol_escape_js($or->ref) . '\');">';
				print dol_escape_htmltag($langs->trans('WorkshopPlanORPick')) . '</button></td>';
				print '</tr>' . "\n";
			}
			print '      </tbody>' . "\n";
			print '    </table>' . "\n";
		}
		print '  </div>' . "\n";
		print '</div>' . "\n";

		// Modal #2 – date picker form
		print '<div id="wsDateModal" class="ws-modal" onclick="if(event.target===this)wsCloseDateModal();">' . "\n";
		print '  <div class="ws-modal-content" style="min-width:420px;">' . "\n";
		print '    <div class="ws-modal-header">' . "\n";
		print '      <h3>' . dol_escape_htmltag($langs->trans('WorkshopPlanORDatesTitle')) . ' <span id="wsOrRef" style="color:#888;font-weight:normal;"></span></h3>' . "\n";
		print '      <button type="button" class="ws-modal-close" onclick="wsCloseDateModal();">&times;</button>' . "\n";
		print '    </div>' . "\n";
		print '    <form method="POST" action="' . dol_escape_htmltag($post_url) . '">' . "\n";
		print '      <input type="hidden" name="token" value="' . newToken() . '">' . "\n";
		print '      <input type="hidden" name="action" value="plan_or">' . "\n";
		print '      <input type="hidden" name="or_id" id="wsOrId" value="">' . "\n";
		print '      <div class="ws-modal-form-row">';
		print '<label for="wsDateStartIn">' . dol_escape_htmltag($langs->trans('DateStart')) . '</label>';
		print '<input type="date" name="date_start_in" id="wsDateStartIn" value="' . dol_escape_htmltag($default_dt) . '" required>';
		print '</div>' . "\n";
		print '      <div class="ws-modal-form-row">';
		print '<label for="wsDateEndIn">' . dol_escape_htmltag($langs->trans('DateEnd')) . '</label>';
		print '<input type="date" name="date_end_in" id="wsDateEndIn" value="' . dol_escape_htmltag($default_dt) . '" required>';
		print '</div>' . "\n";
		print '      <div class="ws-modal-actions">' . "\n";
		print '        <button type="button" class="butActionDelete" onclick="wsCloseDateModal();">' . dol_escape_htmltag($langs->trans('Cancel')) . '</button>' . "\n";
		print '        <button type="submit" class="butAction">' . dol_escape_htmltag($langs->trans('Validate')) . '</button>' . "\n";
		print '      </div>' . "\n";
		print '    </form>' . "\n";
		print '  </div>' . "\n";
		print '</div>' . "\n";

		print '<script type="text/javascript">' . "\n";
		print 'function wsOpenPlanModal()  { document.getElementById("wsPlanModal").classList.add("is-open"); }' . "\n";
		print 'function wsClosePlanModal() { document.getElementById("wsPlanModal").classList.remove("is-open"); }' . "\n";
		print 'function wsOpenDateModal(orId, orRef) {' . "\n";
		print '  document.getElementById("wsOrId").value = orId;' . "\n";
		print '  document.getElementById("wsOrRef").textContent = "(" + orRef + ")";' . "\n";
		print '  wsClosePlanModal();' . "\n";
		print '  document.getElementById("wsDateModal").classList.add("is-open");' . "\n";
		print '}' . "\n";
		print 'function wsCloseDateModal() { document.getElementById("wsDateModal").classList.remove("is-open"); }' . "\n";
		print '</script>' . "\n";
	}

} else {
	// -----------------------------------------------------------------------
	// Mode Pointages – FullCalendar timeGridWeek
	// -----------------------------------------------------------------------
	$fc_locale = strtolower(substr($langs->defaultlang, 0, 2));
	if ($fc_locale === 'en') {
		$fc_locale = 'en-gb';
	}

	if (empty($planning_groups)) {
		print '<p class="opacitymedium">' . $langs->trans('WorkshopNoGroupDefined') . '</p>';
	}

	print '<div id="workshop-calendar" style="margin-top:4px;"></div>' . "\n";

	print '<script type="text/javascript">' . "\n";
	print '/* global FullCalendar */' . "\n";
	print 'document.addEventListener(\'DOMContentLoaded\', function () {' . "\n";
	print '  var calendarEl = document.getElementById(\'workshop-calendar\');' . "\n";
	print '  if (!calendarEl) { return; }' . "\n";
	print "\n";
	print '  var businessHours = ' . json_encode($business_hours, JSON_UNESCAPED_UNICODE) . ';' . "\n";
	print "\n";
	print '  var calendar = new FullCalendar.Calendar(calendarEl, {' . "\n";
	print '    locale:           \'' . dol_escape_js($fc_locale) . '\',' . "\n";
	print '    initialView:      \'timeGridWeek\',' . "\n";
	print '    initialDate:      \'' . dol_escape_js($week_start) . '\',' . "\n";
	print '    firstDay:         1,' . "\n";
	print '    hiddenDays:       ' . json_encode($hidden_days) . ',' . "\n";
	print '    businessHours:    ' . (empty($business_hours) ? 'false' : 'businessHours') . ',' . "\n";
	print '    slotMinTime:      \'' . dol_escape_js($slot_min) . '\',' . "\n";
	print '    slotMaxTime:      \'' . dol_escape_js($slot_max) . '\',' . "\n";
	print '    allDaySlot:       false,' . "\n";
	print '    headerToolbar:    false,' . "\n";
	print '    height:           \'auto\',' . "\n";
	print '    nowIndicator:     true,' . "\n";
	print '    slotDuration:     \'00:15:00\',' . "\n";
	print '    slotLabelInterval:\'01:00\',' . "\n";
	print '    events:           []' . "\n";
	print '  });' . "\n";
	print "\n";
	print '  calendar.render();' . "\n";
	print '});' . "\n";
	print '</script>' . "\n";
}

print dol_get_fiche_end();

llxFooter();
$db->close();
