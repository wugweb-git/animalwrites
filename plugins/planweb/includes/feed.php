<?php
/**
 * The read-only calendar feed (14 Nov 2026): GET /wp-json/planweb/v1/calendar.ics?key=… for Google Calendar, Apple
 * Calendar and Outlook to subscribe to. One way: nothing is read back, nothing is posted (D6). Access is by the secret
 * key made in PlanWeb → Settings → Calendar feed, never by sign-in cookies; no key, no feed.
 *
 * Optional filters: type (slugs joined by commas, or "marketing" = social, email, campaign, content) and status
 * (slugs, or "open" = idea, draft, approved, due). The feed holds entries from 90 days ago on; each event has the date,
 * time, title, type, channels, status and link, never the note (feed-model.php).
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Option holding the feed key (not autoloaded). */
const PLANWEB_FEED_KEY = 'planweb_feed_key';

/** Days back the feed reaches. */
const PLANWEB_FEED_PAST_DAYS = 90;

/** The REST route (under PLANWEB_REST). */
const PLANWEB_FEED_ROUTE = '/calendar.ics';

/** The stored feed key, or '' when the feed is off. */
function planweb_feed_key(): string {
	return (string) get_option( PLANWEB_FEED_KEY, '' );
}

/** Make a new feed key; the old address stops working. */
function planweb_feed_new_key(): string {
	$key = wp_generate_password( 40, false, false );
	update_option( PLANWEB_FEED_KEY, $key, false );
	return $key;
}

/**
 * The feed's address with the key and any filters, or '' when the feed is off.
 *
 * @param array $filters type, status.
 */
function planweb_feed_url( array $filters = array() ): string {
	$key = planweb_feed_key();
	if ( '' === $key ) {
		return '';
	}
	return add_query_arg( array_map( 'rawurlencode', array( 'key' => $key ) + array_filter( $filters ) ), rest_url( PLANWEB_REST . PLANWEB_FEED_ROUTE ) );
}

/** The Settings group (administrators only: it holds the key). */
function planweb_feed_payload(): array {
	return array(
		'on'   => '' !== planweb_feed_key(),
		'urls' => '' === planweb_feed_key() ? array() : array(
			array(
				'label' => __( 'Everything', 'planweb' ),
				'url'   => planweb_feed_url(),
			),
			array(
				'label' => __( 'Marketing only (social, email, campaign, content)', 'planweb' ),
				'url'   => planweb_feed_url( array( 'type' => 'marketing' ) ),
			),
			array(
				'label' => __( 'Marketing, still open (not sent or done)', 'planweb' ),
				'url'   => planweb_feed_url(
					array(
						'type'   => 'marketing',
						'status' => 'open',
					)
				),
			),
		),
	);
}

/**
 * Whether the request carries the right key. A wrong or missing key looks like no route at all.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function planweb_feed_can( WP_REST_Request $request ) {
	$key  = planweb_feed_key();
	$sent = (string) ( $request->get_param( 'key' ) ?? '' );
	if ( '' === $key || '' === $sent || ! hash_equals( $key, $sent ) ) {
		return new WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method.', 'planweb' ), array( 'status' => 404 ) );
	}
	return true;
}

add_action(
	'rest_api_init',
	function () {
		if ( ! planweb_on() ) {
			return;
		}
		register_rest_route(
			PLANWEB_REST,
			PLANWEB_FEED_ROUTE,
			array(
				'methods'             => 'GET',
				'permission_callback' => 'planweb_feed_can',
				'callback'            => 'planweb_feed_serve',
				'args'                => array(
					'key'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'type'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'status' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}
);

/**
 * GET calendar.ics: the feed as text/calendar.
 *
 * @param WP_REST_Request $request Request.
 */
function planweb_feed_serve( WP_REST_Request $request ) {
	$types    = planweb_feed_list( $request['type'], PLANWEB_TYPES, PLANWEB_FEED_TYPE_GROUPS );
	$statuses = planweb_feed_list( $request['status'], PLANWEB_STATUSES, PLANWEB_FEED_STATUS_GROUPS );
	if ( null === $types || null === $statuses ) {
		return new WP_Error( 'planweb_feed_filter', __( 'Unknown type or status in the feed address.', 'planweb' ), array( 'status' => 400 ) );
	}
	$from    = wp_date( 'Y-m-d', time() - PLANWEB_FEED_PAST_DAYS * DAY_IN_SECONDS );
	$entries = planweb_feed_pick( planweb_entries( $from, '', array( 'posts_per_page' => -1 ) ), $types, $statuses );
	$ics     = planweb_ics(
		$entries,
		array(
			/* translators: %s: site name. */
			'name'     => sprintf( __( '%s: PlanWeb', 'planweb' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'version'  => PLANWEB_VERSION,
			'host'     => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'stamp'    => gmdate( 'Ymd\THis\Z' ),
			'types'    => PLANWEB_TYPES,
			'channels' => PLANWEB_CHANNELS,
			'statuses' => PLANWEB_STATUSES,
		)
	);
	do_action( 'litespeed_control_set_nocache', 'planweb calendar feed' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own hook: the key is in the address, so never cache it.
	$response = new WP_REST_Response( $ics, 200 );
	$response->header( 'Content-Type', 'text/calendar; charset=utf-8' );
	$response->header( 'Content-Disposition', 'inline; filename="planweb.ics"' );
	$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
	$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
	return $response;
}

// The REST server would send the feed as a JSON string; send the text itself.
add_filter(
	'rest_pre_serve_request',
	function ( $served, $result, $request ) {
		if ( $served || ! $request instanceof WP_REST_Request || '/' . PLANWEB_REST . PLANWEB_FEED_ROUTE !== $request->get_route() ) {
			return $served;
		}
		$data = $result instanceof WP_HTTP_Response ? $result->get_data() : null;
		if ( ! is_string( $data ) || 200 !== $result->get_status() ) {
			return $served; // Errors stay JSON.
		}
		if ( 'HEAD' !== $request->get_method() ) {
			echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar text, built and escaped by planweb_ics(); not HTML.
		}
		return true;
	},
	10,
	3
);
