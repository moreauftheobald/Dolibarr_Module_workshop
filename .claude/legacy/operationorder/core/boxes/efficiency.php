<?php
/* Copyright (C) 2013 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *    \file       htdocs/core/boxes/box_graph_orders_permonth.php
 *    \ingroup    commandes
 *    \brief      Box to show graph of orders per month
 */
include_once DOL_DOCUMENT_ROOT . '/core/boxes/modules_boxes.php';


/**
 * Class to manage the box to show last orders
 */
class efficiency extends ModeleBoxes
{
	public $boxcode = "efficiency";
	public $boximg = "object_loperationorder@operationorder";
	public $boxlabel = "workshopefficiency";
	public $depends = array("operationorder");

	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	public $info_box_head = array();
	public $info_box_contents = array();


	/**
	 *  Constructor
	 *
	 * @param DoliDB $db Database handler
	 * @param string $param More parameters
	 */
	public function __construct($db, $param)
	{
		global $user, $conf;

		$this->db = $db;
		$this->enabled = isModEnabled("operationorder");         // Condition when module is enabled or not
		$this->hidden = !$user->hasRight("operationorder", "read");   // Condition when module is visible by user (test on permission)
	}

	/**
	 *  Load data into info_box_contents array to show array later.
	 *
	 * @param int $max Maximum number of records to load
	 * @return    void
	 */
	public function loadBox($max = 5)
	{
		global $conf, $langs;
		dol_include_once('/core/class/dolgraph.class.php');
		dol_include_once('/operationorder/lib/operationorder.lib.php');

		$this->max = $max;
		$text = $langs->trans("efficiencybox");

		$this->info_box_head = array(
			'text' => $text,
			'limit' => dol_strlen($text),
			'graph' => 1,
			'sublink' => '',
			'subtext' => $langs->trans("Filter"),
			'subpicto' => 'filter.png',
			'subclass' => 'linkobject boxfilter',
			'target' => 'none'    // Set '' to get target="_blank"
		);
		$dir = ''; // We don't need a path because image file will not be saved into disk

		$data1 = array(
			0 => array(0 => 'M-1', 1 => get_efficacite(0, 'lastmonth'), 2 => get_efficacite($conf->entity, 'lastmonth')),
			1 => array(0 => 'M', 1 => get_efficacite(0, 'month'), 2 => get_efficacite($conf->entity, 'month')),
			2 => array(0 => 'W-1', 1 => get_efficacite(0, 'lastweek'), 2 => get_efficacite($conf->entity, 'lastweek')),
			3 => array(0 => 'W', 1 => get_efficacite(0, 'week'), 2 => get_efficacite($conf->entity, 'week')),
			4 => array(0 => 'J-1', 1 => get_efficacite(0, 'yesterday'), 2 => get_efficacite($conf->entity, 'yesterday')),
			5 => array(0 => 'J', 1 => get_efficacite(0, 'today'), 2 => get_efficacite($conf->entity, 'today'))
		);
		$filenamenb = $dir . "/efficiency.png";
		// default value for customer mode
		$fileurlnb = DOL_URL_ROOT . '/viewimage.php?modulepart=operationorder&amp;file=efficiency.png';
		$WIDTH = '320';
		$HEIGHT = '192';

		$px1 = new DolGraph();
		$mesg = $px1->isGraphKo();
		if (!$mesg) {
			$px1->SetData($data1);
			unset($data1);

			$legend = array(0 => "Groupe", 1 => "Atelier");

			$px1->SetLegend($legend);
			$px1->SetMaxValue(100);
			$px1->SetWidth($WIDTH);
			$px1->SetHeight($HEIGHT);
			$px1->SetYLabel($langs->trans("NumberOfOrders"));
			$px1->SetShading(3);
			$px1->SetHorizTickIncrement(1);
			$px1->SetCssPrefix("cssboxes");
			$px1->mode = 'depth';
			$px1->SetTitle($langs->trans("workshopefficiency"));

			$px1->draw($filenamenb, $fileurlnb);
		}
		$this->info_box_contents[0][0] = array(
			'tr' => 'class="oddeven nohover"',
			'td' => 'class="nohover center"',
			'textnoformat' => $px1->show(),
		);
	}

	/**
	 *  Method to show box
	 *
	 * @param array $head Array with properties of box title
	 * @param array $contents Array with properties of box lines
	 * @param int $nooutput No print, only return string
	 * @return    string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
