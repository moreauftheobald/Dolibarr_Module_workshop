<?php
/*
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

dol_include_once('/dolifleet/class/dictionary.class.php');

class dictionaryContractType extends dictionary
{
	/** @var string $table_element Table name in SQL */
	public $table_element = 'c_dolifleet_contract_type';

	/** @var string $element Name of the element */
	public $element = 'dolifleetContractType';
}
