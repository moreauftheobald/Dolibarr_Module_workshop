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
 * \file    workshop/core/modules/workshopor/modules_workshopor.php
 * \ingroup workshop
 * \brief   Base classes for Workshop OR document generators and numbering models
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';


/**
 * Parent class for Workshop OR document (PDF) models
 */
abstract class ModelePDFWorkshopor extends CommonDocGenerator
{
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Return list of active generation modules
	 *
	 * @param  DoliDB  $db                 Database handler
	 * @param  integer $maxfilenamelength  Max length of value to show
	 * @return array                       List of templates
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		// phpcs:enable
		$type = 'workshopor';
		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		return getListOfModels($db, $type, $maxfilenamelength);
	}
}


/**
 * Parent class to manage numbering of Workshop OR
 */
abstract class ModeleNumRefWorkshopOR extends CommonNumRefGenerator
{
	// No overload code
}
