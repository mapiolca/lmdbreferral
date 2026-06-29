<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

$langs->loadLangs(array('companies', 'propal', 'lmdbreferral@lmdbreferral'));

$permissiontoread = lmdbreferralCanDo($user, 'read');
$permissiontoreadown = lmdbreferralCanDo($user, 'own');
if (!isModEnabled('lmdbreferral') || (!$permissiontoread && !$permissiontoreadown)) {
	accessforbidden();
}

$form = new Form($db);
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$buttonSearch = (GETPOST('button_search', 'alpha') || GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha'));
$buttonRemoveFilter = (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha'));
$page = GETPOSTINT('page');
if ($page < 0 || $buttonSearch || $buttonRemoveFilter) {
	$page = 0;
}
$offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortfield) {
	$sortfield = 't.date_creation';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}
$allowedSort = array('t.date_creation', 't.referrer_type', 'referrer_name', 'filleul.nom', 't.status', 'signed_amount_ht', 'signed_amount_ttc', 't.entity');
if (!in_array($sortfield, $allowedSort, true)) {
	$sortfield = 't.date_creation';
}

$searchReferrerType = '';
$searchStatus = 0;
$searchSigned = '';
$searchEntityInput = array();
if (!$buttonRemoveFilter) {
	$searchReferrerType = GETPOST('search_referrer_type', 'alpha');
	$searchStatus = GETPOST('search_status', 'int');
	$searchSigned = GETPOST('search_signed', 'alpha');
	$searchEntityInput = GETPOST('search_entity', 'array');
}
$searchEntities = array();
if (!is_array($searchEntityInput)) {
	$singleEntity = GETPOSTINT('search_entity');
	$searchEntityInput = $singleEntity > 0 ? array($singleEntity) : array();
}
foreach ($searchEntityInput as $entityId) {
	if ((int) $entityId > 0) {
		$searchEntities[(int) $entityId] = (int) $entityId;
	}
}

$where = array();
$where[] = 't.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')';
if ($searchReferrerType !== '' && in_array($searchReferrerType, array('soc', 'user'), true)) {
	$where[] = "t.referrer_type = '".$db->escape($searchReferrerType)."'";
}
if ($searchStatus > 0) {
	$where[] = 't.status = '.((int) $searchStatus);
}
if (!$permissiontoread && $permissiontoreadown) {
	$where[] = "t.referrer_type = 'user'";
	$where[] = 't.fk_user_parrain = '.((int) $user->id);
}
if (!empty($searchEntities)) {
	$where[] = 't.entity IN ('.implode(',', array_map('intval', $searchEntities)).')';
}
if ($searchSigned === 'yes') {
	$where[] = 'e.rowid IS NOT NULL';
} elseif ($searchSigned === 'no') {
	$where[] = 'e.rowid IS NULL';
}

$from = ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as t';
$from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as par ON par.rowid = t.fk_soc_parrain';
$from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as up ON up.rowid = t.fk_user_parrain';
$from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as filleul ON filleul.rowid = t.fk_soc_filleul';
$from .= ' LEFT JOIN '.MAIN_DB_PREFIX."lmdbreferral_event as e ON e.fk_lmdbreferral_link = t.rowid AND e.event_type = 'propal_signed'";
$from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'propal as p ON p.rowid = e.fk_propal';
$from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entity as ent ON ent.rowid = t.entity';

$sqlCount = 'SELECT COUNT(DISTINCT t.rowid) as nb'.$from.' WHERE '.implode(' AND ', $where);
$resql = $db->query($sqlCount);
$num = 0;
if ($resql && ($obj = $db->fetch_object($resql))) {
	$num = (int) $obj->nb;
}

$sql = 'SELECT t.rowid, t.ref, t.referrer_type, t.fk_soc_parrain, t.fk_user_parrain, t.fk_soc_filleul, t.status, t.date_creation, t.entity,';
$sql .= ' par.nom as parrain_name, up.lastname, up.firstname, up.login, filleul.nom as filleul_name, ent.label as entity_label,';
$sql .= " CASE WHEN t.referrer_type = 'soc' THEN par.nom ELSE CONCAT(up.firstname, ' ', up.lastname, ' ', up.login) END as referrer_name,";
$sql .= " COUNT(DISTINCT e.fk_propal) as signed_count, MAX(e.date_event) as date_signature, SUM(e.amount_ht) as signed_amount_ht, SUM(e.amount_ttc) as signed_amount_ttc, GROUP_CONCAT(p.ref ORDER BY e.date_event SEPARATOR ', ') as propal_refs";
$sql .= $from;
$sql .= ' WHERE '.implode(' AND ', $where);
$sql .= ' GROUP BY t.rowid, t.ref, t.referrer_type, t.fk_soc_parrain, t.fk_user_parrain, t.fk_soc_filleul, t.status, t.date_creation, t.entity, par.nom, up.lastname, up.firstname, up.login, filleul.nom, ent.label';
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
}

$param = '';
if ($searchReferrerType !== '') {
	$param .= '&search_referrer_type='.urlencode($searchReferrerType);
}
if ($searchStatus > 0) {
	$param .= '&search_status='.(int) $searchStatus;
}
if ($searchSigned !== '') {
	$param .= '&search_signed='.urlencode($searchSigned);
}
foreach ($searchEntities as $entityId) {
	$param .= '&search_entity[]='.(int) $entityId;
}

llxHeader('', $langs->trans('LmdbReferralList'));
print_barre_liste($langs->trans('LmdbReferralList'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'fa-handshake', 0, '', '', $limit);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre maxwidthsearch center actioncolumn">'.$form->showFilterButtons('left').'</td>';
print '<td>'.$form->selectarray('search_referrer_type', array('soc' => $langs->trans('ThirdParty'), 'user' => $langs->trans('User')), $searchReferrerType, 1, 0, 0, '', 0, 0, 0, '', 'minwidth100').'</td>';
print '<td></td>';
print '<td></td>';
print '<td></td>';
print '<td>'.$form->selectarray('search_signed', array('yes' => $langs->trans('Yes'), 'no' => $langs->trans('No')), $searchSigned, 1, 0, 0, '', 0, 0, 0, '', 'minwidth100').'</td>';
print '<td></td><td></td>';
print '<td>'.$form->selectarray('search_status', array(LmdbReferralLink::STATUS_ACTIVE => $langs->trans('LmdbReferralStatusActive'), LmdbReferralLink::STATUS_CANCELLED => $langs->trans('LmdbReferralStatusCancelled')), $searchStatus, 1, 0, 0, '', 0, 0, 0, '', 'minwidth100').'</td>';
print '<td>';
print '<select class="flat minwidth100 multiselect2" multiple name="search_entity[]" id="search_entity">';
$sqlEntity = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').') ORDER BY label ASC';
$resqlEntity = $db->query($sqlEntity);
if ($resqlEntity) {
	while ($entity = $db->fetch_object($resqlEntity)) {
		print '<option value="'.((int) $entity->rowid).'"'.(!empty($searchEntities[(int) $entity->rowid]) ? ' selected' : '').'>'.dol_escape_htmltag($entity->label).'</option>';
	}
}
print '</select>';
if (function_exists('ajax_combobox')) {
	print ajax_combobox('search_entity');
}
print '</td>';
print '<td></td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<th class="liste_titre maxwidthsearch center actioncolumn"></th>';
print_liste_field_titre('LmdbReferralReferrer', $_SERVER['PHP_SELF'], 'referrer_name', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('LmdbReferralReferrerType', $_SERVER['PHP_SELF'], 't.referrer_type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('LmdbReferralReferredThirdparty', $_SERVER['PHP_SELF'], 'filleul.nom', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('LmdbReferralAttachedDate', $_SERVER['PHP_SELF'], 't.date_creation', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('LmdbReferralSignedProposal', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('LmdbReferralSignedAmountHT', $_SERVER['PHP_SELF'], 'signed_amount_ht', '', $param, 'right', $sortfield, $sortorder);
print_liste_field_titre('LmdbReferralSignedAmountTTC', $_SERVER['PHP_SELF'], 'signed_amount_ttc', '', $param, 'right', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 't.status', '', $param, 'center', $sortfield, $sortorder);
print_liste_field_titre('Environment', $_SERVER['PHP_SELF'], 't.entity', '', $param, '', $sortfield, $sortorder);
print '<th class="right">'.$langs->trans('Actions').'</th>';
print '</tr>';

$shown = 0;
if ($resql) {
	while (($obj = $db->fetch_object($resql)) && $shown < $limit) {
		$shown++;
		$referrerLabel = ($obj->referrer_type === 'soc') ? dol_escape_htmltag($obj->parrain_name) : dol_escape_htmltag(trim($obj->firstname.' '.$obj->lastname) ?: $obj->login);
		print '<tr class="oddeven">';
		print '<td class="center actioncolumn"></td>';
		print '<td>'.lmdbreferralGetReferrerNomUrl($obj->referrer_type, $obj->referrer_type === 'soc' ? (int) $obj->fk_soc_parrain : (int) $obj->fk_user_parrain).'</td>';
		print '<td>'.$langs->trans($obj->referrer_type === 'soc' ? 'ThirdParty' : 'User').'</td>';
		print '<td><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.(int) $obj->fk_soc_filleul.'">'.dol_escape_htmltag($obj->filleul_name).'</a></td>';
		print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
		print '<td>'.((int) $obj->signed_count > 0 ? dol_escape_htmltag($obj->propal_refs).' <span class="badge">'.((int) $obj->signed_count).'</span>' : '<span class="opacitymedium">'.$langs->trans('No').'</span>').'</td>';
		print '<td class="right">'.price((float) $obj->signed_amount_ht).'</td>';
		print '<td class="right">'.price((float) $obj->signed_amount_ttc).'</td>';
		print '<td class="center">'.lmdbreferralStatusBadge((int) $obj->status).'</td>';
		print '<td>'.dol_escape_htmltag($obj->entity_label ? $obj->entity_label : $obj->entity).'</td>';
		print '<td class="right"><a href="'.dol_buildpath('/lmdbreferral/card.php', 1).'?id='.(int) $obj->rowid.'">'.img_picto($langs->trans('View'), 'object_generic').'</a></td>';
		print '</tr>';
	}
}
if ($shown === 0) {
	lmdbreferralPrintNoRecordRow(11);
}
print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
