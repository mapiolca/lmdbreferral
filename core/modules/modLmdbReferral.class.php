<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor for lmdbreferral.
 */
class modLmdbReferral extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;
		$this->numero = 450023;
		$this->rights_class = 'lmdbreferral';
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = '92';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ModuleLmdbReferralDesc';
		$this->descriptionlong = 'ModuleLmdbReferralDescLong';
		$this->editor_name = 'Les Métiers du Bâtiment';
		$this->editor_url = 'lesmetiersdubatiment.fr';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-handshake';

		$this->module_parts = array(
			'api' => 1,
			'triggers' => 1,
			'substitutions' => 0,
			'models' => 0,
			'hooks' => array(
				'data' => array(
					'thirdpartycard',
					'usercard',
					'globalcard',
					'multicompanyexternalmodulesharing',
					'multicompanyexternalmodules',
					'multicompanysharingoptions',
				),
				'entity' => '0',
			),
		);

		$this->dirs = array('/lmdbreferral/temp');
		$this->config_page_url = array('setup.php@lmdbreferral');
		$this->hidden = false;
		$this->depends = array('always' => array('modSociete', 'modPropale'));
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('lmdbreferral@lmdbreferral');
		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);
		$this->need_javascript_ajax = 0;

		$this->const = array(
			array('LMDBREFERRAL_ENABLE_THIRDPARTY_REFERRERS', 'chaine', '1', 'Enable thirdparty referrers', 0, 'current', 0),
			array('LMDBREFERRAL_ALLOW_USER_REFERRERS', 'chaine', '0', 'Enable user referrers', 0, 'current', 0),
			array('LMDBREFERRAL_ONE_ACTIVE_REFERRER', 'chaine', '1', 'Allow one active referrer per referred thirdparty', 0, 'current', 0),
			array('LMDBREFERRAL_PREVENT_DUPLICATE_PAIR', 'chaine', '1', 'Prevent duplicate active referrer/referred pairs', 0, 'current', 0),
			array('LMDBREFERRAL_LOCK_REFERRER_AFTER_SIGNED_PROPAL', 'chaine', '1', 'Lock referrer after signed proposal', 0, 'current', 0),
			array('LMDBREFERRAL_SHOW_THIRDPARTY_BLOCK', 'chaine', '1', 'Show referral block on thirdparty card', 0, 'current', 0),
			array('LMDBREFERRAL_SHOW_THIRDPARTY_TAB', 'chaine', '1', 'Show thirdparty referrals tab', 0, 'current', 0),
			array('LMDBREFERRAL_SHOW_USER_TAB', 'chaine', '1', 'Show user referrals tab', 0, 'current', 0),
			array('LMDBREFERRAL_STATS_INCLUDE_CANCELLED_OR_REFUSED_PROPALS', 'chaine', '0', 'Include cancelled/refused proposals in statistics', 0, 'current', 0),
			array('LMDBREFERRAL_AMOUNT_REFERENCE', 'chaine', 'HT', 'Reference amount for statistics', 0, 'current', 0),
		);

		$this->tabs = array();
		$this->tabs[] = array(
			'data' => 'thirdparty:+lmdbreferral_filleuls:LmdbReferralTabReferrals:lmdbreferral@lmdbreferral:($user->admin || $user->hasRight(\'lmdbreferral\', \'referral\', \'read\')) && getDolGlobalInt(\'LMDBREFERRAL_SHOW_THIRDPARTY_TAB\', 1):/lmdbreferral/tabs/soc_filleuls.php?id=__ID__',
		);
		$this->tabs[] = array(
			'data' => 'user:+lmdbreferral_filleuls:LmdbReferralTabReferrals:lmdbreferral@lmdbreferral:($user->admin || $user->hasRight(\'lmdbreferral\', \'referral\', \'read\') || $user->hasRight(\'lmdbreferral\', \'referral\', \'own\')) && getDolGlobalInt(\'LMDBREFERRAL_ALLOW_USER_REFERRERS\') && getDolGlobalInt(\'LMDBREFERRAL_SHOW_USER_TAB\', 1):/lmdbreferral/tabs/user_filleuls.php?id=__ID__',
		);

		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero + 1;
		$this->rights[$r][1] = 'Read referrals';
		$this->rights[$r][4] = 'referral';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero + 2;
		$this->rights[$r][1] = 'Create/modify referrals';
		$this->rights[$r][4] = 'referral';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero + 3;
		$this->rights[$r][1] = 'Cancel referrals';
		$this->rights[$r][4] = 'referral';
		$this->rights[$r][5] = 'cancel';
		$r++;
		$this->rights[$r][0] = $this->numero + 4;
		$this->rights[$r][1] = 'View all referrals';
		$this->rights[$r][4] = 'referral';
		$this->rights[$r][5] = 'all';
		$r++;
		$this->rights[$r][0] = $this->numero + 5;
		$this->rights[$r][1] = 'View own referred thirdparties';
		$this->rights[$r][4] = 'referral';
		$this->rights[$r][5] = 'own';
		$r++;
		$this->rights[$r][0] = $this->numero + 6;
		$this->rights[$r][1] = 'Export referrals';
		$this->rights[$r][4] = 'referral';
		$this->rights[$r][5] = 'export';
		$r++;
		$this->rights[$r][0] = $this->numero + 7;
		$this->rights[$r][1] = 'Use referral API';
		$this->rights[$r][4] = 'referral';
		$this->rights[$r][5] = 'api';
		$r++;
		$this->rights[$r][0] = $this->numero + 8;
		$this->rights[$r][1] = 'Configure referral module';
		$this->rights[$r][4] = 'referral';
		$this->rights[$r][5] = 'setup';
		$r++;

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=companies',
			'type' => 'left',
			'titre' => 'LmdbReferralMenu',
			'prefix' => img_picto('', 'fa-handshake', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'companies',
			'leftmenu' => 'lmdbreferral',
			'url' => '/lmdbreferral/index.php',
			'langs' => 'lmdbreferral@lmdbreferral',
			'position' => 1050,
			'enabled' => 'isModEnabled("lmdbreferral")',
			'perms' => '$user->admin || $user->hasRight("lmdbreferral", "referral", "read") || $user->hasRight("lmdbreferral", "referral", "own")',
			'target' => '',
			'user' => 2,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=companies,fk_leftmenu=lmdbreferral',
			'type' => 'left',
			'titre' => 'LmdbReferralOverview',
			'mainmenu' => 'companies',
			'leftmenu' => 'lmdbreferral_overview',
			'url' => '/lmdbreferral/index.php',
			'langs' => 'lmdbreferral@lmdbreferral',
			'position' => 1051,
			'enabled' => 'isModEnabled("lmdbreferral")',
			'perms' => '$user->admin || $user->hasRight("lmdbreferral", "referral", "read") || $user->hasRight("lmdbreferral", "referral", "own")',
			'target' => '',
			'user' => 2,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=companies,fk_leftmenu=lmdbreferral',
			'type' => 'left',
			'titre' => 'LmdbReferralList',
			'mainmenu' => 'companies',
				'leftmenu' => 'lmdbreferral_list',
				'url' => '/lmdbreferral/list.php',
				'langs' => 'lmdbreferral@lmdbreferral',
				'position' => 1052,
				'enabled' => 'isModEnabled("lmdbreferral")',
				'perms' => '$user->admin || $user->hasRight("lmdbreferral", "referral", "read") || $user->hasRight("lmdbreferral", "referral", "own")',
				'target' => '',
				'user' => 2,
			);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=companies,fk_leftmenu=lmdbreferral',
			'type' => 'left',
			'titre' => 'Settings',
			'mainmenu' => 'companies',
			'leftmenu' => 'lmdbreferral_setup',
			'url' => '/lmdbreferral/admin/setup.php',
			'langs' => 'lmdbreferral@lmdbreferral',
			'position' => 1053,
			'enabled' => 'isModEnabled("lmdbreferral")',
			'perms' => '$user->admin || $user->hasRight("lmdbreferral", "referral", "setup")',
			'target' => '',
			'user' => 2,
		);

		$r = 0;
		$this->export_code[$r] = $this->rights_class.'_'.$r;
		$this->export_label[$r] = 'LmdbReferralExportReferrals';
		$this->export_icon[$r] = $this->picto;
		$this->export_permission[$r] = array(array('lmdbreferral', 'referral', 'export'));
		$this->export_fields_array[$r] = array(
			't.referrer_type' => 'LmdbReferralReferrerType',
			'par.nom' => 'LmdbReferralThirdpartyReferrer',
			'up.login' => 'LmdbReferralUserReferrer',
			'filleul.nom' => 'LmdbReferralReferredThirdparty',
			't.date_creation' => 'LmdbReferralAttachedDate',
			'p.ref' => 'LmdbReferralSignedProposal',
			'e.date_event' => 'LmdbReferralSignatureDate',
			'e.amount_ht' => 'LmdbReferralSignedAmountHT',
			'e.amount_ttc' => 'LmdbReferralSignedAmountTTC',
			't.status' => 'Status',
			't.entity' => 'Entity',
		);
		$this->export_TypeFields_array[$r] = array(
			't.referrer_type' => 'Text',
			'par.nom' => 'Text',
			'up.login' => 'Text',
			'filleul.nom' => 'Text',
			't.date_creation' => 'Date',
			'p.ref' => 'Text',
			'e.date_event' => 'Date',
			'e.amount_ht' => 'Numeric',
			'e.amount_ttc' => 'Numeric',
			't.status' => 'Numeric',
			't.entity' => 'Numeric',
		);
		$this->export_sql_start[$r] = 'SELECT DISTINCT ';
		$this->export_sql_end[$r] = ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as t';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as par ON par.rowid = t.fk_soc_parrain';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as up ON up.rowid = t.fk_user_parrain';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as filleul ON filleul.rowid = t.fk_soc_filleul';
		$this->export_sql_end[$r] .= " LEFT JOIN ".MAIN_DB_PREFIX."lmdbreferral_event as e ON e.fk_lmdbreferral_link = t.rowid AND e.event_type = 'propal_signed'";
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'propal as p ON p.rowid = e.fk_propal';
		$this->export_sql_end[$r] .= ' WHERE t.entity IN ('.getEntity('lmdbreferrallink').')';
		$r++;
	}

	/**
	 * Module initialization.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/lmdbreferral/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->syncMulticompanySharing(1);

		return $this->_init(array(), $options);
	}

	/**
	 * Module removal.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		$savedConstants = $this->fetchModuleConstants();
		$this->syncMulticompanySharing(0);
		$result = $this->_remove(array(), $options);
		if ($result > 0) {
			$this->restoreModuleConstants($savedConstants);
		}

		return $result;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function fetchModuleConstants()
	{
		$constants = array();
		$sql = 'SELECT name, value, type, visible, note, entity FROM '.MAIN_DB_PREFIX.'const';
		$sql .= " WHERE name LIKE 'LMDBREFERRAL\\_%'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $constants;
		}
		while ($row = $this->db->fetch_object($resql)) {
			$constants[] = array(
				'name' => $row->name,
				'value' => $row->value,
				'type' => $row->type,
				'visible' => (int) $row->visible,
				'note' => $row->note,
				'entity' => (int) $row->entity,
			);
		}

		return $constants;
	}

	/**
	 * @param array<int,array<string,mixed>> $constants Constants
	 * @return void
	 */
	private function restoreModuleConstants($constants)
	{
		if (!function_exists('dolibarr_set_const')) {
			return;
		}
		foreach ($constants as $constant) {
			dolibarr_set_const(
				$this->db,
				$constant['name'],
				isset($constant['value']) ? $constant['value'] : '',
				empty($constant['type']) ? 'chaine' : $constant['type'],
				isset($constant['visible']) ? (int) $constant['visible'] : 0,
				isset($constant['note']) ? $constant['note'] : '',
				isset($constant['entity']) ? (int) $constant['entity'] : 1
			);
		}
	}

	/**
	 * Synchronize Multicompany sharing payload.
	 *
	 * @param int $enable 1=merge, 0=remove
	 * @return void
	 */
	private function syncMulticompanySharing($enable)
	{
		global $conf;

		dol_include_once('/lmdbreferral/class/actions_lmdbreferral.class.php');
		if (!class_exists('ActionsLmdbReferral') || !function_exists('dolibarr_set_const')) {
			return;
		}

		$current = array();
		if (!empty($conf->global->MULTICOMPANY_EXTERNAL_MODULES_SHARING)) {
			$decoded = json_decode($conf->global->MULTICOMPANY_EXTERNAL_MODULES_SHARING, true);
			if (is_array($decoded)) {
				$current = $decoded;
			}
		}
		if ($enable) {
			$current = array_replace_recursive($current, ActionsLmdbReferral::getMulticompanySharingDefinition());
		} else {
			unset($current['lmdbreferral']);
		}

		dolibarr_set_const($this->db, 'MULTICOMPANY_EXTERNAL_MODULES_SHARING', json_encode($current), 'chaine', 0, '', (int) $conf->entity);
	}
}
