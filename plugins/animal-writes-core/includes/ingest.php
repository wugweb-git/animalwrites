<?php
/**
 * Ingest endpoint for the sourcing workers.
 *
 * POST /wp-json/aw/v1/ingest  (Application Password auth, user needs edit_posts)
 * Everything lands as "pending" (Pending Review). Nothing is ever auto-published.
 * Terms (topics, source_type, city, listing_type) are only assigned if they already exist;
 * unknown ones are skipped and returned in "ignored_terms".
 *
 * Body (JSON):
 * {
 *   "type": "news|legal|research|listing",
 *   "title": "...", "summary": "2–3 lines in our own words",
 *   "source_url": "...", "source_name": "...", "source_date": "YYYY-MM-DD",
 *   "topics": ["..."], "source_type": "Supreme Court", "city": "delhi",
 *   "listing": { "listing_type": "animal-shelter", "phone": "", "whatsapp": "", "email": "", "website": "", "area": "", "services": "" }
 * }
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Find an existing post by source URL (any status) to avoid duplicates.
 *
 * @param string $post_type Post type.
 * @param string $url       Source URL.
 * @return int Post ID or 0.
 */
function aw_find_by_source( $post_type, $url ) {
	if ( ! $url ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'meta_key'       => 'aw_source_url',
			'meta_value'     => $url,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Assign only terms that already exist (matched by slug, then name). Never creates terms.
 *
 * @param int    $post_id  Post ID.
 * @param array  $values   Term names or slugs as received.
 * @param string $taxonomy Taxonomy.
 * @param array  $ignored  Collects unknown values, keyed by taxonomy.
 */
function aw_assign_existing_terms( $post_id, array $values, $taxonomy, array &$ignored ) {
	$ids = array();
	foreach ( $values as $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			continue;
		}
		$term = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'name', $value, $taxonomy );
		}
		if ( $term ) {
			$ids[] = (int) $term->term_id;
		} else {
			$ignored[ $taxonomy ][] = $value;
		}
	}
	if ( $ids ) {
		wp_set_object_terms( $post_id, array_unique( $ids ), $taxonomy );
	}
}

/**
 * Handle an ingest request.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response|WP_Error
 */
function aw_ingest( WP_REST_Request $req ) {
	$map = array(
		'news'     => 'aw_news',
		'legal'    => 'aw_legal',
		'research' => 'aw_research',
		'listing'  => 'aw_listing',
	);
	$type = $map[ $req->get_param( 'type' ) ] ?? '';
	if ( ! $type ) {
		return new WP_Error( 'aw_bad_type', 'type must be news, legal, research or listing', array( 'status' => 400 ) );
	}

	$title      = sanitize_text_field( (string) $req->get_param( 'title' ) );
	$summary    = sanitize_textarea_field( (string) $req->get_param( 'summary' ) );
	$source_url = esc_url_raw( (string) $req->get_param( 'source_url' ) );

	if ( '' === $title ) {
		return new WP_Error( 'aw_no_title', 'title is required', array( 'status' => 400 ) );
	}
	if ( 'aw_listing' !== $type && '' === $source_url ) {
		return new WP_Error( 'aw_no_source', 'source_url is required for cards', array( 'status' => 400 ) );
	}

	$existing = aw_find_by_source( $type, $source_url );
	if ( $existing ) {
		return new WP_REST_Response( array( 'status' => 'duplicate', 'id' => $existing ), 200 );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'pending',
			'post_title'   => $title,
			'post_excerpt' => $summary,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, 'aw_source_url', $source_url );
	update_post_meta( $post_id, 'aw_source_name', sanitize_text_field( (string) $req->get_param( 'source_name' ) ) );
	update_post_meta( $post_id, 'aw_source_date', aw_sanitize_meta( (string) $req->get_param( 'source_date' ), 'aw_source_date' ) );
	$image = esc_url_raw( (string) $req->get_param( 'image_url' ) );
	if ( $image && preg_match( '#^https://#', $image ) ) {
		update_post_meta( $post_id, 'aw_source_image', $image ); // Linked, never downloaded.
	}

	$ignored = array();

	$topics = array_filter( (array) $req->get_param( 'topics' ), 'is_scalar' );
	if ( $topics ) {
		aw_assign_existing_terms( $post_id, $topics, 'aw_topic', $ignored );
	}
	if ( $req->get_param( 'source_type' ) && in_array( $type, array( 'aw_legal', 'aw_research' ), true ) ) {
		aw_assign_existing_terms( $post_id, array( $req->get_param( 'source_type' ) ), 'aw_source_type', $ignored );
	}
	if ( $req->get_param( 'city' ) && in_array( $type, array( 'aw_listing', 'aw_news' ), true ) ) {
		aw_assign_existing_terms( $post_id, array( $req->get_param( 'city' ) ), 'aw_city', $ignored );
	}

	if ( 'aw_listing' === $type ) {
		$l = (array) $req->get_param( 'listing' );
		foreach ( array( 'phone', 'whatsapp', 'email', 'website', 'area', 'services' ) as $f ) {
			if ( isset( $l[ $f ] ) ) {
				update_post_meta( $post_id, 'aw_' . $f, aw_sanitize_meta( $l[ $f ], 'aw_' . $f ) );
			}
		}
		if ( ! empty( $l['listing_type'] ) ) {
			aw_assign_existing_terms( $post_id, array( $l['listing_type'] ), 'aw_listing_type', $ignored );
		}
		update_post_meta( $post_id, 'aw_verified', false ); // Imports are never verified.
		update_post_meta( $post_id, 'aw_featured', 0 );
	}

	return new WP_REST_Response(
		array(
			'status'        => 'created',
			'id'            => $post_id,
			'ignored_terms' => (object) $ignored,
		),
		201
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/ingest',
			array(
				'methods'             => 'POST',
				'callback'            => 'aw_ingest',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
);
