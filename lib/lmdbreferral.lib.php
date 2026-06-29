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
		array(dol_buildpath('/lmdbreferral/admin/compatibility.php', 1), $langs->trans('Compatibility'), 'compatibility'),
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
 * Return document directory for a referral link.
 *
 * @param LmdbReferralLink|object $object Referral link
 * @return string
 */
function lmdbreferralGetLinkDocumentDir($object)
{
	global $conf;

	if (!function_exists('dol_sanitizeFileName')) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	}

	$upload_dir = '';
	if (function_exists('getMultidirOutput')) {
		$upload_dir = (string) getMultidirOutput($object, 'lmdbreferral', 1);
	}

	if (empty($upload_dir)) {
		$objectEntity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$moduleOutput = '';
		if (!empty($conf->lmdbreferral->multidir_output[$objectEntity])) {
			$moduleOutput = (string) $conf->lmdbreferral->multidir_output[$objectEntity];
		} elseif (!empty($conf->lmdbreferral->dir_output)) {
			$moduleOutput = (string) $conf->lmdbreferral->dir_output;
		}
		if ($moduleOutput !== '') {
			$upload_dir = $moduleOutput.'/'.$object->element.'/'.dol_sanitizeFileName((string) $object->ref);
		}
	}

	return $upload_dir;
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
	if (empty($token) || ($token !== newToken() && $token !== currentToken())) {
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
