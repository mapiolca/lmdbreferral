<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Prepare admin tabs.
 *
 * @return array<int,array<int,string>>
 */
function lmdbreferralAdminPrepareHead()
{
	global $langs;

	$langs->load('lmdbreferral@lmdbreferral');

	return array(
		array(dol_buildpath('/lmdbreferral/admin/setup.php', 1), $langs->trans('Settings'), 'settings'),
		array(dol_buildpath('/lmdbreferral/admin/compatibility.php', 1), $langs->trans('LmdbReferralCompatibilityShort'), 'compatibility'),
		array(dol_buildpath('/lmdbreferral/admin/about.php', 1), $langs->trans('About'), 'about'),
	);
}

/**
 * Prepare referral link card tabs.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return array<int,array<int,string>>
 */
function lmdbreferralLinkPrepareHead($object)
{
	global $langs;

	$langs->loadLangs(array('agenda', 'lmdbreferral@lmdbreferral'));

	$id = !empty($object->id) ? (int) $object->id : 0;
	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/lmdbreferral/card.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('Card');
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbreferral/document.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('Documents');
	$head[$h][2] = 'documents';
	$h++;

	if (isModEnabled('agenda')) {
		$head[$h][0] = dol_buildpath('/lmdbreferral/agenda.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('Events').'/'.$langs->trans('Agenda');
		$head[$h][2] = 'agenda';
		$h++;
	}

	return $head;
}

/**
 * Return document root directory for referral link documents.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return string
 */
function lmdbreferralGetLinkDocumentRootDir($object)
{
	global $conf;

	if (!function_exists('dol_sanitizeFileName')) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	}

	$moduleOutput = '';
	if (function_exists('getMultidirOutput')) {
		$moduleOutput = (string) getMultidirOutput($object, 'lmdbreferral', 0);
	}

	if ($moduleOutput === '' || strpos($moduleOutput, 'error-') === 0) {
		$objectEntity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		if (!empty($conf->lmdbreferral->multidir_output[$objectEntity])) {
			$moduleOutput = (string) $conf->lmdbreferral->multidir_output[$objectEntity];
		} elseif (!empty($conf->lmdbreferral->dir_output)) {
			$moduleOutput = (string) $conf->lmdbreferral->dir_output;
		}
	}

	if ($moduleOutput === '' || strpos($moduleOutput, 'error-') === 0) {
		return '';
	}

	return rtrim($moduleOutput, '/');
}

/**
 * Return document directory for a referral link.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return string
 */
function lmdbreferralGetLinkDocumentDir($object)
{
	$moduleOutput = lmdbreferralGetLinkDocumentRootDir($object);
	if ($moduleOutput === '') {
		return '';
	}

	lmdbreferralEnsureLinkDocumentDirectory($object, $moduleOutput);

	return $moduleOutput.'/'.lmdbreferralGetLinkDocumentSubdir($object);
}

/**
 * Return native document modulepart used by FormFile for referral links.
 *
 * @return string
 */
function lmdbreferralGetLinkDocumentModulePart()
{
	return 'lmdbreferral';
}

/**
 * Return native document relative path for a referral link.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return string
 */
function lmdbreferralGetLinkDocumentSubdir($object)
{
	if (!function_exists('dol_sanitizeFileName')) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	}

	return 'referral/'.$object->element.'/'.dol_sanitizeFileName((string) $object->ref);
}

/**
 * Return the legacy document relative path used before the permission-aware directory.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return string
 */
function lmdbreferralGetLinkLegacyDocumentSubdir($object)
{
	if (!function_exists('dol_sanitizeFileName')) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	}

	return $object->element.'/'.dol_sanitizeFileName((string) $object->ref);
}

/**
 * Move legacy referral link documents to the permission-aware native path.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @param string                  $rootDir Root document directory
 * @return void
 */
function lmdbreferralEnsureLinkDocumentDirectory($object, $rootDir)
{
	if ($rootDir === '') {
		return;
	}
	if (!function_exists('dol_mkdir')) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	}

	$currentSubdir = lmdbreferralGetLinkDocumentSubdir($object);
	$legacySubdir = lmdbreferralGetLinkLegacyDocumentSubdir($object);
	if ($currentSubdir === $legacySubdir) {
		return;
	}

	$currentDir = rtrim($rootDir, '/').'/'.$currentSubdir;
	$legacyDir = rtrim($rootDir, '/').'/'.$legacySubdir;
	$encodedCurrentDir = function_exists('dol_osencode') ? dol_osencode($currentDir) : $currentDir;
	$encodedLegacyDir = function_exists('dol_osencode') ? dol_osencode($legacyDir) : $legacyDir;
	if (!is_dir($encodedLegacyDir) || is_dir($encodedCurrentDir)) {
		return;
	}

	$parentDir = dirname($currentDir);
	if (!is_dir(function_exists('dol_osencode') ? dol_osencode($parentDir) : $parentDir) && dol_mkdir($parentDir) < 0) {
		dol_syslog(__METHOD__.' unable to create '.$parentDir, LOG_WARNING);
		return;
	}

	if (!dol_move_dir($legacyDir, $currentDir, 1, 0, 0)) {
		dol_syslog(__METHOD__.' unable to move '.$legacyDir.' to '.$currentDir, LOG_WARNING);
	}
}

/**
 * Return the main generated PDF relative path for a referral link.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return string
 */
function lmdbreferralGetLinkMainPdfRelativePath($object)
{
	$rootDir = lmdbreferralGetLinkDocumentRootDir($object);
	if ($rootDir === '') {
		return '';
	}

	if (!function_exists('dol_sanitizeFileName')) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	}

	lmdbreferralEnsureLinkDocumentDirectory($object, $rootDir);

	$modulepart = lmdbreferralGetLinkDocumentModulePart();
	$candidates = array();
	if (!empty($object->last_main_doc)) {
		$lastMainDoc = str_replace('\\', '/', (string) $object->last_main_doc);
		$lastMainDocWithoutModule = preg_replace('/^'.preg_quote($modulepart, '/').'\//', '', $lastMainDoc);
		if (is_string($lastMainDocWithoutModule)) {
			$candidates[] = $lastMainDocWithoutModule;
		}
		$candidates[] = $lastMainDoc;
	}
	$candidates[] = lmdbreferralGetLinkDocumentSubdir($object).'/'.dol_sanitizeFileName((string) $object->ref).'.pdf';
	$candidates[] = lmdbreferralGetLinkLegacyDocumentSubdir($object).'/'.dol_sanitizeFileName((string) $object->ref).'.pdf';

	foreach ($candidates as $candidate) {
		$candidate = trim((string) $candidate, '/');
		if ($candidate === '' || preg_match('/(^|\/)\.\.(\/|$)/', $candidate)) {
			continue;
		}
		$fullpath = $rootDir.'/'.$candidate;
		$encodedPath = function_exists('dol_osencode') ? dol_osencode($fullpath) : $fullpath;
		if (is_file($encodedPath)) {
			if (strpos($candidate, $modulepart.'/') === 0) {
				$candidate = substr($candidate, strlen($modulepart) + 1);
			}

			return $candidate;
		}
	}

	return '';
}

/**
 * Return banner HTML with the native preview of the main generated PDF.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return string
 */
function lmdbreferralGetLinkBannerPdfPreviewHtml($object)
{
	global $conf, $langs;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$rootDir = lmdbreferralGetLinkDocumentRootDir($object);
	$relativePdf = lmdbreferralGetLinkMainPdfRelativePath($object);
	if ($rootDir === '' || $relativePdf === '') {
		return '';
	}

	$modulepart = lmdbreferralGetLinkDocumentModulePart();
	$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
	$pdfPath = $rootDir.'/'.$relativePdf;
	$encodedPdfPath = function_exists('dol_osencode') ? dol_osencode($pdfPath) : $pdfPath;
	if (!is_file($encodedPdfPath)) {
		return '';
	}

	$pathinfo = pathinfo($relativePdf);
	$previewDirname = isset($pathinfo['dirname']) && (string) $pathinfo['dirname'] !== '.' ? (string) $pathinfo['dirname'] : '';
	$previewBasename = (string) ($pathinfo['filename'] ?? dol_sanitizeFileName((string) $object->ref));
	$previewRelative = ($previewDirname !== '' ? $previewDirname.'/' : '').$previewBasename.'.pdf_preview.png';
	$previewRelativeBis = ($previewDirname !== '' ? $previewDirname.'/' : '').$previewBasename.'.pdf_preview-0.png';
	$previewPath = $rootDir.'/'.$previewRelative;
	$previewPathBis = $rootDir.'/'.$previewRelativeBis;
	$encodedPreviewPath = function_exists('dol_osencode') ? dol_osencode($previewPath) : $previewPath;
	$encodedPreviewPathBis = function_exists('dol_osencode') ? dol_osencode($previewPathBis) : $previewPathBis;
	$previewForDisplayRelative = '';
	$previewForDisplayPath = '';
	if (is_file($encodedPreviewPath)) {
		$previewForDisplayRelative = $previewRelative;
		$previewForDisplayPath = $encodedPreviewPath;
	} elseif (is_file($encodedPreviewPathBis)) {
		$previewForDisplayRelative = $previewRelativeBis;
		$previewForDisplayPath = $encodedPreviewPathBis;
	}

	if (class_exists('Imagick') && ($previewForDisplayPath === '' || filemtime($previewForDisplayPath) < filemtime($encodedPdfPath))) {
		$convertResult = dol_convert_file($pdfPath, 'png', $previewPath, '0');
		if ($convertResult <= 0) {
			dol_syslog('lmdbreferral banner PDF preview generation skipped for '.$relativePdf.' result='.$convertResult, LOG_DEBUG);
		}
		if (is_file($encodedPreviewPath)) {
			$previewForDisplayRelative = $previewRelative;
			$previewForDisplayPath = $encodedPreviewPath;
		} elseif (is_file($encodedPreviewPathBis)) {
			$previewForDisplayRelative = $previewRelativeBis;
			$previewForDisplayPath = $encodedPreviewPathBis;
		}
	}

	$previewUrl = '';
	if (function_exists('getAdvancedPreviewUrl')) {
		$tmpPreviewUrl = getAdvancedPreviewUrl($modulepart, $relativePdf, 1, '&entity='.$entity);
		if (is_array($tmpPreviewUrl) && !empty($tmpPreviewUrl['url'])) {
			$previewUrl = '<a href="'.$tmpPreviewUrl['url'].'"'
				.(!empty($tmpPreviewUrl['css']) ? ' class="'.$tmpPreviewUrl['css'].'"' : '')
				.(!empty($tmpPreviewUrl['mime']) ? ' mime="'.$tmpPreviewUrl['mime'].'"' : '')
				.(!empty($tmpPreviewUrl['target']) ? ' target="'.$tmpPreviewUrl['target'].'"' : '')
				.'>';
		}
	}
	if ($previewUrl === '') {
		$previewUrl = '<a href="'.DOL_URL_ROOT.'/document.php?modulepart='.urlencode($modulepart).'&entity='.$entity.'&attachment=0&file='.urlencode($relativePdf).'" target="_blank">';
	}

	$heightForPhotoref = !empty($conf->dol_optimize_smallscreen) ? 60 : 80;
	$out = '<div class="floatleft inline-block valignmiddle divphotoref">';
	$out .= '<div class="photoref">';
	$out .= $previewUrl;
	if ($previewForDisplayRelative !== '') {
		$out .= '<img height="'.((int) $heightForPhotoref).'" class="photo photowithborder" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.urlencode($modulepart).'&entity='.$entity.'&file='.urlencode($previewForDisplayRelative).'" alt="'.dol_escape_htmltag($langs->trans('Preview')).'">';
	} else {
		$out .= '<span class="photo photowithborder valignmiddle">'.img_mime($relativePdf, $langs->trans('Preview'), 'pictopreview').'</span>';
	}
	$out .= '</a>';
	$out .= '</div>';
	$out .= '</div>';

	return $out;
}

/**
 * Return the referral link important information for native banners.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return string
 */
function lmdbreferralGetLinkBannerMoreHtmlRef($object)
{
	global $db, $langs;

	$referrerId = $object->referrer_type === 'soc' ? (int) $object->fk_soc_parrain : (int) $object->fk_user_parrain;
	$referrerNomUrl = lmdbreferralGetReferrerNomUrl((string) $object->referrer_type, $referrerId);
	$filleulNomUrl = '';
	if ((int) $object->fk_soc_filleul > 0) {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$soc = new Societe($db);
		if ($soc->fetch((int) $object->fk_soc_filleul) > 0) {
			$filleulNomUrl = $soc->getNomUrl(1);
		}
	}

	$creationDate = lmdbreferralGetLinkEffectiveCreationDate($object);

	$out = '<div class="refidno">';
	$out .= $langs->trans('LmdbReferralReferrer').' : '.$referrerNomUrl;
	$out .= '<br>'.$langs->trans('LmdbReferralReferredThirdparty').' : '.($filleulNomUrl !== '' ? $filleulNomUrl : '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>');
	$out .= '<br>'.$langs->trans('DateCreation').' : '.($creationDate !== '' ? dol_print_date($db->jdate($creationDate), 'dayhour') : '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>');
	$out .= '</div>';

	return $out;
}

/**
 * Return the effective business creation date of a referral link.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return string SQL date
 */
function lmdbreferralGetLinkEffectiveCreationDate($object)
{
	$thirdpartyCreationDate = '';
	if (getDolGlobalInt('LMDBREFERRAL_USE_REFERRED_THIRDPARTY_CREATION_DATE', 1) && !empty($object->fk_soc_filleul)) {
		$thirdpartyCreationDate = lmdbreferralGetReferredThirdpartyCreationDate((int) $object->fk_soc_filleul);
	}

	if ($thirdpartyCreationDate !== '') {
		return $thirdpartyCreationDate;
	}

	return !empty($object->date_creation) ? (string) $object->date_creation : '';
}

/**
 * Return the creation date of a referred thirdparty in the current entity scope.
 *
 * @param int $fkSocFilleul Referred thirdparty id
 * @return string SQL date
 */
function lmdbreferralGetReferredThirdpartyCreationDate($fkSocFilleul)
{
	global $db;

	if ((int) $fkSocFilleul <= 0 || !is_object($db)) {
		return '';
	}

	$sql = 'SELECT s.datec';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'societe as s';
	$sql .= ' WHERE s.rowid = '.((int) $fkSocFilleul);
	$sql .= ' AND s.entity IN ('.lmdbreferralGetEntitySql('societe').')';
	$sql .= $db->plimit(1);
	$resql = $db->query($sql);
	if (!$resql) {
		return '';
	}

	$obj = $db->fetch_object($resql);
	$creationDate = is_object($obj) && !empty($obj->datec) ? (string) $obj->datec : '';
	$db->free($resql);

	return $creationDate;
}

/**
 * Return the native shortlist limit used for linked agenda events.
 *
 * @return int
 */
function lmdbreferralGetAgendaBlockLimit()
{
	$limit = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT');
	if ($limit <= 0) {
		$limit = 10;
	}

	return $limit;
}

/**
 * Return the native Agenda element type for referral links.
 *
 * @return string
 */
function lmdbreferralGetAgendaElementType()
{
	return 'lmdbreferrallink@lmdbreferral';
}

/**
 * Check module permissions with administrator override.
 *
 * @param User        $user   User
 * @param string      $action Action code
 * @param object|null $object Optional object
 * @return bool
 */
function lmdbreferralCanDo($user, $action, $object = null)
{
	if (!empty($user->admin)) {
		return true;
	}
	if (!method_exists($user, 'hasRight')) {
		return false;
	}

	$map = array(
		'read' => 'read',
		'write' => 'write',
		'cancel' => 'cancel',
		'all' => 'all',
		'own' => 'own',
		'export' => 'export',
		'api' => 'api',
		'setup' => 'setup',
		'delete' => 'delete',
	);
	$right = isset($map[$action]) ? $map[$action] : $action;

	return $user->hasRight('lmdbreferral', 'referral', $right);
}

/**
 * Tell if a user is allowed to view a referral link as own data.
 *
 * @param User               $user User
 * @param LmdbReferralLink|object $link Link-like object
 * @return bool
 */
function lmdbreferralCanReadOwnLink($user, $link)
{
	return lmdbreferralCanDo($user, 'own')
		&& !empty($link->referrer_type)
		&& $link->referrer_type === 'user'
		&& !empty($link->fk_user_parrain)
		&& (int) $link->fk_user_parrain === (int) $user->id;
}

/**
 * Check CSRF token for explicit sensitive actions.
 *
 * @return void
 */
function lmdbreferralCheckToken()
{
	$token = GETPOST('token', 'alphanohtml');
	$currentToken = function_exists('currentToken') ? (string) currentToken() : '';
	if (empty($token) || $currentToken === '' || !hash_equals($currentToken, $token)) {
		accessforbidden('Bad token');
	}
}

/**
 * Return entity SQL scope.
 *
 * @param string $element Element key
 * @return string
 */
function lmdbreferralGetEntitySql($element = 'lmdbreferrallink')
{
	global $conf, $db;

	if (function_exists('getEntity')) {
		return $db->sanitize(getEntity($element));
	}

	return (string) ((int) $conf->entity);
}

/**
 * Return entity SQL scope for referral link numbering.
 *
 * @param object|null $object Optional object
 * @return string
 */
function lmdbreferralGetNumberingEntitySql($object = null)
{
	global $conf;

	$entityIds = array();
	$scopeParts = array(
		lmdbreferralGetEntitySql('lmdbreferrallink'),
		lmdbreferralGetEntitySql('lmdbreferrallinknumber'),
	);
	if (is_object($object) && !empty($object->entity)) {
		$scopeParts[] = (string) ((int) $object->entity);
	}

	foreach ($scopeParts as $scopePart) {
		foreach (explode(',', $scopePart) as $entityId) {
			$entityId = (int) trim($entityId);
			if ($entityId > 0) {
				$entityIds[$entityId] = $entityId;
			}
		}
	}

	if (empty($entityIds)) {
		$entityIds[(int) $conf->entity] = (int) $conf->entity;
	}

	return implode(',', $entityIds);
}

/**
 * Tell if a native Dolibarr user combobox filter is enabled.
 *
 * @param string $name Constant name
 * @return bool
 */
function lmdbreferralUserComboFilterEnabled($name)
{
	$value = function_exists('getDolUserString') ? getDolUserString($name, getDolGlobalString($name)) : getDolGlobalString($name);

	return !empty($value);
}

/**
 * Return SQL conditions for users that can be selected as referrers in the current entity scope.
 *
 * @param string $alias SQL alias for llx_user
 * @return string
 */
function lmdbreferralGetSelectableReferrerUserWhere($alias = 'u')
{
	global $conf, $user;

	$alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
	if ($alias === '') {
		$alias = 'u';
	}

	$conditions = array();
	if (function_exists('isModEnabled') && isModEnabled('multicompany') && getDolGlobalInt('MULTICOMPANY_TRANSVERSE_MODE')) {
		if (is_object($user) && !empty($user->admin) && empty($user->entity) && (int) $conf->entity === 1) {
			$conditions[] = $alias.'.entity IS NOT NULL';
		} else {
			$conditions[] = '('.$alias.'.entity = 0 OR EXISTS (SELECT ug.fk_user FROM '.MAIN_DB_PREFIX.'usergroup_user as ug WHERE ug.fk_user = '.$alias.'.rowid AND ug.entity IN ('.lmdbreferralGetEntitySql('usergroup').')))';
		}
	} else {
		$conditions[] = $alias.'.entity IN ('.lmdbreferralGetEntitySql('user').')';
	}

	$conditions[] = $alias.'.statut <> 0';
	if (lmdbreferralUserComboFilterEnabled('USER_HIDE_NONEMPLOYEE_IN_COMBOBOX')) {
		$conditions[] = $alias.'.employee <> 0';
	}
	if (lmdbreferralUserComboFilterEnabled('USER_HIDE_EXTERNAL_IN_COMBOBOX')) {
		$conditions[] = $alias.'.fk_soc IS NULL';
	}

	return implode(' AND ', $conditions);
}

/**
 * Return users that can be selected as referrers.
 *
 * @param bool $onlyEligible Restrict to users enabled in referral settings
 * @param int  $userId       Optional user id filter
 * @return array<int,array{rowid:int,lastname:string,firstname:string,login:string}>
 */
function lmdbreferralGetSelectableReferrerUsers($onlyEligible = false, $userId = 0)
{
	global $db;

	$users = array();
	$sql = 'SELECT DISTINCT u.rowid, u.lastname, u.firstname, u.login';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'user as u';
	if ($onlyEligible) {
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbreferral_user_eligibility as e';
		$sql .= ' ON e.fk_user = u.rowid';
		$sql .= ' AND e.active = 1';
		$sql .= ' AND e.entity IN ('.lmdbreferralGetEntitySql('lmdbreferralusereligibility').')';
	}
	$sql .= ' WHERE '.lmdbreferralGetSelectableReferrerUserWhere('u');
	if ((int) $userId > 0) {
		$sql .= ' AND u.rowid = '.((int) $userId);
	}
	$sql .= ' ORDER BY u.lastname ASC, u.firstname ASC, u.login ASC';

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_WARNING);
		return $users;
	}

	while (is_object($obj = $db->fetch_object($resql))) {
		$users[(int) $obj->rowid] = array(
			'rowid' => (int) $obj->rowid,
			'lastname' => (string) $obj->lastname,
			'firstname' => (string) $obj->firstname,
			'login' => (string) $obj->login,
		);
	}
	$db->free($resql);

	return $users;
}

/**
 * Tell if a user can currently be selected as a referrer.
 *
 * @param int  $userId       User id
 * @param bool $onlyEligible Restrict to users enabled in referral settings
 * @return bool
 */
function lmdbreferralIsSelectableReferrerUser($userId, $onlyEligible = false)
{
	if ((int) $userId <= 0) {
		return false;
	}

	$users = lmdbreferralGetSelectableReferrerUsers($onlyEligible, (int) $userId);

	return isset($users[(int) $userId]);
}

/**
 * Return display label for a user referrer option.
 *
 * @param string $firstname First name
 * @param string $lastname  Last name
 * @param string $login     Login
 * @return string
 */
function lmdbreferralFormatUserReferrerLabel($firstname, $lastname, $login)
{
	$label = trim((string) $firstname.' '.(string) $lastname);

	return $label !== '' ? $label : (string) $login;
}

/**
 * Return status label translation key.
 *
 * @param int $status Status
 * @return string
 */
function lmdbreferralStatusLabel($status)
{
	return ((int) $status === 9) ? 'LmdbReferralStatusCancelled' : 'LmdbReferralStatusActive';
}

/**
 * Render a Dolibarr-like status badge.
 *
 * @param int $status Status
 * @return string
 */
function lmdbreferralStatusBadge($status)
{
	global $langs;

	$label = $langs->trans(lmdbreferralStatusLabel($status));
	$css = ((int) $status === 9) ? 'badge badge-status4' : 'badge badge-status4 badge-status';

	return '<span class="'.$css.'">'.dol_escape_htmltag($label).'</span>';
}

/**
 * Parse a referrer selector value.
 *
 * @param string $value Submitted value
 * @return array{type:string,id:int}
 */
function lmdbreferralParseReferrerValue($value)
{
	$value = trim((string) $value);
	if ($value === '') {
		return array('type' => '', 'id' => 0);
	}
	$parts = explode(':', $value, 2);
	if (count($parts) !== 2 || !in_array($parts[0], array('soc', 'user'), true)) {
		return array('type' => '', 'id' => 0);
	}

	return array('type' => $parts[0], 'id' => max(0, (int) $parts[1]));
}

/**
 * Build a typed referrer selector.
 *
 * @param string $htmlname HTML name
 * @param string $selected Selected value
 * @param int    $excludeSocid Thirdparty to exclude
 * @return string
 */
function lmdbreferralSelectReferrer($htmlname, $selected = '', $excludeSocid = 0)
{
	global $db, $conf, $langs;

	$out = '<select class="flat minwidth300" name="'.dol_escape_htmltag($htmlname).'" id="'.dol_escape_htmltag($htmlname).'">';
	$out .= '<option value="">&nbsp;</option>';

	if (getDolGlobalInt('LMDBREFERRAL_ENABLE_THIRDPARTY_REFERRERS', 1)) {
		$sql = 'SELECT rowid, nom as name';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'societe';
		$sql .= ' WHERE entity IN ('.lmdbreferralGetEntitySql('societe').')';
		$sql .= ' AND status = 1';
		if ($excludeSocid > 0) {
			$sql .= ' AND rowid <> '.((int) $excludeSocid);
		}
		$sql .= ' ORDER BY nom ASC';
		$sql .= $db->plimit(500);
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			while ($obj = $db->fetch_object($resql)) {
				$value = 'soc:'.((int) $obj->rowid);
				$label = $langs->trans('ThirdParty').' - '.$obj->name;
				$out .= '<option value="'.$value.'"'.($selected === $value ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
			}
		}
	}

	if (getDolGlobalInt('LMDBREFERRAL_ALLOW_USER_REFERRERS')) {
		$referrerUsers = lmdbreferralGetSelectableReferrerUsers(true);
		if (!empty($referrerUsers)) {
			foreach ($referrerUsers as $referrerUser) {
				$label = $langs->trans('User').' - '.lmdbreferralFormatUserReferrerLabel($referrerUser['firstname'], $referrerUser['lastname'], $referrerUser['login']);
				$value = 'user:'.((int) $referrerUser['rowid']);
				$out .= '<option value="'.$value.'"'.($selected === $value ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
			}
		}
	}

	$out .= '</select>';
	if (function_exists('ajax_combobox')) {
		$out .= ajax_combobox($htmlname);
	}

	return $out;
}

/**
 * Return typed referrer display.
 *
 * @param string $type Referrer type
 * @param int    $id   Referrer id
 * @return string
 */
function lmdbreferralGetReferrerNomUrl($type, $id)
{
	global $db, $langs;

	if ($type === 'soc' && $id > 0) {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$soc = new Societe($db);
		if ($soc->fetch($id) > 0) {
			return $soc->getNomUrl(1);
		}
	}
	if ($type === 'user' && $id > 0) {
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$tmpuser = new User($db);
		if ($tmpuser->fetch($id) > 0) {
			return $tmpuser->getNomUrl(-1);
		}
	}

	return '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
}

/**
 * Print no record row.
 *
 * @param int $colspan Column count
 * @return void
 */
function lmdbreferralPrintNoRecordRow($colspan)
{
	global $langs;

	print '<tr class="oddeven"><td colspan="'.((int) $colspan).'">';
	print '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
	print '</td></tr>';
}

/**
 * Cancel selected referral links with the same business guards as single-link actions.
 *
 * @param DoliDB     $db         Database handler
 * @param User       $user       Current user
 * @param array<int|string,mixed> $ids        Selected ids
 * @param string     $whereExtra Optional SQL condition using alias l
 * @return array{done:int,errors:array<int,string>}
 */
function lmdbreferralMassCancelLinks($db, $user, $ids, $whereExtra = '')
{
	global $langs;

	$result = array('done' => 0, 'errors' => array());
	if (!lmdbreferralCanDo($user, 'cancel')) {
		$result['errors'][] = $langs->trans('NotEnoughPermissions');
		return $result;
	}
	if (!is_array($ids) || empty($ids)) {
		$result['errors'][] = $langs->trans('NoRecordSelected');
		return $result;
	}

	dol_include_once('/lmdbreferral/class/lmdbreferralservice.class.php');
	$service = new LmdbReferralService($db);
	$seen = array();
	foreach ($ids as $id) {
		$linkId = (int) $id;
		if ($linkId <= 0 || isset($seen[$linkId])) {
			continue;
		}
		$seen[$linkId] = true;

		$sql = 'SELECT l.rowid, l.ref, l.fk_soc_filleul, l.status';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' WHERE l.rowid = '.$linkId;
		$sql .= ' AND l.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		if ($whereExtra !== '') {
			$sql .= ' AND ('.$whereExtra.')';
		}
		$sql .= $db->plimit(1);
		$resql = $db->query($sql);
		if (!$resql) {
			$result['errors'][] = $db->lasterror();
			continue;
		}
		$obj = $db->fetch_object($resql);
		if (!$obj) {
			$result['errors'][] = $langs->trans('LmdbReferralMassCancelUnavailable', $linkId);
			continue;
		}
		$linkRef = !empty($obj->ref) ? (string) $obj->ref : (string) $linkId;
		if ((int) $obj->status !== LmdbReferralLink::STATUS_ACTIVE) {
			continue;
		}
		if ($service->isLockedBySignedProposal((int) $obj->fk_soc_filleul)) {
			$result['errors'][] = $langs->trans('LmdbReferralMassCancelLocked', $linkRef);
			continue;
		}
		if ($service->cancelLink($linkId, $user) < 0) {
			$message = $service->error !== '' ? $langs->trans($service->error) : $langs->trans('Error');
			if (!empty($service->errors)) {
				$message .= ' '.implode(', ', $service->errors);
			}
			$result['errors'][] = $message;
			continue;
		}
		$result['done']++;
	}

	return $result;
}

/**
 * Delete selected referral links.
 *
 * @param DoliDB     $db         Database handler
 * @param User       $user       Current user
 * @param array<int|string,mixed> $ids        Selected ids
 * @param string     $whereExtra Optional SQL condition using alias l
 * @return array{done:int,errors:array<int,string>}
 */
function lmdbreferralMassDeleteLinks($db, $user, $ids, $whereExtra = '')
{
	global $langs;

	$result = array('done' => 0, 'errors' => array());
	if (!lmdbreferralCanDo($user, 'delete')) {
		$result['errors'][] = $langs->trans('NotEnoughPermissions');
		return $result;
	}
	if (!is_array($ids) || empty($ids)) {
		$result['errors'][] = $langs->trans('NoRecordSelected');
		return $result;
	}

	dol_include_once('/lmdbreferral/class/lmdbreferralservice.class.php');
	$service = new LmdbReferralService($db);
	$seen = array();
	foreach ($ids as $id) {
		$linkId = (int) $id;
		if ($linkId <= 0 || isset($seen[$linkId])) {
			continue;
		}
		$seen[$linkId] = true;

		$sql = 'SELECT l.rowid, l.ref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
		$sql .= ' WHERE l.rowid = '.$linkId;
		$sql .= ' AND l.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
		if ($whereExtra !== '') {
			$sql .= ' AND ('.$whereExtra.')';
		}
		$sql .= $db->plimit(1);
		$resql = $db->query($sql);
		if (!$resql) {
			$result['errors'][] = $db->lasterror();
			continue;
		}
		$obj = $db->fetch_object($resql);
		if (!$obj) {
			$result['errors'][] = $langs->trans('LmdbReferralMassDeleteUnavailable', $linkId);
			continue;
		}

		if ($service->deleteLink($linkId, $user) < 0) {
			$message = $service->error !== '' ? $langs->trans($service->error) : $langs->trans('Error');
			if (!empty($service->errors)) {
				$message .= ' '.implode(', ', $service->errors);
			}
			$result['errors'][] = $message;
			continue;
		}
		$result['done']++;
	}

	return $result;
}

/**
 * Return the native Multicompany entity badge markup.
 *
 * @param int    $entityId    Entity id
 * @param string $entityLabel Entity label
 * @return string
 */
function lmdbreferralMulticompanyEntityBadge($entityId, $entityLabel = '')
{
	$label = $entityLabel !== '' ? $entityLabel : (string) $entityId;

	return '<div class="refidno multicompany-entity-card-container">'
		.'<span class="fa fa-globe"></span>'
		.'<span class="multiselect-selected-title-text">'.dol_escape_htmltag($label).'</span>'
		.'</div>';
}

/**
 * Return the signed proposal references without the redundant count badge.
 *
 * @param int         $signedCount Number of signed proposals
 * @param string|null $propalRefs  Proposal refs
 * @return string
 */
function lmdbreferralFormatSignedProposalRefs($signedCount, $propalRefs)
{
	global $langs;

	if ((int) $signedCount <= 0) {
		return '<span class="opacitymedium">'.$langs->trans('No').'</span>';
	}

	$refs = trim((string) $propalRefs);
	if ($refs === '') {
		return '<span class="opacitymedium">'.$langs->trans('Yes').'</span>';
	}

	return dol_escape_htmltag($refs);
}

/**
 * Round a monetary amount using Dolibarr total price precision.
 *
 * @param int|float|string|null $amount Amount
 * @return float
 */
function lmdbreferralRoundAmount($amount)
{
	return (float) price2num($amount, 'MT');
}

/**
 * Format a monetary amount using Dolibarr total price precision and currency.
 *
 * @param int|float|string|null $amount   Amount
 * @param Translate|string      $outlangs Output language or empty string
 * @return string
 */
function lmdbreferralFormatAmount($amount, $outlangs = '')
{
	return price(lmdbreferralRoundAmount($amount), 0, $outlangs, 1, -1, 'MT', 'auto');
}

/**
 * Print individual statistics for a referral link.
 *
 * @param array{
 *     is_transformed?: bool,
 *     is_locked?: bool,
 *     signed_propals?: int,
 *     amount_ht?: float,
 *     amount_ttc?: float,
 *     average_basket_ht?: float,
 *     first_signature_date?: string,
 *     last_signature_date?: string,
 *     days_to_first_signature?: int|null,
 *     days_to_first_signature_start_date?: string,
 *     days_to_first_signature_end_date?: string,
 *     age_days?: int,
 *     age_start_date?: string,
 *     age_end_date?: string
 * } $stats Link statistics
 * @return void
 */
function lmdbreferralPrintLinkStatsBlock(array $stats)
{
	global $langs;

	$isTransformed = !empty($stats['is_transformed']);
	$isLocked = !empty($stats['is_locked']);
	$daysToFirstSignature = array_key_exists('days_to_first_signature', $stats) ? $stats['days_to_first_signature'] : null;

	$transformationLabel = $isTransformed ? $langs->trans('LmdbReferralConverted') : $langs->trans('LmdbReferralToFollow');
	$transformationCss = $isTransformed ? 'badge badge-status4 badge-status' : 'badge badge-status0';
	$lockLabel = $isLocked ? $langs->trans('LmdbReferralCommercialLockActive') : $langs->trans('LmdbReferralCommercialLockInactive');
	$lockCss = $isLocked ? 'badge badge-status4 badge-status' : 'badge badge-status0';

	print '<div class="fichecenter">';
	print load_fiche_titre($langs->trans('LmdbReferralLinkStats'), '', '');
	print '<div class="fichehalfleft">';
	print '<table class="noborder centpercent">';
	print '<tr class="oddeven">';
	print '<td class="titlefield">'.$langs->trans('LmdbReferralLinkConversionStatus').'</td>';
	print '<td class="nowrap"><span class="'.$transformationCss.'">'.dol_escape_htmltag($transformationLabel).'</span></td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('LmdbReferralGeneratedCAHT').'</td>';
	print '<td class="right nowrap">'.lmdbreferralFormatAmount($stats['amount_ht'] ?? 0.0).'</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('LmdbReferralAverageBasketHT').'</td>';
	print '<td class="right nowrap">'.lmdbreferralFormatAmount($stats['average_basket_ht'] ?? 0.0).'</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('LmdbReferralLastSignatureDate').'</td>';
	print '<td class="nowrap">'.lmdbreferralFormatLinkStatsDate((string) ($stats['last_signature_date'] ?? '')).'</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('LmdbReferralLinkAgeDays').'</td>';
	print '<td class="nowrap">'.lmdbreferralFormatLinkStatsDays((int) ($stats['age_days'] ?? 0), (string) ($stats['age_start_date'] ?? ''), (string) ($stats['age_end_date'] ?? '')).'</td>';
	print '</tr>';
	print '</table>';
	print '</div>';

	print '<div class="fichehalfright">';
	print '<table class="noborder centpercent">';
	print '<tr class="oddeven">';
	print '<td class="titlefield">'.$langs->trans('LmdbReferralSignedPropalsCount').'</td>';
	print '<td class="right nowrap">'.((int) ($stats['signed_propals'] ?? 0)).'</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('LmdbReferralSignedAmountTTC').'</td>';
	print '<td class="right nowrap">'.lmdbreferralFormatAmount($stats['amount_ttc'] ?? 0.0).'</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('LmdbReferralFirstSignatureDate').'</td>';
	print '<td class="nowrap">'.lmdbreferralFormatLinkStatsDate((string) ($stats['first_signature_date'] ?? '')).'</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('LmdbReferralDaysToFirstSignature').'</td>';
	print '<td class="nowrap">'.lmdbreferralFormatLinkStatsDays($daysToFirstSignature, (string) ($stats['days_to_first_signature_start_date'] ?? ''), (string) ($stats['days_to_first_signature_end_date'] ?? '')).'</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td class="titlefield">'.$langs->trans('LmdbReferralCommercialLockStatus').'</td>';
	print '<td class="nowrap"><span class="'.$lockCss.'">'.dol_escape_htmltag($lockLabel).'</span></td>';
	print '</tr>';
	print '</table>';
	print '</div>';
	print '<div class="clearboth"></div>';
	print '</div>';
	print '<br>';
}

/**
 * Format an optional SQL date for link statistics.
 *
 * @param string $date SQL date
 * @return string
 */
function lmdbreferralFormatLinkStatsDate($date)
{
	global $db, $langs;

	if ($date === '') {
		return '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
	}

	return dol_escape_htmltag(dol_print_date($db->jdate($date), 'dayhour'));
}

/**
 * Format an optional day count for link statistics as a calendar duration.
 *
 * @param int|null $days      Number of days
 * @param string   $startDate SQL start date
 * @param string   $endDate   SQL end date
 * @return string
 */
function lmdbreferralFormatLinkStatsDays($days, $startDate = '', $endDate = '')
{
	global $langs;

	if ($days === null) {
		return '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
	}

	return dol_escape_htmltag(lmdbreferralFormatDurationFromDays($days, $startDate, $endDate, $langs));
}

/**
 * Format a day count as a years/months/days duration.
 *
 * @param int|null         $days      Number of days
 * @param string           $startDate SQL start date
 * @param string           $endDate   SQL end date
 * @param Translate|string $outlangs  Output language or empty string
 * @return string
 */
function lmdbreferralFormatDurationFromDays($days, $startDate = '', $endDate = '', $outlangs = '')
{
	global $langs;

	$outputlangs = is_object($outlangs) ? $outlangs : $langs;
	if ($days === null) {
		return lmdbreferralDurationTrans($outputlangs, 'NotAvailable');
	}

	$parts = null;
	$startTimestamp = lmdbreferralDurationDateToTimestamp($startDate);
	$endTimestamp = lmdbreferralDurationDateToTimestamp($endDate);
	if ($startTimestamp > 0 && $endTimestamp > 0) {
		$parts = lmdbreferralGetCalendarDurationParts($startTimestamp, $endTimestamp);
	}
	if (!is_array($parts)) {
		$parts = lmdbreferralGetApproxDurationParts((int) $days);
	}

	return lmdbreferralFormatDurationParts($parts, $outputlangs);
}

/**
 * Convert a Dolibarr SQL date or timestamp to a timestamp.
 *
 * @param mixed $date Date value
 * @return int
 */
function lmdbreferralDurationDateToTimestamp($date)
{
	global $db;

	if (empty($date)) {
		return 0;
	}
	if (is_numeric($date)) {
		return (int) $date;
	}
	if (!is_object($db)) {
		return 0;
	}

	return (int) $db->jdate($date);
}

/**
 * Return calendar duration parts from timestamps.
 *
 * @param int $startTimestamp Start timestamp
 * @param int $endTimestamp   End timestamp
 * @return array{years:int,months:int,days:int}
 */
function lmdbreferralGetCalendarDurationParts($startTimestamp, $endTimestamp)
{
	if ($startTimestamp <= 0 || $endTimestamp <= 0) {
		return lmdbreferralGetApproxDurationParts(0);
	}
	if ($endTimestamp < $startTimestamp) {
		$endTimestamp = $startTimestamp;
	}

	$start = new DateTimeImmutable('@'.$startTimestamp);
	$end = new DateTimeImmutable('@'.$endTimestamp);
	$interval = $start->diff($end);

	return array(
		'years' => (int) $interval->y,
		'months' => (int) $interval->m,
		'days' => (int) $interval->d,
	);
}

/**
 * Return approximate duration parts from a number of days.
 *
 * @param int $days Number of days
 * @return array{years:int,months:int,days:int}
 */
function lmdbreferralGetApproxDurationParts($days)
{
	$remainingDays = max(0, (int) $days);
	$years = intdiv($remainingDays, 365);
	$remainingDays -= $years * 365;
	$months = intdiv($remainingDays, 30);
	$remainingDays -= $months * 30;

	return array(
		'years' => $years,
		'months' => $months,
		'days' => $remainingDays,
	);
}

/**
 * Format duration parts.
 *
 * @param array{years:int,months:int,days:int} $parts    Duration parts
 * @param Translate|string                     $outlangs Output language or empty string
 * @return string
 */
function lmdbreferralFormatDurationParts(array $parts, $outlangs = '')
{
	global $langs;

	$outputlangs = is_object($outlangs) ? $outlangs : $langs;
	$labels = array();
	$units = array(
		'years' => array('LmdbReferralDurationYear', 'LmdbReferralDurationYears'),
		'months' => array('LmdbReferralDurationMonth', 'LmdbReferralDurationMonths'),
		'days' => array('LmdbReferralDurationDay', 'LmdbReferralDurationDays'),
	);

	foreach ($units as $unit => $translationKeys) {
		$value = max(0, (int) $parts[$unit]);
		if ($value <= 0) {
			continue;
		}
		$labels[] = $value.' '.lmdbreferralDurationTrans($outputlangs, $value > 1 ? $translationKeys[1] : $translationKeys[0]);
	}

	if (empty($labels)) {
		$labels[] = '0 '.lmdbreferralDurationTrans($outputlangs, 'LmdbReferralDurationDays');
	}

	return implode(', ', $labels);
}

/**
 * Return a translation without HTML entities when possible.
 *
 * @param Translate|string $outlangs Output language or empty string
 * @param string           $key      Translation key
 * @return string
 */
function lmdbreferralDurationTrans($outlangs, $key)
{
	global $langs;

	$outputlangs = is_object($outlangs) ? $outlangs : $langs;
	if (is_object($outputlangs) && method_exists($outputlangs, 'transnoentitiesnoconv')) {
		return $outputlangs->transnoentitiesnoconv($key);
	}
	if (is_object($outputlangs)) {
		return html_entity_decode($outputlangs->trans($key), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	return $key;
}
