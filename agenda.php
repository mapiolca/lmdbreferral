<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/cactioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

/**
 * Return translated label with a module fallback when a core key is absent.
 *
 * @param string $key Core translation key
 * @param string $fallback Module fallback key
 * @return string
 */
function lmdbreferralAgendaTrans($key, $fallback)
{
	global $langs;

	$label = $langs->trans($key);
	if ($label === $key) {
		$label = $langs->trans($fallback);
	}

	return $label;
}

/**
 * Render a user link with an optional timestamp.
 *
 * @param int              $fkUser User id
 * @param int|string|null  $date   Dolibarr timestamp or SQL date
 * @return string
 */
function lmdbreferralAgendaUserDate($fkUser, $date = null)
{
	global $db, $langs;

	static $cache = array();

	if ((int) $fkUser <= 0) {
		return '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
	}

	if (!isset($cache[(int) $fkUser])) {
		$userstatic = new User($db);
		$cache[(int) $fkUser] = ($userstatic->fetch((int) $fkUser) > 0)
			? $userstatic->getNomUrl(-1, '', 0, 0, 16, 0, 'firstelselast', '')
			: '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
	}

	$out = $cache[(int) $fkUser];
	if (!empty($date)) {
		$timestamp = is_numeric($date) ? (int) $date : $db->jdate($date);
		if ($timestamp > 0) {
			$out .= ' <span class="opacitymedium">- '.dol_print_date($timestamp, 'dayhour').'</span>';
		}
	}

	return $out;
}

$langs->loadLangs(array('agenda', 'companies', 'other', 'users', 'lmdbreferral@lmdbreferral'));

if (!isModEnabled('lmdbreferral')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$contextpage = GETPOST('contextpage', 'aZ09') ? GETPOST('contextpage', 'aZ09') : getDolDefaultContextPage(__FILE__);
$buttonSearch = (GETPOST('button_search', 'alpha') || GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha'));
$buttonRemoveFilter = (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha'));

if (GETPOST('actioncode', 'array') && !$buttonRemoveFilter) {
	$actioncode = GETPOST('actioncode', 'array', 3);
	if (!is_array($actioncode) || !count($actioncode)) {
		$actioncode = '0';
	}
} elseif (!$buttonRemoveFilter) {
	$actioncode = GETPOST('actioncode', 'alpha', 3) ? GETPOST('actioncode', 'alpha', 3) : (GETPOST('actioncode') == '0' ? '0' : getDolGlobalString('AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT'));
} else {
	$actioncode = '';
}
$searchRowid = $buttonRemoveFilter ? '' : GETPOST('search_rowid', 'alphanohtml');
$searchAgendaLabel = $buttonRemoveFilter ? '' : GETPOST('search_agenda_label', 'alphanohtml');
$searchComplete = $buttonRemoveFilter ? '' : GETPOST('search_complete', 'alpha');
$searchFilterUser = $buttonRemoveFilter ? 0 : GETPOSTINT('search_filtert');
$dateeventStartYear = $buttonRemoveFilter ? 0 : GETPOSTINT('dateevent_startyear');
$dateeventStartMonth = $buttonRemoveFilter ? 0 : GETPOSTINT('dateevent_startmonth');
$dateeventStartDay = $buttonRemoveFilter ? 0 : GETPOSTINT('dateevent_startday');
$dateeventEndYear = $buttonRemoveFilter ? 0 : GETPOSTINT('dateevent_endyear');
$dateeventEndMonth = $buttonRemoveFilter ? 0 : GETPOSTINT('dateevent_endmonth');
$dateeventEndDay = $buttonRemoveFilter ? 0 : GETPOSTINT('dateevent_endday');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : (int) $conf->liste_limit;
if ($limit <= 0) {
	$limit = 20;
}
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0 || $buttonSearch || $buttonRemoveFilter) {
	$page = 0;
}
$offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = strtoupper(GETPOST('sortorder', 'aZ09comma'));
if (!$sortfield) {
	$sortfield = 'a.datep,a.id';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}
$allowedSortFields = array('a.id', 'a.datep,a.id', 'u.lastname,u.firstname', 'c.libelle', 'a.label', 'a.percent');
if (!in_array($sortfield, $allowedSortFields, true)) {
	$sortfield = 'a.datep,a.id';
}
if (!in_array($sortorder, array('ASC', 'DESC'), true)) {
	$sortorder = 'DESC';
}

$object = new LmdbReferralLink($db);
if ($id > 0 && $object->fetch($id) <= 0) {
	recordNotFound();
}
if (empty($object->id)) {
	accessforbidden();
}
if (!lmdbreferralCanDo($user, 'read') && !lmdbreferralCanReadOwnLink($user, $object)) {
	accessforbidden();
}

$agendaEnabled = isModEnabled('agenda');
$canReadAllAgenda = !empty($user->admin) || (method_exists($user, 'hasRight') && $user->hasRight('agenda', 'allactions', 'read'));
$canReadOwnAgenda = !empty($user->admin) || (method_exists($user, 'hasRight') && $user->hasRight('agenda', 'myactions', 'read'));
if ($agendaEnabled && !$canReadOwnAgenda && !$canReadAllAgenda) {
	accessforbidden();
}

$form = new Form($db);
$formactions = new FormActions($db);
$socid = (int) $object->fk_soc_filleul;
$agendaElementType = lmdbreferralGetAgendaElementType();

llxHeader('', $langs->trans('LmdbReferralLink').' - '.$object->ref);

$head = lmdbreferralLinkPrepareHead($object);
print dol_get_fiche_head($head, 'agenda', $langs->trans('LmdbReferralLink'), -1, 'fa-handshake');

$linkback = '<a href="'.dol_buildpath('/lmdbreferral/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', '');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldmiddle">'.dol_escape_htmltag(lmdbreferralAgendaTrans('CreatedBy', 'LmdbReferralCreatedBy')).'</td><td>';
print lmdbreferralAgendaUserDate((int) $object->fk_user_author, $object->date_creation);
print '</td></tr>';
print '<tr><td>'.dol_escape_htmltag(lmdbreferralAgendaTrans('ModifiedBy', 'LmdbReferralModifiedBy')).'</td><td>';
print lmdbreferralAgendaUserDate((int) $object->fk_user_modif, $object->date_modification);
print '</td></tr>';
print '</table>';
print '</div>';
print '<div class="clearboth"></div>';

print dol_get_fiche_end();

if (!$agendaEnabled) {
	print '<div class="opacitymedium">'.$langs->trans('LmdbReferralAgendaModuleDisabled').'</div>';
	llxFooter();
	$db->close();
	exit;
}

$out = '&origin='.urlencode($agendaElementType).'&originid='.urlencode((string) $object->id);
$out .= '&backtopage='.urlencode($_SERVER['PHP_SELF'].'?id='.(int) $object->id);
if ($socid > 0) {
	$out .= '&socid='.urlencode((string) $socid);
}

$morehtmlright = '';
$canCreateAgenda = !empty($user->admin) || (method_exists($user, 'hasRight') && ($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create')));
if ($canCreateAgenda) {
	$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out);
} else {
	$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out, '', 0);
}

$tmsStart = '';
$tmsEnd = '';
if (!empty($dateeventStartYear) && !empty($dateeventStartMonth) && !empty($dateeventStartDay)) {
	$tmsStart = dol_mktime(0, 0, 0, $dateeventStartMonth, $dateeventStartDay, $dateeventStartYear, 'tzuserrel');
}
if (!empty($dateeventEndYear) && !empty($dateeventEndMonth) && !empty($dateeventEndDay)) {
	$tmsEnd = dol_mktime(23, 59, 59, $dateeventEndMonth, $dateeventEndDay, $dateeventEndYear, 'tzuserrel');
}

$filters = array(
	'search_agenda_label' => $searchAgendaLabel,
	'search_rowid' => $searchRowid,
	'search_filtert' => ($canReadAllAgenda ? (string) $searchFilterUser : ''),
	'search_complete' => $searchComplete,
);

$param = '&id='.(int) $object->id;
if (!empty($contextpage) && $contextpage != getDolDefaultContextPage(__FILE__)) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != (int) $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
if (is_array($actioncode)) {
	foreach ($actioncode as $code) {
		if ((string) $code !== '') {
			$param .= '&actioncode[]='.urlencode((string) $code);
		}
	}
} elseif ((string) $actioncode !== '') {
	$param .= '&actioncode='.urlencode((string) $actioncode);
}
if ($searchRowid !== '') {
	$param .= '&search_rowid='.urlencode((string) $searchRowid);
}
if ($searchAgendaLabel !== '') {
	$param .= '&search_agenda_label='.urlencode($searchAgendaLabel);
}
if ($canReadAllAgenda && $searchFilterUser > 0) {
	$param .= '&search_filtert='.((int) $searchFilterUser);
}
if ($searchComplete !== '') {
	$param .= '&search_complete='.urlencode($searchComplete);
}
if (!empty($dateeventStartYear) && !empty($dateeventStartMonth) && !empty($dateeventStartDay)) {
	$param .= '&dateevent_startyear='.((int) $dateeventStartYear).'&dateevent_startmonth='.((int) $dateeventStartMonth).'&dateevent_startday='.((int) $dateeventStartDay);
}
if (!empty($dateeventEndYear) && !empty($dateeventEndMonth) && !empty($dateeventEndDay)) {
	$param .= '&dateevent_endyear='.((int) $dateeventEndYear).'&dateevent_endmonth='.((int) $dateeventEndMonth).'&dateevent_endday='.((int) $dateeventEndDay);
}

$sqlselect = 'SELECT DISTINCT a.id, a.label, a.datep as dp, a.datep2 as dp2, a.percent, a.fk_element, a.elementtype, a.fk_contact, a.code,';
$sqlselect .= ' c.code as acode, c.libelle as alabel, c.picto as apicto,';
$sqlselect .= ' u.rowid as user_id, u.login as user_login, u.photo as user_photo, u.firstname as user_firstname, u.lastname as user_lastname';
$sqlfrom = ' FROM '.MAIN_DB_PREFIX.'actioncomm as a';
$sqlfrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as u ON u.rowid = a.fk_user_action';
$sqlfrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_actioncomm as c ON a.fk_action = c.id';
if (!$canReadAllAgenda) {
	$sqlfrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'actioncomm_resources as ar ON ar.fk_actioncomm = a.id';
	$sqlfrom .= " AND ar.element_type = 'user' AND ar.fk_element = ".((int) $user->id);
}
$sqlwhere = ' WHERE a.entity IN ('.lmdbreferralGetEntitySql('agenda').')';
$sqlwhere .= ' AND a.fk_element = '.((int) $object->id);
$sqlwhere .= " AND a.elementtype = '".$db->escape($agendaElementType)."'";
if (!$canReadAllAgenda) {
	$sqlwhere .= ' AND (a.fk_user_author = '.((int) $user->id).' OR a.fk_user_action = '.((int) $user->id).' OR ar.fk_element = '.((int) $user->id).')';
}
if (!empty($tmsStart) && !empty($tmsEnd)) {
	$sqlwhere .= " AND ((a.datep BETWEEN '".$db->idate($tmsStart)."' AND '".$db->idate($tmsEnd)."') OR (a.datep2 BETWEEN '".$db->idate($tmsStart)."' AND '".$db->idate($tmsEnd)."'))";
} elseif (empty($tmsStart) && !empty($tmsEnd)) {
	$sqlwhere .= " AND ((a.datep <= '".$db->idate($tmsEnd)."') OR (a.datep2 <= '".$db->idate($tmsEnd)."'))";
} elseif (!empty($tmsStart) && empty($tmsEnd)) {
	$sqlwhere .= " AND ((a.datep >= '".$db->idate($tmsStart)."') OR (a.datep2 >= '".$db->idate($tmsStart)."'))";
}
if (is_array($actioncode) && !empty($actioncode)) {
	$tmpconditions = array();
	foreach ($actioncode as $code) {
		if ((string) $code === '-1' || (string) $code === '') {
			continue;
		}
		$tmpcondition = '';
		if (function_exists('addEventTypeSQL')) {
			addEventTypeSQL($tmpcondition, (string) $code, '');
		}
		if ($tmpcondition !== '') {
			$tmpconditions[] = trim($tmpcondition);
		}
	}
	if (!empty($tmpconditions)) {
		$sqlwhere .= ' AND ('.implode(' OR ', $tmpconditions).')';
	}
} elseif (!empty($actioncode) && $actioncode != '-1' && function_exists('addEventTypeSQL')) {
	addEventTypeSQL($sqlwhere, $actioncode);
}
if (function_exists('addOtherFilterSQL')) {
	addOtherFilterSQL($sqlwhere, '', dol_now('tzuser'), $filters);
}

$nbEvent = 0;
$sqlcount = 'SELECT COUNT(DISTINCT a.id) as nb'.$sqlfrom.$sqlwhere;
$resqlcount = $db->query($sqlcount);
if ($resqlcount) {
	$objCount = $db->fetch_object($resqlcount);
	$nbEvent = !empty($objCount->nb) ? (int) $objCount->nb : 0;
	$db->free($resqlcount);
} else {
	dol_syslog('lmdbreferral agenda count failed for link id='.((int) $object->id).' error='.$db->lasterror(), LOG_ERR);
	setEventMessages($db->lasterror(), null, 'errors');
}
if ($page > 0 && $offset >= $nbEvent) {
	$page = 0;
	$offset = 0;
}

$sqllist = $sqlselect.$sqlfrom.$sqlwhere.$db->order($sortfield, $sortorder);
if ($limit) {
	$sqllist .= $db->plimit($limit + 1, $offset);
}
$resql = $db->query($sqllist);
$num = ($resql ? $db->num_rows($resql) : 0);

$titlelist = $langs->trans('Actions').'<span class="opacitymedium colorblack paddingleft">('.((int) $nbEvent).')</span>';

print '<form name="listactionsfilter" class="listactionsfilter" action="'.$_SERVER['PHP_SELF'].'" method="GET">';
print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
if (!empty($contextpage) && $contextpage != getDolDefaultContextPage(__FILE__)) {
	print '<input type="hidden" name="contextpage" value="'.dol_escape_htmltag($contextpage).'">';
}

print_barre_liste($titlelist, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $nbEvent, 'object_action', 0, $morehtmlright, '', $limit, 0, 0, 1);

if (!$resql) {
	dol_print_error($db);
} else {
	$caction = new CActionComm($db);
	$arraylist = $caction->liste_array(1, 'code', '', (getDolGlobalString('AGENDA_USE_EVENT_TYPE') ? 0 : 1), '', 1);
	$userlinkcache = array();
	$contactlinkcache = array();
	$elementlinkcache = array();
	$percent = $searchComplete !== '' ? $searchComplete : -1;
	if ((string) $searchComplete === '0') {
		$percent = '0';
	} elseif ((int) $searchComplete === 100) {
		$percent = '100';
	}

	print '<div class="div-table-responsive-no-min">';
	print '<table class="tagtable nobottomiftotal liste listwithfilterbefore centpercent">';

	print '<tr class="liste_titre_filter">';
	print '<td class="liste_titre maxwidthsearch center actioncolumn">'.$form->showFilterButtons('left').'</td>';
	print '<td class="liste_titre"><input type="text" class="width50" name="search_rowid" value="'.dol_escape_htmltag((string) $searchRowid).'"></td>';
	print '<td class="liste_titre center">'.$form->selectDateToDate($tmsStart, $tmsEnd, 'dateevent', 1).'</td>';
	print '<td class="liste_titre">';
	print $form->select_dolusers(($canReadAllAgenda && $searchFilterUser > 0 ? $searchFilterUser : (!$canReadAllAgenda ? $user->id : '')), 'search_filtert', 1, null, (int) !$canReadAllAgenda, '', '', '0', 0, 0, '', 2, '', 'minwidth100 maxwidth250 widthcentpercentminusx');
	print '</td>';
	print '<td class="liste_titre">';
	print $formactions->select_type_actions($actioncode, 'actioncode', '', getDolGlobalString('AGENDA_USE_EVENT_TYPE') ? -1 : 1, 0, (getDolGlobalString('AGENDA_USE_MULTISELECT_TYPE') ? 1 : 0), 1, 'selecttype combolargeelem minwidth100 maxwidth150', 1);
	print '</td>';
	print '<td class="liste_titre maxwidth100onsmartphone"><input type="text" class="maxwidth125" name="search_agenda_label" value="'.dol_escape_htmltag((string) $searchAgendaLabel).'"></td>';
	print '<td class="liste_titre"></td>';
	print '<td class="liste_titre"></td>';
	print '<td class="liste_titre parentonrightofpage">'.$formactions->form_select_status_action('formaction', $percent, 1, 'search_complete', 1, 2, 'search_status width100 onrightofpage', 1).'</td>';
	print '</tr>';

	print '<tr class="liste_titre">';
	print '<th class="liste_titre maxwidthsearch center actioncolumn"></th>';
	print getTitleFieldOfList('Ref', 0, $_SERVER['PHP_SELF'], 'a.id', '', $param, '', $sortfield, $sortorder);
	print getTitleFieldOfList('Date', 0, $_SERVER['PHP_SELF'], 'a.datep,a.id', '', $param, '', $sortfield, $sortorder, 'center ');
	print getTitleFieldOfList('Owner', 0, $_SERVER['PHP_SELF'], 'u.lastname,u.firstname', '', $param, '', $sortfield, $sortorder);
	print getTitleFieldOfList('Type', 0, $_SERVER['PHP_SELF'], 'c.libelle', '', $param, '', $sortfield, $sortorder);
	print getTitleFieldOfList('Title', 0, $_SERVER['PHP_SELF'], 'a.label', '', $param, '', $sortfield, $sortorder);
	print getTitleFieldOfList('ActionOnContact', 0, $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'tdoverflowmax125 ', 0, '', 0);
	print getTitleFieldOfList(lmdbreferralAgendaTrans('LinkedObject', 'LmdbReferralLinkedObject'), 0, $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder);
	print getTitleFieldOfList('Status', 0, $_SERVER['PHP_SELF'], 'a.percent', '', $param, '', $sortfield, $sortorder, 'center ');
	print '</tr>';

	$i = 0;
	$imaxinloop = min($num, $limit);
	while ($i < $imaxinloop) {
		$obj = $db->fetch_object($resql);
		if (empty($obj)) {
			break;
		}

		$actionstatic = new ActionComm($db);
		$actionstatic->fetch((int) $obj->id);
		$actionstatic->id = (int) $obj->id;
		$actionstatic->ref = (string) $obj->id;
		$actionstatic->label = (string) $obj->label;
		$actionstatic->datep = $db->jdate($obj->dp);
		$actionstatic->datef = $db->jdate($obj->dp2);
		$actionstatic->percentage = (int) $obj->percent;
		$actionstatic->code = (string) $obj->code;
		$actionstatic->type_code = (string) $obj->acode;
		$actionstatic->type_label = (string) $obj->alabel;
		$actionstatic->type_picto = (string) $obj->apicto;
		$actionstatic->fetchResources();

		print '<tr class="oddeven">';
		print '<td class="center actioncolumn"></td>';
		print '<td class="nowraponall">'.$actionstatic->getNomUrl(1, -1).'</td>';

		print '<td class="center nowraponall nopaddingtopimp nopaddingbottomimp">';
		$tmpa = dol_getdate($actionstatic->datep);
		$tmpb = !empty($actionstatic->datef) ? dol_getdate($actionstatic->datef) : $tmpa;
		if ($tmpa['mday'] == $tmpb['mday'] && $tmpa['mon'] == $tmpb['mon'] && $tmpa['year'] == $tmpb['year']) {
			print '<div class="center inline-block lineheightsmall">';
			print dol_print_date($actionstatic->datep, 'dayreduceformat', 'tzuserrel');
			print '<br><span class="opacitymedium hourspan">';
			print dol_print_date($actionstatic->datep, 'hourreduceformat', 'tzuserrel');
			if (!empty($actionstatic->datef) && ($tmpa['hours'] != $tmpb['hours'] || $tmpa['minutes'] != $tmpb['minutes'])) {
				print '-'.dol_print_date($actionstatic->datef, 'hourreduceformat', 'tzuserrel');
			}
			print '</span></div>';
		} else {
			print '<div class="center inline-block lineheightsmall">';
			print dol_print_date($actionstatic->datep, 'dayreduceformat', 'tzuserrel');
			print '<br><span class="opacitymedium hourspan">'.dol_print_date($actionstatic->datep, 'hourreduceformat', 'tzuserrel').'</span>';
			print '</div> - <div class="center inline-block lineheightsmall">';
			print dol_print_date($actionstatic->datef, 'dayreduceformat', 'tzuserrel');
			print '<br><span class="opacitymedium hourspan">'.dol_print_date($actionstatic->datef, 'hourreduceformat', 'tzuserrel').'</span>';
			print '</div>';
		}
		if ($actionstatic->hasDelay() && $actionstatic->percentage >= 0 && $actionstatic->percentage < 100) {
			print img_warning($langs->trans('Late')).' ';
		}
		print '</td>';

		print '<td class="tdoverflowmax125">';
		if ((int) $obj->user_id > 0) {
			if (!isset($userlinkcache[(int) $obj->user_id])) {
				$userstatic = new User($db);
				$userlinkcache[(int) $obj->user_id] = ($userstatic->fetch((int) $obj->user_id) > 0)
					? $userstatic->getNomUrl(-1, '', 0, 0, 16, 0, 'firstelselast', '')
					: dol_escape_htmltag((string) $obj->user_login);
			}
			print $userlinkcache[(int) $obj->user_id];
		}
		print '</td>';

		$labeltype = $actionstatic->type_code;
		if (!getDolGlobalString('AGENDA_USE_EVENT_TYPE') && empty($arraylist[$labeltype])) {
			$labeltype = 'AC_OTH';
		}
		if (!empty($actionstatic->code) && preg_match('/^TICKET_MSG/', $actionstatic->code)) {
			$labeltype = $langs->trans('Message');
		} else {
			if (!empty($arraylist[$labeltype])) {
				$labeltype = $arraylist[$labeltype];
			} elseif ($actionstatic->type_code === 'AC_EMAILING') {
				$langs->load('mails');
				$labeltype = $langs->trans('Emailing');
			}
			if ($actionstatic->type_code === 'AC_OTH_AUTO' && ($actionstatic->type_code !== $actionstatic->code) && $labeltype && !empty($arraylist[$actionstatic->code])) {
				$labeltype .= ' - '.$arraylist[$actionstatic->code];
			}
		}
		$labeltypelong = $labeltype.($actionstatic->type_code === 'AC_OTH_AUTO' ? ' (auto)' : '');
		print '<td class="tdoverflowmax125" title="'.dol_escape_htmltag($labeltypelong).'">';
		print $actionstatic->getTypePicto();
		print dol_trunc($labeltype, 28);
		print '</td>';

		print '<td class="tdoverflowmax300" title="'.dol_escape_htmltag($actionstatic->label).'">';
		print dol_trunc($actionstatic->label, 120);
		print '</td>';

		print '<td class="valignmiddle">';
		if (!empty($actionstatic->socpeopleassigned) && is_array($actionstatic->socpeopleassigned)) {
			foreach ($actionstatic->socpeopleassigned as $cid => $cvalue) {
				$contactid = is_array($cvalue) && !empty($cvalue['id']) ? (int) $cvalue['id'] : (int) $cid;
				if (empty($contactid) && is_numeric($cvalue)) {
					$contactid = (int) $cvalue;
				}
				if ($contactid <= 0) {
					continue;
				}
				if (!isset($contactlinkcache[$contactid])) {
					$contactstatic = new Contact($db);
					$contactlinkcache[$contactid] = ($contactstatic->fetch($contactid) > 0 ? $contactstatic->getNomUrl(-2, '', 0, '', -1, 0, 'paddingright') : '');
				}
				print $contactlinkcache[$contactid];
			}
		} elseif (!empty($obj->fk_contact)) {
			$contactid = (int) $obj->fk_contact;
			if (!isset($contactlinkcache[$contactid])) {
				$contactstatic = new Contact($db);
				$contactlinkcache[$contactid] = ($contactstatic->fetch($contactid) > 0 ? $contactstatic->getNomUrl(-2, '', 0, '', -1, 0, 'paddingright') : '');
			}
			print $contactlinkcache[$contactid];
		}
		print '</td>';

		print '<td class="tdoverflowmax200 nowraponall">';
		if (!empty($obj->elementtype) && !empty($obj->fk_element)) {
			if (!isset($elementlinkcache[$obj->elementtype])) {
				$elementlinkcache[$obj->elementtype] = array();
			}
			if (!isset($elementlinkcache[$obj->elementtype][(int) $obj->fk_element])) {
				$link = dolGetElementUrl((int) $obj->fk_element, $obj->elementtype, 1);
				if (empty($link) && $obj->elementtype === $agendaElementType && (int) $obj->fk_element === (int) $object->id) {
					$link = $object->getNomUrl(1);
				}
				$elementlinkcache[$obj->elementtype][(int) $obj->fk_element] = $link;
			}
			print $elementlinkcache[$obj->elementtype][(int) $obj->fk_element];
		}
		print '</td>';

		print '<td class="nowrap center">'.$actionstatic->LibStatut((int) $obj->percent, 2, 0, $actionstatic->datep).'</td>';
		print '</tr>';

		$i++;
	}
	if ($num == 0) {
		print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}

	print '</table>';
	print '</div>';
	$db->free($resql);
}
print '</form>';

llxFooter();
$db->close();
