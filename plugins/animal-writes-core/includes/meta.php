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
		'aw_instagram'       => array( 'string', array( 'aw_listing' ), 'Instagram' ),
		'aw_facebook'        => array( 'string', array( 'aw_listing' ), 'Facebook' ),
		'aw_ngobase_url'     => array( 'string', array( 'aw_listing' ), 'ngobase.org profile' ),
		'aw_x'               => array( 'string', array( 'aw_listing' ), 'X (Twitter)' ),
		'aw_youtube'         => array( 'string', array( 'aw_listing' ), 'YouTube' ),
		'aw_gallery'         => array( 'string', array( 'aw_listing' ), 'Photos: up to 6 media IDs, comma separated' ),
		'aw_founded'         => array( 'string', array( 'aw_listing' ), 'Year established' ),
		'aw_head'            => array( 'string', array( 'aw_listing' ), 'Head of the organisation (name and role, as the organisation publishes it)' ),
		'aw_registration'    => array( 'string', array( 'aw_listing' ), 'Registration (Trust/Society number)' ),
		'aw_reg_12a'         => array( 'string', array( 'aw_listing' ), '12A registration number' ),
		'aw_reg_80g'         => array( 'string', array( 'aw_listing' ), '80G registration number' ),
		'aw_darpan_id'       => array( 'string', array( 'aw_listing' ), 'NGO Darpan ID' ),
		'aw_animals'         => array( 'string', array( 'aw_listing' ), 'Animals helped (comma separated)' ),
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
		'aw_employment_type' => array( 'string', array( 'aw_opportunity' ), 'Employment type as the source states it: FULL_TIME, PART_TIME, CONTRACTOR, TEMPORARY, PER_DIEM, INTERN, VOLUNTEER or OTHER (empty when not stated)' ),

		// Campaigns (read by the Event schema in aw-seo-extras).
		'aw_event_date'      => array( 'string', array( 'aw_campaign', 'aw_opportunity' ), 'Event date (Y-m-d)' ),
		'aw_event_schedule'  => array( 'string', array( 'aw_opportunity' ), 'Repeats, as the source states it: "second Saturday monthly 14:00-16:00" (read by the Event schema)' ),
		'aw_event_place'     => array( 'string', array( 'aw_campaign' ), 'Event place' ),
		'aw_campaign_close'  => array( 'string', array( 'aw_campaign' ), 'Closes on (Y-m-d)' ),
		'aw_campaign_doc'    => array( 'string', array( 'aw_campaign' ), 'Campaign document (Library item ID)' ),
		'aw_campaign_partners' => array( 'string', array( 'aw_campaign' ), 'Partners (Directory listing IDs, comma-separated; only the owner\'s list)' ),
		'aw_campaign_open'   => array( 'string', array( 'aw_campaign' ), 'Opens on (Y-m-d, IST)' ),
		'aw_campaign_outcome' => array( 'string', array( 'aw_campaign' ), 'Outcome after closing: pending, achieved, partial, not, inconclusive' ),
		'aw_campaign_outcome_asof' => array( 'string', array( 'aw_campaign' ), 'Outcome as of (Y-m-d)' ),
		'aw_campaign_state_override' => array( 'string', array( 'aw_campaign' ), 'State override: pre, active or post (needs a reason)' ),
		'aw_campaign_override_note' => array( 'string', array( 'aw_campaign' ), 'Reason for the state override' ),
		'aw_campaign_metric_min' => array( 'string', array( 'aw_campaign' ), 'Show the count from this number' ),
		'aw_campaign_metric_stale' => array( 'string', array( 'aw_campaign' ), 'Days without a new letter before the count shows an as-of date' ),

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
	$date_keys = array( 'aw_source_date', 'aw_verified_on', 'aw_featured_until', 'aw_deadline', 'aw_review_until', 'aw_awbi_recognised', 'aw_awbi_renewed', 'aw_awbi_valid', 'aw_services_on', 'aw_event_date', 'aw_campaign_close', 'aw_campaign_open', 'aw_campaign_outcome_asof', 'kits_last_reviewed' );

	if ( in_array( $key, $url_keys, true ) ) {
		return esc_url_raw( (string) $value );
	}
	if ( in_array( $key, $date_keys, true ) ) {
		$value = (string) $value;
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
	if ( 'aw_gallery' === $key ) { // up to six media IDs
		return implode( ',', array_slice( array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ), 0, 6 ) );
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
	if ( 'aw_email' === $key ) {
		return sanitize_email( (string) $value );
	}
	if ( 'aw_employment_type' === $key ) { // Google's JobPosting values only
		$value = strtoupper( str_replace( array( '-', ' ' ), '_', trim( (string) $value ) ) );
		$value = preg_replace( '/^(FULL|PART)_?TIME$/', '$1_TIME', $value ); // "Fulltime", "Full time"
		$value = array( 'CONTRACT' => 'CONTRACTOR', 'INTERNSHIP' => 'INTERN', 'TEMP' => 'TEMPORARY' )[ $value ] ?? $value;
		return in_array( $value, array( 'FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'PER_DIEM', 'INTERN', 'VOLUNTEER', 'OTHER' ), true ) ? $value : '';
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

/*
 * Phone numbers and opening hours: the only source is the two lists (SCF repeaters aw_phones and aw_opening_hours),
 * stored the way SCF stores a repeater: the row count under the key, each sub-field under "{key}_{row}_{name}", and the
 * field-key reference rows SCF writes. Read and written here straight from the meta, so the page, the schema, imports
 * and claims work with or without SCF. (The former single fields aw_phone, aw_whatsapp, aw_hours and aw_hours_spec were
 * copied into these lists on 16 Oct 2026; their stored values are left in the database and nothing reads them.)
 */

/** The days in order, Monday first. */
const AW_WEEKDAYS = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );

/** Sub-fields of the two lists: name => SCF field key. */
const AW_LIST_FIELDS = array(
	'aw_phones'        => array( 'number' => 'field_aw_listing_phones_number', 'label' => 'field_aw_listing_phones_label', 'whatsapp' => 'field_aw_listing_phones_whatsapp' ),
	'aw_opening_hours' => array( 'day' => 'field_aw_listing_opening_hours_day', 'open' => 'field_aw_listing_opening_hours_open', 'close' => 'field_aw_listing_opening_hours_close', 'closed' => 'field_aw_listing_opening_hours_closed' ),
);

/**
 * A listing's phone numbers: [ [ number, label, whatsapp (bool) ], … ] from aw_phones.
 *
 * @param int $id Listing ID.
 * @return array
 */
function aw_listing_phones( $id ) {
	$rows = array();
	$n    = (int) get_post_meta( $id, 'aw_phones', true );
	for ( $i = 0; $i < $n; $i++ ) {
		$num = trim( (string) get_post_meta( $id, "aw_phones_{$i}_number", true ) );
		if ( '' !== $num ) {
			$rows[] = array(
				'number'   => $num,
				'label'    => trim( (string) get_post_meta( $id, "aw_phones_{$i}_label", true ) ),
				'whatsapp' => (bool) get_post_meta( $id, "aw_phones_{$i}_whatsapp", true ),
			);
		}
	}
	return $rows;
}

/**
 * A listing's opening hours by day: [ [ day, open (H:i), close (H:i), closed (bool) ], … ] from aw_opening_hours, in
 * weekday order.
 *
 * @param int $id Listing ID.
 * @return array
 */
function aw_listing_hours( $id ) {
	$rows = array();
	$n    = (int) get_post_meta( $id, 'aw_opening_hours', true );
	$hm   = fn( $t ) => preg_match( '/^(\d{1,2}):(\d{2})/', trim( (string) $t ), $x ) ? sprintf( '%02d:%s', $x[1], $x[2] ) : '';
	for ( $i = 0; $i < $n; $i++ ) {
		$day = (string) get_post_meta( $id, "aw_opening_hours_{$i}_day", true );
		if ( ! in_array( $day, AW_WEEKDAYS, true ) ) {
			continue;
		}
		$closed = (bool) get_post_meta( $id, "aw_opening_hours_{$i}_closed", true );
		$open   = $hm( get_post_meta( $id, "aw_opening_hours_{$i}_open", true ) );
		$close  = $hm( get_post_meta( $id, "aw_opening_hours_{$i}_close", true ) );
		if ( $closed || ( $open && $close ) ) {
			$rows[] = array( 'day' => $day, 'open' => $closed ? '' : $open, 'close' => $closed ? '' : $close, 'closed' => $closed );
		}
	}
	usort( $rows, fn( $a, $b ) => array( array_search( $a['day'], AW_WEEKDAYS, true ), $a['open'] ) <=> array( array_search( $b['day'], AW_WEEKDAYS, true ), $b['open'] ) );
	return $rows;
}

/**
 * Write a whole list in SCF's repeater format (count, rows, reference rows); rows past the new count are removed, as SCF
 * does when rows are taken out.
 *
 * @param int    $id   Listing ID.
 * @param string $key  aw_phones or aw_opening_hours.
 * @param array  $rows Rows keyed by sub-field name (number/label/whatsapp, or day/open/close/closed).
 */
function aw_set_listing_list( $id, $key, array $rows ) {
	$subs = AW_LIST_FIELDS[ $key ];
	$old  = (int) get_post_meta( $id, $key, true );
	$rows = array_values( $rows );
	foreach ( $rows as $i => $r ) {
		foreach ( $subs as $name => $fkey ) {
			$v = $r[ $name ] ?? '';
			if ( in_array( $name, array( 'whatsapp', 'closed' ), true ) ) {
				$v = $v ? 1 : 0;
			} elseif ( in_array( $name, array( 'open', 'close' ), true ) ) {
				$v = preg_match( '/^\d{2}:\d{2}$/', (string) $v ) ? $v . ':00' : (string) $v;
			}
			update_post_meta( $id, "{$key}_{$i}_{$name}", is_int( $v ) ? $v : sanitize_text_field( (string) $v ) );
			update_post_meta( $id, "_{$key}_{$i}_{$name}", $fkey );
		}
	}
	for ( $i = count( $rows ); $i < $old; $i++ ) {
		foreach ( array_keys( $subs ) as $name ) {
			delete_post_meta( $id, "{$key}_{$i}_{$name}" );
			delete_post_meta( $id, "_{$key}_{$i}_{$name}" );
		}
	}
	update_post_meta( $id, $key, count( $rows ) );
	update_post_meta( $id, '_' . $key, 'aw_phones' === $key ? 'field_aw_listing_phones' : 'field_aw_listing_opening_hours' );
}

/**
 * Put a phone and/or WhatsApp number into a listing's phone list (imports, the ingest endpoint, approved claims): the
 * phone replaces the first number (keeping its label and WhatsApp mark) or is added; the WhatsApp number marks the row
 * with the same number, else replaces the first number marked WhatsApp, else is added as a WhatsApp row. Other rows stay.
 *
 * @param int    $id       Listing ID.
 * @param string $phone    Phone ('' to leave as is).
 * @param string $whatsapp WhatsApp number ('' to leave as is).
 */
function aw_listing_put_phone( $id, $phone = '', $whatsapp = '' ) {
	$phone    = trim( sanitize_text_field( (string) $phone ) );
	$whatsapp = trim( sanitize_text_field( (string) $whatsapp ) );
	if ( '' === $phone && '' === $whatsapp ) {
		return;
	}
	$rows = aw_listing_phones( $id );
	$norm = fn( $n ) => function_exists( 'aw_normalise_phone' ) ? aw_normalise_phone( $n ) : preg_replace( '/\D+/', '', $n );
	if ( '' !== $phone ) {
		if ( $rows ) {
			$rows[0]['number'] = $phone;
		} else {
			$rows[] = array( 'number' => $phone, 'label' => '', 'whatsapp' => false );
		}
	}
	if ( '' !== $whatsapp ) {
		$done = false;
		foreach ( $rows as $i => $r ) {
			if ( $norm( $r['number'] ) === $norm( $whatsapp ) ) {
				$rows[ $i ]['whatsapp'] = $done = true;
				break;
			}
		}
		if ( ! $done ) {
			foreach ( $rows as $i => $r ) {
				if ( $r['whatsapp'] && ( 0 !== $i || '' === $phone ) ) {
					$rows[ $i ]['number'] = $whatsapp;
					$done                 = true;
					break;
				}
			}
		}
		if ( ! $done ) {
			$rows[] = array( 'number' => $whatsapp, 'label' => '', 'whatsapp' => true );
		}
	}
	aw_set_listing_list( $id, 'aw_phones', $rows );
}

/** The first number of a listing (Call), or the first marked WhatsApp. */
function aw_listing_first_phone( $id, $whatsapp = false ) {
	foreach ( aw_listing_phones( $id ) as $p ) {
		if ( ! $whatsapp || $p['whatsapp'] ) {
			return $p['number'];
		}
	}
	return '';
}

/**
 * Opening hours by day as schema.org OpeningHoursSpecification: days with the same hours in one node; a closed day as
 * opens and closes 00:00 (Google's form for "closed all day").
 *
 * @param array $rows aw_listing_hours().
 * @return array
 */
function aw_hours_rows_spec( $rows ) {
	$groups = array();
	foreach ( $rows as $r ) {
		$k = $r['closed'] ? '00:00-00:00' : $r['open'] . '-' . $r['close'];
		$groups[ $k ][] = $r['day'];
	}
	$out = array();
	foreach ( $groups as $k => $days ) {
		list( $open, $close ) = explode( '-', $k );
		$out[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => array_values( array_unique( $days ) ), 'opens' => $open, 'closes' => $close );
	}
	return $out;
}
