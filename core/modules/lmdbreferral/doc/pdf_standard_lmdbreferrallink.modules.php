<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

dol_include_once('/lmdbreferral/core/modules/lmdbreferral/modules_lmdbreferrallink.php');
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');
dol_include_once('/lmdbreferral/class/lmdbreferralstats.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/**
 * Standard PDF model for referral links.
 */
class pdf_standard_lmdbreferrallink extends ModelePDFLmdbReferralLink
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $name = 'standard_lmdbreferrallink';

	/** @var string */
	public $description;

	/** @var string */
	public $type = 'pdf';

	/** @var string */
	public $version = 'dolibarr';

	/** @var int */
	public $update_main_doc_field = 1;

	/** @var float */
	public $page_largeur;

	/** @var float */
	public $page_hauteur;

	/** @var array<int,float> */
	public $format = array();

	/** @var int */
	public $marge_gauche;

	/** @var int */
	public $marge_droite;

	/** @var int */
	public $marge_haute;

	/** @var int */
	public $marge_basse;

	/** @var Societe|null */
	public $emetteur;

	/** @var string */
	public $watermark = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;
		$langs->loadLangs(array('main', 'companies', 'lmdbreferral@lmdbreferral'));
		$this->description = $this->pdfTrans($langs, 'LmdbReferralPdfStandardDescription');

		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->emetteur = is_object($mysoc) ? $mysoc : null;
	}

	/**
	 * Build PDF onto disk.
	 *
	 * @param LmdbReferralLink $object Object
	 * @param Translate        $outputlangs Output language
	 * @param string           $srctemplatepath Source template path
	 * @param int              $hidedetails Hide details
	 * @param int              $hidedesc Hide description
	 * @param int              $hideref Hide reference
	 * @return int
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $langs, $user;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (getDolGlobalInt('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}
		$outputlangs->loadLangs(array('main', 'companies', 'users', 'lmdbreferral@lmdbreferral'));

		$dir = lmdbreferralGetLinkDocumentDir($object);
		$objectref = dol_sanitizeFileName(!empty($object->ref) ? (string) $object->ref : 'SPECIMEN');
		if ($dir === '' || $objectref === '') {
			$this->error = $outputlangs->transnoentities('ErrorConstantNotDefined', 'LMDBREFERRAL_OUTPUTDIR');
			return 0;
		}
		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $outputlangs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		$file = $dir.'/'.$objectref.'.pdf';
		$pdf = pdf_getInstance($this->format);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$defaultFont = pdf_getPDFFont($outputlangs);
		$defaultFontSize = pdf_getPDFFontSize($outputlangs);
		$footerHeight = $this->getFooterReservedHeight(0);

		$pdf->SetCreator('Dolibarr '.(defined('DOL_VERSION') ? DOL_VERSION : ''));
		$pdf->SetAuthor(is_object($user) ? $outputlangs->convToOutputCharset($user->getFullName($outputlangs)) : '');
		$pdf->SetTitle($this->pdfText($outputlangs, $object->ref));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(true, 0);
		$pdf->SetFont($defaultFont, '', $defaultFontSize);
		$pdf->AddPage();
		$this->applyReservedFooter($pdf, $footerHeight);

		$y = $this->marge_haute;
		$width = $this->page_largeur - $this->marge_droite - $this->marge_gauche;
		$statsService = new LmdbReferralStats($this->db);
		$linkStats = $statsService->getLinkStats($user, $object);

		$this->writeHeaderBlock($pdf, $outputlangs, $object, $y, $width, $defaultFontSize);
		$this->writeIdentityBlock($pdf, $outputlangs, $object, $y, $width, $defaultFontSize, $footerHeight);
		$this->writeStatsBlock($pdf, $outputlangs, $object, $y, $width, $defaultFontSize, $footerHeight, $linkStats);
		$this->writeSignedPropalsBlock($pdf, $outputlangs, $object, $y, $width, $defaultFontSize, $footerHeight, $linkStats);
		$this->writeNotesBlock($pdf, $outputlangs, $object, $y, $width, $defaultFontSize, $footerHeight);

		$this->renderFooter($pdf, $object, $outputlangs, 0);
		if (method_exists($pdf, 'AliasNbPages')) {
			$pdf->AliasNbPages();
		}
		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);

		$this->result = array('fullpath' => $file);

		return 1;
	}

	/**
	 * Write the document header.
	 *
	 * @param TCPDF|TCPDI      $pdf PDF instance
	 * @param Translate        $outputlangs Output language
	 * @param LmdbReferralLink $object Object
	 * @param float            $y Current Y
	 * @param float            $width Available width
	 * @param int              $fontSize Base font size
	 * @return void
	 */
	private function writeHeaderBlock(&$pdf, $outputlangs, $object, &$y, $width, $fontSize)
	{
		$headerHeight = 28;
		$pdf->SetDrawColor(225, 230, 235);
		$pdf->SetFillColor(245, 247, 250);
		$pdf->Rect($this->marge_gauche, $y, $width, $headerHeight, 'DF');

		$pdf->SetTextColor(35, 35, 35);
		$pdf->SetFont('', 'B', $fontSize + 5);
		$pdf->SetXY($this->marge_gauche + 4, $y + 5);
		$pdf->MultiCell($width - 52, 8, $this->pdfText($outputlangs, $this->pdfTrans($outputlangs, 'LmdbReferralLink').' '.$object->ref), 0, 'L');

		$pdf->SetFont('', '', max(7, $fontSize - 2));
		$pdf->SetTextColor(95, 95, 95);
		$pdf->SetXY($this->marge_gauche + 4, $y + 18);
		$pdf->MultiCell($width - 52, 5, $this->pdfText($outputlangs, $this->pdfTrans($outputlangs, 'LmdbReferralPdfGeneratedOn').' '.dol_print_date(dol_now(), 'dayhour')), 0, 'L');

		if ((int) $object->status === LmdbReferralLink::STATUS_CANCELLED) {
			$pdf->SetFillColor(145, 74, 74);
		} else {
			$pdf->SetFillColor(55, 133, 87);
		}
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('', 'B', max(7, $fontSize - 1));
		$pdf->SetXY($this->marge_gauche + $width - 45, $y + 8);
		$pdf->MultiCell(38, 7, $this->pdfText($outputlangs, $object->getLibStatut(0)), 0, 'C', 1);

		$y += $headerHeight + 7;
	}

	/**
	 * Write identity information.
	 *
	 * @param TCPDF|TCPDI      $pdf PDF instance
	 * @param Translate        $outputlangs Output language
	 * @param LmdbReferralLink $object Object
	 * @param float            $y Current Y
	 * @param float            $width Available width
	 * @param int              $fontSize Base font size
	 * @param float            $footerHeight Footer height
	 * @return void
	 */
	private function writeIdentityBlock(&$pdf, $outputlangs, $object, &$y, $width, $fontSize, $footerHeight)
	{
		$this->ensureSpace($pdf, $object, $outputlangs, $y, 38, $footerHeight);
		$this->writeSectionTitle($pdf, $outputlangs, $y, $width, $this->pdfTrans($outputlangs, 'LmdbReferralPdfIdentity'), $fontSize);
		$this->writePairRow($pdf, $outputlangs, $y, $width, $fontSize, $this->pdfTrans($outputlangs, 'Ref'), (string) $object->ref, $this->pdfTrans($outputlangs, 'Status'), $object->getLibStatut(0));
		$this->writePairRow($pdf, $outputlangs, $y, $width, $fontSize, $this->pdfTrans($outputlangs, 'LmdbReferralReferrer'), $this->getReferrerLabel($object, $outputlangs), $this->pdfTrans($outputlangs, 'LmdbReferralReferredThirdparty'), $this->getThirdpartyLabel((int) $object->fk_soc_filleul));
		$this->writePairRow($pdf, $outputlangs, $y, $width, $fontSize, $this->pdfTrans($outputlangs, 'DateCreation'), $this->formatDate($object->date_creation), $this->pdfTrans($outputlangs, 'DateModificationShort'), !empty($object->date_modification) ? $this->formatDate($object->date_modification) : $this->pdfTrans($outputlangs, 'NotAvailable'));
		if (!empty($object->date_annulation)) {
			$this->writePairRow($pdf, $outputlangs, $y, $width, $fontSize, $this->pdfTrans($outputlangs, 'LmdbReferralCancellationDate'), $this->formatDate($object->date_annulation), '', '');
		}
		$y += 4;
	}

	/**
	 * Write KPI block.
	 *
	 * @param TCPDF|TCPDI      $pdf PDF instance
	 * @param Translate        $outputlangs Output language
	 * @param LmdbReferralLink $object Object
	 * @param float            $y Current Y
	 * @param float            $width Available width
	 * @param int              $fontSize Base font size
	 * @param float            $footerHeight Footer height
	 * @param array<string,mixed> $stats Link statistics
	 * @return void
	 */
	private function writeStatsBlock(&$pdf, $outputlangs, $object, &$y, $width, $fontSize, $footerHeight, array $stats)
	{
		$this->ensureSpace($pdf, $object, $outputlangs, $y, 48, $footerHeight);
		$this->writeSectionTitle($pdf, $outputlangs, $y, $width, $this->pdfTrans($outputlangs, 'LmdbReferralLinkStats'), $fontSize);

		$boxGap = 2;
		$boxHeight = 19;
		$boxWidth = ($width - (3 * $boxGap)) / 4;
		$daysToFirstSignature = array_key_exists('days_to_first_signature', $stats) ? $stats['days_to_first_signature'] : null;
		$delayValue = !empty($stats['is_transformed']) ? $this->formatStatsDays($outputlangs, $daysToFirstSignature) : $this->formatStatsDays($outputlangs, (int) ($stats['age_days'] ?? 0));
		$delayLabel = !empty($stats['is_transformed']) ? $this->pdfTrans($outputlangs, 'LmdbReferralDaysToFirstSignature') : $this->pdfTrans($outputlangs, 'LmdbReferralLinkAgeDays');

		$x = $this->marge_gauche;
		$this->writeMetricBox($pdf, $outputlangs, $x, $y, $boxWidth, $boxHeight, $this->pdfTrans($outputlangs, 'LmdbReferralSignedPropalsCount'), (string) ((int) ($stats['signed_propals'] ?? 0)), $fontSize);
		$x += $boxWidth + $boxGap;
		$this->writeMetricBox($pdf, $outputlangs, $x, $y, $boxWidth, $boxHeight, $this->pdfTrans($outputlangs, 'LmdbReferralGeneratedCAHT'), $this->formatAmount((float) ($stats['amount_ht'] ?? 0.0)), $fontSize);
		$x += $boxWidth + $boxGap;
		$this->writeMetricBox($pdf, $outputlangs, $x, $y, $boxWidth, $boxHeight, $this->pdfTrans($outputlangs, 'LmdbReferralAverageBasketHT'), $this->formatAmount((float) ($stats['average_basket_ht'] ?? 0.0)), $fontSize);
		$x += $boxWidth + $boxGap;
		$this->writeMetricBox($pdf, $outputlangs, $x, $y, $boxWidth, $boxHeight, $delayLabel, $delayValue, $fontSize);
		$y += $boxHeight + 3;

		$transformation = !empty($stats['is_transformed']) ? $this->pdfTrans($outputlangs, 'LmdbReferralConverted') : $this->pdfTrans($outputlangs, 'LmdbReferralToFollow');
		$lockStatus = !empty($stats['is_locked']) ? $this->pdfTrans($outputlangs, 'LmdbReferralCommercialLockActive') : $this->pdfTrans($outputlangs, 'LmdbReferralCommercialLockInactive');
		$this->writePairRow($pdf, $outputlangs, $y, $width, $fontSize, $this->pdfTrans($outputlangs, 'LmdbReferralLinkConversionStatus'), $transformation, $this->pdfTrans($outputlangs, 'LmdbReferralCommercialLockStatus'), $lockStatus);
		$this->writePairRow($pdf, $outputlangs, $y, $width, $fontSize, $this->pdfTrans($outputlangs, 'LmdbReferralFirstSignatureDate'), $this->formatOptionalDate($outputlangs, (string) ($stats['first_signature_date'] ?? '')), $this->pdfTrans($outputlangs, 'LmdbReferralLastSignatureDate'), $this->formatOptionalDate($outputlangs, (string) ($stats['last_signature_date'] ?? '')));
		$y += 4;
	}

	/**
	 * Write signed proposals table.
	 *
	 * @param TCPDF|TCPDI      $pdf PDF instance
	 * @param Translate        $outputlangs Output language
	 * @param LmdbReferralLink $object Object
	 * @param float            $y Current Y
	 * @param float            $width Available width
	 * @param int              $fontSize Base font size
	 * @param float            $footerHeight Footer height
	 * @param array<string,mixed> $stats Link statistics
	 * @return void
	 */
	private function writeSignedPropalsBlock(&$pdf, $outputlangs, $object, &$y, $width, $fontSize, $footerHeight, array $stats)
	{
		$propals = !empty($stats['propals']) && is_array($stats['propals']) ? $stats['propals'] : array();
		if (empty($propals)) {
			return;
		}

		$this->ensureSpace($pdf, $object, $outputlangs, $y, 25, $footerHeight);
		$this->writeSectionTitle($pdf, $outputlangs, $y, $width, $this->pdfTrans($outputlangs, 'LmdbReferralSignedProposalsDetails'), $fontSize);
		$this->writeProposalTableHeader($pdf, $outputlangs, $y, $width, $fontSize);

		foreach ($propals as $propal) {
			if (($y + 7) > ($this->page_hauteur - $footerHeight)) {
				$this->renderFooter($pdf, $object, $outputlangs, 1);
				$pdf->AddPage();
				$this->applyReservedFooter($pdf, $footerHeight);
				$y = $this->marge_haute;
				$this->writeProposalTableHeader($pdf, $outputlangs, $y, $width, $fontSize);
			}
			$this->writeProposalTableRow($pdf, $outputlangs, $y, $width, $fontSize, $propal);
		}
		$y += 4;
	}

	/**
	 * Write private notes.
	 *
	 * @param TCPDF|TCPDI      $pdf PDF instance
	 * @param Translate        $outputlangs Output language
	 * @param LmdbReferralLink $object Object
	 * @param float            $y Current Y
	 * @param float            $width Available width
	 * @param int              $fontSize Base font size
	 * @param float            $footerHeight Footer height
	 * @return void
	 */
	private function writeNotesBlock(&$pdf, $outputlangs, $object, &$y, $width, $fontSize, $footerHeight)
	{
		$note = trim((string) $object->note_private);
		if ($note === '') {
			return;
		}

		$noteText = $this->pdfText($outputlangs, $this->normalizeText($note));
		$noteHeight = 18;
		if (method_exists($pdf, 'getStringHeight')) {
			$noteHeight = max($noteHeight, (float) $pdf->getStringHeight($width, $noteText) + 8);
		}
		$this->ensureSpace($pdf, $object, $outputlangs, $y, $noteHeight + 10, $footerHeight);
		$this->writeSectionTitle($pdf, $outputlangs, $y, $width, $this->pdfTrans($outputlangs, 'NotePrivate'), $fontSize);
		$pdf->SetDrawColor(220, 220, 220);
		$pdf->SetFillColor(252, 252, 252);
		$pdf->SetTextColor(35, 35, 35);
		$pdf->SetFont('', '', max(7, $fontSize - 1));
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->MultiCell($width, 5, $noteText, 1, 'L', 1);
		$y = $pdf->GetY() + 3;
	}

	/**
	 * Write a section title.
	 *
	 * @param TCPDF|TCPDI $pdf PDF instance
	 * @param Translate   $outputlangs Output language
	 * @param float       $y Current Y
	 * @param float       $width Available width
	 * @param string      $title Title
	 * @param int         $fontSize Base font size
	 * @return void
	 */
	private function writeSectionTitle(&$pdf, $outputlangs, &$y, $width, $title, $fontSize)
	{
		$pdf->SetTextColor(45, 45, 45);
		$pdf->SetFont('', 'B', $fontSize + 1);
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->MultiCell($width, 6, $this->pdfText($outputlangs, $title), 0, 'L');
		$y = $pdf->GetY() + 1;
	}

	/**
	 * Write a two-pair information row.
	 *
	 * @param TCPDF|TCPDI $pdf PDF instance
	 * @param Translate   $outputlangs Output language
	 * @param float       $y Current Y
	 * @param float       $width Available width
	 * @param int         $fontSize Base font size
	 * @param string      $leftLabel Left label
	 * @param string      $leftValue Left value
	 * @param string      $rightLabel Right label
	 * @param string      $rightValue Right value
	 * @return void
	 */
	private function writePairRow(&$pdf, $outputlangs, &$y, $width, $fontSize, $leftLabel, $leftValue, $rightLabel, $rightValue)
	{
		$gap = 4;
		$halfWidth = ($width - $gap) / 2;
		$labelWidth = 34;
		$valueWidth = $halfWidth - $labelWidth;
		$rowHeight = 7;
		$leftValueText = $this->pdfText($outputlangs, $leftValue);
		$rightValueText = $this->pdfText($outputlangs, $rightValue);
		if (method_exists($pdf, 'getStringHeight')) {
			$rowHeight = max($rowHeight, (float) $pdf->getStringHeight($valueWidth, $leftValueText) + 2, (float) $pdf->getStringHeight($valueWidth, $rightValueText) + 2);
		}

		$pdf->SetDrawColor(222, 222, 222);
		$pdf->SetFillColor(248, 248, 248);
		$pdf->SetTextColor(55, 55, 55);
		$pdf->SetFont('', 'B', max(7, $fontSize - 1));
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->MultiCell($labelWidth, $rowHeight, $this->pdfText($outputlangs, $leftLabel), 1, 'L', 1, 0);
		$pdf->SetFont('', '', max(7, $fontSize - 1));
		$pdf->SetXY($this->marge_gauche + $labelWidth, $y);
		$pdf->MultiCell($valueWidth, $rowHeight, $leftValueText, 1, 'L', 0, 0);

		if ($rightLabel !== '' || $rightValue !== '') {
			$pdf->SetFont('', 'B', max(7, $fontSize - 1));
			$pdf->SetXY($this->marge_gauche + $halfWidth + $gap, $y);
			$pdf->MultiCell($labelWidth, $rowHeight, $this->pdfText($outputlangs, $rightLabel), 1, 'L', 1, 0);
			$pdf->SetFont('', '', max(7, $fontSize - 1));
			$pdf->SetXY($this->marge_gauche + $halfWidth + $gap + $labelWidth, $y);
			$pdf->MultiCell($valueWidth, $rowHeight, $rightValueText, 1, 'L', 0, 0);
		} else {
			$pdf->SetXY($this->marge_gauche + $halfWidth + $gap, $y);
			$pdf->MultiCell($halfWidth, $rowHeight, '', 1, 'L', 0, 0);
		}
		$y += $rowHeight;
	}

	/**
	 * Write one KPI box.
	 *
	 * @param TCPDF|TCPDI $pdf PDF instance
	 * @param Translate   $outputlangs Output language
	 * @param float       $x X position
	 * @param float       $y Y position
	 * @param float       $width Box width
	 * @param float       $height Box height
	 * @param string      $label Label
	 * @param string      $value Value
	 * @param int         $fontSize Base font size
	 * @return void
	 */
	private function writeMetricBox(&$pdf, $outputlangs, $x, $y, $width, $height, $label, $value, $fontSize)
	{
		$pdf->SetDrawColor(222, 226, 230);
		$pdf->SetFillColor(246, 249, 251);
		$pdf->Rect($x, $y, $width, $height, 'DF');
		$pdf->SetTextColor(90, 90, 90);
		$pdf->SetFont('', '', max(6, $fontSize - 3));
		$pdf->SetXY($x + 2, $y + 2);
		$pdf->MultiCell($width - 4, 5, $this->pdfText($outputlangs, $label), 0, 'C');
		$pdf->SetTextColor(30, 30, 30);
		$pdf->SetFont('', 'B', max(8, $fontSize + 1));
		$pdf->SetXY($x + 2, $y + 9);
		$pdf->MultiCell($width - 4, 7, $this->pdfText($outputlangs, $value), 0, 'C');
	}

	/**
	 * Write the signed proposals table header.
	 *
	 * @param TCPDF|TCPDI $pdf PDF instance
	 * @param Translate   $outputlangs Output language
	 * @param float       $y Current Y
	 * @param float       $width Available width
	 * @param int         $fontSize Base font size
	 * @return void
	 */
	private function writeProposalTableHeader(&$pdf, $outputlangs, &$y, $width, $fontSize)
	{
		$refWidth = 42;
		$dateWidth = 42;
		$amountWidth = ($width - $refWidth - $dateWidth) / 2;
		$pdf->SetDrawColor(210, 210, 210);
		$pdf->SetFillColor(232, 236, 240);
		$pdf->SetTextColor(45, 45, 45);
		$pdf->SetFont('', 'B', max(7, $fontSize - 1));
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->MultiCell($refWidth, 7, $this->pdfText($outputlangs, $this->pdfTrans($outputlangs, 'Ref')), 1, 'L', 1, 0);
		$pdf->MultiCell($dateWidth, 7, $this->pdfText($outputlangs, $this->pdfTrans($outputlangs, 'LmdbReferralSignatureDate')), 1, 'L', 1, 0);
		$pdf->MultiCell($amountWidth, 7, $this->pdfText($outputlangs, $this->pdfTrans($outputlangs, 'LmdbReferralSignedAmountHT')), 1, 'R', 1, 0);
		$pdf->MultiCell($amountWidth, 7, $this->pdfText($outputlangs, $this->pdfTrans($outputlangs, 'LmdbReferralSignedAmountTTC')), 1, 'R', 1, 0);
		$y += 7;
	}

	/**
	 * Write one signed proposal row.
	 *
	 * @param TCPDF|TCPDI $pdf PDF instance
	 * @param Translate   $outputlangs Output language
	 * @param float       $y Current Y
	 * @param float       $width Available width
	 * @param int         $fontSize Base font size
	 * @param array<string,mixed> $propal Signed proposal row
	 * @return void
	 */
	private function writeProposalTableRow(&$pdf, $outputlangs, &$y, $width, $fontSize, array $propal)
	{
		$refWidth = 42;
		$dateWidth = 42;
		$amountWidth = ($width - $refWidth - $dateWidth) / 2;
		$pdf->SetDrawColor(225, 225, 225);
		$pdf->SetFillColor(255, 255, 255);
		$pdf->SetTextColor(40, 40, 40);
		$pdf->SetFont('', '', max(7, $fontSize - 1));
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->MultiCell($refWidth, 7, $this->pdfText($outputlangs, (string) ($propal['ref'] ?? '')), 1, 'L', 0, 0);
		$pdf->MultiCell($dateWidth, 7, $this->pdfText($outputlangs, $this->formatOptionalDate($outputlangs, (string) ($propal['date_event'] ?? ''))), 1, 'L', 0, 0);
		$pdf->MultiCell($amountWidth, 7, $this->pdfText($outputlangs, $this->formatAmount((float) ($propal['amount_ht'] ?? 0.0))), 1, 'R', 0, 0);
		$pdf->MultiCell($amountWidth, 7, $this->pdfText($outputlangs, $this->formatAmount((float) ($propal['amount_ttc'] ?? 0.0))), 1, 'R', 0, 0);
		$y += 7;
	}

	/**
	 * Format an amount for PDF output.
	 *
	 * @param float $amount Amount
	 * @return string
	 */
	private function formatAmount($amount)
	{
		return html_entity_decode(price((float) $amount), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * Format a day count for PDF output.
	 *
	 * @param Translate $outputlangs Output language
	 * @param mixed     $days Number of days
	 * @return string
	 */
	private function formatStatsDays($outputlangs, $days)
	{
		if ($days === null) {
			return $this->pdfTrans($outputlangs, 'NotAvailable');
		}

		return ((int) $days).' '.$this->pdfTrans($outputlangs, 'Days');
	}

	/**
	 * Return an untranslated-HTML-safe translation.
	 *
	 * @param Translate $outputlangs Output language
	 * @param string    $key Translation key
	 * @return string
	 */
	private function pdfTrans($outputlangs, $key)
	{
		if (method_exists($outputlangs, 'transnoentitiesnoconv')) {
			return $outputlangs->transnoentitiesnoconv($key);
		}

		return html_entity_decode($outputlangs->trans($key), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * Return referrer display label.
	 *
	 * @param LmdbReferralLink $object Object
	 * @param Translate        $outputlangs Output language
	 * @return string
	 */
	private function getReferrerLabel($object, $outputlangs)
	{
		if ($object->referrer_type === 'soc' && (int) $object->fk_soc_parrain > 0) {
			return $this->getThirdpartyLabel((int) $object->fk_soc_parrain);
		}
		if ($object->referrer_type === 'user' && (int) $object->fk_user_parrain > 0) {
			$tmpuser = new User($this->db);
			if ($tmpuser->fetch((int) $object->fk_user_parrain) > 0) {
				return $tmpuser->getFullName($outputlangs);
			}
		}

		return $outputlangs->trans('NotAvailable');
	}

	/**
	 * Return thirdparty label.
	 *
	 * @param int $socid Thirdparty id
	 * @return string
	 */
	private function getThirdpartyLabel($socid)
	{
		if ($socid <= 0) {
			return '';
		}
		$soc = new Societe($this->db);
		if ($soc->fetch($socid) > 0) {
			return (string) $soc->name;
		}

		return '';
	}

	/**
	 * Format SQL or timestamp date.
	 *
	 * @param mixed $date Date value
	 * @return string
	 */
	private function formatDate($date)
	{
		if (empty($date)) {
			return '';
		}

		return dol_print_date(is_numeric($date) ? (int) $date : $this->db->jdate($date), 'dayhour');
	}

	/**
	 * Format an optional SQL or timestamp date.
	 *
	 * @param Translate $outputlangs Output language
	 * @param mixed     $date Date value
	 * @return string
	 */
	private function formatOptionalDate($outputlangs, $date)
	{
		if (empty($date)) {
			return $this->pdfTrans($outputlangs, 'NotAvailable');
		}

		return $this->formatDate($date);
	}

	/**
	 * Normalize text for TCPDF cells.
	 *
	 * @param string $text Raw text
	 * @return string
	 */
	private function normalizeText($text)
	{
		if (function_exists('dol_string_nohtmltag')) {
			return dol_string_nohtmltag($text, 1);
		}

		return trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
	}

	/**
	 * Reserve footer area for current page.
	 *
	 * @param TCPDF|TCPDI $pdf PDF instance
	 * @param float       $height Reserved height
	 * @return void
	 */
	private function applyReservedFooter(&$pdf, $height)
	{
		if (method_exists($pdf, 'setPageOrientation')) {
			$pdf->setPageOrientation('', true, $height);
		} else {
			$pdf->SetAutoPageBreak(true, $height);
		}
	}

	/**
	 * Return reserved footer height.
	 *
	 * @param int $hidefreetext 1 to hide free text
	 * @return float
	 */
	private function getFooterReservedHeight($hidefreetext)
	{
		$height = (float) ($this->marge_basse + 18);
		if (!$hidefreetext) {
			$freeText = trim(getDolGlobalString('LMDBREFERRAL_FREE_TEXT'));
			$lineCount = $freeText !== '' ? max(1, substr_count($freeText, "\n") + 1) : 0;
			$textHeight = max((float) getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5), (float) ($lineCount * 4 + ceil(strlen(strip_tags($freeText)) / 110) * 4));
			$height += $textHeight;
		}

		return max(32.0, min(70.0, $height));
	}

	/**
	 * Ensure enough vertical space remains.
	 *
	 * @param TCPDF|TCPDI      $pdf PDF instance
	 * @param LmdbReferralLink $object Object
	 * @param Translate        $outputlangs Output language
	 * @param float            $y Current Y
	 * @param float            $needed Needed height
	 * @param float            $footerHeight Reserved footer height
	 * @return void
	 */
	private function ensureSpace(&$pdf, $object, $outputlangs, &$y, $needed, $footerHeight)
	{
		if (($y + $needed) <= ($this->page_hauteur - $footerHeight)) {
			return;
		}

		$this->renderFooter($pdf, $object, $outputlangs, 1);
		$pdf->AddPage();
		$this->applyReservedFooter($pdf, $footerHeight);
		$y = $this->marge_haute;
	}

	/**
	 * Render native PDF footer.
	 *
	 * @param TCPDF|TCPDI      $pdf PDF instance
	 * @param LmdbReferralLink $object Object
	 * @param Translate        $outputlangs Output language
	 * @param int              $hidefreetext 1 on intermediate pages
	 * @return void
	 */
	private function renderFooter(&$pdf, $object, $outputlangs, $hidefreetext)
	{
		if (is_object($pdf) && method_exists($pdf, 'SetAutoPageBreak')) {
			$pdf->SetAutoPageBreak(false, 0);
		}
		pdf_pagefoot($pdf, $outputlangs, 'LMDBREFERRAL_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, 0, $hidefreetext, $this->page_largeur, $this->watermark);
		if (is_object($pdf) && method_exists($pdf, 'SetAutoPageBreak')) {
			$pdf->SetAutoPageBreak(true, 0);
		}
	}

	/**
	 * Convert text to the PDF output charset.
	 *
	 * @param Translate $outputlangs Output language
	 * @param string    $text Text
	 * @return string
	 */
	private function pdfText($outputlangs, $text)
	{
		$clean = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		if (function_exists('dol_string_nohtmltag')) {
			$clean = dol_string_nohtmltag($clean, 1);
		}

		return $outputlangs->convToOutputCharset((string) $clean);
	}
}
