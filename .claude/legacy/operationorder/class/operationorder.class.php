<?php
/*
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

if (!class_exists('SeedObject')) {
	/**
	 * Needed if $form->showLinkedObjectBlock() is call or for session timeout on our module page
	 */
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__) . '/../config.php';
}

require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once __DIR__ . '/unitstools.class.php';
require_once __DIR__ . '/operationorderstatus.class.php';
require_once __DIR__ . '/operationorderaction.class.php';
require_once __DIR__ . '/operationorderhistory.class.php';
require_once __DIR__ . '/productdefaultwarehouse.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';


/**
 * Class OperationOrder
 */
class OperationOrder extends SeedObject
{
	/** @var string $table_element Table name in SQL */
	public $table_element = 'operationorder';

	/** @var string $element Name of the element (tip for better integration in Dolibarr: this value should be the reflection of the class name with ucfirst() function) */
	public $element = 'operationorder';

	/** @var string $origin_type for compatibily with stock mouvement class */
	public $origin_type = 'operationorder@operationorder';

	/** @var int $isextrafieldmanaged Enable the fictionalises of extrafields */
	public $isextrafieldmanaged = 1;

	/** @var int $ismultientitymanaged 0=No test on entity, 1=Test with field entity, 2=Test with link by societe */
	public $ismultientitymanaged = 1;

	/** @var $objStatus OperationOrderStatus used for cache */
	public $objStatus;

	/** @var string $picto a picture file in [@...]/img/object_[...@].png */
	public $picto = 'operationorder@operationorder';

	/**
	 *  'type' is the field format.
	 *  'label' the translation key.
	 *  'enabled' is a condition when the field must be managed.
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'default' is a default value for creation (can still be replaced by the global setup of default values)
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
	 *  'position' is the sort order of field.
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 if you want to have a total on list for this field. Field type must be summable like integer or double(24,8).
	 *  'css' is the CSS style to use on field. For example: 'maxwidth200'
	 *  'help' is a string visible as a tooltip on field
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'arraykeyval' to set list of value if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel")
	 */

	public $fields = array(
		// affichage dans la baniere
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 110, 'notnull' => 1, 'visible' => 4, 'noteditable' => '1', 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => '1', 'comment' => "Reference of object"),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php:0:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))', 'label' => 'ThirdParty', 'enabled' => 'isModEnabled("societe")', 'visible' => -1, 'notnull' => 1, 'position' => 20, 'help' => "LinkToThirparty"),
		'ref_client' => array('type' => 'varchar(128)', 'label' => 'RefCustomer', 'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 1),
		'status' => array('type' => 'int', 'label' => 'Status', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 2, 'index' => 1, 'arrayofkeyval' => array(-1 => 'OperationOrderStatusShortCanceled', 0 => 'OperationOrderStatusShortDraft', 1 => 'OperationOrderStatusShortValidated')),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 160, 'notnull' => 1, 'visible' => 0,),
		//Affichage sur la card 1ere colonne
		'orcheck' => array('type' => 'integer', 'label' => 'CheckOR', 'enabled' => 1, 'position' => 1000, 'notnull' => 1, 'visible' => 0, 'arrayofkeyval' => array(0 => 'ORchecknotdone', 1 => 'ORcheckfailled', 2 => 'ORcheckPassed'), 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreationOperationOrder', 'enabled' => 1, 'position' => 1100, 'notnull' => 1, 'visible' => 4, 'noteditable' => '1'),
		'date_operation_order' => array('type' => 'datetime', 'label' => 'DateOperationOrder', 'enabled' => 1, 'position' => 1200, 'notnull' => 1, 'visible' => 1, 'noteditable' => 0),
		'planned_date' => array('type' => 'datetime', 'label' => 'PlannedDate', 'enabled' => 1, 'position' => 1400, 'notnull' => 0, 'visible' => 1),
		'time_planned_t' => array('type' => 'duration', 'label' => 'TimePlannedTheoretical', 'enabled' => 1, 'position' => 1500, 'notnull' => 1, 'visible' => 4, 'default' => 0, 'noteditable' => 1, 'help' => "HoursMinFormat"),
		'time_planned_f' => array('type' => 'duration', 'label' => 'TimePlannedForced', 'enabled' => 1, 'position' => 1600, 'notnull' => 0, 'visible' => 1, 'help' => "HoursMinFormat"),
		'fk_user_meca' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Mecanics', 'enabled' => 1, 'position' => 1700, 'notnull' => 0, 'visible' => 1,),
		'total_ht_mo' => array('type' => 'real', 'label' => 'TotalHTMO', 'enabled' => 1, 'position' => 1800, 'notnull' => 1, 'required' => 0, 'visible' => 5, 'noteditable' => '1', 'default' => '0'),
		'total_ht_part' => array('type' => 'real', 'label' => 'TotalHTPart', 'enabled' => 1, 'position' => 1900, 'notnull' => 1, 'required' => 0, 'visible' => 5, 'noteditable' => '1', 'default' => '0'),
		'total_ht_service' => array('type' => 'real', 'label' => 'TotalHTService', 'enabled' => 1, 'position' => 1950, 'notnull' => 1, 'required' => 0, 'visible' => 5, 'noteditable' => '1', 'default' => '0'),
		'total_ht_external' => array('type' => 'real', 'label' => 'TotalHTExternal', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'required' => 0, 'visible' => 5, 'noteditable' => '1', 'default' => '0'),
		'total_ht_reimbursement' => array('type' => 'real', 'label' => 'TotalHTReimbursement', 'enabled' => 1, 'position' => 2100, 'notnull' => 1, 'required' => 0, 'visible' => 5, 'noteditable' => '1', 'default' => '0'),
		'total_ht' => array('type' => 'real', 'label' => 'TotalHT', 'enabled' => 1, 'position' => 2200, 'notnull' => 1, 'required' => 0, 'visible' => 5, 'noteditable' => '1', 'default' => '0'),
		//Affichage sur la card 2eme colonne
		'fk_vehicule' => array('type' => 'integer:Vehicule:dolifleet/class/vehicule.class.php', 'label' => 'Vehicule', 'visible' => 1, 'enabled' => 1, 'position' => 5000, 'index' => 1, 'notnull' => 1),
		'fk_conducteur' => array('type' => 'integer:Contact:contact/class/contact.class.php', 'label' => 'Driver', 'enabled' => 1, 'position' => 5100, 'notnull' => 0, 'visible' => 1,),
		'km_on_creation' => array('type' => 'integer', 'label' => 'km_on_creation', 'enabled' => 1, 'position' => 5200, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'date_cloture' => array('type' => 'datetime', 'label' => 'DateClose', 'enabled' => 1, 'position' => 5300, 'notnull' => 0, 'visible' => 4, 'noteditable' => 1),
		'categories' => array('type' => 'varchar(255)', 'label' => 'categories', 'enabled' => 1, 'position' => 5600, 'notnull' => 0, 'visible' => 0, 'arrayofkeyval' => array(0 => 'depannage', 1 => 'travaux exterieurs', 2 => 'véhicule non présenté')),
		// Affichage géré dans un onglet
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => 1, 'position' => 1, 'notnull' => 0, 'visible' => 0),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 2, 'notnull' => 0, 'visible' => 0),
		'date_valid' => array('type' => 'datetime', 'label' => 'DateValid', 'enabled' => 1, 'position' => 3, 'notnull' => 0, 'visible' => 0,),
		// données non affcihées
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 4, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid',),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 5, 'notnull' => 0, 'visible' => 0,),
		'fk_user_valid' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserValid', 'enabled' => 1, 'position' => 6, 'notnull' => 0, 'visible' => 0,),
		'fk_user_cloture' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserClose', 'enabled' => 1, 'position' => 7, 'notnull' => 0, 'visible' => 0,),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 8, 'notnull' => -1, 'visible' => -2,),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'Model pdf', 'enabled' => 1, 'position' => 9, 'notnull' => -1, 'visible' => 0,),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => 1, 'position' => 10, 'notnull' => 0, 'visible' => 0,),


	);

	public $ref;
	public $ref_client;
	public $fk_soc;
	public $date_valid;
	public $date_cloture;
	public $date_operation_order;
	public $note_public;
	public $note_private;
	public $fk_user_creat;
	public $fk_user_modif;
	public $fk_user_valid;
	public $fk_user_cloture;
	public $import_key;
	public $model_pdf;
	public $modelpdf;
	/** @see $model_pdf */
	public $status;
	public $last_main_doc;
	public $entity;
	public $overshot;
	public $time_planned_t;
	public $time_planned_f;
	public $planned_date;
	public $total_ht_reimbursement;
	public $total_ht_part;
	public $total_ht_service;
	public $total_ht_external;
	public $total_ht_mo;
	public $total_ht;
	public $orcheck;
	public $fk_user_meca;
	public $categories;
	public $fk_vehicule;
	public $km_on_creation;
	public $notetheobald;
	public $orgds;

	/**
	 * @var int    Name of subtable line
	 */
	public $table_element_line = 'operationorderdet';

	/**
	 * @var int    Field with ID of parent key if this field has a parent
	 */
	public $fk_element = 'fk_operation_order';

	/**
	 * @var int    Name of subtable class that manage subtable lines
	 */
	public $class_element_line = 'operationorderLine';

	/**
	 * @var array    List of child tables. To test if we can delete object.
	 */
	protected $childtables = array('operationorderdet' => 'operationorderLine');

	/**
	 * @var operationorderLine[] $lines Array of subtable lines
	 */
	public $lines = array();
	/**
	 * @var TOperationOrderLine[] $TOperationOrderLine Array of subtable lines
	 */
	public $TOperationOrderLine = array();

	const OR_ALL_STOCK_NOT_ENOUGH = -2;
	const OR_ONLY_PHYSICAL_STOCK_NOT_ENOUGH = -1;
	const OR_STOCK_IS_ENOUGH = 1;

	/**
	 * @var array    Cache form planning schedule
	 */
	public $planningSchedulCache = array();

	/**
	 * OperationOrder constructor.
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		global $conf;

		parent::__construct($db);

		$this->init();

		$this->status = 0;
		$this->entity = $conf->entity;
		$this->date_cloture = null;
		$this->lines = &$this->TOperationOrderLine;
		$this->modelpdf = &$this->model_pdf;
		$this->socid = $this->fk_soc; // Compatibility with select ajax on formadd product
		$this->statut = &$this->status; // Compatibility with select ajax on formadd product
	}

	/**
	 * @param User $user User object
	 * @param bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int
	 */
	public function save($user, $notrigger = false)
	{
		$this->time_planned_t = $this->getTimePlannedT();
		$resTotCalc = $this->calcTotal($user);
		if ($resTotCalc < 0) return $resTotCalc;
		return $this->create($user, $notrigger);
	}

	/**
	 * Function to create object in database
	 *
	 * @param User $user user object
	 * @param bool $notrigger false=launch triggers after, true=disable triggers
	 * @return  int                < 0 if ko, > 0 if ok
	 */
	public function create(User &$user, $notrigger = false)
	{
		global $conf;
		if (!empty($this->is_clone)) {
			// TODO determinate if auto generate
			// $this->ref = '(PROV'.$this->id.')';
			$this->ref = $this->getNextNumRef();
			$this->orcheck = 0;
			// $this->fk_user_valid = $user->id;
		}

		$needCreate = empty($this->id);
		if ($needCreate) {
			if (getDolGlobalInt('OPODER_STATUS_ON_CREATE')) {
				// Set status by default conf
				$this->status = getDolGlobalInt('OPODER_STATUS_ON_CREATE');
			} else {
				if (empty($this->entity)) {
					$this->entity = $conf->entity;
				}

				$status = new Operationorderstatus($this->db);
				$res = $status->fetchDefault($this->status, $this->entity);
				if ($res > 0) {
					$this->status = $status->id;
				} else {
					return -1;
				}
			}
		}

		if ($this->id > 0) {
			return $this->updateCommon($user, $notrigger);
		} else {
			$id = parent::create($user, $notrigger);
			if ($needCreate && $id > 0) {
				$oOHistory = new OperationOrderHistory($this->db);
				$oOHistory->saveCreationOrDeletion($this);
			}
		}
		return $id;
	}

	/**
	 * @param User $user object
	 * @param bool $notrigger false=launch triggers after, true=disable triggers
	 * @return  int
	 */
	public function cloneObject($user, $notrigger = false)
	{
		global $conf;

		$this->clear();
		$this->is_clone = 1;

		$result = $this->create($user, $notrigger);

		if ($result > 0) {
			if (!empty($this->is_clone) && !empty(getDolGlobalString("OPODER_STATUS_ON_CREATE"))) {
				// Set status by default conf
				$this->setStatus($user, getDolGlobalInt("OPODER_STATUS_ON_CREATE"));
			}


			if (!empty($this->lines)) {
				foreach ($this->lines as $i => $line) {
					if (empty($line->fk_parent_line)) {
						$lineNeedUpdate = false;

						// search new price
						if (!empty($line->fk_product)) {
							$product = new Product($this->db);
							$res = $product->fetch($line->fk_product);
							if ($res) {
								$lineNeedUpdate = true;
							}
						}

						// Update line if needed
						if ($lineNeedUpdate) {
							$this->updateline(
								$line->id,
								$line->description,
								$line->qty,
								$product->price,
								$line->fk_warehouse,
								$line->pr,
								$line->time_planned,
								$line->time_spent,
								$line->fk_product,
								$line->info_bits,
								$line->date_start,
								$line->date_end,
								$line->type,
								$line->fk_parent_line,
								$line->label,
								$line->special_code,
								$line->array_options
							);
						}
						// Add others products for lines
						$this->recurciveAddChildLines($line->id, $line->fk_product, $line->qty);
					}
				}
			}
		}

		return $result;
	}


	/**
	 * @return string
	 * @deprecated
	 */
	public function getSocName()
	{
		$sql = "SELECT nom FROM " . $this->db->prefix() . "societe WHERE rowid = " . $this->fk_soc;
		$resql = $this->db->query($sql);
		if (!empty($resql)) {
			$obj = $this->db->fetch_object($resql);
			return $obj->nom;
		}
		return '';
	}

	/**
	 * Function to update object or create or delete if needed
	 *
	 * @param User $user user object
	 * @param bool $notrigger false=launch triggers after, true=disable triggers
	 * @return  int                < 0 if ko, > 0 if ok
	 */
	public function update(User &$user, $notrigger = false)
	{
		$this->time_planned_t = $this->getTimePlannedT();
		$resTotCalc = $this->calcTotal($user, 1);
		if ($resTotCalc < 0) return $resTotCalc;

		$res = $this->updateOperationOrderActions();
		if ($res < 0) return -1;

		if (!empty($this->oldcopy)) {
			$oOHistory = new OperationOrderHistory($this->db);
			$oOHistory->compareAndSaveDiff($this->oldcopy, $this);
		}

		//Avoid php warning (update an object is not possible)
		$dataFieldObjStatus = array();
		if (is_array($this->fields) && array_key_exists('objStatus', $this->fields) && is_object($this->objStatus)) {
			$dataFieldObjStatus = $this->fields['objStatus'];
			unset($this->fields['objStatus']);
		}
		$result = parent::update($user, $notrigger); // TODO: Change the autogenerated stub

		if (!empty($dataFieldObjStatus)) {
			$this->fields['objStatus'] = $dataFieldObjStatus;
		}

		return $result;
	}

	/**
	 * @param $Tab tab
	 * @return int
	 */
	public function setValues(&$Tab)
	{
		$TFields = array('time_planned_t', 'time_planned_f');

		foreach ($Tab as $key => $value) {
			if (in_array($key, $TFields)) {
				if (strstr($value, ':')) {
					$THourMin = explode(':', $value);
					$Tab[$key] = convertTime2Seconds($THourMin[0], $THourMin[1]);
				}
			}
		}

		if (strstr($this->time_planned_f, ':')) {
			$THourMin = explode(':', $this->time_planned_f);
			$this->time_planned_f = convertTime2Seconds($THourMin[0], $THourMin[1]);
		}

		// TODO: Change the autogenerated stub
		$result = parent::setValues($Tab);

		if ($result > 0) {
			foreach ($Tab as $key => $value) {
				if (array_key_exists($key, $this->fields) && $this->fields[$key]['type'] == 'datetime') {
					if (!empty($value)) {
						$value .= ' ' . $Tab[$key . 'hour'] . ':' . $Tab[$key . 'min'] . ':' . $Tab[$key . 'sec'];
					}
					$this->setDate($key, $value);
				}
			}
		}

		return $result;
	}


	/**
	 *    Get object and children from database
	 *
	 * @param int $id Id of object to load
	 * @param bool $loadChild used to load children from database
	 * @param string $ref Ref
	 * @return     int                        >0 if OK, <0 if KO, 0 if not found
	 */
	public function fetch($id, $loadChild = true, $ref = null)
	{
		$res = parent::fetch($id, $loadChild, $ref);

		//TODO review exit from abricot
		if (isset($this->ToperationorderLine)) {
			$this->lines = $this->ToperationorderLine;
		} else {
			$this->lines = [];
		}

		$this->socid = $this->fk_soc;

		usort($this->TOperationOrderLine, function ($a, $b) {
			return $a->rang - $b->rang;
		});

		$this->fetch_thirdparty();
		if (empty($this->objStatus)) $this->loadStatusObj();
		$this->oldcopy = clone $this;
		return $res;
	}

	/**
	 * @param $loadProduct boolean
	 * @param $parentLineId integer Parent Line rowid
	 * @return void
	 */
	public function fetchLines($loadProduct = true, $parentLineId = 0)
	{
		$TNested = $this->fetch_all_children_nested($parentLineId, $loadProduct);
		$this->lines = array();
		$this->fetchNestedLines($TNested);
	}

	/**
	 * @param $TNested Array
	 * @param $level deep level
	 * @return void
	 */
	public function fetchNestedLines($TNested, $level = 0)
	{
		if (!empty($TNested) && is_array($TNested)) {
			foreach ($TNested as $k => $v) {
				$v['object']->level = $level;
				$this->lines[] = $v['object'];
				$this->fetchNestedLines($v['children'], $level + 1);
			}
		}
	}

	/**
	 * @param $code code
	 * @return bool
	 */
	public function checkNegativeProductVentilation($code)
	{
		global $langs;
		$ok = false;

		if (!empty($this->fk_c_operationorder_type) && !empty($code)) {
			$oOrderType = new OperationOrderDictType($this->db);
			$res = $oOrderType->fetch($this->fk_c_operationorder_type);
			if ($res > 0 && !empty($oOrderType->blocked_status_code) && $oOrderType->blocked_status_code == $code) {
				if (empty($this->lines)) $this->fetchLines();
				if (!empty($this->lines)) {
					foreach ($this->lines as $line) {
						if (empty($line->product)) $line->fetch_product();
						if (empty($line->product->array_options)) $line->product->fetch_optionals();
						if (!empty($line->product->array_options['options_oorder_ventilation_produit']) && $line->total_ht < 0) {
							$ok = true;
						}
					}
				}
			} else $ok = true;
		} else $ok = true;

		if (!$ok) setEventMessage($langs->trans('MissingNegativeProductVentilationLine'), 'warnings');

		return $ok;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps

	/**
	 * Load object in memory from database
	 *
	 * @param int $fk_parent_line object
	 * @param boolean $loadProduct bool
	 * @return array array of object
	 */
	public function fetch_all_children_nested($fk_parent_line = 0, $loadProduct = true)
	{

		$TNested = array();

		$sql = "SELECT";
		$sql .= " line.rowid,";
		$sql .= " line.rang,";
		$sql .= " line.fk_parent_line,";
		$sql .= " line.fk_product";
		$sql .= " FROM " . $this->db->prefix() . "operationorderdet as line";
		$sql .= " WHERE line.fk_operation_order=" . intval($this->id);
		if (empty($fk_parent_line)) {
			$sql .= " AND ( line.fk_parent_line = 0 OR line.fk_parent_line IS NULL ) ";
		} else {
			$sql .= " AND line.fk_parent_line=" . intval($fk_parent_line);
		}

		$sql .= " ORDER BY line.rang ASC";

		dol_syslog(get_class($this) . "::fetch_all_children_nested", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;

			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);

				$line = new operationorderLine($this->db);
				$line->fetch($obj->rowid, true, null, $loadProduct);

				$TNested[$i] = array(
					'object' => $line,
					'children' => $this->fetch_all_children_nested($obj->rowid)
				);
				$i++;
			}
			$this->db->free($resql);

			return $TNested;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(get_class($this) . "::fetch " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * @return void
	 * @see cloneObject
	 */
	public function clearUniqueFields()
	{
	}

	/**
	 * @param $user User USer Action
	 * @param $lineid line id
	 * @return int
	 */
	public function deleteline(User $user, $lineid = 0)
	{

		$this->db->begin();
		$line = new operationorderLine($this->db);

		// For triggers
		$line->fetch($lineid);
		$fk_parent_line = $line->fk_parent_line;

		if ($line->delete($user) > 0) {
			$this->setTimePlannedT();
			//          if (!empty($fk_parent_line)) {
			//
			//              foreach ($this->TOperationOrderLine as $det) {
			//                  if ($det->id == $fk_parent_line) {
			//                      $resultUpdPL = $this->calcTotalLine($user, $det);
			//                      if ($resultUpdPL < 0) {
			//                          dol_syslog(get_class($this) . "::deleteline error=" . $this->error, LOG_ERR);
			//                          $this->db->rollback();
			//                          return -2;
			//                      }
			//                      break;
			//                  }
			//              }
			//          }

			$this->db->commit();
			return 1;
		} else {
			$this->error = $line->error;
			$this->errors = $line->errors;
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 * @param User $user User object
	 * @param bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int
	 */
	public function delete(User &$user, $notrigger = false)
	{
		$this->deleteObjectLinked();
		$res = $this->deleteORAction();

		$oOHistory = new OperationOrderHistory($this->db);
		$oOHistory->saveCreationOrDeletion($this, 'delete');

		if ($res < 0) return -1;

		$opdet = new operationorderLine($this->db);
		$this->childtables = array($opdet->table_element);
		return parent::deleteCommon($user, $notrigger, 1);
	}

	/**
	 * @return string
	 */
	public function getRef()
	{
		if (preg_match('/^[\(]?PROV/i', $this->ref) || empty($this->ref)) {
			return $this->getNextNumRef();
		}

		return $this->ref;
	}

	/**
	 * @param $fk_operationorder int
	 * @return string
	 */
	public static function getStaticRef($fk_operationorder)
	{
		global $db;

		$sql = "SELECT ref FROM " . $db->prefix() . "operationorder WHERE rowid = " . $fk_operationorder;
		$resql = $db->query($sql);
		if (!empty($resql)) {
			$obj = $db->fetch_object($resql);
			return $obj->ref;
		}
		return '';
	}

	/**
	 *  Returns the reference to the following non used object depending on the active numbering module.
	 *
	 * @return string            Object free reference
	 */
	public function getNextNumRef()
	{
		global $langs, $conf;
		$langs->load("operationorder@operationorder");

		if (empty(getDolGlobalString("OPERATIONORDER_ADDON"))) {
			$conf->global->OPERATIONORDER_ADDON = 'mod_operationorder_standard';
		}

		if (!empty(getDolGlobalString("OPERATIONORDER_ADDON"))) {
			$mybool = false;

			$file = getDolGlobalString("OPERATIONORDER_ADDON") . ".php";
			$classname = getDolGlobalString("OPERATIONORDER_ADDON");

			// Include file with class
			$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
			foreach ($dirmodels as $reldir) {
				$dir = dol_buildpath($reldir . "core/modules/operationorder/");

				// Load file with numbering class (if found)
				$mybool |= @include_once $dir . $file;
			}

			if ($mybool === false) {
				dol_print_error('', "Failed to include file " . $file);
				return '';
			}

			$obj = new $classname();
			$numref = $obj->getNextValue($this);

			if ($numref != "") {
				return $numref;
			} else {
				$this->error = $obj->error;
				//dol_print_error($this->db,get_class($this)."::getNextNumRef ".$obj->error);
				return "";
			}
		} else {
			print $langs->trans("Error") . " " . $langs->trans("Error_OPERATIONORDER_ADDON_NotDefined");
			return "";
		}
	}

	/**
	 * @param $user User
	 * @return bool
	 */
	public function isEditable($user)
	{
		return $this->userCan($user, 'edit');
	}

	/**
	 * @param $user User
	 * @param string $action action
	 * @return bool
	 */
	public function userCan($user, $action = '')
	{

		if ($this->loadStatusObj()) {
			return $this->objStatus->userCan($user, $action);
		}

		return false;
	}


	/**
	 * @param bool $forceReload false = use cache ; true = force reload status
	 * @return bool
	 */
	public function loadStatusObj($forceReload = false)
	{

		if (empty($this->objStatus) || is_object($this->objStatus) || $forceReload) {
			$this->objStatus = new Operationorderstatus($this->db);
			$res = $this->objStatus->fetchDefault($this->status, $this->entity);
			if ($res > 0) {
				return true;
			}
		} elseif ($this->status != $this->objStatus->id) {
			return $this->loadStatusObj(true);
		}

		return true;
	}


	/**
	 *    Set to a status
	 *
	 * @param User $user Object user that modify
	 * @param int $fk_status New status to set (often a constant like self::STATUS_XXX)
	 * @param int $notrigger 1=Does not execute triggers, 0=Execute triggers
	 * @param string $triggercode Trigger code to use
	 * @return    int                        <0 if KO, >0 if OK
	 * @throws Exception
	 */
	public function setStatus($user, $fk_status, $notrigger = 0, $triggercode = 'OPERATIONORDER_STATUS_CHANGE', $fromwebportal = false)
	{

		global $langs;

		$error = 0;

		$this->loadStatusObj();

		$newStatus = new OperationOrderStatus($this->db);
		$resNewStatus = $newStatus->fetch($fk_status);
		if ($resNewStatus > 0 && (int) $this->status !== (int) $fk_status) {
			if ($newStatus->userCan($user, 'changeToThisStatus')) {
				if ($newStatus->require_planned_date && empty($this->planned_date)) {
					$this->error = $langs->trans('PlannedDateMustBeFilledToPassAtThisStatus');
					$this->errors[] = $this->error;
					return -1;
				}
			} else {
				$this->error = $langs->trans('ConfirmSetStatusNotAllowed');
				$this->errors[] = $this->error;
				return -1;
			}

			if ($this->objStatus->checkStatusTransition($user, $newStatus->id)) {
				$this->status = $newStatus->id;
				$this->withChild = false;

				$this->db->begin();
				$sql = "UPDATE " . $this->db->prefix() . $this->table_element;
				$sql .= " SET status = " . $this->status;

				$newref = $this->getRef();
				if ($this->ref != $newref) {
					$this->ref = $newref;
					$sql .= " , ref = '" . $this->db->escape($this->ref) . "' ";
				}

				if (!empty($newStatus->clean_event)) {
					$this->planned_date = '';
					$sql .= " , planned_date = NULL ";
				}
				if (!empty($newStatus->require_planned_date)) {
					$sql .= " , planned_date = '" . $this->db->idate($this->planned_date) . "'";
				}

				if (!empty($newStatus->save_date_cloture)) {
					$this->date_cloture = time();
					$sql .= " , date_cloture = '" . $this->db->idate($this->date_cloture) . "'";
				}
				$sql .= " WHERE rowid = " . $this->id;

				if ($this->db->query($sql)) {
					if (!empty($newStatus->clean_event)) {
						if ($this->deleteORAction() < 0) {
							$this->error = 'Error cleaning operation order events';
							$error++;
						}
					}

					if (empty($newStatus->or_pointable)) {
						if ($this->closeAllPointage($user) < 0) {
							$this->error = 'Error close pointage';
							$error++;
						}
					}

					if (!$error) {
						$this->oldcopy = clone $this;
						$this->objStatus = $newStatus;
					}

					if (!empty($this->objStatus)) {
						dol_include_once('/dolifleet/class/vehicule.class.php');;
						if (empty($this->array_options)) $this->fetch_optionals();
						if (empty($this->objStatus->array_options)) $this->objStatus->fetch_optionals();
						if (!empty($this->objStatus->update_vehicule_info)) {
							dol_include_once('/dolifleet/lib/dolifleet.lib.php');

							$vehicule = new Vehicule($this->db);
							$vehicule->fetch($this->fk_vehicule);
							$rep = callAPI('GET', 'https://api.volvotrucks.com/vehicle/vehiclestatuses', array('vin' => $vehicule->vin, 'latestOnly' => 'true', 'trigger' => 'DISTANCE_TRAVELLED'), array('Accept: application/x.volvogroup.com.vehiclestatuses.v1.0+json; UTF-8'));

							if ($rep != -1) {
								$km = $rep['vehicleStatusResponse']['vehicleStatuses'][0]['hrTotalVehicleDistance'] / 1000;

								if (!empty($km)) {
									//Màj du nombre de kilomètres du véhicule
									$this->km_on_creation = $km;
									$res = $this->update($user, true);
									if ($res < 0) {
										$error++;
									}
								}
							}

							$vehicule->getOperations();
							if (!empty($vehicule->operations)) {
								/**
								 * @var $operation dolifleetVehiculeOperation
								 */
								foreach ($vehicule->operations as $operation) {
									if ($operation->operationNeedUpdate($this)) {
										$operation->date_done = time();
										if (!empty($this->km_on_creation)) $operation->km_done = (int) $this->km_on_creation;
										$operation->or_next = null;
										$operation->on_time = 0;
										if (!empty($operation->km)) {
											$operation->date_next = null;
										}

										$res = $operation->update($user);
										if ($res < 0) {
											$this->error = $operation->error;
											$this->errors[] = $this->error;
											$this->errors = array_merge($operation->errors, $this->errors);
											$error++;
										}
									}
								}
							}
						}
					}

					if ($this->objStatus->require_planned_date) {
						$res = $this->createOperationOrderAction($this->planned_date, $fromwebportal);
						if ($res < 0) {
							$this->error = $langs->trans('OperationOrderActionNotCreated');
							$this->errors[] = $this->error;
							$error++;
						}
					}

					if (!$error && !$notrigger) {
						// Call trigger
						$result = $this->call_trigger($triggercode, $user);
						if ($result < 0) $error++;
					}

					if (!$error) {
						$this->db->commit();
						$ret = 1;
					} else {
						$this->db->rollback();
						$ret = -1 * $error;
					}
				} else {
					$this->error = $this->db->error();
					$this->errors[] = $this->error;
					$this->db->rollback();
					$ret = -1;
				}

				if ($ret > 0) {
					if (!empty($this->oldcopy)) {
						$oOHistory = new OperationOrderHistory($this->db);
						$oOHistory->compareAndSaveDiff($this->oldcopy, $this);
					}

					return 1;
				} else {
					return $ret;
				}
			} else {
				$this->error = $langs->trans('ConfirmSetStatusNotAllowed');
				$this->errors[] = $this->error;
				return -1;
			}
		}

		return 0;
	}

	/**
	 * @return array
	 */
	public function getAlreadyUsedQtyLines()
	{
		$alreadyUsed = array();
		$sql = "SELECT mvt.fk_product, SUM(mvt.value) as total FROM " . $this->db->prefix() . "stock_mouvement as mvt";
		$sql .= " WHERE mvt.origintype = 'operationorder@operationorder'";
		$sql .= " AND mvt.fk_origin = " . $this->id;
		$sql .= " GROUP BY mvt.fk_product";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$alreadyUsed[$obj->fk_product] = $obj->total * -1;
			}
		} else {
			$this->errors[] = $this->db->lasterror;
			return -1;
		}
		return $alreadyUsed;
	}

	/**
	 * @return array
	 */
	public function getLastLinesByProduct()
	{
		$TLastLines = array();
		foreach ($this->lines as $line) {
			if ($line->fk_product) {
				$TLastLines[$line->fk_product] = $line->id;
			}
		}
		return $TLastLines;
	}


	/**
	 * @param int $withpicto Add picto into link
	 * @param string $moreparams Add more parameters in the URL
	 * @param int $notooltip 1=Disable tooltip
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $moreparams = '', $notooltip = 0)
	{
		global $langs, $conf;

		if (!empty($conf->dol_no_mouse_hover)) $notooltip = 1; // Force disable tooltips

		$label = '';
		$linkclose = '>';
		if (empty($notooltip)) {
			$label = $this->getORTooltips();

			$linkclose = '" title="' . dol_escape_htmltag($label, 1) . '" class="classfortooltip">';
		}
		$link = '<a href="' . dol_buildpath('/operationorder/operationorder_card.php', 1) . '?id=' . $this->id . urlencode($moreparams) . '"' . $linkclose;
		$linkend = '</a>';
		$picto = '';
		if ($withpicto) $picto = img_picto($label, 'setup', ($notooltip ? '' : 'class="classfortooltip"'));

		$result = $link . $picto . $this->ref . $linkend;

		global $action, $hookmanager;
		$hookmanager->initHooks(array('operationorderdao'));
		$parameters = array('id' => $this->id, 'getnomurl' => $result);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook > 0) $result = $hookmanager->resPrint;
		else $result .= $hookmanager->resPrint;

		return $result;
	}


	/** Get Tooltips
	 * @return string
	 */
	public function getORTooltips()
	{
		$label = '';
		$this->fetchLines(true);
		foreach ($this->lines as $line) {
			if ($line->product->type == 1 && empty($line->fk_parent_line)) {
				$label .= '<br> +' . $line->product->label;
				if (!empty($line->description)) {
					$text = preg_replace("/\r|\n/", "", $line->description);
					$label .= ' - ' . substr($text, 0, 300);
					if (strlen($text) > 300) {
						$label .= '...';
					}
				}
			}
		}
		if (!empty($label)) {
			$label = '<strong> Détail des operations: </strong>' . $label;
		}
		return $label;
	}

	/**
	 * @param int $id Identifiant
	 * @param null $ref Ref
	 * @param int $withpicto Add picto into link
	 * @param string $moreparams Add more parameters in the URL
	 * @return string
	 */
	public static function getStaticNomUrl($id, $ref = null, $withpicto = 0, $moreparams = '')
	{
		global $db;

		$object = new OperationOrder($db);
		$object->fetch($id, false, $ref);

		return $object->getNomUrl($withpicto, $moreparams);
	}


	/**
	 * @param int $mode 0=Long label, 1=Short label, 2=Picto + Short label, 3=Picto, 4=Picto + Long label, 5=Short label + Picto, 6=Long label + Picto
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return self::LibStatut($this->status, $mode, $this->entity);
	}

	/**
	 * @param int $fk_status status
	 * @param int $mode 0=Long label, 1=Short label, 2=Picto + Short label, 3=Picto, 4=Picto + Long label, 5=Short label + Picto, 6=Long label + Picto
	 * @param int $force_entity entity
	 * @return string
	 */
	public static function LibStatut($fk_status, $mode, $force_entity = 0)
	{
		global $langs, $db;
		$langs->load('operationorder@operationorder');

		$status = new Operationorderstatus($db);
		$res = $status->fetchDefault($fk_status, $force_entity);
		if ($res > 0) {
			return $status->getBadge();
		}

		return 'err';
	}

	/**
	 *  Create a document onto disk according to template module.
	 *
	 * @param string $modele Force template to use ('' to not force)
	 * @param Translate $outputlangs objet lang a utiliser pour traduction
	 * @param int $hidedetails Hide details of lines
	 * @param int $hidedesc Hide description
	 * @param int $hideref Hide ref
	 * @param null|array $moreparams Array to provide more information
	 * @return     int                        0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $conf, $langs;

		$langs->load("operationorder@operationorder");

		if (!dol_strlen($modele)) {
			$modele = 'standard';

			if ($this->modelpdf) {
				$modele = $this->modelpdf;
			} elseif (!empty(getDolGlobalString("OPERATIONORDER_ADDON_PDF"))) {
				$modele = getDolGlobalString("OPERATIONORDER_ADDON_PDF");
			}
		}

		$modelpath = "core/modules/operationorder/doc/";

		return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
	}

	/**
	 * @param string $desc desc
	 * @param float $qty qty
	 * @param float $price price
	 * @param int $fk_warehouse warehouse
	 * @param float $pr costprice
	 * @param int $time_planned time_planned
	 * @param int $time_spent time_spent
	 * @param int $fk_product product
	 * @param int $info_bits info_bits
	 * @param int $date_start date_start
	 * @param int $date_end date end
	 * @param int $type type
	 * @param int $rang rang
	 * @param int $special_code special code
	 * @param int $fk_parent_line parent line
	 * @param string $label label
	 * @param array $array_options exrafields
	 * @param string $origin origin type
	 * @param int $origin_id origin id
	 * @param bool $dontUpdateObj dontUpdateObj
	 * @param int $fk_c_operationorder_type from ditct OR TYPE
	 * @param float $remise_percent discount percent
	 * @return int
	 * @throws Exception
	 */
	public function addline($desc, $qty, $price, $fk_warehouse, $pr, $time_planned, $time_spent, $fk_product = 0, $info_bits = 0, $date_start = '', $date_end = '', $type = 0, $rang = -1, $special_code = 0, $fk_parent_line = 0, $label = '', $array_options = 0, $origin = '', $origin_id = 0, $dontUpdateObj = false, $fk_c_operationorder_type = 0, $remise_percent = 0)
	{
		global $user, $langs;

		$logtext = "::addline commandeid=$this->id, desc=$desc, fk_product=$fk_product";
		$logtext .= ", info_bits=$info_bits, date_start=$date_start";
		$logtext .= ", date_end=$date_end, type=$type special_code=$special_code, origin=$origin, origin_id=$origin_id";
		$logtext .= ", dontUpdateObj=$dontUpdateObj, fk_c_operationorder_type=$fk_c_operationorder_type remise_percent=$remise_percent";
		dol_syslog(get_class($this) . $logtext, LOG_DEBUG);

		if (!$this->isEditable($user)) {
			$this->error = $langs->trans("UserDoesNotHaveRightPermission");
			dol_syslog(get_class($this) . "::addline status of order must be Draft to allow use of ->addline()", LOG_ERR);
			return -3;
		}
		//            include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';

		// Clean parameters
		if (empty($qty)) $qty = 0;
		if (empty($time_planned)) $time_planned = 0;
		if (empty($time_spent)) $time_spent = 0;
		if (empty($info_bits)) $info_bits = 0;
		if (empty($rang)) $rang = 0;
		if (empty($fk_parent_line) || $fk_parent_line < 0) $fk_parent_line = 0;
		if ($type === '') $type = 0;

		$qty = price2num($qty);
		$time_planned = price2num($time_planned);
		$time_spent = price2num($time_spent);
		$price = price2num($price, 'MU');
		$remise_percent = price2num($remise_percent, 'MU');
		$label = trim($label);
		$desc = trim($desc);

		// Check parameters
		if ($type < 0) {
			$this->error = $langs->trans('WrongValueForOperationOrderType');
			return -1;
		}

		$this->db->begin();

		$product_type = $type;

		// Rang to use
		$ranktouse = $rang;
		if ($ranktouse == -1) {
			$rangmax = $this->line_max($fk_parent_line);
			$ranktouse = $rangmax + 1;
		}

		$product = new Product($this->db);
		if (!empty($fk_product)) {
			$product->fetch($fk_product, '', '', '', 1, 1, 1);
		}

		// Insert line
		$this->line = new operationorderLine($this->db);
		$this->line->context = $this->context;

		$this->line->fk_operation_order = $this->id;
		$this->line->fk_product = $fk_product;
		$this->line->description = $desc;
		$this->line->qty = $qty;
		$this->line->fk_warehouse = $fk_warehouse;
		$this->line->price = $price;
		$this->line->remise_percent = price2num($remise_percent);
		$this->line->remise = price2num((float) $this->line->price * ((float) $this->line->remise_percent / 100), 'MU');
		$this->line->total_ht = price2num((float) ($this->line->price - $this->line->remise) * $this->line->qty, 'MT');
		$this->line->pr = $pr;
		$this->line->time_planned = $time_planned; // TODO
		$this->line->time_spent = $time_spent; // TODO

		$this->line->label = $label;

		$this->line->product_type = $product_type;
		$this->line->rang = $ranktouse;
		$this->line->info_bits = $info_bits;
		$this->line->origin = $origin;
		$this->line->origin_id = $origin_id;
		$this->line->fk_parent_line = $fk_parent_line;
		$this->line->fk_c_operationorder_type = $fk_c_operationorder_type;

		if (is_array($array_options) && count($array_options) > 0) {
			$this->line->array_options = $array_options;
		}

		$result = $this->line->create($user);
		if ($result > 0) {
			$oOHistory = new OperationOrderHistory($this->db);
			$oOHistory->saveCreationOrDeletion($this->line);
			// Reorder if child line
			if (!empty($fk_parent_line)) $this->line_order(true, 'DESC');

			// Mise a jour informations denormalisees au niveau de la commande meme
			//                $result=$this->update_price(1, 'auto', 0, $mysoc);    // This method is designed to add line from user input so total calculation must be done using 'auto' mode.
			$this->db->commit();
			$this->setTimePlannedT($dontUpdateObj);

			return $this->line->id;
		} else {
			$this->error = $this->line->error;
			$this->errors = array_merge($this->errors, $this->line->errors);
			dol_syslog(get_class($this) . "::addline error=" . $this->error, LOG_ERR);
			$this->db->rollback();
			return -2;
		}
	}

	/**
	 * @param int $rowid rowid
	 * @param string $desc desc
	 * @param int $qty qty
	 * @param float $price price
	 * @param int $fk_warehouse warehouse
	 * @param float $pr cost price
	 * @param int $time_planned time_planned
	 * @param int $time_spent time_spent
	 * @param int $fk_product product
	 * @param int $info_bits info_bits
	 * @param string $date_start date_start
	 * @param string $date_end date_end
	 * @param int $type type
	 * @param int $fk_parent_line parent_line
	 * @param string $label label
	 * @param int $special_code special_code
	 * @param int $array_options array_options
	 * @param int $notrigger notrigger
	 * @param int $fk_c_operationorder_type from ditct OR TYPE
	 * @param float $remise_percent discount percent
	 * @return int
	 * @throws Exception
	 */
	public function updateline($rowid, $desc, $qty, $price, $fk_warehouse, $pr, $time_planned, $time_spent, $fk_product, $info_bits = 0, $date_start = '', $date_end = '', $type = 0, $fk_parent_line = 0, $label = '', $special_code = 0, $array_options = 0, $notrigger = 0, $fk_c_operationorder_type = 0, $remise_percent = 0)
	{
		global $langs, $user;

		dol_syslog(get_class($this) . "::updateline id=$rowid, desc=$desc, info_bits=$info_bits, date_start=$date_start, date_end=$date_end, type=$type, fk_parent_line=$fk_parent_line, special_code=$special_code");

		if ($this->isEditable($user)) {
			// Clean parameters
			if (empty($qty)) $qty = 0;
			if (empty($time_planned)) $time_planned = 0;
			if (empty($time_spent)) $time_spent = 0;
			if (empty($info_bits)) $info_bits = 0;
			if (empty($special_code) || $special_code == 3) $special_code = 0;

			if ($date_start && $date_end && $date_start > $date_end) {
				$langs->load("errors");
				$this->error = $langs->trans('ErrorStartDateGreaterEnd');
				return -1;
			}

			$qty = price2num($qty);
			$time_planned = price2num($time_planned);
			$time_spent = price2num($time_spent);
			$price = price2num($price, 'MU');

			$this->db->begin();

			//Fetch current line from the database and then clone the object and set it in $oldline property
			$k = $this->addChild('operationorderLine', $rowid);
			$line = $this->TOperationOrderLine[$k];

			$staticline = clone $line;

			$line->oldline = $staticline;
			$this->line = $line;
			$this->line->context = $this->context;

			// Reorder if fk_parent_line change
			if (!empty($fk_parent_line) && !empty($staticline->fk_parent_line) && $fk_parent_line != $staticline->fk_parent_line) {
				$rangmax = $this->line_max($fk_parent_line);
				$this->line->rang = $rangmax + 1;
			}

			$this->line->id = $rowid;
			$this->line->label = $label;
			$this->line->description = $desc;
			$this->line->qty = $qty;
			$this->line->fk_warehouse = $fk_warehouse;
			$this->line->pr = $pr;
			$this->line->price = ($price < price2num($this->line->product->price_min, 'MU') && !$user->admin) ? price2num($this->line->product->price_min, 'MU') : $price;
			$this->line->remise_percent = $remise_percent;
			$this->line->remise = price2num((float) $this->line->price * ((float) $this->line->remise_percent / 100), 'MU');
			$this->line->total_ht = price2num((float) ($this->line->price - $this->line->remise) * $this->line->qty, 'MT');
			$this->line->fk_product = $fk_product;


			$this->line->time_planned = $time_planned;
			$this->line->time_spent = $time_spent;

			$this->line->info_bits = $info_bits;

			$this->line->date_start = $date_start;
			$this->line->date_end = $date_end;

			$this->line->product_type = $type;
			$this->line->fk_parent_line = $fk_parent_line;
			$this->line->fk_c_operationorder_type = $fk_c_operationorder_type;


			if (is_array($array_options) && count($array_options) > 0) {
				// We replace values in this->line->array_options only for entries defined into $array_options
				foreach ($array_options as $key => $value) {
					$this->line->array_options[$key] = $array_options[$key];
				}
			}

			$result = $this->line->update($user, $notrigger);
			if ($result > 0) {
				//
				// Reorder if child line
				if (!empty($fk_parent_line)) {
					$this->line_order(true, 'DESC');
				}
				$nestedOpeLine = new OperationOrder($this->db);
				$nestedOpeLine->fetch($this->line->fk_operation_order);
				$TNested = $nestedOpeLine->fetch_all_children_nested($this->line->id);
				if (!empty($TNested)) {
					foreach ($TNested as $childLines) {
						$childLine = new operationorderLine($this->db);
						$childLine->fetch($childLines['object']->id);
						$resUpdChild = $nestedOpeLine->updateline(
							$childLine->id,
							$childLine->description,
							$childLine->qty,
							$childLine->price,
							$childLine->fk_warehouse,
							$childLine->pr,
							$childLine->time_planned,
							$childLine->time_spent,
							$childLine->fk_product,
							$childLine->info_bits,
							$childLine->date_start,
							$childLine->date_end,
							$childLine->product_type,
							$childLine->fk_parent_line,
							$childLine->label,
							$childLine->special_code,
							$childLine->array_options,
							1,
							$childLine->fk_c_operationorder_type,
							$this->line->remise_percent, 1);
						if ($resUpdChild < 0) {
							return $resUpdChild;
						}
					}
				}

				if (!empty($this->line->fk_parent_line) && $notrigger !== 1) {
					$remiseAmountData = $this->calcTotalRemise($this->line->fk_parent_line);
					if (is_object($remiseAmountData)) {
						$parentLine = new operationorderLine($this->db);
						$parentLine->fetch($this->line->fk_parent_line);
						$parentLine->remise = $remiseAmountData->totalremiseamount;
						$parentLine->remise_percent = ($remiseAmountData->totalremiseamount / ($remiseAmountData->total_ht + $remiseAmountData->totalremiseamount)) * 100;
						$parentLine->total_ht = $remiseAmountData->total_ht;
						$parentLine->total_ht_part = $remiseAmountData->total_ht_part;
						$parentLine->total_ht_service = $remiseAmountData->total_ht_service;
						$parentLine->total_ht_external = $remiseAmountData->total_ht_external;
						$parentLine->total_ht_reimbursement = $remiseAmountData->total_ht_reimbursement;
						$resUpdParent = $parentLine->update($user, 1);
						if ($resUpdParent < 0) {
							$this->error = $parentLine->error;
							$this->errors[] = $this->error;
							$this->errors = array_merge($this->errors, $parentLine->errors);

							return $resUpdParent;
						}
					} elseif ($remiseAmountData < 0) {
						return $remiseAmountData;
					}
				}

				$this->db->commit();

				if (!empty($this->line->oldcopy)) {
					$oOHistory = new OperationOrderHistory($this->db);
					$oOHistory->compareAndSaveDiff($this->line->oldcopy, $this->line);
				}

				return $result;
			} else {
				$this->error = $this->line->error;
				$this->errors = $this->line->errors;

				$this->db->rollback();
				return -1;
			}
			return $this->line->id;
		} else {
			$this->error = get_class($this) . "::updateline Order status makes operation forbidden";
			dol_syslog(get_class($this) . "::updateline Error:" . $this->error);
			$this->errors = array('OrderStatusMakeOperationForbidden');
			return -2;
		}
	}

	/**
	 * @param $fk_parent_line int
	 * @return int|Object|void
	 */
	public function calcTotalRemise($fk_parent_line = 0)
	{
		$sql = 'SELECT sum(remise) as totalremiseamount,
       			sum(total_ht) as total_ht,
       			sum(total_ht_part) as total_ht_part,
       			sum(total_ht_mo) as total_ht_mo,
				sum(total_ht_service) as total_ht_service,
				sum(total_ht_external) as total_ht_external,
				sum(total_ht_reimbursement) as total_ht_reimbursement
			FROM ' . $this->db->prefix() . 'operationorderdet
			WHERE fk_parent_line = ' . (int) $fk_parent_line;

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($obj = $this->db->fetch_object($resql)) {
				return $obj;
			}
		} else {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
	}

	/**
	 * Initialise object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		$this->thirdparty = new Societe($this->db);
		$this->initAsSpecimenCommon();
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps

	/**
	 *    Update position of line with ajax (rang)
	 *
	 * @param array $rows Array of rows
	 * @return    void
	 */
	public function line_ajaxorder($rows)
	{
		$TId = array();
		foreach ($this->TOperationOrderLine as $operationOrderDet) {
			if (empty($operationOrderDet->fk_parent_line)) $TId[$operationOrderDet->id] = array();
			else $TId[$operationOrderDet->fk_parent_line][] = $operationOrderDet->id;
		}

		// phpcs:enable
		$i = 1;
		foreach ($rows as $id) {
			// Si id parent
			if (isset($TId[$id])) {
				$this->updateRangOfLine($id, $i++);
				foreach ($TId[$id] as $fk_child_line) {
					$this->updateRangOfLine($fk_child_line, $i++);
				}
			}
		}
	}

	/**
	 * @return bool
	 */
	protected function clear()
	{
		// backup origins lines
		$this->originLines = $this->lines;
		$this->status = 0;

		if (!empty($this->lines) && !empty($this->fk_element)) {
			foreach ($this->lines as $i => & $line) {
				if (!empty($line->fk_parent_line)) {
					unset($this->lines[$i]);
				} else {
					$line->{$this->fk_element} = 0;
					$line->clear();
				}
			}

			sort($this->lines);
		}

		return parent::clear();;
	}

	/**
	 * @param $fk_line_parent parent line
	 * @param $fk_product product
	 * @param $qty qty
	 * @param $dontUpdateObj dontUpdateObj
	 * @param float $remise_percent Discount percent
	 * @return float|int|mixed|string
	 * @throws Exception
	 */
	public function recurciveAddChildLines($fk_line_parent, $fk_product, $qty, $dontUpdateObj = false, $remise_percent = 0)
	{
		global $conf, $langs, $hookmanager;

		if (!empty(getDolGlobalString("PRODUIT_SOUSPRODUITS")) && !empty($fk_line_parent) && !empty($fk_product)) {
			$product = new Product($this->db);
			$res = $product->fetch($fk_product);
			if ($res) {
				$arbo = $product->getChildsArbo($product->id, 1);
				if (!empty($arbo)) {
					foreach ($arbo as $productid => $product_info) {
						$childLineProduct = new Product($this->db);
						$res = $childLineProduct->fetch($productid);
						if ($res) {
							$nb = doubleval(!empty($product_info[1]) ? $product_info[1] : 0);

							$newLineQty = $nb * $qty;

							// Convertion des temps planifier
							$time_plannedhour = 0;
							$time_plannedmin = 0;
							$timePlanned = 0;

							if (!empty($childLineProduct->duration_unit) && !empty($childLineProduct->duration_value)) {
								$fk_duration_unit = UnitsTools::getUnitFromCode($childLineProduct->duration_unit, 'short_label');
								if ($fk_duration_unit < 1) {
									$this->errors[] = $langs->transnoentities('UnitCodeNotFound', $childLineProduct->duration_unit);
								}

								if (!empty($childLineProduct->duration_value) && $fk_duration_unit > 0) {
									$fk_unit_hours = UnitsTools::getUnitFromCode('H', 'code');
									if ($fk_unit_hours > 0) {
										$durationHours = UnitsTools::unitConverteur($childLineProduct->duration_value, $fk_duration_unit, $fk_unit_hours);

										$time_plannedhour = floor($durationHours);
										$time_plannedmin = round($durationHours - floor($durationHours), 2) * 60;
									} else {
										$this->errors[] = $langs->transnoentities('UnitCodeNotFound', 'H');
									}
								}

								// set time planned after time conversion according to qty
								$timePlanned = ($time_plannedhour * 60 * 60 + $time_plannedmin * 60) * $newLineQty;
							}

							// Pas le choix de passer par un hook et pas par un trigger
							$parameters = array(
								'parent_product' => & $product,
								'product_info' => $product_info,
								'childLineProduct' => & $childLineProduct,
								'fk_line_parent' => $fk_line_parent,
								'fk_product' => $fk_product,
								'qty' => $qty,
								'newLineQty' => $newLineQty,
								'nb' => $nb,
								'timePlanned' => $timePlanned,
							);
							$reshook = $hookmanager->executeHooks('recurciveAddChildLines', $parameters, $this);    // Note that $action and $object may have been modified by hook
							if ($reshook < 0) {
								return $reshook;
							} elseif ($reshook > 0) {
								continue;
							} else {
								// Ajout de la ligne
								$newLineRes = $this->addline(
									'',
									$newLineQty,
									$childLineProduct->price,
									$childLineProduct->fk_default_warehouse,
									$childLineProduct->cost_price,
									$timePlanned,
									0,
									$childLineProduct->id,
									0,
									'',
									'',
									$childLineProduct->type,
									-1,
									0,
									$fk_line_parent,
									'',
									array(),
									'',
									0,
									$dontUpdateObj,
									0,
									$remise_percent
								);


								if ($newLineRes > 0) {
									$recusiveRes = $this->recurciveAddChildLines($newLineRes, $childLineProduct->id, $newLineQty, $dontUpdateObj, $remise_percent);
									if ($recusiveRes < 0) {
										$this->errors[] = $langs->transnoentities('RecurciveLineaddFail');
										return -2;
									}
								} else {
									$this->errors[] = $langs->transnoentities('LineaddFail');
									return -1;
								}
							}
						}
					}
					return 1;
				}
			}
		}

		return 0;
	}


	/**
	 * Return HTML string to show a field into a page
	 * Code very similar with showOutputField of extra fields
	 *
	 * @param array $val Array of properties of field to show
	 * @param string $key Key of attribute
	 * @param string $value Preselected value to show (for date type it must be in timestamp format, for amount or price it must be a php numeric value)
	 * @param string $moreparam To add more parametes on html input tag
	 * @param string $keysuffix Prefix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param string $keyprefix Suffix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param mixed $morecss Value for css to define size. May also be a numeric.
	 * @return string
	 */
	public function showOutputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{
		global $conf, $langs, $db;
		$out = '';
		if ($key == 'time_planned_t' || $key == 'time_planned_f') {
			$val['type'] = 'duration';
		}
		$out .= parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);

		return $out;
	}

	/**
	 * @param $val val
	 * @param $key key
	 * @param $value value
	 * @param $moreparam moreparam
	 * @param $keysuffix keysuffix
	 * @param $keyprefix keyprefix
	 * @param $morecss morecss
	 * @param $nonewbutton nonewbutton
	 * @return array|string
	 */
	public function showInputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = 0, $nonewbutton = 0)
	{
		global $user, $conf;
		if ($key == 'time_planned_f') {
			$out = '<input  name="' . $keyprefix . $key . $keysuffix . '" id="' . $keyprefix . $key . $keysuffix . '" value="' . convertSecondToTime($value) . '" >';
		} elseif ($key == 'fk_user_meca') {
			dol_include_once('core/class/hthml.form.class.php');
			$sql = 'SELECT fk_user FROM ' . $this->db->prefix() . 'usergroup_user WHERE entity = ' . $conf->entity . ' AND fk_usergroup = ' . getDolGlobalInt("OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING");
			$resql = $this->db->query($sql);
			$include = [];
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$include[] = $obj->fk_user;
				}
			}
			$form = new Form($this->db);
			$out = $form->select_dolusers($this->{$key}, $key, 1, null, 0, $include);
		} else {
			if ($key == 'fk_c_operationorder_type') {
				$nonewbutton = !($user->admin);
			}
			if ($key == 'fk_soc') {
				$nonewbutton = !$user->hasRight("societe", "creer", "");
			}
			$out = parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
			if ($key == 'fk_conducteur' && GETPOST("action") !== 'create') {
				$out .= '<a href="' . $_SERVER["PHP_SELF"] . '?id=' . intval($this->id) . '&action=view&action_driver=create"><span class="fa fa-plus-circle valignmiddle"></span>';
			}
		}
		if ($key == 'fk_vehicule' && GETPOSTISSET("action") && GETPOST("action") == 'create') {
			$out = parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
			$out .= '<a href="' . $_SERVER["PHP_SELF"] . '?id=' . intval($this->id) . '&action=create&action_veh=create"><span class="fa fa-plus-circle valignmiddle"></span>';
		}

		return $out;
	}

	/**
	 * Return HTML string to show a field into a page
	 *
	 * @param string $key Key of attribute
	 * @param string $moreparam To add more parameters on html input tag
	 * @param string $keysuffix Prefix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param string $keyprefix Suffix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param mixed $morecss Value for css to define size. May also be a numeric.
	 * @return string
	 */
	public function showOutputFieldQuick($key, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{
		return $this->showOutputField($this->fields[$key], $key, $this->{$key}, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * @param $useCache use chache
	 * @return false|Object
	 */
	public function getOvershoot($useCache = true)
	{

		if ($useCache && is_object($this->overshot)) {
			return $this->overshot;
		}

		$sql = ' SELECT SUM(l.time_planned) sum_time_planned,  SUM(l.time_spent) sum_time_spent';
		$sql .= ' FROM ' . $this->db->prefix() . 'operationorderdet l ';
		$sql .= ' WHERE l.fk_operation_order = ' . $this->id;

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->overshot = $this->db->fetch_object($resql);
		} else {
			$this->overshot = false;
		}

		return $this->overshot;
	}

	/**
	 * @param $useCache use cache
	 * @return string
	 */
	public function getOvershootStatus($useCache = true)
	{
		global $langs;

		$out = '';

		if ($this->getOvershoot($useCache)) {
			if (!empty($this->overshot->sum_time_planned) && !empty($this->overshot->sum_time_spent)) {
				$ecart = intval($this->overshot->sum_time_planned) - intval($this->overshot->sum_time_spent);
				$sign = '';
				if ($ecart > 0) {
					$textClass = "text-success";
					$iconClass = "fa-caret-down";
					$sign = '-';
				} elseif ($ecart == 0) {
					$textClass = "text-warning";
					$iconClass = "fa-caret-left";
				} else {
					$textClass = "text-danger";
					$iconClass = "fa-caret-up";
					$sign = '+';
				}

				$out .= '<span class="' . $textClass . ' classfortooltip paddingrightonly" title="' . $langs->trans('TimeDifference') . '" ><i class="fa ' . $iconClass . '"></i> ' . $sign . dol_print_date(abs($ecart), '%HH%M', true) . '</span>';
			} else {
				$out .= ' -- ';
			}
		} else {
			$out = 'error';
		}


		return $out;
	}

	/**
	 * @return self[]
	 */
	public static function getPlannableOperationOrder()
	{
		global $db;
		$TPlanableOO = array();
		$sql = "SELECT oo.rowid, oo.ref
                FROM " . $db->prefix() . "operationorder as oo
                INNER JOIN " . $db->prefix() . "operationorder_status as oos ON (oo.status = oos.rowid)
                WHERE oos.planable = 1 AND oo.entity IN (" . getEntity('operationorder') . ")";
		$resql = $db->query($sql);
		if (!empty($resql) && $resql > 0) {
			while ($obj = $db->fetch_object($resql)) {
				$operationOrder = new self($db);
				$operationOrder->fetch($obj->rowid);
				$TPlanableOO[$obj->rowid] = $operationOrder;
			}
		}

		return $TPlanableOO;
	}

	/**
	 * @param $onlyUpdateTimePlannedT boolean
	 * @return int|resource
	 */
	public function setTimePlannedT($onlyUpdateTimePlannedT = false)
	{
		global $user;

		$total_time = 0;

		if (empty($this->lines)) $this->fetchLines();

		foreach ($this->lines as $line) {
			if (empty($line->fk_parent_line)) $total_time += $line->time_planned;
		}

		$this->time_planned_t = $total_time;

		if (!$onlyUpdateTimePlannedT) $res = $this->update($user);
		else {
			$sql = 'UPDATE ' . $this->db->prefix() . $this->table_element . ' SET time_planned_t = ' . $this->time_planned_t . ' WHERE rowid = ' . $this->id;
			$res = $this->db->query($sql);
		}

		return $res;
	}

	/**
	 * @param $value value
	 * @return int|resource
	 */
	public function setOrCheck($value)
	{
		if ($value <= 2 && $value >= 0) {
			$sql = 'UPDATE ' . $this->db->prefix() . $this->table_element . ' SET orcheck = ' . $value . ' WHERE rowid = ' . $this->id;
			$res = $this->db->query($sql);
		} else {
			$res = -1;
		}
		return $res;
	}

	/**
	 * @param $loadProduct boolean
	 * @return int
	 */
	public function getTimePlannedT($loadProduct = true)
	{

		$total_time = 0;
		$this->fetchLines($loadProduct);
		if (!empty($this->lines)) {
			foreach ($this->lines as $line) {
				if (empty($line->fk_parent_line)) $total_time += $line->time_planned;
			}
		}

		return $total_time;
	}

	/**
	 * @param User $user current USer
	 * @param int $dontUpdate 0 no update, 1 update
	 * @return int|void < 0 KO > 1 OK
	 * @throws Exception
	 */
	public function calcTotal($user, $dontUpdate = 0)
	{

		$this->total_ht_reimbursement = 0;
		$this->total_ht_external = 0;
		$this->total_ht_mo = 0;
		$this->total_ht_part = 0;
		$this->total_ht_service = 0;
		$this->total_ht = 0;
		if (empty($this->lines)) $this->fetchLines();
		if (!empty($this->lines)) {
			$arrayCompose = array();
			foreach ($this->lines as $line) {
				if (empty($line->product)) {
					$retFetch = $line->fetch_product();
					if ($retFetch < 0) {
						$this->error = $line->error;
						$this->errors[] = $this->error;
						$this->errors = array_merge($this->errors, $line->errors);
						return -3;
					} elseif (empty($retFetch)) {
						continue;
					}
				}
				if (empty($line->product->array_options)) $line->product->fetch_optionals();

				$resCalc = $line->calcAmountLine($user);
				if ($resCalc < 0) {
					$this->error = $line->error;
					$this->errors[] = $this->error;
					$this->errors = array_merge($this->errors, $line->errors);
					return -3;
				}
				$parent_line = (int) $line->fk_parent_line;
				if ((int) $line->product->hasFatherOrChild(1) == 0) {
					if (!isset($arrayCompose[$parent_line])) {
						$arrayCompose[$parent_line] = [
							'total_ht_part' => 0,
							'time_planned' => 0,
							'time_spent' => 0,
							'total_ht_mo' => 0,
							'total_ht_reimbursement' => 0,
							'total_ht_service' => 0,
							'total_ht_external' => 0,
							'total_ht' => 0,
						];
					}
					if ($line->product->type == Product::TYPE_PRODUCT) {
						$this->total_ht_part += $line->total_ht;
						$arrayCompose[$parent_line]['total_ht_part'] += $line->total_ht;
					} elseif ($line->product->type == Product::TYPE_SERVICE) {
						if (!empty($line->product->array_options['options_oorder_available_for_supplier_order'])) {
							$this->total_ht_external += $line->total_ht;
							$arrayCompose[$parent_line]['total_ht_external'] += $line->total_ht;
						} elseif (!empty($line->product->array_options['options_or_scan'])) {
							$this->total_ht_mo += $line->total_ht;
							$arrayCompose[$parent_line]['time_planned'] += $line->time_planned;
							$arrayCompose[$parent_line]['time_spent'] += $line->time_spent;
							$arrayCompose[$parent_line]['total_ht_mo'] += $line->total_ht;
						} elseif (!empty($line->product->array_options['options_oorder_ventilation_produit']) && empty($line->object->fk_parent_line)) {
							$this->total_ht_reimbursement += $line->total_ht;
							$arrayCompose[$parent_line]['total_ht_reimbursement'] += $line->total_ht;
						} elseif (empty($line->product->array_options['options_or_is_job'])) {
							$this->total_ht_service += $line->total_ht;
							$arrayCompose[$parent_line]['total_ht_service'] += $line->total_ht;
						}
					}
					$arrayCompose[(int) $line->fk_parent_line]['total_ht'] += $line->total_ht;
				}
				unset($arrayCompose[0]);
				if ($line->product->type == Product::TYPE_SERVICE &&
					!empty($line->product->array_options['options_or_is_job']) &&
					!empty($this->id)) {
					$resultUpdPL = $this->calcTotalLine($user, $line);
					if ($resultUpdPL < 0) {
						dol_syslog(get_class($this) . "::calcTotal error=" . $this->error, LOG_ERR);
						return -2;
					}
					unset($arrayCompose[$line->id]);
				}
			}
			foreach ($arrayCompose as $parentLineId => $dataTotal) {
				$orLine = new operationorderLine($this->db);
				$resFetchLine = $orLine->fetch($parentLineId);
				if ($resFetchLine < 0) {
					$this->error = $orLine->error;
					$this->errors[] = $orLine->error;
					$this->errors = array_merge($this->errors, $orLine->errors);
					return -4;
				}

				$orLine->price = ($dataTotal['total_ht'] ?? 0) / $orLine->qty;
				$orLine->total_ht = $dataTotal['total_ht'] ?? 0;
				$orLine->total_ht_external = ($dataTotal['total_ht_external'] ?? 0);
				$orLine->total_ht_mo = $dataTotal['total_ht_mo'] ?? 0;
				$orLine->total_ht_reimbursement = $dataTotal['total_ht_reimbursement'] ?? 0;
				$orLine->total_ht_service = $dataTotal['total_ht_service'] ?? 0;
				$orLine->total_ht_part = $dataTotal['total_ht_part'] ?? 0;
				$orLine->time_planned = $dataTotal['time_planned'] ?? 0;
				$orLine->time_spent = $dataTotal['time_spent'] ?? 0;
				$resUpdLineParent = $orLine->update($user, true);
				if ($resUpdLineParent < 0) {
					$this->error = $orLine->error;
					$this->errors[] = $orLine->error;
					$this->errors = array_merge($this->errors, $orLine->errors);
					return -5;
				}
			}
			$this->total_ht = price2num($this->total_ht_part + $this->total_ht_service + $this->total_ht_mo + $this->total_ht_external + $this->total_ht_reimbursement, 'MT');
			if (!empty($this->id) && empty($dontUpdate)) {
				$resUpd = $this->update($user, true);
				if ($resUpd < 0) {
					dol_syslog(get_class($this) . "::calcTotal error=" . $this->error, LOG_ERR);
					return -1;
				}
			}
		}
	}

	/**
	 * @param User $user user Action
	 * @param operationorderLine $parentLine line rowid
	 * @return int  < 0 if OK
	 */
	public function calcTotalLine(User $user, operationorderLine $parentLine)
	{
		$total_ht_reimbursement = 0;
		$total_ht_external = 0;
		$total_ht_mo = 0;
		$total_ht_part = 0;
		$total_ht_service = 0;
		$total_time_planned = 0;
		$total_time_spend = 0;
		$TNested = $this->fetch_all_children_nested($parentLine->id);
		if (!empty($TNested)) {
			foreach ($TNested as $line) {
				if (empty($line['object']->product)) $line['object']->fetch_product();
				if (empty($line['object']->product->array_options)) $line['object']->product->fetch_optionals();

				if ($line['object']->product->type == Product::TYPE_SERVICE) {
					if (!empty($line['object']->product->array_options['options_oorder_available_for_supplier_order'])) {
						$total_ht_external += $line['object']->total_ht;
					} elseif (!empty($line['object']->product->array_options['options_or_scan'])) {
						$total_ht_mo += $line['object']->total_ht;
						$total_time_planned += $line['object']->time_planned;
						$total_time_spend += $line['object']->time_spent;
					} elseif (!empty($line['object']->product->array_options['options_oorder_ventilation_produit']) && empty($line['object']->fk_parent_line)) {
						$total_ht_reimbursement += $line['object']->total_ht;
					} else {
						if ((int) $line['object']->product->hasFatherOrChild(1) == 0) {
							$total_ht_service = $line['object']->total_ht;
						} else {
							$total_ht_service += $line['object']->total_ht_service;
							$total_ht_mo += $line['object']->total_ht_mo;
							$total_ht_external += $line['object']->total_ht_external;
							$total_ht_reimbursement += $line['object']->total_ht_reimbursement;
							$total_ht_part += $line['object']->total_ht_part;
						}
					}
				}
				if ($line['object']->product->type == Product::TYPE_PRODUCT) {
					$total_ht_part += $line['object']->total_ht;
				}
			}
			$parentLine->total_ht_part = $total_ht_part;
			$parentLine->total_ht_service = $total_ht_service;
			$parentLine->total_ht_mo = $total_ht_mo;
			$parentLine->total_ht_external = $total_ht_external;
			$parentLine->total_ht_reimbursement = $total_ht_reimbursement;
			$parentLine->total_ht = $total_ht_part + $total_ht_service + $total_ht_mo + $total_ht_external + $total_ht_reimbursement;
			$parentLine->time_planned = $total_time_planned;
			$parentLine->time_spent = $total_time_spend;
			$resultUpdLine = $parentLine->update($user, true);
			if ($resultUpdLine < 0) {
				$this->errors[] = $parentLine->error;
				$this->errors = array_merge($this->errors, $parentLine->errors);
			}
			return $resultUpdLine;
		}
	}

	/**
	 * @param $loadProduct boolean
	 * @return int
	 */
	public function getTimeSpent($loadProduct = true)
	{

		$total_time = 0;

		$this->fetchLines($loadProduct);

		if (!empty($this->lines)) {
			foreach ($this->lines as $line) {
				$total_time += $line->time_spent;
			}
		}

		return $total_time;
	}

	/**
	 * @return int
	 */
	public function deleteORAction()
	{

		$resql = $this->db->query("DELETE FROM " . $this->db->prefix() . "operationorderaction WHERE fk_operationorder = '" . $this->id . "'");

		if ($resql) return 1;
		else return -1;
	}

	/**
	 * Close all pointage on going
	 *
	 * @param $user User
	 * @return int
	 */
	public function closeAllPointage($user)
	{

		dol_include_once('operationorder/class/operationordertasktime.class.php');
		$counter = new OperationOrderTaskTime($this->db);
		$resultCounter = $counter->fetchCourantCounterForOR($this->id);
		if (!is_array($resultCounter) && $resultCounter < 0) {
			$this->error = $counter->error;
			$this->errors = array_merge($this->errors, $counter->errors);
			return -1;
		} elseif (!empty($resultCounter)) {
			foreach ($resultCounter as $counterId) {
				$counterToClose = new OperationOrderTaskTime($this->db);
				$counterToClose->fetch($counterId);
				$counterToClose->task_datehour_f = dol_now();
				$counterToClose->task_duration = $counterToClose->task_datehour_f - $counterToClose->task_datehour_d;
				$retUpdateCounter = $counterToClose->update($user);
				if ($retUpdateCounter < 0) {
					$this->error = $counterToClose->error;
					$this->errors = array_merge($this->errors, $counterToClose->errors);
					return -1;
				}
			}
			return 1;
		} else {
			return 0;
		}
	}

	/**
	 * @return int
	 */
	public function updateOperationOrderActions()
	{
		dol_include_once('/operationorder/lib/operationorder.lib.php');
		dol_include_once('/operationorder/class/operationorderaction.class.php');

		global $user;

		$operationorderaction = new OperationOrderAction($this->db);
		$TORActions = $operationorderaction->fetchByOR($this->id);

		if ($TORActions) {
			$operationorderaction = $TORActions[0];

			//update operationorderaction
			$operationorderaction->dated = $this->planned_date;
			if (!empty($this->time_planned_f)) $operationorderaction->datef = $this->calculateEndTimeEventByBusinessHours($operationorderaction->dated, $this->time_planned_f);
			else $operationorderaction->datef = $this->calculateEndTimeEventByBusinessHours($operationorderaction->dated, $this->time_planned_t);
			//dol_syslog('sur le updateOperationOrderActions: $action_or->datef='.dol_print_date($operationorderaction->datef,'dayhour'). ' $operationorderaction->dated='.dol_print_date($operationorderaction->dated,'dayhour'));
			$res = $operationorderaction->save($user);

			if ($res < 0) {
				$this->error = $operationorderaction->error;
				$this->errors[] = $this->error;
				$this->errors = array_merge($operationorderaction->errors, $this->errors);
				return -1;
			} else return $res;
		} else {
			return 0;
		}
	}

	/**
	 * @return int
	 */
	public function isStockAvailable()
	{
		if ($this->planned_date < strtotime('today midnight')) return 1; // Pas besoin de vérifier pour les ORs passés
		$return = $this::OR_STOCK_IS_ENOUGH;
		foreach ($this->lines as $line) {
			if (empty($line->product) && !empty($line->fk_product)) $line->fetch_product();
			if ($line->product->type == Product::TYPE_PRODUCT) {
				if (empty($line->product->stock_reel)) $line->product->load_stock();
				if ($line->product->stock_reel < $line->qty) { //Si on a pas assez de stock physique il faut vérifier le stock virtuel en tenant compte des dates de livraisons des CFs
					if ($line->isVirtualStockAvailableForDate($this->planned_date)) {
						$return = $this::OR_ONLY_PHYSICAL_STOCK_NOT_ENOUGH; //virtual stock available but not physical
					} else { // On break dans ce cas là car ça signifie qu'au moins une ligne n'a pas assez de stocks
						$return = $this::OR_ALL_STOCK_NOT_ENOUGH;
						break;
					}//not enough virtual stock
				}
			}
		}
		return $return;
	}

	/**
	 * @param $fk_operationorder int operation order
	 * @param $forceFetch force
	 * @return false|OperationOrder
	 */
	public function fetchOperationOrderCache($fk_operationorder, $forceFetch = false)
	{
		global $db, $operationOrderCache;

		if (empty($fk_operationorder) || $fk_operationorder < 0) return false;

		if (!empty($operationOrderCache) && !$forceFetch && $operationOrderCache->id == $fk_operationorder) return $operationOrderCache;
		else {
			$operationOrderCache = new OperationOrder($this->db);
			$res = $operationOrderCache->fetch($fk_operationorder, false);
			if ($res) {
				return $operationOrderCache;
			}
		}

		return false;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps

	/**
	 * @param $socid soc id
	 * @param $product product
	 * @return int
	 */
	public function load_stats_operationorder($socid, $product)
	{
		global $user, $hookmanager;
		$this->stats_or['customers'] = 0;
		$this->stats_or['rows'] = 0;
		$this->stats_or['nb'] = 0;
		$this->stats_or['qty'] = 0;

		$sql = "SELECT COUNT(DISTINCT f.fk_soc) as nb_customers, COUNT(DISTINCT f.rowid) as nb,";
		$sql .= " COUNT(fd.rowid) as nb_rows, SUM(" . $this->db->ifsql('fd.qty > 0', 'fd.qty', 'fd.qty * -1') . ") as qty";
		$sql .= " FROM " . $this->db->prefix() . "operationorderdet as fd";
		$sql .= ", " . $this->db->prefix() . "operationorder as f";
		$sql .= ", " . $this->db->prefix() . "societe as s";
		if (!$user->hasRight("societe", "client", "voir") && !$socid) {
			$sql .= ", " . $this->db->prefix() . "societe_commerciaux as sc";
		}
		$sql .= " WHERE f.rowid = fd.fk_operation_order";
		$sql .= " AND f.fk_soc = s.rowid";
		$sql .= " AND f.entity IN (" . getEntity('operationorder') . ")";
		$sql .= " AND fd.fk_product = " . $product;
		if (!$user->hasRight("societe", "client", "voir") && !$socid) {
			$sql .= " AND f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		}
		if ($socid > 0) {
			$sql .= " AND f.fk_soc = " . $socid;
		}
		$result = $this->db->query($sql);

		if ($result) {
			$obj = $this->db->fetch_object($result);
			$this->stats_or['customers'] = $obj->nb_customers;
			$this->stats_or['nb'] = $obj->nb;
			$this->stats_or['rows'] = $obj->nb_rows;
			$this->stats_or['qty'] = $obj->qty ? $obj->qty : 0;
			$parameters = array('socid' => $socid);
			$reshook = $hookmanager->executeHooks('loadStatsoperationorder', $parameters, $this, $action);
			if ($reshook > 0) $this->stats_or = $hookmanager->resArray['stats_facture_or'];

			return 1;
		} else {
			$this->error = $this->db->error();
			return -1;
		}
	}

	/**
	 * @param int $fk_product fk_product
	 * @param int $qty qty
	 * @param boolean $only_with_delai with delay
	 * @param int $fk_soc socid
	 * @return false|float|int
	 */
	public function getMinAvailability($fk_product, $qty, $only_with_delai = false, $fk_soc = 0)
	{


		$sql = "SELECT fk_availability,delivery_time_days
				FROM " . $this->db->prefix() . "product_fournisseur_price
				WHERE fk_product=" . intval($fk_product) . " AND quantity <= " . $qty;

		if (!empty($fk_soc)) {
			$sql .= ' AND fk_soc=' . intval($fk_soc);
		}

		$res_av = $this->db->query($sql);
		if (!$res_av) {
			$this->error = $this->db->lasterror;
			$this->errors[] = $this->error;
			return -1;
		}
		$min = false;

		$form = new Form($this->db);
		if (empty($form->cache_availability)) {
			$form->load_cache_availability();
		}

		while ($obj_availability = $this->db->fetch_object($res_av)) {
			if (!empty($obj_availability->delivery_time_days)) $nb_day = $obj_availability->delivery_time_days;
			else {
				$av_code = $form->cache_availability[$obj_availability->fk_availability];
				$nb_day = $this->getDayFromAvailabilityCode($av_code['code']);
			}
			if (($min === false || $nb_day < $min)
				&& (!$only_with_delai || $nb_day > 0)) $min = $nb_day;
		}

		return $min;
	}

	/**
	 * @param string $av_code avalabity code
	 * @return float|int
	 */
	public function getDayFromAvailabilityCode($av_code)
	{

		if ($av_code == 'AV_NOW') return 0;
		elseif (preg_match('/AV_([0-9]+)([W,D,M]+)/', $av_code, $reg)) {
			$nb = (int) $reg[1];

			if ($reg[2] == 'D') return $nb;
			elseif ($reg[2] == 'W') return $nb * 7;
			elseif ($reg[2] == 'M') return $nb * 31;

			return 0;
		} else {
			return 0;
		}
	}

	/**
	 * @return array
	 */
	public function getOrabledProductFormOR()
	{
		$commande = array();
		$res = $this->fetchObjectLinked();
		if (is_array($this->linkedObjects) && array_key_exists('order_supplier', $this->linkedObjects) && count($this->linkedObjects['order_supplier']) > 0) {
			$i = 0;
			foreach ($this->linkedObjects['order_supplier'] as $order) {
				$commande[] = array_values($this->linkedObjects['order_supplier'])[$i];
				$i++;
			}
		}

		//Construction du tableau des produits deja commandés:
		$alreadyordered = array();
		if (count($commande) > 0) {
			foreach ($commande as $supplierorder) {
				foreach ($supplierorder->lines as $suporderline) {
					$alreadyordered[$suporderline->fk_product] = $alreadyordered[$suporderline->fk_product] + $suporderline->qty;
				}
			}
		}

		//on construit le tableau des produits commmandable
		$TLineQtyUsed = $this->getAlreadyUsedQtyLines();
		if (!is_array($TLineQtyUsed) && $TLineQtyUsed < 0) {
			return -1;
		}
		$TLastLinesByProduct = $this->getLastLinesByProduct();
		$orderableproduct = array();
		$alreadyused = array();
		foreach ($this->lines as $line) {
			$qtyUsed = $line->getQtyUsed($TLineQtyUsed, $TLastLinesByProduct);
			if ($line->product->type == 0 && $line->qty > $qtyUsed) {
				if (!array_key_exists($line->product->id, $alreadyused)) {
					$alreadyused[$line->product->id] = 0;
				}
				$alreadyused[$line->product->id] += $qtyUsed;
				if (!empty($line->fk_warehouse)) {
					$line->product->load_stock('warehouseopen');
					$stock_reel = $line->product->stock_reel;
				} else {
					$stock_reel = 0;
				}
				if ($stock_reel < $line->qty) {
					$orderableproduct[] = $line;
				}
			}
		}


		//construction du tableau des produits à commander
		foreach ($orderableproduct as $line) {
			$alreadyorderedqty = isset($alreadyordered[$line->fk_product]) ? $alreadyordered[$line->fk_product] : 0;
			$alreadyusedqty = isset($alreadyused[$line->fk_product]) ? $alreadyused[$line->fk_product] : 0;
			$qtytoorder = $line->qty - $alreadyorderedqty - $alreadyusedqty;
			if ($qtytoorder > 0) {
				$toorder[$line->fk_product]['line'] = $line;
				$toorder[$line->fk_product]['qty'] = $qtytoorder;
				$toorder[$line->fk_product]['product'] = $line->product;
				$toorder[$line->fk_product]['origin'] = $line->element;
				$toorder[$line->fk_product]['originid'] = $line->id;
				$toorder[$line->fk_product]['alreadyorderedqty'] = $alreadyorderedqty;
				$toorder[$line->fk_product]['alreadyusedqty'] = $alreadyusedqty;
			}
		}

		return $toorder;
	}

	/**
	 * Renvoie les créneaux disponibles en fonction de l'utilisateur, du groupe d'utilisateurs, des absences, des jours fériés et de l'entité (alias BusinessHours)
	 * @param timestamp $startTimeWeek Start Time
	 * @param timestamp $endTimeWeek End Time
	 * @return array|int si planning existe, 0 si inexistant, -1 si erreur
	 */
	public function getOperationOrderUserPlanningSchedule($startTimeWeek = 0, $endTimeWeek = 0)
	{

		require_once DOL_DOCUMENT_ROOT . '/user/class/usergroup.class.php';

		global $conf, $langs;

		dol_syslog(__METHOD__ . ' $startTimeWeek=' . dol_print_date($startTimeWeek) . ' $endTimeWeek=' . dol_print_date($endTimeWeek), LOG_DEBUG);

		if (!empty($this->planningSchedulCache)
			&& array_key_exists($startTimeWeek, $this->planningSchedulCache)
			&& array_key_exists($endTimeWeek, $this->planningSchedulCache[$startTimeWeek])) {
			return $this->planningSchedulCache[$startTimeWeek][$endTimeWeek];
		}
		$TSchedules = array();
		$TSchedulesByUser = array();
		$TDaysOff = array();
		$TDaysConvert = array('Mon' => 'lundi', 'Tue' => 'mardi', 'Wed' => 'mercredi', 'Thu' => 'jeudi', 'Fri' => 'vendredi', 'Sat' => 'samedi', 'Sun' => 'dimanche');

		$dateStart = new DateTime();
		$dateStart->setTimestamp($startTimeWeek);

		$dateEnd = new DateTime();
		$dateEnd->setTimestamp($endTimeWeek);

		//Dates de la semaine en cours
		$TDates = array();

		$jourOff = new OperationOrderJoursOff($this->db);

		$date_start_details = date_parse($dateStart->format('Y-m-d'));
		$date_end_details = date_parse($dateEnd->format('Y-m-d'));

		$debut_date = mktime(0, 0, 0, $date_start_details['month'], $date_start_details['day'], $date_start_details['year']);
		$fin_date = mktime(0, 0, 0, $date_end_details['month'], $date_end_details['day'], $date_end_details['year']);

		for ($i = $debut_date; $i < $fin_date; $i += 86400) {
			$TDates[] = $i;
		}

		//recherche des jours fériés dans la semaine
		foreach ($TDates as $date) {
			$currentDate = date('Y-m-d H:i:s', $date);

			$res = $jourOff->isOff($currentDate);

			if ($res && !in_array($date, $TDaysOff)) {
				$TDaysOff[] = $date;
			}
		}

		//suppression des jours fériés dans les jours à traiter
		foreach ($TDates as $date) {
			if (in_array($date, $TDaysOff)) {
				unset($TDates[array_search($date, $TDates)]);
			}
		}

		//usergroup paramétré
		$fk_groupuser = getDolGlobalInt("OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING");

		//initialisation userplanning
		$userplanning = new OperationOrderUserPlanning($this->db);

		if (!empty($fk_groupuser)) {
			$usergroup = new UserGroupOperationOrder($this->db);
			$usergroup->fetch($fk_groupuser);
			$TUsers = $usergroup->listUsersForGroup();

			//userplanning en fonction des utilisateurs
			//var_dump($TUsers);
			//exit();
			foreach ($TUsers as $user) {
				$res = $userplanning->fetchByObject($user->id, 'user');
				//si l'utilisateur a un planning actif alors on utilise son planning
				if ($res > 0 && $userplanning->active > 0) {
					$TSchedulesByUser[] = $userplanning;
				} else {
					//si l'utilisateur n'a pas de planning actif ou que le planning est inexistant alors on utilise son planning de groupe
					$res = $userplanning->fetchByObject($fk_groupuser, 'usergroup');

					if ($res > 0 && $userplanning->active > 0) {
						$TSchedulesByUser[] = $userplanning;
					}
				}
				if (empty($TSchedulesByUser)) {
					$this->error = $langs->trans('ImcompleteSetup', $langs->transnoentities('PlanningGroupOrUser'));
					$this->errors[] = $this->error;
					return -1;
				}
				//On récupère toutes les absences de l'utilisateur pour la semaine
				$TAbsences = array();

				if (isModEnabled('absence')) {
					$PDOdb = new TPDOdb;
					$absence = new TRH_Absence($this->db);

					$TPlanning = $absence->requetePlanningAbsence2($PDOdb, '', $user->id, $dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d'));
					foreach ($TPlanning as $t_current => $TAbsence) {
						foreach ($TAbsence as $fk_user => $TRH_absenceDay) {
							foreach ($TRH_absenceDay as $absence) {
								if (!($absence->isPresence)) {
									$absenceDateTimestamp = strtotime($absence->date);

									if (!empty($absence) && $absence->ddMoment == 'matin' && $absence->dfMoment == 'apresmidi') {
										$TAbsences[] = $absenceDateTimestamp . '_am';
										$TAbsences[] = $absenceDateTimestamp . '_pm';
									} elseif (!empty($absence) && $absence->ddMoment == 'matin' && $absence->dfMoment == 'matin') {
										$TAbsences[] = $absenceDateTimestamp . '_am';
									} elseif (!empty($absence) && $absence->ddMoment == 'apresmidi' && $absence->dfMoment == 'apresmidi') {
										$TAbsences[] = $absenceDateTimestamp . '_pm';
									}
								} else {
									$datetime = new DateTime($absence->date);
									$day = $datetime->format('D');
									$day = $TDaysConvert[$day];
									$hourMorningStart = date('H:i', strtotime($absence->date_hourStart));
									$hourMorningEnd = date('H:i', strtotime($absence->date_hourMorningEnd));
									$hourAfternoonStart = date('H:i', strtotime($absence->date_hourAfternoonStart));
									$hourAfternoonEnd = date('H:i', strtotime($absence->date_hourEnd));
									$pos = count($TSchedulesByUser);
									$TSchedulesByUser[$pos] = new OperationOrderUserPlanning($this->db);
									$TSchedulesByUser[$pos]->{$day . '_heuredam'} = $hourMorningStart;
									$TSchedulesByUser[$pos]->{$day . '_heurefam'} = $hourMorningEnd;
									$TSchedulesByUser[$pos]->{$day . '_heuredpm'} = $hourAfternoonStart;
									$TSchedulesByUser[$pos]->{$day . '_heurefpm'} = $hourAfternoonEnd;
								}
							}
						}
					}
				}

				foreach ($TDates as $date) {
					$i = 0;
					$datetime = new DateTime();
					$datetime->setTimestamp($date);

					$day = $datetime->format('D');

					$day = $TDaysConvert[$day];

					foreach ($TSchedulesByUser as $userplanning) {
						if (empty($userplanning->{$day . '_heuredam'})
							&& empty($userplanning->{$day . '_heurefam'})
							&& empty($userplanning->{$day . '_heuredpm'})
							&& empty($userplanning->{$day . '_heurefpm'}))
							continue;

						if (empty($userplanning->{$day . '_heuredam'}) || !empty(in_array($date . '_am', $TAbsences))) $userplanning->{$day . '_heuredam'} = '00:00';
						if (empty($userplanning->{$day . '_heurefam'}) || !empty(in_array($date . '_am', $TAbsences))) $userplanning->{$day . '_heurefam'} = '00:00';
						if (empty($userplanning->{$day . '_heuredpm'}) || !empty(in_array($date . '_pm', $TAbsences))) $userplanning->{$day . '_heuredpm'} = '00:00';
						if (empty($userplanning->{$day . '_heurefpm'}) || !empty(in_array($date . '_pm', $TAbsences))) $userplanning->{$day . '_heurefpm'} = '00:00';

						if (empty($TSchedules[$date])) {
							$TSchedules[$date][$i]['min'] = $userplanning->{$day . '_heuredam'};
							$TSchedules[$date][$i]['max'] = $userplanning->{$day . '_heurefam'};
							$i++;
							$TSchedules[$date][$i]['min'] = $userplanning->{$day . '_heuredpm'};
							$TSchedules[$date][$i]['max'] = $userplanning->{$day . '_heurefpm'};
						} else {
							$scheduletoaddam = true;
							$scheduletoaddpm = true;
							foreach ($TSchedules[$date] as &$schedule) {
								//si l'heure de début est inférieure au minimum et que l'heure de fin est contenue dans le créneau, alors on usurpe le minimum
								if ($userplanning->{$day . '_heuredam'} < $schedule['min'] && ($userplanning->{$day . '_heurefam'} <= $schedule['max'] && $userplanning->{$day . '_heurefam'} >= $schedule['min'])) {
									$schedule['min'] = $userplanning->{$day . '_heuredam'};
									$scheduletoaddam = false;
								} elseif ($userplanning->{$day . '_heuredpm'} < $schedule['min'] && ($userplanning->{$day . '_heurefpm'} <= $schedule['max'] && $userplanning->{$day . '_heurefpm'} >= $schedule['min'])) {
									$schedule['min'] = $userplanning->{$day . '_heuredpm'};
									$scheduletoaddpm = false;
								} elseif ($userplanning->{$day . '_heurefam'} > $schedule['max'] && ($userplanning->{$day . '_heuredam'} >= $schedule['min'] && $userplanning->{$day . '_heuredam'} <= $schedule['max'])) {
									//si l'heure de fin est supérieure au maximum et que l'heure du début est contenue dans le créneau, alors on usurpe le maximum
									$schedule['max'] = $userplanning->{$day . '_heurefam'};
									$scheduletoaddam = false;
								} elseif ($userplanning->{$day . '_heurefpm'} > $schedule['max'] && ($userplanning->{$day . '_heuredpm'} >= $schedule['min'] && $userplanning->{$day . '_heuredpm'} <= $schedule['max'])) {
									$schedule['max'] = $userplanning->{$day . '_heurefpm'};
									$scheduletoaddpm = false;
								} elseif ($userplanning->{$day . '_heuredam'} <= $schedule['min'] && $userplanning->{$day . '_heurefam'} >= $schedule['max']) {
									//si l'heure de fin est supérieure au maximum et que l'heure de début est inférieure au minimum alors on usurpe le min et le max
									$schedule['min'] = $userplanning->{$day . '_heuredam'};
									$schedule['max'] = $userplanning->{$day . '_heurefam'};
									$scheduletoaddam = false;
								} elseif ($userplanning->{$day . '_heuredpm'} <= $schedule['min'] && $userplanning->{$day . '_heurefpm'} >= $schedule['max']) {
									$schedule['min'] = $userplanning->{$day . '_heuredpm'};
									$schedule['max'] = $userplanning->{$day . '_heurefpm'};
									$scheduletoaddpm = false;
								} elseif ($userplanning->{$day . '_heuredam'} >= $schedule['min'] && $userplanning->{$day . '_heurefam'} <= $schedule['max']) {
									$scheduletoaddam = false;
								} elseif ($userplanning->{$day . '_heuredpm'} >= $schedule['min'] && $userplanning->{$day . '_heurefpm'} <= $schedule['max']) {
									$scheduletoaddpm = false;
								}
							}

							if ($scheduletoaddam) {
								$TSchedules[$date][] = array('min' => $userplanning->{$day . '_heuredam'}, 'max' => $userplanning->{$day . '_heurefam'});
							} elseif ($scheduletoaddpm) {
								$TSchedules[$date][] = array('min' => $userplanning->{$day . '_heuredpm'}, 'max' => $userplanning->{$day . '_heurefpm'});
							}
						}
						$i++;
					}
				}
			}
		} else {
			$this->error = $langs->trans('ImcompleteSetup', $langs->transnoentities('OPERATION_ORDER_GROUPUSER_DEFAULTPLANNING'));
			$this->errors[] = $this->error;
			return -1;
		}

		$this->planningSchedulCache[$startTimeWeek][$endTimeWeek] = $TSchedules;

		return $TSchedules;
	}

	/**
	 * @param int $fk_groupuser user group id
	 * @return array
	 */
	public function getOperationOrderTUserPlanningFromGroup($fk_groupuser)
	{


		$TSchedulesByUser = array();
		$userGroupPlanning = new OperationOrderUserPlanning($this->db);

		if (!empty($fk_groupuser)) {
			$userGroupPlanning->fetchByObject($fk_groupuser, 'usergroup');

			$usergroup = new UserGroupOperationOrder($this->db);
			$res = $usergroup->fetch($fk_groupuser);
			if ($res > 0) {
				$TUsers = $usergroup->listUsersForGroup();
				if (!empty($TUsers)) {
					//userplanning en fonction des utilisateurs
					foreach ($TUsers as $user) {
						$userplanning = new OperationOrderUserPlanning($this->db);
						$res = $userplanning->fetchByObject($user->id, 'user');
						//FHE : create default planning Ticket 0000025
						//var_dump($userplanning);
						foreach ($userplanning as $varname => $val) {
							if (strpos($varname, 'heured') > 0) {
								//$userplanning->{$varname} = '06:01';
								$userplanning->{$varname} = $val;
							}
							if (strpos($varname, 'heuref') > 0) {
								//$userplanning->{$varname} = '21:00';
								$userplanning->{$varname} = $val;
							}
						}
						//si l'utilisateur a un planning actif alors on utilise son planning
						if ($res > 0 && $userplanning->active > 0) {
							$TSchedulesByUser[$user->id] = $userplanning;
						} else {
							//si l'utilisateur n'a pas de planning actif ou que le planning est inexistant alors on utilise son planning de groupe

							if ($userGroupPlanning->rowid > 0) {
								$TSchedulesByUser[$user->id] = $userGroupPlanning;
							}
						}
					}
				}
			}
		}

		return $TSchedulesByUser;
	}

	/**
	 * Renvoie tous les créneaux qui suivent l'horaire donné sur trois semaines
	 * @param timestamp $startTime start time
	 * @return array $TSchedulesFinal
	 */
	public function getNextSchedules($startTime)
	{
		dol_include_once('/operationorder/lib/operationorder.lib.php');
		$TSchedulesFinal = array();

		dol_syslog(get_class($this) . ' getNextSchedules $startTime=' . dol_print_date($startTime, 'dayhour'), LOG_DEBUG);

		$toadd = 0;             //compteur du nombre de semaine de créneauxà ajouter
		$i = 0;

		$TWeekDates = getWeekRange($startTime);     //dates de la semaine en cours
		$beginOfWeek = $TWeekDates[0];              //début de la semaine
		$endOfWeek = end($TWeekDates);         //fin de la semaine

		while ($toadd <= 3) {
			$TBusinessHours = $this->getOperationOrderUserPlanningSchedule($beginOfWeek, $endOfWeek);
			if (!is_array($TBusinessHours) && $TBusinessHours < 0) {
				return $TBusinessHours;
			}
			$TBusinessHours = sortBusinessHours($TBusinessHours);
			foreach ($TBusinessHours as $date => $TSchedules) {
				$currentDate = new DateTime();
				$currentDate->setTimestamp($date);
				$currentDateFormat = $currentDate->format('Y-m-d');

				$startDate = new DateTime();
				$startDate->setTimestamp($startTime);
				$startDateFormat = $startDate->format('Y-m-d');
				//var_dump('$startDate',dol_print_date($startTime,'dayhour'));
				if ($startDateFormat == $currentDateFormat) $toadd++;
				//var_dump($startDateFormat,$currentDateFormat,$toadd);
				//dès qu'on tombe sur le créneau en cours, on commence à ajouter dans le tableau $TSchedulesFinal
				if ($toadd) {
					foreach ($TSchedules as $schedule) {
						$TScheduleMin = explode(':', $schedule['min']);
						$timestampMin = $date + convertTime2Seconds($TScheduleMin[0], $TScheduleMin[1]);
						//var_dump('$timestampMin',dol_print_date($timestampMin,'dayhour'),$i);
						$TScheduleMax = explode(':', $schedule['max']);
						$timestampMax = $date + convertTime2Seconds($TScheduleMax[0], $TScheduleMax[1]);
						//var_dump('$timestampMax',dol_print_date($timestampMax,'dayhour'),$i);
						//var_dump(dol_print_date($timestampMin,'dayhour'),dol_print_date($startDate->getTimestamp(),'dayhour'),dol_print_date($timestampMax,'dayhour'));
						if (empty($i)
							&& (
								($startDate->getTimestamp() < $timestampMin)
								|| ($startDate->getTimestamp() > $timestampMax)
							)) {
							//var_dump('continue');
							continue;
						} else {
							//var_dump('may be');
							if ($schedule['min'] != "00:00" && $schedule['max'] != "00:00") {
								$TSchedulesFinal[$i]['date'] = $date;
								$TSchedulesFinal[$i]['min'] = $schedule['min'];
								$TSchedulesFinal[$i]['max'] = $schedule['max'];
								$i++;
							}
						}
					}
				}
			}

			$toadd++;

			//on passe à la semaine suivante
			$beginOfWeek = $endOfWeek;
			$endOfWeek = $beginOfWeek + 24 * 60 * 60 * 7;
		}

		return $TSchedulesFinal;
	}

	/**
	 * Calcule la date de fin d'un événement OR en fonction du début de l'événement, de sa durée et des BusinessHours
	 * @param int $startTime start time
	 * @param int $duration duration in seconds
	 * @return timestamp $endTime  or -1 if KO
	 */
	public function calculateEndTimeEventByBusinessHours($startTime, $duration)
	{
		global $langs;
		dol_syslog(get_class($this) . ' calculateEndTimeEventByBusinessHours $startTime=' . dol_print_date($startTime) . ' $duration=' . $duration, LOG_DEBUG);

		//fin de l'événement
		$endTime = $startTime + $duration;

		$i = 0;

		$durationRest = $duration;

		//créneaux suivants
		$TNextSchedules = $this->getNextSchedules($startTime);
		//cas où il n'y a pas de créneaux disponibles (pas d'utilisateurs paramétrés ou pas de businessHours libres)
		if (empty($TNextSchedules)) {
			$this->error = $langs->trans('CannotGetNextSchedules');
			$this->errors[] = $this->error;
			return -1;
		}

		//tant qu'il reste du temps pas traité
		while ($durationRest > 0) {
			//date de début du créneau
			$TScheduleD = explode(':', $TNextSchedules[$i]['min']);
			if (!empty($i)) $dateDScheduleTimeStamp = $TNextSchedules[$i]['date'] + convertTime2Seconds($TScheduleD[0], $TScheduleD[1]);
			else $dateDScheduleTimeStamp = $startTime;
			$dateDSchedule = new DateTime();
			$dateDSchedule->setTimestamp($dateDScheduleTimeStamp);

			//date de fin du créneau
			$TScheduleF = explode(':', $TNextSchedules[$i]['max']);
			$dateFScheduleTimeStamp = $TNextSchedules[$i]['date'] + convertTime2Seconds($TScheduleF[0], $TScheduleF[1]);
			$dateFSchedule = new DateTime();
			$dateFSchedule->setTimestamp($dateFScheduleTimeStamp);

			//temps du créneau
			$timeSchedule = $dateDSchedule->diff($dateFSchedule);
			$timeSchedule = convertTime2Seconds($timeSchedule->h, $timeSchedule->i);

			//si il ne reste pas de temps d'événement on calcule la fin du créneau
			if (($durationRest - $timeSchedule) <= 0) {
				$dateDSchedule = $dateDSchedule->format('H:i');
				$dateDSchedule = explode(':', $dateDSchedule);
				$timeDSchedule = convertTime2Seconds($dateDSchedule[0], $dateDSchedule[1]);
				$endTime = $TNextSchedules[$i]['date'] + $timeDSchedule + $durationRest;
				$durationRest = 0;
			} else {
				$durationRest = $durationRest - $timeSchedule;
			}

			$i++;
		}

		return $endTime;
	}


	/**
	 * Création d'un événement OR en fonction d'une date de début, d'une date de fin et d'un ordre de réparation
	 * @param timestamp $startTime start time
	 * @return  int         1 if OK, -1 if KO
	 */
	public function createOperationOrderAction($startTime, $fromwebportal = false)
	{

		global $user, $conf;

		dol_include_once('/operationorder/class/operationorderaction.class.php');

		$error = 0;

		$this->db->begin();

		if (!empty($this->id)) {
			$this->fetch($this->id);

			$action_or = new OperationOrderAction($this->db);
			$action_or->dated = $startTime;

			//OR temps forcé ou temps théorique ou rien
			if ($this->time_planned_f) {
				$action_or->datef = $this->calculateEndTimeEventByBusinessHours($startTime, $this->time_planned_f);
			} else {
				$action_or->datef = $this->calculateEndTimeEventByBusinessHours($startTime, $this->time_planned_t);
			}

			if (!$fromwebportal) {
				//si il n'y a pas de date de fin disponible, alors on ne créé pas l'événement
				if ($action_or->datef <= 0) {
					$this->error = 'CannotSetDatef';
					$this->errors[] = $this->error;
					$error++;
				}
			}
			if (empty($error)) {
				$action_or->fk_operationorder = $this->id;
				$action_or->fk_user_author = $user->id;

				$res = $action_or->save($user);
				if ($res < 0) {
					$this->error = $action_or->error;
					$this->errors[] = $this->error;
					$this->errors = array_merge($action_or->errors, $this->errors);
					$error++;
				}
			}
		} else {
			$error++;
		}
		if (!$error) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1 * $error;
		}
	}

	/**
	 * Vérifie si le créneau donné est compris dans un créneau de businessHours
	 * @param timestamp $startTime start time
	 * @return boolean
	 */
	public function verifyScheduleInBusinessHours($startTime)
	{
		dol_include_once('/operationorder/lib/operationorder.lib.php');

		$TWeekDates = getWeekRange($startTime);     //dates de la semaine en cours
		$beginOfWeek = $TWeekDates[0];              //début de la semaine
		$endOfWeek = end($TWeekDates);         //fin de la semaine

		$TBusinessHours = $this->getOperationOrderUserPlanningSchedule($beginOfWeek, $endOfWeek);
		if (!is_array($TBusinessHours) && $TBusinessHours < 0) {
			return $TBusinessHours;
		}
		$TBusinessHours = sortBusinessHours($TBusinessHours);

		foreach ($TBusinessHours as $date => $TSchedule) {
			foreach ($TSchedule as $schedule) {
				$TScheduleMin = explode(':', $schedule['min']);
				$TScheduleMax = explode(':', $schedule['max']);

				if ($startTime >= ($date + convertTime2Seconds($TScheduleMin[0], $TScheduleMin[1])) && $startTime <= ($date + convertTime2Seconds($TScheduleMax[0], $TScheduleMax[1]))) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Renvoie le temps plannifié d'un événement OR en fonction de sa date de début, de sa date de fin et des businessHours
	 * @param timestamp $startTime start time
	 * @param timestamp $endTime end time
	 * @return string (seconds) $time_planned
	 */
	public function calculatePlannedTimeEventByBusinessHours($startTime, $endTime)
	{

		dol_syslog(get_class($this) . '::' . __METHOD__ . ' $startTime=' . dol_print_date($startTime, 'dayhour'), LOG_DEBUG);
		//créneau actuel + créneaux suivants
		$TNextSchedules = $this->getNextSchedules($startTime);

		$time_planned = 0;  //temps plannifié
		$i = 0;
		$lastSchedule = false;

		while (!$lastSchedule && $i <= 20) {
			//date début créneau en cours
			$TScheduleD = explode(':', $TNextSchedules[$i]['min']);
			if (!empty($i)) $dateDScheduleTimeStamp = $TNextSchedules[$i]['date'] + convertTime2Seconds($TScheduleD[0], $TScheduleD[1]);
			else $dateDScheduleTimeStamp = $startTime;
			$dateDSchedule = new DateTime();
			$dateDSchedule->setTimestamp($dateDScheduleTimeStamp);

			//date fin créneau en cours
			$TScheduleF = explode(':', $TNextSchedules[$i]['max']);
			$dateFScheduleTimeStamp = $TNextSchedules[$i]['date'] + convertTime2Seconds($TScheduleF[0], $TScheduleF[1]);
			$dateFSchedule = new DateTime();
			$dateFSchedule->setTimestamp($dateFScheduleTimeStamp);

			//temps du créneau
			if ($endTime > $dateFScheduleTimeStamp) {
				$timeSchedule = $dateDSchedule->diff($dateFSchedule);
			} else {
				$lastSchedule = true;       //dernier créneau à traiter

				$endTimeDateFormat = new DateTime();
				$endTimeDateFormat->setTimestamp($endTime);

				if ($endTime < $dateDScheduleTimeStamp) {
					//si la date de fin est placée hors créneau
					$TPrevScheduleF = explode(':', $TNextSchedules[$i - 1]['max']);
					$prevDateFScheduleTimeStamp = $TNextSchedules[$i - 1]['date'] + convertTime2Seconds($TPrevScheduleF[0], $TPrevScheduleF[1]);
					$prevDateFSchedule = new DateTime();
					$prevDateFSchedule->setTimestamp($prevDateFScheduleTimeStamp);

					$timeSchedule = $prevDateFSchedule->diff($endTimeDateFormat);
				} else {
					//si la date de fin est placée sur un créneau
					$timeSchedule = $dateDSchedule->diff($endTimeDateFormat);
				}
			}

			//convertis temps du créneau en secondes
			$timeSchedule = convertTime2Seconds($timeSchedule->h, $timeSchedule->i);

			//ajout du temps du créneau sur le temps plannifié
			$time_planned += $timeSchedule;

			$i++;
			if ($i == 20) {
				$this->errors[] = 'Cannot find Next Schedule';
				return -1;
			}
		}

		return $time_planned;
	}

	/**
	 * Action executed by scheduler
	 * CAN BE A CRON TASK. In such a case, parameters come from the schedule job setup field 'Parameters'
	 * Use public function doScheduledJob($param1, $param2, ...) to get parameters
	 *
	 * @return    int            0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function doScheduledJob()
	{
		global $conf, $langs, $mysoc, $user;

		//getDolGlobalString("SYSLOG_FILE")  = 'DOL_DATA_ROOT/dolibarr_mydedicatedlofile.log';

		$langs->load('operationorder@operationorder');

		$error = 0;
		$this->output = '';
		$this->error = '';
		$sendMail = false;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$now = dol_now();

		$this->db->begin();

		$this->output .= $langs->trans('OperationOrderStartJob', dol_print_date($now, 'dayhourtext')) . '<BR> <BR>';

		//Close pointage
		$autoclosetime = getDolGlobalString("OPERATIONORDER_AUTO_CLOSE_POINTAGE_TIME");
		if (!empty($autoclosetime)) {
			dol_include_once('/operationorder/class/operationordertasktime.class.php');

			$hourtime = explode(':', $autoclosetime);
			if (is_array($hourtime) && count($hourtime) == 2) {
				$sql = "SELECT fk_user FROM " . $this->db->prefix() . "operationordertasktime WHERE (task_datehour_f IS NULL) OR (task_datehour_f = 0) GROUP BY fk_user";
				$resql = $this->db->query($sql);
				if ($resql) {
					$num = $this->db->num_rows($resql);
					if ($num > 0) {
						while ($obj = $this->db->fetch_object($resql)) {
							$usrPointage = new User($this->db);
							$usrPointage->fetch($obj->fk_user);

							$counter = new OperationOrderTaskTime($this->db);
							$ret = $counter->fetchCourantCounter($obj->fk_user);

							if ($ret > 0) {
								$counter->task_datehour_f = dol_mktime($hourtime[0], $hourtime[1], 0, dol_print_date($now, '%m'), dol_print_date($now, '%d'), dol_print_date($now, '%Y'));
								if ($counter->task_datehour_f < dol_now()) {
									$counter->task_duration = $counter->task_datehour_f - $counter->task_datehour_d;
									$retupd = $counter->update($usrPointage);

									if ($retupd > 0) {
										if ($counter->fk_orDet > 0) {
											// mise à jour du temps passé sur la ligne pointable
											$ordet = new operationorderLine($this->db);
											$ordet->fetch($counter->fk_orDet);
											$or = new OperationOrder($this->db);
											if (!empty($ordet->fk_operation_order)) {
												$or->fetch($ordet->fk_operation_order);
												$or->updateline($ordet->id,
													$ordet->description,
													$ordet->qty,
													$ordet->price,
													$ordet->fk_warehouse,
													$ordet->pr,
													$ordet->time_planned,
													($ordet->time_spent + $counter->task_duration),
													$ordet->fk_product,
													0,
													$ordet->date_start,
													$ordet->date_end,
													$ordet->type,
													$ordet->fk_parent_line,
													$ordet->label,
													$ordet->special_code,
													$ordet->array_options,
													0,
													0,
													$ordet->remise_percent);
											}

											$remaining = $counter->remainingCountersForOR($ordet->id);
											// changement de statut de l'OR de la ligne
											if (!empty(getDolGlobalInt('OPORDER_CHANGE_OR_STATUS_ON_STOP')) && !empty(getDolGlobalInt('OPODER_STATUS_ON_STOP')) && !$remaining && !empty($or->id)) {
												$resultUpdStatus = $or->setStatus($usrPointage, getDolGlobalInt('OPODER_STATUS_ON_STOP'));
												if ($resultUpdStatus < 0) {
													$this->error = $or->error;
													$this->errors[] = $this->error;
													$this->errors = array_merge($this->errors, $or->errors);
													$error++;
												} else {
													$this->output .= $langs->transnoentities('ORUpdatedStopJob', $or->ref) . '<BR>';
													$sendMail = true;
												}
											}
										}
										$this->output .= $langs->trans('MsgCounterStop', $counter->label, $usr->login) . '<BR>';
										$sendMail = true;
									} else {
										$this->errors[] = $langs->trans('ErreurCounterStop', $counter->label, $usr->login);
										$error++;
									}
								}
							} else {
								$this->errors[] = $this->db->lasterror();
								$error++;
							}
						}
					}
				}
			}

			//MAJ PVMin produit

			//Move OR not done to today
			$ORStatusToReplan = getDolGlobalString('OPERATIONORDER_AUTO_RESCHEDUL_OR_STATUS');
			if (!empty($ORStatusToReplan)) {
				$TORStatusToReplan = explode(',', $ORStatusToReplan);
				$timeLimit = dol_mktime(23, 59, 59, dol_print_date($now, '%m'), dol_print_date($now, '%d'), dol_print_date($now, '%Y'));
				if (is_array($TORStatusToReplan) && count($TORStatusToReplan) > 0) {
					$sql = "SELECT DISTINCT o.rowid as orid FROM " . $this->db->prefix() . "operationorder as o";
					$sql .= " INNER JOIN " . $this->db->prefix() . "operationorder_status as st ON o.status = st.rowid ";
					$sql .= " INNER JOIN " . $this->db->prefix() . "operationorderaction as a ON a.fk_operationorder = o.rowid ";
					$sql .= " WHERE st.code IN ('" . implode("','", $TORStatusToReplan) . "') AND a.datef <= '" . $this->db->idate($timeLimit) . "'";
					$resql = $this->db->query($sql);
					if ($resql) {
						$num = $this->db->num_rows($resql);
						if ($num > 0) {
							dol_include_once('/operationorder/class/operationorderaction.class.php');
							require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
							while ($obj = $this->db->fetch_object($resql)) {
								$or = new self($this->db);
								$or->fetch($obj->orid);
								$newdatetime = dol_mktime(
									dol_print_date($or->planned_date, '%H'),
									dol_print_date($or->planned_date, '%M'),
									0,
									dol_print_date($now, '%m'),
									dol_print_date($now, '%d'),
									dol_print_date($now, '%Y'));
								$or->planned_date = dol_time_plus_duree($newdatetime, 1, 'd');
								$resultUpdOr = $or->save($user);
								if ($resultUpdOr < 0) {
									$this->error = $or->error;
									$this->errors[] = $this->error;
									$this->errors = array_merge($this->errors, $or->errors);
									$error++;
								}
								$this->output .= $langs->trans('OperationOrderReschedulJob', $or->ref, dol_print_date($or->planned_date, 'dayhour')) . '<BR>';
								$sendMail = true;
							}
						}
					} else {
						$this->errors[] = $this->db->lasterror();
						$error++;
					}
				}
			}


			if (empty($error)) {
				$this->db->commit();
			} else {
				$this->db->rollback();
				$sendMail = true;
			}

			$this->output .= '<BR>' . $langs->trans('OperationOrderEndJob', dol_print_date(dol_now(), 'dayhourtext')) . '<BR>';


			if (!empty($this->errors)) {
				$this->output .= $langs->trans('Error') . '<span style="color: red">' . implode('<BR>', $this->errors) . '<BR></span>';
			}

			if (getDolGlobalString('OPERATIONORDER_AUTO_CLOSE_EMAIL') && $sendMail) {
				require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
				$userReportEmail = getDolGlobalString('OPERATIONORDER_AUTO_CLOSE_EMAIL');
				$cmail = new CMailFile($langs->transnoentities('OperationOrderJobRunResult', $mysoc->name), $userReportEmail, getDolGlobalString("MAIN_MAIL_EMAIL_FROM"), nl2br($this->output), array(), array(), array(), '', '', 0, 1);
				$result = $cmail->sendfile();
				if ($result < 0 || !$result) {
					if (is_array($cmail->errors)) {
						$this->errors = array_merge($this->errors, $cmail->errors);
					}
					$this->errors[] = 'Send Mail Error : ' . $cmail->error;
					$error++;
				}
			}

			if (strlen($this->output) > 4294967295) {
				$this->output = substr($this->output, 0, 4294967295 - 1);
			}
		}
		return (!empty($error) ? $error : 0);
	}
}

/**
 * Class operationorderLine
 */
class operationorderLine extends SeedObject
{
	public $table_element = 'operationorderdet';

	public $element = 'operationorderdet';

	//public $fk_element = 'fk_operation_order';

	/** @var int $isextrafieldmanaged Enable the fictionalises of extrafields */
	public $isextrafieldmanaged = 1;

	public $fields = array(
		'fk_operation_order' => array(
			'type' => 'integer',
			'label' => 'OperationOrder',
			'enabled' => 1,
			'position' => 5,
			'notnull' => 1,
			'visible' => 0,
		),
		'fk_product' => array(
			'type' => 'integer:Product:product/class/product.class.php:1',
			'required' => 1,
			'label' => 'Product',
			'enabled' => 1,
			'position' => 1,
			'notnull' => -1,
			'visible' => -1,
			'index' => 1,
		),
		'fk_parent_line' => array(
			'type' => 'integer',
			'label' => 'Inclure dans',
			'enabled' => 1,
			'visible' => 1,
			'position' => 10,
		),
		'price' => array(
			'type' => 'real',
			'label' => 'UnitPrice',
			'enabled' => 1,
			'position' => 30,
			'notnull' => 0,
			'required' => 1,
			'visible' => 1,
		),
		'remise_percent' => array(
			'type' => 'real',
			'label' => 'DiscountPercent',
			'enabled' => 1,
			'position' => 40,
			'notnull' => 0,
			'required' => 0,
			'visible' => 1,
			'noteditable' => '1',
			'default' => '0'
		),
		'description' => array(
			'type' => 'html',
			'label' => 'Description',
			'enabled' => 1,
			'position' => 20,
			'notnull' => 0,
			'visible' => 3,
		),
		'fk_c_operationorder_type' => array(
			'type' => 'integer:OperationOrderDictType:operationorder/class/operationorder.class.php:0:',
			'label' => 'LineOperationOrderType',
			'enabled' => 1,
			'position' => 25,
			'visible' => 1,
			'foreignkey' => 'c_operationorder_type.rowid',
			'notnull' => 0
		),
		'qty' => array(
			'type' => 'real',
			'required' => 1,
			'label' => 'Qty',
			'enabled' => 1,
			'position' => 50,
			'notnull' => 0,
			'visible' => 1,
			'default' => 1,
			'isameasure' => '1',
			'css' => 'maxwidth75imp',
		),
		'fk_warehouse' => array(
			'type' => 'varchar(255)',
			'label' => 'StockPlace',
			'length' => 255,
			'enabled' => 1,
			'position' => 60,
			'visible' => 1,
		),
		'time_planned' => array(
			'type' => 'integer',
			'label' => 'TimePlanned',
			'enabled' => 1,
			'position' => 70,
			'notnull' => 0,
			'visible' => 1,
		),
		'time_spent' => array(
			'type' => 'integer',
			'label' => 'TimeSpent',
			'enabled' => 1,
			'position' => 80,
			'notnull' => 0,
			'visible' => 1,
		),
		'product_type' => array(
			'type' => 'integer',
			'label' => 'ProductType',
			'enabled' => 1,
			'position' => 90,
			'notnull' => 1,
			'visible' => 0,
		),
		'rang' => array(
			'type' => 'integer',
			'label' => 'Rank',
			'enabled' => 1,
			'position' => 100,
			'notnull' => 0,
			'visible' => 0,
		),
		'fk_user_creat' => array(
			'type' => 'integer:User:user/class/user.class.php',
			'label' => 'UserAuthor',
			'enabled' => 1,
			'position' => 510,
			'notnull' => 1,
			'visible' => -2,
			'foreignkey' => 'user.rowid',
		),
		'fk_user_modif' => array(
			'type' => 'integer:User:user/class/user.class.php',
			'label' => 'UserModif',
			'enabled' => 1,
			'position' => 511,
			'notnull' => 0,
			'visible' => -2,
		),
		'import_key' => array(
			'type' => 'varchar(14)',
			'length' => 14,
			'label' => 'ImportId',
			'enabled' => 1,
			'position' => 1000,
			'notnull' => -1,
			'visible' => -2,
		),
		'info_bits' => array(
			'type' => 'int',
			'visible' => 0,
		),
		'pr' => array(
			'type' => 'real',
			'label' => 'CostPrice',
			'enabled' => 1,
			'position' => 1001,
			'notnull' => 0,
			'required' => 0,
			'visible' => 0,
		),
		'total_ht_mo' => array(
			'type' => 'real',
			'label' => 'LineTotalHTMO',
			'enabled' => 1,
			'position' => 1003,
			'notnull' => 1,
			'required' => 0,
			'visible' => 5,
			'noteditable' => '1',
			'default' => '0'
		),
		'total_ht_part' => array(
			'type' => 'real',
			'label' => 'LineTotalHTPart',
			'enabled' => 1,
			'position' => 1004,
			'notnull' => 1,
			'required' => 0,
			'visible' => 5,
			'noteditable' => '1',
			'default' => '0'
		),
		'total_ht_service' => array(
			'type' => 'real',
			'label' => 'LineTotalHTService',
			'enabled' => 1,
			'position' => 1005,
			'notnull' => 1, 'required' => 0,
			'visible' => 5,
			'noteditable' => '1',
			'default' => '0'
		),
		'total_ht_external' => array(
			'type' => 'real',
			'label' => 'LineTotalHTExternal',
			'enabled' => 1,
			'position' => 1006,
			'notnull' => 1,
			'required' => 0,
			'visible' => 5,
			'noteditable' => '1',
			'default' => '0'
		),
		'total_ht_reimbursement' => array(
			'type' => 'real',
			'label' => 'LineTotalHTReimbursement',
			'enabled' => 1,
			'position' => 1007,
			'notnull' => 1,
			'required' => 0,
			'visible' => 5,
			'noteditable' => '1',
			'default' => '0'
		),
		'total_ht' => array(
			'type' => 'real',
			'label' => 'LineTotalHT',
			'enabled' => 1,
			'position' => 1008,
			'notnull' => 1,
			'required' => 0, 'visible' => 5,
			'noteditable' => '1',
			'default' => '0'
		),
		'remise' => array(
			'type' => 'real',
			'label' => 'DiscountHT',
			'enabled' => 1,
			'position' => 1009,
			'notnull' => 1,
			'required' => 0,
			'visible' => 5,
			'noteditable' => '1',
			'default' => '0'
		),


	);

	public $fk_operation_order;
	public $fk_product;
	public $fk_parent_line;
	public $description;
	public $qty;
	public $fk_warehouse;
	public $pc;
	public $time_planned;
	public $time_spent;
	public $product_type;
	public $rang;
	public $fk_user_creat;
	public $fk_user_modif;
	public $import_key;
	public $price;
	public $total_ht;
	public $info_bits;
	public $pr;
	public $fk_c_operationorder_type;
	public $total_ht_mo;
	public $total_ht_part;
	public $total_ht_service;
	public $total_ht_external;
	public $total_ht_reimbursement;
	public $remise;
	public $remise_percent;

	/**
	 * @var $product Product
	 */
	public $product;

	/**
	 * operationorderLine constructor.
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->init();
	}

	/**
	 *    Get object and children from database
	 *
	 * @param int $id Id of object to load
	 * @param bool $loadChild used to load children from database
	 * @param string $ref Ref
	 * @param bool $loadProduct used to load product
	 * @return     int                        >0 if OK, <0 if KO, 0 if not found
	 */
	public function fetch($id, $loadChild = true, $ref = null, $loadProduct = true)
	{
		$res = parent::fetch($id, $loadChild, $ref);

		$this->product = new Product($this->db);
		if ($this->fk_product > 0) {
			// Pour palier à l'absence de méthode getLinesArray
			if ($loadProduct) {
				$this->product->fetch($this->fk_product);
				$this->ref = $this->product->ref;
				$this->product_ref = $this->product->ref;
				$this->label = $this->product->label;
			}
		} else {
			$this->product = false;
		}

		// désactivation de l'entrepot pour les services
		if ($this->product_type != 0) {
			$this->fields['fk_warehouse']['visible'] = 0;
		}

		$this->oldcopy = clone $this;
		return $res;
	}

	/**
	 * @return string
	 */
	public function getProductRef()
	{
		$sql = "SELECT ref FROM " . $this->db->prefix() . "product WHERE rowid = " . $this->fk_product;
		$resql = $this->db->query($sql);
		if (!empty($resql)) {
			$obj = $this->db->fetch_object($resql);
			return $obj->ref;
		}
		return '';
	}

	/**
	 * @param $TLineQtyUsed array
	 * @param $TLastLinesByProduct array
	 * @return int
	 */
	public function getQtyUsed(&$TLineQtyUsed, &$TLastLinesByProduct)
	{
		$qtyUsed = 0;
		if (isset($TLineQtyUsed[$this->fk_product])) {
			//s'il y a plus de quantité utilisé que ce qu'il y a dans la ligne
			if ($TLineQtyUsed[$this->fk_product] > $this->qty) {
				//Si on n'est pas sur la dernière ligne mais que tout ne rentre pas
				if ($TLastLinesByProduct[$this->fk_product] != $this->id) {
					$qtyUsed = $this->qty;
					$TLineQtyUsed[$this->fk_product] -= $this->qty;
				} else // Si on est sur la dernière ligne on met tout
				{
					$qtyUsed = $TLineQtyUsed[$this->fk_product];
					unset($TLineQtyUsed[$this->fk_product]);
				}
			} else // Si ça rentre on met dans la ligne actuelle
			{
				$qtyUsed = $TLineQtyUsed[$this->fk_product];
				unset($TLineQtyUsed[$this->fk_product]);
			}
		}
		return $qtyUsed;
	}

	/**
	 * @param $mode mode
	 * @param $url url
	 * @param $params params
	 * @param $warehousetype params
	 * @return string
	 */
	public function stockStatus($mode = '', $url = '', $params = array(), $warehousetype = '')
	{
		global $langs;

		$langs->loadLangs(array('operationorder@operationorder', 'stocks'));

		$out = '';
		if ($this->fk_product > 0 && empty($this->product->type) && $this->product) {
			$this->product->load_stock($warehousetype);
			if (!empty($params['planned_date'])) $this->isVirtualStockAvailableForDate($params['planned_date']);


			if (!empty($params['fk_warehouse'])) {
				$stock_reel = $this->product->stock_reel;
			} else {
				$stock_reel = 0;
			}

			$tooltipLabel = $langs->trans('RealStock') . ' : ' . $stock_reel . '</br>';
			$tooltipLabel .= $langs->trans('VirtualStock') . ' : ' . $this->product->stock_theorique;

			if (empty($params['attr']['title'])) {
				$params['attr']['title'] = $tooltipLabel;
			}

			if ($stock_reel >= $this->qty) {
				$out .= dolGetBadge($langs->trans('StockAvailable') . ' ' . $stock_reel, '', 'success classfortooltip', $mode, $url, $params);
			} elseif ($stock_reel < $this->qty && $this->product->stock_theorique >= $this->qty) {
				$out .= dolGetBadge($langs->trans('VirtualStockAvailable') . ' ' . $stock_reel, '', 'warning classfortooltip', $mode, $url, $params);
			} else {
				$out .= dolGetBadge($langs->trans('NotEnoughStockAvailable') . ' ' . $stock_reel, '', 'danger classfortooltip', $mode, $url, $params);
			}
		}

		return $out;
	}

	/**
	 * Return HTML string to show a field into a page
	 *
	 * @param string $key Key of attribute
	 * @param string $moreparam To add more parameters on html input tag
	 * @param string $keysuffix Prefix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param string $keyprefix Suffix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param mixed $morecss Value for css to define size. May also be a numeric.
	 * @return string
	 */
	public function showOutputFieldQuick($key, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{
		return $this->showOutputField($this->fields[$key], $key, $this->{$key}, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * Return HTML string to show a field into a page
	 * Code very similar with showOutputField of extra fields
	 *
	 * @param array $val Array of properties of field to show
	 * @param string $key Key of attribute
	 * @param string $value Preselected value to show (for date type it must be in timestamp format, for amount or price it must be a php numeric value)
	 * @param string $moreparam To add more parametes on html input tag
	 * @param string $keysuffix Prefix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param string $keyprefix Suffix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param mixed $morecss Value for css to define size. May also be a numeric.
	 * @return string
	 */
	public function showOutputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{
		global $conf, $langs;
		$out = '';
		if ($key == 'fk_warehouse') {
			$warehouse = new Entrepot($this->db);
			$res = $warehouse->fetch($value);
			if ($res > 0) {
				$out .= $warehouse->getNomUrl(1);
			}
		} elseif ($key == 'time_planned' || $key == 'time_spent') {
			if ($key == 'time_planned' && !empty($this->time_planned)) {
				if (!function_exists('convertSecondToTime')) {
					include_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
				}

				$out .= convertSecondToTime(intval($this->time_planned), 'allhourmin');
			} elseif ($key == 'time_spent') {
				if (!function_exists('convertSecondToTime')) {
					include_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
				}

				$out .= convertSecondToTime(intval($this->time_spent), 'allhourmin');
			} else $out .= ' -- ';
		} else {
			$out .= parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
		}

		return $out;
	}


	/**
	 * Return HTML string to put an input field into a page
	 * Code very similar with showInputField of extra fields
	 *
	 * @param array $val Array of properties for field to show
	 * @param string $key Key of attribute
	 * @param string $value Preselected value to show (for date type it must be in timestamp format, for amount or price it must be a php numeric value)
	 * @param string $moreparam To add more parameters on html input tag
	 * @param string $keysuffix Prefix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param string $keyprefix Suffix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param string|int $morecss Value for css to define style/length of field. May also be a numeric.
	 * @param int $nonewbutton nonewbutton
	 * @return string
	 * @throws Exception
	 */
	public function showInputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = 0, $nonewbutton = 0)
	{
		global $langs, $conf, $user;


		if (!empty($this->fields[$key]['required'])) {
			$moreparam .= " required";
		}

		// for cache
		if (empty($this->form)) {
			$this->form = new Form($this->db);
		}

		if (empty($this->formproduct)) {
			include_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
			$this->formproduct = new FormProduct($this->db);
		}

		if ($key == 'fk_product') {
			if ($this->{$key} > 0) {
				// désactivation de l'affichage en mode edition
				$out = '<input type="hidden" class="flat ' . $morecss . '"  name="' . $keyprefix . $key . $keysuffix . '" id="' . $keyprefix . $key . $keysuffix . '" value="' . $value . '" ' . ($moreparam ? $moreparam : '') . '>';
				$out .= $this->showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
				$out .= '<script type="text/javascript">
						' . $this->_getJSDisplayFormORDetField() . '

						$(document).ready(function ()
						{
							let data = {
								"is_job":' . (int) $this->product->array_options['options_or_is_job'] . ',
								"product_type":' . (int) $this->product->type . ',
								"or_scan":' . (int) $this->product->array_options['options_or_scan'] . ',
								"oorder_available_for_supplier_order":' . (int) $this->product->array_options['options_oorder_available_for_supplier_order'] . ',
							}
							setFieldDisplay(data);
						});

					</script>
				';
			} else {
				$out = parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);

				$out .= '<script type="text/javascript">

					' . $this->_getJSDisplayFormORDetField() . '
					$(document).ready(function ()
					{
						$("#field_price").hide();
						$("#field_qty").hide();
						$("#field_remise_percent").hide();
						$("#field_fk_warehouse").hide();
						$("#field_time_planned").hide();
						$("#field_time_spent").hide();
						$("#field_fk_parent_line").hide();
						$("#field_fk_c_operationorder_type").hide();

					    if($("#' . $keyprefix . $key . $keysuffix . '").length>0){
							$("#' . $keyprefix . $key . $keysuffix . '").change(function(){
								$.ajax({
									url: "' . dol_buildpath('operationorder/scripts/interface.php?action=getProductInfos', 1) . '",
									method: "POST",
									data: {
										\'fk_product\' : $( this ).val(),
										\'element\' : \'operationorder\',
										\'element_id\' : ' . intval($this->fk_operation_order) . ',
										\'load_stock\' : \'1\',
										\'token\' : "' . currentToken() . '"
									},
									dataType: "json",
									// La fonction à apeller si la requête aboutie
									success: function (data) {
										$("#unitaire_timehour").remove();
										$("#unitaire_timemin").remove();
										// Loading data
										setFieldDisplay(data);
										if(data.result > 0 ){
											// ok case
											if (data.stock_info){
												let wh_id = [];
											 	$.each(data.stock_info, function(key, value){
											 		wh_id.push(key);
											 	});
											 	if (wh_id.length >0) {
											 		$("#' . $keyprefix . 'fk_warehouse' . $keysuffix . ' option").each(function () {
														if (!wh_id.includes($(this).val())) {
															$(this).remove();
														} else {
															$(this).text($(this).text() + " (Qté:" + data.stock_info[$(this).val()].real+")");
															$(this).attr("data-html",$(this).data("html") + " (Qté:" + data.stock_info[$(this).val()].real+")");
														}
													});
											 	} else {
													$("#' . $keyprefix . 'fk_warehouse' . $keysuffix . ' option[value!="+data.fk_default_warehouse+"]").hide();
											 	}
											}
											$("#' . $keyprefix . 'fk_warehouse' . $keysuffix . '").val(data.fk_default_warehouse).change();
											$("#' . $keyprefix . 'price' . $keysuffix . '").val(data.price);
											$("[name=' . $keyprefix . 'time_plannedhour' . $keysuffix . ']").val(data.time_plannedhour);
											$("[name=' . $keyprefix . 'time_plannedhour' . $keysuffix . ']").after("<input type=\'hidden\' id=\'unitaire_timehour\' value=\'"+data.time_plannedhour+"\' />");
											$("[name=' . $keyprefix . 'time_plannedmin' . $keysuffix . ']").val(data.time_plannedmin);
											$("[name=' . $keyprefix . 'time_plannedmin' . $keysuffix . ']").after("<input type=\'hidden\' id=\'unitaire_timemin\' value=\'"+data.time_plannedmin+"\' />");
										} else {
										   // nothing to do ?
										   $("#' . $keyprefix . 'fk_warehouse' . $keysuffix . '").val(-1).change();
										   $("#' . $keyprefix . 'price' . $keysuffix . '").val("");
										   $("[name=' . $keyprefix . 'time_plannedhour' . $keysuffix . ']").val("");
										   $("[name=' . $keyprefix . 'time_plannedmin' . $keysuffix . ']").val("");
										}

										if(data.errorMsg.length > 0){
											$.jnotify(data.errorMsg, "error", true);
										}

									},
									// La fonction à appeler si la requête n\'a pas abouti
									error: function( jqXHR, textStatus ) {
										alert( "Request failed: " + textStatus );
									}
								})
							});
						}
					});
					</script>
				';
			}
		} elseif ($key == 'qty') {
			$out = '<input type="number" min="0" step="any" class="flat ' . $morecss . '"  name="' . $keyprefix . $key . $keysuffix . '" id="' . $keyprefix . $key . $keysuffix . '" value="' . $value . '" ' . ($moreparam ? $moreparam : '') . '>';
		} elseif ($key == 'time_planned') {
			$out = $this->form->select_duration($keyprefix . $key . $keysuffix, $value, 0, 'text', 0, 1);
		} elseif ($key == 'time_spent') {
			$out = $this->form->select_duration($keyprefix . $key . $keysuffix, $value, 0, 'text', 0, 1);
		} elseif ($key == 'fk_warehouse') {
			if (!empty(isModEnabled("stock"))) {
				if (!empty($this->fk_product)) {
					$out = $this->formproduct->selectWarehouses($value, $keyprefix . $key . $keysuffix, 'warehouseopen', 1, 0, $this->fk_product, '', 1, 0, null, 'csswarehouse');
				} else {
					$out = $this->formproduct->selectWarehouses($value, $keyprefix . $key . $keysuffix, 'warehouseopen', 1);
				}
			} else {
				$out = '<input type="hidden"  name="' . $keyprefix . $key . $keysuffix . '" id="' . $keyprefix . $key . $keysuffix . '" value="' . $value . '" >';
			}
		} elseif ($key == 'fk_parent_line') {
			//if (!empty($this->fk_product)) {
			if (empty($this->product)) {
				$this->fetch_product();
			}
			if (!empty($this->fk_product) && (int) $this->product->hasFatherOrChild(-1) > 0) {
				$out = '<input type="hidden" name="' . $keyprefix . $key . $keysuffix . '" value="' . $this->fk_parent_line . '">';
				$OrDetParent = new operationorderLine($this->db);
				$OrDetParent->fetch($this->fk_parent_line);
				$out .= $OrDetParent->product->ref . ' - ' . $OrDetParent->product->label;
			} else {
				$ORlines = $this->fetchAll(0, true, array("fk_operation_order" => $this->fk_operation_order));
				$selarray = array();
				foreach ($ORlines as $keyORline => $valueORline) {
					if (!empty($valueORline->product->array_options['options_or_is_job'])) {
						$selarray[$valueORline->id] = $valueORline->product->ref . ' - ' . $valueORline->product->label;
					}
				}
				$out = $this->form->selectarray($keyprefix . $key . $keysuffix, $selarray, $this->fk_parent_line, 1, 0, 0, ($moreparam ? $moreparam : ''), 0, 0, 0, '', ($morecss ? $morecss : ''), 1);
			}
			//}
		} else {
			$out = parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
		}

		return $out;
	}

	/**
	 * Return JS script needed for OR det display form
	 * @return string JS script
	 */
	private function _getJSDisplayFormORDetField()
	{
		return 'function setFieldDisplay(data) {

					data.is_job = data.is_job ?? 0;
					data.or_scan = data.or_scan ?? 0;
					data.oorder_available_for_supplier_order = data.oorder_available_for_supplier_order ?? 0;

					$("#field_price").hide();
					$("#field_qty").hide();
					$("#qty").removeAttr("required");
					$("#field_remise_percent").hide();
					$("#field_fk_warehouse").hide();
					$("#field_time_planned").hide();
					$("#field_time_spent").hide();
					$("#field_fk_parent_line").hide();
					$("#field_fk_c_operationorder_type").hide();

					if (data.is_job > 0) {
						$("#field_fk_c_operationorder_type").show();
					}

					if (parseInt(data.product_type) == 0) {
						$("#field_qty").show();
						$("#qty").prop("required","required");
						$("#field_remise_percent").show();
						$("#field_fk_warehouse").show();
						$("#field_fk_parent_line").show();
						$("#field_fk_parent_line .select2-container").css("min-width","300px");
						$("#field_price").show();
					} else if (parseInt(data.product_type) == 1 && data.is_job == 0) {
						$("#field_qty").show();
						$("#qty").prop("required","required");
						$("#field_remise_percent").show();
						$("#field_fk_parent_line").show();
						$("#field_fk_parent_line .select2-container").css("min-width","300px");
						$("#field_price").show();
					}

					if (data.or_scan > 0) {
						$("#field_qty").show();
						$("#field_fk_parent_line").show();
						$("#field_fk_parent_line .select2-container").css("min-width","300px");
						$("#qty").prop("required","required");
						$("#field_remise_percent").show();
						$("#field_time_spent").show();
						$("#field_time_planned").show();
						$("#field_price").show();
					}

					if (data.oorder_available_for_supplier_order > 0) {
						$("#field_price").show();
						$("#field_fk_parent_line").show();
						$("#field_fk_parent_line .select2-container").css("min-width","300px");
					}

			}';
	}


	/**
	 * Function to delete object in database
	 *
	 * @param User $user user object
	 * @param bool $notrigger false=launch triggers after, true=disable triggers
	 * @return  int                 < 0 if ko, > 0 if ok
	 */
	public function delete(User &$user, $notrigger = false)
	{
		global $conf;
		if ($this->id <= 0) return 0;

		$Tlines = $this->fetch_all_children_lines();
		if (is_array($Tlines)) {
			foreach ($Tlines as $line) {
				/**
				 * @var $line operationorderLine
				 */
				if (!empty($this->parent)) $line->parent = $this->parent;
				$res = $line->delete($user, $notrigger);
				if ($res < 0) {
					return -2;
				}
			}
		}

		$oOHistory = new OperationOrderHistory($this->db);
		$oOHistory->saveCreationOrDeletion($this, 'delete');
		$mvtok = 0;
		if ($this->product->product_type == 0) {
			if (empty($this->fk_warehouse)) {
				$dProductWarehouse = new ProductDefaultWarehouse($this->db);
				$defaultWarehouse = $dProductWarehouse->fetch($this->fk_product, $conf->entity);
				if ($defaultWarehouse < 0) {
					$this->error = $dProductWarehouse->error;
					$this->errors += $dProductWarehouse->errors;
					return -1;
				}

				$this->fk_warehouse = $defaultWarehouse;
			}
			dol_include_once('operationorder/class/operationorder.class.php');
			$operation_order = new OperationOrder($this->db);
			$operation_order->fetch($this->fk_operation_order);
			$TLineQtyUsed = $operation_order->getAlreadyUsedQtyLines();
			$TLastLinesByProduct = $operation_order->getLastLinesByProduct();
			$qtyUsed = $this->getQtyUsed($TLineQtyUsed, $TLastLinesByProduct);
			if ($qtyUsed <> 0) {
				dol_include_once('/product/stock/class/mouvementstock.class.php');
				$mvt = new MouvementStock($this->db);
				//$mvt->origin = $operation_order;
				$mvt->origin_type = $operation_order->origin_type;
				$mvt->origin_id = $operation_order->id;
				$mvtok = $mvt->reception(
					$user,
					$this->fk_product,
					$this->fk_warehouse,
					$qtyUsed,
					0,
					'Suppression ligne OR ' . $operation_order->ref
				);
				if ($mvtok < 0) {
					$line->error = $mvt->error;
					$line->errors += $mvt->errors;
				}
			} else {
				$mvtok = 1;
			}
		} else {
			$mvtok = 1;
		}
		if ($mvtok) {
			return parent::delete($user, $notrigger);
		} else {
			return -99;
		}
	}

	/**
	 * Load object in memory from database
	 *
	 * @param int $fk_parent_line object
	 * @param bool $nested 0 = return simple array of lines , 1 = return recusive table of object need recursive nested
	 * @param bool $flat 0 = return nested array , 1 = return flat array
	 * @param array $TNested array
	 * @return array array of object
	 * @throws Exception
	 */
	public function fetch_all_children_lines($fk_parent_line = 0, $nested = false, $flat = false, &$TNested = array())
	{

		$sql = "SELECT";
		$sql .= " line.rowid,";
		$sql .= " line.rang,";
		$sql .= " line.fk_parent_line";
		$sql .= " FROM " . $this->db->prefix() . "operationorderdet as line";
		$sql .= " WHERE line.fk_operation_order=" . intval($this->fk_operation_order);
		if (empty($fk_parent_line)) {
			$sql .= " AND line.fk_parent_line=" . intval($this->id);
		} else {
			$sql .= " AND line.fk_parent_line=" . intval($fk_parent_line);
		}

		$sql .= " ORDER BY line.rang ASC";

		dol_syslog(get_class($this) . "::fetch_all", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;

			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);

				$line = new operationorderLine($this->db);
				$line->fetch($obj->rowid);

				if ($nested) {
					if (!$flat) {
						$TNested[$i] = array(
							'object' => $line,
							'children' => $this->fetch_all_children_lines($obj->rowid, true)
						);
					} else {
						$TNested[$obj->rowid] = $line;
						$this->fetch_all_children_lines($obj->rowid, true, true, $TNested);
					}
				} else {
					$TNested[$i] = $line;
				}
				$i++;
			}
			$this->db->free($resql);

			return $TNested;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(get_class($this) . "::fetch " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * @param $TParentLines array
	 * @return array|mixed
	 */
	public function fetch_all_parent_lines(&$TParentLines = array())
	{
		if (!empty($this->fk_parent_line)) {
			$parentLine = new operationorderLine($this->db);
			$parentLine->fetch($this->fk_parent_line);
			$TParentLines[$parentLine->id] = $parentLine;
			$parentLine->fetch_all_parent_lines($TParentLines);
		}

		return $TParentLines;
	}

	/**
	 * @param $date date
	 * @return bool|void
	 */
	public function isVirtualStockAvailableForDate($date)
	{
		global $conf;
		$virtualStock = 0;
		if (!empty($date) && !empty($this->product)) {
			$virtualStock = $this->product->stock_reel;
			$orderQty = 0;
			$sendingQty = 0;
			$supplierQty = 0;
			$receptionQty = 0;

			//Load qtys
			if (!empty(isModEnabled("fournisseur"))) {
				$supplierQty = $this->loadSupplierOrderQty($date);
				$receptionQty = $this->loadSupplierOrderReceptionQty($date); //On retire ce qui a déjà été réceptionné car c'est contenu dans le stock reel
			}
			if (!empty(isModEnabled("commande"))) $orderQty = $this->loadOrderQty($date);
			if (!empty(isModEnabled("expedition")) && (!empty(getDolGlobalString("STOCK_CALCULATE_ON_SHIPMENT")) || !empty(getDolGlobalString("STOCK_CALCULATE_ON_SHIPMENT_CLOSE")))) {
				require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
				if (!empty(getDolGlobalString("STOCK_CALCULATE_ON_SHIPMENT"))) {
					$filterShipmentStatus = Expedition::STATUS_VALIDATED . ',' . Expedition::STATUS_CLOSED;
				} elseif (!empty(getDolGlobalString("STOCK_CALCULATE_ON_SHIPMENT_CLOSE"))) {
					$filterShipmentStatus = Expedition::STATUS_CLOSED;
				}
				$sendingQty = $this->loadSendingQty($date, $filterShipmentStatus);
			}
			$ooQty = $this->loadOperationOrderQty($date);
			if (!empty($ooQty)) $virtualStock -= $ooQty;

			// Stock decrease mode
			if (!empty(getDolGlobalString("STOCK_CALCULATE_ON_SHIPMENT")) || !empty(getDolGlobalString("STOCK_CALCULATE_ON_SHIPMENT_CLOSE"))) {
				$virtualStock -= ($orderQty - $sendingQty);
			} elseif (!empty(getDolGlobalString("STOCK_CALCULATE_ON_VALIDATE_ORDER"))) {
				$virtualStock += 0;
			} elseif (!empty(getDolGlobalString("STOCK_CALCULATE_ON_BILL"))) {
				$virtualStock -= $orderQty;
			}

			// Stock Increase mode
			if (!empty(getDolGlobalString("STOCK_CALCULATE_ON_RECEPTION")) || !empty(getDolGlobalString("STOCK_CALCULATE_ON_RECEPTION_CLOSE"))) {
				$virtualStock += ($supplierQty - $receptionQty);
			} elseif (!empty(getDolGlobalString("STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER"))) {
				$virtualStock += ($supplierQty - $receptionQty);
			} elseif (!empty(getDolGlobalString("STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER"))) {
				$virtualStock -= $receptionQty;
			} elseif (!empty(getDolGlobalString("STOCK_CALCULATE_ON_SUPPLIER_BILL"))) {
				$virtualStock += ($supplierQty - $receptionQty);
			}
			$this->product->stock_theorique = $virtualStock;
			if ($virtualStock >= $this->qty) return true;
			else return false;
		}
	}

	/**
	 * @param $date date
	 * @return int
	 */
	public function loadOperationOrderQty($date = '')
	{
		$qty = 0;
		$oOStatus = new OperationOrderStatus($this->db);
		$TStatus = $oOStatus->fetchAll(0, false, array("check_virtual_stock" => 1));
		$TStatusId = array();
		if (!empty($TStatus)) {
			foreach ($TStatus as $status) $TStatusId[] = $status->id;
			$sql = "SELECT SUM(ood.qty) as qty
                    FROM " . $this->db->prefix() . "operationorderdet as ood
                    LEFT JOIN " . $this->db->prefix() . "operationorder as oo ON (oo.rowid = ood.fk_operation_order)
		    LEFT JOIN " . $this->db->prefix() . "stock_mouvement sm ON (
                        sm.fk_product = ood.fk_product
			AND sm.origintype = 'operationorder'
			AND sm.fk_origin = oo.rowid
                    )
                    WHERE ood.fk_product = " . $this->product->id . "
                    AND oo.entity IN (" . getEntity('operationorder') . ")
                    AND oo.status IN (" . implode(',', $TStatusId) . ")
                    AND sm.rowid IS NULL ";
			if (!empty($date)) $sql .= "AND oo.planned_date < '" . date('Y-m-d', $date) . "'";
			$resql = $this->db->query($sql);
			if (!$resql) {
				setEventMessages($this->db->lasterror(), null, 'errors');;
			} elseif ($this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				if (!empty($obj->qty)) {
					return $obj->qty;
				}
			}
		}

		return $qty;
	}

	/**
	 * @param $date date
	 * @param $filterShipmentStatus array
	 * @return int
	 */
	public function loadSendingQty($date, $filterShipmentStatus = array())
	{
		$sql = "SELECT SUM(ed.qty) as qty";
		$sql .= " FROM " . $this->db->prefix() . "expeditiondet as ed";
		$sql .= ", " . $this->db->prefix() . "commandedet as cd";
		$sql .= ", " . $this->db->prefix() . "commande as c";
		$sql .= ", " . $this->db->prefix() . "expedition as e";
		$sql .= ", " . $this->db->prefix() . "societe as s";
		$sql .= " WHERE e.rowid = ed.fk_expedition";
		$sql .= " AND c.rowid = cd.fk_commande";
		$sql .= " AND e.fk_soc = s.rowid";
		$sql .= " AND e.entity IN (" . getEntity('expedition') . ")";
		$sql .= " AND ed.fk_origin_line = cd.rowid";
		$sql .= " AND cd.fk_product = " . $this->product->id;
		$sql .= " AND c.fk_statut in (1,2)";
		if (!empty($filterShipmentStatus)) $sql .= " AND e.fk_statut IN (" . $filterShipmentStatus . ")";
		$sql .= " AND e.date_delivery < '" . date('Y-m-d', $date) . "'";

		$resql = $this->db->query($sql);
		if (!empty($resql) && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			if (!empty($obj->qty)) return $obj->qty;
		}
		return 0;
	}

	/**
	 * @param $date date
	 * @return int
	 */
	public function loadOrderQty($date)
	{
		global $conf;
		$tmpqty = 0;
		$sql = "SELECT SUM(cd.qty) as qty";
		$sql .= " FROM " . $this->db->prefix() . "commandedet as cd";
		$sql .= ", " . $this->db->prefix() . "commande as c";
		$sql .= ", " . $this->db->prefix() . "societe as s";
		$sql .= " WHERE c.rowid = cd.fk_commande";
		$sql .= " AND c.fk_soc = s.rowid";
		$sql .= " AND c.entity IN (" . getEntity('commande') . ")";
		$sql .= " AND cd.fk_product = " . $this->product->id;
		$sql .= " AND c.fk_statut in (1,2)";
		$sql .= " AND c.date_livraison < '" . date('Y-m-d', $date) . "'";
		$resql = $this->db->query($sql);
		if (!empty($resql) && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			$tmpqty = $obj->qty;
		} else return 0;

		if (empty($tmpqty)) $tmpqty = 0;

		// If stock decrease is on invoice validation, the theorical stock continue to
		// count the orders to ship in theorical stock when some are already removed b invoice validation.
		// If option DECREASE_ONLY_UNINVOICEDPRODUCTS is on, we make a compensation.
		if (!empty(getDolGlobalString("STOCK_CALCULATE_ON_BILL"))) {
			if (!empty(getDolGlobalString("DECREASE_ONLY_UNINVOICEDPRODUCTS"))) {
				$adeduire = 0;
				$sql = "SELECT sum(fd.qty) as count FROM " . $this->db->prefix() . "facturedet fd ";
				$sql .= " JOIN " . $this->db->prefix() . "facture f ON fd.fk_facture = f.rowid ";
				$sql .= " JOIN " . $this->db->prefix() . "element_element el ON el.fk_target = f.rowid and el.targettype = 'facture' and sourcetype = 'commande'";
				$sql .= " JOIN " . $this->db->prefix() . "commande c ON el.fk_source = c.rowid ";
				$sql .= " WHERE c.fk_statut IN (1,2) AND c.facture = 0 AND fd.fk_product = " . $this->product->id;
				$sql .= " AND c.date_livraison < '" . date('Y-m-d', $date) . "'";

				$resql = $this->db->query($sql);
				if ($resql) {
					if ($this->db->num_rows($resql) > 0) {
						$obj = $this->db->fetch_object($resql);
						$adeduire += $obj->count;
					}
				}
				$tmpqty -= $adeduire;
			}
		}

		return $tmpqty;
	}

	/**
	 * @param $date date
	 * @return int
	 */
	public function loadSupplierOrderQty($date)
	{
		$sql = "SELECT SUM(cd.qty) as qty";
		$sql .= " FROM " . $this->db->prefix() . "commande_fournisseurdet as cd";
		$sql .= ", " . $this->db->prefix() . "commande_fournisseur as c";
		$sql .= ", " . $this->db->prefix() . "societe as s";
		$sql .= " WHERE c.rowid = cd.fk_commande";
		$sql .= " AND c.fk_soc = s.rowid";
		$sql .= " AND c.entity IN (" . getEntity('supplier_order') . ")";
		$sql .= " AND cd.fk_product = " . $this->product->id;
		$sql .= " AND c.fk_statut in (1,2,3,4)";
		$sql .= " AND c.date_livraison < '" . date('Y-m-d', $date) . "'";
		$resql = $this->db->query($sql);
		if (!empty($resql) && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			if (!empty($obj->qty)) return $obj->qty;
		}
		return 0;
	}

	/**
	 * @param $date date
	 * @return int
	 */
	public function loadSupplierOrderReceptionQty($date)
	{
		$sql = "SELECT SUM(fd.qty) as qty";
		$sql .= " FROM " . $this->db->prefix() . "receptiondet_batch as fd";
		$sql .= ", " . $this->db->prefix() . "commande_fournisseur as cf";
		$sql .= ", " . $this->db->prefix() . "societe as s";
		$sql .= " WHERE cf.rowid = fd.fk_element";
		$sql .= " AND fd.element_type='supplier_order'";
		$sql .= " AND cf.fk_soc = s.rowid";
		$sql .= " AND cf.entity IN (" . getEntity('supplier_order') . ")";
		$sql .= " AND fd.fk_product = " . $this->product->id;
		$sql .= " AND cf.fk_statut in (4)";
		$sql .= " AND cf.date_livraison < '" . date('Y-m-d', $date) . "'";
		$resql = $this->db->query($sql);
		if (!empty($resql) && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			if (!empty($obj->qty)) return $obj->qty;
		}
		return 0;
	}

	/**
	 * Function to update amount if needed
	 *
	 * @param User $user user object
	 * @param bool $notrigger false=launch triggers after, true=disable triggers
	 * @return  int                 < 0 if ko, > 0 if ok
	 */
	public function calcAmountLine($user, $notrigger = false)
	{
		$totalHt = 0;
		if ($this->product->type == Product::TYPE_SERVICE) {
			if (!empty($this->product->array_options['options_or_scan'])) {
				if ($this->time_spent > $this->time_planned) {
					$totalHt = price2num((float) ($this->price - $this->remise) * ($this->time_spent / 3600), 'MT');
				}
			}
			if (!empty($this->product->array_options['options_oorder_available_for_supplier_order'])) {
				//Fetch liked object sur la ligne de l'OR pour charcher les order supplier
				//pour chaque order supplier => fetch linked Object pour récup les facture
				// et aller caler sur la ligne total_ht le total ht de la facture
				//$linkedObject
			}
		}
		if ($this->product->type == Product::TYPE_PRODUCT) {
			$qtyUsed = $this->getAlreadyUsedQty();
			if ($qtyUsed > $this->qty) {
				$totalHt = price2num((float) ($this->price - $this->remise) * $qtyUsed, 'MT');
			}
		}

		if ($totalHt !== $this->total_ht && !empty($totalHt)) {
			$this->total_ht = $totalHt;
			$resUpdate = $this->update($user, $notrigger);
			if ($resUpdate < 0) {
				return -1;
			} else {
				return 1;
			}
		} else {
			return 0;
		}
	}

	/**
	 * @return  int  < 0 if ko, > 0 if ok
	 */
	public function getAlreadyUsedQty()
	{
		$alreadyUsed = 0;
		$sql = "SELECT SUM(mvt.value) as total FROM " . $this->db->prefix() . "stock_mouvement as mvt";
		$sql .= " WHERE mvt.origintype = 'operationorder@operationorder'";
		$sql .= " AND mvt.fk_origin = " . $this->fk_operation_order;
		$sql .= " AND mvt.fk_product = " . (int) $this->fk_product;
		$sql .= " GROUP BY mvt.fk_product";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$alreadyUsed += $obj->total * -1;
			}
		} else {
			$this->errors[] = $this->db->lasterror;
			return -1;
		}
		return $alreadyUsed;
	}
}

//class operationorderLine extends operationorderLine {
//
//}

/**
 * Class OperationOrderDictType
 */
class OperationOrderDictType extends SeedObject
{
	public $table_element = 'c_operationorder_type';

	public $element = 'operationorder_type';

	public $fields = array(
		'code' => array('varchar(30)', 'length' => 30, 'enabled' => 1),
		'label' => array('varchar(255)', 'length' => 255, 'showoncombobox' => 1, 'enabled' => 1),
		'blocked_status_code' => array('varchar(255)', 'length' => 255, 'enabled' => 1),
		'position' => array('integer', 'enabled' => 1),
		'active' => array('integer', 'enabled' => 1),
		'entity' => array('integer', 'index' => true, 'enabled' => 1)
	);

	/**
	 * operationorderLine constructor.
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->init();
	}

	/**
	 * @param $getnomurlparam getnomurlparam
	 * @return mixed
	 */
	public function getNomUrl($getnomurlparam = '')
	{
		return $this->label;
	}
}

/**
 * Class OperationOrderDictTag
 */
class OperationOrderDictTag extends SeedObject
{
	public $table_element = 'c_operationorder_tag';

	public $element = 'operationordertag';

	public $fields = array(
		'code' => array('type' => 'varchar(30)', 'length' => 30, 'enabled' => 1),
		'label' => array('type' => 'varchar(255)', 'length' => 255, 'showoncombobox' => 1, 'enabled' => 1),
		'color' => array('type' => 'varchar(16)', 'label' => 'Color', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'default' => '#3c8dbc'),
		'position' => array('type' => 'integer', 'enabled' => 1),
		'active' => array('type' => 'integer', 'enabled' => 1),
		'entity' => array('type' => 'integer', 'index' => true, 'enabled' => 1)
	);

	/**
	 * operationorderLine constructor.
	 * @param DoliDB $db Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->init();
	}

	/**
	 * @param $getnomurlparam getnomurlparam
	 * @return mixed
	 */
	public function getNomUrl($getnomurlparam = '')
	{
		return $this->label;
	}
}
