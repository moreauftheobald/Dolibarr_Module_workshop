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
dol_include_once('/operationorder/class/html.formlistwebportal.class.parent.php');
dol_include_once('/operationorder/class/operationorderstatus.class.php');

/**
 *    Class to manage generation of HTML components
 *    Only common components for WebPortal must be here.
 *
 */
class VSRFormListWebPortal extends ParentFormListWebPortal
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
		$this->findVehicule($context);

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

		// Build and execute select
		// --------------------------------------------------------------------
		unset($object->fields['vin']);
		unset($object->fields['immatriculation']);
		unset($object->fields['fk_conducteur']);
		$resql = null;
		$nbtotalofrecords = 0;
		if (!empty($this->idVh)) {
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

		// table with search filters and column titles
		$html .= '
			<div id="global_search">
				<table>
					<tr>
						<td>' . $langs->trans('VIN') . '</td>
						<td><input id="vin" name="vin" value="' . $this->vin . '"></td>
					</tr>
					<tr>
						<td>' . $langs->trans('Immatriculation') . '</td>
						<td><input id="immatriculation" name="immatriculation" value="' . $this->immatriculation . '"></td>
					</tr>
				</table>
			<div>';
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
		$colspan = 9;

		if ($resql) {
			$imaxinloop = ($this->limit ? min($num, $this->limit) : $num);
			while ($i < $imaxinloop) {
				$obj = $this->db->fetch_object($resql);
				if (empty($obj)) {
					break; // Should not happen
				}

				// Store properties in $object
				$object->setVarsFromFetchObj($obj);

				$html .= $this->printLineVSR($obj, $object, $arrayfields, $context);
				$html .= $this->printSubLineVSR($object);

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

	public function printLineVSR($obj, $object, $arrayfields, $context)
	{
		// Show line of result
		$html = '<tr style="background:#ddddff;" data-rowid="' . $object->rowid . '">';
		$html .= '<td class="nowrap left"></td>';

		foreach ($object->fields as $key => $val) {
			$prefix = $this->getPrefix($key);
			if (!empty($arrayfields[$prefix . $key]['checked'])) {
				$html .= '<td>';
				if ($key == 'status') {
					$status = new OperationOrderStatus($this->db);
					$res = $status->fetchDefault($obj->{$key}, 0);
					if ($res > 0) {
						$html .= $status->getBadge();
					} else {
						$html .= $status->getStaticNomUrl($obj->{$key});
					}
				} elseif ($key == 'rowid') {
					$html .=  $object->showOutputField($val, $key, $obj->id, '');
				} elseif ($key == 'ref') {
					$urlOp = $context->getControllerUrl('operationordercard', ['op_id' => $object->id]);
					$html .= '<a href="' . $urlOp . '">' .$object->ref . '</a>';
				} else {
					$html .= $object->showOutputField($val, $key, $obj->$key);
				}
				$html .= '</td>';
			}
		}

		$html .= '</tr>';
		$object->fetchLines();

		if (empty($object->lines)) {
			return;
		}

		return $html;
	}


	public function printSubLineVSR($object)
	{
		global $langs;
		$html = '';
		$TLineQtyUsed = null;
		$TLastLinesByProduct = null;

		foreach ($object->lines as $line) {
			// QTY ORDERED
			//Repris d'interface manager
			$qtyUsed = $line->getQtyUsed($TLineQtyUsed, $TLastLinesByProduct);
			if ($qtyUsed > $line->qty) {
				$textClass = "text-danger paddingrightonly";
				$iconInfo = '<i class="fa fa-caret-up"></i>';
			} elseif ($qtyUsed < 0) {
				$textClass = "text-danger paddingrightonly";
				$iconInfo = '<i class="fa fa-bolt"></i>';
			} else {
				$textClass = "";
				$iconInfo = "";
			}
			$outQty = '
				<div class="operation-order-sortable-list__item__title__col -qty-ordered">';
			if (!empty($qtyUsed)) {
				$outQty .= '<span class="' . $textClass . 'classfortooltip" title="' . $langs->trans("QtyUsed") . '" >
					' . $iconInfo . '<i class="fas fa-box-open"></i>' . $qtyUsed . '</span> / ';
			}
			if (empty($line->product->array_options['options_or_is_job'])) {
				$outQty .= '<span class=" classfortooltip" title="' . $langs->trans("QtyPlanned") . '" ><i class="fas fa-box-open"></i>' . $line->qty . '</span>';
			} else {
				$outQty .= '<span class=" classfortooltip" title="' . $langs->trans($line->fields['fk_c_operationorder_type']['label']) . '" >' . $line->showOutputField($line->fields['fk_c_operationorder_type'], 'fk_c_operationorder_type', $line->fk_c_operationorder_type) . '</span>';
			}
			$outQty .= '</div>';
			$productRef = $line->product->ref;
			$html .= '
				<tr class="oddeven">
					<td colspan="2"></td>
					<td>
						' .$productRef . '
					</td>
					<td>
						' . $outQty . '
					</td>
					<td>
						' . $line->label . '
					</td>
					<td colspan="4">
						' . dolPrintText($line->description) . '
					</td>
				</tr>';
		}
		return $html;
	}

	private function findVehicule($context)
	{
		global $langs;

		$vin = GETPOST('vin', 'alpha');
		$idVh = GETPOST('idVh');
		$immatriculation = GETPOST('immatriculation', 'alpha');
		$pattern = '/^([A-Za-z]{2})[- ]?([0-9]{3})[- ]?([A-Za-z]{2})$/';
		$immatriculationArray = [];
		if (
			!empty($vin)
			|| (!empty($immatriculation) && preg_match($pattern, $immatriculation, $immatriculationArray) == 1)
		) {
			unset($immatriculationArray[0]);
			$immatriculationWithUnderscore = implode('-', $immatriculationArray);
			$immatriculationWithoutUnderscore = implode('', $immatriculationArray);
			$sql = '
				SELECT rowid, vin, immatriculation
				FROM ' . $this->db->prefix() . 'dolifleet_vehicule 
				WHERE vin LIKE "%' . $this->db->escape($vin) . '%"
					AND (
						immatriculation LIKE "%' . $this->db->escape($immatriculationWithUnderscore) . '%"
						OR immatriculation LIKE "%' . $this->db->escape($immatriculationWithoutUnderscore) . '%"
					)
					AND fk_soc="' . intval($context->logged_thirdparty->id) . '"
				LIMIT 1';

			$resql = $this->db->query($sql);

			if (!$resql) {
				$context->setEventMessage($this->db->lasterror(), "errors");
				$this->idVh = 0;
			} else {
				$obj = $this->db->fetch_object($resql);
				if (empty($obj)) {
					$context->setEventMessage($langs->trans('VehiculeNotFound'), "errors");
				} else {
					$this->idVh = intval($obj->rowid);
					$this->vin = strval($obj->vin);
					$this->immatriculation = strval($obj->immatriculation);
				}
			}
		} elseif (!empty($idVh)) {
			$vehicule = new Vehicule($this->db);
			$retFetch = $vehicule->fetch($idVh);
			if ($retFetch < 0) {
				$context->setEventMessages($vehicule->error, $vehicule->errors, 'errors');
			} else {
				$this->idVh = $vehicule->id;
				$this->vin = $vehicule->vin;
				$this->immatriculation = $vehicule->immatriculation;
			}
		}
	}

	private function buildSQL($object, $search, $offset, &$nbtotalofrecords)
	{
		$sql = "SELECT ";
		$sql .= $object->getFieldList('t');

		$sqlfields = $sql; // $sql fields to remove for count total

		$sql .= " FROM " . $this->db->prefix() . $object->table_element . " as t";
		$sql .= " LEFT JOIN ".$this->db->prefix()."operationorder_status as status_or ON status_or.rowid = t.status";
		$sql .= " WHERE 1 = 1";


		$sql .= ' AND t.fk_vehicule="' . $this->db->escape($this->idVh) .  '" ';
		$sql .= $this->getSqlSearchFilters($object, $search);
		// Count total nb of records
		if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
			$nbtotalofrecords = $this->getSqlNbTotalOfRecord($sql, $sqlfields, $offset, $this->page);
		}

		if (!$this->sortorder) {
			$this->sortorder = 'DESC';
		}

		if (!$this->sortfield) {
			$this->sortfield = 'date_creation';
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
			$prefix = $this->getPrefix($key);
			if (array_key_exists($key, $object->fields)) {
				if (($key == 'status') && $val == -1) {
					continue;
				}
				if ($key == 'status' && !empty($val)) {
					$sql .= " AND status_or.code IN ('" . $val . "')";
					continue;
				}

				$mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
				if (
					strpos($object->fields[$key]['type'], 'integer:') === 0 || strpos($object->fields[$key]['type'],
						'sellist:') === 0 || !empty($object->fields[$key]['arrayofkeyval'])
				) {
					if (
						$val == "-1" || (
						$val === '0' && (
						empty($object->fields[$key]['arrayofkeyval']) || !array_key_exists('0',
							$object->fields[$key]['arrayofkeyval'])
						)
						)
					) {
						$val = '';
					}
					$mode_search = 2;
				}

				$sql .= natural_search($prefix . $this->db->escape($key), $val, (($key == 'status') ? 2 : $mode_search));
			} elseif (preg_match('/(_dtstart|_dtend)$/', $key) && $val != '') {
				$columnName = preg_replace('/(_dtstart|_dtend)$/', '', $key);
				if (preg_match('/^(date|timestamp|datetime)/', $object->fields[$columnName]['type'])) {
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
