<?php
/* Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
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
 * \file        core/modules/workshop/modules_workshop.php
 * \ingroup     workshop
 * \brief       Classe de base pour les modèles PDF des Ordres de Réparation
 *              et pour la numérotation des OR Workshop.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';


/**
 * Parent class for OR document (PDF) models
 */
abstract class ModelePDFWorkshop extends CommonDocGenerator
{
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Return list of active PDF generation modules
	 *
	 * @param  DoliDB  $db                 Database handler
	 * @param  int     $maxfilenamelength  Max length of value to show
	 * @return array                       List of templates
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		// phpcs:enable
		$type = 'workshop';
		$list = array();

		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		$list = getListOfModels($db, $type, $maxfilenamelength);

		return $list;
	}
}


/**
 * Parent class to manage numbering of Operationorder (OR)
 */
abstract class ModeleNumRefWorkshop extends CommonNumRefGenerator
{
	// No overload code
}
