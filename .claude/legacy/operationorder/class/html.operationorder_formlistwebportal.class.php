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


dol_include_once('/operationorder/class/html.formlistwebportal.class.parent.php');
dol_include_once('/dolifleet/class/vehicule.class.php');
dol_include_once('/operationorder/class/operationorderstatus.class.php');

/**
 *    Class to manage generation of HTML components
 *    Only common components for WebPortal must be here.
 *
 */
class OperationOrderFormListWebPortal extends ParentFormListWebPortal
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
		$resql = null;
		$nbtotalofrecords = 0;
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

			$html .= $this->printLineOperationOrder($obj, $object, $arrayfields, $context);
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
			$html .= '<tr><td colspan="' . $colspan . '"><span class="opacitymedium">' . $langs->trans("NoRecordFound") . '</span></td></tr>';
		}

		$html .= '</tbody>';

		$this->db->free($resql);

		$html .= '</table>';

		$html .= '</form>';

		return $html;
	}

	public function getPrefix($key)
	{
		$prefix = "t.";
		if (in_array($key, ['immatriculation', 'vin'])) {
			$prefix = 'dfv.';
		}
		if ($key == 'fk_conducteur') {
			$prefix = 'c.';
		}

		return $prefix;
	}

	public function printLineOperationOrder($obj, $object, $arrayfields, $context)
	{

		// Show line of result
		$html = '<tr data-rowid="' . $obj->rowid . '">';
		$html .= '<td class="nowraponall">';
		$html .= '</td>';

		foreach ($object->fields as $key => $val) {
			$prefix = $this->getPrefix($key);
			if (!empty($arrayfields[$prefix . $key]['checked'])) {
				$html .= '<td class="nowraponall" data-label="' . $arrayfields[$prefix . $key]['label'] . '">';
				if ($key == 'status') {
					$status = new OperationOrderStatus($this->db);
					$res = $status->fetchDefault($obj->{$key}, 0);
					if ($res > 0) {
						$html .= $status->getBadge();
					} else {
						$html .= $status->getStaticNomUrl($obj->{$key});
					}
				} elseif ($key == 'fk_conducteur') {
					if (!empty($obj->{$key})) {
						$conducteur = new Contact($this->db);
						$conducteur->fetch($obj->{$key}, false);

						$html .= ucwords($conducteur->firstname);
						$html .= ' ' . strtoupper($conducteur->lastname);
					}
				} elseif ($key == 'ref') {
					$url_file = $context->getControllerUrl('operationordercard', ['op_id' => $obj->rowid]);
					$html .= '<a href="' . $url_file . '">' . $obj->ref . '</a>';
				} elseif ($key == 'vin') {
					$url_card = $context->getControllerUrl('vehiculecard', ['vh_id' => $obj->fk_vehicule]);
					$html .= '<a href="' . $url_card . '">' . $obj->{$key} . '</a>';
				} else {
					$html .= $this->form->showOutputFieldForObject($object, $val, $key, $obj->{$key}, '');
				}
				$html .= '</td>';
			}
		}

		$html .= '</tr>';
		return $html;
	}

	public function getSqlSearchFilters($object, $search)
	{
		$sql = '';
		foreach ($search as $key => $val) {
			$prefix = $this->getPrefix($key);
			if (array_key_exists($key, $object->fields) && !empty($search[$key])) {
				if (($key == 'status') && $search[$key] == -1) {
					continue;
				}
				if (in_array($key, ['immatriculation', 'vin']) && !empty($search[$key])) {
					$sql .= natural_search($prefix . $key, $search[$key]);
					continue;
				}

				if ($key=='status' && !empty($search[$key])) {
					$sql .= " AND ops.code IN ('". $search[$key]."')";
					continue;
				}

				if ($key == 'fk_conducteur') {
					$sql .= ' AND CONCAT(c.firstname, " ", c.lastname) LIKE "%' . $this->db->escape($search[$key]) . '%"';
					continue;
				}

				$mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
				if (
					strpos($object->fields[$key]['type'], 'integer:') === 0
					|| strpos($object->fields[$key]['type'], 'sellist:') === 0
					|| !empty($object->fields[$key]['arrayofkeyval'])
				) {
					if (
						$search[$key] == "-1" || (
							$search[$key] === '0' && (
								empty($object->fields[$key]['arrayofkeyval'])
								|| !array_key_exists('0', $object->fields[$key]['arrayofkeyval'])
							)
						)
					) {
						$search[$key] = '';
					}
					$mode_search = 2;
				}

				$sql .= natural_search($prefix . $this->db->escape($key), $val, (($key == 'status') ? 2 : $mode_search));
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
		$sql .= $object->getFieldList('t', ['immatriculation', 'vin']);
		$sql .= ", t.entity as element_entity";
		$sql .= ", dfv.rowid as fk_vehicule";
		$sql .= ", dfv.immatriculation as immatriculation";
		$sql .= ", dfv.vin as vin";

		$sqlfields = $sql; // $sql fields to remove for count total

		$sql .= " FROM " . $this->db->prefix() . $object->table_element . " as t";
		$sql .= " LEFT JOIN " . $this->db->prefix() . "dolifleet_vehicule as dfv ON t.fk_vehicule = dfv.rowid";
		$sql .= " LEFT JOIN " . $this->db->prefix() . "socpeople as c ON t.fk_conducteur = c.rowid";
		$sql .= " LEFT JOIN " . $this->db->prefix() . "operationorder_status as ops ON t.status = ops.rowid";

		$sql .= " WHERE 1 = 1";

		// filter on logged third-party
		$sql .= " AND t.fk_soc = " . ((int) $this->socid);
		// discard record with status draft
		$sql .= " AND t.status <> 0 ";

		$sql .= $this->getSqlSearchFilters($object, $search);

		// Count total nb of records
		$nbtotalofrecords = 0;
		if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
			$nbtotalofrecords = $this->getSqlNbTotalOfRecord($sql, $sqlfields, $offset, $this->page);
		}
		if (!$this->sortfield) {
			reset($object->fields); // Reset is required to avoid key() to return null.
			$this->sortfield = 't.date_creation'; // Set here default search field. By default 1st field in definition.
		} else {
			$this->sortfield = str_replace("t.", "", $this->sortfield);
			if ($this->sortfield == 'c.fk_conducteur') {
				$this->sortfield = 'c.lastname';
			}
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
}
