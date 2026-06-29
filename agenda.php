<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

$langs->loadLangs(array('agenda', 'companies', 'lmdbreferral@lmdbreferral'));

if (!isModEnabled('lmdbreferral')) {
	accessforbidden();
}

$id = GETPOSTINT('id');

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
$canReadAgenda = !empty($user->admin) || (method_exists($user, 'hasRight') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read')));
if ($agendaEnabled && !$canReadAgenda) {
	accessforbidden();
}

$formactions = new FormActions($db);
$socid = (int) $object->fk_soc_filleul;

llxHeader('', $langs->trans('LmdbReferralLink').' - '.$object->ref);

$head = lmdbreferralLinkPrepareHead($object);
print dol_get_fiche_head($head, 'agenda', $langs->trans('LmdbReferralLink'), -1, 'fa-handshake');

$linkback = '<a href="'.dol_buildpath('/lmdbreferral/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">'.$object->getLibStatut(1).'</div>';
dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', $morehtmlref);

print dol_get_fiche_end();

if (!$agendaEnabled) {
	print '<div class="opacitymedium">'.$langs->trans('LmdbReferralAgendaModuleDisabled').'</div>';
	llxFooter();
	$db->close();
	exit;
}

$formactions->showactions($object, lmdbreferralGetAgendaElementType(), $socid, 1);

llxFooter();
$db->close();
