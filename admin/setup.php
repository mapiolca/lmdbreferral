<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');

$langs->loadLangs(array('admin', 'users', 'lmdbreferral@lmdbreferral'));

if (!lmdbreferralCanDo($user, 'setup')) {
	accessforbidden();
}

$form = new Form($db);
$action = GETPOST('action', 'aZ09');

if ($action === 'save') {
	lmdbreferralCheckToken();

	$constants = array(
		'LMDBREFERRAL_ENABLE_THIRDPARTY_REFERRERS' => GETPOSTINT('LMDBREFERRAL_ENABLE_THIRDPARTY_REFERRERS'),
		'LMDBREFERRAL_ALLOW_USER_REFERRERS' => GETPOSTINT('LMDBREFERRAL_ALLOW_USER_REFERRERS'),
		'LMDBREFERRAL_ONE_ACTIVE_REFERRER' => GETPOSTINT('LMDBREFERRAL_ONE_ACTIVE_REFERRER'),
		'LMDBREFERRAL_PREVENT_DUPLICATE_PAIR' => GETPOSTINT('LMDBREFERRAL_PREVENT_DUPLICATE_PAIR'),
		'LMDBREFERRAL_LOCK_REFERRER_AFTER_SIGNED_PROPAL' => GETPOSTINT('LMDBREFERRAL_LOCK_REFERRER_AFTER_SIGNED_PROPAL'),
		'LMDBREFERRAL_SHOW_THIRDPARTY_BLOCK' => GETPOSTINT('LMDBREFERRAL_SHOW_THIRDPARTY_BLOCK'),
		'LMDBREFERRAL_SHOW_THIRDPARTY_TAB' => GETPOSTINT('LMDBREFERRAL_SHOW_THIRDPARTY_TAB'),
		'LMDBREFERRAL_SHOW_USER_TAB' => GETPOSTINT('LMDBREFERRAL_SHOW_USER_TAB'),
		'LMDBREFERRAL_STATS_INCLUDE_CANCELLED_OR_REFUSED_PROPALS' => GETPOSTINT('LMDBREFERRAL_STATS_INCLUDE_CANCELLED_OR_REFUSED_PROPALS'),
		'LMDBREFERRAL_AMOUNT_REFERENCE' => GETPOST('LMDBREFERRAL_AMOUNT_REFERENCE', 'alpha') === 'TTC' ? 'TTC' : 'HT',
	);
	foreach ($constants as $name => $value) {
		dolibarr_set_const($db, $name, $value, 'chaine', 0, '', (int) $conf->entity);
	}

	$selectedUsers = GETPOST('eligible_users', 'array');
	if (!is_array($selectedUsers)) {
		$selectedUsers = array();
	}
	$selected = array();
	foreach ($selectedUsers as $id) {
		if ((int) $id > 0) {
			$selected[(int) $id] = (int) $id;
		}
	}

	$db->begin();
	$error = 0;
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbreferral_user_eligibility';
	$sql .= " SET active = 0, date_modification = '".$db->idate(dol_now())."', fk_user_modif = ".((int) $user->id);
	$sql .= ' WHERE entity = '.((int) $conf->entity);
	if (!$db->query($sql)) {
		$error++;
	}
	foreach ($selected as $fkUser) {
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbreferral_user_eligibility';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_user = '.((int) $fkUser);
		$resql = $db->query($sql);
			if ($resql && ($obj = $db->fetch_object($resql))) {
				$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbreferral_user_eligibility';
				$sql .= " SET active = 1, date_modification = '".$db->idate(dol_now())."', fk_user_modif = ".((int) $user->id);
				$sql .= ' WHERE rowid = '.((int) $obj->rowid);
			} else {
				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbreferral_user_eligibility';
				$sql .= ' (entity, fk_user, active, date_creation, fk_user_author)';
				$sql .= ' VALUES ('.((int) $conf->entity).', '.((int) $fkUser).", 1, '".$db->idate(dol_now())."', ".((int) $user->id).')';
			}
		if (!$db->query($sql)) {
			$error++;
			break;
		}
	}

	if ($error) {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		$db->commit();
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: setup.php');
	exit;
}

llxHeader('', $langs->trans('LmdbReferralSetup'));
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('lmdbreferral').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('LmdbReferralSetup'), $linkback, 'fa-handshake');
$head = lmdbreferralAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('LmdbReferralSetup'), -1, 'lmdbreferral@lmdbreferral');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('LmdbReferralBusinessRules').'</th></tr>';
lmdbreferral_print_setup_yesno('LMDBREFERRAL_ENABLE_THIRDPARTY_REFERRERS', 'LmdbReferralEnableThirdpartyReferrers', 1);
lmdbreferral_print_setup_yesno('LMDBREFERRAL_ALLOW_USER_REFERRERS', 'LmdbReferralAllowUserReferrers', 0);
lmdbreferral_print_setup_users();
lmdbreferral_print_setup_yesno('LMDBREFERRAL_ONE_ACTIVE_REFERRER', 'LmdbReferralOneActiveReferrer', 1);
lmdbreferral_print_setup_yesno('LMDBREFERRAL_PREVENT_DUPLICATE_PAIR', 'LmdbReferralPreventDuplicatePair', 1);
lmdbreferral_print_setup_yesno('LMDBREFERRAL_LOCK_REFERRER_AFTER_SIGNED_PROPAL', 'LmdbReferralLockAfterSignedProposal', 1);
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('Display').'</th></tr>';
lmdbreferral_print_setup_yesno('LMDBREFERRAL_SHOW_THIRDPARTY_BLOCK', 'LmdbReferralShowThirdpartyBlock', 1);
lmdbreferral_print_setup_yesno('LMDBREFERRAL_SHOW_THIRDPARTY_TAB', 'LmdbReferralShowThirdpartyTab', 1);
lmdbreferral_print_setup_yesno('LMDBREFERRAL_SHOW_USER_TAB', 'LmdbReferralShowUserTab', 1);
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('Statistics').'</th></tr>';
lmdbreferral_print_setup_yesno('LMDBREFERRAL_STATS_INCLUDE_CANCELLED_OR_REFUSED_PROPALS', 'LmdbReferralIncludeCancelledOrRefusedPropals', 0);
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('LmdbReferralAmountReference').'</td><td>';
print $form->selectarray('LMDBREFERRAL_AMOUNT_REFERENCE', array('HT' => $langs->trans('AmountHT'), 'TTC' => $langs->trans('AmountTTC')), getDolGlobalString('LMDBREFERRAL_AMOUNT_REFERENCE', 'HT'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth100');
print '</td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();

/**
 * Print yes/no setup row.
 *
 * @param string $name Constant name
 * @param string $label Translation key
 * @param int    $default Default
 * @return void
 */
function lmdbreferral_print_setup_yesno($name, $label, $default)
{
	global $conf, $langs, $form;

	$value = getDolGlobalInt($name, $default);
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans($label).'</td><td>'.$form->selectyesno($name, $value, 1).'</td></tr>';
}

/**
 * Print eligible users multiselect.
 *
 * @return void
 */
function lmdbreferral_print_setup_users()
{
	global $db, $langs;

	$selected = array();
	$sql = 'SELECT fk_user FROM '.MAIN_DB_PREFIX.'lmdbreferral_user_eligibility WHERE entity IN ('.lmdbreferralGetEntitySql('lmdbreferralusereligibility').') AND active = 1';
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$selected[(int) $obj->fk_user] = true;
		}
	}

	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('LmdbReferralEligibleUsers').'</td><td>';
	print '<select class="flat minwidth500 multiselect2" multiple name="eligible_users[]" id="eligible_users">';
	$sql = 'SELECT rowid, lastname, firstname, login FROM '.MAIN_DB_PREFIX.'user WHERE statut = 1 ORDER BY lastname ASC, firstname ASC, login ASC';
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$label = trim($obj->firstname.' '.$obj->lastname);
			if ($label === '') {
				$label = $obj->login;
			}
			print '<option value="'.((int) $obj->rowid).'"'.(!empty($selected[(int) $obj->rowid]) ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
		}
	}
	print '</select>';
	if (function_exists('ajax_combobox')) {
		print ajax_combobox('eligible_users');
	}
	print '</td></tr>';
}
