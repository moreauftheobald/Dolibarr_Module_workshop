<?php
/* Copyright (C) 2024 SuperAdmin <test@test.com>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    workshop/lib/workshop.lib.php
 * \ingroup workshop
 * \brief   Library files with common functions for Workshop
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function workshopAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabVehicules");
	$head[$h][2] = 'vehicules';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabOR");
	$head[$h][2] = 'ordres_reparation';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_divers.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabDivers");
	$head[$h][2] = 'divers';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_partage.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabPartageEntites");
	$head[$h][2] = 'partage_entites';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_objets_annexes.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabObjetsAnnexes");
	$head[$h][2] = 'objets_annexes';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshop@workshop');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshop@workshop', 'remove');

	return $head;
}


/**
 * Prepare sub-tabs header for the Objets Annexes admin page
 *
 * @return array
 */
function workshopObjetsAnnexesPrepareHead(): array
{
	global $langs, $conf;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_marque.php", 1);
	$head[$h][1] = $langs->trans("VhSetupMarque");
	$head[$h][2] = 'marque';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_type.php", 1);
	$head[$h][1] = $langs->trans("VhSetupType");
	$head[$h][2] = 'type';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_typect.php", 1);
	$head[$h][1] = $langs->trans("VhSetupTypeCt");
	$head[$h][2] = 'typect';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_pneu.php", 1);
	$head[$h][1] = $langs->trans("VhSetupPneu");
	$head[$h][2] = 'pneu';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/operationorder/param/operationorder_setup_service_type.php", 1);
	$head[$h][1] = $langs->trans("WorkshopSetupServiceType");
	$head[$h][2] = 'service_type';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopobjetsannexes@workshop');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopobjetsannexes@workshop', 'remove');

	return $head;
}


/**
 * Prepare sub-tabs header for the OR admin page (setup_or.php and related pages)
 *
 * @return array
 */
function workshopORAdminPrepareHead(): array
{
	global $langs;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=general';
	$head[$h][1] = $langs->trans('WorkshopORSubTabGeneral');
	$head[$h][2] = 'general';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=planning';
	$head[$h][1] = $langs->trans('WorkshopORSubTabPlanning');
	$head[$h][2] = 'planning';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=statuts';
	$head[$h][1] = $langs->trans('WorkshopORSubTabStatuts');
	$head[$h][2] = 'statuts';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=commandes';
	$head[$h][1] = $langs->trans('WorkshopORSubTabCommandes');
	$head[$h][2] = 'commandes';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=facturation';
	$head[$h][1] = $langs->trans('WorkshopORSubTabFacturation');
	$head[$h][2] = 'facturation';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=comptabilite';
	$head[$h][1] = $langs->trans('WorkshopORSubTabComptabilite');
	$head[$h][2] = 'comptabilite';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/operationorder_extrafields.php", 1);
	$head[$h][1] = $langs->trans('WorkshopORSubTabExtrafields');
	$head[$h][2] = 'extrafields';
	$h++;

	return $head;
}


/**
 * Prepare array of tabs for Vehicule Setup screen
 * @return    array                    Array of tabs
 */
function workshopSetupPrepareHead(): array
{
	global $langs, $conf;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/operationorder/param/operationorder_setup_service_type.php", 1);
	$head[$h][1] = $langs->trans("WorkshopSetupServiceType");
	$head[$h][2] = 'service_type';
	$h++;


	complete_head_from_modules($conf, $langs,null, $head, $h, 'workshopsetup@workshop');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopsetup@workshop', 'remove');

	return $head;
}
