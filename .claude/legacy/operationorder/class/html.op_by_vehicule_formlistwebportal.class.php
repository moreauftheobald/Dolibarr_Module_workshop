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


dol_include_once('/dolifleet/class/vehicule.class.php');
dol_include_once('/dolifleet/class/vehiculeOperation.class.php');
dol_include_once('/operationorder/class/html.formlistwebportal.class.parent.php');
dol_include_once('/operationorder/class/operationorderstatus.class.php');
dol_include_once('/operationorder/class/webportaloperation.class.php');

/**
 *    Class to manage generation of HTML components
 *    Only common components for WebPortal must be here.
 *
 */
class OPByVehiculeFormListWebPortal extends ParentFormListWebPortal
{
	private $idVh = null;
	private $vin = null;
	private $immatriculation = null;

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

		$operation = new WebPortalOperation($this->db);
		$arrayfields = $this->arrayfields;
		unset($arrayfields['t.status']);
		foreach ($operation->fields as $key => $val) {
			// If $val['visible']==0, then we never show the field
			if (empty($val['visible'])) {
				continue;
			}

			$visible = (int) dol_eval((string) $val['visible'], 1);
			$arrayfields['t.' . $key] = array(
				'label'	   => $val['label'],
				'checked'  => (($visible < 0) ? 0 : 1),
				'enabled'  => (int) (abs($visible) != 3 && (bool) dol_eval($val['enabled'], 1)),
				'position' => $val['position'],
				'help'	   => isset($val['help']) ? $val['help'] : ''
			);

			if (GETPOST('search_' . $key, 'alpha') !== '') {
				$search[$key] = GETPOST('search_' . $key, 'alpha');
			}
			if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
				$search[$key . '_dtstartmonth'] = GETPOSTINT('search_' . $key . '_dtstartmonth');
				$search[$key . '_dtstartday'] = GETPOSTINT('search_' . $key . '_dtstartday');
				$search[$key . '_dtstartyear'] = GETPOSTINT('search_' . $key . '_dtstartyear');
				$search[$key . '_dtstart'] = dol_mktime(
					23, 59, 59, $search[$key . '_dtstartmonth'], $search[$key . '_dtstartday'],
					$search[$key . '_dtstartyear']
				);
				$search[$key . '_dtendmonth'] = GETPOSTINT('search_' . $key . '_dtendmonth');
				$search[$key . '_dtendday'] = GETPOSTINT('search_' . $key . '_dtendday');
				$search[$key . '_dtendyear'] = GETPOSTINT('search_' . $key . '_dtendyear');
				$search[$key . '_dtend'] = dol_mktime(
					23, 59, 59, $search[$key . '_dtendmonth'], $search[$key . '_dtendday'], $search[$key . '_dtendyear']
				);
			}
		}
		// Purge search criteria
		if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
			foreach ($operation->fields as $key => $val) {
				$search[$key] = '';
				if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
					//$search[$key . '_dtstart'] = '';
					//$search[$key . '_dtend'] = '';
					$search[$key . '_dtstartmonth'] = '';
					$search[$key . '_dtendmonth'] = '';
					$search[$key . '_dtstartday'] = '';
					$search[$key . '_dtendday'] = '';
					$search[$key . '_dtstartyear'] = '';
					$search[$key . '_dtendyear'] = '';
				}
			}
			$this->search = $search;
		}

		$elementEn = $object->element;
		$this->context = $context;
		$this->titleKey = 'OperationByVehiculeList';

		if ($this->limit < 0) {
			$this->limit = $conf->liste_limit;
		}
		if ($this->page <= 0) {
			$this->page = 1;
		}
		$offset = $this->limit * ($this->page - 1);

		// Build and execute select
		// --------------------------------------------------------------------

		$nbtotalofrecords = 0;

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


		$object->fields += $operation->fields;

		// make array[sort field => sort order] for this list
		$sortList = $this->getSortList($this->sortfield, $this->sortorder);

		$param = $this->getParams($this->contextpage, $this->limit, $search);
		$url_file = $context->getControllerUrl($context->controller, ['idVh' => $this->idVh]);
		$html .= '<form method="POST" id="searchFormList" action="' . $url_file . '">' . "\n";
		$html .= $context->getFormToken();
		$html .= '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
		$html .= '<input type="hidden" name="action" value="list">';
		$html .= '<input type="hidden" name="sortfield" value="' . $this->sortfield . '">';
		$html .= '<input type="hidden" name="sortorder" value="' . $this->sortorder . '">';
		$html .= '<input type="hidden" name="page" value="' . $this->page . '">';
		$html .= '<input type="hidden" name="contextpage" value="' . $this->contextpage . '">';
		$html .= '<input type="hidden" name="idVh" value="' . intval($this->idVh) . '">';

		// pagination
		$pagination_param = $param . '&sortfield=' . $this->sortfield . '&sortorder=' . $this->sortorder . '&idVh=' . intval($this->idVh);
		$html .= '<nav id="webportal-' . $elementEn . '-pagination">';
		$html .= '<ul>';
		$html .= '<li><strong>' . $langs->trans($this->titleKey) . '</strong> (' . $nbtotalofrecords . ')</li>';
		$html .= '</ul>';

		/* Generate pagination list */
		$html .= static::generatePageListNav($url_file . $pagination_param, $nbpages, $this->page);

		$html .= '</nav>';

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
		$colspan = 22;

		if ($resql) {
			$imaxinloop = ($this->limit ? min($num, $this->limit) : $num);
			while ($i < $imaxinloop) {
				$obj = $this->db->fetch_object($resql);
				if (empty($obj)) {
					break; // Should not happen
				}

				// Store properties in $object
				$object->setVarsFromFetchObj($obj);

				$html .= $this->printLineOperationByVehicule($obj, $object, $arrayfields, $context);
				$i++;
			}
			// If no record found
			if ($num == 0) {
				$html .= '
				<tr>
					<td colspan="' . $colspan . '">
						<span class="opacitymedium">' . $langs->trans("NoRecordFound") . '</span>
					</td>
				</tr>';
			}
		} else {
			$html .= '
				<tr>
					<td colspan="' . $colspan . '">
						<span class="opacitymedium">' . $langs->trans("NoRecordFoundPleaseSelectAVehiculeFirst") . '</span>
					</td>
				</tr>';
		}

		$this->db->free($resql);
		$html .= '</tbody>';

		$html .= '</table>';

		$html .= '</form>';

		return $html;
	}

	public function printLineOperationByVehicule($obj, $object, $arrayfields, $context)
	{
		// Show line of result
		$html = '<tr data-rowid="' . $object->rowid . '">';
		$html .= '<td class="nowrap left"></td>';

		foreach ($object->fields as $key => $val) {
			$prefix = $this->getPrefix($key);
			if (!empty($arrayfields[$prefix . $key]['checked'])) {
				$html .= '<td>';
				if ($key == 'rowid') {
					$html .= $object->showOutputField($val, $key, $obj->id, '');
				} elseif ($key == 'vin') {
					$urlOp = $context->getControllerUrl('vehiculecard', ['vh_id' => $obj->rowid]);
					$html .= '<a href="' . $urlOp . '">' . $obj->vin . '</a>';
				} elseif ($key == 'fk_product') {
					$html .= $obj->label;
				} else {
					$html .= $object->showOutputField($val, $key, $obj->$key);
				}
				$html .= '</td>';
			}
		}

		$html .= '</tr>';

		return $html;
	}

	private function buildSQL($object, $search, $offset, &$nbtotalofrecords)
	{
		$operation = new WebPortalOperation($this->db);
		$sql = "SELECT ";
		$sql .= $object->getFieldList('t') . ', ';
		$sql .= $operation->getFieldList('o') . ', ';
		$sql .= ' p.label';

		$sqlfields = $sql; // $sql fields to remove for count total

		$sql .= ' FROM ' . $this->db->prefix() . $object->table_element . ' t ';
		$sql .= ' INNER JOIN  ' . $this->db->prefix() . $operation->table_element . ' o ON o.fk_vehicule=t.rowid ';
		$sql .= ' LEFT JOIN  ' . $this->db->prefix() . $object->table_element . '_extrafields te ON te.fk_object=t.rowid ';
		$sql .= ' INNER JOIN  ' . $this->db->prefix() . 'product as p ON o.fk_product=p.rowid ';

		$sql .= ' WHERE 1=1';
		$sql .= ' AND t.status = 1';
		//      $sql .= " AND o.date_next < '" . $this->db->idate(dol_time_plus_duree(dol_now(), (int) getDolGlobalInt("THEO_NB_MONTH_CHECKING_VEHICULE_BY_ANTICIPATION"), 'm')) . "'";

		if (GETPOSTISSET('o_fk_product')) {
			$sql .= ' AND o.fk_product IN (' . implode(',', GETPOST('o_fk_product', 'array')) . ')';
		}

		$this->operation = $operation;
		$sql .= $this->getSqlSearchFilters($object, $search);
		$sql .= ' AND t.fk_soc = ' . $this->context->logged_thirdparty->id;
		// Count total nb of records
		if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
			$nbtotalofrecords = $this->getSqlNbTotalOfRecord($sql, $sqlfields, $offset, $this->page);
		}

		if (!$this->sortorder) {
			$this->sortorder = 'DESC';
		}

		if (!$this->sortfield) {
			$this->sortfield = 't.date_creation';
		}

		// Complete request and execute it with limit
		$sql .= $this->db->order($this->sortfield, $this->sortorder);
		if ($this->limit) {
			$sql .= $this->db->plimit($this->limit, $offset);
		}
		return $sql;
	}

	public function getSqlSearchFilters($object, $search)
	{
		$sql = '';
		foreach ($search as $key => $val) {
			if (empty($val)) {
				continue;
			}
			if ($key == 'fk_product') {
				$sql .= ' AND p.label LIKE "%' . $val . '%"';
				continue;
			}
			$prefix = $this->getPrefix($key);
			if (array_key_exists($key, $object->fields)) {
				if (($key == 'status') && $val == -1) {
					continue;
				}
				if ($key == 'status' && !empty($val)) {
					$sql .= " AND status_or.code IN ('" . $val . "')";
					continue;
				}

				if ($key == 'or_next' && !empty($val) && $val > 0) {
					$sql .= ' AND o.or_next > 0 AND o.or_next IS NOT NULL';
				}

				$mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
				if (
					strpos($object->fields[$key]['type'], 'integer:') === 0
					|| strpos($object->fields[$key]['type'], 'sellist:') === 0
					|| !empty($object->fields[$key]['arrayofkeyval'])
				) {
					if (
						$val == "-1"
						|| (
							$val === '0'
							&& (
								empty($object->fields[$key]['arrayofkeyval'])
								|| !array_key_exists('0', $object->fields[$key]['arrayofkeyval'])
							)
						)
					) {
						$val = '';
					}
					$mode_search = 2;
				}

				$sql .= natural_search($prefix . $this->db->escape($key), $val, (($key == 'status') ? 2 : $mode_search));
			} elseif (array_key_exists($key, $this->operation->fields)) {
				$mode_search = (($object->isInt($this->operation->fields[$key]) || $object->isFloat($this->operation->fields[$key])) ? 1 : 0);
				if (
					strpos($this->operation->fields[$key]['type'], 'integer:') === 0
					|| strpos($this->operation->fields[$key]['type'], 'sellist:') === 0
					|| !empty($this->operation->fields[$key]['arrayofkeyval'])
				) {
					if (
						$val == "-1"
						|| (
							$val === '0'
							&& (
								empty($this->operation->fields[$key]['arrayofkeyval'])
								|| !array_key_exists('0', $this->operation->fields[$key]['arrayofkeyval'])
							)
						)
					) {
						$val = '';
					}
					$mode_search = 2;
				}

				$sql .= natural_search('o.' . $this->db->escape($key), $val, (($key == 'status') ? 2 : $mode_search));
			} elseif (preg_match('/(_dtstart|_dtend)$/', $key) && $val != '') {
				$columnName = preg_replace('/(_dtstart|_dtend)$/', '', $key);
				if (array_key_exists($columnName, $object->fields)) {
					$type = $object->fields[$columnName]['type'];
					$prefix = 't.';
				} else {
					$type = $this->operation->fields[$columnName]['type'];
					$prefix = 'o.';
				}

				if (preg_match('/^(date|timestamp|datetime)/', $type)) {
					if (preg_match('/_dtstart$/', $key)) {
						$sql .= " AND " . $prefix . $this->db->escape($columnName) . " >= '" . $this->db->idate($val) . "'";
					}
					if (preg_match('/_dtend$/', $key)) {
						$sql .= " AND " . $prefix . $this->db->escape($columnName) . " <= '" . $this->db->idate($val) . "'";
					}
				}
			}
		}
		return $sql;
	}
}
