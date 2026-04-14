<?php
/* Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/operationorder.class.php
 * \ingroup     workshop
 * \brief       This file is a CRUD class file for Operationorder (Create/Read/Update/Delete)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/workshop/class/workshopoperationorderstatus.class.php');

/**
 * Class for Operationorder (Ordre de Réparation)
 */
class Operationorder extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'workshop';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'operationorder';

	/**
	 * @var string Name of table without prefix where object is stored.
	 */
	public $table_element = 'workshop_operationorder';

	/**
	 * @var string Name of the child table for lines (jobs).
	 */
	public $table_element_line = 'workshop_operationorder_jobs';

	/**
	 * @var string Class name for child lines.
	 */
	public $class_element_line = 'Operationorder_jobs';

	/**
	 * @var string Path to child line class file.
	 */
	public $childtablesql = 'workshop_operationorder_jobs';

	/**
	 * @var string Icon for Operationorder.
	 */
	public $picto = 'fa-tools';

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array Array with all fields and their property.
	 */
	const STATUS_DRAFT     = 0;
	const STATUS_OPEN      = 1;
	const STATUS_DONE      = 2;
	const STATUS_CLOSED    = 3;
	const STATUS_CANCELLED = -1;

	public $fields = array(
		'rowid'          => array('type' => 'integer',      'label' => 'TechnicalID',    'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0),
		'ref'            => array('type' => 'varchar(255)',  'label' => 'Ref',            'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'validate' => 1),
		'entity'         => array('type' => 'integer',      'label' => 'Entity',         'enabled' => 1, 'position' => 905, 'notnull' => 1, 'visible' => -2, 'default' => 1, 'index' => 1),
		'ref_client'     => array('type' => 'varchar(255)',  'label' => 'RefClient',      'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 1, 'searchall' => 1),
		'fk_soc'         => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'picto' => 'company', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150', 'showoncombobox' => 1),
		'fk_vehicule'    => array('type' => 'integer:Vehicule:workshop/class/Vehicule.class.php', 'label' => 'Vehicule', 'picto' => 'fa-truck', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'fk_conducteur'  => array('type' => 'integer:Conducteur:workshop/class/Conducteur.class.php', 'label' => 'Conducteur', 'picto' => 'fa-id-card', 'enabled' => 1, 'position' => 45, 'notnull' => 0, 'visible' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'km'             => array('type' => 'double',        'label' => 'Km',             'enabled' => 1, 'position' => 50,  'notnull' => 0, 'visible' => 1),
		'date_planned'   => array('type' => 'datetime',      'label' => 'DatePlanned',    'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 1),
		'date_valid'     => array('type' => 'datetime',      'label' => 'DateOR',         'enabled' => 1, 'position' => 70,  'notnull' => 0, 'visible' => 1),
		'date_start'     => array('type' => 'datetime',      'label' => 'DateStart',      'enabled' => 1, 'position' => 80,  'notnull' => 0, 'visible' => -1),
		'date_end'       => array('type' => 'datetime',      'label' => 'DateEnd',        'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => -1),
		'fk_user_assign' => array('type' => 'integer:User:user/class/user.class.php',     'label' => 'Mechanic',       'picto' => 'user', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1, 'csslist' => 'tdoverflowmax150'),
		'total_ht'       => array('type' => 'double',        'label' => 'TotalHT',        'enabled' => 1, 'position' => 200, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'total_ht_part'  => array('type' => 'double',        'label' => 'TotalHTPart',    'enabled' => 1, 'position' => 210, 'notnull' => 1, 'visible' => -1),
		'total_ht_mo'    => array('type' => 'double',        'label' => 'TotalHTMO',      'enabled' => 1, 'position' => 220, 'notnull' => 1, 'visible' => -1),
		'total_ht_service' => array('type' => 'double',      'label' => 'TotalHTService', 'enabled' => 1, 'position' => 230, 'notnull' => 1, 'visible' => -1),
		'total_ht_external' => array('type' => 'double',     'label' => 'TotalHTExternal', 'enabled' => 1, 'position' => 240, 'notnull' => 1, 'visible' => -1),
		'total_ht_refund' => array('type' => 'double', 'label' => 'TotalHTRefund', 'enabled' => 1, 'position' => 250, 'notnull' => 1, 'visible' => -1),
		'temps_immobilisation' => array('type' => 'double', 'label' => 'TempsImmobilisationTheo', 'enabled' => 1, 'position' => 260, 'notnull' => 0, 'visible' => -1),
		'check_or'       => array('type' => 'integer', 'label' => 'CheckOR', 'enabled' => 1, 'position' => 270, 'notnull' => 0, 'visible' => -1,
			'arrayofkeyval' => array(
				0 => 'CheckORNonVerifie',
				1 => 'CheckOREnCours',
				2 => 'CheckORVerifieOK',
				3 => 'CheckORVerifieReserves',
			),
		),
		'note_public'    => array('type' => 'html',          'label' => 'NotePublic',     'enabled' => 1, 'position' => 300, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'note_private'   => array('type' => 'html',          'label' => 'NotePrivate',    'enabled' => 1, 'position' => 310, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'model_pdf'      => array('type' => 'varchar(255)',   'label' => 'ModelPdf',       'enabled' => 1, 'position' => 400, 'notnull' => 0, 'visible' => 0),
		'last_main_doc'  => array('type' => 'varchar(255)',   'label' => 'LastMainDoc',    'enabled' => 1, 'position' => 410, 'notnull' => 0, 'visible' => 0),
		'fk_user_creat'  => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid', 'csslist' => 'tdoverflowmax150'),
		'fk_user_modif'  => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'fk_user_valid'  => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserValid',  'picto' => 'user', 'enabled' => 1, 'position' => 512, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'date_creation'  => array('type' => 'datetime',      'label' => 'DateCreation',   'enabled' => 1, 'position' => 520, 'notnull' => 0, 'visible' => -2),
		'tms'            => array('type' => 'timestamp',     'label' => 'DateModification', 'enabled' => 1, 'position' => 530, 'notnull' => 1, 'visible' => -2),
		'import_key'     => array('type' => 'varchar(255)',   'label' => 'ImportId',       'enabled' => 1, 'position' => 900, 'notnull' => 0, 'visible' => 0),
		'status'         => array('type' => 'integer',       'label' => 'Status',         'enabled' => 1, 'position' => 1000, 'notnull' => 1, 'visible' => 2, 'index' => 1,
			'csslist' => 'center',
		),
	);

	public $rowid;
	public $ref;
	public $ref_client;
	public $entity;
	public $fk_soc;
	public $fk_vehicule;
	public $fk_conducteur;
	public $km;
	public $date_planned;
	public $date_valid;
	public $date_start;
	public $date_end;
	public $fk_user_assign;
	public $total_ht;
	public $total_ht_part;
	public $total_ht_mo;
	public $total_ht_service;
	public $total_ht_external;
	public $total_ht_refund;
	public $note_public;
	public $note_private;
	public $model_pdf;
	public $last_main_doc;
	public $fk_user_creat;
	public $fk_user_modif;
	public $fk_user_valid;
	public $date_creation;
	public $tms;
	public $import_key;
	public $status;
	public $temps_immobilisation;
	public $check_or;

	/**
	 * @var Operationorder_jobs[] Array of job lines
	 */
	public $lines = array();

	/**
	 * Objet statut dynamique chargé depuis llx_workshop_operationorder_status.
	 * Utilisé pour les contrôles de droits et l'affichage.
	 * @var WorkshopOperationOrderStatus|null
	 */
	public $objStatus = null;

	// END MODULEBUILDER PROPERTIES

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	/** @var array Label status mapping */
	public $labelStatus = array(
		self::STATUS_CANCELLED => 'Cancelled',
		self::STATUS_DRAFT     => 'Draft',
		self::STATUS_OPEN      => 'Open',
		self::STATUS_DONE      => 'Done',
		self::STATUS_CLOSED    => 'Closed',
	);

	/** @var array CSS badge type per status (dolGetStatus type) */
	public $labelStatusShort = array(
		self::STATUS_CANCELLED => 'status9',
		self::STATUS_DRAFT     => 'status0',
		self::STATUS_OPEN      => 'status4',
		self::STATUS_DONE      => 'status6',
		self::STATUS_CLOSED    => 'status6',
	);

	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;
		$this->ismultientitymanaged = 1;
		$this->isextrafieldmanaged = 1;

		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid']) && !empty($this->fields['ref'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}

		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Return next reference for this object (used by createCommon numbering logic).
	 *
	 * @return string  Next reference string, or '' on error
	 */
	public function getNextNumRef()
	{
		$modName = getDolGlobalString('WORKSHOP_OR_ADDON', 'mod_workshopor_standard');
		dol_include_once('/workshop/core/modules/workshopor/modules_workshopor.php');
		$modFile = dol_buildpath('/workshop/core/modules/workshopor/'.$modName.'.php', 0);
		if (!file_exists($modFile)) {
			$this->error = 'BadDefinedNumRef';
			return '';
		}
		require_once $modFile;
		$obj = new $modName();
		return $obj->getNextValue($this);
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
	 * Clone an object into another one
	 *
	 * @param  User $user   User that creates
	 * @param  int  $fromid Id of object to clone
	 * @return mixed        New object created, <0 if KO
	 */
	public function createFromClone(User $user, $fromid)
	{
		global $langs, $extrafields;
		$error = 0;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$object = new self($this->db);

		$this->db->begin();

		$result = $object->fetchCommon($fromid);
		if ($result > 0 && !empty($object->table_element_line)) {
			$object->fetchLines();
		}

		unset($object->id);
		unset($object->fk_user_creat);
		unset($object->import_key);

		if (property_exists($object, 'ref')) {
			$object->ref = empty($this->fields['ref']['default']) ? 'Copy_Of_'.$object->ref : $this->fields['ref']['default'];
		}
		if (property_exists($object, 'date_creation')) {
			$object->date_creation = dol_now();
		}
		if (property_exists($object, 'date_valid')) {
			$object->date_valid = null;
		}

		if (is_array($object->array_options) && count($object->array_options) > 0) {
			$extrafields->fetch_name_optionals_label($this->table_element);
			foreach ($object->array_options as $key => $option) {
				$shortkey = preg_replace('/options_/', '', $key);
				if (!empty($extrafields->attributes[$this->table_element]['unique'][$shortkey])) {
					unset($object->array_options[$key]);
				}
			}
		}

		$object->context['createfromclone'] = 'createfromclone';
		$result = $object->createCommon($user);
		if ($result < 0) {
			$error++;
			$this->setErrorsFromObject($object);
		}

		unset($object->context['createfromclone']);

		if (!$error) {
			$this->db->commit();
			return $object;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param  int    $id            Id object
	 * @param  string $ref           Ref
	 * @param  int    $noextrafields 0=Default, 1=No extrafields
	 * @param  int    $nolines       0=Default, 1=No lines
	 * @return int                   Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
	{
		$result = $this->fetchCommon($id, $ref, '', $noextrafields);
		if ($result > 0 && !$nolines) {
			$this->fetchLines();
		}
		return $result;
	}

	/**
	 * Load object lines in memory from the database
	 *
	 * @return int Return integer <0 if KO, >0 if OK
	 */
	public function fetchLines()
	{
		$this->lines = array();

		dol_include_once('/workshop/class/operationorder_jobs.class.php');

		$sql  = 'SELECT rowid FROM '.$this->db->prefix().'workshop_operationorder_jobs';
		$sql .= ' WHERE fk_operationorder = '.((int) $this->id);
		$sql .= ' ORDER BY rang ASC, rowid ASC';

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$line = new Operationorder_jobs($this->db);
				$result = $line->fetch($obj->rowid);
				if ($result > 0) {
					$this->lines[] = $line;
				}
			}
			$this->db->free($resql);
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Load list of objects in memory from the database.
	 *
	 * @param  string $sortorder  Sort Order
	 * @param  string $sortfield  Sort field
	 * @param  int    $limit      Limit
	 * @param  int    $offset     Offset
	 * @param  string $filter     Universal Search filter string
	 * @param  string $filtermode No more used
	 * @return array|int          int <0 if KO, array if OK
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 100, $offset = 0, string $filter = '', $filtermode = 'AND')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql  = 'SELECT ';
		$sql .= $this->getFieldList('t');
		$sql .= ' FROM '.$this->db->prefix().$this->table_element.' as t';
		if ($this->isextrafieldmanaged == 1) {
			$sql .= ' LEFT JOIN '.$this->db->prefix().$this->table_element.'_extrafields as te ON te.fk_object = t.rowid';
		}
		if ($this->ismultientitymanaged == 1) {
			$sql .= ' WHERE t.entity IN ('.getEntity($this->element).')';
		} else {
			$sql .= ' WHERE 1 = 1';
		}

		$errormessage = '';
		$sql .= forgeSQLFromUniversalSearchCriteria($filter, $errormessage);
		if ($errormessage) {
			$this->errors[] = $errormessage;
			dol_syslog(__METHOD__.' '.implode(',', $this->errors), LOG_ERR);
			return -1;
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i   = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj    = $this->db->fetch_object($resql);
				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);
				$records[$record->id] = $record;
				$i++;
			}
			$this->db->free($resql);
			return $records;
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			dol_syslog(__METHOD__.' '.implode(',', $this->errors), LOG_ERR);
			return -1;
		}
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
	 * @param User $user      User that deletes
	 * @param int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int            Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Generate PDF document for this Repair Order
	 *
	 * @param  string    $modele      Model name (default: 'standard_or')
	 * @param  Translate $outputlangs Output language object
	 * @param  int       $hidedetails Do not show line details
	 * @param  int       $hidedesc    Do not show descriptions
	 * @param  int       $hideref     Do not show references
	 * @param  array     $moreparams  Extra parameters
	 * @return int                    1 if OK, <=0 if KO
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $langs;

		$outputlangs->loadLangs(array('workshop@workshop'));

		if (!dol_strlen($modele)) {
			$modele = 'standard_or';
			if (!empty($this->model_pdf)) {
				$modele = $this->model_pdf;
			}
		}

		$modelpath = 'workshop/core/modules/workshopor/doc/';

		return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
	}

	/**
	 * Recalculate totals from jobs and their detail lines
	 *
	 * @param  User $user User performing the update
	 * @return int        Return integer <0 if KO, >0 if OK
	 */
	public function updateTotals(User $user)
	{
		$sql  = 'SELECT';
		$sql .= '  COALESCE(SUM(j.total_ht), 0)        AS total_ht,';
		$sql .= '  COALESCE(SUM(j.total_ht_part), 0)   AS total_ht_part,';
		$sql .= '  COALESCE(SUM(j.total_ht_mo), 0)     AS total_ht_mo,';
		$sql .= '  COALESCE(SUM(j.total_ht_service), 0) AS total_ht_service,';
		$sql .= '  COALESCE(SUM(j.total_ht_external), 0) AS total_ht_external,';
		$sql .= '  COALESCE(SUM(j.total_ht_refund), 0)  AS total_ht_refund';
		$sql .= ' FROM '.$this->db->prefix().'workshop_operationorder_jobs as j';
		$sql .= ' WHERE j.fk_operationorder = '.((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$this->total_ht          = $obj->total_ht;
			$this->total_ht_part     = $obj->total_ht_part;
			$this->total_ht_mo       = $obj->total_ht_mo;
			$this->total_ht_service  = $obj->total_ht_service;
			$this->total_ht_external = $obj->total_ht_external;
			$this->total_ht_refund   = $obj->total_ht_refund;
			$this->db->free($resql);
			return $this->update($user, 1);
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Synchronise la remise du tiers sur tous les jobs de cet OR, puis recalcule les totaux.
	 *
	 * Appelé après un changement de tiers (fk_soc) ou de véhicule (qui change le tiers).
	 * Remplace la logique dupliquée dans or_card.php (save_fk_soc / save_fk_vehicule).
	 *
	 * @param  User $user  Utilisateur courant
	 * @return int         1 si OK ou rien à faire, <0 si erreur
	 */
	public function syncThirdPartyDiscount(User $user): int
	{
		if ($this->fk_soc <= 0) {
			return 1;
		}

		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$socObj = new Societe($this->db);
		if ($socObj->fetch($this->fk_soc) <= 0) {
			return 1;
		}

		dol_include_once('/workshop/class/operationorder_jobs.class.php');
		$jobsObj = new Operationorder_jobs($this->db);
		$res = $jobsObj->updateRemiseForOR($this->id, (float) $socObj->remise_percent, $user);
		if ($res < 0) {
			$this->error = $jobsObj->error;
			$this->errors = $jobsObj->errors;
			return -1;
		}

		return $this->updateTotals($user);
	}

	/**
	 * Return a link to the object card (with optionally the picto)
	 *
	 * @param  int    $withpicto             Include picto in link (0=No, 1=With picto, 2=Only picto)
	 * @param  string $option                On what the link point to
	 * @param  int    $notooltip             1=Disable tooltip
	 * @param  string $morecss               Additional CSS
	 * @param  int    $save_lastsearch_value Save last search values
	 * @return string                        String with URL
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs, $hookmanager;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1;
		}

		$result = '';
		$params = array(
			'id'    => $this->id,
			'objecttype' => $this->element,
			'option' => $option,
		);
		$classfortooltip = 'classfortooltip';
		$dataparams = '';
		if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
			$classfortooltip = 'classforajaxtooltip';
			$dataparams = ' data-params="'.dol_escape_htmltag(json_encode($params)).'"';
			$label = '';
		} else {
			$label = implode($this->getTooltipContentArray($params));
		}

		$url =dol_buildpath('/workshop/operationorder/or_card.php',1) . '?id='.$this->id;

		if ($option != 'nolink') {
			$linkclose = '';
			if (empty($notooltip)) {
				if (!empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER)) {
					$label = $langs->trans("ShowOperationorder");
					$linkclose = ' alt="'.dol_escape_htmltag($label, 1).'"';
				}
				$linkclose .= ' title="'.dol_escape_htmltag($label, 1).'"';
				$linkclose .= ' class="'.$classfortooltip.($morecss ? ' '.$morecss : '').'"';
				$linkclose .= $dataparams;
			} else {
				$linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
			}

			if ($save_lastsearch_value == -1 && preg_match('/list\.php/', $_SERVER['PHP_SELF'])) {
				$save_lastsearch_value = 1;
			}
			if ($save_lastsearch_value == 1) {
				$url .= '&save_lastsearch_values=1';
			}

			$linkstart  = '<a href="'.$url.'"';
			$linkstart .= $linkclose.'>';
			$linkend     = '</a>';

			$result .= $linkstart;
			if ($withpicto) {
				$result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), ($notooltip ? (($withpicto != 2) ? 'class="paddingright"' : '') : $dataparams.' class="'.(($withpicto != 2) ? 'paddingright ' : '').$classfortooltip.'"'), 0, 0, $notooltip ? 0 : 1);
			}
			if ($withpicto != 2) {
				$result .= $this->ref;
			}
			$result .= $linkend;
		} else {
			$result .= $this->ref;
		}

		return $result;
	}

	/**
	 * getTooltipContentArray
	 *
	 * @param  array $params Params to construct tooltip data
	 * @return array
	 */
	public function getTooltipContentArray($params)
	{
		global $langs;

		$datas = array();

		if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
			return array('optimize' => $langs->trans('ShowOperationorder'));
		}

		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans('Operationorder').'</u>';
		if (!empty($this->ref)) {
			$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
		}
		if (!empty($this->ref_client)) {
			$datas['ref_client'] = '<br><b>'.$langs->trans('RefClient').':</b> '.$this->ref_client;
		}

		return $datas;
	}

	/**
	 * Charger l'objet statut dynamique depuis la base si ce n'est pas déjà fait,
	 * ou si le statut a changé.
	 *
	 * @param  bool $forceReload Forcer le rechargement même si déjà en cache
	 * @return WorkshopOperationOrderStatus|null
	 */
	public function loadStatus($forceReload = false)
	{
		if ($this->status <= 0) {
			$this->objStatus = null;
			return null;
		}

		if ($forceReload || $this->objStatus === null || (int) $this->objStatus->id !== (int) $this->status) {
			$this->objStatus = new WorkshopOperationOrderStatus($this->db);
			$res = $this->objStatus->fetch((int) $this->status);
			if ($res <= 0) {
				$this->objStatus = null;
			}
		}

		return $this->objStatus;
	}

	/**
	 * Vérifier si l'utilisateur peut effectuer une action sur cet OR en fonction du statut courant.
	 *
	 * @param  User   $user   Utilisateur
	 * @param  string $action Action : 'read', 'edit', 'changeToThisStatus'
	 * @return bool
	 */
	public function userCan(User $user, $action = 'edit')
	{
		$objStatus = $this->loadStatus();
		if ($objStatus === null) {
			return true; // Pas de statut défini : pas de restriction
		}
		return $objStatus->userCan($user, $action);
	}

	/**
	 * Passer l'ordre de réparation à un nouveau statut dynamique.
	 *
	 * Vérifie :
	 * 1. Que la transition est autorisée (définie dans llx_workshop_operationorder_status_target)
	 * 2. Que l'utilisateur a le droit "changeToThisStatus" pour le nouveau statut dans l'entité courante
	 *
	 * @param  User $user        Utilisateur
	 * @param  int  $fk_status   Rowid du nouveau statut (WorkshopOperationOrderStatus)
	 * @param  int  $notrigger   Désactiver les triggers
	 * @return int               >0 si OK, <0 si KO
	 */
	public function setStatus(User $user, $fk_status, $notrigger = 0)
	{
		$fk_status = (int) $fk_status;

		if ($fk_status <= 0) {
			$this->error = 'InvalidStatusId';
			return -1;
		}

		if ((int) $this->status === $fk_status) {
			return 1; // Déjà à ce statut
		}

		// Charger le nouveau statut et vérifier les droits
		$newStatus = new WorkshopOperationOrderStatus($this->db);
		$res       = $newStatus->fetch($fk_status);
		if ($res <= 0) {
			$this->error = 'WorkshopORStatusNotFound';
			return -2;
		}

		if (!$newStatus->userCan($user, 'changeToThisStatus')) {
			$this->error = 'WorkshopORStatusForbiddenChangeToThisStatus';
			return -3;
		}

		// Vérifier que la transition est autorisée depuis le statut courant
		$currentStatus = $this->loadStatus();
		if ($currentStatus !== null && !$currentStatus->checkStatusTransition($user, $fk_status)) {
			$this->error = 'WorkshopORStatusTransitionNotAllowed';
			return -4;
		}

		// Enregistrer le nouveau statut
		$sql  = 'UPDATE ' . $this->db->prefix() . $this->table_element;
		$sql .= ' SET status = ' . $fk_status;
		$sql .= ' WHERE rowid = ' . (int) $this->id;

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -5;
		}

		$this->status    = $fk_status;
		$this->objStatus = $newStatus;

		return 1;
	}

	/**
	 * Return label of the current status badge
	 *
	 * @param  int $mode 0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Return the badge HTML for a given status rowid.
	 * Uses WorkshopOperationOrderStatus when status > 0.
	 *
	 * @param  int $status Status rowid (llx_workshop_operationorder_status)
	 * @param  int $mode   Display mode (used if status is 0 or unknown)
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		if ($status > 0) {
			$objStatus = new WorkshopOperationOrderStatus($this->db);
			$res       = $objStatus->fetch((int) $status, false);
			if ($res > 0) {
				return $objStatus->getBadge($objStatus->getCardUrl());
			}
		}

		// Fallback : statut non défini ou non trouvé
		global $langs;
		$langs->load('workshop@workshop');
		return dolGetStatus($langs->trans('WorkshopORStatusUndefined'), '', '', 'status0', $mode);
	}

	/**
	 * Set status to Open (validated) — kept for backward compatibility with or_card.php
	 * @deprecated Utiliser setStatus($user, $fk_status) avec un statut dynamique
	 */
	public function setOpen(User $user, $notrigger = 0)
	{
		return $this->setStatusCommon($user, self::STATUS_OPEN, $notrigger, 'OPERATIONORDER_OPEN');
	}

	/**
	 * Set status to Done — kept for backward compatibility with or_card.php
	 * @deprecated Utiliser setStatus($user, $fk_status) avec un statut dynamique
	 */
	public function setDone(User $user, $notrigger = 0)
	{
		return $this->setStatusCommon($user, self::STATUS_DONE, $notrigger, 'OPERATIONORDER_DONE');
	}

	/**
	 * Set status to Closed — kept for backward compatibility with or_card.php
	 * @deprecated Utiliser setStatus($user, $fk_status) avec un statut dynamique
	 */
	public function setClosed(User $user, $notrigger = 0)
	{
		return $this->setStatusCommon($user, self::STATUS_CLOSED, $notrigger, 'OPERATIONORDER_CLOSE');
	}

	/**
	 * Set status back to Draft — kept for backward compatibility with or_card.php
	 * @deprecated Utiliser setStatus($user, $fk_status) avec un statut dynamique
	 */
	public function setDraft(User $user, $notrigger = 0)
	{
		return $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'OPERATIONORDER_REOPEN');
	}

	/**
	 * Set status to Cancelled — kept for backward compatibility with or_card.php
	 * @deprecated Utiliser setStatus($user, $fk_status) avec un statut dynamique
	 */
	public function cancel(User $user, $notrigger = 0)
	{
		return $this->setStatusCommon($user, self::STATUS_CANCELLED, $notrigger, 'OPERATIONORDER_CANCEL');
	}

	/**
	 * Load the info information in the object
	 *
	 * @param  int  $id Id of object
	 * @return void
	 */
	public function info($id)
	{
		$sql  = 'SELECT rowid, date_creation as datec, tms as datem, fk_user_creat, fk_user_modif, fk_user_valid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' as t';
		$sql .= ' WHERE t.rowid = '.((int) $id);

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$this->id                  = $obj->rowid;
				$this->user_creation_id    = $obj->fk_user_creat;
				$this->user_modification_id = $obj->fk_user_modif;
				$this->user_validation_id  = $obj->fk_user_valid;
				$this->date_creation       = $this->db->jdate($obj->datec);
				$this->date_modification   = empty($obj->datem) ? '' : $this->db->jdate($obj->datem);
			}
			$this->db->free($result);
		} else {
			dol_print_error($this->db);
		}
	}
}
