<?php
/* Copyright (C) 2007-2010  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2007-2010  Jean Heimburger         <jean@tiaris.info>
 * Copyright (C) 2011-2014  Juanjo Menent           <jmenent@2byte.es>
 * Copyright (C) 2012       Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2011-2012  Alexandre Spangaro      <aspangaro@open-dsi.fr>
 * Copyright (C) 2012       Cédric Salvador         <csalvador@gpcsolutions.fr>
 * Copyright (C) 2013       Marcos García           <marcosgdf@gmail.com>
 * Copyright (C) 2014       Raphaël Doursenaud      <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2018       Frédéric France         <frederic.france@netlogic.fr>
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
 *    \file       operationorder/compta/journal/sellsjournal.php
 *        \ingroup    operationorder
 *        \brief      Page with sells journal
 */

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
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/client.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/accounting.lib.php';
dol_include_once('/operationorder/lib/operationorder.lib.php');

// Load translation files required by the page
$langs->loadLangs(array('companies', 'other', 'bills', 'compta', 'theobald@theobald'));

$alreadyexporteddate=GETPOST('alreadyexporteddate', 'int');
if ($alreadyexporteddate==-1) {
	$alreadyexporteddate='';
}
if (empty($alreadyexporteddate)) {
	$date_startmonth = GETPOST('date_startmonth', 'int');
	$date_startday = GETPOST('date_startday', 'int');
	$date_startyear = GETPOST('date_startyear', 'int');
	$date_endmonth = GETPOST('date_endmonth', 'int');
	$date_endday = GETPOST('date_endday', 'int');
	$date_endyear = GETPOST('date_endyear', 'int');
}

$action = 'refresh';
if (GETPOSTISSET('exportcsv')) {
	$action = "exportcsv";
}

// Security check
if ($user->socid > 0) {
	$socid = $user->socid;
}
if (!empty(isModEnabled("comptabilite"))) {
	$result = restrictedArea($user, 'compta', '', '', 'resultat');
}
if (!empty(isModEnabled("accounting"))) {
	$result = restrictedArea($user, 'accounting', '', '', 'comptarapport');
}

$error = 0;

/*
 * View
 */

$form = new Form($db);

$morequery = '&date_startyear=' . $date_startyear . '&date_startmonth=' . $date_startmonth . '&date_startday=' . $date_startday . '&date_endyear=' . $date_endyear . '&date_endmonth=' . $date_endmonth . '&date_endday=' . $date_endday;

$year_current = strftime("%Y", dol_now());
$date_start = dol_mktime(0, 0, 0, $date_startmonth, $date_startday, $date_startyear);
$date_end = dol_mktime(23, 59, 59, $date_endmonth, $date_endday, $date_endyear);

if (empty($date_startmonth) || empty($date_endmonth)) {
	// Period by default on transfer
	$dates = getDefaultDatesForTransfer();
	$date_start = $dates['date_start'];
	$date_end = $dates['date_end'];
	$pastmonthyear = $dates['pastmonthyear'];
	$pastmonth = $dates['pastmonth'];
}

if (!GETPOSTISSET('date_startmonth') && (empty($date_start) || empty($date_end))) { // We define date_start and date_end, only if we did not submit the form
	$date_start = dol_get_first_day($pastmonthyear, $pastmonth, false);
	$date_end = dol_get_last_day($pastmonthyear, $pastmonth, false);
}


$name = $langs->trans("SellsJournal");
$periodlink = '';
$exportlink = '';
$builddate = dol_now();
$description = $langs->trans("DescSellsJournal") . '<br>';
if (!empty(getDolGlobalString("FACTURE_DEPOSITS_ARE_JUST_PAYMENTS"))) {
	$description .= $langs->trans("DepositsAreNotIncluded");
} else {
	$description .= $langs->trans("DepositsAreIncluded");
}
$period = $form->selectDate($date_start, 'date_start', 0, 0, 1, '', 1, 0) . ' - ' . $form->selectDate($date_end, 'date_end', 0, 0, 1, '', 1, 0);

//finbd already exported date
$dt_array=array();
$sql ='SELECT DISTINCT fe.dt_export_compta FROM ';
$sql .= " " . $db->prefix() . "facture as f";
$sql .= " INNER JOIN " . $db->prefix() . "facture_extrafields as fe ON f.rowid=fe.fk_object";
$sql .= " WHERE fe.dt_export_compta IS NOT NULL";
//$sql .= " ORDER BY fe.dt_export_compta ASC";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$dt_array[$db->jdate($obj->dt_export_compta)] = dol_print_date($db->jdate($obj->dt_export_compta), 'dayhour');
	}
} else {
	$error++;
	setEventMessage($db->lasterror, 'errors');
}
if (!empty($dt_array)) {
	$period .= $langs->trans('or').' '.$langs->trans('SellExportDateTime').':'.$form->selectarray('alreadyexporteddate', $dt_array, $alreadyexporteddate, 1);
}

$export_available=true;

$p = explode(":", getDolGlobalString("MAIN_INFO_SOCIETE_COUNTRY"));
$idpays = $p[0];

$sql = "SELECT f.rowid, f.ref, f.type, f.datef, f.ref_client,";
$sql .= " fd.product_type, fd.total_ht, fd.total_tva, fd.tva_tx, fd.total_ttc, fd.localtax1_tx,
	fd.localtax2_tx, fd.total_localtax1, fd.total_localtax2, fd.rowid as id, fd.situation_percent,";
$sql .= " s.rowid as socid, s.nom as name, s.code_compta as code_compta_client, s.client,";
$sql .= " p.rowid as pid, p.ref as pref,";
$sql .= " cd.code as country_code,";
$sql .= " f.entity,";
$sql .= " f.date_lim_reglement,";
$sql .= " fe.dt_export_compta,";
$sql .= " se.cd_compta_intragp,";
if (!empty(getDolGlobalString("MAIN_PRODUCT_PERENTITY_SHARED"))) {
	$sql .= " ppe.accountancy_code_sell,ppe.accountancy_code_sell_intra,ppe.accountancy_code_sell_export,";
} else {
	$sql .= " p.accountancy_code_sell,p.accountancy_code_sell_intra,p.accountancy_code_sell_export,";
}
$sql .= " ct.accountancy_code_sell as account_tva, ct.recuperableonly";
$sql .= " FROM " . $db->prefix() . "facturedet as fd";
$sql .= " LEFT JOIN " . $db->prefix() . "product as p ON p.rowid = fd.fk_product";
$sql .= " LEFT JOIN " . $db->prefix() . "product_extrafields as pef ON p.rowid = pef.fk_object";
if (!empty(getDolGlobalString("MAIN_PRODUCT_PERENTITY_SHARED"))) {
	$sql .= " LEFT JOIN " . $db->prefix() . "product_perentity as ppe ON ppe.fk_product = p.rowid AND ppe.entity = f.entity";
}
$sql .= " INNER JOIN " . $db->prefix() . "facture as f ON f.rowid = fd.fk_facture";
$sql .= " INNER JOIN " . $db->prefix() . "societe as s ON s.rowid = f.fk_soc";
$sql .= " LEFT JOIN " . $db->prefix() . "societe_extrafields as se ON s.rowid = se.fk_object";
$sql .= " LEFT JOIN " . $db->prefix() . "c_tva as ct ON fd.tva_tx = ct.taux AND fd.info_bits = ct.recuperableonly AND ct.fk_pays = " . ((int) $idpays) . " AND  ct.entity IN (" . getEntity('invoice') . ")";
$sql .= " LEFT JOIN " . $db->prefix() . "c_country as cd ON cd.rowid=s.fk_pays";
$sql .= " LEFT JOIN " . $db->prefix() . "facture_extrafields as fe ON f.rowid=fe.fk_object";
$sql .= " WHERE f.entity IN (" . getEntity('invoice') . ")";
$sql .= " AND f.fk_statut > 0";
if (!empty(getDolGlobalString("FACTURE_DEPOSITS_ARE_JUST_PAYMENTS"))) {
	$sql .= " AND f.type IN (" . Facture::TYPE_STANDARD . "," . Facture::TYPE_REPLACEMENT . "," . Facture::TYPE_CREDIT_NOTE . "," . Facture::TYPE_SITUATION . ")";
} else {
	$sql .= " AND f.type IN (" . Facture::TYPE_STANDARD . "," . Facture::TYPE_STANDARD . "," . Facture::TYPE_CREDIT_NOTE . "," . Facture::TYPE_DEPOSIT . "," . Facture::TYPE_SITUATION . ")";
}

$sql .= " AND fd.product_type IN (0,1)";
if ($date_start && $date_end) {
	$sql .= " AND f.datef >= '" . $db->idate($date_start) . "' AND f.datef <= '" . $db->idate($date_end) . "'";
}
if (!empty($alreadyexporteddate)) {
	$sql .= " AND fe.dt_export_compta = '".$db->idate($alreadyexporteddate)."'";
} else {
	$sql .= " AND fe.dt_export_compta IS NULL";
}

$sql .= " ORDER BY f.rowid";
//var_dump($sql);
$result = $db->query($sql);
if ($result) {
	$isSellerInEEC = isInEEC($mysoc);

	$tabfac = array();
	$tabht = array();
	$tabtva = array();
	$tablocaltax1 = array();
	$tablocaltax2 = array();
	$tabttc = array();
	$tabcompany = array();
	$tabprodtype = array();
	$account_localtax1 = 0;
	$account_localtax2 = 0;

	$num = $db->num_rows($result);
	while ($num > 0 && ($obj = $db->fetch_object($result))) {
		// les variables
		$cptcli = (getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') ? getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') : $langs->trans("CodeNotDef"));
		$compta_soc = (!empty($obj->code_compta_client) ? $obj->code_compta_client : $cptcli);
		$buyer = new Societe($db);
		$buyer->country_code = $obj->country_code;

		$cd_compta_intragp = $obj->cd_compta_intragp;

		$compta_prod = '';
		$type_code_p = '';

		$isBuyerInEEC = isInEEC($buyer);

		if (($p[1] == $buyer->country_code) && ($obj->tva_tx == 0) ) {
			// European intravat sale, but with VAT
			if (!empty($obj->accountancy_code_sell)) {
				$compta_prod = $obj->accountancy_code_sell;
				//$type_code_p = ' EXO';
			}
		} elseif ($isSellerInEEC && $isBuyerInEEC && $obj->tva_tx != 0) {
			// European intravat sale, but with VAT
			if (!empty($obj->accountancy_code_sell)) {
				$compta_prod = $obj->accountancy_code_sell;
			}
		} elseif ($isSellerInEEC && $isBuyerInEEC && !empty($obj->accountancy_code_sell_intra)) {
			// European intravat sale
			if (!empty($obj->accountancy_code_sell_intra)) {
				$compta_prod = $obj->accountancy_code_sell_intra;
				$type_code_p = ' INTRA';
			}
		} else {
			// Foreign sale
			if (!empty($obj->accountancy_code_sell_export)) {
				$compta_prod = $obj->accountancy_code_sell_export;
				$type_code_p = ' EXPORT';
			}
		}

		if (empty($compta_prod)) {
			if ($obj->product_type == 0) {
				$compta_prod = getDolGlobalString('ACCOUNTING_PRODUCT_SOLD_ACCOUNT');
			} elseif ($obj->product_type == 1) {
				$compta_prod = getDolGlobalString('ACCOUNTING_SERVICE_SOLD_ACCOUNT');
			}
		}


		$cpttva = (!empty(getDolGlobalString('ACCOUNTING_VAT_SOLD_ACCOUNT')) ? getDolGlobalString('ACCOUNTING_VAT_SOLD_ACCOUNT') : $langs->trans("CodeNotDef"));
		$compta_tva = (!empty($obj->account_tva) ? $obj->account_tva : $cpttva);


		$tabfac[$obj->rowid]["accoutancy_customer_code"] = getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER');

		if (empty($compta_prod)) {
			setEventMessage($langs->trans("AccountancyCodeStupImompleteForInvoice", $obj->ref), 'errors');
			$export_available=false;
		} elseif (!empty((int) $cd_compta_intragp)) {
				$compta_prod = substr_replace($compta_prod, $cd_compta_intragp, 5, 3);
				$tabfac[$obj->rowid]["accoutancy_customer_code"] = getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER_INTRAGP');
		}


		$account_localtax1 = getLocalTaxesFromRate($obj->tva_tx, 1, $obj->thirdparty, $mysoc);
		$compta_localtax1 = (!empty($account_localtax1[3]) ? $account_localtax1[3] : $langs->trans("CodeNotDef"));
		$account_localtax2 = getLocalTaxesFromRate($obj->tva_tx, 2, $obj->thirdparty, $mysoc);
		$compta_localtax2 = (!empty($account_localtax2[3]) ? $account_localtax2[3] : $langs->trans("CodeNotDef"));

		// Situation invoices handling
		/*$line = new FactureLigne($db);
		$line->fetch($obj->id); // id of line*/
		$situation_ratio = 1;

		//la ligne facture
		$tabfac[$obj->rowid]["date"] = $obj->datef;
		$tabfac[$obj->rowid]["ref"] = $obj->ref;
		$tabfac[$obj->rowid]["type"] = $obj->type;
		$tabfac[$obj->rowid]["dt_export"] = dol_print_date($db->jdate($obj->dt_export_compta), 'dayhour');
		$tabfac[$obj->rowid]["date_lim_reglement"] = $obj->date_lim_reglement;


		//$tabfac[$obj->rowid]["entity"] = $obj->entity;
		if (!isset($tabttc[$obj->rowid][$compta_soc])) {
			$tabttc[$obj->rowid][$compta_soc] = 0;
		}
		if (!isset($tabht[$obj->rowid][$compta_prod])) {
			$tabht[$obj->rowid][$compta_prod] = 0;
		}
		if (!isset($tabtva[$obj->rowid][$compta_tva])) {
			$tabtva[$obj->rowid][$compta_tva] = 0;
		}
		if (!isset($tablocaltax1[$obj->rowid][$compta_localtax1])) {
			$tablocaltax1[$obj->rowid][$compta_localtax1] = 0;
		}
		if (!isset($tablocaltax2[$obj->rowid][$compta_localtax2])) {
			$tablocaltax2[$obj->rowid][$compta_localtax2] = 0;
		}
		$tabttc[$obj->rowid][$compta_soc] += $obj->total_ttc * $situation_ratio;
		$tabht[$obj->rowid][$compta_prod] += $obj->total_ht * $situation_ratio;
		if ($obj->recuperableonly != 1) {
			$tabtva[$obj->rowid][$compta_tva] += $obj->total_tva * $situation_ratio;
		}

		$tabprodtype[$obj->rowid][$compta_prod] = $obj->pref . $type_code_p;

		$tablocaltax1[$obj->rowid][$compta_localtax1] += $obj->total_localtax1;
		$tablocaltax2[$obj->rowid][$compta_localtax2] += $obj->total_localtax2;
		$tabcompany[$obj->rowid] = array('id' => $obj->socid, 'name' => $obj->name, 'code_compta' => $obj->code_compta_client);
	}
} else {
	$error++;
	setEventMessage($db->lasterror, 'errors');
}

// Export

if ($action == 'exportcsv' && empty($error)) {
	$date_export = "_" . $date_startday . $date_startmonth . $date_startyear;
	$date_export .= "_" . $date_endday . $date_endmonth . $date_endyear;
	$filename = 'journal';
	$completefilename = $filename . $date_export . '.csv';

	top_httphead('Content-Type: text/txt');
	header('Content-Disposition: attachment;filename=' . $completefilename);
	$sep = ';';
	//$sep = "\t";
	$companystatic = new Client($db);
	$invoicestatic = new Facture($db);

	foreach ($tabfac as $key => $val) {
		$companystatic->id = $tabcompany[$key]['id'];
		$companystatic->name = $tabcompany[$key]['name'];
		$companystatic->code_compta_client = $tabcompany[$key]['code_compta'];

		$invoicestatic->id = $key;
		$invoicestatic->ref = (string) $val["ref"];
		$invoicestatic->type = $val["type"];

		$date = dol_print_date($val["date"], '%d/%m/%Y');
		$date_lim_reglement = dol_print_date($val["date_lim_reglement"], '%d/%m/%Y');

		// Third party
		foreach ($tabttc[$key] as $k => $mt) {
			//if ($mt) {
			print '"' . getDolGlobalString("OPERATIONORDER_CODE_JOUNAL_VT") . '"' . $sep;
			print  $date  . $sep;
			print '"' . $invoicestatic->ref . '"' . $sep;
			print '"' . $invoicestatic->ref . '"' . $sep;
			print '"' . $val["accoutancy_customer_code"] . '"' . $sep;
			print '"' . $companystatic->code_compta_client . '"' . $sep;
			print '"' . dol_trunc($companystatic->name, 16) . ' - ' . $invoicestatic->ref . '"' . $sep;
			print  $date_lim_reglement . $sep;
			print '"' . ($mt >= 0 ? price($mt) : '') . '"' . $sep;
			print '"' . ($mt < 0 ? price(-$mt) : '') . '"';
			print "\n";
		}

		// Product / Service
		foreach ($tabht[$key] as $k => $mt) {
			print '"' . getDolGlobalString("OPERATIONORDER_CODE_JOUNAL_VT") . '"' . $sep;
			print  $date  . $sep;
			print '"' . $invoicestatic->ref . '"' . $sep;
			print '"' . $invoicestatic->ref . '"' . $sep;
			print '"' . $k . '"' . $sep;
			print $sep;
			//print '"' . dol_trunc($companystatic->name, 16) . ' - ' . dol_trunc($tabprodtype[$key][$k]) . '"' . $sep;
			print '"' . dol_trunc($companystatic->name, 16) . ' - ' . $invoicestatic->ref . '"' . $sep;
			print  $date_lim_reglement . $sep;
			print '"' . ($mt < 0 ? price(-$mt) : '') . '"' . $sep;
			print '"' . ($mt >= 0 ? price($mt) : '') . '"';
			print "\n";
		}

		// VAT
		$listoftax = array(0, 1, 2);
		foreach ($listoftax as $numtax) {
			$arrayofvat = $tabtva;
			if ($numtax == 1) {
				$arrayofvat = $tablocaltax1;
			}
			if ($numtax == 2) {
				$arrayofvat = $tablocaltax2;
			}

			foreach ($arrayofvat[$key] as $k => $mt) {
				if ($mt) {
					print '"' . getDolGlobalString("OPERATIONORDER_CODE_JOUNAL_VT") . '"' . $sep;
					print  $date  . $sep;
					print '"' . $invoicestatic->ref . '"' . $sep;
					print '"' . $invoicestatic->ref . '"' . $sep;
					print '"' . $k . '"' . $sep;
					print $sep;
					print '"' . dol_trunc($companystatic->name, 16) . ' - ' . $invoicestatic->ref . '"' . $sep;
					print  $date_lim_reglement . $sep;
					print '"' . ($mt < 0 ? price(-$mt) : '') . '"' . $sep;
					print '"' . ($mt >= 0 ? price($mt) : '') . '"';
					print "\n";
				}
			}
		}
	}

	if (empty($alreadyexporteddate)) {
		$db->begin();
		$errorUpdateFact = 0;
		$now = dol_now();
		foreach ($tabfac as $key => $val) {
			$invoice = new Facture($db);
			$resultFetch = $invoice->fetch($key);
			if ($resultFetch < 0) {
				$errorUpdateFact++;
				setEventMessages($invoicestatic->error, $invoicestatic->errors, 'errors');
			} else {
				$invoice->array_options['options_dt_export_compta'] = $now;
				$resultUpd = $invoice->update($user, 1);
				if ($resultUpd < 0) {
					$errorUpdateFact++;
					setEventMessages($invoicestatic->error, $invoicestatic->errors, 'errors');
				}
			}
		}
		if (empty($errorUpdateFact)) {
			$db->commit();
		} else {
			$db->rollback();
		}
	}
}

if (empty($action) || $action == 'refresh') {
	llxHeader('', $langs->trans("SellsJournal"), '', '', 0, 0, '', '', $morequery);
	reportHeaderExportCompta($name, $period, $periodlink, $description, $builddate, $exportlink, array(), '', '', $export_available);
	/*
	 * Show result array
	 */
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print "<td>" . $langs->trans("CodeJournal") . "</td>";
	print '<td>' . $langs->trans('Date') . '</td>';
	print '<td>' . $langs->trans('Piece') . ' (' . $langs->trans('InvoiceRef') . ')</td>';
	print '<td>' . $langs->trans('Piece') . ' (' . $langs->trans('InvoiceRef') . ')</td>';
	print '<td>' . $langs->trans('Account') . '</td>';
	print '<td>' . $langs->trans('CodeAux') . '</td>';
	print '<td>' . $langs->trans('Label') . '</td>';
	print '<td>' . $langs->trans('DateDue') . '</td>';
	print '<td class="right">' . $langs->trans('Debit') . '</td><td class="right">' . $langs->trans('Credit') . '</td>';
	print '<td>' . $langs->trans('SellExportDateTime') . '</td>';
	print "</tr>\n";

	if (empty($error)) {
		$companystatic = new Client($db);
		$invoicestatic = new Facture($db);

		foreach ($tabfac as $key => $val) {
			$companystatic->id = $tabcompany[$key]['id'];
			$companystatic->name = $tabcompany[$key]['name'];
			$companystatic->code_compta_client = $tabcompany[$key]['code_compta'];

			$invoicestatic->id = $key;
			$invoicestatic->ref = (string) $val["ref"];
			$invoicestatic->type = $val["type"];
			//$invoicestatic->close_code = $val["close_code"];

			$date = dol_print_date($val["date"], '%d/%m/%Y');
			$date_lim_reglement = dol_print_date($val["date_lim_reglement"], '%d/%m/%Y');

			// Third party
			foreach ($tabttc[$key] as $k => $mt) {
				print '<tr class="oddeven">';
				print '<td>' . getDolGlobalString("OPERATIONORDER_CODE_JOUNAL_VT") . '</td>';
				print '<td>' . $date . '</td>';
				print '<td>' . $invoicestatic->getNomUrl(1) . '</td>';
				print '<td>' . $invoicestatic->getNomUrl(1) . '</td>';
				print '<td>' . $val["accoutancy_customer_code"] . '</td>';
				print '<td>' . $companystatic->code_compta_client . '</td>';
				print '<td>' . dol_trunc($companystatic->name, 16) . ' - ' . $invoicestatic->ref . '</td>';
				print '<td>' . $date_lim_reglement . '</td>';
				print '<td class="right">' . ($mt >= 0 ? price($mt) : '') . '</td>';
				print '<td class="right">' . ($mt < 0 ? price(-$mt) : '') . '</td>';
				print '<td>'. $val["dt_export"].'</td>';
				print "</tr>";
			}

			// Product / Service
			foreach ($tabht[$key] as $k => $mt) {
				print '<tr class="oddeven">';
				print '<td>' . getDolGlobalString("OPERATIONORDER_CODE_JOUNAL_VT") . '</td>';
				print '<td>' . $date . '</td>';
				print '<td>' . $invoicestatic->getNomUrl(1) . '</td>';
				print '<td>' . $invoicestatic->getNomUrl(1) . '</td>';
				print '<td>' . $k . '</td>';
				print '<td></td>';
				//print '<td>' . dol_trunc($companystatic->name, 16) . ' - ' . dol_trunc($tabprodtype[$key][$k]) . '</td>';
				print '<td>' . dol_trunc($companystatic->name, 16) . ' - ' . $invoicestatic->ref  . '</td>';
				print '<td>' . $date_lim_reglement . '</td>';
				print '<td class="right">' . ($mt < 0 ? price(-$mt) : '') . '</td>';
				print '<td class="right">' . ($mt >= 0 ? price($mt) : '') . '</td>';
				print '<td>'. $val["dt_export"].'</td>';
				print "</tr>";
			}

			// VAT
			$listoftax = array(0, 1, 2);
			foreach ($listoftax as $numtax) {
				$arrayofvat = $tabtva;
				if ($numtax == 1) {
					$arrayofvat = $tablocaltax1;
				}
				if ($numtax == 2) {
					$arrayofvat = $tablocaltax2;
				}

				foreach ($arrayofvat[$key] as $k => $mt) {
					if ($mt) {
						print '<tr class="oddeven">';
						print '<td>' . getDolGlobalString("OPERATIONORDER_CODE_JOUNAL_VT") . '</td>';
						print '<td>' . $date . '</td>';
						print '<td>' . $invoicestatic->getNomUrl(1) . '</td>';
						print '<td>' . $invoicestatic->getNomUrl(1) . '</td>';
						print '<td>' . $k . '</td>';
						print '<td></td>';
						print '<td>' . dol_trunc($companystatic->name, 16) . ' - ' . $invoicestatic->ref . '</td>';
						print '<td>' . $date_lim_reglement . '</td>';
						print '<td class="right">' . ($mt < 0 ? price(-$mt) : '') . '</td>';
						print '<td class="right">' . ($mt >= 0 ? price($mt) : '') . '</td>';
						print '<td>'. $val["dt_export"].'</td>';
						print "</tr>";
					}
				}
			}
		}
	}

	print "</table>";

	// End of page
	llxFooter();
}

$db->close();
