<?php
/**
 * The Directory profile (owner, 20 Oct 2026): single-aw_listing.html, built from the item template's shared blocks
 * (breadcrumb, meta row, Sources, Share, ad slots, More) and these groups, each printed only when it has data:
 *
 *   animal-writes/listing-about          logo (featured image) left, the description right; no logo: full width
 *   animal-writes/listing-registrations  AWBI, registration, 12A, 80G, FCRA, NGO Darpan: number, valid until / expired
 *   animal-writes/listing-contact        Call, WhatsApp, Email, Website, Directions; other numbers
 *   animal-writes/listing-location       address, district, opening hours
 *   animal-writes/listing-services       services, animals helped, areas served, languages
 *   animal-writes/listing-organisation   established, incorporated, head of the organisation
 *   animal-writes/listing-profiles       small icon links: Instagram, Facebook, X, YouTube, LinkedIn, Google
 *   animal-writes/listing-support        Donate, Volunteer, Adopt; UPI with a copy button
 *   animal-writes/listing-photos         the organisation's own photos (never stock or CC images)
 *
 * Rows are "Label: value" (definition lists). Critical details the listing lacks (phone, email, website, address, opening
 * hours, services, registration, AWBI number) are never hidden: "Data not yet available" with a link to suggest an update. Separators between parts of a value are CSS, never typed. Expiry is
 * worked out at render time from today's date in India, not stored. docs/listing-template.md describes each block.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A listing field, trimmed. */
function aw_lf( $id, $key ) {
	return trim( (string) get_post_meta( $id, $key, true ) );
}

/** rel for the organisation's own links (nofollow sponsored when the listing says so). */
function aw_listing_rel( $id ) {
	return 'noopener' . ( aw_lf( $id, 'aw_rel_nofollow' ) || get_post_meta( $id, 'aw_sponsored', true ) ? ' nofollow sponsored' : '' );
}

/** Whether this block runs on a listing's own page (else the editor shows what it is). */
function aw_listing_here( $id ) {
	return function_exists( 'aw_item_here' ) && aw_item_here( $id ) && 'aw_listing' === get_post_type( $id );
}

/**
 * One definition list. $rows: [label, value HTML]; empty values are left out.
 *
 * @param array  $rows  Rows.
 * @param string $class Extra class.
 * @return string
 */
function aw_listing_dl( $rows, $class = '' ) {
	$out = '';
	foreach ( $rows as $r ) {
		if ( '' !== trim( (string) $r[1] ) ) {
			$out .= '<div class="aw-facts-row"><dt>' . esc_html( $r[0] ) . '</dt><dd>' . $r[1] . '</dd></div>';
		}
	}
	return '' !== $out ? '<dl class="aw-facts' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '">' . $out . '</dl>' : '';
}

/** One group: an H2 and its body ('' when the body is empty). */
function aw_listing_group( $slug, $head, $body ) {
	if ( '' === $body ) {
		return '';
	}
	$hid = 'aw-' . $slug . '-h';
	return '<section class="aw-listing-group aw-listing-' . esc_attr( $slug ) . '" aria-labelledby="' . $hid . '"><h2 class="aw-listing-head" id="' . $hid . '">' . esc_html( $head ) . '</h2>' . $body . '</section>';
}

/** A value's parts, separated by CSS. */
function aw_listing_parts( $parts ) {
	$parts = array_filter( $parts, fn( $p ) => '' !== trim( (string) $p ) );
	return implode( '', array_map( fn( $p ) => '<span class="aw-facts-part">' . $p . '</span>', $parts ) );
}

/**
 * A critical detail the listing does not have yet (owner, 20 Oct 2026: never hide it): "Data not yet available" and a
 * link to suggest an update (the Report form). Used for phone, email, website, address, opening hours, services,
 * registration and the AWBI number.
 */
function aw_listing_na( $id ) {
	return '<span class="aw-facts-na">' . esc_html__( 'Data not yet available', 'animal-writes-core' ) . '</span>'
		. ( function_exists( 'aw_report_url' ) ? ' <a class="aw-facts-suggest" href="' . esc_url( aw_report_url( $id ) ) . '" rel="nofollow">' . esc_html__( 'Suggest an update', 'animal-writes-core' ) . '</a>' : '' );
}

/** "valid until <date>", or "expired <date>" in the warning colour once the date has passed (today, India). */
function aw_listing_validity( $date ) {
	$date = trim( (string) $date );
	if ( '' === $date || ! strtotime( $date ) ) {
		return '';
	}
	$day = wp_date( 'Y-m-d', strtotime( $date . ' 12:00:00' ) );
	return $day < wp_date( 'Y-m-d' )
		? '<span class="aw-facts-expired">' . esc_html__( 'expired', 'animal-writes-core' ) . ' ' . aw_date( $day ) . '</span>'
		: esc_html__( 'valid until', 'animal-writes-core' ) . ' ' . aw_date( $day );
}

/* ---------- 4–5 Logo and description ---------- */

function aw_block_listing_about( $a, $id ) {
	if ( ! aw_listing_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'Logo and description' ) : '';
	}
	$logo = get_the_post_thumbnail( $id, 'thumbnail', array( 'class' => 'aw-listing-logo', 'alt' => '', 'loading' => 'eager' ) );
	$body = trim( (string) get_post_field( 'post_content', $id ) );
	$text = '' !== trim( wp_strip_all_tags( $body ) ) ? apply_filters( 'the_content', $body ) : ( has_excerpt( $id ) ? wpautop( esc_html( get_post_field( 'post_excerpt', $id ) ) ) : '' );
	if ( '' === $logo . trim( wp_strip_all_tags( $text ) ) ) {
		return '';
	}
	return '<div class="aw-listing-about' . ( $logo ? ' has-logo' : '' ) . '">' . ( $logo ? '<div class="aw-listing-logo-box">' . $logo . '</div>' : '' ) . ( '' !== trim( wp_strip_all_tags( $text ) ) ? '<div class="aw-listing-desc">' . $text . '</div>' : '' ) . '</div>';
}

/* ---------- 6 Registrations ---------- */

function aw_block_listing_registrations( $a, $id ) {
	if ( ! aw_listing_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'Registrations: AWBI, registration, 12A, 80G, FCRA, NGO Darpan' ) : '';
	}
	$awbi = aw_lf( $id, 'aw_awbi_code' );
	$drop = aw_lf( $id, 'aw_awbi_dropped' );
	$type = aw_lf( $id, 'aw_reg_type' );
	$rows = array(
		array( __( 'AWBI number', 'animal-writes-core' ), $awbi ? aw_listing_parts( array( esc_html( $awbi ), aw_listing_validity( aw_lf( $id, 'aw_awbi_valid' ) ), $drop ? '<span class="aw-facts-expired">' . esc_html__( 'not on the current AWBI list', 'animal-writes-core' ) . ' ' . aw_date( $drop ) . '</span>' : '' ) ) : aw_listing_na( $id ) ),
		/* translators: %s: registration type, e.g. Trust. */
		array( $type ? sprintf( __( 'Registration (%s)', 'animal-writes-core' ), $type ) : __( 'Registration', 'animal-writes-core' ), aw_lf( $id, 'aw_registration' ) ? esc_html( aw_lf( $id, 'aw_registration' ) ) : aw_listing_na( $id ) ),
		array( '12A', esc_html( aw_lf( $id, 'aw_reg_12a' ) ) ),
		array( '80G', esc_html( aw_lf( $id, 'aw_reg_80g' ) ) ),
		array( 'FCRA', esc_html( aw_lf( $id, 'aw_fcra' ) ) ),
		array( __( 'NGO Darpan ID', 'animal-writes-core' ), esc_html( aw_lf( $id, 'aw_darpan_id' ) ) ),
	);
	return aw_listing_group( 'registrations', __( 'Registrations', 'animal-writes-core' ), aw_listing_dl( $rows ) );
}

/* ---------- 7 Contact ---------- */

function aw_block_listing_contact( $a, $id ) {
	if ( ! aw_listing_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'Contact: Call, WhatsApp, Email, Website, Directions, other numbers' ) : '';
	}
	$rel    = aw_listing_rel( $id );
	$phones = function_exists( 'aw_listing_phones' ) ? aw_listing_phones( $id ) : array();
	$first  = $phones ? $phones[0]['number'] : '';
	$wa     = function_exists( 'aw_listing_first_phone' ) ? aw_listing_first_phone( $id, true ) : '';
	$btns   = '';
	if ( '' !== $first && '' !== aw_tel_url( $first ) ) {
		/* translators: %s: phone number. */
		$btns .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( aw_tel_url( $first ) ) . '" data-act="phone">' . aw_i( 'phone' ) . esc_html( sprintf( __( 'Call %s', 'animal-writes-core' ), $first ) ) . '</a>';
	}
	if ( '' !== $wa && strlen( aw_normalise_phone( $wa ) ) > 5 ) {
		$btns .= '<a class="kit-btn" href="' . esc_url( 'https://wa.me/' . aw_normalise_phone( $wa ) ) . '" rel="noopener" data-act="whatsapp">' . aw_i( 'brand-whatsapp' ) . esc_html__( 'WhatsApp', 'animal-writes-core' ) . '</a>';
	}
	if ( aw_lf( $id, 'aw_email' ) ) {
		$btns .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( 'mailto:' . aw_lf( $id, 'aw_email' ) ) . '" data-act="email">' . aw_i( 'mail' ) . esc_html__( 'Email', 'animal-writes-core' ) . '</a>';
	}
	if ( aw_lf( $id, 'aw_website' ) ) {
		$btns .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( aw_lf( $id, 'aw_website' ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" data-act="website">' . aw_i( 'world' ) . esc_html__( 'Website', 'animal-writes-core' ) . ' <span aria-hidden="true">↗</span><span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'animal-writes-core' ) . '</span></a>';
	}
	$btns .= aw_lf( $id, 'aw_maps_url' )
		? '<a class="kit-btn kit-btn--secondary" href="' . esc_url( aw_lf( $id, 'aw_maps_url' ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" data-act="maps_url">' . aw_i( 'map-pin' ) . esc_html__( 'Directions', 'animal-writes-core' ) . '</a>'
		: '<span class="kit-btn kit-btn--secondary" role="link" aria-disabled="true" data-act="maps_url">' . aw_i( 'map-pin' ) . esc_html__( 'Directions', 'animal-writes-core' ) . '</span>'; // no map link yet: greyed out (field note)
	$more = array();
	foreach ( array( array( '' === $first, __( 'Phone', 'animal-writes-core' ) ), array( ! aw_lf( $id, 'aw_email' ), __( 'Email', 'animal-writes-core' ) ), array( ! aw_lf( $id, 'aw_website' ), __( 'Website', 'animal-writes-core' ) ) ) as $na ) {
		if ( $na[0] ) { // a missing way to reach them is said, never hidden
			$more[] = array( $na[1], aw_listing_na( $id ) );
		}
	}
	foreach ( array_slice( $phones, 1 ) as $p ) {
		$tel = aw_tel_url( $p['number'] );
		if ( '' === $tel ) {
			continue;
		}
		$num    = '<a href="' . esc_url( $tel ) . '" data-act="phone">' . esc_html( $p['number'] ) . '</a>';
		$waa    = $p['whatsapp'] && strlen( aw_normalise_phone( $p['number'] ) ) > 5 ? ' <a class="aw-listing-wa" href="' . esc_url( 'https://wa.me/' . aw_normalise_phone( $p['number'] ) ) . '" rel="noopener" data-act="whatsapp" aria-label="' . esc_attr( 'WhatsApp ' . $p['number'] ) . '">' . aw_i( 'brand-whatsapp' ) . '</a>' : '';
		$more[] = array( '' !== $p['label'] ? $p['label'] : __( 'Phone', 'animal-writes-core' ), $num . $waa );
	}
	return aw_listing_group( 'contact', __( 'Contact', 'animal-writes-core' ), '<div class="aw-listing-actions">' . $btns . '</div>' . aw_listing_dl( $more, 'aw-facts--phones' ) );
}

/* ---------- 8 Location ---------- */

function aw_block_listing_location( $a, $id ) {
	if ( ! aw_listing_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'Location: address, district, opening hours' ) : '';
	}
	$addr  = aw_lf( $id, 'aw_address' );
	$addr  = '' !== $addr ? $addr : ( aw_lf( $id, 'aw_area' ) && function_exists( 'aw_listing_location_text' ) ? aw_listing_location_text( aw_lf( $id, 'aw_area' ), aw_listing_place( $id ), aw_lf( $id, 'aw_pincode' ) ) : '' );
	$place = '';
	foreach ( function_exists( 'aw_item_places' ) ? aw_item_places( $id ) : array() as $p ) {
		$st     = 'district' === $p->kind && function_exists( 'aw_place_state' ) ? aw_place_state( $p->state ) : null;
		$place .= '<a class="kit-chip aw-place-chip" href="' . esc_url( aw_directory_url( $p->slug ) ) . '"><span class="kit-ico kit-ico--map-pin" aria-hidden="true"></span>' . esc_html( $st ? $p->name . ', ' . $st->name : $p->name ) . '</a>';
	}
	$hours = function_exists( 'aw_org_hours_html' ) && function_exists( 'aw_listing_hours' ) ? aw_org_hours_html( aw_listing_hours( $id ) ) : '';
	$rows  = array(
		array( __( 'Address', 'animal-writes-core' ), '' !== $addr ? esc_html( $addr ) : aw_listing_na( $id ) ),
		array( __( 'District', 'animal-writes-core' ), $place ),
		array( __( 'Opening hours', 'animal-writes-core' ), '' !== $hours ? $hours : aw_listing_na( $id ) ),
	);
	return aw_listing_group( 'location', __( 'Location', 'animal-writes-core' ), aw_listing_dl( $rows ) );
}

/* ---------- 9 Services ---------- */

function aw_block_listing_services( $a, $id ) {
	if ( ! aw_listing_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'Services: services, animals helped, areas served, languages' ) : '';
	}
	$names = function_exists( 'aw_listing_service_names' ) ? aw_listing_service_names( $id ) : array();
	$svc   = $names ? esc_html( implode( ', ', $names ) ) : esc_html( aw_lf( $id, 'aw_services' ) );
	$by    = aw_lf( $id, 'aw_services_source' );
	if ( '' !== $svc && '' !== $by ) { // who said so, and when
		/* translators: %s: who stated the services. */
		$svc .= '<span class="aw-facts-note">' . esc_html( sprintf( __( 'Stated by %s', 'animal-writes-core' ), 'organisation' === $by ? __( 'the organisation', 'animal-writes-core' ) : $by ) ) . ( aw_lf( $id, 'aw_services_on' ) ? ' ' . aw_date( aw_lf( $id, 'aw_services_on' ) ) : '' ) . '</span>';
	}
	$rows = array(
		array( __( 'Services', 'animal-writes-core' ), '' !== $svc ? $svc : aw_listing_na( $id ) ),
		array( __( 'Animals helped', 'animal-writes-core' ), esc_html( aw_lf( $id, 'aw_animals' ) ) ),
		array( __( 'Areas served', 'animal-writes-core' ), esc_html( aw_lf( $id, 'aw_areas_served' ) ) ),
		array( __( 'Languages', 'animal-writes-core' ), esc_html( aw_lf( $id, 'aw_languages' ) ) ),
	);
	return aw_listing_group( 'services', __( 'Services', 'animal-writes-core' ), aw_listing_dl( $rows ) );
}

/* ---------- 10 Organisation ---------- */

function aw_block_listing_organisation( $a, $id ) {
	if ( ! aw_listing_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'Organisation: established, incorporated, head of the organisation' ) : '';
	}
	$rows = array(
		array( __( 'Established', 'animal-writes-core' ), esc_html( aw_lf( $id, 'aw_founded' ) ) ),
		array( __( 'Incorporated', 'animal-writes-core' ), aw_lf( $id, 'aw_reg_date' ) && strtotime( aw_lf( $id, 'aw_reg_date' ) ) ? aw_date( aw_lf( $id, 'aw_reg_date' ) ) : '' ), // the registration date as NGO Darpan lists it (owner, 20 Oct 2026)
		array( __( 'Head of the organisation', 'animal-writes-core' ), esc_html( aw_lf( $id, 'aw_head' ) ) ),
	);
	return aw_listing_group( 'organisation', __( 'Organisation', 'animal-writes-core' ), aw_listing_dl( $rows ) );
}

/* ---------- 12 Online profiles ---------- */

function aw_block_listing_profiles( $a, $id ) {
	if ( ! aw_listing_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'Online profiles: small icon links' ) : '';
	}
	$rel   = aw_listing_rel( $id );
	$links = '';
	foreach ( array( 'aw_instagram' => array( 'instagram', 'Instagram' ), 'aw_facebook' => array( 'facebook', 'Facebook' ), 'aw_x' => array( 'x-brand', 'X' ), 'aw_youtube' => array( 'youtube', 'YouTube' ), 'aw_linkedin' => array( 'linkedin', 'LinkedIn' ), 'aw_g_profile' => array( 'map-pin', 'Google Business Profile' ) ) as $k => $l ) {
		if ( aw_lf( $id, $k ) ) {
			/* translators: 1: organisation, 2: network. */
			$label  = sprintf( __( '%1$s on %2$s', 'animal-writes-core' ), get_the_title( $id ), $l[1] );
			$links .= '<a class="aw-listing-profile" href="' . esc_url( aw_lf( $id, $k ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" data-act="' . esc_attr( substr( $k, 3 ) ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $l[1] ) . '"><span class="kit-ico kit-ico--' . esc_attr( $l[0] ) . '" aria-hidden="true"></span></a>';
		}
	}
	return '' !== $links ? '<section class="aw-listing-profiles" aria-label="' . esc_attr__( 'Online profiles', 'animal-writes-core' ) . '"><p class="aw-listing-profiles-label">' . esc_html__( 'Online profiles', 'animal-writes-core' ) . '</p><div class="aw-listing-profiles-links">' . $links . '</div></section>' : '';
}

/* ---------- 13 Support ---------- */

function aw_block_listing_support( $a, $id ) {
	if ( ! aw_listing_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'Support: Donate, Volunteer, Adopt, UPI' ) : '';
	}
	$rel  = aw_listing_rel( $id );
	$btns = '';
	foreach ( array( 'aw_donate_url' => array( 'heart', __( 'Donate', 'animal-writes-core' ) ), 'aw_volunteer_url' => array( 'name', __( 'Volunteer', 'animal-writes-core' ) ), 'aw_adopt_url' => array( 'home', __( 'Adopt', 'animal-writes-core' ) ) ) as $k => $l ) {
		if ( aw_lf( $id, $k ) ) {
			$btns .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( aw_lf( $id, $k ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" data-act="' . esc_attr( substr( $k, 3 ) ) . '">' . aw_i( $l[0] ) . esc_html( $l[1] ) . '</a>';
		}
	}
	$upi  = aw_lf( $id, 'aw_upi' );
	$body = ( '' !== $btns ? '<div class="aw-listing-actions">' . $btns . '</div>' : '' )
		. ( '' !== $upi ? aw_listing_dl( array( array( 'UPI', '<span class="aw-listing-upi">' . esc_html( $upi ) . '</span><button type="button" class="kit-btn kit-btn--secondary kit-btn--sm kit-share-copy aw-upi-copy" data-url="' . esc_attr( $upi ) . '" data-done="' . esc_attr__( 'UPI ID copied', 'animal-writes-core' ) . '" aria-label="' . esc_attr__( 'Copy UPI ID', 'animal-writes-core' ) . '" title="' . esc_attr__( 'Copy UPI ID', 'animal-writes-core' ) . '"><span class="kit-ico kit-ico--copy" aria-hidden="true"></span><span class="kit-share-name">' . esc_html__( 'Copy', 'animal-writes-core' ) . '</span></button><span class="screen-reader-text" role="status" aria-live="polite"></span>' ) ) ) : '' );
	return aw_listing_group( 'support', __( 'Support', 'animal-writes-core' ), $body );
}

/* ---------- 14 Photos ---------- */

/** Whether an attachment is a stock or CC image (the CC0 worker marks those), never one of an organisation's own photos. */
function aw_is_stock_image( $att ) {
	return '' !== (string) get_post_meta( (int) $att, 'aw_cc0_source', true ) || '' !== (string) get_post_meta( (int) $att, 'aw_openverse_id', true )
		|| (bool) preg_match( '#(unsplash|rawpixel|wikimedia|pexels|pixabay|openverse|flickr)\.#i', (string) get_post_meta( (int) $att, '_aw_source_url', true ) . ' ' . (string) wp_get_attachment_url( (int) $att ) );
}

/** A listing's own photos (gallery IDs without stock images). */
function aw_listing_photo_ids( $id ) {
	return array_values( array_filter( array_map( 'absint', explode( ',', aw_lf( $id, 'aw_gallery' ) ) ), fn( $p ) => $p && ! aw_is_stock_image( $p ) ) );
}

function aw_block_listing_photos( $a, $id ) {
	if ( ! aw_listing_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( "Photos: the organisation's own" ) : '';
	}
	$out = '';
	foreach ( array_slice( aw_listing_photo_ids( $id ), 0, 6 ) as $pid ) {
		$cap  = trim( (string) wp_get_attachment_caption( $pid ) );
		$cap  = $cap ? $cap : trim( (string) get_post_meta( $pid, '_wp_attachment_image_alt', true ) );
		$out .= '<figure><div class="aw-org-photo">' . wp_get_attachment_image( $pid, 'medium_large', false, array( 'loading' => 'lazy' ) ) . '</div>' . ( $cap ? '<figcaption>' . esc_html( $cap ) . '</figcaption>' : '' ) . '</figure>';
	}
	return aw_listing_group( 'photos', __( 'Photos', 'animal-writes-core' ), '' !== $out ? '<div class="aw-org-gallery">' . $out . '</div>' : '' );
}

/* ---------- 15 Sources (the item template's Sources block, listing variant) ---------- */

/** "Last updated": the latest of verified on, the source's date and the details checked; never the post's own date. */
function aw_listing_last_updated( $id ) {
	$dates = array( aw_lf( $id, 'aw_verified_on' ), aw_lf( $id, 'aw_source_date' ) );
	$n     = (int) get_post_meta( $id, 'aw_detail_sources', true );
	for ( $i = 0; $i < $n; $i++ ) {
		$dates[] = trim( (string) get_post_meta( $id, "aw_detail_sources_{$i}_checked", true ) );
	}
	$dates = array_filter( array_map( fn( $d ) => $d && strtotime( $d ) ? wp_date( 'Y-m-d', strtotime( $d . ' 12:00:00' ) ) : '', $dates ) );
	return $dates ? max( $dates ) : '';
}

/** The listing's sources: each with its mark, name and "as on" date, linked. */
function aw_listing_sources_html( $id ) {
	return function_exists( 'aw_source_labels' ) ? aw_source_labels( $id, true ) : '';
}

/* ---------- 18 Claim: only while not claimed (claimed = verified) ---------- */

add_filter(
	'render_block_animal-writes/listing-help',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? get_the_ID() );
		return get_post_meta( $id, 'aw_verified', true ) ? '' : $html;
	},
	10,
	3
);

/* ---------- Clean-up on the profile ---------- */

// No tick beside the name on the profile (the meta row says Verified); cards keep it.
add_filter(
	'render_block_core/post-title',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? get_the_ID() );
		return aw_listing_here( $id ) && 1 === (int) ( $block['attrs']['level'] ?? 2 ) && function_exists( 'aw_verified_mark' ) ? str_replace( aw_verified_mark(), '', $html ) : $html;
	},
	30,
	3
);

// The profile's ads are its two Ad slot blocks.
add_filter( 'kits_ads_placed_types', fn( $t ) => array_merge( (array) $t, array( 'aw_listing' ) ) );

/**
 * Listings whose gallery holds stock or CC images: [listing ID => stock attachment IDs]. They are never shown
 * (aw_listing_photo_ids); nothing is deleted.
 *
 * @return array
 */
function aw_listing_stock_galleries() {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT p.ID, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_gallery' WHERE p.post_type = 'aw_listing' AND m.meta_value <> ''" ); // phpcs:ignore WordPress.DB
	$out  = array();
	foreach ( $rows as $r ) {
		$stock = array_values( array_filter( array_map( 'absint', explode( ',', (string) $r->meta_value ) ), 'aw_is_stock_image' ) );
		if ( $stock ) {
			$out[ (int) $r->ID ] = $stock;
		}
	}
	return $out;
}
