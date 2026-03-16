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
 * \file        class/servicetype.class.php
 * \ingroup     workshop
 * \brief       Dictionary class for ServiceType (MO, Pieces, Forfaits…)
 */

dol_include_once('/workshop/class/dictionary.class.php');

/**
 * Class for ServiceType
 */
class ServiceType extends dictionary
{
	/** @var string ID to identify managed object */
	public $element = 'servicetype';

	/** @var string Name of table without prefix */
	public $table_element = 'workshop_c_servicetype';

	/** @var string Picto */
	public $picto = 'fa-file';

	/** @var int $group_type Service group type (0=MO, 1=Piece, 2=Divers, 3=Forfait, 4=Ext) */
	public $group_type;

	/** @var float $prix_mo Hourly rate HT/h */
	public $prix_mo;

	/**
	 * Override $fields to match the actual table schema.
	 * workshop_c_servicetype has no 'entity' column →
	 * ismultientitymanaged is set to 0 in the constructor.
	 */
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
			'type'           => 'varchar(20)',
			'length'         => 20,
			'label'          => 'Code',
			'enabled'        => 1,
			'visible'        => 1,
			'notnull'        => 0,
			'index'          => 1,
			'position'       => 10,
			'showoncombobox' => 1,
		),
		'active' => array(
			'type'          => 'integer',
			'label'         => 'Active',
			'enabled'       => 1,
			'visible'       => 0,
			'notnull'       => 1,
			'default'       => 1,
			'index'         => 1,
			'position'      => 20,
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
			'position'       => 30,
			'searchall'      => 1,
			'alwayseditable' => 1,
			'css'            => 'minwidth300',
			'cssview'        => 'wordbreak',
			'csslist'        => 'tdoverflowmax150',
			'showoncombobox' => 0,
		),
		'group_type' => array(
			'type'    => 'select',
			'label'   => 'GroupType',
			'enabled' => 1,
			'visible' => 1,
			'notnull' => 0,
			'position' => 40,
			'options' => array(0 => 'MO', 1 => 'Piece', 2 => 'Divers', 3 => 'Forfait', 4 => 'Ext'),
			'css'     => 'maxwidth500 widthcentpercentminusxx',
		),
		'prix_mo' => array(
			'type'     => 'double',
			'label'    => 'PrixMO',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 50,
			'help'     => 'TauxHoraireHTParH',
			'css'      => 'maxwidth100',
			'default'  => 0,
		),
	);

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		// No entity column in workshop_c_servicetype → disable multi-entity
		$this->ismultientitymanaged = 0;
		$this->isextrafieldmanaged  = 0;

		parent::__construct($db);

		// Translate arrayofkeyval entries
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Return a link to the object card (with optionally the picto)
	 *
	 * @param  int    $withpicto             Include picto (0=No, 1=Yes, 2=Only picto)
	 * @param  string $option                Link target option ('nolink', …)
	 * @param  int    $notooltip             1=Disable tooltip
	 * @param  string $morecss               Additional CSS classes
	 * @param  int    $save_lastsearch_value -1=Auto, 0=No, 1=Yes
	 * @return string                        HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		return dol_escape_htmltag($this->label ?: $this->code);
	}
}
