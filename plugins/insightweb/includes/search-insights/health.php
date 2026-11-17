<?php
/**
 * Site Health test "Search Insights sources fresh" (owner addendum, 24 Oct): warns when a source never ran, last failed,
 * or is more than twice its schedule late. Crawler access has its own test (crawlcheck.php) and is not repeated here.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The test result in Site Health's format. */
function aw_si_health_test(): array {
	$late  = array();
	$fails = array();
	foreach ( aw_si_sources() as $src => $s ) {
		if ( aw_si_rising_blocked( $src ) ) {
			continue; // refused by the service (HTTP 429/403): a known limit, said on the panel, not a failure
		}
		if ( in_array( $src, array( 'crawl-in', 'crawl-out' ), true ) ) {
			continue; // judged once, by the crawler-access test (crawlcheck.php), which also says when a run is missing
		}
		$err = aw_si_run_problem( $src );
		$dry = aw_si_rows_dry_days( $src );
		if ( $err ) {
			$fails[] = $s['label'] . ': ' . $err;
		} elseif ( $dry ) {
			/* translators: %d: days. */
			$fails[] = $s['label'] . ': ' . sprintf( __( 'source failed: its runs have stored no rows for %d days.', 'insightweb' ), $dry );
		} elseif ( aw_si_stale( $src ) ) {
			$late[] = $s['label'];
		}
	}
	$ok     = ! $late && ! $fails;
	$result = array(
		'label'       => $ok ? __( 'Search Insights sources are fresh', 'insightweb' ) : __( 'Some Search Insights sources are late or failing', 'insightweb' ),
		'status'      => $ok ? 'good' : 'recommended',
		'badge'       => array(
			'label' => __( 'Search Insights', 'insightweb' ),
			'color' => $ok ? 'blue' : 'orange',
		),
		'description' => '<p>' . esc_html__( 'Each Search Insights source keeps its last good data when a run fails, and shows when it was last updated.', 'insightweb' ) . '</p>',
		'actions'     => '<p><a href="' . esc_url( aw_si_url() ) . '">' . esc_html__( 'Open Search Insights', 'insightweb' ) . '</a></p>',
		'test'        => 'aw_search_insights_fresh',
	);
	if ( ! $ok ) {
		$items = '';
		foreach ( $fails as $f ) {
			$items .= '<li>' . esc_html( $f ) . '</li>';
		}
		foreach ( $late as $l ) {
			/* translators: %s: source name. */
			$items .= '<li>' . esc_html( sprintf( __( '%s: not updated on schedule.', 'insightweb' ), $l ) ) . '</li>';
		}
		$result['description'] .= '<ul>' . $items . '</ul>';
	}
	return $result;
}

add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['aw_search_insights_fresh'] = array(
			'label' => __( 'Search Insights sources fresh', 'insightweb' ),
			'test'  => 'aw_si_health_test',
		);
		return $tests;
	}
);
