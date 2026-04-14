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
 * \file    workshop/core/modules/workshopor/doc/pdf_standard_or.modules.php
 * \ingroup workshop
 * \brief   Modèle PDF standard pour les Ordres de Réparation Workshop
 */

dol_include_once('/workshop/core/modules/workshop/modules_workshop.php');
dol_include_once('/workshop/class/operationorder.class.php');
dol_include_once('/workshop/class/operationorder_jobs.class.php');
dol_include_once('/workshop/class/Vehicule.class.php');
dol_include_once('/workshop/class/vehiculetype.class.php');
dol_include_once('/workshop/class/vehiculemark.class.php');
dol_include_once('/workshop/class/vehiculecontracttype.class.php');
dol_include_once('/workshop/class/servicetype.class.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * Class to build standard PDF document for Workshop Repair Orders
 */
class pdf_standard_or extends ModelePDFWorkshop
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Model name
	 */
	public $name;

	/**
	 * @var string Model description
	 */
	public $description;

	/**
	 * @var string Document type
	 */
	public $type;

	/**
	 * @var array Minimum PHP version required
	 */
	public $phpmin = array(8, 0);

	/**
	 * @var string Version
	 */
	public $version = 'dolibarr';

	/**
	 * @var float Page width (mm)
	 */
	public $page_largeur;

	/**
	 * @var float Page height (mm)
	 */
	public $page_hauteur;

	/**
	 * @var array Page format
	 */
	public $format;

	/**
	 * @var float Left margin (mm)
	 */
	public $marge_gauche;

	/**
	 * @var float Right margin (mm)
	 */
	public $marge_droite;

	/**
	 * @var float Top margin (mm)
	 */
	public $marge_haute;

	/**
	 * @var float Bottom margin (mm)
	 */
	public $marge_basse;

	/**
	 * @var Societe Issuer (emitter)
	 */
	public $emetteur;


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		$langs->loadLangs(array('workshop@workshop', 'companies', 'bills', 'main'));

		$this->db          = $db;
		$this->name        = 'standard_or';
		$this->description = $langs->trans('ORPdfStandardDescription');

		// Document type
		$this->type = 'pdf';

		// Page format A4
		$formatarray         = pdf_getFormat();
		$this->page_largeur  = $formatarray['width'];
		$this->page_hauteur  = $formatarray['height'];
		$this->format        = array($this->page_largeur, $this->page_hauteur);

		// Margins (mm) – use global settings when defined
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute  = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse  = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

		// Option flags
		$this->option_logo            = 1; // Display company logo
		$this->option_draft_watermark = 1; // Support draft watermark

		// Issuer = my company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Build the PDF onto disk
	 *
	 * @param  Operationorder $object         OR object to generate
	 * @param  Translate      $outputlangs    Output language object
	 * @param  string         $srctemplatepath Not used for PDF models
	 * @param  int            $hidedetails    Do not show line details
	 * @param  int            $hidedesc       Do not show descriptions
	 * @param  int            $hideref        Do not show references
	 * @return int                            1 = OK, 0 = KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $mysoc, $hookmanager;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}

		// Force ISO output charset when using FPDF
		if (!empty(getDolGlobalString('MAIN_USE_FPDF'))) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		$outputlangs->loadLangs(array('workshop@workshop', 'main', 'dict', 'companies', 'bills'));

		// ── Output path ──────────────────────────────────────────────────────
		if (!$conf->workshop->dir_output) {
			$this->error = $langs->transnoentities('ErrorWorkshopDirOutput');
			return 0;
		}

		$object->fetch_thirdparty();

		if ($object->specimen) {
			$dir  = $conf->workshop->multidir_output[$conf->entity];
			$file = $dir.'/SPECIMEN.pdf';
		} else {
			$dir  = $conf->workshop->multidir_output[$object->entity].'/operationorder/'.(int) $object->id;
			$file = $dir.'/'.dol_sanitizeFileName($object->ref).'.pdf';
		}

		if (!file_exists($dir)) {
			if (dol_mkdir($dir) < 0) {
				$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
				return 0;
			}
		}

		// ── PDF initialisation ───────────────────────────────────────────────
		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		global $action;
		$hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);

		$pdf = pdf_getInstance($this->format);
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$pdf->SetAutoPageBreak(1, 0);

		$heightforfooter   = $this->marge_basse + (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 15 : 10);
		$heightforfreetext = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5);

		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));

		// Background PDF template
		if (getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')) {
			$pagecount = $pdf->setSourceFile($conf->mycompany->multidir_output[$object->entity].'/'.getDolGlobalString('MAIN_ADD_PDF_BACKGROUND'));
			$tplidx    = $pdf->importPage(1);
		}

		$pdf->Open();
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities('OperationOrder'));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

		// ── First page ───────────────────────────────────────────────────────
		$pdf->AddPage();
		if (!empty($tplidx)) {
			$pdf->useTemplate($tplidx);
		}

		// Bandeau dessiné sur chaque page ; cadres info uniquement sur la première page
		$header_bottom = $this->_pagehead($pdf, $object, 1, $outputlangs);
		$tab_top = $this->_infoboxes($pdf, $object, $outputlangs, $header_bottom);
		$pdf->SetFont('', '', $default_font_size - 1);
		$pdf->SetTextColor(0, 0, 0);

		// ── Cadre corps du document ──────────────────────────────────────────
		// Le cadre s'étend toujours jusqu'à la marge basse (pleine hauteur).
		// Sur la dernière page, _signatureboxes() pose un fond blanc sur sa zone
		// avant de dessiner les cadres → masque proprement le bord inférieur du corps.
		$sig_h    = 35; // hauteur du triple cadre bas — doit rester synchronisé avec _signatureboxes()
		$footer_y = $this->page_hauteur - $this->marge_basse - $sig_h;
		$body_x   = $this->marge_gauche;
		$body_w   = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$body_y   = $tab_top;
		$body_h   = $this->page_hauteur - $this->marge_basse - $body_y - 3;

		$pdf->SetDrawColor(80, 80, 140);
		$pdf->SetLineWidth(0.4);
		$pdf->RoundedRect($body_x, $body_y, $body_w, $body_h, 3, '1111', 'D');
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetLineWidth(0.2);
		$pdf->SetTextColor(0, 0, 0);

		// ── Contenu : jobs de l'OR ──────────────────────────────────────────
		// Pleine hauteur sur toutes les pages : la signature se place sur la dernière
		// page uniquement. Si les jobs de la dernière page dépassent footer_y,
		// _signatureboxes() est renvoyée sur une nouvelle page.
		$body_bottom = $this->page_hauteur - $this->marge_basse - 3;
		$drawResult  = $this->_drawjobs($pdf, $object, $outputlangs, $body_x, $body_y, $body_w, $body_bottom);
		$cy_jobs     = $drawResult['cy'];
		$moredoc     = $drawResult['moredoc'];
		$moredoc_st  = $drawResult['moredoc_st'];

		// ── Triple cadre bas de page (commentaires + signatures) ────────────
		// Si le dernier job empiète sur la zone signature, on l'envoie sur une
		// nouvelle page (la page précédente reste pleine, pas de signature dessus).
		if ($cy_jobs > $footer_y) {
			$pdf->AddPage();
			$this->_pagehead($pdf, $object, 0, $outputlangs);
		}
		$this->_signatureboxes($pdf, $object, $outputlangs);

		// ── Annexion des documents obligatoires des produits ─────────────────
		// Chaque produit avec un extrafield doc_oblig non vide a son PDF annexé
		// (une seule fois par ref produit, même si le produit apparaît plusieurs fois)
		if (!empty($moredoc)) {
			foreach ($moredoc as $productref => $docinfo) {
				$this->_addAttachedDoc($pdf, $productref, $docinfo['docname'], (int) $docinfo['type'], (int) $docinfo['entity']);
			}
		}

		// ── Annexion des documents obligatoires des types de service ─────────
		if (!empty($moredoc_st)) {
			$stDocDir = $conf->workshop->multidir_output[$conf->entity].'/servicetype';
			foreach ($moredoc_st as $fk_st => $docname) {
				$infile = $stDocDir.'/'.$docname.'.pdf';
				if (file_exists($infile) && is_readable($infile)) {
					$pagecount = $pdf->setSourceFile($infile);
					for ($i = 1; $i <= $pagecount; $i++) {
						$tplIdx = $pdf->importPage($i);
						if ($tplIdx !== false) {
							$s = $pdf->getTemplatesize($tplIdx);
							$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
							$pdf->useTemplate($tplIdx);
						} else {
							dol_syslog('pdf_standard_or::write_file impossible d\'importer la page '.$i.' de '.$infile, LOG_WARNING);
							setEventMessages('Document obligatoire introuvable ou protégé pour le type de service #'.$fk_st.' : '.$docname.'.pdf', null, 'warnings');
							break;
						}
					}
				} else {
					dol_syslog('pdf_standard_or::write_file fichier introuvable : '.$infile, LOG_WARNING);
					setEventMessages('Document obligatoire introuvable pour le type de service #'.$fk_st.' : '.$docname.'.pdf', null, 'warnings');
				}
			}
		}

		// ── Save ─────────────────────────────────────────────────────────────
		$pdf->Close();
		$pdf->Output($file, 'F');

		// Trigger hook after generation
		$hookmanager->executeHooks('afterPDFCreation', $parameters, $object, $action);

		// Return result
		$this->result = array('fullpath' => $file);
		return 1;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Draw page header banner — called on EVERY page
	 *
	 * Bandeau 30 mm (dans les marges) :
	 *   [ Logo ]  [ Atelier - Société / Ordre de Réparation - Réf ]  [ Barcode ]
	 *
	 * Les cadres info client/OR ne sont dessinés que sur la première page
	 * via _infoboxes().
	 *
	 * @param  TCPDF          $pdf         PDF object
	 * @param  Operationorder $object      OR object
	 * @param  int            $showaddress Non utilisé (conservé pour compatibilité)
	 * @param  Translate      $outputlangs Output language
	 * @return float          Y absolue du bas du bandeau (= début de la zone sous le bandeau)
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		// phpcs:enable
		global $conf, $langs, $mysoc;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		// ── Bandeau en-tête : 30 mm, dans les marges d'impression ────────────
		$header_x = $this->marge_gauche;
		$header_y = $this->marge_haute;
		$header_w = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$header_h = 30;
		$pad      = 3; // padding intérieur gauche/haut/bas

		// Zone logo (gauche)
		$logo_x    = $header_x + $pad;
		$logo_maxw = 45;
		$logo_maxh = $header_h - 2 * $pad; // 24 mm

		// Zone code-barres : flush sur la marge droite
		$barcode_w = 42;
		$barcode_h = 20;
		$barcode_x = $this->page_largeur - $this->marge_droite - $barcode_w;
		$barcode_y = $header_y + ($header_h - $barcode_h) / 2.0;

		// Zone texte centrale (entre logo et barcode)
		$center_x = $logo_x + $logo_maxw + $pad;
		$center_w = $barcode_x - $center_x - 2;

		// ── Cadre arrondi ─────────────────────────────────────────────────────
		$pdf->SetDrawColor(80, 80, 140);
		$pdf->SetLineWidth(0.5);
		$pdf->RoundedRect($header_x, $header_y, $header_w, $header_h, 4, '1111', 'D');

		// ── Logo ──────────────────────────────────────────────────────────────
		$logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
		if ($mysoc->logo && is_readable($logo)) {
			$imgsize = @getimagesize($logo);
			if (is_array($imgsize) && $imgsize[0] > 0 && $imgsize[1] > 0) {
				$ratio = $imgsize[0] / $imgsize[1];
				if ($ratio > ($logo_maxw / $logo_maxh)) {
					$display_w = $logo_maxw;
					$display_h = round($logo_maxw / $ratio, 2);
				} else {
					$display_h = $logo_maxh;
					$display_w = round($logo_maxh * $ratio, 2);
				}
			} else {
				$display_h = min(pdf_getHeightForLogo($logo), $logo_maxh);
				$display_w = 0;
			}
			$logo_vert = $header_y + ($header_h - $display_h) / 2.0;
			$pdf->Image($logo, $logo_x, $logo_vert, ($display_w ?: 0), $display_h);
		} else {
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->SetTextColor(0, 0, 80);
			$pdf->SetXY($logo_x, $header_y + ($header_h / 2) - 3);
			$pdf->MultiCell($logo_maxw, 6, $outputlangs->convToOutputCharset($mysoc->name), 0, 'C');
		}

		// ── Texte central : deux lignes verticalement centrées ────────────────
		$text_block_h = 14;
		$text_y1      = $header_y + ($header_h - $text_block_h) / 2.0;
		$text_y2      = $text_y1 + 8;

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetTextColor(40, 40, 100);
		$pdf->SetXY($center_x, $text_y1);
		$pdf->MultiCell(
			$center_w,
			7,
			$outputlangs->transnoentities('Workshop').' - '.$outputlangs->convToOutputCharset($mysoc->name),
			0,
			'C'
		);

		$pdf->SetFont('', 'B', $default_font_size + 1);
		$pdf->SetTextColor(60, 60, 60);
		$pdf->SetXY($center_x, $text_y2);
		$pdf->MultiCell(
			$center_w,
			6,
			$outputlangs->transnoentities('OperationOrder').' - '.$outputlangs->convToOutputCharset($object->ref),
			0,
			'C'
		);

		// ── Code-barres Code128 (rowid de l'OR), aligné marge droite ─────────
		$barcode_val = !empty($object->id) ? (string) $object->id : '1';
		$pdf->write1DBarcode(
			$barcode_val,
			'C128',
			$barcode_x,
			$barcode_y,
			$barcode_w,
			$barcode_h,
			'',
			array(
				'position'    => '',
				'align'       => 'C',
				'stretch'     => false,
				'fitwidth'    => true,
				'cellfitmaxw' => '',
				'border'      => false,
				'padding'     => 0,
				'fgcolor'     => array(0, 0, 0),
				'bgcolor'     => false,
				'text'        => true,
				'font'        => 'helvetica',
				'fontsize'    => 7,
				'stretchtext' => 4,
			),
			'N'
		);

		// Réinitialisation du style de tracé
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetLineWidth(0.2);
		$pdf->SetTextColor(0, 0, 0);

		// Retourne le bas du bandeau — _infoboxes() dessinera en dessous
		return $header_y + $header_h;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Draw first-page info boxes — called ONLY on the first page
	 *
	 * Deux cadres côte à côte, coins arrondis, séparés de 2 mm :
	 *   Gauche : infos client (nom, adresse complète, tél, email) — max 7 lignes
	 *   Droite : infos OR (réf, date création, créateur, réf client, date document)
	 *
	 * @param  TCPDF          $pdf         PDF object
	 * @param  Operationorder $object      OR object
	 * @param  Translate      $outputlangs Output language
	 * @param  float          $start_y     Bas du bandeau (retour de _pagehead)
	 * @return float          Y absolue de début du corps du document
	 */
	protected function _infoboxes(&$pdf, $object, $outputlangs, $start_y)
	{
		// phpcs:enable
		global $mysoc;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$header_x = $this->marge_gauche;
		$header_w = $this->page_largeur - $this->marge_gauche - $this->marge_droite;

		// ── Dimensions ─────────────────────────────────────────────────────
		$gap     = 2;    // espace entre les deux cadres
		$boxes_y = $start_y + 3;
		$title_h = 5;
		$ipad    = 2;    // padding intérieur
		$line_h  = 3.5;
		$box_h   = $title_h + $ipad + (int) ceil(7 * $line_h) + $ipad; // ~34 mm
		$box_w   = ($header_w - $gap) / 2.0;
		$box_lx  = $header_x;
		$box_rx  = $header_x + $box_w + $gap;
		$r       = 2;    // rayon des coins arrondis

		// ── Cadres et barres de titre ───────────────────────────────────────
		// Ordre : fills d'abord, puis bordures par-dessus (sinon le fill masque la bordure)
		// Ordre des coins TCPDF : [0]=NE [1]=SE [2]=SW [3]=NW → '1001' = haut-droit + haut-gauche
		$pdf->SetFillColor(210, 215, 230);
		$pdf->RoundedRect($box_lx, $boxes_y, $box_w, $title_h, $r, '1001', 'F');
		$pdf->RoundedRect($box_rx, $boxes_y, $box_w, $title_h, $r, '1001', 'F');

		$pdf->SetDrawColor(80, 80, 140);
		$pdf->SetLineWidth(0.4);
		$pdf->RoundedRect($box_lx, $boxes_y, $box_w, $box_h, $r, '1111', 'D');
		$pdf->RoundedRect($box_rx, $boxes_y, $box_w, $box_h, $r, '1111', 'D');

		// Titres
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->SetTextColor(40, 40, 100);
		$pdf->SetXY($box_lx + $ipad, $boxes_y + 0.5);
		$pdf->Cell($box_w - 2 * $ipad, $title_h - 1, $outputlangs->transnoentities('Customer'), 0, 0, 'C');
		$pdf->SetXY($box_rx + $ipad, $boxes_y + 0.5);
		$pdf->Cell($box_w - 2 * $ipad, $title_h - 1, $outputlangs->transnoentities('OperationOrder'), 0, 0, 'C');

		// ── Cadre gauche : infos client ─────────────────────────────────────
		$cx = $box_lx + $ipad;
		$cw = $box_w - 2 * $ipad;
		$cy = $boxes_y + $title_h + $ipad;

		if (!empty($object->thirdparty)) {
			// Nom (gras)
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($cx, $cy);
			$pdf->Cell($cw, $line_h, $outputlangs->convToOutputCharset($object->thirdparty->name), 0, 0, 'L');
			$cy += $line_h;

			// Adresse (max 3 lignes)
			$pdf->SetFont('', '', $default_font_size - 1);
			if (!empty($object->thirdparty->address)) {
				foreach (array_slice(explode("\n", trim($object->thirdparty->address)), 0, 3) as $addr_line) {
					$addr_line = trim($addr_line);
					if ($addr_line === '') {
						continue;
					}
					$pdf->SetXY($cx, $cy);
					$pdf->Cell($cw, $line_h, $outputlangs->convToOutputCharset($addr_line), 0, 0, 'L');
					$cy += $line_h;
				}
			}

			// Code postal + ville
			$zip_town = trim($object->thirdparty->zip.' '.$object->thirdparty->town);
			if ($zip_town !== '') {
				$pdf->SetXY($cx, $cy);
				$pdf->Cell($cw, $line_h, $outputlangs->convToOutputCharset($zip_town), 0, 0, 'L');
				$cy += $line_h;
			}

			// Téléphone
			if (!empty($object->thirdparty->phone)) {
				$pdf->SetXY($cx, $cy);
				$pdf->Cell($cw, $line_h, $outputlangs->transnoentities('Phone').' : '.$object->thirdparty->phone, 0, 0, 'L');
				$cy += $line_h;
			}

			// Email
			if (!empty($object->thirdparty->email)) {
				$pdf->SetXY($cx, $cy);
				$pdf->Cell($cw, $line_h, $outputlangs->transnoentities('Email').' : '.$object->thirdparty->email, 0, 0, 'L');
			}
		}

		// ── Cadre droit : infos OR ──────────────────────────────────────────
		$rx      = $box_rx + $ipad;
		$rw      = $box_w - 2 * $ipad;
		$ry      = $boxes_y + $title_h + $ipad;
		$label_w = 35;
		$value_w = $rw - $label_w;

		// Chargement du créateur
		$creator_name = '';
		if (!empty($object->fk_user_creat)) {
			require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
			$user_creator = new User($this->db);
			if ($user_creator->fetch((int) $object->fk_user_creat) > 0) {
				$creator_name = $user_creator->getFullName($outputlangs);
			}
		}

		// Toutes les lignes sont toujours affichées (y compris réf client vide)
		$or_rows = array(
			array($outputlangs->transnoentities('Ref'),                     (string) $object->ref),
			array($outputlangs->transnoentities('DateCreation'),            dol_print_date($object->date_creation, 'dayhour', false, $outputlangs)),
			array($outputlangs->transnoentities('WorkshopPDFCreatedBy'),    $creator_name),
			array($outputlangs->transnoentities('RefCustomer'),             (string) $object->ref_client),
			array($outputlangs->transnoentities('WorkshopPDFDocumentDate'), dol_print_date(dol_now(), 'dayhour', false, $outputlangs)),
		);

		foreach ($or_rows as $row) {
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->SetTextColor(60, 60, 100);
			$pdf->SetXY($rx, $ry);
			$pdf->Cell($label_w, $line_h, $row[0].' :', 0, 0, 'L');

			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($rx + $label_w, $ry);
			$pdf->Cell($value_w, $line_h, $outputlangs->convToOutputCharset($row[1]), 0, 0, 'L');
			$ry += $line_h;
		}

		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetLineWidth(0.2);
		$pdf->SetTextColor(0, 0, 0);

		// ── Cadre véhicule (pleine largeur, première page uniquement) ──────────
		$veh_y    = $boxes_y + $box_h + 3;
		$veh_x    = $header_x;
		$veh_w    = $header_w;
		$veh_rows = 5; // colonne gauche = la plus longue (5 lignes)
		$veh_h    = $title_h + $ipad + (int) ceil($veh_rows * $line_h) + $ipad;

		// Charger le véhicule lié à l'OR
		$vehicule = null;
		if (!empty($object->fk_vehicule)) {
			$vehicule = new Vehicule($this->db);
			if ($vehicule->fetch((int) $object->fk_vehicule) <= 0) {
				$vehicule = null;
			}
		}

		// Résoudre les libellés des dictionnaires
		$veh_type_label     = '';
		$veh_mark_label     = '';
		$veh_contract_label = '';
		if (!is_null($vehicule)) {
			if (!empty($vehicule->fk_vehicule_type)) {
				$vehTypeObj = new VehiculeType($this->db);
				$veh_type_label = $vehTypeObj->getValueFromId((int) $vehicule->fk_vehicule_type);
			}
			if (!empty($vehicule->fk_vehicule_mark)) {
				$vehMarkObj = new VehiculeMark($this->db);
				$veh_mark_label = $vehMarkObj->getValueFromId((int) $vehicule->fk_vehicule_mark);
			}
			if (!empty($vehicule->fk_contract_type)) {
				$vehContractObj = new VehiculeContractType($this->db);
				$veh_contract_label = $vehContractObj->getValueFromId((int) $vehicule->fk_contract_type);
			}
		}

		// Dessin : fill du titre en premier, puis bordure par-dessus
		$pdf->SetFillColor(210, 215, 230);
		$pdf->RoundedRect($veh_x, $veh_y, $veh_w, $title_h, $r, '1001', 'F');
		$pdf->SetDrawColor(80, 80, 140);
		$pdf->SetLineWidth(0.4);
		$pdf->RoundedRect($veh_x, $veh_y, $veh_w, $veh_h, $r, '1111', 'D');

		// Titre centré
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->SetTextColor(40, 40, 100);
		$pdf->SetXY($veh_x + $ipad, $veh_y + 0.5);
		$pdf->Cell($veh_w - 2 * $ipad, $title_h - 1, $outputlangs->transnoentities('WorkshopPDFVehicle'), 0, 0, 'C');

		// Ligne séparatrice verticale entre les deux colonnes
		$sep_x   = $veh_x + $veh_w / 2.0;
		$sep_top = $veh_y + $title_h;
		$sep_bot = $veh_y + $veh_h;
		$pdf->SetDrawColor(180, 180, 200);
		$pdf->SetLineWidth(0.2);
		$pdf->Line($sep_x, $sep_top, $sep_x, $sep_bot);

		// Calcul des colonnes
		// label_w_veh doit accommoder le libellé le plus long de la colonne droite
		// "Date 1ère mise en circulation" ≈ 48 mm à 9pt Helvetica
		$col_inner_w = $veh_w / 2.0 - 2 * $ipad;
		$label_w_veh = 48;
		$value_w_veh = $col_inner_w - $label_w_veh;
		$col_left_x  = $veh_x + $ipad;
		$col_right_x = $veh_x + $veh_w / 2.0 + $ipad;
		$col_y       = $veh_y + $title_h + $ipad;

		// Colonne gauche : Immat, VIN, Type, Marque, Modèle
		$veh_left_rows = array(
			array($outputlangs->transnoentities('immatriculation'), $vehicule ? (string) $vehicule->immatriculation : ''),
			array($outputlangs->transnoentities('VIN'),             $vehicule ? (string) $vehicule->vin : ''),
			array($outputlangs->transnoentities('vehiculeType'),    (string) $veh_type_label),
			array($outputlangs->transnoentities('vehiculeMark'),    (string) $veh_mark_label),
			array($outputlangs->transnoentities('vehiculeModele'),  $vehicule ? (string) $vehicule->modele : ''),
		);

		// Colonne droite : Km OR, Date 1ère MEC, Type contrat, Date fin contrat
		$veh_right_rows = array(
			array($outputlangs->transnoentities('kilometrage'),       (string) $object->km),
			array($outputlangs->transnoentities('immatriculation_date'), dol_print_date($vehicule ? $vehicule->date_immat : null, 'day', false, $outputlangs)),
			array($outputlangs->transnoentities('contractType'),      (string) $veh_contract_label),
			array($outputlangs->transnoentities('date_end_contract'), dol_print_date($vehicule ? $vehicule->date_end_contract : null, 'day', false, $outputlangs)),
		);

		// Affichage colonne gauche
		$lvy = $col_y;
		foreach ($veh_left_rows as $vrow) {
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->SetTextColor(60, 60, 100);
			$pdf->SetXY($col_left_x, $lvy);
			$pdf->Cell($label_w_veh, $line_h, $vrow[0].' :', 0, 0, 'L');
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($col_left_x + $label_w_veh, $lvy);
			$pdf->Cell($value_w_veh, $line_h, $outputlangs->convToOutputCharset($vrow[1]), 0, 0, 'L');
			$lvy += $line_h;
		}

		// Affichage colonne droite
		$rvy = $col_y;
		foreach ($veh_right_rows as $vrow) {
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->SetTextColor(60, 60, 100);
			$pdf->SetXY($col_right_x, $rvy);
			$pdf->Cell($label_w_veh, $line_h, $vrow[0].' :', 0, 0, 'L');
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($col_right_x + $label_w_veh, $rvy);
			$pdf->Cell($value_w_veh, $line_h, $outputlangs->convToOutputCharset($vrow[1]), 0, 0, 'L');
			$rvy += $line_h;
		}

		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetLineWidth(0.2);
		$pdf->SetTextColor(0, 0, 0);

		return $veh_y + $veh_h + 3;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Draw jobs content inside the body frame
	 *
	 * Pour chaque job de l'OR (trié par rang) :
	 *   1. Cadre à angles droits : ligne d'en-tête [Type | Libellé | Tiers]
	 *      suivi de la description multi-lignes (si présente)
	 *   2. Ligne MO sous le cadre : [Main d'oeuvre | qty_mo | code-barres rowid]
	 *   3. Lignes de détail (pièces/services) sous la ligne MO :
	 *      pic | ref | libellé | [stock (pièces)] | qty (droite, X fixe)
	 *
	 * La bordure du cadre job est tracée APRÈS le contenu textuel (pattern TCPDF).
	 * Des sauts de page automatiques sont insérés dès que le contenu dépasserait
	 * $body_bottom (haut du bloc signature).
	 *
	 * @param  TCPDF          $pdf          PDF object
	 * @param  Operationorder $object       OR object
	 * @param  Translate      $outputlangs  Output language
	 * @param  float          $body_x       X du cadre corps (bord gauche)
	 * @param  float          $body_y       Y du cadre corps (bord haut)
	 * @param  float          $body_w       Largeur du cadre corps
	 * @param  float          $body_bottom  Y limite basse (haut du bloc signature)
	 * @return array          ['cy' => float, 'moredoc' => array docs produits, 'moredoc_st' => array docs types de service]
	 */
	protected function _drawjobs(&$pdf, $object, $outputlangs, $body_x, $body_y, $body_w, $body_bottom)
	{
		// phpcs:enable
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
		dol_include_once('/workshop/class/operationorderdet.class.php');

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$jobObj = new Operationorder_jobs($this->db);
		$jobs   = $jobObj->fetchAllByOperationorder((int) $object->id);

		if (!is_array($jobs) || empty($jobs)) {
			return $body_y;
		}

		// ── Dimensions ─────────────────────────────────────────────────────
		$sig_h     = 35;   // hauteur du bloc signature — synchronisé avec _signatureboxes()
		$bpad      = 3;    // padding intérieur du cadre corps
		$jpad      = 1.5;  // padding intérieur du cadre job
		$row_h     = 6;    // hauteur ligne d'en-tête du job
		$line_h    = 3.5;  // hauteur d'une ligne de texte
		$gap       = 2;    // espace vertical entre blocs jobs
		$mo_h      = 8;    // hauteur de la ligne MO (sous le cadre)
		$det_row_h = 5;    // hauteur d'une ligne de détail

		// Zone utile dans le cadre corps
		$cx = $body_x + $bpad;
		$cw = $body_w - 2 * $bpad;
		$cy = $body_y + $bpad;

		// ── Colonnes lignes de détail ────────────────────────────────────────
		// La colonne quantité est à X fixe (même position pour pièces et services)
		$pic_w     = 5;    // picto type
		$ref_w     = 28;   // référence produit
		$qty_det_w = 15;   // quantité (droite)
		$stk_w     = 32;   // emplacement stock (pièces uniquement, avant qty)

		$det_x     = $cx + $jpad + $pic_w;               // début de la colonne ref
		$qty_col_x = $cx + $cw - $jpad - $qty_det_w;     // X fixe colonne qty

		// Pré-charger le résolveur de types de service
		$serviceTypeObj = new ServiceType($this->db);

		// Caches pour éviter les requêtes dupliquées sur produits / entrepôts
		$product_cache   = array();  // fk_product => ['ref' => string, 'type' => int, 'entity' => int, 'doc_oblig' => string|'']
		$warehouse_cache = array();

		// Documents obligatoires à annexer au PDF (ref produit => ['docname' => string, 'type' => int, 'entity' => int])
		$moredoc = array();

		// Documents obligatoires liés aux types de service/job (fk_service_type => docname)
		$moredoc_st    = array();
		$st_doc_cache  = array(); // fk_service_type => doc_obl (string|'')

		// ── Chargement Font Awesome Solid pour les pictos pièce/service ────────
		// On essaie plusieurs chemins candidats ; en cas d'échec on utilise
		// des formes géométriques distinctes (carré / cercle) — N&B compatible.
		$fa_font = '';
		if (class_exists('TCPDF_FONTS')) {
			$fa_candidates = array(
				DOL_DOCUMENT_ROOT.'/theme/common/fontawesome-5/webfonts/fa-solid-900.ttf',
				DOL_DOCUMENT_ROOT.'/theme/eldy/fontawesome-5/webfonts/fa-solid-900.ttf',
				DOL_DOCUMENT_ROOT.'/theme/common/fontawesome-6/webfonts/fa-solid-900.ttf',
			);
			foreach ($fa_candidates as $fa_path) {
				if (file_exists($fa_path)) {
					$loaded = TCPDF_FONTS::addTTFfont($fa_path, 'TrueTypeUnicode', '', 32);
					if (!empty($loaded)) {
						$fa_font = $loaded;
						break;
					}
				}
			}
		}

		// Désactiver le saut de page automatique pendant le dessin
		$pdf->SetAutoPageBreak(false);

		foreach ($jobs as $job) {
			// ── Données préparées HORS transaction (une seule fois par job) ──────
			$type_label = '';
			if (!empty($job->fk_service_type)) {
				$fk_st = (int) $job->fk_service_type;
				$lbl = $serviceTypeObj->getValueFromId($fk_st);
				if ($lbl) {
					$type_label = (string) $lbl;
				}

				// Collecter le document obligatoire du type de service (dédupliqué par fk_service_type)
				if (!isset($st_doc_cache[$fk_st])) {
					$stObj = new ServiceType($this->db);
					if ($stObj->fetch($fk_st) > 0 && !empty($stObj->doc_obl)) {
						$st_doc_cache[$fk_st] = $stObj->doc_obl;
					} else {
						$st_doc_cache[$fk_st] = '';
					}
				}
				if ($st_doc_cache[$fk_st] !== '') {
					$moredoc_st[$fk_st] = $st_doc_cache[$fk_st];
				}
			}

			$tiers_name = '';
			if (!empty($job->fk_soc)) {
				$socObj = new Societe($this->db);
				if ($socObj->fetch((int) $job->fk_soc) > 0) {
					$tiers_name = $socObj->name;
				}
			}

			$desc_text = '';
			if (!empty($job->description)) {
				$desc_text = trim(strip_tags($job->description));
			}

			$job->fetchLines();

			// ── Closure : dessine le bloc complet du job SANS saut de page interne ─
			// Capturée par référence : $pdf (état TCPDF), $cy (position courante),
			// $product_cache / $warehouse_cache (caches DB persistants entre jobs).
			// Toutes les autres variables sont read-only → capture par valeur.
			$drawFullJob = function () use (
				&$pdf, &$cy, &$product_cache, &$warehouse_cache, &$moredoc,
				$outputlangs, $default_font_size, $fa_font,
				$cx, $cw, $jpad, $row_h, $line_h, $mo_h, $det_row_h,
				$pic_w, $ref_w, $qty_det_w, $stk_w, $det_x, $qty_col_x,
				$job, $type_label, $tiers_name, $desc_text
			) {
				// ─── 1. Cadre job : en-tête + description ────────────────────────
				$box_start_y = $cy;

				$pdf->SetFillColor(235, 237, 245);
				$pdf->Rect($cx, $box_start_y, $cw, $row_h, 'F');

				$type_w  = 35;
				$tiers_w = !empty($tiers_name) ? 52 : 0;
				$label_w = $cw - 2 * $jpad - $type_w - $tiers_w;
				$type_x  = $cx + $jpad;
				$label_x = $type_x + $type_w;
				$tiers_x = $label_x + $label_w;

				$pdf->SetDrawColor(160, 160, 190);
				$pdf->SetLineWidth(0.2);
				$pdf->Line($label_x, $box_start_y, $label_x, $box_start_y + $row_h);
				if (!empty($tiers_name)) {
					$pdf->Line($tiers_x, $box_start_y, $tiers_x, $box_start_y + $row_h);
				}

				$text_y = $box_start_y + ($row_h - $line_h) / 2.0;

				$pdf->SetFont('', 'B', $default_font_size - 2);
				$pdf->SetTextColor(60, 60, 120);
				$pdf->SetXY($type_x, $text_y);
				$pdf->Cell($type_w - $jpad, $line_h, $outputlangs->convToOutputCharset($type_label), 0, 0, 'L');

				$pdf->SetFont('', 'B', $default_font_size - 1);
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetXY($label_x + $jpad, $text_y);
				$pdf->Cell($label_w - 2 * $jpad, $line_h, $outputlangs->convToOutputCharset((string) $job->label), 0, 0, 'L');

				if (!empty($tiers_name)) {
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->SetTextColor(80, 80, 80);
					$pdf->SetXY($tiers_x + $jpad, $text_y);
					$pdf->Cell($tiers_w - 2 * $jpad, $line_h, $outputlangs->convToOutputCharset($tiers_name), 0, 0, 'R');
				}

				$box_bottom_y = $box_start_y + $row_h;
				if ($desc_text !== '') {
					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->SetTextColor(40, 40, 40);
					$pdf->SetXY($cx + $jpad, $box_bottom_y + $jpad);
					$pdf->MultiCell($cw - 2 * $jpad, $line_h, $outputlangs->convToOutputCharset($desc_text), 0, 'L', false, 1);
					$box_bottom_y = $pdf->GetY() + $jpad;
				}

				$pdf->SetDrawColor(80, 80, 140);
				$pdf->SetLineWidth(0.3);
				$pdf->Rect($cx, $box_start_y, $cw, $box_bottom_y - $box_start_y, 'D');
				$cy = $box_bottom_y;

				// ─── 2. Ligne MO (sous le cadre job) ────────────────────────────
				$mo_barcode_w = 28;
				$mo_qty_w     = 22;
				$mo_lbl_w     = $cw - $mo_barcode_w - $mo_qty_w;
				$mo_text_y    = $cy + ($mo_h - $line_h) / 2.0;

				$pdf->SetFont('', 'B', $default_font_size - 1);
				$pdf->SetTextColor(60, 60, 100);
				$pdf->SetXY($cx + $jpad, $mo_text_y);
				$pdf->Cell($mo_lbl_w - $jpad, $line_h, $outputlangs->transnoentities('WorkshopPDFLaborLabel'), 0, 0, 'L');

				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetXY($cx + $mo_lbl_w, $mo_text_y);
				$qty_mo_str = isset($job->qty_mo) && $job->qty_mo != 0 ? price2num($job->qty_mo, 2).' h' : '-';
				$pdf->Cell($mo_qty_w, $line_h, $qty_mo_str, 0, 0, 'C');

				$bar_x = $cx + $cw - $mo_barcode_w;
				$bar_y = $cy + ($mo_h - 5) / 2.0;
				$pdf->write1DBarcode(
					(string) $job->id,
					'C128',
					$bar_x,
					$bar_y,
					$mo_barcode_w,
					5,
					'',
					array(
						'position'    => '',
						'align'       => 'C',
						'stretch'     => false,
						'fitwidth'    => true,
						'cellfitmaxw' => '',
						'border'      => false,
						'padding'     => 0,
						'fgcolor'     => array(0, 0, 0),
						'bgcolor'     => false,
						'text'        => false,
						'fontsize'    => 0,
						'stretchtext' => 0,
					),
					'N'
				);
				$cy += $mo_h;

				// ─── 3. Lignes de détail (pièces et services) ───────────────────
				if (!empty($job->lines)) {
					$pdf->SetDrawColor(160, 160, 190);
					$pdf->SetLineWidth(0.2);
					$pdf->Line($cx, $cy, $cx + $cw, $cy);

					foreach ($job->lines as $idx => $detline) {
						if ($idx > 0) {
							$pdf->SetDrawColor(215, 218, 235);
							$pdf->SetLineWidth(0.15);
							$pdf->Line($cx + $jpad, $cy, $cx + $cw - $jpad, $cy);
						}

						$det_row_y  = $cy;
						$det_text_y = $det_row_y + ($det_row_h - $line_h) / 2.0;
						$is_piece   = ((int) $detline->product_type === Operationorderdet::TYPE_PRODUCT);

						// Picto FA5 Solid ou fallback géométrique
						$pic_cx = $cx + $jpad + $pic_w / 2.0;
						$pic_cy = $det_row_y + $det_row_h / 2.0;
						if (!empty($fa_font)) {
							if ((int) $detline->product_type === Operationorderdet::TYPE_PRODUCT) {
								$fa_char = "\xEF\x80\x93"; // fa-cog
							} elseif ((int) $detline->product_type === Operationorderdet::TYPE_SERVICE) {
								$fa_char = "\xEF\x82\xAD"; // fa-wrench
							} else {
								$fa_char = "\xEF\x83\xA2"; // fa-undo
							}
							$sv_family = $pdf->getFontFamily();
							$sv_style  = $pdf->getFontStyle();
							$sv_size   = $pdf->getFontSizePt();
							$pdf->SetFont($fa_font, '', 7);
							$pdf->SetTextColor(40, 40, 40);
							$pdf->SetXY($cx + $jpad, $pic_cy - $line_h / 2.0);
							$pdf->Cell($pic_w, $line_h, $fa_char, 0, 0, 'C');
							$pdf->SetFont($sv_family, $sv_style, $sv_size);
						} else {
							$pdf->SetFillColor(50, 50, 50);
							if ((int) $detline->product_type === Operationorderdet::TYPE_SERVICE) {
								$pdf->Ellipse($pic_cx, $pic_cy, 1.5, 1.5, 0, 0, 360, 'F');
							} elseif ((int) $detline->product_type === Operationorderdet::TYPE_PRODUCT) {
								$pdf->Rect($pic_cx - 1.5, $pic_cy - 1.5, 3, 3, 'F');
							} else {
								$pdf->SetDrawColor(50, 50, 50);
								$pdf->SetLineWidth(0.4);
								$pdf->Line($pic_cx, $pic_cy - 2, $pic_cx + 1.5, $pic_cy);
								$pdf->Line($pic_cx + 1.5, $pic_cy, $pic_cx, $pic_cy + 2);
								$pdf->Line($pic_cx, $pic_cy + 2, $pic_cx - 1.5, $pic_cy);
								$pdf->Line($pic_cx - 1.5, $pic_cy, $pic_cx, $pic_cy - 2);
							}
						}

						$product_ref = '';
						if (!empty($detline->fk_product)) {
							$fk_prod = (int) $detline->fk_product;
							if (!isset($product_cache[$fk_prod])) {
								$prodObj = new Product($this->db);
								if ($prodObj->fetch($fk_prod) > 0) {
									$prodObj->fetch_optionals();
									$product_cache[$fk_prod] = array(
										'ref' => $prodObj->ref,
										'type' => (int) $prodObj->type,
										'entity' => (int) $prodObj->entity,
										'doc_oblig' => !empty($prodObj->array_options['options_doc_obl']) ? $prodObj->array_options['options_doc_obl'] : '',
									);
								} else {
									$product_cache[$fk_prod] = array('ref' => '', 'type' => 0, 'entity' => 0, 'doc_oblig' => '');
								}
							}
							$product_ref = $product_cache[$fk_prod]['ref'];

							// Collecter le document obligatoire (dédupliqué par ref produit)
							if ($product_ref !== '' && $product_cache[$fk_prod]['doc_oblig'] !== '') {
								$moredoc[$product_ref] = array(
									'docname' => $product_cache[$fk_prod]['doc_oblig'],
									'type'    => $product_cache[$fk_prod]['type'],
									'entity'  => $product_cache[$fk_prod]['entity'],
								);
							}
						}

						$warehouse_name = '';
						if ($is_piece && !empty($detline->fk_warehouse)) {
							$fk_wh = (int) $detline->fk_warehouse;
							if (!isset($warehouse_cache[$fk_wh])) {
								$whObj = new Entrepot($this->db);
								$warehouse_cache[$fk_wh] = ($whObj->fetch($fk_wh) > 0) ? $whObj->label : '';
							}
							$warehouse_name = $warehouse_cache[$fk_wh];
						}

						$has_stock = ($is_piece && $warehouse_name !== '');
						$stk_col_x = $qty_col_x - $stk_w;
						$lbl_end_x = $has_stock ? $stk_col_x : $qty_col_x;
						$lbl_det_w = $lbl_end_x - ($det_x + $ref_w);

						$pdf->SetFont('', 'B', $default_font_size - 2);
						$pdf->SetTextColor(40, 40, 80);
						$pdf->SetXY($det_x, $det_text_y);
						$pdf->Cell($ref_w, $line_h, $outputlangs->convToOutputCharset($product_ref), 0, 0, 'L');

						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->SetTextColor(0, 0, 0);
						$pdf->SetXY($det_x + $ref_w, $det_text_y);
						$pdf->Cell($lbl_det_w, $line_h, $outputlangs->convToOutputCharset((string) $detline->label), 0, 0, 'L');

						if ($has_stock) {
							$pdf->SetFont('', '', $default_font_size - 2);
							$pdf->SetTextColor(80, 80, 80);
							$pdf->SetXY($stk_col_x, $det_text_y);
							$pdf->Cell($stk_w, $line_h, $outputlangs->convToOutputCharset($warehouse_name), 0, 0, 'L');
						}

						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->SetTextColor(0, 0, 0);
						$qty_val = (float) $detline->qty;
						$qty_str = ($qty_val == (int) $qty_val) ? (string) (int) $qty_val : price2num($qty_val, 2);
						$pdf->SetXY($qty_col_x, $det_text_y);
						$pdf->Cell($qty_det_w, $line_h, $qty_str, 0, 0, 'R');

						$cy = $det_row_y + $det_row_h;

						$det_desc = !empty($detline->description) ? trim(strip_tags($detline->description)) : '';
						if ($det_desc !== '') {
							$pdf->SetFont('', 'I', $default_font_size - 2);
							$pdf->SetTextColor(80, 80, 80);
							$pdf->SetXY($cx + $jpad + $pic_w, $cy);
							$pdf->MultiCell($cw - 2 * $jpad - $pic_w, $line_h, $outputlangs->convToOutputCharset($det_desc), 0, 'L', false, 1);
							$cy = $pdf->GetY();
						}
					}
				}
			}; // end $drawFullJob

			// ── Dessin transactionnel ─────────────────────────────────────────────
			// 1er essai sur la page courante.
			// Si le job dépasse $body_bottom → rollbackTransaction(true) pour annuler
			// tout le dessin, puis on ouvre une nouvelle page (pleine hauteur, sans
			// réservation pour le bloc signature qui se positionne sur la dernière page)
			// et on retente. Si ça déborde encore (job > 1 page), on commite quand même.
			$attempt = 0;
			while (true) {
				$pdf->startTransaction();
				$cy_save = $cy;

				$drawFullJob();

				if ($cy > $body_bottom && $attempt === 0) {
					// Débordement détecté → rollback complet du dessin tenté
					$pdf->rollbackTransaction(true);
					$cy = $cy_save;

					// Nouvelle page de continuation — pleine hauteur disponible
					$pdf->AddPage();
					$new_hb       = $this->_pagehead($pdf, $object, 0, $outputlangs);
					$new_body_top = $new_hb + 3;
					// Pas de réservation $sig_h : le bloc signature ne s'affiche
					// que sur la dernière page (géré par _signatureboxes())
					$body_bottom  = $this->page_hauteur - $this->marge_basse - 3;
					$new_body_h   = $body_bottom - $new_body_top;
					$pdf->SetDrawColor(80, 80, 140);
					$pdf->SetLineWidth(0.4);
					$pdf->RoundedRect($body_x, $new_body_top, $body_w, $new_body_h, 3, '1111', 'D');
					$pdf->SetDrawColor(0, 0, 0);
					$pdf->SetLineWidth(0.2);
					$cy      = $new_body_top + $bpad;
					$attempt = 1;
				} else {
					// Pas de débordement (ou 2e tentative) → on valide
					$pdf->commitTransaction();
					break;
				}
			}

			$cy += $gap;
		}

		$pdf->SetAutoPageBreak(true, 0);
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetLineWidth(0.2);
		$pdf->SetTextColor(0, 0, 0);

		return array('cy' => $cy, 'moredoc' => $moredoc, 'moredoc_st' => $moredoc_st);
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Draw bottom triple box on the last page (stuck to the bottom margin)
	 *
	 * [ Commentaires (1/2) | Visa atelier (1/4) | Visa client (1/4) ]
	 *
	 * La note publique de l'OR est affichée dans le cadre Commentaires.
	 *
	 * @param  TCPDF          $pdf         PDF object
	 * @param  Operationorder $object      OR object
	 * @param  Translate      $outputlangs Output language
	 * @return void
	 */
	protected function _signatureboxes(&$pdf, $object, $outputlangs)
	{
		// phpcs:enable
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$gap      = 2;    // espace entre cadres (mm)
		$footer_h = 35;   // hauteur totale du triple cadre
		$title_h  = 5;
		$ipad     = 2;
		$line_h   = 3.5;
		$r        = 2;

		$header_x = $this->marge_gauche;
		$header_w = $this->page_largeur - $this->marge_gauche - $this->marge_droite;

		// Largeurs : gauche = 1/2, milieu = 1/4, droite = 1/4 (déduction des espaces)
		$avail_w = $header_w - 2 * $gap;
		$left_w  = round($avail_w / 2.0, 2);
		$mid_w   = round($avail_w / 4.0, 2);
		$right_w = $avail_w - $left_w - $mid_w; // absorbe les arrondis flottants

		$footer_y = $this->page_hauteur - $this->marge_basse - $footer_h;
		$left_x   = $header_x;
		$mid_x    = $left_x + $left_w + $gap;
		$right_x  = $mid_x + $mid_w + $gap;

		// Désactiver le saut de page automatique pour dessiner en bas
		$pdf->SetAutoPageBreak(false);

		// Fond blanc sur la zone signature pour effacer d'éventuels traits du cadre corps
		// (sur les pages de continuation, le cadre corps s'étend jusqu'à la marge basse)
		$pdf->SetFillColor(255, 255, 255);
		$pdf->Rect($header_x, $footer_y, $header_w, $footer_h, 'F');

		// ── Fills de titre (arrondis haut), puis bordures complètes par-dessus ──
		$pdf->SetFillColor(210, 215, 230);
		$pdf->RoundedRect($left_x,  $footer_y, $left_w,  $title_h, $r, '1001', 'F');
		$pdf->RoundedRect($mid_x,   $footer_y, $mid_w,   $title_h, $r, '1001', 'F');
		$pdf->RoundedRect($right_x, $footer_y, $right_w, $title_h, $r, '1001', 'F');

		$pdf->SetDrawColor(80, 80, 140);
		$pdf->SetLineWidth(0.4);
		$pdf->RoundedRect($left_x,  $footer_y, $left_w,  $footer_h, $r, '1111', 'D');
		$pdf->RoundedRect($mid_x,   $footer_y, $mid_w,   $footer_h, $r, '1111', 'D');
		$pdf->RoundedRect($right_x, $footer_y, $right_w, $footer_h, $r, '1111', 'D');

		// ── Titres ────────────────────────────────────────────────────────────
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->SetTextColor(40, 40, 100);

		$pdf->SetXY($left_x + $ipad, $footer_y + 0.5);
		$pdf->Cell($left_w - 2 * $ipad, $title_h - 1, $outputlangs->transnoentities('WorkshopPDFComments'), 0, 0, 'C');

		$pdf->SetXY($mid_x + $ipad, $footer_y + 0.5);
		$pdf->Cell($mid_w - 2 * $ipad, $title_h - 1, $outputlangs->transnoentities('WorkshopPDFSignatureWorkshop'), 0, 0, 'C');

		$pdf->SetXY($right_x + $ipad, $footer_y + 0.5);
		$pdf->Cell($right_w - 2 * $ipad, $title_h - 1, $outputlangs->transnoentities('WorkshopPDFSignatureCustomer'), 0, 0, 'C');

		// ── Note publique dans le cadre Commentaires ──────────────────────────
		$content_y  = $footer_y + $title_h + $ipad;
		$content_h  = $footer_h - $title_h - 2 * $ipad; // ≈ 26 mm
		$notetoshow = !empty($object->note_public) ? strip_tags($object->note_public) : '';

		if ($notetoshow !== '') {
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetTextColor(0, 0, 0);
			// MultiCell avec $maxh = $content_h pour écrêter le contenu dans le cadre
			$pdf->MultiCell(
				$left_w - 2 * $ipad,  // largeur
				$line_h,               // hauteur d'une ligne
				$outputlangs->convToOutputCharset(trim($notetoshow)),
				0,                     // bordure
				'L',                   // alignement
				false,                 // remplissage
				1,                     // retour à la ligne
				$left_x + $ipad,       // x
				$content_y,            // y
				true,                  // reseth
				0,                     // stretch
				false,                 // ishtml
				true,                  // autopadding
				$content_h             // maxh — écrête à la hauteur du cadre
			);
		}

		$pdf->SetAutoPageBreak(true, 0);
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetLineWidth(0.2);
		$pdf->SetTextColor(0, 0, 0);
	}


	/**
	 * Annexe un document PDF (pièce jointe produit/service) à la fin du PDF courant
	 *
	 * Utilise FPDI (intégré à TCPDF dans Dolibarr) pour importer chaque page
	 * du fichier source et l'ajouter au document en cours de génération.
	 * Le chemin est résolu via $conf->product->multidir_output (type 0) ou
	 * $conf->service->multidir_output (type 1) pour compatibilité multi-entité.
	 *
	 * @param  TCPDF  $pdf           PDF object en cours de génération
	 * @param  string $productref    Référence du produit/service
	 * @param  string $docname       Nom du document sans extension .pdf
	 * @param  int    $producttype   Type du produit (0=produit, 1=service)
	 * @param  int    $productentity Entité du produit (pour résoudre le bon répertoire)
	 * @return int                   1 = OK, 0 = fichier introuvable ou erreur d'import
	 */
	protected function _addAttachedDoc(&$pdf, $productref, $docname, $producttype, $productentity)
	{
		global $conf;

		// Construire la liste des répertoires candidats (service et produit)
		// En fonction de la configuration Dolibarr, un service peut être stocké
		// dans le répertoire "service/" ou "produit/" — on teste les deux.
		$candidates = array();
		if (!empty($conf->service->multidir_output[$productentity])) {
			$candidates[] = $conf->service->multidir_output[$productentity];
		}
		if (!empty($conf->product->multidir_output[$productentity])) {
			$candidates[] = $conf->product->multidir_output[$productentity];
		}
		if (empty($candidates) && !empty($conf->product->multidir_output[$conf->entity])) {
			$candidates[] = $conf->product->multidir_output[$conf->entity];
		}

		$infile = '';
		$subpath = dol_sanitizeFileName($productref).'/'.$docname.'.pdf';
		foreach ($candidates as $basedir) {
			$testpath = $basedir.'/'.$subpath;
			if (file_exists($testpath) && is_readable($testpath)) {
				$infile = $testpath;
				break;
			}
		}

		if ($infile === '') {
			dol_syslog(__METHOD__.' fichier introuvable dans les répertoires candidats pour '.$subpath, LOG_WARNING);
			setEventMessages('Document obligatoire introuvable pour le produit '.$productref.' : '.$docname.'.pdf', null, 'warnings');
			return 0;
		}

		$pagecount = $pdf->setSourceFile($infile);
		for ($i = 1; $i <= $pagecount; $i++) {
			$tplIdx = $pdf->importPage($i);
			if ($tplIdx !== false) {
				$s = $pdf->getTemplatesize($tplIdx);
				$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
				$pdf->useTemplate($tplIdx);
			} else {
				dol_syslog(__METHOD__.' impossible d\'importer la page '.$i.' de '.$infile.' (PDF protégé ?)', LOG_WARNING);
				setEventMessages(null, array($infile.' cannot be added, probably protected PDF'), 'warnings');
				return 0;
			}
		}

		return 1;
	}
}
