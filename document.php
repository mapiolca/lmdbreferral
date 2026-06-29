<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

$langs->loadLangs(array('companies', 'other', 'lmdbreferral@lmdbreferral'));

if (!isModEnabled('lmdbreferral')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (empty($sortfield)) {
	$sortfield = 'name';
}
if (empty($sortorder)) {
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

$permissiontoadd = lmdbreferralCanDo($user, 'write', $object) || lmdbreferralCanDo($user, 'cancel', $object);
$permissiontodelete = $permissiontoadd;
$permtoedit = $permissiontoadd;
$modulepart = 'lmdbreferral:LmdbReferralLink';
$modulesubdir = $object->element.'/'.dol_sanitizeFileName((string) $object->ref);
$upload_dir = lmdbreferralGetLinkDocumentDir($object);
$urlsource = $_SERVER['PHP_SELF'].'?id='.(int) $object->id;
$param = '&id='.(int) $object->id;
$relativepathwithnofile = $modulesubdir.'/';
$genallowed = 0;
$delallowed = $permissiontodelete;
$modelselected = '';

if ($upload_dir !== '') {
	include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
}

$form = new Form($db);
$formfile = new FormFile($db);

$filearray = array();
if ($upload_dir !== '') {
	$listedFiles = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) === 'desc' ? SORT_DESC : SORT_ASC), 1);
	if (is_array($listedFiles)) {
		$filearray = $listedFiles;
	}
}
$totalsize = 0;
foreach ($filearray as $file) {
	if (is_array($file) && isset($file['size'])) {
		$totalsize += (int) $file['size'];
	}
}

llxHeader('', $langs->trans('LmdbReferralLink').' - '.$object->ref);

$head = lmdbreferralLinkPrepareHead($object);
print dol_get_fiche_head($head, 'documents', $langs->trans('LmdbReferralLink'), -1, 'fa-handshake');

$linkback = '<a href="'.dol_buildpath('/lmdbreferral/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">'.$object->getLibStatut(1).'</div>';
dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('NbOfAttachedFiles').'</td><td>'.count($filearray).'</td></tr>';
print '<tr><td>'.$langs->trans('TotalSizeOfAttachedFiles').'</td><td>'.dol_print_size($totalsize).'</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

if ($upload_dir !== '') {
	include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';
} else {
	print '<div class="opacitymedium">'.$langs->trans('NotAvailable').'</div>';
}

llxFooter();
$db->close();
