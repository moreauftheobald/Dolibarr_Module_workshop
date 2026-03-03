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
 * \file    operationorder/or_status_list.php
 * \ingroup workshop
 * \brief   Liste des statuts des ordres de réparation
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
dol_include_once('/workshop/class/workshopoperationorderstatus.class.php');
dol_include_once('/workshop/lib/workshop_operationorder.lib.php');

if (!$user->hasRight('workshop', 'status', 'read')) {
    accessforbidden();
}

$langs->loadLangs(array('workshop@workshop'));

$object = new WorkshopOperationOrderStatus($db);

$hookmanager->initHooks(array('workshopoperationorderstatuslist'));

/*
 * Actions
 */

$massaction     = GETPOST('massaction', 'alpha');
$confirmmassaction = GETPOST('confirmmassaction', 'alpha');
$toselect       = GETPOST('toselect', 'array');

$parameters = array();
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object);
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (!empty($confirmmassaction) && $massaction === 'delete' && !empty($toselect)) {
    foreach ($toselect as $deleteId) {
        $objectToDelete = new WorkshopOperationOrderStatus($db);
        $res            = $objectToDelete->fetch((int) $deleteId);
        if ($res > 0) {
            if ($objectToDelete->delete($user) < 0) {
                setEventMessages($langs->trans('WorkshopORStatusDeleteError'), array(), 'errors');
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * Vue
 */

llxHeader('', $langs->trans('WorkshopORStatusList'), '');

$newcardbutton = dolGetButtonTitle(
    $langs->trans('NewWorkshopORStatus'),
    '',
    'fa fa-plus-circle',
    dol_buildpath('/workshop/operationorder/or_status_card.php?action=create', 1),
    '',
    $user->hasRight('workshop', 'status', 'write')
);

print load_fiche_titre($langs->trans('WorkshopORStatusList'), $newcardbutton, 'fa-traffic-light');

$Tlist = $object->fetchAll(0, false, array('status' => WorkshopOperationOrderStatus::STATUS_ACTIVE));

print '<table id="workshop-or-status-list" class="liste">';
print '<thead>';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('Code') . '</th>';
print '<th>' . $langs->trans('Label') . '</th>';
print '<th>' . $langs->trans('Color') . '</th>';
print '<th>' . $langs->trans('Status') . '</th>';
print '<th></th>';
print '</tr>';
print '</thead>';
print '<tbody>';

if (!empty($Tlist)) {
    foreach ($Tlist as $oStatus) {
        print '<tr data-lineid="' . $oStatus->id . '">';
        print '<td><a href="' . $oStatus->getCardUrl() . '">' . dol_escape_htmltag($oStatus->code) . '</a></td>';
        print '<td><a href="' . $oStatus->getCardUrl() . '">' . $oStatus->getBadge() . '</a></td>';
        print '<td><input disabled type="color" value="' . dol_escape_htmltag($oStatus->color) . '"></td>';
        print '<td>' . $oStatus->getLibStatut(2) . '</td>';
        print '<td class="linecolmove"></td>';
        print '</tr>';
    }

    ?>
    <script type="text/javascript">
        $(document).ready(function () {
            var moveBlockCol = $('td.linecolmove');
            moveBlockCol.disableSelection();
            moveBlockCol.css({
                'background-image': 'url(<?php echo dol_buildpath('theme/eldy/img/grip.png', 2); ?>)',
                'background-repeat': 'no-repeat',
                'background-position': 'center center',
                'cursor': 'move'
            });
            moveBlockCol.attr('title', '<?php echo dol_escape_js($langs->trans('MoveRow')); ?>');

            $('#workshop-or-status-list').sortable({
                cursor: 'move',
                handle: '.linecolmove',
                items: 'tr:not(.liste_titre)',
                delay: 150,
                opacity: 0.8,
                axis: 'y',
                placeholder: 'ui-state-highlight',
                start: function (event, ui) {
                    var colCount = ui.item.children().length;
                    ui.placeholder.html('<td colspan="' + colCount + '">&nbsp;</td>');
                },
                update: function (event, ui) {
                    var TRowOrder = $(this).sortable('toArray', {attribute: 'data-lineid'});
                    $.ajax({
                        data: {
                            action: 'workshopStatusRank',
                            TRowOrder: TRowOrder,
                            token: '<?php echo currentToken(); ?>'
                        },
                        type: 'POST',
                        url: '<?php echo dol_buildpath('/workshop/ajax/workshopstatus.php', 1); ?>'
                    });
                }
            });
        });
    </script>
    <style>
        tr.ui-state-highlight td {
            border: 1px solid #dad55e;
            background: #fffa90;
            color: #777620;
        }
    </style>
    <?php
}

print '</tbody>';
print '</table>';

llxFooter();
$db->close();
