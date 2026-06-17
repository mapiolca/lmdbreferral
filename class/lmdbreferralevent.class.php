<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Referral event object.
 */
class LmdbReferralEvent extends CommonObject
{
	public $module = 'lmdbreferral';
	public $element = 'lmdbreferralevent';
	public $table_element = 'lmdbreferral_event';
	public $picto = 'fa-history';
	public $ismultientitymanaged = 1;

	public $id;
	public $entity;
	public $fk_lmdbreferral_link;
	public $event_type;
	public $fk_propal;
	public $amount_ht;
	public $amount_ttc;
	public $date_event;
	public $fk_user_author;
	public $note_private;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}
}
