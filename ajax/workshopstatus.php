<?php
/* Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
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

/**
 * \file    ajax/workshopstatus.php
 * \ingroup workshop
 * \brief   AJAX handler — mise à jour de l'ordre d'affichage des statuts (drag & drop)
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}

$res = @include '../main.inc.php';
if (!$res) {
    $res = @include '../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

dol_include_once('/workshop/class/workshopoperationorderstatus.class.php');

if (!$user->hasRight('workshop', 'status', 'write')) {
    http_response_code(403);
    print json_encode(array('error' => 'Forbidden'));
    exit;
}

$action    = GETPOST('action', 'aZ09');
$TRowOrder = GETPOST('TRowOrder', 'array');

if ($action === 'workshopStatusRank' && !empty($TRowOrder)) {
    foreach ($TRowOrder as $rang => $rowid) {
        WorkshopOperationOrderStatus::updateRank((int) $rowid, (int) $rang);
    }
    print json_encode(array('result' => 'ok'));
}

exit;
