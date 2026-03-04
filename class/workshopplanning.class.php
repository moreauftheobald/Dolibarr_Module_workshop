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
 * \file    workshop/class/workshopplanning.class.php
 * \ingroup workshop
 * \brief   Class to manage workshop planning (working hours per workshop/group/user)
 */

/**
 * Class WorkshopPlanning
 * Stores and manages weekly working hour schedules for the workshop, user groups, and individual users.
 */
class WorkshopPlanning
{
	/**
	 * @var DoliDB Database handle
	 */
	public $db;

	/** @var int Row ID */
	public $rowid;

	/** @var int ID of the linked object (0 for global workshop, group id, or user id) */
	public $fk_object;

	/** @var string Type of linked object: 'workshop', 'usergroup', or 'user' */
	public $object_type;

	/** @var int Entity */
	public $entity;

	/** @var int Active flag (1=active, 0=inactive) */
	public $active;

	// Monday
	public $lundi_heuredam;
	public $lundi_heurefam;
	public $lundi_heuredpm;
	public $lundi_heurefpm;

	// Tuesday
	public $mardi_heuredam;
	public $mardi_heurefam;
	public $mardi_heuredpm;
	public $mardi_heurefpm;

	// Wednesday
	public $mercredi_heuredam;
	public $mercredi_heurefam;
	public $mercredi_heuredpm;
	public $mercredi_heurefpm;

	// Thursday
	public $jeudi_heuredam;
	public $jeudi_heurefam;
	public $jeudi_heuredpm;
	public $jeudi_heurefpm;

	// Friday
	public $vendredi_heuredam;
	public $vendredi_heurefam;
	public $vendredi_heuredpm;
	public $vendredi_heurefpm;

	// Saturday
	public $samedi_heuredam;
	public $samedi_heurefam;
	public $samedi_heuredpm;
	public $samedi_heurefpm;

	// Sunday
	public $dimanche_heuredam;
	public $dimanche_heurefam;
	public $dimanche_heuredpm;
	public $dimanche_heurefpm;

	/** @var string Error message */
	public $error;

	/** @var array Error messages */
	public $errors = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handle
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->active = 1;
	}

	/**
	 * Load planning record by linked object
	 *
	 * @param  int    $fk_object   ID of the linked object
	 * @param  string $object_type Type: 'workshop', 'usergroup', or 'user'
	 * @param  int    $entity      Entity (null = current entity)
	 * @return int                 >0 if found, 0 if not found, <0 on error
	 */
	public function fetchByObject($fk_object, $object_type, $entity = null)
	{
		global $conf;

		if ($entity === null) {
			$entity = $conf->entity;
		}

		$days  = array('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche');
		$slots = array('heuredam', 'heurefam', 'heuredpm', 'heurefpm');

		$fieldList = 'rowid, fk_object, object_type, entity, active';
		foreach ($days as $day) {
			foreach ($slots as $slot) {
				$fieldList .= ', ' . $day . '_' . $slot;
			}
		}

		$sql  = 'SELECT ' . $fieldList;
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'workshop_planning';
		$sql .= ' WHERE fk_object = ' . ((int) $fk_object);
		$sql .= " AND object_type = '" . $this->db->escape($object_type) . "'";
		$sql .= ' AND entity = ' . ((int) $entity);

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($res);
		if (!$obj) {
			return 0;
		}

		$this->rowid       = $obj->rowid;
		$this->fk_object   = $obj->fk_object;
		$this->object_type = $obj->object_type;
		$this->entity      = $obj->entity;
		$this->active      = $obj->active;

		foreach ($days as $day) {
			foreach ($slots as $slot) {
				$field        = $day . '_' . $slot;
				$this->$field = $obj->$field;
			}
		}

		return 1;
	}

	/**
	 * Save (insert or update) a planning record
	 *
	 * @param  User $user Current user
	 * @return int        >0 if OK, <0 on error
	 */
	public function save($user)
	{
		global $conf;

		$days  = array('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche');
		$slots = array('heuredam', 'heurefam', 'heuredpm', 'heurefpm');
		$entity = isset($this->entity) && $this->entity ? $this->entity : $conf->entity;

		if ($this->rowid) {
			// UPDATE
			$sets = array('active = ' . ((int) $this->active));
			foreach ($days as $day) {
				foreach ($slots as $slot) {
					$field  = $day . '_' . $slot;
					$sets[] = $field . ' = ' . ($this->$field !== null && $this->$field !== '' ? "'" . $this->db->escape($this->$field) . "'" : 'NULL');
				}
			}

			$sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'workshop_planning SET ';
			$sql .= implode(', ', $sets);
			$sql .= ' WHERE rowid = ' . ((int) $this->rowid);
		} else {
			// INSERT
			$cols   = array('date_creation', 'fk_object', 'object_type', 'entity', 'active');
			$values = array("'" . $this->db->idate(dol_now()) . "'", (int) $this->fk_object, "'" . $this->db->escape($this->object_type) . "'", (int) $entity, (int) $this->active);

			foreach ($days as $day) {
				foreach ($slots as $slot) {
					$field    = $day . '_' . $slot;
					$cols[]   = $field;
					$values[] = ($this->$field !== null && $this->$field !== '' ? "'" . $this->db->escape($this->$field) . "'" : 'NULL');
				}
			}

			$sql  = 'INSERT INTO ' . MAIN_DB_PREFIX . 'workshop_planning (' . implode(', ', $cols) . ')';
			$sql .= ' VALUES (' . implode(', ', $values) . ')';
		}

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		if (!$this->rowid) {
			$this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX . 'workshop_planning');
			$this->entity = $entity;
		}

		return 1;
	}

	/**
	 * Set active flag
	 *
	 * @param  User $user   Current user
	 * @param  int  $active 1 to activate, 0 to deactivate
	 * @return int          >0 if OK, <0 on error
	 */
	public function setActive($user, $active)
	{
		$this->active = (int) $active;
		return $this->save($user);
	}
}
