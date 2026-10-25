<?php
/**
 * Phase 2 sources (plan §5.1, §5.2, §5.5): their entries in the run log and Site Health, their schedule (IST), how the
 * rollup keeps their monthly values, and the redirect map that counts an old address as the page it redirects to.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'aw_si_sources',
	function ( $s ) {
		$s['ga4'] = array(
			'label' => __( 'GA4 (Site Kit)', 'aw-seo-extras' ),
			'every' => DAY_IN_SECONDS,
		);
		$s['psi'] = array(
			'label' => __( 'PageSpeed (Site Kit)', 'aw-seo-extras' ),
			'every' => WEEK_IN_SECONDS,
		);
		if ( aw_si_clarity_on() ) { // Listed only once the owner has added the token, so it never shows as late before.
			$s['clarity'] = array(
				'label' => __( 'Clarity', 'aw-seo-extras' ),
				'every' => DAY_IN_SECONDS,
			);
		}
		return $s;
	}
);

// Monthly rows keep the last value of rates, scores and notes (sums would mean nothing); counts are summed.
add_filter(
	'aw_si_last_value_metrics',
	function ( $last ) {
		foreach ( array( 'score', 'lcp', 'tbt', 'cls' ) as $m ) {
			$last[] = 'psi:' . $m . '-mobile';
			$last[] = 'psi:' . $m . '-desktop';
		}
		foreach ( array( 'scroll', 'rage', 'dead', 'quickback', 'jserror', 'flag' ) as $m ) {
			$last[] = 'clarity:' . $m;
		}
		$last[] = 'psi:flag';
		$last[] = 'clarity-note:note';
		return $last;
	}
);

/** Cron hooks of every Search Insights job, for scheduling, Delete data and deactivation. */
function aw_si_cron_hooks(): array {
	return (array) apply_filters( 'aw_si_cron_hooks', array( 'aw_si_daily', 'aw_si_rollup', AW_SI_GA4_CRON, AW_SI_PSI_CRON, AW_SI_PSI_MORE, AW_SI_CLARITY_CRON ) );
}

add_action(
	'init',
	function () {
		if ( get_option( 'aw_si_off' ) ) {
			return;
		}
		$tz = wp_timezone();
		if ( ! wp_next_scheduled( AW_SI_GA4_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'tomorrow 07:20', $tz ) )->getTimestamp(), 'daily', AW_SI_GA4_CRON );
		}
		if ( ! wp_next_scheduled( AW_SI_PSI_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'next sunday 06:00', $tz ) )->getTimestamp(), 'weekly', AW_SI_PSI_CRON );
		}
		if ( aw_si_clarity_on() && ! wp_next_scheduled( AW_SI_CLARITY_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'tomorrow 06:30', $tz ) )->getTimestamp(), 'daily', AW_SI_CLARITY_CRON );
		}
	}
);

/**
 * Old addresses and where they lead now: active Rank Math redirections with an exact source, and posts' old slugs.
 * Cached for 12 hours. Keys have no trailing slash, so /old and /old/ both match.
 *
 * @return array<string, string> old path => new path.
 */
function aw_si_redirect_map(): array {
	$map = get_transient( 'aw_si_redirects' );
	if ( is_array( $map ) ) {
		return $map;
	}
	global $wpdb;
	$map   = array();
	$table = $wpdb->prefix . 'rank_math_redirections';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $wpdb->get_results( "SELECT sources, url_to FROM {$table} WHERE status = 'active' AND header_code IN (301, 302, 307, 308)" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
			$to = aw_si_path( (string) $r->url_to );
			foreach ( (array) maybe_unserialize( $r->sources ) as $s ) {
				if ( 'exact' === ( $s['comparison'] ?? '' ) && '' !== (string) ( $s['pattern'] ?? '' ) && '' !== $to ) {
					$map[ '/' . trim( (string) $s['pattern'], '/' ) ] = $to;
				}
			}
		}
	}
	// Old slugs WordPress itself redirects (wp_old_slug_redirect): the same path with the old slug.
	$old = $wpdb->get_results( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_status = 'publish' WHERE m.meta_key = '_wp_old_slug' LIMIT 5000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $old as $r ) {
		$now  = aw_si_object_path( 'post', (int) $r->post_id );
		$slug = basename( untrailingslashit( $now ) );
		if ( '' !== $now && '' !== $slug && (string) $r->meta_value !== $slug ) {
			$map[ substr( untrailingslashit( $now ), 0, -strlen( $slug ) ) . $r->meta_value ] = $now;
		}
	}
	set_transient( 'aw_si_redirects', $map, 12 * HOUR_IN_SECONDS );
	return $map;
}

/**
 * Where an address leads now: its redirect target (following up to 5 hops), or the address itself.
 *
 * @param string $path Site path.
 */
function aw_si_final_path( string $path ): string {
	static $map = null;
	if ( null === $map ) {
		$map = aw_si_redirect_map();
	}
	$seen = array();
	$key  = untrailingslashit( $path );
	$hops = 0;
	while ( '' !== $key && isset( $map[ $key ] ) && ! isset( $seen[ $key ] ) && $hops < 5 ) {
		++$hops;
		$seen[ $key ] = true;
		$path         = $map[ $key ];
		$key          = untrailingslashit( $path );
	}
	return $path;
}

// The old Search demand screen (removed 25 Oct 2026): the one-time clean-up of its daily job and stored figures ran and
// was removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history).
