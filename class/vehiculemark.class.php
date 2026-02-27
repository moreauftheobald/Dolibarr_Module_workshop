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
 * \file        class/vehiculemark.class.php
 * \ingroup     workshop
 * \brief       Dictionary class for VehiculeMark (brands)
 */

dol_include_once('/workshop/class/dictionary.class.php');

class VehiculeMark extends dictionary
{
	/** @var string $element Element name */
	public $element = 'vehiculemark';

	/** @var string $table_element Table name without prefix */
	public $table_element = 'workshop_vehicule_c_vehicule_mark';

	/** @var string $picto Picto */
	public $picto = 'fa-industry';
}
