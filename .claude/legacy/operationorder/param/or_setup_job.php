<?php
/* Copyright (C) 2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
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
 *    \file       thtimesheet_card.php
 *        \ingroup    theobald
 *        \brief      Page to create/edit/view thtimesheet
 */

//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');				// Do not create database handler $db
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');				// Do not load object $user
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');				// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');				// Do not load object $langs
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');		// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');		// Do not check injection attack on POST parameters
//if (! defined('NOCSRFCHECK'))              define('NOCSRFCHECK', '1');				// Do not check CSRF attack (test on referer + on token).
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');				// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');				// Do not check style html tag into posted data
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');				// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');				// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');       	  	// Do not load ajax.lib.php library
//if (! defined("NOLOGIN"))                  define("NOLOGIN", '1');					// If this page is public (can be called outside logged session). This include the NOIPCHECK too.
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');					// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined("MAIN_LANG_DEFAULT"))        define('MAIN_LANG_DEFAULT', 'auto');					// Force lang to a particular value
//if (! defined("MAIN_AUTHENTICATION_MODE")) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined("NOREDIRECTBYMAINTOLOGIN"))  define('NOREDIRECTBYMAINTOLOGIN', 1);		// The main.inc.php does not make a redirect if not logged, instead show simple error message
//if (! defined("FORCECSP"))                 define('FORCECSP', 'none');				// Disable all Content Security Policies
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');		// Force use of CSRF protection with tokens even for GET
//if (! defined('NOBROWSERNOTIF'))     		 define('NOBROWSERNOTIF', '1');				// Disable browser notification
//if (! defined('NOSESSION'))     		     define('NOSESSION', '1');				    // Disable session

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/operationorder/lib/operationorder.lib.php');

// Load translation files required by the page
$langs->load("operationorder@operationorder");

// Get parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$rowid = GETPOST('rowid', 'int');
$ref = GETPOST('ref', 'alpha');
$label = GETPOST('label', 'alpha');
$file_uplaoded = GETPOST('file_uplaoded', 'alpha');
$tosell = GETPOST('tosell', 'int');
$tobuy = GETPOST('tobuy', 'int');
$doc_obl = GETPOST('doc_obl', 'alpha');
$doc_obl_sel = GETPOST('doc_obl_sel', 'alpha');
$page = GETPOST('page', 'int');

if (!$user->hasRight('produit', 'read')) {
	accessforbidden();
}

if (empty(isModEnabled("operationorder"))) accessforbidden();

$hookmanager->initHooks(array('operationorderparam', 'globalcard')); // Note that conf->hooks_modules contains array


$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	if ($confirm=='yes') {
		if (($action == 'confirmnew') || ($action == 'confirmedit' && !empty($rowid)) || ($action == 'confirmdelete' && !empty($rowid))) {
			if (($action == 'confirmnew') || ($action == 'confirmedit')) {
				if (empty($ref)) {
					$error++;
					$errors[] = $langs->trans("MissingCode");
				}
				if (empty($label)) {
					$error++;
					$errors[] = $langs->trans("Missinglabel");
				}
			}
		}
		if (empty($error)) {
			if ($action == 'confirmnew') {
				$product = new Product($db);
				$product->fetch('', $ref);
				if ($product->id >0) {
					$error++;
					$errors[] = 'Ref Already Exists';
				}
				$product = new Product($db);
				if (empty($error)) {
					$product->ref = $ref;
					$product->barcode = $ref;
					$product->label = $label;
					$product->type = $product::TYPE_SERVICE;
					$product->status = $tosell;
					$product->status_buy = $tobuy;
					$product->price = 0;
					$product->price_ttc = 0;
					$product->price_min = 0;
					$product->price_min_ttc = 0;
					$product->cost_price = 0;
					$product->pmp = 0;
					$product->price_base_type = 'HT';
					$product->tva_tx = 0;
					$product->accountancy_code_buy = null;
					$product->accountancy_code_buy_export = null;
					$product->accountancy_code_buy_intra = null;
					$product->accountancy_code_sell = null;
					$product->accountancy_code_sell_export = null;
					$product->accountancy_code_sell_intra = null;
					if (!empty($file_uplaoded)) {
						$product->array_options['options_doc_obl'] = basename($file_uplaoded, ".pdf");
					}
					$product->array_options['options_or_is_job'] = 1;
					$product->array_options['options_or_scan'] = 0;
					$product->array_options['oorder_available_for_supplier_order'] = 0;
					$product->array_options['oorder_ventilation_produit'] = null;
					$product->array_options['cfe_cfeprefixcatalogue'] = null;
					$product->array_options['cfe_cfecoderemise'] = null;
					$product->import_key = dol_now();
					$res = $product->create($user);
					if ($res > 0 && !empty($file_uplaoded)) {
						$product->fetch($res);
						if (isModEnabled("service")) {
							$upload_dir = $conf->service->multidir_output[$product->entity] . '/' . get_exdir(0, 0, 0, 1, $product, 'product');
						}
						if (!empty($upload_dir)) {
							if (!file_exists($upload_dir)) {
								if (dol_mkdir($upload_dir) < 0) {
									$error++;
									$errors[] = 'Unable to create product directory';
									$product->array_options['options_doc_obl'] = null;
									$product->update($res, $user);
								}
							}
							$newname = $upload_dir . '/' . basename($file_uplaoded);
							$resmove = dol_move($file_uplaoded, $newname);
							if (!$resmove) {
								$error++;
								$errors[] = 'Unable to move file :' . $file_uplaoded;
								$product->array_options['options_doc_obl'] = null;
								$product->update($res, $user);
							}
						}
					} else {
						if (!empty($file_uplaoded)) {
							dol_delete_file($file_uplaoded);
						}
					}
				}
			} elseif ($action == 'confirmedit') {
				if ($doc_obl_sel == -1) {
					unset($doc_obl_sel);
				}
				$product = new Product($db);
				$resfetch = $product->fetch($rowid);
				if ($resfetch) {
					$product->ref = $ref;
					$product->barcode = $ref;
					$product->label = $label;
					$product->type = $product::TYPE_SERVICE;
					$product->status = $tosell;
					$product->status_buy = $tobuy;
					$product->array_options['options_or_is_job'] = 1;
					$product->array_options['options_or_scan'] = 0;
					$product->array_options['oorder_available_for_supplier_order'] = 0;
					$product->array_options['oorder_ventilation_produit'] = null;
					$product->array_options['cfe_cfeprefixcatalogue'] = null;
					$product->array_options['cfe_cfecoderemise'] = null;
					$product->mandatory_period = 0;
					if (!empty($doc_obl_sel) || ($doc_obl_sel != $product->array_options['options_doc_obl'])) {
						$product->array_options['options_doc_obl'] = $doc_obl_sel;
					} elseif (!empty($file_uplaoded)) {
						$product->array_options['options_doc_obl'] = basename($file_uplaoded, ".pdf");
						if (isModEnabled("service")) {
							$upload_dir = $conf->service->multidir_output[$product->entity] . '/' . get_exdir(0, 0, 0, 1, $product, 'product');
						}
						if (!empty($upload_dir)) {
							if (!file_exists($upload_dir)) {
								if (dol_mkdir($upload_dir) < 0) {
									$error++;
									$errors[] = 'Unable to create product directory';
									$product->array_options['options_doc_obl'] = null;
								}
							}
							$newname = $upload_dir . '/' . basename($file_uplaoded);
							$resmove = dol_move($file_uplaoded, $newname);
							if (!$resmove) {
								$error++;
								$errors[] = 'Unable to move file :' . $file_uplaoded;
								$product->array_options['options_doc_obl'] = null;
							}
						}
					} elseif (empty($file_uplaoded) && empty($doc_obl_sel)) {
						$product->array_options['options_doc_obl'] = null;
					}
					$res = $product->update($rowid, $user);
				} else {
					$res =0;
					$error ++;
					$errors[] = 'Unable to fetch product';
				}
			} elseif ($action == 'confirmdelete') {
				$product = new Product($db);
				$resfetch = $product->fetch($rowid);
				if ($resfetch) {
					$res = $product->delete($user);
				} else {
					$res =0;
					$error ++;
					$errors[] = 'Unable to fetch product';
				}
			}
			if ($res < 0) {
				$error++;
				if ($db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
					$errors[] = "ErrorRefAlreadyExists";
				} else {
					$errors[] = $db->lasterror();
				}
			}
		}
		if ($error > 0) {
			if ($action == 'confirmnew') {
				$msg='CreateErrors';
			} elseif ($action == 'confirmedit') {
				$msg='UpdateErrors';
			} elseif ($action == 'confirmdelete') {
				$msg='DeleteErrors';
			}
			setEventMessages($msg, $errors, 'errors');
		} else {
			if ($action == 'confirmnew') {
				$msg='CreateSucces';
			} elseif ($action == 'confirmedit') {
				$msg='UpdateSucces';
			} elseif ($action == 'confirmdelete') {
				$msg='DeleteSucces';
			}
			setEventMessage($msg);
			if (!empty($file_uplaoded)) {
				dol_delete_file($file_uplaoded);
			}
			unset($action);
			unset($confirm);
			unset($cancel);
			unset($rowid);
			unset($ref);
			unset($tosell);
			unset($tobuy);
			unset($doc_obl);
			unset($file_uplaoded);
		}
	}
}

$jobarray = array();
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	// If $page is not defined, or '' or -1 or if we click on clear filters
	$page = 0;
}
$limit = 25;
$offset = $limit * $page;

$sql  = "SELECT p.rowid as rowid, p.ref as ref, p.label as label, p.tosell as tosell, p.tobuy as tobuy, ef.doc_obl ";
$sql .= "FROM ".$db->prefix()."product as p ";
$sql .= "INNER JOIN ".$db->prefix()."product_extrafields as ef ON p.rowid = ef.fk_object ";
$sql .= "WHERE p.entity IN (".getEntity('product').") AND p.fk_product_type=1 AND ef.or_is_job =1 ";

$nbtotalofrecords = 0;
$resql = $db->query($sql);

if ($resql) {
	$nbtotalofrecords = $db->num_rows($resql);
	if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller than the paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
	$db->free($resql);
}

if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$num=0;
$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i = 0;
	while ($i < ($limit ? min($limit, $num) : $num)) {
		$obj = $db->fetch_object($resql);
		$job = new stdClass();
		$job->id = $obj->rowid;
		$job->ref = $obj->ref;
		$job->label = $obj->label;
		$job->tosell = $obj->tosell;
		$job->tobuy = $obj->tobuy;
		$job->doc_obl = $obj->doc_obl;
		$jobarray[$job->id] = $job;

		$i++;
	}
	$db->free($resql);
}

/*
 * View
 *
 * Put here all code to build page
 */

$form = new Form($db);
$product_static = new Product($db);

$title = $langs->trans('ParamOrSetupJob');
$help_url = '';
llxHeader('', $title, $help_url);

$head = OrSetupPrepareHead();
print dol_get_fiche_head($head, 'job', $langs->trans("ParamOrSetupJob"), -1, "fontawesome_fa-tools");
// Part to show record

$formconfirm = '';
if ($action=='delete' && !empty($rowid)) {
	$formquestion[] = array('type'=>'hidden','name'=>'rowid','value'=>$rowid);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('DeleteJob'), $langs->trans('DeleteJobQuestion'), 'confirmdelete', $formquestion, 'yes', 1);
} elseif ($action =='new') {
	$upload_dir = $conf->user->multidir_temp[$conf->entity];
	$out  = '<input class="flat minwidth400 maxwidth200onsmartphone" id="doc_obl" type="file" name="doc_obl" accept="application/pdf"/>';
	$out .= '<button id="upload" value="Upload">' . img_picto('', 'fontawesome_fa-save', 'class="paddingright pictofixedwidth valignmiddle"') . '</button>';
	$out .= '<script>';
	$out .= '$(document).on("click", "#upload", function() {'."\n";
	$out .= '	let file_data = $("#doc_obl").prop("files")[0];'."\n";
	$out .= '	let form_data = new FormData();'."\n";
	$out .= '	form_data.append("file", file_data);'."\n";
	$out .= '	form_data.append("upload_dir", "' . $upload_dir . '");'."\n";
	$out .= '	form_data.append("token", "' . newToken() . '");'."\n";
	$out .= '	$.ajax({'."\n";
	$out .= '		url: "' . dol_buildpath('/operationorder/scripts/upload_files.php', 3) . '",'."\n";
	$out .= '		cache: false,'."\n";
	$out .= '		contentType: false,'."\n";
	$out .= '		processData: false,'."\n";
	$out .= '		data: form_data,'."\n";
	$out .= '		type: "POST",'."\n";
	$out .= '		success: function (data) {'."\n";
	$out .= '			document.getElementById("doc_obl").disabled = true;'."\n";
	$out .= '			document.getElementById("upload").disabled = true;'."\n";
	$out .= '			document.getElementById("file_uplaoded").value = data;'."\n";
	$out .= '		}'."\n";
	$out .= '	});'."\n";
	$out .= '});'."\n";
	$out .= '</script>';
	$formquestion[] = array('type'=>'hidden','name'=>'file_uplaoded','value'=>'');
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('ref'), 'name'=>'ref','value'=> $ref);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('joblabel'), 'name'=>'label','value'=>$label);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('tobuy'), 'name'=>'tobuy','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>empty($tobuy)?'1':$tobuy);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('tosell'), 'name'=>'tosell','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>empty($tosell)?'1':$tosell);
	$formquestion[] = array('type'=>'other','label'=>$langs->trans('doc_obligatoire'), 'value'=>$out);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('NewJob'), '', 'confirmnew', $formquestion, 'yes', 1, 0, 700);
} elseif ($action =='edit' && !empty($rowid)) {
	$dataedit = $jobarray[$rowid];
	$product = new Product($db);
	$product->fetch($rowid);
	$upload_dir = $conf->user->multidir_temp[$conf->entity];
	$search_dir = $conf->product->multidir_output[$product->entity].'/'.get_exdir(0, 0, 0, 1, $product, 'product');
	$filearray = dol_dir_list($search_dir, "files", 0, '.pdf', '(\.meta|_preview.*\.png)$', 'name', ' SORT_ASC', 0);
	$fileselect = array();
	foreach ($filearray as $file) {
		$fileselect[basename($file['name'], '.pdf')] = $file['name'];
	}
	$out  = '<input class="flat minwidth400 maxwidth200onsmartphone" id="doc_obl" type="file" name="doc_obl" accept="application/pdf"/>';
	$out .= '<button id="upload" value="Upload">' . img_picto('', 'fontawesome_fa-save', 'class="paddingright pictofixedwidth valignmiddle"') . '</button>';
	$out .= '<script>';
	$out .= '$(document).on("click", "#upload", function() {'."\n";
	$out .= '	let file_data = $("#doc_obl").prop("files")[0];'."\n";
	$out .= '	let form_data = new FormData();'."\n";
	$out .= '	form_data.append("file", file_data);'."\n";
	$out .= '	form_data.append("upload_dir", "' . $upload_dir . '");'."\n";
	$out .= '	form_data.append("token", "' . newToken() . '");'."\n";
	$out .= '	$.ajax({'."\n";
	$out .= '		url: "' . dol_buildpath('/operationorder/scripts/upload_files.php', 3) . '",'."\n";
	$out .= '		cache: false,'."\n";
	$out .= '		contentType: false,'."\n";
	$out .= '		processData: false,'."\n";
	$out .= '		data: form_data,'."\n";
	$out .= '		type: "POST",'."\n";
	$out .= '		success: function (data) {'."\n";
	$out .= '			document.getElementById("doc_obl").disabled = true;'."\n";
	$out .= '			document.getElementById("upload").disabled = true;'."\n";
	$out .= '			document.getElementById("file_uplaoded").value = data;'."\n";
	$out .= '		}'."\n";
	$out .= '	});'."\n";
	$out .= '});'."\n";
	$out .= '</script>';
	$formquestion[] = array('type'=>'hidden','name'=>'rowid','value'=>$rowid);
	$formquestion[] = array('type'=>'hidden','name'=>'file_uplaoded','value'=>'');
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('ref'), 'name'=>'ref','value'=> $dataedit->ref);
	$formquestion[] = array('type'=>'text','label'=>$langs->trans('joblabel'), 'name'=>'label','value'=>$dataedit->label);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('tobuy'), 'name'=>'tobuy','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>$dataedit->tobuy);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('tosell'), 'name'=>'tosell','values'=>array('0'=>'Non', '1'=>'Oui'), 'default'=>$dataedit->tosell);
	$formquestion[] = array('type'=>'select','label'=>$langs->trans('doc_obligatoire'), 'name'=>'doc_obl_sel','values'=>$fileselect, 'default'=>$dataedit->doc_obl);
	$formquestion[] = array('type'=>'other','label'=>$langs->trans('doc_obligatoire'), 'value'=>$out);
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('EditJob'), '', 'confirmedit', $formquestion, 'yes', 1, 0, 700);
}

// Call Hook formConfirm
$parameters = array('formConfirm' => $formconfirm);
$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
if (empty($reshook)) {
	$formconfirm .= $hookmanager->resPrint;
} elseif ($reshook > 0) {
	$formconfirm = $hookmanager->resPrint;
}

// Print form confirm
print $formconfirm;
$actionpathnew = dol_buildpath('/operationorder/param/or_setup_job.php', 3). '?action=new';
$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $actionpathnew, '', $user->hasRight('produit', 'creer'));
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], '', '', '', '', $num, $nbtotalofrecords, 'service', 0, $newcardbutton, '', $limit, 0, 0, 1);

print '<div class="fichecenter">';

print '<table class="border centpercent tableforfield liste">' . "\n";
print '<tr class="liste_titre">';
print '<th class="liste_titre">' . $langs->trans("ref") . '</th>';
print '<th class="liste_titre">' . $langs->trans("joblabel") . '</th>';
print '<th class="liste_titre">' . $langs->trans("doc_obligatoire") . '</th>';
print '<th class="liste_titre">' . $langs->trans("statut") . '</th>';
print '<th class="liste_titre">' . $langs->trans("action") . '</th>';
print '</tr>';
foreach ($jobarray as $key=>$data) {
	$product_static->id = $data->id;
	$product_static->ref = $data->ref;
	print '<tr class="oddeven">';
	print '<td>' . $product_static->getNomUrl(1) . '</td>';
	print '<td>' . $data->label . '</td>';
	print '<td>' . (empty($data->doc_obl)?'':$data->doc_obl.'.pdf' ) . '</td>';

	print '<td>';
	print '<span>' . $product_static->LibStatut($data->tobuy, 5, 0) . '</span>&nbsp &nbsp';
	print '<span>' . $product_static->LibStatut($data->tosell, 5, 1) . '</span>';
	print '</td>';
	$actionpath = dol_buildpath('/operationorder/param/or_setup_job.php', 3) . '?rowid=' . $data->id . '&action=';
	$action  = '<a href="' . $actionpath . 'edit"><span class="fas fa-pen" title="' . $langs->trans('Edit') . '"></span></a>';
	if ($user->admin) {
		$action .= '&nbsp &nbsp';
		$action .= '<a href="' . $actionpath . 'delete&token='.newToken().'"><span class="fas fa-trash-alt" title="' . $langs->trans('Delete') . '"></span></a>';
	}
	print '<td>' . $action . '</td>';
	print '</tr>';
}

print '</table>';

print '</div>' . "\n";

// Buttons for actions
$parameters = array();
$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	print '<div class="tabsAction">' . "\n";

	print '</div>' . "\n";
}
// End of page
llxFooter();
$db->close();
