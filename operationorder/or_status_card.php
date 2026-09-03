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
 * \file    operationorder/or_status_card.php
 * \ingroup workshop
 * \brief   Fiche de création/édition/consultation d'un statut d'ordre de réparation
 *
 * Particularités :
 * - Les statuts sont définis sur entity = 0 (partagés)
 * - Les transitions sont partagées (entity = 0)
 * - Les droits des groupes (read/edit/changeToThisStatus) sont définis par entité courante
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
dol_include_once('/workshop/class/workshopoperationorderstatus.class.php');
dol_include_once('/workshop/lib/workshop_operationorder.lib.php');

if (!$user->hasRight('workshop', 'status', 'read')) {
    accessforbidden();
}

$langs->loadLangs(array('workshop@workshop'));

$action     = GETPOST('action', 'aZ09');
$cancel     = GETPOST('cancel', 'alpha');
$id         = GETPOST('id', 'int');
$ref        = GETPOST('ref', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new WorkshopOperationOrderStatus($db);

if (!empty($id) || !empty($ref)) {
    $object->fetch($id, true, $ref ?: null);
}

// Récupérer les valeurs POST pour les droits groupes
$TGroupCan = array();
foreach ($object->TGroupRightsType as $field) {
    $posted = GETPOST('TGroupCan_' . $field['code'], 'array');
    $TGroupCan[$field['code']] = !empty($posted) ? array_map('intval', $posted) : array();
}

// Récupérer les transitions soumises
$TStatusAllowed = GETPOST('TStatusAllowed', 'array');
$TStatusAllowed = !empty($TStatusAllowed) ? array_map('intval', $TStatusAllowed) : array();

$hookmanager->initHooks(array('workshopoperationorderstatuscard', 'globalcard'));

/*
 * Actions
 */

$parameters = array('id' => $id, 'ref' => $ref);
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    if ($cancel) {
        if (!empty($backtopage)) {
            header('Location: ' . $backtopage);
            exit;
        }
        $action = '';
    }

    $error = 0;

    switch ($action) {
        case 'add':
        case 'update':
            if (!$user->hasRight('workshop', 'status', 'write')) {
                accessforbidden();
            }

            // Remplir l'objet depuis le formulaire
            $object->code    = GETPOST('code', 'alpha');
            $object->label   = GETPOST('label', 'alpha');
            $object->color   = GETPOST('color', 'alpha');
            $object->status_type          = GETPOSTINT('status_type');
            $object->rang                 = GETPOST('rang', 'int');
            $object->planable             = GETPOST('planable', 'int');
            $object->clean_event          = GETPOST('clean_event', 'int');
            $object->display_on_planning  = GETPOST('display_on_planning', 'int');
            $object->check_virtual_stock  = GETPOST('check_virtual_stock', 'int');
            $object->or_pointable         = GETPOST('or_pointable', 'int');
            $object->save_date_cloture    = GETPOST('save_date_cloture', 'int');
            $object->require_planned_date = GETPOST('require_planned_date', 'int');
            $object->update_vehicule_info = GETPOST('update_vehicule_info', 'int');
            $object->require_conf         = GETPOST('require_conf', 'int');
            $object->status               = GETPOST('status_obj', 'int'); // "status" de l'objet statut lui-même

            // Droits des groupes par entité courante
            $object->TGroupCan = $TGroupCan;

            // Transitions (partagées, entity=0)
            $object->TStatusAllowed = $TStatusAllowed;

            if (empty($object->code)) {
                $error++;
                setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Code')), array(), 'errors');
            }
            if (empty($object->label)) {
                $error++;
                setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Label')), array(), 'errors');
            }

            if ($error > 0) {
                $action = (empty($object->id) ? 'create' : 'edit');
                break;
            }

            $res = $object->save($user);

            if ($res <= 0) {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = (empty($object->id) ? 'create' : 'edit');
            } else {
                header('Location: ' . dol_buildpath('/workshop/operationorder/or_status_card.php', 1) . '?id=' . $object->id);
                exit;
            }
            break;

        case 'activate':
            if ($user->hasRight('workshop', 'status', 'write')) {
                $object->setActive($user);
            }
            break;

        case 'disable':
            if ($user->hasRight('workshop', 'status', 'write')) {
                $object->setDisabled($user);
            }
            break;

        case 'confirm_delete':
            if ($user->hasRight('workshop', 'status', 'write')) {
                $object->delete($user);
                header('Location: ' . dol_buildpath('/workshop/operationorder/or_status_list.php', 1));
                exit;
            }
            break;
    }
}

/*
 * Vue
 */

$form = new Form($db);

llxHeader('', $langs->trans('WorkshopORStatus'), '');

// ============================================================
// FORMULAIRE DE CRÉATION
// ============================================================

if ($action === 'create') {
    print load_fiche_titre($langs->trans('NewWorkshopORStatus'), '', 'fa-traffic-light');

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="add">';
    print '<input type="hidden" name="backtopage" value="' . dol_escape_htmltag($backtopage) . '">';

    print dol_get_fiche_head(array(), '');
    print '<table class="border centpercent">' . "\n";

    _printStatusFormFields($object, $form, $langs, $TGroupCan, $TStatusAllowed);

    print '</table>' . "\n";
    print dol_get_fiche_end();

    print '<div class="center">';
    print '<input type="submit" class="button" name="add" value="' . dol_escape_htmltag($langs->trans('Create')) . '">';
    print ' &nbsp; ';
    print '<input type="button" class="button" name="cancel" value="' . dol_escape_htmltag($langs->trans('Cancel')) . '" onclick="history.go(-1)">';
    print '</div>';

    print '</form>';

// ============================================================
// FORMULAIRE D'ÉDITION
// ============================================================
} elseif (!empty($object->id) && $action === 'edit') {
    if (!$user->hasRight('workshop', 'status', 'write')) {
        accessforbidden();
    }

    $head = workshopOperationOrderStatusPrepareHead($object);
    print dol_get_fiche_head($head, 'card', $langs->trans('WorkshopORStatus'), 0, 'fa-traffic-light');

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="' . $object->id . '">';
    print '<input type="hidden" name="backtopage" value="' . dol_escape_htmltag($backtopage) . '">';

    print '<table class="border centpercent">' . "\n";

    _printStatusFormFields($object, $form, $langs, $object->TGroupCan, $object->TStatusAllowed);

    print '</table>';
    print dol_get_fiche_end();

    print '<div class="center">';
    print '<input type="submit" class="button" name="save" value="' . dol_escape_htmltag($langs->trans('Save')) . '">';
    print ' &nbsp; ';
    print '<input type="submit" class="button" name="cancel" value="' . dol_escape_htmltag($langs->trans('Cancel')) . '">';
    print '</div>';

    print '</form>';

// ============================================================
// VUE EN LECTURE
// ============================================================
} elseif (!empty($object->id)) {
    $head = workshopOperationOrderStatusPrepareHead($object);
    print dol_get_fiche_head($head, 'card', $langs->trans('WorkshopORStatus'), -1, 'fa-traffic-light');

    // Confirmation de suppression
    if ($action === 'delete') {
        print $form->formconfirm(
            $_SERVER['PHP_SELF'] . '?id=' . $object->id,
            $langs->trans('WorkshopORStatusConfirmDelete'),
            $langs->trans('WorkshopORStatusConfirmDeleteMessage', $object->label),
            'confirm_delete',
            '',
            0,
            1
        );
    }

    $linkback = '<a href="' . dol_buildpath('/workshop/operationorder/or_status_list.php', 1) . '">'
        . $langs->trans('BackToList') . '</a>';

    // Bannière avec le badge coloré comme titre
    $morehtmlref = '<div class="refidno">' . $object->getBadge() . '</div>';

    dol_banner_tab($object, 'rowid', $linkback, 0, 'rowid', 'code', $morehtmlref, '', 0, '', '');

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border tableforfield" width="100%">' . "\n";

    // Code
    print '<tr class="oddeven"><td class="titlefield">' . $langs->trans('Code') . '</td>';
    print '<td>' . dol_escape_htmltag($object->code) . '</td></tr>';

    // Label
    print '<tr class="oddeven"><td>' . $langs->trans('Label') . '</td>';
    print '<td>' . $object->getBadge() . '</td></tr>';

    // Couleur
    print '<tr class="oddeven"><td>' . $langs->trans('Color') . '</td>';
    print '<td><input disabled type="color" value="' . dol_escape_htmltag($object->color) . '"></td></tr>';

    // Type de statut
    print '<tr class="oddeven"><td>' . $langs->trans('WorkshopStatusType') . '</td>';
    print '<td>' . $object->getLibStatusType() . '</td></tr>';

    // Rang
    print '<tr class="oddeven"><td>' . $langs->trans('Rank') . '</td>';
    print '<td>' . (int) $object->rang . '</td></tr>';

    // Champs booléens comportement
    $boolViewFields = array(
        'planable'            => 'Planable',
        'clean_event'         => 'WorkshopCleanEvent',
        'display_on_planning' => 'WorkshopDisplayOnPlanning',
        'check_virtual_stock' => 'WorkshopCheckVirtualStock',
        'or_pointable'        => 'WorkshopOrPointable',
        'save_date_cloture'   => 'WorkshopSaveDateCloture',
        'require_planned_date'=> 'WorkshopRequirePlannedDate',
        'update_vehicule_info'=> 'WorkshopUpdateVehiculeInfo',
        'require_conf'        => 'WorkshopRequireConf',
    );
    foreach ($boolViewFields as $fieldName => $labelKey) {
        print '<tr class="oddeven"><td>' . $langs->trans($labelKey) . '</td>';
        print '<td>' . yn(!empty($object->$fieldName) ? 1 : 0) . '</td></tr>';
    }

    // Statut (actif/désactivé)
    print '<tr class="oddeven"><td>' . $langs->trans('Status') . '</td>';
    print '<td>' . $object->getLibStatut(2) . '</td></tr>';

    // ---- Droits des groupes par entité ----
    foreach ($object->TGroupRightsType as $rightType) {
        $code = $rightType['code'];
        print '<tr class="oddeven"><td>' . $langs->trans($rightType['label']) . '</td><td>';
        if (!empty($object->TGroupCan[$code])) {
            foreach ($object->TGroupCan[$code] as $fk_group) {
                $group = new UserGroup($db);
                if ($group->fetch((int) $fk_group) > 0) {
                    print dolGetBadge($group->name, '', 'secondary') . ' ';
                }
            }
        }
        print '</td></tr>';
    }

    // ---- Transitions autorisées ----
    print '<tr class="oddeven"><td>' . $langs->trans('WorkshopTargetableStatus') . '</td><td>';
    if (!empty($object->TStatusAllowed)) {
        foreach ($object->TStatusAllowed as $fk_target) {
            $statusTarget = new WorkshopOperationOrderStatus($db);
            if ($statusTarget->fetch((int) $fk_target, false) > 0) {
                print $statusTarget->getNomUrl(0) . ' ';
            }
        }
    }
    print '</td></tr>';

    print '</table>';
    print '</div>';
    print '</div>';
    print '<div class="clearboth"></div><br>';

    // Boutons d'action
    print '<div class="tabsAction">' . "\n";

    $actionUrl = $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&amp;action=';

    print dolGetButtonAction($langs->trans('Modify'), '', 'default', $actionUrl . 'edit', '', $user->hasRight('workshop', 'status', 'write'));

    if ($object->status != WorkshopOperationOrderStatus::STATUS_ACTIVE) {
        print dolGetButtonAction($langs->trans('Activate'), '', 'default', $actionUrl . 'activate', '', $user->hasRight('workshop', 'status', 'write'));
    }
    if ($object->status != WorkshopOperationOrderStatus::STATUS_DISABLED) {
        print dolGetButtonAction($langs->trans('Disable'), '', 'default', $actionUrl . 'disable', '', $user->hasRight('workshop', 'status', 'write'));
    }

    print dolGetButtonAction($langs->trans('Delete'), '', 'danger', $actionUrl . 'delete', '', $user->hasRight('workshop', 'status', 'write'));

    print '</div>' . "\n";
    print dol_get_fiche_end(-1);

} else {
    $langs->load('errors');
    print $langs->trans('ErrorRecordNotFound');
}

llxFooter();
$db->close();


// ---------------------------------------------------------------------------
// Fonctions utilitaires locales
// ---------------------------------------------------------------------------

/**
 * Affiche les lignes du formulaire (création ET édition).
 *
 * @param WorkshopOperationOrderStatus $object        Objet statut
 * @param Form                         $form          Objet formulaire Dolibarr
 * @param Translate                    $langs         Objet traductions
 * @param array                        $TGroupCan     Droits groupes courants
 * @param array                        $TStatusAllowed Transitions courantes
 */
function _printStatusFormFields($object, $form, $langs, $TGroupCan, $TStatusAllowed)
{
    global $db, $conf;

    // Code
    print '<tr class="oddeven"><td class="fieldrequired titlefield">' . $langs->trans('Code') . '</td>';
    print '<td><input type="text" name="code" class="minwidth200" value="' . dol_escape_htmltag($object->code) . '" required></td></tr>';

    // Label
    print '<tr class="oddeven"><td class="fieldrequired">' . $langs->trans('Label') . '</td>';
    print '<td><input type="text" name="label" class="minwidth300" value="' . dol_escape_htmltag($object->label) . '" required></td></tr>';

    // Couleur
    $color = $object->color ?: '#3c8dbc';
    print '<tr class="oddeven"><td>' . $langs->trans('Color') . '</td>';
    print '<td><input type="color" name="color" value="' . dol_escape_htmltag($color) . '"></td></tr>';

    // Type de statut
    print '<tr class="oddeven"><td>' . $langs->trans('WorkshopStatusType') . '</td><td>';
    print $form->selectarray('status_type', array(
        WorkshopOperationOrderStatus::TYPE_OR         => $langs->trans('WorkshopStatusTypeOR'),
        WorkshopOperationOrderStatus::TYPE_OR_AND_JOB => $langs->trans('WorkshopStatusTypeORAndJob'),
    ), (isset($object->status_type) ? (int) $object->status_type : WorkshopOperationOrderStatus::TYPE_OR));
    print '</td></tr>';

    // Rang
    print '<tr class="oddeven"><td>' . $langs->trans('Rank') . '</td>';
    print '<td><input type="number" name="rang" value="' . (int) $object->rang . '"></td></tr>';

    // Champs booléens comportement
    $boolFields = array(
        'planable'            => 'Planable',
        'clean_event'         => 'WorkshopCleanEvent',
        'display_on_planning' => 'WorkshopDisplayOnPlanning',
        'check_virtual_stock' => 'WorkshopCheckVirtualStock',
        'or_pointable'        => 'WorkshopOrPointable',
        'save_date_cloture'   => 'WorkshopSaveDateCloture',
        'require_planned_date'=> 'WorkshopRequirePlannedDate',
        'update_vehicule_info'=> 'WorkshopUpdateVehiculeInfo',
        'require_conf'        => 'WorkshopRequireConf',
    );
    foreach ($boolFields as $fieldName => $labelKey) {
        $checked = !empty($object->$fieldName) ? ' checked' : '';
        $help = isset($object->fields[$fieldName]['help']) ? $object->fields[$fieldName]['help'] : '';
        print '<tr class="oddeven"><td>' . $langs->trans($labelKey);
        if ($help) {
            print ' ' . $form->textwithtooltip('', $langs->trans($help), 2, 1, img_help(1, ''));
        }
        print '</td>';
        print '<td><input type="checkbox" name="' . $fieldName . '" value="1"' . $checked . '></td></tr>';
    }

    // Statut actif/désactivé de l'objet statut lui-même
    print '<tr class="oddeven"><td>' . $langs->trans('Status') . '</td>';
    print '<td>';
    $TStatusVals = array(
        WorkshopOperationOrderStatus::STATUS_ACTIVE   => $langs->trans('WorkshopORStatusActive'),
        WorkshopOperationOrderStatus::STATUS_DISABLED => $langs->trans('WorkshopORStatusDisabled'),
    );
    print $form->selectarray('status_obj', $TStatusVals, isset($object->status) ? (int) $object->status : WorkshopOperationOrderStatus::STATUS_ACTIVE);
    print '</td></tr>';

    // ---- Droits des groupes (par entité courante) ----
    // Avertissement multi-entité
    if (isModEnabled('multicompany')) {
        print '<tr><td colspan="2"><div class="info">';
        print img_picto('', 'info') . ' ';
        print $langs->trans('WorkshopStatusGroupRightsPerEntity', $conf->entity);
        print '</div></td></tr>';
    }

    foreach ($object->TGroupRightsType as $rightType) {
        $code     = $rightType['code'];
        $selected = !empty($TGroupCan[$code]) ? array_values($TGroupCan[$code]) : array();
        print '<tr class="oddeven">';
        print '<td>' . $langs->trans($rightType['label']);
        if (!empty($rightType['help'])) {
            print ' ' . $form->textwithtooltip('', $langs->trans($rightType['help']), 2, 1, img_help(1, ''));
        }
        print '</td>';
        print '<td>';
        print $form->select_dolgroups($selected, 'TGroupCan_' . $code, 1, '', 0, '', 1, $conf->entity, true);
        print '</td></tr>';
    }

    // ---- Transitions autorisées (partagées, entity=0) ----
    if (isModEnabled('multicompany')) {
        print '<tr><td colspan="2"><div class="info">';
        print img_picto('', 'info') . ' ';
        print $langs->trans('WorkshopStatusTransitionsShared');
        print '</div></td></tr>';
    }

    print '<tr class="oddeven"><td>' . $langs->trans('WorkshopTargetableStatus') . '</td>';
    print '<td>';

    $allStatuses  = $object->fetchAll(0, false, array('status' => WorkshopOperationOrderStatus::STATUS_ACTIVE));
    $TAvailable   = array();
    foreach ($allStatuses as $statusId => $statusObj) {
        if ($statusId != $object->id) {
            $TAvailable[$statusId] = $statusObj->label;
        }
    }

    if (!empty($TAvailable)) {
        print $form->multiselectarray('TStatusAllowed', $TAvailable, $TStatusAllowed, 0, 0, '', 0, '100%', '', '', '', 1);
    } else {
        print '<span class="opacitymedium">' . $langs->trans('WorkshopNoOtherStatusAvailable') . '</span>';
    }

    print '</td></tr>';
}
