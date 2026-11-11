<?php
/**
 * Deploy check: InsightWeb (Search Insights) loads once and every scheduled job has its handler, whoever loads it.
 * Run with `wp eval-file tools/ci/insightweb_load_check.php <case>` from tools/ci/smoke.sh, where <case> is one of:
 *
 *   bridge    RankWeb active, InsightWeb not: RankWeb's bridge loads it (docs/insightweb-plan.md 3.2).
 *   both      both active, in either order: InsightWeb loads it, the bridge does nothing.
 *
 * Each case also checks that no Search Insights job is booked twice. Prints ok/FAIL lines; exits 1 on any FAIL.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aw_case = (string) ( $args[0] ?? '' );
$aw_fail = 0;
$aw_t    = function ( bool $ok, string $what ) use ( &$aw_fail, $aw_case ) {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . "insightweb {$aw_case}: {$what}\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	$aw_fail += $ok ? 0 : 1;
};

$aw_t( defined( 'INSIGHTWEB_LOADED' ) && function_exists( 'aw_si_url' ) && function_exists( 'aw_si_page_metrics' ), 'Search Insights is loaded' );
$aw_t( ( 'bridge' !== $aw_case ) === defined( 'INSIGHTWEB_FILE' ), 'bridge' === $aw_case ? 'loaded by RankWeb\'s bridge (InsightWeb not active)' : 'loaded by InsightWeb itself' );
$aw_t( function_exists( 'aw_seo_api_allowed' ), 'RankWeb is loaded' );
$aw_t( function_exists( 'aw_si_api_allowed' ) && 'aw_si_api_allowed' === ( rest_get_server()->get_routes()['/aw/v1/search-insights'][0]['permission_callback'] ?? '' ), 'aw/v1/search-insights uses aw_si_api_allowed' );
$aw_t( isset( rest_get_server()->get_routes()['/aw/v1/search-insights/crawl'] ), 'aw/v1/search-insights/crawl is registered' );
$aw_t( isset( rest_get_server()->get_routes()['/aw/v1/seo-focus'] ), 'aw/v1/seo-focus stays RankWeb\'s' );

// The seven numbers are constants, with the defaults when nothing is saved (as RankWeb defined them).
foreach ( array( 'AW_SI_HIGH_TRENDS', 'AW_SI_HIGH_IMPR', 'AW_SI_QUICK_MIN', 'AW_SI_MOVERS', 'AW_SI_PSI_FLAG', 'AW_SI_RM_LOW', 'AW_SI_KEEP_DAYS' ) as $aw_const ) {
	$aw_t( defined( $aw_const ), "{$aw_const} is defined" );
}
$aw_t( 90 === (int) AW_SI_KEEP_DAYS && 20 === (int) AW_SI_HIGH_TRENDS, 'the numbers have RankWeb\'s defaults' );

// Every Search Insights job has a handler (docs/insightweb-plan.md 1.4), so none runs empty or is lost.
$aw_hooks = array( 'aw_si_clarity', 'aw_si_clarity_retry', 'aw_si_daily', 'aw_si_gsc_types', 'aw_si_ga4', 'aw_si_inspect', 'aw_si_inspect_more', 'aw_si_alerts', 'aw_si_rollup', 'aw_si_psi', 'aw_si_psi_more', 'aw_si_bing_crawl', 'aw_si_crawl', 'aw_si_bing', 'aw_si_bing_kw', 'aw_si_geo_refresh', 'aw_si_srv_speed', 'aw_si_srv_ai' );
foreach ( $aw_hooks as $aw_hook ) {
	$aw_t( (bool) has_action( $aw_hook ), "{$aw_hook} has a handler" );
}
foreach ( array( 'aw_si_daily', 'aw_si_rollup', 'aw_si_ga4', 'aw_si_psi', 'aw_si_clarity', 'aw_si_bing' ) as $aw_hook ) {
	$aw_t( in_array( $aw_hook, aw_si_cron_hooks(), true ), "{$aw_hook} is in the deactivation list" );
}

// No job booked twice (each repeating job once, with no arguments).
$aw_count = array();
foreach ( (array) _get_cron_array() as $aw_events ) {
	foreach ( (array) $aw_events as $aw_hook => $aw_by_args ) {
		if ( 0 === strpos( (string) $aw_hook, 'aw_si_' ) ) {
			$aw_count[ $aw_hook ] = ( $aw_count[ $aw_hook ] ?? 0 ) + count( (array) $aw_by_args );
		}
	}
}
foreach ( array( 'aw_si_daily', 'aw_si_rollup', 'aw_si_ga4', 'aw_si_psi', 'aw_si_gsc_types', 'aw_si_inspect', 'aw_si_crawl', 'aw_si_bing_crawl', 'aw_si_bing_kw' ) as $aw_hook ) {
	$aw_t( 1 === ( $aw_count[ $aw_hook ] ?? 0 ), "{$aw_hook} is booked once (" . (int) ( $aw_count[ $aw_hook ] ?? 0 ) . ')' );
}

// The screen: InsightWeb's own menu, the same slug as before (owner, 11 Nov 2026), not under RankWeb. Run as the CI admin
// (smoke.sh passes --user=ci), so the capability checks in add_*_page pass.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
global $submenu, $menu;
$menu    = is_array( $menu ) ? $menu : array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- CLI only, no admin screen.
$submenu = is_array( $submenu ) ? $submenu : array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
try {
	do_action( 'admin_menu', '' );
} catch ( Throwable $aw_e ) {
	$aw_t( false, 'admin_menu ran without an error: ' . $aw_e->getMessage() );
}
$aw_own   = in_array( 'aw-search-insights', array_column( $menu, 2 ), true );
$aw_under = in_array( 'aw-search-insights', array_column( (array) ( $submenu['aw-seo-plus'] ?? array() ), 2 ), true );
$aw_t( $aw_own && ! $aw_under, 'Search Insights is InsightWeb\'s own menu, not under RankWeb (same address)' );

if ( $aw_fail ) {
	exit( 1 );
}
