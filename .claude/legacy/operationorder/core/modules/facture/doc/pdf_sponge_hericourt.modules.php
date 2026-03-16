<?php
/* Copyright (C) 2004-2014  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2008       Raphael Bertrand        <raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2014  Juanjo Menent           <jmenent@2byte.es>
 * Copyright (C) 2012       Christophe Battarel     <christophe.battarel@altairis.fr>
 * Copyright (C) 2012       Cédric Salvador         <csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014  Raphaël Doursenaud      <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015       Marcos García           <marcosgdf@gmail.com>
 * Copyright (C) 2017       Ferran Marcet           <fmarcet@2byte.es>
 * Copyright (C) 2018       Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2022		Anthony Berton				<anthony.berton@bb2a.fr>
 * Copyright (C) 2022       Alexandre Spangaro      <aspangaro@open-dsi.fr>
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
 * or see https://www.gnu.org/
 */

/**
 *  \file       htdocs/core/modules/facture/doc/pdf_sponge.modules.php
 *  \ingroup    facture
 *  \brief      File of class to generate customers invoices from sponge model
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/modules/facture/doc/pdf_sponge.modules.php';


/**
 *    Class to manage PDF invoice template sponge
 */
class pdf_sponge_hericourt extends pdf_sponge
{

	/**
	 *    Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		// Translations
		$langs->loadLangs(array("main", "bills", "operationorder@operationorder"));

		$this->db = $db;
		$this->name = "sponge_hericourt";
		$this->description = $langs->trans('PDFSpongeDescriptionHericourt');
		$this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

		// Dimension page
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

		$this->option_logo = 1; // Display logo
		$this->option_tva = 1; // Manage the vat option FACTURE_TVAOPTION
		$this->option_modereg = 1; // Display payment mode
		$this->option_condreg = 1; // Display payment terms
		$this->option_multilang = 1; // Available in several languages
		$this->option_escompte = 1; // Displays if there has been a discount
		$this->option_credit_note = 1; // Support credit notes
		$this->option_freetext = 1; // Support add of a personalised text
		$this->option_draft_watermark = 1; // Support add of a watermark on drafts
		$this->watermark = '';

		// Get source company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined
		}

		// Define position of columns
		$this->posxdesc = $this->marge_gauche + 1; // used for notes ans other stuff


		$this->tabTitleHeight = 5; // default height

		//  Use new system for position of columns, view  $this->defineColumnField()

		$this->tva = array();
		$this->tva_array = array();
		$this->localtax1 = array();
		$this->localtax2 = array();
		$this->atleastoneratenotnull = 0;
		$this->atleastonediscount = 0;
		$this->situationinvoice = false;
	}


	/**
	 *  Show total to pay
	 *
	 * @param TCPDF $pdf Object PDF
	 * @param Facture $object Object invoice
	 * @param int $deja_regle Amount already paid (in the currency of invoice)
	 * @param int $posy Position depart
	 * @param Translate $outputlangs Objet langs
	 * @param Translate $outputlangsbis Object lang for output bis
	 * @return int                            Position pour suite
	 */
	protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis)
	{
		global $conf, $mysoc, $hookmanager;

		$sign = 1;
		if ($object->type == 2 && !empty(getDolGlobalString("INVOICE_POSITIVE_CREDIT_NOTE"))) {
			$sign = -1;
		}

		$outputlangs->load("operationorder@operationorder");

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy;
		$tab2_hl = 4;
		if (is_object($outputlangsbis)) {    // When we show 2 languages we need more room for text, so we use a smaller font.
			$pdf->SetFont('', '', $default_font_size - 2);
		} else {
			$pdf->SetFont('', '', $default_font_size - 1);
		}

		// Total table
		$col1x = 120;
		$col2x = 170;
		if ($this->page_largeur < 210) { // To work with US executive format
			$col1x -= 15;
			$col2x -= 10;
		}
		$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

		$useborder = 0;
		$index = 0;

		// Add trigger to allow to edit $object
		$parameters = array(
			'object' => &$object,
			'outputlangs' => $outputlangs,
		);
		$hookmanager->executeHooks('beforePercentCalculation', $parameters, $this); // Note that $object may have been modified by hook

		// overall percentage of advancement
		$percent = 0;
		$i = 0;
		foreach ($object->lines as $line) {
			if ($line->product_type != 9) {
				$percent += $line->situation_percent;
				$i++;
			}
		}

		if (!empty($i)) {
			$avancementGlobal = $percent / $i;
		} else {
			$avancementGlobal = 0;
		}

		$object->fetchPreviousNextSituationInvoice();
		$TPreviousIncoice = $object->tab_previous_situation_invoice;

		$total_a_payer = 0;
		$total_a_payer_ttc = 0;
		foreach ($TPreviousIncoice as &$fac) {
			$total_a_payer += $fac->total_ht;
			$total_a_payer_ttc += $fac->total_ttc;
		}
		$total_a_payer += $object->total_ht;
		$total_a_payer_ttc += $object->total_ttc;

		if (!empty($avancementGlobal)) {
			$total_a_payer = $total_a_payer * 100 / $avancementGlobal;
			$total_a_payer_ttc = $total_a_payer_ttc * 100 / $avancementGlobal;
		} else {
			$total_a_payer = 0;
			$total_a_payer_ttc = 0;
		}

		$i = 1;
		if (!empty($TPreviousIncoice)) {
			$pdf->setY($tab2_top);
			$posy = $pdf->GetY();

			foreach ($TPreviousIncoice as &$fac) {
				if ($posy > $this->page_hauteur - 4 - $this->heightforfooter) {
					$this->_pagefoot($pdf, $object, $outputlangs, 1, $this->getHeightForQRInvoice($pdf->getPage(), $object, $outputlangs));
					$pdf->addPage();
					if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
						$this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis);
						$pdf->setY($this->tab_top_newpage);
					} else {
						$pdf->setY($this->marge_haute);
					}
					$posy = $pdf->GetY();
				}

				// Cumulate preceding VAT
				$index++;
				$pdf->SetFillColor(255, 255, 255);
				$pdf->SetXY($col1x, $posy);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("PDFSituationTitle", $fac->situation_counter) . ' ' . $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

				$pdf->SetXY($col2x, $posy);

				$facSign = '';
				if ($i > 1) {
					$facSign = $fac->total_ht >= 0 ? '+' : '';
				}

				$displayAmount = ' ' . $facSign . ' ' . price($fac->total_ht, 0, $outputlangs);

				$pdf->MultiCell($largcol2, $tab2_hl, $displayAmount, 0, 'R', 1);

				$i++;
				$posy += $tab2_hl;

				$pdf->setY($posy);
			}

			// Display current total
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetXY($col1x, $posy);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("PDFSituationTitle", $object->situation_counter) . ' ' . $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

			$pdf->SetXY($col2x, $posy);
			$facSign = '';
			if ($i > 1) {
				$facSign = $object->total_ht >= 0 ? '+' : ''; // management of a particular customer case
			}

			if ($fac->type === Facture::TYPE_CREDIT_NOTE) {
				$facSign = '-'; // les avoirs
			}


			$displayAmount = ' ' . $facSign . ' ' . price($object->total_ht, 0, $outputlangs);
			$pdf->MultiCell($largcol2, $tab2_hl, $displayAmount, 0, 'R', 1);

			$posy += $tab2_hl;

			// Display all total
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetXY($col1x, $posy);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("SituationTotalProgress", $avancementGlobal), 0, 'L', 1);

			$pdf->SetXY($col2x, $posy);
			$pdf->MultiCell($largcol2, $tab2_hl, price($total_a_payer * $avancementGlobal / 100, 0, $outputlangs), 0, 'R', 1);
			$pdf->SetFont('', '', $default_font_size - 2);

			$posy += $tab2_hl;

			if ($posy > $this->page_hauteur - 4 - $this->heightforfooter) {
				$pdf->addPage();
				if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
					$this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis);
					$pdf->setY($this->tab_top_newpage);
				} else {
					$pdf->setY($this->marge_haute);
				}

				$posy = $pdf->GetY();
			}

			$tab2_top = $posy;
			$index = 0;

			$tab2_top += 3;
		}

		// Total total_ht_mo
		if (((float) $object->array_options['options_total_ht_mo'])) {
			$index++;
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl,
				$outputlangs->transnoentities("total_ht_mo"), 0, 'L', 1);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($object->array_options['options_total_ht_mo'], 0, $outputlangs), 0, 'R', 1);
		}

		// Total total_ht_part
		if (((float) $object->array_options['options_total_ht_part'])) {
			$index++;
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl,
				$outputlangs->transnoentities("total_ht_part"), 0, 'L', 1);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($object->array_options['options_total_ht_part'], 0, $outputlangs), 0, 'R', 1);
		}

		// Total total_ht_service
		if (((float) $object->array_options['options_total_ht_service'])) {
			$index++;
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl,
				$outputlangs->transnoentities("total_ht_service"), 0, 'L', 1);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl,  price($object->array_options['options_total_ht_service'], 0, $outputlangs), 0, 'R', 1);
		}

		// Total total_ht_external
		if (((float) $object->array_options['options_total_ht_external'])) {
			$index++;
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl,
				$outputlangs->transnoentities("total_ht_external"), 0, 'L', 1);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($object->array_options['options_total_ht_external'], 0, $outputlangs), 0, 'R', 1);
		}

		// Total total_ht_reimbursement
		if (((float) $object->array_options['options_total_ht_reimbursement'])) {
			$index++;
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl,
				$outputlangs->transnoentities("total_ht_reimbursement"), 0, 'L', 1);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($object->array_options['options_total_ht_reimbursement'], 0, $outputlangs), 0, 'R', 1);
		}

		$index++;

		// Get Total HT
		$total_ht = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1 ? $object->multicurrency_total_ht : $object->total_ht);

		// Total remise
		$total_line_remise = 0;
		foreach ($object->lines as $i => $line) {
			$resdiscount = pdfGetLineTotalDiscountAmount($object, $i, $outputlangs, 2);
			$total_line_remise += (is_numeric($resdiscount) ? $resdiscount : 0);
			// Gestion remise sous forme de ligne négative
			if ($line->total_ht < 0) {
				$total_line_remise += -$line->total_ht;
			}
		}
		if ($total_line_remise > 0) {
			if (!empty(getDolGlobalString("MAIN_SHOW_AMOUNT_DISCOUNT"))) {
				$pdf->SetFillColor(255, 255, 255);
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalDiscount") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalDiscount") : ''), 0, 'L', 1);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl);
				$pdf->MultiCell($largcol2, $tab2_hl, price($total_line_remise, 0, $outputlangs), 0, 'R', 1);

				$index++;
			}
			// Show total NET before discount
			if (!empty(getDolGlobalString("MAIN_SHOW_AMOUNT_BEFORE_DISCOUNT"))) {
				$pdf->SetFillColor(255, 255, 255);
				$pdf->SetXY($col1x, $tab2_top);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalHTBeforeDiscount") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalHTBeforeDiscount") : ''), 0, 'L', 1);
				$pdf->SetXY($col2x, $tab2_top);
				$pdf->MultiCell($largcol2, $tab2_hl, price($total_line_remise + $total_ht, 0, $outputlangs), 0, 'R', 1);

				$index++;
			}
		}

		// Total HT
		$pdf->SetFillColor(255, 255, 255);
		$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
		$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities(empty(getDolGlobalString("MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT")) ? "TotalHT" : "Total") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities(empty(getDolGlobalString("MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT")) ? "TotalHT" : "Total") : ''), 0, 'L', 1);

		$total_ht = ((isModEnabled("multicurrency") && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ht : $object->total_ht);
		$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
		$pdf->MultiCell($largcol2, $tab2_hl, price($sign * ($total_ht + (!empty($object->remise) ? $object->remise : 0)), 0, $outputlangs), 0, 'R', 1);

		// Show VAT by rates and total
		$pdf->SetFillColor(248, 248, 248);

		$total_ttc = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

		$this->atleastoneratenotnull = 0;
		if (empty(getDolGlobalString("MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT"))) {
			$tvaisnull = ((!empty($this->tva) && count($this->tva) == 1 && isset($this->tva['0.000']) && is_float($this->tva['0.000'])) ? true : false);
			if (!empty(getDolGlobalString("MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_IFNULL")) && $tvaisnull) {
				// Nothing to do
			} else {
				//Local tax 1 before VAT
				//if (!empty(getDolGlobalString("FACTURE_LOCAL_TAX1_OPTION") ) && getDolGlobalString("FACTURE_LOCAL_TAX1_OPTION") =='localtax1on')
				//{
				foreach ($this->localtax1 as $localtax_type => $localtax_rate) {
					if (in_array((string) $localtax_type, array('1', '3', '5'))) {
						continue;
					}

					foreach ($localtax_rate as $tvakey => $tvaval) {
						if ($tvakey != 0) {    // On affiche pas taux 0
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey)) {
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
							}

							$totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT1", $mysoc->country_code) : '');
							$totalvat .= ' ';
							$totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$total_localtax = ((isModEnabled("multicurrency") && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? price2num($tvaval * $object->multicurrency_tx, 'MT') : $tvaval);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($total_localtax, 0, $outputlangs), 0, 'R', 1);
						}
					}
				}
				//}
				//Local tax 2 before VAT
				//if (!empty(getDolGlobalString("FACTURE_LOCAL_TAX2_OPTION") ) && getDolGlobalString("FACTURE_LOCAL_TAX2_OPTION") =='localtax2on')
				//{
				foreach ($this->localtax2 as $localtax_type => $localtax_rate) {
					if (in_array((string) $localtax_type, array('1', '3', '5'))) {
						continue;
					}

					foreach ($localtax_rate as $tvakey => $tvaval) {
						if ($tvakey != 0) {    // On affiche pas taux 0
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey)) {
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT2", $mysoc->country_code) : '');
							$totalvat .= ' ';
							$totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$total_localtax = ((isModEnabled("multicurrency") && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? price2num($tvaval * $object->multicurrency_tx, 'MT') : $tvaval);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($total_localtax, 0, $outputlangs), 0, 'R', 1);
						}
					}
				}
				//}

				// Situations totals migth be wrong on huge amounts with old mode 1
				if (getDolGlobalInt('INVOICE_USE_SITUATION') == 1 && $object->situation_cycle_ref && $object->situation_counter > 1) {
					$sum_pdf_tva = 0;
					foreach ($this->tva as $tvakey => $tvaval) {
						$sum_pdf_tva += $tvaval; // sum VAT amounts to compare to object
					}

					if ($sum_pdf_tva != $object->total_tva) { // apply coef to recover the VAT object amount (the good one)
						if (!empty($sum_pdf_tva)) {
							$coef_fix_tva = $object->total_tva / $sum_pdf_tva;
						} else {
							$coef_fix_tva = 1;
						}


						foreach ($this->tva as $tvakey => $tvaval) {
							$this->tva[$tvakey] = $tvaval * $coef_fix_tva;
						}
						foreach ($this->tva_array as $tvakey => $tvaval) {
							$this->tva_array[$tvakey]['amount'] = $tvaval['amount'] * $coef_fix_tva;
						}
					}
				}

				// VAT
				//              foreach ($this->tva_array as $tvakey => $tvaval) {
				//                  if ($tvakey != 0) {    // On affiche pas taux 0
				//                      $this->atleastoneratenotnull++;
				//
				//                      $index++;
				//                      $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				//
				//                      $tvacompl = '';
				//                      if (preg_match('/\*/', $tvakey)) {
				//                          $tvakey = str_replace('*', '', $tvakey);
				//                          $tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
				//                      }
				//                      $totalvat = $outputlangs->transcountrynoentities("TotalVAT", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalVAT", $mysoc->country_code) : '');
				//                      $totalvat .= ' ';
				//                      if (getDolGlobalString('PDF_VAT_LABEL_IS_CODE_OR_RATE') == 'rateonly') {
				//                          $totalvat .= vatrate($tvaval['vatrate'], 1) . $tvacompl;
				//                      } elseif (getDolGlobalString('PDF_VAT_LABEL_IS_CODE_OR_RATE') == 'codeonly') {
				//                          $totalvat .= ($tvaval['vatcode'] ? $tvaval['vatcode'] : vatrate($tvaval['vatrate'], 1)) . $tvacompl;
				//                      } else {
				//                          $totalvat .= vatrate($tvaval['vatrate'], 1) . ($tvaval['vatcode'] ? ' (' . $tvaval['vatcode'] . ')' : '') . $tvacompl;
				//                      }
				//                      $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);
				//
				//                      $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				//                      $pdf->MultiCell($largcol2, $tab2_hl, price(price2num($tvaval['amount'], 'MT'), 0, $outputlangs), 0, 'R', 1);
				//                  }
				//              }

				$index++;
				$pdf->SetFillColor(255, 255, 255);
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("Total TVA"), 0, 'L', 1);

				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->total_tva), 0, 'R', 1);

				//Local tax 1 after VAT
				//if (!empty(getDolGlobalString("FACTURE_LOCAL_TAX1_OPTION") ) && getDolGlobalString("FACTURE_LOCAL_TAX1_OPTION") =='localtax1on')
				//{
				foreach ($this->localtax1 as $localtax_type => $localtax_rate) {
					if (in_array((string) $localtax_type, array('2', '4', '6'))) {
						continue;
					}

					foreach ($localtax_rate as $tvakey => $tvaval) {
						if ($tvakey != 0) {    // On affiche pas taux 0
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey)) {
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT1", $mysoc->country_code) : '');
							$totalvat .= ' ';
							$totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;

							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$total_localtax = ((isModEnabled("multicurrency") && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? price2num($tvaval * $object->multicurrency_tx, 'MT') : $tvaval);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($total_localtax, 0, $outputlangs), 0, 'R', 1);
						}
					}
				}
				//}
				//Local tax 2 after VAT
				//if (!empty(getDolGlobalString("FACTURE_LOCAL_TAX2_OPTION") ) && getDolGlobalString("FACTURE_LOCAL_TAX2_OPTION") =='localtax2on')
				//{
				foreach ($this->localtax2 as $localtax_type => $localtax_rate) {
					if (in_array((string) $localtax_type, array('2', '4', '6'))) {
						continue;
					}

					foreach ($localtax_rate as $tvakey => $tvaval) {
						// retrieve global local tax
						if ($tvakey != 0) {    // On affiche pas taux 0
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey)) {
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT2", $mysoc->country_code) : '');
							$totalvat .= ' ';

							$totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$total_localtax = ((isModEnabled("multicurrency") && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? price2num($tvaval * $object->multicurrency_tx, 'MT') : $tvaval);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($total_localtax, 0, $outputlangs), 0, 'R', 1);
						}
					}
				}


				// Revenue stamp
				if (price2num($object->revenuestamp) != 0) {
					$index++;
					$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("RevenueStamp") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("RevenueStamp", $mysoc->country_code) : ''), $useborder, 'L', 1);

					$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->revenuestamp), $useborder, 'R', 1);
				}

				// Total TTC
				$index++;
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->SetFillColor(224, 224, 224);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalTTC") : ''), $useborder, 'L', 1);

				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $total_ttc, 0, $outputlangs), $useborder, 'R', 1);


				// Retained warranty
				if ($object->displayRetainedWarranty()) {
					$pdf->SetTextColor(40, 40, 40);
					$pdf->SetFillColor(255, 255, 255);

					$retainedWarranty = $object->getRetainedWarrantyAmount();
					$billedWithRetainedWarranty = $object->total_ttc - $retainedWarranty;

					// Billed - retained warranty
					$index++;
					$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("ToPayOn", dol_print_date($object->date_lim_reglement, 'day')), $useborder, 'L', 1);

					$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($largcol2, $tab2_hl, price($billedWithRetainedWarranty), $useborder, 'R', 1);

					// retained warranty
					$index++;
					$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

					$retainedWarrantyToPayOn = $outputlangs->transnoentities("RetainedWarranty") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("RetainedWarranty") : '') . ' (' . $object->retained_warranty . '%)';
					$retainedWarrantyToPayOn .= !empty($object->retained_warranty_date_limit) ? ' ' . $outputlangs->transnoentities("toPayOn", dol_print_date($object->retained_warranty_date_limit, 'day')) : '';

					$pdf->MultiCell($col2x - $col1x, $tab2_hl, $retainedWarrantyToPayOn, $useborder, 'L', 1);
					$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($largcol2, $tab2_hl, price($retainedWarranty), $useborder, 'R', 1);
				}
			}
		}

		$pdf->SetTextColor(0, 0, 0);

		$creditnoteamount = $object->getSumCreditNotesUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0); // Warning, this also include excess received
		$depositsamount = $object->getSumDepositsUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0);

		$resteapayer = price2num($total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
		if (!empty($object->paye)) {
			$resteapayer = 0;
		}

		if (($deja_regle > 0 || $creditnoteamount > 0 || $depositsamount > 0) && empty(getDolGlobalInt("INVOICE_NO_PAYMENT_DETAILS"))) {
			// Already paid + Deposits
			$index++;
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("Paid") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("Paid") : ''), 0, 'L', 0);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($deja_regle + $depositsamount, 0, $outputlangs), 0, 'R', 0);

			// Credit note
			if ($creditnoteamount) {
				$labeltouse = ($outputlangs->transnoentities("CreditNotesOrExcessReceived") != "CreditNotesOrExcessReceived") ? $outputlangs->transnoentities("CreditNotesOrExcessReceived") : $outputlangs->transnoentities("CreditNotes");
				$labeltouse .= (is_object($outputlangsbis) ? (' / ' . ($outputlangsbis->transnoentities("CreditNotesOrExcessReceived") != "CreditNotesOrExcessReceived") ? $outputlangsbis->transnoentities("CreditNotesOrExcessReceived") : $outputlangsbis->transnoentities("CreditNotes")) : '');
				$index++;
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $labeltouse, 0, 'L', 0);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($creditnoteamount, 0, $outputlangs), 0, 'R', 0);
			}

			$index++;
			$pdf->SetTextColor(0, 0, 60);
			$pdf->SetFillColor(224, 224, 224);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("RemainderToPay") : ''), $useborder, 'L', 1);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($resteapayer, 0, $outputlangs), $useborder, 'R', 1);

			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetTextColor(0, 0, 0);
		}

		$index++;
		return ($tab2_top + ($tab2_hl * $index));
	}


	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		Facture		$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param  	Translate	$outputlangsbis	Object lang for output bis
	 *   @return	int							Pos y
	 */
	protected function drawInfoTable(&$pdf, $object, $posy, $outputlangs, $outputlangsbis)
	{
		global $conf, $mysoc;
		krsort($this->tva_array);
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('', '', $default_font_size - 1);
		// Show VAT  details
		if ($object->total_tva!=0) {
			// Total HT by VAT rate
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("Détails TVA") . ':';
			$pdf->MultiCell(100, 4, $titre, 0, 'L');
			$posy = $pdf->GetY() + 1;

			$pdf->Rect($this->marge_gauche, $posy, 20, 4);

			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("Taux TVA");
			$pdf->MultiCell(20, 4, $titre, 0, 'L');
			$pdf->Rect($this->marge_gauche, $posy, 20, 4);

			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche + 20, $posy);
			$titre = $outputlangs->transnoentities("Total HT");
			$pdf->MultiCell(30, 4, $titre, 0, 'C');
			$pdf->Rect($this->marge_gauche + 20, $posy, 30, 4);

			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche + 50, $posy);
			$titre = $outputlangs->transnoentities("TVA");
			$pdf->MultiCell(20, 4, $titre, 0, 'C');
			$pdf->Rect($this->marge_gauche + 50, $posy, 20, 4);

			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche + 70, $posy);
			$titre = $outputlangs->transnoentities("Total TTC");
			$pdf->MultiCell(30, 4, $titre, 0, 'C');
			$pdf->Rect($this->marge_gauche + 70, $posy, 30, 4);

			$posy = $pdf->GetY();
			$tot_ht = 0;
			$tot_tva = 0;
			$tot_ttc = 0;
			foreach ($this->tva_array as $tvakey => $tvaval) {
				$pdf->SetFillColor(255, 255, 255);
				$pdf->SetFont('', 'B', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche, $posy);
				$titre = "TVA " . round($tvakey, 2) . "%";
				$pdf->MultiCell(20, 4, $titre, 0, 'L');
				$pdf->Rect($this->marge_gauche, $posy, 20, 4);

				$pdf->SetFillColor(255, 255, 255);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche + 20, $posy);
				$titre = price($tvaval['tot_ht']);
				$pdf->MultiCell(30, 4, $titre, 0, 'R');
				$pdf->Rect($this->marge_gauche + 20, $posy, 30, 4);
				$tot_ht += $tvaval['tot_ht'];

				$pdf->SetFillColor(255, 255, 255);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche + 50, $posy);
				$titre = price($tvaval['amount']);
				$pdf->MultiCell(20, 4, $titre, 0, 'R');
				$pdf->Rect($this->marge_gauche + 50, $posy, 20, 4);
				$tot_tva += $tvaval['amount'];

				$pdf->SetFillColor(255, 255, 255);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche + 70, $posy);
				$titre = price($tvaval['tot_ht'] + $tvaval['amount']);
				$pdf->MultiCell(30, 4, $titre, 0, 'R');
				$pdf->Rect($this->marge_gauche + 70, $posy, 30, 4);
				$tot_ttc += ($tvaval['tot_ht'] + $tvaval['amount']);

				$posy = $pdf->GetY();
			}

			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = "Total";
			$pdf->MultiCell(20, 4, $titre, 0, 'L');
			$pdf->Rect($this->marge_gauche, $posy, 20, 4);

			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche + 20, $posy);
			$titre = price($tot_ht);
			$pdf->MultiCell(30, 4, $titre, 0, 'R');
			$pdf->Rect($this->marge_gauche + 20, $posy, 30, 4);

			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche + 50, $posy);
			$titre = price($tot_tva);
			$pdf->MultiCell(20, 4, $titre, 0, 'R');
			$pdf->Rect($this->marge_gauche + 50, $posy, 20, 4);

			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche + 70, $posy);
			$titre = price($tot_ttc);
			$pdf->MultiCell(30, 4, $titre, 0, 'R');
			$pdf->Rect($this->marge_gauche + 70, $posy, 30, 4);
			$posy = $pdf->GetY() + 3;
		}

		$posxval = 52;	// Position of values of properties shown on left side
		$posxend = 110;	// End of x for text on left side
		if ($this->page_largeur < 210) { // To work with US executive format
			$posxend -= 10;
		}

		// Show payments conditions
		if ($object->type != 2 && ($object->cond_reglement_code || $object->cond_reglement)) {
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("PaymentConditions").':';
			$pdf->MultiCell($posxval - $this->marge_gauche, 4, $titre, 0, 'L');

			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$lib_condition_paiement = $outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code) != ('PaymentCondition'.$object->cond_reglement_code) ? $outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code) : $outputlangs->convToOutputCharset($object->cond_reglement_doc ? $object->cond_reglement_doc : $object->cond_reglement_label);
			$lib_condition_paiement = str_replace('\n', "\n", $lib_condition_paiement);
			$pdf->MultiCell($posxend - $posxval, 4, $lib_condition_paiement, 0, 'L');

			// We need spaces for 2 lines payment conditions
			$posy = $pdf->GetY()+1  ;
		}

		if ($object->type != 2) {
			// Check a payment mode is defined
			if (empty($object->mode_reglement_code)
				&& empty($conf->global->FACTURE_CHQ_NUMBER)
				&& empty($conf->global->FACTURE_RIB_NUMBER)) {
				$this->error = $outputlangs->transnoentities("ErrorNoPaiementModeConfigured");
			} elseif (($object->mode_reglement_code == 'CHQ' && empty($conf->global->FACTURE_CHQ_NUMBER) && empty($object->fk_account) && empty($object->fk_bank))
				|| ($object->mode_reglement_code == 'VIR' && empty($conf->global->FACTURE_RIB_NUMBER) && empty($object->fk_account) && empty($object->fk_bank))) {
				// Avoid having any valid PDF with setup that is not complete
				$outputlangs->load("errors");

				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->SetTextColor(200, 0, 0);
				$pdf->SetFont('', 'B', $default_font_size - 2);
				$this->error = $outputlangs->transnoentities("ErrorPaymentModeDefinedToWithoutSetup", $object->mode_reglement_code);
				$pdf->MultiCell($posxend - $this->marge_gauche, 3, $this->error, 0, 'L', 0);
				$pdf->SetTextColor(0, 0, 0);

				$posy = $pdf->GetY() + 1;
			}

			// Show payment mode
			if (!empty($object->mode_reglement_code)
				&& $object->mode_reglement_code != 'CHQ'
				&& $object->mode_reglement_code != 'VIR') {
				$pdf->SetFont('', 'B', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche, $posy);
				$titre = $outputlangs->transnoentities("PaymentMode").':';
				$pdf->MultiCell($posxend - $this->marge_gauche, 5, $titre, 0, 'L');

				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posxval, $posy);
				$lib_mode_reg = $outputlangs->transnoentities("PaymentType".$object->mode_reglement_code) != ('PaymentType'.$object->mode_reglement_code) ? $outputlangs->transnoentities("PaymentType".$object->mode_reglement_code) : $outputlangs->convToOutputCharset($object->mode_reglement);
				$pdf->MultiCell($posxend - $posxval, 5, $lib_mode_reg, 0, 'L');

				$posy = $pdf->GetY();
			}

			// Show online payment link
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CB' || $object->mode_reglement_code == 'VAD') {
				$useonlinepayment = 0;
				if (!empty($conf->global->PDF_SHOW_LINK_TO_ONLINE_PAYMENT)) {
					if (!empty($conf->paypal->enabled)) {
						$useonlinepayment++;
					}
					if (!empty($conf->stripe->enabled)) {
						$useonlinepayment++;
					}
					if (!empty($conf->paybox->enabled)) {
						$useonlinepayment++;
					}
				}

				if ($object->statut != Facture::STATUS_DRAFT && $useonlinepayment) {
					require_once DOL_DOCUMENT_ROOT.'/core/lib/payments.lib.php';
					global $langs;

					$langs->loadLangs(array('payment', 'paybox', 'stripe'));
					$servicename = $langs->transnoentities('Online');
					$paiement_url = getOnlinePaymentUrl('', 'invoice', $object->ref, '', '', '');
					$linktopay = $langs->trans("ToOfferALinkForOnlinePayment", $servicename).' <a href="'.$paiement_url.'">'.$outputlangs->transnoentities("ClickHere").'</a>';

					$pdf->SetXY($this->marge_gauche, $posy);
					$pdf->writeHTMLCell($posxend - $this->marge_gauche, 5, '', '', dol_htmlentitiesbr($linktopay), 0, 1);

					$posy = $pdf->GetY() + 1;
				}
			}

			// Show payment mode CHQ
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ') {
				// If payment mode unregulated or payment mode forced to CHQ
				if (!empty($conf->global->FACTURE_CHQ_NUMBER)) {
					$diffsizetitle = (empty($conf->global->PDF_DIFFSIZE_TITLE) ? 3 : $conf->global->PDF_DIFFSIZE_TITLE);

					if ($conf->global->FACTURE_CHQ_NUMBER > 0) {
						$account = new Account($this->db);
						$account->fetch($conf->global->FACTURE_CHQ_NUMBER);

						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
						$pdf->MultiCell($posxend - $this->marge_gauche, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $account->proprio), 0, 'L', 0);
						$posy = $pdf->GetY() + 1;

						if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS)) {
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont('', '', $default_font_size - $diffsizetitle);
							$pdf->MultiCell($posxend - $this->marge_gauche, 3, $outputlangs->convToOutputCharset($account->owner_address), 0, 'L', 0);
							$posy = $pdf->GetY() + 2;
						}
					}
					if ($conf->global->FACTURE_CHQ_NUMBER == -1) {
						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
						$pdf->MultiCell($posxend - $this->marge_gauche, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $this->emetteur->name), 0, 'L', 0);
						$posy = $pdf->GetY() + 1;

						if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS)) {
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont('', '', $default_font_size - $diffsizetitle);
							$pdf->MultiCell($posxend - $this->marge_gauche, 3, $outputlangs->convToOutputCharset($this->emetteur->getFullAddress()), 0, 'L', 0);
							$posy = $pdf->GetY() + 2;
						}
					}
				}
			}

			// If payment mode not forced or forced to VIR, show payment with BAN
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR') {
				if ($object->fk_account > 0 || $object->fk_bank > 0 || !empty($conf->global->FACTURE_RIB_NUMBER)) {
					$bankid = ($object->fk_account <= 0 ? $conf->global->FACTURE_RIB_NUMBER : $object->fk_account);
					if ($object->fk_bank > 0) {
						$bankid = $object->fk_bank; // For backward compatibility when object->fk_account is forced with object->fk_bank
					}
					$account = new Account($this->db);
					$account->fetch($bankid);

					$curx = $this->marge_gauche;
					$cury = $posy;

					$posy = pdf_bank($pdf, $outputlangs, $curx, $cury, $account, 0, $default_font_size);

					$posy += 2;
				}
			}
		}

		return $posy;
	}
}
