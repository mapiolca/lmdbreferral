<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');
dol_include_once('/lmdbreferral/class/lmdbreferralservice.class.php');

$langs->loadLangs(array('companies', 'propal', 'lmdbreferral@lmdbreferral'));

if (!isModEnabled('lmdbreferral')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$object = new LmdbReferralLink($db);
if ($id > 0 && $object->fetch($id) <= 0) {
	recordNotFound();
}
if (!lmdbreferralCanDo($user, 'read') && !lmdbreferralCanReadOwnLink($user, $object)) {
	accessforbidden();
}

if ($action === 'cancel' && $object->id > 0) {
	if (!lmdbreferralCanDo($user, 'cancel', $object)) {
		accessforbidden();
	}
	lmdbreferralCheckToken();
	$service = new LmdbReferralService($db);
	if ($service->cancelLink((int) $object->id, $user) < 0) {
		setEventMessages($langs->trans($service->error), $service->errors, 'errors');
	} else {
		setEventMessages($langs->trans('LmdbReferralLinkCancelled'), null, 'mesgs');
	}
	header('Location: '.dol_buildpath('/lmdbreferral/card.php', 1).'?id='.(int) $object->id);
	exit;
}

llxHeader('', $langs->trans('LmdbReferralLink'));
if ($object->id > 0) {
	print load_fiche_titre($langs->trans('LmdbReferralLink').' '.$object->ref, '<a href="'.dol_buildpath('/lmdbreferral/list.php', 1).'">'.$langs->trans('BackToList').'</a>', 'fa-handshake');
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.$object->getNomUrl(0).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbReferralReferrer').'</td><td>'.lmdbreferralGetReferrerNomUrl($object->referrer_type, $object->referrer_type === 'soc' ? (int) $object->fk_soc_parrain : (int) $object->fk_user_parrain).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbReferralReferredThirdparty').'</td><td>';
	require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
	$soc = new Societe($db);
	if ($soc->fetch((int) $object->fk_soc_filleul) > 0) {
		print $soc->getNomUrl(1);
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(1).'</td></tr>';
	print '<tr><td>'.$langs->trans('DateCreation').'</td><td>'.dol_print_date($db->jdate($object->date_creation), 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans('Environment').'</td><td>'.((int) $object->entity).'</td></tr>';
	print '</table>';

	if ($object->status == LmdbReferralLink::STATUS_ACTIVE && lmdbreferralCanDo($user, 'cancel')) {
		print '<div class="tabsAction">';
		print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=cancel&token='.newToken().'">'.$langs->trans('LmdbReferralCancelLink').'</a>';
		print '</div>';
	}

	print '<br>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Event').'</th><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Proposal').'</th><th class="right">'.$langs->trans('AmountHT').'</th><th class="right">'.$langs->trans('AmountTTC').'</th></tr>';
	$sql = 'SELECT e.*, p.ref as propal_ref FROM '.MAIN_DB_PREFIX.'lmdbreferral_event as e';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'propal as p ON p.rowid = e.fk_propal';
	$sql .= ' WHERE e.entity IN ('.lmdbreferralGetEntitySql('lmdbreferralevent').') AND e.fk_lmdbreferral_link = '.((int) $object->id);
	$sql .= ' ORDER BY e.date_event DESC, e.rowid DESC';
	$resql = $db->query($sql);
	$n = 0;
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$n++;
			print '<tr class="oddeven">';
			print '<td>'.$langs->trans('LmdbReferralEvent_'.$obj->event_type).'</td>';
			print '<td>'.dol_print_date($db->jdate($obj->date_event), 'dayhour').'</td>';
			print '<td>'.($obj->fk_propal ? '<a href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.(int) $obj->fk_propal.'">'.dol_escape_htmltag($obj->propal_ref).'</a>' : '').'</td>';
			print '<td class="right">'.price((float) $obj->amount_ht).'</td>';
			print '<td class="right">'.price((float) $obj->amount_ttc).'</td>';
			print '</tr>';
		}
	}
	if ($n === 0) {
		lmdbreferralPrintNoRecordRow(5);
	}
	print '</table>';
}

llxFooter();
$db->close();
