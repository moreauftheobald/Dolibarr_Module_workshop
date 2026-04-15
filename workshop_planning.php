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

// Prev / Next targets differ between week modes and the day mode
$prev_date = ($mode === 'journee')
	? date('Y-m-d', $date_ts - 86400)
	: date('Y-m-d', strtotime($week_start) - 7 * 86400);
$next_date = ($mode === 'journee')
	? date('Y-m-d', $date_ts + 86400)
	: date('Y-m-d', strtotime($week_start) + 7 * 86400);

// Week end date (Sunday) for display labels
$week_end = date('Y-m-d', strtotime($week_start) + 6 * 86400);

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
	// "Semaine du xx/xx/xxxx au xx/xx/xxxx"
	$week_start_label = dol_print_date(strtotime($week_start), 'day');
	$week_end_label   = dol_print_date(strtotime($week_end), 'day');
	print '<span style="font-weight:bold;margin:0 4px;">';
	print $langs->trans('WorkshopPlanningWeekFromTo', $week_start_label, $week_end_label);
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

	print '<div style="overflow-x:auto;margin-top:4px;">' . "\n";
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
	print '  g.setDayColWidth(40);' . "\n";

	// Language – uses the jsgantt_language.js.php bridge from Dolibarr core
	print '  if (typeof vLangs !== \'undefined\') {' . "\n";
	print '    g.addLang(vLang, vLangs);' . "\n";
	print '    g.setLang(vLang);' . "\n";
	print '  }' . "\n";
	print "\n";

	// -----------------------------------------------------------------------
	// Load OR data for the displayed week
	// -----------------------------------------------------------------------
	// TODO: Load real OR data via AJAX or PHP query. For now, show an empty
	// Gantt with a placeholder message when no data is available.
	//
	// Future data loading pattern:
	// - Query llx_workshop_operationorder WHERE date_planned BETWEEN week_start AND week_end
	// - For each OR: g.AddTaskItem(new JSGantt.TaskItem(id, name, start, end, ...))
	// -----------------------------------------------------------------------

	// Add a placeholder task so the Gantt renders its timeline structure
	// even when no real OR data is loaded yet
	print '  g.AddTaskItem(new JSGantt.TaskItem(' . "\n";
	print '    0,' . "\n";                                                          // ID
	print '    \'' . dol_escape_js($langs->trans('WorkshopPlanningNoOR')) . '\',' . "\n"; // Name
	print '    \'' . dol_escape_js($week_start) . '\',' . "\n";                     // Start
	print '    \'' . dol_escape_js($week_end) . '\',' . "\n";                       // End
	print '    \'ggroupblack\',' . "\n";                                            // CSS class
	print '    \'\',' . "\n";                                                       // Link
	print '    0,' . "\n";                                                          // Milestone
	print '    \'\',' . "\n";                                                       // Resource
	print '    0,' . "\n";                                                          // Percent complete
	print '    0,' . "\n";                                                          // Group
	print '    0,' . "\n";                                                          // Parent
	print '    1,' . "\n";                                                          // Open
	print '    \'\',' . "\n";                                                       // Dependencies
	print '    \'\',' . "\n";                                                       // Caption
	print '    \'\',' . "\n";                                                       // Notes
	print '    g' . "\n";                                                           // Chart reference
	print '  ));' . "\n";
	print "\n";

	// Draw the chart
	print '  g.Draw(jQuery("#tabs").width() > 0 ? jQuery("#tabs").width() - 40 : jQuery(".fiche").width() - 40);' . "\n";
	print '});' . "\n";
	print '</script>' . "\n";

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
