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
 * \file    workshop/admin/setup_or.php
 * \ingroup workshop
 * \brief   Workshop admin page - Ordres de réparation tab.
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

global $langs, $user;

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/pdf.lib.php";
require_once '../lib/workshop.lib.php';
dol_include_once('/workshop/class/operationorder.class.php');

$langs->loadLangs(array("admin", "workshop@workshop"));

$hookmanager->initHooks(array('workshopsetup', 'globalsetup'));

$action     = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$subtab     = GETPOST('subtab', 'aZ09') ?: 'general';
$value      = GETPOST('value', 'alpha');
$label      = GETPOST('label', 'alpha');
$scandir    = GETPOST('scan_dir', 'alpha');
$type       = 'workshopor';
$error      = 0;

if (!$user->admin) {
	accessforbidden();
}

/*
 * Actions
 */

// --- Actions numérotation & PDF (uniquement si OR actif) ---
if (getDolGlobalInt('WORKSHOP_USE_OR')) {
	if ($action == 'updateMask') {
		$maskconstor = GETPOST('maskconstWorkshopOR', 'alpha');
		$maskor      = GETPOST('maskWorkshopOR', 'alpha');
		if ($maskconstor) {
			$res = dolibarr_set_const($db, $maskconstor, $maskor, 'chaine', 0, '', $conf->entity);
		}
		if (!$res > 0) {
			$error++;
		}
		if (!$error) {
			setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	} elseif ($action == 'specimen') {
		$modele = GETPOST('module', 'alpha');
		$workshopor = new Operationorder($db);
		$workshopor->initAsSpecimenCommon();
		$file = ''; $classname = ''; $filefound = 0;
		$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
		foreach ($dirmodels as $reldir) {
			$file = dol_buildpath($reldir."core/modules/workshopor/doc/pdf_".$modele.".modules.php", 0);
			if (file_exists($file)) {
				$filefound = 1;
				$classname = "pdf_".$modele;
				break;
			}
		}
		if ($filefound) {
			require_once $file;
			$module = new $classname($db);
			if ($module->write_file($workshopor, $langs) > 0) {
				header("Location: ".DOL_URL_ROOT."/document.php?modulepart=workshopor&file=SPECIMEN.pdf");
				return;
			} else {
				setEventMessages($module->error, null, 'errors');
				dol_syslog($module->error, LOG_ERR);
			}
		} else {
			setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
			dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
		}
	} elseif ($action == 'set') {
		addDocumentModel($value, $type, $label, $scandir);
	} elseif ($action == 'del') {
		$ret = delDocumentModel($value, $type);
		if ($ret > 0 && getDolGlobalString('WORKSHOP_OR_ADDON_PDF') == $value) {
			dolibarr_del_const($db, 'WORKSHOP_OR_ADDON_PDF', $conf->entity);
		}
	} elseif ($action == 'setdoc') {
		if (dolibarr_set_const($db, 'WORKSHOP_OR_ADDON_PDF', $value, 'chaine', 0, '', $conf->entity)) {
			$conf->global->WORKSHOP_OR_ADDON_PDF = $value;
		}
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			addDocumentModel($value, $type, $label, $scandir);
		}
	} elseif ($action == 'setmod') {
		dolibarr_set_const($db, 'WORKSHOP_OR_ADDON', $value, 'chaine', 0, '', $conf->entity);
	}
}

if ($action == 'update_use_or' && !empty($user->admin)) {
	// Save WORKSHOP_USE_OR
	$useOR = GETPOSTINT('WORKSHOP_USE_OR');
	$res = dolibarr_set_const($db, 'WORKSHOP_USE_OR', $useOR, 'chaine', 0, '', $conf->entity);
	if (!($res > 0)) {
		$error++;
	}
	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

if ($action == 'update_subtab' && !empty($user->admin)) {
	if ($subtab == 'general') {
		// Save WORKSHOP_MECHANIC_GROUP
		$mechanicGroupId = GETPOSTINT('WORKSHOP_MECHANIC_GROUP');
		$res = dolibarr_set_const($db, 'WORKSHOP_MECHANIC_GROUP', $mechanicGroupId, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
		// Save WORKSHOP_OR_ALERT_STATUTS
		$alertStatuts = GETPOST('WORKSHOP_OR_ALERT_STATUTS', 'array:int');
		$alertStatutsValue = is_array($alertStatuts) ? implode(',', array_map('intval', $alertStatuts)) : '';
		$res = dolibarr_set_const($db, 'WORKSHOP_OR_ALERT_STATUTS', $alertStatutsValue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
		if (!$error) {
			setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	} elseif ($subtab == 'planning') {
		// WORKSHOP_OR_PLANNING_GROUPS
		$planningGroups = GETPOST('WORKSHOP_OR_PLANNING_GROUPS', 'array:int');
		$planningGroupsValue = is_array($planningGroups) ? implode(',', array_map('intval', $planningGroups)) : '';
		$res = dolibarr_set_const($db, 'WORKSHOP_OR_PLANNING_GROUPS', $planningGroupsValue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
		// WORKSHOP_OR_PLANNING_REFRESH_RATE (min 5)
		$refreshRate = max(5, GETPOSTINT('WORKSHOP_OR_PLANNING_REFRESH_RATE'));
		$res = dolibarr_set_const($db, 'WORKSHOP_OR_PLANNING_REFRESH_RATE', $refreshRate, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
		// Colors (stored without leading #)
		foreach (array('WORKSHOP_OR_ACTIVITYPLANNING_IMPROD_COLOR', 'WORKSHOP_OR_ACTIVITYPLANNING_INPROGRESS_COLOR', 'WORKSHOP_OR_ACTIVITYPLANNING_TOFINISH_COLOR') as $colorKey) {
			$colorVal = ltrim(GETPOST($colorKey, 'alphanohtml'), '#');
			$res = dolibarr_set_const($db, $colorKey, $colorVal, 'chaine', 0, '', $conf->entity);
			if (!($res > 0)) {
				$error++;
			}
		}
		// WORKSHOP_OR_EXCLUDE_IMPRO
		$excludeImpro = GETPOST('WORKSHOP_OR_EXCLUDE_IMPRO', 'array');
		$excludeImproValue = is_array($excludeImpro) ? implode(',', $excludeImpro) : '';
		$res = dolibarr_set_const($db, 'WORKSHOP_OR_EXCLUDE_IMPRO', $excludeImproValue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
		// WORKSHOP_OR_EXCLUDE_EFF
		$excludeEff = GETPOST('WORKSHOP_OR_EXCLUDE_EFF', 'array');
		$excludeEffValue = is_array($excludeEff) ? implode(',', $excludeEff) : '';
		$res = dolibarr_set_const($db, 'WORKSHOP_OR_EXCLUDE_EFF', $excludeEffValue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
		if (!$error) {
			setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	} elseif ($subtab == 'statuts') {
		$statusFields = array(
			'WORKSHOP_OR_STATUS_ON_CREATE',
			'WORKSHOP_OR_STATUS_ON_PLANNED',
			'WORKSHOP_OR_CHANGE_STATUS_ON_START',
			'WORKSHOP_OR_STATUS_ON_START',
			'WORKSHOP_OR_CHANGE_STATUS_ON_STOP',
			'WORKSHOP_OR_STATUS_ON_STOP',
			'WORKSHOP_OR_STATUT_BEFORE_CHECK',
			'WORKSHOP_OR_STATUT_AFTER_CHECK',
			'WORKSHOP_OR_STATUT_FOR_INVOICE',
			'WORKSHOP_OR_STATUT_AFTER_INVOICE',
		);
		foreach ($statusFields as $field) {
			$val = GETPOSTINT($field);
			$res = dolibarr_set_const($db, $field, $val, 'chaine', 0, '', $conf->entity);
			if (!($res > 0)) {
				$error++;
			}
		}
		if (!$error) {
			setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	} elseif ($subtab == 'commandes') {
		// Toggle fields
		foreach (array('WORKSHOP_OR_SUPPLIER_ORDER_LIMITED_TO_SERVICE', 'WORKSHOP_OR_SUPPLIER_ORDER_AUTO_VALIDATE', 'WORKSHOP_OR_SOFOR_CREATE_NEW_SUPPLIER_ODER_ANY_TIME') as $field) {
			$res = dolibarr_set_const($db, $field, GETPOSTINT($field), 'chaine', 0, '', $conf->entity);
			if (!($res > 0)) {
				$error++;
			}
		}
		// Multi-select: statuts permettant la commande
		$orderableStatus = GETPOST('WORKSHOP_OR_ORDERABLE_STATUS', 'array:int');
		$orderableStatusValue = is_array($orderableStatus) ? implode(',', array_map('intval', $orderableStatus)) : '';
		$res = dolibarr_set_const($db, 'WORKSHOP_OR_ORDERABLE_STATUS', $orderableStatusValue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
		if (!$error) {
			setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	} elseif ($subtab == 'facturation') {
		$billingFields = array(
			'WORKSHOP_OR_COEF_REFACT_PRESTA_EXT',
			'WORKSHOP_OR_MO_COEF_MIN',
			'WORKSHOP_OR_MO_COEF_MAX',
			'WORKSHOP_OR_PT_COEF_MIN',
			'WORKSHOP_OR_PT_COEF_MAX',
			'WORKSHOP_OR_PERCENT_PRICE_UP_PMP',
		);
		foreach ($billingFields as $field) {
			$val = price2num(GETPOST($field, 'alphanohtml'));
			$res = dolibarr_set_const($db, $field, $val, 'chaine', 0, '', $conf->entity);
			if (!($res > 0)) {
				$error++;
			}
		}
		if (!$error) {
			setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	} elseif ($subtab == 'comptabilite') {
		// Journal code (toujours sauvegardé)
		$val = GETPOST('WORKSHOP_OR_CODE_JOURNAL_VT', 'alphanohtml');
		$res = dolibarr_set_const($db, 'WORKSHOP_OR_CODE_JOURNAL_VT', $val, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
		// Comptes comptables (uniquement si module comptabilité avancée non activé)
		if (!isModEnabled('accounting')) {
			$accountingFields = array(
				'ACCOUNTING_ACCOUNT_CUSTOMER',
				'ACCOUNTING_ACCOUNT_CUSTOMER_INTRAGP',
				'ACCOUNTING_PRODUCT_SOLD_ACCOUNT',
				'ACCOUNTING_PRODUCT_SOLD_INTRA_ACCOUNT',
				'ACCOUNTING_PRODUCT_SOLD_EXPORT_ACCOUNT',
				'ACCOUNTING_SERVICE_SOLD_ACCOUNT',
				'ACCOUNTING_SERVICE_SOLD_INTRA_ACCOUNT',
				'ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT',
				'ACCOUNTING_VAT_SOLD_ACCOUNT',
			);
			foreach ($accountingFields as $field) {
				$val = GETPOST($field, 'alphanohtml');
				$res = dolibarr_set_const($db, $field, $val, 'chaine', 0, '', $conf->entity);
				if (!($res > 0)) {
					$error++;
				}
			}
		}
		if (!$error) {
			setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	}
}

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title    = "WorkshopSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-workshop page-admin_or');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = workshopAdminPrepareHead();
print dol_get_fiche_head($head, 'ordres_reparation', $langs->trans($title), -1, "workshop@workshop");

// --- Section 1: activation des Ordres de réparation (toujours visible) ---
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update_use_or">';
print '<input type="hidden" name="subtab" value="'.dol_escape_htmltag($subtab).'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>';
print $form->textwithpicto(
	$langs->trans('WORKSHOP_USE_OR'),
	$langs->trans('WORKSHOP_USE_ORTooltip')
);
print '</td>';
print '<td>';
$useOR = getDolGlobalInt('WORKSHOP_USE_OR');
print $form->selectyesno('WORKSHOP_USE_OR', $useOR, 1);
print '</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// --- Section 2: sous-onglets (uniquement si WORKSHOP_USE_OR est actif) ---
if (getDolGlobalInt('WORKSHOP_USE_OR')) {
	// Build sub-tabs
	$subhead = array();
	$sh = 0;
	$subhead[$sh][0] = $_SERVER["PHP_SELF"].'?subtab=general';
	$subhead[$sh][1] = $langs->trans('WorkshopORSubTabGeneral');
	$subhead[$sh][2] = 'general';
	$sh++;
	$subhead[$sh][0] = $_SERVER["PHP_SELF"].'?subtab=planning';
	$subhead[$sh][1] = $langs->trans('WorkshopORSubTabPlanning');
	$subhead[$sh][2] = 'planning';
	$sh++;
	$subhead[$sh][0] = $_SERVER["PHP_SELF"].'?subtab=statuts';
	$subhead[$sh][1] = $langs->trans('WorkshopORSubTabStatuts');
	$subhead[$sh][2] = 'statuts';
	$sh++;
	$subhead[$sh][0] = $_SERVER["PHP_SELF"].'?subtab=commandes';
	$subhead[$sh][1] = $langs->trans('WorkshopORSubTabCommandes');
	$subhead[$sh][2] = 'commandes';
	$sh++;
	$subhead[$sh][0] = $_SERVER["PHP_SELF"].'?subtab=facturation';
	$subhead[$sh][1] = $langs->trans('WorkshopORSubTabFacturation');
	$subhead[$sh][2] = 'facturation';
	$sh++;
	$subhead[$sh][0] = $_SERVER["PHP_SELF"].'?subtab=comptabilite';
	$subhead[$sh][1] = $langs->trans('WorkshopORSubTabComptabilite');
	$subhead[$sh][2] = 'comptabilite';
	$sh++;

	print dol_get_fiche_head($subhead, $subtab, '', -1, '');

	if ($subtab == 'general') {
		// Build mechanic group select
		$sqlGrp = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."usergroup WHERE entity IN (0, ".((int) $conf->entity).") ORDER BY nom";
		$resGrp  = $db->query($sqlGrp);
		$grpOpts = '<option value="0">--- '.$langs->trans('None').' ---</option>';
		if ($resGrp) {
			while ($objGrp = $db->fetch_object($resGrp)) {
				$sel = (getDolGlobalInt('WORKSHOP_MECHANIC_GROUP') == $objGrp->rowid) ? ' selected="selected"' : '';
				$grpOpts .= '<option value="'.$objGrp->rowid.'"'.$sel.'>'.dol_htmlentities($objGrp->nom).'</option>';
			}
		}

		// Build alert statuts multi-select options
		$currentAlertStatuts = array_filter(explode(',', getDolGlobalString('WORKSHOP_OR_ALERT_STATUTS')));
		$sqlStatuts = "SELECT rowid, label FROM ".$db->prefix()."workshop_operationorder_status WHERE status = 1 ORDER BY rang ASC";
		$resStatuts = $db->query($sqlStatuts);
		$statuts_options = '';
		if ($resStatuts) {
			while ($objStatut = $db->fetch_object($resStatuts)) {
				$sel = in_array((string) $objStatut->rowid, $currentAlertStatuts) ? ' selected="selected"' : '';
				$statuts_options .= '<option value="'.$objStatut->rowid.'"'.$sel.'>'.dol_htmlentities($objStatut->label).'</option>';
			}
		}

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_subtab">';
		print '<input type="hidden" name="subtab" value="general">';

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Parameter").'</td>';
		print '<td>'.$langs->trans("Value").'</td>';
		print '</tr>';

		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto(
			$langs->trans('WorkshopMechanicGroup'),
			$langs->trans('WorkshopMechanicGroupHelp')
		);
		print '</td>';
		print '<td>';
		print '<select name="WORKSHOP_MECHANIC_GROUP" id="WORKSHOP_MECHANIC_GROUP" class="flat">'.$grpOpts.'</select>';
		print '</td>';
		print '</tr>';

		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto(
			$langs->trans('WorkshopORAlertStatuts'),
			$langs->trans('WorkshopORAlertStatutsHelp')
		);
		print '</td>';
		print '<td>';
		if ($statuts_options) {
			print '<select name="WORKSHOP_OR_ALERT_STATUTS[]" id="WORKSHOP_OR_ALERT_STATUTS" class="flat" multiple style="height:100px">'.$statuts_options.'</select>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans('WorkshopNoStatusDefined').'</span>';
		}
		print '</td>';
		print '</tr>';

		print '</table>';

		print '<div class="tabsAction">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
		print '</div>';

		print '</form>';

		// --- Section : modèles de numérotation ---
		$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

		print load_fiche_titre($langs->trans("WorkshopORNumberingModules"), '', '');

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Name").'</td>';
		print '<td>'.$langs->trans("Description").'</td>';
		print '<td class="nowrap">'.$langs->trans("Example").'</td>';
		print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
		print '<td class="center" width="16">'.$langs->trans("ShortInfo").'</td>';
		print '</tr>'."\n";

		clearstatcache();
		foreach ($dirmodels as $reldir) {
			$dir = dol_buildpath($reldir."core/modules/workshopor/");
			if (!is_dir($dir)) {
				continue;
			}
			$handle = opendir($dir);
			if (!is_resource($handle)) {
				continue;
			}
			while (($file = readdir($handle)) !== false) {
				if (substr($file, 0, 15) !== 'mod_workshopor_' || substr($file, -4) !== '.php') {
					continue;
				}
				$file = substr($file, 0, -4);
				require_once $dir.$file.'.php';
				$module = new $file($db);
				if ($module->version == 'development'  && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
					continue;
				}
				if ($module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
					continue;
				}
				if (!$module->isEnabled()) {
					continue;
				}
				print '<tr class="oddeven"><td>'.$module->name."</td><td>\n";
				print $module->info($langs);
				print '</td>';
				print '<td class="nowrap">';
				$tmp = $module->getExample();
				if (preg_match('/^Error/', $tmp)) {
					print '<div class="error">'.$langs->trans($tmp).'</div>';
				} elseif ($tmp == 'NotConfigured') {
					print $langs->trans($tmp);
				} else {
					print $tmp;
				}
				print '</td>'."\n";
				print '<td class="center">';
				if (getDolGlobalString('WORKSHOP_OR_ADDON') == $file) {
					print img_picto($langs->trans("Activated"), 'switch_on');
				} else {
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&subtab=general&value='.urlencode($file).'&token='.newToken().'">';
					print img_picto($langs->trans("Disabled"), 'switch_off');
					print '</a>';
				}
				print '</td>';
				// Info tooltip
				$workshopor = new Operationorder($db);
				$workshopor->initAsSpecimenCommon();
				$htmltooltip  = $langs->trans("Version").': <b>'.$module->getVersion().'</b><br>';
				$nextval = $module->getNextValue($workshopor);
				if ("$nextval" != $langs->trans("NotAvailable")) {
					$htmltooltip .= $langs->trans("NextValue").': ';
					$htmltooltip .= (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured') ? $langs->trans($nextval) : $nextval;
					$htmltooltip .= '<br>';
				}
				print '<td class="center">';
				print $form->textwithpicto('', $htmltooltip, 1, 0);
				print '</td>';
				print "</tr>\n";
			}
			closedir($handle);
		}
		print "</table><br>\n";

		// --- Section : modèles de documents PDF ---
		print load_fiche_titre($langs->trans("WorkshopORDocumentModels"), '', '');

		$def = array();
		$sqldef  = "SELECT nom FROM ".$db->prefix()."document_model";
		$sqldef .= " WHERE type = 'workshopor' AND entity = ".$conf->entity;
		$resdef = $db->query($sqldef);
		if ($resdef) {
			while ($rowdef = $db->fetch_array($resdef)) {
				$def[] = $rowdef[0];
			}
		}

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Name").'</td>';
		print '<td>'.$langs->trans("Description").'</td>';
		print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
		print '<td class="center" width="60">'.$langs->trans("Default").'</td>';
		print '<td class="center" width="32">'.$langs->trans("ShortInfo").'</td>';
		print '<td class="center" width="32">'.$langs->trans("Preview").'</td>';
		print "</tr>\n";

		clearstatcache();
		foreach ($dirmodels as $reldir) {
			foreach (array('', '/doc') as $valdir) {
				$dir = dol_buildpath($reldir."core/modules/workshopor".$valdir);
				if (!is_dir($dir)) {
					continue;
				}
				$handle = opendir($dir);
				if (!is_resource($handle)) {
					continue;
				}
				$filelist = array();
				while (($file = readdir($handle)) !== false) {
					$filelist[] = $file;
				}
				closedir($handle);
				arsort($filelist);
				foreach ($filelist as $file) {
					if (!preg_match('/\.modules\.php$/i', $file) || !preg_match('/^(pdf_|doc_)/', $file)) {
						continue;
					}
					if (!file_exists($dir.'/'.$file)) {
						continue;
					}
					$name      = substr($file, 4, dol_strlen($file) - 16);
					$classname = substr($file, 0, dol_strlen($file) - 12);
					require_once $dir.'/'.$file;
					$module = new $classname($db);
					if ($module->version == 'development'  && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
						continue;
					}
					if ($module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
						continue;
					}
					print '<tr class="oddeven"><td width="100">';
					print (empty($module->name) ? $name : $module->name);
					print "</td><td>\n";
					print method_exists($module, 'info') ? $module->info($langs) : $module->description;
					print '</td>';
					// Active
					if (in_array($name, $def)) {
						print '<td class="center"><a href="'.$_SERVER["PHP_SELF"].'?action=del&subtab=general&value='.urlencode($name).'&token='.newToken().'">';
						print img_picto($langs->trans("Enabled"), 'switch_on');
						print '</a></td>';
					} else {
						print '<td class="center"><a href="'.$_SERVER["PHP_SELF"].'?action=set&subtab=general&value='.urlencode($name).'&scan_dir='.urlencode($module->scandir).'&label='.urlencode($module->name).'&token='.newToken().'">';
						print img_picto($langs->trans("Disabled"), 'switch_off');
						print '</a></td>';
					}
					// Default
					print '<td class="center">';
					if (getDolGlobalString('WORKSHOP_OR_ADDON_PDF') == $name) {
						print img_picto($langs->trans("Default"), 'on');
					} else {
						print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&subtab=general&value='.urlencode($name).'&scan_dir='.urlencode($module->scandir).'&label='.urlencode($module->name).'&token='.newToken().'">';
						print img_picto($langs->trans("SetAsDefault"), 'off');
						print '</a>';
					}
					print '</td>';
					// Info tooltip
					$htmltooltip  = $langs->trans("Name").': '.$module->name;
					$htmltooltip .= '<br>'.$langs->trans("Type").': '.($module->type ?: $langs->trans("Unknown"));
					if ($module->type == 'pdf') {
						$htmltooltip .= '<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
					}
					$htmltooltip .= '<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
					$htmltooltip .= '<br>'.$langs->trans("Logo").': '.yn($module->option_logo, 1, 1);
					$htmltooltip .= '<br>'.$langs->trans("MultiLanguage").': '.yn($module->option_multilang, 1, 1);
					$htmltooltip .= '<br>'.$langs->trans("WatermarkOnDraftInvoices").': '.yn($module->option_draft_watermark, 1, 1);
					print '<td class="center">'.$form->textwithpicto('', $htmltooltip, 1, 0).'</td>';
					// Preview
					print '<td class="center">';
					if ($module->type == 'pdf') {
						print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&subtab=general&module='.urlencode($name).'">'.img_object($langs->trans("Preview"), 'bill').'</a>';
					} else {
						print img_object($langs->trans("PreviewNotAvailable"), 'generic');
					}
					print '</td>';
					print "</tr>\n";
				}
			}
		}
		print '</table><br>';
	}

	if ($subtab == 'planning') {
		// Set color defaults if not yet configured
		if (!getDolGlobalString('WORKSHOP_OR_ACTIVITYPLANNING_IMPROD_COLOR')) {
			dolibarr_set_const($db, 'WORKSHOP_OR_ACTIVITYPLANNING_IMPROD_COLOR', '4f93d6', 'chaine', 0, '', $conf->entity);
			$conf->global->WORKSHOP_OR_ACTIVITYPLANNING_IMPROD_COLOR = '4f93d6';
		}
		if (!getDolGlobalString('WORKSHOP_OR_ACTIVITYPLANNING_INPROGRESS_COLOR')) {
			dolibarr_set_const($db, 'WORKSHOP_OR_ACTIVITYPLANNING_INPROGRESS_COLOR', 'ff00ff', 'chaine', 0, '', $conf->entity);
			$conf->global->WORKSHOP_OR_ACTIVITYPLANNING_INPROGRESS_COLOR = 'ff00ff';
		}
		if (!getDolGlobalString('WORKSHOP_OR_ACTIVITYPLANNING_TOFINISH_COLOR')) {
			dolibarr_set_const($db, 'WORKSHOP_OR_ACTIVITYPLANNING_TOFINISH_COLOR', 'ff8800', 'chaine', 0, '', $conf->entity);
			$conf->global->WORKSHOP_OR_ACTIVITYPLANNING_TOFINISH_COLOR = 'ff8800';
		}

		// Build planning groups multi-select options
		$currentPlanningGroups = array_filter(explode(',', getDolGlobalString('WORKSHOP_OR_PLANNING_GROUPS')));
		$sqlPlanGrp = "SELECT rowid, nom FROM ".$db->prefix()."usergroup WHERE entity IN (0, ".((int) $conf->entity).") ORDER BY nom";
		$resPlanGrp = $db->query($sqlPlanGrp);
		$planningGrpOpts = '';
		if ($resPlanGrp) {
			while ($objGrp = $db->fetch_object($resPlanGrp)) {
				$sel = in_array((string) $objGrp->rowid, $currentPlanningGroups) ? ' selected="selected"' : '';
				$planningGrpOpts .= '<option value="'.$objGrp->rowid.'"'.$sel.'>'.dol_htmlentities($objGrp->nom).'</option>';
			}
		}

		// Build service types for Pointage Exclus
		$sqlSvc = "SELECT rowid, label, code FROM ".$db->prefix()."workshop_c_servicetype WHERE active = 1 ORDER BY label ASC";
		$resSvc = $db->query($sqlSvc);
		$TServices = array();
		if ($resSvc) {
			while ($objSvc = $db->fetch_object($resSvc)) {
				$key = $objSvc->code ?: (string) $objSvc->rowid;
				$TServices[$key] = $objSvc->label;
			}
		}

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_subtab">';
		print '<input type="hidden" name="subtab" value="planning">';

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Parameter").'</td>';
		print '<td>'.$langs->trans("Value").'</td>';
		print '</tr>';

		// Groupes utilisateurs planning
		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto($langs->trans('WorkshopORPlanningGroups'), $langs->trans('WorkshopORPlanningGroupsHelp'));
		print '</td>';
		print '<td>';
		if ($planningGrpOpts) {
			print '<select name="WORKSHOP_OR_PLANNING_GROUPS[]" id="WORKSHOP_OR_PLANNING_GROUPS" class="flat" multiple style="height:100px">'.$planningGrpOpts.'</select>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans('WorkshopNoGroupDefined').'</span>';
		}
		print '</td>';
		print '</tr>';

		// Rafraîchissement automatique
		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto($langs->trans('WorkshopORPlanningRefreshRate'), $langs->trans('WorkshopORPlanningRefreshRateHelp'));
		print '</td>';
		print '<td>';
		print '<input type="number" name="WORKSHOP_OR_PLANNING_REFRESH_RATE" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_PLANNING_REFRESH_RATE', '30')).'" min="5" style="width:80px">';
		print ' '.$langs->trans('WorkshopSeconds');
		print '</td>';
		print '</tr>';

		// Couleur activités improductives
		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto($langs->trans('WorkshopORActivityImprodColor'), $langs->trans('WorkshopORActivityImprodColorHelp'));
		print '</td>';
		print '<td>';
		print '<input type="color" name="WORKSHOP_OR_ACTIVITYPLANNING_IMPROD_COLOR" class="flat" value="#'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_ACTIVITYPLANNING_IMPROD_COLOR', '4f93d6')).'">';
		print '</td>';
		print '</tr>';

		// Couleur tâche en cours
		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto($langs->trans('WorkshopORActivityInProgressColor'), $langs->trans('WorkshopORActivityInProgressColorHelp'));
		print '</td>';
		print '<td>';
		print '<input type="color" name="WORKSHOP_OR_ACTIVITYPLANNING_INPROGRESS_COLOR" class="flat" value="#'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_ACTIVITYPLANNING_INPROGRESS_COLOR', 'ff00ff')).'">';
		print '</td>';
		print '</tr>';

		// Couleur tâche à finir
		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto($langs->trans('WorkshopORActivityToFinishColor'), $langs->trans('WorkshopORActivityToFinishColorHelp'));
		print '</td>';
		print '<td>';
		print '<input type="color" name="WORKSHOP_OR_ACTIVITYPLANNING_TOFINISH_COLOR" class="flat" value="#'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_ACTIVITYPLANNING_TOFINISH_COLOR', 'ff8800')).'">';
		print '</td>';
		print '</tr>';

		// Pointage Exclus des temps travaillés
		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto($langs->trans('WorkshopORExcludeImpro'), $langs->trans('WorkshopORExcludeImproHelp'));
		print '</td>';
		print '<td>';
		$excludeImpro = array_filter(explode(',', getDolGlobalString('WORKSHOP_OR_EXCLUDE_IMPRO')));
		print $form->multiselectarray('WORKSHOP_OR_EXCLUDE_IMPRO', $TServices, $excludeImpro, 0, 0, 'flat', 0, 0, '', '', false);
		print '</td>';
		print '</tr>';

		// Pointage Exclus des temps pour calcul de l'efficacité
		print '<tr class="oddeven">';
		print '<td>';
		print $form->textwithpicto($langs->trans('WorkshopORExcludeEff'), $langs->trans('WorkshopORExcludeEffHelp'));
		print '</td>';
		print '<td>';
		$excludeEff = array_filter(explode(',', getDolGlobalString('WORKSHOP_OR_EXCLUDE_EFF')));
		print $form->multiselectarray('WORKSHOP_OR_EXCLUDE_EFF', $TServices, $excludeEff, 0, 0, 'flat', 0, 0, '', '', false);
		print '</td>';
		print '</tr>';

		print '</table>';

		print '<div class="tabsAction">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
		print '</div>';

		print '</form>';
	}

	if ($subtab == 'statuts') {
		// Load available statuses (active only, ordered by rank)
		$sqlORS = "SELECT rowid, label FROM ".$db->prefix()."workshop_operationorder_status WHERE status = 1 ORDER BY rang ASC";
		$resORS = $db->query($sqlORS);
		$TStatusAvailable = array(0 => '--- '.$langs->trans('None').' ---');
		if ($resORS) {
			while ($objORS = $db->fetch_object($resORS)) {
				$TStatusAvailable[$objORS->rowid] = $objORS->label;
			}
		}

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_subtab">';
		print '<input type="hidden" name="subtab" value="statuts">';

		// ---- Section : Workflow des statuts ----
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td colspan="2">'.$langs->trans('WorkshopORStatusesWorkflow').'</td>';
		print '</tr>';

		// Statut à la création
		print '<tr class="oddeven">';
		print '<td>'.$form->textwithpicto($langs->trans('WorkshopORStatusOnCreate'), $langs->trans('WorkshopORStatusOnCreateHelp')).'</td>';
		print '<td>'.$form->selectarray('WORKSHOP_OR_STATUS_ON_CREATE', $TStatusAvailable, getDolGlobalInt('WORKSHOP_OR_STATUS_ON_CREATE'), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1).'</td>';
		print '</tr>';

		// Statut à la planification
		print '<tr class="oddeven">';
		print '<td>'.$form->textwithpicto($langs->trans('WorkshopORStatusOnPlanned'), $langs->trans('WorkshopORStatusOnPlannedHelp')).'</td>';
		print '<td>'.$form->selectarray('WORKSHOP_OR_STATUS_ON_PLANNED', $TStatusAvailable, getDolGlobalInt('WORKSHOP_OR_STATUS_ON_PLANNED'), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1).'</td>';
		print '</tr>';

		// Toggle : changer statut au démarrage d'une tâche
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORChangeStatusOnStart').'</td>';
		print '<td>'.$form->selectyesno('WORKSHOP_OR_CHANGE_STATUS_ON_START', getDolGlobalInt('WORKSHOP_OR_CHANGE_STATUS_ON_START'), 1).'</td>';
		print '</tr>';

		// Statut lors de la réalisation de tâches
		print '<tr class="oddeven">';
		print '<td>'.$form->textwithpicto($langs->trans('WorkshopORStatusOnStart'), $langs->trans('WorkshopORStatusOnStartHelp')).'</td>';
		print '<td>'.$form->selectarray('WORKSHOP_OR_STATUS_ON_START', $TStatusAvailable, getDolGlobalInt('WORKSHOP_OR_STATUS_ON_START'), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1).'</td>';
		print '</tr>';

		// Toggle : changer statut à l'arrêt d'une tâche
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORChangeStatusOnStop').'</td>';
		print '<td>'.$form->selectyesno('WORKSHOP_OR_CHANGE_STATUS_ON_STOP', getDolGlobalInt('WORKSHOP_OR_CHANGE_STATUS_ON_STOP'), 1).'</td>';
		print '</tr>';

		// Statut à l'arrêt des tâches
		print '<tr class="oddeven">';
		print '<td>'.$form->textwithpicto($langs->trans('WorkshopORStatusOnStop'), $langs->trans('WorkshopORStatusOnStopHelp')).'</td>';
		print '<td>'.$form->selectarray('WORKSHOP_OR_STATUS_ON_STOP', $TStatusAvailable, getDolGlobalInt('WORKSHOP_OR_STATUS_ON_STOP'), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1).'</td>';
		print '</tr>';

		print '</table>';

		// ---- Section : Check et Facturation ----
		print '<br>';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td colspan="2">'.$langs->trans('WorkshopORStatusesCheckInvoice').'</td>';
		print '</tr>';

		// Statut précédent le Check OR
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORStatutBeforeCheck').'</td>';
		print '<td>'.$form->selectarray('WORKSHOP_OR_STATUT_BEFORE_CHECK', $TStatusAvailable, getDolGlobalInt('WORKSHOP_OR_STATUT_BEFORE_CHECK'), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1).'</td>';
		print '</tr>';

		// Statut suivant le Check OR
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORStatutAfterCheck').'</td>';
		print '<td>'.$form->selectarray('WORKSHOP_OR_STATUT_AFTER_CHECK', $TStatusAvailable, getDolGlobalInt('WORKSHOP_OR_STATUT_AFTER_CHECK'), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1).'</td>';
		print '</tr>';

		// Statut depuis lequel on peut facturer
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORStatutForInvoice').'</td>';
		print '<td>'.$form->selectarray('WORKSHOP_OR_STATUT_FOR_INVOICE', $TStatusAvailable, getDolGlobalInt('WORKSHOP_OR_STATUT_FOR_INVOICE'), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1).'</td>';
		print '</tr>';

		// Statut de l'OR après création de la facture
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORStatutAfterInvoice').'</td>';
		print '<td>'.$form->selectarray('WORKSHOP_OR_STATUT_AFTER_INVOICE', $TStatusAvailable, getDolGlobalInt('WORKSHOP_OR_STATUT_AFTER_INVOICE'), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1).'</td>';
		print '</tr>';

		print '</table>';

		print '<div class="tabsAction">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
		print '</div>';

		print '</form>';
	}

	if ($subtab == 'commandes') {
		// Load available statuses (active only, ordered by rank)
		$sqlORS = "SELECT rowid, label FROM ".$db->prefix()."workshop_operationorder_status WHERE status = 1 ORDER BY rang ASC";
		$resORS = $db->query($sqlORS);
		$TStatusAvailable = array();
		$currentOrderableStatus = array_filter(explode(',', getDolGlobalString('WORKSHOP_OR_ORDERABLE_STATUS')));
		$orderableStatusOpts = '';
		if ($resORS) {
			while ($objORS = $db->fetch_object($resORS)) {
				$sel = in_array((string) $objORS->rowid, $currentOrderableStatus) ? ' selected="selected"' : '';
				$orderableStatusOpts .= '<option value="'.$objORS->rowid.'"'.$sel.'>'.dol_htmlentities($objORS->label).'</option>';
			}
		}

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_subtab">';
		print '<input type="hidden" name="subtab" value="commandes">';

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Parameter").'</td>';
		print '<td>'.$langs->trans("Value").'</td>';
		print '</tr>';

		// Limiter aux services
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORSupplierOrderLimitedToService').'</td>';
		print '<td>'.$form->selectyesno('WORKSHOP_OR_SUPPLIER_ORDER_LIMITED_TO_SERVICE', getDolGlobalInt('WORKSHOP_OR_SUPPLIER_ORDER_LIMITED_TO_SERVICE'), 1).'</td>';
		print '</tr>';

		// Statuts permettant de commander les pièces manquantes
		print '<tr class="oddeven">';
		print '<td>'.$form->textwithpicto($langs->trans('WorkshopOROrderableStatus'), $langs->trans('WorkshopOROrderableStatusHelp')).'</td>';
		print '<td>';
		if ($orderableStatusOpts) {
			print '<select name="WORKSHOP_OR_ORDERABLE_STATUS[]" id="WORKSHOP_OR_ORDERABLE_STATUS" class="flat" multiple style="height:100px">'.$orderableStatusOpts.'</select>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans('WorkshopNoStatusDefined').'</span>';
		}
		print '</td>';
		print '</tr>';

		// Valider automatiquement les commandes fournisseurs
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORSupplierOrderAutoValidate').'</td>';
		print '<td>'.$form->selectyesno('WORKSHOP_OR_SUPPLIER_ORDER_AUTO_VALIDATE', getDolGlobalInt('WORKSHOP_OR_SUPPLIER_ORDER_AUTO_VALIDATE'), 1).'</td>';
		print '</tr>';

		// Créer commande fournisseur brouillon pour chaque commande client
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORSoforCreateNewSupplierOrderAnyTime').'</td>';
		print '<td>'.$form->selectyesno('WORKSHOP_OR_SOFOR_CREATE_NEW_SUPPLIER_ODER_ANY_TIME', getDolGlobalInt('WORKSHOP_OR_SOFOR_CREATE_NEW_SUPPLIER_ODER_ANY_TIME'), 1).'</td>';
		print '</tr>';

		print '</table>';

		print '<div class="tabsAction">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
		print '</div>';

		print '</form>';
	}

	if ($subtab == 'facturation') {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_subtab">';
		print '<input type="hidden" name="subtab" value="facturation">';

		// ---- Section : Coefficients de facturation ----
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td colspan="2">'.$langs->trans('WorkshopORBillingCoefficients').'</td>';
		print '</tr>';

		// Coefficient sous-traitance
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORCoefRefactPrestaExt').'</td>';
		print '<td><input type="number" name="WORKSHOP_OR_COEF_REFACT_PRESTA_EXT" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_COEF_REFACT_PRESTA_EXT', '0')).'" step="0.01" min="0" style="width:100px"> %</td>';
		print '</tr>';

		// MO min
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORMoCoefMin').'</td>';
		print '<td><input type="number" name="WORKSHOP_OR_MO_COEF_MIN" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_MO_COEF_MIN', '0')).'" step="0.01" style="width:100px"> %</td>';
		print '</tr>';

		// MO max
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORMoCoefMax').'</td>';
		print '<td><input type="number" name="WORKSHOP_OR_MO_COEF_MAX" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_MO_COEF_MAX', '0')).'" step="0.01" style="width:100px"> %</td>';
		print '</tr>';

		// Pièce min
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORPtCoefMin').'</td>';
		print '<td><input type="number" name="WORKSHOP_OR_PT_COEF_MIN" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_PT_COEF_MIN', '0')).'" step="0.01" style="width:100px"> %</td>';
		print '</tr>';

		// Pièce max
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORPtCoefMax').'</td>';
		print '<td><input type="number" name="WORKSHOP_OR_PT_COEF_MAX" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_PT_COEF_MAX', '0')).'" step="0.01" style="width:100px"> %</td>';
		print '</tr>';

		print '</table>';

		// ---- Section : Politique de prix (PRODUIT_MULTIPRICES uniquement) ----
		if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
			print '<br>';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<td colspan="2">'.$langs->trans('WorkshopORPricePolicies').'</td>';
			print '</tr>';

			// % PMP → prix de vente
			print '<tr class="oddeven">';
			print '<td>'.$form->textwithpicto($langs->trans('WorkshopORPercentPriceUpPmp'), $langs->trans('WorkshopORPercentPriceUpPmpHelp')).'</td>';
			print '<td><input type="number" name="WORKSHOP_OR_PERCENT_PRICE_UP_PMP" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_PERCENT_PRICE_UP_PMP', '0')).'" step="0.01" style="width:100px"> %</td>';
			print '</tr>';

			print '</table>';
		} else {
			// Champ caché pour ne pas perdre la valeur si PRODUIT_MULTIPRICES est désactivé
			print '<input type="hidden" name="WORKSHOP_OR_PERCENT_PRICE_UP_PMP" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_PERCENT_PRICE_UP_PMP', '0')).'">';
		}

		print '<div class="tabsAction">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
		print '</div>';

		print '</form>';
	}

	if ($subtab == 'comptabilite') {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_subtab">';
		print '<input type="hidden" name="subtab" value="comptabilite">';

		// ---- Section : Journal de vente ----
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Parameter").'</td>';
		print '<td>'.$langs->trans("Value").'</td>';
		print '</tr>';

		print '<tr class="oddeven">';
		print '<td>'.$langs->trans('WorkshopORCodeJournalVt').'</td>';
		print '<td><input type="text" name="WORKSHOP_OR_CODE_JOURNAL_VT" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('WORKSHOP_OR_CODE_JOURNAL_VT')).'" style="width:120px"></td>';
		print '</tr>';

		print '</table>';

		// ---- Section : Comptes comptables (si module compta avancée inactif) ----
		print '<br>';
		if (!isModEnabled('accounting')) {
			$accountingRows = array(
				'ACCOUNTING_ACCOUNT_CUSTOMER'           => 'WorkshopORAccountCustomer',
				'ACCOUNTING_ACCOUNT_CUSTOMER_INTRAGP'   => 'WorkshopORAccountCustomerIntragp',
				'ACCOUNTING_PRODUCT_SOLD_ACCOUNT'       => 'WorkshopORAccountProductSold',
				'ACCOUNTING_PRODUCT_SOLD_INTRA_ACCOUNT' => 'WorkshopORAccountProductSoldIntra',
				'ACCOUNTING_PRODUCT_SOLD_EXPORT_ACCOUNT'=> 'WorkshopORAccountProductSoldExport',
				'ACCOUNTING_SERVICE_SOLD_ACCOUNT'       => 'WorkshopORAccountServiceSold',
				'ACCOUNTING_SERVICE_SOLD_INTRA_ACCOUNT' => 'WorkshopORAccountServiceSoldIntra',
				'ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT'=> 'WorkshopORAccountServiceSoldExport',
				'ACCOUNTING_VAT_SOLD_ACCOUNT'           => 'WorkshopORAccountVatSold',
			);

			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<td colspan="2">'.$langs->trans('WorkshopORAccountancySection').'</td>';
			print '</tr>';

			foreach ($accountingRows as $constKey => $langKey) {
				print '<tr class="oddeven">';
				print '<td>'.$langs->trans($langKey).'</td>';
				print '<td><input type="text" name="'.$constKey.'" class="flat" value="'.dol_escape_htmltag(getDolGlobalString($constKey)).'" style="width:150px"></td>';
				print '</tr>';
			}

			print '</table>';
		} else {
			print '<div class="info">'.$langs->trans('WorkshopORAccountancyManagedByModule').'</div>';
		}

		print '<div class="tabsAction">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
		print '</div>';

		print '</form>';
	}

	print dol_get_fiche_end();
}

print dol_get_fiche_end();

llxFooter();
$db->close();
