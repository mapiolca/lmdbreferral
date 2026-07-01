<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');
dol_include_once('/lmdbreferral/class/lmdbreferralservice.class.php');
dol_include_once('/lmdbreferral/class/lmdbreferralstats.class.php');

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
	 * Delete referral link.
	 *
	 * @url DELETE /referrals/{id}
	 *
	 * @param int $id Link id
	 * @return array<string,string>
	 */
	public function deleteReferral($id)
	{
		$this->checkPermission('delete');
		$service = new LmdbReferralService($this->db);
		if ($service->deleteLink((int) $id, DolibarrApiAccess::$user) < 0) {
			throw new RestException(400, $service->error);
		}

		return array('success' => 'ok');
	}

	/**
	 * Cancel referral link.
	 *
	 * @url POST /referrals/{id}/cancel
	 *
	 * @param int $id Link id
	 * @return array<string,string>
	 */
	public function cancelReferral($id)
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
	 * @param string $datefield Date field
	 * @param string $date_start Start date
	 * @param string $date_end End date
	 * @param string $referrer_type Referrer type
	 * @param int    $status Link status
	 * @param string $signed Signed filter
	 * @param mixed  $entity Entity filter
	 * @return array<string,mixed>
	 */
	public function getStats($datefield = 'link', $date_start = '', $date_end = '', $referrer_type = '', $status = 0, $signed = '', $entity = null)
	{
		$this->checkPermission('read');

		$stats = new LmdbReferralStats($this->db);
		$filters = $this->buildStatsFilters($datefield, $date_start, $date_end, $referrer_type, $status, $signed, $entity);
		$overview = $stats->getOverviewStats(DolibarrApiAccess::$user, $filters);
		if ($stats->error !== '') {
			throw new RestException(500, $stats->error);
		}
		$funnel = $stats->getFunnelStats(DolibarrApiAccess::$user, $filters);

		return array(
			'active_referrers' => (int) $overview['active_referrers'],
			'total_referred' => (int) $overview['total_referred'],
			'signed_referred' => (int) $overview['signed_referred'],
			'amount_ht' => (float) $overview['amount_ht'],
			'amount_ttc' => (float) $overview['amount_ttc'],
			'overview' => $overview,
			'funnel' => $funnel,
			'rankings' => array(
				'signed_count' => $stats->getRankingBySignedCount(DolibarrApiAccess::$user, $filters, 10),
				'amount' => $stats->getRankingByAmount(DolibarrApiAccess::$user, $filters, 10),
			),
			'followup' => $stats->getFollowUpList(DolibarrApiAccess::$user, $filters, 20),
		);
	}

	/**
	 * Get referral star graph.
	 *
	 * @url GET /graph
	 *
	 * @param string $center Graph center
	 * @param int    $depth Graph depth
	 * @param string $datefield Date field
	 * @param string $date_start Start date
	 * @param string $date_end End date
	 * @param string $referrer_type Referrer type
	 * @param int    $status Link status
	 * @param string $signed Signed filter
	 * @param mixed  $entity Entity filter
	 * @return array<string,mixed>
	 */
	public function getGraph($center = '', $depth = 1, $datefield = 'link', $date_start = '', $date_end = '', $referrer_type = '', $status = 0, $signed = '', $entity = null)
	{
		$this->checkPermission('read');

		$stats = new LmdbReferralStats($this->db);
		$filters = $this->buildStatsFilters($datefield, $date_start, $date_end, $referrer_type, $status, $signed, $entity);
		$filters['center'] = $center;
		$filters['depth'] = (int) $depth;
		$graph = $stats->getStarGraphData(DolibarrApiAccess::$user, $filters, $center, (int) $depth);
		if ($stats->error !== '') {
			throw new RestException(500, $stats->error);
		}

		return $graph;
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
	 * Build stats filters from REST parameters and optional Dolibarr date components.
	 *
	 * @param string $datefield Date field
	 * @param string $date_start Start date
	 * @param string $date_end End date
	 * @param string $referrer_type Referrer type
	 * @param int    $status Link status
	 * @param string $signed Signed filter
	 * @param mixed  $entity Entity filter
	 * @return array<string,mixed>
	 */
	private function buildStatsFilters($datefield, $date_start, $date_end, $referrer_type, $status, $signed, $entity)
	{
		return array(
			'datefield' => $datefield,
			'date_start' => $date_start,
			'date_end' => $date_end,
			'date_startday' => GETPOSTINT('date_startday'),
			'date_startmonth' => GETPOSTINT('date_startmonth'),
			'date_startyear' => GETPOSTINT('date_startyear'),
			'date_endday' => GETPOSTINT('date_endday'),
			'date_endmonth' => GETPOSTINT('date_endmonth'),
			'date_endyear' => GETPOSTINT('date_endyear'),
			'referrer_type' => $referrer_type,
			'status' => (int) $status,
			'signed' => $signed,
			'entity' => $entity !== null ? $entity : GETPOST('entity', 'array'),
		);
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
