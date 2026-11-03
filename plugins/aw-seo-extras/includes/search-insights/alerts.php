<?php
/**
 * Google Alerts → Links (3 Nov 2026): the owner creates up to two alerts (Google Alerts → Deliver to: RSS feed) and pastes
 * each feed address on Animal Writes → Keys. Daily at 08:10 IST each feed is read and every item is a Links row of type
 * "Mention": the page that mentions us (Google's redirect removed), its title as the anchor, source "Google Alerts",
 * linked page = our home page (a mention, not necessarily a link). The owner's type and hidden flag are never changed.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_ALERTS_CRON = 'aw_si_alerts';
const AW_SI_ALERTS_KEYS = array( 'AW_GOOGLE_ALERTS_FEED_1', 'AW_GOOGLE_ALERTS_FEED_2' );

add_filter(
	'aw_keys',
	function ( $keys ) {
		$by = __( 'Search Insights', 'aw-seo-extras' );
		foreach ( AW_SI_ALERTS_KEYS as $i => $name ) {
			/* translators: %d: 1 or 2. */
			$keys[ $name ] = array( sprintf( __( 'Google Alerts feed %d (RSS address)', 'aw-seo-extras' ), $i + 1 ), __( 'google.com/alerts → create an alert → Show options → Deliver to: RSS feed → copy the feed address (https://www.google.com/alerts/feeds/…).', 'aw-seo-extras' ), $by );
		}
		return $keys;
	}
);

/** The feed addresses that are set and look like Google Alerts feeds. */
function aw_si_alerts_feeds(): array {
	$out = array();
	foreach ( AW_SI_ALERTS_KEYS as $name ) {
		$url = trim( aw_si_key( $name ) );
		if ( preg_match( '#^https://www\.google\.[a-z.]+/alerts/feeds/#', $url ) ) {
			$out[] = $url;
		}
	}
	return $out;
}

add_filter(
	'aw_si_sources',
	function ( $s ) {
		if ( aw_si_alerts_feeds() ) {
			$s['alerts'] = array(
				'label' => __( 'Google Alerts (mentions)', 'aw-seo-extras' ),
				'every' => DAY_IN_SECONDS,
			);
		}
		return $s;
	}
);
add_filter( 'aw_si_cron_hooks', fn( $h ) => array_merge( (array) $h, array( AW_SI_ALERTS_CRON ) ) );
add_filter(
	'aw_si_link_types',
	fn( $t ) => (array) $t + array( 'mention' => __( 'Mention', 'aw-seo-extras' ) )
);

add_action(
	'init',
	function () {
		if ( ! get_option( 'aw_si_off' ) && ! wp_next_scheduled( AW_SI_ALERTS_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'tomorrow 08:10', wp_timezone() ) )->getTimestamp(), 'daily', AW_SI_ALERTS_CRON );
		}
	}
);

/**
 * The page behind a Google Alerts link (https://www.google.com/url?…&url=REAL&…), or the link itself.
 *
 * @param string $link Link from the feed.
 */
function aw_si_alerts_target( string $link ): string {
	$q = array();
	wp_parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $q );
	return ! empty( $q['url'] ) && false !== strpos( (string) wp_parse_url( $link, PHP_URL_HOST ), 'google.' ) ? (string) $q['url'] : $link;
}

/**
 * Read the feeds and add their items to Links.
 *
 * @param bool $dry Read, do not write.
 */
function aw_si_alerts_run( bool $dry = false ): array {
	$feeds = aw_si_alerts_feeds();
	if ( ! $feeds || get_option( 'aw_si_off' ) || ! aw_si_ready() ) {
		return array( 'off' => true );
	}
	if ( ! $dry ) {
		aw_si_run_start( 'alerts' );
	}
	require_once ABSPATH . WPINC . '/feed.php';
	$rep  = array(
		'rows'     => 0,
		'added'    => 0,
		'warnings' => array(),
	);
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	foreach ( $feeds as $i => $url ) {
		$feed = fetch_feed( $url );
		if ( is_wp_error( $feed ) ) {
			/* translators: 1: feed number, 2: the problem. */
			$rep['warnings'][] = sprintf( __( 'Feed %1$d could not be read: %2$s', 'aw-seo-extras' ), $i + 1, $feed->get_error_message() );
			continue;
		}
		foreach ( (array) $feed->get_items( 0, 50 ) as $item ) {
			$from = aw_si_alerts_target( (string) $item->get_permalink() );
			if ( '' === $from || wp_parse_url( $from, PHP_URL_HOST ) === $home ) {
				continue; // Our own pages are not mentions.
			}
			++$rep['rows'];
			if ( $dry ) {
				continue;
			}
			$seen = $item->get_date( 'Y-m-d' );
			$n    = aw_si_link_put(
				array(
					'from_url'   => $from,
					'linked_url' => home_url( '/' ),
					'anchor'     => html_entity_decode( wp_strip_all_tags( (string) $item->get_title() ), ENT_QUOTES, 'UTF-8' ),
					'first_seen' => $seen ? $seen : '',
					'last_seen'  => $seen ? $seen : '',
					'source'     => 'Google Alerts',
					'type'       => 'mention',
				)
			);
			$rep['added'] += 1 === $n ? 1 : 0;
		}
	}
	if ( $dry ) {
		return $rep;
	}
	if ( ! $rep['rows'] && count( $rep['warnings'] ) === count( $feeds ) ) {
		aw_si_run_fail( 'alerts', (string) $rep['warnings'][0] );
	} else {
		aw_si_run_ok( 'alerts', $rep );
	}
	return $rep;
}
add_action( AW_SI_ALERTS_CRON, fn() => aw_si_alerts_run() );
