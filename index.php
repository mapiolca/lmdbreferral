<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/lmdbreferral/lib/lmdbreferral.lib.php');
dol_include_once('/lmdbreferral/class/lmdbreferrallink.class.php');

$langs->loadLangs(array('companies', 'propal', 'lmdbreferral@lmdbreferral'));

if (!isModEnabled('lmdbreferral') || (!lmdbreferralCanDo($user, 'read') && !lmdbreferralCanDo($user, 'own'))) {
	accessforbidden();
}

$where = array('l.entity IN ('.lmdbreferralGetEntitySql('lmdbreferrallink').')');
if (!lmdbreferralCanDo($user, 'all') && lmdbreferralCanDo($user, 'own')) {
	$where[] = "l.referrer_type = 'user'";
	$where[] = 'l.fk_user_parrain = '.((int) $user->id);
}
$whereSql = implode(' AND ', $where);

$stats = array(
	'active_referrers' => 0,
	'total_referred' => 0,
	'signed_referred' => 0,
	'ca_ht' => 0,
	'avg_delay' => 0,
	'referred_became_referrers' => 0,
);

$sql = "SELECT COUNT(DISTINCT CONCAT(l.referrer_type, ':', COALESCE(l.fk_soc_parrain, l.fk_user_parrain))) as nb FROM ".MAIN_DB_PREFIX.'lmdbreferral_link as l WHERE '.$whereSql.' AND l.status = '.LmdbReferralLink::STATUS_ACTIVE;
$resql = $db->query($sql);
if ($resql && ($obj = $db->fetch_object($resql))) {
	$stats['active_referrers'] = (int) $obj->nb;
}
$sql = 'SELECT COUNT(DISTINCT l.fk_soc_filleul) as nb FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l WHERE '.$whereSql;
$resql = $db->query($sql);
if ($resql && ($obj = $db->fetch_object($resql))) {
	$stats['total_referred'] = (int) $obj->nb;
}
$sql = 'SELECT COUNT(DISTINCT l.fk_soc_filleul) as nb, SUM(e.amount_ht) as ca_ht, AVG(DATEDIFF(e.date_event, l.date_creation)) as avg_delay';
$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l INNER JOIN '.MAIN_DB_PREFIX."lmdbreferral_event as e ON e.fk_lmdbreferral_link = l.rowid AND e.event_type = 'propal_signed'";
$sql .= ' WHERE '.$whereSql;
$resql = $db->query($sql);
if ($resql && ($obj = $db->fetch_object($resql))) {
	$stats['signed_referred'] = (int) $obj->nb;
	$stats['ca_ht'] = (float) $obj->ca_ht;
	$stats['avg_delay'] = (float) $obj->avg_delay;
}
$sql = 'SELECT COUNT(DISTINCT l1.fk_soc_filleul) as nb';
$sql .= " FROM ".MAIN_DB_PREFIX."lmdbreferral_link as l1 INNER JOIN ".MAIN_DB_PREFIX."lmdbreferral_link as l2 ON l2.referrer_type = 'soc' AND l2.fk_soc_parrain = l1.fk_soc_filleul";
$sql .= ' WHERE '.str_replace('l.', 'l1.', $whereSql);
$resql = $db->query($sql);
if ($resql && ($obj = $db->fetch_object($resql))) {
	$stats['referred_became_referrers'] = (int) $obj->nb;
}

$conversion = $stats['total_referred'] > 0 ? ($stats['signed_referred'] / $stats['total_referred'] * 100) : 0;
$avgBasket = $stats['signed_referred'] > 0 ? ($stats['ca_ht'] / $stats['signed_referred']) : 0;

llxHeader('', $langs->trans('LmdbReferralOverview'));
print load_fiche_titre($langs->trans('LmdbReferralOverview'), '', 'fa-handshake');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="4">'.$langs->trans('LmdbReferralMainIndicators').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('LmdbReferralActiveReferrers').'</td><td>'.((int) $stats['active_referrers']).'</td><td>'.$langs->trans('LmdbReferralTotalReferred').'</td><td>'.((int) $stats['total_referred']).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralSignedReferred').'</td><td>'.((int) $stats['signed_referred']).'</td><td>'.$langs->trans('LmdbReferralConversionRate').'</td><td>'.price($conversion).' %</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralGeneratedCAHT').'</td><td>'.price($stats['ca_ht']).'</td><td>'.$langs->trans('LmdbReferralAverageBasketHT').'</td><td>'.price($avgBasket).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbReferralAverageDelay').'</td><td>'.price($stats['avg_delay']).' '.$langs->trans('Days').'</td><td>'.$langs->trans('LmdbReferralBecameReferrers').'</td><td>'.((int) $stats['referred_became_referrers']).'</td></tr>';
print '</table><br>';

lmdbreferral_print_ranking($db, $langs, $whereSql, 'signed_count', 'DESC', 'LmdbReferralRankingBySignedReferred');
print '<br>';
lmdbreferral_print_ranking($db, $langs, $whereSql, 'ca_ht', 'DESC', 'LmdbReferralRankingByCAHT');

llxFooter();
$db->close();

/**
 * Print referrer ranking.
 *
 * @param DoliDB    $db Database
 * @param Translate $langs Langs
 * @param string    $whereSql Where SQL
 * @param string    $metric Metric
 * @param string    $direction Direction
 * @param string    $titleKey Title key
 * @return void
 */
function lmdbreferral_print_ranking($db, $langs, $whereSql, $metric, $direction, $titleKey)
{
	$sql = 'SELECT l.referrer_type, l.fk_soc_parrain, l.fk_user_parrain, par.nom as parrain_name, up.lastname, up.firstname, up.login,';
	$sql .= ' COUNT(DISTINCT l.fk_soc_filleul) as filleuls, COUNT(DISTINCT e.fk_propal) as signed_count, SUM(e.amount_ht) as ca_ht';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbreferral_link as l';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as par ON par.rowid = l.fk_soc_parrain';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as up ON up.rowid = l.fk_user_parrain';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX."lmdbreferral_event as e ON e.fk_lmdbreferral_link = l.rowid AND e.event_type = 'propal_signed'";
	$sql .= ' WHERE '.$whereSql;
	$sql .= ' GROUP BY l.referrer_type, l.fk_soc_parrain, l.fk_user_parrain, par.nom, up.lastname, up.firstname, up.login';
	$sql .= ' ORDER BY '.$metric.' '.$direction;
	$sql .= $db->plimit(10);
	$resql = $db->query($sql);

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th colspan="6">'.$langs->trans($titleKey).'</th></tr>';
	print '<tr class="liste_titre"><th class="right">'.$langs->trans('Rank').'</th><th>'.$langs->trans('LmdbReferralReferrer').'</th><th>'.$langs->trans('Type').'</th><th class="right">'.$langs->trans('LmdbReferralTotalReferred').'</th><th class="right">'.$langs->trans('LmdbReferralSignedReferred').'</th><th class="right">'.$langs->trans('LmdbReferralGeneratedCAHT').'</th></tr>';
	$rank = 0;
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$rank++;
			print '<tr class="oddeven">';
			print '<td class="right">'.$rank.'</td>';
			print '<td>'.lmdbreferralGetReferrerNomUrl($obj->referrer_type, $obj->referrer_type === 'soc' ? (int) $obj->fk_soc_parrain : (int) $obj->fk_user_parrain).'</td>';
			print '<td>'.$langs->trans($obj->referrer_type === 'soc' ? 'ThirdParty' : 'User').'</td>';
			print '<td class="right">'.((int) $obj->filleuls).'</td>';
			print '<td class="right">'.((int) $obj->signed_count).'</td>';
			print '<td class="right">'.price((float) $obj->ca_ht).'</td>';
			print '</tr>';
		}
	}
	if ($rank === 0) {
		lmdbreferralPrintNoRecordRow(6);
	}
	print '</table>';
}
