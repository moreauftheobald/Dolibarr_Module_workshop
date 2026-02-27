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
 * \file        class/operationorder_jobs.class.php
 * \ingroup     workshop
 * \brief       This file is a CRUD class file for Operationorder_jobs (Create/Read/Update/Delete)
 *              Child of Operationorder, parent of Operationorderdet.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Operationorder_jobs (Travaux/Jobs d'un Ordre de Réparation)
 * Child of Operationorder, parent of Operationorderdet.
 */
class Operationorder_jobs extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'workshop';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'operationorder_jobs';

	/**
	 * @var string Name of table without prefix where object is stored.
	 */
	public $table_element = 'workshop_operationorder_jobs';

	/**
	 * @var string Name of the child table for detail lines.
	 */
	public $table_element_line = 'workshop_operationorderdet';

	/**
	 * @var string Class name for child lines.
	 */
	public $class_element_line = 'Operationorderdet';

	/**
	 * @var string Icon for Operationorder_jobs.
	 */
	public $picto = 'fa-wrench';

	/** Status constants */
	const STATUS_TODO       = 0;
	const STATUS_INPROGRESS = 1;
	const STATUS_DONE       = 2;
	const STATUS_CANCELLED  = 9;

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array Array with all fields and their property.
	 */
	public $fields = array(
		'rowid'             => array('type' => 'integer',     'label' => 'TechnicalID',  'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0),
		'fk_operationorder' => array('type' => 'integer:Operationorder:workshop/class/operationorder.class.php', 'label' => 'OperationOrder', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'label'             => array('type' => 'varchar(255)', 'label' => 'Label',        'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300', 'autofocusoncreate' => 1),
		'description'       => array('type' => 'html',        'label' => 'Description',  'enabled' => 1, 'position' => 30,  'notnull' => 0, 'visible' => 3, 'cssview' => 'wordbreak'),
		'fk_service_type'   => array('type' => 'integer:ServiceType:workshop/class/servicetype.class.php', 'label' => 'ServiceType', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx'),
		'fk_user_assign'    => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AssignedTo', 'picto' => 'user', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'csslist' => 'tdoverflowmax150'),
		'status'            => array('type' => 'integer',     'label' => 'Status',       'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 1, 'default' => self::STATUS_TODO, 'arrayofkeyval' => array(
			self::STATUS_TODO       => 'Todo',
			self::STATUS_INPROGRESS => 'InProgress',
			self::STATUS_DONE       => 'Done',
			self::STATUS_CANCELLED  => 'Cancelled',
		)),
		'rang'              => array('type' => 'integer',     'label' => 'Rang',         'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 0, 'default' => 0),
		'time_planned'      => array('type' => 'duration',    'label' => 'TimePlanned',  'enabled' => 1, 'position' => 70,  'notnull' => 0, 'visible' => 1),
		'time_spent'        => array('type' => 'duration',    'label' => 'TimeSpent',    'enabled' => 1, 'position' => 80,  'notnull' => 0, 'visible' => 1),
		'date_start'        => array('type' => 'datetime',    'label' => 'DateStart',    'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => -1),
		'date_end'          => array('type' => 'datetime',    'label' => 'DateEnd',      'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => -1),
		'total_ht'          => array('type' => 'double',      'label' => 'TotalHT',      'enabled' => 1, 'position' => 200, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'note_public'       => array('type' => 'html',        'label' => 'NotePublic',   'enabled' => 1, 'position' => 300, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'note_private'      => array('type' => 'html',        'label' => 'NotePrivate',  'enabled' => 1, 'position' => 310, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'fk_user_creat'     => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid', 'csslist' => 'tdoverflowmax150'),
		'fk_user_modif'     => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'date_creation'     => array('type' => 'datetime',    'label' => 'DateCreation', 'enabled' => 1, 'position' => 520, 'notnull' => 0, 'visible' => -2),
		'tms'               => array('type' => 'timestamp',   'label' => 'DateModification', 'enabled' => 1, 'position' => 530, 'notnull' => 1, 'visible' => -2),
		'import_key'        => array('type' => 'varchar(255)', 'label' => 'ImportId',    'enabled' => 1, 'position' => 900, 'notnull' => 0, 'visible' => 0),
	);

	public $rowid;
	public $fk_operationorder;
	public $label;
	public $description;
	public $fk_service_type;
	public $fk_user_assign;
	public $status;
	public $rang;
	public $time_planned;
	public $time_spent;
	public $date_start;
	public $date_end;
	public $total_ht;
	public $note_public;
	public $note_private;
	public $fk_user_creat;
	public $fk_user_modif;
	public $date_creation;
	public $tms;
	public $import_key;

	/**
	 * @var Operationorderdet[] Array of detail lines
	 */
	public $lines = array();

	// END MODULEBUILDER PROPERTIES

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;
		$this->ismultientitymanaged = 0;
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
	 * @param  int    $id            Id object
	 * @param  string $ref           Ref (not used here)
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
	 * Load detail lines in memory from the database
	 *
	 * @return int Return integer <0 if KO, >0 if OK
	 */
	public function fetchLines()
	{
		$this->lines = array();

		dol_include_once('/workshop/class/operationorderdet.class.php');

		$sql  = 'SELECT rowid FROM '.$this->db->prefix().'workshop_operationorderdet';
		$sql .= ' WHERE fk_operationorder_jobs = '.((int) $this->id);
		$sql .= ' ORDER BY rang ASC, rowid ASC';

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$line = new Operationorderdet($this->db);
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
		$sql .= ' WHERE 1 = 1';

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
	 * Load all jobs for a given Operationorder
	 *
	 * @param  int    $fk_operationorder Id of the parent Operationorder
	 * @param  string $sortfield         Sort field
	 * @param  string $sortorder         Sort order
	 * @return array|int                 int <0 if KO, array if OK
	 */
	public function fetchAllByOperationorder($fk_operationorder, $sortfield = 'rang', $sortorder = 'ASC')
	{
		$filter = '(fk_operationorder:=:'.((int) $fk_operationorder).')';
		return $this->fetchAll($sortorder, $sortfield, 0, 0, $filter);
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
	 * Set status to InProgress
	 *
	 * @param  User $user      User performing the action
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function setInProgress(User $user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_TODO) {
			$this->error = 'BadStatusForAction';
			return -1;
		}

		$this->status     = self::STATUS_INPROGRESS;
		$this->date_start = dol_now();

		return $this->update($user, $notrigger);
	}

	/**
	 * Set status to Done
	 *
	 * @param  User $user      User performing the action
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function setDone(User $user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_INPROGRESS) {
			$this->error = 'BadStatusForAction';
			return -1;
		}

		$this->status   = self::STATUS_DONE;
		$this->date_end = dol_now();

		return $this->update($user, $notrigger);
	}

	/**
	 * Recalculate total from detail lines
	 *
	 * @param  User $user User performing the update
	 * @return int        Return integer <0 if KO, >0 if OK
	 */
	public function updateTotals(User $user)
	{
		$sql  = 'SELECT COALESCE(SUM(total_ht), 0) AS total_ht';
		$sql .= ' FROM '.$this->db->prefix().'workshop_operationorderdet';
		$sql .= ' WHERE fk_operationorder_jobs = '.((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj             = $this->db->fetch_object($resql);
			$this->total_ht  = $obj->total_ht;
			$this->db->free($resql);
			return $this->update($user, 1);
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
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
		global $langs;

		$result = '';
		if ($withpicto) {
			$result .= img_picto('', $this->picto, 'class="paddingright"');
		}
		$result .= dol_escape_htmltag($this->label);

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
			return array('optimize' => $langs->trans('ShowOperationorderJobs'));
		}

		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans('OperationorderJobs').'</u>';
		if (!empty($this->label)) {
			$datas['label'] = '<br><b>'.$langs->trans('Label').':</b> '.$this->label;
		}
		if (isset($this->status)) {
			$datas['status'] = '<br><b>'.$langs->trans('Status').':</b> '.$this->getLibStatut(5);
		}

		return $datas;
	}

	/**
	 * Return label of status
	 *
	 * @param  int    $mode 0=Short label, 1=Long label, 5=Short label with picto, 6=Long label with picto
	 * @return string       Label
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Return label of a given status
	 *
	 * @param  int    $status Status
	 * @param  int    $mode   0=Short, 1=Long, 5=Short+picto, 6=Long+picto
	 * @return string         Label
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$statusLabel = array(
			self::STATUS_TODO       => array('label' => 'Todo',       'picto' => 'status0'),
			self::STATUS_INPROGRESS => array('label' => 'InProgress', 'picto' => 'status4'),
			self::STATUS_DONE       => array('label' => 'Done',       'picto' => 'status6'),
			self::STATUS_CANCELLED  => array('label' => 'Cancelled',  'picto' => 'status9'),
		);

		$infos = isset($statusLabel[$status]) ? $statusLabel[$status] : array('label' => 'Unknown', 'picto' => 'status0');

		if ($mode == 0) {
			return $langs->trans($infos['label']);
		} elseif ($mode == 1) {
			return $langs->trans($infos['label']);
		} elseif ($mode == 5) {
			return dolGetStatus($langs->trans($infos['label']), '', '', $infos['picto'], $mode);
		} elseif ($mode == 6) {
			return dolGetStatus($langs->trans($infos['label']), '', '', $infos['picto'], $mode);
		}

		return $langs->trans($infos['label']);
	}

	/**
	 * Load the info information in the object
	 *
	 * @param  int  $id Id of object
	 * @return void
	 */
	public function info($id)
	{
		$sql  = 'SELECT rowid, date_creation as datec, tms as datem, fk_user_creat, fk_user_modif';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' as t';
		$sql .= ' WHERE t.rowid = '.((int) $id);

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$this->id                   = $obj->rowid;
				$this->user_creation_id     = $obj->fk_user_creat;
				$this->user_modification_id = $obj->fk_user_modif;
				$this->date_creation        = $this->db->jdate($obj->datec);
				$this->date_modification    = empty($obj->datem) ? '' : $this->db->jdate($obj->datem);
			}
			$this->db->free($result);
		} else {
			dol_print_error($this->db);
		}
	}
}
