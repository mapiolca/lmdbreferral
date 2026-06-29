<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Print dashboard filters.
 *
 * @param Form                    $form            Form helper
 * @param array<string,mixed>     $filters         Normalized filters
 * @param array<int,string>       $entityOptions   Entity options
 * @param array<int,array<string,string>> $referrerOptions Referrer options
 * @return void
 */
function lmdbreferral_dashboard_print_filters($form, array $filters, array $entityOptions, array $referrerOptions)
{
	global $langs;

	print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="lmdbreferral-dashboard-filters">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th colspan="8">'.$langs->trans('Search').'</th></tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('Date').'</td><td>';
	print $form->selectarray('datefield', array('link' => $langs->trans('LmdbReferralDateFieldLink'), 'signature' => $langs->trans('LmdbReferralDateFieldSignature')), (string) $filters['datefield'], 0, 0, 0, '', 0, 0, 0, '', 'minwidth125');
	print '</td>';
	print '<td>'.$langs->trans('DateStart').'</td><td>'.$form->selectDate(!empty($filters['date_start']) ? (int) $filters['date_start'] : -1, 'date_start', 0, 0, 1, '', 1, 1).'</td>';
	print '<td>'.$langs->trans('DateEnd').'</td><td>'.$form->selectDate(!empty($filters['date_end']) ? (int) $filters['date_end'] : -1, 'date_end', 0, 0, 1, '', 1, 1).'</td>';
	print '<td>'.$langs->trans('LmdbReferralReferrerType').'</td><td>';
	print $form->selectarray('referrer_type', array('soc' => $langs->trans('ThirdParty'), 'user' => $langs->trans('User')), (string) $filters['referrer_type'], 1, 0, 0, '', 0, 0, 0, '', 'minwidth100');
	print '</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('Status').'</td><td>';
	print $form->selectarray('status', array(LmdbReferralLink::STATUS_ACTIVE => $langs->trans('LmdbReferralStatusActive'), LmdbReferralLink::STATUS_CANCELLED => $langs->trans('LmdbReferralStatusCancelled')), (int) $filters['status'], 1, 0, 0, '', 0, 0, 0, '', 'minwidth100');
	print '</td>';
	print '<td>'.$langs->trans('LmdbReferralSignedProposal').'</td><td>';
	print $form->selectarray('signed', array('yes' => $langs->trans('Yes'), 'no' => $langs->trans('No')), (string) $filters['signed'], 1, 0, 0, '', 0, 0, 0, '', 'minwidth100');
	print '</td>';
	print '<td>'.$langs->trans('Environment').'</td><td>';
	print '<select class="flat minwidth125 multiselect2" multiple name="entity[]" id="lmdbreferral_dashboard_entity">';
	foreach ($entityOptions as $entityId => $label) {
		print '<option value="'.((int) $entityId).'"'.(!empty($filters['entities'][(int) $entityId]) ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
	}
	print '</select>';
	if (function_exists('ajax_combobox')) {
		print ajax_combobox('lmdbreferral_dashboard_entity');
	}
	print '</td>';
	print '<td>'.$langs->trans('LmdbReferralGraphCenter').'</td><td>';
	print '<select class="flat minwidth200" name="center" id="lmdbreferral_dashboard_center">';
	print '<option value="">'.$langs->trans('Automatic').'</option>';
	foreach ($referrerOptions as $option) {
		$value = (string) $option['id'];
		print '<option value="'.dol_escape_htmltag($value).'"'.($value === (string) $filters['center'] ? ' selected' : '').'>'.dol_escape_htmltag((string) $option['label']).'</option>';
	}
	print '</select>';
	if (function_exists('ajax_combobox')) {
		print ajax_combobox('lmdbreferral_dashboard_center');
	}
	print '</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('LmdbReferralGraphDepth').'</td><td>';
	print $form->selectarray('depth', array(1 => '1', 2 => '2'), (int) $filters['depth'], 0, 0, 0, '', 0, 0, 0, '', 'maxwidth75');
	print '</td>';
	print '<td colspan="6" class="right">';
	print '<input type="submit" class="button smallpaddingimp" name="button_search" value="'.$langs->trans('Search').'">';
	print ' <input type="submit" class="button button-cancel smallpaddingimp" name="button_removefilter" value="'.$langs->trans('RemoveFilter').'">';
	print '</td>';
	print '</tr>';
	print '</table>';
	print '</form>';
}

/**
 * Print KPI cards.
 *
 * @param array<string,int|float> $overview Overview stats
 * @return void
 */
function lmdbreferral_dashboard_print_kpis(array $overview)
{
	global $langs;

	$cards = array(
		array('label' => 'LmdbReferralActiveReferrers', 'value' => (int) $overview['active_referrers'], 'class' => 'neutral'),
		array('label' => 'LmdbReferralTotalReferred', 'value' => (int) $overview['total_referred'], 'class' => 'neutral'),
		array('label' => 'LmdbReferralActiveReferred', 'value' => (int) $overview['active_referred'], 'class' => 'success'),
		array('label' => 'LmdbReferralSignedReferred', 'value' => (int) $overview['signed_referred'], 'class' => 'success'),
		array('label' => 'LmdbReferralConversionRate', 'value' => price((float) $overview['conversion_rate']).' %', 'class' => 'accent'),
		array('label' => 'LmdbReferralGeneratedCAHT', 'value' => price((float) $overview['amount_ht']), 'class' => 'money'),
		array('label' => 'LmdbReferralAverageBasketHT', 'value' => price((float) $overview['average_basket_ht']), 'class' => 'money'),
		array('label' => 'LmdbReferralAverageDelay', 'value' => price((float) $overview['average_delay']).' '.$langs->trans('Days'), 'class' => 'neutral'),
		array('label' => 'LmdbReferralBecameReferrers', 'value' => (int) $overview['referred_became_referrers'], 'class' => 'accent'),
	);

	print '<div class="lmdbreferral-dashboard-grid">';
	foreach ($cards as $card) {
		print '<div class="lmdbreferral-kpi-card lmdbreferral-kpi-'.dol_escape_htmltag((string) $card['class']).'">';
		print '<div class="lmdbreferral-kpi-label">'.$langs->trans((string) $card['label']).'</div>';
		print '<div class="lmdbreferral-kpi-value">'.$card['value'].'</div>';
		print '</div>';
	}
	print '</div>';
}

/**
 * Print funnel.
 *
 * @param array<string,mixed> $funnel Funnel data
 * @return void
 */
function lmdbreferral_dashboard_print_funnel(array $funnel)
{
	global $langs;

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th colspan="3">'.$langs->trans('LmdbReferralConversionFunnel').'</th></tr>';
	$graphHtml = lmdbreferral_dashboard_try_dolgraph_funnel($funnel);
	if ($graphHtml !== '') {
		print '<tr class="oddeven"><td colspan="3">'.$graphHtml.'</td></tr>';
	}
	if (!empty($funnel['steps']) && is_array($funnel['steps'])) {
		foreach ($funnel['steps'] as $step) {
			$value = isset($step['value']) ? (int) $step['value'] : 0;
			$percent = isset($step['percent']) ? (float) $step['percent'] : 0.0;
			print '<tr class="oddeven">';
			print '<td class="titlefield">'.$langs->trans((string) $step['label']).'</td>';
			print '<td class="right maxwidth100">'.((int) $value).' <span class="opacitymedium">('.price($percent).' %)</span></td>';
			print '<td><div class="lmdbreferral-funnel-bar"><span style="width:'.min(100, max(0, $percent)).'%"></span></div></td>';
			print '</tr>';
		}
	}
	print '<tr class="liste_total"><td>'.$langs->trans('LmdbReferralSignedProposal').'</td><td class="right">'.((int) $funnel['signed_propals']).'</td><td></td></tr>';
	print '<tr class="liste_total"><td>'.$langs->trans('LmdbReferralGeneratedCAHT').'</td><td class="right">'.price((float) $funnel['amount_ht']).'</td><td></td></tr>';
	print '<tr class="liste_total"><td>'.$langs->trans('LmdbReferralSignedAmountTTC').'</td><td class="right">'.price((float) $funnel['amount_ttc']).'</td><td></td></tr>';
	print '<tr class="liste_total"><td>'.$langs->trans('LmdbReferralAverageBasketHT').'</td><td class="right">'.price((float) $funnel['average_basket_ht']).'</td><td></td></tr>';
	print '</table>';
}

/**
 * Try to render funnel with DolGraph, return empty string on unsupported environments.
 *
 * @param array<string,mixed> $funnel Funnel data
 * @return string
 */
function lmdbreferral_dashboard_try_dolgraph_funnel(array $funnel)
{
	global $langs;

	if (!file_exists(DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php')) {
		return '';
	}
	require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
	if (!class_exists('DolGraph')) {
		return '';
	}

	try {
		$bufferLevel = ob_get_level();
		$data = array();
		if (!empty($funnel['steps']) && is_array($funnel['steps'])) {
			foreach ($funnel['steps'] as $step) {
				$data[] = array($langs->trans((string) $step['label']), isset($step['value']) ? (int) $step['value'] : 0);
			}
		}
		if (empty($data)) {
			return '';
		}

		$graph = new DolGraph();
		if (method_exists($graph, 'SetData')) {
			$graph->SetData($data);
		}
		if (method_exists($graph, 'SetType')) {
			$graph->SetType(array('bars'));
		}
		if (method_exists($graph, 'SetWidth')) {
			$graph->SetWidth('100%');
		}
		if (method_exists($graph, 'SetHeight')) {
			$graph->SetHeight('180');
		}
		if (!method_exists($graph, 'draw') || !method_exists($graph, 'show')) {
			return '';
		}

		ob_start();
		$graph->draw('lmdbreferral_funnel', '');
		$output = ob_get_clean();
		$shown = $graph->show();

		return (string) $output.(string) $shown;
	} catch (Throwable $e) {
		while (isset($bufferLevel) && ob_get_level() > $bufferLevel) {
			ob_end_clean();
		}

		return '';
	}
}

/**
 * Print ranking by signed referred thirdparties.
 *
 * @param array<int,array<string,mixed>> $rows Ranking rows
 * @return void
 */
function lmdbreferral_dashboard_print_ranking_signed(array $rows)
{
	global $langs;

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th colspan="6">'.$langs->trans('LmdbReferralRankingBySignedReferred').'</th></tr>';
	print '<tr class="liste_titre"><th class="right">'.$langs->trans('Rank').'</th><th>'.$langs->trans('LmdbReferralReferrer').'</th><th>'.$langs->trans('Type').'</th><th class="right">'.$langs->trans('LmdbReferralTotalReferred').'</th><th class="right">'.$langs->trans('LmdbReferralSignedReferred').'</th><th class="right">'.$langs->trans('LmdbReferralGeneratedCAHT').'</th></tr>';
	$rank = 0;
	foreach ($rows as $row) {
		$rank++;
		print '<tr class="oddeven">';
		print '<td class="right">'.$rank.'</td>';
		print '<td>'.lmdbreferralGetReferrerNomUrl((string) $row['referrer_type'], (int) $row['referrer_id']).'</td>';
		print '<td>'.$langs->trans((string) $row['referrer_type'] === 'soc' ? 'ThirdParty' : 'User').'</td>';
		print '<td class="right">'.((int) $row['filleuls']).'</td>';
		print '<td class="right">'.((int) $row['signed_referred']).'</td>';
		print '<td class="right">'.price((float) $row['amount_ht']).'</td>';
		print '</tr>';
	}
	if ($rank === 0) {
		lmdbreferralPrintNoRecordRow(6);
	}
	print '</table>';
}

/**
 * Print ranking by amount.
 *
 * @param array<int,array<string,mixed>> $rows Ranking rows
 * @return void
 */
function lmdbreferral_dashboard_print_ranking_amount(array $rows)
{
	global $langs;

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th colspan="6">'.$langs->trans('LmdbReferralRankingByCAHT').'</th></tr>';
	print '<tr class="liste_titre"><th class="right">'.$langs->trans('Rank').'</th><th>'.$langs->trans('LmdbReferralReferrer').'</th><th>'.$langs->trans('Type').'</th><th class="right">'.$langs->trans('LmdbReferralGeneratedCAHT').'</th><th class="right">'.$langs->trans('LmdbReferralSignedReferred').'</th><th class="right">'.$langs->trans('LmdbReferralAverageBasketHT').'</th></tr>';
	$rank = 0;
	foreach ($rows as $row) {
		$rank++;
		print '<tr class="oddeven">';
		print '<td class="right">'.$rank.'</td>';
		print '<td>'.lmdbreferralGetReferrerNomUrl((string) $row['referrer_type'], (int) $row['referrer_id']).'</td>';
		print '<td>'.$langs->trans((string) $row['referrer_type'] === 'soc' ? 'ThirdParty' : 'User').'</td>';
		print '<td class="right">'.price((float) $row['amount_ht']).'</td>';
		print '<td class="right">'.((int) $row['signed_referred']).'</td>';
		print '<td class="right">'.price((float) $row['average_basket_ht']).'</td>';
		print '</tr>';
	}
	if ($rank === 0) {
		lmdbreferralPrintNoRecordRow(6);
	}
	print '</table>';
}

/**
 * Print follow-up list.
 *
 * @param array<int,array<string,mixed>> $rows Follow-up rows
 * @return void
 */
function lmdbreferral_dashboard_print_followup(array $rows)
{
	global $langs, $db;

	print '<table class="tagtable nobottomiftotal liste centpercent">';
	print '<tr class="liste_titre"><th colspan="7">'.$langs->trans('LmdbReferralFollowUpTitle').'</th></tr>';
	print '<tr class="liste_titre"><th>'.$langs->trans('LmdbReferralReferredThirdparty').'</th><th>'.$langs->trans('LmdbReferralReferrer').'</th><th>'.$langs->trans('LmdbReferralAttachedDate').'</th><th class="right">'.$langs->trans('LmdbReferralAgeDays').'</th><th>'.$langs->trans('LmdbReferralReferrerType').'</th><th>'.$langs->trans('SalesRepresentatives').'</th><th>'.$langs->trans('Action').'</th></tr>';
	$count = 0;
	foreach ($rows as $row) {
		$count++;
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.((int) $row['filleul_id']).'">'.dol_escape_htmltag((string) $row['filleul_label']).'</a></td>';
		print '<td>'.lmdbreferralGetReferrerNomUrl((string) $row['referrer_type'], (int) $row['referrer_id']).'</td>';
		print '<td>'.dol_print_date($db->jdate((string) $row['date_creation']), 'day').'</td>';
		print '<td class="right">'.((int) $row['age_days']).'</td>';
		print '<td>'.$langs->trans((string) $row['referrer_type'] === 'soc' ? 'ThirdParty' : 'User').'</td>';
		print '<td>'.dol_escape_htmltag((string) $row['commercials']).'</td>';
		print '<td><a class="button smallpaddingimp" href="'.DOL_URL_ROOT.'/societe/card.php?socid='.((int) $row['filleul_id']).'">'.$langs->trans('View').'</a></td>';
		print '</tr>';
	}
	if ($count === 0) {
		lmdbreferralPrintNoRecordRow(7);
	}
	print '</table>';
}

/**
 * Print star graph SVG.
 *
 * @param array{center:array<string,mixed>,nodes:array<int,array<string,mixed>>,edges:array<int,array<string,mixed>>,overflow_count:int} $graph Graph data
 * @return void
 */
function lmdbreferral_dashboard_print_star_graph(array $graph)
{
	global $langs;

	print '<div class="lmdbreferral-stargraph">';
	print '<div class="titre">'.$langs->trans('LmdbReferralStarGraph').'</div>';
	if (empty($graph['center'])) {
		print '<div class="opacitymedium">'.$langs->trans('NoRecordFound').'</div>';
		print '</div>';
		return;
	}

	$width = 820;
	$height = 460;
	$cx = 410;
	$cy = 230;
	$positions = array();
	$positions[(string) $graph['center']['id']] = array('x' => $cx, 'y' => $cy);
	$nodes = $graph['nodes'];
	$count = max(1, count($nodes));
	foreach ($nodes as $index => $node) {
		$angle = (-90 + (360 / $count) * $index) * M_PI / 180;
		$radius = !empty($node['level']) && (int) $node['level'] > 1 ? 200 : 145;
		$positions[(string) $node['id']] = array(
			'x' => $cx + cos($angle) * $radius,
			'y' => $cy + sin($angle) * $radius,
		);
	}

	print '<svg viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.dol_escape_htmltag($langs->trans('LmdbReferralStarGraph')).'">';
	foreach ($graph['edges'] as $edge) {
		$from = (string) $edge['from'];
		$to = (string) $edge['to'];
		if (empty($positions[$from]) || empty($positions[$to])) {
			continue;
		}
		print '<line class="lmdbreferral-star-edge" x1="'.price2num($positions[$from]['x']).'" y1="'.price2num($positions[$from]['y']).'" x2="'.price2num($positions[$to]['x']).'" y2="'.price2num($positions[$to]['y']).'"></line>';
	}
	lmdbreferral_dashboard_print_graph_node($graph['center'], $positions[(string) $graph['center']['id']], true);
	foreach ($nodes as $node) {
		if (!empty($positions[(string) $node['id']])) {
			lmdbreferral_dashboard_print_graph_node($node, $positions[(string) $node['id']], false);
		}
	}
	print '</svg>';
	if (!empty($graph['overflow_count'])) {
		print '<div class="opacitymedium center">'.$langs->trans('LmdbReferralGraphOverflow', (int) $graph['overflow_count']).'</div>';
	}
	print '</div>';
}

/**
 * Print a graph node.
 *
 * @param array<string,mixed> $node Node
 * @param array<string,float> $pos  Position
 * @param bool                $center Center node
 * @return void
 */
function lmdbreferral_dashboard_print_graph_node(array $node, array $pos, $center)
{
	$class = $center ? 'center' : (string) $node['type'];
	$label = dol_trunc((string) $node['label'], $center ? 28 : 20);
	$url = !empty($node['url']) ? (string) $node['url'] : '#';
	print '<a href="'.dol_escape_htmltag($url).'">';
	print '<g class="lmdbreferral-star-node lmdbreferral-star-node-'.dol_escape_htmltag($class).'">';
	print '<title>'.dol_escape_htmltag((string) $node['label']).'</title>';
	print '<circle cx="'.price2num($pos['x']).'" cy="'.price2num($pos['y']).'" r="'.($center ? 34 : 24).'"></circle>';
	print '<text x="'.price2num($pos['x']).'" y="'.price2num($pos['y'] + ($center ? 52 : 42)).'" text-anchor="middle">'.dol_escape_htmltag($label).'</text>';
	print '</g>';
	print '</a>';
}
