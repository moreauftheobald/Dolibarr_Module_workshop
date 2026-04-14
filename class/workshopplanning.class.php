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

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class WorkshopPlanning
 * Stores and manages weekly working hour schedules for the workshop, user groups, and individual users.
 */
class WorkshopPlanning extends CommonObject
{
	/** @var string Module name */
	public $module = 'workshop';

	/** @var string Element name */
	public $element = 'workshop_planning';

	/** @var string Table name without prefix */
	public $table_element = 'workshop_planning';

	/** @var int Multi-entity managed */
	public $ismultientitymanaged = 1;

	/** @var int Extrafields managed */
	public $isextrafieldmanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 0, 'index' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 1),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 2),
		'fk_object' => array('type' => 'integer', 'label' => 'Object', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'default' => 0, 'position' => 10),
		'object_type' => array('type' => 'varchar(50)', 'label' => 'ObjectType', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'default' => 'workshop', 'position' => 11),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'default' => 1, 'position' => 12, 'index' => 1),
		'active' => array('type' => 'integer', 'label' => 'Active', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'default' => 1, 'position' => 13),
		// Monday
		'lundi_heuredam' => array('type' => 'varchar(5)', 'label' => 'LundiAM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 100),
		'lundi_heurefam' => array('type' => 'varchar(5)', 'label' => 'LundiAM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 101),
		'lundi_heuredpm' => array('type' => 'varchar(5)', 'label' => 'LundiPM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 102),
		'lundi_heurefpm' => array('type' => 'varchar(5)', 'label' => 'LundiPM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 103),
		// Tuesday
		'mardi_heuredam' => array('type' => 'varchar(5)', 'label' => 'MardiAM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 110),
		'mardi_heurefam' => array('type' => 'varchar(5)', 'label' => 'MardiAM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 111),
		'mardi_heuredpm' => array('type' => 'varchar(5)', 'label' => 'MardiPM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 112),
		'mardi_heurefpm' => array('type' => 'varchar(5)', 'label' => 'MardiPM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 113),
		// Wednesday
		'mercredi_heuredam' => array('type' => 'varchar(5)', 'label' => 'MercrediAM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 120),
		'mercredi_heurefam' => array('type' => 'varchar(5)', 'label' => 'MercrediAM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 121),
		'mercredi_heuredpm' => array('type' => 'varchar(5)', 'label' => 'MercrediPM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 122),
		'mercredi_heurefpm' => array('type' => 'varchar(5)', 'label' => 'MercrediPM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 123),
		// Thursday
		'jeudi_heuredam' => array('type' => 'varchar(5)', 'label' => 'JeudiAM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 130),
		'jeudi_heurefam' => array('type' => 'varchar(5)', 'label' => 'JeudiAM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 131),
		'jeudi_heuredpm' => array('type' => 'varchar(5)', 'label' => 'JeudiPM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 132),
		'jeudi_heurefpm' => array('type' => 'varchar(5)', 'label' => 'JeudiPM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 133),
		// Friday
		'vendredi_heuredam' => array('type' => 'varchar(5)', 'label' => 'VendrediAM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 140),
		'vendredi_heurefam' => array('type' => 'varchar(5)', 'label' => 'VendrediAM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 141),
		'vendredi_heuredpm' => array('type' => 'varchar(5)', 'label' => 'VendrediPM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 142),
		'vendredi_heurefpm' => array('type' => 'varchar(5)', 'label' => 'VendrediPM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 143),
		// Saturday
		'samedi_heuredam' => array('type' => 'varchar(5)', 'label' => 'SamediAM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 150),
		'samedi_heurefam' => array('type' => 'varchar(5)', 'label' => 'SamediAM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 151),
		'samedi_heuredpm' => array('type' => 'varchar(5)', 'label' => 'SamediPM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 152),
		'samedi_heurefpm' => array('type' => 'varchar(5)', 'label' => 'SamediPM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 153),
		// Sunday
		'dimanche_heuredam' => array('type' => 'varchar(5)', 'label' => 'DimancheAM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 160),
		'dimanche_heurefam' => array('type' => 'varchar(5)', 'label' => 'DimancheAM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 161),
		'dimanche_heuredpm' => array('type' => 'varchar(5)', 'label' => 'DimanchePM_Start', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 162),
		'dimanche_heurefpm' => array('type' => 'varchar(5)', 'label' => 'DimanchePM_End', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 163),
	);

	/** @var int ID of the linked object (0 for global workshop, group id, or user id) */
	public $fk_object;

	/** @var string Type of linked object: 'workshop', 'usergroup', or 'user' */
	public $object_type;

	/** @var int Entity */
	public $entity;

	/** @var int Active flag (1=active, 0=inactive) */
	public $active;

	/** @var int|string Date creation */
	public $date_creation;

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
	 * Create object in database
	 *
	 * @param  User $user      User that creates
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object by ID
	 *
	 * @param  int    $id  Row ID
	 * @param  string $ref Not used (no ref field)
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref);
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

		$sql = 'SELECT *';
		$sql .= ' FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_object = '.((int) $fk_object);
		$sql .= " AND object_type = '".$this->db->escape($object_type)."'";
		$sql .= ' AND entity = '.((int) $entity);

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($res);
		if (!$obj) {
			return 0;
		}

		$this->setVarsFromFetchObj($obj);

		return 1;
	}

	/**
	 * Update object in database
	 *
	 * @param  User $user      User that modifies
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
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

		if (!empty($this->id)) {
			return $this->updateCommon($user);
		}

		if (empty($this->entity)) {
			$this->entity = $conf->entity;
		}

		return $this->createCommon($user);
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
