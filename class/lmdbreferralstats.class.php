<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

/**
 * Centralized referral statistics service.
 */
class LmdbReferralStats
{
	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Build normalized dashboard filters.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Raw filters
	 * @return array<string,mixed>
	 */
	public function buildFilters($user, array $filters = array())
	{
		$datefield = !empty($filters['datefield']) ? (string) $filters['datefield'] : 'link';
		if (!in_array($datefield, array('link', 'signature'), true)) {
			$datefield = 'link';
		}

		$referrerType = !empty($filters['referrer_type']) ? (string) $filters['referrer_type'] : '';
		if (!in_array($referrerType, array('soc', 'user'), true)) {
			$referrerType = '';
		}

		$status = !empty($filters['status']) ? (int) $filters['status'] : 0;
		if (!in_array($status, array(LmdbReferralLink::STATUS_ACTIVE, LmdbReferralLink::STATUS_CANCELLED), true)) {
			$status = 0;
		}

		$signed = !empty($filters['signed']) ? (string) $filters['signed'] : '';
		if (!in_array($signed, array('yes', 'no'), true)) {
			$signed = '';
		}

		$entities = $this->normalizeEntityFilter($filters);
		$depth = !empty($filters['depth']) ? (int) $filters['depth'] : getDolGlobalInt('LMDBREFERRAL_STAR_DEFAULT_DEPTH', 1);
		$allowDepth2 = getDolGlobalInt('LMDBREFERRAL_STAR_ENABLE_DEPTH_2', 1);
		$depth = max(1, min($allowDepth2 ? 2 : 1, $depth));

		$center = !empty($filters['center']) ? (string) $filters['center'] : '';
		if (!$this->isValidReferrerKey($center)) {
			$center = '';
		}

		return array(
			'datefield' => $datefield,
			'date_start' => $this->normalizeDateFilter($filters, 'date_start', false),
			'date_end' => $this->normalizeDateFilter($filters, 'date_end', true),
			'referrer_type' => $referrerType,
			'status' => $status,
			'signed' => $signed,
			'entities' => $entities,
			'center' => $center,
			'depth' => $depth,
			'own_only' => (!lmdbreferralCanDo($user, 'all') && lmdbreferralCanDo($user, 'own')),
			'user_id' => (int) $user->id,
		);
	}

	/**
	 * Return overview statistics.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Filters
	 * @return array<string,int|float>
	 */
	public function getOverviewStats($user, array $filters = array())
	{
		$filters = $this->buildFilters($user, $filters);
		$where = $this->buildLinkWhere('l', $filters);
		$eventJoin = $this->buildEventJoinCondition('e', 'l', $filters);

		$out = array(
			'active_referrers' => 0,
			'total_referred' => 0,
			'active_referred' => 0,
			'signed_referred' => 0,
			'signed_propals' => 0,
			'amount_ht' => 0.0,
			'amount_ttc' => 0.0,
			'conversion_rate' => 0.0,
			'average_basket_ht' => 0.0,
			'average_delay' => 0.0,
			'referred_became_referrers' => 0,
		);

		$sql = "SELECT";
		$sql .= " COUNT(DISTINCT CASE WHEN l.status = ".LmdbReferralLink::STATUS_ACTIVE." THEN CONCAT(l.referrer_type, ':', COALESCE(l.fk_soc_parrain, l.fk_user_parrain, 0)) END) as active_referrers,";
		$sql .= ' COUNT(DISTINCT l.fk_soc_filleul) as total_referred,';
		$sql .= ' COUNT(DISTINCT CASE WHEN l.status = '.LmdbReferralLink::STATUS_ACTIVE.' THEN l.fk_soc_filleul END) as active_referred,';
		$sql .= ' COUNT(DISTINCT CASE WHEN e.rowid IS NOT NULL THEN l.fk_soc_filleul END) as signed_referred,';
		$sql .= ' COUNT(DISTINCT e.fk_propal) as signed_propals,';
		$sql .= ' SUM(e.amount_ht) as amount_ht, SUM(e.amount_ttc) as amount_ttc,';
		$sql .= ' AVG(CASE WHEN e.rowid IS NOT NULL THEN DATEDIFF(e.date_event, l.date_creation) END) as average_delay';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbreferral_event as e ON '.$eventJoin;
		$sql .= ' WHERE '.implode(' AND ', $where);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $out;
		}
		if ($obj = $this->db->fetch_object($resql)) {
			$out['active_referrers'] = (int) $obj->active_referrers;
			$out['total_referred'] = (int) $obj->total_referred;
			$out['active_referred'] = (int) $obj->active_referred;
			$out['signed_referred'] = (int) $obj->signed_referred;
			$out['signed_propals'] = (int) $obj->signed_propals;
			$out['amount_ht'] = lmdbreferralRoundAmount($obj->amount_ht);
			$out['amount_ttc'] = lmdbreferralRoundAmount($obj->amount_ttc);
			$out['average_delay'] = (float) $obj->average_delay;
		}
		$this->db->free($resql);

		$out['conversion_rate'] = $out['total_referred'] > 0 ? ($out['signed_referred'] / $out['total_referred'] * 100) : 0.0;
		$out['average_basket_ht'] = lmdbreferralRoundAmount($out['signed_referred'] > 0 ? ($out['amount_ht'] / $out['signed_referred']) : 0.0);
		$out['referred_became_referrers'] = $this->getReferredBecameReferrers($filters);

		return $out;
	}

	/**
	 * Return statistics for one referral link.
	 *
	 * @param User             $user Current user
	 * @param LmdbReferralLink $link Referral link
	 * @return array{
	 *     is_transformed: bool,
	 *     is_locked: bool,
	 *     signed_propals: int,
	 *     amount_ht: float,
	 *     amount_ttc: float,
	 *     average_basket_ht: float,
	 *     first_signature_date: string,
	 *     last_signature_date: string,
	 *     days_to_first_signature: int|null,
	 *     days_to_first_signature_start_date: string,
	 *     days_to_first_signature_end_date: string,
	 *     age_days: int,
	 *     age_start_date: string,
	 *     age_end_date: string,
	 *     propals: array<int,array{fk_propal:int,ref:string,amount_ht:float,amount_ttc:float,date_event:string}>
	 * }
	 */
	public function getLinkStats($user, $link)
	{
		$out = array(
			'is_transformed' => false,
			'is_locked' => false,
			'signed_propals' => 0,
			'amount_ht' => 0.0,
			'amount_ttc' => 0.0,
			'average_basket_ht' => 0.0,
			'first_signature_date' => '',
			'last_signature_date' => '',
			'days_to_first_signature' => null,
			'days_to_first_signature_start_date' => '',
			'days_to_first_signature_end_date' => '',
			'age_days' => 0,
			'age_start_date' => '',
			'age_end_date' => '',
			'propals' => array(),
		);

		$linkId = !empty($link->id) ? (int) $link->id : (!empty($link->rowid) ? (int) $link->rowid : 0);
		if ($linkId <= 0) {
			return $out;
		}
		if (!lmdbreferralCanDo($user, 'read') && !lmdbreferralCanReadOwnLink($user, $link)) {
			$this->error = 'NotEnoughPermissions';
			return $out;
		}

		$linkCreationTimestamp = $this->dateToTimestamp(!empty($link->date_creation) ? $link->date_creation : 0);
		$useReferredCreationDate = getDolGlobalInt('LMDBREFERRAL_USE_REFERRED_THIRDPARTY_CREATION_DATE', 1);
		$referredCreationTimestamp = $useReferredCreationDate ? $this->getReferredThirdpartyCreationTimestamp(!empty($link->fk_soc_filleul) ? (int) $link->fk_soc_filleul : 0) : 0;
		$ageStartTimestamp = $referredCreationTimestamp > 0 ? $referredCreationTimestamp : $linkCreationTimestamp;
		$ageEndTimestamp = dol_now();
		$out['age_days'] = $this->getDateDiffInDays($ageStartTimestamp, $ageEndTimestamp);
		if ($ageStartTimestamp > 0) {
			$out['age_start_date'] = $this->db->idate($ageStartTimestamp);
			$out['age_end_date'] = $this->db->idate($ageEndTimestamp);
		}

		$where = array(
			'e.entity IN ('.lmdbreferralGetEntitySql('lmdbreferralevent').')',
			'e.fk_lmdbreferral_link = '.$linkId,
			"e.event_type = 'propal_signed'",
		);

		$sql = 'SELECT COUNT(DISTINCT e.fk_propal) as signed_propals, SUM(e.amount_ht) as amount_ht, SUM(e.amount_ttc) as amount_ttc,';
		$sql .= ' MIN(e.date_event) as first_signature_date, MAX(e.date_event) as last_signature_date';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_event as e';
		$sql .= ' WHERE '.implode(' AND ', $where);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $out;
		}
		if ($obj = $this->db->fetch_object($resql)) {
			$out['signed_propals'] = (int) $obj->signed_propals;
			$out['amount_ht'] = lmdbreferralRoundAmount($obj->amount_ht);
			$out['amount_ttc'] = lmdbreferralRoundAmount($obj->amount_ttc);
			$out['first_signature_date'] = !empty($obj->first_signature_date) ? (string) $obj->first_signature_date : '';
			$out['last_signature_date'] = !empty($obj->last_signature_date) ? (string) $obj->last_signature_date : '';
		}
		$this->db->free($resql);

		$out['is_transformed'] = $out['signed_propals'] > 0;
		$out['is_locked'] = $out['is_transformed'];
		$out['average_basket_ht'] = lmdbreferralRoundAmount($out['signed_propals'] > 0 ? ($out['amount_ht'] / $out['signed_propals']) : 0.0);
		if ($out['first_signature_date'] !== '' && $ageStartTimestamp > 0) {
			$delayStartTimestamp = $ageStartTimestamp;
			$delayEndTimestamp = $this->dateToTimestamp($out['first_signature_date']);
			$out['days_to_first_signature'] = $this->getDateDiffInDays($delayStartTimestamp, $delayEndTimestamp);
			if ($delayStartTimestamp > 0 && $delayEndTimestamp > 0) {
				$out['days_to_first_signature_start_date'] = $this->db->idate($delayStartTimestamp);
				$out['days_to_first_signature_end_date'] = $this->db->idate($delayEndTimestamp);
			}
		}

		$sql = 'SELECT e.fk_propal, e.amount_ht, e.amount_ttc, e.date_event, p.ref as propal_ref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_event as e';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'propal as p ON p.rowid = e.fk_propal';
		$sql .= ' WHERE '.implode(' AND ', $where);
		$sql .= ' ORDER BY e.date_event ASC, e.rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $out;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$propalId = !empty($obj->fk_propal) ? (int) $obj->fk_propal : 0;
			$ref = !empty($obj->propal_ref) ? (string) $obj->propal_ref : ($propalId > 0 ? (string) $propalId : '');
			$out['propals'][] = array(
				'fk_propal' => $propalId,
				'ref' => $ref,
				'amount_ht' => lmdbreferralRoundAmount($obj->amount_ht),
				'amount_ttc' => lmdbreferralRoundAmount($obj->amount_ttc),
				'date_event' => !empty($obj->date_event) ? (string) $obj->date_event : '',
			);
		}
		$this->db->free($resql);

		return $out;
	}

	/**
	 * Return funnel data.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Filters
	 * @return array<string,mixed>
	 */
	public function getFunnelStats($user, array $filters = array())
	{
		$overview = $this->getOverviewStats($user, $filters);
		$total = (int) $overview['total_referred'];

		return array(
			'total_referred' => $total,
			'active_referred' => (int) $overview['active_referred'],
			'signed_referred' => (int) $overview['signed_referred'],
			'signed_propals' => (int) $overview['signed_propals'],
			'amount_ht' => lmdbreferralRoundAmount($overview['amount_ht']),
			'amount_ttc' => lmdbreferralRoundAmount($overview['amount_ttc']),
			'average_basket_ht' => lmdbreferralRoundAmount($overview['average_basket_ht']),
			'conversion_rate' => (float) $overview['conversion_rate'],
			'steps' => array(
				array('key' => 'total_referred', 'label' => 'LmdbReferralTotalReferred', 'value' => $total, 'percent' => 100.0),
				array('key' => 'active_referred', 'label' => 'LmdbReferralActiveReferred', 'value' => (int) $overview['active_referred'], 'percent' => $total > 0 ? ((int) $overview['active_referred'] / $total * 100) : 0.0),
				array('key' => 'signed_referred', 'label' => 'LmdbReferralSignedReferred', 'value' => (int) $overview['signed_referred'], 'percent' => $total > 0 ? ((int) $overview['signed_referred'] / $total * 100) : 0.0),
			),
		);
	}

	/**
	 * Return ranking by signed referred thirdparties.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Filters
	 * @param int                 $limit   Limit
	 * @return array<int,array<string,mixed>>
	 */
	public function getRankingBySignedCount($user, array $filters = array(), $limit = 10)
	{
		return $this->getRanking($user, $filters, 'signed_referred', (int) $limit);
	}

	/**
	 * Return ranking by generated amount.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Filters
	 * @param int                 $limit   Limit
	 * @return array<int,array<string,mixed>>
	 */
	public function getRankingByAmount($user, array $filters = array(), $limit = 10)
	{
		return $this->getRanking($user, $filters, 'amount_ht', (int) $limit);
	}

	/**
	 * Return active referred thirdparties without signed proposal after the configured delay.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Filters
	 * @param int                 $limit   Limit
	 * @return array<int,array<string,mixed>>
	 */
	public function getFollowUpList($user, array $filters = array(), $limit = 20)
	{
		$filters = $this->buildFilters($user, $filters);
		$where = $this->buildLinkWhere('l', $filters);
		$where[] = 'l.status = '.LmdbReferralLink::STATUS_ACTIVE;
		$where[] = "NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."lmdbreferral_event as e2 WHERE e2.fk_lmdbreferral_link = l.rowid AND e2.event_type = 'propal_signed')";
		$delayDays = max(0, getDolGlobalInt('LMDBREFERRAL_FOLLOWUP_DELAY_DAYS', 30));
		if ($delayDays > 0) {
			$where[] = "l.date_creation < '".$this->db->idate(dol_time_plus_duree(dol_now(), -$delayDays, 'd'))."'";
		}

		$sql = 'SELECT l.rowid, l.referrer_type, l.fk_soc_parrain, l.fk_user_parrain, l.fk_soc_filleul, l.date_creation, l.entity,';
		$sql .= ' filleul.nom as filleul_name, par.nom as parrain_name, up.lastname, up.firstname, up.login,';
		$sql .= ' DATEDIFF(NOW(), l.date_creation) as age_days, GROUP_CONCAT(DISTINCT CONCAT(uc.firstname, " ", uc.lastname) SEPARATOR ", ") as commercials';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as filleul ON filleul.rowid = l.fk_soc_filleul';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as par ON par.rowid = l.fk_soc_parrain';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as up ON up.rowid = l.fk_user_parrain';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux as sc ON sc.fk_soc = filleul.rowid';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as uc ON uc.rowid = sc.fk_user';
		$sql .= ' WHERE '.implode(' AND ', $where);
		$sql .= ' GROUP BY l.rowid, l.referrer_type, l.fk_soc_parrain, l.fk_user_parrain, l.fk_soc_filleul, l.date_creation, l.entity, filleul.nom, par.nom, up.lastname, up.firstname, up.login';
		$sql .= ' ORDER BY l.date_creation ASC, l.rowid ASC';
		$sql .= $this->db->plimit(max(1, (int) $limit));

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}

		$out = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$out[] = array(
				'link_id' => (int) $obj->rowid,
				'filleul_id' => (int) $obj->fk_soc_filleul,
				'filleul_label' => (string) $obj->filleul_name,
				'referrer_type' => (string) $obj->referrer_type,
				'referrer_id' => (string) $obj->referrer_type === 'soc' ? (int) $obj->fk_soc_parrain : (int) $obj->fk_user_parrain,
				'referrer_label' => $this->formatReferrerLabel($obj),
				'date_creation' => (string) $obj->date_creation,
				'age_days' => (int) $obj->age_days,
				'commercials' => !empty($obj->commercials) ? (string) $obj->commercials : '',
				'entity' => (int) $obj->entity,
			);
		}
		$this->db->free($resql);

		return $out;
	}

	/**
	 * Return star graph data.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Filters
	 * @param string              $center  Center key
	 * @param int                 $depth   Depth
	 * @return array{center:array<string,mixed>,nodes:array<int,array<string,mixed>>,edges:array<int,array<string,mixed>>,overflow_count:int}
	 */
	public function getStarGraphData($user, array $filters = array(), $center = '', $depth = 1)
	{
		$filters['center'] = $center !== '' ? $center : (!empty($filters['center']) ? (string) $filters['center'] : '');
		$filters['depth'] = $depth > 0 ? $depth : (!empty($filters['depth']) ? (int) $filters['depth'] : 1);
		$filters = $this->buildFilters($user, $filters);
		$centerKey = !empty($filters['center']) ? (string) $filters['center'] : $this->guessGraphCenter($user, $filters);
		if (!$this->isValidReferrerKey($centerKey)) {
			return array('center' => array(), 'nodes' => array(), 'edges' => array(), 'overflow_count' => 0);
		}

		$centerNode = $this->getReferrerNode($centerKey);
		if (empty($centerNode)) {
			return array('center' => array(), 'nodes' => array(), 'edges' => array(), 'overflow_count' => 0);
		}

		$maxNodes = max(1, getDolGlobalInt('LMDBREFERRAL_STAR_MAX_NODES', 30));
		$nodes = array();
		$edges = array();
		$overflow = 0;
		$direct = $this->fetchGraphChildren($centerKey, $filters, $maxNodes + 1);
		if (count($direct) > $maxNodes) {
			$overflow += count($direct) - $maxNodes;
			$direct = array_slice($direct, 0, $maxNodes);
		}

		foreach ($direct as $node) {
			$nodes[$node['id']] = $node;
			$edges[] = array('from' => $centerKey, 'to' => $node['id'], 'status' => (int) $node['status'], 'amount_ht' => lmdbreferralRoundAmount($node['amount_ht']));
		}

		if ((int) $filters['depth'] > 1 && count($nodes) < $maxNodes) {
			$firstLevelIds = array_keys($nodes);
			foreach ($firstLevelIds as $firstLevelId) {
				if (count($nodes) >= $maxNodes) {
					$overflow++;
					break;
				}
				$children = $this->fetchGraphChildren($firstLevelId, $filters, $maxNodes - count($nodes) + 1);
				foreach ($children as $node) {
					if (count($nodes) >= $maxNodes) {
						$overflow++;
						continue;
					}
					if (!isset($nodes[$node['id']]) && $node['id'] !== $centerKey) {
						$node['level'] = 2;
						$nodes[$node['id']] = $node;
					}
					$edges[] = array('from' => $firstLevelId, 'to' => $node['id'], 'status' => (int) $node['status'], 'amount_ht' => lmdbreferralRoundAmount($node['amount_ht']));
				}
			}
		}

		return array(
			'center' => $centerNode,
			'nodes' => array_values($nodes),
			'edges' => $edges,
			'overflow_count' => $overflow,
		);
	}

	/**
	 * Return referrer options for graph center selection.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Filters
	 * @param int                 $limit   Limit
	 * @return array<int,array<string,string>>
	 */
	public function getReferrerOptions($user, array $filters = array(), $limit = 100)
	{
		$rows = $this->getRankingByAmount($user, $filters, max(1, (int) $limit));
		$out = array();
		foreach ($rows as $row) {
			$out[] = array(
				'id' => $row['referrer_type'].':'.$row['referrer_id'],
				'label' => (string) $row['referrer_label'],
			);
		}

		return $out;
	}

	/**
	 * Return selectable entities.
	 *
	 * @return array<int,string>
	 */
	public function getEntityOptions()
	{
		$sql = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity';
		$sql .= ' WHERE rowid IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		$sql .= ' ORDER BY label ASC';
		$resql = $this->db->query($sql);
		$out = array();
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$out[(int) $obj->rowid] = (string) $obj->label;
			}
			$this->db->free($resql);
		}

		return $out;
	}

	/**
	 * Return ranking.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Filters
	 * @param string              $orderBy Order metric
	 * @param int                 $limit   Limit
	 * @return array<int,array<string,mixed>>
	 */
	private function getRanking($user, array $filters, $orderBy, $limit)
	{
		$filters = $this->buildFilters($user, $filters);
		$where = $this->buildLinkWhere('l', $filters);
		$eventJoin = $this->buildEventJoinCondition('e', 'l', $filters);
		$orderSql = $orderBy === 'amount_ht' ? 'amount_ht DESC, signed_referred DESC, filleuls DESC' : 'signed_referred DESC, amount_ht DESC, filleuls DESC';

		$sql = 'SELECT l.referrer_type, l.fk_soc_parrain, l.fk_user_parrain, par.nom as parrain_name, up.lastname, up.firstname, up.login,';
		$sql .= ' COUNT(DISTINCT l.fk_soc_filleul) as filleuls, COUNT(DISTINCT CASE WHEN e.rowid IS NOT NULL THEN l.fk_soc_filleul END) as signed_referred,';
		$sql .= ' COUNT(DISTINCT e.fk_propal) as signed_propals, SUM(e.amount_ht) as amount_ht, SUM(e.amount_ttc) as amount_ttc';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as par ON par.rowid = l.fk_soc_parrain';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as up ON up.rowid = l.fk_user_parrain';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbreferral_event as e ON '.$eventJoin;
		$sql .= ' WHERE '.implode(' AND ', $where);
		$sql .= ' GROUP BY l.referrer_type, l.fk_soc_parrain, l.fk_user_parrain, par.nom, up.lastname, up.firstname, up.login';
		$sql .= ' ORDER BY '.$orderSql;
		$sql .= $this->db->plimit(max(1, (int) $limit));
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}

		$out = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$signedReferred = (int) $obj->signed_referred;
			$amountHt = lmdbreferralRoundAmount($obj->amount_ht);
			$amountTtc = lmdbreferralRoundAmount($obj->amount_ttc);
			$referrerId = (string) $obj->referrer_type === 'soc' ? (int) $obj->fk_soc_parrain : (int) $obj->fk_user_parrain;
			$out[] = array(
				'referrer_type' => (string) $obj->referrer_type,
				'referrer_id' => $referrerId,
				'referrer_label' => $this->formatReferrerLabel($obj),
				'filleuls' => (int) $obj->filleuls,
				'signed_referred' => $signedReferred,
				'signed_propals' => (int) $obj->signed_propals,
				'amount_ht' => $amountHt,
				'amount_ttc' => $amountTtc,
				'average_basket_ht' => lmdbreferralRoundAmount($signedReferred > 0 ? ($amountHt / $signedReferred) : 0.0),
			);
		}
		$this->db->free($resql);

		return $out;
	}

	/**
	 * Return count of referred thirdparties that later became referrers.
	 *
	 * @param array<string,mixed> $filters Filters
	 * @return int
	 */
	private function getReferredBecameReferrers(array $filters)
	{
		$where = $this->buildLinkWhere('l1', $filters);
		$sql = 'SELECT COUNT(DISTINCT l1.fk_soc_filleul) as nb';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l1';
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."lmdbreferral_link as l2 ON l2.referrer_type = 'soc' AND l2.fk_soc_parrain = l1.fk_soc_filleul";
		$sql .= ' AND l2.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		$sql .= ' WHERE '.implode(' AND ', $where);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$value = $obj ? (int) $obj->nb : 0;
		$this->db->free($resql);

		return $value;
	}

	/**
	 * Build SQL conditions for a link alias.
	 *
	 * @param string              $alias   SQL alias
	 * @param array<string,mixed> $filters Filters
	 * @return array<int,string>
	 */
	private function buildLinkWhere($alias, array $filters)
	{
		$where = array($alias.'.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')');
		if (!empty($filters['entities']) && is_array($filters['entities'])) {
			$where[] = $alias.'.entity IN ('.implode(',', array_map('intval', $filters['entities'])).')';
		}
		if (!empty($filters['own_only'])) {
			$where[] = $alias.".referrer_type = 'user'";
			$where[] = $alias.'.fk_user_parrain = '.((int) $filters['user_id']);
		}
		if (!empty($filters['referrer_type'])) {
			$where[] = $alias.".referrer_type = '".$this->db->escape((string) $filters['referrer_type'])."'";
		}
		if (!empty($filters['status'])) {
			$where[] = $alias.'.status = '.((int) $filters['status']);
		}
		if (!empty($filters['date_start']) && (string) $filters['datefield'] === 'link') {
			$where[] = $alias.".date_creation >= '".$this->db->idate((int) $filters['date_start'])."'";
		}
		if (!empty($filters['date_end']) && (string) $filters['datefield'] === 'link') {
			$where[] = $alias.".date_creation <= '".$this->db->idate((int) $filters['date_end'])."'";
		}
		if (!empty($filters['signed'])) {
			$exists = $this->buildSignedExistsCondition($alias, $filters);
			$where[] = ((string) $filters['signed'] === 'yes') ? $exists : 'NOT '.$exists;
		} elseif ((string) $filters['datefield'] === 'signature' && (!empty($filters['date_start']) || !empty($filters['date_end']))) {
			$where[] = $this->buildSignedExistsCondition($alias, $filters);
		}

		return $where;
	}

	/**
	 * Build event join condition.
	 *
	 * @param string              $eventAlias Event alias
	 * @param string              $linkAlias  Link alias
	 * @param array<string,mixed> $filters    Filters
	 * @return string
	 */
	private function buildEventJoinCondition($eventAlias, $linkAlias, array $filters)
	{
		$condition = $eventAlias.'.fk_lmdbreferral_link = '.$linkAlias.'.rowid';
		$condition .= " AND ".$eventAlias.".event_type = 'propal_signed'";
		if ((string) $filters['datefield'] === 'signature') {
			if (!empty($filters['date_start'])) {
				$condition .= " AND ".$eventAlias.".date_event >= '".$this->db->idate((int) $filters['date_start'])."'";
			}
			if (!empty($filters['date_end'])) {
				$condition .= " AND ".$eventAlias.".date_event <= '".$this->db->idate((int) $filters['date_end'])."'";
			}
		}

		return $condition;
	}

	/**
	 * Build signed event exists condition.
	 *
	 * @param string              $linkAlias Link alias
	 * @param array<string,mixed> $filters   Filters
	 * @return string
	 */
	private function buildSignedExistsCondition($linkAlias, array $filters)
	{
		$condition = 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbreferral_event as es';
		$condition .= ' WHERE es.fk_lmdbreferral_link = '.$linkAlias.'.rowid';
		$condition .= " AND es.event_type = 'propal_signed'";
		if ((string) $filters['datefield'] === 'signature') {
			if (!empty($filters['date_start'])) {
				$condition .= " AND es.date_event >= '".$this->db->idate((int) $filters['date_start'])."'";
			}
			if (!empty($filters['date_end'])) {
				$condition .= " AND es.date_event <= '".$this->db->idate((int) $filters['date_end'])."'";
			}
		}
		$condition .= ')';

		return $condition;
	}

	/**
	 * Normalize entity filter.
	 *
	 * @param array<string,mixed> $filters Raw filters
	 * @return array<int,int>
	 */
	private function normalizeEntityFilter(array $filters)
	{
		$input = array();
		if (isset($filters['entities'])) {
			$input = is_array($filters['entities']) ? $filters['entities'] : array($filters['entities']);
		} elseif (isset($filters['entity'])) {
			$input = is_array($filters['entity']) ? $filters['entity'] : array($filters['entity']);
		}
		$out = array();
		foreach ($input as $entityId) {
			if ((int) $entityId > 0) {
				$out[(int) $entityId] = (int) $entityId;
			}
		}

		return $out;
	}

	/**
	 * Convert a Dolibarr SQL date or timestamp to a timestamp.
	 *
	 * @param mixed $date Date value
	 * @return int
	 */
	private function dateToTimestamp($date)
	{
		if (empty($date)) {
			return 0;
		}
		if (is_numeric($date)) {
			return (int) $date;
		}

		return (int) $this->db->jdate($date);
	}

	/**
	 * Return the creation timestamp of the referred thirdparty in the current entity scope.
	 *
	 * @param int $fkSocFilleul Referred thirdparty id
	 * @return int
	 */
	private function getReferredThirdpartyCreationTimestamp($fkSocFilleul)
	{
		if ((int) $fkSocFilleul <= 0) {
			return 0;
		}

		$sql = 'SELECT s.datec';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'societe as s';
		$sql .= ' WHERE s.rowid = '.((int) $fkSocFilleul);
		$sql .= ' AND s.entity IN ('.lmdbreferralGetEntitySql('societe').')';
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		$timestamp = is_object($obj) && !empty($obj->datec) ? $this->dateToTimestamp($obj->datec) : 0;
		$this->db->free($resql);

		return $timestamp;
	}

	/**
	 * Return a non-negative day difference.
	 *
	 * @param int $startTimestamp Start timestamp
	 * @param int $endTimestamp End timestamp
	 * @return int
	 */
	private function getDateDiffInDays($startTimestamp, $endTimestamp)
	{
		if ($startTimestamp <= 0 || $endTimestamp <= 0) {
			return 0;
		}

		return max(0, (int) floor(($endTimestamp - $startTimestamp) / 86400));
	}

	/**
	 * Normalize date filter.
	 *
	 * @param array<string,mixed> $filters Raw filters
	 * @param string              $prefix  Prefix
	 * @param bool                $endDay  End of day
	 * @return int
	 */
	private function normalizeDateFilter(array $filters, $prefix, $endDay)
	{
		$value = 0;
		if (!empty($filters[$prefix]) && is_numeric($filters[$prefix])) {
			$value = (int) $filters[$prefix];
		} elseif (!empty($filters[$prefix]) && is_string($filters[$prefix])) {
			$timestamp = strtotime((string) $filters[$prefix]);
			$value = $timestamp !== false ? (int) $timestamp : 0;
		} elseif (!empty($filters[$prefix.'year']) && !empty($filters[$prefix.'month']) && !empty($filters[$prefix.'day'])) {
			$value = dol_mktime($endDay ? 23 : 0, $endDay ? 59 : 0, $endDay ? 59 : 0, (int) $filters[$prefix.'month'], (int) $filters[$prefix.'day'], (int) $filters[$prefix.'year']);
		}
		if ($value > 0 && $endDay) {
			$parts = dol_getdate($value);
			$value = dol_mktime(23, 59, 59, (int) $parts['mon'], (int) $parts['mday'], (int) $parts['year']);
		}

		return $value > 0 ? $value : 0;
	}

	/**
	 * Check referrer key format.
	 *
	 * @param string $key Referrer key
	 * @return bool
	 */
	private function isValidReferrerKey($key)
	{
		return (bool) preg_match('/^(soc|user):[1-9][0-9]*$/', $key);
	}

	/**
	 * Guess graph center from rankings.
	 *
	 * @param User                $user    Current user
	 * @param array<string,mixed> $filters Filters
	 * @return string
	 */
	private function guessGraphCenter($user, array $filters)
	{
		$rows = $this->getRankingByAmount($user, $filters, 1);
		if (empty($rows)) {
			$rows = $this->getRankingBySignedCount($user, $filters, 1);
		}
		if (empty($rows)) {
			return '';
		}

		return $rows[0]['referrer_type'].':'.$rows[0]['referrer_id'];
	}

	/**
	 * Return graph center node.
	 *
	 * @param string $key Referrer key
	 * @return array<string,mixed>
	 */
	private function getReferrerNode($key)
	{
		list($type, $id) = explode(':', $key, 2);
		$id = (int) $id;
		if ($type === 'soc') {
			$sql = 'SELECT rowid, nom FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.$id;
			$resql = $this->db->query($sql);
			$obj = $resql ? $this->db->fetch_object($resql) : null;
			if (!$obj) {
				return array();
			}

			return array('id' => $key, 'label' => (string) $obj->nom, 'type' => 'soc', 'url' => DOL_URL_ROOT.'/societe/card.php?socid='.$id);
		}

		$sql = 'SELECT rowid, lastname, firstname, login FROM '.MAIN_DB_PREFIX.'user WHERE rowid = '.$id;
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		if (!$obj) {
			return array();
		}
		$label = trim((string) $obj->firstname.' '.(string) $obj->lastname);
		if ($label === '') {
			$label = (string) $obj->login;
		}

		return array('id' => $key, 'label' => $label, 'type' => 'user', 'url' => DOL_URL_ROOT.'/user/card.php?id='.$id);
	}

	/**
	 * Fetch children for graph.
	 *
	 * @param string              $centerKey Center key
	 * @param array<string,mixed> $filters   Filters
	 * @param int                 $limit     Limit
	 * @return array<int,array<string,mixed>>
	 */
	private function fetchGraphChildren($centerKey, array $filters, $limit)
	{
		if (!$this->isValidReferrerKey($centerKey)) {
			return array();
		}
		list($type, $id) = explode(':', $centerKey, 2);
		$id = (int) $id;
		$where = $this->buildLinkWhere('l', $filters);
		$where[] = "l.referrer_type = '".$this->db->escape($type)."'";
		$where[] = ($type === 'soc' ? 'l.fk_soc_parrain' : 'l.fk_user_parrain').' = '.$id;
		$eventJoin = $this->buildEventJoinCondition('e', 'l', $filters);

		$sql = 'SELECT l.rowid, l.status, l.fk_soc_filleul, l.date_creation, filleul.nom as filleul_name,';
		$sql .= ' COUNT(DISTINCT e.fk_propal) as signed_propals, SUM(e.amount_ht) as amount_ht,';
		$sql .= " MAX(CASE WHEN child.rowid IS NOT NULL THEN 1 ELSE 0 END) as became_referrer";
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as filleul ON filleul.rowid = l.fk_soc_filleul';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbreferral_event as e ON '.$eventJoin;
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."lmdbreferral_link as child ON child.referrer_type = 'soc' AND child.fk_soc_parrain = l.fk_soc_filleul";
		$sql .= ' WHERE '.implode(' AND ', $where);
		$sql .= ' GROUP BY l.rowid, l.status, l.fk_soc_filleul, l.date_creation, filleul.nom';
		$sql .= ' ORDER BY amount_ht DESC, l.date_creation DESC, l.rowid DESC';
		$sql .= $this->db->plimit(max(1, (int) $limit));
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}

		$out = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$signedPropals = (int) $obj->signed_propals;
			$typeNode = (int) $obj->status === LmdbReferralLink::STATUS_CANCELLED ? 'cancelled' : ($signedPropals > 0 ? 'signed' : 'unsigned');
			if (!empty($obj->became_referrer)) {
				$typeNode = 'became_referrer';
			}
			$out[] = array(
				'id' => 'soc:'.((int) $obj->fk_soc_filleul),
				'label' => (string) $obj->filleul_name,
				'type' => $typeNode,
				'level' => 1,
				'status' => (int) $obj->status,
				'amount_ht' => lmdbreferralRoundAmount($obj->amount_ht),
				'signed_propals' => $signedPropals,
				'url' => DOL_URL_ROOT.'/societe/card.php?socid='.((int) $obj->fk_soc_filleul),
			);
		}
		$this->db->free($resql);

		return $out;
	}

	/**
	 * Format referrer label from a SQL row.
	 *
	 * @param object $obj SQL row
	 * @return string
	 */
	private function formatReferrerLabel($obj)
	{
		if ((string) $obj->referrer_type === 'soc') {
			return (string) $obj->parrain_name;
		}
		$label = trim((string) $obj->firstname.' '.(string) $obj->lastname);
		if ($label === '') {
			$label = (string) $obj->login;
		}

		return $label;
	}
}
