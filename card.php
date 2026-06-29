<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');
dol_include_once('/lmdbreferral/class/lmdbreferralservice.class.php');

$langs->loadLangs(array('companies', 'propal', 'agenda', 'lmdbreferral@lmdbreferral'));

if (!isModEnabled('lmdbreferral')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

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

$permissiontoadd = lmdbreferralCanDo($user, 'write', $object);
$permissiontocancel = lmdbreferralCanDo($user, 'cancel', $object);
$socid = (int) $object->fk_soc_filleul;
$service = new LmdbReferralService($db);
$linkLocked = $service->isLockedBySignedProposal((int) $object->fk_soc_filleul);

if ($action === 'cancel') {
	if (!$permissiontocancel) {
		accessforbidden();
	}
	lmdbreferralCheckToken();
	if ($linkLocked) {
		setEventMessages($langs->trans('LmdbReferralLockedAfterSignedProposal'), null, 'errors');
		header('Location: '.dol_buildpath('/lmdbreferral/card.php', 1).'?id='.(int) $object->id);
		exit;
	}
	if ($service->cancelLink((int) $object->id, $user) < 0) {
		setEventMessages($langs->trans($service->error), $service->errors, 'errors');
	} else {
		setEventMessages($langs->trans('LmdbReferralLinkCancelled'), null, 'mesgs');
	}
	header('Location: '.dol_buildpath('/lmdbreferral/card.php', 1).'?id='.(int) $object->id);
	exit;
}

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader('', $langs->trans('LmdbReferralLink').' - '.$object->ref);

$head = lmdbreferralLinkPrepareHead($object);
print dol_get_fiche_head($head, 'card', $langs->trans('LmdbReferralLink'), -1, 'fa-handshake');

$linkback = '<a href="'.dol_buildpath('/lmdbreferral/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '';

dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield centpercent">';

print '<tr><td class="titlefield">'.$langs->trans('LmdbReferralReferrer').'</td><td>'.lmdbreferralGetReferrerNomUrl($object->referrer_type, $object->referrer_type === 'soc' ? (int) $object->fk_soc_parrain : (int) $object->fk_user_parrain).'</td></tr>';
print '<tr><td>'.$langs->trans('LmdbReferralReferredThirdparty').'</td><td>';
$soc = new Societe($db);
if ($soc->fetch((int) $object->fk_soc_filleul) > 0) {
	print $soc->getNomUrl(1);
}
print '</td></tr>';
print '<tr><td>'.$langs->trans('DateCreation').'</td><td>'.dol_print_date($db->jdate($object->date_creation), 'dayhour').'</td></tr>';
if (!empty($object->date_modification)) {
	print '<tr><td>'.$langs->trans('DateModificationShort').'</td><td>'.dol_print_date($db->jdate($object->date_modification), 'dayhour').'</td></tr>';
}
if (!empty($object->date_annulation)) {
	print '<tr><td>'.$langs->trans('LmdbReferralCancellationDate').'</td><td>'.dol_print_date($db->jdate($object->date_annulation), 'dayhour').'</td></tr>';
}

print '</table>';
print '</div>';

if ((int) $object->status === LmdbReferralLink::STATUS_ACTIVE && $permissiontocancel && !$linkLocked) {
	print '<div class="tabsAction">';
	print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=cancel&token='.newToken().'">'.$langs->trans('LmdbReferralCancelLink').'</a>';
	print '</div>';
}

print dol_get_fiche_end();

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<a name="builddoc"></a>';

$upload_dir = lmdbreferralGetLinkDocumentDir($object);
$urlsource = $_SERVER['PHP_SELF'].'?id='.(int) $object->id;
$modulepart = 'lmdbreferral:LmdbReferralLink';
$modulesubdir = $object->element.'/'.dol_sanitizeFileName($object->ref);
$delallowed = $permissiontoadd || $permissiontocancel;
if ($upload_dir !== '') {
	print $formfile->showdocuments($modulepart, $modulesubdir, $upload_dir, $urlsource, 0, $delallowed, '', 0, 0, 0, 28, 0, 'id='.(int) $object->id, '', '', $langs->defaultlang, '', $object);
} else {
	print '<div class="opacitymedium">'.$langs->trans('NotAvailable').'</div>';
}

print '<br>';
$form->showLinkedObjectBlock($object, '');

print '</div>';
print '<div class="fichehalfright">';
$canReadAgenda = !empty($user->admin) || (method_exists($user, 'hasRight') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read')));
if (isModEnabled('agenda') && $canReadAgenda) {
	$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/lmdbreferral/agenda.php', 1).'?id='.(int) $object->id);
	$formactions = new FormActions($db);
	$formactions->showactions($object, lmdbreferralGetAgendaElementType(), $socid, 1, '', lmdbreferralGetAgendaBlockLimit(), '', $morehtmlcenter);
} else {
	print '<div class="opacitymedium">'.$langs->trans('LmdbReferralAgendaModuleDisabled').'</div>';
}
print '</div>';
print '</div>';

llxFooter();
$db->close();
