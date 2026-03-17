<?php
/* Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
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
 * \file        class/vehiculemaintenanceoperation.class.php
 * \ingroup     workshop
 * \brief       Dictionnaire des opérations de maintenance véhicule.
 *              Chaque opération peut être restreinte à un type de véhicule
 *              et/ou une marque spécifique.
 */

dol_include_once('/workshop/class/dictionary.class.php');

/**
 * Class VehiculeMaintenanceOperation — opérations de maintenance paramétrables
 */
class VehiculeMaintenanceOperation extends dictionary
{
	/** @var string $element Element name */
	public $element = 'vehiculemaintenanceoperation';

	/** @var string $table_element Table name without prefix */
	public $table_element = 'workshop_vehicule_c_maintenance_operation';

	/** @var string $picto Picto */
	public $picto = 'fa-tools';

	/** @var int|null $fk_vehicule_type Restriction à un type de véhicule (null = tous types) */
	public $fk_vehicule_type;

	/** @var int|null $fk_vehicule_mark Restriction à une marque (null = toutes marques) */
	public $fk_vehicule_mark;

	public $fields = array(
		'rowid' => array(
			'type'     => 'integer',
			'label'    => 'TechnicalID',
			'enabled'  => 1,
			'visible'  => 0,
			'notnull'  => 1,
			'position' => 1,
			'index'    => 1,
		),
		'code' => array(
			'type'     => 'varchar(20)',
			'length'   => 20,
			'label'    => 'Code',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 1,
			'index'    => 1,
			'position' => 10,
		),
		'entity' => array(
			'type'     => 'integer',
			'label'    => 'Entity',
			'enabled'  => 1,
			'visible'  => 0,
			'default'  => 1,
			'notnull'  => 1,
			'index'    => 1,
			'position' => 20,
		),
		'active' => array(
			'type'          => 'integer',
			'label'         => 'Active',
			'enabled'       => 1,
			'visible'       => 0,
			'notnull'       => 1,
			'default'       => 1,
			'index'         => 1,
			'position'      => 30,
			'arrayofkeyval' => array(
				0 => 'Disabled',
				1 => 'Active',
			),
		),
		'label' => array(
			'type'           => 'varchar(255)',
			'label'          => 'Label',
			'enabled'        => 1,
			'visible'        => 1,
			'position'       => 40,
			'searchall'      => 1,
			'css'            => 'minwidth200',
			'showoncombobox' => 1,
		),
		'fk_vehicule_type' => array(
			'type'     => 'integer:VehiculeType:workshop/class/vehiculetype.class.php',
			'label'    => 'VehiculeType',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 50,
		),
		'fk_vehicule_mark' => array(
			'type'     => 'integer:VehiculeMark:workshop/class/vehiculemark.class.php',
			'label'    => 'VehiculeMarkId',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 60,
		),
	);
}
