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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/usergroup.class.php';
dol_include_once('/workshop/class/workshopoperationorderstatus.class.php');
dol_include_once('/workshop/lib/workshop_operationorder.lib.php');

if (!$user->hasRight('workshop', 'status', 'read')) {
    accessforbidden();
}

$langs->loadLangs(array('workshop@workshop'));

$object = new WorkshopOperationOrderStatus($db);

$action      = GETPOST('action', 'aZ09');
$contextpage = 'workshopoperationorderstatuslist';

$hookmanager->initHooks(array($contextpage));

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

if (empty($reshook)) {
    // Enregistrement du choix des colonnes affichées (sélecteur de colonnes standard Dolibarr)
    include DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';
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

$Tlist = $object->fetchAll(0, true, array('status' => WorkshopOperationOrderStatus::STATUS_ACTIVE));

// Champs booléens de comportement (identiques à ceux de la fiche)
$boolCols = array(
    'planable'             => 'Planable',
    'clean_event'          => 'WorkshopCleanEvent',
    'display_on_planning'  => 'WorkshopDisplayOnPlanning',
    'check_virtual_stock'  => 'WorkshopCheckVirtualStock',
    'or_pointable'         => 'WorkshopOrPointable',
    'save_date_cloture'    => 'WorkshopSaveDateCloture',
    'require_planned_date' => 'WorkshopRequirePlannedDate',
    'update_vehicule_info' => 'WorkshopUpdateVehiculeInfo',
    'require_conf'         => 'WorkshopRequireConf',
);

// Définition des colonnes optionnelles pilotées par le sélecteur de colonnes standard Dolibarr.
// Les colonnes Code et Libellé restent toujours affichées (identité de la ligne).
$arrayfields = array(
    'status_type' => array('label' => 'WorkshopStatusType', 'checked' => '1', 'enabled' => '1', 'position' => 25),
    'color'       => array('label' => 'Color', 'checked' => '1', 'enabled' => '1', 'position' => 30),
);
$posfield = 40;
foreach ($boolCols as $fieldName => $labelKey) {
    $arrayfields[$fieldName] = array('label' => $labelKey, 'checked' => '1', 'enabled' => '1', 'position' => $posfield);
    $posfield += 5;
}
$arrayfields['transitions'] = array('label' => 'WorkshopTargetableStatus', 'checked' => '1', 'enabled' => '1', 'position' => 200);
$posfield = 210;
foreach ($object->TGroupRightsType as $rightType) {
    $arrayfields['gr_' . $rightType['code']] = array('label' => $rightType['label'], 'checked' => '1', 'enabled' => '1', 'position' => $posfield);
    $posfield += 5;
}
$arrayfields['status'] = array('label' => 'Status', 'checked' => '1', 'enabled' => '1', 'position' => 1000);

$form = new Form($db);
$varpage = $contextpage;
// Applique la sélection enregistrée de l'utilisateur et retourne le sélecteur HTML
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column);

print '<form method="POST" id="searchFormList" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';

print '<div class="div-table-responsive">';
print '<table id="workshop-or-status-list" class="liste">';
print '<thead>';
print '<tr class="liste_titre">';
print '<th class="center maxwidthsearch">' . $selectedfields . '</th>';
print '<th>' . $langs->trans('Code') . '</th>';
print '<th>' . $langs->trans('Label') . '</th>';
if (!empty($arrayfields['status_type']['checked'])) {
    print '<th>' . $langs->trans('WorkshopStatusType') . '</th>';
}
if (!empty($arrayfields['color']['checked'])) {
    print '<th>' . $langs->trans('Color') . '</th>';
}
foreach ($boolCols as $fieldName => $labelKey) {
    if (!empty($arrayfields[$fieldName]['checked'])) {
        print '<th class="center">' . $langs->trans($labelKey) . '</th>';
    }
}
if (!empty($arrayfields['transitions']['checked'])) {
    print '<th>' . $langs->trans('WorkshopTargetableStatus') . '</th>';
}
foreach ($object->TGroupRightsType as $rightType) {
    if (!empty($arrayfields['gr_' . $rightType['code']]['checked'])) {
        print '<th>' . $langs->trans($rightType['label']) . '</th>';
    }
}
if (!empty($arrayfields['status']['checked'])) {
    print '<th>' . $langs->trans('Status') . '</th>';
}
print '<th></th>';
print '</tr>';
print '</thead>';
print '<tbody>';

if (!empty($Tlist)) {
    // Badges (avec lien) de tous les statuts actifs, pour l'affichage des transitions
    $statusBadges = array();
    foreach ($Tlist as $sBadge) {
        $statusBadges[$sBadge->id] = $sBadge->getNomUrl(0);
    }
    $groupNameCache = array();

    foreach ($Tlist as $oStatus) {
        print '<tr data-lineid="' . $oStatus->id . '">';
		print '<td class="linecolmove"></td>';
        print '<td><a href="' . $oStatus->getCardUrl() . '">' . dol_escape_htmltag($oStatus->code) . '</a></td>';
        print '<td><a href="' . $oStatus->getCardUrl() . '">' . $oStatus->getBadge() . '</a></td>';

        // Type de statut
        if (!empty($arrayfields['status_type']['checked'])) {
            print '<td>' . dol_escape_htmltag($oStatus->getLibStatusType()) . '</td>';
        }

        // Couleur
        if (!empty($arrayfields['color']['checked'])) {
            print '<td><input disabled type="color" value="' . dol_escape_htmltag($oStatus->color) . '"></td>';
        }

        // Champs booléens de comportement
        foreach ($boolCols as $fieldName => $labelKey) {
            if (empty($arrayfields[$fieldName]['checked'])) {
                continue;
            }
            print '<td class="center">' . yn(!empty($oStatus->$fieldName) ? 1 : 0) . '</td>';
        }

        // Transitions autorisées vers d'autres statuts
        if (!empty($arrayfields['transitions']['checked'])) {
            print '<td>';
            if (!empty($oStatus->TStatusAllowed)) {
                foreach ($oStatus->TStatusAllowed as $fkTarget) {
                    if (isset($statusBadges[$fkTarget])) {
                        print $statusBadges[$fkTarget] . ' ';
                    } else {
                        $statusTarget = new WorkshopOperationOrderStatus($db);
                        if ($statusTarget->fetch((int) $fkTarget, false) > 0) {
                            print $statusTarget->getNomUrl(0) . ' ';
                        }
                    }
                }
            }
            print '</td>';
        }

        // Droits des groupes par action, pour l'entité courante
        foreach ($object->TGroupRightsType as $rightType) {
            $code = $rightType['code'];
            if (empty($arrayfields['gr_' . $code]['checked'])) {
                continue;
            }
            print '<td>';
            if (!empty($oStatus->TGroupCan[$code])) {
                foreach ($oStatus->TGroupCan[$code] as $fkGroup) {
                    if (!isset($groupNameCache[$fkGroup])) {
                        $group = new UserGroup($db);
                        $groupNameCache[$fkGroup] = ($group->fetch((int) $fkGroup) > 0) ? $group->name : '';
                    }
                    if ($groupNameCache[$fkGroup] !== '') {
                        print dolGetBadge($groupNameCache[$fkGroup], '', 'secondary') . ' ';
                    }
                }
            }
            print '</td>';
        }

        // Statut actif / désactivé
        if (!empty($arrayfields['status']['checked'])) {
            print '<td>' . $oStatus->getLibStatut(2) . '</td>';
        }

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
print '</div>';
print '</form>';

llxFooter();
$db->close();
