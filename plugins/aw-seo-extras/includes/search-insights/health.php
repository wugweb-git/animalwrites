<?php
/**
 * Site Health test "Search Insights sources fresh" (owner addendum, 24 Oct): warns when a source never ran, last failed,
 * or is more than twice its schedule late.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The test result in Site Health's format. */
function aw_si_health_test(): array {
	$late  = array();
	$fails = array();
	foreach ( aw_si_sources() as $src => $s ) {
		$err = aw_si_run_problem( $src );
		if ( $err ) {
			$fails[] = $s['label'] . ': ' . $err;
		} elseif ( aw_si_stale( $src ) ) {
			$late[] = $s['label'];
		}
	}
	$ok     = ! $late && ! $fails;
	$result = array(
		'label'       => $ok ? __( 'Search Insights sources are fresh', 'aw-seo-extras' ) : __( 'Some Search Insights sources are late or failing', 'aw-seo-extras' ),
		'status'      => $ok ? 'good' : 'recommended',
		'badge'       => array(
			'label' => __( 'Search Insights', 'aw-seo-extras' ),
			'color' => $ok ? 'blue' : 'orange',
		),
		'description' => '<p>' . esc_html__( 'Each Search Insights source keeps its last good data when a run fails, and shows when it was last updated.', 'aw-seo-extras' ) . '</p>',
		'actions'     => '<p><a href="' . esc_url( admin_url( 'tools.php?page=aw-search-insights' ) ) . '">' . esc_html__( 'Open Search Insights', 'aw-seo-extras' ) . '</a></p>',
		'test'        => 'aw_search_insights_fresh',
	);
	if ( ! $ok ) {
		$items = '';
		foreach ( $fails as $f ) {
			$items .= '<li>' . esc_html( $f ) . '</li>';
		}
		foreach ( $late as $l ) {
			/* translators: %s: source name. */
			$items .= '<li>' . esc_html( sprintf( __( '%s: not updated on schedule.', 'aw-seo-extras' ), $l ) ) . '</li>';
		}
		$result['description'] .= '<ul>' . $items . '</ul>';
	}
	return $result;
}

add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['aw_search_insights_fresh'] = array(
			'label' => __( 'Search Insights sources fresh', 'aw-seo-extras' ),
			'test'  => 'aw_si_health_test',
		);
		return $tests;
	}
);
