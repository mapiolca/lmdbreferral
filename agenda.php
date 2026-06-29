<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/agenda.lib.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

$langs->loadLangs(array('agenda', 'companies', 'lmdbreferral@lmdbreferral'));

if (!isModEnabled('lmdbreferral')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$actioncode = GETPOST('actioncode', 'alpha');
$searchAgendaLabel = GETPOST('search_agenda_label', 'alphanohtml');
$searchRowid = GETPOSTINT('search_rowid');
$buttonRemoveFilter = (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha'));
if (empty($sortfield)) {
	$sortfield = 'a.datep,a.id';
}
if (empty($sortorder)) {
	$sortorder = 'DESC';
}
if ($buttonRemoveFilter) {
	$actioncode = '';
	$searchAgendaLabel = '';
	$searchRowid = 0;
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
$canReadAgenda = !empty($user->admin) || (method_exists($user, 'hasRight') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read')));
if ($agendaEnabled && !$canReadAgenda) {
	accessforbidden();
}

$form = new Form($db);
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

$param = '&id='.(int) $object->id;
if ($actioncode !== '') {
	$param .= '&actioncode='.urlencode($actioncode);
}
if ($searchAgendaLabel !== '') {
	$param .= '&search_agenda_label='.urlencode($searchAgendaLabel);
}
if ($searchRowid > 0) {
	$param .= '&search_rowid='.(int) $searchRowid;
}

print_barre_liste($langs->trans('LmdbReferralActionsOnLink'), 0, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', 0, -1, 'title_agenda');

$agendaObject = clone $object;
$agendaObject->element = $object->element.'@'.$object->module;
$filters = array(
	'search_agenda_label' => $searchAgendaLabel,
	'search_rowid' => $searchRowid,
);

if (function_exists('show_actions_done')) {
	show_actions_done($conf, $langs, $db, $agendaObject, 0, $socid, $actioncode, '', $filters, $sortfield, $sortorder);
} else {
	$formactions->showactions($object, $object->element.'@'.$object->module, $socid, 1);
}

llxFooter();
$db->close();
