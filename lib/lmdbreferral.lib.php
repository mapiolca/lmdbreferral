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

	return $object->element.'/'.dol_sanitizeFileName((string) $object->ref);
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

	$out = '<div class="refidno">';
	$out .= '<strong>'.$langs->trans('LmdbReferralReferrer').'</strong> : '.$referrerNomUrl;
	$out .= '<br><strong>'.$langs->trans('LmdbReferralReferredThirdparty').'</strong> : '.($filleulNomUrl !== '' ? $filleulNomUrl : '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>');
	$out .= '<br><strong>'.$langs->trans('DateCreation').'</strong> : '.dol_print_date($db->jdate($object->date_creation), 'dayhour');
	$out .= '</div>';

	return $out;
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
			$out .= '<optgroup label="'.dol_escape_htmltag($langs->trans('ThirdParties')).'">';
			while ($obj = $db->fetch_object($resql)) {
				$value = 'soc:'.((int) $obj->rowid);
				$out .= '<option value="'.$value.'"'.($selected === $value ? ' selected' : '').'>'.dol_escape_htmltag($obj->name).'</option>';
			}
			$out .= '</optgroup>';
		}
	}

	if (getDolGlobalInt('LMDBREFERRAL_ALLOW_USER_REFERRERS')) {
		$sql = 'SELECT u.rowid, u.lastname, u.firstname, u.login';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'user as u';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbreferral_user_eligibility as e ON e.fk_user = u.rowid';
		$sql .= ' WHERE e.entity IN ('.lmdbreferralGetEntitySql('lmdbreferralusereligibility').')';
		$sql .= ' AND e.active = 1 AND u.statut = 1';
		$sql .= ' ORDER BY u.lastname ASC, u.firstname ASC, u.login ASC';
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			$out .= '<optgroup label="'.dol_escape_htmltag($langs->trans('Users')).'">';
			while ($obj = $db->fetch_object($resql)) {
				$label = trim($obj->firstname.' '.$obj->lastname);
				if ($label === '') {
					$label = $obj->login;
				}
				$value = 'user:'.((int) $obj->rowid);
				$out .= '<option value="'.$value.'"'.($selected === $value ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
			}
			$out .= '</optgroup>';
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
 *     age_days?: int
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
	print '<td class="nowrap">'.lmdbreferralFormatLinkStatsDays((int) ($stats['age_days'] ?? 0)).'</td>';
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
	print '<td class="nowrap">'.lmdbreferralFormatLinkStatsDays($daysToFirstSignature).'</td>';
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
 * Format an optional day count for link statistics.
 *
 * @param int|null $days Number of days
 * @return string
 */
function lmdbreferralFormatLinkStatsDays($days)
{
	global $langs;

	if ($days === null) {
		return '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
	}

	return ((int) $days).' '.$langs->trans('Days');
}
