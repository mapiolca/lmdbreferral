<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');

/**
 * Referral link business object.
 */
class LmdbReferralLink extends CommonObject
{
	public $module = 'lmdbreferral';
	public $element = 'lmdbreferrallink';
	public $table_element = 'lmdbreferral_link';
	public $picto = 'fa-handshake';
	public $ismultientitymanaged = 1;

	public $id;
	public $rowid;
	public $entity;
	public $ref;
	public $referrer_type;
	public $fk_soc_parrain;
	public $fk_user_parrain;
	public $fk_soc_filleul;
	public $status;
	public $date_creation;
	public $date_modification;
	public $date_annulation;
	public $fk_user_author;
	public $fk_user_modif;
	public $fk_user_cancel;
	public $fk_project = 0;
	public $socid = 0;
	public $note_private;
	public $referrer_label = '';
	public $filleul_label = '';
	public $entity_label = '';

	public const STATUS_ACTIVE = 1;
	public const STATUS_CANCELLED = 9;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->status = self::STATUS_ACTIVE;
	}

	/**
	 * Fetch object.
	 *
	 * @param int         $id  Row id
	 * @param string|null $ref Ref
	 * @return int
	 */
	public function fetch($id, $ref = null)
	{
		$sql = 'SELECT t.* FROM '.MAIN_DB_PREFIX.$this->table_element.' as t WHERE 1 = 1';
		if ($id > 0) {
			$sql .= ' AND t.rowid = '.((int) $id);
		} elseif ($ref !== null && $ref !== '') {
			$sql .= " AND t.ref = '".$this->db->escape($ref)."'";
		} else {
			return 0;
		}
		$sql .= ' AND t.entity IN ('.lmdbreferralGetEntitySql($this->element).')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}

		$this->setVarsFromFetchObj($obj);

		return 1;
	}

	/**
	 * Set properties from SQL object.
	 *
	 * @param object $obj SQL object
	 * @return void
	 */
	public function setVarsFromFetchObj(&$obj)
	{
		foreach ($obj as $key => $value) {
			$this->$key = $value;
		}
		$this->id = (int) $obj->rowid;
		$this->rowid = (int) $obj->rowid;
		$this->fk_project = 0;
		$this->socid = !empty($this->fk_soc_filleul) ? (int) $this->fk_soc_filleul : 0;
	}

	/**
	 * Create referral link.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 disables triggers
	 * @return int
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		$error = 0;
		$now = dol_now();
		$this->entity = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;
		$this->status = !empty($this->status) ? (int) $this->status : self::STATUS_ACTIVE;
		$this->date_creation = !empty($this->date_creation) ? $this->date_creation : $now;
		$this->fk_user_author = !empty($this->fk_user_author) ? (int) $this->fk_user_author : (int) $user->id;

		$this->db->begin();

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
		$sql .= 'entity, ref, referrer_type, fk_soc_parrain, fk_user_parrain, fk_soc_filleul, status, date_creation, fk_user_author, note_private';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).', ';
		$sql .= ($this->ref ? "'".$this->db->escape($this->ref)."'" : 'NULL').', ';
		$sql .= "'".$this->db->escape($this->referrer_type)."', ";
		$sql .= ($this->fk_soc_parrain > 0 ? ((int) $this->fk_soc_parrain) : 'NULL').', ';
		$sql .= ($this->fk_user_parrain > 0 ? ((int) $this->fk_user_parrain) : 'NULL').', ';
		$sql .= ((int) $this->fk_soc_filleul).', ';
		$sql .= ((int) $this->status).', ';
		$sql .= "'".$this->db->idate($this->date_creation)."', ";
		$sql .= ((int) $this->fk_user_author).', ';
		$sql .= ($this->note_private ? "'".$this->db->escape($this->note_private)."'" : 'NULL');
		$sql .= ')';

		if (!$this->db->query($sql)) {
			$error++;
			$this->error = $this->db->lasterror();
		} else {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
			$this->rowid = $this->id;
			if (empty($this->ref)) {
				$this->ref = 'REFERRAL-'.$this->id;
				$this->db->query("UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ref = '".$this->db->escape($this->ref)."' WHERE rowid = ".((int) $this->id));
			}
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('LMDBREFERRAL_LINK_CREATE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			$this->db->commit();
			return $this->id;
		}

		$this->db->rollback();
		return -1;
	}

	/**
	 * Update referral link.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 disables triggers
	 * @return int
	 */
	public function update(User $user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();
		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET';
		$sql .= " referrer_type = '".$this->db->escape($this->referrer_type)."'";
		$sql .= ', fk_soc_parrain = '.($this->fk_soc_parrain > 0 ? ((int) $this->fk_soc_parrain) : 'NULL');
		$sql .= ', fk_user_parrain = '.($this->fk_user_parrain > 0 ? ((int) $this->fk_user_parrain) : 'NULL');
		$sql .= ', status = '.((int) $this->status);
		$sql .= ", date_modification = '".$this->db->idate(dol_now())."'";
		$sql .= ', fk_user_modif = '.((int) $user->id);
		$sql .= ', note_private = '.($this->note_private ? "'".$this->db->escape($this->note_private)."'" : 'NULL');
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity IN ('.lmdbreferralGetEntitySql($this->element).')';

		if (!$this->db->query($sql)) {
			$error++;
			$this->error = $this->db->lasterror();
		}
		if (!$error && !$notrigger) {
			$result = $this->call_trigger('LMDBREFERRAL_LINK_UPDATE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		}

		$this->db->rollback();
		return -1;
	}

	/**
	 * Functionally cancel link.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 disables triggers
	 * @return int
	 */
	public function cancel(User $user, $notrigger = 0)
	{
		$this->status = self::STATUS_CANCELLED;
		$this->date_annulation = dol_now();
		$this->fk_user_cancel = (int) $user->id;

		$error = 0;
		$this->db->begin();
		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET';
		$sql .= ' status = '.self::STATUS_CANCELLED;
		$sql .= ", date_annulation = '".$this->db->idate($this->date_annulation)."'";
		$sql .= ", date_modification = '".$this->db->idate($this->date_annulation)."'";
		$sql .= ', fk_user_cancel = '.((int) $user->id);
		$sql .= ', fk_user_modif = '.((int) $user->id);
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity IN ('.lmdbreferralGetEntitySql($this->element).')';
		if (!$this->db->query($sql)) {
			$error++;
			$this->error = $this->db->lasterror();
		}
		if (!$error && !$notrigger) {
			$result = $this->call_trigger('LMDBREFERRAL_LINK_CANCEL', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();

		return -1;
	}

	/**
	 * Get object URL.
	 *
	 * @param int $withpicto Picto mode
	 * @return string
	 */
	public function getNomUrl($withpicto = 0)
	{
		global $langs;

		$langs->loadLangs(array('lmdbreferral@lmdbreferral'));

		$label = $this->ref ? $this->ref : 'Referral';
		$url = dol_buildpath('/lmdbreferral/card.php', 1).'?id='.(int) $this->id;
		$tooltip = '<b>'.dol_escape_htmltag($langs->trans('LmdbReferralLink')).'</b>';
		$tooltip .= '<br><b>'.dol_escape_htmltag($langs->trans('Ref')).':</b> '.dol_escape_htmltag($label);
		if ($this->referrer_label !== '') {
			$tooltip .= '<br><b>'.dol_escape_htmltag($langs->trans('LmdbReferralReferrer')).':</b> '.dol_escape_htmltag($this->referrer_label);
		}
		if ($this->filleul_label !== '') {
			$tooltip .= '<br><b>'.dol_escape_htmltag($langs->trans('LmdbReferralReferredThirdparty')).':</b> '.dol_escape_htmltag($this->filleul_label);
		}
		if (!empty($this->date_creation)) {
			$tooltip .= '<br><b>'.dol_escape_htmltag($langs->trans('LmdbReferralAttachedDate')).':</b> '.dol_escape_htmltag(dol_print_date(is_numeric($this->date_creation) ? (int) $this->date_creation : $this->db->jdate($this->date_creation), 'dayhour'));
		}
		if (!empty($this->status)) {
			$tooltip .= '<br><b>'.dol_escape_htmltag($langs->trans('Status')).':</b> '.dol_escape_htmltag($this->getLibStatut(0));
		}
		if ($this->entity_label !== '' || !empty($this->entity)) {
			$tooltip .= '<br><b>'.dol_escape_htmltag($langs->trans('Environment')).':</b> '.dol_escape_htmltag($this->entity_label !== '' ? $this->entity_label : (string) $this->entity);
		}

		$link = '<a href="'.dol_escape_htmltag($url).'" class="classfortooltip" title="'.dolPrintHTMLForAttribute($tooltip).'">';
		if ($withpicto) {
			$link .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		$link .= dol_escape_htmltag($label).'</a>';

		return $link;
	}

	/**
	 * @param int $mode Mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return self::LibStatut($this->status, $mode);
	}

	/**
	 * @param int $status Status
	 * @param int $mode   Mode
	 * @return string
	 */
	public static function LibStatut($status, $mode = 0)
	{
		if ($mode > 0) {
			return lmdbreferralStatusBadge($status);
		}

		global $langs;
		return $langs->trans(lmdbreferralStatusLabel($status));
	}
}
