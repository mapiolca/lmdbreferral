<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferralcompatibility.class.php');
dol_include_once('/lmdbreferral/core/modules/modLmdbReferral.class.php');

$langs->loadLangs(array('admin', 'lmdbreferral@lmdbreferral'));

if (!lmdbreferralCanDo($user, 'setup')) {
	accessforbidden();
}

$moduleDescriptor = new modLmdbReferral($db);

llxHeader('', $langs->trans('LmdbReferralAbout'));
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('lmdbreferral').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('LmdbReferralAbout'), $linkback, 'info');
$head = lmdbreferralAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('LmdbReferralSetup'), -1, 'lmdbreferral@lmdbreferral');

print '<div class="underbanner opacitymedium">'.$langs->trans('LmdbReferralAboutPage').'</div><br>';
print '<div class="fichecenter">';
print '<div class="fichehalfleft"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('LmdbReferralAboutGeneral').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Module').'</td><td>'.$langs->trans('ModuleLmdbReferralName').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Version').'</td><td>'.dol_escape_htmltag($moduleDescriptor->version).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Publisher').'</td><td>'.dol_escape_htmltag($moduleDescriptor->editor_name).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Description').'</td><td>'.$langs->trans($moduleDescriptor->description).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralCompatibilityShort').'</td><td>'.$langs->trans('LmdbReferralAboutCompatibilityValue', LmdbReferralCompatibility::MIN_DOLIBARR_VERSION, LmdbReferralCompatibility::MIN_PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('License').'</td><td>GPL-3.0-or-later</td></tr>';
print '</table></div>';
print '<div class="fichehalfright"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('LmdbReferralAboutResources').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Documentation').'</td><td><a href="'.dol_buildpath('/lmdbreferral/README.md', 1).'" target="_blank" rel="noopener">README.md</a></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Support').'</td><td>'.$langs->trans('LmdbReferralAboutSupportValue').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Dependencies').'</td><td>'.$langs->trans('LmdbReferralAboutDependenciesValue').'</td></tr>';
print '</table></div>';
print '</div>';

print '<br><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('LmdbReferralAboutFeatures').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralAboutFeatureThirdpartyReferrers').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralAboutFeatureUserReferrers').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralAboutFeaturePropalSigned').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralAboutFeatureStats').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralAboutFeatureDocuments').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralAboutFeatureNumbering').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralAboutFeatureMulticompany').'</td></tr>';
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
