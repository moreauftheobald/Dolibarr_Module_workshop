<?php
/* Copyright (C) 2003-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2019      Nicolas ZABOURI      <info@inovea-conseil.com>
 * Copyright (C) 2019       Frédéric France         <frederic.france@netlogic.fr>
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
 *	\file       htdocs/commande/index.php
 *	\ingroup    commande
 *	\brief      Home page of customer order module
 */

require 'config.php';
dol_include_once('/core/class/dolgraph.class.php');
dol_include_once('/operationorder/lib/operationorder.lib.php');
global $user,$db,$lang,$langs,$conf;
if (!$user->hasRight("operationorder", "read")) accessforbidden();

$hookmanager = new HookManager($db);

// Initialize technical object to manage hooks. Note that conf->hooks_modules contains array
$hookmanager->initHooks(array('operationorderindex'));

// Load translation files required by the page
$langs->loadLangs(array('opeartionorder@operationorder'));

/*
 * View
 */

llxHeader("", $langs->trans("operationorder"));


print load_fiche_titre($langs->trans("efficiency"), '', 'operationorder@operationorder');


print '<div class="fichecenter"><div class="fichethirdleft">';

/*
 * Statistics
 */

$dir = ''; // We don't need a path because image file will not be saved into disk

$data1=Array(
	0=>Array(0=>'M-1',1=>get_efficacite(0, 'lastmonth'),2=>get_efficacite($conf->entity, 'lastmonth')),
	1=>Array(0=>'M',1=>get_efficacite(0, 'month'),2=>get_efficacite($conf->entity, 'month')),
	2=>Array(0=>'W-1',1=>get_efficacite(0, 'lastweek'),2=>get_efficacite($conf->entity, 'lastweek')),
	3=>Array(0=>'W',1=>get_efficacite(0, 'week'),2=>get_efficacite($conf->entity, 'week')),
	4=>Array(0=>'J-1',1=>get_efficacite(0, 'yesterday'),2=>get_efficacite($conf->entity, 'yesterday')),
	5=>Array(0=>'J',1=>get_efficacite(0, 'today'),2=>get_efficacite($conf->entity, 'today'))
);
$filenamenb = $dir."/efficiency.png";
// default value for customer mode
$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=operationorder&amp;file=efficiency.png';
$WIDTH =  '600';
$HEIGHT = '400';

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (!$mesg) {
	$px1->SetData($data1);
	unset($data1);

	$legend = array(0=>"Groupe",1=>"Atelier");

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

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder nohover centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("efficiencybox").'</th></tr>'."\n";
print '<tr class="impair"><td align="center" colspan="2">';
print $px1->show();
print '</td></tr>';
print "</table></div><br>";


print '</div></div></div>';

$parameters = array('user' => $user);
$reshook = $hookmanager->executeHooks('dashboardOrders', $parameters, $object); // Note that $action and $object may have been modified by hook

// End of page
llxFooter();
$db->close();
