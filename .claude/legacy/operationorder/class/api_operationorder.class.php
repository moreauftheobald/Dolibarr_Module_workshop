<?php

/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2016	Laurent Destailleur		<eldy@users.sourceforge.net>
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

dol_include_once('/api/class/api.class.php');

use Luracast\Restler\RestException;

/**
 * API class for Operationorders
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class OperationorderApi extends DolibarrApi
{

	/**
	 * @var OperationOrder $operationOrder
	 */
	public $operationOrder;

	/**
	 * @var OperationOrderTaskTime $operationOrderTaskTime
	 */
	public $operationOrderTaskTime;

	/**
	 * Contructor
	 *
	 *
	 * @throws RestException 500 Internal error
	 */
	public function __construct()
	{
		global $db;
		$this->db = &$db;

		require_once __DIR__ . '/operationorder.class.php';
		require_once __DIR__ . '/operationordertasktime.class.php';

		$this->operationOrder = new OperationOrder($this->db);
		$this->operationOrderTaskTime = new OperationOrderTaskTime($this->db);
	}

	/**
	 * Get properties of an operation order object by id
	 *
	 * Return an array with operation order informations
	 *
	 * @param       int         $id            ID of order
	 * @return 	array|mixed data without useless information
	 *
	 * @url GET    operationorder/{id}
	 * @throws 	RestException
	 */
	public function getOR($id)
	{
		return $this->_fetch($id, '', '');
	}

	/**
	 * Get properties of an operation order object by ref
	 *
	 * Return an array with operation order informations
	 *
	 * @param       string		$ref			Ref of object
	 * @return 	array|mixed data without useless information
	 *
	 * @url GET     operationorder/ref/{ref}
	 *
	 * @throws 	RestException
	 */
	public function getORByRef($ref)
	{
		return $this->_fetch('', $ref);
	}

	/**
	 * Get lines of an operation order
	 *
	 * @param int   $id             Id of order
	 *
	 * @url	GET  operationorder/{id}/lines
	 *
	 * @return int
	 */
	public function getORLines($id)
	{
		if (!DolibarrApiAccess::$user->hasRight("operationorder", "read")) {
			throw new RestException(401);
		}

		$result = $this->operationOrder->fetch($id, true);

		if (!$result) {
			throw new RestException(404, 'Operation Order not found');
		}

		$this->operationOrder->fetchLines();

		$result = array();

		foreach ($this->operationOrder->lines as $line) {
			array_push($result, $this->_cleanObjectDatas($line));
		}

		return $result;
	}

	/**
	 * Get Operation Order Task Time
	 *
	 * @param int $id Id of Operation Order Task Time
	 *
	 * @url	GET  operationordertasktime/{id}
	 *
	 * @return int
	 */
	public function getORTaskTime($id)
	{
		if (!DolibarrApiAccess::$user->hasRight("operationorder", "read")) {
			throw new RestException(401);
		}

		$result = $this->operationOrderTaskTime->fetch($id, true);

		if (!$result) {
			throw new RestException(404, 'Operation Order not found');
		}

		return $this->_cleanObjectDatas($this->operationOrderTaskTime);
	}

	/**
	 * List operationOrders
	 *
	 * Get a list of Operation Orders
	 *
	 * @param string	       $sortfield	        Sort field
	 * @param string	       $sortorder	        Sort order
	 * @param int		       $limit		        Limit for list
	 * @param int		       $page		        Page number
	 * @param string           $entities            Entities ids to filter operation orders of (example '1' or '1,2,3', '' for all)
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @return  array                               Array of order objects
	 *
	 * @url GET    operationorders
	 *
	 * @throws RestException 404 Not found
	 * @throws RestException 503 Error
	 */
	public function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $entities = '', $sqlfilters = '')
	{
		global $user;

		if (!DolibarrApiAccess::$user->hasRight("operationorder", "read")) {
			throw new RestException(401);
		}

		$obj_ret = array();

		$sql = "SELECT t.rowid";

		$sql .= " FROM " . $this->db->prefix() . "operationorder as t";

		$sql .= ' WHERE 1=1';

		if (!empty($entities)) {
			$sql .= ' AND t.entity IN (' . $entities . ')';
		}

		// Add sql filters
		if ($sqlfilters) {
			if (!DolibarrApi::_checkFilters($sqlfilters)) {
				throw new RestException(503, 'Error when validating parameter sqlfilters ' . $sqlfilters);
			}

			$regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^\(\)]+)\)';
			$sql .= " AND (" . preg_replace_callback(
							'/' . $regexstring . '/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters) . ")";
		}

		$sql .= $this->db->order($sortfield, $sortorder);

		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		dol_syslog("API Rest request");
		$result = $this->db->query($sql);

		if ($result) {
			$num = $this->db->num_rows($result);
			$min = min($num, ($limit <= 0 ? $num : $limit));
			$i = 0;

			while ($i < $min) {
				$obj = $this->db->fetch_object($result);
				$operationOrderStatic = new OperationOrder($this->db);

				if ($operationOrderStatic->fetch($obj->rowid, true)) {
					// Add external contacts ids
					$obj_ret[] = $this->_cleanObjectDatas($operationOrderStatic);
				}

				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieve operation order list : ' . $this->db->lasterror());
		}

		if (!count($obj_ret)) {
			throw new RestException(404, 'No operation order found');
		}

		return $obj_ret;
	}

	/**
	 * List operation orders  task time
	 *
	 * Get a list of operation order task times
	 *
	 * @param string	       $sortfield	        Sort field
	 * @param string	       $sortorder	        Sort order
	 * @param int		       $limit		        Limit for list
	 * @param int		       $page		        Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @return  array                               Array of order objects
	 *
	 * @url GET    operationordertasktimes
	 *
	 * @throws RestException 404 Not found
	 * @throws RestException 503 Error
	 */
	public function indexTaskTime($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
	{
		global $user;

		if (!DolibarrApiAccess::$user->hasRight("operationorder", "read")) {
			throw new RestException(401);
		}

		$obj_ret = array();

		$sql = "SELECT t.rowid";

		$sql .= " FROM " . $this->db->prefix() . "operationordertasktime as t";

		$sql .= ' WHERE 1=1';

		// Add sql filters
		if ($sqlfilters) {
			if (!DolibarrApi::_checkFilters($sqlfilters)) {
				throw new RestException(503, 'Error when validating parameter sqlfilters ' . $sqlfilters);
			}

			$regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^\(\)]+)\)';
			$sql .= " AND (" . preg_replace_callback(
							'/' . $regexstring . '/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters) . ")";
		}

		$sql .= $this->db->order($sortfield, $sortorder);

		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}

			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		dol_syslog("API Rest request");
		$result = $this->db->query($sql);

		if ($result) {
			$num = $this->db->num_rows($result);
			$min = min($num, ($limit <= 0 ? $num : $limit));
			$i = 0;

			while ($i < $min) {
				$obj = $this->db->fetch_object($result);
				$operationOrderTaskTimeStatic = new OperationOrderTaskTime($this->db);

				if ($operationOrderTaskTimeStatic->fetch($obj->rowid, true)) {
					$obj_ret[] = $this->_cleanObjectDatas($operationOrderTaskTimeStatic);
				}

				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieve operation order task time list : ' . $this->db->lasterror());
		}

		if (!count($obj_ret)) {
			throw new RestException(404, 'No operation order task times  found');
		}

		return $obj_ret;
	}

	/**
	 * Get properties of an operation order object
	 *
	 * Return an array with order informations
	 *
	 * @param       int         $id            ID of order
	 * @param		string		$ref			Ref of object
	 * @return 	array|mixed data without useless information
	 *
	 * @throws 	RestException
	 */
	private function _fetch($id, $ref = '')
	{
		global $user;

		if (!DolibarrApiAccess::$user->hasRight("operationorder", "read")) {
			throw new RestException(401);
		}


		$result = $this->operationOrder->fetch($id, true, $ref);

		if (!$result) {
			throw new RestException(404, 'Operation Order not found');
		}

		$this->operationOrder->fetchObjectLinked();

		return $this->_cleanObjectDatas($this->operationOrder);
	}
}
