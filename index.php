<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/lib/lmdbreferral_dashboard.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');
dol_include_once('/lmdbreferral/class/lmdbreferralstats.class.php');

$langs->loadLangs(array('companies', 'commercial', 'propal', 'lmdbreferral@lmdbreferral'));

if (!isModEnabled('lmdbreferral') || (!lmdbreferralCanDo($user, 'read') && !lmdbreferralCanDo($user, 'own'))) {
	accessforbidden();
}

$form = new Form($db);
$statsService = new LmdbReferralStats($db);
$buttonRemoveFilter = (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha'));

$rawFilters = array();
if (!$buttonRemoveFilter) {
	$rawFilters = array(
		'datefield' => GETPOST('datefield', 'alpha'),
		'date_startday' => GETPOSTINT('date_startday'),
		'date_startmonth' => GETPOSTINT('date_startmonth'),
		'date_startyear' => GETPOSTINT('date_startyear'),
		'date_endday' => GETPOSTINT('date_endday'),
		'date_endmonth' => GETPOSTINT('date_endmonth'),
		'date_endyear' => GETPOSTINT('date_endyear'),
		'referrer_type' => GETPOST('referrer_type', 'alpha'),
		'status' => GETPOSTINT('status'),
		'signed' => GETPOST('signed', 'alpha'),
		'entity' => GETPOST('entity', 'array'),
		'center' => GETPOST('center', 'alphanohtml'),
		'depth' => GETPOSTINT('depth'),
	);
}

$filters = $statsService->buildFilters($user, $rawFilters);
$overview = $statsService->getOverviewStats($user, $filters);
$funnel = $statsService->getFunnelStats($user, $filters);
$rankingSigned = $statsService->getRankingBySignedCount($user, $filters, 10);
$rankingAmount = $statsService->getRankingByAmount($user, $filters, 10);
$followup = $statsService->getFollowUpList($user, $filters, $conf->liste_limit);
$graph = $statsService->getStarGraphData($user, $filters, (string) $filters['center'], (int) $filters['depth']);
$entityOptions = $statsService->getEntityOptions();
$referrerOptions = $statsService->getReferrerOptions($user, $filters, 100);
if (!empty($graph['center'])) {
	$foundCenter = false;
	foreach ($referrerOptions as $option) {
		if ((string) $option['id'] === (string) $graph['center']['id']) {
			$foundCenter = true;
			break;
		}
	}
	if (!$foundCenter) {
		$referrerOptions[] = array('id' => (string) $graph['center']['id'], 'label' => (string) $graph['center']['label']);
	}
}

$dashboardCss = '/lmdbreferral/css/lmdbreferral_dashboard.css';
$dashboardCssFile = dol_buildpath($dashboardCss, 0);
if ($dashboardCssFile !== '' && is_readable($dashboardCssFile)) {
	$dashboardCssMtime = filemtime($dashboardCssFile);
	if ($dashboardCssMtime !== false) {
		$dashboardCss .= '?v='.(int) $dashboardCssMtime;
	}
}

llxHeader('', $langs->trans('LmdbReferralOverview'), '', '', 0, 0, array(), array($dashboardCss));
print load_fiche_titre($langs->trans('LmdbReferralOverview'), '', 'fa-handshake');

lmdbreferral_dashboard_print_filters($form, $filters, $entityOptions, $referrerOptions);
lmdbreferral_dashboard_print_kpis($overview);

print '<div class="lmdbreferral-dashboard-columns">';
print '<div class="lmdbreferral-dashboard-block">';
lmdbreferral_dashboard_print_funnel($funnel);
print '<br>';
lmdbreferral_dashboard_print_ranking_signed($rankingSigned);
print '<br>';
lmdbreferral_dashboard_print_ranking_amount($rankingAmount);
print '</div>';

print '<div class="lmdbreferral-dashboard-block">';
lmdbreferral_dashboard_print_star_graph($graph);
lmdbreferral_dashboard_print_followup($followup);
print '</div>';
print '</div>';

llxFooter();
$db->close();
