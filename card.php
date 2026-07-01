<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');
dol_include_once('/lmdbreferral/class/lmdbreferralservice.class.php');
dol_include_once('/lmdbreferral/class/lmdbreferralstats.class.php');

$langs->loadLangs(array('companies', 'propal', 'agenda', 'lmdbreferral@lmdbreferral'));

if (!isModEnabled('lmdbreferral')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if ($sortfield === '') {
	$sortfield = 'name';
}
if ($sortorder === '') {
	$sortorder = 'ASC';
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

$permissiontoadd = lmdbreferralCanDo($user, 'write', $object);
$permissiontocancel = lmdbreferralCanDo($user, 'cancel', $object);
$permissiontodelete = lmdbreferralCanDo($user, 'delete', $object);
$permtoedit = $permissiontoadd;
$socid = (int) $object->fk_soc_filleul;
$service = new LmdbReferralService($db);
$linkLocked = $service->isLockedBySignedProposal((int) $object->fk_soc_filleul);
$statsService = new LmdbReferralStats($db);
$linkStats = $statsService->getLinkStats($user, $object);
$document_root_dir = lmdbreferralGetLinkDocumentRootDir($object);
$filedir = lmdbreferralGetLinkDocumentDir($object);
$upload_dir = $document_root_dir;
$urlsource = $_SERVER['PHP_SELF'].'?id='.(int) $object->id;
$modulepart = lmdbreferralGetLinkDocumentModulePart();
$modulesubdir = lmdbreferralGetLinkDocumentSubdir($object);
$param = '&id='.(int) $object->id;
$relativepathwithnofile = $modulesubdir.'/';
$modelselected = !empty($object->model_pdf) ? $object->model_pdf : getDolGlobalString('LMDBREFERRAL_LINK_ADDON_PDF', 'standard_lmdbreferrallink');
$genallowed = $permissiontoadd;
$delallowed = $permissiontoadd || $permissiontocancel || $permissiontodelete;

if ($document_root_dir !== '') {
	$upload_dir = $document_root_dir;
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';
}
if ($filedir !== '') {
	$upload_dir = $filedir;
	include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
}

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

if ($action === 'delete') {
	if (!$permissiontodelete) {
		accessforbidden();
	}
	lmdbreferralCheckToken();
	if ($service->deleteLink((int) $object->id, $user) < 0) {
		setEventMessages($langs->trans($service->error), $service->errors, 'errors');
		header('Location: '.dol_buildpath('/lmdbreferral/card.php', 1).'?id='.(int) $object->id);
		exit;
	}

	setEventMessages($langs->trans('LmdbReferralLinkDeleted'), null, 'mesgs');
	header('Location: '.dol_buildpath('/lmdbreferral/list.php', 1));
	exit;
}

$form = new Form($db);
$formfile = new FormFile($db);
$hookmanager->initHooks(array('lmdbreferrallinkcard', 'globalcard'));

llxHeader('', $langs->trans('LmdbReferralLink').' - '.$object->ref);

$head = lmdbreferralLinkPrepareHead($object);
print dol_get_fiche_head($head, 'card', $langs->trans('LmdbReferralLink'), -1, 'fa-handshake');

$linkback = '<a href="'.dol_buildpath('/lmdbreferral/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
$morehtmlref = lmdbreferralGetLinkBannerMoreHtmlRef($object);

dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', $morehtmlref);

print '<div class="underbanner clearboth"></div>';

if (!empty($object->date_modification) || !empty($object->date_annulation)) {
	print '<div class="fichecenter">';
	print '<table class="border tableforfield centpercent">';
	if (!empty($object->date_modification)) {
		print '<tr><td class="titlefield">'.$langs->trans('DateModificationShort').'</td><td>'.dol_print_date($db->jdate($object->date_modification), 'dayhour').'</td></tr>';
	}
	if (!empty($object->date_annulation)) {
		print '<tr><td class="titlefield">'.$langs->trans('LmdbReferralCancellationDate').'</td><td>'.dol_print_date($db->jdate($object->date_annulation), 'dayhour').'</td></tr>';
	}
	print '</table>';
	print '</div>';
}

print dol_get_fiche_end();

lmdbreferralPrintLinkStatsBlock($linkStats);

$showCancelAction = (int) $object->status === LmdbReferralLink::STATUS_ACTIVE && $permissiontocancel && !$linkLocked;
if ($showCancelAction || $permissiontodelete) {
	print '<div class="tabsAction">';
	if ($showCancelAction) {
		print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=cancel&token='.newToken().'">'.$langs->trans('LmdbReferralCancelLink').'</a>';
	}
	if ($permissiontodelete) {
		print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=delete&token='.newToken().'">'.$langs->trans('LmdbReferralDeleteLink').'</a>';
	}
	print '</div>';
}

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<a name="builddoc"></a>';

if ($filedir !== '') {
	print $formfile->showdocuments($modulepart, $modulesubdir, $filedir, $urlsource, $genallowed, $delallowed, $modelselected, 0, 0, 0, 28, 0, 'id='.(int) $object->id, '', '', $langs->defaultlang, '', $object);
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
