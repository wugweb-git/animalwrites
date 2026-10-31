<?php
/**
 * Search Insights (owner, 24 Oct 2026; plan in docs/seo/search-insights-plan.md). One record per keyword, with its
 * roles, owning pages, Search Console figures by day, priority and a suggested action. Read-only data and suggestions:
 * nothing here edits Rank Math keyphrases or the Sourcing lists.
 *
 * Phase 1: tables, the one-time seed from the keyword map, Search Console through Site Kit (each module as its own
 * owner, one call per calendar day), keyword roles, priority, the 90-day rollup, Overview and Keywords tabs, a Site
 * Health test and the `wp aw-si` commands. The old Search demand screen (search-demand.php) was removed on 25 Oct 2026:
 * its Search Console call is aw_si_gsc_query() and its gaps are an Overview box (searches.php).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The keyword table and its helpers are Animal Writes Core's (owner's decision 3, 25 Oct 2026): without Core, Search
// Insights stays off.
if ( ! function_exists( 'aw_kw_install' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'Search Insights needs Animal Writes Core (the keyword table lives there). It is off until Core is active.', 'aw-seo-extras' ) . '</p></div>';
			}
		}
	);
	return;
}

foreach ( array( 'schema', 'runs', 'sitekit', 'keywords', 'gsc', 'compute', 'rollup', 'cron', 'health', 'searches', 'dryrun', 'rising', 'admin', 'cli' ) as $aw_si_file ) {
	require_once __DIR__ . '/search-insights/' . $aw_si_file . '.php';
}
// Phase 2: GA4, PageSpeed, Clarity, searches on the site, the Pages tab, redirects counted as their target.
// Phase 4: the Check list tab with the bookmarklet live check, and the imports on the Settings tab.
// Phase 5: the monthly snapshot CSV and the rebuilt keyword map. Rank Math: SEO scores, and its Search Console rows as a backup.
// Links (31 Oct 2026): who links to us, and the Bing Webmaster monthly run.
foreach ( array( 'sitesearch', 'ga4', 'psi', 'clarity', 'bing', 'sources', 'pages', 'checklist', 'imports', 'snapshot', 'rankmath', 'links' ) as $aw_si_file ) {
	require_once __DIR__ . '/search-insights/' . $aw_si_file . '.php';
}
unset( $aw_si_file );
