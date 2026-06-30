<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferralcompatibility.class.php');
dol_include_once('/comm/propal/class/propal.class.php');
dol_include_once('/compta/facture/class/facture.class.php');

$langs->loadLangs(array('admin', 'lmdbreferral@lmdbreferral'));

if (!lmdbreferralCanDo($user, 'setup')) {
	accessforbidden();
}

llxHeader('', $langs->trans('LmdbReferralCompatibility'));
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('lmdbreferral').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('LmdbReferralCompatibility'), $linkback, 'technic');
$head = lmdbreferralAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans('LmdbReferralSetup'), -1, 'lmdbreferral@lmdbreferral');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('LmdbReferralCompatibilityEnvironment').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('LmdbReferralCompatibilityDetectedPhp').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralCompatibilityDetectedDolibarr').'</td><td>'.dol_escape_htmltag(defined('DOL_VERSION') ? DOL_VERSION : '').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralCompatibilityMinimumPhp').'</td><td>'.LmdbReferralCompatibility::MIN_PHP_VERSION.'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralCompatibilityMinimumDolibarr').'</td><td>'.LmdbReferralCompatibility::MIN_DOLIBARR_VERSION.'</td></tr>';
print '</table><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Code').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('Description').'</th><th class="center">'.$langs->trans('Status').'</th><th>'.$langs->trans('Reason').'</th></tr>';
foreach (LmdbReferralCompatibility::getFeatures() as $code => $feature) {
	$status = LmdbReferralCompatibility::getFeatureStatus($code, $feature);
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($code).'</td>';
	print '<td>'.$langs->trans($status['label']).'</td>';
	print '<td>'.$langs->trans($status['description']).'</td>';
	print '<td class="center">'.yn(!empty($status['available'])).'</td>';
	print '<td>'.$langs->trans($status['reason']).'</td>';
	print '</tr>';
}
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
