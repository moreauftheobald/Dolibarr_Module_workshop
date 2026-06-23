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
 * \file        class/workshoppointage.class.php
 * \ingroup     workshop
 * \brief       CRUD class for mechanics time tracking (llx_workshop_pointage).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/workshop/class/workshopimpro.class.php');

/**
 * Class WorkshopPointage
 *
 * One row = one time block for a mechanic. A block is either work on a job
 * (type 'job', linked to fk_job/fk_operationorder) or an improductive period
 * (type 'impro', identified by impro_code — absences are improductive codes).
 *
 * Core business rules (see Spec_planning_workshop.md §2) are implemented here
 * and orchestrated under a single DB transaction by addPointage().
 */
class WorkshopPointage extends CommonObject
{
	/** @var string $module Module name */
	public $module = 'workshop';

	/** @var string $table_element Table name in SQL (without llx_ prefix) */
	public $table_element = 'workshop_pointage';

	/** @var string $element Name of the element */
	public $element = 'workshoppointage';

	/** @var string $picto Picto */
	public $picto = 'fa-clock';

	/** @var int $isextrafieldmanaged Enable extrafields management */
	public $isextrafieldmanaged = 0;

	/** @var int $ismultientitymanaged Test with field entity */
	public $ismultientitymanaged = 1;

	/** Pointage on a job/OR. */
	const TYPE_JOB = 'job';

	/** Improductive pointage (incl. absences, identified by impro_code). */
	const TYPE_IMPRO = 'impro';

	public $fields = array(
		'rowid' => array(
			'type'     => 'integer',
			'label'    => 'TechnicalID',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 1,
			'position' => 1,
			'index'    => 1,
		),
		'fk_user' => array(
			'type'     => 'integer:User:user/class/user.class.php',
			'label'    => 'WorkshopMechanic',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 1,
			'position' => 10,
			'index'    => 1,
		),
		'fk_job' => array(
			'type'     => 'integer',
			'label'    => 'WorkshopJob',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 20,
			'index'    => 1,
		),
		'fk_operationorder' => array(
			'type'     => 'integer',
			'label'    => 'WorkshopOR',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 30,
			'index'    => 1,
		),
		'type' => array(
			'type'     => 'varchar(32)',
			'label'    => 'Type',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 1,
			'default'  => 'job',
			'position' => 40,
		),
		'impro_code' => array(
			'type'     => 'varchar(64)',
			'label'    => 'WorkshopImproCode',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 50,
		),
		'date_start' => array(
			'type'     => 'datetime',
			'label'    => 'DateStart',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 1,
			'position' => 60,
		),
		'date_end' => array(
			'type'     => 'datetime',
			'label'    => 'DateEnd',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 70,
		),
		'note' => array(
			'type'     => 'varchar(255)',
			'label'    => 'Note',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 80,
		),
		'date_creation' => array(
			'type'     => 'datetime',
			'label'    => 'DateCreation',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 0,
			'position' => 500,
		),
		'tms' => array(
			'type'     => 'timestamp',
			'label'    => 'DateModification',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 0,
			'position' => 501,
		),
		'fk_user_creat' => array(
			'type'     => 'integer:User:user/class/user.class.php',
			'label'    => 'UserAuthor',
			'enabled'  => 1,
			'visible'  => -2,
			'notnull'  => 1,
			'position' => 510,
		),
		'fk_user_modif' => array(
			'type'     => 'integer:User:user/class/user.class.php',
			'label'    => 'UserModif',
			'enabled'  => 1,
			'visible'  => -2,
			'notnull'  => -1,
			'position' => 511,
		),
		'entity' => array(
			'type'     => 'integer',
			'label'    => 'Entity',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 1,
			'default'  => 1,
			'position' => 520,
			'index'    => 1,
		),
	);

	/** @var int $fk_user Mechanic */
	public $fk_user;

	/** @var int|null $fk_job Related job */
	public $fk_job;

	/** @var int|null $fk_operationorder Related OR (denormalized) */
	public $fk_operationorder;

	/** @var string $type 'job' | 'impro' */
	public $type = self::TYPE_JOB;

	/** @var string|null $impro_code Improductive code when type='impro' */
	public $impro_code;

	/** @var int|string $date_start Start datetime */
	public $date_start;

	/** @var int|string|null $date_end End datetime (NULL = open) */
	public $date_end;

	/** @var string|null $note Free note */
	public $note;

	/** @var int $entity Entity */
	public $entity;

	/**
	 * WorkshopPointage constructor.
	 *
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param  int    $id  Id object
	 * @param  string $ref Ref
	 * @return int         Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param  User $user      User that deletes
	 * @param  int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Fetch the currently open pointage (date_end IS NULL) for a given user.
	 *
	 * @param  int $fk_user Mechanic id
	 * @return WorkshopPointage|null Open pointage or null if none
	 */
	public function fetchOpenForUser($fk_user)
	{
		global $conf;

		$sql  = 'SELECT rowid FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_user = '.((int) $fk_user);
		$sql .= ' AND date_end IS NULL';
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' ORDER BY date_start DESC';
		$sql .= $this->db->plimit(1);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}
		if ($obj = $this->db->fetch_object($resql)) {
			$this->db->free($resql);
			$open = new WorkshopPointage($this->db);
			if ($open->fetch($obj->rowid) > 0) {
				return $open;
			}
		}
		$this->db->free($resql);

		return null;
	}

	/**
	 * Fetch the most recent pointage (by date_start) for a given user.
	 *
	 * @param  int $fk_user Mechanic id
	 * @return WorkshopPointage|null Last pointage or null
	 */
	public function fetchLastForUser($fk_user)
	{
		global $conf;

		$sql  = 'SELECT rowid FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_user = '.((int) $fk_user);
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' ORDER BY date_start DESC, rowid DESC';
		$sql .= $this->db->plimit(1);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}
		if ($obj = $this->db->fetch_object($resql)) {
			$this->db->free($resql);
			$last = new WorkshopPointage($this->db);
			if ($last->fetch($obj->rowid) > 0) {
				return $last;
			}
		}
		$this->db->free($resql);

		return null;
	}

	/**
	 * Register a new pointage applying the business rules under a transaction:
	 *  1. any open pointage of the user is closed at ($date_start - 1 minute);
	 *  2. the new pointage is created;
	 *  3. time_spent of the affected job(s) is recomputed (no billing impact).
	 *
	 * @param  User $user       User performing the action
	 * @param  int  $date_start Start timestamp of the new pointage
	 * @return int              Return integer <0 if KO, id of created pointage if OK
	 */
	public function addPointage(User $user, $date_start)
	{
		$this->db->begin();

		$jobsToRefresh = array();

		// 1. Close any currently open pointage for this user.
		$open = $this->fetchOpenForUser($this->fk_user);
		if ($open !== null) {
			$open->date_end = $date_start - 60;
			if ($open->update($user) < 0) {
				$this->error = $open->error;
				$this->db->rollback();
				return -1;
			}
			if (!empty($open->fk_job)) {
				$jobsToRefresh[(int) $open->fk_job] = (int) $open->fk_job;
			}
		}

		// 2. Create the new pointage.
		$this->date_start = $date_start;
		$newid = $this->create($user);
		if ($newid < 0) {
			$this->db->rollback();
			return -1;
		}
		if (!empty($this->fk_job)) {
			$jobsToRefresh[(int) $this->fk_job] = (int) $this->fk_job;
		}

		// 3. Recompute time_spent for impacted jobs (isolated, no billing recompute).
		foreach ($jobsToRefresh as $fkjob) {
			if ($this->recomputeJobTimeSpent($fkjob) < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();

		return $newid;
	}

	/**
	 * End-of-day action: close the user's current open pointage without
	 * opening a new one (reserved code IMPFin).
	 *
	 * @param  User $user     User performing the action
	 * @param  int  $date_end End timestamp
	 * @return int            Return integer <0 if KO, >0 if OK, 0 if nothing was open
	 */
	public function endDay(User $user, $date_end)
	{
		$open = $this->fetchOpenForUser($this->fk_user);
		if ($open === null) {
			return 0;
		}

		$this->db->begin();

		$open->date_end = $date_end;
		if ($open->update($user) < 0) {
			$this->error = $open->error;
			$this->db->rollback();
			return -1;
		}

		if (!empty($open->fk_job) && $this->recomputeJobTimeSpent((int) $open->fk_job) < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();

		return 1;
	}

	/**
	 * Cancel (delete) the user's last pointage if it started less than
	 * $delayminutes ago (reserved code IMPAnnul).
	 *
	 * @param  User $user         User performing the action
	 * @param  int  $delayminutes Max age in minutes (WORKSHOP_IMPRO_CANCEL_DELAY)
	 * @return int                >0 if cancelled, 0 if nothing to cancel / too old, <0 on error
	 */
	public function cancelLast(User $user, $delayminutes)
	{
		$last = $this->fetchLastForUser($this->fk_user);
		if ($last === null) {
			return 0;
		}

		$age = dol_now() - (int) $last->date_start;
		if ($age > ((int) $delayminutes * 60)) {
			return 0;
		}

		$this->db->begin();

		$fkjob = (int) $last->fk_job;
		if ($last->delete($user) < 0) {
			$this->error = $last->error;
			$this->db->rollback();
			return -1;
		}

		if (!empty($fkjob) && $this->recomputeJobTimeSpent($fkjob) < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();

		return 1;
	}

	/**
	 * Recompute the time_spent (in seconds) of a job from the sum of its closed
	 * 'job' pointages. Updates ONLY the time_spent column — billing totals are
	 * intentionally left untouched.
	 *
	 * @param  int $fk_job Job id
	 * @return int         Return integer <0 if KO, >0 if OK
	 */
	public function recomputeJobTimeSpent($fk_job)
	{
		if (empty($fk_job)) {
			return 1;
		}

		$sql  = 'SELECT SUM(TIMESTAMPDIFF(SECOND, date_start, date_end)) as total';
		$sql .= ' FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_job = '.((int) $fk_job);
		$sql .= " AND type = '".$this->db->escape(self::TYPE_JOB)."'";
		$sql .= ' AND date_end IS NOT NULL';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj   = $this->db->fetch_object($resql);
		$total = ($obj && $obj->total !== null) ? (int) $obj->total : 0;
		$this->db->free($resql);

		$sqlu  = 'UPDATE '.$this->db->prefix().'workshop_operationorder_jobs';
		$sqlu .= ' SET time_spent = '.((int) $total);
		$sqlu .= ' WHERE rowid = '.((int) $fk_job);

		if (!$this->db->query($sqlu)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		dol_syslog(__METHOD__.' job='.((int) $fk_job).' time_spent='.((int) $total), LOG_DEBUG);

		return 1;
	}

	/**
	 * Fetch all pointages of a user between two timestamps (ordered by start).
	 *
	 * @param  int $fk_user    Mechanic id
	 * @param  int $date_start Range start timestamp
	 * @param  int $date_end   Range end timestamp
	 * @return array|int        Array of WorkshopPointage, or -1 on error
	 */
	public function fetchByUserAndPeriod($fk_user, $date_start, $date_end)
	{
		global $conf;

		$sql  = 'SELECT rowid FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_user = '.((int) $fk_user);
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= " AND date_start >= '".$this->db->idate($date_start)."'";
		$sql .= " AND date_start <= '".$this->db->idate($date_end)."'";
		$sql .= ' ORDER BY date_start ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$ret = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$tmp = new WorkshopPointage($this->db);
			$tmp->fetch($obj->rowid);
			$ret[$obj->rowid] = $tmp;
		}
		$this->db->free($resql);

		return $ret;
	}
}
