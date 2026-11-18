<?php
/**
 * Organisation profile (directory listings): the details box in wp-admin and the block that shows them.
 *
 * Admin: "Organisation profile" on every listing — a one-pager (identity header, then field groups as cards) grouped
 * as Contact, Online, About, Support, Links and Photos. Every field is
 * optional; empty fields print nothing, except phone, email, website, address, opening hours and services, which say
 * they are not available yet (with a link to suggest an update), and Directions, which shows greyed out. The short bio is the listing's excerpt (WordPress's own field).
 * Front end: block directoryweb/org-details — links (map, social, donate, volunteer), up to four photos, and the facts.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The fields in the box: group => [ key => [label, input type, hint] ]. */
function directoryweb_listing_fields() {
	return array(
		'Contact' => array(
			'directoryweb_email' => array( 'Email', 'email', '' ),
			'directoryweb_area'  => array( 'Area or landmark', 'text', 'No full street address' ),
		),
		'Online'  => array(
			'directoryweb_website'    => array( 'Website', 'url', '' ),
			'directoryweb_maps_url'   => array( 'Google Maps link', 'url', 'Share → Copy link in Google Maps' ),
			'directoryweb_lat'        => array( 'Latitude', 'text', 'From Google Maps, e.g. 28.5797' ),
			'directoryweb_lng'        => array( 'Longitude', 'text', 'e.g. 77.3167' ),
			'directoryweb_place_id'   => array( 'Google place ID', 'text', '' ),
			'directoryweb_g_rating'   => array( 'Google rating', 'text', 'Out of 5, e.g. 4.6' ),
			'directoryweb_g_reviews'  => array( 'Google reviews', 'text', 'How many reviews' ),
			'directoryweb_g_category' => array( 'Google category', 'text', 'As on the Google profile, e.g. Animal shelter' ),
			'directoryweb_g_profile'  => array( 'Google Business Profile link', 'url', 'The share.google link' ),
			'directoryweb_instagram'  => array( 'Instagram', 'url', '' ),
			'directoryweb_facebook'   => array( 'Facebook', 'url', '' ),
			'directoryweb_x'          => array( 'X (Twitter)', 'url', '' ),
			'directoryweb_youtube'    => array( 'YouTube', 'url', '' ),
		),
		'About'   => array(
			'directoryweb_founded'      => array( 'Year established', 'text', 'e.g. 2014' ),
			'directoryweb_head'         => array( 'Head of the organisation', 'text', 'Name and role, as the organisation publishes it' ),
			'directoryweb_registration' => array( 'Registration', 'text', 'Trust/Society registration number' ),
			'directoryweb_reg_12a'      => array( '12A', 'text', '12A registration number' ),
			'directoryweb_reg_80g'      => array( '80G', 'text', '80G registration number' ),
			'directoryweb_darpan_id'    => array( 'NGO Darpan ID', 'text', 'e.g. KL/2017/0123456' ),
			'directoryweb_animals'      => array( 'Animals helped', 'text', 'Comma separated, e.g. dogs, cats, cattle, birds' ),
			'directoryweb_services'     => array( 'Services', 'text', 'Comma separated, as the organisation states them' ),
			'directoryweb_languages'    => array( 'Languages', 'text', 'Comma separated' ),
		),
		'Links'   => array(
			'directoryweb_rel_nofollow' => array( 'Mark this organisation\'s links nofollow', 'checkbox', 'For sponsored or paid listings' ),
		),
		'Support' => array(
			'directoryweb_donate_url'    => array( 'Donate link', 'url', '' ),
			'directoryweb_volunteer_url' => array( 'Volunteer link', 'url', '' ),
		),
	);
}

// With SCF active the listing field groups (acf-json/group_aw_listing*.json: Contact and seven more) edit these same keys; this box
// is the fallback when SCF is off.
add_action(
	'add_meta_boxes_directoryweb_listing',
	function () {
		if ( function_exists( 'directoryweb_scf_active' ) && directoryweb_scf_active() ) {
			return;
		}
		add_meta_box( 'directoryweb-listing-details', __( 'Organisation profile', 'directoryweb' ), 'directoryweb_listing_details_box', 'directoryweb_listing', 'normal', 'high' );
	}
);

/** The card the one-pager lays each field group into: group key => [ title, hint ]. Order sets the layout. */
function directoryweb_listing_cards() {
	return array(
		'Contact' => array( __( 'Contact', 'directoryweb' ), __( 'How people reach the organisation.', 'directoryweb' ) ),
		'Online'  => array( __( 'Online & social', 'directoryweb' ), __( 'Website, map and social profiles.', 'directoryweb' ) ),
		'About'   => array( __( 'About & registration', 'directoryweb' ), __( 'Who they are and how they are registered.', 'directoryweb' ) ),
		'Support' => array( __( 'Support', 'directoryweb' ), __( 'Ways people can help.', 'directoryweb' ) ),
		'Links'   => array( __( 'Link visibility', 'directoryweb' ), '' ),
	);
}

/**
 * One field's control (label, input, hint) for the one-pager.
 *
 * @param string $key Meta key.
 * @param array  $f   [ label, input type, hint ] from directoryweb_listing_fields().
 * @param string $val Current value.
 * @return string
 */
function directoryweb_listing_control( $key, $f, $val ) {
	if ( 'checkbox' === $f[1] ) {
		return '<label class="directoryweb-profile__check" for="' . esc_attr( $key ) . '"><input type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '> ' . esc_html( $f[0] ) . '</label>'
			. ( $f[2] ? '<p class="description">' . esc_html( $f[2] ) . '</p>' : '' );
	}
	$input = 'textarea' === $f[1]
		? '<textarea class="widefat" rows="3" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $val ) . '</textarea>'
		: '<input class="widefat" type="' . esc_attr( $f[1] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '">';
	return '<label class="directoryweb-profile__label" for="' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label>' . $input
		. ( $f[2] ? '<p class="description">' . esc_html( $f[2] ) . '</p>' : '' );
}

/**
 * The listing edit screen's "Organisation profile" box: a one-pager (an identity header, then field groups as cards in
 * a responsive grid), modelled on a CRM contact profile rather than one long column of form-tables.
 *
 * @param WP_Post $post The listing being edited.
 */
function directoryweb_listing_details_box( $post ) {
	wp_nonce_field( 'directoryweb_listing_details', 'directoryweb_listing_details_nonce' );
	wp_enqueue_media();
	$fields   = directoryweb_listing_fields();
	$cards    = directoryweb_listing_cards();
	$logo     = get_the_post_thumbnail( $post, array( 72, 72 ), array( 'class' => 'directoryweb-profile__logo-img' ) );
	$title    = get_the_title( $post );
	$statuses = get_post_statuses();
	$status   = $statuses[ get_post_status( $post ) ] ?? ucfirst( (string) get_post_status( $post ) );
	$badges   = '';
	if ( get_post_meta( $post->ID, 'directoryweb_verified', true ) ) {
		$badges .= '<span class="directoryweb-profile__badge">' . esc_html__( 'Claimed', 'directoryweb' ) . '</span>';
	}
	if ( get_post_meta( $post->ID, 'directoryweb_gov_verified_on', true ) ) {
		$badges .= '<span class="directoryweb-profile__badge directoryweb-profile__badge--gov">' . esc_html__( 'Verified', 'directoryweb' ) . '</span>';
	}

	directoryweb_listing_profile_css();
	echo '<div class="directoryweb-profile">';

	// Identity header.
	echo '<div class="directoryweb-profile__head">'
		. '<div class="directoryweb-profile__logo">' . ( $logo ? $logo : '<span class="directoryweb-profile__logo-ph dashicons dashicons-location"></span>' ) // phpcs:ignore WordPress.Security.EscapeOutput -- get_the_post_thumbnail is safe; dashicon markup is static.
		. '</div><div class="directoryweb-profile__id">'
		. '<strong class="directoryweb-profile__name">' . ( '' !== $title ? esc_html( $title ) : esc_html__( '(no name yet)', 'directoryweb' ) ) . '</strong>'
		. '<div class="directoryweb-profile__meta"><span class="directoryweb-profile__status">' . esc_html( $status ) . '</span>' . $badges // phpcs:ignore WordPress.Security.EscapeOutput -- badge markup escaped above.
		. '</div></div></div>';
	echo '<p class="description directoryweb-profile__note">' . esc_html__( 'Every field is optional; empty fields are not shown on the listing. The short bio is the Excerpt.', 'directoryweb' ) . '</p>';

	echo '<div class="directoryweb-profile__grid">';
	foreach ( $cards as $group => $card ) {
		if ( empty( $fields[ $group ] ) ) {
			continue;
		}
		echo '<section class="directoryweb-card"><h3 class="directoryweb-card__h">' . esc_html( $card[0] ) . '</h3>'
			. ( $card[1] ? '<p class="directoryweb-card__sub">' . esc_html( $card[1] ) . '</p>' : '' )
			. '<div class="directoryweb-card__body">';
		foreach ( $fields[ $group ] as $key => $f ) {
			echo '<div class="directoryweb-field directoryweb-field--' . esc_attr( $f[1] ) . '">' . directoryweb_listing_control( $key, $f, (string) get_post_meta( $post->ID, $key, true ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- control built with escaping above.
		}
		if ( 'Contact' === $group ) {
			$ph = implode( "\n", array_map( fn( $r ) => $r['number'] . ' | ' . $r['label'] . ' | ' . ( $r['whatsapp'] ? 'whatsapp' : '' ), directoryweb_listing_phones( $post->ID ) ) );
			$hr = implode( "\n", array_map( fn( $r ) => $r['day'] . ' ' . ( $r['closed'] ? 'closed' : $r['open'] . '-' . $r['close'] ), directoryweb_listing_hours( $post->ID ) ) );
			echo '<div class="directoryweb-field"><label class="directoryweb-profile__label" for="directoryweb_phones_lines">' . esc_html__( 'Phone numbers', 'directoryweb' ) . '</label>'
				. '<textarea class="widefat" rows="3" autocomplete="off" id="directoryweb_phones_lines" name="directoryweb_phones_lines">' . esc_textarea( $ph ) . '</textarea>'
				. '<p class="description">' . esc_html__( 'One per line: number | label | whatsapp (the last two optional), e.g. 98765 43210 | Helpline | whatsapp', 'directoryweb' ) . '</p></div>';
			echo '<div class="directoryweb-field"><label class="directoryweb-profile__label" for="directoryweb_hours_lines">' . esc_html__( 'Opening hours by day', 'directoryweb' ) . '</label>'
				. '<textarea class="widefat" rows="4" autocomplete="off" id="directoryweb_hours_lines" name="directoryweb_hours_lines">' . esc_textarea( $hr ) . '</textarea>'
				. '<p class="description">' . esc_html__( 'One day per line: Monday 09:00-18:00, or Sunday closed. Open all day: 00:00-23:59.', 'directoryweb' ) . '</p></div>';
		}
		echo '</div></section>';
	}

	// Photos card.
	$ids = (string) get_post_meta( $post->ID, 'directoryweb_gallery', true );
	echo '<section class="directoryweb-card"><h3 class="directoryweb-card__h">' . esc_html__( 'Photos', 'directoryweb' ) . '</h3>'
		. '<p class="directoryweb-card__sub">' . esc_html__( 'Up to six photos of the organisation.', 'directoryweb' ) . '</p>'
		. '<div class="directoryweb-card__body"><input type="hidden" id="directoryweb_gallery" name="directoryweb_gallery" value="' . esc_attr( $ids ) . '">'
		. '<div id="directoryweb-gallery-prev" class="directoryweb-profile__gallery">';
	foreach ( array_filter( array_map( 'absint', explode( ',', $ids ) ) ) as $id ) {
		echo wp_get_attachment_image( $id, array( 80, 80 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div><p><button type="button" class="button" id="directoryweb-gallery-pick">' . esc_html__( 'Choose photos', 'directoryweb' ) . '</button> <button type="button" class="button-link" id="directoryweb-gallery-clear">' . esc_html__( 'Remove all', 'directoryweb' ) . '</button></p></div></section>';

	echo '</div></div>'; // .directoryweb-profile__grid, .directoryweb-profile
	echo "<script>(function(){var f,inp=document.getElementById('directoryweb_gallery'),prev=document.getElementById('directoryweb-gallery-prev');"
		. "document.getElementById('directoryweb-gallery-pick').addEventListener('click',function(){f=f||wp.media({title:'Photos (up to 6)',multiple:true,library:{type:'image'}});"
		. "f.off('select').on('select',function(){var s=f.state().get('selection').toArray().slice(0,6);inp.value=s.map(function(a){return a.id}).join(',');prev.innerHTML='';s.forEach(function(a){var i=document.createElement('img');i.src=(a.attributes.sizes&&a.attributes.sizes.thumbnail?a.attributes.sizes.thumbnail.url:a.attributes.url);i.width=80;i.height=80;i.style.objectFit='cover';prev.appendChild(i);});});f.open();});"
		. "document.getElementById('directoryweb-gallery-clear').addEventListener('click',function(){inp.value='';prev.innerHTML='';});})();</script>";
}

/** The one-pager's scoped styles, printed once. Admin palette only (WordPress CSS variables), so it follows the colour scheme. */
function directoryweb_listing_profile_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
<style>
.directoryweb-profile__head{display:flex;align-items:center;gap:14px;padding:4px 0 14px;border-bottom:1px solid #dcdcde;margin-bottom:14px}
.directoryweb-profile__logo-img{border-radius:8px;object-fit:cover;display:block}
.directoryweb-profile__logo-ph{width:72px;height:72px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#f0f0f1;color:#8c8f94;font-size:34px}
.directoryweb-profile__logo-ph:before{line-height:72px}
.directoryweb-profile__name{font-size:18px;line-height:1.3}
.directoryweb-profile__meta{display:flex;align-items:center;gap:8px;margin-top:5px;flex-wrap:wrap}
.directoryweb-profile__status{color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.03em}
.directoryweb-profile__badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600;background:#f0f6fc;color:#0a4b78;border:1px solid #c5d9ed}
.directoryweb-profile__badge--gov{background:#edfaef;color:#135e2b;border-color:#b8e0c2}
.directoryweb-profile__note{margin:0 0 14px}
.directoryweb-profile__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;align-items:start}
.directoryweb-card{border:1px solid #dcdcde;border-radius:8px;background:#fff;padding:16px}
.directoryweb-card__h{margin:0;font-size:14px}
.directoryweb-card__sub{margin:3px 0 12px;color:#646970;font-size:12px}
.directoryweb-card__body{display:flex;flex-direction:column;gap:12px}
.directoryweb-profile__label{display:block;font-weight:600;margin-bottom:4px;font-size:13px}
.directoryweb-field .description{margin-top:3px}
.directoryweb-profile__check{display:inline-flex;align-items:center;gap:6px;font-weight:600;font-size:13px}
.directoryweb-profile__gallery{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
.directoryweb-profile__gallery img{border-radius:6px;object-fit:cover}
</style>
	<?php
}

add_action(
	'save_post_directoryweb_listing',
	function ( $id ) {
		if ( ! isset( $_POST['directoryweb_listing_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['directoryweb_listing_details_nonce'] ) ), 'directoryweb_listing_details' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$keys = array( 'directoryweb_gallery' );
		foreach ( directoryweb_listing_fields() as $fields ) {
			$keys = array_merge( $keys, array_keys( $fields ) );
		}
		foreach ( $keys as $key ) {
			$v = directoryweb_sanitize_meta( wp_unslash( $_POST[ $key ] ?? '' ), $key ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'' === $v ? delete_post_meta( $id, $key ) : update_post_meta( $id, $key, $v );
		}
		$rows = array();
		foreach ( preg_split( '/\R/', (string) wp_unslash( $_POST['directoryweb_phones_lines'] ?? '' ) ) as $line ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$p = array_map( 'trim', explode( '|', sanitize_text_field( $line ) ) );
			if ( '' !== $p[0] ) {
				$rows[] = array(
					'number'   => $p[0],
					'label'    => $p[1] ?? '',
					'whatsapp' => 'whatsapp' === strtolower( $p[2] ?? '' ),
				);
			}
		}
		directoryweb_set_listing_list( $id, 'directoryweb_phones', $rows );
		$rows = array();
		foreach ( preg_split( '/\R/', (string) wp_unslash( $_POST['directoryweb_hours_lines'] ?? '' ) ) as $line ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( preg_match( '/^\s*(' . implode( '|', DIRECTORYWEB_WEEKDAYS ) . ')\s+(?:(closed)|(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2}))\s*$/i', sanitize_text_field( $line ), $x ) ) {
				$rows[] = array(
					'day'    => ucfirst( strtolower( $x[1] ) ),
					'open'   => $x[3] ?? '',
					'close'  => $x[4] ?? '',
					'closed' => ! empty( $x[2] ),
				);
			}
		}
		directoryweb_set_listing_list( $id, 'directoryweb_opening_hours', $rows );
	}
);

/* ---------- Front end: directoryweb/org-details ---------- */

/** Links (map, social, donate, volunteer), photos and facts of an organisation. */
function directoryweb_block_org_details( $a, $id ) {
	$m     = fn( $k ) => trim( (string) get_post_meta( $id, $k, true ) );
	$links = '';
	foreach ( array(
		'directoryweb_maps_url'      => array( 'map-pin', 'Directions' ),
		'directoryweb_instagram'     => array( 'instagram', 'Instagram' ),
		'directoryweb_facebook'      => array( 'facebook', 'Facebook' ),
		'directoryweb_x'             => array( 'x-brand', 'X' ),
		'directoryweb_youtube'       => array( 'youtube', 'YouTube' ),
		'directoryweb_linkedin'      => array( 'link', 'LinkedIn' ),
		'directoryweb_volunteer_url' => array( 'name', 'Volunteer' ),
		'directoryweb_adopt_url'     => array( 'home', 'Adopt' ),
	) as $k => $l ) {
		if ( $m( $k ) ) {
			$rel    = 'noopener' . ( $m( 'directoryweb_rel_nofollow' ) || get_post_meta( $id, 'directoryweb_sponsored', true ) ? ' nofollow sponsored' : '' );
			$links .= '<a class="kit-btn kit-btn--secondary kit-btn--sm" href="' . esc_url( $m( $k ) ) . '" rel="' . esc_attr( $rel ) . '" data-act="' . esc_attr( substr( $k, 3 ) ) . '">' . directoryweb_i( $l[0] ) . esc_html( $l[1] ) . '</a>'; // data-act: analytics (directoryweb-analytics.js)
		} elseif ( 'directoryweb_maps_url' === $k ) { // no map link yet: Directions stays, greyed out and not a link.
			$links .= '<span class="kit-btn kit-btn--secondary kit-btn--sm" aria-disabled="true" data-act="maps_url">' . directoryweb_i( $l[0] ) . esc_html( $l[1] ) . '</span>'; // Greyed out, a plain span (role="link" dropped — it is not a link).
		}
	}
	$photos = '';
	foreach ( array_slice( array_filter( array_map( 'absint', explode( ',', $m( 'directoryweb_gallery' ) ) ) ), 0, 6 ) as $pid ) {
		$cap     = trim( (string) wp_get_attachment_caption( $pid ) );
		$cap     = $cap ? $cap : trim( (string) get_post_meta( $pid, '_wp_attachment_image_alt', true ) ); // the caption, else the description of the photo
		$photos .= '<figure><div class="directoryweb-org-photo">' . wp_get_attachment_image( $pid, 'medium_large', false, array( 'loading' => 'lazy' ) ) . '</div>' . ( $cap ? '<figcaption>' . esc_html( $cap ) . '</figcaption>' : '' ) . '</figure>';
	}
	$facts = '';
	$svc   = directoryweb_listing_service_names( $id ); // the fixed services list, as tags
	// Contact details as rows (the number, address or link), and the details most people look for say when they are missing
	// "… not available yet", with a link to suggest an update (the Report form).
	$rel     = 'noopener' . ( $m( 'directoryweb_rel_nofollow' ) || get_post_meta( $id, 'directoryweb_sponsored', true ) ? ' nofollow sponsored' : '' );
	$phones  = directoryweb_listing_phones( $id ); // the Phone numbers list
	$hours   = directoryweb_org_hours_html( directoryweb_listing_hours( $id ) ); // the Opening hours by day list
	$contact = array(
		'directoryweb_phone'   => array( 'phone', 1 < count( $phones ) ? 'Phone numbers' : 'Phone', directoryweb_org_phones_html( $phones, $id ) ),
		'directoryweb_email'   => array( 'mail', 'Email', directoryweb_org_two_values( $id, 'directoryweb_email', 'email', $rel ) ),
		'directoryweb_website' => array( 'link', 'Website', directoryweb_org_two_values( $id, 'directoryweb_website', 'website', $rel ) ),
	);
	$na      = array(
		'directoryweb_phone'    => 'Phone number not available yet',
		'directoryweb_email'    => 'Email address not available yet',
		'directoryweb_website'  => 'Website not available yet',
		'directoryweb_area'     => 'Address not available yet',
		'directoryweb_hours'    => 'Opening hours not available yet',
		'directoryweb_services' => 'Services not available yet',
	);
	$field   = array(
		'directoryweb_phone' => 'phone',
		'directoryweb_email' => 'email',
		'directoryweb_area'  => 'address',
		'directoryweb_hours' => 'hours',
	); // the Contact form's Field
	$suggest = fn( $k ) => function_exists( 'directoryweb_report_url' ) ? '<a class="directoryweb-org-fact-note" href="' . esc_url( directoryweb_report_url( $id, $field[ $k ] ?? '' ) ) . '" rel="nofollow">Suggest an update</a>' : '';
	$missing = fn( $k ) => '<span class="directoryweb-org-fact-na">' . esc_html( $na[ $k ] ) . '</span>' . $suggest( $k );
	$facts   = '';
	foreach ( $contact as $k => $c ) {
		$facts .= '<div class="directoryweb-org-fact"><dt><span class="directoryweb-org-fact-ico">' . directoryweb_i( $c[0] ) . '</span>' . esc_html( $c[1] ) . '</dt><dd>' . ( '' !== $c[2] ? $c[2] : $missing( $k ) ) . '</dd></div>';
	}
	$icons = array(
		'directoryweb_g_rating'     => 'star',
		'directoryweb_services'     => 'check',
		'directoryweb_animals'      => 'heart',
		'directoryweb_area'         => 'map-pin',
		'directoryweb_founded'      => 'calendar',
		'directoryweb_head'         => 'name',
		'directoryweb_registration' => 'research',
		'directoryweb_reg_12a'      => 'research',
		'directoryweb_reg_80g'      => 'research',
		'directoryweb_darpan_id'    => 'research',
		'directoryweb_fcra'         => 'research',
		'directoryweb_reg_type'     => 'research',
		'directoryweb_areas_served' => 'map-pin',
		'directoryweb_hours'        => 'clock',
		'directoryweb_languages'    => 'world',
	);
	foreach ( array(
		'directoryweb_g_rating'     => 'On Google',
		'directoryweb_services'     => 'Services',
		'directoryweb_animals'      => 'Animals helped',
		'directoryweb_area'         => 'Location',
		'directoryweb_areas_served' => 'Areas served',
		'directoryweb_founded'      => 'Established',
		'directoryweb_head'         => 'Head of the organisation',
		'directoryweb_reg_type'     => 'Registered as',
		'directoryweb_registration' => 'Registration',
		'directoryweb_reg_12a'      => '12A',
		'directoryweb_reg_80g'      => '80G',
		'directoryweb_fcra'         => 'FCRA',
		'directoryweb_darpan_id'    => 'NGO Darpan ID',
		'directoryweb_hours'        => 'Opening hours',
		'directoryweb_languages'    => 'Languages',
	) as $k => $label ) {
		if ( 'directoryweb_hours' === $k ) { // the hours table, from the list only
			$facts .= '<div class="directoryweb-org-fact' . ( $hours ? ' directoryweb-org-fact--wide' : '' ) . '"><dt><span class="directoryweb-org-fact-ico">' . directoryweb_i( $icons[ $k ] ) . '</span>' . esc_html( $label ) . '</dt><dd>' . ( $hours ? $hours : $missing( $k ) ) . '</dd></div>';
		} elseif ( $m( $k ) || ( 'directoryweb_services' === $k && $svc ) ) {
			$note = '';
			if ( 'directoryweb_services' === $k && $m( 'directoryweb_services_source' ) ) { // who said so, and when
				$note = '<span class="directoryweb-org-fact-note">As stated by ' . esc_html( 'organisation' === $m( 'directoryweb_services_source' ) ? 'the organisation' : $m( 'directoryweb_services_source' ) ) . ( $m( 'directoryweb_services_on' ) ? ', ' . directoryweb_date( $m( 'directoryweb_services_on' ) ) : '' ) . '</span>';
			}
			$val    = 'directoryweb_g_rating' === $k ? $m( 'directoryweb_g_rating' ) . ' out of 5' . ( $m( 'directoryweb_g_reviews' ) ? ' (' . number_format_i18n( (int) $m( 'directoryweb_g_reviews' ) ) . ' reviews)' : '' ) : ( 'directoryweb_area' === $k && $m( 'directoryweb_address' ) ? $m( 'directoryweb_address' ) : ( 'directoryweb_area' === $k && function_exists( 'directoryweb_listing_place' ) ? directoryweb_listing_location_text( $m( 'directoryweb_area' ), directoryweb_listing_place( $id ), $m( 'directoryweb_pincode' ) ) : $m( $k ) ) );
			$shown  = 'directoryweb_services' === $k && $svc ? '<span class="kit-tags directoryweb-org-services">' . implode( '', array_map( fn( $n ) => '<span class="kit-tag kit-tag--type">' . esc_html( $n ) . '</span>', $svc ) ) . '</span>' : esc_html( $val );
			$facts .= '<div class="directoryweb-org-fact"><dt><span class="directoryweb-org-fact-ico">' . directoryweb_i( $icons[ $k ] ) . '</span>' . esc_html( $label ) . '</dt><dd>' . $shown . $note . '</dd></div>';
		} elseif ( isset( $na[ $k ] ) ) {
			$facts .= '<div class="directoryweb-org-fact"><dt><span class="directoryweb-org-fact-ico">' . directoryweb_i( $icons[ $k ] ) . '</span>' . esc_html( $label ) . '</dt><dd>' . $missing( $k ) . '</dd></div>';
		}
	}
	$extra = function_exists( 'directoryweb_enrich_profile_extras' ) ? directoryweb_enrich_profile_extras( $id ) : ''; // live Google block, signals, news mentions
	if ( '' === $links . $photos . $facts . $extra ) {
		return '';
	}
	if ( $photos ) {
		directoryweb_use_component_style( 'directoryweb-directory' ); // directoryweb-org-gallery, directoryweb-org-photo
	}
	directoryweb_use_component_style( 'directoryweb-listing' ); // directoryweb-org-details, directoryweb-org-facts, directoryweb-org-phones
	return '<section class="directoryweb-org-details" aria-label="About the organisation">'
		. ( $links ? '<div class="directoryweb-org-details-links">' . $links . '</div>' : '' )
		. ( $photos ? '<div class="directoryweb-org-gallery">' . $photos . '</div>' : '' )
		. ( $facts ? '<h2 class="directoryweb-profile-h">Details</h2><dl class="directoryweb-org-facts">' . $facts . '</dl>' : '' )
		. $extra
		. '</section>';
}

/**
 * An email or website: the value on the listing and, when the enrichment worker found a different one, that one beside
 * it. Each carries the small "unverified" mark while the listing holds two (directoryweb_unverified).
 *
 * @param int    $id   Listing ID.
 * @param string $key  directoryweb_email or directoryweb_website.
 * @param string $kind email or website.
 * @param string $rel  Link rel.
 * @return string
 */
function directoryweb_org_two_values( $id, $key, $kind, $rel ) {
	$out = array();
	foreach ( array( $key, $key . '_alt' ) as $k ) {
		$v = trim( (string) get_post_meta( $id, $k, true ) );
		if ( '' === $v ) {
			continue;
		}
		$mark  = function_exists( 'directoryweb_unverified_mark' ) ? directoryweb_unverified_mark( $id, $v ) : '';
		$out[] = 'email' === $kind
			? '<a href="' . esc_url( 'mailto:' . $v ) . '" data-act="email">' . esc_html( $v ) . '</a>' . $mark
			: '<a href="' . esc_url( $v ) . '" rel="' . esc_attr( $rel ) . '" data-act="website">' . esc_html( preg_replace( '#^https?://(www\\.)?|/$#', '', $v ) ) . '</a>' . $mark;
	}
	return 1 < count( $out ) ? '<ul class="directoryweb-org-phones"><li>' . implode( '</li><li>', $out ) . '</li></ul>' : ( $out ? $out[0] : '' );
}

/**
 * The phone numbers: each a Call link (tel:), with its label, and a WhatsApp link when the row is flagged. The links are
 * what the site's analytics count (call_click on tel:, whatsapp_click on wa.me; kit.js).
 *
 * @param array $phones directoryweb_listing_phones().
 * @return string HTML, or '' when there is no number that can be dialled.
 */
function directoryweb_org_phones_html( $phones, $lid = 0 ) {
	$items = '';
	foreach ( $phones as $p ) {
		$tel = directoryweb_tel_url( $p['number'] );
		if ( '' === $tel ) {
			continue;
		}
		$wa     = $p['whatsapp'] ? directoryweb_normalise_phone( $p['number'] ) : '';
		$items .= '<li><a href="' . esc_url( $tel ) . '" data-act="phone">' . esc_html( $p['number'] ) . '</a>' . ( $lid && function_exists( 'directoryweb_unverified_mark' ) ? directoryweb_unverified_mark( $lid, $p['number'] ) : '' )
			. ( '' !== $p['label'] ? ' <span class="directoryweb-org-phones-label">' . esc_html( $p['label'] ) . '</span>' : '' )
			. ( $wa && strlen( $wa ) > 5 ? ' <a class="directoryweb-org-phones-wa" href="' . esc_url( 'https://wa.me/' . $wa ) . '" rel="noopener" data-act="whatsapp" aria-label="' . esc_attr( 'WhatsApp ' . $p['number'] ) . '">' . directoryweb_i( 'brand-whatsapp' ) . 'WhatsApp</a>' : '' )
			. '</li>';
	}
	return $items ? '<ul class="directoryweb-org-phones">' . $items . '</ul>' : '';
}

/**
 * Opening hours by day as a small table: one row per day (Monday first), split days as two times, "Closed", or "Open 24
 * hours" for 00:00 to 23:59. Days with no row are not stated, so they are not listed.
 *
 * @param array $rows directoryweb_listing_hours().
 * @return string
 */
function directoryweb_org_hours_html( $rows ) {
	if ( ! $rows ) {
		return '';
	}
	$fmt  = fn( $t ) => wp_date( 'g:i a', strtotime( '2000-01-01 ' . $t . ':00 UTC' ), new DateTimeZone( 'UTC' ) );
	$days = array();
	foreach ( $rows as $r ) {
		$days[ $r['day'] ][] = $r['closed'] ? 'Closed' : ( '00:00' === $r['open'] && '23:59' === $r['close'] ? 'Open 24 hours' : $fmt( $r['open'] ) . ' – ' . $fmt( $r['close'] ) );
	}
	$trs = '';
	foreach ( DIRECTORYWEB_WEEKDAYS as $d ) {
		if ( isset( $days[ $d ] ) ) {
			$closed = array( 'Closed' ) === array_unique( $days[ $d ] );
			$trs   .= '<tr' . ( $closed ? ' class="directoryweb-org-hours-closed"' : '' ) . '><th scope="row">' . esc_html( $d ) . '</th><td>' . esc_html( implode( ', ', array_unique( $days[ $d ] ) ) ) . '</td></tr>';
		}
	}
	return '<table class="directoryweb-org-hours"><caption class="screen-reader-text">Opening hours by day</caption><tbody>' . $trs . '</tbody></table>';
}

// The description sits beside the logo with no heading of its own (see listing-template.php).

/**
 * "Area, District, State, PIN" with each place named once (an import's area often already names the district and state).
 *
 * @param string $area    Area or landmark.
 * @param array  $place   [state, district] names.
 * @param string $pincode PIN code.
 * @return string
 */
function directoryweb_listing_location_text( $area, $place, $pincode ) {
	$out = array();
	foreach ( array_merge( array_map( 'trim', explode( ',', (string) $area ) ), array_reverse( array_filter( (array) $place ) ), array( $pincode ) ) as $part ) {
		if ( '' !== $part && ! in_array( strtolower( $part ), array_map( 'strtolower', $out ), true ) ) {
			$out[] = $part;
		}
	}
	return implode( ', ', $out );
}
