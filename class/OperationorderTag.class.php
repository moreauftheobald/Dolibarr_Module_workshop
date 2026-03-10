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
 * \file        class/OperationorderTag.class.php
 * \ingroup     workshop
 * \brief       Management of Tag links on Operation Orders (relation 1-to-many OR→Tags)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class OperationorderTag extends CommonObject
{
	/** @var string $module Module name */
	public $module = 'workshop';

	/** @var string $table_element Table name in SQL (without llx_ prefix) */
	public $table_element = 'workshop_operationorder_tag';

	/** @var string $element Name of the element */
	public $element = 'operationordertag';

	/** @var int $ismultientitymanaged No entity filter — link table */
	public $ismultientitymanaged = 0;

	/** @var int $fk_operationorder FK to operation order */
	public $fk_operationorder;

	/** @var int $fk_tag FK to tag */
	public $fk_tag;

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
		'fk_operationorder' => array(
			'type'     => 'integer',
			'label'    => 'OperationOrder',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 1,
			'position' => 10,
			'index'    => 1,
		),
		'fk_tag' => array(
			'type'     => 'integer',
			'label'    => 'Tag',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 1,
			'position' => 20,
			'index'    => 1,
		),
	);

	/**
	 * OperationorderTag constructor.
	 *
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Create link into database
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
	 * Add a tag to an operation order
	 *
	 * @param  int  $fk_operationorder Operation order id
	 * @param  int  $fk_tag            Tag id
	 * @param  User $user              User performing the action
	 * @return int                     >0 OK, <0 KO, 0 already exists
	 */
	public function addTag($fk_operationorder, $fk_tag, User $user)
	{
		// Check if link already exists
		$sql  = 'SELECT rowid FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_operationorder = '.((int) $fk_operationorder);
		$sql .= ' AND fk_tag = '.((int) $fk_tag);

		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			return 0; // already linked
		}

		$this->fk_operationorder = $fk_operationorder;
		$this->fk_tag            = $fk_tag;

		return $this->create($user);
	}

	/**
	 * Remove a tag from an operation order
	 *
	 * @param  int  $fk_operationorder Operation order id
	 * @param  int  $fk_tag            Tag id
	 * @param  User $user              User performing the action
	 * @return int                     >0 OK, <0 KO
	 */
	public function removeTag($fk_operationorder, $fk_tag, User $user)
	{
		$sql  = 'DELETE FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_operationorder = '.((int) $fk_operationorder);
		$sql .= ' AND fk_tag = '.((int) $fk_tag);

		$resql = $this->db->query($sql);
		if ($resql) {
			return $this->db->affected_rows($resql) > 0 ? 1 : 0;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Remove all tags from an operation order
	 *
	 * @param  int  $fk_operationorder Operation order id
	 * @return int                     >0 OK, <0 KO
	 */
	public function removeAllTags($fk_operationorder)
	{
		$sql  = 'DELETE FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_operationorder = '.((int) $fk_operationorder);

		$resql = $this->db->query($sql);
		if ($resql) {
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Get all Tag objects linked to an operation order
	 *
	 * @param  int $fk_operationorder Operation order id
	 * @return array|int              Array of Tag objects indexed by tag rowid, or -1 on error
	 */
	public function getTagsForOR($fk_operationorder)
	{
		dol_include_once('/workshop/class/Tag.class.php');

		$sql  = 'SELECT fk_tag FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_operationorder = '.((int) $fk_operationorder);
		$sql .= ' ORDER BY fk_tag ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$ret = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$tag = new Tag($this->db);
			if ($tag->fetch((int) $obj->fk_tag) > 0) {
				$ret[$obj->fk_tag] = $tag;
			}
		}

		return $ret;
	}

	/**
	 * Get all tag ids linked to an operation order (lightweight version)
	 *
	 * @param  int $fk_operationorder Operation order id
	 * @return array|int              Array of tag ids, or -1 on error
	 */
	public function getTagIdsForOR($fk_operationorder)
	{
		$sql  = 'SELECT fk_tag FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE fk_operationorder = '.((int) $fk_operationorder);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$ret = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$ret[] = (int) $obj->fk_tag;
		}

		return $ret;
	}
}
