<?php
/**
 * InsightWeb's bootstrap: Search Insights (owner, 24 Oct 2026; plan in docs/seo/search-insights-plan.md). One record
 * per keyword, with its roles, owning pages, Search Console figures by day, priority and a suggested action. Read-only
 * data and suggestions: nothing here edits Rank Math keyphrases or the Sourcing lists.
 *
 * Moved out of RankWeb on 11 Nov 2026 with no other change (docs/insightweb-plan.md). Called on plugins_loaded by
 * insightweb.php, or by RankWeb's bridge while InsightWeb is not active yet; whichever calls first loads it, once.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'INSIGHTWEB_LOADED' ) ) {
	return; // Already loaded on this request (InsightWeb or RankWeb's bridge, whichever came first).
}
define( 'INSIGHTWEB_LOADED', true );
if ( ! defined( 'INSIGHTWEB_DIR' ) ) {
	define( 'INSIGHTWEB_DIR', dirname( __DIR__ ) . '/' );
}

// Kits Core (loaded by Animal Writes Core on live) and Rank Math are needed, as for RankWeb's Search Insights module
// (owner, 11 Nov 2026: checked here, not with a Requires Plugins header).
$aw_si_missing = array_keys(
	array_filter(
		array(
			'Kits Core' => ! defined( 'KITS_CORE_LOADED' ),
			'Rank Math' => ! ( defined( 'RANK_MATH_VERSION' ) && class_exists( '\RankMath\Helper' ) ),
		)
	)
);
if ( $aw_si_missing ) {
	add_action(
		'admin_notices',
		function () use ( $aw_si_missing ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				/* translators: %s: plugin names. */
				echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'InsightWeb does nothing until %s is active.', 'aw-seo-extras' ), implode( ' and ', $aw_si_missing ) ) ) . '</p></div>';
			}
		}
	);
	unset( $aw_si_missing );
	return;
}
unset( $aw_si_missing );

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

require_once INSIGHTWEB_DIR . 'includes/settings.php'; // the seven numbers (constants) and the API rule, first

foreach ( array( 'schema', 'runs', 'sitekit', 'keywords', 'gsc', 'compute', 'rollup', 'cron', 'health', 'searches', 'dryrun', 'rising', 'admin', 'cli' ) as $aw_si_file ) {
	require_once __DIR__ . '/search-insights/' . $aw_si_file . '.php';
}
// Phase 2: GA4, PageSpeed, Clarity, searches on the site, the Pages tab, redirects counted as their target.
// Phase 4: the Check list tab with the bookmarklet live check, and the imports on the Settings tab.
// Phase 5: the monthly snapshot CSV and the rebuilt keyword map. Rank Math: SEO scores, and its Search Console rows as a backup.
// Links (31 Oct 2026): who links to us, the Bing Webmaster monthly run and the links seed.
// Crawler access (31 Oct 2026): weekly checks from the server and from outside (workers), in Site Health.
// Server (3 Nov 2026): crawler requests, 4xx/5xx answers and PHP errors per day, and the hPanel numbers typed by hand.
// Dashboard (3 Nov 2026): the SEO box on the WordPress Dashboard, from the stored rows.
// Referrer icons (3 Nov 2026): site icons beside the referring sources on the Overview.
// Search Console types (3 Nov 2026): Discover and Google News per page, and URL Inspection index status.
// Bing crawl (3 Nov 2026): Bing's crawl statistics weekly and keyword volumes monthly.
// Google Alerts (3 Nov 2026): up to two alert feeds from Animal Writes → Keys, as Mention rows on the Links tab.
foreach ( array( 'sitesearch', 'ga4', 'psi', 'clarity', 'bing', 'sources', 'pages', 'checklist', 'imports', 'snapshot', 'rankmath', 'links', 'links-seed', 'crawlcheck', 'server', 'visitors', 'selfcheck', 'dashboard', 'referrer-icons', 'gsc-types', 'bing-crawl', 'alerts', 'tool-imports', 'openseo-positions' ) as $aw_si_file ) {
	require_once __DIR__ . '/search-insights/' . $aw_si_file . '.php';
}
unset( $aw_si_file );

// The new Dashboard and Settings on the Kits admin shell, the default since 12 Nov 2026 (&ui=classic: the earlier tabs).
require_once INSIGHTWEB_DIR . 'includes/insightweb-dashboard.php';
require_once INSIGHTWEB_DIR . 'includes/insightweb-rest.php';
require_once INSIGHTWEB_DIR . 'includes/insightweb-boxes.php'; // the Overview boxes on the new Dashboard (12 Nov 2026)
require_once INSIGHTWEB_DIR . 'includes/insightweb-ui.php';
