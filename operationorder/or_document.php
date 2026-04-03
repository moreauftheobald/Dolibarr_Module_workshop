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
 * \file        operationorder/or_document.php
 * \ingroup     workshop
 * \brief       Onglet de gestion des fichiers joints à un Ordre de Réparation
 */

// Load Dolibarr environment
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/workshop/class/operationorder.class.php');
dol_include_once('/workshop/lib/workshop_operationorder.lib.php');

$langs->loadLangs(array('workshop@workshop', 'other'));

// Paramètres
$action   = GETPOST('action', 'aZ09');
$confirm  = GETPOST('confirm', 'alpha');
$id       = GETPOSTINT('id');
$ref      = GETPOST('ref', 'alpha');

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page == -1) {
	$page = 0;
}
$offset   = $conf->liste_limit * $page;
if (!$sortorder) {
	$sortorder = 'ASC';
}
if (!$sortfield) {
	$sortfield = 'name';
}

// Objets techniques
$object      = new Operationorder($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('operationorderdocument', 'globalcard'));

$extrafields->fetch_name_optionals_label($object->table_element);

// Chargement de l'objet (fetch + fetch_thirdparty)
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Répertoire d'upload
$upload_dir = '';
if ($id > 0 || !empty($ref)) {
	$upload_dir = (isset($conf->workshop->multidir_output[$object->entity]) ? $conf->workshop->multidir_output[$object->entity] : $conf->workshop->dir_output)
		.'/operationorder/'.dol_sanitizeFileName($object->ref);
}

// Droits
$permissiontoread = $user->hasRight('workshop', 'operationorders', 'read');
$permissiontoadd  = $user->hasRight('workshop', 'operationorders', 'write');

// Sécurité
if (!isModEnabled('workshop')) {
	accessforbidden();
}
if (!$permissiontoread) {
	accessforbidden();
}
if (empty($object->id)) {
	accessforbidden();
}

/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';


/*
 * Vue
 */

$form = new Form($db);

$title    = $langs->trans('OperationOrder').' - '.$langs->trans('Documents');
$help_url = '';
llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-workshop page-or_document');

$head = operationorderPrepareHead($object);

print dol_get_fiche_head($head, 'document', $langs->trans('OperationOrder'), -1, 'fa-tools', 0, '', '', 0, '', 1);

// Bandeau objet
$linkback = '<a href="'.dol_buildpath('/workshop/operationorder/or_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '');

// Compteurs fichiers
$filearray = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);
$totalsize = 0;
foreach ($filearray as $file) {
	$totalsize += $file['size'];
}

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('NbOfAttachedFiles').'</td><td colspan="3">'.count($filearray).'</td></tr>';
print '<tr><td>'.$langs->trans('TotalSizeOfAttachedFiles').'</td><td colspan="3">'.$totalsize.' '.$langs->trans('bytes').'</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

$modulepart           = 'workshop';
$param                = '&id='.$object->id;
$relativepathwithnofile = 'operationorder/'.dol_sanitizeFileName($object->ref).'/';

include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';

llxFooter();
$db->close();
