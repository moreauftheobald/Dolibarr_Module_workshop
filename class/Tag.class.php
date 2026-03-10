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
 * \file        class/Tag.class.php
 * \ingroup     workshop
 * \brief       CRUD class for Tag — shared across all entities, used to categorise operation orders
 */

dol_include_once('/workshop/class/dictionary.class.php');

class Tag extends dictionary
{
	/** @var string $table_element Table name in SQL (without llx_ prefix) */
	public $table_element = 'workshop_tag';

	/** @var string $element Name of the element */
	public $element = 'workshoptag';

	/** @var string $picto Picto */
	public $picto = 'fa-tag';

	/**
	 * 0 = No test on entity, object is shared across all entities
	 *
	 * @var int $ismultientitymanaged
	 */
	public $ismultientitymanaged = 0;

	/** @var string $color Hex color code */
	public $color;

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
			'type'           => 'varchar(20)',
			'length'         => 20,
			'label'          => 'Code',
			'enabled'        => 1,
			'visible'        => 1,
			'notnull'        => 1,
			'index'          => 1,
			'position'       => 10,
			'showoncombobox' => 1,
		),
		'label' => array(
			'type'           => 'varchar(255)',
			'label'          => 'TagLibelle',
			'enabled'        => 1,
			'visible'        => 1,
			'notnull'        => 1,
			'position'       => 20,
			'searchall'      => 1,
			'css'            => 'minwidth200',
			'showoncombobox' => 1,
		),
		'color' => array(
			'type'     => 'varchar(7)',
			'label'    => 'TagCouleur',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'default'  => '#000000',
			'position' => 30,
			'css'      => 'minwidth100',
		),
		'active' => array(
			'type'     => 'integer',
			'label'    => 'Active',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 1,
			'default'  => 1,
			'index'    => 1,
			'position' => 40,
			'arrayofkeyval' => array(
				0 => 'Disabled',
				1 => 'Active',
			),
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
		'fk_user_creat' => array(
			'type'     => 'integer:User:user/class/user.class.php',
			'label'    => 'UserAuthor',
			'enabled'  => 1,
			'visible'  => -2,
			'notnull'  => -1,
			'position' => 510,
		),
		'fk_user_modif' => array(
			'type'     => 'integer:User:user/class/user.class.php',
			'label'    => 'UserModif',
			'enabled'  => 1,
			'visible'  => -2,
			'notnull'  => -1,
			'position' => 511,
		),
	);

	/**
	 * Tag constructor.
	 *
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return the label with a colored badge
	 *
	 * @param  int $withpicto Ignored
	 * @return string
	 */
	public function getNomUrl($withpicto = 0)
	{
		if (!empty($this->color)) {
			return '<span class="badge" style="background-color:'.dol_escape_htmltag($this->color).';color:#fff;">'.dol_escape_htmltag($this->label).'</span>';
		}
		return dol_escape_htmltag($this->label);
	}

	/**
	 * Get all active tags as array indexed by rowid
	 *
	 * @return array  Array of rowid => Tag objects, or -1 on error
	 */
	public function getAllActiveTags()
	{
		$sql  = 'SELECT rowid FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE active = 1';
		$sql .= ' ORDER BY label ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$ret = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$tag = new self($this->db);
			$tag->fetch($obj->rowid);
			$ret[$obj->rowid] = $tag;
		}

		return $ret;
	}
}
