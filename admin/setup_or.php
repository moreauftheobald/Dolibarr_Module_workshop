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
		$workshopor->initAsSpecimen();
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

	print dol_get_fiche_head($subhead, $subtab, '', 0, '');

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
				$workshopor->initAsSpecimen();
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

	print dol_get_fiche_end();
}

print dol_get_fiche_end();

llxFooter();
$db->close();
