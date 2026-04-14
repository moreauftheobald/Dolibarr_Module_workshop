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
 * \file       workshop/ajax/or_card_ajax.php
 * \ingroup    workshop
 * \brief      Endpoints AJAX pour la fiche Ordre de Réparation (or_card.php)
 *
 * Actions supportées :
 *   - get_warehouses_for_product : retourne la liste des entrepôts + stock pour un produit donné
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf      $conf
 * @var DoliDB    $db
 * @var Translate $langs
 * @var User      $user
 */

if (!isModEnabled('workshop')) {
	accessforbidden();
}

$action     = GETPOST('action', 'aZ09');
$id         = GETPOSTINT('id');
$permissiontoadd = $user->hasRight('workshop', 'operationorders', 'write');

dol_syslog("Call ajax workshop/ajax/or_card_ajax.php action=".$action);

// ── Entrepôts + stock pour un produit donné ──────────────────────────────────
if ($action == 'get_warehouses_for_product' && $id > 0 && $permissiontoadd) {
	$product_id = GETPOSTINT('product_id');
	$result = array();

	if ($product_id > 0) {
		$sql  = "SELECT e.rowid, e.ref, COALESCE(ps.reel, 0) as qty, p.fk_default_warehouse";
		$sql .= " FROM ".MAIN_DB_PREFIX."entrepot e";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_stock ps ON (ps.fk_entrepot = e.rowid AND ps.fk_product = ".(int) $product_id.")";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON (p.rowid = ".(int) $product_id.")";
		$sql .= " WHERE e.entity IN (".getEntity('stock').")";
		$sql .= " AND e.statut >= 0";
		$sql .= " ORDER BY";
		$sql .= "   CASE WHEN e.rowid = p.fk_default_warehouse THEN 0 ELSE 1 END ASC,";
		$sql .= "   CASE WHEN COALESCE(ps.reel, 0) > 0 THEN 0 ELSE 1 END ASC,";
		$sql .= "   e.ref ASC";

		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$result[] = array(
					'rowid'      => (int) $obj->rowid,
					'ref'        => (string) $obj->ref,
					'qty'        => (float) $obj->qty,
					'is_default' => ((int) $obj->rowid === (int) $obj->fk_default_warehouse),
				);
			}
			$db->free($resql);
		} else {
			dol_syslog("or_card_ajax.php: SQL error ".$db->lasterror(), LOG_ERR);
		}
	}

	header('Content-Type: application/json');
	echo json_encode($result);
	$db->close();
	exit;
}

// Action non reconnue
header('HTTP/1.1 400 Bad Request');
header('Content-Type: application/json');
echo json_encode(array('error' => 'Unknown action'));
$db->close();
exit;
