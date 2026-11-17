<?php
/**
 * Back-compat aliases for InsightWeb's cross-plugin API (renamed aw_si_* -> insightweb_* on 17 Nov 2026).
 *
 * SourceWeb (aw-sourcing) and DirectoryWeb still call the old aw_si_ names, and RankWeb (aw-seo-extras) may during a
 * partial-deploy window. These guarded shims keep them resolving. Remove once no plugin calls the aw_si_ names.
 * (InsightWeb's own ~250 functions, its options, cron hooks and tables were renamed to insightweb_; the live data is
 * moved by the versioned migration in search-insights/schema.php + visitors.php, and kits-core's keyword migration.)
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'AW_SI_SLUG' ) || ( defined( 'INSIGHTWEB_SLUG' ) && define( 'AW_SI_SLUG', INSIGHTWEB_SLUG ) );

if ( ! function_exists( 'aw_si_url' ) && function_exists( 'insightweb_url' ) ) {
	function aw_si_url( ...$a ) { return insightweb_url( ...$a ); }
}
if ( ! function_exists( 'aw_si_cap' ) && function_exists( 'insightweb_cap' ) ) {
	function aw_si_cap( ...$a ) { return insightweb_cap( ...$a ); }
}
if ( ! function_exists( 'aw_si_ready' ) && function_exists( 'insightweb_ready' ) ) {
	function aw_si_ready( ...$a ) { return insightweb_ready( ...$a ); }
}
if ( ! function_exists( 'aw_si_object_path' ) && function_exists( 'insightweb_object_path' ) ) {
	function aw_si_object_path( ...$a ) { return insightweb_object_path( ...$a ); }
}
if ( ! function_exists( 'aw_si_health_test' ) && function_exists( 'insightweb_health_test' ) ) {
	function aw_si_health_test( ...$a ) { return insightweb_health_test( ...$a ); }
}
if ( ! function_exists( 'aw_si_page_metrics' ) && function_exists( 'insightweb_page_metrics' ) ) {
	function aw_si_page_metrics( ...$a ) { return insightweb_page_metrics( ...$a ); }
}
if ( ! function_exists( 'aw_si_path' ) && function_exists( 'insightweb_path' ) ) {
	function aw_si_path( ...$a ) { return insightweb_path( ...$a ); }
}
if ( ! function_exists( 'aw_si_runs' ) && function_exists( 'insightweb_runs' ) ) {
	function aw_si_runs( ...$a ) { return insightweb_runs( ...$a ); }
}
if ( ! function_exists( 'aw_si_api_allowed' ) && function_exists( 'insightweb_api_allowed' ) ) {
	function aw_si_api_allowed( ...$a ) { return insightweb_api_allowed( ...$a ); }
}

// The seven Search Insights setting constants (defined in settings.php as INSIGHTWEB_*): keep the legacy AW_SI_* names
// defined for RankWeb's bridge and any code reading them through the transition. Same values (RankWeb's defaults).
foreach ( array( 'HIGH_TRENDS', 'HIGH_IMPR', 'QUICK_MIN', 'MOVERS', 'PSI_FLAG', 'RM_LOW', 'KEEP_DAYS' ) as $insightweb_num ) {
	defined( "AW_SI_{$insightweb_num}" ) || ( defined( "INSIGHTWEB_{$insightweb_num}" ) && define( "AW_SI_{$insightweb_num}", constant( "INSIGHTWEB_{$insightweb_num}" ) ) );
}
unset( $insightweb_num );
