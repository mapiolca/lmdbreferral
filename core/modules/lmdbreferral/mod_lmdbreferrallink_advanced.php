<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

dol_include_once('/lmdbreferral/core/modules/lmdbreferral/modules_lmdbreferrallink.php');
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

/**
 * Advanced numbering rule for referral links.
 */
class mod_lmdbreferrallink_advanced extends ModeleNumRefLmdbReferralLink
{
	/** @var string */
	public $version = 'dolibarr';

	/** @var string */
	public $error = '';

	/** @var string */
	public $name = 'advanced';

	/**
	 * Return description of numbering module.
	 *
	 * @param Translate $langs Translate object
	 * @return string
	 */
	public function info($langs)
	{
		global $db;

		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

		$langs->load('bills');
		$form = new Form($db);

		$text = $langs->trans('GenericNumRefModelDesc')."<br>\n";
		$token = function_exists('currentToken') ? currentToken() : newToken();
		$text .= '<form action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" method="POST">';
		$text .= '<input type="hidden" name="token" value="'.dol_escape_htmltag($token).'">';
		$text .= '<input type="hidden" name="action" value="updateMask">';
		$text .= '<input type="hidden" name="maskconst" value="LMDBREFERRAL_LINK_ADVANCED_MASK">';
		$text .= '<table class="nobordernopadding centpercent">';

		$tooltip = $langs->trans('GenericMaskCodes', $langs->transnoentities('LmdbReferralLink'), $langs->transnoentities('LmdbReferralLink'));
		$tooltip .= $langs->trans('GenericMaskCodes1');
		$tooltip .= '<br>'.$langs->trans('GenericMaskCodes2');
		$tooltip .= '<br>'.$langs->trans('GenericMaskCodes3');
		$tooltip .= $langs->trans('GenericMaskCodes4a', $langs->transnoentities('LmdbReferralLink'), $langs->transnoentities('LmdbReferralLink'));
		$tooltip .= $langs->trans('GenericMaskCodes5');
		$tooltip .= '<br>'.$langs->trans('GenericMaskCodes5b');

		$text .= '<tr><td>'.$langs->trans('Mask').':</td>';
		$text .= '<td class="right">'.$form->textwithpicto('<input type="text" class="flat minwidth175" name="maskvalue" value="'.dol_escape_htmltag(getDolGlobalString('LMDBREFERRAL_LINK_ADVANCED_MASK')).'">', $tooltip, 1, 'help').'</td>';
		$text .= '<td class="left" rowspan="2">&nbsp; <input type="submit" class="button button-edit" value="'.$langs->trans('Modify').'" name="Button"></td>';
		$text .= '</tr>';
		$text .= '</table>';
		$text .= '</form>';

		return $text;
	}

	/**
	 * Return an example of numbering.
	 *
	 * @return string
	 */
	public function getExample()
	{
		global $db, $langs;

		$object = new LmdbReferralLink($db);
		$object->initAsSpecimen();
		$numExample = $this->getNextValue($object);

		if (!$numExample) {
			$numExample = $langs->trans('NotConfigured');
		}

		return (string) $numExample;
	}

	/**
	 * Return next free value.
	 *
	 * @param LmdbReferralLink $object Object source
	 * @return string|int
	 */
	public function getNextValue($object)
	{
		global $db;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

		$mask = getDolGlobalString('LMDBREFERRAL_LINK_ADVANCED_MASK');
		if ($mask === '') {
			$this->error = 'NotConfigured';
			return 0;
		}

		$date = !empty($object->date_creation) ? $object->date_creation : dol_now();
		$where = ' AND entity IN ('.lmdbreferralGetNumberingEntitySql($object).')';

		return get_next_value($db, $mask, 'lmdbreferral_link', 'ref', $where, '', $date, 'next', false);
	}
}
