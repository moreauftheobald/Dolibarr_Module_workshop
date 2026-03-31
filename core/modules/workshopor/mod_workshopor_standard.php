<?php
/* Copyright (C) 2024 SuperAdmin <test@test.com>
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
 * \file    workshop/core/modules/workshopor/mod_workshopor_standard.php
 * \ingroup workshop
 * \brief   Standard numbering model for Workshop Ordres de Réparation
 */

dol_include_once('/workshop/core/modules/workshopor/modules_workshopor.php');


/**
 * Class to manage the Standard numbering rule for Workshop OR
 */
class mod_workshopor_standard extends ModeleNumRefWorkshopOR
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	public $prefix = 'OR';

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string name
	 */
	public $name = 'standard';


	/**
	 * Return description of numbering module
	 *
	 * @param  Translate $langs Translate Object
	 * @return string           Text with description
	 */
	public function info($langs)
	{
		return $langs->trans("SimpleNumRefModelDesc", $this->prefix);
	}


	/**
	 * Return an example of numbering
	 *
	 * @return string  Example
	 */
	public function getExample()
	{
		return $this->prefix."2501-0001";
	}


	/**
	 * Checks if the numbers already in the database do not
	 * cause conflicts that would prevent this numbering working.
	 *
	 * @param  Object  $object  Object we need next value for
	 * @return boolean          false if conflict, true if ok
	 */
	public function canBeActivated($object)
	{
		global $conf, $langs, $db;

		$coyymm = '';
		$max = '';

		$posindice = strlen($this->prefix) + 6;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
		$sql .= " FROM ".MAIN_DB_PREFIX."workshop_operationorder";
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefix)."____-%'";
		if ($object->ismultientitymanaged == 1) {
			$sql .= " AND entity = ".$conf->entity;
		}

		$resql = $db->query($sql);
		if ($resql) {
			$row = $db->fetch_row($resql);
			if ($row) {
				$coyymm = substr($row[0], 0, strlen($this->prefix) + 4);
				$max = $row[0];
			}
		}
		if ($coyymm && !preg_match('/'.$this->prefix.'[0-9][0-9][0-9][0-9]/i', $coyymm)) {
			$langs->load("errors");
			$this->error = $langs->trans('ErrorNumRefModel', $max);
			return false;
		}

		return true;
	}


	/**
	 * Return next free value
	 *
	 * @param  Object      $object  Object we need next value for
	 * @return string|-1            Next free value if OK, -1 if KO
	 */
	public function getNextValue($object)
	{
		global $db, $conf;

		$posindice = strlen($this->prefix) + 6;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
		$sql .= " FROM ".MAIN_DB_PREFIX."workshop_operationorder";
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefix)."____-%'";
		if ($object->ismultientitymanaged == 1) {
			$sql .= " AND entity = ".$conf->entity;
		}

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			$max = $obj ? intval($obj->max) : 0;
		} else {
			dol_syslog("mod_workshopor_standard::getNextValue", LOG_DEBUG);
			return -1;
		}

		$date = !empty($object->date_creation) ? $object->date_creation : dol_now();
		$yymm = dol_print_date($date, "%y%m");

		$num = ($max >= (pow(10, 4) - 1)) ? $max + 1 : sprintf("%04s", $max + 1);

		// Verify the candidate doesn't already exist.
		// The broad LIKE 'OR____-%' can miss records with non-standard refs (PROV, migration…),
		// causing a false low MAX and a collision. If a collision is detected, recompute
		// on the exact current YYMM to find the real maximum.
		$candidate = $this->prefix.$yymm.'-'.$num;
		$sqlCheck  = "SELECT rowid FROM ".MAIN_DB_PREFIX."workshop_operationorder WHERE ref = '".$db->escape($candidate)."'";
		if ($object->ismultientitymanaged == 1) {
			$sqlCheck .= " AND entity = ".(int) $conf->entity;
		}
		$resCheck = $db->query($sqlCheck);
		if ($resCheck && $db->num_rows($resCheck) > 0) {
			$sqlRetry  = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
			$sqlRetry .= " FROM ".MAIN_DB_PREFIX."workshop_operationorder";
			$sqlRetry .= " WHERE ref LIKE '".$db->escape($this->prefix.$yymm)."-%'";
			if ($object->ismultientitymanaged == 1) {
				$sqlRetry .= " AND entity = ".(int) $conf->entity;
			}
			$resRetry = $db->query($sqlRetry);
			if ($resRetry) {
				$objRetry = $db->fetch_object($resRetry);
				$maxRetry = $objRetry ? intval($objRetry->max) : 0;
				$num      = ($maxRetry >= (pow(10, 4) - 1)) ? $maxRetry + 1 : sprintf("%04s", $maxRetry + 1);
			}
		}

		dol_syslog("mod_workshopor_standard::getNextValue return ".$this->prefix.$yymm."-".$num);
		return $this->prefix.$yymm."-".$num;
	}
}
