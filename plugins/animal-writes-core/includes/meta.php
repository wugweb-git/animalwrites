<?php
/**
 * Post meta. Registered natively (no ACF) and exposed to the block editor + REST.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta schema: key => [ type, post types, description ].
 *
 * @return array
 */
function aw_meta_schema() {
	$cards = array( 'aw_news', 'aw_legal', 'aw_listing', 'aw_opportunity' ); // listings and opportunities: where a record came from
	$all   = array_keys( aw_post_types() );

	return array(
		// Thin cards: summary lives in the excerpt; this is the attribution.
		'aw_source_url'      => array( 'string', $cards, 'Original source URL' ),
		'aw_source_name'     => array( 'string', $cards, 'Original source name' ),
		'aw_source_date'     => array( 'string', $cards, 'Source publication date (Y-m-d)' ),

		// Monetisation guardrails.
		'aw_sponsored'       => array( 'boolean', $all, 'Sponsored content' ),
		'aw_sponsor_name'    => array( 'string', $all, 'Sponsor name' ),

		// Directory.
		'aw_verified'        => array( 'boolean', array( 'aw_listing' ), 'Verified by Animal Writes' ),
		'aw_verified_on'     => array( 'string', array( 'aw_listing' ), 'Last verified date (Y-m-d)' ),
		'aw_awbi_recognised' => array( 'string', array( 'aw_listing' ), 'AWBI recognised on (Y-m-d)' ),
		'aw_awbi_renewed'    => array( 'string', array( 'aw_listing' ), 'AWBI renewed on (Y-m-d)' ),
		'aw_awbi_valid'      => array( 'string', array( 'aw_listing' ), 'AWBI recognition valid until (Y-m-d)' ),
		'aw_awbi_code'       => array( 'string', array( 'aw_listing' ), 'AWBI recognition code (from the AWBI list; read only)' ),
		'aw_needs_reverify'  => array( 'boolean', array( 'aw_listing' ), 'Flagged for re-verification' ),
		'aw_featured'        => array( 'integer', array( 'aw_listing' ), 'Featured (1) or not (0)' ),
		'aw_featured_until'  => array( 'string', array( 'aw_listing' ), 'Featured until (Y-m-d)' ),
		'aw_phone'           => array( 'string', array( 'aw_listing' ), 'Phone' ),
		'aw_whatsapp'        => array( 'string', array( 'aw_listing' ), 'WhatsApp' ),
		'aw_email'           => array( 'string', array( 'aw_listing' ), 'Email' ),
		'aw_website'         => array( 'string', array( 'aw_listing', 'aw_opportunity' ), 'Website' ),
		'aw_area'            => array( 'string', array( 'aw_listing' ), 'Area / locality (no full address)' ),
		'aw_services'        => array( 'string', array( 'aw_listing' ), 'Services offered, as stated by the organisation or a named list (comma separated)' ),
		'aw_services_source' => array( 'string', array( 'aw_listing' ), 'Who stated the services (e.g. "the organisation", or the list name)' ),
		'aw_services_on'     => array( 'string', array( 'aw_listing' ), 'Date the services were stated (YYYY-MM-DD)' ),
		'aw_maps_url'        => array( 'string', array( 'aw_listing' ), 'Google Maps link' ),
		'aw_lat'             => array( 'string', array( 'aw_listing' ), 'Latitude (from Google Maps)' ),
		'aw_lng'             => array( 'string', array( 'aw_listing' ), 'Longitude (from Google Maps)' ),
		'aw_place_id'        => array( 'string', array( 'aw_listing' ), 'Google place ID' ),
		'aw_g_rating'        => array( 'string', array( 'aw_listing' ), 'Google rating (out of 5)' ),
		'aw_g_reviews'       => array( 'string', array( 'aw_listing' ), 'Number of Google reviews' ),
		'aw_g_category'      => array( 'string', array( 'aw_listing' ), 'Google category (e.g. Animal shelter)' ),
		'aw_g_profile'       => array( 'string', array( 'aw_listing' ), 'Google Business Profile link' ),
		'aw_hours_spec'      => array( 'string', array( 'aw_listing' ), 'Opening hours, one line per day range: "Mon-Fri 09:00-18:00"' ),
		'aw_instagram'       => array( 'string', array( 'aw_listing' ), 'Instagram' ),
		'aw_facebook'        => array( 'string', array( 'aw_listing' ), 'Facebook' ),
		'aw_ngobase_url'     => array( 'string', array( 'aw_listing' ), 'ngobase.org profile' ),
		'aw_x'               => array( 'string', array( 'aw_listing' ), 'X (Twitter)' ),
		'aw_youtube'         => array( 'string', array( 'aw_listing' ), 'YouTube' ),
		'aw_gallery'         => array( 'string', array( 'aw_listing' ), 'Photos: up to 4 media IDs, comma separated' ),
		'aw_founded'         => array( 'string', array( 'aw_listing' ), 'Year established' ),
		'aw_head'            => array( 'string', array( 'aw_listing' ), 'Head of the organisation (name and role, as the organisation publishes it)' ),
		'aw_registration'    => array( 'string', array( 'aw_listing' ), 'Registration (Trust/Society number)' ),
		'aw_reg_12a'         => array( 'string', array( 'aw_listing' ), '12A registration number' ),
		'aw_reg_80g'         => array( 'string', array( 'aw_listing' ), '80G registration number' ),
		'aw_darpan_id'       => array( 'string', array( 'aw_listing' ), 'NGO Darpan ID' ),
		'aw_animals'         => array( 'string', array( 'aw_listing' ), 'Animals helped (comma separated)' ),
		'aw_hours'           => array( 'string', array( 'aw_listing' ), 'Opening hours' ),
		'aw_languages'       => array( 'string', array( 'aw_listing' ), 'Languages spoken (comma separated)' ),
		'aw_donate_url'      => array( 'string', array( 'aw_listing' ), 'Donate link' ),
		'aw_volunteer_url'   => array( 'string', array( 'aw_listing' ), 'Volunteer link' ),
		'aw_rel_nofollow'    => array( 'string', array( 'aw_listing' ), 'Links marked nofollow (1)' ),

		// Opportunities.
		'aw_org_name'        => array( 'string', array( 'aw_opportunity' ), 'Organisation' ),
		'aw_apply_url'       => array( 'string', array( 'aw_opportunity' ), 'Apply URL' ),
		'aw_deadline'        => array( 'string', array( 'aw_opportunity' ), 'Deadline (Y-m-d)' ),
		'aw_review_until'    => array( 'string', array( 'aw_opportunity' ), 'Review date (Y-m-d): with no deadline, the listing closes after this date unless renewed' ),
		'aw_paid_listing'    => array( 'boolean', array( 'aw_opportunity' ), 'Paid listing' ),
		'aw_stipend'         => array( 'string', array( 'aw_opportunity' ), 'Stipend or pay, as stated (Phase 2; not shown yet)' ),

		// Campaigns (read by the Event schema in aw-seo-extras).
		'aw_event_date'      => array( 'string', array( 'aw_campaign' ), 'Event date (Y-m-d)' ),
		'aw_event_place'     => array( 'string', array( 'aw_campaign' ), 'Event place' ),
		'aw_campaign_close'  => array( 'string', array( 'aw_campaign' ), 'Closes on (Y-m-d)' ),
		'aw_campaign_doc'    => array( 'string', array( 'aw_campaign' ), 'Campaign document (Library item ID)' ),

		// Resources: the date a guide was last checked (schema lastReviewed).
		'kits_last_reviewed' => array( 'string', array( 'post' ), 'Last reviewed (Y-m-d)' ),

		// Resources.
		'aw_file_url'        => array( 'string', array( 'aw_legal' ), 'Downloadable file URL' ),
		'aw_file_id'         => array( 'string', array( 'aw_legal' ), 'Document file (attachment ID)' ),
		'aw_requires_email'  => array( 'boolean', array( 'aw_legal' ), 'Gate download behind newsletter signup' ),
		'aw_legal_number'    => array( 'string', array( 'aw_legal' ), 'Official number of the text (e.g. G.S.R. 864(E))' ),
		'aw_legal_force'     => array( 'string', array( 'aw_legal' ), 'In force: InForce, NotInForce or PartiallyInForce' ),
	);
}

/**
 * Sanitize a meta value by key.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed
 */
function aw_sanitize_meta( $value, $key ) {
	$url_keys  = array( 'aw_source_url', 'aw_website', 'aw_apply_url', 'aw_file_url', 'aw_maps_url', 'aw_g_profile', 'aw_instagram', 'aw_facebook', 'aw_x', 'aw_youtube', 'aw_donate_url', 'aw_volunteer_url', 'aw_ngobase_url' );
	$date_keys = array( 'aw_source_date', 'aw_verified_on', 'aw_featured_until', 'aw_deadline', 'aw_review_until', 'aw_awbi_recognised', 'aw_awbi_renewed', 'aw_awbi_valid', 'aw_services_on', 'aw_event_date', 'aw_campaign_close', 'kits_last_reviewed' );

	if ( in_array( $key, $url_keys, true ) ) {
		return esc_url_raw( (string) $value );
	}
	if ( in_array( $key, $date_keys, true ) ) {
		$value = (string) $value;
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
	if ( 'aw_gallery' === $key ) { // up to four media IDs
		return implode( ',', array_slice( array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ), 0, 4 ) );
	}
	if ( in_array( $key, array( 'aw_lat', 'aw_lng', 'aw_g_rating' ), true ) ) {
		return is_numeric( trim( (string) $value ) ) ? trim( (string) $value ) : '';
	}
	if ( 'aw_g_reviews' === $key ) {
		return ctype_digit( trim( (string) $value ) ) ? trim( (string) $value ) : '';
	}
	if ( 'aw_founded' === $key ) {
		return preg_match( '/^(18|19|20)\d{2}$/', trim( (string) $value ) ) ? trim( (string) $value ) : '';
	}
	if ( 'aw_rel_nofollow' === $key ) {
		return $value ? '1' : '';
	}
	if ( 'aw_hours_spec' === $key ) { // keep line breaks
		return sanitize_textarea_field( (string) $value );
	}
	if ( 'aw_email' === $key ) {
		return sanitize_email( (string) $value );
	}
	return sanitize_text_field( (string) $value );
}

/**
 * Register meta.
 */
function aw_register_meta() {
	foreach ( aw_meta_schema() as $key => $def ) {
		list( $type, $post_types, $description ) = $def;
		foreach ( $post_types as $post_type ) {
			$args = array(
				'type'          => $type,
				'description'   => $description,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			);
			if ( 'string' === $type ) {
				$args['sanitize_callback'] = function ( $value ) use ( $key ) {
					return aw_sanitize_meta( $value, $key );
				};
			}
			if ( 'aw_featured' === $key ) {
				$args['default'] = 0;
			}
			register_post_meta( $post_type, $key, $args );
		}
	}
}
add_action( 'init', 'aw_register_meta' );

/**
 * Listings always carry aw_featured so directory ordering never drops a listing.
 *
 * @param int $post_id Post ID.
 */
function aw_ensure_featured_flag( $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! metadata_exists( 'post', $post_id, 'aw_featured' ) ) {
		update_post_meta( $post_id, 'aw_featured', 0 );
	}
}
add_action( 'save_post_aw_listing', 'aw_ensure_featured_flag' );

/**
 * Add aw_featured = 0 to any listing missing it (imports, manual DB edits).
 * Runs on activation, daily, and once per plugin version in admin.
 */
function aw_backfill_featured_flag() {
	$ids = get_posts(
		array(
			'post_type'      => 'aw_listing',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => 'aw_featured',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	foreach ( $ids as $id ) {
		update_post_meta( $id, 'aw_featured', 0 );
	}
}

add_action(
	'admin_init',
	function () {
		if ( get_option( 'aw_core_backfill_version' ) !== AW_CORE_VERSION ) {
			aw_backfill_featured_flag();
			update_option( 'aw_core_backfill_version', AW_CORE_VERSION );
		}
	}
);

/**
 * Exact opening hours ("Mon-Fri 09:00-18:00", one range per line) as schema.org OpeningHoursSpecification.
 *
 * @param string $text Lines.
 * @return array
 */
function aw_hours_spec( $text ) {
	$days = array( 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday' );
	$keys = array_keys( $days );
	$out  = array();
	foreach ( preg_split( '/[\r\n]+/', (string) $text ) as $line ) {
		if ( ! preg_match( '/^\s*([a-z]{3})[a-z]*(?:\s*[-–]\s*([a-z]{3})[a-z]*)?\s+(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/i', $line, $mm ) ) {
			continue;
		}
		$from = array_search( strtolower( $mm[1] ), $keys, true );
		$to   = isset( $mm[2] ) && '' !== $mm[2] ? array_search( strtolower( $mm[2] ), $keys, true ) : $from;
		if ( false === $from || false === $to ) {
			continue;
		}
		$list = array();
		for ( $i = $from; ; $i = ( $i + 1 ) % 7 ) {
			$list[] = $days[ $keys[ $i ] ];
			if ( $i === $to ) {
				break;
			}
		}
		$out[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $list, 'opens' => $mm[3], 'closes' => $mm[4] );
	}
	return $out;
}
