<?php
/**
 * Organisation profile (directory listings): the details box in wp-admin and the block that shows them.
 *
 * Admin: "Organisation details" on every listing, grouped as Contact, Online, About, Photos and Support. Every field is
 * optional; empty fields print nothing, except phone, email, website, address, opening hours and services, which say
 * they are not available yet (with a link to suggest an update), and Directions, which shows greyed out. The short bio is the listing's excerpt (WordPress's own field).
 * Front end: block animal-writes/org-details — links (map, social, donate, volunteer), up to four photos, and the facts.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The fields in the box: group => [ key => [label, input type, hint] ]. */
function aw_listing_fields() {
	return array(
		'Contact' => array(
			'aw_email'    => array( 'Email', 'email', '' ),
			'aw_area'     => array( 'Area or landmark', 'text', 'No full street address' ),
		),
		'Online'  => array(
			'aw_website'   => array( 'Website', 'url', '' ),
			'aw_maps_url'  => array( 'Google Maps link', 'url', 'Share → Copy link in Google Maps' ),
			'aw_lat'       => array( 'Latitude', 'text', 'From Google Maps, e.g. 28.5797' ),
			'aw_lng'       => array( 'Longitude', 'text', 'e.g. 77.3167' ),
			'aw_place_id'  => array( 'Google place ID', 'text', '' ),
			'aw_g_rating'  => array( 'Google rating', 'text', 'Out of 5, e.g. 4.6' ),
			'aw_g_reviews' => array( 'Google reviews', 'text', 'How many reviews' ),
			'aw_g_category' => array( 'Google category', 'text', 'As on the Google profile, e.g. Animal shelter' ),
			'aw_g_profile'  => array( 'Google Business Profile link', 'url', 'The share.google link' ),
			'aw_instagram' => array( 'Instagram', 'url', '' ),
			'aw_facebook'  => array( 'Facebook', 'url', '' ),
			'aw_x'         => array( 'X (Twitter)', 'url', '' ),
			'aw_youtube'   => array( 'YouTube', 'url', '' ),
		),
		'About'   => array(
			'aw_founded'      => array( 'Year established', 'text', 'e.g. 2014' ),
			'aw_head'         => array( 'Head of the organisation', 'text', 'Name and role, as the organisation publishes it' ),
			'aw_registration' => array( 'Registration', 'text', 'Trust/Society registration number' ),
			'aw_reg_12a'      => array( '12A', 'text', '12A registration number' ),
			'aw_reg_80g'      => array( '80G', 'text', '80G registration number' ),
			'aw_darpan_id'    => array( 'NGO Darpan ID', 'text', 'e.g. KL/2017/0123456' ),
			'aw_animals'      => array( 'Animals helped', 'text', 'Comma separated, e.g. dogs, cats, cattle, birds' ),
			'aw_services'     => array( 'Services', 'text', 'Comma separated, as the organisation states them' ),
			'aw_languages'    => array( 'Languages', 'text', 'Comma separated' ),
		),
		'Links'   => array(
			'aw_rel_nofollow' => array( 'Mark this organisation\'s links nofollow', 'checkbox', 'For sponsored or paid listings' ),
		),
		'Support' => array(
			'aw_donate_url'    => array( 'Donate link', 'url', '' ),
			'aw_volunteer_url' => array( 'Volunteer link', 'url', '' ),
		),
	);
}

// With SCF active the "Organisation details" field group (acf-json/group_aw_listing.json) edits these same keys; this box
// is the fallback when SCF is off.
add_action(
	'add_meta_boxes_aw_listing',
	function () {
		if ( function_exists( 'aw_scf_active' ) && aw_scf_active() ) {
			return;
		}
		add_meta_box( 'aw-listing-details', 'Organisation details', 'aw_listing_details_box', 'aw_listing', 'normal', 'high' );
	}
);

function aw_listing_details_box( $post ) {
	wp_nonce_field( 'aw_listing_details', 'aw_listing_details_nonce' );
	wp_enqueue_media();
	echo '<p>Every field is optional; empty fields are not shown. The short bio is the Excerpt.</p>';
	foreach ( aw_listing_fields() as $group => $fields ) {
		echo '<h3 style="margin:1.25em 0 .5em">' . esc_html( $group ) . '</h3><table class="form-table" role="presentation"><tbody>';
		foreach ( $fields as $key => $f ) {
			$val     = (string) get_post_meta( $post->ID, $key, true );
			$control = 'checkbox' === $f[1] ? '<input type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '>' : ( 'textarea' === $f[1] ? '<textarea class="large-text" rows="3" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $val ) . '</textarea>' : '<input class="regular-text" type="' . esc_attr( $f[1] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '">' );
			echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label></th><td>' . $control // phpcs:ignore WordPress.Security.EscapeOutput
				. ( $f[2] ? '<p class="description">' . esc_html( $f[2] ) . '</p>' : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	// The two lists (SCF repeaters) as plain lines, for when SCF is off.
	$ph = implode( "\n", array_map( fn( $r ) => $r['number'] . ' | ' . $r['label'] . ' | ' . ( $r['whatsapp'] ? 'whatsapp' : '' ), aw_listing_phones( $post->ID ) ) );
	$hr = implode( "\n", array_map( fn( $r ) => $r['day'] . ' ' . ( $r['closed'] ? 'closed' : $r['open'] . '-' . $r['close'] ), aw_listing_hours( $post->ID ) ) );
	echo '<h3 style="margin:1.25em 0 .5em">Phone numbers and opening hours</h3><table class="form-table" role="presentation"><tbody>'
		. '<tr><th scope="row"><label for="aw_phones_lines">Phone numbers</label></th><td><textarea class="large-text" rows="3" id="aw_phones_lines" name="aw_phones_lines">' . esc_textarea( $ph ) . '</textarea><p class="description">One per line: number | label | whatsapp (the last two optional), e.g. 98765 43210 | Helpline | whatsapp</p></td></tr>'
		. '<tr><th scope="row"><label for="aw_hours_lines">Opening hours by day</label></th><td><textarea class="large-text" rows="4" id="aw_hours_lines" name="aw_hours_lines">' . esc_textarea( $hr ) . '</textarea><p class="description">One day per line: Monday 09:00-18:00, or Sunday closed. Open all day: 00:00-23:59.</p></td></tr>'
		. '</tbody></table>';
	$ids = (string) get_post_meta( $post->ID, 'aw_gallery', true );
	echo '<h3 style="margin:1.25em 0 .5em">Photos (up to 6)</h3><input type="hidden" id="aw_gallery" name="aw_gallery" value="' . esc_attr( $ids ) . '"><div id="aw-gallery-prev" style="display:flex;gap:8px;margin-bottom:8px">';
	foreach ( array_filter( array_map( 'absint', explode( ',', $ids ) ) ) as $id ) {
		echo wp_get_attachment_image( $id, array( 80, 80 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div><button type="button" class="button" id="aw-gallery-pick">Choose photos</button> <button type="button" class="button-link" id="aw-gallery-clear">Remove all</button>';
	echo "<script>(function(){var f,inp=document.getElementById('aw_gallery'),prev=document.getElementById('aw-gallery-prev');"
		. "document.getElementById('aw-gallery-pick').addEventListener('click',function(){f=f||wp.media({title:'Photos (up to 6)',multiple:true,library:{type:'image'}});"
		. "f.off('select').on('select',function(){var s=f.state().get('selection').toArray().slice(0,6);inp.value=s.map(function(a){return a.id}).join(',');prev.innerHTML='';s.forEach(function(a){var i=document.createElement('img');i.src=(a.attributes.sizes&&a.attributes.sizes.thumbnail?a.attributes.sizes.thumbnail.url:a.attributes.url);i.width=80;i.height=80;i.style.objectFit='cover';prev.appendChild(i);});});f.open();});"
		. "document.getElementById('aw-gallery-clear').addEventListener('click',function(){inp.value='';prev.innerHTML='';});})();</script>";
}

add_action(
	'save_post_aw_listing',
	function ( $id ) {
		if ( ! isset( $_POST['aw_listing_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_listing_details_nonce'] ) ), 'aw_listing_details' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$keys = array( 'aw_gallery' );
		foreach ( aw_listing_fields() as $fields ) {
			$keys = array_merge( $keys, array_keys( $fields ) );
		}
		foreach ( $keys as $key ) {
			$v = aw_sanitize_meta( wp_unslash( $_POST[ $key ] ?? '' ), $key ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'' === $v ? delete_post_meta( $id, $key ) : update_post_meta( $id, $key, $v );
		}
		$rows = array();
		foreach ( preg_split( '/\R/', (string) wp_unslash( $_POST['aw_phones_lines'] ?? '' ) ) as $line ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$p = array_map( 'trim', explode( '|', sanitize_text_field( $line ) ) );
			if ( '' !== $p[0] ) {
				$rows[] = array( 'number' => $p[0], 'label' => $p[1] ?? '', 'whatsapp' => 'whatsapp' === strtolower( $p[2] ?? '' ) );
			}
		}
		aw_set_listing_list( $id, 'aw_phones', $rows );
		$rows = array();
		foreach ( preg_split( '/\R/', (string) wp_unslash( $_POST['aw_hours_lines'] ?? '' ) ) as $line ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( preg_match( '/^\s*(' . implode( '|', AW_WEEKDAYS ) . ')\s+(?:(closed)|(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2}))\s*$/i', sanitize_text_field( $line ), $x ) ) {
				$rows[] = array( 'day' => ucfirst( strtolower( $x[1] ) ), 'open' => $x[3] ?? '', 'close' => $x[4] ?? '', 'closed' => ! empty( $x[2] ) );
			}
		}
		aw_set_listing_list( $id, 'aw_opening_hours', $rows );
	}
);

/* ---------- Front end: animal-writes/org-details ---------- */

/** Links (map, social, donate, volunteer), photos and facts of an organisation. */
function aw_block_org_details( $a, $id ) {
	$m     = fn( $k ) => trim( (string) get_post_meta( $id, $k, true ) );
	$links = '';
	foreach ( array( 'aw_maps_url' => array( 'map-pin', 'Directions' ), 'aw_instagram' => array( 'instagram', 'Instagram' ), 'aw_facebook' => array( 'facebook', 'Facebook' ), 'aw_x' => array( 'x-brand', 'X' ), 'aw_youtube' => array( 'youtube', 'YouTube' ), 'aw_linkedin' => array( 'link', 'LinkedIn' ), 'aw_donate_url' => array( 'heart', 'Donate' ), 'aw_volunteer_url' => array( 'name', 'Volunteer' ), 'aw_adopt_url' => array( 'home', 'Adopt' ) ) as $k => $l ) {
		if ( $m( $k ) ) {
			$rel    = 'noopener' . ( $m( 'aw_rel_nofollow' ) || get_post_meta( $id, 'aw_sponsored', true ) ? ' nofollow sponsored' : '' );
			$links .= '<a class="kit-btn kit-btn--secondary kit-btn--sm" href="' . esc_url( $m( $k ) ) . '" rel="' . esc_attr( $rel ) . '" data-act="' . esc_attr( substr( $k, 3 ) ) . '">' . aw_i( $l[0] ) . esc_html( $l[1] ) . '</a>'; // data-act: analytics (aw-analytics.js)
		} elseif ( 'aw_maps_url' === $k ) { // no map link yet: Directions stays, greyed out and not a link (owner, 15 Oct 2026)
			$links .= '<span class="kit-btn kit-btn--secondary kit-btn--sm" role="link" aria-disabled="true" data-act="maps_url">' . aw_i( $l[0] ) . esc_html( $l[1] ) . '</span>';
		}
	}
	$photos = '';
	foreach ( array_slice( array_filter( array_map( 'absint', explode( ',', $m( 'aw_gallery' ) ) ) ), 0, 6 ) as $pid ) {
		$cap     = trim( (string) wp_get_attachment_caption( $pid ) );
		$cap     = $cap ? $cap : trim( (string) get_post_meta( $pid, '_wp_attachment_image_alt', true ) ); // the caption, else the description of the photo
		$photos .= '<figure><div class="aw-org-photo">' . wp_get_attachment_image( $pid, 'medium_large', false, array( 'loading' => 'lazy' ) ) . '</div>' . ( $cap ? '<figcaption>' . esc_html( $cap ) . '</figcaption>' : '' ) . '</figure>';
	}
	$facts = '';
	$svc   = aw_listing_service_names( $id ); // the fixed services list, as tags
	// Contact details as rows (the number, address or link), and the details most people look for say when they are missing
	// (owner, 15 Oct 2026): "… not available yet", with a link to suggest an update (the Report form).
	$rel     = 'noopener' . ( $m( 'aw_rel_nofollow' ) || get_post_meta( $id, 'aw_sponsored', true ) ? ' nofollow sponsored' : '' );
	$phones  = aw_listing_phones( $id ); // the Phone numbers list
	$hours   = aw_org_hours_html( aw_listing_hours( $id ) ); // the Opening hours by day list
	$contact = array(
		'aw_phone'   => array( 'phone', 1 < count( $phones ) ? 'Phone numbers' : 'Phone', aw_org_phones_html( $phones ) ),
		'aw_email'   => array( 'mail', 'Email', $m( 'aw_email' ) ? '<a href="' . esc_url( 'mailto:' . $m( 'aw_email' ) ) . '" data-act="email">' . esc_html( $m( 'aw_email' ) ) . '</a>' : '' ),
		'aw_website' => array( 'link', 'Website', $m( 'aw_website' ) ? '<a href="' . esc_url( $m( 'aw_website' ) ) . '" rel="' . esc_attr( $rel ) . '" data-act="website">' . esc_html( preg_replace( '#^https?://(www\.)?|/$#', '', $m( 'aw_website' ) ) ) . '</a>' : '' ),
	);
	$na      = array( 'aw_phone' => 'Phone number not available yet', 'aw_email' => 'Email address not available yet', 'aw_website' => 'Website not available yet', 'aw_area' => 'Address not available yet', 'aw_hours' => 'Opening hours not available yet', 'aw_services' => 'Services not available yet' );
	$suggest = function_exists( 'aw_report_url' ) ? '<a class="aw-org-fact-note" href="' . esc_url( aw_report_url( $id ) ) . '" rel="nofollow">Suggest an update</a>' : '';
	$missing = fn( $k ) => '<span class="aw-org-fact-na">' . esc_html( $na[ $k ] ) . '</span>' . $suggest;
	$facts   = '';
	foreach ( $contact as $k => $c ) {
		$facts .= '<div class="aw-org-fact"><dt><span class="aw-org-fact-ico">' . aw_i( $c[0] ) . '</span>' . esc_html( $c[1] ) . '</dt><dd>' . ( '' !== $c[2] ? $c[2] : $missing( $k ) ) . '</dd></div>';
	}
	$icons = array( 'aw_g_rating' => 'star', 'aw_services' => 'check', 'aw_animals' => 'heart', 'aw_area' => 'map-pin', 'aw_founded' => 'calendar', 'aw_head' => 'name', 'aw_registration' => 'research', 'aw_reg_12a' => 'research', 'aw_reg_80g' => 'research', 'aw_darpan_id' => 'research', 'aw_fcra' => 'research', 'aw_reg_type' => 'research', 'aw_areas_served' => 'map-pin', 'aw_upi' => 'heart', 'aw_hours' => 'clock', 'aw_languages' => 'world' );
	foreach ( array( 'aw_g_rating' => 'On Google', 'aw_services' => 'Services', 'aw_animals' => 'Animals helped', 'aw_area' => 'Location', 'aw_areas_served' => 'Areas served', 'aw_founded' => 'Established', 'aw_head' => 'Head of the organisation', 'aw_reg_type' => 'Registered as', 'aw_registration' => 'Registration', 'aw_reg_12a' => '12A', 'aw_reg_80g' => '80G', 'aw_fcra' => 'FCRA', 'aw_darpan_id' => 'NGO Darpan ID', 'aw_upi' => 'Donate by UPI', 'aw_hours' => 'Opening hours', 'aw_languages' => 'Languages' ) as $k => $label ) {
		if ( 'aw_hours' === $k ) { // the hours table, from the list only
			$facts .= '<div class="aw-org-fact' . ( $hours ? ' aw-org-fact--wide' : '' ) . '"><dt><span class="aw-org-fact-ico">' . aw_i( $icons[ $k ] ) . '</span>' . esc_html( $label ) . '</dt><dd>' . ( $hours ? $hours : $missing( $k ) ) . '</dd></div>';
		} elseif ( $m( $k ) || ( 'aw_services' === $k && $svc ) ) {
			$note = '';
			if ( 'aw_services' === $k && $m( 'aw_services_source' ) ) { // who said so, and when
				$note = '<span class="aw-org-fact-note">As stated by ' . esc_html( 'organisation' === $m( 'aw_services_source' ) ? 'the organisation' : $m( 'aw_services_source' ) ) . ( $m( 'aw_services_on' ) ? ', ' . aw_date( $m( 'aw_services_on' ) ) : '' ) . '</span>';
			}
			$val = 'aw_g_rating' === $k ? $m( 'aw_g_rating' ) . ' out of 5' . ( $m( 'aw_g_reviews' ) ? ' (' . number_format_i18n( (int) $m( 'aw_g_reviews' ) ) . ' reviews)' : '' ) : ( 'aw_area' === $k && $m( 'aw_address' ) ? $m( 'aw_address' ) : ( 'aw_area' === $k && function_exists( 'aw_listing_place' ) ? aw_listing_location_text( $m( 'aw_area' ), aw_listing_place( $id ), $m( 'aw_pincode' ) ) : $m( $k ) ) );
			$shown  = 'aw_services' === $k && $svc ? '<span class="kit-tags aw-org-services">' . implode( '', array_map( fn( $n ) => '<span class="kit-tag kit-tag--type">' . esc_html( $n ) . '</span>', $svc ) ) . '</span>' : esc_html( $val );
			$facts .= '<div class="aw-org-fact"><dt><span class="aw-org-fact-ico">' . aw_i( $icons[ $k ] ) . '</span>' . esc_html( $label ) . '</dt><dd>' . $shown . $note . '</dd></div>';
		} elseif ( isset( $na[ $k ] ) ) {
			$facts .= '<div class="aw-org-fact"><dt><span class="aw-org-fact-ico">' . aw_i( $icons[ $k ] ) . '</span>' . esc_html( $label ) . '</dt><dd>' . $missing( $k ) . '</dd></div>';
		}
	}
	$extra = function_exists( 'aw_enrich_profile_extras' ) ? aw_enrich_profile_extras( $id ) : ''; // live Google block, signals, news mentions
	if ( '' === $links . $photos . $facts . $extra ) {
		return '';
	}
	return '<section class="aw-org-details" aria-label="About the organisation">'
		. ( $links ? '<div class="aw-org-details-links">' . $links . '</div>' : '' )
		. ( $photos ? '<div class="aw-org-gallery">' . $photos . '</div>' : '' )
		. ( $facts ? '<h2 class="aw-profile-h">Details</h2><dl class="aw-org-facts">' . $facts . '</dl>' : '' )
		. $extra
		. '</section>';
}

/**
 * The phone numbers: each a Call link (tel:), with its label, and a WhatsApp link when the row is flagged. The links are
 * what the site's analytics count (call_click on tel:, whatsapp_click on wa.me; kit.js).
 *
 * @param array $phones aw_listing_phones().
 * @return string HTML, or '' when there is no number that can be dialled.
 */
function aw_org_phones_html( $phones ) {
	$items = '';
	foreach ( $phones as $p ) {
		$tel = aw_tel_url( $p['number'] );
		if ( '' === $tel ) {
			continue;
		}
		$wa     = $p['whatsapp'] ? aw_normalise_phone( $p['number'] ) : '';
		$items .= '<li><a href="' . esc_url( $tel ) . '" data-act="phone">' . esc_html( $p['number'] ) . '</a>'
			. ( '' !== $p['label'] ? ' <span class="aw-org-phones-label">' . esc_html( $p['label'] ) . '</span>' : '' )
			. ( $wa && strlen( $wa ) > 5 ? ' <a class="aw-org-phones-wa" href="' . esc_url( 'https://wa.me/' . $wa ) . '" rel="noopener" data-act="whatsapp" aria-label="' . esc_attr( 'WhatsApp ' . $p['number'] ) . '">' . aw_i( 'brand-whatsapp' ) . 'WhatsApp</a>' : '' )
			. '</li>';
	}
	return $items ? '<ul class="aw-org-phones">' . $items . '</ul>' : '';
}

/**
 * Opening hours by day as a small table: one row per day (Monday first), split days as two times, "Closed", or "Open 24
 * hours" for 00:00 to 23:59. Days with no row are not stated, so they are not listed.
 *
 * @param array $rows aw_listing_hours().
 * @return string
 */
function aw_org_hours_html( $rows ) {
	if ( ! $rows ) {
		return '';
	}
	$fmt  = fn( $t ) => wp_date( 'g:i a', strtotime( '2000-01-01 ' . $t . ':00 UTC' ), new DateTimeZone( 'UTC' ) );
	$days = array();
	foreach ( $rows as $r ) {
		$days[ $r['day'] ][] = $r['closed'] ? 'Closed' : ( '00:00' === $r['open'] && '23:59' === $r['close'] ? 'Open 24 hours' : $fmt( $r['open'] ) . ' – ' . $fmt( $r['close'] ) );
	}
	$trs = '';
	foreach ( AW_WEEKDAYS as $d ) {
		if ( isset( $days[ $d ] ) ) {
			$closed = array( 'Closed' ) === array_unique( $days[ $d ] );
			$trs   .= '<tr' . ( $closed ? ' class="aw-org-hours-closed"' : '' ) . '><th scope="row">' . esc_html( $d ) . '</th><td>' . esc_html( implode( ', ', array_unique( $days[ $d ] ) ) ) . '</td></tr>';
		}
	}
	return '<table class="aw-org-hours"><caption class="screen-reader-text">Opening hours by day</caption><tbody>' . $trs . '</tbody></table>';
}

// The description gets its heading ("About"), so the page reads: name, details, about.
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'aw_listing' ) || ! in_the_loop() || ! is_main_query() || '' === trim( wp_strip_all_tags( $content ) ) ) {
			return $content;
		}
		return '<h2 class="aw-profile-h">About</h2>' . $content;
	},
	5
);

/**
 * "Area, District, State, PIN" with each place named once (an import's area often already names the district and state).
 *
 * @param string $area    Area or landmark.
 * @param array  $place   [state, district] names.
 * @param string $pincode PIN code.
 * @return string
 */
function aw_listing_location_text( $area, $place, $pincode ) {
	$out = array();
	foreach ( array_merge( array_map( 'trim', explode( ',', (string) $area ) ), array_reverse( array_filter( (array) $place ) ), array( $pincode ) ) as $part ) {
		if ( '' !== $part && ! in_array( strtolower( $part ), array_map( 'strtolower', $out ), true ) ) {
			$out[] = $part;
		}
	}
	return implode( ', ', $out );
}
