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
		),
		'Online'  => array(
			'aw_website'   => array( 'Website', 'url', '' ),
			'aw_maps_url'  => array( 'Google Maps link', 'url', 'Share → Copy link in Google Maps' ),
			'aw_instagram' => array( 'Instagram', 'url', '' ),
			'aw_facebook'  => array( 'Facebook', 'url', '' ),
			'aw_x'         => array( 'X (Twitter)', 'url', '' ),
			'aw_youtube'   => array( 'YouTube', 'url', '' ),
		),
		'About'   => array(
			'aw_founded'      => array( 'Year established', 'text', 'e.g. 2014' ),
			'aw_head'         => array( 'Head of the organisation', 'text', 'Name and role, as the organisation publishes it' ),
			'aw_registration' => array( 'Registration', 'text', 'Trust/Society number, 12A, 80G, NGO Darpan ID' ),
			'aw_animals'      => array( 'Animals helped', 'text', 'Comma separated, e.g. dogs, cats, cattle, birds' ),
			'aw_services'     => array( 'Services', 'text', 'Comma separated, as the organisation states them' ),
			'aw_languages'    => array( 'Languages', 'text', 'Comma separated' ),
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
			echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label></th><td><input class="regular-text" type="' . esc_attr( $f[1] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) get_post_meta( $post->ID, $key, true ) ) . '">'
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
			$links .= '<a class="kit-btn kit-btn--secondary kit-btn--sm" href="' . esc_url( $m( $k ) ) . '" rel="noopener">' . aw_i( $l[0] ) . esc_html( $l[1] ) . '</a>';
		}
	}
	$photos = '';
	foreach ( array_slice( array_filter( array_map( 'absint', explode( ',', $m( 'aw_gallery' ) ) ) ), 0, 4 ) as $pid ) {
		$photos .= '<figure>' . wp_get_attachment_image( $pid, 'medium_large', false, array( 'loading' => 'lazy' ) ) . '</figure>';
	}
	$facts = '';
	foreach ( array( 'aw_founded' => 'Established', 'aw_head' => 'Head of the organisation', 'aw_registration' => 'Registration', 'aw_animals' => 'Animals helped', 'aw_hours' => 'Opening hours', 'aw_languages' => 'Languages' ) as $k => $label ) {
		if ( $m( $k ) ) {
			$facts .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $m( $k ) ) . '</dd></div>';
		}
	}
	if ( '' === $links . $photos . $facts ) {
		return '';
	}
	return '<section class="aw-org-details" aria-label="About the organisation">'
		. ( $links ? '<div class="aw-org-details-links">' . $links . '</div>' : '' )
		. ( $photos ? '<div class="aw-org-gallery">' . $photos . '</div>' : '' )
		. ( $facts ? '<dl class="aw-org-facts">' . $facts . '</dl>' : '' )
		. '</section>';
}
