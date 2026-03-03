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
 * \file    lib/workshop_operationorder.lib.php
 * \ingroup workshop
 * \brief   Library file with common functions for OperationOrder (OR)
 */

/**
 * Prepare array of tabs for WorkshopOperationOrderStatus card
 *
 * @param  WorkshopOperationOrderStatus $object Status object
 * @return array                                Array of tabs
 */
function workshopOperationOrderStatusPrepareHead($object)
{
	global $langs, $conf;

	$langs->load('workshop@workshop');

	$h    = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/workshop/operationorder/or_status_card.php', 1) . '?id=' . $object->id;
	$head[$h][1] = $langs->trans('WorkshopORStatus');
	$head[$h][2] = 'card';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'workshopoperationorderstatus@workshop');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'workshopoperationorderstatus@workshop', 'remove');

	return $head;
}

/**
 * Prepare array of tabs for Operationorder card
 *
 * @param  Operationorder $object Operationorder object
 * @return array                  Array of tabs
 */
function operationorderPrepareHead($object)
{
	global $langs, $conf, $user;

	$langs->load('workshop@workshop');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/workshop/operationorder/or_card.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('ORCard');
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = dol_buildpath('/workshop/operationorder/or_note.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Notes');
	$head[$h][2] = 'note';
	$h++;

	$head[$h][0] = dol_buildpath('/workshop/operationorder/or_document.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Documents');
	$head[$h][2] = 'document';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'operationorder@workshop');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'operationorder@workshop', 'remove');

	return $head;
}
