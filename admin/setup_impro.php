<?php
/* Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
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
 * \file    workshop/admin/setup_impro.php
 * \ingroup workshop
 * \brief   Workshop admin page - improductive codes (mechanics planning).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
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
if (!$res) {
	die("Include of main fails");
}

global $conf, $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
dol_include_once('/workshop/lib/workshop.lib.php');
dol_include_once('/workshop/class/workshopimpro.class.php');

$langs->loadLangs(array("admin", "workshop@workshop"));

$hookmanager->initHooks(array('workshopsetupimpro', 'globalsetup'));

$action = GETPOST('action', 'aZ09');
$title  = "WorkshopSetup";

if (!$user->admin) {
	accessforbidden();
}

$object = new WorkshopImpro($db);

/*
 * Actions
 */

if ($action == 'addimpro' && !empty($user->admin)) {
	$label = GETPOST('imp_label', 'alphanohtml');
	if (trim($label) === '') {
		setEventMessages($langs->trans('WorkshopImproLabelRequired'), null, 'errors');
	} else {
		$object->label      = $label;
		$object->is_absence = GETPOSTINT('imp_is_absence');
		$object->active     = 1;
		if ($object->create($user) > 0) {
			setEventMessages($langs->trans('WorkshopImproAdded'), null, 'mesgs');
		} else {
			setEventMessages($object->error ?: $langs->trans('Error'), $object->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
} elseif ($action == 'deleteimpro' && !empty($user->admin)) {
	$improid = GETPOSTINT('improid');
	if ($improid > 0 && $object->fetch($improid) > 0) {
		if ($object->delete($user) > 0) {
			setEventMessages($langs->trans('WorkshopImproDeleted'), null, 'mesgs');
		} else {
			setEventMessages($object->error ?: $langs->trans('Error'), $object->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
} elseif ($action == 'savedelay' && !empty($user->admin)) {
	$delay = max(0, GETPOSTINT('WORKSHOP_IMPRO_CANCEL_DELAY'));
	$res   = dolibarr_set_const($db, 'WORKSHOP_IMPRO_CANCEL_DELAY', $delay, 'chaine', 0, '', $conf->entity);
	if ($res > 0) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

/*
 * View
 */

$form = new Form($db);

$help_url = '';

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-workshop page-admin_impro');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = workshopAdminPrepareHead();
print dol_get_fiche_head($head, 'ordres_reparation', $langs->trans($title), -1, "workshop@workshop");

if (!getDolGlobalInt('WORKSHOP_USE_OR')) {
	print '<span class="opacitymedium">'.$langs->trans('WorkshopORDisabledEnableFirst').'</span>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

$subhead = workshopORAdminPrepareHead();
print dol_get_fiche_head($subhead, 'improductifs', '', -1, '');

print '<span class="opacitymedium">'.$langs->trans('WorkshopImproPageIntro').'</span><br><br>';

// --- Delay before a pointage can be cancelled (reserved code Annulation) ---
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="savedelay">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>';
print $form->textwithpicto($langs->trans('WorkshopImproCancelDelay'), $langs->trans('WorkshopImproCancelDelayHelp'));
print '</td>';
print '<td>';
print '<input type="number" name="WORKSHOP_IMPRO_CANCEL_DELAY" class="flat" min="0" style="width:80px" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_IMPRO_CANCEL_DELAY', '15')).'">';
print ' '.$langs->trans('WorkshopMinutes');
print '</td>';
print '</tr>';
print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
print '</div>';
print '</form>';

// --- Add a new improductive code ---
print '<br>';
print load_fiche_titre($langs->trans('WorkshopImproCodes'), '', '');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="addimpro">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('WorkshopImproCode').'</td>';
print '<td>'.$langs->trans('Label').'</td>';
print '<td class="center">'.$langs->trans('WorkshopImproIsAbsence').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td class="right"></td>';
print '</tr>';

// Reserved codes (read-only)
$reserved = array(
	WorkshopImpro::CODE_FIN_JOURNEE => $langs->trans('WorkshopImproFinJournee'),
	WorkshopImpro::CODE_ANNULATION  => $langs->trans('WorkshopImproAnnulation'),
);
foreach ($reserved as $code => $lbl) {
	print '<tr class="oddeven">';
	print '<td><span class="opacitymedium">'.dol_escape_htmltag($code).'</span></td>';
	print '<td>'.dol_escape_htmltag($lbl).'</td>';
	print '<td class="center">'.$langs->trans('No').'</td>';
	print '<td class="center"><span class="badge badge-status4 badge-status">'.$langs->trans('WorkshopImproReserved').'</span></td>';
	print '<td class="right"></td>';
	print '</tr>';
}

// User-defined codes
$list = $object->fetchAll('ASC', 'code', 0);
if (is_array($list)) {
	foreach ($list as $impro) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($impro->code).'</td>';
		print '<td>'.dol_escape_htmltag($impro->label).'</td>';
		print '<td class="center">'.($impro->is_absence ? $langs->trans('Yes') : $langs->trans('No')).'</td>';
		print '<td class="center">'.($impro->active ? $langs->trans('Enabled') : $langs->trans('Disabled')).'</td>';
		print '<td class="right">';
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=deleteimpro&improid='.((int) $impro->id).'&token='.newToken().'" onclick="return confirm(\''.dol_escape_js($langs->trans('WorkshopImproConfirmDelete')).'\');">';
		print img_delete();
		print '</a>';
		print '</td>';
		print '</tr>';
	}
}

// New code input row
print '<tr class="oddeven">';
print '<td><span class="opacitymedium">'.$langs->trans('WorkshopImproAutoCode').'</span></td>';
print '<td><input type="text" name="imp_label" class="flat minwidth200" value="" placeholder="'.dol_escape_htmltag($langs->trans('Label')).'"></td>';
print '<td class="center"><input type="checkbox" name="imp_is_absence" value="1"></td>';
print '<td class="center">'.$langs->trans('Enabled').'</td>';
print '<td class="right"><input type="submit" class="button small" value="'.$langs->trans('Add').'"></td>';
print '</tr>';

print '</table>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
