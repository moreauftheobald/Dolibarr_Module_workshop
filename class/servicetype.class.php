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
 * \brief       Dictionnaire unifié des types de service (fusion ServiceType + JobType).
 *              Contient : code, libellé, tiers de facturation, taux MO, TVA MO, TVA ST, plannable.
 */

dol_include_once('/workshop/class/dictionary.class.php');

/**
 * Class ServiceType — dictionnaire unifié type de service atelier
 */
class ServiceType extends dictionary
{
	/** @var string ID to identify managed object */
	public $element = 'servicetype';

	/** @var string Name of table without prefix */
	public $table_element = 'workshop_c_servicetype';

	/** @var string Picto */
	public $picto = 'fa-file';

	/** @var int|null $fk_job_type FK legacy vers llx_workshop_job_type (conservé pour migration) */
	public $fk_job_type;

	/** @var float $prix_mo Taux horaire HT (€/h) */
	public $prix_mo;

	/** @var int|null $fk_soc Tiers de facturation par défaut pour ce type de service */
	public $fk_soc;

	/** @var int $plannable 1 = utilisable dans la planification des entretiens véhicule */
	public $plannable;

	/** @var float|null $tva_tx_mo Taux de TVA applicable sur la main d'œuvre */
	public $tva_tx_mo;

	/** @var float|null $tva_tx_st Taux de TVA applicable sur la sous-traitance */
	public $tva_tx_st;

	/** @var string|null $doc_obl Nom du document obligatoire (sans extension .pdf) */
	public $doc_obl;

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
		'fk_soc' => array(
			'type'     => 'integer:Societe:societe/class/societe.class.php',
			'label'    => 'BillingThirdParty',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 40,
			'css'      => 'maxwidth500 widthcentpercentminusxx',
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
		'tva_tx_mo' => array(
			'type'     => 'double',
			'label'    => 'TvaTxMO',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 55,
			'css'      => 'maxwidth100',
			'default'  => null,
		),
		'tva_tx_st' => array(
			'type'     => 'double',
			'label'    => 'TvaTxST',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 60,
			'css'      => 'maxwidth100',
			'default'  => null,
		),
		'plannable' => array(
			'type'          => 'integer',
			'label'         => 'JobTypePlannable',
			'enabled'       => 1,
			'visible'       => 1,
			'notnull'       => 1,
			'default'       => 0,
			'position'      => 65,
			'arrayofkeyval' => array(
				0 => 'No',
				1 => 'Yes',
			),
		),
		'doc_obl' => array(
			'type'     => 'varchar(255)',
			'label'    => 'WorkshopDocObl',
			'enabled'  => 1,
			'visible'  => 1,
			'notnull'  => 0,
			'position' => 70,
			'css'      => 'maxwidth300',
		),
		'fk_job_type' => array(
			'type'     => 'integer:WorkshopJobType:workshop/class/workshopjobtype.class.php',
			'label'    => 'JobTypeLegacy',
			'enabled'  => 1,
			'visible'  => 0, // caché — conservé uniquement pour migration
			'notnull'  => 0,
			'position' => 900,
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
	 * Return a label/link for this object
	 *
	 * @param  int    $withpicto Include picto
	 * @param  string $option    Link option
	 * @param  int    $notooltip 1=No tooltip
	 * @param  string $morecss   Extra CSS
	 * @param  int    $save_lastsearch_value -1=Auto
	 * @return string HTML
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		return dol_escape_htmltag($this->label ?: $this->code);
	}

	/**
	 * Retourne un tableau pour un select dropdown : rowid => label (+ taux)
	 *
	 * @return array<int,string>|int  array rowid => label, ou -1 si erreur
	 */
	public function getSelectList()
	{
		$sql  = 'SELECT rowid, label, prix_mo FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE active = 1 ORDER BY label ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$ret = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$suffix = $obj->prix_mo > 0 ? ' ('.price2num($obj->prix_mo, 2).' €/h)' : '';
			$ret[(int) $obj->rowid] = $obj->label.$suffix;
		}
		$this->db->free($resql);
		return $ret;
	}
}
