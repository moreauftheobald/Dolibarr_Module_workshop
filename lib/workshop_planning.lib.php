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
 * \file    lib/workshop_planning.lib.php
 * \ingroup workshop
 * \brief   Shared helpers for the workshop planning / mechanics views.
 */

/**
 * Return the list of mechanics, i.e. the active members of the user group
 * configured in the module admin (constant WORKSHOP_MECHANIC_GROUP), for the
 * current entity.
 *
 * @param  DoliDB $db     Database handler
 * @param  int    $entity Entity to filter on (default: current entity)
 * @return array          Array indexed by user id of stdClass
 *                        {id, lastname, firstname, fullname}, sorted by name.
 *                        Empty array if no group is configured or on error.
 */
function workshop_get_mechanics($db, $entity = null)
{
	global $conf;

	if ($entity === null) {
		$entity = $conf->entity;
	}

	$groupid = getDolGlobalInt('WORKSHOP_MECHANIC_GROUP');
	if (empty($groupid)) {
		return array();
	}

	$sql  = 'SELECT DISTINCT u.rowid, u.lastname, u.firstname';
	$sql .= ' FROM '.$db->prefix().'usergroup_user as gu';
	$sql .= ' INNER JOIN '.$db->prefix().'user as u ON u.rowid = gu.fk_user';
	$sql .= ' WHERE gu.fk_usergroup = '.((int) $groupid);
	$sql .= ' AND u.statut = 1';
	$sql .= ' AND gu.entity IN (0, ' . (int)$entity . ')';
	$sql .= ' ORDER BY u.lastname ASC, u.firstname ASC';

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('workshop_get_mechanics '.$db->lasterror(), LOG_ERR);
		return array();
	}

	$mechanics = array();
	while ($obj = $db->fetch_object($resql)) {
		$m           = new stdClass();
		$m->id       = (int) $obj->rowid;
		$m->lastname = $obj->lastname;
		$m->firstname = $obj->firstname;
		$m->fullname = dolGetFirstLastname($obj->firstname, $obj->lastname);
		$mechanics[$m->id] = $m;
	}
	$db->free($resql);

	return $mechanics;
}

/**
 * Compute the time slot range (start/end) of a given day, as the union of all
 * active workshop / usergroup / user planning records for that weekday.
 *
 * @param  DoliDB $db      Database handler
 * @param  int    $date_ts Timestamp of the day to evaluate
 * @param  int    $entity  Entity (default: current entity)
 * @return array           array('min'=>'HH:MM', 'max'=>'HH:MM'); fallback 07:00–18:00
 */
function workshop_get_day_slot_range($db, $date_ts, $entity = null)
{
	global $conf;

	if ($entity === null) {
		$entity = $conf->entity;
	}

	// ISO weekday (1=Mon … 7=Sun) → French day key used in llx_workshop_planning.
	$daykeys = array(1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche');
	$dow     = (int) dol_print_date($date_ts, '%w'); // 0=Sun..6=Sat, timezone-aware
	if ($dow === 0) {
		$dow = 7; // Convert to ISO (1=Mon..7=Sun)
	}
	$daykey  = isset($daykeys[$dow]) ? $daykeys[$dow] : 'lundi';

	$colstart = $daykey.'_heuredam';
	$colend   = $daykey.'_heurefpm';

	$sql  = 'SELECT MIN('.$colstart.') as mindam, MAX('.$colend.') as maxfpm';
	$sql .= ' FROM '.$db->prefix().'workshop_planning';
	$sql .= ' WHERE entity = '.((int) $entity);
	$sql .= ' AND active = 1';
	$sql .= " AND ".$colstart." IS NOT NULL AND ".$colstart." <> ''";

	$min = '07:00';
	$max = '18:00';

	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj && !empty($obj->mindam) && !empty($obj->maxfpm)) {
			$min = substr($obj->mindam, 0, 5);
			$max = substr($obj->maxfpm, 0, 5);
		}
		$db->free($resql);
	}

	// Safety: ensure max > min, otherwise fall back to defaults.
	if (dol_stringtotime('2000-01-01 '.$max, 'tzuserrel') <= dol_stringtotime('2000-01-01 '.$min, 'tzuserrel')) {
		$min = '07:00';
		$max = '18:00';
	}

	return array('min' => $min, 'max' => $max);
}
