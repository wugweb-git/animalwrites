<?php
/**
 * Directory enrichment: what the enrichment worker (animal-writes-workers, aw_workers/directory) found for a listing,
 * where each value came from, and how it is applied.
 *
 * - Every proposed value is one row of {prefix}aw_enrich: field, value, the value it replaced, its sources
 *   ({key, name, url, fetched_at} each; the exact page), confidence (high / medium / low) and status
 *   (auto-applied, approved, pending, rejected, undone, superseded). The rows are the change log (old → new) and the
 *   provenance of every value the worker set: a field's source is its latest applied row.
 * - Apply rules (owner's brief, 18 Oct 2026): a listing that is claimed or verified is never changed; everything goes to
 *   the review queue. Otherwise a high-confidence value is applied at once when the field is empty; a value that would
 *   replace something already on the listing, and every medium or low one, waits in the queue.
 * - Google Places is a locator and a check only: the listing keeps its place ID and Maps link; Google's phone, hours,
 *   rating and status are never stored here (shown live, enrichment-front.php). A field whose only source is Google is
 *   flagged "Invite to claim". Google reporting the place permanently closed: the listing stays in search and
 *   carries a "Reported closed" note (owner, 21 Oct 2026); logged, nothing to review. Two listings with the same place ID or Darpan ID: "Possible duplicate" (never merged).
 * - List fields (services, animals helped, areas served; owner, 24 Oct 2026): a proposal is the union of what the
 *   listing has and what was found, so a value is never dropped; when it adds something it is applied like a second
 *   phone (claimed, verified and low-confidence still wait for review).
 * - Identity unresolved (owner, 24 Oct 2026): while an editor has ticked it on the listing (it is not known which
 *   organisation the listing is), the queue leaves the listing out and its proposals are dropped.
 *
 * REST (Editor's Application Password, like the ingest):
 *   GET  aw/v1/enrich/queue    the next listings to check (new or claimed first, then AWBI address-only, then the
 *                              rest, then any not checked for 90 days), with what they hold now.
 *   POST aw/v1/enrich/results  one listing's findings; the site applies the rules above.
 *   GET  aw/v1/enrich/config   which sources are switched on, and Google spend this month.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_ENRICH_DB_VERSION = '1';

/** How many days a check stays fresh before the listing is checked again. */
const AW_ENRICH_REFRESH_DAYS = 90;

/**
 * The sources: key => [name, host for the icon, on by default, what it gives].
 * A new source is one module in the worker and one row here.
 */
function aw_enrich_sources() {
	return array(
		'claim'         => array( 'The organisation (claim)', '', true, 'Values the organisation sent with the claim or update form (highest trust)' ),
		'website'       => array( 'The organisation\'s website', '', true, 'Home, contact, about, donate, volunteer and adopt pages (up to 8)' ),
		'social'        => array( 'Instagram / Facebook', 'instagram.com', true, 'Public page: bio, contact, followers, posts, last post' ),
		'darpan'        => array( 'NGO Darpan', 'ngodarpan.gov.in', true, 'Legal details (only where the page can be read without a CAPTCHA)' ),
		'osm'           => array( 'OpenStreetMap', 'openstreetmap.org', true, 'Coordinates and tagged hours (ODbL)' ),
		'wikidata'      => array( 'Wikidata', 'wikidata.org', true, 'Larger organisations: founded, website, social profiles' ),
		'awbi'          => array( 'AWBI', 'awbi.gov.in', true, 'The AWBI address already on the listing' ),
		'listing'       => array( 'The listing\'s own details', '', true, 'Clean-up pass: the address, PIN code and place already on the listing, and same-name listings in the same district' ),
		'india_post'    => array( 'India Post (PIN codes)', 'postalpincode.in', true, 'Clean-up pass: the district and state of the listing\'s own PIN code' ),
		'google_places' => array( 'Google Maps', 'google.com', true, 'Locator and check only: place ID, Maps link and website lead are kept' ),
	);
}

/** Switched-on sources (Settings → Sourcing → Directory enrichment). */
function aw_enrich_sources_on() {
	$saved = get_option( 'aw_enrich_sources' );
	$out   = array();
	foreach ( aw_enrich_sources() as $k => $s ) {
		$out[ $k ] = is_array( $saved ) && array_key_exists( $k, $saved ) ? (bool) $saved[ $k ] : $s[2];
	}
	return $out;
}

/**
 * The fields: key => [label, where it is kept, group]. "Where" is a meta key, or one of the special stores:
 * phones (the Phone numbers list), hours (Opening hours by day), services (service tags), excerpt (the short bio),
 * logo (featured image), photos (aw_gallery, sideloaded).
 */
function aw_enrich_fields() {
	return array(
		'phone'          => array( 'Phone', 'phones', 'Contact' ),
		'whatsapp'       => array( 'WhatsApp', 'phones', 'Contact' ),
		'emergency'      => array( 'Emergency / ambulance', 'phones', 'Contact' ),
		'email'          => array( 'Email', 'aw_email', 'Contact' ),
		'website'        => array( 'Website', 'aw_website', 'Contact' ),
		'address'        => array( 'Full address', 'aw_address', 'Location' ),
		'pincode'        => array( 'PIN code', 'aw_pincode', 'Location' ),
		'lat'            => array( 'Latitude', 'aw_lat', 'Location' ),
		'lng'            => array( 'Longitude', 'aw_lng', 'Location' ),
		'maps_url'       => array( 'Google Maps link', 'aw_maps_url', 'Location' ),
		'place_id'       => array( 'Google place ID', 'aw_place_id', 'Location' ),
		'hours'          => array( 'Opening hours', 'hours', 'Hours' ),
		'logo'           => array( 'Logo', 'logo', 'Identity' ),
		'photos'         => array( 'Photos', 'photos', 'Identity' ),
		'description'    => array( 'About (short bio)', 'excerpt', 'Identity' ),
		'founded'        => array( 'Year established', 'aw_founded', 'Identity' ),
		'reg_type'       => array( 'Registered as', 'aw_reg_type', 'Legal' ),
		'registration'   => array( 'Registration number', 'aw_registration', 'Legal' ),
		'darpan_id'      => array( 'NGO Darpan ID', 'aw_darpan_id', 'Legal' ),
		'reg_80g'        => array( '80G', 'aw_reg_80g', 'Legal' ),
		'reg_12a'        => array( '12A', 'aw_reg_12a', 'Legal' ),
		'fcra'           => array( 'FCRA', 'aw_fcra', 'Legal' ),
		'services'       => array( 'Services', 'services', 'Work' ),
		'animals'        => array( 'Animals helped', 'aw_animals', 'Work' ),
		'areas_served'   => array( 'Areas served', 'aw_areas_served', 'Work' ),
		'donate_url'     => array( 'Donate link', 'aw_donate_url', 'Action links' ),
		'upi'            => array( 'UPI ID', 'aw_upi', 'Action links' ),
		'volunteer_url'  => array( 'Volunteer link', 'aw_volunteer_url', 'Action links' ),
		'adopt_url'      => array( 'Adopt link', 'aw_adopt_url', 'Action links' ),
		'instagram'      => array( 'Instagram', 'aw_instagram', 'Social' ),
		'facebook'       => array( 'Facebook', 'aw_facebook', 'Social' ),
		'x'              => array( 'X', 'aw_x', 'Social' ),
		'youtube'        => array( 'YouTube', 'aw_youtube', 'Social' ),
		'linkedin'       => array( 'LinkedIn', 'aw_linkedin', 'Social' ),
		'ig_followers'   => array( 'Instagram followers', 'aw_ig_followers', 'Social' ),
		'ig_posts'       => array( 'Instagram posts', 'aw_ig_posts', 'Social' ),
		'last_post'      => array( 'Last post', 'aw_last_post', 'Social' ),
		'head'           => array( 'Founder / key contact', 'aw_head', 'People' ),
		'wikidata'       => array( 'Wikidata ID', 'aw_wikidata', 'Signals' ),
		'closed'         => array( 'Reported closed', 'aw_reported_closed', 'Signals' ),
		'place'          => array( 'State and district', 'place', 'Location' ),
		'merge_into'     => array( 'Duplicate: merge into', 'merge', 'Identity' ),
	);
}

/** The table. */
function aw_enrich_table() {
	global $wpdb;
	return $wpdb->prefix . 'aw_enrich';
}

/** Create or update the table (once per version). */
function aw_enrich_install() {
	if ( get_option( 'aw_enrich_db' ) === AW_ENRICH_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = aw_enrich_table();
	dbDelta(
		"CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		listing_id bigint(20) unsigned NOT NULL,
		field varchar(40) NOT NULL,
		value longtext NOT NULL,
		old_value longtext NOT NULL,
		sources longtext NOT NULL,
		confidence varchar(8) NOT NULL DEFAULT '',
		status varchar(14) NOT NULL DEFAULT 'pending',
		note varchar(190) NOT NULL DEFAULT '',
		run varchar(40) NOT NULL DEFAULT '',
		created datetime NOT NULL,
		decided_at datetime NULL,
		decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY listing_field (listing_id, field),
		KEY status (status)
		) {$wpdb->get_charset_collate()};"
	);
	update_option( 'aw_enrich_db', AW_ENRICH_DB_VERSION, false );
}
add_action( 'admin_init', 'aw_enrich_install' );
add_action( 'rest_api_init', 'aw_enrich_install', 1 );

/** New fields on listings (registered like the others in meta.php: shown in REST, edited by editors). */
add_action(
	'init',
	function () {
		$new = array(
			'aw_address'       => 'Full address (from the organisation or a named source)',
			'aw_reg_type'      => 'Registered as (Trust, Society, Section 8 company)',
			'aw_fcra'          => 'FCRA registration number',
			'aw_areas_served'  => 'Areas served (comma separated)',
			'aw_upi'           => 'UPI ID for donations',
			'aw_adopt_url'     => 'Adopt link',
			'aw_linkedin'      => 'LinkedIn',
			'aw_ig_followers'  => 'Instagram followers',
			'aw_ig_posts'      => 'Instagram posts',
			'aw_last_post'     => 'Date of the latest social post (Y-m-d)',
			'aw_wikidata'      => 'Wikidata ID (Q…)',
		);
		foreach ( $new as $key => $desc ) {
			register_post_meta(
				'aw_listing',
				$key,
				array(
					'type'              => 'string',
					'description'       => $desc,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => fn( $v ) => aw_enrich_sanitize( $key, $v ),
					'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
);

/** Clean one value for its meta key. */
function aw_enrich_sanitize( $key, $v ) {
	if ( in_array( $key, array( 'aw_adopt_url', 'aw_linkedin' ), true ) ) {
		return esc_url_raw( (string) $v );
	}
	if ( in_array( $key, array( 'aw_ig_followers', 'aw_ig_posts' ), true ) ) {
		return ctype_digit( trim( (string) $v ) ) ? trim( (string) $v ) : '';
	}
	if ( 'aw_last_post' === $key ) {
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? (string) $v : '';
	}
	if ( 'aw_wikidata' === $key ) {
		return preg_match( '/^Q\d+$/', trim( (string) $v ) ) ? trim( (string) $v ) : '';
	}
	if ( 'aw_upi' === $key ) {
		return preg_match( '/^[\w.\-]{2,256}@[a-zA-Z]{2,64}$/', trim( (string) $v ) ) ? trim( (string) $v ) : '';
	}
	return function_exists( 'aw_sanitize_meta' ) ? aw_sanitize_meta( $v, $key ) : sanitize_text_field( (string) $v );
}

/* ---------- Reading and writing a field ---------- */

/**
 * What a listing holds now for a field (string, or a list for hours / services / photos).
 *
 * @param int    $lid   Listing ID.
 * @param string $field Field key.
 * @return mixed
 */
function aw_enrich_current( $lid, $field ) {
	$def = aw_enrich_fields()[ $field ] ?? null;
	if ( ! $def ) {
		return '';
	}
	switch ( $def[1] ) {
		case 'phones':
			$rows = aw_listing_phones( $lid );
			if ( 'whatsapp' === $field ) {
				return implode( ', ', wp_list_pluck( array_filter( $rows, fn( $r ) => $r['whatsapp'] ), 'number' ) );
			}
			if ( 'emergency' === $field ) {
				return implode( ', ', wp_list_pluck( array_filter( $rows, fn( $r ) => 'Emergency' === $r['label'] ), 'number' ) );
			}
			return implode( ', ', wp_list_pluck( $rows, 'number' ) );
		case 'hours':
			return aw_listing_hours( $lid );
		case 'services':
			return wp_list_pluck( aw_item_tags( $lid, aw_service_slugs() ), 'slug' );
		case 'excerpt':
			return (string) get_post_field( 'post_excerpt', $lid );
		case 'logo':
			$t = (int) get_post_thumbnail_id( $lid );
			return $t ? (string) wp_get_attachment_url( $t ) : '';
		case 'photos':
			return array_values( array_filter( array_map( fn( $p ) => (string) wp_get_attachment_url( (int) $p ), array_filter( explode( ',', (string) get_post_meta( $lid, 'aw_gallery', true ) ) ) ) ) );
		case 'place':
			return aw_enrich_place_current( $lid );
		case 'merge':
			return (int) get_post_meta( $lid, 'aw_merged_into', true ) ? (int) get_post_meta( $lid, 'aw_merged_into', true ) : '';
		default:
			return (string) get_post_meta( $lid, $def[1], true );
	}
}

/** Raw state of the store a field lives in, for undo. */
function aw_enrich_snapshot( $lid, $field ) {
	$def = aw_enrich_fields()[ $field ] ?? array( '', '' );
	switch ( $def[1] ) {
		case 'phones':
			return array( 'rows' => aw_listing_phones( $lid ), 'unv' => aw_unverified_list( $lid ) );
		case 'hours':
			return aw_listing_hours( $lid );
		case 'services':
			return aw_enrich_current( $lid, $field );
		case 'excerpt':
			return (string) get_post_field( 'post_excerpt', $lid );
		case 'logo':
			return (int) get_post_thumbnail_id( $lid );
		case 'photos':
			return (string) get_post_meta( $lid, 'aw_gallery', true );
		case 'place':
			list( $states, $districts ) = aw_item_codes( $lid );
			return array( 'states' => $states, 'districts' => $districts );
		case 'merge':
			return '';
		default:
			if ( isset( AW_ENRICH_ALT[ $field ] ) ) {
				return array( 'v' => (string) get_post_meta( $lid, $def[1], true ), 'alt' => (string) get_post_meta( $lid, AW_ENRICH_ALT[ $field ], true ), 'unv' => aw_unverified_list( $lid ) );
			}
			return (string) get_post_meta( $lid, $def[1], true );
	}
}

/** Put a snapshot back (undo). */
function aw_enrich_restore( $lid, $field, $snap ) {
	$def = aw_enrich_fields()[ $field ] ?? array( '', '' );
	switch ( $def[1] ) {
		case 'phones':
			$rows = isset( $snap['rows'] ) ? (array) $snap['rows'] : (array) $snap; // older rows kept the list alone
			aw_set_listing_list( $lid, 'aw_phones', $rows );
			if ( isset( $snap['unv'] ) ) {
				$snap['unv'] ? update_post_meta( $lid, 'aw_unverified', array_values( (array) $snap['unv'] ) ) : delete_post_meta( $lid, 'aw_unverified' );
			}
			break;
		case 'hours':
			aw_set_listing_list( $lid, 'aw_opening_hours', (array) $snap );
			break;
		case 'services':
			aw_set_item_tags( $lid, (array) $snap, aw_service_slugs() );
			break;
		case 'excerpt':
			wp_update_post( array( 'ID' => $lid, 'post_excerpt' => (string) $snap ) );
			break;
		case 'logo':
			(int) $snap ? set_post_thumbnail( $lid, (int) $snap ) : delete_post_thumbnail( $lid );
			break;
		case 'photos':
			'' === (string) $snap ? delete_post_meta( $lid, 'aw_gallery' ) : update_post_meta( $lid, 'aw_gallery', (string) $snap );
			break;
		case 'place':
			aw_enrich_place_restore( $lid, (array) $snap );
			break;
		case 'merge':
			break; // a merge is undone by aw_enrich_unmerge()
		default:
			if ( is_array( $snap ) && isset( $snap['v'] ) ) { // a field that can hold a second value
				'' === $snap['v'] ? delete_post_meta( $lid, $def[1] ) : update_post_meta( $lid, $def[1], $snap['v'] );
				'' === $snap['alt'] ? delete_post_meta( $lid, AW_ENRICH_ALT[ $field ] ) : update_post_meta( $lid, AW_ENRICH_ALT[ $field ], $snap['alt'] );
				$snap['unv'] ? update_post_meta( $lid, 'aw_unverified', array_values( (array) $snap['unv'] ) ) : delete_post_meta( $lid, 'aw_unverified' );
				break;
			}
			'' === (string) $snap ? delete_post_meta( $lid, $def[1] ) : update_post_meta( $lid, $def[1], $snap );
	}
	aw_enrich_after_change( $lid );
}

/**
 * Write a proposed value onto the listing. Returns false when the value cannot be used.
 *
 * @param int    $lid   Listing ID.
 * @param string $field Field key.
 * @param mixed  $value Value (string; list for hours, services, photos).
 * @return bool
 */
function aw_enrich_write( $lid, $field, $value, $mode = 'apply' ) {
	$def = aw_enrich_fields()[ $field ] ?? null;
	if ( ! $def ) {
		return false;
	}
	$name = get_the_title( $lid );
	switch ( $def[1] ) {
		case 'phones':
			$num  = trim( sanitize_text_field( (string) $value ) );
			$rows = aw_listing_phones( $lid );
			$norm = fn( $n ) => substr( preg_replace( '/\D+/', '', $n ), -10 );
			$hit  = null;
			foreach ( $rows as $i => $r ) {
				if ( $norm( $r['number'] ) === $norm( $num ) ) {
					$hit = $i;
				}
			}
			if ( '' === $num || strlen( $norm( $num ) ) < 6 ) {
				return false;
			}
			if ( null === $hit ) {
				if ( 'both' === $mode && $rows ) { // another number is already there: both are shown as unverified
					aw_unverified_add( $lid, array_merge( wp_list_pluck( $rows, 'number' ), array( $num ) ) );
				}
				$rows[] = array( 'number' => $num, 'label' => 'emergency' === $field ? 'Emergency' : '', 'whatsapp' => 'whatsapp' === $field );
			} elseif ( 'whatsapp' === $field ) {
				$rows[ $hit ]['whatsapp'] = true;
			} elseif ( 'emergency' === $field && '' === $rows[ $hit ]['label'] ) {
				$rows[ $hit ]['label'] = 'Emergency';
			}
			aw_set_listing_list( $lid, 'aw_phones', $rows );
			break;
		case 'hours':
			$rows = array();
			foreach ( (array) $value as $r ) {
				if ( isset( $r['day'] ) && in_array( $r['day'], AW_WEEKDAYS, true ) ) {
					$rows[] = array( 'day' => $r['day'], 'open' => (string) ( $r['open'] ?? '' ), 'close' => (string) ( $r['close'] ?? '' ), 'closed' => ! empty( $r['closed'] ) );
				}
			}
			if ( ! $rows ) {
				return false;
			}
			aw_set_listing_list( $lid, 'aw_opening_hours', $rows );
			break;
		case 'services':
			$slugs = array_values( array_intersect( array_map( 'sanitize_title', (array) $value ), aw_service_slugs() ) );
			if ( ! $slugs ) {
				return false;
			}
			aw_set_item_tags( $lid, $slugs, aw_service_slugs() );
			update_post_meta( $lid, 'aw_services_source', 'the organisation\'s own pages' );
			update_post_meta( $lid, 'aw_services_on', wp_date( 'Y-m-d' ) );
			break;
		case 'excerpt':
			wp_update_post( array( 'ID' => $lid, 'post_excerpt' => sanitize_textarea_field( (string) $value ) ) );
			break;
		case 'logo':
			$att = aw_enrich_sideload( (string) $value, $lid, $name . ' logo' );
			if ( ! $att ) {
				return false;
			}
			set_post_thumbnail( $lid, $att );
			break;
		case 'photos':
			$ids = array();
			foreach ( array_slice( (array) $value, 0, 6 ) as $url ) {
				$att = aw_enrich_sideload( (string) $url, $lid, $name );
				if ( $att ) {
					$ids[] = $att;
				}
			}
			if ( ! $ids ) {
				return false;
			}
			update_post_meta( $lid, 'aw_gallery', implode( ',', $ids ) );
			break;
		case 'place':
			if ( ! aw_enrich_place_write( $lid, $value ) ) {
				return false;
			}
			break;
		case 'merge':
			return false; // never written as a value: an approved merge runs aw_enrich_merge()
		default:
			$v = aw_enrich_sanitize( $def[1], is_array( $value ) ? implode( ', ', $value ) : (string) $value );
			if ( '' === $v ) {
				return false;
			}
			if ( 'both' === $mode && isset( AW_ENRICH_ALT[ $field ] ) ) { // keep the value already there, add this one next to it
				$now = (string) get_post_meta( $lid, $def[1], true );
				update_post_meta( $lid, AW_ENRICH_ALT[ $field ], $v );
				aw_unverified_add( $lid, array( $now, $v ) );
				break;
			}
			update_post_meta( $lid, $def[1], $v );
	}
	aw_enrich_after_change( $lid );
	return true;
}

/** A picture from a public page into the Media Library (alt = the organisation's name). */
function aw_enrich_sideload( $url, $lid, $alt ) {
	if ( ! preg_match( '#^https?://#i', $url ) ) { // http too: the picture is copied into Media, never linked
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$tmp = download_url( $url, 20 );
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}
	$type = wp_get_image_mime( $tmp );
	$ext  = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif' )[ $type ] ?? '';
	if ( ! $ext || filesize( $tmp ) < 2000 ) {
		wp_delete_file( $tmp );
		return 0;
	}
	// No picture is used by two items (deploy check, owner 9 Oct 2026): one that another item already uses is not taken
	// (the same organisation often has two listings; the first keeps the picture).
	$hash = (string) md5_file( $tmp );
	global $wpdb;
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'aw_md5' AND meta_value = %s LIMIT 10", $hash ) ) as $used ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inuse = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status = 'publish' WHERE ( pm.meta_key = '_thumbnail_id' AND pm.meta_value = %s ) OR ( pm.meta_key = 'aw_gallery' AND FIND_IN_SET( %s, pm.meta_value ) )", (string) $used, (string) $used ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $inuse ) {
			wp_delete_file( $tmp );
			return 0;
		}
	}
	$id = media_handle_sideload( array( 'name' => sanitize_title( $alt ) . '.' . $ext, 'tmp_name' => $tmp ), $lid, $alt );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, 'aw_md5', $hash );
	update_post_meta( $id, '_aw_source_url', esc_url_raw( $url ) ); // where the picture came from
	return (int) $id;
}

/** After a listing changes: its page cache. */
function aw_enrich_after_change( $lid ) {
	clean_post_cache( $lid );
	if ( function_exists( 'aw_cdn_purge_post' ) ) {
		aw_cdn_purge_post( $lid );
	}
	do_action( 'litespeed_purge_post', $lid );
}

/** Values compared loosely: phones by the last 10 digits, links without scheme / www / slash, text lower-case. */
function aw_enrich_norm( $field, $v ) {
	if ( is_array( $v ) ) {
		$v = wp_json_encode( $v );
	}
	$v = strtolower( trim( (string) $v ) );
	if ( in_array( $field, array( 'phone', 'whatsapp', 'emergency' ), true ) ) {
		return substr( preg_replace( '/\D+/', '', $v ), -10 );
	}
	if ( preg_match( '#^https?://#', $v ) ) {
		return rtrim( preg_replace( '#^https?://(www\.|m\.)?#', '', $v ), '/' );
	}
	return preg_replace( '/[\s,.]+/', ' ', $v );
}

/** Whether a listing already holds this value (for phones: any of its numbers). */
function aw_enrich_has_value( $lid, $field, $value ) {
	if ( 'place' === $field ) {
		return aw_enrich_place_held( $lid, $value );
	}
	$now = aw_enrich_current( $lid, $field );
	if ( in_array( $field, array( 'phone', 'whatsapp', 'emergency' ), true ) ) {
		$want = aw_enrich_norm( $field, $value );
		foreach ( explode( ',', (string) $now ) as $n ) {
			if ( '' !== $want && aw_enrich_norm( $field, $n ) === $want ) {
				return true;
			}
		}
		return false;
	}
	if ( 'services' === $field ) {
		return ! array_diff( (array) $value, (array) $now );
	}
	if ( 'photos' === $field || 'logo' === $field ) {
		return ! empty( $now ); // never replaced automatically; a reviewer decides
	}
	if ( 'hours' === $field ) {
		return wp_json_encode( aw_enrich_hours_key( (array) $now ) ) === wp_json_encode( aw_enrich_hours_key( (array) $value ) );
	}
	return aw_enrich_norm( $field, $now ) === aw_enrich_norm( $field, $value );
}

/** Hours rows reduced to day => "open-close" for comparing. */
function aw_enrich_hours_key( $rows ) {
	$out = array();
	foreach ( $rows as $r ) {
		$out[ $r['day'] ?? '' ] = ! empty( $r['closed'] ) ? 'closed' : ( $r['open'] ?? '' ) . '-' . ( $r['close'] ?? '' );
	}
	ksort( $out );
	return $out;
}

/** Whether a field is empty on the listing. */
function aw_enrich_is_empty( $lid, $field ) {
	if ( 'place' === $field ) {
		return ! aw_item_codes( $lid )[1]; // a state without a district is a place still to fill
	}
	if ( in_array( $field, array( 'whatsapp', 'emergency' ), true ) ) {
		return true; // adding a marked number never removes one
	}
	$now = aw_enrich_current( $lid, $field );
	return is_array( $now ) ? ! $now : '' === trim( (string) $now );
}

/** Claimed (an approved claim) or verified listings are never changed by the worker. */
function aw_enrich_is_protected( $lid ) {
	if ( get_post_meta( $lid, 'aw_verified', true ) ) {
		return true;
	}
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} a JOIN {$wpdb->postmeta} s ON s.post_id = a.post_id AND s.meta_key = 'aw_c_status' AND s.meta_value = 'approved' WHERE a.meta_key = 'aw_c_listing' AND a.meta_value = %d LIMIT 1", $lid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/* ---------- Owner's rule (21 Oct 2026): fill empty fields with sourced values; keep both when a value is already there ---------- */

/**
 * The change-log note of a value kept next to what was there.
 *
 * @param string $field Field key.
 * @return string
 */
function aw_enrich_both_note( $field ) {
	return in_array( $field, AW_ENRICH_LIST_FIELDS, true ) ? 'Added to what was there; nothing removed' : 'Kept next to the value already there; both shown as unverified';
}

/** Fields that can hold a second value next to the one already on the listing: field => meta key of the second value. */
const AW_ENRICH_ALT = array( 'email' => 'aw_email_alt', 'website' => 'aw_website_alt' );

/**
 * What to do with a sourced value: 'apply' (the field is empty), 'both' (phones, email, website: keep it next to what
 * is there, both unverified) or 'queue' (a reviewer decides: conflicting sources, claimed or verified listings, and any
 * other field that already has a value).
 */
function aw_enrich_mode( $lid, $field, $conf, $protected, $value = null ) {
	if ( $protected || 'low' === $conf || 'closed' === $field || 'merge_into' === $field ) {
		return 'queue'; // a merge is always the owner's decision (25 Oct 2026)
	}
	if ( 'place' === $field ) {
		return aw_enrich_place_fills( $lid, $value ) ? 'apply' : 'queue';
	}
	if ( in_array( $field, array( 'phone', 'whatsapp', 'emergency' ), true ) ) {
		return aw_listing_phones( $lid ) ? 'both' : 'apply';
	}
	if ( aw_enrich_is_empty( $lid, $field ) ) {
		return 'apply';
	}
	if ( in_array( $field, AW_ENRICH_LIST_FIELDS, true ) ) {
		return 'both'; // The value is the union (aw_enrich_union): it only adds.
	}
	if ( isset( AW_ENRICH_ALT[ $field ] ) && '' === trim( (string) get_post_meta( $lid, AW_ENRICH_ALT[ $field ], true ) ) ) {
		return 'both';
	}
	return 'queue';
}

/* ---------- Rows ---------- */

/** Whether a value holds a number on the never-publish list (aw_phone_never_publish), at any depth. */
function aw_enrich_never_publish( $value ) {
	if ( ! function_exists( 'aw_phone_never_publish' ) ) {
		return false;
	}
	$blocked = false;
	$vals    = (array) $value;
	array_walk_recursive(
		$vals,
		function ( $v ) use ( &$blocked ) {
			$blocked = $blocked || ( is_scalar( $v ) && aw_phone_never_publish( $v ) );
		}
	);
	return $blocked;
}

/** Add a row. */
function aw_enrich_add_row( $lid, $field, $value, $old, $sources, $confidence, $status, $run = '', $note = '' ) {
	global $wpdb;
	if ( aw_enrich_never_publish( $value ) ) {
		return 0; // On the never-publish list: not proposed, not queued.
	}
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		aw_enrich_table(),
		array(
			'listing_id' => $lid,
			'field'      => $field,
			'value'      => wp_json_encode( $value ),
			'old_value'  => wp_json_encode( $old ),
			'sources'    => wp_json_encode( array_values( (array) $sources ) ),
			'confidence' => $confidence,
			'status'     => $status,
			'note'       => substr( (string) $note, 0, 190 ),
			'run'        => substr( (string) $run, 0, 40 ),
			'created'    => current_time( 'mysql' ),
			'decided_at' => in_array( $status, array( 'auto-applied', 'approved' ), true ) ? current_time( 'mysql' ) : null,
			'decided_by' => in_array( $status, array( 'approved', 'rejected' ), true ) ? get_current_user_id() : 0,
		)
	);
	return (int) $wpdb->insert_id;
}

/** One row, decoded. */
function aw_enrich_row( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . aw_enrich_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	return $r ? aw_enrich_decode( $r ) : null;
}

/** JSON columns to values. */
function aw_enrich_decode( $r ) {
	foreach ( array( 'value', 'old_value', 'sources' ) as $k ) {
		$r[ $k ] = json_decode( (string) $r[ $k ], true );
	}
	return $r;
}

/** Set a row's status. */
function aw_enrich_set_status( $id, $status, $note = null ) {
	global $wpdb;
	$data = array( 'status' => $status, 'decided_at' => current_time( 'mysql' ), 'decided_by' => get_current_user_id() );
	if ( null !== $note ) {
		$data['note'] = substr( (string) $note, 0, 190 );
	}
	$wpdb->update( aw_enrich_table(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Approve a pending row: write it (old value kept for undo). The "Reported closed" row: approving keeps the listing out
 * of search with its note; rejecting clears both.
 */
function aw_enrich_approve( $id ) {
	$r = aw_enrich_row( $id );
	if ( ! $r || 'pending' !== $r['status'] ) {
		return false;
	}
	$lid = (int) $r['listing_id'];
	if ( 'closed' === $r['field'] ) {
		update_post_meta( $lid, 'aw_reported_closed', wp_date( 'Y-m-d' ) );
		aw_enrich_set_status( $id, 'approved' );
		aw_enrich_after_change( $lid );
		return true;
	}
	if ( 'merge_into' === $r['field'] ) {
		$done = aw_enrich_merge( $lid, (int) $r['value'], $id );
		if ( is_string( $done ) ) {
			aw_enrich_set_status( $id, 'rejected', 'Not merged: ' . $done );
			return false;
		}
		global $wpdb;
		$wpdb->update( aw_enrich_table(), array( 'old_value' => wp_json_encode( $done ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		aw_enrich_set_status( $id, 'approved' );
		return true;
	}
	$snap = aw_enrich_snapshot( $lid, $r['field'] );
	if ( ! aw_enrich_write( $lid, $r['field'], $r['value'] ) ) {
		aw_enrich_set_status( $id, 'rejected', 'Could not be applied (unusable value or picture)' );
		return false;
	}
	global $wpdb;
	$wpdb->update( aw_enrich_table(), array( 'old_value' => wp_json_encode( $snap ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	aw_enrich_set_status( $id, 'approved' );
	aw_enrich_supersede( $lid, $r['field'], $id );
	if ( is_scalar( $r['value'] ) ) {
		aw_unverified_remove( $lid, $r['value'] ); // a person confirmed this value
	}
	return true;
}

/** Reject a pending row. */
function aw_enrich_reject( $id ) {
	$r = aw_enrich_row( $id );
	if ( ! $r || 'pending' !== $r['status'] ) {
		return false;
	}
	if ( 'closed' === $r['field'] ) {
		delete_post_meta( (int) $r['listing_id'], 'aw_reported_closed' );
		aw_enrich_flag( (int) $r['listing_id'], 'reported_closed', false );
	}
	aw_enrich_set_status( $id, 'rejected' );
	return true;
}

/** Undo an applied row: the value before it goes back. */
function aw_enrich_undo( $id ) {
	$r = aw_enrich_row( $id );
	if ( ! $r || ! in_array( $r['status'], array( 'auto-applied', 'approved' ), true ) ) {
		return false;
	}
	if ( 'closed' === $r['field'] ) {
		delete_post_meta( (int) $r['listing_id'], 'aw_reported_closed' );
		aw_enrich_flag( (int) $r['listing_id'], 'reported_closed', false );
	} elseif ( 'merge_into' === $r['field'] ) {
		aw_enrich_unmerge( (int) $r['listing_id'], (array) $r['old_value'] );
	} else {
		aw_enrich_restore( (int) $r['listing_id'], $r['field'], $r['old_value'] );
	}
	aw_enrich_set_status( $id, 'undone' );
	return true;
}

/** Older pending rows for the same field are settled by a newer decision. */
function aw_enrich_supersede( $lid, $field, $except ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . aw_enrich_table() . " SET status = 'superseded' WHERE listing_id = %d AND field = %s AND status = 'pending' AND id <> %d", $lid, $field, $except ) ); // phpcs:ignore WordPress.DB
}

/**
 * Where a listing's field came from: the latest applied row's sources ([] when the worker never set it).
 *
 * @return array{sources: array, confidence: string, at: string, status: string}|null
 */
function aw_enrich_provenance( $lid, $field ) {
	static $cache = array();
	if ( ! isset( $cache[ $lid ] ) ) {
		global $wpdb;
		$cache[ $lid ] = array();
		if ( get_option( 'aw_enrich_db' ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT field, sources, confidence, status, created FROM ' . aw_enrich_table() . " WHERE listing_id = %d AND status IN ('auto-applied','approved') ORDER BY id ASC", $lid ), ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $rows as $r ) {
				$cache[ $lid ][ $r['field'] ] = array( 'sources' => (array) json_decode( $r['sources'], true ), 'confidence' => $r['confidence'], 'at' => $r['created'], 'status' => $r['status'] );
			}
		}
	}
	return $cache[ $lid ][ $field ] ?? null;
}

/** Every source key behind a listing's applied values. */
function aw_enrich_listing_source_keys( $lid ) {
	global $wpdb;
	if ( ! get_option( 'aw_enrich_db' ) ) {
		return array();
	}
	$keys = array();
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT sources FROM ' . aw_enrich_table() . " WHERE listing_id = %d AND status IN ('auto-applied','approved')", $lid ) ) as $s ) { // phpcs:ignore WordPress.DB
		foreach ( (array) json_decode( $s, true ) as $src ) {
			$k = (string) ( $src['key'] ?? '' );
			if ( $k && ! isset( $keys[ $k ] ) ) {
				$keys[ $k ] = $src;
			}
		}
	}
	return $keys;
}

/** Listing flags (Invite to claim, Reported closed, Possible duplicate): aw_enrich_flags = [flag => detail]. */
function aw_enrich_flags( $lid ) {
	$f = get_post_meta( $lid, 'aw_enrich_flags', true );
	return is_array( $f ) ? array_filter( $f ) : array();
}

/** Set or clear one flag. */
function aw_enrich_flag( $lid, $flag, $detail ) {
	$f = aw_enrich_flags( $lid );
	if ( false === $detail || null === $detail || array() === $detail ) {
		unset( $f[ $flag ] );
	} else {
		$f[ $flag ] = $detail;
	}
	$f ? update_post_meta( $lid, 'aw_enrich_flags', $f ) : delete_post_meta( $lid, 'aw_enrich_flags' );
}

/**
 * Whether an editor marked the listing's identity unresolved: then nothing is proposed for it.
 *
 * @param int $lid Listing ID.
 * @return bool
 */
function aw_enrich_identity_unresolved( $lid ) {
	return ! empty( aw_enrich_flags( $lid )['identity'] );
}

/** List fields: a proposal adds to what is there and never drops a value. */
const AW_ENRICH_LIST_FIELDS = array( 'services', 'animals', 'areas_served' );

/**
 * The items of a list field's value: an array (services) or comma-separated text.
 *
 * @param mixed $v Value.
 * @return array
 */
function aw_enrich_list_items( $v ) {
	$items = is_array( $v ) ? $v : explode( ',', (string) $v );
	return array_values( array_filter( array_map( fn( $s ) => trim( (string) $s ), $items ), 'strlen' ) );
}

/**
 * A list field's proposal as the union of what the listing holds and what was found (current order first, matched
 * case-insensitively), in the field's own shape. Returns the proposal unchanged for other fields or an empty listing.
 *
 * @param int    $lid   Listing ID.
 * @param string $field Field key.
 * @param mixed  $value Proposed value.
 * @return mixed
 */
function aw_enrich_union( $lid, $field, $value ) {
	if ( ! in_array( $field, AW_ENRICH_LIST_FIELDS, true ) || aw_enrich_is_empty( $lid, $field ) ) {
		return $value;
	}
	$out  = aw_enrich_list_items( aw_enrich_current( $lid, $field ) );
	$seen = array_map( 'strtolower', $out );
	foreach ( aw_enrich_list_items( $value ) as $item ) {
		if ( 'services' === $field && ! in_array( sanitize_title( $item ), aw_service_slugs(), true ) ) {
			continue; // Only known services are stored (aw_enrich_write), so an unknown one adds nothing.
		}
		if ( ! in_array( strtolower( $item ), $seen, true ) ) {
			$out[]  = $item;
			$seen[] = strtolower( $item );
		}
	}
	return is_array( $value ) ? $out : implode( ', ', $out );
}

/* ---------- Claims are a source too ---------- */

// An approved claim's values are logged as approved rows from the organisation (provenance "claim"), before they apply.
add_action(
	'aw_claim_before_apply',
	function ( $lid, $proposed, $claim_id ) {
		aw_enrich_install();
		$map = array( 'aw_phone' => 'phone', 'aw_whatsapp' => 'whatsapp', 'aw_email' => 'email', 'aw_website' => 'website', 'aw_services' => 'services' );
		delete_post_meta( $lid, 'aw_unverified' ); // the organisation has spoken: nothing on the listing is "unverified" any more
		delete_post_meta( $lid, 'aw_email_alt' );
		delete_post_meta( $lid, 'aw_website_alt' );
		$src = array( array( 'key' => 'claim', 'name' => 'The organisation (claim)', 'url' => '', 'fetched_at' => wp_date( 'c' ) ) );
		foreach ( (array) $proposed as $k => $v ) {
			if ( isset( $map[ $k ] ) && '' !== (string) $v ) {
				$field = $map[ $k ];
				$val   = 'services' === $field ? explode( ',', (string) $v ) : $v;
				aw_enrich_add_row( $lid, $field, $val, aw_enrich_snapshot( $lid, $field ), $src, 'high', 'approved', 'claim-' . $claim_id );
			}
		}
	},
	10,
	3
);

/* ---------- Spend (Google Places) ---------- */

/** Google spend this month (USD): the worker's calls plus the live block's. */
function aw_enrich_spent( $which = 'all' ) {
	$l = (array) get_option( 'aw_enrich_ledger', array() );
	$m = (array) ( $l[ wp_date( 'Y-m' ) ] ?? array() );
	return 'all' === $which ? (float) array_sum( $m ) : (float) ( $m[ $which ] ?? 0 );
}

/** Add to this month's spend. */
function aw_enrich_spend( $usd, $which = 'worker' ) {
	$l   = (array) get_option( 'aw_enrich_ledger', array() );
	$key = wp_date( 'Y-m' );
	$l[ $key ][ $which ] = round( (float) ( $l[ $key ][ $which ] ?? 0 ) + (float) $usd, 4 );
	$l   = array_slice( $l, -13, null, true );
	update_option( 'aw_enrich_ledger', $l, false );
}

/* ---------- REST ---------- */

add_action(
	'rest_api_init',
	function () {
		$can = fn() => current_user_can( 'edit_others_posts' );
		register_rest_route( 'aw/v1', '/enrich/config', array( 'methods' => 'GET', 'permission_callback' => $can, 'callback' => 'aw_enrich_rest_config' ) );
		register_rest_route( 'aw/v1', '/enrich/queue', array( 'methods' => 'GET', 'permission_callback' => $can, 'callback' => 'aw_enrich_rest_queue' ) );
		register_rest_route( 'aw/v1', '/enrich/results', array( 'methods' => 'POST', 'permission_callback' => $can, 'callback' => 'aw_enrich_rest_results' ) );
		register_rest_route( 'aw/v1', '/enrich/index', array( 'methods' => 'GET', 'permission_callback' => $can, 'callback' => 'aw_enrich_rest_index' ) );
		register_rest_route( 'aw/v1', '/enrich/cleanup', array( 'methods' => 'GET', 'permission_callback' => $can, 'callback' => 'aw_enrich_rest_cleanup' ) );
	}
);

/** GET enrich/config. */
function aw_enrich_rest_config() {
	return array(
		'sources'      => aw_enrich_sources_on(),
		'google_spent' => aw_enrich_spent( 'worker' ),
		'fields'       => array_keys( aw_enrich_fields() ),
		'services'     => AW_SERVICES,
		'counts'       => aw_enrich_progress(),
	);
}

/** How far the worker has got: listings checked / total, and pending reviews. */
function aw_enrich_progress() {
	global $wpdb;
	$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'aw_listing' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB
	$checked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_enrich_checked' WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish'" ); // phpcs:ignore WordPress.DB
	$pending = get_option( 'aw_enrich_db' ) ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aw_enrich_table() . " WHERE status = 'pending'" ) : 0; // phpcs:ignore WordPress.DB
	return array( 'total' => $total, 'checked' => $checked, 'pending' => $pending );
}

/**
 * GET enrich/index?state=Haryana&page=1: the published listings of one state (id, name, district, whether they have any
 * contact), for matching an outside list (NGO Darpan CSV) to listings. 1,000 a page.
 */
function aw_enrich_rest_index( WP_REST_Request $req ) {
	global $wpdb;
	$want = strtolower( trim( (string) $req['state'] ) );
	$page = max( 1, (int) ( $req['page'] ?? 1 ) );
	$out  = array();
	$code = 0;
	foreach ( aw_place_states() as $st ) {
		if ( strtolower( $st->name ) === $want ) {
			$code = (int) $st->code;
		}
	}
	if ( ! $code ) {
		return array( 'items' => array(), 'state' => (string) $req['state'] );
	}
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_state_code' AND m.meta_value = %d WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' ORDER BY p.ID LIMIT 1000 OFFSET %d", $code, ( $page - 1 ) * 1000 ) ); // phpcs:ignore WordPress.DB
	foreach ( $ids as $id ) {
		$pl    = aw_listing_place( (int) $id );
		$out[] = array(
			'id'        => (int) $id,
			'name'      => html_entity_decode( get_the_title( (int) $id ), ENT_QUOTES ),
			'district'  => (string) $pl[1],
			'state'     => (string) $pl[0],
			'contact'   => (bool) ( aw_listing_phones( (int) $id ) || get_post_meta( $id, 'aw_email', true ) || get_post_meta( $id, 'aw_website', true ) || get_post_meta( $id, 'aw_facebook', true ) ),
			'darpan_id' => (string) get_post_meta( $id, 'aw_darpan_id', true ),
		);
	}
	return array( 'items' => $out, 'state' => (string) $req['state'], 'page' => $page );
}

/**
 * GET enrich/queue?limit=50&ids=1,2&group=awbi-bare. Order: published or claimed in the last 24 hours and not checked
 * since; never checked AWBI listings with no phone, email or website; every other never-checked listing; then those
 * last checked more than 90 days ago.
 */
function aw_enrich_rest_queue( WP_REST_Request $req ) {
	global $wpdb;
	$limit = max( 1, min( 200, (int) ( $req['limit'] ?? 50 ) ) );
	$ids   = array_filter( array_map( 'absint', explode( ',', (string) ( $req['ids'] ?? '' ) ) ) );
	$group = sanitize_key( (string) ( $req['group'] ?? '' ) );
	$google = ! empty( $req['google'] ); // the worker has Google on this run
	if ( ! $ids ) {
		$p      = $wpdb->posts;
		$m      = $wpdb->postmeta;
		$base   = "FROM {$p} p LEFT JOIN {$m} c ON c.post_id = p.ID AND c.meta_key = 'aw_enrich_checked' WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish'"
			. " AND NOT EXISTS (SELECT 1 FROM {$m} f WHERE f.post_id = p.ID AND f.meta_key = 'aw_enrich_flags' AND f.meta_value LIKE '%\"identity\"%')"; // Identity unresolved: left out.
		$bare   = "EXISTS (SELECT 1 FROM {$m} a WHERE a.post_id = p.ID AND a.meta_key = 'aw_awbi_code' AND a.meta_value <> '') AND NOT EXISTS (SELECT 1 FROM {$m} x WHERE x.post_id = p.ID AND x.meta_key IN ('aw_email','aw_website') AND x.meta_value <> '') AND NOT EXISTS (SELECT 1 FROM {$m} x WHERE x.post_id = p.ID AND x.meta_key = 'aw_phones' AND x.meta_value > 0)";
		// No way to reach them at all (no phone, email, website or Facebook): these go first after new and claimed listings
		// (SEO, 24 Oct 2026: such profiles are not indexed until a contact is added).
		$none   = "NOT EXISTS (SELECT 1 FROM {$m} x WHERE x.post_id = p.ID AND x.meta_key IN ('aw_email','aw_website','aw_facebook') AND x.meta_value <> '') AND NOT EXISTS (SELECT 1 FROM {$m} x WHERE x.post_id = p.ID AND x.meta_key = 'aw_phones' AND x.meta_value > 0)";
		$gpend  = "c.meta_value IS NOT NULL AND p.ID IN (SELECT post_id FROM {$m} WHERE meta_key = 'aw_enrich_google_pending' AND meta_value = '1')"; // checked while Google was off
		$since  = wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$claims = "SELECT a.meta_value FROM {$m} a JOIN {$m} s ON s.post_id = a.post_id AND s.meta_key = 'aw_c_status' AND s.meta_value = 'approved' JOIN {$p} cp ON cp.ID = a.post_id AND cp.post_modified >= '{$since}' WHERE a.meta_key = 'aw_c_listing'";
		$steps  = 'awbi-bare' === $group
			? array( "c.meta_value IS NULL AND {$bare}" )
			: array(
				"c.meta_value IS NULL AND p.post_date >= '{$since}'",
				"p.ID IN ({$claims}) AND (c.meta_value IS NULL OR c.meta_value < '{$since}')",
				"c.meta_value IS NULL AND {$none}",
				"c.meta_value IS NULL AND {$bare}",
				...( $google ? array( "{$gpend} AND {$none}", $gpend ) : array() ),
				'c.meta_value IS NULL',
				"c.meta_value < '" . wp_date( 'Y-m-d H:i:s', time() - AW_ENRICH_REFRESH_DAYS * DAY_IN_SECONDS ) . "'",
			);
		foreach ( $steps as $where ) {
			if ( count( $ids ) >= $limit ) {
				break;
			}
			$not  = $ids ? ' AND p.ID NOT IN (' . implode( ',', $ids ) . ')' : '';
			$ids  = array_merge( $ids, array_map( 'intval', $wpdb->get_col( "SELECT p.ID {$base} AND {$where}{$not} ORDER BY p.ID ASC LIMIT " . ( $limit - count( $ids ) ) ) ) ); // phpcs:ignore WordPress.DB
		}
	}
	$items = array();
	foreach ( array_slice( $ids, 0, $limit ) as $id ) {
		if ( 'aw_listing' === get_post_type( $id ) ) {
			$items[] = aw_enrich_listing_payload( $id );
		}
	}
	return array( 'items' => $items, 'progress' => aw_enrich_progress(), 'google_spent' => aw_enrich_spent( 'worker' ) );
}

/** What the worker needs to know about one listing. */
function aw_enrich_listing_payload( $id ) {
	list( $state, $district ) = aw_listing_place( $id );
	$now = array();
	foreach ( array_keys( aw_enrich_fields() ) as $f ) {
		$v = aw_enrich_current( $id, $f );
		if ( ( is_array( $v ) && $v ) || ( ! is_array( $v ) && '' !== trim( (string) $v ) ) ) {
			$now[ $f ] = $v;
		}
	}
	$claim = array();
	global $wpdb;
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT a.post_id FROM {$wpdb->postmeta} a JOIN {$wpdb->postmeta} s ON s.post_id = a.post_id AND s.meta_key = 'aw_c_status' AND s.meta_value = 'approved' WHERE a.meta_key = 'aw_c_listing' AND a.meta_value = %d", $id ) ) as $cid ) { // phpcs:ignore WordPress.DB
		$claim = array_merge( $claim, (array) get_post_meta( (int) $cid, 'aw_c_proposed', true ) );
	}
	return array(
		'id'           => $id,
		'name'         => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
		'url'          => get_permalink( $id ),
		'state'        => $state,
		'district'     => $district,
		'awbi_code'    => (string) get_post_meta( $id, 'aw_awbi_code', true ),
		'awbi_address' => (string) get_post_meta( $id, 'aw_awbi_address', true ),
		'area'         => (string) get_post_meta( $id, 'aw_area', true ),
		'types'        => function_exists( 'aw_listing_types' ) ? wp_list_pluck( aw_listing_types( $id ), 'slug' ) : array(),
		'protected'    => aw_enrich_is_protected( $id ),
		'checked'      => (string) get_post_meta( $id, 'aw_enrich_checked', true ),
		'now'          => $now,
		'claim'        => $claim,
	);
}

/**
 * POST enrich/results: {listing_id, run, google_cost, sources_used: [keys], proposals: [{field, value, confidence,
 * sources: [{key, name, url, fetched_at}]}], flags: {invite_claim: [fields], reported_closed: {url, at}}}.
 */
function aw_enrich_rest_results( WP_REST_Request $req ) {
	aw_enrich_install();
	$b   = (array) $req->get_json_params();
	$lid = (int) ( $b['listing_id'] ?? 0 );
	if ( 'aw_listing' !== get_post_type( $lid ) ) {
		return new WP_Error( 'aw_enrich', 'Not a listing', array( 'status' => 400 ) );
	}
	$run       = sanitize_text_field( (string) ( $b['run'] ?? '' ) );
	$protected = aw_enrich_is_protected( $lid );
	$fields    = aw_enrich_fields();
	$out       = array( 'applied' => array(), 'queued' => array(), 'skipped' => array() );
	if ( ! empty( $b['google_cost'] ) ) {
		aw_enrich_spend( (float) $b['google_cost'], 'worker' );
	}
	if ( aw_enrich_identity_unresolved( $lid ) ) { // Which organisation this is is not known: nothing is proposed.
		$out['skipped'] = array_values( array_filter( array_map( fn( $p ) => sanitize_key( (string) ( $p['field'] ?? '' ) ), (array) ( $b['proposals'] ?? array() ) ) ) );
		return $out + array(
			'protected'           => $protected,
			'identity_unresolved' => true,
		);
	}
	foreach ( (array) ( $b['proposals'] ?? array() ) as $p ) {
		$field = sanitize_key( (string) ( $p['field'] ?? '' ) );
		$conf  = in_array( $p['confidence'] ?? '', array( 'high', 'medium', 'low' ), true ) ? $p['confidence'] : 'low';
		$value = $p['value'] ?? '';
		$srcs  = array_map(
			fn( $s ) => array(
				'key'        => sanitize_key( (string) ( $s['key'] ?? '' ) ),
				'name'       => sanitize_text_field( (string) ( $s['name'] ?? '' ) ),
				'url'        => esc_url_raw( (string) ( $s['url'] ?? '' ) ),
				'fetched_at' => sanitize_text_field( (string) ( $s['fetched_at'] ?? '' ) ),
			),
			(array) ( $p['sources'] ?? array() )
		);
		if ( ! isset( $fields[ $field ] ) || 'closed' === $field || ( is_string( $value ) && '' === trim( $value ) ) || ( is_array( $value ) && ! $value ) ) {
			$out['skipped'][] = $field;
			continue;
		}
		if ( ! array_filter( $srcs, fn( $s ) => 'google_places' !== $s['key'] ) && ! in_array( $field, array( 'place_id', 'maps_url' ), true ) ) {
			$out['skipped'][] = $field; // Google alone never stores a fact
			continue;
		}
		if ( aw_enrich_never_publish( $value ) ) {
			$out['skipped'][] = $field; // on the never-publish list (privacy takedown)
			continue;
		}
		$value = aw_enrich_union( $lid, $field, $value ); // List fields: what is there plus what was found.
		if ( aw_enrich_has_value( $lid, $field, $value ) ) {
			$out['skipped'][] = $field;
			continue;
		}
		global $wpdb;
		$same = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . aw_enrich_table() . " WHERE listing_id = %d AND field = %s AND status IN ('pending','rejected') AND value = %s LIMIT 1", $lid, $field, wp_json_encode( $value ) ) ); // phpcs:ignore WordPress.DB
		if ( $same ) {
			$out['skipped'][] = $field; // already waiting, or a reviewer said no
			continue;
		}
		$mode = ! empty( $b['force_review'] ) ? 'queue' : aw_enrich_mode( $lid, $field, $conf, $protected, $value ); // an outside list (Darpan CSV) always goes to review
		if ( 'queue' !== $mode ) {
			$snap = aw_enrich_snapshot( $lid, $field );
			if ( aw_enrich_write( $lid, $field, $value, $mode ) ) {
				$rid = aw_enrich_add_row( $lid, $field, $value, $snap, $srcs, $conf, 'auto-applied', $run, 'both' === $mode ? aw_enrich_both_note( $field ) : '' );
				aw_enrich_supersede( $lid, $field, $rid );
				$out['applied'][] = $field;
				continue;
			}
			$out['skipped'][] = $field;
			continue;
		}
		$note = 'merge_into' === $field ? 'Same name and district, shared contact: approve to merge (the other listing becomes a draft with a 301)' : ( 'place' === $field && ! $protected ? ( 'low' === $conf ? 'Check first: the PIN code points to another state or district, or only the name gives the district' : 'The listing has a different state or already a district' ) : null );
		$note = null !== $note ? $note : ( ! empty( $b['force_review'] ) ? 'From an imported list: approve to apply' : ( $protected ? 'Claimed or verified listing: never changed automatically' : ( 'low' === $conf ? 'Sources disagree' : 'Would replace a value already on the listing' ) ) );
		aw_enrich_add_row( $lid, $field, $value, aw_enrich_snapshot( $lid, $field ), $srcs, $conf, 'pending', $run, $note );
		$out['queued'][] = $field;
	}

	if ( ! empty( $b['import'] ) ) { // an import neither marks the listing checked nor changes its flags
		return $out + array( 'protected' => $protected );
	}
	$flags = (array) ( $b['flags'] ?? array() );
	aw_enrich_flag( $lid, 'invite_claim', array_values( array_intersect( array_map( 'sanitize_key', (array) ( $flags['invite_claim'] ?? array() ) ), array_keys( $fields ) ) ) );
	if ( ! empty( $flags['reported_closed'] ) ) {
		$rc  = (array) $flags['reported_closed'];
		$src = array( array( 'key' => 'google_places', 'name' => 'Google Maps', 'url' => esc_url_raw( (string) ( $rc['url'] ?? '' ) ), 'fetched_at' => sanitize_text_field( (string) ( $rc['at'] ?? '' ) ) ) );
		update_post_meta( $lid, 'aw_reported_closed', wp_date( 'Y-m-d' ) ); // the listing stays in search and shows the "Reported closed" note (owner, 21 Oct 2026)
		aw_enrich_flag( $lid, 'reported_closed', wp_date( 'Y-m-d' ) );
		global $wpdb;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . aw_enrich_table() . " WHERE listing_id = %d AND field = 'closed' AND status IN ('auto-applied','approved')", $lid ) ) ) { // phpcs:ignore WordPress.DB
			aw_enrich_add_row( $lid, 'closed', 'Google Maps shows this place as permanently closed', '', $src, 'medium', 'auto-applied', $run, 'Shown on the listing as "Reported closed"; nothing to review' );
			$out['applied'][] = 'closed';
		}
		aw_enrich_after_change( $lid );
	}
	aw_enrich_duplicates( $lid );
	$used = array_values( array_unique( array_map( 'sanitize_key', (array) ( $b['sources_used'] ?? array() ) ) ) );
	update_post_meta( $lid, 'aw_enrich_checked', wp_date( 'Y-m-d H:i:s' ) );
	update_post_meta( $lid, 'aw_enrich_tried', $used );
	// Checked while Google was off (monthly quota used): it comes back first when Google is on, unless it already has its place.
	if ( ! empty( $b['google_skipped'] ) && '' === (string) get_post_meta( $lid, 'aw_place_id', true ) ) {
		update_post_meta( $lid, 'aw_enrich_google_pending', '1' );
	} else {
		delete_post_meta( $lid, 'aw_enrich_google_pending' );
	}
	return $out + array( 'protected' => $protected );
}

/** Another listing with the same place ID or Darpan ID (stored or waiting in the queue): flag both, never merge. */
function aw_enrich_duplicates( $lid ) {
	global $wpdb;
	$dups = array();
	foreach ( array( 'place_id' => 'aw_place_id', 'darpan_id' => 'aw_darpan_id' ) as $field => $key ) {
		$vals = array_filter( array( (string) get_post_meta( $lid, $key, true ) ) );
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT value FROM ' . aw_enrich_table() . " WHERE listing_id = %d AND field = %s AND status = 'pending'", $lid, $field ) ) as $v ) { // phpcs:ignore WordPress.DB
			$vals[] = (string) json_decode( $v, true );
		}
		foreach ( array_unique( array_filter( $vals ) ) as $v ) {
			$other = array_merge(
				$wpdb->get_col( $wpdb->prepare( "SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'aw_listing' AND p.post_status <> 'trash' WHERE m.meta_key = %s AND m.meta_value = %s AND m.post_id <> %d", $key, $v, $lid ) ), // phpcs:ignore WordPress.DB
				$wpdb->get_col( $wpdb->prepare( 'SELECT listing_id FROM ' . aw_enrich_table() . " WHERE field = %s AND value = %s AND status = 'pending' AND listing_id <> %d", $field, wp_json_encode( $v ), $lid ) ) // phpcs:ignore WordPress.DB
			);
			foreach ( array_unique( array_map( 'intval', $other ) ) as $o ) {
				$dups[ $o ] = $field;
				$theirs     = (array) ( aw_enrich_flags( $o )['duplicate'] ?? array() );
				$theirs[ $lid ] = $field;
				aw_enrich_flag( $o, 'duplicate', $theirs );
			}
		}
	}
	aw_enrich_flag( $lid, 'duplicate', $dups );
}


/**
 * Apply the owner's rule (21 Oct 2026) to proposals already waiting: a sourced value fills an empty field; a second
 * phone, email or website is kept next to the first, both unverified. Rows of claimed or verified listings and low
 * confidence rows stay for review. wp eval 'echo aw_enrich_policy_pass();'
 *
 * @return string What was done.
 */
function aw_enrich_policy_pass() {
	global $wpdb;
	$t = aw_enrich_table();
	$n = array( 'apply' => 0, 'both' => 0, 'queue' => 0, 'skip' => 0 );
	foreach ( $wpdb->get_results( "SELECT id FROM {$t} WHERE status = 'pending' AND field <> 'closed' ORDER BY id ASC", ARRAY_A ) as $x ) { // phpcs:ignore WordPress.DB
		$r = aw_enrich_row( (int) $x['id'] );
		if ( ! $r || 'pending' !== $r['status'] ) {
			continue;
		}
		$lid = (int) $r['listing_id'];
		if ( aw_enrich_identity_unresolved( $lid ) ) {
			++$n['queue'];
			continue;
		}
		$r['value'] = aw_enrich_union( $lid, $r['field'], $r['value'] );
		if ( aw_enrich_has_value( $lid, $r['field'], $r['value'] ) ) {
			aw_enrich_set_status( (int) $r['id'], 'superseded', 'Already on the listing' );
			$n['skip']++;
			continue;
		}
		$mode = aw_enrich_mode( $lid, $r['field'], $r['confidence'], aw_enrich_is_protected( $lid ), $r['value'] );
		if ( 'queue' === $mode ) {
			$n['queue']++;
			continue;
		}
		$snap = aw_enrich_snapshot( $lid, $r['field'] );
		if ( ! aw_enrich_write( $lid, $r['field'], $r['value'], $mode ) ) {
			$n['queue']++;
			continue;
		}
		$wpdb->update( $t, array( 'value' => wp_json_encode( $r['value'] ), 'old_value' => wp_json_encode( $snap ), 'status' => 'auto-applied', 'decided_at' => current_time( 'mysql' ), 'note' => 'both' === $mode ? aw_enrich_both_note( $r['field'] ) : 'Sourced value for an empty field (owner rule, 21 Oct 2026)' ), array( 'id' => (int) $r['id'] ) ); // phpcs:ignore WordPress.DB
		aw_enrich_supersede( $lid, $r['field'], (int) $r['id'] );
		$n[ $mode ]++;
	}
	return wp_json_encode( $n );
}
