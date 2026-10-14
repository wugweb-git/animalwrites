<?php
/**
 * Organisation profile (directory listings): the details box in wp-admin and the block that shows them.
 *
 * Admin: "Organisation details" on every listing, grouped as Contact, Online, About, Photos and Support. Every field is
 * optional; empty fields print nothing. The short bio is the listing's excerpt (WordPress's own field).
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
			'aw_phone'    => array( 'Phone', 'tel', '' ),
			'aw_whatsapp' => array( 'WhatsApp', 'tel', '' ),
			'aw_email'    => array( 'Email', 'email', '' ),
			'aw_area'     => array( 'Area or landmark', 'text', 'No full street address' ),
			'aw_hours'    => array( 'Opening hours', 'text', 'e.g. Mon–Sat 9 am – 6 pm; emergencies 24 hours' ),
			'aw_hours_spec' => array( 'Opening hours (exact)', 'textarea', 'One line per day range, e.g. Mon-Fri 09:00-18:00 and Sat 10:00-14:00' ),
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

add_action(
	'add_meta_boxes_aw_listing',
	function () {
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
	$ids = (string) get_post_meta( $post->ID, 'aw_gallery', true );
	echo '<h3 style="margin:1.25em 0 .5em">Photos (up to 4)</h3><input type="hidden" id="aw_gallery" name="aw_gallery" value="' . esc_attr( $ids ) . '"><div id="aw-gallery-prev" style="display:flex;gap:8px;margin-bottom:8px">';
	foreach ( array_filter( array_map( 'absint', explode( ',', $ids ) ) ) as $id ) {
		echo wp_get_attachment_image( $id, array( 80, 80 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div><button type="button" class="button" id="aw-gallery-pick">Choose photos</button> <button type="button" class="button-link" id="aw-gallery-clear">Remove all</button>';
	echo "<script>(function(){var f,inp=document.getElementById('aw_gallery'),prev=document.getElementById('aw-gallery-prev');"
		. "document.getElementById('aw-gallery-pick').addEventListener('click',function(){f=f||wp.media({title:'Photos (up to 4)',multiple:true,library:{type:'image'}});"
		. "f.off('select').on('select',function(){var s=f.state().get('selection').toArray().slice(0,4);inp.value=s.map(function(a){return a.id}).join(',');prev.innerHTML='';s.forEach(function(a){var i=document.createElement('img');i.src=(a.attributes.sizes&&a.attributes.sizes.thumbnail?a.attributes.sizes.thumbnail.url:a.attributes.url);i.width=80;i.height=80;i.style.objectFit='cover';prev.appendChild(i);});});f.open();});"
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
	}
);

/* ---------- Front end: animal-writes/org-details ---------- */

/** Links (map, social, donate, volunteer), photos and facts of an organisation. */
function aw_block_org_details( $a, $id ) {
	$m     = fn( $k ) => trim( (string) get_post_meta( $id, $k, true ) );
	$links = '';
	foreach ( array( 'aw_maps_url' => array( 'map-pin', 'Directions' ), 'aw_instagram' => array( 'instagram', 'Instagram' ), 'aw_facebook' => array( 'facebook', 'Facebook' ), 'aw_x' => array( 'x', 'X' ), 'aw_youtube' => array( 'youtube', 'YouTube' ), 'aw_donate_url' => array( 'heart', 'Donate' ), 'aw_volunteer_url' => array( 'name', 'Volunteer' ) ) as $k => $l ) {
		if ( $m( $k ) ) {
			$rel    = 'noopener' . ( $m( 'aw_rel_nofollow' ) || get_post_meta( $id, 'aw_sponsored', true ) ? ' nofollow sponsored' : '' );
			$links .= '<a class="kit-btn kit-btn--secondary kit-btn--sm" href="' . esc_url( $m( $k ) ) . '" rel="' . esc_attr( $rel ) . '">' . aw_i( $l[0] ) . esc_html( $l[1] ) . '</a>';
		}
	}
	$photos = '';
	foreach ( array_slice( array_filter( array_map( 'absint', explode( ',', $m( 'aw_gallery' ) ) ) ), 0, 4 ) as $pid ) {
		$cap     = trim( (string) wp_get_attachment_caption( $pid ) );
		$cap     = $cap ? $cap : trim( (string) get_post_meta( $pid, '_wp_attachment_image_alt', true ) ); // the caption, else the description of the photo
		$photos .= '<figure><div class="aw-org-photo">' . wp_get_attachment_image( $pid, 'medium_large', false, array( 'loading' => 'lazy' ) ) . '</div>' . ( $cap ? '<figcaption>' . esc_html( $cap ) . '</figcaption>' : '' ) . '</figure>';
	}
	$facts = '';
	$svc   = wp_get_object_terms( $id, 'aw_service', array( 'fields' => 'names' ) ); // the fixed services list, as tags
	$svc   = is_wp_error( $svc ) ? array() : $svc;
	$icons = array( 'aw_g_rating' => 'star', 'aw_services' => 'check', 'aw_animals' => 'heart', 'aw_area' => 'map-pin', 'aw_founded' => 'calendar', 'aw_head' => 'name', 'aw_registration' => 'research', 'aw_reg_12a' => 'research', 'aw_reg_80g' => 'research', 'aw_darpan_id' => 'research', 'aw_hours' => 'clock', 'aw_languages' => 'world' );
	foreach ( array( 'aw_g_rating' => 'On Google', 'aw_services' => 'Services', 'aw_animals' => 'Animals helped', 'aw_area' => 'Location', 'aw_founded' => 'Established', 'aw_head' => 'Head of the organisation', 'aw_registration' => 'Registration', 'aw_reg_12a' => '12A', 'aw_reg_80g' => '80G', 'aw_darpan_id' => 'NGO Darpan ID', 'aw_hours' => 'Opening hours', 'aw_languages' => 'Languages' ) as $k => $label ) {
		if ( $m( $k ) || ( 'aw_services' === $k && $svc ) ) {
			$note = '';
			if ( 'aw_services' === $k && $m( 'aw_services_source' ) ) { // who said so, and when
				$note = '<span class="aw-org-fact-note">As stated by ' . esc_html( 'organisation' === $m( 'aw_services_source' ) ? 'the organisation' : $m( 'aw_services_source' ) ) . ( $m( 'aw_services_on' ) ? ', ' . aw_date( $m( 'aw_services_on' ) ) : '' ) . '</span>';
			}
			$val = 'aw_g_rating' === $k ? $m( 'aw_g_rating' ) . ' out of 5' . ( $m( 'aw_g_reviews' ) ? ' (' . number_format_i18n( (int) $m( 'aw_g_reviews' ) ) . ' reviews)' : '' ) : ( 'aw_area' === $k && function_exists( 'aw_listing_place' ) ? implode( ', ', array_filter( array_merge( array( $m( 'aw_area' ) ), array_reverse( array_filter( aw_listing_place( $id ) ) ), array( $m( 'aw_pincode' ) ) ) ) ) : $m( $k ) );
			$shown  = 'aw_services' === $k && $svc ? '<span class="kit-tags aw-org-services">' . implode( '', array_map( fn( $n ) => '<span class="kit-tag kit-tag--type">' . esc_html( $n ) . '</span>', $svc ) ) . '</span>' : esc_html( $val );
			$facts .= '<div class="aw-org-fact"><span class="aw-org-fact-ico">' . aw_i( $icons[ $k ] ) . '</span><dt>' . esc_html( $label ) . '</dt><dd>' . $shown . $note . '</dd></div>';
		}
	}
	if ( '' === $links . $photos . $facts ) {
		return '';
	}
	return '<section class="aw-org-details" aria-label="About the organisation">'
		. ( $links ? '<div class="aw-org-details-links">' . $links . '</div>' : '' )
		. ( $photos ? '<div class="aw-org-gallery">' . $photos . '</div>' : '' )
		. ( $facts ? '<h2 class="aw-profile-h">Details</h2><dl class="aw-org-facts">' . $facts . '</dl>' : '' )
		. '</section>';
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

