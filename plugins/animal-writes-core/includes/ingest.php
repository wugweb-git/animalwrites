<?php
/**
 * Ingest endpoint for the sourcing workers.
 *
 * POST /wp-json/aw/v1/ingest  (Application Password auth, user needs edit_posts)
 * Everything lands as "pending" (Pending Review). Nothing is ever auto-published.
 * Terms (categories and tags from the section's lists, source_type, city) are only assigned if they already exist;
 * unknown ones are skipped and returned in "ignored_terms".
 *
 * Body (JSON):
 * {
 *   "type": "news|legal|research|listing",
 *   "title": "...", "summary": "2–3 lines in our own words",
 *   "source_url": "...", "source_name": "...", "source_date": "YYYY-MM-DD",
 *   "categories": ["..."], "tags": ["judgment"], "source_type": "Supreme Court", "city": "delhi",
 *   "court": "supreme-court", "document_url": "https://...pdf"   (Library only; its type is a tag, e.g. "judgment"),
 *   "listing": { "phone": "", "whatsapp": "", "email": "", "website": "", "area": "", "services": "" }
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
 * A Library item already holding this notification number ("G.S.R. 864(E)", "GSR 864 (E)" ... compared without spaces
 * and dots), in any state but the bin; 0 when none.
 *
 * @param string $number Notification number.
 * @return int
 */
function aw_legal_by_number( $number ) {
	global $wpdb;
	$key = strtoupper( preg_replace( '/[^0-9A-Z]/i', '', (string) $number ) );
	if ( ! preg_match( '/\d/', $key ) || ! preg_match( '/(\d+)/', $key, $d ) ) {
		return 0;
	}
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'aw_legal' AND p.post_status <> 'trash' WHERE m.meta_key = 'aw_legal_number' AND m.meta_value LIKE %s", '%' . $wpdb->esc_like( $d[1] ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $rows as $r ) {
		if ( strtoupper( preg_replace( '/[^0-9A-Z]/i', '', (string) $r->meta_value ) ) === $key ) {
			return (int) $r->post_id;
		}
	}
	return 0;
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
	if ( in_array( $type, array( 'aw_news', 'aw_legal', 'post' ), true ) && function_exists( 'aw_whole_sentences' ) ) {
		$summary = aw_whole_sentences( $summary ); // never a tail cut off mid-sentence (item-head.php)
	}
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
	// A Gazette notification we already hold under the same number (G.S.R. / S.O. ...(E)), whatever its title or source
	// (owner, 16 Oct 2026: the draft lab animal rules came in twice, from the Gazette and from CCSEA).
	$number = sanitize_text_field( (string) $req->get_param( 'legal_number' ) );
	if ( 'aw_legal' === $type && '' !== $number ) {
		$held = aw_legal_by_number( $number );
		if ( $held ) {
			return new WP_REST_Response( array( 'status' => 'duplicate', 'id' => $held ), 200 );
		}
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
			// A research paper's short keyword slug from its headline (research_titles.py, owner 20 Oct 2026).
			'post_name'    => 'research' === $req->get_param( 'type' ) ? sanitize_title( (string) $req->get_param( 'slug' ) ) : '',
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
	if ( $req->get_param( 'hold' ) ) { // the workers' topic rule asks for a person (e.g. final rules of a campaign's draft)
		update_post_meta( $post_id, 'aw_hold', sanitize_text_field( (string) $req->get_param( 'hold' ) ) );
	}
	update_post_meta( $post_id, 'aw_source_name', sanitize_text_field( (string) $req->get_param( 'source_name' ) ) );
	update_post_meta( $post_id, 'aw_source_date', aw_sanitize_meta( (string) $req->get_param( 'source_date' ), 'aw_source_date' ) );
	$image = esc_url_raw( (string) $req->get_param( 'image_url' ) );
	if ( $image && preg_match( '#^https://#', $image ) ) {
		update_post_meta( $post_id, 'aw_source_image', $image ); // Linked, never downloaded.
	}

	$ignored = array();

	// Categories: only those the item's section uses (Settings → Sourcing → Sections).
	$cats    = array_values( array_unique( array_map( 'sanitize_title', array_filter( (array) $req->get_param( 'categories' ), 'is_scalar' ) ) ) );
	$allowed = function_exists( 'aw_section_categories' ) ? aw_section_categories( $type ) : $cats;
	foreach ( array_diff( $cats, $allowed ) as $bad ) {
		$ignored[] = 'category: ' . $bad;
	}
	if ( array_intersect( $cats, $allowed ) ) {
		aw_assign_existing_terms( $post_id, array_values( array_intersect( $cats, $allowed ) ), 'category', $ignored );
	}
	$tags = array_filter( (array) $req->get_param( 'tags' ), 'is_scalar' );
	if ( $req->get_param( 'source_type' ) && 'aw_legal' === $type ) {
		if ( ! aw_set_issuer( $post_id, (string) $req->get_param( 'source_type' ) ) ) { // issuer: a label (issuers.php)
			$ignored[] = 'issuer: ' . sanitize_text_field( (string) $req->get_param( 'source_type' ) );
		}
	}
	// Library documents: what it is (judgment, order, notification...) and, for judgments, the court.
	if ( 'aw_legal' === $type ) {
		// The Library type is a tag; with none from the worker, a government press release (PIB) is an Official release.
		$kind = ( array_intersect( array_map( 'sanitize_title', $tags ), array_keys( AW_LEGAL_KINDS ) ) ? '' : ( 'pib' === $req->get_param( 'source_type' ) ? 'official-release' : '' ) );
		foreach ( array_merge( $kind ? array( $kind ) : array(), array_intersect( array_map( 'sanitize_title', $tags ), array_keys( AW_LEGAL_KINDS ) ) ) as $k ) {
			if ( ! term_exists( $k, 'post_tag' ) ) {
				wp_insert_term( AW_LEGAL_KINDS[ $k ] ?? $k, 'post_tag', array( 'slug' => $k ) );
			}
		}
		if ( $kind ) {
			$tags[] = $kind;
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
		// Employment type only as the source states it ("Type: Full time"); nothing is guessed (meta.php keeps Google's values).
		$emp = aw_sanitize_meta( (string) $req->get_param( 'employment_type' ), 'aw_employment_type' );
		if ( $emp ) {
			update_post_meta( $post_id, 'aw_employment_type', $emp );
		}
		// Place as published ("Pune/Hyderabad", "Bhopal, Madhya Pradesh"): every part that names an LGD state or district.
		$places = aw_places_from_text( (string) $req->get_param( 'place' ) );
		if ( $places ) {
			aw_set_item_places( $post_id, $places );
		}
	}

	if ( 'aw_listing' === $type ) {
		$l = (array) $req->get_param( 'listing' );
		foreach ( array( 'email', 'website', 'area', 'services' ) as $f ) {
			if ( isset( $l[ $f ] ) ) {
				update_post_meta( $post_id, 'aw_' . $f, aw_sanitize_meta( $l[ $f ], 'aw_' . $f ) );
			}
		}
		// Phone and WhatsApp go into the Phone numbers list (one row, marked WhatsApp, when they are the same number).
		aw_listing_put_phone( $post_id, (string) ( $l['phone'] ?? '' ), (string) ( $l['whatsapp'] ?? '' ) );
		update_post_meta( $post_id, 'aw_verified', false ); // Imports are never verified.
		update_post_meta( $post_id, 'aw_featured', 0 );
	}

	if ( 'aw_legal' === $type && function_exists( 'aw_mirror_gov_pdf' ) ) {
		$doc_url = esc_url_raw( (string) $req->get_param( 'document_url' ) ); // the PDF, when the source page is not the PDF itself
		aw_mirror_gov_pdf( $post_id, $doc_url ? $doc_url : $source_url );
	}

	// Routine items go live when every check passes; the rest wait with the reason (auto-review.php).
	if ( 'research' === $req->get_param( 'type' ) ) {
		$tags[] = 'research';
	}
	// Tags: only those the item's section uses (Settings → Sourcing → Sections); unknown ones are ignored.
	$allowed = function_exists( 'aw_section_tags' ) ? aw_section_tags( $type ) : array();
	$tags    = array_values( array_unique( array_map( 'sanitize_title', array_filter( $tags, 'is_scalar' ) ) ) );
	// A campaign is not a tag (owner, 20 Oct 2026): campaign-* slugs file the item under its campaign (campaign-links.php).
	$camp = array_values( array_filter( $tags, fn( $t ) => 0 === strpos( $t, 'campaign-' ) ) );
	if ( $camp && function_exists( 'aw_set_campaign_links' ) ) {
		aw_set_campaign_links( $post_id, $camp );
	}
	$tags = array_values( array_diff( $tags, $camp ) );
	foreach ( array_diff( $tags, $allowed ) as $bad ) {
		$ignored[] = 'tag: ' . $bad;
	}
	if ( array_intersect( $tags, $allowed ) ) {
		aw_assign_existing_terms( $post_id, array_values( array_intersect( $tags, $allowed ) ), 'post_tag', $ignored );
	}
	// A summary below its section's minimum after the workers' fallback (source re-read, other coverage merged) is
	// published like any other (owner, 15 Oct 2026); it is marked so a later rewrite pass can find it.
	if ( $req->get_param( 'summary_short' ) ) {
		update_post_meta( $post_id, 'aw_summary_short', current_time( 'Y-m-d' ) );
	}
	// A research paper's credit (research-citation.php): authors and journal from the worker, else from doi.org later.
	if ( 'research' === $req->get_param( 'type' ) && function_exists( 'aw_research_save_citation' ) ) {
		aw_research_save_citation( $post_id, (array) $req->get_param( 'authors' ), (string) ( $req->get_param( 'journal' ) ?: $req->get_param( 'source_name' ) ) );
		aw_research_save_study_title( $post_id, (string) $req->get_param( 'study_title' ) ); // the paper's own title; the post title is the headline
		if ( ! get_post_meta( $post_id, 'aw_authors', true ) || '' === aw_research_study_title( $post_id ) ) {
			wp_schedule_single_event( time() + 60, 'aw_research_citation', array( $post_id ) );
		}
		// The headline's SEO title and focus keyphrase (research_titles.py), stored as written so Rank Math shows them.
		$seo = sanitize_text_field( (string) $req->get_param( 'seo_title' ) );
		$kw  = sanitize_text_field( (string) $req->get_param( 'focus_keyword' ) );
		if ( '' !== $seo ) {
			update_post_meta( $post_id, 'rank_math_title', $seo );
		}
		if ( '' !== $kw ) {
			update_post_meta( $post_id, 'rank_math_focus_keyword', $kw );
		}
	}
	if ( 'aw_legal' === $type && '' !== $number ) {
		update_post_meta( $post_id, 'aw_legal_number', $number );
	}
	if ( 'aw_legal' === $type && $req->get_param( 'draft' ) ) { // a draft published for objections, not rules in force
		$t = get_term_by( 'slug', 'draft', 'post_tag' );
		$t = $t ? (int) $t->term_id : (int) ( wp_insert_term( 'Draft', 'post_tag', array( 'slug' => 'draft' ) )['term_id'] ?? 0 );
		if ( $t ) {
			wp_set_object_terms( $post_id, $t, 'post_tag', true );
		}
		update_post_meta( $post_id, 'aw_legal_force', 'NotInForce' );
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
