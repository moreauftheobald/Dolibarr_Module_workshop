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
 * \file        class/vehiculeActivity.class.php
 * \ingroup     workshop
 * \brief       CRUD class for VehiculeActivity
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class WorkshopVehiculeActivity extends CommonObject
{
	/** @var string $module Module name */
	public $module = 'workshop';

	/** @var string $table_element Table name in SQL */
	public $table_element = 'workshop_vehicule_activity';

	/** @var string $element Name of the element */
	public $element = 'workshop_vehicule_activity';

	/** @var int $isextrafieldmanaged Enable extrafields management */
	public $isextrafieldmanaged = 0;

	/** @var int $ismultientitymanaged 0=No test on entity, 1=Test with field entity, 2=Test with link by societe */
	public $ismultientitymanaged = 0;

	/** @var int $fk_vehicule Object link to vehicule */
	public $fk_vehicule;

	/** @var int $fk_type Object type */
	public $fk_type;

	/** @var int $fk_soc Third party id */
	public $fk_soc;

	/** @var int|string $date_start Start date */
	public $date_start;

	/** @var int|string $date_end End date */
	public $date_end;

	/** @var int|string $date_creation Creation date */
	public $date_creation;

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
			'notnull' => 1,
			'position' => 10,
			'index'   => 1,
		),
		'fk_type' => array(
			'type'    => 'sellist:workshop_vehicule_c_vehicule_activity_type:label:rowid::active=1',
			'label'   => 'ActivityType',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 50,
			'index'   => 1,
		),
		'date_start' => array(
			'type'      => 'date',
			'label'     => 'date_start',
			'enabled'   => 1,
			'visible'   => 1,
			'position'  => 70,
			'searchall' => 1,
		),
		'date_end' => array(
			'type'      => 'date',
			'label'     => 'date_end',
			'enabled'   => 1,
			'visible'   => 1,
			'position'  => 80,
			'searchall' => 1,
		),
		'fk_soc' => array(
			'type'    => 'integer:Societe:societe/class/societe.class.php',
			'label'   => 'ThirdParty',
			'visible' => 1,
			'enabled' => 1,
			'position' => 90,
			'index'   => 1,
			'help'    => 'LinkToThirparty'
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
	 * WorkshopVehiculeActivity constructor.
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
	 * Get activity type label from dictionary
	 *
	 * @return string Type label or empty string
	 */
	public function getType()
	{
		if (empty($this->fk_type)) {
			return '';
		}

		$sql = "SELECT label FROM ".$this->db->prefix()."workshop_vehicule_c_vehicule_activity_type";
		$sql .= " WHERE rowid = ".((int) $this->fk_type);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				return $obj->label;
			}
		}

		return '';
	}

	/**
	 * Verify that no existing activity overlaps the given dates for this vehicule
	 *
	 * @return bool true if dates are valid (no overlap), false otherwise
	 */
	public function verifyDates()
	{
		global $langs;

		$sql = "SELECT COUNT(rowid) as nb FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_vehicule = ".((int) $this->fk_vehicule);
		if (!empty($this->date_start)) {
			$sql .= " AND date_end > '".$this->db->idate($this->date_start)."'";
		}
		if (!empty($this->date_end)) {
			$sql .= " AND date_start < '".$this->db->idate($this->date_end)."'";
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if (empty($obj->nb)) {
				return true;
			} else {
				$this->error = $langs->trans('ErrAlreadyInActivity');
			}
		} else {
			$this->error = $this->db->lasterror();
		}

		return false;
	}

	/**
	 * Verify that no existing activity overlaps the given dates for this vehicule (excluding current record)
	 *
	 * @return bool true if dates are valid (no overlap), false otherwise
	 */
	public function verifyDatesforupdate()
	{
		global $langs;

		$sql = "SELECT COUNT(rowid) as nb FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_vehicule = ".((int) $this->fk_vehicule);
		$sql .= " AND rowid <> ".((int) $this->id);
		$sql .= " AND ((date_start < '".$this->db->idate($this->date_start)."' AND date_end > '".$this->db->idate($this->date_start)."')";
		$sql .= " OR (date_start < '".$this->db->idate($this->date_end)."' AND date_end > '".$this->db->idate($this->date_end)."'))";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if (empty($obj->nb)) {
				return true;
			} else {
				$this->error = $langs->trans('ErrAlreadyInActivity');
			}
		} else {
			$this->error = $this->db->lasterror();
		}

		return false;
	}
}
