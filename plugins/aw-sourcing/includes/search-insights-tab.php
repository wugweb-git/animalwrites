<?php
/**
 * AW Sourcing in Tools → Search Insights (owner, 24 Oct 2026; rebuilt on Search Insights phase 1).
 *
 * - "Sourcing keywords" tab (filter aw_si_tabs): per Google News search of the workers, the items it brought in over
 *   30 days, News and Library apart (aw_sourcing_keyword_stats), with the 28-day and 3-month clicks and impressions of the
 *   published items' pages from Search Insights (aw_si_page_metrics: the daily page rows, which include searches Google hides).
 * - The searches behind Sourcing → Keywords → Suggestions (filter aw_search_demand_terms): Search
 *   Insights' keywords with their 28-day figures, English only (owner: no Hindi or Hinglish suggestions).
 * - Soft link both ways: without aw-seo-extras (or before Search Insights is installed) the tab is not added and the
 *   suggestions get no searches; Search Insights' own screens do not depend on this file.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The tab sits between Search Insights' Searches and Settings tabs.
add_filter( 'aw_si_tabs', fn( $tabs ) => (array) $tabs + array( 'sourcing' => 'Sourcing keywords' ) );

add_action(
	'aw_si_tab_sourcing',
	function () {
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		require_once __DIR__ . '/class-aw-sourcing-si-keywords-table.php';
		$table = new AW_Sourcing_SI_Keywords_Table( aw_sourcing_keyword_stats( 30 ) );
		$table->prepare_items();
		echo '<p>Each English Include word in <a href="' . esc_url( admin_url( 'admin.php?page=aw-sourcing&tab=keywords' ) ) . '">Sourcing → Keywords</a> is a Google News search in every run. '
			. '<strong>Fetched</strong>: the items it brought in over the last 30 days, whatever happened to them since (held, merged, backfilled). <strong>Published</strong>: those live now. '
			. '<strong>Impressions and clicks</strong>: Search Console over the last 28 days and the last 3 months for the published items\' own pages, searches Google hides included. '
			. 'Items from before 24 Oct 2026 did not record their search: they are counted by the word in their title or summary and marked estimated, and one with several of the words counts under each. Hindi Include words are not searches and are not listed.</p>';
		if ( ! $table->has_page_data() ) { // Search Insights has not stored a Search Console run yet, or is switched off
			$off = function_exists( 'aw_si_ready' ) && ! aw_si_ready();
			echo '<div class="notice notice-info inline"><p><span class="dashicons dashicons-info" aria-hidden="true"></span> '
				. esc_html( $off ? 'Search Insights is off, so there are no page figures. Settings → Run the daily update starts it again.' : ( $table->page_data_stale() ? 'Search Insights has not had a successful Search Console run in the last 28 days, so there are no current page figures. Check Search Insights → Settings.' : 'Page figures appear after Search Insights\' next daily Search Console run (07:10 IST); the first 30 days fill in over about two runs, the 3 months over about a week.' ) ) . '</p></div>';
		}
		$table->display();
	}
);

/**
 * Search Insights' searches for the suggestions: keyword, 28-day impressions / clicks / position, brand. English only.
 *
 * @param mixed $terms Null when nothing has offered searches yet.
 * @return array|null
 */
function aw_sourcing_si_terms( $terms ) {
	if ( ! aw_sourcing_si_available() ) {
		return $terms;
	}
	global $wpdb;
	$k     = aw_si_table( 'keywords' );
	$brand = defined( 'AW_SI_ROLE_BRAND' ) ? (int) AW_SI_ROLE_BRAND : 8;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the table name comes from Search Insights.
	$rows = $wpdb->get_results( "SELECT norm, label, impr28, clicks28, pos28, roles FROM {$k} WHERE lang = 'en' AND impr28 >= 20 ORDER BY impr28 DESC LIMIT 5000" ); // the suggestions' own floor (keyword-suggestions.php)
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'q'      => '' !== (string) $r->label ? (string) $r->label : (string) $r->norm,
			'impr'   => (int) $r->impr28,
			'clicks' => (int) $r->clicks28,
			'pos'    => null === $r->pos28 ? 0.0 : (float) $r->pos28,
			'brand'  => (bool) ( (int) $r->roles & $brand ),
			'rising' => false, // Search Console Rising is off until 8 weeks of history (owner, 24 Oct 2026)
		);
	}
	return array_merge( is_array( $terms ) ? $terms : array(), $out ); // another provider's searches are kept
}
add_filter( 'aw_search_demand_terms', 'aw_sourcing_si_terms' );

/** Search Insights is installed and switched on (aw-seo-extras active). */
function aw_sourcing_si_available() {
	return function_exists( 'aw_si_ready' ) && function_exists( 'aw_si_table' ) && aw_si_ready();
}
