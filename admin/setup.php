<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

$langs->loadLangs(array('admin', 'users', 'lmdbreferral@lmdbreferral'));

if (!lmdbreferralCanDo($user, 'setup')) {
	accessforbidden();
}

$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alphanohtml');
$pageToken = '';

if (in_array($action, array('setmodule', 'updateMask', 'setdocmodel', 'delmodel', 'setdoc'), true)) {
	lmdbreferralCheckToken();
}

if ($action === 'setmodule' && $value !== '') {
	$result = lmdbreferral_set_numbering_module($value);
	if ($result > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
	header('Location: setup.php');
	exit;
}

if ($action === 'updateMask') {
	$maskconst = GETPOST('maskconst', 'alphanohtml');
	$maskvalue = GETPOST('maskvalue', 'nohtml');
	if ($maskconst === 'LMDBREFERRAL_LINK_ADVANCED_MASK') {
		$result = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', (int) $conf->entity);
		if ($result > 0) {
			setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}
	header('Location: setup.php');
	exit;
}

if (in_array($action, array('setdocmodel', 'delmodel', 'setdoc'), true) && $value !== '') {
	$result = lmdbreferral_update_document_model($action, $value);
	if ($result > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
	header('Location: setup.php');
	exit;
}

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
		'LMDBREFERRAL_FOLLOWUP_DELAY_DAYS' => max(0, GETPOSTINT('LMDBREFERRAL_FOLLOWUP_DELAY_DAYS')),
		'LMDBREFERRAL_STAR_MAX_NODES' => max(1, GETPOSTINT('LMDBREFERRAL_STAR_MAX_NODES')),
		'LMDBREFERRAL_STAR_DEFAULT_DEPTH' => GETPOSTINT('LMDBREFERRAL_STAR_DEFAULT_DEPTH') === 2 ? 2 : 1,
		'LMDBREFERRAL_STAR_ENABLE_DEPTH_2' => GETPOSTINT('LMDBREFERRAL_STAR_ENABLE_DEPTH_2'),
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

$pageToken = newToken();

llxHeader('', $langs->trans('LmdbReferralSetup'));
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('lmdbreferral').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('LmdbReferralSetup'), $linkback, 'fa-handshake');
$head = lmdbreferralAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('LmdbReferralSetup'), -1, 'lmdbreferral@lmdbreferral');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.dol_escape_htmltag($pageToken).'">';
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
lmdbreferral_print_setup_int('LMDBREFERRAL_FOLLOWUP_DELAY_DAYS', 'LmdbReferralFollowUpDelayDays', 30, 0);
lmdbreferral_print_setup_int('LMDBREFERRAL_STAR_MAX_NODES', 'LmdbReferralStarMaxNodes', 30, 1);
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('LmdbReferralStarDefaultDepth').'</td><td>';
print $form->selectarray('LMDBREFERRAL_STAR_DEFAULT_DEPTH', array(1 => '1', 2 => '2'), getDolGlobalInt('LMDBREFERRAL_STAR_DEFAULT_DEPTH', 1), 0, 0, 0, '', 0, 0, 0, '', 'maxwidth75');
print '</td></tr>';
lmdbreferral_print_setup_yesno('LMDBREFERRAL_STAR_ENABLE_DEPTH_2', 'LmdbReferralStarEnableDepth2', 1);
print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print '<br>';
lmdbreferral_print_numbering_models($pageToken);
print '<br>';
lmdbreferral_print_document_models($pageToken);

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
 * Print integer setup row.
 *
 * @param string $name Constant name
 * @param string $label Translation key
 * @param int    $default Default
 * @param int    $min Minimum value
 * @return void
 */
function lmdbreferral_print_setup_int($name, $label, $default, $min)
{
	global $langs;

	$value = getDolGlobalInt($name, $default);
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans($label).'</td><td>';
	print '<input type="number" class="flat maxwidth75" min="'.((int) $min).'" name="'.dol_escape_htmltag($name).'" value="'.((int) $value).'">';
	print '</td></tr>';
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

/**
 * Persist numbering model selection.
 *
 * @param string $classname Numbering class
 * @return int
 */
function lmdbreferral_set_numbering_module($classname)
{
	global $db, $conf;

	if (!preg_match('/^mod_lmdbreferrallink_[a-z0-9_]+$/', $classname)) {
		return -1;
	}

	$file = dol_buildpath('/lmdbreferral/core/modules/lmdbreferral/'.$classname.'.php');
	if (!is_readable($file)) {
		return -1;
	}
	require_once $file;
	if (!class_exists($classname)) {
		return -1;
	}

	$sample = new LmdbReferralLink($db);
	$sample->initAsSpecimen();
	$module = new $classname();
	if (method_exists($module, 'canBeActivated') && !$module->canBeActivated($sample)) {
		return -1;
	}

	return dolibarr_set_const($db, 'LMDBREFERRAL_LINK_ADDON', $classname, 'chaine', 0, '', (int) $conf->entity);
}

/**
 * Update document model activation/default state.
 *
 * @param string $action Action code
 * @param string $value Model name
 * @return int
 */
function lmdbreferral_update_document_model($action, $value)
{
	global $db, $conf;

	$type = 'lmdbreferrallink';
	$scandir = 'lmdbreferral/core/modules/lmdbreferral/doc';
	$label = 'Standard';

	if (!preg_match('/^[a-z0-9_]+$/', $value)) {
		return -1;
	}

	if ($action === 'delmodel') {
		$result = delDocumentModel($value, $type);
		if ($result > 0 && getDolGlobalString('LMDBREFERRAL_LINK_ADDON_PDF') === $value) {
			dolibarr_del_const($db, 'LMDBREFERRAL_LINK_ADDON_PDF', (int) $conf->entity);
		}

		return $result;
	}

	$result = 1;
	if (!lmdbreferral_document_model_is_active($value)) {
		$result = addDocumentModel($value, $type, $label, $scandir);
	}
	if ($result <= 0) {
		return -1;
	}
	if ($action === 'setdoc') {
		return dolibarr_set_const($db, 'LMDBREFERRAL_LINK_ADDON_PDF', $value, 'chaine', 0, '', (int) $conf->entity);
	}

	return 1;
}

/**
 * Tell if a document model is active for current entity.
 *
 * @param string $name Model name
 * @return bool
 */
function lmdbreferral_document_model_is_active($name)
{
	global $db, $conf;

	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'document_model';
	$sql .= " WHERE nom = '".$db->escape($name)."'";
	$sql .= " AND type = 'lmdbreferrallink'";
	$sql .= ' AND entity = '.((int) $conf->entity);
	$resql = $db->query($sql);
	if (!$resql) {
		return false;
	}
	$active = (bool) $db->fetch_object($resql);
	$db->free($resql);

	return $active;
}

/**
 * Print numbering models block.
 *
 * @param string $token CSRF token
 * @return void
 */
function lmdbreferral_print_numbering_models($token)
{
	global $db, $langs;

	print load_fiche_titre($langs->trans('LmdbReferralNumberingModels'), '', 'hashtag');
	print '<div class="underbanner opacitymedium">'.$langs->trans('LmdbReferralNumberingModelsHelp').'</div>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Name').'</th><th>'.$langs->trans('Description').'</th><th>'.$langs->trans('Example').'</th><th class="center">'.$langs->trans('Status').'</th></tr>';

	$current = getDolGlobalString('LMDBREFERRAL_LINK_ADDON', 'mod_lmdbreferrallink_standard');
	$dir = dol_buildpath('/lmdbreferral/core/modules/lmdbreferral/');
	$files = is_dir($dir) ? dol_dir_list($dir, 'files', 0, '^mod_lmdbreferrallink_[a-z0-9_]+\.php$') : array();
	$found = false;
	foreach ($files as $fileinfo) {
		$file = $fileinfo['name'];
		$classname = substr($file, 0, -4);
		require_once $dir.$file;
		if (!class_exists($classname)) {
			continue;
		}
		$module = new $classname();
		$sample = new LmdbReferralLink($db);
		$sample->initAsSpecimen();
		$canBeActivated = !method_exists($module, 'canBeActivated') || $module->canBeActivated($sample);
		$found = true;

		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_escape_htmltag(!empty($module->name) ? $module->name : $classname).'<br><span class="opacitymedium">'.dol_escape_htmltag($classname).'</span></td>';
		print '<td class="small">'.$module->info($langs).'</td>';
		print '<td class="small">'.dol_escape_htmltag((string) $module->getExample()).'</td>';
		print '<td class="center">';
		if ($current === $classname) {
			print img_picto($langs->trans('Enabled'), 'switch_on');
		} elseif ($canBeActivated) {
			$url = $_SERVER['PHP_SELF'].'?action=setmodule&value='.urlencode($classname).'&token='.urlencode($token);
			print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</a>';
		} else {
			print img_picto($langs->trans('Disabled'), 'switch_off');
		}
		print '</td>';
		print '</tr>';
	}
	if (!$found) {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
	print '</div>';
}

/**
 * Print document models block.
 *
 * @param string $token CSRF token
 * @return void
 */
function lmdbreferral_print_document_models($token)
{
	global $db, $langs;

	$default = getDolGlobalString('LMDBREFERRAL_LINK_ADDON_PDF', 'standard_lmdbreferrallink');

	print load_fiche_titre($langs->trans('LmdbReferralDocumentModels'), '', 'pdf');
	print '<div class="underbanner opacitymedium">'.$langs->trans('LmdbReferralDocumentModelsHelp').'</div>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Name').'</th><th>'.$langs->trans('Description').'</th><th class="center">'.$langs->trans('Type').'</th><th class="center">'.$langs->trans('Status').'</th><th class="center">'.$langs->trans('Default').'</th></tr>';

	$dir = dol_buildpath('/lmdbreferral/core/modules/lmdbreferral/doc/');
	$scandir = 'lmdbreferral/core/modules/lmdbreferral/doc';
	$files = is_dir($dir) ? dol_dir_list($dir, 'files', 0, '^pdf_[a-z0-9_]+\.modules\.php$') : array();
	$found = false;
	foreach ($files as $fileinfo) {
		$file = $fileinfo['name'];
		$name = substr($file, 4, -12);
		$classname = substr($file, 0, -12);
		require_once $dir.$file;
		if (!class_exists($classname)) {
			continue;
		}
		$model = new $classname($db);
		$modelName = !empty($model->name) ? $model->name : $name;
		$isActive = lmdbreferral_document_model_is_active($modelName);
		$isDefault = ($default === $modelName);
		$found = true;

		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_escape_htmltag($modelName).'</td>';
		print '<td class="small">'.dol_escape_htmltag(!empty($model->description) ? $model->description : '').'</td>';
		print '<td class="center">'.dol_escape_htmltag(!empty($model->type) ? $model->type : '').'</td>';
		print '<td class="center">';
		if ($isActive) {
			$url = $_SERVER['PHP_SELF'].'?action=delmodel&value='.urlencode($modelName).'&token='.urlencode($token);
			print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Enabled'), 'switch_on').'</a>';
		} else {
			$url = $_SERVER['PHP_SELF'].'?action=setdocmodel&value='.urlencode($modelName).'&token='.urlencode($token);
			print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</a>';
		}
		print '</td>';
		print '<td class="center">';
		if ($isDefault) {
			print img_picto($langs->trans('Default'), 'on');
		} elseif ($isActive) {
			$url = $_SERVER['PHP_SELF'].'?action=setdoc&value='.urlencode($modelName).'&token='.urlencode($token);
			print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('SetDefault'), 'switch_on').'</a>';
		} else {
			print '&nbsp;';
		}
		print '</td>';
		print '</tr>';
	}
	if (!$found) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
	print '</div>';
}
