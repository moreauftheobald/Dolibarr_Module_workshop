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
		'fk_job_type'       => array('type' => 'integer:WorkshopJobType:workshop/class/workshopjobtype.class.php', 'label' => 'JobType', 'enabled' => 1, 'position' => 42, 'notnull' => 0, 'visible' => 0),
		'fk_soc'            => array('type' => 'integer:Societe:societe/class/societe.class.php:1', 'label' => 'BillingThirdParty', 'enabled' => 1, 'position' => 44, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth500'),
		'fk_user_assign'    => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AssignedTo', 'picto' => 'user', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'csslist' => 'tdoverflowmax150'),
		'qty_mo'            => array('type' => 'double',      'label' => 'QtyMO',        'enabled' => 1, 'position' => 55, 'notnull' => 0, 'visible' => 1, 'default' => 0, 'css' => 'maxwidth100'),
		'prix_mo'           => array('type' => 'double',      'label' => 'PrixMO',       'enabled' => 1, 'position' => 56, 'notnull' => 0, 'visible' => 1, 'default' => 0, 'css' => 'maxwidth100'),
		'remise_percent'    => array('type' => 'double',      'label' => 'RemisePercent', 'enabled' => 1, 'position' => 57, 'notnull' => 1, 'visible' => 1, 'default' => 0, 'css' => 'maxwidth75'),
		'tva_tx_mo'         => array('type' => 'double',      'label' => 'TvaTxMO',      'enabled' => 1, 'position' => 58, 'notnull' => 0, 'visible' => 1, 'default' => null, 'css' => 'maxwidth75'),
		'tva_tx_st'         => array('type' => 'double',      'label' => 'TvaTxST',      'enabled' => 1, 'position' => 59, 'notnull' => 0, 'visible' => 1, 'default' => null, 'css' => 'maxwidth75'),
		'rang'              => array('type' => 'integer',     'label' => 'Rang',         'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 0, 'default' => 0),
		'time_planned'      => array('type' => 'duration',    'label' => 'TimePlanned',  'enabled' => 1, 'position' => 70,  'notnull' => 0, 'visible' => 1),
		'time_spent'        => array('type' => 'duration',    'label' => 'TimeSpent',    'enabled' => 1, 'position' => 80,  'notnull' => 0, 'visible' => 1),
		'date_start'        => array('type' => 'datetime',    'label' => 'DateStart',    'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => -1),
		'date_end'          => array('type' => 'datetime',    'label' => 'DateEnd',      'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => -1),
		'total_ht'          => array('type' => 'double',      'label' => 'TotalHT',        'enabled' => 1, 'position' => 200, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'total_ht_part'     => array('type' => 'double',      'label' => 'TotalHTPart',    'enabled' => 1, 'position' => 210, 'notnull' => 1, 'visible' => -1),
		'total_ht_mo'       => array('type' => 'double',      'label' => 'TotalHTMO',      'enabled' => 1, 'position' => 220, 'notnull' => 1, 'visible' => -1),
		'total_ht_service'  => array('type' => 'double',      'label' => 'TotalHTService', 'enabled' => 1, 'position' => 230, 'notnull' => 1, 'visible' => -1),
		'total_ht_external' => array('type' => 'double',      'label' => 'TotalHTExternal', 'enabled' => 1, 'position' => 240, 'notnull' => 1, 'visible' => -1),
		'total_ht_refund'   => array('type' => 'double',      'label' => 'TotalHTRefund',  'enabled' => 1, 'position' => 250, 'notnull' => 1, 'visible' => -1),
		'note_public'       => array('type' => 'html',        'label' => 'NotePublic',     'enabled' => 1, 'position' => 300, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
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
	public $fk_job_type;
	public $fk_soc;
	public $fk_user_assign;
	public $qty_mo;
	public $prix_mo;
	public $remise_percent;
	public $tva_tx_mo;
	public $tva_tx_st;
	public $rang;
	public $time_planned;
	public $time_spent;
	public $date_start;
	public $date_end;
	public $total_ht;
	public $total_ht_part;
	public $total_ht_mo;
	public $total_ht_service;
	public $total_ht_external;
	public $total_ht_refund;
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

		$detObj = new Operationorderdet($this->db);
		$lines = $detObj->fetchAllByJob($this->id);
		if (!is_array($lines)) {
			$this->error = $detObj->error ?: implode(',', $detObj->errors);
			return -1;
		}

		$this->lines = array_values($lines);
		return 1;
	}

	/**
	 * Recompute remise_percent on all jobs of an OR from the OR's third-party discount.
	 * Called when the billing third-party of an OR changes.
	 *
	 * @param  int  $fk_operationorder  OR id
	 * @param  float $remise_percent    New discount rate (from societe.remise_percent)
	 * @param  User $user               User performing the update
	 * @return int                      <0 if KO, number of updated jobs if OK
	 */
	public function updateRemiseForOR(int $fk_operationorder, float $remise_percent, User $user): int
	{
		$jobs = $this->fetchAllByOperationorder($fk_operationorder);
		if (!is_array($jobs)) {
			return -1;
		}

		// Recalculate total_ht including parts/services already in DB, for all jobs in one query.
		$sums = array();
		if (!empty($jobs)) {
			$jobIds = array_map(function ($j) {
				return (int) $j->id;
			}, $jobs);
			$sqlTot  = 'SELECT fk_operationorder_jobs,';
			$sqlTot .= '  COALESCE(SUM(total_ht_part), 0)   AS total_ht_part,';
			$sqlTot .= '  COALESCE(SUM(total_ht_service), 0) AS total_ht_service,';
			$sqlTot .= '  COALESCE(SUM(total_ht_refund), 0)  AS total_ht_refund';
			$sqlTot .= ' FROM '.$this->db->prefix().'workshop_operationorderdet';
			$sqlTot .= ' WHERE fk_operationorder_jobs IN ('.implode(',', $jobIds).')';
			$sqlTot .= ' GROUP BY fk_operationorder_jobs';
			$resTot = $this->db->query($sqlTot);
			if ($resTot) {
				while ($objTot = $this->db->fetch_object($resTot)) {
					$sums[(int) $objTot->fk_operationorder_jobs] = $objTot;
				}
				$this->db->free($resTot);
			}
		}

		$updated = 0;
		foreach ($jobs as $job) {
			$job->remise_percent = $remise_percent;
			$job->computeMO();
			$objTot = isset($sums[$job->id]) ? $sums[$job->id] : null;
			$job->total_ht_part    = $objTot ? (float) $objTot->total_ht_part : 0.0;
			$job->total_ht_service = $objTot ? (float) $objTot->total_ht_service : 0.0;
			$job->total_ht_refund  = $objTot ? (float) $objTot->total_ht_refund : 0.0;
			$job->total_ht = $job->total_ht_mo + $job->total_ht_part + $job->total_ht_service + (float) $job->total_ht_external + $job->total_ht_refund;
			if ($job->updateCommon($user, 1) > 0) {
				$updated++;
			}
		}
		return $updated;
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
		$this->computeMO();
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
	 * Compute total_ht_mo from qty_mo * prix_mo * (1 - remise_percent/100).
	 * Called automatically by create() and update().
	 *
	 * @return void
	 */
	public function computeMO()
	{
		$remise = max(0.0, min(100.0, (float) $this->remise_percent));
		$this->total_ht_mo = (float) price2num((float) $this->qty_mo * (float) $this->prix_mo * (1 - $remise / 100), 'MT');
	}

	/**
	 * Recalculate totals from detail lines and own MO cost.
	 * Aggregates part/service/refund from Operationorderdet lines,
	 * adds MO (qty_mo × prix_mo) and external (future dev), then persists.
	 *
	 * @param  User $user User performing the update
	 * @return int        Return integer <0 if KO, >0 if OK
	 */
	public function updateTotals(User $user)
	{
		// MO cost: job's own theoretical quantity × hourly rate
		$this->computeMO();

		$sql  = 'SELECT';
		$sql .= '  COALESCE(SUM(total_ht_part), 0)    AS total_ht_part,';
		$sql .= '  COALESCE(SUM(total_ht_service), 0)  AS total_ht_service,';
		$sql .= '  COALESCE(SUM(total_ht_refund), 0)   AS total_ht_refund';
		$sql .= ' FROM '.$this->db->prefix().'workshop_operationorderdet';
		$sql .= ' WHERE fk_operationorder_jobs = '.((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$this->total_ht_part     = (float) $obj->total_ht_part;
			$this->total_ht_service  = (float) $obj->total_ht_service;
			// total_ht_external est géré par refreshExternalAmount() — on conserve la valeur courante
			$this->total_ht_refund   = (float) $obj->total_ht_refund;
			$this->total_ht          = $this->total_ht_part
				+ $this->total_ht_mo
				+ $this->total_ht_service
				+ $this->total_ht_external
				+ $this->total_ht_refund;
			$this->db->free($resql);
			// Use updateCommon directly to avoid triggering computeMO() again via update()
			return $this->updateCommon($user, 1);
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Recalculates total_ht_external as the sum of total_ht of all supplier orders
	 * linked to this job via llx_element_element, then persists and calls updateTotals().
	 * Called at each OR card display to keep the amount in sync.
	 *
	 * @param  User $user User performing the update
	 * @return int        <0 if KO, >0 if OK
	 */
	public function refreshExternalAmount(User $user): int
	{
		$sql  = 'SELECT COALESCE(SUM(cf.total_ht), 0) AS ext_total';
		$sql .= ' FROM '.$this->db->prefix().'element_element ee';
		$sql .= ' INNER JOIN '.$this->db->prefix().'commande_fournisseur cf ON cf.rowid = ee.fk_target';
		$sql .= ' WHERE ee.fk_source = '.(int) $this->id;
		$sql .= " AND ee.sourcetype = '".$this->db->escape($this->element)."'";
		$sql .= " AND ee.targettype = 'order_supplier'";

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($res);
		$this->db->free($res);

		$this->total_ht_external = (float) price2num($obj ? $obj->ext_total : 0, 'MT');
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

		return $datas;
	}

	/**
	 * Return ids of VehiculeOperation records linked to this job.
	 *
	 * @return int[]|int Array of ids, or <0 on DB error
	 */
	public function fetchLinkedMaintenanceOperationIds()
	{
		$sql  = 'SELECT fk_vehicule_operation FROM '.$this->db->prefix().'workshop_operationorder_jobs_maintenance';
		$sql .= ' WHERE fk_job = '.((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$ids = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$ids[] = (int) $obj->fk_vehicule_operation;
		}
		$this->db->free($resql);
		return $ids;
	}

	/**
	 * Link this job to one or more VehiculeOperation records.
	 * Ignores duplicates (INSERT IGNORE).
	 *
	 * @param  int[] $ids Array of fk_vehicule_operation ids
	 * @return int        Number of rows inserted, or <0 on DB error
	 */
	public function linkMaintenanceOperations(array $ids): int
	{
		$idVos = array();
		foreach ($ids as $idVo) {
			$idVo = (int) $idVo;
			if ($idVo > 0) {
				$idVos[] = $idVo;
			}
		}
		if (empty($idVos)) {
			return 0;
		}

		$values = array();
		foreach ($idVos as $idVo) {
			$values[] = '('.((int) $this->id).', '.$idVo.')';
		}
		$sql  = 'INSERT IGNORE INTO '.$this->db->prefix().'workshop_operationorder_jobs_maintenance';
		$sql .= ' (fk_job, fk_vehicule_operation) VALUES '.implode(', ', $values);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return -1;
		}
		return count($idVos);
	}

	/**
	 * Remove all VehiculeOperation links for this job.
	 *
	 * @return int >0 if OK, <0 on DB error
	 */
	public function unlinkAllMaintenanceOperations(): int
	{
		$sql  = 'DELETE FROM '.$this->db->prefix().'workshop_operationorder_jobs_maintenance';
		$sql .= ' WHERE fk_job = '.((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return -1;
		}
		return 1;
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
