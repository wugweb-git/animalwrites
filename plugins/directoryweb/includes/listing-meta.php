<?php
/**
 * The listing's data model: its meta keys and their rules, the phone and opening-hours lists, and the never-publish
 * list. Moved from Animal Writes Core (meta.php) on 18 Nov 2026 with the keys, types and rules unchanged; Core keeps
 * its old function names as wrappers that call these.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The listing post type. Fixed on this site; a filter comes once every DirectoryWeb file reads this (scope §8.5).
 *
 * @return string
 */
function directoryweb_post_type(): string {
	return 'aw_listing';
}

/**
 * The prefix of the listing meta keys. Fixed on this site (the stored keys never change); a filter comes once every
 * DirectoryWeb file reads this (scope §8.5).
 *
 * @return string
 */
function directoryweb_meta_prefix(): string {
	return 'aw_';
}

/**
 * The listing meta: key => [ type, post types, description ], as Core's aw_meta_schema() gives it.
 *
 * @return array
 */
function directoryweb_meta_schema(): array {
	static $schema = null;
	if ( null !== $schema ) {
		return $schema;
	}
	$fields = array(
		'verified'              => array( 'boolean', 'Verified by Animal Writes' ),
		'verified_on'           => array( 'string', 'Last verified date (Y-m-d)' ),
		'awbi_recognised'       => array( 'string', 'AWBI recognised on (Y-m-d)' ),
		'awbi_renewed'          => array( 'string', 'AWBI renewed on (Y-m-d)' ),
		'awbi_valid'            => array( 'string', 'AWBI recognition valid until (Y-m-d)' ),
		'awbi_code'             => array( 'string', 'AWBI recognition code (from the AWBI list; read only)' ),
		'awbi_dropped'          => array( 'string', 'Date of the current AWBI list the code is missing from (Y-m-d): recognition may have lapsed' ),
		'awbi_list_on'          => array( 'string', 'Date of the AWBI list the code comes from (Y-m-d; empty = 31 May 2026 list)' ),
		'needs_reverify'        => array( 'boolean', 'Flagged for re-verification' ),
		'gov_verified_on'       => array( 'string', 'Verified: date of the government document (Y-m-d; set by code, gov-verified.php)' ),
		'gov_verified_by'       => array( 'string', 'Verified: the government sources (set by code)' ),
		'email'                 => array( 'string', 'Email' ),
		'website'               => array( 'string', 'Website' ),
		'area'                  => array( 'string', 'Area / locality (no full address)' ),
		'services'              => array( 'string', 'Services offered, as stated by the organisation or a named list (comma separated)' ),
		'services_source'       => array( 'string', 'Who stated the services (e.g. "the organisation", or the list name)' ),
		'services_on'           => array( 'string', 'Date the services were stated (YYYY-MM-DD)' ),
		'maps_url'              => array( 'string', 'Google Maps link' ),
		'lat'                   => array( 'string', 'Latitude (from Google Maps)' ),
		'lng'                   => array( 'string', 'Longitude (from Google Maps)' ),
		'place_id'              => array( 'string', 'Google place ID' ),
		'g_rating'              => array( 'string', 'Google rating (out of 5)' ),
		'g_reviews'             => array( 'string', 'Number of Google reviews' ),
		'g_category'            => array( 'string', 'Google category (e.g. Animal shelter)' ),
		'g_profile'             => array( 'string', 'Google Business Profile link' ),
		'instagram'             => array( 'string', 'Instagram' ),
		'facebook'              => array( 'string', 'Facebook' ),
		'ngobase_url'           => array( 'string', 'ngobase.org profile' ),
		'x'                     => array( 'string', 'X (Twitter)' ),
		'youtube'               => array( 'string', 'YouTube' ),
		'gallery'               => array( 'string', 'Photos: up to 6 media IDs, comma separated' ),
		'founded'               => array( 'string', 'Year established' ),
		'head'                  => array( 'string', 'Head of the organisation (name and role, as the organisation publishes it)' ),
		'registration'          => array( 'string', 'Registration (Trust/Society number)' ),
		'reg_12a'               => array( 'string', '12A registration number' ),
		'reg_80g'               => array( 'string', '80G registration number' ),
		'darpan_id'             => array( 'string', 'NGO Darpan ID' ),
		'darpan_blacklisted_by' => array( 'string', 'Blacklisted on NGO Darpan by (as Darpan lists it)' ),
		'darpan_blacklisted_on' => array( 'string', 'Blacklisted on NGO Darpan from (Y-m-d)' ),
		'reg_authority'         => array( 'string', 'Registration authority (from NGO Darpan; admin only)' ),
		'reg_act'               => array( 'string', 'Registered under (Act; from NGO Darpan; admin only)' ),
		'reg_date'              => array( 'string', 'Registration date (Y-m-d; from NGO Darpan; admin only)' ),
		'sectors'               => array( 'string', 'Sectors of work as NGO Darpan lists them (comma separated; admin only)' ),
		'animals'               => array( 'string', 'Animals helped (comma separated)' ),
		'languages'             => array( 'string', 'Languages spoken (comma separated)' ),
		'donate_url'            => array( 'string', 'Donate link' ),
		'volunteer_url'         => array( 'string', 'Volunteer link' ),
		'rel_nofollow'          => array( 'string', 'Links marked nofollow (1)' ),
	);
	$schema = array();
	foreach ( $fields as $name => $def ) {
		$schema[ directoryweb_meta_prefix() . $name ] = array( $def[0], array( directoryweb_post_type() ), $def[1] );
	}
	return $schema;
}

/**
 * Sanitize a listing meta value by key (the rules Core's aw_sanitize_meta() had for these keys).
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed
 */
function directoryweb_sanitize_meta( $value, $key ) {
	$p    = directoryweb_meta_prefix();
	$name = 0 === strpos( (string) $key, $p ) ? substr( (string) $key, strlen( $p ) ) : '';

	if ( in_array( $name, array( 'website', 'maps_url', 'g_profile', 'instagram', 'facebook', 'x', 'youtube', 'donate_url', 'volunteer_url', 'ngobase_url' ), true ) ) {
		return esc_url_raw( (string) $value );
	}
	if ( in_array( $name, array( 'verified_on', 'awbi_recognised', 'awbi_renewed', 'awbi_valid', 'services_on' ), true ) ) {
		$value = (string) $value;
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
	if ( 'gallery' === $name ) { // up to six media IDs
		return implode( ',', array_slice( array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ), 0, 6 ) );
	}
	if ( in_array( $name, array( 'lat', 'lng', 'g_rating' ), true ) ) {
		return is_numeric( trim( (string) $value ) ) ? trim( (string) $value ) : '';
	}
	if ( 'g_reviews' === $name ) {
		return ctype_digit( trim( (string) $value ) ) ? trim( (string) $value ) : '';
	}
	if ( 'founded' === $name ) {
		return preg_match( '/^(18|19|20)\d{2}$/', trim( (string) $value ) ) ? trim( (string) $value ) : '';
	}
	if ( 'rel_nofollow' === $name ) {
		return $value ? '1' : '';
	}
	if ( 'email' === $name ) {
		return sanitize_email( (string) $value );
	}
	return sanitize_text_field( (string) $value );
}

/** Register the listing meta (REST and the block editor), with the same arguments Core registered them with. */
function directoryweb_register_meta(): void {
	foreach ( directoryweb_meta_schema() as $key => $def ) {
		list( $type, $post_types, $description ) = $def;
		$args                                    = array(
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
				return directoryweb_sanitize_meta( $value, $key );
			};
		}
		foreach ( $post_types as $post_type ) {
			register_post_meta( $post_type, $key, $args );
		}
	}
}
add_action( 'init', 'directoryweb_register_meta' );

/*
 * Phone numbers and opening hours: the only source is the two lists (SCF repeaters aw_phones and aw_opening_hours),
 * stored the way SCF stores a repeater: the row count under the key, each sub-field under "{key}_{row}_{name}", and the
 * field-key reference rows SCF writes. Read and written here straight from the meta, so the page, the schema, imports
 * and claims work with or without SCF. (The former single fields aw_phone, aw_whatsapp, aw_hours and aw_hours_spec were
 * copied into these lists on 16 Oct 2026; their stored values are left in the database and nothing reads them.)
 */

/** The days in order, Monday first. */
const DIRECTORYWEB_WEEKDAYS = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );

/** Sub-fields of the two lists: name => SCF field key (the keys of the SCF groups, unchanged). */
const DIRECTORYWEB_LIST_FIELDS = array(
	'aw_phones'        => array(
		'number'   => 'field_aw_listing_phones_number',
		'label'    => 'field_aw_listing_phones_label',
		'whatsapp' => 'field_aw_listing_phones_whatsapp',
	),
	'aw_opening_hours' => array(
		'day'    => 'field_aw_listing_opening_hours_day',
		'open'   => 'field_aw_listing_opening_hours_open',
		'close'  => 'field_aw_listing_opening_hours_close',
		'closed' => 'field_aw_listing_opening_hours_closed',
	),
);

/**
 * Never-publish list: numbers taken down on privacy grounds, as SHA-256 of the normalised digits (aw_normalise_phone,
 * e.g. 91XXXXXXXXXX) so the code holds no copy of the number. Checked on every phone write (imports, ingest, claims,
 * the listing profile, enrichment) and on display. Add a hash with: php -r 'echo hash("sha256","91XXXXXXXXXX");'
 */
const DIRECTORYWEB_NEVER_PUBLISH_PHONES = array(
	'6eafd3202ed0fb7bd82d280061d154ede4b1c5a97a7b2b76fbf1a46646631289', // Privacy takedown, owner 25 Oct 2026 (contact form entry #4).
);

/**
 * Whether a phone number is on the never-publish list, in any format (+91, spaces, dashes, leading 0).
 *
 * @param string $raw Phone number as typed.
 * @return bool
 */
function directoryweb_phone_never_publish( $raw ) {
	$n = directoryweb_normalise_phone( $raw );
	if ( 10 === strlen( $n ) ) {
		$n = '91' . $n;
	}
	// The older filter name keeps working inside the new one.
	$hashes = (array) apply_filters( 'directoryweb_never_publish_phones', (array) apply_filters( 'aw_never_publish_phones', DIRECTORYWEB_NEVER_PUBLISH_PHONES ) );
	return '' !== $n && in_array( hash( 'sha256', $n ), $hashes, true );
}

/**
 * A listing's phone numbers: [ [ number, label, whatsapp (bool) ], … ] from aw_phones.
 *
 * @param int $id Listing ID.
 * @return array
 */
function directoryweb_listing_phones( $id ) {
	$key  = directoryweb_meta_prefix() . 'phones';
	$rows = array();
	$n    = (int) get_post_meta( $id, $key, true );
	for ( $i = 0; $i < $n; $i++ ) {
		$num = trim( (string) get_post_meta( $id, "{$key}_{$i}_number", true ) );
		if ( '' !== $num && ! directoryweb_phone_never_publish( $num ) ) {
			$rows[] = array(
				'number'   => $num,
				'label'    => trim( (string) get_post_meta( $id, "{$key}_{$i}_label", true ) ),
				'whatsapp' => (bool) get_post_meta( $id, "{$key}_{$i}_whatsapp", true ),
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
function directoryweb_listing_hours( $id ) {
	$key  = directoryweb_meta_prefix() . 'opening_hours';
	$rows = array();
	$n    = (int) get_post_meta( $id, $key, true );
	$hm   = fn( $t ) => preg_match( '/^(\d{1,2}):(\d{2})/', trim( (string) $t ), $x ) ? sprintf( '%02d:%s', $x[1], $x[2] ) : '';
	for ( $i = 0; $i < $n; $i++ ) {
		$day = (string) get_post_meta( $id, "{$key}_{$i}_day", true );
		if ( ! in_array( $day, DIRECTORYWEB_WEEKDAYS, true ) ) {
			continue;
		}
		$closed = (bool) get_post_meta( $id, "{$key}_{$i}_closed", true );
		$open   = $hm( get_post_meta( $id, "{$key}_{$i}_open", true ) );
		$close  = $hm( get_post_meta( $id, "{$key}_{$i}_close", true ) );
		if ( $closed || ( $open && $close ) ) {
			$rows[] = array(
				'day'    => $day,
				'open'   => $closed ? '' : $open,
				'close'  => $closed ? '' : $close,
				'closed' => $closed,
			);
		}
	}
	usort( $rows, fn( $a, $b ) => array( array_search( $a['day'], DIRECTORYWEB_WEEKDAYS, true ), $a['open'] ) <=> array( array_search( $b['day'], DIRECTORYWEB_WEEKDAYS, true ), $b['open'] ) );
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
function directoryweb_set_listing_list( $id, $key, array $rows ) {
	if ( ! isset( DIRECTORYWEB_LIST_FIELDS[ $key ] ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html( 'Not a listing list: ' . $key . '. Nothing was written.' ), '1.0.0' );
		return;
	}
	$subs   = DIRECTORYWEB_LIST_FIELDS[ $key ];
	$phones = directoryweb_meta_prefix() . 'phones' === $key;
	$old    = (int) get_post_meta( $id, $key, true );
	if ( $phones ) {
		$rows = array_filter( $rows, fn( $r ) => ! directoryweb_phone_never_publish( $r['number'] ?? '' ) );
	}
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
	update_post_meta( $id, '_' . $key, $phones ? 'field_aw_listing_phones' : 'field_aw_listing_opening_hours' );
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
function directoryweb_listing_put_phone( $id, $phone = '', $whatsapp = '' ) {
	$phone    = trim( sanitize_text_field( (string) $phone ) );
	$whatsapp = trim( sanitize_text_field( (string) $whatsapp ) );
	if ( '' === $phone && '' === $whatsapp ) {
		return;
	}
	$rows = directoryweb_listing_phones( $id );
	$norm = fn( $n ) => directoryweb_normalise_phone( $n );
	if ( '' !== $phone ) {
		if ( $rows ) {
			$rows[0]['number'] = $phone;
		} else {
			$rows[] = array(
				'number'   => $phone,
				'label'    => '',
				'whatsapp' => false,
			);
		}
	}
	if ( '' !== $whatsapp ) {
		$done = false;
		foreach ( $rows as $i => $r ) {
			if ( $norm( $r['number'] ) === $norm( $whatsapp ) ) {
				$rows[ $i ]['whatsapp'] = true;
				$done                   = true;
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
			$rows[] = array(
				'number'   => $whatsapp,
				'label'    => '',
				'whatsapp' => true,
			);
		}
	}
	directoryweb_set_listing_list( $id, directoryweb_meta_prefix() . 'phones', $rows );
}

/**
 * The first number of a listing (Call), or the first marked WhatsApp.
 *
 * @param int  $id       Listing ID.
 * @param bool $whatsapp Only a number marked WhatsApp.
 * @return string
 */
function directoryweb_listing_first_phone( $id, $whatsapp = false ) {
	foreach ( directoryweb_listing_phones( $id ) as $p ) {
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
 * @param array $rows directoryweb_listing_hours().
 * @return array
 */
function directoryweb_hours_rows_spec( $rows ) {
	$groups = array();
	foreach ( $rows as $r ) {
		$k              = $r['closed'] ? '00:00-00:00' : $r['open'] . '-' . $r['close'];
		$groups[ $k ][] = $r['day'];
	}
	$out = array();
	foreach ( $groups as $k => $days ) {
		list( $open, $close ) = explode( '-', $k );
		$out[]                = array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => array_values( array_unique( $days ) ),
			'opens'     => $open,
			'closes'    => $close,
		);
	}
	return $out;
}

/**
 * Daily: Claimed listings last checked more than AW_REVERIFY_DAYS ago, and AWBI recognition past its "valid until",
 * are flagged for re-checking (the badge and trust line say so). Runs on Core's daily event (aw_daily), as before.
 */
function directoryweb_flag_reverify() {
	$p      = directoryweb_meta_prefix();
	$today  = current_time( 'Y-m-d' );
	$days   = defined( 'AW_REVERIFY_DAYS' ) ? (int) AW_REVERIFY_DAYS : 180;
	$cutoff = gmdate( 'Y-m-d', strtotime( $today . ' -' . $days . ' days' ) );
	$stale  = get_posts(
		array(
			'post_type'      => directoryweb_post_type(),
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- once a day, on cron
				'relation' => 'AND',
				array(
		'key'   => $p . 'verified',
		'value' => '1',
			),
				array(
			'key'     => $p . 'verified_on',
			'value'   => $cutoff,
			'compare' => '<',
			'type'    => 'DATE',
			),
			),
		)
	);
	foreach ( $stale as $id ) {
		update_post_meta( $id, $p . 'needs_reverify', true );
	}

	$lapsed = get_posts(
		array(
			'post_type'      => directoryweb_post_type(),
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- once a day, on cron
				array(
		'key'     => $p . 'awbi_valid',
		'value'   => $today,
		'compare' => '<',
		'type'    => 'DATE',
			),
			),
		)
	);
	foreach ( $lapsed as $id ) {
		update_post_meta( $id, $p . 'needs_reverify', true );
	}
}
add_action( 'aw_daily', 'directoryweb_flag_reverify' );
