<?php
/**
 * RankWeb Dashboard counts (docs/rankweb-plan.md §2): built by the hourly job rankweb_health_build, never while a
 * screen loads. The job keeps the newest counts in the transient rankweb_health and one row a day in the option
 * rankweb_health_log (not autoloaded, 120 days), so the Dashboard's time range can show the change.
 *
 * The schema count asks Rank Math's schema sync for every published item in a dry run, which is slow on a large site,
 * so it runs in slices of at most 20 seconds per job and keeps its place in the option rankweb_health_schema; the tile
 * shows the last full pass. Everything else is one query or a stored result.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const RANKWEB_HEALTH          = 'rankweb_health';
const RANKWEB_HEALTH_LOG      = 'rankweb_health_log';
const RANKWEB_HEALTH_SCHEMA   = 'rankweb_health_schema';
const RANKWEB_HEALTH_JOB      = 'rankweb_health_build';
const RANKWEB_HEALTH_LOG_DAYS = 120;

add_action( RANKWEB_HEALTH_JOB, 'rankweb_health_build' );

// Hourly, scheduled from an admin request (as the plugin's other jobs), so a page view never writes.
add_action(
	'admin_init',
	function () {
		if ( ! wp_next_scheduled( RANKWEB_HEALTH_JOB ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', RANKWEB_HEALTH_JOB );
		}
	}
);

/**
 * Ask for a count now (the Dashboard's first view, or Count now): one single event, never a count in this request.
 *
 * @return bool Whether a count is waiting to run.
 */
function rankweb_health_request(): bool {
	if ( wp_next_scheduled( RANKWEB_HEALTH_JOB, array( 'now' ) ) ) {
		return true;
	}
	return false !== wp_schedule_single_event( time(), RANKWEB_HEALTH_JOB, array( 'now' ) );
}

/**
 * The job: every count, then the day's row in the log.
 *
 * @param string $why 'now' when asked for from the Dashboard, '' on the hourly run.
 * @return array The counts.
 */
function rankweb_health_build( $why = '' ) {
	unset( $why );
	$types = array_values( array_filter( (array) AW_SEO_ITEM_TYPES, 'post_type_exists' ) );
	$c     = array(
		'at'      => time(),
		'items'   => rankweb_count_items( $types ),
		'titles'  => rankweb_count_missing_meta( $types, 'rank_math_title' ),
		'descs'   => rankweb_count_missing_meta( $types, 'rank_math_description' ),
		'filled'  => rankweb_count_filled_descriptions( $types ),
		'focus'   => rankweb_count_missing_meta( $types, 'rank_math_focus_keyword' ),
		'schema'  => rankweb_count_schema_gaps(),
		'faq'     => rankweb_count_faq(),
		'noindex' => rankweb_count_noindex(),
	);
	set_transient( RANKWEB_HEALTH, $c, 3 * HOUR_IN_SECONDS );
	rankweb_health_log( $c );
	return $c;
}

/**
 * Published items of the item types, by type.
 *
 * @param string[] $types Post types.
 * @return array type => count.
 */
function rankweb_count_items( array $types ): array {
	$out = array();
	foreach ( $types as $t ) {
		$out[ $t ] = (int) ( wp_count_posts( $t )->publish ?? 0 );
	}
	return $out;
}

/**
 * Published items (hub Pages left out: they take theirs from the hub maps) with no value in a Rank Math field.
 *
 * @param string[] $types    Post types.
 * @param string   $meta_key Rank Math field.
 * @return array [ 'total' => n, 'by' => [ type => n ] ].
 */
function rankweb_count_missing_meta( array $types, string $meta_key ): array {
	global $wpdb;
	$out = array(
		'total' => 0,
		'by'    => array(),
	);
	if ( ! $types ) {
		return $out;
	}
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- hourly count in the background
		$wpdb->prepare(
			"SELECT p.ID, p.post_type FROM {$wpdb->posts} p WHERE p.post_type IN ( " . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . " ) AND p.post_status = 'publish' AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = %s AND TRIM( m.meta_value ) <> '' )",
			array_merge( $types, array( $meta_key ) )
		)
	);
	foreach ( (array) $rows as $r ) {
		if ( 'page' === $r->post_type && function_exists( 'aw_seo_is_hub_page' ) && aw_seo_is_hub_page( (int) $r->ID ) ) {
			continue;
		}
		$out['by'][ $r->post_type ] = ( $out['by'][ $r->post_type ] ?? 0 ) + 1;
		++$out['total'];
	}
	arsort( $out['by'] );
	return $out;
}

/**
 * Published items whose description the daily job wrote (marked in _aw_seo_auto, titles.php).
 *
 * @param string[] $types Post types.
 */
function rankweb_count_filled_descriptions( array $types ): int {
	global $wpdb;
	if ( ! $types ) {
		return 0;
	}
	return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- hourly count in the background
		$wpdb->prepare(
			"SELECT COUNT( DISTINCT p.ID ) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_aw_seo_auto' WHERE p.post_type IN ( " . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . " ) AND p.post_status = 'publish' AND m.meta_value LIKE %s",
			array_merge( $types, array( '%' . $wpdb->esc_like( '"description"' ) . '%' ) )
		)
	);
}

/**
 * Items whose Rank Math schema entry does not name the type the page has: what `wp aw-seo rank-math-schema` would
 * write. Counted in slices (at most 20 seconds per job); the last full pass is the number shown.
 *
 * @return array [ 'on' => bool, 'gaps' => n|null, 'seen' => n, 'at' => time|0, 'part' => items checked in this pass ].
 */
function rankweb_count_schema_gaps(): array {
	if ( ! function_exists( 'aw_rm_schema_sync' ) || ! defined( 'AW_RM_SCHEMA_TYPES' ) ) {
		return array(
			'on'   => false,
			'gaps' => null,
			'seen' => 0,
			'at'   => 0,
			'part' => 0,
		);
	}
	global $wpdb;
	$state = wp_parse_args(
		(array) get_option( RANKWEB_HEALTH_SCHEMA, array() ),
		array(
			'cursor' => 0,
			'gaps'   => 0,
			'seen'   => 0,
			'done'   => array(),
		)
	);
	$types = array_values( array_filter( (array) AW_RM_SCHEMA_TYPES, 'post_type_exists' ) );
	$start = microtime( true );
	while ( $types && microtime( true ) - $start < 20 ) {
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- background count, by ID
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( " . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . " ) AND post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT 200",
				array_merge( $types, array( (int) $state['cursor'] ) )
			)
		);
		if ( ! $ids ) {
			$state['done']   = array(
				'gaps' => (int) $state['gaps'],
				'seen' => (int) $state['seen'],
				'at'   => time(),
			);
			$state['cursor'] = 0;
			$state['gaps']   = 0;
			$state['seen']   = 0;
			break;
		}
		_prime_post_caches( array_map( 'intval', $ids ), false, true );
		foreach ( $ids as $id ) {
			$did = (string) aw_rm_schema_sync( (int) $id, true ); // Dry run: reports, writes nothing.
			if ( preg_match( '/^(added|updated|closed)\b/', $did ) ) {
				++$state['gaps'];
			}
			++$state['seen'];
			$state['cursor'] = (int) $id;
			clean_post_cache( (int) $id );
		}
	}
	update_option( RANKWEB_HEALTH_SCHEMA, $state, false );
	return array(
		'on'   => true,
		'gaps' => isset( $state['done']['gaps'] ) ? (int) $state['done']['gaps'] : null,
		'seen' => (int) ( $state['done']['seen'] ?? 0 ),
		'at'   => (int) ( $state['done']['at'] ?? 0 ),
		'part' => (int) $state['seen'],
	);
}

/**
 * FAQ coverage from the FAQ count (faq-overview.php; this job runs it when its hour has run out).
 *
 * @return array [ 'on' => bool, 'pages' => n, 'drafts' => n ].
 */
function rankweb_count_faq(): array {
	if ( ! function_exists( 'aw_faq_index' ) ) {
		return array(
			'on'     => false,
			'pages'  => 0,
			'drafts' => 0,
		);
	}
	$ix = aw_faq_index();
	return array(
		'on'     => true,
		'pages'  => (int) array_sum( wp_list_pluck( (array) $ix['counts'], 'faq' ) ),
		'drafts' => (int) array_sum( wp_list_pluck( (array) $ix['counts'], 'drafts' ) ),
	);
}

/**
 * Pages RankWeb's own rules keep out of the index: listings with no way to reach them, thin Directory district × type
 * pages, and category views of a hub below the minimum. Plus what is kept indexed on purpose.
 *
 * @return array [ 'total', 'listings', 'thin', 'filtered', 'kept' ].
 */
function rankweb_count_noindex(): array {
	$out = array(
		'listings' => 0,
		'thin'     => 0,
		'filtered' => 0,
		'kept'     => count( (array) ( defined( 'AW_DIRECTORY_KEEP_INDEXED' ) ? AW_DIRECTORY_KEEP_INDEXED : array() ) ) + count( (array) get_option( 'aw_seo_keep_listings', array() ) ),
	);
	if ( function_exists( 'aw_sitemap_unreachable_listings' ) ) {
		$out['listings'] = count( (array) aw_sitemap_unreachable_listings() );
	}
	if ( function_exists( 'aw_place_counts' ) && function_exists( 'aw_place_district' ) && function_exists( 'aw_directory_keep_indexed' ) && function_exists( 'aw_directory_url' ) && defined( 'AW_THIN_DISTRICT_TYPE' ) ) {
		foreach ( (array) ( aw_place_counts()['pairs'] ?? array() ) as $p ) {
			$d = aw_place_district( $p[0] );
			if ( $d && (int) $p[3] < AW_THIN_DISTRICT_TYPE && ! aw_directory_keep_indexed( aw_directory_url( $d->slug, $p[1] ) ) ) {
				++$out['thin'];
			}
		}
	}
	if ( function_exists( 'aw_seo_filtered_count' ) && function_exists( 'aw_section_categories' ) && defined( 'AW_FILTERED_CATEGORY_HUBS' ) ) {
		foreach ( (array) AW_FILTERED_CATEGORY_HUBS as $type ) {
			foreach ( (array) aw_section_categories( $type ) as $slug ) {
				$n = (int) aw_seo_filtered_count( $type, 'category_name', (string) $slug );
				if ( $n > 0 && $n < AW_FILTERED_MIN_ITEMS ) {
					++$out['filtered'];
				}
			}
		}
	}
	$out['total'] = $out['listings'] + $out['thin'] + $out['filtered'];
	return $out;
}

/**
 * Keep the day's numbers (the last run of the day wins), for the Dashboard's time range.
 *
 * @param array $c Counts from rankweb_health_build().
 */
function rankweb_health_log( array $c ): void {
	$log                       = (array) get_option( RANKWEB_HEALTH_LOG, array() );
	$log[ wp_date( 'Y-m-d' ) ] = rankweb_health_numbers( $c );
	ksort( $log );
	$log = array_slice( $log, -RANKWEB_HEALTH_LOG_DAYS, null, true );
	update_option( RANKWEB_HEALTH_LOG, $log, false );
}

/**
 * The numbers the tiles compare over time.
 *
 * @param array $c Counts.
 * @return array metric => number.
 */
function rankweb_health_numbers( array $c ): array {
	$n = array(
		'titles'  => (int) ( $c['titles']['total'] ?? 0 ),
		'descs'   => (int) ( $c['descs']['total'] ?? 0 ),
		'focus'   => (int) ( $c['focus']['total'] ?? 0 ),
		'faq'     => (int) ( $c['faq']['pages'] ?? 0 ),
		'noindex' => (int) ( $c['noindex']['total'] ?? 0 ),
	);
	if ( isset( $c['schema']['gaps'] ) && null !== $c['schema']['gaps'] ) {
		$n['schema'] = (int) $c['schema']['gaps'];
	}
	return $n;
}
