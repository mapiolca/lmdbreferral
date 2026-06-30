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
		$this->description = $langs->trans('LmdbReferralPdfStandardDescription');

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
		$right = $this->page_largeur - $this->marge_droite;
		$width = $right - $this->marge_gauche;

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont($defaultFont, 'B', $defaultFontSize + 5);
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->MultiCell($width, 8, $this->pdfText($outputlangs, $outputlangs->trans('LmdbReferralLink').' '.$object->ref), 0, 'L');
		$y = $pdf->GetY() + 5;

		$pdf->SetDrawColor(210, 210, 210);
		$pdf->Line($this->marge_gauche, $y, $right, $y);
		$y += 6;

		$pdf->SetFont($defaultFont, '', $defaultFontSize);
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('Ref'), (string) $object->ref);
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('Status'), $object->getLibStatut(0));
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('LmdbReferralReferrer'), $this->getReferrerLabel($object, $outputlangs));
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('LmdbReferralReferredThirdparty'), $this->getThirdpartyLabel((int) $object->fk_soc_filleul));
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('DateCreation'), $this->formatDate($object->date_creation));
		if (!empty($object->date_modification)) {
			$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('DateModificationShort'), $this->formatDate($object->date_modification));
		}
		if (!empty($object->date_annulation)) {
			$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('LmdbReferralCancellationDate'), $this->formatDate($object->date_annulation));
		}

		$note = trim((string) $object->note_private);
		if ($note !== '') {
			$y += 4;
			$this->ensureSpace($pdf, $object, $outputlangs, $y, 24, $footerHeight);
			$pdf->SetFont($defaultFont, 'B', $defaultFontSize);
			$pdf->SetXY($this->marge_gauche, $y);
			$pdf->MultiCell($width, 6, $this->pdfText($outputlangs, $outputlangs->trans('NotePrivate')), 0, 'L');
			$y = $pdf->GetY() + 1;
			$pdf->SetFont($defaultFont, '', $defaultFontSize - 1);
			$pdf->SetXY($this->marge_gauche, $y);
			$pdf->MultiCell($width, 5, $this->pdfText($outputlangs, $this->normalizeText($note)), 1, 'L');
			$y = $pdf->GetY() + 2;
		}

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
	 * Write one information line.
	 *
	 * @param TCPDF|TCPDI $pdf PDF instance
	 * @param Translate   $outputlangs Output language
	 * @param float       $y Current Y
	 * @param string      $label Label
	 * @param string      $value Value
	 * @return void
	 */
	private function writeInfoLine(&$pdf, $outputlangs, &$y, $label, $value)
	{
		$lineHeight = 7;
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->SetFont('', 'B');
		$pdf->MultiCell(55, $lineHeight, $this->pdfText($outputlangs, $label), 0, 'L', 0, 0);
		$pdf->SetFont('', '');
		$pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->marge_gauche - 55, $lineHeight, $this->pdfText($outputlangs, $value), 0, 'L', 0, 1);
		$y = $pdf->GetY();
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
		return $outputlangs->convToOutputCharset((string) $text);
	}
}
