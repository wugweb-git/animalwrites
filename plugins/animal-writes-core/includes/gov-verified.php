<?php
/**
 * Verified (owner, 31 Oct 2026): a listing whose registration details come from government documents. A second badge
 * beside Claimed (aw_verified, an approved claim); a listing can show both.
 *
 * - Government documents: AWBI recognition (aw_awbi_code, still on the AWBI list: not aw_awbi_dropped), and the
 *   registration details (registration number, 12A, 80G, FCRA, NGO Darpan ID) of a listing imported from a government
 *   list (data sources darpan, datagov, nalsa, gujforest, gov).
 * - Saved on the listing: aw_gov_verified_on (the government document's own date: the AWBI list's date, or the import's
 *   source date; the newest when there are several) and aw_gov_verified_by (the sources). Kept in step when those
 *   details change once it is on. Off until `wp aw gov-verify` (after a `--dry-run`) turns it on and checks every
 *   listing; `wp aw gov-verify --off` clears it again. Imports that write with SQL bypass the re-check: run
 *   `wp aw gov-verify` after them (e.g. a new AWBI list).
 * - Shown: the green tick beside the name on cards, "Verified on {date}" on the profile, "Verified only" in the
 *   Directory filter (?gov=1) and on the map.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Import sources that are government lists (data-sources.php). AWBI is read from the listing's AWBI code. */
const AW_GOV_SOURCES = array( 'darpan', 'datagov', 'nalsa', 'gujforest', 'gov' );

/** The listing fields a government import's registration details live in => their enrichment field. */
const AW_GOV_REG_KEYS = array( 'aw_registration' => 'registration', 'aw_reg_12a' => 'reg_12a', 'aw_reg_80g' => 'reg_80g', 'aw_fcra' => 'fcra', 'aw_darpan_id' => 'darpan_id' );

/**
 * Does a registration field's value come from the government import? Yes unless enrichment (or an approved claim) last
 * wrote it from a source that is not a government one.
 */
function aw_gov_value_official( $id, $enrich_field ) {
	$p = function_exists( 'aw_enrich_provenance' ) ? aw_enrich_provenance( $id, $enrich_field ) : null;
	if ( ! $p ) {
		return true; // never changed by enrichment: the import's own value
	}
	foreach ( (array) $p['sources'] as $s ) {
		if ( in_array( $s['key'] ?? '', array( 'darpan', 'awbi' ), true ) || preg_match( '#(\.gov\.in|\.nic\.in)(/|$)#i', (string) wp_parse_url( (string) ( $s['url'] ?? '' ), PHP_URL_HOST ) . '/' ) ) {
			return true;
		}
	}
	return false;
}

/** The listing fields whose change can change Verified. */
const AW_GOV_WATCH = array( 'aw_awbi_code', 'aw_awbi_dropped', 'aw_awbi_list_on', 'aw_data_sources', 'aw_source_date', 'aw_registration', 'aw_reg_12a', 'aw_reg_80g', 'aw_fcra', 'aw_darpan_id' );

/**
 * The government documents behind a listing: source slug => the document's date (Y-m-d, or '' when unknown).
 * Empty: not Verified.
 *
 * @param int $id Listing ID.
 * @return array
 */
function aw_gov_verification( $id ) {
	$m   = fn( $k ) => trim( (string) get_post_meta( $id, $k, true ) );
	$ymd = fn( $d ) => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : '';
	$out = array();
	if ( '' !== $m( 'aw_awbi_code' ) && '' === $m( 'aw_awbi_dropped' ) ) {
		$list        = $ymd( $m( 'aw_awbi_list_on' ) );
		$out['awbi'] = '' !== $list ? $list : (string) ( function_exists( 'aw_data_sources' ) ? aw_data_sources()['awbi'][2] : '' ); // empty: the current AWBI list
	}
	$srcs = array_filter( (array) get_post_meta( $id, 'aw_data_sources', true ) );
	$name = $m( 'aw_source_name' );
	if ( ! $srcs && '' !== $name && false === stripos( $name, 'google' ) && false === stripos( $name, 'AWBI' ) ) {
		$srcs = array( 'gov' ); // imported before aw_data_sources was kept: the named list is a government one (as aw_listing_sources() labels it)
	}
	$gov = array_values( array_intersect( $srcs, AW_GOV_SOURCES ) );
	if ( $gov && array_filter( AW_GOV_REG_KEYS, fn( $f, $k ) => '' !== $m( $k ) && aw_gov_value_official( $id, $f ), ARRAY_FILTER_USE_BOTH ) ) {
		$out[ $gov[0] ] = $ymd( $m( 'aw_source_date' ) );
	}
	return $out;
}

/**
 * Save (or clear) a listing's Verified state from its government documents.
 *
 * @param int  $id  Listing ID.
 * @param bool $dry True: report only.
 * @return array the sources (empty: not Verified)
 */
function aw_gov_verified_sync( $id, $dry = false ) {
	$docs = 'aw_listing' === get_post_type( $id ) ? aw_gov_verification( $id ) : array();
	if ( $dry ) {
		return $docs;
	}
	$on  = $docs ? max( array_map( fn( $d ) => '' !== $d ? $d : '0000-00-00', $docs ) ) : '';
	$on  = '0000-00-00' === $on ? wp_date( 'Y-m-d' ) : $on; // no date on the document: the day it was given
	$was = (string) get_post_meta( $id, 'aw_gov_verified_on', true );
	if ( $docs ) {
		update_post_meta( $id, 'aw_gov_verified_on', $on );
		update_post_meta( $id, 'aw_gov_verified_by', implode( ',', array_keys( $docs ) ) );
	} else {
		delete_post_meta( $id, 'aw_gov_verified_on' );
		delete_post_meta( $id, 'aw_gov_verified_by' );
	}
	if ( $was !== $on ) {
		delete_transient( 'aw_gov_verified_count' );
		delete_transient( 'aw_dir_points' );
	}
	return $docs;
}

// Kept in step: a change to an AWBI, import or registration field of a listing re-checks it once, at the end of the request.
$aw_gov_touch = function ( $meta_id, $post_id, $key ) {
	if ( in_array( $key, AW_GOV_WATCH, true ) && 'aw_listing' === get_post_type( $post_id ) ) {
		$GLOBALS['aw_gov_pending'][ (int) $post_id ] = true;
	}
};
add_action( 'added_post_meta', $aw_gov_touch, 10, 3 );
add_action( 'updated_post_meta', $aw_gov_touch, 10, 3 );
add_action( 'deleted_post_meta', fn( $ids, $post_id, $key ) => $aw_gov_touch( 0, $post_id, $key ), 10, 3 );
add_action(
	'shutdown',
	function () {
		if ( empty( $GLOBALS['aw_gov_pending'] ) || ! get_option( 'aw_gov_verified_live' ) ) {
			return; // nothing changed, or not switched on yet (wp aw gov-verify)
		}
		foreach ( array_keys( (array) ( $GLOBALS['aw_gov_pending'] ?? array() ) ) as $id ) {
			aw_gov_verified_sync( $id );
		}
	}
);

// A listing published, unpublished or trashed changes the count.
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( $new !== $old && 'aw_listing' === $post->post_type ) {
			delete_transient( 'aw_gov_verified_count' );
		}
	},
	10,
	3
);

/** Number of Verified listings (cached). "Verified only" appears once there is at least one. */
function aw_gov_verified_count() {
	$n = get_transient( 'aw_gov_verified_count' );
	if ( false === $n ) {
		global $wpdb;
		$n = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_gov_verified_on' WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$n -= count( array_filter( function_exists( 'kits_preview_only_ids' ) ? (array) kits_preview_only_ids() : array(), fn( $p ) => 'aw_listing' === get_post_type( $p ) && 'publish' === get_post_status( $p ) && '' !== (string) get_post_meta( $p, 'aw_gov_verified_on', true ) ) ); // as for Claimed: preview-only listings do not count
		set_transient( 'aw_gov_verified_count', $n, HOUR_IN_SECONDS );
	}
	return (int) $n;
}

/** The Verified mark beside a listing's name: the green tick. */
function aw_gov_verified_mark() {
	return ' <span class="aw-gov-mark" title="Verified: registration details from government documents">' . aw_i( 'check' ) . '<span class="screen-reader-text">Verified: registration details from government documents</span></span>';
}

/** "Verified on {date}" for the profile's meta row, or ''. */
function aw_gov_verified_line( $id ) {
	$on = (string) get_post_meta( $id, 'aw_gov_verified_on', true );
	if ( '' === $on ) {
		return '';
	}
	/* translators: %s: date. */
	return '<span class="kit-badge kit-badge--verified aw-item-gov">' . aw_i( 'check' ) . esc_html( sprintf( __( 'Verified on %s', 'animal-writes-core' ), wp_date( 'j M Y', strtotime( $on . ' 12:00:00' ) ) ) ) . '</span>';
}

// The tick beside the name on cards (after the Claimed mark when both apply); not on the profile's own title.
add_filter(
	'render_block_core/post-title',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? get_the_ID() );
		if ( ! $id || 'aw_listing' !== get_post_type( $id ) || '' === (string) get_post_meta( $id, 'aw_gov_verified_on', true ) ) {
			return $html;
		}
		if ( function_exists( 'aw_listing_here' ) && aw_listing_here( $id ) && 1 === (int) ( $block['attrs']['level'] ?? 2 ) ) {
			return $html; // the profile: its meta row says "Verified on"
		}
		$end = strrpos( $html, '</a>' );
		$end = false !== $end ? $end : strrpos( $html, '</h' );
		return false === $end ? $html : substr_replace( $html, aw_gov_verified_mark(), $end, 0 );
	},
	26,
	3
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// wp aw gov-verify [--dry-run] [--off]: check every published listing (and switch Verified on); how many are
	// Verified, by source. --off removes it from every listing and switches it off.
	WP_CLI::add_command(
		'aw gov-verify',
		function ( $args, $assoc ) {
			if ( ! empty( $assoc['off'] ) ) {
				delete_option( 'aw_gov_verified_live' );
				delete_post_meta_by_key( 'aw_gov_verified_on' );
				delete_post_meta_by_key( 'aw_gov_verified_by' );
				delete_transient( 'aw_gov_verified_count' );
				delete_transient( 'aw_dir_points' );
				WP_CLI::success( 'Verified is off; removed from every listing.' );
				return;
			}
			$dry = ! empty( $assoc['dry-run'] );
			if ( ! $dry ) {
				update_option( 'aw_gov_verified_live', 1, false );
			}
			$ids = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
			update_meta_cache( 'post', $ids );
			$by  = array();
			$yes = 0;
			foreach ( $ids as $id ) {
				$docs = aw_gov_verified_sync( $id, $dry );
				if ( $docs ) {
					++$yes;
					$k        = implode( ' + ', array_keys( $docs ) );
					$by[ $k ] = ( $by[ $k ] ?? 0 ) + 1;
				}
			}
			delete_transient( 'aw_gov_verified_count' );
			delete_transient( 'aw_dir_points' );
			arsort( $by );
			foreach ( $by as $k => $n ) {
				WP_CLI::log( sprintf( '%-24s %d', $k, $n ) );
			}
			WP_CLI::success( sprintf( '%s%d of %d published listings Verified.', $dry ? 'Dry run: ' : '', $yes, count( $ids ) ) );
		}
	);
}
