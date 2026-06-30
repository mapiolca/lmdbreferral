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

/**
 * Standard numbering rule for referral links.
 */
class mod_lmdbreferrallink_standard extends ModeleNumRefLmdbReferralLink
{
	/** @var string */
	public $version = 'dolibarr';

	/** @var string */
	public $prefix = 'REF';

	/** @var string */
	public $error = '';

	/** @var string */
	public $name = 'standard';

	/**
	 * Return description of numbering module.
	 *
	 * @param Translate $langs Translate object
	 * @return string
	 */
	public function info($langs)
	{
		return $langs->trans('SimpleNumRefModelDesc', $this->prefix);
	}

	/**
	 * Return an example of numbering.
	 *
	 * @return string
	 */
	public function getExample()
	{
		return $this->prefix.'2606-0001';
	}

	/**
	 * Tell if the model can be activated.
	 *
	 * @param CommonObject $object Object source
	 * @return bool
	 */
	public function canBeActivated($object)
	{
		global $langs;

		$max = $this->fetchCurrentMax($object);
		if ($max < 0) {
			$langs->load('errors');
			$this->error = $langs->trans('ErrorNumRefModel', $this->prefix);
			return false;
		}

		return true;
	}

	/**
	 * Return next free value.
	 *
	 * @param LmdbReferralLink $object Object source
	 * @return string|int
	 */
	public function getNextValue($object)
	{
		global $db;

		$date = !empty($object->date_creation) ? $object->date_creation : dol_now();
		$timestamp = is_numeric($date) ? (int) $date : $db->jdate($date);
		if ($timestamp <= 0) {
			$timestamp = dol_now();
		}
		$yymm = dol_print_date($timestamp, '%y%m');
		$max = $this->fetchCurrentMax($object, $yymm);
		if ($max < 0) {
			return -1;
		}
		$num = ($max >= (pow(10, 4) - 1)) ? (string) ($max + 1) : sprintf('%04u', $max + 1);

		return $this->prefix.$yymm.'-'.$num;
	}

	/**
	 * Fetch current max counter in the numbering scope.
	 *
	 * @param CommonObject $object Object source
	 * @param string       $yymm Year and month scope, or empty for any period
	 * @return int
	 */
	private function fetchCurrentMax($object, $yymm = '')
	{
		global $db;

		$period = preg_match('/^[0-9]{4}$/', $yymm) ? $yymm : '____';
		$posindice = strlen($this->prefix) + 6;
		$sql = 'SELECT MAX(CAST(SUBSTRING(ref FROM '.$posindice.') AS SIGNED)) as max';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link';
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefix.$period)."-%'";
		$sql .= ' AND entity IN ('.lmdbreferralGetNumberingEntitySql($object).')';

		$resql = $db->query($sql);
		if (!$resql) {
			$this->error = $db->lasterror();
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return -1;
		}

		$obj = $db->fetch_object($resql);
		$max = ($obj && $obj->max !== null) ? (int) $obj->max : 0;
		$db->free($resql);

		return $max;
	}
}
