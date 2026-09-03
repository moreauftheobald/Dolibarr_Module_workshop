<?php
/* Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
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
 * \file        class/workshopimpro.class.php
 * \ingroup     workshop
 * \brief       CRUD class for improductive codes dictionary (llx_workshop_c_impro).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class WorkshopImpro
 *
 * Improductive codes used by the mechanics planning / pointage.
 * Codes are auto-generated with the 'IMP' prefix (e.g. IMP00001).
 * Two codes are reserved and handled in code (never stored in the table):
 *   - IMPFin    : "Fin de journée" — closes the current open pointage without reopening.
 *   - IMPAnnul  : "Annulation"     — deletes the mechanic's last pointage if recent enough.
 */
class WorkshopImpro extends CommonObject
{
	/** @var string $module Module name */
	public $module = 'workshop';

	/** @var string $table_element Table name in SQL (without llx_ prefix) */
	public $table_element = 'workshop_c_impro';

	/** @var string $element Name of the element */
	public $element = 'workshopimpro';

	/** @var string $picto Picto */
	public $picto = 'fa-ban';

	/** @var int $isextrafieldmanaged Enable extrafields management */
	public $isextrafieldmanaged = 0;

	/**
	 * 0 = No test on entity, object is shared across all entities
	 * 1 = Test with field entity
	 *
	 * @var int $ismultientitymanaged
	 */
	public $ismultientitymanaged = 1;

	/** Reserved code: end of the working day (closes current pointage, no reopen). */
	const CODE_FIN_JOURNEE = 'IMPFin';

	/** Reserved code: cancel the last pointage if recent enough. */
	const CODE_ANNULATION = 'IMPAnnul';

	/** Prefix used to auto-generate improductive codes. */
	const CODE_PREFIX = 'IMP';

	public $fields = array(
		'rowid' => array(
			'type'     => 'integer',
			'label'    => 'TechnicalID',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 1,
			'position' => 1,
			'index'    => 1,
		),
		'code' => array(
			'type'           => 'varchar(64)',
			'label'          => 'WorkshopImproCode',
			'enabled'        => 1,
			'visible'        => 1,
			'notnull'        => 1,
			'position'       => 10,
			'showoncombobox' => 1,
			'css'            => 'maxwidth100',
		),
		'label' => array(
			'type'           => 'varchar(255)',
			'label'          => 'Label',
			'enabled'        => 1,
			'visible'        => 1,
			'notnull'        => 0,
			'position'       => 20,
			'searchall'      => 1,
			'showoncombobox' => 1,
			'css'            => 'minwidth200',
		),
		'is_absence' => array(
			'type'     => 'integer',
			'label'    => 'WorkshopImproIsAbsence',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 1,
			'default'  => 0,
			'position' => 30,
		),
		'active' => array(
			'type'     => 'integer',
			'label'    => 'Status',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 1,
			'default'  => 1,
			'position' => 40,
		),
		'date_creation' => array(
			'type'     => 'datetime',
			'label'    => 'DateCreation',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 0,
			'position' => 500,
		),
		'tms' => array(
			'type'     => 'timestamp',
			'label'    => 'DateModification',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 0,
			'position' => 501,
		),
		'entity' => array(
			'type'     => 'integer',
			'label'    => 'Entity',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 1,
			'default'  => 1,
			'position' => 510,
			'index'    => 1,
		),
	);

	/** @var string $code Improductive code */
	public $code;

	/** @var string $label Human label */
	public $label;

	/** @var int $is_absence 1 if the code represents an absence */
	public $is_absence = 0;

	/** @var int $active 1 if active */
	public $active = 1;

	/** @var int $entity Entity */
	public $entity;

	/**
	 * WorkshopImpro constructor.
	 *
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
		if (empty($this->code)) {
			$this->code = $this->getNextCode();
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
	 * Compute the next available improductive code (IMP00001, IMP00002, ...).
	 * Reserved codes (IMPFin, IMPAnnul) are not numeric and never collide.
	 *
	 * @return string Next code
	 */
	public function getNextCode()
	{
		global $conf;

		$sql  = 'SELECT code FROM '.$this->db->prefix().$this->table_element;
		$sql .= " WHERE code LIKE '".$this->db->escape(self::CODE_PREFIX)."%'";
		$sql .= ' AND entity = '.((int) $conf->entity);

		$resql = $this->db->query($sql);
		$max   = 0;
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$num = (int) substr($obj->code, strlen(self::CODE_PREFIX));
				if ($num > $max) {
					$max = $num;
				}
			}
			$this->db->free($resql);
		}

		return self::CODE_PREFIX.str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
	}

	/**
	 * Tell whether a code is one of the reserved (hardcoded) codes.
	 *
	 * @param  string $code Code to test
	 * @return bool
	 */
	public static function isReserved($code)
	{
		return in_array($code, array(self::CODE_FIN_JOURNEE, self::CODE_ANNULATION), true);
	}

	/**
	 * Load all improductive codes from the database (current entity).
	 *
	 * @param  string $sortorder Sort order ('ASC' or 'DESC')
	 * @param  string $sortfield Sort field
	 * @param  int    $activeonly 1 to return only active codes
	 * @return array|int          Array of WorkshopImpro indexed by rowid, or -1 on error
	 */
	public function fetchAll($sortorder = 'ASC', $sortfield = 'label', $activeonly = 0)
	{
		global $conf;

		$sf = !empty($sortfield) ? $this->db->sanitize($sortfield) : 'label';
		$so = !empty($sortorder) ? $this->db->sanitize($sortorder) : 'ASC';

		$sql  = 'SELECT '.$this->getFieldList('t').' FROM '.$this->db->prefix().$this->table_element.' as t';
		$sql .= ' WHERE t.entity = '.((int) $conf->entity);
		if ($activeonly) {
			$sql .= ' AND t.active = 1';
		}
		$sql .= ' ORDER BY t.'.$sf.' '.$so;

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$ret = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$tmp = new WorkshopImpro($this->db);
			$tmp->setVarsFromFetchObj($obj);
			$ret[$tmp->id] = $tmp;
		}
		$this->db->free($resql);

		return $ret;
	}

	/**
	 * Return the list of selectable improductive codes (code => label) for the
	 * current entity. The two reserved codes are appended (translated labels).
	 *
	 * @param  int $withreserved 1 to include the reserved codes (Fin de journée, Annulation)
	 * @return array             Associative array code => label
	 */
	public function getSelectList($withreserved = 1)
	{
		global $langs;

		$list = array();

		$all = $this->fetchAll('ASC', 'label', 1);
		if (is_array($all)) {
			foreach ($all as $impro) {
				$list[$impro->code] = $impro->label;
			}
		}

		if ($withreserved) {
			$list[self::CODE_FIN_JOURNEE] = $langs->trans('WorkshopImproFinJournee');
			$list[self::CODE_ANNULATION]  = $langs->trans('WorkshopImproAnnulation');
		}

		return $list;
	}
}
