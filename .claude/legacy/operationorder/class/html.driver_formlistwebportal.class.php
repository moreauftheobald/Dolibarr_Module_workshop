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


require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php';
dol_include_once('/operationorder/class/html.formlistwebportal.class.parent.php');

/**
 *    Class to manage generation of HTML components
 *    Only common components for WebPortal must be here.
 *
 */
class DriverFormListWebPortal extends ParentFormListWebPortal
{

	/**
	 * List for an element in the page context
	 *
	 * @param	Context		$context		Context object
	 * @return	string		Html output
	 */
	public function elementList($context)
	{
		global $conf, $langs;

		$html = '';
		$nbpages = 0;

		// initialize
		$object = $this->object;

		$search = $this->search;
		$arrayfields = $this->arrayfields;
		$elementEn = $object->element;

		if ($this->limit < 0) {
			$this->limit = $conf->liste_limit;
		}
		if ($this->page <= 0) {
			$this->page = 1;
		}
		$offset = $this->limit * ($this->page - 1);

		$this->socid = (int) $context->logged_thirdparty->id;
		$nbtotalofrecords = 0;
		$resql = null;
		if (!empty($this->socid)) {
			$sql = $this->buildSQL($object, $search, $offset, $nbtotalofrecords);

			$resql = $this->db->query($sql);
			if (!$resql) {
				dol_print_error($this->db);
				return '';
			}

			$num = $this->db->num_rows($resql);
			if ($this->limit > 0) {
				$nbpages = ceil($nbtotalofrecords / $this->limit);
			}
			if ($nbpages <= 0) {
				$nbpages = 1;
			}
		} else {
			$num = 0;
			$nbpages = 0;
		}

		// make array[sort field => sort order] for this list
		$sortList = $this->getSortList($this->sortfield, $this->sortorder);

		$param = $this->getParams($this->contextpage, $this->limit, $search);
		$url_file = $context->getControllerUrl($context->controller);
		$html .= '<form method="POST" id="searchFormList" action="' . $url_file . '">' . "\n";
		$html .= $context->getFormToken();
		$html .= '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
		$html .= '<input type="hidden" name="action" value="list">';
		$html .= '<input type="hidden" name="sortfield" value="' . $this->sortfield . '">';
		$html .= '<input type="hidden" name="sortorder" value="' . $this->sortorder . '">';
		$html .= '<input type="hidden" name="page" value="' . $this->page . '">';
		$html .= '<input type="hidden" name="contextpage" value="' . $this->contextpage . '">';

		// pagination
		$pagination_param = $param . '&sortfield=' . $this->sortfield . '&sortorder=' . $this->sortorder;
		$html .= '<nav id="webportal-' . $elementEn . '-pagination">';
		$html .= '<ul>';
		$html .= '<li><strong>' . $langs->trans($this->titleKey) . '</strong> (' . $nbtotalofrecords . ')</li>';
		$html .= '<li><strong><a style="background-color:#066fac"  class="butAction right" href="' . $context->getControllerUrl('driverform') . '" label="">' . $langs->trans('NewDriver') . '</a></strong></li>';
		$html .= '</ul>';

		/* Generate pagination list */
		$html .= static::generatePageListNav($url_file . $pagination_param, $nbpages, $this->page);

		$html .= '</nav>';

		// table with search filters and column titles
		$html .= '<table id="webportal-' . $elementEn . '-list" responsive="scroll" role="grid">';
		$html .= '<thead>';

		// Fields title search
		// --------------------------------------------------------------------
		$html .= $this->printLineSearchInputs($object, $arrayfields, $search);

		// Fields title label
		// --------------------------------------------------------------------

		$html .= $this->printLineTitle($object, $arrayfields, $sortList, $url_file, $param);

		$html .= '</thead>';
		$html .= '<tbody>';

		// Loop on record
		// --------------------------------------------------------------------
		$i = 0;

		$imaxinloop = ($this->limit ? min($num, $this->limit) : $num);
		while ($i < $imaxinloop) {
			$obj = $this->db->fetch_object($resql);
			if (empty($obj)) {
				break; // Should not happen
			}

			// Store properties in $object
			$object->setVarsFromFetchObj($obj);

			$html .= $this->printLineDriver($obj, $object, $arrayfields, $context);
			$i++;
		}

		// If no record found
		if ($num == 0) {
			$colspan = 1;
			foreach ($arrayfields as $val) {
				if (!empty($val['checked'])) {
					$colspan++;
				}
			}
			$html .= '
				<tr>
					<td colspan="' . $colspan . '">
						<span class="opacitymedium">' . $langs->trans("NoRecordFound") . '</span>
					</td>
				</tr>';
		}

		$html .= '</tbody>';

		$this->db->free($resql);

		$html .= '</table>';

		$html .= '</form>';

		return $html;
	}

	public function getSqlSearchFilters($object, $search)
	{
		$sql = '';
		foreach ($search as $key => $val) {
			$prefix = $this->getPrefix($key);
			if (array_key_exists($key, $object->fields)) {
				if (($key == 'statut') && ($search[$key] == -1 || $search[$key] == '')) {
					continue;
				}
				$mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
				if (
					(strpos($object->fields[$key]['type'], 'integer:') === 0)
					|| (strpos($object->fields[$key]['type'], 'sellist:') === 0)
					|| !empty($object->fields[$key]['arrayofkeyval'])
				) {
					if (
						$search[$key] == "-1"
						|| (
							$search[$key] === '0'
							&& (
								empty($object->fields[$key]['arrayofkeyval'])
								|| !array_key_exists('0', $object->fields[$key]['arrayofkeyval'])
							)
						)
					) {
						$search[$key] = '';
					}
					$mode_search = 2;
				}

				if ($search[$key] != '') {
					$sql .= natural_search(
						$prefix . $this->db->escape($key),
						$search[$key],
						(($key == 'status') ? ($search[$key] < 0 ? 1 : 2) : $mode_search)
					);
				}
			} elseif (preg_match('/(_dtstart|_dtend)$/', $key) && $search[$key] != '') {
				$columnName = preg_replace('/(_dtstart|_dtend)$/', '', $key);
				if (preg_match('/^(date|timestamp|datetime)/', $object->fields[$columnName]['type'])) {
					if (preg_match('/_dtstart$/', $key)) {
						$sql .= " AND " . $prefix . $this->db->escape($columnName) . " >= '" . $this->db->idate($search[$key]) . "'";
					}
					if (preg_match('/_dtend$/', $key)) {
						$sql .= " AND " . $prefix . $this->db->escape($columnName) . " <= '" . $this->db->idate($search[$key]) . "'";
					}
				}
			}
		}
		return $sql;
	}

	private function buildSQL($object, $search, $offset, &$nbtotalofrecords)
	{
		// Build and execute select
		// --------------------------------------------------------------------
		$sql = "SELECT ";
		$sql .= $object->getFieldList('t');

		$sqlfields = $sql; // $sql fields to remove for count total

		$sql .= " FROM " . $this->db->prefix() . "socpeople as t";
		$sql .= " LEFT JOIN " . $this->db->prefix() . "socpeople_extrafields as te ON te.fk_object=t.rowid";

		$sql .= " WHERE 1 = 1";

		// filter on logged third-party
		$sql .= " AND t.fk_soc = " . ((int) $this->socid);
		$sql .= " AND te.driver = 1 ";
		// discard record with status draft

		$sql .= $this->getSqlSearchFilters($object, $search);

		// Count total nb of records
		$nbtotalofrecords = 0;
		if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
			$nbtotalofrecords = $this->getSqlNbTotalOfRecord($sql, $sqlfields, $offset, $this->page);
		}

		if (!$this->sortorder) {
			$this->sortorder = 'DESC';
		}

		// Complete request and execute it with limit
		$sql .= $this->db->order($this->sortfield, $this->sortorder);
		if ($this->limit) {
			$sql .= $this->db->plimit($this->limit, $offset);
		}

		return $sql;
	}

	public function getPrefix($key)
	{
		$prefix = "t.";
		return $prefix;
	}

	public function printLineSearchInputs($object, $arrayfields, $search)
	{
		global $langs;
		$html = '<tr role="search-row">';
		$html .= '<td data-col="row-checkbox" >';
		$html .= '	<button class="btn-filter-icon btn-search-filters-icon" type="submit" name="button_search_x" value="x" aria-label="' . dol_escape_htmltag($langs->trans('Search')) . '" ></button>';
		$html .= '	<button class="btn-filter-icon btn-remove-search-filters-icon" type="submit" name="button_removefilter_x" value="x" aria-label="' . dol_escape_htmltag($langs->trans('RemoveSearchFilters')) . '"></button>';
		$html .= '</td>';
		// }

		$statusArray = [
			0 => 'DriverInactive',
			1 => 'DriverActive'
		];

		foreach ($object->fields as $key => $val) {
			$prefix = $this->getPrefix($key);
			if (empty($arrayfields[$prefix . $key]['checked'])) {
				continue;
			}

			$search_val = (isset($search[$key]) ? $search[$key] : '');
			$html .= '<td data-label="' . $arrayfields[$prefix . $key]['label'] . '" data-col="' . dol_escape_htmltag($key) . '" >';
			if ($key == 'statut') {
				$html .= $this->form->selectarray(
					'search_' . $key, $statusArray, $search_val, $val['notnull'], 0, 0, '', 1, 0, 0, '', ''
				);
			} elseif (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
				$html .= $this->form->selectarray(
					'search_' . $key, $val['arrayofkeyval'], $search_val, $val['notnull'], 0, 0, '', 1, 0, 0, '', ''
				);
			} elseif (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
				$postDateStart = dol_mktime(
					0, 0, 0, (int) $search[$key . '_dtstartmonth'], (int) $search[$key . '_dtstartday'],
					(int) $search[$key . '_dtstartyear']
				);
				$postDateEnd = dol_mktime(
					0, 0, 0, (int) $search[$key . '_dtendmonth'], (int) $search[$key . '_dtendday'],
					(int) $search[$key . '_dtendyear']
				);
				$html .= '<div class="grid width150">';
				$html .= $this->form->inputDate(
					'search_' . $key . '_dtstart', $postDateStart ? $postDateStart : '', $langs->trans('From')
				);
				$html .= '</div>';
				$html .= '<div class="grid width150">';
				$html .= $this->form->inputDate(
					'search_' . $key . '_dtend', $postDateEnd ? $postDateEnd : '', $langs->trans('to')
				);
				$html .= '</div>';
			} else {
				$html .= '<input type="text" name="search_' . $key . '" value="' . dol_escape_htmltag($search_val) . '">';
			}
			$html .= '</td>';
		}

		$html .= '</tr>';
		return $html;
	}


	public function printLineDriver($obj, $object, $arrayfields, $context)
	{

		// Show line of result
		$html = '<tr data-rowid="' . $obj->rowid . '">';
		$html .= '<td class="nowraponall">';
		$html .= '</td>';
		foreach ($object->fields as $key => $val) {
			$prefix = $this->getPrefix($key);
			if (!empty($arrayfields[$prefix . $key]['checked'])) {
				$html .= '<td class="nowraponall" data-label="' . $arrayfields[$prefix . $key]['label'] . '">';
				if ($key == 'statut') {
					$html .= $object->ajax_object_onoff('statut', 'DriverActive', 'DriverInactive');
				} elseif ($key == 'lastname') {
					$url = $context->getControllerUrl('driverform', ['id' => $object->id]);
					$html .= '<a href="' . $url . '">' . $object->lastname . '</a>';
				} else {
					$html .= $this->form->showOutputFieldForObject($object, $val, $key, $obj->{$key}, '');
				}
			}
		}

		$html .= '</tr>';
		return $html;
	}
}
