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
 * \file        class/operationorderdet.class.php
 * \ingroup     workshop
 * \brief       This file is a CRUD class file for Operationorderdet (Create/Read/Update/Delete)
 *              Child of Operationorder_jobs (which is itself child of Operationorder).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Operationorderdet (Ligne de détail d'un travail d'Ordre de Réparation)
 * Child of Operationorder_jobs.
 */
class Operationorderdet extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'workshop';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'operationorderdet';

	/**
	 * @var string Name of table without prefix where object is stored.
	 */
	public $table_element = 'workshop_operationorderdet';

	/**
	 * @var string Icon for Operationorderdet.
	 */
	public $picto = 'fa-list';

	/** Product type constants */
	const TYPE_PRODUCT = 0;
	const TYPE_SERVICE = 1;
	const TYPE_REFUND  = 2;

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array Array with all fields and their property.
	 */
	public $fields = array(
		'rowid'                  => array('type' => 'integer',      'label' => 'TechnicalID',       'enabled' => 1, 'position' => 1,   'notnull' => 1,  'visible' => 0),
		'fk_operationorder_jobs' => array('type' => 'integer:Operationorder_jobs:workshop/class/operationorder_jobs.class.php', 'label' => 'OperationorderJob', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 0),
		'fk_product'             => array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'Product', 'picto' => 'product', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'label'                  => array('type' => 'varchar(255)', 'label' => 'Label',             'enabled' => 1, 'position' => 30,  'notnull' => 0,  'visible' => 1, 'searchall' => 1, 'css' => 'minwidth300'),
		'description'            => array('type' => 'html',         'label' => 'Description',       'enabled' => 1, 'position' => 40,  'notnull' => 0,  'visible' => 3, 'cssview' => 'wordbreak'),
		'product_type'           => array('type' => 'integer',      'label' => 'ProductType',       'enabled' => 1, 'position' => 50,  'notnull' => 1,  'visible' => 1, 'default' => self::TYPE_PRODUCT, 'arrayofkeyval' => array(
			self::TYPE_PRODUCT => 'Product',
			self::TYPE_SERVICE => 'Service',
			self::TYPE_REFUND  => 'Refund',
		)),
		'qty'                    => array('type' => 'double',        'label' => 'Qty',               'enabled' => 1, 'position' => 60,  'notnull' => 0,  'visible' => 1, 'isameasure' => 1),
		'price'                  => array('type' => 'double',        'label' => 'UnitPriceHT',       'enabled' => 1, 'position' => 70,  'notnull' => 0,  'visible' => 1),
		'remise_percent'         => array('type' => 'real',          'label' => 'DiscountPercent',   'enabled' => 1, 'position' => 80,  'notnull' => 0,  'visible' => 1),
		'remise'                 => array('type' => 'real',          'label' => 'DiscountAmount',    'enabled' => 1, 'position' => 85,  'notnull' => 0,  'visible' => -1),
		'total_ht'               => array('type' => 'double',        'label' => 'TotalHT',        'enabled' => 1, 'position' => 90,  'notnull' => 1,  'visible' => 1, 'isameasure' => 1),
		'total_ht_part'          => array('type' => 'double',        'label' => 'TotalHTPart',    'enabled' => 1, 'position' => 100, 'notnull' => 1,  'visible' => -1),
		'total_ht_service'       => array('type' => 'double',        'label' => 'TotalHTService', 'enabled' => 1, 'position' => 110, 'notnull' => 1,  'visible' => -1),
		'total_ht_refund'        => array('type' => 'double',        'label' => 'TotalHTRefund',  'enabled' => 1, 'position' => 120, 'notnull' => 1,  'visible' => -1),
		'pc'                     => array('type' => 'varchar(255)',   'label' => 'PC',             'enabled' => 1, 'position' => 130, 'notnull' => 0,  'visible' => -1),
		'pr'                     => array('type' => 'double',         'label' => 'PR',             'enabled' => 1, 'position' => 140, 'notnull' => 0,  'visible' => -1),
		'fk_warehouse'           => array('type' => 'integer:Entrepot:product/class/entrepot.class.php', 'label' => 'Warehouse', 'picto' => 'stock', 'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => 1, 'css' => 'maxwidth300', 'csslist' => 'tdoverflowmax150'),
		'info_bits'              => array('type' => 'integer',        'label' => 'InfoBits',       'enabled' => 1, 'position' => 160, 'notnull' => 0,  'visible' => 0),
		'rang'                   => array('type' => 'integer',        'label' => 'Rang',           'enabled' => 1, 'position' => 170, 'notnull' => 0,  'visible' => 0, 'default' => 0),
		'fk_user_creat'          => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid', 'csslist' => 'tdoverflowmax150'),
		'fk_user_modif'          => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'date_creation'          => array('type' => 'datetime',       'label' => 'DateCreation',      'enabled' => 1, 'position' => 520, 'notnull' => 0,  'visible' => -2),
		'tms'                    => array('type' => 'timestamp',      'label' => 'DateModification',  'enabled' => 1, 'position' => 530, 'notnull' => 1,  'visible' => -2),
		'import_key'             => array('type' => 'varchar(14)',     'label' => 'ImportId',          'enabled' => 1, 'position' => 900, 'notnull' => 0,  'visible' => 0),
	);

	public $rowid;
	public $fk_operationorder_jobs;
	public $fk_product;
	public $label;
	public $description;
	public $product_type;
	public $qty;
	public $price;
	public $remise_percent;
	public $remise;
	public $total_ht;
	public $total_ht_part;
	public $total_ht_service;
	public $total_ht_refund;
	public $pc;
	public $pr;
	public $fk_warehouse;
	public $info_bits;
	public $rang;
	public $fk_user_creat;
	public $fk_user_modif;
	public $date_creation;
	public $tms;
	public $import_key;

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
		$this->computeTotals();
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param  int    $id            Id object
	 * @param  string $ref           Ref (not used here)
	 * @param  int    $noextrafields 0=Default, 1=No extrafields
	 * @param  int    $nolines       0=Default (unused, no child level below)
	 * @return int                   Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
	{
		return $this->fetchCommon($id, $ref, '', $noextrafields);
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
	 * Load all detail lines for a given job
	 *
	 * @param  int    $fk_operationorder_jobs Id of the parent job
	 * @param  string $sortfield              Sort field
	 * @param  string $sortorder              Sort order
	 * @return array|int                      int <0 if KO, array if OK
	 */
	public function fetchAllByJob($fk_operationorder_jobs, $sortfield = 'rang', $sortorder = 'ASC')
	{
		$filter = '(fk_operationorder_jobs:=:'.((int) $fk_operationorder_jobs).')';
		return $this->fetchAll($sortorder, $sortfield, 0, 0, $filter);
	}

	/**
	 * Load all detail lines for a given operation order (all jobs combined), via a JOIN on jobs.
	 *
	 * @param  int    $fk_operationorder Id of the parent OR
	 * @param  string $sortfield         Sort field (prefixed with 't.')
	 * @param  string $sortorder         Sort order
	 * @return array|int                 int <0 if KO, array if OK
	 */
	public function fetchAllByOperationorder($fk_operationorder, $sortfield = 't.rang', $sortorder = 'ASC')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql  = 'SELECT t.*';
		$sql .= ' FROM '.$this->db->prefix().$this->table_element.' as t';
		$sql .= ' INNER JOIN '.$this->db->prefix().'workshop_operationorder_jobs as j ON j.rowid = t.fk_operationorder_jobs';
		$sql .= ' WHERE j.fk_operationorder = '.((int) $fk_operationorder);
		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);
				$records[$record->id] = $record;
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
		$this->computeTotals();
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
	 * Compute total_ht and detail totals from qty, price and product_type.
	 * Also applies discount.
	 *
	 * @return void
	 */
	public function computeTotals()
	{
		$qty            = (float) $this->qty;
		$price          = (float) $this->price;
		$remise_percent = (float) $this->remise_percent;

		$base = $qty * $price;
		if ($remise_percent > 0) {
			$remise       = $base * $remise_percent / 100;
			$this->remise = $remise;
			$base         = $base - $remise;
		} else {
			$this->remise = 0;
		}

		$this->total_ht         = $base;
		$this->total_ht_part    = 0;
		$this->total_ht_service = 0;
		$this->total_ht_refund  = 0;

		switch ((int) $this->product_type) {
			case self::TYPE_PRODUCT:
				$this->total_ht_part = $base;
				break;
			case self::TYPE_SERVICE:
				$this->total_ht_service = $base;
				break;
			case self::TYPE_REFUND:
				$this->total_ht_refund = $base;
				break;
		}
	}

	/**
	 * Return a link pointing to the parent Operationorder (OR) card.
	 * Operationorderdet has no standalone card page — the meaningful link
	 * is the OR that contains this detail line.
	 *
	 * @param  int    $withpicto             Include picto in link (0=No, 1=With picto, 2=Only picto)
	 * @param  string $option                Unused (kept for interface compatibility)
	 * @param  int    $notooltip             1=Disable tooltip
	 * @param  string $morecss               Additional CSS
	 * @param  int    $save_lastsearch_value Unused
	 * @return string                        HTML link to the OR card
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $langs;

		// Retrieve the parent OR ref via a single join query
		$orRef  = '';
		$orId   = 0;
		if (!empty($this->fk_operationorder_jobs)) {
			$sql  = 'SELECT o.rowid, o.ref';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'workshop_operationorder o';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'workshop_operationorder_jobs j ON j.fk_operationorder = o.rowid';
			$sql .= ' WHERE j.rowid = '.((int) $this->fk_operationorder_jobs);
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				$orId  = (int) $obj->rowid;
				$orRef = $obj->ref;
				$this->db->free($resql);
			}
		}

		// Label affiché : ref OR uniquement
		$labelDisplay = $orRef ?: $langs->trans('OperationOrder');

		$result = '';
		if ($orId > 0) {
			$url = dol_buildpath('/workshop/operationorder/or_card.php', 1).'?id='.$orId;
			if ($withpicto) {
				$result .= '<a href="'.dol_escape_htmltag($url).'"'.($morecss ? ' class="'.dol_escape_htmltag($morecss).'"' : '').'>';
				$result .= img_picto('', 'fa-tools', 'class="paddingright"');
				$result .= '</a>';
				if ($withpicto != 2) {
					$result .= '<a href="'.dol_escape_htmltag($url).'"'.($morecss ? ' class="'.dol_escape_htmltag($morecss).'"' : '').'>';
					$result .= dol_escape_htmltag($labelDisplay);
					$result .= '</a>';
				}
			} else {
				$result .= '<a href="'.dol_escape_htmltag($url).'"'.($morecss ? ' class="'.dol_escape_htmltag($morecss).'"' : '').'>';
				$result .= dol_escape_htmltag($labelDisplay);
				$result .= '</a>';
			}
		} else {
			if ($withpicto) {
				$result .= img_picto('', 'fa-tools', 'class="paddingright"');
			}
			$result .= dol_escape_htmltag($labelDisplay);
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
			return array('optimize' => $langs->trans('ShowOperationorderdet'));
		}

		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans('Operationorderdet').'</u>';
		if (!empty($this->label)) {
			$datas['label'] = '<br><b>'.$langs->trans('Label').':</b> '.$this->label;
		}
		if (!empty($this->qty)) {
			$datas['qty'] = '<br><b>'.$langs->trans('Qty').':</b> '.$this->qty;
		}
		if (!empty($this->total_ht)) {
			$datas['total_ht'] = '<br><b>'.$langs->trans('TotalHT').':</b> '.$this->total_ht;
		}

		return $datas;
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
