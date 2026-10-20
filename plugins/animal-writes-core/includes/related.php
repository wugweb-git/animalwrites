<?php
/**
 * Related items (SEO Phase 7, internal linking): [aw_related] at the end of an item page.
 *
 * What an item shows, each group only when it has something:
 *   News         Related news · From the Library
 *   Library      Related in the Library · News about this · Resources
 *   Resources    Related resources · From the Library
 *   Opportunity  More opportunities (open ones only)
 *   Listing      More organisations in {District} · More organisations in {State} (only to fill up to 6: same type
 *                nearby, other types nearby, then the rest of the state; aw_related_state_fill()) · News from {District}
 *
 * - Picked with WordPress's own queries: shared categories (category__in), shared tags (tag__in) and the same place
 *   (aw_state_code / aw_district_code meta). Links set by hand in WP Admin (aw_rel_legal, aw_rel_resource) come first.
 *   Scored: same district 3, each shared category 2, same state 1, each shared tag 1; newer first on a tie.
 * - The cards are the hubs' own (aw_hub_item() in a core Query Loop with the hub list styles, aw-hub-list), so pictures, dates and labels are the
 *   same as on the lists. Card titles are h3 under the group's h2.
 * - The picked IDs are cached for 12 hours per item (a transient); the page itself is cached by LiteSpeed.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How many cards per group. */
const AW_RELATED_COUNT = array( 'aw_news' => 3, 'aw_legal' => 3, 'post' => 3, 'aw_opportunity' => 3, 'aw_listing' => 6 );

/** First queryId used by the related lists (the hubs use 101–107). */
const AW_RELATED_QUERY_ID = 151;

/** Items never shown as related: preview-only ones (the sample listing). */
function aw_related_skip_ids() {
	static $ids = null;
	if ( null === $ids ) {
		$ids = function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array();
	}
	return $ids;
}

/**
 * Pick related items of one type for an item.
 *
 * @param int    $id     The item.
 * @param string $type   Post type to pick from.
 * @param int    $n      How many.
 * @param array  $first  IDs that come first (links set by hand).
 * @param array  $extra  Extra WP_Query vars (e.g. open opportunities only).
 * @param bool   $place_only Pick from the same place only (categories and tags still order them).
 * @return int[]
 */
function aw_related_pick( $id, $type, $n, $first = array(), $extra = array(), $place_only = false ) {
	$cats = wp_get_object_terms( $id, 'category', array( 'fields' => 'ids' ) );
	$tags = wp_get_object_terms( $id, 'post_tag', array( 'fields' => 'ids' ) );
	$cats = is_wp_error( $cats ) ? array() : array_map( 'intval', $cats );
	$tags = is_wp_error( $tags ) ? array() : array_map( 'intval', $tags );
	if ( $type !== get_post_type( $id ) ) {
		$sec  = function_exists( 'aw_section_tags' ) ? aw_section_tags( $type ) : array(); // another section's tags mean nothing here
		$tags = array_values( array_filter( $tags, fn( $t ) => in_array( (string) get_term_field( 'slug', $t, 'post_tag' ), $sec, true ) ) );
	}
	list( $states, $districts ) = function_exists( 'aw_item_codes' ) ? aw_item_codes( $id ) : array( array(), array() );
	$skip = array_merge( array( (int) $id ), aw_related_skip_ids() );
	$base = array_merge(
		array(
			'post_type'           => $type,
			'post_status'         => 'publish',
			'posts_per_page'      => 40,
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'post__not_in'        => $skip,
			'orderby'             => 'date',
			'order'               => 'DESC',
		),
		$extra
	);
	$pool = array();
	$tax  = array( 'relation' => 'OR' );
	if ( $cats ) {
		$tax[] = array( 'taxonomy' => 'category', 'terms' => $cats, 'include_children' => false );
	}
	if ( $tags ) {
		$tax[] = array( 'taxonomy' => 'post_tag', 'terms' => $tags );
	}
	$camp = function_exists( 'aw_campaign_links' ) ? wp_list_pluck( aw_campaign_links( $id ), 'term_id' ) : array();
	if ( $camp && taxonomy_exists( 'aw_campaign_link' ) ) { // the same campaign (campaign-links.php)
		$tax[] = array( 'taxonomy' => 'aw_campaign_link', 'terms' => array_map( 'intval', $camp ) );
	}
	if ( count( $tax ) > 1 && ! $place_only ) {
		$pool = array_merge( $pool, ( new WP_Query( $base + array( 'tax_query' => $tax ) ) )->posts ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$where = $districts ? array( 'key' => 'aw_district_code', 'value' => array_map( 'strval', $districts ), 'compare' => 'IN' ) : ( $states ? array( 'key' => 'aw_state_code', 'value' => array_map( 'strval', $states ), 'compare' => 'IN' ) : null );
	if ( $where && in_array( $type, AW_PLACE_TYPES, true ) ) {
		$mq   = isset( $base['meta_query'] ) ? array( 'relation' => 'AND', $base['meta_query'], $where ) : array( $where );
		$pool = array_merge( $pool, ( new WP_Query( array_merge( $base, array( 'meta_query' => $mq ) ) ) )->posts ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$pool = array_values( array_unique( array_map( 'intval', $pool ) ) );
	if ( ! $pool && ! $first ) {
		return array();
	}
	// Score the pool.
	$score = array();
	$terms = $pool ? wp_get_object_terms( $pool, array( 'category', 'post_tag' ), array( 'fields' => 'all_with_object_id' ) ) : array();
	foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
		$oid = (int) $t->object_id;
		if ( 'category' === $t->taxonomy && in_array( (int) $t->term_id, $cats, true ) ) {
			$score[ $oid ] = ( $score[ $oid ] ?? 0 ) + 2;
		} elseif ( 'post_tag' === $t->taxonomy && in_array( (int) $t->term_id, $tags, true ) ) {
			$score[ $oid ] = ( $score[ $oid ] ?? 0 ) + 1;
		}
	}
	if ( $pool && ( $states || $districts ) ) {
		update_meta_cache( 'post', $pool );
		foreach ( $pool as $pid ) {
			list( $s, $d ) = aw_item_codes( $pid );
			$score[ $pid ] = ( $score[ $pid ] ?? 0 ) + ( array_intersect( $d, $districts ) ? 3 : 0 ) + ( array_intersect( $s, $states ) ? 1 : 0 );
		}
	}
	if ( 'title' === ( $base['orderby'] ?? '' ) ) { // A–Z lists (listings): start after this item's own name, so neighbours link each other
		_prime_post_caches( $pool, false, false );
		$own  = mb_strtolower( (string) get_post_field( 'post_title', $id ) );
		$pool = array_merge( array_values( array_filter( $pool, fn( $p ) => mb_strtolower( (string) get_post_field( 'post_title', $p ) ) > $own ) ), array_values( array_filter( $pool, fn( $p ) => mb_strtolower( (string) get_post_field( 'post_title', $p ) ) <= $own ) ) );
	}
	$order = array_flip( $pool ); // newer first (each query is by date) on a tie; for A–Z lists, the next names
	usort( $pool, fn( $a, $b ) => ( ( $score[ $b ] ?? 0 ) <=> ( $score[ $a ] ?? 0 ) ) ?: ( $order[ $a ] <=> $order[ $b ] ) );
	$pool = array_values( array_filter( $pool, fn( $p ) => ( $score[ $p ] ?? 0 ) > 0 ) );
	$first = array_values( array_filter( array_map( 'intval', $first ), fn( $p ) => $p !== (int) $id && 'publish' === get_post_status( $p ) && get_post_type( $p ) === $type && ! in_array( $p, $skip, true ) ) );
	return array_slice( array_values( array_unique( array_merge( $first, $pool ) ) ), 0, $n );
}

/** Within this distance (km) of a listing's district, another district counts as nearby for the state fill. */
const AW_RELATED_NEAR_KM = 150;

/** District centres [lat, lng] by LGD district code (data/district-centroids.json, OpenStreetMap Nominatim, 20 Oct 2026). */
function aw_district_centroid( $code ) {
	static $c = null;
	if ( null === $c ) {
		$f = dirname( __DIR__ ) . '/data/district-centroids.json';
		$c = is_readable( $f ) ? (array) json_decode( (string) file_get_contents( $f ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $c[ (string) (int) $code ] ?? null;
}

/** Distance in km between two districts' centres; null when either is not known. */
function aw_district_km( $a, $b ) {
	$p = aw_district_centroid( $a );
	$q = aw_district_centroid( $b );
	if ( ! $p || ! $q ) {
		return null;
	}
	$r = M_PI / 180;
	$h = sin( ( $q[0] - $p[0] ) * $r / 2 ) ** 2 + cos( $p[0] * $r ) * cos( $q[0] * $r ) * sin( ( $q[1] - $p[1] ) * $r / 2 ) ** 2;
	return 12742 * asin( min( 1, sqrt( $h ) ) );
}

/**
 * A state's published listings: [ID, district code, title, type slugs], cached for 12 hours per state.
 *
 * @param int $state State code.
 * @return array
 */
function aw_related_state_pool( $state ) {
	$key  = 'aw_related_state_' . (int) $state;
	$pool = get_transient( $key );
	if ( is_array( $pool ) ) {
		return $pool;
	}
	global $wpdb;
	$types = "'" . implode( "','", array_map( 'esc_sql', function_exists( 'aw_listing_type_slugs' ) ? aw_listing_type_slugs() : array() ) ) . "'";
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID id, p.post_title title, MIN(CAST(dm.meta_value AS UNSIGNED)) district, GROUP_CONCAT(DISTINCT t.slug) types FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = 'aw_state_code' AND sm.meta_value = %s LEFT JOIN {$wpdb->postmeta} dm ON dm.post_id = p.ID AND dm.meta_key = 'aw_district_code' LEFT JOIN {$wpdb->term_relationships} rt ON rt.object_id = p.ID LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = rt.term_taxonomy_id AND tt.taxonomy = 'post_tag' LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.slug IN ({$types}) WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND p.post_name <> '' GROUP BY p.ID", (string) (int) $state ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$pool  = array();
	foreach ( $rows as $r ) {
		$pool[] = array( (int) $r['id'], (int) $r['district'], (string) $r['title'], array_filter( explode( ',', (string) $r['types'] ) ) );
	}
	set_transient( $key, $pool, 12 * HOUR_IN_SECONDS );
	return $pool;
}

/**
 * Organisations elsewhere in a listing's state, to fill its related list when its district has too few (owner, 20 Oct 2026):
 * the same type in nearby districts first, then other types nearby, then the rest of the state; nearest first, then
 * A–Z, so the order is the same on every load.
 *
 * @param int   $id    The listing.
 * @param object $place Its district.
 * @param int   $n     How many.
 * @param int[] $skip  IDs already shown.
 * @return int[]
 */
function aw_related_state_fill( $id, $place, $n, $skip ) {
	if ( $n < 1 || ! $place || 'district' !== $place->kind ) {
		return array();
	}
	$tags = wp_get_object_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) );
	$own  = array_flip( array_intersect( function_exists( 'aw_listing_type_slugs' ) ? aw_listing_type_slugs() : array(), is_array( $tags ) ? $tags : array() ) );
	$skip = array_flip( array_merge( array( (int) $id ), array_map( 'intval', $skip ), aw_related_skip_ids() ) );
	$rows = array();
	foreach ( aw_related_state_pool( $place->state ) as $r ) {
		if ( isset( $skip[ $r[0] ] ) || (int) $r[1] === (int) $place->code ) {
			continue; // this listing, ones already shown, and its own district (the district group has those)
		}
		$km   = $r[1] ? aw_district_km( $place->code, $r[1] ) : null;
		$near = null !== $km && $km <= AW_RELATED_NEAR_KM;
		$same = (bool) array_intersect_key( array_flip( $r[3] ), $own );
		$rows[] = array( $near ? ( $same ? 0 : 1 ) : 2, $km ?? INF, mb_strtolower( $r[2] ), $r[0] );
	}
	usort( $rows, fn( $a, $b ) => array( $a[0], $a[1], $a[2], $a[3] ) <=> array( $b[0], $b[1], $b[2], $b[3] ) );
	return array_slice( array_column( $rows, 3 ), 0, $n );
}

/** Items that link to this one by hand (the other side of a relation): IDs. */
function aw_related_back( $id, $key, $type ) {
	return get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 10, 'fields' => 'ids', 'meta_key' => $key, 'meta_value' => (string) $id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
}

/** Links set by hand on this item (aw_rel_*): IDs. */
function aw_related_set( $id, $key ) {
	return array_map( 'intval', (array) get_post_meta( $id, $key ) );
}

/**
 * The related groups of an item: list of [heading, post type, IDs]. Cached for 12 hours.
 *
 * @param int $id Item.
 * @return array
 */
function aw_related_groups( $id ) {
	$key    = 'aw_related_' . (int) $id;
	$groups = get_transient( $key );
	if ( is_array( $groups ) ) {
		return $groups;
	}
	$type   = get_post_type( $id );
	$c      = AW_RELATED_COUNT;
	$groups = array();
	switch ( $type ) {
		case 'aw_news':
			$groups[] = array( 'Related news', 'aw_news', aw_related_pick( $id, 'aw_news', $c['aw_news'] ) );
			$groups[] = array( 'From the Library', 'aw_legal', aw_related_pick( $id, 'aw_legal', $c['aw_legal'], aw_related_set( $id, 'aw_rel_legal' ) ) );
			break;
		case 'aw_legal':
			$groups[] = array( 'Related in the Library', 'aw_legal', aw_related_pick( $id, 'aw_legal', $c['aw_legal'] ) );
			$groups[] = array( 'News about this', 'aw_news', aw_related_pick( $id, 'aw_news', $c['aw_news'], aw_related_back( $id, 'aw_rel_legal', 'aw_news' ) ) );
			$groups[] = array( 'Resources', 'post', aw_related_pick( $id, 'post', $c['post'], aw_related_back( $id, 'aw_rel_resource', 'post' ) ) );
			break;
		case 'post':
			$groups[] = array( 'Related resources', 'post', aw_related_pick( $id, 'post', $c['post'] ) );
			$groups[] = array( 'From the Library', 'aw_legal', aw_related_pick( $id, 'aw_legal', $c['aw_legal'], aw_related_set( $id, 'aw_rel_resource' ) ) );
			break;
		case 'aw_opportunity':
			$open     = function_exists( 'aw_open_opportunities_meta_query' ) ? array( 'meta_query' => aw_open_opportunities_meta_query() ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
			$groups[] = array( 'More opportunities', 'aw_opportunity', aw_related_pick( $id, 'aw_opportunity', $c['aw_opportunity'], array(), $open ) );
			break;
		case 'aw_listing':
			$p = function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null;
			if ( $p ) {
				$local    = aw_related_pick( $id, 'aw_listing', $c['aw_listing'], array(), array( 'orderby' => 'title', 'order' => 'ASC', 'posts_per_page' => 300 ), true );
				$groups[] = array( sprintf( 'More organisations in %s', $p->name ), 'aw_listing', $local );
				$state    = 'district' === $p->kind ? aw_place_state( $p->state ) : null;
				if ( $state ) { // too few in the district: fill up from the state
					$groups[] = array( sprintf( 'More organisations in %s', $state->name ), 'aw_listing', aw_related_state_fill( $id, $p, $c['aw_listing'] - count( $local ), $local ) );
				}
				$groups[] = array( sprintf( 'News from %s', $p->name ), 'aw_news', aw_related_pick( $id, 'aw_news', $c['aw_news'], aw_related_back( $id, 'aw_rel_org', 'aw_news' ), array(), true ) );
			}
			break;
	}
	$groups = array_values( array_filter( $groups, fn( $g ) => ! empty( $g[2] ) ) );
	set_transient( $key, $groups, 12 * HOUR_IN_SECONDS );
	return $groups;
}

// An item's own related list is worked out again when it is saved.
add_action( 'save_post', fn( $id ) => delete_transient( 'aw_related_' . (int) $id ) );

/** The IDs each related Query Loop shows (queryId => IDs), for this request. */
function aw_related_queries( $qid = null, $ids = null ) {
	static $q = array();
	if ( null !== $ids ) {
		$q[ $qid ] = $ids;
	}
	return null === $qid ? $q : ( $q[ $qid ] ?? null );
}

// A related Query Loop shows exactly its picked items, in order.
add_filter(
	'query_loop_block_query_vars',
	function ( $vars, $block ) {
		$ids = aw_related_queries( (int) ( $block->context['queryId'] ?? 0 ) );
		if ( null === $ids ) {
			return $vars;
		}
		return array(
			'post_type'           => $block->context['query']['postType'] ?? 'post',
			'post_status'         => 'publish',
			'post__in'            => $ids ? $ids : array( 0 ),
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
	},
	30,
	2
);

/**
 * One group: its heading and the hub's cards, through a core Query Loop.
 *
 * @param string $head Heading.
 * @param string $type Post type.
 * @param int[]  $ids  Items.
 * @return string
 */
function aw_related_group_html( $head, $type, $ids ) {
	static $n = 0;
	if ( ! $ids || ! function_exists( 'aw_hub_item' ) ) {
		return '';
	}
	$qid   = AW_RELATED_QUERY_ID + ( $n++ );
	$lists = array( 'aw_news' => 'aw-news-list', 'aw_legal' => 'aw-cases', 'post' => 'aw-tasks', 'aw_opportunity' => 'aw-opps', 'aw_listing' => 'aw-orgs' );
	aw_related_queries( $qid, array_map( 'intval', $ids ) );
	$item  = str_replace( '"level":2', '"level":3', aw_hub_item( $type ) ); // under the group's h2
	$block = '<!-- wp:query {"queryId":' . $qid . ',"query":{"perPage":' . count( $ids ) . ',"pages":1,"offset":0,"postType":"' . $type . '","order":"desc","orderBy":"date","inherit":false},"className":"aw-hub-list aw-related-list","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list aw-related-list"><!-- wp:post-template {"className":"' . $lists[ $type ] . '","layout":{"type":"default"}} -->
' . $item . '
<!-- /wp:post-template --></div>
<!-- /wp:query -->';
	$html  = do_blocks( $block );
	if ( false === strpos( $html, 'wp-block-post' ) ) {
		return '';
	}
	$hid = 'aw-related-' . $qid;
	return '<section class="aw-related alignwide" aria-labelledby="' . esc_attr( $hid ) . '"><h2 class="aw-related-head" id="' . esc_attr( $hid ) . '">' . esc_html( $head ) . '</h2>' . $html . '</section>';
}

/** [aw_related]: the related groups of the item page. */
add_shortcode(
	'aw_related',
	function () {
		$id = get_queried_object_id();
		if ( ! $id || ! is_singular( array_keys( AW_RELATED_COUNT ) ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		$html = '';
		foreach ( aw_related_groups( $id ) as $g ) {
			$html .= aw_related_group_html( $g[0], $g[1], $g[2] );
		}
		return $html;
	}
);
