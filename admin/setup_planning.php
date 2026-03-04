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
 * \file    workshop/admin/setup_planning.php
 * \ingroup workshop
 * \brief   Workshop planning setup - weekly schedule per workshop, group and user.
 */

// Load Dolibarr environment
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once '../lib/workshop.lib.php';
dol_include_once('/workshop/class/workshopplanning.class.php');

$langs->loadLangs(array('admin', 'workshop@workshop', 'users'));

if (!$user->hasRight('workshop', 'workshopplanning', 'read')) {
	accessforbidden();
}

$canWrite = $user->hasRight('workshop', 'workshopplanning', 'write');

$hookmanager->initHooks(array('workshopplanningsetup', 'globalsetup'));

// Parameters
$action   = GETPOST('action', 'aZ09');
$fk_group = GETPOST('fk_group', 'int');
$fk_user  = GETPOST('fk_user', 'int');

// Determine context: 'atelier' | 'group' | 'user'
if ($fk_group > 0 && $fk_user > 0) {
	$context     = 'user';
	$objecttype  = 'user';
	$objectid    = $fk_user;
} elseif ($fk_group > 0) {
	$context     = 'group';
	$objecttype  = 'usergroup';
	$objectid    = $fk_group;
} else {
	$context     = 'atelier';
	$objecttype  = 'workshop';
	$objectid    = 0;
}

// Load the groups configured in WORKSHOP_OR_PLANNING_GROUPS (stored as JSON array of IDs)
$planning_groups    = array();
$planning_group_ids = array();
$conf_groups        = getDolGlobalString('WORKSHOP_OR_PLANNING_GROUPS');
if ($conf_groups) {
	$decoded = json_decode($conf_groups, true);
	if (is_array($decoded)) {
		$planning_group_ids = $decoded;
	}
}
foreach ($planning_group_ids as $gid) {
	$grp = new Usergroup($db);
	if ($grp->fetch((int) $gid) > 0) {
		$planning_groups[(int) $gid] = $grp;
	}
}

// Load users for the active group (only for group/user context)
$group_users = array();
if ($fk_group > 0 && isset($planning_groups[$fk_group])) {
	$activeGroup = $planning_groups[$fk_group];
	// getListUsersForGroup returns array of User objects
	$list = $activeGroup->listUsersForGroup('', 1);
	if (is_array($list)) {
		foreach ($list as $usr) {
			// Filter users belonging to current entity
			if ($usr->entity == $conf->entity || $usr->entity == 0) {
				$group_users[$usr->id] = $usr;
			}
		}
	}
}

// Days and slots definition
$days = array(
	'lundi'    => 'Monday',
	'mardi'    => 'Tuesday',
	'mercredi' => 'Wednesday',
	'jeudi'    => 'Thursday',
	'vendredi' => 'Friday',
	'samedi'   => 'Saturday',
	'dimanche' => 'Sunday',
);
$slots = array('heuredam', 'heurefam', 'heuredpm', 'heurefpm');

// Load or initialise planning record
$planning = new WorkshopPlanning($db);
$res      = $planning->fetchByObject($objectid, $objecttype);
if ($res < 0) {
	setEventMessages($langs->trans('Error') . ': ' . $planning->error, null, 'errors');
} elseif ($res == 0) {
	// Create empty record
	$planning->fk_object   = $objectid;
	$planning->object_type = $objecttype;
	$planning->active      = 1;
	$planning->save($user);
}

/*
 * Actions
 */

if ($action == 'save' && $canWrite) {
	foreach ($days as $dayKey => $dayLabel) {
		foreach ($slots as $slot) {
			$field           = $dayKey . '_' . $slot;
			$val             = GETPOST($field, 'alpha');
			// Accept HH:MM or empty
			$planning->$field = (preg_match('/^\d{1,2}:\d{2}$/', $val) ? $val : null);
		}
	}
	$result = $planning->save($user);
	if ($result > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error') . ': ' . $planning->error, null, 'errors');
	}
	// Redirect to avoid form resubmission
	$redirect = $_SERVER['PHP_SELF'] . '?fk_group=' . ((int) $fk_group) . '&fk_user=' . ((int) $fk_user);
	header('Location: ' . $redirect);
	exit;
} elseif ($action == 'activate' && $canWrite) {
	$planning->setActive($user, 1);
	header('Location: ' . $_SERVER['PHP_SELF'] . '?fk_group=' . ((int) $fk_group) . '&fk_user=' . ((int) $fk_user));
	exit;
} elseif ($action == 'disable' && $canWrite) {
	$planning->setActive($user, 0);
	header('Location: ' . $_SERVER['PHP_SELF'] . '?fk_group=' . ((int) $fk_group) . '&fk_user=' . ((int) $fk_user));
	exit;
}

/*
 * View
 */

$help_url = '';
$title    = $langs->trans('WorkshopPlanningSetup');

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-workshop page-admin_planning');

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkback, 'fa-calendar-alt');

// -----------------------------------------------------------------------
// ROW 1 of tabs: "Planning Atelier" + one tab per group
// -----------------------------------------------------------------------

$head1    = array();
$h        = 0;
$baseUrl  = dol_buildpath('/workshop/admin/setup_planning.php', 1);

// Tab "Planning Atelier" (global)
$head1[$h][0] = $baseUrl;
$head1[$h][1] = $langs->trans('WorkshopPlanningTabAtelier');
$head1[$h][2] = 'atelier';
$h++;

// One tab per configured group
foreach ($planning_groups as $gid => $grp) {
	$head1[$h][0] = $baseUrl . '?fk_group=' . $gid;
	$head1[$h][1] = $grp->name;
	$head1[$h][2] = 'group_' . $gid;
	$h++;
}

// Active tab for row 1
$activeTab1 = ($fk_group > 0 ? 'group_' . $fk_group : 'atelier');

print dol_get_fiche_head($head1, $activeTab1, '', -1, 'fa-calendar-alt');

// -----------------------------------------------------------------------
// ROW 2 of tabs: only visible when a group is selected
// "Groupe" sub-tab + one sub-tab per user in the group
// -----------------------------------------------------------------------

if ($fk_group > 0) {
	$head2 = array();
	$h2    = 0;

	// Sub-tab "Groupe" (group-level planning)
	$head2[$h2][0] = $baseUrl . '?fk_group=' . $fk_group;
	$head2[$h2][1] = $langs->trans('WorkshopPlanningTabGroup');
	$head2[$h2][2] = 'group';
	$h2++;

	// Sub-tabs: one per user in the group (belonging to current entity)
	foreach ($group_users as $uid => $usr) {
		$head2[$h2][0] = $baseUrl . '?fk_group=' . $fk_group . '&fk_user=' . $uid;
		$head2[$h2][1] = dolGetFirstLastname($usr->firstname, $usr->lastname);
		$head2[$h2][2] = 'user_' . $uid;
		$h2++;
	}

	$activeTab2 = ($fk_user > 0 ? 'user_' . $fk_user : 'group');

	print dol_get_fiche_head($head2, $activeTab2, '', -1, 'user');
}

// -----------------------------------------------------------------------
// Planning form
// -----------------------------------------------------------------------

// Show activate / deactivate button (only for groups/users, not global)
if ($context !== 'atelier' && $canWrite) {
	print '<div class="tabsAction">';
	if ($planning->active) {
		print '<a class="butActionDelete" href="' . $_SERVER['PHP_SELF'] . '?fk_group=' . ((int) $fk_group) . '&fk_user=' . ((int) $fk_user) . '&action=disable&token=' . newToken() . '">';
		print $langs->trans('WorkshopPlanningDeactivate') . '</a>';
	} else {
		print '<div class="info">' . $langs->trans('WorkshopPlanningInactive') . '</div>';
		print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?fk_group=' . ((int) $fk_group) . '&fk_user=' . ((int) $fk_user) . '&action=activate&token=' . newToken() . '">';
		print $langs->trans('WorkshopPlanningActivate') . '</a>';
	}
	print '</div>';
}

print '<form action="' . $_SERVER['PHP_SELF'] . '" method="POST">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="fk_group" value="' . ((int) $fk_group) . '">';
print '<input type="hidden" name="fk_user" value="' . ((int) $fk_user) . '">';

// Planning title based on context
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';

// Header row: label column + one column per day
print '<tr class="liste_titre">';
print '<th class="minwidth100">' . $langs->trans('WorkshopPlanningPeriod') . '</th>';
foreach ($days as $dayKey => $dayLabel) {
	print '<th class="center">' . $langs->trans($dayLabel) . '</th>';
}
print '</tr>';

// Rows: Matin debut, Matin fin, AM debut, AM fin
$rowDefs = array(
	'heuredam' => 'WorkshopPlanningMorningStart',
	'heurefam' => 'WorkshopPlanningMorningEnd',
	'heuredpm' => 'WorkshopPlanningAfternoonStart',
	'heurefpm' => 'WorkshopPlanningAfternoonEnd',
);

$rowClass = 0;
foreach ($rowDefs as $slot => $labelKey) {
	print '<tr class="' . ($rowClass % 2 == 0 ? 'oddeven' : 'oddeven') . '">';
	print '<td class="titlefieldcreate">' . $langs->trans($labelKey) . '</td>';
	foreach ($days as $dayKey => $dayLabel) {
		$field    = $dayKey . '_' . $slot;
		$val      = $planning->$field;
		$readOnly = $canWrite ? '' : ' readonly';
		print '<td class="center">';
		print '<input type="text" class="flat maxwidth75 center" name="' . $field . '" value="' . dol_escape_htmltag($val ?? '') . '" placeholder="HH:MM"' . $readOnly . '>';
		print '</td>';
	}
	print '</tr>';
	$rowClass++;
}

print '</table>';
print '</div>';

if ($canWrite) {
	print '<div class="center" style="margin-top:10px;">';
	print '<input type="submit" class="button button-save" value="' . $langs->trans('Save') . '">';
	print '</div>';
}

print '</form>';

// Close row-2 tabs if open
if ($fk_group > 0) {
	print dol_get_fiche_end();
}

// Close row-1 tabs
print dol_get_fiche_end();

llxFooter();
$db->close();
