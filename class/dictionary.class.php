<?php
/*
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
 * \file        class/dictionary.class.php
 * \ingroup     workshop
 * \brief       Abstract base class for workshop dictionary objects
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

abstract class dictionary extends CommonObject
{
	/** @var string $module Module name */
	public $module = 'workshop';

	/** @var int $isextrafieldmanaged Enable extrafields management */
	public $isextrafieldmanaged = 0;

	/** @var int $ismultientitymanaged 0=No test on entity, 1=Test with field entity, 2=Test with link by societe */
	public $ismultientitymanaged = 1;

	/** @var string $code Object reference */
	public $code;

	/** @var int $entity Object entity */
	public $entity;

	/** @var int $active Object active */
	public $active;

	/** @var string $label Object name */
	public $label;

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
		'code' => array(
			'type'    => 'varchar(20)',
			'length'  => 20,
			'label'   => 'Code',
			'enabled' => 1,
			'visible' => 1,
			'notnull' => 1,
			'index'   => 1,
			'position' => 10,
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
		'active' => array(
			'type'    => 'integer',
			'label'   => 'Active',
			'enabled' => 1,
			'visible' => 0,
			'notnull' => 1,
			'default' => 0,
			'index'   => 1,
			'position' => 30,
			'arrayofkeyval' => array(
				0 => 'Disabled',
				1 => 'Active'
			)
		),
		'label' => array(
			'type'          => 'varchar(255)',
			'label'         => 'Label',
			'enabled'       => 1,
			'visible'       => 1,
			'position'      => 40,
			'searchall'     => 1,
			'css'           => 'minwidth200',
			'showoncombobox' => 1,
		),
	);

	/**
	 * Dictionary constructor.
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
	 * Create object into database (alias for create)
	 *
	 * @param  User $user User that creates
	 * @return int        Return integer <0 if KO, Id of created object if OK
	 */
	public function save($user)
	{
		return $this->create($user);
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
	 * Get all active records as array
	 *
	 * @param  string $field Optional field to return as value (default: rowid)
	 * @return array|int     Array of rowid => value, or -1 on error
	 */
	public function getAllActiveArray($field = '')
	{
		$Tab = array();

		$sql = 'SELECT rowid';
		if (!empty($field)) {
			$sql .= ', '.$this->db->sanitize($field);
		}
		$sql .= ' FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE active = 1';
		if ($this->ismultientitymanaged) {
			$sql .= ' AND entity IN (0, '.getEntity('workshop').')';
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$Tab[$obj->rowid] = empty($field) ? $obj->rowid : $obj->{$field};
			}
			return $Tab;
		} else {
			return -1;
		}
	}

	/**
	 * Get a field value from dictionary by id
	 *
	 * @param  int    $id    Row id
	 * @param  string $field Field name to return (default: 'label')
	 * @return string        Field value or empty string
	 */
	public function getValueFromId($id, $field = 'label')
	{
		$dict = new static($this->db);
		$ret  = $dict->fetch($id);
		if ($ret > 0 && isset($dict->{$field})) {
			return $dict->{$field};
		}

		return '';
	}

	/**
	 * Load all objects from database
	 *
	 * @param  string $sortorder  Sort order ('ASC' or 'DESC')
	 * @param  string $sortfield  Sort field
	 * @param  int    $limit      Max number of records (0 = no limit)
	 * @param  int    $offset     Offset for pagination
	 * @param  array  $filter     Filter criteria (unused, kept for signature compat)
	 * @param  string $filtermode Filter mode 'AND' or 'OR'
	 * @return array|int          Array of objects indexed by rowid, or -1 on error
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = array(), $filtermode = 'AND')
	{
		$sql = 'SELECT '.$this->getFieldList('t').' FROM '.$this->db->prefix().$this->table_element.' as t';
		if ($this->ismultientitymanaged) {
			$sql .= ' WHERE t.entity IN (0, '.getEntity($this->module).')';
		} else {
			$sql .= ' WHERE 1=1';
		}

		if (!empty($sortfield)) {
			$sql .= ' ORDER BY t.'.$this->db->sanitize($sortfield);
			if (!empty($sortorder)) {
				$sql .= ' '.$this->db->sanitize($sortorder);
			}
		} else {
			$sql .= ' ORDER BY t.label ASC';
		}

		if ($limit > 0) {
			$sql .= $this->db->plimit((int) $limit, (int) $offset);
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$ret = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$tmp = new static($this->db);
			$tmp->setVarsFromFetchObj($obj);
			$ret[$tmp->id] = $tmp;
		}
		$this->db->free($resql);

		return $ret;
	}

	/**
	 * Return the label (used by sellist/combobox rendering)
	 *
	 * @param  int    $withpicto Ignored
	 * @return string
	 */
	public function getNomUrl($withpicto = 0)
	{
		return dol_escape_htmltag($this->label);
	}
}
