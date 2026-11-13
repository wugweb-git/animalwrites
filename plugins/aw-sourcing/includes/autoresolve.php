<?php
/**
 * Review-queue rules in the plugin (owner, 14 Nov 2026: "you don't have to put me on review"). The rules are in
 * autoresolve-model.php (tested without WordPress); this file applies them.
 *
 * - When a value is queued for review (aw_enrich_queued) it is decided at once: approved, rejected as no change or
 *   another place, or left for the owner. The row shows its reason ("Auto: ...") in the change log.
 * - POST sourceweb/v1/enrichment/autoresolve {dry, limit} decides the rows already waiting (dry run by default).
 * - A PIN code in a listing's address fills an empty PIN field (so the address schema has postalCode), after every
 *   write to the address and in the sweep.
 * - Never on a claimed or verified listing. Off with the option aw_enrich_autoresolve = 0 or the filter
 *   aw_enrich_autoresolve.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/autoresolve-model.php';

/** Whether queued values are decided by the rules. */
function aw_ar_on() {
	return (bool) apply_filters( 'aw_enrich_autoresolve', '0' !== (string) get_option( 'aw_enrich_autoresolve', '1' ) );
}

/**
 * What the model needs to know about where a value came from.
 *
 * @param int   $lid     Listing.
 * @param array $sources Row sources.
 * @return array{own_site: bool, site_stem: string, site_host: string}
 */
function aw_ar_context( $lid, $sources ) {
	$own = false;
	foreach ( (array) $sources as $s ) {
		if ( is_array( $s ) && ( 'website' === ( $s['key'] ?? '' ) || false !== stripos( (string) ( $s['name'] ?? '' ), 'own website' ) ) ) {
			$own = true;
		}
	}
	$host = wp_parse_url( (string) get_post_meta( $lid, 'aw_website', true ), PHP_URL_HOST );
	$stem = $host ? (string) preg_replace( '/\..*$/', '', (string) preg_replace( '/^www\./', '', strtolower( $host ) ) ) : '';
	return array( 'own_site' => $own, 'site_stem' => $stem, 'site_host' => $host ? (string) preg_replace( '/^www\\./', '', strtolower( $host ) ) : '' );
}

/**
 * Decide one waiting row, and apply the decision unless $apply is false.
 *
 * @param int  $id    Row ID.
 * @param bool $apply Write the decision (false: only say what it would be).
 * @return array{act: string, why: string}|null null when the row is not waiting.
 */
function aw_ar_row( $id, $apply = true ) {
	$r = aw_enrich_row( (int) $id );
	if ( ! $r || 'pending' !== $r['status'] || ! is_string( $r['value'] ) ) {
		return null;
	}
	$lid = (int) $r['listing_id'];
	if ( 0 === strpos( (string) $r['note'], 'From an imported list' ) ) {
		return array( 'act' => 'leave', 'why' => 'an imported list is always checked by a person' );
	}
	if ( aw_enrich_is_protected( $lid ) ) {
		return array( 'act' => 'leave', 'why' => 'a claimed or verified listing is never changed automatically' );
	}
	$d = aw_ar_decide( $r['field'], (string) aw_enrich_current( $lid, $r['field'] ), $r['value'], (string) $r['confidence'], aw_ar_context( $lid, $r['sources'] ) );
	if ( ! $apply || 'leave' === $d['act'] ) {
		return $d;
	}
	if ( 'reject' === $d['act'] ) {
		aw_enrich_set_status( (int) $id, 'rejected', 'Auto: ' . $d['why'] );
	} elseif ( aw_enrich_approve( (int) $id ) ) {
		aw_enrich_set_status( (int) $id, 'approved', 'Auto: ' . $d['why'] );
	} else {
		return array( 'act' => 'leave', 'why' => 'could not be written' );
	}
	return $d;
}

// A value queued for review is decided at once.
add_action(
	'aw_enrich_queued',
	function ( $id ) {
		if ( $id && aw_ar_on() ) {
			aw_ar_row( (int) $id );
		}
	}
);

/**
 * The PIN code in the address, when the PIN field is empty.
 *
 * @param int $lid Listing.
 * @return bool Whether the PIN field was filled.
 */
function aw_ar_fill_pin( $lid ) {
	static $busy = false;
	$lid = (int) $lid;
	if ( $busy || 'aw_listing' !== get_post_type( $lid ) || aw_enrich_is_protected( $lid ) || '' !== trim( (string) get_post_meta( $lid, 'aw_pincode', true ) ) ) {
		return false;
	}
	$pin = aw_ar_pin( (string) get_post_meta( $lid, 'aw_address', true ) );
	if ( '' === $pin ) {
		return false;
	}
	$src  = array( array( 'key' => 'listing', 'name' => 'The listing\'s own address', 'url' => '', 'fetched_at' => wp_date( 'Y-m-d' ) ) );
	$busy = true; // writing the PIN calls aw_enrich_changed again
	$ok   = aw_enrich_write( $lid, 'pincode', $pin, 'apply', $src );
	$busy = false;
	if ( $ok ) {
		aw_enrich_add_row( $lid, 'pincode', $pin, '', $src, 'high', 'auto-applied', 'autoresolve', 'From the listing\'s own address' );
	}
	return (bool) $ok;
}

// After every write to a listing from the enrichment: its address may now hold a PIN the field lacks.
add_action(
	'aw_enrich_changed',
	function ( $lid ) {
		if ( aw_ar_on() ) {
			aw_ar_fill_pin( (int) $lid );
		}
	}
);

/**
 * Decide the rows already waiting.
 *
 * @param WP_REST_Request $req dry (default true), limit (default 300, at most 1000).
 * @return WP_REST_Response
 */
function aw_ar_rest_sweep( WP_REST_Request $req ) {
	global $wpdb;
	$dry   = ! ( false === $req->get_param( 'dry' ) || 'false' === $req->get_param( 'dry' ) || '0' === (string) $req->get_param( 'dry' ) );
	$limit = max( 1, min( 1000, (int) ( $req->get_param( 'limit' ) ? $req->get_param( 'limit' ) : 300 ) ) );
	$t     = aw_enrich_table();
	$ids   = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE status = 'pending' AND field NOT IN ( 'closed', 'merge_into', 'place' ) ORDER BY id ASC LIMIT %d", $limit ) ) ); // phpcs:ignore WordPress.DB
	$count = array();
	$seen  = array();
	foreach ( $ids as $id ) {
		$d = aw_ar_row( $id, ! $dry );
		if ( ! $d ) {
			continue;
		}
		$k           = $d['act'] . ': ' . $d['why'];
		$count[ $k ] = ( $count[ $k ] ?? 0 ) + 1;

		$seen[ $d['act'] ][] = $id;
	}
	$pins = 0;
	if ( ! $dry ) {
		foreach ( (array) $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = 'aw_address' LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = 'aw_pincode' WHERE p.post_type = 'aw_listing' AND p.post_status IN ( 'publish', 'pending' ) AND ( c.meta_value IS NULL OR c.meta_value = '' ) AND a.meta_value REGEXP '[1-9][0-9]{2} ?[0-9]{3}' LIMIT 500" ) as $lid ) { // phpcs:ignore WordPress.DB
			$pins += aw_ar_fill_pin( (int) $lid ) ? 1 : 0;
		}
	}
	ksort( $count );
	return rest_ensure_response(
		array(
			'dry'       => $dry,
			'rows'      => count( $ids ),
			'by_reason' => $count,
			'approve'   => count( $seen['approve'] ?? array() ),
			'reject'    => count( $seen['reject'] ?? array() ),
			'leave'     => count( $seen['leave'] ?? array() ),
			'pins'      => $pins,
		)
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'sourceweb/v1',
			'/enrichment/autoresolve',
			array(
				'methods'             => 'POST',
				'permission_callback' => fn() => current_user_can( defined( 'AW_ENRICH_CAP' ) ? AW_ENRICH_CAP : 'edit_others_posts' ),
				'callback'            => 'aw_ar_rest_sweep',
				'args'                => array(
					'dry'   => array( 'required' => false ),
					'limit' => array( 'type' => 'integer', 'required' => false ),
				),
			)
		);
	}
);
