<?php

/* Copyright (C) 2023-2024 	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2023-2024	Lionel Vessiller		<lvessiller@easya.solutions>
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
 * \file        dolifleet/controllers/vehiculelist.controller.class.php
 * \ingroup     dolifleet
 * \brief       This file is a controller for vehicule list
 */
require_once DOL_DOCUMENT_ROOT . '/webportal/class/controller.class.php';

/**
 * Class for VehiculeListController
 */
class OPByVehiculeListController extends Controller
{

	/**
	 * @var DriverListWebPortal Form for list
	 */
	protected $formList;

	/**
	 * Check current access to controller
	 *
	 * @return  bool
	 */
	public function checkAccess()
	{
		$this->accessRight = isModEnabled('operationorder');
		return isModEnabled('operationorder');
	}

	/**
	 * Action method is called before html output
	 * can be used to manage security and change context
	 *
	 * @return  int     Return integer < 0 on error, > 0 on success
	 */
	public function action()
	{
		global $langs;

		$context = Context::getInstance();
		if (!$context->controllerInstance->checkAccess()) {
			return -1;
		}


		// Load translation files required by the page
		$langs->loadLangs(array('bills', 'companies', 'products', 'categories'));

		$context->title = $langs->trans('OPByVehicule');
		$context->desc = "";
		$context->menu_active[] = 'opbyvehicule_list';

		dol_include_once('/operationorder/class/html.op_by_vehicule_formlistwebportal.class.php');
		// set form list
		$formListWebPortal = new OPByVehiculeFormListWebPortal($this->db);
		$formListWebPortal->init('vehicule');
		// hook for action
		//      $hookRes = $this->hookDoAction();
		//      if (empty($hookRes)) {
		//      }
		$formListWebPortal->doActions();
		$this->formList = $formListWebPortal;

		return 1;
	}

	/**
	 * Display
	 *
	 * @return  void
	 */
	public function display()
	{
		$context = Context::getInstance();
		if (!$context->controllerInstance->checkAccess()) {
			$this->display404();
			return;
		}

		$this->loadTemplate('header');
		$this->loadTemplate('menu');
		$this->loadTemplate('hero-header-banner');
		$hookRes = $this->hookPrintPageView();

		if (empty($hookRes)) {
			dol_include_once('/operationorder/class/html.op_by_vehicule_formlistwebportal.class.php');

			print '<main class="container">';
			print '<figure style="overflow-x:unset">';

			print $this->formList->elementList($context);
			print '</figure>';
			print '</main>';
		}

		$this->loadTemplate('footer');
	}
}
