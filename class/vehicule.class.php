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
 * \file        class/vehicule.class.php
 * \ingroup     workshop
 * \brief       CRUD class for Vehicule — V5 refactored from doliFleet
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

dol_include_once('/workshop/class/vehiculeActivity.class.php');
dol_include_once('/workshop/class/vehiculeLink.class.php');
dol_include_once('/workshop/class/vehiculeOperation.class.php');
dol_include_once('/workshop/class/vehiculemark.class.php');
dol_include_once('/workshop/class/vehiculetype.class.php');

class Vehicule extends CommonObject
{
	/**
	 * Draft status
	 */
	const STATUS_DRAFT = 0;

	/**
	 * Active status
	 */
	const STATUS_ACTIVE = 1;

	/** @var array $TStatus Array of translate key for each const */
	public static $TStatus = array(
		self::STATUS_DRAFT  => 'VehiculeStatusShortDraft',
		self::STATUS_ACTIVE => 'VehiculeStatusShortActivated',
	);

	/** @var string $module Module name */
	public $module = 'workshop';

	/** @var string $table_element Table name in SQL (without llx_ prefix) */
	public $table_element = 'workshop_vehicule';

	/** @var string $element Name of the element */
	public $element = 'vehicule';

	/** @var string $picto Picto */
	public $picto = 'fa-truck';

	/** @var int $isextrafieldmanaged Enable extrafields management */
	public $isextrafieldmanaged = 1;

	/** @var int $ismultientitymanaged 0=No test on entity, 1=Test with field entity, 2=Test with link by societe */
	public $ismultientitymanaged = 1;

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
		'vin' => array(
			'type'          => 'varchar(50)',
			'length'        => 50,
			'label'         => 'VIN',
			'enabled'       => 1,
			'visible'       => 1,
			'notnull'       => 1,
			'showoncombobox' => 1,
			'index'         => 1,
			'position'      => 10,
			'searchall'     => 1,
			'comment'       => 'Vehicule international number',
		),
		'entity' => array(
			'type'    => 'integer',
			'label'   => 'Entity',
			'enabled' => 1,
			'visible' => 0,
			'default' => 1,
			'notnull' => 1,
			'index'   => 1,
			'position' => 20,
		),
		'status' => array(
			'type'    => 'integer',
			'label'   => 'Status',
			'enabled' => 1,
			'visible' => 1,
			'notnull' => 1,
			'default' => 0,
			'index'   => 1,
			'position' => 30,
			'arrayofkeyval' => array(
				0 => 'VehiculeStatusShortDraft',
				1 => 'VehiculeStatusShortActivated',
			)
		),
		'fk_vehicule_type' => array(
			'type'    => 'sellist:workshop_vehicule_c_vehicule_type:label:rowid::(active:=:1)',
			'label'   => 'vehiculeType',
			'visible' => 1,
			'notnull' => 1,
			'default' => 0,
			'enabled' => 1,
			'position' => 40,
			'index'   => 1,
		),
		'fk_vehicule_mark' => array(
			'type'    => 'sellist:workshop_vehicule_c_vehicule_mark:label:rowid::(active:=:1)',
			'label'   => 'vehiculeMark',
			'visible' => 1,
			'notnull' => 1,
			'default' => 0,
			'enabled' => 1,
			'position' => 50,
			'index'   => 1,
		),
		'modele' => array(
			'type'    => 'varchar(255)',
			'label'   => 'modele',
			'enabled' => 1,
			'visible' => 1,
			'notnull' => 0,
			'index'   => 0,
			'position' => 55,
		),
		'immatriculation' => array(
			'type'          => 'varchar(20)',
			'label'         => 'immatriculation',
			'enabled'       => 1,
			'visible'       => 1,
			'notnull'       => 1,
			'position'      => 60,
			'searchall'     => 1,
			'css'           => 'minwidth200',
			'showoncombobox' => 1,
		),
		'date_immat' => array(
			'type'    => 'date',
			'label'   => 'immatriculation_date',
			'enabled' => 1,
			'visible' => 1,
			'notnull' => 1,
			'default' => 0,
			'position' => 70,
			'searchall' => 1,
		),
		'fk_soc' => array(
			'type'    => 'integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))',
			'label'   => 'ThirdParty',
			'picto'   => 'company',
			'enabled' => 'isModEnabled("societe")',
			'position' => 50,
			'notnull' => -1,
			'visible' => 1,
			'index'   => 1,
			'css'     => 'maxwidth500 widthcentpercentminusxx',
			'csslist' => 'tdoverflowmax150',
			'help'    => 'ThirdPartyBookCalHelp',
			'validate' => 1,
		),
		'date_customer_exploit' => array(
			'type'    => 'date',
			'label'   => 'date_customer_exploit',
			'visible' => 1,
			'enabled' => 1,
			'position' => 90,
		),
		'km' => array(
			'type'    => 'double',
			'label'   => 'kilometrage',
			'visible' => 1,
			'notnull' => 1,
			'default' => 0,
			'enabled' => 1,
			'position' => 100,
		),
		'km_date' => array(
			'type'    => 'date',
			'label'   => 'km_date',
			'visible' => 1,
			'enabled' => 1,
			'position' => 110,
		),
		'fk_contract_type' => array(
			'type'    => 'sellist:workshop_vehicule_c_contract_type:label:rowid::(active:=:1)',
			'label'   => 'contractType',
			'visible' => 1,
			'enabled' => 1,
			'position' => 120,
			'index'   => 1,
		),
		'date_end_contract' => array(
			'type'    => 'date',
			'label'   => 'date_end_contract',
			'visible' => 1,
			'enabled' => 1,
			'position' => 130,
		),
		'atelier' => array(
			'type'    => 'sellist:entity:label:rowid::(visible:=:1)',
			'label'   => 'AtelierPrincipal',
			'visible' => 1,
			'enabled' => 1,
			'position' => 140,
		),
		'carrosserie' => array(
			'type'    => 'text',
			'label'   => 'Carrosserie',
			'enabled' => 1,
			'visible' => '1',
			'position' => 150,
		),
		'dfol' => array(
			'type'    => 'integer',
			'label'   => 'DFolVC',
			'enabled' => 1,
			'visible' => 1,
			'notnull' => 1,
			'default' => 0,
			'position' => 160,
			'arrayofkeyval' => array(
				0 => 'Non',
				1 => 'Oui'
			)
		),
		'nb_pneu' => array(
			'type'    => 'int',
			'label'   => 'NbPneu',
			'enabled' => 1,
			'visible' => '1',
			'position' => 240,
		),
		'dim_pneu' => array(
			'type'    => 'chkbxlst:workshop_vehicule_c_vehicule_dimpneu:label:rowid::(active:=:1)',
			'label'   => 'DimensionsPneumatiques',
			'enabled' => 1,
			'visible' => '1',
			'position' => 250,
		),
		'essieu' => array(
			'type'    => 'varchar(255)',
			'label'   => 'SNEssieu',
			'enabled' => 1,
			'visible' => '0',
			'position' => 170,
		),
		'type_custom' => array(
			'type'    => 'int',
			'label'   => 'Type',
			'enabled' => 1,
			'visible' => 0,
			'position' => 171,
		),
		'coutm' => array(
			'type'    => 'price',
			'label'   => 'CoutMensuel',
			'enabled' => 1,
			'visible' => 0,
			'position' => 172,
		),
		'date_fin_fin' => array(
			'type'    => 'date',
			'label'   => 'DateFinFinancement',
			'enabled' => 1,
			'visible' => 0,
			'position' => 180,
		),
		'type_fin' => array(
			'type'    => 'varchar(255)',
			'label'   => 'TypeFinancement',
			'enabled' => 1,
			'visible' => 0,
			'position' => 190,
		),
		'com_custom' => array(
			'type'    => 'text',
			'label'   => 'Commentaire',
			'enabled' => 1,
			'visible' => '1',
			'position' => 200,
		),
		'date_fin_loc' => array(
			'type'    => 'date',
			'label'   => 'DateEndLocation',
			'enabled' => 1,
			'visible' => 0,
			'position' => 210,
		),
		'exit_data' => array(
			'type'    => 'int',
			'label'   => 'SortiePrevue',
			'enabled' => 1,
			'visible' => 0,
			'position' => 220,
		),
		'age_veh' => array(
			'type'    => 'int',
			'label'   => 'AgeVeh',
			'enabled' => 1,
			'visible' => 0,
			'position' => 230,
		),
		'import_key' => array(
			'type'    => 'varchar(14)',
			'label'   => 'ImportId',
			'enabled' => 1,
			'visible' => -2,
			'notnull' => -1,
			'index'   => 0,
			'position' => 1000,
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

	/** @var string $vin Object reference */
	public $vin;

	/** @var int $entity Object entity */
	public $entity;

	/** @var int $status Object status */
	public $status;

	public $fk_vehicule_type;
	public $fk_vehicule_mark;
	public $modele;
	public $immatriculation;
	public $date_immat;
	public $fk_soc;
	public $date_customer_exploit;
	public $km;
	public $km_date;
	public $fk_contract_type;
	public $date_end_contract;
	public $atelier;
	public $carrosserie;
	public $dfol;
	public $nb_pneu;
	public $dim_pneu;
	public $essieu;
	public $type_custom;
	public $coutm;
	public $date_fin_fin;
	public $type_fin;
	public $com_custom;
	public $date_fin_loc;
	public $exit_data;
	public $age_veh;
	public $import_key;

	/** @var int|string $date_creation Creation date */
	public $date_creation;

	/**
	 * Vehicule constructor.
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->status = self::STATUS_DRAFT;
	}

	/**
	 * Create or update object into database with validation
	 *
	 * @param  User $user      User that creates
	 * @param  int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, Id of created object if OK
	 */
	public function save($user, $notrigger = false)
	{
		global $langs;

		if (empty($this->vin)) {
			$this->errors[] = $langs->trans("ErrNoVinNumber");
		}

		// Check VIN uniqueness
		$veh = new static($this->db);
		$ret = $veh->fetchByVin($this->vin);
		if ($ret > 0 && $veh->id != $this->id) {
			$this->errors[] = $langs->trans('ErrVinAlreadyUsed', html_entity_decode($veh->getNomUrl()));
		}

		if (empty($this->fk_vehicule_type)) {
			$this->errors[] = $langs->trans('ErrInvalidVehiculeType');
		}
		if (empty($this->fk_vehicule_mark)) {
			$this->errors[] = $langs->trans('ErrInvalidVehiculeMark');
		}
		if (empty($this->immatriculation)) {
			$this->errors[] = $langs->trans('ErrEmptyVehiculeImmatriculation');
		}
		if (empty($this->date_immat)) {
			$this->errors[] = $langs->trans('ErrEmptyVehiculeImmatDate');
		}
		if (empty($this->fk_soc) || $this->fk_soc == '-1') {
			$this->errors[] = $langs->trans('ErrInvalidSocid');
		}

		if (!empty($this->errors)) {
			return -1;
		}

		if (!empty($this->id)) {
			return $this->updateCommon($user, (int) $notrigger);
		} else {
			return $this->createCommon($user, (int) $notrigger);
		}
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
	 * Load object by VIN
	 *
	 * @param  string $vin VIN to search
	 * @return int         Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchByVin($vin)
	{
		$sql = "SELECT rowid FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE vin = '".$this->db->escape($vin)."'";
		$sql .= " AND entity IN (".getEntity($this->element).")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				return $this->fetch($obj->rowid);
			}
			return 0;
		}

		$this->error = $this->db->lasterror();
		return -1;
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
		$this->deleteObjectLinked();

		unset($this->fk_element); // avoid conflict with standard Dolibarr behaviour

		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Set draft status
	 *
	 * @param  User $user User object
	 * @return int        Return integer <0 if KO, >0 if OK
	 */
	public function setDraft($user)
	{
		if ($this->status === self::STATUS_ACTIVE) {
			$this->status = self::STATUS_DRAFT;
			return $this->updateCommon($user);
		}

		return 0;
	}

	/**
	 * Set active status
	 *
	 * @param  User $user User object
	 * @return int        Return integer <0 if KO, >0 if OK
	 */
	public function setValid($user)
	{
		if ($this->status === self::STATUS_DRAFT) {
			$this->status = self::STATUS_ACTIVE;
			return $this->updateCommon($user);
		}

		return 0;
	}

	// -----------------------------------------------------------
	// Activities management
	// -----------------------------------------------------------

	public function getActivities($date_start = '', $date_end = '')
	{
		$this->activities = array();

		$act = new WorkshopVehiculeActivity($this->db);

		$sql = "SELECT rowid FROM ".$this->db->prefix().$act->table_element;
		$sql .= " WHERE fk_vehicule = ".((int) $this->id);
		if (!empty($date_end)) {
			$sql .= " AND date_start < '".$this->db->idate($date_end)."'";
		}
		if (!empty($date_start)) {
			$sql .= " AND date_end > '".$this->db->idate($date_start)."'";
		}
		$sql .= " ORDER BY date_start ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			if ($num) {
				while ($obj = $this->db->fetch_object($resql)) {
					$act = new WorkshopVehiculeActivity($this->db);
					$ret = $act->fetch($obj->rowid);
					if ($ret > 0) {
						$this->activities[$obj->rowid] = $act;
					} else {
						$this->error = $act->error;
					}
				}
			}

			return $num;
		}

		return -1;
	}

	/**
	 * @param int    $type       Activity Type
	 * @param string $date_start Start date
	 * @param string $date_end   End date
	 * @return int >0 OK <0 KO
	 */
	public function addActivity($type, $date_start, $date_end)
	{
		global $user;

		if (empty($type) || $type == '-1') {
			$this->error = "ErrNoActivityType";
			return -1;
		}

		// Auto-close the previous activity: set its date_end to new date_start - 1 day (23:59:59)
		if (!empty($date_start)) {
			$sqlPrev = "SELECT rowid FROM ".$this->db->prefix()."workshop_vehicule_activity";
			$sqlPrev .= " WHERE fk_vehicule = ".((int) $this->id);
			$sqlPrev .= " AND date_start < '".$this->db->idate($date_start)."'";
			$sqlPrev .= " ORDER BY date_start DESC LIMIT 1";
			$resqlPrev = $this->db->query($sqlPrev);
			if ($resqlPrev) {
				$objPrev = $this->db->fetch_object($resqlPrev);
				if ($objPrev) {
					$prevAct = new WorkshopVehiculeActivity($this->db);
					if ($prevAct->fetch((int) $objPrev->rowid) > 0) {
						$prevAct->date_end = $date_start - 1; // 23:59:59 of day before new date_start
						$prevAct->update($user);
					}
				}
			}
		}

		$act = new WorkshopVehiculeActivity($this->db);

		$act->fk_vehicule = $this->id;
		$act->fk_type     = $type;
		$act->fk_soc      = $this->fk_soc;
		$act->date_start  = $date_start;
		$act->date_end    = $date_end;

		$retDate = $act->verifyDates();
		if ($retDate) {
			return $act->create($user);
		} else {
			$this->error = $act->error;
			return -1;
		}
	}

	/**
	 * @param int    $act_id     Activity id
	 * @param int    $type       Activity Type
	 * @param string $date_start Start date
	 * @param string $date_end   End date
	 * @param int    $fk_soc     Third party id
	 * @return int >0 OK <0 KO
	 */
	public function updateActivity($act_id, $type, $date_start, $date_end, $fk_soc)
	{
		global $user;

		if (empty($type) || $type == '-1') {
			$this->error = "ErrNoActivityType";
			return -1;
		}

		$act = new WorkshopVehiculeActivity($this->db);
		$result = $act->fetch($act_id);
		if ($result < 0) {
			$this->errors = array_merge($act->errors, array($act->error));
			return $result;
		}

		$act->fk_vehicule = $this->id;
		$act->fk_type     = $type;
		$act->fk_soc      = $fk_soc;
		$act->date_start  = $date_start;
		$act->date_end    = $date_end;

		$retDate = $act->verifyDatesforupdate();
		if ($retDate) {
			return $act->update($user);
		} else {
			$this->error = $act->error;
			return -1;
		}
	}

	public function delActivity($user, $act_id)
	{
		$act = new WorkshopVehiculeActivity($this->db);

		$ret = $act->fetch($act_id);

		if ($act->fk_vehicule != $this->id) {
			$this->error = "IllegalDeletion";
			return -1;
		} else {
			$ret = $act->delete($user);
			if ($ret > 0) {
				return 1;
			} else {
				$this->error = $act->error;
				return -1;
			}
		}
	}

	// -----------------------------------------------------------
	// Linked vehicules management
	// -----------------------------------------------------------

	public function getLinkedVehicules($date_start = '', $date_end = '')
	{
		$this->linkedVehicules = array();
		if (!empty($this->id)) {
			$sql = 'SELECT rowid';
			$sql .= ' FROM '.$this->db->prefix().'workshop_vehicule_link';
			$sql .= " WHERE (fk_source = ".((int) $this->id)." OR fk_target = ".((int) $this->id).")";
			if (!empty($date_end)) {
				$sql .= " AND date_start < '".$this->db->idate($date_end)."'";
			}
			if (!empty($date_start)) {
				$sql .= " AND date_end > '".$this->db->idate($date_start)."'";
			}
			$sql .= " ORDER BY date_start ASC";

			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$Vlink = new WorkshopVehiculeLink($this->db);
					$ret   = $Vlink->fetch($obj->rowid);

					if ($Vlink->fk_source != $this->id) {
						$Vlink->fk_other_vehicule = $Vlink->fk_source;
					} elseif ($Vlink->fk_target != $this->id) {
						$Vlink->fk_other_vehicule = $Vlink->fk_target;
					}

					if ($ret > 0) {
						$this->linkedVehicules[$Vlink->date_start] = $Vlink;
					}
				}
			}
		}
	}

	/**
	 * Add a link between vehicules for a date range
	 *
	 * @param  int    $id         Target vehicule id
	 * @param  string $date_start Start date
	 * @param  string $date_end   End date
	 * @return int                >0 OK, <0 KO
	 */
	public function addLink($id, $date_start, $date_end)
	{
		global $langs, $user;

		$this->vehicules = $this->errors = array();

		$vehiculeToLink = new static($this->db);
		$vehiculeToLink->fetch($id);

		// Auto-close the previous link: set its date_end to new date_start - 1 day (23:59:59)
		if (!empty($date_start)) {
			$sqlPrevLink = "SELECT rowid FROM ".$this->db->prefix()."workshop_vehicule_link";
			$sqlPrevLink .= " WHERE (fk_source = ".((int) $this->id)." OR fk_target = ".((int) $this->id).")";
			$sqlPrevLink .= " AND date_start < '".$this->db->idate($date_start)."'";
			$sqlPrevLink .= " ORDER BY date_start DESC LIMIT 1";
			$resqlPrevLink = $this->db->query($sqlPrevLink);
			if ($resqlPrevLink) {
				$objPrevLink = $this->db->fetch_object($resqlPrevLink);
				if ($objPrevLink) {
					$prevLink = new WorkshopVehiculeLink($this->db);
					if ($prevLink->fetch((int) $objPrevLink->rowid) > 0) {
						$prevLink->date_end = $date_start - 1; // 23:59:59 of day before new date_start
						$prevLink->update($user);
					}
				}
			}
		}

		$this->getLinkedVehicules($date_start, $date_end);
		if (!empty($this->linkedVehicules)) {
			foreach ($this->linkedVehicules as $v) {
				if (!in_array($v->fk_other_vehicule, array_keys($this->vehicules))) {
					$veh = new Vehicule($this->db);
					$veh->fetch($v->fk_other_vehicule);
					$this->vehicules[$v->fk_other_vehicule] = $veh;
				}

				$this->errors[] = $langs->trans(
					"ErrVehiculeAlreadyLinkedDates",
					'',
					html_entity_decode($this->vehicules[$v->fk_other_vehicule]->getLinkUrl(0, '', 'immatriculation')),
					dol_print_date($v->date_start, "%d/%m/%Y"),
					dol_print_date($v->date_end, "%d/%m/%Y")
				);
			}
			unset($v);
		}

		$vehiculeToLink->getLinkedVehicules($date_start, $date_end);
		if (!empty($vehiculeToLink->linkedVehicules)) {
			foreach ($vehiculeToLink->linkedVehicules as $v) {
				if (!in_array($v->fk_other_vehicule, array_keys($this->vehicules))) {
					$veh = new Vehicule($this->db);
					$veh->fetch($v->fk_other_vehicule);
					$this->vehicules[$v->fk_other_vehicule] = $veh;
				}

				$this->errors[] = $langs->trans(
					"ErrVehiculeAlreadyLinkedDates",
					html_entity_decode($vehiculeToLink->getLinkUrl(0, '', 'immatriculation')),
					html_entity_decode($this->vehicules[$v->fk_other_vehicule]->getLinkUrl(0, '', 'immatriculation')),
					dol_print_date($v->date_start, "%d/%m/%Y"),
					dol_print_date($v->date_end, "%d/%m/%Y")
				);
			}
		}

		if ($this->fk_soc != $vehiculeToLink->fk_soc) {
			$this->errors[] = $langs->trans('ErrVehiculeThirPartiesAreDifferent');
		}

		if (!empty($this->errors)) {
			return -1;
		} else {
			$Vlink = new WorkshopVehiculeLink($this->db);
			$Vlink->fk_source              = $this->id;
			$Vlink->fk_soc_vehicule_source = $this->fk_soc;
			$Vlink->fk_target              = $id;
			$Vlink->fk_soc_vehicule_target = $vehiculeToLink->fk_soc;
			$Vlink->date_start             = $date_start;
			$Vlink->date_end               = $date_end;

			$ret = $Vlink->create($user);
			if ($ret < 0) {
				$this->errors[] = $Vlink->error;
				return -2;
			}
		}

		return 1;
	}

	public function delLink($id)
	{
		global $user;

		$link = new WorkshopVehiculeLink($this->db);

		$ret = $link->fetch($id);

		if ($ret > 0 && $link->fk_source != $this->id && $link->fk_target != $this->id) {
			$this->errors[] = "IllegalDeletion";
			return -1;
		}

		$ret = $link->delete($user);
		if ($ret > 0) {
			return 1;
		} else {
			$this->errors[] = $link->error;
			return -1;
		}
	}

	// -----------------------------------------------------------
	// Operations management
	// -----------------------------------------------------------

	/**
	 * @return false|int
	 */
	public function getOperations()
	{
		$this->operations = array();

		$sql = "SELECT rowid FROM ".$this->db->prefix().$this->table_element."_operation";
		$sql .= " WHERE fk_vehicule = ".((int) $this->id);
		$sql .= " ORDER BY rang ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			if ($num) {
				while ($obj = $this->db->fetch_object($resql)) {
					$ope = new WorkshopVehiculeOperation($this->db);
					$ret = $ope->fetch($obj->rowid);
					if ($ret >= 0) {
						$this->operations[] = $ope;
					} else {
						$this->error = $ope->error;
						return $ret;
					}
				}
			}

			return $num;
		} else {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * @param int $maintenanceOperationId VehiculeMaintenanceOperation id
	 * @param int $km                     Km interval
	 * @param int $delayInMonths          Delay in months
	 * @param int $dateDone               Last done date
	 * @param int $kmDone                 Last done km
	 * @return int
	 */
	public function addOperation($maintenanceOperationId, $km = 0, $delayInMonths = 0, $dateDone = 0, $kmDone = 0)
	{
		global $user;

		$ope = new WorkshopVehiculeOperation($this->db);

		$ope->fk_vehicule               = $this->id;
		$ope->fk_maintenance_operation  = $maintenanceOperationId;
		$ope->km                        = $km;
		$ope->delai_from_last_op        = $delayInMonths;
		$ope->km_done                   = $kmDone;
		$ope->date_done                 = $dateDone;

		$ret = $ope->create($user);
		if ($ret < 0) {
			$this->errors = array_merge($ope->errors, array($ope->error));
			return -1;
		}

		return $ret;
	}

	/**
	 * @param int $ope_id Operation id
	 * @return int
	 */
	public function delOperation($ope_id)
	{
		global $user;

		$ope = new WorkshopVehiculeOperation($this->db);
		$ope->fetch($ope_id);

		if ($ope->fk_vehicule != $this->id) {
			$this->errors[] = "IllegalDeletion";
			return -1;
		}

		$ret = $ope->delete($user);
		if ($ret < 0) {
			$this->errors = array_merge($ope->errors, array($ope->error));
			return -2;
		}

		return 1;
	}

	public function updateOperation($ope_id, $maintenanceOperationId, $km = 0, $delayInMonths = 0, $dateDone = 0, $kmDone = 0)
	{
		global $user;

		$ope    = new WorkshopVehiculeOperation($this->db);
		$result = $ope->fetch($ope_id);
		if ($result < 0) {
			$this->errors = array_merge($ope->errors, array($ope->error));
			return $result;
		}

		$ope->fk_vehicule              = $this->id;
		$ope->fk_maintenance_operation = $maintenanceOperationId;
		$ope->km                       = $km;
		$ope->delai_from_last_op       = $delayInMonths;
		$ope->km_done                  = $kmDone;
		$ope->date_done                = $dateDone;

		$ret = $ope->update($user);
		if ($ret < 0) {
			$this->errors = array_merge($ope->errors, array($ope->error));
			return -1;
		}

		return $ret;
	}

	// -----------------------------------------------------------
	// Display methods
	// -----------------------------------------------------------

	/**
	 * Build the HTML tooltip label for this vehicule (used by getNomUrl/getLinkUrl).
	 *
	 * @return string  HTML tooltip content
	 */
	private function buildTooltipLabel(): string
	{
		global $langs, $db;

		$label = '<u>'.$langs->trans("ShowVehicule").'</u>';
		if (!empty($this->vin)) {
			$label .= '<br><b>'.$langs->trans('VIN').':</b> '.$this->vin;
		}
		if (!empty($this->immatriculation)) {
			$label .= '<br><b>'.$langs->trans('immatriculation').':</b> '.$this->immatriculation;
		}

		$dict   = new VehiculeMark($db);
		$label .= '<br><b>'.$langs->trans('vehiculeMark').':</b> '.$dict->getValueFromId($this->fk_vehicule_mark);

		$dict   = new VehiculeType($db);
		$label .= '<br><b>'.$langs->trans('vehiculeType').':</b> '.$dict->getValueFromId($this->fk_vehicule_type);

		$this->fetch_thirdparty();
		if (!empty($this->thirdparty)) {
			$label .= '<br><b>'.$langs->trans('ThirdParty').':</b> '.$this->thirdparty->name;
		}

		return $label;
	}

	/**
	 * Return a link to the object card (with eventually picto)
	 *
	 * @param  int    $withpicto  Add picto into link (0=No, 1=With picto, 2=Only picto)
	 * @param  string $moreparams Add more parameters in the URL
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $moreparams = '')
	{
		$label = $this->buildTooltipLabel();

		$linkclose = '" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
		$link      = '<a href="'.dol_buildpath('/workshop/vehicule/vehicule_card.php', 1).'?id='.$this->id.urlencode($moreparams).$linkclose;
		$linkend   = '</a>';

		$result = '';
		if ($withpicto) {
			$result .= $link.img_object($label, 'fa-truck', 'class="classfortooltip"').$linkend;
		}
		if ($withpicto && $withpicto != 2) {
			$result .= ' ';
		}
		$result .= $link.$this->immatriculation.' - '.$this->vin.$linkend;

		return $result;
	}

	/**
	 * Alias of getNomUrl — kept for backward compatibility.
	 *
	 * @param  int    $withpicto       Add picto into link
	 * @param  string $moreparams      Add more parameters in the URL
	 * @param  string $fieldtodisplay  Unused (kept for signature compat)
	 * @return string
	 */
	public function getLinkUrl($withpicto = 0, $moreparams = '', $fieldtodisplay = 'immatriculation,vin')
	{
		return $this->getNomUrl($withpicto, $moreparams);
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

		$labelStatus      = array();
		$labelStatusShort = array();

		$labelStatus[self::STATUS_DRAFT]  = $langs->transnoentitiesnoconv('VehiculeStatusDraft');
		$labelStatus[self::STATUS_ACTIVE] = $langs->transnoentitiesnoconv('VehiculeStatusActivated');

		$labelStatusShort[self::STATUS_DRAFT]  = $langs->transnoentitiesnoconv('VehiculeStatusShortDraft');
		$labelStatusShort[self::STATUS_ACTIVE] = $langs->transnoentitiesnoconv('VehiculeStatusShortValidate');

		$statusType = 'status0';
		if ($status == self::STATUS_ACTIVE) {
			$statusType = 'status4';
		}

		return dolGetStatus($labelStatus[$status], $labelStatusShort[$status], '', $statusType, $mode);
	}

	public function printbuttons_or()
	{
		global $langs;
		$langs->load('workshop@workshop');

		$nb     = $this->countordertoplan('all');
		$nblate = $this->countordertoplan('late');

		if ($nblate > 0) {
			$class = 'class="badge  badge-danger classfortooltip"';
		} else {
			$class = 'class="badge  badge-success classfortooltip"';
		}

		$out = '<a href="javascript:elementtoplan()" '.$class.'">'.$langs->trans('OperationOrderToCreate').': '.$nb.'</a>';

		return $out;
	}

	public function countordertoplan($mode = 'all')
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

		$sql = "SELECT COUNT(op.rowid) as nbop FROM ".$this->db->prefix()."workshop_vehicule_operation as op";
		$sql .= " WHERE op.fk_vehicule=".((int) $this->id);

		if ($mode == 'late') {
			$sql .= " AND op.on_time = 1";
		}
		$sql .= " AND (op.or_next IS NULL OR op.or_next=0) ";
		$sql .= " AND op.date_next IS NOT NULL";
		$sql .= " AND op.date_next < '".$this->db->idate(dol_time_plus_duree(dol_now(), (int) getDolGlobalInt('THEO_NB_MONTH_CHECKING_VEHICULE_BY_ANTICIPATION'), 'm'))."'";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			if ($num > 0) {
				$obj = $this->db->fetch_object($resql);
				return $obj->nbop;
			}
		} else {
			setEventMessages($this->db->lasterror(), null, 'errors');
			return 0;
		}

		return 0;
	}

	public function getorlinkedHV()
	{
		$out = 'Pas de véhicule lié';
		$sql = 'SELECT IF(fk_target = '.((int) $this->id).',fk_source,fk_target) as linked FROM '.$this->db->prefix().'workshop_vehicule_link ';
		$sql .= 'WHERE (fk_source = '.((int) $this->id).' OR fk_target = '.((int) $this->id).') ORDER BY date_start DESC';
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			if ($num > 0) {
				$obj = $this->db->fetch_object($resql);
				$vh  = new Vehicule($this->db);
				$ret = $vh->fetch($obj->linked);
				if ($ret > 0) {
					$out = $vh->getNomUrl(1);
				}
			}
		}

		return $out;
	}

//	public function getBanner($linkback='',$morehtmlref='') {
//		return dol_banner_tab($this, 'vin', $linkback, 1, 'vin', 'vin', $morehtmlref, '', 0, '', '');
//	}
}
