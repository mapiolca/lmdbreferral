<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

$langs->loadLangs(array('companies', 'propal', 'lmdbreferral@lmdbreferral'));

$id = GETPOSTINT('id') ? GETPOSTINT('id') : GETPOSTINT('socid');
$object = new Societe($db);
if ($id > 0) {
	$object->fetch($id);
}
if (!$object->id || !isModEnabled('lmdbreferral') || !lmdbreferralCanDo($user, 'read')) {
	accessforbidden();
}

restrictedArea($user, 'societe', $object->id, '&societe');

llxHeader('', $langs->trans('LmdbReferralTabReferrals'));
$head = societe_prepare_head($object);
print dol_get_fiche_head($head, 'lmdbreferral_filleuls', $langs->trans('ThirdParty'), -1, 'company');
$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');
print '<div class="underbanner clearboth"></div>';
lmdbreferral_print_filleuls_table($db, $langs, "l.referrer_type = 'soc' AND l.fk_soc_parrain = ".((int) $object->id), (int) $object->id);
print dol_get_fiche_end();
llxFooter();
$db->close();

/**
 * Print filleuls table.
 *
 * @param DoliDB    $db Database
 * @param Translate $langs Langs
 * @param string    $whereExtra Extra where
 * @param int       $currentId Current thirdparty id
 * @return void
 */
function lmdbreferral_print_filleuls_table($db, $langs, $whereExtra, $currentId)
{
	global $conf, $user;

	$form = new Form($db);
	$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
	$massaction = GETPOST('massaction', 'alpha');
	$toselect = GETPOST('toselect', 'array');
	$buttonSearch = (GETPOST('button_search', 'alpha') || GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha'));
	$buttonRemoveFilter = (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha'));
	$page = GETPOSTINT('page');
	if ($page < 0 || $buttonSearch || $buttonRemoveFilter || $massaction !== '') {
		$page = 0;
	}
	$offset = $limit * $page;
	$sortfield = GETPOST('sortfield', 'aZ09comma');
	$sortorder = GETPOST('sortorder', 'aZ09comma');
	if (!$sortfield) {
		$sortfield = 'l.date_creation';
	}
	if (!$sortorder) {
		$sortorder = 'DESC';
	}
	$allowedSort = array('filleul.nom', 'l.date_creation', 'l.status', 'signed_count', 'date_signature', 'amount_ht', 'amount_ttc', 'l.entity');
	if (!in_array($sortfield, $allowedSort, true)) {
		$sortfield = 'l.date_creation';
	}

	$searchStatus = 0;
	$searchEntityInput = array();
	if (!$buttonRemoveFilter) {
		$searchStatus = GETPOST('search_lmdbreferral_status', 'int');
		$searchEntityInput = GETPOST('search_lmdbreferral_entity', 'array');
	}
	$searchEntities = array();
	if (!is_array($searchEntityInput)) {
		$searchEntityInput = array();
	}
	foreach ($searchEntityInput as $entityId) {
		if ((int) $entityId > 0) {
			$searchEntities[(int) $entityId] = (int) $entityId;
		}
	}

	$where = array(
		'l.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')',
		$whereExtra,
	);
	if ($searchStatus > 0) {
		$where[] = 'l.status = '.((int) $searchStatus);
	}
	if (!empty($searchEntities)) {
		$where[] = 'l.entity IN ('.implode(',', array_map('intval', $searchEntities)).')';
	}

	$param = '&id='.((int) $currentId);
	if ($searchStatus > 0) {
		$param .= '&search_lmdbreferral_status='.(int) $searchStatus;
	}
	foreach ($searchEntities as $entityId) {
		$param .= '&search_lmdbreferral_entity[]='.(int) $entityId;
	}

	if ($massaction === 'cancel') {
		lmdbreferralCheckToken();
		$massResult = lmdbreferralMassCancelLinks($db, $user, $toselect, $whereExtra);
		if ($massResult['done'] > 0) {
			setEventMessages($langs->trans('LmdbReferralMassCancelDone', $massResult['done']), null, 'mesgs');
		}
		if (!empty($massResult['errors'])) {
			setEventMessages('', $massResult['errors'], 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?'.ltrim($param, '&'));
		exit;
	}
	if ($massaction === 'delete') {
		lmdbreferralCheckToken();
		$massResult = lmdbreferralMassDeleteLinks($db, $user, $toselect, $whereExtra);
		if ($massResult['done'] > 0) {
			setEventMessages($langs->trans('LmdbReferralMassDeleteDone', $massResult['done']), null, 'mesgs');
		}
		if (!empty($massResult['errors'])) {
			setEventMessages('', $massResult['errors'], 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?'.ltrim($param, '&'));
		exit;
	}

	$sqlCount = 'SELECT COUNT(DISTINCT l.rowid) as nb FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
	$sqlCount .= ' WHERE '.implode(' AND ', $where);
	$resqlCount = $db->query($sqlCount);
	$num = 0;
	if ($resqlCount && ($countObj = $db->fetch_object($resqlCount))) {
		$num = (int) $countObj->nb;
	}

	$arrayofmassactions = array();
	$permissiontocancel = lmdbreferralCanDo($user, 'cancel');
	if ($permissiontocancel) {
		$arrayofmassactions['cancel'] = $langs->trans('LmdbReferralCancelSelectedLinks');
	}
	$permissiontodelete = lmdbreferralCanDo($user, 'delete');
	if ($permissiontodelete) {
		$arrayofmassactions['delete'] = $langs->trans('LmdbReferralDeleteSelectedLinks');
	}
	$massactionbutton = !empty($arrayofmassactions) ? $form->selectMassAction('', $arrayofmassactions) : '';

	$sql = 'SELECT l.rowid, l.status, l.date_creation, l.entity, filleul.rowid as filleul_id, filleul.nom as filleul_name, ent.label as entity_label,';
	$sql .= " COUNT(DISTINCT e.fk_propal) as signed_count, MAX(e.date_event) as date_signature, SUM(e.amount_ht) as amount_ht, SUM(e.amount_ttc) as amount_ttc, GROUP_CONCAT(p.ref ORDER BY e.date_event SEPARATOR ', ') as propal_refs";
	$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as filleul ON filleul.rowid = l.fk_soc_filleul';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX."lmdbreferral_event as e ON e.fk_lmdbreferral_link = l.rowid AND e.event_type = 'propal_signed'";
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'propal as p ON p.rowid = e.fk_propal';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entity as ent ON ent.rowid = l.entity';
	$sql .= ' WHERE '.implode(' AND ', $where);
	$sql .= ' GROUP BY l.rowid, l.status, l.date_creation, l.entity, filleul.rowid, filleul.nom, ent.label';
	$sql .= $db->order($sortfield, $sortorder);
	$sql .= $db->plimit($limit + 1, $offset);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_print_error($db);
	}

	$titleList = $langs->trans('LmdbReferralTabReferrals');

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" name="form_lmdbreferral_soc_filleuls">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="id" value="'.((int) $currentId).'">';
	print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
	print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
	print '<input type="hidden" name="limit" value="'.((int) $limit).'">';
	print_barre_liste($titleList, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $massactionbutton, $num, $num, 'fa-handshake', 0, '', '', $limit);
	print '<div class="div-table-responsive-no-min">';
	print '<table class="tagtable nobottomiftotal liste listwithfilterbefore centpercent">';
	print '<tr class="liste_titre_filter">';
	print '<td class="liste_titre maxwidthsearch center actioncolumn">'.$form->showFilterButtons('left').'</td>';
	print '<td></td><td></td>';
	print '<td class="center">'.$form->selectarray('search_lmdbreferral_status', array(LmdbReferralLink::STATUS_ACTIVE => $langs->trans('LmdbReferralStatusActive'), LmdbReferralLink::STATUS_CANCELLED => $langs->trans('LmdbReferralStatusCancelled')), $searchStatus, 1, 0, 0, '', 0, 0, 0, '', 'minwidth100').'</td>';
	print '<td></td><td></td><td></td><td></td>';
	print '<td class="center"><select class="flat minwidth100 multiselect2" multiple name="search_lmdbreferral_entity[]" id="search_lmdbreferral_entity">';
	$sqlEntity = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').') ORDER BY label ASC';
	$resqlEntity = $db->query($sqlEntity);
	if ($resqlEntity) {
		while ($entity = $db->fetch_object($resqlEntity)) {
			print '<option value="'.((int) $entity->rowid).'"'.(!empty($searchEntities[(int) $entity->rowid]) ? ' selected' : '').'>'.dol_escape_htmltag($entity->label).'</option>';
		}
	}
	print '</select>';
	if (function_exists('ajax_combobox')) {
		print ajax_combobox('search_lmdbreferral_entity');
	}
	print '</td>';
	print '</tr>';
	print '<tr class="liste_titre">';
	print '<th class="liste_titre maxwidthsearch center actioncolumn">';
	if ($massactionbutton !== '') {
		if (method_exists($form, 'showCheckAddButtons')) {
			print $form->showCheckAddButtons('checkforselect', 1);
		}
	}
	print '</th>';
	print_liste_field_titre('LmdbReferralReferredThirdparty', $_SERVER['PHP_SELF'], 'filleul.nom', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre('LmdbReferralAttachedDate', $_SERVER['PHP_SELF'], 'l.date_creation', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'l.status', '', $param, 'center', $sortfield, $sortorder);
	print_liste_field_titre('LmdbReferralSignedProposal', $_SERVER['PHP_SELF'], 'signed_count', '', $param, 'center', $sortfield, $sortorder);
	print_liste_field_titre('LmdbReferralSignatureDate', $_SERVER['PHP_SELF'], 'date_signature', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre('LmdbReferralSignedAmountHT', $_SERVER['PHP_SELF'], 'amount_ht', '', $param, 'right', $sortfield, $sortorder);
	print_liste_field_titre('LmdbReferralSignedAmountTTC', $_SERVER['PHP_SELF'], 'amount_ttc', '', $param, 'right', $sortfield, $sortorder);
	print_liste_field_titre('Environment', $_SERVER['PHP_SELF'], 'l.entity', '', $param, 'center', $sortfield, $sortorder);
	print '</tr>';
	$n = 0;
	if ($resql) {
		while (($obj = $db->fetch_object($resql)) && $n < $limit) {
			$n++;
			print '<tr class="oddeven">';
			print '<td class="center actioncolumn">';
			if ($massactionbutton !== '') {
				print '<input id="cb'.((int) $obj->rowid).'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.((int) $obj->rowid).'">';
			}
			print '</td>';
			print '<td><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.(int) $obj->filleul_id.'">'.dol_escape_htmltag($obj->filleul_name).'</a></td>';
			print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
			print '<td class="center">'.lmdbreferralStatusBadge((int) $obj->status).'</td>';
			print '<td class="center">'.lmdbreferralFormatSignedProposalRefs((int) $obj->signed_count, $obj->propal_refs).'</td>';
			print '<td>'.($obj->date_signature ? dol_print_date($db->jdate($obj->date_signature), 'day') : '').'</td>';
			print '<td class="right">'.lmdbreferralFormatAmount($obj->amount_ht).'</td>';
			print '<td class="right">'.lmdbreferralFormatAmount($obj->amount_ttc).'</td>';
			print '<td align="center">'.lmdbreferralMulticompanyEntityBadge((int) $obj->entity, $obj->entity_label ? (string) $obj->entity_label : (string) $obj->entity).'</td>';
			print '</tr>';
		}
	}
	if ($n === 0) {
		lmdbreferralPrintNoRecordRow(9);
	}
	print '</table>';
	print '</div>';
	print '</form>';
}
