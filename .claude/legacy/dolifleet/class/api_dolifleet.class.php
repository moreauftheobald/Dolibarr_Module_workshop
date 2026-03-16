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
 * API class for Vehicules
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class DolifleetApi extends DolibarrApi
{

	/**
	 * @var Vehicule $vehicule
	 */
	public $vehicule;

	/**
	 * @var VehiculeOperation $vehiculeOperation
	 */
	public $vehiculeOperation;

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

		require_once __DIR__ . '/vehicule.class.php';
		require_once __DIR__ . '/vehiculeOperation.class.php';

		$this->vehicule = new Vehicule($this->db);
		$this->vehiculeOperation = new dolifleetVehiculeOperation($this->db);
	}

	/**
	 * Get properties of an vehicule object by id
	 *
	 * Return an array with vehicule informations
	 *
	 * @param       int         $id            ID of order
	 * @return 	array|mixed data without useless information
	 *
	 * @url GET    vehicule/{id}
	 * @throws 	RestException
	 */
	public function getVehicule($id)
	{
		return $this->_fetch($id, '', '');
	}

	/**
	 * Get properties of an vehicule object by ref
	 *
	 * Return an array with vehicule informations
	 *
	 * @param       string		$ref			Ref of object
	 * @return 	array|mixed data without useless information
	 *
	 * @url GET     vehicule/ref/{ref}
	 *
	 * @throws 	RestException
	 */
	public function getVehiculeByRef($ref)
	{
		return $this->_fetch('', $ref);
	}

	/**
	 * List Vehicules
	 *
	 * Get a list of Vehicules
	 *
	 * @param string	       $sortfield	        Sort field
	 * @param string	       $sortorder	        Sort order
	 * @param int		       $limit		        Limit for list
	 * @param int		       $page		        Page number
	 * @param string           $entities            Entities ids to filter vehicules of (example '1' or '1,2,3', '' for all)
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @return  array                               Array of order objects
	 *
	 * @url GET    vehicules
	 *
	 * @throws RestException 404 Not found
	 * @throws RestException 503 Error
	 */
	public function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $entities = '', $sqlfilters = '')
	{
		global $user;

		if (!DolibarrApiAccess::$user->hasRight("dolifleet", "read")) {
			throw new RestException(401);
		}

		$obj_ret = array();

		$sql = "SELECT t.rowid";

		$sql .= " FROM " . $this->db->prefix() . "dolifleet_vehicule as t";

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
				$vehiculeStatic = new Vehicule($this->db);

				if ($vehiculeStatic->fetch($obj->rowid, true)) {
					$obj_ret[] = $this->_cleanObjectDatas($vehiculeStatic);
				}

				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieve vehicule list : ' . $this->db->lasterror());
		}

		if (!count($obj_ret)) {
			throw new RestException(404, 'No vehicule found');
		}

		return $obj_ret;
	}

	/**
	 * Get properties of an vehicule Operation object by id
	 *
	 * Return an array with vehicule Operation informations
	 *
	 * @param       int         $id            ID of order
	 * @return 	array|mixed data without useless information
	 *
	 * @url GET    vehiculeoperation/{id}
	 * @throws 	RestException
	 */
	public function getVehiculeOperation($id)
	{
		if (!DolibarrApiAccess::$user->hasRight("dolifleet", "read")) {
			throw new RestException(401);
		}


		$result = $this->vehiculeOperation->fetch($id, true);

		if (!$result) {
			throw new RestException(404, 'Vehicule Operation not found');
		}

		return $this->_cleanObjectDatas($this->vehiculeOperation);
	}

	/**
	 * List Vehicule Operations
	 *
	 * Get a list of Vehicule Operations
	 *
	 * @param string	       $sortfield	        Sort field
	 * @param string	       $sortorder	        Sort order
	 * @param int		       $limit		        Limit for list
	 * @param int		       $page		        Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @return  array                               Array of order objects
	 *
	 * @url GET    vehiculeoperations Operations
	 *
	 * @throws RestException 404 Not found
	 * @throws RestException 503 Error
	 */
	public function indexOperation($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
	{
		global $user;

		if (!DolibarrApiAccess::$user->hasRight("dolifleet", "read")) {
			throw new RestException(401);
		}

		$obj_ret = array();

		$sql = "SELECT t.rowid";

		$sql .= " FROM " . $this->db->prefix() . "dolifleet_vehicule_operation as t";

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
				$vehiculeOperationStatic = new doliFleetVehiculeOperation($this->db);

				if ($vehiculeOperationStatic->fetch($obj->rowid, true)) {
					$obj_ret[] = $this->_cleanObjectDatas($vehiculeOperationStatic);
				}

				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieve vehicule operation list : ' . $this->db->lasterror());
		}

		if (!count($obj_ret)) {
			throw new RestException(404, 'No vehicule operation found');
		}

		return $obj_ret;
	}

	/**
	 * Get properties of an vehicule object
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

		if (!DolibarrApiAccess::$user->hasRight("dolifleet", "read")) {
			throw new RestException(401);
		}


		$result = $this->vehicule->fetch($id, true, $ref);

		if (!$result) {
			throw new RestException(404, 'Operation Order not found');
		}

		$this->vehicule->fetchObjectLinked();

		return $this->_cleanObjectDatas($this->vehicule);
	}
}
