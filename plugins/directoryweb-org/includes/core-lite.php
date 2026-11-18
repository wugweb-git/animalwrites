<?php
/**
 * Standalone seam: the helpers DirectoryWeb needs, as its own code (no external dependency). Needs no companion plugin, theme or admin shell package.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIRECTORYWEB_WEEKDAYS = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );

/** Sub-fields of the two lists: name => SCF field key. */
const DIRECTORYWEB_LIST_FIELDS = array(
	'directoryweb_phones'        => array( 'number' => '', 'label' => '', 'whatsapp' => '' ),
	'directoryweb_opening_hours' => array( 'day' => '', 'open' => '', 'close' => '', 'closed' => '' ),
);

const DIRECTORYWEB_SERVICES = array( 'rescue' => 'Rescue Work', 'treatment' => 'Treatment and Vet Care', 'shelter' => 'Sheltering', 'adoption' => 'Adoption Service', 'feeding' => 'Feeding', 'sterilisation' => 'Sterilisation (ABC)', 'vaccination' => 'Vaccination', 'ambulance' => 'Ambulance Service', 'boarding-foster' => 'Boarding and Fostering', 'wildlife-rescue' => 'Wildlife Rescue', 'legal-help' => 'Legal Help', 'cattle-care' => 'Cattle Care', 'animal-welfare' => 'Animal Welfare', 'bird-treatment' => 'Bird Treatment', 'emergency-rescue' => 'Emergency Rescue' ); // Animal welfare and Bird treatment cover services implied by a listing's type; Emergency rescue is the existing type tag.

/** SHA-256 hashes of normalised phone numbers that are never shown (privacy takedowns). Add yours with the directoryweb_never_publish_phones filter. */
const DIRECTORYWEB_NEVER_PUBLISH_PHONES = array();

// These helpers are the plugin's own copies; define them only when nothing else already provides the same names, so a
// companion plugin that defines them cannot cause a redeclare fatal. The first helper stands in for the whole set.
if ( function_exists( 'directoryweb_sanitize_meta' ) ) {
	return;
}

/**
 * Sanitize a meta value by key.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed
 */
function directoryweb_sanitize_meta( $value, $key ) {
	$url_keys  = array( 'directoryweb_source_url', 'directoryweb_website', 'directoryweb_apply_url', 'directoryweb_file_url', 'directoryweb_maps_url', 'directoryweb_g_profile', 'directoryweb_instagram', 'directoryweb_facebook', 'directoryweb_x', 'directoryweb_youtube', 'directoryweb_donate_url', 'directoryweb_volunteer_url', 'directoryweb_ngobase_url' );
	$date_keys = array( 'directoryweb_source_date', 'directoryweb_verified_on', 'directoryweb_deadline', 'directoryweb_review_until', 'directoryweb_awbi_recognised', 'directoryweb_awbi_renewed', 'directoryweb_awbi_valid', 'directoryweb_services_on', 'directoryweb_event_date', 'directoryweb_campaign_close', 'directoryweb_campaign_open', 'directoryweb_campaign_outcome_asof', 'directoryweb_last_reviewed' );

	if ( in_array( $key, $url_keys, true ) ) {
		return esc_url_raw( (string) $value );
	}
	if ( in_array( $key, $date_keys, true ) ) {
		$value = (string) $value;
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
	if ( 'directoryweb_campaign_short' === $key ) { // one path segment (campaign-short.php)
		return function_exists( 'directoryweb_camp_short_clean' ) ? directoryweb_camp_short_clean( $value ) : sanitize_title( (string) $value );
	}
	if ( 'directoryweb_gallery' === $key ) { // up to six media IDs
		return implode( ',', array_slice( array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ), 0, 6 ) );
	}
	if ( in_array( $key, array( 'directoryweb_lat', 'directoryweb_lng', 'directoryweb_g_rating' ), true ) ) {
		return is_numeric( trim( (string) $value ) ) ? trim( (string) $value ) : '';
	}
	if ( 'directoryweb_g_reviews' === $key ) {
		return ctype_digit( trim( (string) $value ) ) ? trim( (string) $value ) : '';
	}
	if ( 'directoryweb_cover_image_id' === $key ) { // an attachment ID, or empty to clear
		return (int) $value ? (string) (int) $value : '';
	}
	if ( 'directoryweb_founded' === $key ) {
		return preg_match( '/^(18|19|20)\d{2}$/', trim( (string) $value ) ) ? trim( (string) $value ) : '';
	}
	if ( 'directoryweb_rel_nofollow' === $key ) {
		return $value ? '1' : '';
	}
	if ( 'directoryweb_email' === $key ) {
		return sanitize_email( (string) $value );
	}
	if ( 'directoryweb_employment_type' === $key ) { // Google's JobPosting values only
		$value = strtoupper( str_replace( array( '-', ' ' ), '_', trim( (string) $value ) ) );
		$value = preg_replace( '/^(FULL|PART)_?TIME$/', '$1_TIME', $value ); // "Fulltime", "Full time"
		$value = array( 'CONTRACT' => 'CONTRACTOR', 'INTERNSHIP' => 'INTERN', 'TEMP' => 'TEMPORARY' )[ $value ] ?? $value;
		return in_array( $value, array( 'FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'PER_DIEM', 'INTERN', 'VOLUNTEER', 'OTHER' ), true ) ? $value : '';
	}
	return sanitize_text_field( (string) $value );
}


/** Service slugs (the fixed list; "wildlife-rescue" and "emergency-rescue" are also listing types, and count as types). */
function directoryweb_service_slugs() {
	return array_values( array_diff( array_keys( DIRECTORYWEB_SERVICES ), array( 'wildlife-rescue', 'emergency-rescue' ) ) );
}


/** A listing's types (tags that are listing types). */
function directoryweb_listing_types( $id ) {
	return directoryweb_item_tags( $id, directoryweb_listing_type_slugs() );
}


/** A listing's services (names). */
function directoryweb_listing_service_names( $id ) {
	return wp_list_pluck( directoryweb_item_tags( $id, directoryweb_service_slugs() ), 'name' );
}


/** Replace an item's tags from one list, keeping its other tags. */
function directoryweb_set_item_tags( $id, array $new, array $list ) {
	$keep = array_diff( wp_get_object_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) ), $list );
	wp_set_object_terms( $id, array_values( array_unique( array_merge( $keep, array_intersect( $new, $list ) ) ) ), 'post_tag' );
}


/**
 * Write a whole list in SCF's repeater format (count, rows, reference rows); rows past the new count are removed, as SCF
 * does when rows are taken out.
 *
 * @param int    $id   Listing ID.
 * @param string $key  directoryweb_phones or directoryweb_opening_hours.
 * @param array  $rows Rows keyed by sub-field name (number/label/whatsapp, or day/open/close/closed).
 */
function directoryweb_set_listing_list( $id, $key, array $rows ) {
	$subs = DIRECTORYWEB_LIST_FIELDS[ $key ];
	$old  = (int) get_post_meta( $id, $key, true );
	if ( 'directoryweb_phones' === $key ) {
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
		}
	}
	for ( $i = count( $rows ); $i < $old; $i++ ) {
		foreach ( array_keys( $subs ) as $name ) {
			delete_post_meta( $id, "{$key}_{$i}_{$name}" );
			delete_post_meta( $id, "_{$key}_{$i}_{$name}" );
		}
	}
	update_post_meta( $id, $key, count( $rows ) );
}


/**
 * A listing's phone numbers: [ [ number, label, whatsapp (bool) ], … ] from directoryweb_phones.
 *
 * @param int $id Listing ID.
 * @return array
 */
function directoryweb_listing_phones( $id ) {
	$rows = array();
	$n    = (int) get_post_meta( $id, 'directoryweb_phones', true );
	for ( $i = 0; $i < $n; $i++ ) {
		$num = trim( (string) get_post_meta( $id, "directoryweb_phones_{$i}_number", true ) );
		if ( '' !== $num && ! directoryweb_phone_never_publish( $num ) ) {
			$rows[] = array(
				'number'   => $num,
				'label'    => trim( (string) get_post_meta( $id, "directoryweb_phones_{$i}_label", true ) ),
				'whatsapp' => (bool) get_post_meta( $id, "directoryweb_phones_{$i}_whatsapp", true ),
			);
		}
	}
	return $rows;
}


/** The first number of a listing (Call), or the first marked WhatsApp. */
function directoryweb_listing_first_phone( $id, $whatsapp = false ) {
	foreach ( directoryweb_listing_phones( $id ) as $p ) {
		if ( ! $whatsapp || $p['whatsapp'] ) {
			return $p['number'];
		}
	}
	return '';
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
	$norm = fn( $n ) => function_exists( 'directoryweb_normalise_phone' ) ? directoryweb_normalise_phone( $n ) : preg_replace( '/\D+/', '', $n );
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
	directoryweb_set_listing_list( $id, 'directoryweb_phones', $rows );
}


/**
 * A listing's opening hours by day: [ [ day, open (H:i), close (H:i), closed (bool) ], … ] from directoryweb_opening_hours, in
 * weekday order.
 *
 * @param int $id Listing ID.
 * @return array
 */
function directoryweb_listing_hours( $id ) {
	$rows = array();
	$n    = (int) get_post_meta( $id, 'directoryweb_opening_hours', true );
	$hm   = fn( $t ) => preg_match( '/^(\d{1,2}):(\d{2})/', trim( (string) $t ), $x ) ? sprintf( '%02d:%s', $x[1], $x[2] ) : '';
	for ( $i = 0; $i < $n; $i++ ) {
		$day = (string) get_post_meta( $id, "directoryweb_opening_hours_{$i}_day", true );
		if ( ! in_array( $day, DIRECTORYWEB_WEEKDAYS, true ) ) {
			continue;
		}
		$closed = (bool) get_post_meta( $id, "directoryweb_opening_hours_{$i}_closed", true );
		$open   = $hm( get_post_meta( $id, "directoryweb_opening_hours_{$i}_open", true ) );
		$close  = $hm( get_post_meta( $id, "directoryweb_opening_hours_{$i}_close", true ) );
		if ( $closed || ( $open && $close ) ) {
			$rows[] = array( 'day' => $day, 'open' => $closed ? '' : $open, 'close' => $closed ? '' : $close, 'closed' => $closed );
		}
	}
	usort( $rows, fn( $a, $b ) => array( array_search( $a['day'], DIRECTORYWEB_WEEKDAYS, true ), $a['open'] ) <=> array( array_search( $b['day'], DIRECTORYWEB_WEEKDAYS, true ), $b['open'] ) );
	return $rows;
}


/**
 * Digits-only phone, Indian numbers normalised to 91XXXXXXXXXX.
 *
 * @param string $raw Raw number.
 * @return string
 */
function directoryweb_normalise_phone( $raw ) {
	$digits = preg_replace( '/\D+/', '', (string) $raw );
	if ( '' === $digits ) {
		return '';
	}
	if ( strlen( $digits ) <= 5 ) {
		return $digits; // short helpline code such as 1962: dialled as is
	}
	$digits = ltrim( $digits, '0' ); // trunk prefix of STD landlines and mobiles
	if ( 10 === strlen( $digits ) ) {
		$digits = '91' . $digits;
	}
	return $digits;
}


/**
 * tel: link for a phone number (short codes stay local, others get +91).
 *
 * @param string $raw Raw number.
 * @return string
 */
function directoryweb_tel_url( $raw ) {
	$d = directoryweb_normalise_phone( $raw );
	if ( '' === $d ) {
		return '';
	}
	return 'tel:' . ( strlen( $d ) <= 5 ? '' : '+' ) . $d;
}


/**
 * Icon span.
 *
 * @param string $name  Icon name (see aw.css 4.7), or type-{slug} for a listing or Library type (directoryweb-type-icons.css).
 * @param string $class Optional extra classes, e.g. 'kit-ico--type' (directoryweb_type_icon()).
 * @return string
 */
function directoryweb_i( $name, $class = '' ) {
	return '<span class="kit-ico kit-ico--' . esc_attr( $name ) . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '" aria-hidden="true"></span>';
}


function directoryweb_date( $when, $fmt = DIRECTORYWEB_DATE_FORMAT ) {
	$when = preg_match( '/^\d{8}$/', (string) $when ) ? substr( $when, 0, 4 ) . '-' . substr( $when, 4, 2 ) . '-' . substr( $when, 6, 2 ) : $when; // the date picker stores Ymd, which is not a Unix time
	$ts   = is_numeric( $when ) ? (int) $when : strtotime( (string) $when );
	if ( ! $ts ) {
		return '';
	}
	return '<span class="kit-date directoryweb-date">' . directoryweb_i( 'calendar' ) . '<time datetime="' . esc_attr( wp_date( 'Y-m-d', $ts ) ) . '">' . esc_html( wp_date( $fmt, $ts ) ) . '</time></span>';
}


/** Private folder (blocked from the web), used for claim logos. */
function directoryweb_private_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'directoryweb-private';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
	}
	return $dir;
}


/**
 * Print a read-only core list table.
 *
 * @param string $plural Table name (CSS class; prefix of its sort and header ids).
 * @param array  $cols   Column => label ('cb' => '<input type="checkbox">' for a checkbox column).
 * @param array  $rows   Rows of escaped HTML cells, column => HTML (_sort: column => raw value; _after: a row printed under it).
 * @param array  $opts   per_page, total (rows are already that page), primary, fixed, empty, foot, sortable, caption.
 */
function directoryweb_core_simple_table( $plural, array $cols, array $rows, array $opts = array() ) {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-directoryweb-core-simple-table.php';
	$t = new DIRECTORYWEB_Core_Simple_Table( $plural, $cols, $rows, $opts );
	$t->prepare_items();
	$t->display();
}


/** District centres [lat, lng] by LGD district code (DirectoryWeb's data/district-centroids.json, from OpenStreetMap Nominatim). */
function directoryweb_district_centroid( $code ) {
	static $c = null;
	if ( null === $c ) {
		$f = ( defined( 'DIRECTORYWEB_DIR' ) ? DIRECTORYWEB_DIR : dirname( __DIR__, 2 ) . '/directoryweb/' ) . 'data/district-centroids.json'; // DirectoryWeb's data file
		$c = is_readable( $f ) ? (array) json_decode( (string) file_get_contents( $f ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $c[ (string) (int) $code ] ?? null;
}


/** The item's date: the original's for sourced items, the day posted for opportunities, else the post date (0 for none). */
function directoryweb_item_date_ts( $id ) {
	if ( 'directoryweb_opportunity' === get_post_type( $id ) ) {
		return (int) get_post_time( 'U', true, $id );
	}
	$d = (string) get_post_meta( $id, 'directoryweb_source_date', true );
	if ( $d && $d > '1900' ) {
		return (int) strtotime( $d . ' 12:00:00' );
	}
	return $d ? 0 : (int) get_post_time( 'U', true, $id ); // 0001-01-01: a template with no date of its own
}


/**
 * An item's tags from one list.
 *
 * @param int      $id    Post ID.
 * @param string[] $slugs Allowed slugs ('' = the item's section).
 * @return WP_Term[]
 */
function directoryweb_item_tags( $id, $slugs = null ) {
	$slugs = null === $slugs ? directoryweb_section_tags( (string) get_post_type( $id ) ) : $slugs;
	$terms = get_the_terms( $id, 'post_tag' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}
	return array_values( array_filter( $terms, fn( $t ) => in_array( $t->slug, $slugs, true ) ) );
}


/** Whether a phone number is on the never-publish list, in any format (+91, spaces, dashes, leading 0). */
function directoryweb_phone_never_publish( $raw ) {
	$n = function_exists( 'directoryweb_normalise_phone' ) ? directoryweb_normalise_phone( $raw ) : preg_replace( '/\D+/', '', (string) $raw );
	if ( 10 === strlen( $n ) ) {
		$n = '91' . $n;
	}
	return '' !== $n && in_array( hash( 'sha256', $n ), (array) apply_filters( 'directoryweb_never_publish_phones', DIRECTORYWEB_NEVER_PUBLISH_PHONES ), true );
}

