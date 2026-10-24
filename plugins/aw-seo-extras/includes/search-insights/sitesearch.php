<?php
/**
 * Searches on our own site (plan §5.5 and §9, privacy): WordPress ?s= searches, Directory name or PIN searches (?q=),
 * and Directory "near me" / PIN lookups (the aw/v1/dir-near route, counted by the district it found). Stored only as
 * daily counts per term in obs (src site-search; metric wp, dir-q, dir-pin or dir-near; txt = the term). No IP
 * address, user agent, user ID, cookie or request line is stored, and terms that look like personal data are dropped.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A search term cleaned for counting, or '' when it must not be stored: emails, phone numbers, long digit strings
 * (anything but a 6-digit PIN code), and terms too short or too long to be a search.
 *
 * @param string $term Raw term.
 */
function aw_si_term_ok( string $term ): string {
	$term = trim( preg_replace( '/\s+/', ' ', mb_strtolower( wp_strip_all_tags( $term ) ) ) );
	if ( mb_strlen( $term ) < 2 || mb_strlen( $term ) > 80 ) {
		return '';
	}
	if ( preg_match( '/[^\s@]+@[^\s@]+\.[a-z]{2,}/i', $term ) ) {
		return ''; // An email address.
	}
	$digits = preg_replace( '/\D/', '', $term );
	if ( preg_match( '/^[1-9][0-9]{5}$/', $term ) ) {
		return $term; // A PIN code: an area, kept as a count only.
	}
	if ( strlen( $digits ) >= 7 || preg_match( '/\d{7,}/', $term ) ) {
		return ''; // A phone number or another long number.
	}
	return $term;
}

/** Whether this request should not be counted: bots, logged-in editors, previews and feeds. */
function aw_si_search_skip(): bool {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only matched, never stored.
	return '' === $ua || (bool) preg_match( '/bot|crawl|spider|slurp|fetch|preview|monitor|curl|wget|python|headless/i', $ua ) || current_user_can( 'edit_posts' ) || is_feed() || is_preview();
}

/**
 * Add one to today's count of a term.
 *
 * @param string $metric wp, dir-q, dir-pin or dir-near.
 * @param string $term   Search term (cleaned here).
 */
function aw_si_count_search( string $metric, string $term ): void {
	if ( get_option( 'aw_si_off' ) || ! aw_si_ready() ) {
		return;
	}
	$term = 'dir-near' === $metric ? trim( $term ) : aw_si_term_ok( $term );
	if ( '' === $term ) {
		return;
	}
	global $wpdb;
	$o = aw_si_table( 'obs' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$o} (kw_id, page, page_key, src, metric, period, d, value, txt, fetched) VALUES (0, '', %s, 'site-search', %s, 'day', %s, 1, %s, %s) ON DUPLICATE KEY UPDATE value = value + 1, fetched = VALUES(fetched)", md5( 'term:' . $term ), $metric, wp_date( 'Y-m-d' ), mb_substr( $term, 0, 255 ), aw_si_now() ) );
}

// WordPress search and the Directory search box: first results page only.
add_action(
	'template_redirect',
	function () {
		if ( is_admin() || is_paged() || aw_si_search_skip() ) {
			return;
		}
		if ( is_search() ) {
			aw_si_count_search( 'wp', (string) get_search_query( false ) );
			return;
		}
		$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- a public search, counted only.
		if ( '' !== $q && function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() ) {
			$pin = preg_replace( '/\s/', '', $q );
			aw_si_count_search( preg_match( '/^[1-9][0-9]{5}$/', $pin ) ? 'dir-pin' : 'dir-q', preg_match( '/^[1-9][0-9]{5}$/', $pin ) ? $pin : $q );
		}
	}
);

// Directory "near me" and PIN lookups: counted by the district found (never the coordinates), and the PIN as a count.
add_filter(
	'rest_post_dispatch',
	function ( $result, $server, $request ) {
		if ( ! $request instanceof WP_REST_Request || '/aw/v1/dir-near' !== $request->get_route() || ! $result instanceof WP_REST_Response || 200 !== $result->get_status() || aw_si_search_skip() ) {
			return $result;
		}
		$data = (array) $result->get_data();
		$pin  = preg_replace( '/\D/', '', (string) $request->get_param( 'pin' ) );
		if ( '' !== $pin ) {
			aw_si_count_search( 'dir-pin', $pin );
		} elseif ( ! empty( $data['district']['name'] ) ) {
			aw_si_count_search( 'dir-near', (string) $data['district']['name'] );
		}
		return $result;
	},
	10,
	3
);

/**
 * Site search terms over the last $days days, all kinds together, most searched first.
 *
 * @param int $days  Window.
 * @param int $limit Terms.
 * @return array<int, object{txt: string, metric: string, n: float}>
 */
function aw_si_site_searches( int $days = 28, int $limit = 15 ): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$o     = aw_si_table( 'obs' );
	$since = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-' . max( 1, $days ) . ' days' )->format( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT txt, metric, SUM(value) n FROM {$o} WHERE ((src = 'site-search') OR (src = 'ga4' AND metric = 'search')) AND period = 'day' AND d >= %s GROUP BY txt, metric ORDER BY n DESC LIMIT %d", $since, $limit ) );
}
