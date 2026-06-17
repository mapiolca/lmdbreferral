<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');
dol_include_once('/lmdbreferral/class/lmdbreferralservice.class.php');

/**
 * Referral REST API.
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class LmdbReferralApi extends DolibarrApi
{
	/** @var DoliDB */
	public $db;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	/**
	 * List referral links.
	 *
	 * @url GET /referrals
	 *
	 * @param int $limit Limit
	 * @param int $page Page
	 * @return array<int,array<string,mixed>>
	 */
	public function getReferrals($limit = 100, $page = 0)
	{
		$this->checkPermission('read');
		$limit = min(max((int) $limit, 1), 500);
		$offset = max((int) $page, 0) * $limit;
		$out = array();

		$sql = 'SELECT t.* FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as t';
		$sql .= ' WHERE t.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		$sql .= ' ORDER BY t.date_creation DESC, t.rowid DESC';
		$sql .= $this->db->plimit($limit, $offset);
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $this->db->lasterror());
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$out[] = $this->rowToArray($obj);
		}

		return $out;
	}

	/**
	 * Get referral link.
	 *
	 * @url GET /referrals/{id}
	 *
	 * @param int $id Link id
	 * @return array<string,mixed>
	 */
	public function getReferral($id)
	{
		$this->checkPermission('read');
		$link = new LmdbReferralLink($this->db);
		if ($link->fetch((int) $id) <= 0) {
			throw new RestException(404, 'Referral not found');
		}

		return $this->rowToArray($link);
	}

	/**
	 * Create referral link.
	 *
	 * Expected payload: referrer_type, referrer_id, fk_soc_filleul.
	 *
	 * @url POST /referrals
	 *
	 * @param array<string,mixed> $request_data Request data
	 * @return int
	 */
	public function postReferral($request_data = null)
	{
		$this->checkPermission('write');
		$data = (array) $request_data;
		$service = new LmdbReferralService($this->db);
		$result = $service->createLink(
			isset($data['referrer_type']) ? (string) $data['referrer_type'] : '',
			isset($data['referrer_id']) ? (int) $data['referrer_id'] : 0,
			isset($data['fk_soc_filleul']) ? (int) $data['fk_soc_filleul'] : 0,
			DolibarrApiAccess::$user
		);
		if ($result < 0) {
			throw new RestException(400, $service->error);
		}

		return $result;
	}

	/**
	 * Replace active referrer for a referred thirdparty.
	 *
	 * Expected payload: referrer_type, referrer_id.
	 *
	 * @url PUT /referrals/referred/{fk_soc_filleul}
	 *
	 * @param int                 $fk_soc_filleul Referred thirdparty
	 * @param array<string,mixed> $request_data Request data
	 * @return int
	 */
	public function putReferredReferrer($fk_soc_filleul, $request_data = null)
	{
		$this->checkPermission('write');
		$data = (array) $request_data;
		$value = '';
		if (!empty($data['referrer_type']) && !empty($data['referrer_id'])) {
			$value = $data['referrer_type'].':'.((int) $data['referrer_id']);
		}
		$service = new LmdbReferralService($this->db);
		$result = $service->replaceFromValue($value, (int) $fk_soc_filleul, DolibarrApiAccess::$user);
		if ($result < 0) {
			throw new RestException(400, $service->error);
		}

		return $result;
	}

	/**
	 * Cancel referral link.
	 *
	 * @url DELETE /referrals/{id}
	 *
	 * @param int $id Link id
	 * @return array<string,string>
	 */
	public function deleteReferral($id)
	{
		$this->checkPermission('cancel');
		$service = new LmdbReferralService($this->db);
		if ($service->cancelLink((int) $id, DolibarrApiAccess::$user) < 0) {
			throw new RestException(400, $service->error);
		}

		return array('success' => 'ok');
	}

	/**
	 * List referral events.
	 *
	 * @url GET /referrals/{id}/events
	 *
	 * @param int $id Link id
	 * @return array<int,array<string,mixed>>
	 */
	public function getReferralEvents($id)
	{
		$this->checkPermission('read');
		$out = array();
		$sql = 'SELECT e.* FROM '.MAIN_DB_PREFIX.'lmdbreferral_event as e';
		$sql .= ' WHERE e.entity IN ('.lmdbreferralGetEntitySql('lmdbreferralevent').')';
		$sql .= ' AND e.fk_lmdbreferral_link = '.((int) $id);
		$sql .= ' ORDER BY e.date_event DESC, e.rowid DESC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $this->db->lasterror());
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$out[] = $this->rowToArray($obj);
		}

		return $out;
	}

	/**
	 * Get referral statistics.
	 *
	 * @url GET /stats
	 *
	 * @return array<string,int|float>
	 */
	public function getStats()
	{
		$this->checkPermission('read');

		$where = 'l.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		$out = array(
			'active_referrers' => 0,
			'total_referred' => 0,
			'signed_referred' => 0,
			'amount_ht' => 0.0,
			'amount_ttc' => 0.0,
		);

		$sql = "SELECT COUNT(DISTINCT CONCAT(l.referrer_type, ':', COALESCE(l.fk_soc_parrain, l.fk_user_parrain))) as nb";
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' WHERE '.$where.' AND l.status = '.LmdbReferralLink::STATUS_ACTIVE;
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $this->db->lasterror());
		}
		if ($obj = $this->db->fetch_object($resql)) {
			$out['active_referrers'] = (int) $obj->nb;
		}

		$sql = 'SELECT COUNT(DISTINCT l.fk_soc_filleul) as nb';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' WHERE '.$where;
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $this->db->lasterror());
		}
		if ($obj = $this->db->fetch_object($resql)) {
			$out['total_referred'] = (int) $obj->nb;
		}

		$sql = 'SELECT COUNT(DISTINCT l.fk_soc_filleul) as nb, SUM(e.amount_ht) as amount_ht, SUM(e.amount_ttc) as amount_ttc';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX."lmdbreferral_event as e ON e.fk_lmdbreferral_link = l.rowid AND e.event_type = 'propal_signed'";
		$sql .= ' WHERE '.$where;
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $this->db->lasterror());
		}
		if ($obj = $this->db->fetch_object($resql)) {
			$out['signed_referred'] = (int) $obj->nb;
			$out['amount_ht'] = (float) $obj->amount_ht;
			$out['amount_ttc'] = (float) $obj->amount_ttc;
		}

		return $out;
	}

	/**
	 * Check API permission.
	 *
	 * @param string $right Right
	 * @return void
	 */
	private function checkPermission($right)
	{
		global $conf;

		if (empty($conf->lmdbreferral->enabled) && function_exists('isModEnabled') && !isModEnabled('lmdbreferral')) {
			throw new RestException(503, 'Module disabled');
		}
		if (!lmdbreferralCanDo(DolibarrApiAccess::$user, 'api') || !lmdbreferralCanDo(DolibarrApiAccess::$user, $right)) {
			throw new RestException(403);
		}
	}

	/**
	 * Convert object row to array.
	 *
	 * @param object $obj Object
	 * @return array<string,mixed>
	 */
	private function rowToArray($obj)
	{
		$out = array();
		foreach (get_object_vars($obj) as $key => $value) {
			if (in_array($key, array('db', 'error', 'errors', 'fields'), true)) {
				continue;
			}
			$out[$key] = $value;
		}

		return $out;
	}
}
