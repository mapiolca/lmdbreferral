<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/**
 * Base class for referral link PDF models.
 */
abstract class ModelePDFLmdbReferralLink extends CommonDocGenerator
{
	/**
	 * Return list of active generation modules.
	 *
	 * @param DoliDB $db Database handler
	 * @param int    $maxfilenamelength Max filename length
	 * @return array<string,string>|int
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

		return getListOfModels($db, 'lmdbreferrallink', $maxfilenamelength);
	}

	/**
	 * Build document on disk.
	 *
	 * @param LmdbReferralLink $object Object source
	 * @param Translate        $outputlangs Output language
	 * @param string           $srctemplatepath Source template path
	 * @param int              $hidedetails Hide details
	 * @param int              $hidedesc Hide description
	 * @param int              $hideref Hide reference
	 * @return int 1 if OK, <=0 if KO
	 */
	abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}

/**
 * Base class for referral link numbering models.
 */
abstract class ModeleNumRefLmdbReferralLink extends CommonNumRefGenerator
{
	/**
	 * Return an example of numbering.
	 *
	 * @return string
	 */
	abstract public function getExample();

	/**
	 * Return next free value.
	 *
	 * @param LmdbReferralLink $object Object source
	 * @return string|int
	 */
	abstract public function getNextValue($object);
}
