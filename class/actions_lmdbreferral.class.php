<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferralservice.class.php');

/**
 * Hooks for lmdbreferral.
 */
class ActionsLmdbReferral
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @var array<string,mixed> */
	public $results = array();

	/** @var string */
	public $resprints = '';

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
	 * Multicompany sharing definition.
	 *
	 * @return array<string,mixed>
	 */
	public static function getMulticompanySharingDefinition()
	{
		return array(
			'lmdbreferral' => array(
				'sharingelements' => array(
					'lmdbreferrallink' => array(
						'type' => 'element',
						'icon' => 'handshake',
						'lang' => 'lmdbreferral@lmdbreferral',
						'tooltip' => 'LmdbReferralLinkSharingInfo',
						'enable' => '! empty($conf->lmdbreferral->enabled)',
						'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
					),
					'lmdbreferralevent' => array(
						'type' => 'element',
						'icon' => 'history',
						'lang' => 'lmdbreferral@lmdbreferral',
						'tooltip' => 'LmdbReferralEventSharingInfo',
						'enable' => '! empty($conf->lmdbreferral->enabled)',
						'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
					),
					'lmdbreferralusereligibility' => array(
						'type' => 'element',
						'icon' => 'user-check',
						'lang' => 'lmdbreferral@lmdbreferral',
						'tooltip' => 'LmdbReferralUserEligibilitySharingInfo',
						'enable' => '! empty($conf->lmdbreferral->enabled)',
						'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
					),
				),
				'sharingmodulename' => array(
					'lmdbreferrallink' => 'lmdbreferral',
					'lmdbreferralevent' => 'lmdbreferral',
					'lmdbreferralusereligibility' => 'lmdbreferral',
				),
			),
		);
	}

	/**
	 * @param array<string,mixed> $parameters Parameters
	 * @param object             $object Object
	 * @param string             $action Action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function multicompanyExternalModulesSharing($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}

	/**
	 * @param array<string,mixed> $parameters Parameters
	 * @param object             $object Object
	 * @param string             $action Action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function multicompanyExternalModuleSharing($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}

	/**
	 * @param array<string,mixed> $parameters Parameters
	 * @param object             $object Object
	 * @param string             $action Action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function multicompanySharingOptions($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}

	/**
	 * Process custom thirdparty card actions.
	 *
	 * @param array<string,mixed> $parameters Parameters
	 * @param object             $object Thirdparty
	 * @param string             $action Action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		$contexts = explode(':', isset($parameters['context']) ? $parameters['context'] : '');
		if (!in_array('thirdpartycard', $contexts, true)) {
			return 0;
		}
		if ($action !== 'lmdbreferral_cancel_referrer') {
			return 0;
		}
		if (!lmdbreferralCanDo($user, 'cancel', $object)) {
			accessforbidden();
		}
		lmdbreferralCheckToken();

		$socid = !empty($object->id) ? (int) $object->id : GETPOSTINT('socid');
		$service = new LmdbReferralService($this->db);
		$result = $service->replaceFromValue('', $socid, $user);
		if ($result < 0) {
			setEventMessages($langs->trans($service->error), $service->errors, 'errors');
			return -1;
		}
		setEventMessages($langs->trans('LmdbReferralReferrerRemoved'), null, 'mesgs');
		header('Location: '.DOL_URL_ROOT.'/societe/card.php?socid='.$socid);
		exit;
	}

	/**
	 * Add referrer selector to thirdparty creation.
	 *
	 * @param array<string,mixed> $parameters Parameters
	 * @param object             $object Thirdparty
	 * @param string             $action Action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function tabContentCreateThirdparty($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (!lmdbreferralCanDo($user, 'write', $object)) {
			return 0;
		}
		$langs->load('lmdbreferral@lmdbreferral');
		$selected = GETPOST('lmdbreferral_referrer', 'alphanohtml');
		$this->printThirdpartyFormReferrerField($selected, 0, false);

		return 0;
	}

	/**
	 * Add referrer selector to thirdparty edition.
	 *
	 * @param array<string,mixed> $parameters Parameters
	 * @param object             $object Thirdparty
	 * @param string             $action Action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function tabContentEditThirdparty($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (empty($object->id) || !lmdbreferralCanDo($user, 'write', $object)) {
			return 0;
		}
		$langs->load('lmdbreferral@lmdbreferral');
		$selected = $this->getActiveReferrerValue((int) $object->id);
		if (GETPOSTISSET('lmdbreferral_referrer')) {
			$selected = GETPOST('lmdbreferral_referrer', 'alphanohtml');
		}
		$locked = (new LmdbReferralService($this->db))->isLockedBySignedProposal((int) $object->id);
		$this->printThirdpartyFormReferrerField($selected, (int) $object->id, $locked);

		return 0;
	}

	/**
	 * Add a compact referrer block on thirdparty view.
	 *
	 * @param array<string,mixed> $parameters Parameters
	 * @param object             $object Thirdparty
	 * @param string             $action Action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function tabContentViewThirdparty($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (empty($object->id) || !getDolGlobalInt('LMDBREFERRAL_SHOW_THIRDPARTY_BLOCK', 1) || !lmdbreferralCanDo($user, 'read', $object)) {
			return 0;
		}
		$langs->load('lmdbreferral@lmdbreferral');
		$links = (new LmdbReferralService($this->db))->fetchActiveByFilleul((int) $object->id);
		if (empty($links)) {
			return 0;
		}

		$link = $links[0];
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border tableforfield centpercent">';
		print '<tr><td class="titlefieldmiddle">'.$langs->trans('LmdbReferralReferrer').'</td><td>';
		print lmdbreferralGetReferrerNomUrl($link->referrer_type, $link->referrer_type === 'soc' ? (int) $link->fk_soc_parrain : (int) $link->fk_user_parrain);
		if (lmdbreferralCanDo($user, 'write', $object)) {
			print ' &nbsp; <a class="editfielda" href="'.DOL_URL_ROOT.'/societe/card.php?socid='.(int) $object->id.'&action=edit&token='.newToken().'">'.img_edit($langs->trans('Modify'), 1).'</a>';
		}
		if (lmdbreferralCanDo($user, 'cancel', $object) && !(new LmdbReferralService($this->db))->isLockedBySignedProposal((int) $object->id)) {
			print ' &nbsp; <a class="reposition" href="'.DOL_URL_ROOT.'/societe/card.php?socid='.(int) $object->id.'&action=lmdbreferral_cancel_referrer&token='.newToken().'">'.img_delete($langs->trans('LmdbReferralRemoveReferrer')).'</a>';
		}
		print '</td></tr>';
		print '</table>';

		return 0;
	}

	/**
	 * Get active referrer typed value.
	 *
	 * @param int $socid Thirdparty id
	 * @return string
	 */
	private function getActiveReferrerValue($socid)
	{
		$links = (new LmdbReferralService($this->db))->fetchActiveByFilleul($socid);
		if (empty($links)) {
			return '';
		}
		$link = $links[0];
		if ($link->referrer_type === 'soc') {
			return 'soc:'.((int) $link->fk_soc_parrain);
		}
		if ($link->referrer_type === 'user') {
			return 'user:'.((int) $link->fk_user_parrain);
		}

		return '';
	}

	/**
	 * Print the referrer field and move it into the native thirdparty form table.
	 *
	 * @param string $selected Selected typed referrer
	 * @param int    $excludeSocid Thirdparty to exclude from the selector
	 * @param bool   $locked True when the field must be displayed read-only
	 * @return void
	 */
	private function printThirdpartyFormReferrerField($selected, $excludeSocid, $locked)
	{
		global $langs;

		$suffix = $excludeSocid > 0 ? 'edit' : 'create';
		$wrapperId = 'lmdbreferral-referrer-wrapper-'.$suffix;
		$rowId = 'lmdbreferral-referrer-row-'.$suffix;

		print '<div id="'.$wrapperId.'" class="lmdbreferral-referrer-wrapper">';
		print '<table class="border centpercent">';
		print '<tr id="'.$rowId.'" class="lmdbreferral-referrer-row">';
		print '<td class="titlefieldcreate">'.$langs->trans('LmdbReferralReferrer').'</td><td colspan="3">';
		if ($locked) {
			print '<span class="opacitymedium">'.$langs->trans('LmdbReferralLockedAfterSignedProposal').'</span>';
		} else {
			print lmdbreferralSelectReferrer('lmdbreferral_referrer', $selected, $excludeSocid);
		}
		print '</td></tr>';
		print '</table>';
		print '</div>';

		print '<script>
		jQuery(function($) {
			var $wrapper = $("#'.dol_escape_js($wrapperId).'");
			var $row = $("#'.dol_escape_js($rowId).'");
			if (!$wrapper.length || !$row.length) {
				return;
			}

			var $form = $row.closest("form");
			if (!$form.length) {
				$form = $("form[name=formsoc]").first();
			}
			if (!$form.length) {
				return;
			}

			var $target = $();
			$form.find("select[name=entity], input[name=entity]:not([type=hidden]), #entity, #selectentity").each(function() {
				var $tr = $(this).closest("tr");
				if ($tr.length) {
					$target = $tr;
					return false;
				}
			});

			if (!$target.length) {
				var entityLabels = ["'.dol_escape_js($langs->trans('Environment')).'", "'.dol_escape_js($langs->trans('Entity')).'"];
				$form.find("tr").each(function() {
					var label = $.trim($(this).children("td, th").first().text()).replace(/[:\\s]+$/, "");
					for (var i = 0; i < entityLabels.length; i++) {
						if (label && entityLabels[i] && label.toLowerCase() === entityLabels[i].toLowerCase()) {
							$target = $(this);
							return false;
						}
					}
				});
			}

			if ($target.length) {
				$target.after($row.detach());
				$wrapper.remove();
				return;
			}

			var $nameRow = $form.find("#name").first().closest("tr");
			if ($nameRow.length) {
				$nameRow.before($row.detach());
				$wrapper.remove();
			}
		});
		</script>';
	}
}
