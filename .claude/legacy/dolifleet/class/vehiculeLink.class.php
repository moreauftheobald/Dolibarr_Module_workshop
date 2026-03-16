<?php
/* Copyright (C) 2020 ATM Consulting <support@atm-consulting.fr>
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

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class doliFleetVehiculeLink extends CommonObject
{
	/** @var string $module Module name */
	public $module = 'dolifleet';

	/** @var string $table_element Table name in SQL */
	public $table_element = 'dolifleet_vehicule_link';

	/** @var string $element Name of the element */
	public $element = 'dolifleet_vehicule_link';

	/** @var int $isextrafieldmanaged Enable extrafields management */
	public $isextrafieldmanaged = 0;

	/** @var int $ismultientitymanaged 0=No test on entity, 1=Test with field entity, 2=Test with link by societe */
	public $ismultientitymanaged = 0;

	/** @var int|string $date_start Start date */
	public $date_start;

	/** @var int|string $date_end End date */
	public $date_end;

	/** @var int $fk_source Source vehicule id */
	public $fk_source;

	/** @var int $fk_soc_vehicule_source Source vehicule third party id */
	public $fk_soc_vehicule_source;

	/** @var int $fk_target Target vehicule id */
	public $fk_target;

	/** @var int $fk_soc_vehicule_target Target vehicule third party id */
	public $fk_soc_vehicule_target;

	/** @var int $fk_other_vehicule Other vehicule id (computed, not stored) */
	public $fk_other_vehicule;

	/** @var int|string $date_creation Creation date */
	public $date_creation;

	public $fields = array(
		'rowid' => array(
			'type' => 'integer',
			'label' => 'TechnicalID',
			'enabled' => 1,
			'visible' => 0,
			'notnull' => 1,
			'position' => 1,
			'index' => 1,
		),
		'fk_source' => array(
			'type' => 'integer:Vehicule:dolifleet/class/vehicule.class.php',
			'label' => 'Vehicule',
			'visible' => 1,
			'enabled' => 1,
			'position' => 10,
			'index' => 1,
		),
		'fk_target' => array(
			'type' => 'integer:Vehicule:dolifleet/class/vehicule.class.php',
			'label' => 'Vehicule',
			'visible' => 1,
			'enabled' => 1,
			'position' => 20,
			'index' => 1,
		),
		'date_start' => array(
			'type' => 'date',
			'label' => 'date_start',
			'enabled' => 1,
			'visible' => 1,
			'position' => 30,
			'searchall' => 1,
		),
		'date_end' => array(
			'type' => 'date',
			'label' => 'date_end',
			'enabled' => 1,
			'visible' => 1,
			'position' => 40,
			'searchall' => 1,
		),
		'fk_soc_vehicule_source' => array(
			'type' => 'integer:Societe:societe/class/societe.class.php',
			'label' => 'ThirdParty',
			'visible' => 1,
			'notnull' => 1,
			'default' => 0,
			'enabled' => 1,
			'position' => 50,
			'index' => 1,
			'help' => 'LinkToThirparty'
		),
		'fk_soc_vehicule_target' => array(
			'type' => 'integer:Societe:societe/class/societe.class.php',
			'label' => 'ThirdParty',
			'visible' => 1,
			'notnull' => 1,
			'default' => 0,
			'enabled' => 1,
			'position' => 60,
			'index' => 1,
			'help' => 'LinkToThirparty'
		),
		'date_creation' => array(
			'type' => 'datetime',
			'label' => 'DateCreation',
			'enabled' => 1,
			'visible' => 0,
			'notnull' => 0,
			'position' => 500,
		),
		'tms' => array(
			'type' => 'timestamp',
			'label' => 'DateModification',
			'enabled' => 1,
			'visible' => 0,
			'notnull' => 0,
			'position' => 501,
		),
	);

	/**
	 * doliFleetVehiculeLink constructor.
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
}
