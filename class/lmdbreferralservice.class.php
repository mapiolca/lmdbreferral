<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';

/**
 * Referral service.
 */
class LmdbReferralService
{
	/** @var DoliDB */
	public $db;

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
	 * Create a link from a typed referrer value.
	 *
	 * @param string $value Typed value
	 * @param int    $fkSocFilleul Referred thirdparty
	 * @param User   $user User
	 * @return int Link id, 0 if no value, <0 on error
	 */
	public function createFromValue($value, $fkSocFilleul, User $user)
	{
		$referrer = lmdbreferralParseReferrerValue($value);
		if (empty($referrer['type']) || empty($referrer['id'])) {
			return 0;
		}

		return $this->createLink($referrer['type'], $referrer['id'], $fkSocFilleul, $user);
	}

	/**
	 * Replace active referrer for a thirdparty.
	 *
	 * @param string $value Typed value, empty to cancel
	 * @param int    $fkSocFilleul Referred thirdparty
	 * @param User   $user User
	 * @return int
	 */
	public function replaceFromValue($value, $fkSocFilleul, User $user)
	{
		$referrer = lmdbreferralParseReferrerValue($value);
		$active = $this->fetchActiveByFilleul($fkSocFilleul);
		foreach ($active as $link) {
			if ($this->syncSignedProposalsForLink((int) $link->id, $user) < 0) {
				return -1;
			}
		}

		if ($this->isLockedBySignedProposal($fkSocFilleul)) {
			$this->error = 'LmdbReferralLockedAfterSignedProposal';
			return -1;
		}

		if (empty($referrer['type']) || empty($referrer['id'])) {
			$result = 0;
			foreach ($active as $link) {
				$result = $this->cancelLink($link->id, $user);
				if ($result < 0) {
					return -1;
				}
			}
			return $result;
		}

		foreach ($active as $link) {
			if ($link->referrer_type === $referrer['type']
				&& (int) $link->fk_soc_parrain === ($referrer['type'] === 'soc' ? (int) $referrer['id'] : 0)
				&& (int) $link->fk_user_parrain === ($referrer['type'] === 'user' ? (int) $referrer['id'] : 0)) {
				return (int) $link->id;
			}
		}

		if (getDolGlobalInt('LMDBREFERRAL_ONE_ACTIVE_REFERRER', 1)) {
			foreach ($active as $link) {
				if ($this->cancelLink($link->id, $user) < 0) {
					return -1;
				}
			}
		}

		return $this->createLink($referrer['type'], $referrer['id'], $fkSocFilleul, $user);
	}

	/**
	 * Create a referral link.
	 *
	 * @param string $referrerType Referrer type
	 * @param int    $referrerId Referrer id
	 * @param int    $fkSocFilleul Referred thirdparty
	 * @param User   $user User
	 * @return int
	 */
	public function createLink($referrerType, $referrerId, $fkSocFilleul, User $user)
	{
		global $conf;

		$this->error = '';
		$this->errors = array();
		$referrerType = (string) $referrerType;
		$referrerId = (int) $referrerId;
		$fkSocFilleul = (int) $fkSocFilleul;

		if (!in_array($referrerType, array('soc', 'user'), true) || $referrerId <= 0 || $fkSocFilleul <= 0) {
			$this->error = 'ErrorBadParameters';
			return -1;
		}
		if ($referrerType === 'soc' && $referrerId === $fkSocFilleul) {
			$this->error = 'LmdbReferralSelfReferralForbidden';
			return -1;
		}
		if ($referrerType === 'soc' && !getDolGlobalInt('LMDBREFERRAL_ENABLE_THIRDPARTY_REFERRERS', 1)) {
			$this->error = 'LmdbReferralThirdpartyReferrersDisabled';
			return -1;
		}
		if ($referrerType === 'user' && !getDolGlobalInt('LMDBREFERRAL_ALLOW_USER_REFERRERS')) {
			$this->error = 'LmdbReferralUserReferrersDisabled';
			return -1;
		}
		if (!$this->isReferrerAvailable($referrerType, $referrerId)) {
			$this->error = 'LmdbReferralReferrerUnavailable';
			return -1;
		}
		if (getDolGlobalInt('LMDBREFERRAL_PREVENT_DUPLICATE_PAIR', 1) && $this->activePairExists($referrerType, $referrerId, $fkSocFilleul)) {
			$this->error = 'LmdbReferralDuplicatePairForbidden';
			return -1;
		}
		if (getDolGlobalInt('LMDBREFERRAL_ONE_ACTIVE_REFERRER', 1) && count($this->fetchActiveByFilleul($fkSocFilleul)) > 0) {
			$this->error = 'LmdbReferralOneActiveReferrerOnly';
			return -1;
		}

		$link = new LmdbReferralLink($this->db);
		$link->entity = (int) $conf->entity;
		$link->referrer_type = $referrerType;
		$link->fk_soc_parrain = ($referrerType === 'soc') ? $referrerId : 0;
		$link->fk_user_parrain = ($referrerType === 'user') ? $referrerId : 0;
		$link->fk_soc_filleul = $fkSocFilleul;
		$result = $link->create($user);
		if ($result < 0) {
			$this->error = $link->error;
			$this->errors = $link->errors;
			return -1;
		}

		$this->createEvent((int) $link->id, 'link_created', 0, 0, 0, dol_now(), $user);
		if ($this->syncSignedProposalsForLink((int) $link->id, $user) < 0) {
			return -1;
		}

		return (int) $link->id;
	}

	/**
	 * Cancel a link.
	 *
	 * @param int  $id Link id
	 * @param User $user User
	 * @return int
	 */
	public function cancelLink($id, User $user)
	{
		$link = new LmdbReferralLink($this->db);
		if ($link->fetch($id) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return -1;
		}
		if ($link->cancel($user) < 0) {
			$this->error = $link->error;
			$this->errors = $link->errors;
			return -1;
		}
		$this->createEvent((int) $link->id, 'link_cancelled', 0, 0, 0, dol_now(), $user);

		return 1;
	}

	/**
	 * Delete a referral link and its linked data.
	 *
	 * @param int  $id Link id
	 * @param User $user User
	 * @return int
	 */
	public function deleteLink($id, User $user)
	{
		$link = new LmdbReferralLink($this->db);
		if ($link->fetch($id) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return -1;
		}
		if ($link->delete($user) < 0) {
			$this->error = $link->error;
			$this->errors = $link->errors;
			return -1;
		}

		return 1;
	}

	/**
	 * Tell if a referral link has been transformed by a signed proposal.
	 *
	 * @param int $linkId Link id
	 * @return bool
	 */
	public function isLinkTransformed($linkId)
	{
		$sql = 'SELECT e.rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_event as e';
		$sql .= ' WHERE e.entity IN ('.lmdbreferralGetEntitySql('lmdbreferralevent').')';
		$sql .= ' AND e.fk_lmdbreferral_link = '.((int) $linkId);
		$sql .= " AND e.event_type = 'propal_signed'";
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return true;
		}

		return $this->db->num_rows($resql) > 0;
	}

	/**
	 * Fetch active links for referred thirdparty.
	 *
	 * @param int $fkSocFilleul Referred thirdparty
	 * @return array<int,LmdbReferralLink>
	 */
	public function fetchActiveByFilleul($fkSocFilleul)
	{
		$links = array();
		$sql = 'SELECT t.* FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as t';
		$sql .= ' WHERE t.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		$sql .= ' AND t.fk_soc_filleul = '.((int) $fkSocFilleul);
		$sql .= ' AND t.status = '.LmdbReferralLink::STATUS_ACTIVE;
		$sql .= ' ORDER BY t.date_creation DESC, t.rowid DESC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $links;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$link = new LmdbReferralLink($this->db);
			$link->setVarsFromFetchObj($obj);
			$links[] = $link;
		}

		return $links;
	}

	/**
	 * Tell if a signed proposal already locks the referred thirdparty.
	 *
	 * @param int $fkSocFilleul Referred thirdparty
	 * @return bool
	 */
	public function isLockedBySignedProposal($fkSocFilleul)
	{
		if (!getDolGlobalInt('LMDBREFERRAL_LOCK_REFERRER_AFTER_SIGNED_PROPAL', 1)) {
			return false;
		}
		$sql = 'SELECT e.rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_event as e';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbreferral_link as l ON l.rowid = e.fk_lmdbreferral_link';
		$sql .= ' WHERE l.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		$sql .= ' AND l.fk_soc_filleul = '.((int) $fkSocFilleul);
		$sql .= " AND e.event_type = 'propal_signed'";
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);

		return ($resql && $this->db->num_rows($resql) > 0);
	}

	/**
	 * Handle signed proposal trigger.
	 *
	 * @param Propal $propal Proposal
	 * @param User   $user User
	 * @return int
	 */
	public function handlePropalSigned($propal, User $user)
	{
		$socid = !empty($propal->socid) ? (int) $propal->socid : (int) $propal->fk_soc;
		if ($socid <= 0) {
			return 0;
		}

		$links = $this->fetchActiveByFilleul($socid);
		if (empty($links)) {
			return 0;
		}

		$dateEvent = !empty($propal->date_signature) ? $propal->date_signature : dol_now();
		$result = 0;
		foreach ($links as $link) {
			$res = $this->createEvent(
				(int) $link->id,
				'propal_signed',
				(int) $propal->id,
				isset($propal->total_ht) ? (float) $propal->total_ht : 0,
				isset($propal->total_ttc) ? (float) $propal->total_ttc : 0,
				$dateEvent,
				$user
			);
			if ($res < 0) {
				return -1;
			}
			if ($res > 0) {
				$triggerResult = $link->call_trigger('LMDBREFERRAL_PROPAL_SIGNED', $user);
				if ($triggerResult < 0) {
					$this->error = $link->error;
					$this->errors = $link->errors;
					return -1;
				}
			}
			$result += $res;

			$syncResult = $this->syncSignedProposalsForLink((int) $link->id, $user);
			if ($syncResult < 0) {
				return -1;
			}
			$result += $syncResult;
		}

		return $result;
	}

	/**
	 * Synchronize all signed proposals of the referred thirdparty to a referral link.
	 *
	 * @param int  $linkId Link id
	 * @param User $user   User
	 * @return int Number of internal events created, <0 on error
	 */
	public function syncSignedProposalsForLink($linkId, User $user)
	{
		$link = new LmdbReferralLink($this->db);
		if ($link->fetch((int) $linkId) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return -1;
		}
		if ((int) $link->status !== LmdbReferralLink::STATUS_ACTIVE || (int) $link->fk_soc_filleul <= 0) {
			return 0;
		}

		$created = 0;
		$sql = 'SELECT p.rowid, p.total_ht, p.total_ttc, p.date_signature, p.date_valid, p.tms';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'propal as p';
		$sql .= ' WHERE p.entity IN ('.lmdbreferralGetEntitySql('propal').')';
		$sql .= ' AND p.fk_soc = '.((int) $link->fk_soc_filleul);
		$sql .= ' AND p.fk_statut = '.((int) Propal::STATUS_SIGNED);
		$sql .= ' ORDER BY p.date_signature ASC, p.rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$dateEvent = dol_now();
			if (!empty($obj->date_signature)) {
				$dateEvent = $this->db->jdate($obj->date_signature);
			} elseif (!empty($obj->date_valid)) {
				$dateEvent = $this->db->jdate($obj->date_valid);
			} elseif (!empty($obj->tms)) {
				$dateEvent = $this->db->jdate($obj->tms);
			}

			$result = $this->createEvent(
				(int) $link->id,
				'propal_signed',
				(int) $obj->rowid,
				isset($obj->total_ht) ? (float) $obj->total_ht : 0,
				isset($obj->total_ttc) ? (float) $obj->total_ttc : 0,
				$dateEvent,
				$user
			);
			if ($result < 0) {
				return -1;
			}
			$created += $result;
		}

		return $created;
	}

	/**
	 * Synchronize signed proposals for all active referral links in the current entity scope.
	 *
	 * @param User $user User
	 * @return int Number of internal events created, <0 on error
	 */
	public function syncSignedProposalsForActiveLinks(User $user)
	{
		$created = 0;
		$sql = 'SELECT l.rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' WHERE l.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		$sql .= ' AND l.status = '.LmdbReferralLink::STATUS_ACTIVE;
		$sql .= ' ORDER BY l.rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$result = $this->syncSignedProposalsForLink((int) $obj->rowid, $user);
			if ($result < 0) {
				return -1;
			}
			$created += $result;
		}

		return $created;
	}

	/**
	 * Create event idempotently for signed proposals.
	 *
	 * @param int    $linkId Link id
	 * @param string $type Event type
	 * @param int    $fkPropal Proposal id
	 * @param float  $amountHt Amount HT
	 * @param float  $amountTtc Amount TTC
	 * @param int    $dateEvent Event timestamp
	 * @param User   $user User
	 * @return int 1 created, 0 existing, <0 error
	 */
	public function createEvent($linkId, $type, $fkPropal, $amountHt, $amountTtc, $dateEvent, User $user)
	{
		global $conf;

		$entity = $this->getLinkEntity($linkId);
		if ($entity <= 0) {
			$entity = (int) $conf->entity;
		}

		if ($type === 'propal_signed' && $fkPropal > 0) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbreferral_event';
			$sql .= ' WHERE entity IN ('.lmdbreferralGetEntitySql('lmdbreferralevent').')';
			$sql .= ' AND fk_lmdbreferral_link = '.((int) $linkId);
			$sql .= " AND event_type = '".$this->db->escape($type)."'";
			$sql .= ' AND fk_propal = '.((int) $fkPropal);
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				if ($this->linkSignedProposalObject($linkId, $fkPropal, $user) < 0) {
					return -1;
				}
				if ($this->createNativeAgendaEvent($linkId, $type, $fkPropal, $amountHt, $amountTtc, $dateEvent, $user, $entity) < 0) {
					return -1;
				}
				return 0;
			}
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbreferral_event (';
		$sql .= 'entity, fk_lmdbreferral_link, event_type, fk_propal, amount_ht, amount_ttc, date_event, fk_user_author';
		$sql .= ') VALUES (';
		$sql .= ((int) $entity).', ';
		$sql .= ((int) $linkId).', ';
		$sql .= "'".$this->db->escape($type)."', ";
		$sql .= ($fkPropal > 0 ? ((int) $fkPropal) : 'NULL').', ';
		$sql .= ((float) $amountHt).', ';
		$sql .= ((float) $amountTtc).', ';
		$sql .= "'".$this->db->idate($dateEvent)."', ";
		$sql .= ((int) $user->id);
		$sql .= ')';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		if ($type === 'propal_signed' && $fkPropal > 0) {
			if ($this->linkSignedProposalObject($linkId, $fkPropal, $user) < 0) {
				return -1;
			}
		}

		if ($this->createNativeAgendaEvent($linkId, $type, $fkPropal, $amountHt, $amountTtc, $dateEvent, $user, $entity) < 0) {
			return -1;
		}

		return 1;
	}

	/**
	 * Synchronize existing internal referral events to native Agenda events.
	 *
	 * @param User $user User
	 * @return int Number of native events created, <0 on error
	 */
	public function syncNativeAgendaEvents(User $user)
	{
		if (!isModEnabled('agenda')) {
			return 0;
		}

		$created = 0;
		$sql = 'SELECT e.fk_lmdbreferral_link, e.event_type, e.fk_propal, e.amount_ht, e.amount_ttc, e.date_event, e.entity';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_event as e';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbreferral_link as l ON l.rowid = e.fk_lmdbreferral_link';
		$sql .= ' WHERE e.entity IN ('.lmdbreferralGetEntitySql('lmdbreferralevent').')';
		$sql .= ' ORDER BY e.rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$result = $this->createNativeAgendaEvent(
				(int) $obj->fk_lmdbreferral_link,
				(string) $obj->event_type,
				!empty($obj->fk_propal) ? (int) $obj->fk_propal : 0,
				isset($obj->amount_ht) ? (float) $obj->amount_ht : 0,
				isset($obj->amount_ttc) ? (float) $obj->amount_ttc : 0,
				!empty($obj->date_event) ? $this->db->jdate($obj->date_event) : dol_now(),
				$user,
				(int) $obj->entity
			);
			if ($result < 0) {
				return -1;
			}
			$created += $result;
		}

		return $created;
	}

	/**
	 * Synchronize existing signed proposal events to native linked objects.
	 *
	 * @param User $user User
	 * @return int Number of object links created, <0 on error
	 */
	public function syncNativeLinkedObjects(User $user)
	{
		$created = 0;
		$sql = 'SELECT e.fk_lmdbreferral_link, e.fk_propal';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_event as e';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbreferral_link as l ON l.rowid = e.fk_lmdbreferral_link';
		$sql .= ' WHERE e.entity IN ('.lmdbreferralGetEntitySql('lmdbreferralevent').')';
		$sql .= " AND e.event_type = 'propal_signed'";
		$sql .= ' AND e.fk_propal IS NOT NULL';
		$sql .= ' ORDER BY e.rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$result = $this->linkSignedProposalObject((int) $obj->fk_lmdbreferral_link, (int) $obj->fk_propal, $user);
			if ($result < 0) {
				return -1;
			}
			$created += $result;
		}

		return $created;
	}

	/**
	 * Link a signed proposal to a referral link using Dolibarr native object links.
	 *
	 * @param int  $linkId   Referral link id
	 * @param int  $fkPropal Proposal id
	 * @param User $user     User
	 * @return int 1 created, 0 existing or skipped, <0 error
	 */
	private function linkSignedProposalObject($linkId, $fkPropal, User $user)
	{
		if ((int) $linkId <= 0 || (int) $fkPropal <= 0) {
			return 0;
		}

		$link = new LmdbReferralLink($this->db);
		if ($link->fetch((int) $linkId) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return -1;
		}

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'propal';
		$sql .= ' WHERE rowid = '.((int) $fkPropal);
		$sql .= ' AND entity IN ('.lmdbreferralGetEntitySql('propal').')';
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->num_rows($resql) <= 0) {
			return 0;
		}

		$targetType = $link->getElementType();
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'element_element';
		$sql .= ' WHERE (';
		$sql .= '(fk_source = '.((int) $fkPropal)." AND sourcetype = 'propal' AND fk_target = ".((int) $link->id)." AND targettype = '".$this->db->escape($targetType)."')";
		$sql .= ' OR ';
		$sql .= "(fk_source = ".((int) $link->id)." AND sourcetype = '".$this->db->escape($targetType)."' AND fk_target = ".((int) $fkPropal)." AND targettype = 'propal')";
		$sql .= ')';
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->num_rows($resql) > 0) {
			return 0;
		}

		$this->ensureHookManager();
		$result = $link->add_object_linked('propal', (int) $fkPropal, $user, 1);
		if ($result <= 0) {
			$this->error = $link->error;
			$this->errors = $link->errors;
			return -1;
		}

		return 1;
	}

	/**
	 * Ensure CommonObject link helpers can call hooks safely in non-page contexts.
	 *
	 * @return void
	 */
	private function ensureHookManager()
	{
		global $hookmanager;

		if (!is_object($hookmanager)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
	}

	/**
	 * Create the native Agenda event linked to a referral link.
	 *
	 * @param int    $linkId Link id
	 * @param string $type Event type
	 * @param int    $fkPropal Proposal id
	 * @param float  $amountHt Amount HT
	 * @param float  $amountTtc Amount TTC
	 * @param int    $dateEvent Event timestamp
	 * @param User   $user User
	 * @param int    $entity Owner entity
	 * @return int 1 created, 0 existing or skipped, <0 error
	 */
	private function createNativeAgendaEvent($linkId, $type, $fkPropal, $amountHt, $amountTtc, $dateEvent, User $user, $entity)
	{
		global $conf, $langs;

		if (!isModEnabled('agenda')) {
			return 0;
		}

		$link = new LmdbReferralLink($this->db);
		if ($link->fetch((int) $linkId) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return -1;
		}

		$elementType = lmdbreferralGetAgendaElementType();
		$refExt = $this->buildNativeAgendaRefExt((int) $linkId, $type, (int) $fkPropal);
		$sql = 'SELECT id FROM '.MAIN_DB_PREFIX.'actioncomm';
		$sql .= " WHERE ref_ext = '".$this->db->escape($refExt)."'";
		$sql .= ' AND fk_element = '.((int) $linkId);
		$sql .= " AND elementtype = '".$this->db->escape($elementType)."'";
		$sql .= ' AND entity = '.((int) $entity);
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			return 0;
		}
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$typeCode = $this->getNativeAgendaTypeCode();
		if ($typeCode === '') {
			$this->error = 'ErrorActionCommBadType';
			return -1;
		}

		$langs->load('lmdbreferral@lmdbreferral');
		$label = $langs->transnoentitiesnoconv('LmdbReferralEvent_'.$type);
		$note = $label;
		if ($fkPropal > 0) {
			$note .= "\n".$langs->transnoentitiesnoconv('LmdbReferralSignedProposal').': '.$fkPropal;
		}
		if ((float) $amountHt != 0 || (float) $amountTtc != 0) {
			$note .= "\n".$langs->transnoentitiesnoconv('AmountHT').': '.price((float) $amountHt);
			$note .= "\n".$langs->transnoentitiesnoconv('AmountTTC').': '.price((float) $amountTtc);
		}

		$actioncomm = new ActionComm($this->db);
		$actioncomm->type_code = $typeCode;
		$actioncomm->code = $typeCode;
		$actioncomm->label = $label;
		$actioncomm->note_private = $note;
		$actioncomm->datep = (int) $dateEvent;
		$actioncomm->datef = (int) $dateEvent;
		$actioncomm->percentage = 100;
		$actioncomm->priority = 0;
		$actioncomm->fulldayevent = 0;
		$actioncomm->transparency = 0;
		$actioncomm->location = '';
		$actioncomm->socid = !empty($link->fk_soc_filleul) ? (int) $link->fk_soc_filleul : 0;
		$actioncomm->fk_project = 0;
		$actioncomm->fk_element = (int) $linkId;
		$actioncomm->elementtype = $elementType;
		$actioncomm->ref_ext = $refExt;
		$actioncomm->userownerid = !empty($user->id) ? (int) $user->id : 0;
		$actioncomm->userassigned = array();
		if (!empty($user->id)) {
			$actioncomm->userassigned[(int) $user->id] = array('id' => (int) $user->id, 'transparency' => 0);
		}

		$currentEntity = (int) $conf->entity;
		$conf->entity = (int) $entity;
		try {
			$result = $actioncomm->create($user);
		} finally {
			$conf->entity = $currentEntity;
		}

		if ($result < 0) {
			$this->error = $actioncomm->error;
			$this->errors = $actioncomm->errors;
			return -1;
		}

		return 1;
	}

	/**
	 * Return a stable external reference for native Agenda idempotence.
	 *
	 * @param int    $linkId Link id
	 * @param string $type Event type
	 * @param int    $fkPropal Proposal id
	 * @return string
	 */
	private function buildNativeAgendaRefExt($linkId, $type, $fkPropal)
	{
		return 'lmdbreferral:'.((int) $linkId).':'.(string) $type.':'.((int) $fkPropal);
	}

	/**
	 * Return the best available native Agenda action type code.
	 *
	 * @return string
	 */
	private function getNativeAgendaTypeCode()
	{
		foreach (array('AC_OTH_AUTO', 'AC_OTH') as $code) {
			$sql = 'SELECT id FROM '.MAIN_DB_PREFIX.'c_actioncomm';
			$sql .= " WHERE code = '".$this->db->escape($code)."'";
			$sql .= ' AND active = 1';
			$sql .= $this->db->plimit(1);
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * Return the owner entity of a referral link.
	 *
	 * @param int $linkId Link id
	 * @return int
	 */
	private function getLinkEntity($linkId)
	{
		$sql = 'SELECT entity FROM '.MAIN_DB_PREFIX.'lmdbreferral_link';
		$sql .= ' WHERE rowid = '.((int) $linkId);
		$sql .= ' AND entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return (int) $obj->entity;
		}

		return 0;
	}

	/**
	 * Check referrer availability.
	 *
	 * @param string $type Type
	 * @param int    $id Id
	 * @return bool
	 */
	private function isReferrerAvailable($type, $id)
	{
		if ($type === 'soc') {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe';
			$sql .= ' WHERE rowid = '.((int) $id);
			$sql .= ' AND entity IN ('.lmdbreferralGetEntitySql('societe').')';
			$sql .= ' AND status = 1';
		} else {
			$sql = 'SELECT u.rowid FROM '.MAIN_DB_PREFIX.'user as u';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbreferral_user_eligibility as e ON e.fk_user = u.rowid';
			$sql .= ' WHERE u.rowid = '.((int) $id);
			$sql .= ' AND u.statut = 1';
			$sql .= ' AND e.active = 1';
			$sql .= ' AND e.entity IN ('.lmdbreferralGetEntitySql('lmdbreferralusereligibility').')';
		}
		$resql = $this->db->query($sql);

		return ($resql && $this->db->num_rows($resql) > 0);
	}

	/**
	 * Check active pair existence.
	 *
	 * @param string $type Type
	 * @param int    $id Id
	 * @param int    $fkSocFilleul Referred thirdparty
	 * @return bool
	 */
	private function activePairExists($type, $id, $fkSocFilleul)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbreferral_link';
		$sql .= ' WHERE entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		$sql .= " AND referrer_type = '".$this->db->escape($type)."'";
		$sql .= ' AND fk_soc_filleul = '.((int) $fkSocFilleul);
		$sql .= ' AND status = '.LmdbReferralLink::STATUS_ACTIVE;
		if ($type === 'soc') {
			$sql .= ' AND fk_soc_parrain = '.((int) $id);
		} else {
			$sql .= ' AND fk_user_parrain = '.((int) $id);
		}
		$resql = $this->db->query($sql);

		return ($resql && $this->db->num_rows($resql) > 0);
	}
}
