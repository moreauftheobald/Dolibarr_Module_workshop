<?php
/* Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
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
 * \file    class/workshopoperationorderstatus.class.php
 * \ingroup workshop
 * \brief   Gestion des statuts des ordres de réparation workshop
 *
 * Les statuts sont partagés entre toutes les entités (entity = 0).
 * Les transitions entre statuts sont également globales (entity = 0).
 * Les droits des groupes d'utilisateurs (read/edit/changeToThisStatus) sont définis par entité.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';


/**
 * Classe de gestion des statuts des ordres de réparation
 *
 * Un statut est défini sur entity=0 et partagé par toutes les entités.
 * Les transitions entre statuts sont centralisées sur entity=0.
 * Les droits des groupes par action sont définis par entité via WorkshopOperationOrderStatusUserGroupRight.
 */
class WorkshopOperationOrderStatus extends CommonObject
{
	/** @var string Module name */
	public $module = 'workshop';

	/** @var string Table name in SQL (without prefix) */
	public $table_element = 'workshop_operationorder_status';

	/** @var string Element name */
	public $element = 'workshopoperationorderstatus';

	/** @var string Picto */
	public $picto = 'fa-traffic-light';

	/** @var int Manage extra fields */
	public $isextrafieldmanaged = 1;

	/**
	 * 0 = No test on entity, 1 = Test with field entity, 2 = Test with link by societe
	 * Les statuts étant sur entity=0, on désactive le filtre entity automatique de Dolibarr.
	 * @var int
	 */
	public $ismultientitymanaged = 0;

	/** Statut actif */
	const STATUS_ACTIVE = 1;
	/** Statut désactivé */
	const STATUS_DISABLED = 0;

	/** @var array Libellés des statuts de l'objet statut lui-même */
	public static $TStatus = array(
		self::STATUS_DISABLED => 'WorkshopORStatusDisabled',
		self::STATUS_ACTIVE   => 'WorkshopORStatusActive',
	);

	/**
	 * Définition des champs de l'objet
	 * @var array
	 */
	public $fields = array(
		'rowid' => array(
			'type'     => 'integer',
			'label'    => 'TechnicalID',
			'enabled'  => 1,
			'position' => 0,
			'notnull'  => 1,
			'visible'  => 0,
		),
		'entity' => array(
			'type'     => 'integer',
			'label'    => 'Entity',
			'enabled'  => 1,
			'position' => 1,
			'notnull'  => 1,
			'visible'  => 0,
			'default'  => 0,
			'index'    => 1,
		),
		'date_creation' => array(
			'type'     => 'datetime',
			'label'    => 'DateCreation',
			'enabled'  => 1,
			'position' => 2,
			'notnull'  => -1,
			'visible'  => -2,
		),
		'tms' => array(
			'type'     => 'timestamp',
			'label'    => 'DateModification',
			'enabled'  => 1,
			'position' => 3,
			'notnull'  => 0,
			'visible'  => -2,
		),
		'code' => array(
			'type'     => 'varchar(128)',
			'label'    => 'Code',
			'enabled'  => 1,
			'position' => 10,
			'notnull'  => 1,
			'required' => 1,
			'visible'  => 1,
			'default'  => '',
			'index'    => 1,
		),
		'label' => array(
			'type'     => 'varchar(128)',
			'label'    => 'Label',
			'enabled'  => 1,
			'position' => 20,
			'notnull'  => 1,
			'required' => 1,
			'visible'  => 1,
		),
		'color' => array(
			'type'     => 'varchar(16)',
			'label'    => 'Color',
			'enabled'  => 1,
			'position' => 30,
			'notnull'  => 1,
			'visible'  => 1,
			'default'  => '#3c8dbc',
		),
		'rang' => array(
			'type'     => 'int',
			'label'    => 'Rank',
			'help'     => 'RankHelp',
			'enabled'  => 1,
			'position' => 40,
			'visible'  => 0,
		),
		'planable' => array(
			'type'     => 'boolean',
			'label'    => 'Planable',
			'enabled'  => 1,
			'position' => 50,
			'visible'  => -1,
			'required' => 0,
			'default'  => 0,
			'help'     => 'PlanableHelp',
		),
		'clean_event' => array(
			'type'     => 'boolean',
			'label'    => 'WorkshopCleanEvent',
			'enabled'  => 1,
			'position' => 55,
			'visible'  => -1,
			'required' => 0,
			'default'  => 0,
			'help'     => 'WorkshopCleanEventHelp',
		),
		'display_on_planning' => array(
			'type'     => 'boolean',
			'label'    => 'WorkshopDisplayOnPlanning',
			'enabled'  => 1,
			'position' => 60,
			'visible'  => -1,
			'required' => 0,
			'default'  => 0,
			'help'     => 'WorkshopDisplayOnPlanningHelp',
		),
		'check_virtual_stock' => array(
			'type'     => 'boolean',
			'label'    => 'WorkshopCheckVirtualStock',
			'enabled'  => 1,
			'position' => 65,
			'visible'  => -1,
			'required' => 0,
			'default'  => 0,
			'help'     => 'WorkshopCheckVirtualStockHelp',
		),
		'or_pointable' => array(
			'type'     => 'boolean',
			'label'    => 'WorkshopOrPointable',
			'enabled'  => 1,
			'position' => 70,
			'visible'  => -1,
			'required' => 0,
			'default'  => 0,
			'help'     => 'WorkshopOrPointableHelp',
		),
		'save_date_cloture' => array(
			'type'     => 'boolean',
			'label'    => 'WorkshopSaveDateCloture',
			'enabled'  => 1,
			'position' => 75,
			'visible'  => -1,
			'required' => 0,
			'default'  => 0,
			'help'     => 'WorkshopSaveDateClotureHelp',
		),
		'require_planned_date' => array(
			'type'     => 'boolean',
			'label'    => 'WorkshopRequirePlannedDate',
			'enabled'  => 1,
			'position' => 80,
			'visible'  => -1,
			'required' => 0,
			'default'  => 0,
			'help'     => 'WorkshopRequirePlannedDateHelp',
		),
		'update_vehicule_info' => array(
			'type'     => 'boolean',
			'label'    => 'WorkshopUpdateVehiculeInfo',
			'enabled'  => 1,
			'position' => 85,
			'visible'  => -1,
			'required' => 0,
			'default'  => 0,
			'help'     => 'WorkshopUpdateVehiculeInfoHelp',
		),
		'require_conf' => array(
			'type'     => 'boolean',
			'label'    => 'WorkshopRequireConf',
			'enabled'  => 1,
			'position' => 90,
			'visible'  => -1,
			'required' => 0,
			'default'  => 1,
			'help'     => 'WorkshopRequireConfHelp',
		),
		'status' => array(
			'type'         => 'smallint',
			'label'        => 'Status',
			'enabled'      => 1,
			'position'     => 1000,
			'notnull'      => 1,
			'visible'      => 2,
			'index'        => 1,
			'default'      => 1,
			'arrayofkeyval' => array(
				self::STATUS_DISABLED => 'WorkshopORStatusDisabled',
				self::STATUS_ACTIVE   => 'WorkshopORStatusActive',
			),
		),
		'import_key' => array(
			'type'     => 'varchar(14)',
			'label'    => 'ImportId',
			'enabled'  => 1,
			'position' => 1200,
			'notnull'  => -1,
			'visible'  => -2,
		),
	);

	/** @var int Rowid */
	public $rowid;
	/** @var string Code du statut */
	public $code;
	/** @var string Libellé du statut */
	public $label;
	/** @var string Couleur hexadécimale (#rrggbb) */
	public $color;
	/** @var int Rang d'affichage */
	public $rang;
	/** @var int|bool Planifiable */
	public $planable;
	/** @var int|bool Supprimer l'événement agenda au passage à ce statut */
	public $clean_event;
	/** @var int|bool Afficher sur le planning */
	public $display_on_planning;
	/** @var int|bool Vérifier le stock virtuel */
	public $check_virtual_stock;
	/** @var int|bool Pointage possible sur les OR à ce statut */
	public $or_pointable;
	/** @var int|bool Enregistrer la date de clôture */
	public $save_date_cloture;
	/** @var int|bool Date planifiée obligatoire */
	public $require_planned_date;
	/** @var int|bool Mettre à jour les infos véhicule */
	public $update_vehicule_info;
	/** @var int|bool Ventilation (conf) requise */
	public $require_conf;
	/** @var int Statut actif/désactivé */
	public $status;
	/** @var string Clé d'import */
	public $import_key;
	/** @var int Entité (toujours 0) */
	public $entity = 0;

	/**
	 * Droits des groupes chargés pour l'entité courante.
	 * Structure : $TGroupCan['read'][rowid_ligne] = fk_usergroup
	 *             $TGroupCan['edit'][rowid_ligne] = fk_usergroup
	 *             $TGroupCan['changeToThisStatus'][rowid_ligne] = fk_usergroup
	 * @var array
	 */
	public $TGroupCan = array();

	/**
	 * Transitions autorisées depuis ce statut (partagées, entity=0).
	 * Structure : $TStatusAllowed[rowid_ligne] = fk_workshop_operationorderstatus_target
	 * @var array
	 */
	public $TStatusAllowed = array();

	/**
	 * Types de droits gérés par groupe d'utilisateurs.
	 * Chaque action peut être autorisée ou non pour chaque groupe, par entité.
	 * @var array
	 */
	public $TGroupRightsType = array(
		'read' => array(
			'code'  => 'read',
			'label' => 'WorkshopCouldRead',
			'help'  => 'WorkshopCouldReadHelp',
		),
		'edit' => array(
			'code'  => 'edit',
			'label' => 'WorkshopCouldEdit',
			'help'  => 'WorkshopCouldEditHelp',
		),
		'changeToThisStatus' => array(
			'code'  => 'changeToThisStatus',
			'label' => 'WorkshopCouldChangeToThisStatus',
			'help'  => 'WorkshopCouldChangeToThisStatusHelp',
		),
	);

	/**
	 * Constructeur
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->entity = 0; // Les statuts sont toujours sur l'entité 0
	}

	// ---------------------------------------------------------------------------
	// CRUD
	// ---------------------------------------------------------------------------

	/**
	 * Créer le statut en base et enregistrer les droits et transitions.
	 *
	 * @param  User $user      Utilisateur
	 * @param  bool $notrigger Désactiver les triggers
	 * @return int             >0 si OK, <0 si KO
	 */
	public function create(User $user, $notrigger = false)
	{
		$this->entity = 0; // toujours entity 0

		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Charger un statut depuis la base.
	 *
	 * @param  int    $id        Id
	 * @param  bool   $loadChild Charger les droits et transitions
	 * @param  string $ref       Code du statut (alternative à $id)
	 * @return int               >0 si OK, <0 si KO, 0 si non trouvé
	 */
	public function fetch($id, $loadChild = true, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);

		if ($result > 0 && $loadChild) {
			$this->fetchGroupRights();
			$this->fetchStatusAllowed();
		}

		return $result;
	}

	/**
	 * Récupérer le premier statut actif (par rang) ou celui indiqué par $id.
	 * Utile pour initialiser le statut par défaut d'un OR.
	 *
	 * @param  int $id           Id à tenter en premier
	 * @param  int $force_entity Non utilisé (statuts globaux), conservé par compatibilité
	 * @return int               rowid du statut trouvé, 0 si aucun
	 */
	public function fetchDefault($id, $force_entity = 0)
	{
		if ($id > 0) {
			$res = $this->fetch((int) $id);
			if ($res > 0) {
				return (int) $id;
			}
		}

		// Prendre le premier statut actif trié par rang
		$sql  = 'SELECT rowid FROM ' . $this->db->prefix() . $this->table_element;
		$sql .= ' WHERE status = ' . self::STATUS_ACTIVE;
		$sql .= ' ORDER BY rang ASC LIMIT 1';

		$resql = $this->db->query($sql);
		if ($resql) {
			$row = $this->db->fetch_object($resql);
			if ($row) {
				$res = $this->fetch($row->rowid);
				if ($res > 0) {
					return (int) $row->rowid;
				}
			}
		}

		return 0;
	}

	/**
	 * Mettre à jour un statut existant.
	 *
	 * @param  User $user      Utilisateur
	 * @param  bool $notrigger Désactiver les triggers
	 * @return int             >0 si OK, <0 si KO
	 */
	public function update(User $user, $notrigger = false)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Supprimer un statut et ses données liées (droits groupes + transitions).
	 *
	 * Le cascade est géré manuellement car les tables enfants n'ont pas de
	 * FK ON DELETE CASCADE et ne sont pas couvertes par deleteCommon.
	 *
	 * @param  User $user      Utilisateur
	 * @param  bool $notrigger Désactiver les triggers
	 * @return int             >0 si OK, <0 si KO
	 */
	public function delete(User $user, $notrigger = false)
	{
		if ($this->id <= 0) {
			return 0;
		}

		$this->db->begin();

		$error = 0;

		// Supprimer les droits groupes
		$sql = 'DELETE FROM ' . $this->db->prefix() . 'workshop_operationorder_status_usergroup_rights';
		$sql .= ' WHERE fk_workshop_operationorderstatus = ' . (int) $this->id;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$error++;
		}

		// Supprimer les transitions (comme source ou cible)
		if (!$error) {
			$sql = 'DELETE FROM ' . $this->db->prefix() . 'workshop_operationorder_status_target';
			$sql .= ' WHERE fk_workshop_operationorderstatus = ' . (int) $this->id;
			$sql .= ' OR fk_workshop_operationorderstatus_target = ' . (int) $this->id;
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$error++;
			}
		}

		// Supprimer le statut lui-même (+ extrafields via deleteCommon)
		if (!$error) {
			$result = $this->deleteCommon($user, $notrigger);
			if ($result <= 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Créer ou mettre à jour selon que l'id est défini.
	 *
	 * @param  User $user      Utilisateur
	 * @param  bool $notrigger Désactiver les triggers
	 * @return int             >0 si OK, <0 si KO
	 */
	public function save(User $user, $notrigger = false)
	{
		if (empty($this->id)) {
			$res = $this->create($user, $notrigger);
		} else {
			$res = $this->update($user, $notrigger);
		}

		if ($res > 0) {
			$this->updateGroupRights(true);
			$this->updateStatusAllowed(true);
		}

		return $res;
	}

	// ---------------------------------------------------------------------------
	// Gestion des droits des groupes (par entité)
	// ---------------------------------------------------------------------------

	/**
	 * Charger les droits des groupes pour l'entité courante.
	 *
	 * Les droits sont définis par entité : un groupe peut avoir le droit "read"
	 * sur l'entité 1 mais pas sur l'entité 2, pour le même statut.
	 *
	 * @param  int $entity Entité à charger (0 = utiliser $conf->entity)
	 * @return array       $TGroupCan indexé par code d'action
	 */
	public function fetchGroupRights($entity = 0)
	{
		global $conf;

		$entityFilter = ($entity > 0) ? (int) $entity : (int) $conf->entity;

		$this->TGroupCan = array();

		$sql  = 'SELECT rowid, code, fk_usergroup';
		$sql .= ' FROM ' . $this->db->prefix() . 'workshop_operationorder_status_usergroup_rights';
		$sql .= ' WHERE fk_workshop_operationorderstatus = ' . (int) $this->id;
		$sql .= ' AND entity = ' . $entityFilter;

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($row = $this->db->fetch_object($resql)) {
				$this->TGroupCan[$row->code][$row->rowid] = (int) $row->fk_usergroup;
			}
		}

		return $this->TGroupCan;
	}

	/**
	 * Vérifier si un utilisateur appartient à un groupe autorisé à effectuer $action
	 * sur ce statut pour l'entité courante.
	 *
	 * @param  User   $user   Utilisateur
	 * @param  string $action Action : 'read', 'edit', 'changeToThisStatus'
	 * @return bool
	 */
	public function userGroupCan(User $user, $action = '')
	{
		// L'admin contourne toujours les restrictions de groupe
		if (!empty($user->admin)) {
			return true;
		}

		$TUserGroups = $this->getUserGroups($user);

		foreach ($TUserGroups as $fk_group => $group) {
			if (isset ($this->TGroupCan[$action]) && in_array($fk_group, $this->TGroupCan[$action])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Vérifier si l'utilisateur peut effectuer l'action sur ce statut.
	 *
	 * @param  User   $user   Utilisateur
	 * @param  string $action Action : 'read', 'edit', 'changeToThisStatus'
	 * @return bool
	 */
	public function userCan(User $user, $action = '')
	{
		return $this->userGroupCan($user, $action);
	}

	/**
	 * Récupérer les groupes d'un utilisateur.
	 * Utilise la table llx_usergroup_user.
	 *
	 * @param  User  $user Utilisateur
	 * @return array       Tableau indexé par fk_usergroup
	 */
	protected function getUserGroups(User $user)
	{
		global $conf;

		$TGroups = array();

		$sql  = 'SELECT fk_usergroup FROM ' . $this->db->prefix() . 'usergroup_user';
		$sql .= ' WHERE fk_user = ' . (int) $user->id;
		if (getDolGlobalInt('MULTICOMPANY_TRANSVERSE_MODE')) {
			$sql .= ' AND entity IN (0, ' . (int)$conf->entity . ')';
		} else {
			$sql .= ' AND entity IN (0, ' . (int)$user->entity . ')';
		}
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($row = $this->db->fetch_object($resql)) {
				$TGroups[(int) $row->fk_usergroup] = $row->fk_usergroup;
			}
		}

		return $TGroups;
	}

	/**
	 * Synchroniser les droits des groupes en base pour l'entité courante.
	 *
	 * @param  bool $replace Supprimer les droits non présents dans $TGroupCan
	 * @param  int  $entity  Entité cible (0 = $conf->entity)
	 * @return int           1 si OK, <0 si KO
	 */
	public function updateGroupRights($replace = false, $entity = 0)
	{
		global $conf;

		if (empty($this->id)) {
			return -1;
		}

		$entityTarget = ($entity > 0) ? (int) $entity : (int) $conf->entity;

		// Sauvegarde des valeurs soumises
		$TGroupCanNew = is_array($this->TGroupCan) ? $this->TGroupCan : array();

		// Recharger l'état actuel depuis la base
		$this->fetchGroupRights($entityTarget);
		$TGroupCanCurrent = $this->TGroupCan;

		foreach ($this->TGroupRightsType as $field) {
			$code = $field['code'];

			$currentIds = array();
			if (!empty($TGroupCanCurrent[$code])) {
				$currentIds = array_values($TGroupCanCurrent[$code]);
			}

			$newIds = array();
			if (!empty($TGroupCanNew[$code])) {
				$newIds = array_map('intval', array_values($TGroupCanNew[$code]));
			}

			$toAdd = array_diff($newIds, $currentIds);
			$toDel = array_diff($currentIds, $newIds);

			if (!empty($toAdd)) {
				$insertRows = array();
				foreach ($toAdd as $fk_usergroup) {
					$insertRows[] = '(\'' . $this->db->escape($code) . '\', '
						. (int) $this->id . ', '
						. (int) $fk_usergroup . ', '
						. (int) $entityTarget . ')';
				}
				$sql  = 'INSERT INTO ' . $this->db->prefix() . 'workshop_operationorder_status_usergroup_rights';
				$sql .= ' (code, fk_workshop_operationorderstatus, fk_usergroup, entity)';
				$sql .= ' VALUES ' . implode(', ', $insertRows);

				if (!$this->db->query($sql)) {
					$this->error = $this->db->lasterror();
					return -2;
				}
			}

			if (!empty($toDel) && $replace) {
				$toDel = array_map('intval', $toDel);
				$sql  = 'DELETE FROM ' . $this->db->prefix() . 'workshop_operationorder_status_usergroup_rights';
				$sql .= ' WHERE code = \'' . $this->db->escape($code) . '\'';
				$sql .= ' AND fk_workshop_operationorderstatus = ' . (int) $this->id;
				$sql .= ' AND fk_usergroup IN (' . implode(', ', $toDel) . ')';
				$sql .= ' AND entity = ' . $entityTarget;

				if (!$this->db->query($sql)) {
					$this->error = $this->db->lasterror();
					return -2;
				}
			}
		}

		// Remettre les nouvelles valeurs dans $TGroupCan
		$this->TGroupCan = $TGroupCanNew;

		return 1;
	}

	// ---------------------------------------------------------------------------
	// Gestion des transitions (entity=0, partagées)
	// ---------------------------------------------------------------------------

	/**
	 * Charger les transitions autorisées depuis ce statut.
	 * Les transitions sont sur entity=0 et partagées par toutes les entités.
	 *
	 * @return array $TStatusAllowed[rowid_ligne] = fk_target
	 */
	public function fetchStatusAllowed()
	{
		$this->TStatusAllowed = array();

		$sql  = 'SELECT rowid, fk_workshop_operationorderstatus_target';
		$sql .= ' FROM ' . $this->db->prefix() . 'workshop_operationorder_status_target';
		$sql .= ' WHERE fk_workshop_operationorderstatus = ' . (int) $this->id;

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($row = $this->db->fetch_object($resql)) {
				$this->TStatusAllowed[$row->rowid] = (int) $row->fk_workshop_operationorderstatus_target;
			}
		}

		return $this->TStatusAllowed;
	}

	/**
	 * Synchroniser les transitions autorisées en base.
	 *
	 * @param  bool $replace Supprimer les transitions non présentes dans $TStatusAllowed
	 * @return int           1 si OK, <0 si KO
	 */
	public function updateStatusAllowed($replace = false)
	{
		if (empty($this->id)) {
			return -1;
		}

		$TStatusAllowedNew = is_array($this->TStatusAllowed) ? array_map('intval', array_values($this->TStatusAllowed)) : array();

		// Recharger l'état actuel
		$this->fetchStatusAllowed();
		$TStatusAllowedCurrent = array_values($this->TStatusAllowed);

		$toAdd = array_diff($TStatusAllowedNew, $TStatusAllowedCurrent);
		$toDel = array_diff($TStatusAllowedCurrent, $TStatusAllowedNew);

		if (!empty($toAdd)) {
			$insertRows = array();
			foreach ($toAdd as $fk_target) {
				$insertRows[] = '(' . (int) $this->id . ', ' . (int) $fk_target . ')';
			}
			$sql  = 'INSERT INTO ' . $this->db->prefix() . 'workshop_operationorder_status_target';
			$sql .= ' (fk_workshop_operationorderstatus, fk_workshop_operationorderstatus_target)';
			$sql .= ' VALUES ' . implode(', ', $insertRows);

			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -2;
			}
		}

		if (!empty($toDel) && $replace) {
			$toDel = array_map('intval', $toDel);
			$sql  = 'DELETE FROM ' . $this->db->prefix() . 'workshop_operationorder_status_target';
			$sql .= ' WHERE fk_workshop_operationorderstatus = ' . (int) $this->id;
			$sql .= ' AND fk_workshop_operationorderstatus_target IN (' . implode(', ', $toDel) . ')';

			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -2;
			}
		}

		$this->TStatusAllowed = array_flip(array_flip($TStatusAllowedNew)); // reindex

		return 1;
	}

	// ---------------------------------------------------------------------------
	// Activation / Désactivation
	// ---------------------------------------------------------------------------

	/**
	 * Activer ce statut.
	 *
	 * @param  User $user      Utilisateur
	 * @param  int  $notrigger Désactiver les triggers
	 * @return int
	 */
	public function setActive(User $user, $notrigger = 0)
	{
		$sql  = 'UPDATE ' . $this->db->prefix() . $this->table_element;
		$sql .= ' SET status = ' . self::STATUS_ACTIVE;
		$sql .= ' WHERE rowid = ' . (int) $this->id;

		if ($this->db->query($sql)) {
			$this->status = self::STATUS_ACTIVE;
			return 1;
		}
		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Désactiver ce statut.
	 *
	 * @param  User $user      Utilisateur
	 * @param  int  $notrigger Désactiver les triggers
	 * @return int
	 */
	public function setDisabled(User $user, $notrigger = 0)
	{
		$sql  = 'UPDATE ' . $this->db->prefix() . $this->table_element;
		$sql .= ' SET status = ' . self::STATUS_DISABLED;
		$sql .= ' WHERE rowid = ' . (int) $this->id;

		if ($this->db->query($sql)) {
			$this->status = self::STATUS_DISABLED;
			return 1;
		}
		$this->error = $this->db->lasterror();
		return -1;
	}

	// ---------------------------------------------------------------------------
	// Vérification des transitions
	// ---------------------------------------------------------------------------

	/**
	 * Vérifier qu'un utilisateur peut passer l'OR au statut $newStatusId
	 * depuis le statut courant ($this).
	 *
	 * Deux conditions doivent être remplies :
	 * 1. La transition est autorisée (llx_workshop_operationorder_status_target)
	 * 2. L'utilisateur appartient à un groupe autorisé à "changeToThisStatus"
	 *    pour le nouveau statut, dans l'entité courante.
	 *
	 * @param  User $user        Utilisateur
	 * @param  int  $newStatusId Rowid du nouveau statut
	 * @return bool
	 */
	public function checkStatusTransition(User $user, $newStatusId)
	{
		if (empty($newStatusId)) {
			return false;
		}

		// Vérifier que la transition est autorisée
		if (!in_array((int) $newStatusId, $this->TStatusAllowed)) {
			return false;
		}

		// Vérifier que l'utilisateur a le droit de passer à ce statut
		$newStatus = new self($this->db);
		$res = $newStatus->fetch((int) $newStatusId);
		if ($res > 0 && $newStatus->userCan($user, 'changeToThisStatus')) {
			return true;
		}

		return false;
	}

	/**
	 * Version statique de checkStatusTransition.
	 *
	 * @param  User $user           Utilisateur
	 * @param  int  $currentStatusId Rowid du statut courant
	 * @param  int  $newStatusId     Rowid du nouveau statut
	 * @return bool
	 */
	public static function staticCheckStatusTransition(User $user, $currentStatusId, $newStatusId)
	{
		global $db;

		$object = new self($db);
		$res = $object->fetch((int) $currentStatusId);
		if ($res > 0) {
			return $object->checkStatusTransition($user, $newStatusId);
		}

		return false;
	}

	// ---------------------------------------------------------------------------
	// Lecture en liste
	// ---------------------------------------------------------------------------

	/**
	 * Récupérer tous les statuts.
	 *
	 * @param  int   $limit     Limite
	 * @param  bool  $loadChild Charger droits et transitions
	 * @param  array $TFilter   Filtres additionnels (ex: ['status' => 1])
	 * @return WorkshopOperationOrderStatus[]
	 */
	public function fetchAll($limit = 0, $loadChild = true, $TFilter = array())
	{
		$TRes = array();

		$sql  = 'SELECT rowid FROM ' . $this->db->prefix() . $this->table_element . ' WHERE 1';

		foreach ($TFilter as $field => $value) {
			$sql .= ' AND ' . $this->db->sanitize($field) . ' = ' . (is_string($value) ? '\'' . $this->db->escape($value) . '\'' : (int) $value);
		}

		$sql .= ' ORDER BY rang ASC';

		if ($limit > 0) {
			$sql .= ' LIMIT ' . (int) $limit;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$o = new self($this->db);
				$o->fetch((int) $obj->rowid, $loadChild);
				$TRes[(int) $obj->rowid] = $o;
			}
		}

		return $TRes;
	}

	// ---------------------------------------------------------------------------
	// Ordre d'affichage (drag & drop)
	// ---------------------------------------------------------------------------

	/**
	 * Mettre à jour le rang d'un statut.
	 *
	 * @param  int $rowid Identifiant
	 * @param  int $rank  Nouveau rang
	 * @return bool
	 */
	public static function updateRank($rowid, $rank)
	{
		global $db;

		$sql  = 'UPDATE ' . $db->prefix() . 'workshop_operationorder_status';
		$sql .= ' SET rang = ' . (int) $rank;
		$sql .= ' WHERE rowid = ' . (int) $rowid;

		if (!$db->query($sql)) {
			dol_print_error($db);
			return false;
		}

		return true;
	}

	// ---------------------------------------------------------------------------
	// Affichage HTML
	// ---------------------------------------------------------------------------

	/**
	 * Retourner le badge coloré du statut (lien vers la fiche).
	 *
	 * @param  int    $withpicto  Ajouter le picto (non utilisé, conservé pour compatibilité)
	 * @param  string $moreparams Paramètres supplémentaires dans l'URL
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $moreparams = '')
	{
		return $this->getBadge($this->getCardUrl($moreparams));
	}

	/**
	 * URL de la fiche d'un statut.
	 *
	 * @param  string $moreparams Paramètres supplémentaires
	 * @return string
	 */
	public function getCardUrl($moreparams = '')
	{
		return dol_buildpath('/workshop/operationorder/or_status_card.php', 1) . '?id=' . $this->id . urlencode($moreparams);
	}

	/**
	 * Retourner le badge HTML coloré du statut.
	 *
	 * @param  string $url URL du lien (vide = badge sans lien)
	 * @return string
	 */
	public function getBadge($url = '')
	{
		$color  = $this->color ?: '#3c8dbc';
		$isDark = !colorIsLight($color);

		$params = array(
			'css'  => 'badge-status',
			'attr' => array(
				'style' => 'background-color: ' . $color . '; color: ' . ($isDark ? '#ffffff' : '#000000') . ';',
			),
		);

		return dolGetBadge($this->label, '', '', '', $url, $params);
	}

	/**
	 * Libellé du statut actif/désactivé de l'objet statut lui-même.
	 *
	 * @param  int $mode 0=label long, 1=label court, 2=picto+label court, etc.
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		global $langs;
		$langs->load('workshop@workshop');

		if ($this->status == self::STATUS_ACTIVE) {
			return dolGetStatus($langs->trans('WorkshopORStatusActive'), $langs->trans('WorkshopORStatusActive'), '', 'status4', $mode);
		}
		return dolGetStatus($langs->trans('WorkshopORStatusDisabled'), $langs->trans('WorkshopORStatusDisabled'), '', 'status9', $mode);
	}

	/**
	 * Version statique de getNomUrl.
	 *
	 * @param  int    $id         Identifiant
	 * @param  string $ref        Code (alternative à $id)
	 * @param  int    $withpicto  Ajouter le picto
	 * @param  string $moreparams Paramètres supplémentaires
	 * @return string
	 */
	public static function getStaticNomUrl($id, $ref = null, $withpicto = 0, $moreparams = '')
	{
		global $db;

		$object = new self($db);
		$object->fetch((int) $id, false, $ref);

		return $object->getNomUrl($withpicto, $moreparams);
	}

	/**
	 * Retourner le champ label pour getNomUrl() Dolibarr standard.
	 *
	 * @return string
	 */
	public function getLabelFields()
	{
		return 'label';
	}
}


/**
 * Classe pivot pour les droits groupes d'un statut par entité.
 * Ligne de la table llx_workshop_operationorder_status_usergroup_rights.
 */
class WorkshopOperationOrderStatusUserGroupRight extends CommonObject
{
	/** @var string */
	public $table_element = 'workshop_operationorder_status_usergroup_rights';
	/** @var string */
	public $element = 'workshopoperationorderstatususergroupright';

	public $fields = array(
		'fk_workshop_operationorderstatus' => array('type' => 'integer'),
		'code'       => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_usergroup' => array('type' => 'integer'),
		'entity'     => array('type' => 'integer'),
	);

	public $fk_workshop_operationorderstatus;
	public $code;
	public $fk_usergroup;
	public $entity;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	public function getNomUrl($param = '')
	{
		return '';
	}
}


/**
 * Classe pivot pour les transitions entre statuts.
 * Ligne de la table llx_workshop_operationorder_status_target.
 */
class WorkshopOperationOrderStatusTarget extends CommonObject
{
	/** @var string */
	public $table_element = 'workshop_operationorder_status_target';
	/** @var string */
	public $element = 'workshopoperationorderstatustarget';

	public $fields = array(
		'fk_workshop_operationorderstatus'        => array('type' => 'integer'),
		'fk_workshop_operationorderstatus_target' => array('type' => 'integer'),
	);

	public $fk_workshop_operationorderstatus;
	public $fk_workshop_operationorderstatus_target;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	public function getNomUrl($param = '')
	{
		return '';
	}
}
