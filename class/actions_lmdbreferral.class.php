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
	 * Expose the referral link object to native Dolibarr element resolution.
	 *
	 * @param array<string,mixed> $parameters Parameters
	 * @param object             $object Object
	 * @param string             $action Action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		$elementType = isset($parameters['elementType']) ? (string) $parameters['elementType'] : '';
		if (!in_array($elementType, array('lmdbreferrallink', 'lmdbreferrallink@lmdbreferral', 'lmdbreferral_lmdbreferrallink'), true)) {
			return 0;
		}

		$dirOutput = '';
		if (!empty($conf->lmdbreferral->multidir_output[$conf->entity])) {
			$dirOutput = (string) $conf->lmdbreferral->multidir_output[$conf->entity];
		} elseif (!empty($conf->lmdbreferral->dir_output)) {
			$dirOutput = (string) $conf->lmdbreferral->dir_output;
		}

		$this->results = array(
			'module' => 'lmdbreferral',
			'element' => 'lmdbreferrallink',
			'table_element' => 'lmdbreferral_link',
			'subelement' => 'lmdbreferrallink',
			'classpath' => 'lmdbreferral/class',
			'classfile' => 'lmdbreferrallink',
			'classname' => 'LmdbReferralLink',
			'dir_output' => $dirOutput,
		);

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
		$socid = !empty($object->id) ? (int) $object->id : GETPOSTINT('socid');
		$service = new LmdbReferralService($this->db);

		if ($action === 'lmdbreferral_save_referrer') {
			if (!lmdbreferralCanDo($user, 'write', $object)) {
				accessforbidden();
			}
			lmdbreferralCheckToken();

			$value = GETPOST('lmdbreferral_referrer', 'alphanohtml');
			$result = $service->replaceFromValue($value, $socid, $user);
			if ($result < 0) {
				setEventMessages($langs->trans($service->error), $service->errors, 'errors');
				$action = 'lmdbreferral_edit_referrer';
				return -1;
			}
			setEventMessages($langs->trans('LmdbReferralReferrerUpdated'), null, 'mesgs');
			header('Location: '.DOL_URL_ROOT.'/societe/card.php?socid='.$socid);
			exit;
		}

		if ($action === 'lmdbreferral_cancel_referrer') {
			if (!lmdbreferralCanDo($user, 'cancel', $object)) {
				accessforbidden();
			}
			lmdbreferralCheckToken();

			$result = $service->replaceFromValue('', $socid, $user);
			if ($result < 0) {
				setEventMessages($langs->trans($service->error), $service->errors, 'errors');
				return -1;
			}
			setEventMessages($langs->trans('LmdbReferralReferrerRemoved'), null, 'mesgs');
			header('Location: '.DOL_URL_ROOT.'/societe/card.php?socid='.$socid);
			exit;
		}

		return 0;
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
		$langs->load('commercial');
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
		$langs->load('commercial');
		$service = new LmdbReferralService($this->db);
		$links = $service->fetchActiveByFilleul((int) $object->id);
		$locked = $service->isLockedBySignedProposal((int) $object->id);
		$canWrite = lmdbreferralCanDo($user, 'write', $object);
		$canCancel = lmdbreferralCanDo($user, 'cancel', $object);
		if (empty($links) && !$canWrite) {
			return 0;
		}

		$link = !empty($links) ? $links[0] : null;
		$selected = $link ? $this->getActiveReferrerValue((int) $object->id) : '';
		if (GETPOSTISSET('lmdbreferral_referrer')) {
			$selected = GETPOST('lmdbreferral_referrer', 'alphanohtml');
		}
		$editMode = ($action === 'lmdbreferral_edit_referrer' && $canWrite && !$locked);
		$wrapperId = 'lmdbreferral-referrer-view-wrapper-'.((int) $object->id);
		$rowId = 'lmdbreferral-referrer-view-row-'.((int) $object->id);

		print '<div id="'.$wrapperId.'" class="lmdbreferral-referrer-view-wrapper">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border tableforfield centpercent">';
		print '<tr id="'.$rowId.'" class="lmdbreferral-referrer-view-row"><td class="titlefieldmiddle">';
		print '<table class="nobordernopadding centpercent"><tr><td>'.$langs->trans('LmdbReferralReferrer').'</td>';
		if ($canWrite && !$locked && !$editMode) {
			print '<td class="right"><a class="editfielda" href="'.DOL_URL_ROOT.'/societe/card.php?socid='.(int) $object->id.'&action=lmdbreferral_edit_referrer&token='.newToken().'">'.img_edit($langs->trans('Modify'), 1).'</a></td>';
		}
		print '</tr></table>';
		print '</td><td>';
		if ($editMode) {
			print '<form method="POST" action="'.DOL_URL_ROOT.'/societe/card.php?socid='.(int) $object->id.'" class="lmdbreferral-referrer-inline-form">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="lmdbreferral_save_referrer">';
			print lmdbreferralSelectReferrer('lmdbreferral_referrer', $selected, (int) $object->id);
			print ' <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Save').'">';
			print ' <a class="button smallpaddingimp" href="'.DOL_URL_ROOT.'/societe/card.php?socid='.(int) $object->id.'">'.$langs->trans('Cancel').'</a>';
			print '</form>';
		} elseif ($link) {
			print lmdbreferralGetReferrerNomUrl($link->referrer_type, $link->referrer_type === 'soc' ? (int) $link->fk_soc_parrain : (int) $link->fk_user_parrain);
			if ($canCancel && !$locked) {
				print ' &nbsp; <a class="reposition" href="'.DOL_URL_ROOT.'/societe/card.php?socid='.(int) $object->id.'&action=lmdbreferral_cancel_referrer&token='.newToken().'">'.img_delete($langs->trans('LmdbReferralRemoveReferrer')).'</a>';
			}
		} else {
			print '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
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

			var salesLabel = "'.dol_escape_js($langs->trans('SalesRepresentatives')).'";
			var expectedLabel = normalizeReferralLabel(salesLabel);
			var $target = $();

			function normalizeReferralLabel(value) {
				return $.trim(value).replace(/[:\\s]+$/, "").toLowerCase();
			}

			$("table.tableforfield").each(function() {
				var $table = $(this);
				var $rows = $table.children("tbody").children("tr").add($table.children("tr"));
				$rows.each(function() {
					var $candidate = $(this);
					var label = normalizeReferralLabel($candidate.children("td, th").first().text());
					if (label && expectedLabel && label === expectedLabel) {
						$target = $candidate;
						return false;
					}
				});
				if ($target.length) {
					return false;
				}
			});

			if ($target.length) {
				$target.after($row.detach());
				$wrapper.remove();
			}
		});
		</script>';

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
			var isEditContext = '.($excludeSocid > 0 ? 'true' : 'false').';
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

			function normalizeReferralLabel(value) {
				return $.trim(value).replace(/[:\\s]+$/, "").toLowerCase();
			}

			var $target = $();
			if (isEditContext) {
				var salesLabels = [
					"'.dol_escape_js($langs->trans('AssignSalesRepresentatives')).'",
					"'.dol_escape_js($langs->trans('SalesRepresentatives')).'",
					"'.dol_escape_js($langs->trans('SalesRepresentative')).'",
					"Assigner des commerciaux",
					"Assign sales representatives"
				];
				$form.find("tr").each(function() {
					var $candidate = $(this);
					var label = normalizeReferralLabel($candidate.children("td, th").first().text());
					for (var i = 0; i < salesLabels.length; i++) {
						if (label && salesLabels[i] && label === normalizeReferralLabel(salesLabels[i])) {
							$target = $candidate;
							return false;
						}
					}
					if (label.indexOf("commercial") !== -1 || label.indexOf("commerciaux") !== -1) {
						$target = $candidate;
						return false;
					}
				});

				if (!$target.length) {
					$form.find("select, input").filter(function() {
						var attrName = String($(this).attr("name") || "");
						var attrId = String($(this).attr("id") || "");
						return attrName.indexOf("commercial") !== -1 || attrId.indexOf("commercial") !== -1;
					}).each(function() {
						var $tr = $(this).closest("tr");
						if ($tr.length) {
							$target = $tr;
							return false;
						}
					});
				}

				if ($target.length) {
					$target.after($row.detach());
					$wrapper.remove();
					return;
				}
			}

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

			var $nativeTable = $wrapper.nextAll("table.border.centpercent").first();
			if (!$nativeTable.length) {
				$nativeTable = $form.find("table.border.centpercent").not($wrapper.find("table")).first();
			}
			var $firstNativeRow = $nativeTable.children("tbody").children("tr").add($nativeTable.children("tr")).first();
			if ($firstNativeRow.length) {
				$firstNativeRow.before($row.detach());
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
