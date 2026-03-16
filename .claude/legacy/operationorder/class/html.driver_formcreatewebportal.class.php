<?php

/* Copyright (C) 2023-2024 	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2023-2024	Lionel Vessiller		<lvessiller@easya.solutions>
 * Copyright (C) 2023-2024	Patrice Andreani		<pandreani@easya.solutions>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
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


require_once DOL_DOCUMENT_ROOT . '/webportal/class/html.formcardwebportal.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societeaccount.class.php';
dol_include_once('/operationorder/class/webportaldriver.class.php');

/**
 *    Class to manage generation of HTML components
 *    Only common components for WebPortal must be here.
 *
 */
class DriverFormCreateWebPortal extends FormCardWebPortal
{

	private $context;

	/**
	 * Init
	 *
	 * @param	string	$elementEn				Element (english) : "member" (for adherent), "partnership"
	 * @param	int		$id						[=0] ID element
	 * @param	int		$permissiontoread		[=0] Permission to read (0 : access forbidden by default)
	 * @param	int		$permissiontoadd		[=0] Permission to add (0 : access forbidden by default), used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
	 * @param	int		$permissiontodelete		[=0] Permission to delete (0 : access forbidden by default)
	 * @param	int		$permissionnote			[=0] Permission to note (0 : access forbidden by default)
	 * @param	int		$permissiondellink		[=0] Permission to delete links (0 : access forbidden by default)
	 * @return	void
	 */
	public function init(
		$elementEn, $id = 0, $permissiontoread = 0, $permissiontoadd = 0, $permissiontodelete = 0, $permissionnote = 0, $permissiondellink =
		0
	) {
		global $hookmanager, $langs;
		$_SESSION['dol_tz_string'] = 'Europe/Paris';

		$elementEnUpper = strtoupper($elementEn);
		$objectclass = 'WebPortal' . ucfirst($elementEn);

		// Load translation files required by the page
		$langs->loadLangs(array('website', 'other', 'companies', 'operationorder@operationorder'));

		// load module libraries
		dol_include_once('/operationorder/class/webportal' . $elementEn . '.class.php');

		// Get parameters
		//$id = $id > 0 ? $id : GETPOST('id', 'int');
		$id = GETPOST('id', 'int');

		$action = GETPOST('action', 'aZ09');
		$confirm = GETPOST('confirm', 'alpha');
		$cancel = GETPOST('cancel', 'aZ09');
		$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'webportal' . $elementEn . 'form'; // To manage different context of search
		$backtopage = GETPOST('backtopage', 'alpha');  // if not set, a default page will be used
		$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha'); // if not set, $backtopage will be used
		$backtopagejsfields = GETPOST('backtopagejsfields', 'alpha');
		// Initialize a technical objects
		$object = new WebPortalDriver($this->db);
		//$extrafields = new ExtraFields($db);
		$hookmanager->initHooks(array('webportal' . $elementEn . 'form', 'globalcard')); // Note that conf->hooks_modules contains array
		// Fetch optionals attributes and labels
		//$extrafields->fetch_name_optionals_label($object->table_element);
		//$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

		$action = 'create';

		$retFetch = $object->fetchWebContact($id);

		// Security check (enable the most restrictive one)
		if (!isModEnabled('webportal')) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}
		if (!$permissiontoread) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		// set form card
		$this->action = $action;
		$this->id = $id;
		$this->backtopage = $backtopage;
		$this->backtopageforcancel = $backtopageforcancel;
		$this->backtopagejsfields = $backtopagejsfields;
		$this->cancel = $cancel;
		$this->elementEn = $elementEn;
		$this->object = $object;
		$this->permissiontoread = $permissiontoread;
		$this->permissiontoadd = $permissiontoadd;
		$this->permissiontodelete = $permissiontodelete;
		$this->permissionnote = $permissionnote;
		$this->permissiondellink = $permissiondellink;
		$this->titleKey = $objectclass . 'FormTitle';
		$this->formcompany = new FormCompany($object->db);
	}

	/**
	 * Card for an element in the page context
	 *
	 * @param	Context		$context	Context object
	 * @return	string		Html output
	 */
	public function elementCard($context)
	{
		global $hookmanager, $langs;

		$html = '<!-- elementCard -->';

		$socid = (int) $context->logged_thirdparty->id;

		// initialize
		$action = $this->action;
		$backtopage = $this->backtopage;
		$backtopageforcancel = $this->backtopageforcancel;
		//$backtopagejsfields = $this->backtopagejsfields;
		//$elementEn = $this->elementEn;
		$id = $this->id;
		if ($id > 0) {
			$retFetch = $this->object->fetch($id);
		}
		$object = $this->object;

		//$permissiontoread = $this->permissiontoread;
		$permissiontoadd = $this->permissiontoadd;
		$ref = $this->ref;
		$titleKey = $this->titleKey;
		$title = $langs->trans($titleKey);
		$this->context = $context;
		// Part to show record
		$html .= '<article>';

		$formconfirm = '';

		// Call Hook formConfirm
		$parameters = array('formConfirm' => $formconfirm);
		$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if (empty($reshook)) {
			$formconfirm .= $hookmanager->resPrint;
		} elseif ($reshook > 0) {
			$formconfirm = $hookmanager->resPrint;
		}

		// Print form confirm
		$html .= $formconfirm;

		// Object card
		// ------------------------------------------------------------
		$html .= $this->header($context);

		$url_file = $context->getControllerUrl($context->controller, '', false);
		$html .= '<form method="POST" action="' . $url_file . '">';
		$html .= $context->getFormToken();
		$html .= '<input type="hidden" name="action" value="create">';
		$html .= '<input type="hidden" name="id" value="' . $object->id . '">';
		if ($backtopage) {
			$html .= '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
		}
		if ($backtopageforcancel) {
			$html .= '<input type="hidden" name="backtopageforcancel" value="' . $backtopageforcancel . '">';
		}
		// Common attributes

		$html .= $this->bodyCreate();
		// Save and Cancel buttons
		$html .= '
			<br>
			<div class="grid">
				<div class="center">
					<input type="submit" name="save" role="button" value="' . dol_escape_htmltag($langs->trans('Save')) . '" />
					<input type="submit" name="cancel" role="button" value="' . dol_escape_htmltag($langs->trans('Cancel')) . '" />
				</div>
			</div>';

		// Other attributes. Fields from hook formObjectOptions and Extrafields.
		//include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';
		//$html .= $this->footer();
		$html .= '</article>';
		return $html;
	}

	/**
	 * Html for header
	 *
	 * @param	Context	$context	Context object
	 * @return	string
	 */
	protected function header($context)
	{
		global $langs;

		$html = '';

		// initialize
		$object = $this->object;

		$html .= '
			<!-- html.formcardwebportal.class.php -->
			<header>
				<div class="header-card-left-block inline-block" style="width: 75%;">
					<div class="header-card-main-information inline-block valignmiddle">
						<div><strong>' . $langs->trans("ThirdParty") . ' : ' . dol_escape_htmltag($context->logged_thirdparty->ref) . '</strong></div>
					</div>
				</div>
			</header>';

		return $html;
	}

	/**
	 *  Html for body (create mode)
	 *
	 * @return	string
	 */
	protected function bodyCreate()
	{
		global $langs;

		// initialize
		$object = $this->object;

		// Enhance with select2
		include_once DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php';

		$lastname = GETPOST('lastname') ?: $object->lastname;
		$firstname = GETPOST('firstname') ?: $object->firstname;
		$poste = GETPOST('poste') ?: $object->poste;
		$civility = GETPOST('civility') ?: $object->civility_code;
		$phone = GETPOST('phone') ?: $object->phone_mobile;
		$email = GETPOST('email') ?: $object->email;
		ob_start();
		include dol_buildpath('/custom/operationorder/core/tpl/driver_webportalform.tpl.php', 0);
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Do actions
	 *
	 * @return	void
	 */
	public function doActions()
	{
		global $langs;

		// initialize
		$action = GETPOST('action');
		$backtopage = $this->backtopage;
		$backtopageforcancel = $this->backtopageforcancel;
		$cancel = $this->cancel;
		$elementEn = $this->elementEn;
		$id = $this->id;
		if ($id > 0) {
			$object = new Contact($this->db);
			$object->fetch($id);
		} else {
			$object = $this->object;
		}
		//$permissiontoread = $this->permissiontoread;
		$permissiontoadd = $this->permissiontoadd;

		$context = Context::getInstance();

		$backurlforlist = $context->getControllerUrl('driverlist');

		if (empty($backtopage) || ($cancel && empty($id))) {
			if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
				$backtopage = $context->getControllerUrl($elementEn . 'form');
			}
		}

		// Action to cancel record
		if ($cancel) {
			header("Location: " . $backurlforlist);
			exit;
		}

		$this->object = $object;
		$this->action = $action;
		// Action to update record
		if ($action !== 'create') {
			return;
		}


		$lastname = GETPOST('lastname');
		if (empty($lastname)) {
			$context->setEventMessage($langs->trans('MissingLastname'), 'errors');
			return;
		}

		$firstname = GETPOST('firstname');
		if (empty($firstname)) {
			$context->setEventMessage($langs->trans('MissingFirstname'), 'errors');
			return;
		}

		$poste = GETPOST('poste');
		$email = GETPOST('email', 'custom', 0, FILTER_SANITIZE_EMAIL);
		$phone = GETPOST('phone');

		$civility_id = GETPOST('civility_id');
		if (empty($civility_id)) {
			$context->setEventMessage($langs->trans('MissingCivility'), 'errors');
			return;
		}

		$object->socid = intval($context->logged_thirdparty->id);
		$object->firstname = strval($firstname);
		$object->lastname = strval($lastname);
		$object->poste = strval($poste);
		$object->civility_id = strval($civility_id);
		$object->entity = intval($context->logged_thirdparty->entity);
		$object->phone_mobile = dol_escape_php($phone);
		$object->email = dol_escape_php($email);
		$this->db->begin();

		$object->setExtraField('driver', 1);
		if (intval($object->id) <= 0) {
			$object->statut = 1;
			$res = $object->create($context->logged_user);
		} else {
			$res = $object->update($object->id, $context->logged_user);
		}

		if ($res < 0) {
			$context->setEventMessages($object->error, $object->errors, 'errors');
			$this->db->rollback();
			return;
		}

		$this->db->commit();
		$urlList = $context->getControllerUrl('driverlist');
		header("Location: " . $urlList);
		exit;
	}
}
