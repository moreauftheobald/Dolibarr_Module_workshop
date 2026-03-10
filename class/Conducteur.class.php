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
 * \file        class/Conducteur.class.php
 * \ingroup     workshop
 * \brief       CRUD class for Conducteur (Driver) — shared across all entities
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class Conducteur extends CommonObject
{
	/** @var string $module Module name */
	public $module = 'workshop';

	/** @var string $table_element Table name in SQL (without llx_ prefix) */
	public $table_element = 'workshop_conducteur';

	/** @var string $element Name of the element */
	public $element = 'conducteur';

	/** @var string $picto Picto */
	public $picto = 'fa-id-card';

	/** @var int $isextrafieldmanaged Enable extrafields management */
	public $isextrafieldmanaged = 0;

	/**
	 * 0 = No test on entity, object is shared across all entities
	 * 1 = Test with field entity
	 *
	 * @var int $ismultientitymanaged
	 */
	public $ismultientitymanaged = 0;

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
		'nom' => array(
			'type'           => 'varchar(100)',
			'label'          => 'ConducteurNom',
			'enabled'        => 1,
			'visible'        => 1,
			'notnull'        => 1,
			'position'       => 10,
			'searchall'      => 1,
			'showoncombobox' => 1,
			'css'            => 'minwidth200',
		),
		'prenom' => array(
			'type'           => 'varchar(100)',
			'label'          => 'ConducteurPrenom',
			'enabled'        => 1,
			'visible'        => 1,
			'notnull'        => 1,
			'position'       => 20,
			'searchall'      => 1,
			'showoncombobox' => 1,
			'css'            => 'minwidth200',
		),
		'fk_soc' => array(
			'type'    => 'integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))',
			'label'   => 'ConducteurSociete',
			'picto'   => 'company',
			'enabled' => 'isModEnabled("societe")',
			'visible' => 1,
			'notnull' => -1,
			'position' => 30,
			'index'   => 1,
			'css'     => 'maxwidth500 widthcentpercentminusxx',
			'csslist' => 'tdoverflowmax150',
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

	/** @var string $nom Last name */
	public $nom;

	/** @var string $prenom First name */
	public $prenom;

	/** @var int $fk_soc Related company */
	public $fk_soc;

	/** @var string|int $date_creation Creation date */
	public $date_creation;

	/**
	 * Conducteur constructor.
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
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Create or update object into database
	 *
	 * @param  User $user      User that creates/updates
	 * @param  int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, Id of created/updated object if OK
	 */
	public function save(User $user, $notrigger = 0)
	{
		global $langs;

		$this->errors = array();

		if (empty(trim($this->nom))) {
			$this->errors[] = $langs->trans('ErrConducteurNomRequired');
		}
		if (empty(trim($this->prenom))) {
			$this->errors[] = $langs->trans('ErrConducteurPrenomRequired');
		}

		if (!empty($this->errors)) {
			return -1;
		}

		if (!empty($this->id)) {
			return $this->updateCommon($user, $notrigger);
		} else {
			return $this->createCommon($user, $notrigger);
		}
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
	 * Load all conducteurs from database (used by the unified param page).
	 *
	 * @param  string $sortorder  Sort order ('ASC' or 'DESC')
	 * @param  string $sortfield  Sort field (default: 'nom')
	 * @param  int    $limit      Max number of records (0 = no limit)
	 * @param  int    $offset     Offset for pagination
	 * @param  array  $filter     Unused — kept for signature compat
	 * @param  string $filtermode Unused — kept for signature compat
	 * @return array|int          Array of Conducteur objects indexed by rowid, or -1 on error
	 */
	public function fetchAll($sortorder = 'ASC', $sortfield = 'nom', $limit = 0, $offset = 0, $filter = array(), $filtermode = 'AND')
	{
		$sf  = !empty($sortfield) ? $this->db->sanitize($sortfield) : 'nom';
		$so  = !empty($sortorder) ? $this->db->sanitize($sortorder) : 'ASC';

		$sql  = 'SELECT rowid FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE 1=1';
		$sql .= ' ORDER BY '.$sf.' '.$so;

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
			$tmp = new Conducteur($this->db);
			$tmp->fetch($obj->rowid);
			$ret[$obj->rowid] = $tmp;
		}

		return $ret;
	}

	/**
	 * Return the full name (prenom + nom)
	 *
	 * @return string
	 */
	public function getFullName()
	{
		return trim($this->prenom.' '.$this->nom);
	}

	/**
	 * Return a link to the object card (with eventually picto)
	 *
	 * @param  int    $withpicto  Add picto into link
	 * @param  string $moreparams Add more parameters in the URL
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $moreparams = '')
	{
		global $langs;

		$result    = '';
		$label     = '<u>'.$langs->trans('ShowConducteur').'</u>';
		$label    .= '<br><b>'.$langs->trans('ConducteurNom').':</b> '.dol_escape_htmltag($this->nom);
		$label    .= '<br><b>'.$langs->trans('ConducteurPrenom').':</b> '.dol_escape_htmltag($this->prenom);

		$linkclose = '" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
		$link      = '<a href="'.dol_buildpath('/workshop/conducteur/conducteur_card.php', 1).'?id='.$this->id.urlencode($moreparams).$linkclose;
		$linkend   = '</a>';

		if ($withpicto) {
			$result .= $link.img_object($label, $this->picto, 'class="classfortooltip"').$linkend.' ';
		}

		$result .= $link.dol_escape_htmltag($this->getFullName()).$linkend;

		return $result;
	}
}
