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

/**
 *    \defgroup   operationorder     Module OperationOrder
 *  \brief      Example of a module descriptor.
 *                Such a file must be copied into htdocs/operationorder/core/modules directory.
 *  \file       htdocs/operationorder/core/modules/modOperationOrder.class.php
 *  \ingroup    operationorder
 *  \brief      Description and activation file for module OperationOrder
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module OperationOrder
 */
class modOperationOrder extends DolibarrModules
{

	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->editor_name = 'T-Service';
		$this->editor_url = 'https://www.theobald-groupe.com/';
		$this->numero = 437870; // 104000 to 104999 for ATM CONSULTING // ancien numéro 104088
		$this->rights_class = 'operationorder';
		$this->family = "THEOBALD";
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "OperationOrderModuleDescription";
		$this->version = '4.0.0';
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		$this->special = 0;
		$this->picto = 'operationorder@operationorder';
		$this->module_parts = array(
			'models'   => 1,
			'triggers' => 1,
			'hooks'	   => array(
				'ordersuppliercard',
				'productdao',
				'operationordercard',
				'receptioncard',
				'searchform',
				'dictionaryadmin',
				'invoicecard',
				'ordercard',
				'emailcolector',
				'propalcard',
				'webportalpage',
				'webportal'
			)
		);

		$this->dirs = array();

		$this->config_page_url = array("operationorder_setup.php@operationorder");

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		// List of modules id that must be enabled if this module is enabled
		$this->depends = array('moddoliFleet',
			'modProduct',
			'modService',
			'modSociete',
			'modFournisseur',
			'modStock',
			'modAgenda',
			'modCategorie',
			'modFckeditor',
			'modBookmark',
			'modBarcode',
			'modWorkflow',
			'modListInCSV',
			'modFacture',
			'modCommande',
		);
		// List of modules id to disable if this one is disabled
		$this->requiredby = array('moddoliFleet',
			'modProduct',
			'modService',
			'modStock',
			'modAgenda',
			'modCategorie',
			'modFckeditor',
			'modBookmark',
			'modBarcode',
			'modWorkflow',
			'modListInCSV',
		);

		$this->conflictwith = array();	// List of modules id this module is in conflict with
		$this->phpmin = array(5, 0);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(17, 0);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("operationorder@operationorder");

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		$this->const = array(
			1 => array('CHECKLASTVERSION_EXTERNALMODULE', 'chaine', '0', '', 0, 'current', 0),
			2 => array('MAIN_DISABLE_FREE_LINES', 'chaine', '1', '', 0, 'current', 1),
			3 => array('OPERATIONORDER_STATUT_BEFORE_CHECK', 'chaine', 'FINISHED', '', 0, 'current'),
			4 => array('PRODUIT_DESC_IN_FORM', 'chaine', '1', '', 0, 'current'),
			5 => array('STOCK_ALLOW_NEGATIVE_TRANSFER', 'chaine', '0', '', 1, 'current'),
			6 => array('WEBPORTAL_SUPPLIER_ORDER_LIST_ACCESS', 'chaine', '1', '', 1, 'current'),
			7 => array('WEBPORTAL_OPERATIONORDER_LIST_ACCESS', 'chaine', '1', '', 1, 'current'),
			8 => array('PDF_INVOICE_SHOW_VAT_ANALYSIS', 'chaine', '1', '', 1, 'current'),
		);

		// Array to add new pages in new tabs
		$this->tabs = array(
			'user:+userplanning:Planning:operationorder@operationorder:$user->hasRight("operationorder","read"):/operationorder/operationorder_userplanning.php?objectid=__ID__&objecttype=user',
			'group:+usergroupplanning:Planning:operationorder@operationorder:$user->hasRight("operationorder","read"):/operationorder/operationorder_userplanning.php?objectid=__ID__&objecttype=usergroup',
			'dolifleet:+vsr:VSR:operationorder@operationorder:$user->hasRight("operationorder","read"):/operationorder/vsr.php?originid=__ID__',
		);

		// Dictionaries
		if (!isModEnabled("operationorder")) {
			$conf->operationorder = new stdClass();
			$conf->operationorder->enabled = 0;
		}

		$this->dictionaries = array(
			'langs'			 => 'operationorder@operationorder',
			'tabname'		 => array(
				$this->db->prefix() . 'c_operationorder_type',
				$this->db->prefix() . 'c_operationorder_tag',
			),
			'tablib'		 => array(
				'OperationOrderDictTypeLabel',
				'OperationOrderDictTagLabel'
			), // Label of tables
			'tabsql'		 => array(
				'SELECT f.rowid, f.code, f.label, f.blocked_status_code, f.fk_soc, f.position, f.active, f.entity FROM ' . $this->db->prefix() . 'c_operationorder_type as f WHERE f.entity IN (0, ' . $conf->entity . ')',
				'SELECT f.rowid, f.code, f.label, f.color, f.position, f.active, f.entity FROM ' . $this->db->prefix() . 'c_operationorder_tag as f WHERE f.entity IN (0, ' . $conf->entity . ')',
			),
			'tabsqlsort'	 => array(
				'position ASC, label ASC',
				'position ASC, label ASC',
			),
			'tabfield'		 => array(
				'code,label,blocked_status_code,fk_soc,position',
				'code,label,color,position'
			),
			'tabfieldvalue'	 => array(
				'code,label,blocked_status_code,fk_soc,position,entity',
				'code,label,color,position,entity'
			),
			'tabfieldinsert' => array(
				'code,label,blocked_status_code,fk_soc,position,entity',
				'code,label,color,position,entity'
			),
			'tabrowid'		 => array(
				'rowid',
				'rowid'
			),
			'tabcond'		 => array(
				isModEnabled("operationorder"),
				isModEnabled("operationorder")
			)
		);

		// Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
		$this->boxes = array(0 => array('file' => 'efficiency.php@operationorder', 'note' => ''));

		$this->cronjobs = array(
			0 => array(
				'label'			=> 'OperationOrderJob',
				'jobtype'		=> 'method',
				'class'			=> '/operationorder/class/operationorder.class.php',
				'objectname'	=> 'OperationOrder',
				'method'		=> 'doScheduledJob',
				'parameters'	=> '',
				'comment'		=> 'Cloture auto des pointages, MAJ PMP, replannif,...',
				'frequency'		=> 1,
				'unitfrequency' => 86400,
				'status'		=> 0,
				'test'			=> 'isModEnabled("operationorder")',
				'priority'		=> 50,
			),
		);

		// Permissions
		$this->rights = array();		// Permission array used by this module
		$r = 0;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_read';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'read';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = '';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_advance_read';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'advance_read';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = '';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_write';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'write';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = '';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_delete';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'delete';			// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = '';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_status_read';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'status';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'read';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_status_write';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'status';			// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'write';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_planning_read';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'planning';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'read';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_userplanning_write';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'userplanning';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'write';
		$r++;

		// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_usergroupplanning_write';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'usergroupplanning';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'write';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_manager_read';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'manager';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'read';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_counter_update';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'counter';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'update';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_counter_delete';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'counter';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'delete';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_override_checkor';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'checkor';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'admin';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero . $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'operationorder_compta_read';	// Permission label
		$this->rights[$r][3] = 0;					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'compta';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'read';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r = 0;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder', // Put 0 if this is a single top menu or keep fk_mainmenu to give an entry on left
			'type'	   => 'top', // This is a Top menu entry
			'titre'	   => 'OperationOrderTopMenu',
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left', // This is the name of left menu for the next entries
			'url'	   => 'operationorder/index.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 100,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("operationorder")' if entry must be visible if module is enabled.
			'perms'	   => '$user->hasRight("operationorder","planning","read") || $user->hasRight("operationorder","read")', // Use 'perms'=>'$user->hasRight("operationorder","level","")1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderCreate'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_create',
			'url'	   => '/operationorder/operationorder_card.php?action=create',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "write")', // Use 'perms'=>'$user->hasRight("missionorder","level","")1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderCreateDepan'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_create',
			'url'	   => '/operationorder/operationorder_depan.php?action=create',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "write")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderList'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_list',
			'url'	   => '/operationorder/list.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "read")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderListHistory'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_list_history',
			'url'	   => '/operationorder/operationorder_history.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "read")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderPlanning'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_planning',
			'url'	   => '/operationorder/operationorder_planning.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "planning", "read")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderORPlanning'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_orplanning',
			'url'	   => '/operationorder/operationorder_orplanning.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "planning", "read")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderManager'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_manager',
			'url'	   => '/operationorder/operationorder_manager.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "manager", "read")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderStatusMenu'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_status',
			'url'	   => '/operationorder/operationorderstatus_list.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "status", "read")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left_status', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderStatusCreate'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_status_create',
			'url'	   => '/operationorder/operationorderstatus_card.php?action=create',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "status", "write")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left_status', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderStatusList'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_status_list',
			'url'	   => '/operationorder/operationorderstatus_list.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "status", "read")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left_status', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('LeftMenuOperationOrderParam'),
			'prefix'   => '<span class="fa fa-tools paddingright pictofixedwidth valignmiddle"></span>',
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_status_list',
			'url'	   => '/operationorder/param/or_setup_job.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("produit", "read")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=commercial,fk_leftmenu=orders_suppliers', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => $langs->trans('orderseeking'),
			'mainmenu' => 'commercial',
			'leftmenu' => 'orders_suppliers',
			'url'	   => '/operationorder/commande/commande.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("missionorder")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("fournisseur", "commande", "lire")', // Use 'perms'=>'$user->hasRight("missionorder","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry	                // This is a Left menu entry
			'titre'	   => $langs->trans('efficiency'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_orplanning', // Goes into left menu previously created by the mainmenu
			'url'	   => '/operationorder/rh/efficacite.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("clitheobald")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "read")', // Use 'perms'=>'$user->hasRight("clitheobald","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);								// 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry	                // This is a Left menu entry
			'titre'	   => $langs->trans('workedtime'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_orplanning', // Goes into left menu previously created by the mainmenu
			'url'	   => '/operationorder/rh/pointage.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("clitheobald")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "read")', // Use 'perms'=>'$user->hasRight("clitheobald","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=operationorder,fk_leftmenu=operationorder_left', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry	                // This is a Left menu entry
			'titre'	   => $langs->trans('VSR'),
			'mainmenu' => 'operationorder',
			'leftmenu' => 'operationorder_left_orplanning', // Goes into left menu previously created by the mainmenu
			'url'	   => '/operationorder/vsr.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("clitheobald")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "read")', // Use 'perms'=>'$user->hasRight("clitheobald","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=products,fk_leftmenu=stock', // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry	                // This is a Left menu entry
			'titre'	   => $langs->trans('StockAnomaly'),
			'mainmenu' => 'products',
			'leftmenu' => 'stock', // Goes into left menu previously created by the mainmenu
			'url'	   => '/operationorder/stock_anomaly.php',
			'langs'	   => 'operationorder@operationorder', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")', // Define condition to show or hide menu entry. Use 'isModEnabled("clitheobald")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "read")', // Use 'perms'=>'$user->hasRight("clitheobald","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0
		);
		$r++;

		//Export Journal vente dans facturation
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=billing',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'	   => 'left', // This is a Left menu entry
			'titre'	   => 'ExportComptaVente',
			//'prefix'   => '<span class="fa fa-list paddingright pictofixedwidth valignmiddle"></span> ',
			'mainmenu' => 'billing',
			'leftmenu' => 'selljournal',
			'url'	   => '/operationorder/compta/journal/sellsjournal.php?mainmenu=billing',
			'langs'	   => 'operationorder@operationorder',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("operationorder")',
			// Define condition to show or hide menu entry. Use 'isModEnabled("theobald")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'	   => '$user->hasRight("operationorder", "compta", "read")',
			// Use 'perms'=>'$user->hasRight("theobald","level","level2")' if you want your menu with a permission rules
			'target'   => '',
			'user'	   => 0, // 0=Menu for internal users, 1=external users, 2=both
		);
	}

	/**
	 *        Function called when module is enabled.
	 *        The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *        It also creates data directories
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return     int                1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $langs;
		$sql = array();

		$langs->load('operationorder@operationorder');

		define('INC_FROM_DOLIBARR', true);

		require __DIR__ . '/../../script/create-maj-base.php';

		$result = $this->_load_tables('/operationorder/sql/');

		// Création des extrafields
		dol_include_once('/core/class/extrafields.class.php');

		// Contact
		/*
		 * $e = new ExtraFields($this->db);
		  $res = $e->addExtraField('driver', "driver", 'boolean', 0, 1, 'socpeople', 0, 0, '', '', 1, 1, 1, "driver_help", "", 0, 'operationorder@operationorder');

		  // usercapacity
		  $e = new ExtraFields($this->db);
		  $res = $e->addExtraField('efficiency', "Efficiency", 'int', 0, 3, 'user', 0, 0, '100', 'a:1:{s:7:"options";a:1:{s:0:"";N;}}', 1, 1, 1, "ProductionCapacityRate", "", 1, 'operationorder@operationorder');

		  // product

		  // facture
		  $e = new ExtraFields($this->db);
		  $res = $e->addExtraField('immatriculation', "immatriculation", 'varchar', 10, 20, 'facture', 0, 0, '', 'a:1:{s:7:"options";a:1:{s:0:"";N;}}', 1, 1, 1, "", "", 0, 'operationorder@operationorder', 1, 0, 1);
		  $res = $e->addExtraField('vin', "VIN", 'varchar', 20, 50, 'facture', 0, 0, '', 'a:1:{s:7:"options";a:1:{s:0:"";N;}}', 1, 1, 1, "", "", 0, 'operationorder@operationorder', 1, 0, 1);
		  $res = $e->addExtraField('km_on_creation', "km_on_creation", 'int', 30, 3, 'facture', 0, 0, '', 'a:1:{s:7:"options";a:1:{s:0:"";N;}}', 1, 1, 1, "", "", 0, 'operationorder@operationorder', 1, 0, 1);
		 */

		//Product
		// Pointable sur ordre de réparation
		$e = new ExtraFields($this->db);
		$param = unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}');
		$e->addExtraField(
			'or_scan',
			'OrScan',
			'boolean',
			0,
			'1',
			'product',
			0,
			0,
			'',
			$param,
			1,
			'',
			1,
			'OrScanHelp',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		$res = $e->addExtraField(
			'oorder_available_for_supplier_order',
			"oorder_available_for_supplier_order",
			'boolean',
			0,
			1,
			'product',
			0,
			0,
			'',
			'',
			1,
			1,
			1,
			"oorder_available_for_supplier_order_help",
			"",
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		$res = $e->addExtraField(
			'or_is_job',
			"or_is_job",
			'boolean',
			0,
			1,
			'product',
			0,
			0,
			'',
			'',
			1,
			1,
			1,
			"or_is_job_help",
			"",
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		$res = $e->addExtraField(
			'oorder_ventilation_produit',
			"oorder_ventilation_produit",
			'select',
			0,
			1,
			'product',
			0,
			0,
			'',
			array("options" => array(1 => "Refacturation casse", 2 => "Refacturation diverse", 3 => "Refacturation extérieure")),
			1,
			1,
			1,
			"",
			"",
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		$res = $e->addExtraField(
			'doc_obl',
			"doc obligatoire",
			'varchar',
			0,
			255,
			'product',
			0,
			0,
			'',
			unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'),
			1,
			1,
			1,
			"doc_obl_help",
			"",
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		$e->addExtraField(
			'fk_default_warehouse',
			'DefaultWarehouse',
			'html',
			120,
			'10',
			'product',
			0,
			0,
			'',
			'',
			1,
			'',
			'5',
			'',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		//Order Det
		$res = $e->addExtraField(
			'oorder_fk_warehouse',
			"oorder_fk_warehouse",
			'link',
			1000,
			'',
			'commandedet',
			0,
			1,
			'',
			array("options" => array('Entrepot:product/stock/class/entrepot.class.php:0:(t.statut:>:0)' => null)),
			1,
			1,
			1,
			"",
			"",
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		//Facture Det

		$res = $e->addExtraField(
			'oorder_fk_warehouse',
			"oorder_fk_warehouse",
			'link',
			1000,
			'',
			'facturedet',
			0,
			0,
			'',
			array("options" => array('Entrepot:product/stock/class/entrepot.class.php:0:(t.statut:>:0)' => null)),
			0,
			0,
			0,
			"",
			"",
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		//Facture
		$res = $e->addExtraField(
			'dt_export_compta',
			"SellExportDateTime",
			'datetime',
			105,
			'',
			'facture',
			'0',
			'0',
			'',
			unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'),
			'1',
			'',
			'5',
			'',
			'',
			'0',
			'theobald@theobald',
			'isModEnabled("operationorder")',
			'0',
			'0'
		);

		// user
		$res = $e->addExtraField(
			'fichier_signature',
			"fichier signature",
			'varchar',
			0,
			255,
			'user',
			0,
			0,
			'',
			unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'),
			1,
			1,
			1,
			"sign_files",
			"",
			1,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		// propal
		$e->addExtraField(
			'fk_vehicule',
			'Vehicule',
			'link',
			100,
			'',
			'propal',
			0,
			0,
			0,
			unserialize('a:1:{s:7:"options";a:1:{s:59:"Vehicule:custom/dolifleet/class/vehicule.class.php";N;}}'),
			1,
			'',
			'1',
			'',
			'',
			0, 'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		// societe
		$e->addExtraField(
			'cd_compta_intragp',
			'cdComptaIntragp',
			'varchar',
			110,
			'10',
			'societe',
			0,
			0,
			'00',
			unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'),
			1,
			'',
			'1',
			'',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		// multicompany
		$e->addExtraField(
			'fk_default_warehouse',
			'DefaultWarehouse',
			'link',
			110,
			'10',
			'entity',
			0,
			0,
			'',
			array("options" => array('Entrepot:product/stock/class/entrepot.class.php:0:(t.statut:>:0)' => null)),
			1,
			'',
			'1',
			'',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		$e->addExtraField(
			'thirdparties_linked',
			'OOThirdpartiesLinked',
			'chkbxlst',
			120,
			'',
			'entity',
			0,
			0,
			'',
			array("options" => array('societe:nom:rowid::' => null)),
			1,
			'',
			'1',
			'',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
		);

		$e->addExtraField(
			'total_ht_reimbursement',
			'total_ht_reimbursement',
			'price',
			120,
			'',
			'facture',
			0,
			0,
			'',
			null,
			1,
			'',
			'5',
			'',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
			1,
			0
		);

		$e->addExtraField(
			'total_ht_mo',
			'total_ht_mo',
			'price',
			130,
			'',
			'facture',
			0,
			0,
			'',
			null,
			1,
			'',
			'5',
			'',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
			1,
			0
		);
		$e->addExtraField(
			'total_ht_part',
			'total_ht_part',
			'price',
			140,
			'',
			'facture',
			0,
			0,
			'',
			null,
			1,
			'',
			'5',
			'',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
			1,
			0
		);
		$e->addExtraField(
			'total_ht_service',
			'total_ht_service',
			'price',
			150,
			'',
			'facture',
			0,
			0,
			'',
			null,
			1,
			'',
			'5',
			'',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
			1,
			0
		);
		$e->addExtraField(
			'total_ht_external',
			'total_ht_external',
			'price',
			160,
			'',
			'facture',
			0,
			0,
			'',
			null,
			1,
			'',
			'5',
			'',
			'',
			0,
			'operationorder@operationorder',
			'isModEnabled("operationorder")',
			1,
			0
		);

		return $this->_init($sql, $options);
	}

	/**
	 *        Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *        Data directories are not deleted
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return     int                1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}
}
