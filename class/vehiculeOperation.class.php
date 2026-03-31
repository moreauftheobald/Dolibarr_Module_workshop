<?php
/* Copyright (C) 2020 ATM Consulting <support@atm-consulting.fr>
 * Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
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
 * \file        class/vehiculeOperation.class.php
 * \ingroup     workshop
 * \brief       CRUD class for VehiculeOperation (maintenance scheduling)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class WorkshopVehiculeOperation extends CommonObject
{
	/** Draft status */
	const STATUS_DRAFT = 0;

	/** To Plan status */
	const STATUS_TOPLAN = 1;

	/** Planned status */
	const STATUS_PLANNED = 2;

	/** Done status */
	const STATUS_DONE = 3;

	/** @var array $TStatus Array of translate key for each const */
	public static $TStatus = array(
		self::STATUS_DRAFT   => 'VehiculeOperationStatusShortDraft',
		self::STATUS_TOPLAN  => 'VehiculeOperationStatusShortToPlan',
		self::STATUS_PLANNED => 'VehiculeOperationStatusShortPlanned',
		self::STATUS_DONE    => 'VehiculeOperationStatusShortDone',
	);

	/** @var string $module Module name */
	public $module = 'workshop';

	/** @var string $table_element Table name in SQL */
	public $table_element = 'workshop_vehicule_operation';

	/** @var string $element Name of the element */
	public $element = 'workshop_vehicule_operation';

	/** @var int $isextrafieldmanaged Enable extrafields management */
	public $isextrafieldmanaged = 0;

	/** @var int $ismultientitymanaged 0=No test on entity, 1=Test with field entity, 2=Test with link by societe */
	public $ismultientitymanaged = 0;

	/** @var int $fk_vehicule Object link to vehicule */
	public $fk_vehicule;

	/** @var int $fk_maintenance_operation Maintenance operation id (dictionary) */
	public $fk_maintenance_operation;

	/** @var int $status Status */
	public $status;

	/** @var double $km Km interval */
	public $km;

	/** @var int $delai_from_last_op Delay from last operation in months */
	public $delai_from_last_op;

	/** @var int|string $date_done Last operation date */
	public $date_done;

	/** @var int|string $date_due Due date */
	public $date_due;

	/** @var int $rang Sort rank */
	public $rang;

	/** @var double $km_done Last operation km */
	public $km_done;

	/** @var int|string $date_next Next operation date */
	public $date_next;

	/** @var double $km_next Next operation km */
	public $km_next;

	/** @var int $on_time On time flag */
	public $on_time;

	/** @var int $or_next Next operation order id */
	public $or_next;

	/** @var int|string $date_creation Creation date */
	public $date_creation;

	/** @var array Status label cache */
	public $labelStatus = array();

	/** @var array Status short label cache */
	public $labelStatusShort = array();

	public $fields = array(
		'rowid' => array(
			'type'    => 'integer',
			'label'   => 'TechnicalID',
			'enabled' => 1,
			'visible' => 0,
			'notnull' => 1,
			'position' => 1,
			'index'   => 1,
		),
		'fk_vehicule' => array(
			'type'    => 'integer:Vehicule:workshop/class/vehicule.class.php',
			'label'   => 'Vehicule',
			'visible' => 1,
			'enabled' => 1,
			'position' => 10,
			'index'   => 1,
		),
		'fk_maintenance_operation' => array(
			'type'    => 'integer:VehiculeMaintenanceOperation:workshop/class/vehiculemaintenanceoperation.class.php',
			'label'   => 'VehiculeOperation',
			'visible' => 1,
			'enabled' => 1,
			'position' => 20,
			'notnull' => 0,
			'index'   => 1,
		),
		'status' => array(
			'type'    => 'integer',
			'label'   => 'Status',
			'enabled' => 1,
			'visible' => 0,
			'notnull' => 1,
			'default' => 1,
			'index'   => 1,
			'position' => 30,
			'arrayofkeyval' => array(
				0 => 'VehiculeOperationStatusShortDraft',
				1 => 'VehiculeOperationStatusShortToPlan',
				2 => 'VehiculeOperationStatusShortPlanned',
				3 => 'VehiculeOperationStatusShortDone',
			)
		),
		'rang' => array(
			'type'    => 'integer',
			'visible' => 0,
			'enabled' => 1,
			'position' => 40,
		),
		'km' => array(
			'type'    => 'double',
			'visible' => 1,
			'enabled' => 1,
			'position' => 50,
		),
		'delai_from_last_op' => array(
			'type'    => 'integer',
			'label'   => 'VehiculeOperationDelay',
			'visible' => 1,
			'enabled' => 1,
			'position' => 60,
			'comment' => 'delay from last operation in months',
		),
		'date_done' => array(
			'type'    => 'date',
			'label'   => 'VehiculeOperationLastDateDone',
			'visible' => 1,
			'enabled' => 1,
			'position' => 70,
		),
		'km_done' => array(
			'type'    => 'double',
			'label'   => 'VehiculeOperationLastKmDone',
			'visible' => 1,
			'enabled' => 1,
			'position' => 80,
		),
		'date_next' => array(
			'type'    => 'date',
			'label'   => 'VehiculeOperationDateNext',
			'visible' => 1,
			'enabled' => 1,
			'position' => 85,
		),
		'date_due' => array(
			'type'    => 'date',
			'label'   => 'VehiculeOperationDateDue',
			'visible' => 1,
			'enabled' => 1,
			'position' => 90,
		),
		'km_next' => array(
			'type'    => 'double',
			'label'   => 'VehiculeOperationKmNext',
			'visible' => 1,
			'enabled' => 1,
			'position' => 95,
		),
		'on_time' => array(
			'type'    => 'integer',
			'label'   => 'VehiculeOperationOnTime',
			'visible' => 1,
			'enabled' => 1,
			'position' => 100,
		),
		'or_next' => array(
			'type'    => 'integer',
			'label'   => 'VehiculeOperationNextOR',
			'visible' => 1,
			'enabled' => 1,
			'position' => 105,
			'default' => null,
		),
		'date_creation' => array(
			'type'    => 'datetime',
			'label'   => 'DateCreation',
			'enabled' => 1,
			'visible' => 0,
			'notnull' => 0,
			'position' => 500,
		),
		'tms' => array(
			'type'    => 'timestamp',
			'label'   => 'DateModification',
			'enabled' => 1,
			'visible' => 0,
			'notnull' => 0,
			'position' => 501,
		),
	);

	/**
	 * WorkshopVehiculeOperation constructor.
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->date_next = null;
		$this->date_done = null;
		$this->date_due  = null;
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
		global $langs;

		if (empty($this->fk_vehicule) || $this->fk_vehicule == '-1') {
			$this->errors[] = $langs->trans('ErrInvalidFkVehicule');
		}

		if (empty($this->fk_maintenance_operation)) {
			$this->errors[] = $langs->trans('ErrOperationNoMaintenanceOperation');
		}

		dol_include_once('/workshop/class/vehiculemaintenanceoperation.class.php');
		$mainOpe = new VehiculeMaintenanceOperation($this->db);
		$ret  = $mainOpe->fetch($this->fk_maintenance_operation);
		if ($ret <= 0) {
			$this->errors[] = $langs->trans('ErrOperationInvalidMaintenanceOperation');
		}

		if (empty($this->km) && empty($this->delai_from_last_op)) {
			$this->errors[] = $langs->trans('ErrOperationNoCritera');
		}

		$this->calcNextOpe();

		if (!empty($this->errors)) {
			return -1;
		}

		if (empty($this->id)) {
			$this->rang = (int) $this->getMaxRank() + 1;
		}

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
		$this->calcNextOpe();
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
	 * Return the status label
	 *
	 * @param  int $mode 0=Long label, 1=Short label, 2=Picto + Short label, 3=Picto, 4=Picto + Long label, 5=Short label + Picto, 6=Long label + Picto
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Return the status label for a given status
	 *
	 * @param  int $status Status
	 * @param  int $mode   0=Long label, 1=Short label, 2=Picto + Short label, 3=Picto, 4=Picto + Long label, 5=Short label + Picto, 6=Long label + Picto
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$langs->load('workshop@workshop');

		if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
			$this->labelStatus[self::STATUS_DRAFT]   = $langs->transnoentitiesnoconv('VehiculeOperationStatusDraft');
			$this->labelStatus[self::STATUS_TOPLAN]  = $langs->transnoentitiesnoconv('VehiculeOperationStatusToPlan');
			$this->labelStatus[self::STATUS_PLANNED] = $langs->transnoentitiesnoconv('VehiculeOperationStatusPlanned');
			$this->labelStatus[self::STATUS_DONE]    = $langs->transnoentitiesnoconv('VehiculeOperationStatusDone');

			$this->labelStatusShort[self::STATUS_DRAFT]   = $langs->transnoentitiesnoconv('VehiculeOperationStatusShortDraft');
			$this->labelStatusShort[self::STATUS_TOPLAN]  = $langs->transnoentitiesnoconv('VehiculeOperationStatusShortToPlan');
			$this->labelStatusShort[self::STATUS_PLANNED] = $langs->transnoentitiesnoconv('VehiculeOperationStatusShortPlanned');
			$this->labelStatusShort[self::STATUS_DONE]    = $langs->transnoentitiesnoconv('VehiculeOperationStatusShortDone');
		}

		$statusType = 'status0';
		if ($status == self::STATUS_TOPLAN) {
			$statusType = 'status6';
		} elseif ($status == self::STATUS_PLANNED) {
			$statusType = 'status1';
		} elseif ($status == self::STATUS_DONE) {
			$statusType = 'status4';
		}

		return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
	}

	/**
	 * Get maintenance operation label
	 *
	 * @return string Operation code + label or empty string
	 */
	public function getName()
	{
		if (empty($this->fk_maintenance_operation)) {
			return '';
		}
		dol_include_once('/workshop/class/vehiculemaintenanceoperation.class.php');
		$mainOpe = new VehiculeMaintenanceOperation($this->db);
		if ($mainOpe->fetch($this->fk_maintenance_operation) > 0) {
			return '<b>'.dol_htmlentities($mainOpe->code).'</b> - '.dol_htmlentities($mainOpe->label);
		}

		return '';
	}

	/**
	 * Get maintenance operation label for web display
	 *
	 * @return string Operation code + label or empty string
	 */
	public function getWebName()
	{
		return $this->getName();
	}

	/**
	 * Get max rank for operations of this vehicule
	 *
	 * @return int Max rank value
	 */
	public function getMaxRank()
	{
		$sql = "SELECT MAX(rang) as maxrank FROM ".$this->db->prefix().$this->table_element." WHERE fk_vehicule = ".((int) $this->fk_vehicule);
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			return $obj->maxrank;
		}

		return 0;
	}

	/**
	 * Calculate next operation dates and km
	 *
	 * @return void
	 */
	public function calcNextOpe()
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

		if (!empty($this->km)) {
			$this->km_next = (int) $this->km_done + (int) $this->km;
		} else {
			$this->km_next = null;
		}

		if (empty($this->km) && !empty($this->delai_from_last_op)) {
			$dt            = dol_time_plus_duree($this->date_done, (int) $this->delai_from_last_op, 'm');
			$this->date_due = dol_time_plus_duree($this->date_done, (int) $this->delai_from_last_op, 'm');
			if ($dt > dol_now()) {
				$this->date_next = dol_time_plus_duree($this->date_done, (int) $this->delai_from_last_op, 'm');
			} else {
				$this->date_next = dol_now();
			}
		}

		if ($this->date_next <= dol_now() && empty($this->or_next)) {
			$this->on_time = 1;
		} else {
			$this->on_time = 0;
		}
	}

	/**
	 * Check if operation needs update based on operation order
	 * (matches via or_next field — the explicit OR link)
	 *
	 * @param  CommonObject $object Operation order object
	 * @return bool                 True if this operation is linked to the given OR
	 */
	public function operationNeedUpdate($object)
	{
		return !empty($this->or_next) && (int) $this->or_next === (int) $object->id;
	}
}
