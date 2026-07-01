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
	public $model_pdf;
	public $last_main_doc;
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
		if (empty($this->ref)) {
			$this->ref = $this->getNextNumRef();
			if ($this->ref === '') {
				$this->error = 'ErrorNumberingModuleNotSetup';
				return -1;
			}
		}
		if (empty($this->model_pdf)) {
			$this->model_pdf = getDolGlobalString('LMDBREFERRAL_LINK_ADDON_PDF', 'standard_lmdbreferrallink');
		}

		$this->db->begin();

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
		$sql .= 'entity, ref, referrer_type, fk_soc_parrain, fk_user_parrain, fk_soc_filleul, status, date_creation, fk_user_author, note_private, model_pdf, last_main_doc';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).', ';
		$sql .= "'".$this->db->escape($this->ref)."', ";
		$sql .= "'".$this->db->escape($this->referrer_type)."', ";
		$sql .= ($this->fk_soc_parrain > 0 ? ((int) $this->fk_soc_parrain) : 'NULL').', ';
		$sql .= ($this->fk_user_parrain > 0 ? ((int) $this->fk_user_parrain) : 'NULL').', ';
		$sql .= ((int) $this->fk_soc_filleul).', ';
		$sql .= ((int) $this->status).', ';
		$sql .= "'".$this->db->idate($this->date_creation)."', ";
		$sql .= ((int) $this->fk_user_author).', ';
		$sql .= ($this->note_private ? "'".$this->db->escape($this->note_private)."'" : 'NULL').', ';
		$sql .= ($this->model_pdf ? "'".$this->db->escape($this->model_pdf)."'" : 'NULL').', ';
		$sql .= ($this->last_main_doc ? "'".$this->db->escape($this->last_main_doc)."'" : 'NULL');
		$sql .= ')';

		if (!$this->db->query($sql)) {
			$error++;
			$this->error = $this->db->lasterror();
		} else {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
			$this->rowid = $this->id;
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
	 * Delete referral link and its internal/native linked data.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 disables triggers
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$error = 0;
		$elementType = $this->getElementType();
		$agendaElementType = lmdbreferralGetAgendaElementType();

		$this->db->begin();

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'actioncomm';
		$sql .= ' WHERE fk_element = '.((int) $this->id);
		$sql .= " AND elementtype IN ('".$this->db->escape($elementType)."', '".$this->db->escape($agendaElementType)."')";
		if (!$this->db->query($sql)) {
			$error++;
			$this->error = $this->db->lasterror();
		}

		if (!$error) {
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'element_element';
			$sql .= " WHERE ((fk_source = ".((int) $this->id)." AND sourcetype = '".$this->db->escape($elementType)."')";
			$sql .= " OR (fk_target = ".((int) $this->id)." AND targettype = '".$this->db->escape($elementType)."'))";
			if (!$this->db->query($sql)) {
				$error++;
				$this->error = $this->db->lasterror();
			}
		}

		if (!$error) {
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbreferral_event';
			$sql .= ' WHERE fk_lmdbreferral_link = '.((int) $this->id);
			$sql .= ' AND entity IN ('.lmdbreferralGetEntitySql('lmdbreferralevent').')';
			if (!$this->db->query($sql)) {
				$error++;
				$this->error = $this->db->lasterror();
			}
		}

		if (!$error) {
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$this->table_element;
			$sql .= ' WHERE rowid = '.((int) $this->id);
			$sql .= ' AND entity IN ('.lmdbreferralGetEntitySql($this->element).')';
			if (!$this->db->query($sql)) {
				$error++;
				$this->error = $this->db->lasterror();
			}
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('LMDBREFERRAL_LINK_DELETE', $user);
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

	/**
	 * Initialize object with specimen values.
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		global $conf;

		$this->id = 0;
		$this->rowid = 0;
		$this->entity = !empty($conf->entity) ? (int) $conf->entity : 1;
		$this->ref = 'SPECIMEN';
		$this->referrer_type = 'soc';
		$this->fk_soc_parrain = 0;
		$this->fk_user_parrain = 0;
		$this->fk_soc_filleul = 0;
		$this->status = self::STATUS_ACTIVE;
		$this->date_creation = dol_now();
		$this->note_private = '';
		$this->model_pdf = getDolGlobalString('LMDBREFERRAL_LINK_ADDON_PDF', 'standard_lmdbreferrallink');
	}

	/**
	 * Return next reference from configured numbering model.
	 *
	 * @return string
	 */
	public function getNextNumRef()
	{
		global $langs;

		$langs->load('lmdbreferral@lmdbreferral');

		$module = getDolGlobalString('LMDBREFERRAL_LINK_ADDON', 'mod_lmdbreferrallink_standard');
		if (substr($module, -4) === '.php') {
			$module = substr($module, 0, -4);
		}
		if (!preg_match('/^mod_lmdbreferrallink_[a-z0-9_]+$/', $module)) {
			$module = 'mod_lmdbreferrallink_standard';
		}

		$modules = array($module);
		if ($module !== 'mod_lmdbreferrallink_standard') {
			$modules[] = 'mod_lmdbreferrallink_standard';
		}

		foreach ($modules as $moduleToLoad) {
			$file = dol_buildpath('/lmdbreferral/core/modules/lmdbreferral/'.$moduleToLoad.'.php');
			if (!is_readable($file)) {
				continue;
			}
			require_once $file;
			if (!class_exists($moduleToLoad)) {
				continue;
			}
			/** @var ModeleNumRefLmdbReferralLink $obj */
			$obj = new $moduleToLoad();
			$numref = $obj->getNextValue($this);
			if ($numref !== '' && $numref !== '-1' && $numref !== 0 && $numref !== -1) {
				return (string) $numref;
			}
			$this->error = !empty($obj->error) ? $obj->error : 'ErrorNumberingModuleNotSetup';
		}

		return '';
	}

	/**
	 * Generate a document for the referral link.
	 *
	 * @param string          $modele Model name
	 * @param Translate|null  $outputlangs Output language
	 * @param int             $hidedetails Hide details
	 * @param int             $hidedesc Hide description
	 * @param int             $hideref Hide reference
	 * @param array<string,mixed>|null $moreparams More parameters
	 * @return int
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		if (empty($modele)) {
			$modele = !empty($this->model_pdf) ? $this->model_pdf : getDolGlobalString('LMDBREFERRAL_LINK_ADDON_PDF', 'standard_lmdbreferrallink');
		}

		return $this->commonGenerateDocument('lmdbreferral/core/modules/lmdbreferral/doc/', $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
	}
}
