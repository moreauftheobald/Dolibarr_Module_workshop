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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
dol_include_once('/dolifleet/class/vehicule.class.php');
dol_include_once('/operationorder/class/webportalvehicule.class.php');

/**
 *    Class to manage generation of HTML components
 *    Only common components for WebPortal must be here.
 *
 */
class VehiculeFormCardWebPortal extends FormCardWebPortal
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
		$langs->loadLangs(array('website', 'other', 'companies', 'dolifleet@dolifleet'));
		if ($id <= 0) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		// load module libraries
		dol_include_once('/dolifleet/class/webportal' . $elementEn . '.class.php');
		$formfile = new FormFile($this->db);
		$this->formfile = $formfile;

		// Get parameters
		//$id = $id > 0 ? $id : GETPOST('id', 'int');
		$ref = GETPOST('ref', 'alpha');
		$action = GETPOST('action', 'aZ09');
		$confirm = GETPOST('confirm', 'alpha');
		$cancel = GETPOST('cancel', 'aZ09');
		$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'webportal' . $elementEn . 'card'; // To manage different context of search
		$backtopage = GETPOST('backtopage', 'alpha');	 // if not set, a default page will be used
		$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha'); // if not set, $backtopage will be used
		$backtopagejsfields = GETPOST('backtopagejsfields', 'alpha');

		// Initialize a technical objects
		$object = new WebPortalVehicule($this->db);
		//$extrafields = new ExtraFields($db);
		$hookmanager->initHooks(array('webportal' . $elementEn . 'card', 'globalcard')); // Note that conf->hooks_modules contains array
		// Fetch optionals attributes and labels
		//$extrafields->fetch_name_optionals_label($object->table_element);
		//$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

		if (empty($id)) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}


		$action = 'view';

		$retFetch = $object->fetchWebVehicule($id);

		if ($retFetch < 0) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		if (empty($retFetch)) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		// Security check (enable the most restrictive one)
		if (!isModEnabled('webportal')) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		if (!$permissiontoread) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		// set form card
		$this->action = $action;
		$this->backtopage = $backtopage;
		$this->backtopageforcancel = $backtopageforcancel;
		$this->backtopagejsfields = $backtopagejsfields;
		$this->cancel = $cancel;
		$this->elementEn = $elementEn;
		$this->id = (int) $id;
		$this->object = $object;
		$this->permissiontoread = $permissiontoread;
		$this->permissiontoadd = $permissiontoadd;
		$this->permissiontodelete = $permissiontodelete;
		$this->permissionnote = $permissionnote;
		$this->permissiondellink = $permissiondellink;
		$this->titleKey = $objectclass . 'CardTitle';
		$this->ref = $ref;
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
		if ($socid != $this->object->fk_soc) {
			accessforbidden($langs->trans('YouCannotAccessThisWebPortalPage'), 0, 0, 1);
		}

		$this->context = $context;

		// initialize
		$action = $this->action;
		$backtopage = $this->backtopage;
		$backtopageforcancel = $this->backtopageforcancel;
		//$backtopagejsfields = $this->backtopagejsfields;
		//$elementEn = $this->elementEn;
		$id = $this->id;
		$object = $this->object;
		//$permissiontoread = $this->permissiontoread;
		$permissiontoadd = $this->permissiontoadd;
		$ref = $this->ref;
		$titleKey = $this->titleKey;
		$title = $langs->trans($titleKey);

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

		// Common attributes
		$keyforbreak = '';
		$html .= $this->bodyView($keyforbreak);

		// Other attributes. Fields from hook formObjectOptions and Extrafields.
		//include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';
		//$html .= $this->footer();
		$html .= '</article>';
		return $html;
	}

	/**
	 * Html for body (view mode)
	 * @param	string	$keyforbreak	[=''] Key for break left block
	 * @return	string	Html for body
	 */
	protected function bodyView($keyforbreak = '')
	{
		global $langs, $conf;

		$html = '';

		// initialize
		$object = $this->object;
		$object->fields = dol_sort_array($object->fields, 'position');

		// separate fields to show on the left and on the right
		$fieldShowList = array();
		foreach ($object->fields as $key => $val) {
			// discard if it's a hidden field on form
			if (abs($val['visible']) != 1 && abs($val['visible']) != 3 && abs($val['visible']) != 4 && abs($val['visible']) != 5) {
				continue;
			}

			if (array_key_exists('enabled', $val) && isset($val['enabled']) && !verifCond($val['enabled'])) {
				continue; // we don't want this field
			}

			if (!empty($val['showonheader'])) {
				continue; // already on header
			}

			$fieldShowList[$key] = $val;
		}

		$html .= '<div class="grid">';
		$html .= '<div class="card-left">';
		$keyforbreak = 'km_date';
		unset($object->fields['dfol']);
		unset($object->fields['fk_soc']);
		foreach ($object->fields as $key => $val) {
			if (!array_key_exists($key, $fieldShowList)) {
				continue; // not to show
			}

			$value = $object->$key;

			$html .= '<div class="grid field_' . $key . '">';

			$html .= '<div class="' . (empty($val['tdcss']) ? '' : $val['tdcss']) . ' fieldname_' . $key;
			$html .= '">';
			$labeltoshow = '';
			$labeltoshow .= '<strong>' . $langs->trans($val['label']) . '</strong>';
			$html .= $labeltoshow;
			$html .= '</div>';

			$html .= '<div class="valuefield fieldname_' . $key;
			if (!empty($val['cssview'])) {
				$html .= ' ' . $val['cssview'];
			}
			$html .= '">';
			if ($key == 'lang') {
				$langs->load('languages');
				$labellang = ($value ? $langs->trans('Language_' . $value) : '');
				//$html .= picto_from_langcode($value, 'class="paddingrightonly saturatemedium opacitylow"');
				$html .= $labellang;
			} else {
				$html .= $this->form->showOutputFieldForObject($object, $val, $key, $value, '', '', '', 0);
			}
			$html .= '</div>';

			$html .= '</div>';


			// fields on the right
			if ($key == $keyforbreak) {
				$html .= '</div>';
				$html .= '<div class="card-right">';
			}
		}

		$url = $this->context->getControllerUrl('vsrlist', ['idVh' => $object->id]);
		$html .= '
			<div class="grid field_pdf">
				<div>
					<strong>' . $langs->trans('VSRVehicule') . '</strong>
				</div>
				<div class="valuefield">
					<a style="background-color:#066fac" class="butAction width200" href="' . $url . '">' . $langs->trans('LinkTOVSR') . '</a>
				</div>
			</div>';
		$html .= '</div>';
		$html .= '</div><br><br>';

		$html .= '<div>' . $this->printLines($object) . '</div>';

		// files 
		$filedir = $conf->dolifleet->dir_output . '/vehicule/';
		$filelist = dol_dir_list($filedir . '/' . $object->id, 'files');

		$html .= '
			<div class="grid field_pdf">
				<div class="card-left">
					<div>
						<strong>' . $langs->trans('VehiculeFiles') . '</strong>
					</div>
					<div class="valuefield">';
		if (!empty($filelist) && !empty($filedir)) {
			$html .= '<ul>';
			foreach ($filelist as $file) {
				if (dol_mimetype($file['fullname']) !== 'application/pdf') {
					continue;
				}
				$html .= '<li>';
				$html .= $this->getDocumentsLink(
					'dolifleet',
					'vehicule/' . dol_sanitizeFileName($object->id),
					$filedir . dol_sanitizeFileName($object->id),
					$file['name']
				);

				$html .= '</li>';
			}

			$html .= '</ul>';
		}
		$html .= '
				</div>
			</div>
			<style>
				.fileuploadform input {
					width: 40% !important;
				}
			</style>
			<div class="card-right fileuploadform">';
		$url_file = $this->context->getControllerUrl($this->context->controller, ['vh_id' => $this->object->id], false);
		$html .= $this->formfile->form_attach_new_file($url_file, '', 0,  0, 1, 10, $object, '', 1, '', 0, 'formuserfile', '.pdf', '', 0, 0, 1, 1);
		$html .= '
			</div>

		</div>';
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
		$addgendertxt = '';

		$html .= '
			<!-- html.formcardwebportal.class.php -->
			<header>
				<div class="header-card-left-block inline-block" style="width: 75%;">
					<div class="header-card-main-information inline-block valignmiddle">
						<div><strong>' . $langs->trans("ThirdParty") . ' : ' . dol_escape_htmltag($context->logged_thirdparty->ref) . '</strong></div>
					</div>
				</div>
				<div class="header-card-right-block inline-block" style="width: 24%;">';


		$html .= '</div>';
		// Right block - end

		$html .= '</header>';

		return $html;
	}

	public function getorlinkedHV($vehicule)
	{
		$sql = 'SELECT IF(fk_target = ' . $vehicule->id . ',fk_source,fk_target) as linked FROM ' . $this->db->prefix() . 'dolifleet_vehicule_link ';
		$sql .= 'WHERE (fk_source = ' . $vehicule->id . ' OR fk_target = ' . $vehicule->id . ') ORDER BY date_start DESC';
		$resql = $this->db->query($sql);
		if ($resql) {
			dol_syslog($this->db->lasterror(), 'LOG_ERR');
			return '';
		}

		$num = $this->db->num_rows($resql);
		if ($num <= 0) {
			return '';
		}

		$obj = $this->db->fetch_object($resql);
		$vh = new Vehicule($this->db);
		$ret = $vh->fetch($obj->linked);
		if ($ret < 0) {
			dol_syslog(implode(',', array_merge($vh->errors, [$vh->error])), 'LOG_ERR');
			return '';
		}

		if ($ret == 0) {
			return '';
		}
		return $vh->vin . ' - ' . $vh->immatriculation;
	}


	/**
	 * Html for header
	 *
	 * @param	Context	$object	 object
	 * @return	string
	 */
	protected function printLines($object)
	{
		global $langs;
		$res = $object->getOperations();
		if ($res < 0) {
			setEventMessage($object->error, 'errors');
		}
		if (empty($object->operations)) {
			return '';
		}

		$html = '
			<table id="webportal-vehicule-line-list" role="grid">
				<thead>
					<tr class="">
						<th class="wrapcolumntitle  maxwidthsearch" scope="col" title="' . $langs->trans('Operation') . '">
							' . $langs->trans('VehiculeOperation') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch right" scope="col">
							' . $langs->trans('KM') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch" scope="col">
							' . $langs->trans('VehiculeOperationDelay') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch right" scope="col">
							' . $langs->trans('VehiculeOperationLastDateDone') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch right" scope="col">
							' . $langs->trans('VehiculeOperationLastKmDone') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch right" scope="col">
							' . $langs->trans('VehiculeOperationDateNext') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch right" scope="col">
							' . $langs->trans('VehiculeOperationKmNext') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch" scope="col">
							' . $langs->trans('VehiculeOperationOnTime') . '
						</th>
						<th class="wrapcolumntitle  maxwidthsearch" scope="col">
							' . $langs->trans('VehiculeOperationNextOR') . '
						</th>
					</tr>
				</thead>
				<tbody>
				
			';

		foreach ($object->operations as $operation) {
			$dateDone = '';
			if (!empty($operation->date_done)) {
				$dateDone = dol_print_date($operation->date_done, "%d/%m/%Y");
			}
			$dateNext = '';
			if (!empty($operation->date_next)) {
				$dateNext = dol_print_date($operation->date_next, "%d/%m/%Y");
			}
			$operationorderLink = '';
			if (!empty($operation->or_next)) {
				$operationorder = new OperationOrder($object->db);
				$res = $operationorder->fetch($operation->or_next, false);
				if ($res<0) {
					setEventMessages($operationorder->error, $operationorder->errors, 'errors');
				}

				$url_file = $this->context->getControllerUrl('operationordercard', ['op_id' => $operation->or_next]);
				$operationorderLink .= '<a href="' . $url_file . '">' . $operationorder->ref . '</a>';
			}
			$html .= '
				<tr class="oddeven">
					<td class="left">' . $operation->getWebName() . '</td>
					<td class="right">' . (!empty($operation->km) ? price2num($operation->km) : '') . '</td>
					<td class="left">' . (!empty($operation->delai_from_last_op) ? $operation->delai_from_last_op . ' ' . $langs->trans('Months') : '') . '</td>
					<td class="right">' . $dateDone . '</td>
					<td class="right">' . (!empty($operation->km_done) ? $operation->km_done : '') . '</td>
					<td class="right">'. $dateNext.'</td>
					<td class="right">'. $operation->km_next.'</td>
					<td class="left">'.  (!empty($operation->on_time)?dolGetBadge($langs->trans('VehiculeOperationOnTime'), '', 'danger'):'').'</td>
					<td class="left">'.  $operationorderLink . '</td>
				</tr>';
		}

		$html .= '
			</tbody>
		</table>';
		return $html;
	}

	/**
	 * Show a Document icon with link(s)
	 * You may want to call this into a div like this:
	 * print '<div class="inline-block valignmiddle">'.$formfile->getDocumentsLink($element_doc, $filename, $filedir).'</div>';
	 *
	 * @param string $modulepart 'propal', 'facture', 'facture_fourn', ...
	 * @param string $modulesubdir Sub-directory to scan (Example: '0/1/10', 'FA/DD/MM/YY/9999'). Use '' if file is not into subdir of module.
	 * @param string $filedir Full path to directory to scan
	 * @param string $filter Filter filenames on this regex string (Example: '\.pdf$')
	 * @param string $morecss Add more css to the download picto
	 * @param int<0,1> $allfiles 0=Only generated docs, 1=All files
	 * @return    string                Output string with HTML link of documents (might be empty string). This also fill the array ->infofiles
	 */
	public function getDocumentsLink($modulepart, $modulesubdir, $filedir, $filter = '', $morecss = '')
	{
		include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		$out = '';

		$context = Context::getInstance();
		if (!$context) {
			return '';
		}

		$this->infofiles = array('nboffiles' => 0, 'extensions' => array(), 'files' => array());

		$file_list = dol_dir_list($filedir, 'files', 0, '', '\.meta$|\.png$'); // We also discard .meta and .png preview

		// For ajax treatment
		$out .= '<!-- html.formwebportal::getDocumentsLink -->' . "\n";
		if (!empty($file_list)) {
			$tmpout = '';

			// Loop on each file found
			$found = 0;
			$i = 0;
			foreach ($file_list as $file) {
				$i++;
				if ($filter && !preg_match('/' . $filter . '/i', $file["name"])) {
					continue; // Discard this. It does not match provided filter.
				}

				$found++;
				// Define relative path for download link (depends on module)
				$relativepath = $file["name"]; // Cas general
				if ($modulesubdir) {
					$relativepath = $modulesubdir . "/" . $file["name"]; // Cas propal, facture...
				}
				$url = $context->getControllerUrl('document') . '&modulepart=' . $modulepart . '&entity=0&file=' . urlencode($relativepath) . '&soc_id=' . $context->logged_thirdparty->id;
				$tmpout .= '<a href="' . $url . '"' . ($morecss ? ' class="' . $morecss . '"' : '') . ' role="downloadlink"';
				$mime = dol_mimetype($relativepath, '', 0);
				if (preg_match('/text/', $mime)) {
					$tmpout .= ' target="_blank" rel="noopener noreferrer"';
				}
				$tmpout .= '>';
				$tmpout .= img_mime($relativepath);
				$tmpout .= mb_strimwidth($file["name"], 0, 30, "...");
				$tmpout .= '</a>';
			}

			if ($found) {
				$out .= $tmpout;
			}
		}

		return $out;
	}

	/**
	 * Do actions
	 *
	 * @return	void
	 */
	public function doActions()
	{
		$this->context = Context::getInstance();

		if (GETPOST('sendit', 'alpha')) {
			$this->saveFile();
		}
	}

	/**
	 * Do actions
	 *
	 * @return	void
	 */
	public function saveFile()
	{
		global $langs, $conf;
		$id = GETPOST('vh_id');

		include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		$name = $_FILES['userfile']['name'];
		if (is_array($_FILES['userfile']['tmp_name'])) {
			$userfiles = $_FILES['userfile']['tmp_name'];
		} else {
			$userfiles = array($_FILES['userfile']['tmp_name']);
		}

		if (dol_mimetype($_FILES['userfile']['name']) !== 'application/pdf') {
			$this->context->setEventMessages('OnlyPdfCanBeLoadedHere', null, 'errors');
			return;
		}

		$error = 0;
		foreach ($userfiles as $key => $userfile) {
			if (empty($_FILES['userfile']['tmp_name'][$key])) {
				$error++;
				if ($_FILES['userfile']['error'][$key] == 1 || $_FILES['userfile']['error'][$key] == 2) {
					$this->context->setEventMessages($langs->trans('ErrorFileSizeTooLarge'), null, 'errors');
				} else {
					$this->context->setEventMessages($langs->trans("ErrorFieldRequired",
							$langs->transnoentitiesnoconv("File")), null, 'errors');
				}
			}
			if (preg_match('/__.*__/', $_FILES['userfile']['name'][$key])) {
				$error++;
				$this->context->setEventMessages($langs->trans('ErrorWrongFileName'), null, 'errors');
			}
		}
		$upload_dir = $conf->dolifleet->dir_output . '/vehicule/' . $id;
		if (!$error) {
			$result = dol_add_file_process(
				$upload_dir, 0, 1, 'userfile', $name, null, '', 0, $this->object
			);
			if ($result <= 0) {
				$this->context->setEventMessages($langs->transnoentitiesnoconv("SomethingWrongHappenedDuringFileLoad"),
					null, 'errors');
			}
		}
	}
}
