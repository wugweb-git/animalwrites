<?php
/**
 * Run log per source (option aw_si_runs): last good run, last error, rows written. Feeds the "last updated" notices on
 * every tab and the Site Health test. A failed run never clears the last good data (plan §5, failure rule).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_RUNS = 'aw_si_runs';

/**
 * Sources and how often each should run. Later phases add theirs through the aw_si_sources filter.
 *
 * @return array<string, array{label: string, every: int}>
 */
function aw_si_sources(): array {
	$s = array(
		'gsc'    => array(
			'label' => __( 'Search Console (Site Kit)', 'aw-seo-extras' ),
			'every' => DAY_IN_SECONDS,
		),
		'roles'  => array(
			'label' => __( 'Keyword roles (Rank Math, Sourcing)', 'aw-seo-extras' ),
			'every' => DAY_IN_SECONDS,
		),
		'rollup' => array(
			'label' => __( 'Retention (90-day rollup)', 'aw-seo-extras' ),
			'every' => DAY_IN_SECONDS,
		),
	);
	return (array) apply_filters( 'aw_si_sources', $s );
}

/** The run log: source => ok, error, info. */
function aw_si_runs(): array {
	$r = get_option( AW_SI_RUNS, array() );
	return is_array( $r ) ? $r : array();
}

/**
 * Record a good run.
 *
 * @param string $src  Source key.
 * @param array  $info Kept as is: rows written, days fetched, warnings.
 */
function aw_si_run_ok( string $src, array $info = array() ): void {
	$r         = aw_si_runs();
	$r[ $src ] = array(
		'ok'    => time(),
		'error' => null,
		'info'  => $info,
	);
	update_option( AW_SI_RUNS, $r, false );
}

/**
 * Record a failed run; the last good time and data stay.
 *
 * @param string $src     Source key.
 * @param string $message What went wrong, in plain words.
 */
function aw_si_run_fail( string $src, string $message ): void {
	$r                  = aw_si_runs();
	$r[ $src ]          = $r[ $src ] ?? array( 'ok' => 0 );
	$r[ $src ]['error'] = array(
		'time'    => time(),
		'message' => $message,
	);
	update_option( AW_SI_RUNS, $r, false );
}

/**
 * True when a source never ran or its last good run is older than twice its schedule.
 *
 * @param string $src Source key.
 */
function aw_si_stale( string $src ): bool {
	$every = (int) ( aw_si_sources()[ $src ]['every'] ?? DAY_IN_SECONDS );
	$ok    = (int) ( aw_si_runs()[ $src ]['ok'] ?? 0 );
	return ! $ok || ( time() - $ok ) > 2 * $every;
}
