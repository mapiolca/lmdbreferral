<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferralservice.class.php');

/**
 * Trigger class for lmdbreferral.
 */
class InterfaceLmdbReferralTriggers extends DolibarrTriggers
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'lmdbreferral';
		$this->description = 'LmdbReferral trigger handler';
		$this->version = '1.0.0';
		$this->picto = 'fa-handshake';
	}

	/**
	 * Run trigger.
	 *
	 * @param string    $action Action code
	 * @param object    $object Object
	 * @param User      $user User
	 * @param Translate $langs Languages
	 * @param Conf      $conf Conf
	 * @return int
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->lmdbreferral->enabled)) {
			return 0;
		}

		if (($action === 'COMPANY_CREATE' || $action === 'COMPANY_MODIFY') && GETPOSTISSET('lmdbreferral_referrer')) {
			if (!lmdbreferralCanDo($user, 'write', $object)) {
				$this->error = 'Permission denied';
				return -1;
			}
			$socid = !empty($object->id) ? (int) $object->id : 0;
			if ($socid <= 0) {
				return 0;
			}
			$service = new LmdbReferralService($this->db);
			$result = $service->replaceFromValue(GETPOST('lmdbreferral_referrer', 'alphanohtml'), $socid, $user);
			if ($result < 0) {
				$this->error = $langs->trans($service->error);
				$this->errors = $service->errors;
				return -1;
			}
		}

		if ($action === 'PROPAL_CLOSE_SIGNED') {
			$service = new LmdbReferralService($this->db);
			$result = $service->handlePropalSigned($object, $user);
			if ($result < 0) {
				$this->error = $service->error;
				$this->errors = $service->errors;
				return -1;
			}
		}

		return 0;
	}
}
