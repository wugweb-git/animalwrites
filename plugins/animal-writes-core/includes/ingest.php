<?php
/**
 * Ingest endpoint for the sourcing workers.
 *
 * POST /wp-json/aw/v1/ingest  (Application Password auth, user needs edit_posts)
 * Everything lands as "pending" (Pending Review). Nothing is ever auto-published.
 * Terms (categories, source_type, city, listing_type) are only assigned if they already exist;
 * unknown ones are skipped and returned in "ignored_terms".
 *
 * Body (JSON):
 * {
 *   "type": "news|legal|research|listing",
 *   "title": "...", "summary": "2–3 lines in our own words",
 *   "source_url": "...", "source_name": "...", "source_date": "YYYY-MM-DD",
 *   "categories": ["..."], "source_type": "Supreme Court", "city": "delhi",
 *   "kind": "judgment", "court": "supreme-court", "document_url": "https://...pdf"   (Library only),
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
			// Every section, not just $post_type: an item moved from Legal Watch to News must not come back as a new Legal item.
			'post_type'      => 'aw_listing' === $post_type ? $post_type : array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity' ),
			// Trash included (12 Oct 2026): a trashed item must not come back as new if the workers' memory is lost.
			'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'future', 'trash' ),
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
	if ( 'press' === $req->get_param( 'type' ) && function_exists( 'aw_press_ingest' ) ) {
		return aw_press_ingest( $req ); // coverage of Animal Writes found by the press-discovery worker: drafts to review
	}
	$map = array(
		'news'     => 'aw_news',
		'legal'    => 'aw_legal',
		'research' => 'post', // Resources; tagged Research below
		'listing'  => 'aw_listing',
		'opportunity' => 'aw_opportunity',
	);
	$type = $map[ $req->get_param( 'type' ) ] ?? '';
	if ( ! $type ) {
		return new WP_Error( 'aw_bad_type', 'type must be news, legal, research, listing or opportunity', array( 'status' => 400 ) );
	}

	$title      = sanitize_text_field( (string) $req->get_param( 'title' ) );
	$summary    = sanitize_textarea_field( (string) $req->get_param( 'summary' ) );
	// The workers mark legal summaries "[Needs legal review]": an internal flag, never part of the published text.
	$legal_flag = false !== strpos( $summary, '[Needs legal review]' );
	$summary    = trim( str_replace( '[Needs legal review]', '', $summary ) );
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
	// Same story: the same normalised title dated within a day either side, drafts included (relevance.php).
	if ( 'aw_listing' !== $type && function_exists( 'aw_find_by_title' ) ) {
		$existing = aw_find_by_title( array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity' ), $title, (string) $req->get_param( 'source_date' ) );
		if ( $existing ) {
			return new WP_REST_Response( array( 'status' => 'duplicate', 'id' => $existing ), 200 );
		}
	}

	// Sourcing policy (News → Sourcing): blocked publishers and, when switched on, the keyword filter.
	if ( function_exists( 'aw_sourcing_gate' ) && function_exists( 'kits_sourced_types' ) && in_array( $type, kits_sourced_types(), true ) ) {
		$verdict = aw_sourcing_gate( $source_url, (string) $req->get_param( 'source_name' ), (string) $req->get_param( 'feed' ), $title . ' ' . $summary );
		if ( $verdict ) {
			return new WP_REST_Response( array( 'status' => $verdict ), 200 );
		}
	}

	// Backfill (owner, 9 Oct 2026): older items for review only — never before the floor, never published or merged here.
	$backfill = (bool) $req->get_param( 'backfill' );
	if ( $backfill ) {
		$floor = (string) get_option( 'aw_backfill_floor', '2025-11-01' );
		$day   = substr( (string) $req->get_param( 'source_date' ), 0, 10 );
		if ( 'aw_opportunity' === $type || ( $day && $day < $floor ) ) {
			return new WP_REST_Response( array( 'status' => 'filtered' ), 200 );
		}
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => $type,
			// Opportunities from approved sources are factual listings (title, organisation, place, deadline, link)
			// and go live directly (owner's decision, 7 Oct 2026). Everything else waits for review.
			'post_status'  => 'aw_opportunity' === $type ? 'publish' : 'pending',
			'post_title'   => $title,
			'post_excerpt' => $summary,
			'post_content' => 'aw_opportunity' === $type ? '<!-- wp:paragraph --><p>' . esc_html( $summary ) . '</p><!-- /wp:paragraph -->' : '',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, 'aw_source_url', $source_url );
	if ( function_exists( 'aw_relevance_store' ) ) {
		aw_relevance_store( $post_id, $req->get_param( 'relevance' ) ); // the workers' relevance verdict
	}
	if ( $legal_flag ) {
		update_post_meta( $post_id, 'aw_legal_review_flag', 1 );
	}
	update_post_meta( $post_id, 'aw_source_name', sanitize_text_field( (string) $req->get_param( 'source_name' ) ) );
	update_post_meta( $post_id, 'aw_source_date', aw_sanitize_meta( (string) $req->get_param( 'source_date' ), 'aw_source_date' ) );
	$image = esc_url_raw( (string) $req->get_param( 'image_url' ) );
	if ( $image && preg_match( '#^https://#', $image ) ) {
		update_post_meta( $post_id, 'aw_source_image', $image ); // Linked, never downloaded.
	}

	$ignored = array();

	$cats = array_filter( (array) $req->get_param( 'categories' ), 'is_scalar' );
	if ( ! $cats && AW_WORKER_OLD_NAMES ) {
		$cats = array_filter( (array) $req->get_param( 'topics' ), 'is_scalar' ); // the workers' old name, until they are deployed
	}
	if ( $cats ) {
		aw_assign_existing_terms( $post_id, $cats, 'category', $ignored );
	}
	if ( $req->get_param( 'source_type' ) && 'aw_legal' === $type ) {
		if ( ! aw_set_issuer( $post_id, (string) $req->get_param( 'source_type' ) ) ) { // issuer: a label (issuers.php)
			$ignored[] = 'issuer: ' . sanitize_text_field( (string) $req->get_param( 'source_type' ) );
		}
	}
	// Library documents: what it is (judgment, order, notification...) and, for judgments, the court.
	if ( 'aw_legal' === $type ) {
		// No kind from the worker: a government press release (PIB) is an Official release.
		$kind = $req->get_param( 'kind' ) ? $req->get_param( 'kind' ) : ( 'pib' === $req->get_param( 'source_type' ) ? 'official-release' : '' );
		if ( $kind && ! term_exists( $kind, 'aw_legal_kind' ) && isset( AW_LEGAL_KINDS[ $kind ] ) ) {
			wp_insert_term( AW_LEGAL_KINDS[ $kind ], 'aw_legal_kind', array( 'slug' => $kind ) );
		}
		if ( $kind ) {
			aw_assign_existing_terms( $post_id, array( $kind ), 'aw_legal_kind', $ignored );
		}
		// A court is an issuer (8 Oct 2026): Supreme Court, High Court, National Green Tribunal.
		if ( $req->get_param( 'court' ) && ! $req->get_param( 'source_type' ) ) {
			aw_set_issuer( $post_id, (string) $req->get_param( 'court' ) );
		}
	}
	// Place: names are matched to the LGD file (places.php); an unknown name is ignored, never added.
	if ( $req->get_param( 'city' ) && in_array( $type, array( 'aw_listing', 'aw_news' ), true ) ) {
		$places = aw_places_from_text( (string) $req->get_param( 'city' ) );
		if ( $places ) {
			aw_set_item_places( $post_id, $places );
		} else {
			$ignored[] = 'city: ' . sanitize_text_field( (string) $req->get_param( 'city' ) );
		}
	}

	if ( 'aw_opportunity' === $type ) {
		update_post_meta( $post_id, 'aw_org_name', sanitize_text_field( (string) $req->get_param( 'org_name' ) ) );
		aw_link_opportunity_org( $post_id );
		update_post_meta( $post_id, 'aw_apply_url', $source_url );
		$deadline = aw_sanitize_meta( (string) $req->get_param( 'deadline' ), 'aw_deadline' );
		if ( $deadline ) {
			update_post_meta( $post_id, 'aw_deadline', $deadline );
		}
		if ( $req->get_param( 'opportunity_type' ) ) {
			aw_assign_existing_terms( $post_id, array( $req->get_param( 'opportunity_type' ) ), 'aw_opportunity_type', $ignored );
		}
		// Place as published ("Pune/Hyderabad", "Bhopal, Madhya Pradesh"): every part that names an LGD state or district.
		$places = aw_places_from_text( (string) $req->get_param( 'place' ) );
		if ( $places ) {
			aw_set_item_places( $post_id, $places );
		}
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

	if ( 'aw_legal' === $type && function_exists( 'aw_mirror_gov_pdf' ) ) {
		$doc_url = esc_url_raw( (string) $req->get_param( 'document_url' ) ); // the PDF, when the source page is not the PDF itself
		aw_mirror_gov_pdf( $post_id, $doc_url ? $doc_url : $source_url );
	}

	// Routine items go live when every check passes; the rest wait with the reason (auto-review.php).
	if ( 'research' === $req->get_param( 'type' ) ) {
		wp_set_object_terms( $post_id, 'research', 'aw_resource_type', false );
	}
	if ( $backfill ) {
		update_post_meta( $post_id, 'aw_backfill', current_time( 'Y-m-d' ) );
		$day = substr( (string) $req->get_param( 'source_date' ), 0, 10 );
		// Owner, 9 Oct 2026: backfilled items go live at once, with their own publication date (never the day fetched).
		$when = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? min( $day . ' 09:00:00', current_time( 'mysql' ) ) : current_time( 'mysql' ); // never in the future (would schedule, not publish)
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish', 'post_date' => $when, 'post_date_gmt' => get_gmt_from_date( $when ), 'edit_date' => true ) );
		delete_post_meta( $post_id, 'aw_review' );
		$review = '';
	} else {
		$review = function_exists( 'aw_auto_review' ) ? aw_auto_review( $post_id ) : '';
	}

	return new WP_REST_Response(
		array(
			'status'        => 'created',
			'id'            => $post_id,
			// 'published', 'merged', or why it waits; the workers count published items for the heartbeat.
			'review'        => 'publish' === get_post_status( $post_id ) ? 'published' : (string) $review,
			'merged_into'   => (int) get_post_meta( $post_id, 'aw_merged_into', true ), // same event: the workers add its facts there
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

/**
 * Link an opportunity to its organisation's directory listing (exact name match), so the organisation's profile
 * lists it. Leaves it unlinked when there is no listing of that name, or more than one.
 *
 * @param int $post_id Opportunity.
 */
function aw_link_opportunity_org( $post_id ) {
	$name = trim( (string) get_post_meta( $post_id, 'aw_org_name', true ) );
	if ( '' === $name || get_post_meta( $post_id, 'aw_organisation', true ) ) {
		return;
	}
	$ids = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 'title' => $name, 'numberposts' => 2, 'fields' => 'ids' ) );
	if ( 1 === count( $ids ) ) {
		add_post_meta( $post_id, 'aw_organisation', (int) $ids[0] );
	}
}
