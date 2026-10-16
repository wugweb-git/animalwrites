<?php
/**
 * Home "Opportunities near you" (owner, 16 Oct 2026): the list shows opportunities in the visitor's state. The home
 * page is cached for everyone, so the server draws the newest ones and aw.js swaps in the local rows from
 * GET /wp-json/aw/v1/opps-near?state={slug} when the visitor has chosen a state in a Directory search (kept in
 * their own browser only). Rows are the same Query Loop markup as the pattern; fewer than two local items are
 * filled with the newest.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_NEAR_OPPS_QUERY_ID = 251;
const AW_NEAR_OPPS_COUNT    = 2;

/**
 * Up to two opportunity IDs: the newest in the state first, then the newest anywhere.
 *
 * @param object $state State place (aw_place_by_slug).
 * @return array{0: int[], 1: int} IDs and how many are in the state.
 */
function aw_near_opps_ids( $state ) {
	$base  = array( 'post_type' => 'aw_opportunity', 'post_status' => 'publish', 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'date', 'order' => 'DESC' );
	$local = get_posts( $base + array( 'numberposts' => AW_NEAR_OPPS_COUNT, 'meta_query' => array( array( 'key' => 'aw_state_code', 'value' => (string) $state->code ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$ids   = array_map( 'intval', $local );
	if ( count( $ids ) < AW_NEAR_OPPS_COUNT ) {
		$more = get_posts( $base + array( 'numberposts' => AW_NEAR_OPPS_COUNT, 'post__not_in' => $ids ? $ids : array( 0 ) ) );
		$ids  = array_slice( array_merge( $ids, array_map( 'intval', $more ) ), 0, AW_NEAR_OPPS_COUNT );
	}
	return array( $ids, count( $local ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/opps-near',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array( 'state' => array( 'required' => true, 'sanitize_callback' => 'sanitize_title' ) ),
				'callback'            => function ( WP_REST_Request $r ) {
					$state = function_exists( 'aw_place_by_slug' ) ? aw_place_by_slug( (string) $r['state'] ) : null;
					if ( ! $state || 'state' !== $state->kind ) {
						return new WP_REST_Response( array( 'local' => 0, 'html' => '' ), 200 );
					}
					$key  = 'aw_near_opps_' . $state->code;
					$data = get_transient( $key );
					if ( false === $data ) {
						list( $ids, $local ) = aw_near_opps_ids( $state );
						$html                = '';
						if ( $local && function_exists( 'aw_related_queries' ) ) {
							aw_related_queries( AW_NEAR_OPPS_QUERY_ID, $ids ); // the Query Loop shows exactly these, in order
							$out = do_blocks( '<!-- wp:query {"queryId":' . AW_NEAR_OPPS_QUERY_ID . ',"query":{"perPage":' . count( $ids ) . ',"pages":0,"offset":0,"postType":"aw_opportunity","order":"desc","orderBy":"date","inherit":false},"className":"kit-ed-titles aw-ed-opps"} --><div class="wp-block-query kit-ed-titles aw-ed-opps"><!-- wp:post-template --><!-- wp:post-title {"level":3,"isLink":true} /--><!-- wp:post-terms {"term":"post_tag"} /--><!-- /wp:post-template --></div><!-- /wp:query -->' );
							$html = preg_match( '#<ul\b[^>]*wp-block-post-template.*</ul>#s', $out, $m ) ? $m[0] : '';
						}
						$data = array( 'local' => $local, 'state' => $state->name, 'html' => $html );
						set_transient( $key, $data, HOUR_IN_SECONDS );
					}
					$res = new WP_REST_Response( $data, 200 );
					$res->header( 'Cache-Control', 'public, max-age=600' );
					return $res;
				},
			)
		);
	}
);

// A new, changed or removed opportunity refreshes every state's list.
add_action(
	'save_post_aw_opportunity',
	function () {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aw\_near\_opps\_%' OR option_name LIKE '\_transient\_timeout\_aw\_near\_opps\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
);
