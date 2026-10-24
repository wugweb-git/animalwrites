<?php
/**
 * Tools → Site Health → "Sourcing ran on time" (owner, 24 Oct 2026): the ingest heartbeat as a core Site Health test.
 * Same rules as the hourly watchdog and its dashboard notice (aw_ingest_problems, relevance.php):
 * - critical: no worker run has reported for 8 hours;
 * - recommended: no News published for 12 hours (or none ever, 12 hours after the first report), or half the
 *   sources failed in the last run;
 * - good: otherwise, with the last run's figures.
 * Read-only: it reads the heartbeat option and sends nothing.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Site Health result. */
function aw_sourcing_health_test() {
	$hb    = (array) get_option( 'aw_ingest_heartbeat', array() );
	$probs = array_intersect_key( aw_ingest_problems(), array_flip( array( 'no_run', 'quiet', 'sources' ) ) ); // scheduled tasks have their own test
	// No News published yet at all: the watchdog's 12-hour rule needs a first published item, so the workers' first
	// report is the baseline here (first_run; last_new for heartbeats from before it existed). last_new alone moves on
	// whenever any other type publishes (Codex review, 24 Oct 2026).
	$new_at = (int) ( $hb['first_run'] ?? $hb['last_new'] ?? 0 );
	if ( ! isset( $probs['quiet'] ) && ! aw_newest_published_news() && $new_at && time() - $new_at >= 12 * HOUR_IN_SECONDS ) {
		$probs['quiet'] = sprintf( 'No News has been published yet; the workers have reported runs since %s.', wp_date( 'j M Y, g:i a', $new_at ) );
	}
	$last  = (int) ( $hb['last_run'] ?? 0 );
	$run   = $last ? sprintf(
		'Last run: %1$s. %2$d new, %3$d published, %4$d sources, %5$d failed.',
		wp_date( 'j M Y, g:i a', $last ),
		(int) ( $hb['new'] ?? 0 ),
		(int) ( $hb['published'] ?? 0 ),
		(int) ( $hb['sources'] ?? 0 ),
		(int) ( $hb['sources_failed'] ?? 0 )
	) : ''; // the problem list says so

	$actions = '<a href="' . esc_url( admin_url( 'options-general.php?page=aw-sourcing' ) ) . '">Settings → Sourcing</a>';
	if ( ! empty( $hb['last_link'] ) ) {
		$actions .= ' · <a href="' . esc_url( (string) $hb['last_link'] ) . '" target="_blank" rel="noopener">Last run on GitHub<span class="screen-reader-text"> (opens in a new tab)</span></a>';
	}
	$result = array(
		'label'       => 'Sourcing ran on time',
		'status'      => 'good',
		'badge'       => array( 'label' => 'Sourcing', 'color' => 'blue' ),
		'description' => '<p>The workers fetch News, the Library, opportunities and press several times a day and report each run to the site. ' . esc_html( $run ) . '</p>',
		'actions'     => '<p>' . $actions . '</p>',
		'test'        => 'aw_sourcing_on_time',
	);
	if ( $probs ) {
		$result['status']       = isset( $probs['no_run'] ) ? 'critical' : 'recommended';
		$result['badge']['color'] = isset( $probs['no_run'] ) ? 'red' : 'orange';
		$result['label']        = isset( $probs['no_run'] ) ? 'Sourcing has not run on time' : 'Sourcing ran, with problems';
		$result['description'] .= '<ul>' . implode( '', array_map( fn( $p ) => '<li>' . esc_html( $p ) . '</li>', $probs ) ) . '</ul>';
	}
	return $result;
}

add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['aw_sourcing_on_time'] = array(
			'label' => 'Sourcing ran on time',
			'test'  => 'aw_sourcing_health_test',
		);
		return $tests;
	}
);
