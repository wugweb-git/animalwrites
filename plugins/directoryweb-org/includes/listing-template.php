<?php
/**
 * The Directory profile: single-directoryweb_listing.html, built from the item template's shared blocks
 * (breadcrumb, meta row, Sources, Share, ad slots, More) and these groups, each printed only when it has data:
 *
 *   directoryweb/listing-about          logo (featured image) left, the description right; no logo: full width
 *   directoryweb/listing-registrations  AWBI, registration, 12A, 80G, FCRA, NGO Darpan: number, valid until / expired
 *   directoryweb/listing-contact        Call, WhatsApp, Email, Website, Directions; other numbers
 *   directoryweb/listing-location       address, district, opening hours
 *   directoryweb/listing-services       services, animals helped, areas served, languages
 *   directoryweb/listing-organisation   established, incorporated, head of the organisation
 *   directoryweb/listing-profiles       small icon links: Instagram, Facebook, X, YouTube, LinkedIn, Google
 *   directoryweb/listing-support        Donate, Volunteer, Adopt; UPI with a copy button
 *   directoryweb/listing-photos         the organisation's own photos (never stock or CC images)
 *
 * Rows are "Label: value" (definition lists). Critical details the listing lacks (phone, email, website, address, opening
 * hours, services, registration, AWBI number) are never hidden: "Data not yet available" with a link to suggest an update. Separators between parts of a value are CSS, never typed. Expiry is
 * worked out at render time from today's date in India, not stored. docs/listing-template.md describes each block.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A listing field, trimmed. */
function directoryweb_lf( $id, $key ) {
	return trim( (string) get_post_meta( $id, $key, true ) );
}

/** rel for the organisation's own links (nofollow sponsored when the listing says so). */
function directoryweb_listing_rel( $id ) {
	return 'noopener' . ( directoryweb_lf( $id, 'directoryweb_rel_nofollow' ) || get_post_meta( $id, 'directoryweb_sponsored', true ) ? ' nofollow sponsored' : '' );
}

/** Whether this block runs on a listing's own page (else the editor shows what it is). */
function directoryweb_listing_here( $id ) {
	return function_exists( 'directoryweb_item_here' ) && directoryweb_item_here( $id ) && 'directoryweb_listing' === get_post_type( $id );
}

/**
 * One definition list. $rows: [label, value HTML, icon]; the icon is a Kits icon slug or an issuer's logo (HTML); empty
 * values are left out.
 *
 * @param array  $rows  Rows.
 * @param string $class Extra class.
 * @return string
 */
function directoryweb_listing_dl( $rows, $class = '' ) {
	$out = '';
	foreach ( $rows as $r ) {
		if ( '' !== trim( (string) $r[1] ) ) {
			$ico  = empty( $r[2] ) ? '' : ( 0 === strpos( $r[2], '<' ) ? '<span class="directoryweb-facts-ico">' . $r[2] . '</span>' : directoryweb_i( $r[2] ) );
			$out .= '<div class="directoryweb-facts-row"><dt>' . $ico . esc_html( $r[0] ) . '</dt><dd>' . $r[1] . '</dd></div>';
		}
	}
	return '' !== $out ? '<dl class="directoryweb-facts' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '">' . $out . '</dl>' : '';
}

/** One group: an H2 and its body ('' when the body is empty). */
function directoryweb_listing_group( $slug, $head, $body ) {
	if ( '' === $body ) {
		return '';
	}
	$hid = 'directoryweb-' . $slug . '-h';
	return '<section class="directoryweb-listing-group directoryweb-listing-' . esc_attr( $slug ) . '" aria-labelledby="' . $hid . '"><h2 class="directoryweb-listing-head has-x-large-font-size" id="' . $hid . '">' . esc_html( $head ) . '</h2>' . $body . '</section>';
}

/** A value's parts, separated by CSS. */
function directoryweb_listing_parts( $parts ) {
	$parts = array_filter( $parts, fn( $p ) => '' !== trim( (string) $p ) );
	return implode( '', array_map( fn( $p ) => '<span class="directoryweb-facts-part">' . $p . '</span>', $parts ) );
}

/**
 * A critical detail the listing does not have yet (never hidden): "Data not yet available" and a
 * link to suggest an update (the Report form). Used for phone, email, website, address, opening hours, services,
 * registration and the AWBI number.
 */
function directoryweb_listing_na( $id, $field = '' ) {
	return '<span class="directoryweb-facts-na">' . esc_html__( 'Data not yet available', 'directoryweb' ) . '</span>'
		. ( function_exists( 'directoryweb_report_url' ) ? ' <a class="directoryweb-facts-suggest" href="' . esc_url( directoryweb_report_url( $id, $field ) ) . '" rel="nofollow">' . esc_html__( 'Suggest an update', 'directoryweb' ) . '</a>' : '' );
}

/** "valid until <date>", or "expired <date>" in the warning colour once the date has passed (today, India). */
function directoryweb_listing_validity( $date ) {
	$date = trim( (string) $date );
	if ( '' === $date || ! strtotime( $date ) ) {
		return '';
	}
	$day = wp_date( 'Y-m-d', strtotime( $date . ' 12:00:00' ) );
	return $day < wp_date( 'Y-m-d' )
		? '<span class="kit-tag kit-tag--warning directoryweb-facts-expired">' . esc_html__( 'Expired', 'directoryweb' ) . ' ' . directoryweb_date( $day ) . '</span>'
		: esc_html__( 'valid until', 'directoryweb' ) . ' ' . directoryweb_date( $day );
}

/* ---------- 4–5 Logo and description ---------- */

/**
 * The body is only the line a directory import wrote (tools/directory/build_*.py, awo_held.py): one paragraph saying
 * which list the organisation is in ("… It is listed in <list>.") or that AWBI recognises it ("Recognised by the Animal
 * Welfare Board of India (AWBI code …"). The Sources block says the same, so an enriched description replaces it.
 *
 * @param string $body Post content.
 * @return bool
 */
function directoryweb_listing_is_template_intro( $body ) {
	if ( substr_count( strtolower( $body ), '<p' ) > 1 ) {
		return false;
	}
	$t = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $body ) ) );
	return '' !== $t && ( false !== strpos( $t, '. It is listed in ' ) || 0 === strpos( $t, 'Recognised by the Animal Welfare Board of India (AWBI code' ) );
}

function directoryweb_block_listing_about( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Logo and description' ) : '';
	}
	$logo = get_the_post_thumbnail( $id, 'thumbnail', array( 'class' => 'directoryweb-listing-logo', 'alt' => '', 'loading' => 'eager' ) );
	$body = trim( (string) get_post_field( 'post_content', $id ) );
	// The excerpt (the enriched "About") replaces an empty body or an import's one-line template intro.
	$use  = has_excerpt( $id ) && ( '' === trim( wp_strip_all_tags( $body ) ) || directoryweb_listing_is_template_intro( $body ) );
	$text = $use ? wpautop( esc_html( get_post_field( 'post_excerpt', $id ) ) ) : ( '' !== trim( wp_strip_all_tags( $body ) ) ? apply_filters( 'the_content', $body ) : '' );
	if ( '' === $logo . trim( wp_strip_all_tags( $text ) ) ) {
		return '';
	}
	return '<div class="directoryweb-listing-about' . ( $logo ? ' has-logo' : '' ) . '">' . ( $logo ? '<div class="directoryweb-listing-logo-box">' . $logo . '</div>' : '' ) . ( '' !== trim( wp_strip_all_tags( $text ) ) ? '<div class="directoryweb-listing-desc">' . $text . '</div>' : '' ) . '</div>';
}

/** The issuing body's logo for a registration row (source registry, fetched once), else a Kits icon slug. */
function directoryweb_listing_issuer_icon( $slug, $id, $fallback ) {
	$logo = function_exists( 'directoryweb_source_logo' ) ? directoryweb_source_logo( $slug, $id, '' ) : '';
	return '' !== $logo ? $logo : $fallback;
}

/* ---------- 6 Registrations ---------- */

function directoryweb_block_listing_registrations( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Registrations: AWBI, registration, 12A, 80G, FCRA, NGO Darpan' ) : '';
	}
	$awbi = directoryweb_lf( $id, 'directoryweb_awbi_code' );
	$drop = directoryweb_lf( $id, 'directoryweb_awbi_dropped' );
	$type = directoryweb_lf( $id, 'directoryweb_reg_type' );
	$rows = array(
		array( __( 'AWBI number', 'directoryweb' ), $awbi ? directoryweb_listing_parts( array( esc_html( $awbi ), directoryweb_listing_validity( directoryweb_lf( $id, 'directoryweb_awbi_valid' ) ), $drop ? '<span class="kit-tag kit-tag--warning directoryweb-facts-expired">' . esc_html__( 'Not on the current AWBI list', 'directoryweb' ) . ' ' . directoryweb_date( $drop ) . '</span>' : '' ) ) : directoryweb_listing_na( $id ) , directoryweb_listing_issuer_icon( 'awbi', $id, 'legal' ) ),
		/* translators: %s: registration type, e.g. Trust. */
		array( $type ? sprintf( __( 'Registration (%s)', 'directoryweb' ), $type ) : __( 'Registration', 'directoryweb' ), directoryweb_lf( $id, 'directoryweb_registration' ) ? esc_html( directoryweb_lf( $id, 'directoryweb_registration' ) ) : directoryweb_listing_na( $id ), 'file' ),
		array( '12A', esc_html( directoryweb_lf( $id, 'directoryweb_reg_12a' ) ), directoryweb_listing_issuer_icon( 'incometax', $id, 'legal' ) ),
		array( '80G', esc_html( directoryweb_lf( $id, 'directoryweb_reg_80g' ) ), directoryweb_listing_issuer_icon( 'incometax', $id, 'legal' ) ),
		array( 'FCRA', esc_html( directoryweb_lf( $id, 'directoryweb_fcra' ) ), directoryweb_listing_issuer_icon( 'mha', $id, 'legal' ) ),
		array( __( 'NGO Darpan ID', 'directoryweb' ), esc_html( directoryweb_lf( $id, 'directoryweb_darpan_id' ) ), directoryweb_listing_issuer_icon( 'darpan', $id, 'legal' ) ),
	);
	return directoryweb_listing_group( 'registrations', __( 'Registrations', 'directoryweb' ), directoryweb_listing_dl( $rows ) );
}

/* ---------- 7 Contact ---------- */

function directoryweb_block_listing_contact( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Contact: Call, WhatsApp, Email, Website, Directions, other numbers' ) : '';
	}
	$rel    = directoryweb_listing_rel( $id );
	$phones = function_exists( 'directoryweb_listing_phones' ) ? directoryweb_listing_phones( $id ) : array();
	$first  = $phones ? $phones[0]['number'] : '';
	$wa     = function_exists( 'directoryweb_listing_first_phone' ) ? directoryweb_listing_first_phone( $id, true ) : '';
	$btns   = '';
	if ( '' !== $first && '' !== directoryweb_tel_url( $first ) ) {
		/* translators: %s: phone number. */
		$btns .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( directoryweb_tel_url( $first ) ) . '" data-act="phone">' . directoryweb_i( 'phone' ) . esc_html( sprintf( __( 'Call %s', 'directoryweb' ), $first ) ) . ( function_exists( 'directoryweb_unverified_mark' ) ? directoryweb_unverified_mark( $id, $first ) : '' ) . '</a>';
	}
	if ( '' !== $wa && strlen( directoryweb_normalise_phone( $wa ) ) > 5 ) {
		$btns .= '<a class="kit-btn" href="' . esc_url( 'https://wa.me/' . directoryweb_normalise_phone( $wa ) ) . '" rel="noopener" data-act="whatsapp">' . directoryweb_i( 'brand-whatsapp' ) . esc_html__( 'WhatsApp', 'directoryweb' ) . '</a>';
	}
	if ( directoryweb_lf( $id, 'directoryweb_email' ) ) {
		$btns .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( 'mailto:' . directoryweb_lf( $id, 'directoryweb_email' ) ) . '" data-act="email">' . directoryweb_i( 'mail' ) . esc_html__( 'Email', 'directoryweb' ) . ( function_exists( 'directoryweb_unverified_mark' ) ? directoryweb_unverified_mark( $id, directoryweb_lf( $id, 'directoryweb_email' ) ) : '' ) . '</a>';
	}
	if ( directoryweb_lf( $id, 'directoryweb_website' ) ) {
		$btns .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( directoryweb_lf( $id, 'directoryweb_website' ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" data-act="website">' . directoryweb_i( 'world' ) . esc_html__( 'Website', 'directoryweb' ) . ' <span aria-hidden="true">↗</span><span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'directoryweb' ) . '</span>' . ( function_exists( 'directoryweb_unverified_mark' ) ? directoryweb_unverified_mark( $id, directoryweb_lf( $id, 'directoryweb_website' ) ) : '' ) . '</a>';
	}
	$btns .= directoryweb_lf( $id, 'directoryweb_maps_url' )
		? '<a class="kit-btn kit-btn--secondary" href="' . esc_url( directoryweb_lf( $id, 'directoryweb_maps_url' ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" data-act="maps_url">' . directoryweb_i( 'map-pin' ) . esc_html__( 'Directions', 'directoryweb' ) . '</a>'
		: '<span class="kit-btn kit-btn--secondary" aria-disabled="true" data-act="maps_url">' . directoryweb_i( 'map-pin' ) . esc_html__( 'Directions', 'directoryweb' ) . '</span>'; // No map link yet: greyed out, a plain span (role="link" dropped — it is not a link).
	$more = array();
	foreach ( array( array( '' === $first, __( 'Phone', 'directoryweb' ), 'phone', 'phone' ), array( ! directoryweb_lf( $id, 'directoryweb_email' ), __( 'Email', 'directoryweb' ), 'mail', 'email' ), array( ! directoryweb_lf( $id, 'directoryweb_website' ), __( 'Website', 'directoryweb' ), 'world', '' ) ) as $na ) {
		if ( $na[0] ) { // a missing way to reach them is said, never hidden
			$more[] = array( $na[1], directoryweb_listing_na( $id, $na[3] ), $na[2] );
		}
	}
	foreach ( array_slice( $phones, 1 ) as $p ) {
		$tel = directoryweb_tel_url( $p['number'] );
		if ( '' === $tel ) {
			continue;
		}
		$num    = '<a href="' . esc_url( $tel ) . '" data-act="phone">' . esc_html( $p['number'] ) . '</a>' . ( function_exists( 'directoryweb_unverified_mark' ) ? directoryweb_unverified_mark( $id, $p['number'] ) : '' );
		$waa    = $p['whatsapp'] && strlen( directoryweb_normalise_phone( $p['number'] ) ) > 5 ? ' <a class="directoryweb-listing-wa" href="' . esc_url( 'https://wa.me/' . directoryweb_normalise_phone( $p['number'] ) ) . '" rel="noopener" data-act="whatsapp" aria-label="' . esc_attr( 'WhatsApp ' . $p['number'] ) . '">' . directoryweb_i( 'brand-whatsapp' ) . '</a>' : '';
		$more[] = array( '' !== $p['label'] ? $p['label'] : __( 'Phone', 'directoryweb' ), $num . $waa, $p['whatsapp'] ? 'brand-whatsapp' : 'phone' );
	}
	// A second email or website found by the enrichment worker is kept beside the first, both marked unverified.
	if ( directoryweb_lf( $id, 'directoryweb_email_alt' ) ) {
		$more[] = array( __( 'Email', 'directoryweb' ), '<a href="' . esc_url( 'mailto:' . directoryweb_lf( $id, 'directoryweb_email_alt' ) ) . '" data-act="email">' . esc_html( directoryweb_lf( $id, 'directoryweb_email_alt' ) ) . '</a>' . ( function_exists( 'directoryweb_unverified_mark' ) ? directoryweb_unverified_mark( $id, directoryweb_lf( $id, 'directoryweb_email_alt' ) ) : '' ), 'mail' );
	}
	if ( directoryweb_lf( $id, 'directoryweb_website_alt' ) ) {
		$more[] = array( __( 'Website', 'directoryweb' ), '<a href="' . esc_url( directoryweb_lf( $id, 'directoryweb_website_alt' ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" data-act="website">' . esc_html( preg_replace( '#^https?://(www\\.)?|/$#', '', directoryweb_lf( $id, 'directoryweb_website_alt' ) ) ) . '</a>' . ( function_exists( 'directoryweb_unverified_mark' ) ? directoryweb_unverified_mark( $id, directoryweb_lf( $id, 'directoryweb_website_alt' ) ) : '' ), 'world' );
	}
	return directoryweb_listing_group( 'contact', __( 'Contact', 'directoryweb' ), '<div class="directoryweb-listing-actions">' . $btns . '</div>' . directoryweb_listing_dl( $more, 'directoryweb-facts--phones' ) );
}

/* ---------- 8 Location ---------- */

function directoryweb_block_listing_location( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Location: address, district, opening hours' ) : '';
	}
	$addr  = directoryweb_lf( $id, 'directoryweb_address' );
	$addr  = '' !== $addr ? $addr : ( directoryweb_lf( $id, 'directoryweb_area' ) && function_exists( 'directoryweb_listing_location_text' ) ? directoryweb_listing_location_text( directoryweb_lf( $id, 'directoryweb_area' ), directoryweb_listing_place( $id ), directoryweb_lf( $id, 'directoryweb_pincode' ) ) : '' );
	$place = '';
	foreach ( function_exists( 'directoryweb_item_places' ) ? directoryweb_item_places( $id ) : array() as $p ) {
		$st     = 'district' === $p->kind && function_exists( 'directoryweb_place_state' ) ? directoryweb_place_state( $p->state ) : null;
		$place .= '<a class="directoryweb-item-place" href="' . esc_url( directoryweb_directory_url( $p->slug ) ) . '"><span class="kit-ico kit-ico--map-pin" aria-hidden="true"></span>' . esc_html( $st ? $p->name . ', ' . $st->name : $p->name ) . '</a>';
	}
	$hours = function_exists( 'directoryweb_org_hours_html' ) && function_exists( 'directoryweb_listing_hours' ) ? directoryweb_org_hours_html( directoryweb_listing_hours( $id ) ) : '';
	$rows  = array(
		array( __( 'Address', 'directoryweb' ), '' !== $addr ? esc_html( $addr ) : directoryweb_listing_na( $id, 'address' ), 'map-pin' ),
		array( __( 'District', 'directoryweb' ), $place, 'directory' ),
		array( __( 'Opening hours', 'directoryweb' ), '' !== $hours ? $hours : directoryweb_listing_na( $id, 'hours' ), 'clock' ),
	);
	return directoryweb_listing_group( 'location', __( 'Location', 'directoryweb' ), directoryweb_listing_dl( $rows ) );
}

/* ---------- 9 Services ---------- */

function directoryweb_block_listing_services( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Services: services, animals helped, areas served, languages' ) : '';
	}
	$names = function_exists( 'directoryweb_listing_service_names' ) ? directoryweb_listing_service_names( $id ) : array();
	$svc   = $names ? esc_html( implode( ', ', $names ) ) : esc_html( directoryweb_lf( $id, 'directoryweb_services' ) );
	$by    = directoryweb_lf( $id, 'directoryweb_services_source' );
	if ( '' === $svc && directoryweb_listing_type_services( $id ) ) { // nothing stated: the services its type implies, said so
		$svc = esc_html( implode( ', ', directoryweb_listing_type_services( $id ) ) ) . '<span class="directoryweb-facts-note">' . esc_html__( 'Based on type', 'directoryweb' ) . '</span>';
		$by  = '';
	}
	if ( '' !== $svc && '' !== $by ) { // who said so, and when
		/* translators: %s: who stated the services. */
		$svc .= '<span class="directoryweb-facts-note">' . esc_html( sprintf( __( 'Stated by %s', 'directoryweb' ), 'organisation' === $by ? __( 'the organisation', 'directoryweb' ) : $by ) ) . ( directoryweb_lf( $id, 'directoryweb_services_on' ) ? ' ' . directoryweb_date( directoryweb_lf( $id, 'directoryweb_services_on' ) ) : '' ) . '</span>';
	}
	$rows = array(
		array( __( 'Services', 'directoryweb' ), '' !== $svc ? $svc : directoryweb_listing_na( $id ), 'check' ),
		array( __( 'Animals helped', 'directoryweb' ), esc_html( directoryweb_lf( $id, 'directoryweb_animals' ) ), 'heart' ),
		array( __( 'Areas served', 'directoryweb' ), esc_html( directoryweb_lf( $id, 'directoryweb_areas_served' ) ), 'map-pin' ),
		array( __( 'Languages', 'directoryweb' ), esc_html( directoryweb_lf( $id, 'directoryweb_languages' ) ), 'world' ),
	);
	return directoryweb_listing_group( 'services', __( 'Services', 'directoryweb' ), directoryweb_listing_dl( $rows ) );
}

/**
 * Services implied by a listing's type: shown,
 * marked "Based on type", only when the listing states no services. Type slug => service tag slugs.
 */
const DIRECTORYWEB_TYPE_SERVICES = array(
	'animal-welfare-organisation' => array( 'animal-welfare' ),
	'animal-shelter'              => array( 'shelter' ),
	'rescue-group'                => array( 'rescue' ),
	'emergency-rescue'            => array( 'emergency-rescue' ),
	'animal-ambulance'            => array( 'ambulance' ),
	'abc-centre'                  => array( 'sterilisation' ),
	'gaushala'                    => array( 'cattle-care', 'shelter' ),
	'sanctuary'                   => array( 'shelter' ),
	'wildlife-rescue'             => array( 'wildlife-rescue' ),
	'bird-hospital'               => array( 'bird-treatment' ),
	'veterinary-hospital'         => array( 'treatment', 'vaccination', 'sterilisation' ),
	'veterinary-dispensary'       => array( 'treatment', 'vaccination' ),
	'veterinary-clinic'           => array( 'treatment', 'vaccination' ),
	'veterinary-office'           => array( 'vaccination' ),
	'mobile-veterinary-unit'      => array( 'treatment', 'ambulance' ),
	'legal-aid'                   => array( 'legal-help' ),
	'adoption-network'            => array( 'adoption' ),
	'feeder-network'              => array( 'feeding' ),
);

/** The service names a listing's types imply, combined and without repeats, in the tags' current names. */
function directoryweb_listing_type_services( $id ) {
	$out = array();
	foreach ( function_exists( 'directoryweb_listing_types' ) ? directoryweb_listing_types( $id ) : array() as $t ) {
		foreach ( DIRECTORYWEB_TYPE_SERVICES[ $t->slug ] ?? array() as $svc ) {
			$term         = get_term_by( 'slug', $svc, 'post_tag' );
			$out[ $svc ] = $term ? $term->name : ( DIRECTORYWEB_SERVICES[ $svc ] ?? $svc );
		}
	}
	return array_values( $out );
}

/* ---------- 10 Organisation ---------- */

function directoryweb_block_listing_organisation( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Organisation: established, incorporated, head of the organisation' ) : '';
	}
	$rows = array(
		array( __( 'Established', 'directoryweb' ), esc_html( directoryweb_lf( $id, 'directoryweb_founded' ) ), 'calendar' ),
		array( __( 'Incorporated', 'directoryweb' ), directoryweb_lf( $id, 'directoryweb_reg_date' ) && strtotime( directoryweb_lf( $id, 'directoryweb_reg_date' ) ) ? directoryweb_date( directoryweb_lf( $id, 'directoryweb_reg_date' ) ) : '', 'legal' ), // the registration date as NGO Darpan lists it.
		array( __( 'Head of the organisation', 'directoryweb' ), esc_html( directoryweb_lf( $id, 'directoryweb_head' ) ), 'user' ),
	);
	return directoryweb_listing_group( 'organisation', __( 'Organisation', 'directoryweb' ), directoryweb_listing_dl( $rows ) );
}

/* ---------- 12 Online profiles ---------- */

function directoryweb_block_listing_profiles( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Online profiles: small icon links' ) : '';
	}
	$rel   = directoryweb_listing_rel( $id );
	$links = '';
	foreach ( array( 'directoryweb_instagram' => array( 'instagram', 'Instagram' ), 'directoryweb_facebook' => array( 'facebook', 'Facebook' ), 'directoryweb_x' => array( 'x-brand', 'X' ), 'directoryweb_youtube' => array( 'youtube', 'YouTube' ), 'directoryweb_linkedin' => array( 'linkedin', 'LinkedIn' ), 'directoryweb_g_profile' => array( 'map-pin', 'Google Business Profile' ) ) as $k => $l ) {
		if ( directoryweb_lf( $id, $k ) ) {
			/* translators: 1: organisation, 2: network. */
			$label  = sprintf( __( '%1$s on %2$s', 'directoryweb' ), get_the_title( $id ), $l[1] );
			$mark   = 'directoryweb_g_profile' === $k ? '<img class="directoryweb-listing-profile-img" src="' . esc_url( directoryweb_theme_file_uri( 'assets/img/google-maps.png' ) ) . '" alt="" width="20" height="20">' : '<span class="kit-ico kit-ico--' . esc_attr( $l[0] ) . '" aria-hidden="true"></span>'; // Google: its own Maps mark
			$links .= '<a class="directoryweb-listing-profile" href="' . esc_url( directoryweb_lf( $id, $k ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" data-act="' . esc_attr( substr( $k, 3 ) ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $l[1] ) . '">' . $mark . '</a>';
		}
	}
	return directoryweb_listing_group( 'profiles', __( 'Online profiles', 'directoryweb' ), '' !== $links ? '<div class="directoryweb-listing-profiles-links">' . $links . '</div>' : '' );
}

/* ---------- 13 Support ---------- */

function directoryweb_block_listing_support( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Support: Volunteer, Adopt' ) : '';
	}
	$rel  = directoryweb_listing_rel( $id );
	$btns = '';
	// No money anywhere: no Donate link and no UPI ID on listings; the stored values stay, unused.
	foreach ( array( 'directoryweb_volunteer_url' => array( 'name', __( 'Volunteer', 'directoryweb' ) ), 'directoryweb_adopt_url' => array( 'home', __( 'Adopt', 'directoryweb' ) ) ) as $k => $l ) {
		if ( directoryweb_lf( $id, $k ) ) {
			$btns .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( directoryweb_lf( $id, $k ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" data-act="' . esc_attr( substr( $k, 3 ) ) . '">' . directoryweb_i( $l[0] ) . esc_html( $l[1] ) . '</a>';
		}
	}
	$body = '' !== $btns ? '<div class="directoryweb-listing-actions">' . $btns . '</div>' : '';
	return directoryweb_listing_group( 'support', __( 'Support', 'directoryweb' ), $body );
}

/* ---------- 14 Photos ---------- */

/** Whether an attachment is a stock or CC image (the CC0 worker marks those), never one of an organisation's own photos. */
function directoryweb_is_stock_image( $att ) {
	return '' !== (string) get_post_meta( (int) $att, 'directoryweb_cc0_source', true ) || '' !== (string) get_post_meta( (int) $att, 'directoryweb_openverse_id', true )
		|| (bool) preg_match( '#(unsplash|rawpixel|wikimedia|pexels|pixabay|openverse|flickr)\.#i', (string) get_post_meta( (int) $att, '_aw_source_url', true ) . ' ' . (string) wp_get_attachment_url( (int) $att ) );
}

/** A listing's own photos (gallery IDs without stock images). */
function directoryweb_listing_photo_ids( $id ) {
	return array_values( array_filter( array_map( 'absint', explode( ',', directoryweb_lf( $id, 'directoryweb_gallery' ) ) ), fn( $p ) => $p && ! directoryweb_is_stock_image( $p ) ) );
}

function directoryweb_block_listing_photos( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( "Photos: the organisation's own" ) : '';
	}
	$out = '';
	foreach ( array_slice( directoryweb_listing_photo_ids( $id ), 0, 6 ) as $pid ) {
		$cap  = trim( (string) wp_get_attachment_caption( $pid ) );
		$cap  = $cap ? $cap : trim( (string) get_post_meta( $pid, '_wp_attachment_image_alt', true ) );
		$out .= '<figure><div class="directoryweb-org-photo">' . wp_get_attachment_image( $pid, 'medium_large', false, array( 'loading' => 'lazy' ) ) . '</div>' . ( $cap ? '<figcaption>' . esc_html( $cap ) . '</figcaption>' : '' ) . '</figure>';
	}
	if ( '' !== $out ) {
		directoryweb_use_component_style( 'directoryweb-directory' ); // directoryweb-org-gallery, directoryweb-org-photo
	}
	return directoryweb_listing_group( 'photos', __( 'Photos', 'directoryweb' ), '' !== $out ? '<div class="directoryweb-org-gallery">' . $out . '</div>' : '' );
}

/* ---------- 15 Sources (the item template's Sources block, listing variant) ---------- */

/**
 * "Last updated": the latest of verified on, the source's date, the details checked and the newest applied enrichment
 * change; never the post's own date (imports and migrations can touch every listing's post date).
 */
function directoryweb_listing_last_updated( $id ) {
	$dates = array( directoryweb_lf( $id, 'directoryweb_verified_on' ), directoryweb_lf( $id, 'directoryweb_source_date' ) );
	if ( function_exists( 'directoryweb_enrich_last_applied' ) ) {
		$dates[] = substr( directoryweb_enrich_last_applied( $id ), 0, 10 );
	}
	$n     = (int) get_post_meta( $id, 'directoryweb_detail_sources', true );
	for ( $i = 0; $i < $n; $i++ ) {
		$dates[] = trim( (string) get_post_meta( $id, "directoryweb_detail_sources_{$i}_checked", true ) );
	}
	$dates = array_filter( array_map( fn( $d ) => $d && strtotime( $d ) ? wp_date( 'Y-m-d', strtotime( $d . ' 12:00:00' ) ) : '', $dates ) );
	return $dates ? max( $dates ) : '';
}

/** The listing's sources: each with its mark, name and "as on" date, linked. */
function directoryweb_listing_sources_html( $id ) {
	return function_exists( 'directoryweb_source_labels' ) ? directoryweb_source_labels( $id, true ) : '';
}

/* ---------- 18 Claim: only while not claimed (claimed = verified) ---------- */

add_filter(
	'render_block_animal-writes/listing-help',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? get_the_ID() );
		return get_post_meta( $id, 'directoryweb_verified', true ) ? '' : $html;
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
		return directoryweb_listing_here( $id ) && 1 === (int) ( $block['attrs']['level'] ?? 2 ) && function_exists( 'directoryweb_verified_mark' ) ? str_replace( directoryweb_verified_mark(), '', $html ) : $html;
	},
	30,
	3
);


/* ---------- Activity (after Photos): stored Google and Instagram figures, never fetched on page load ---------- */

/** "as on" date for a profile's figures: a dated detail source for that site, else the last enrichment check, else the source date. */
function directoryweb_listing_figures_on( $id, $host ) {
	$n = (int) get_post_meta( $id, 'directoryweb_detail_sources', true );
	for ( $i = 0; $i < $n; $i++ ) {
		$url = (string) get_post_meta( $id, "directoryweb_detail_sources_{$i}_url", true );
		$on  = trim( (string) get_post_meta( $id, "directoryweb_detail_sources_{$i}_checked", true ) );
		if ( $on && false !== stripos( $url, $host ) ) {
			return $on;
		}
	}
	$chk = trim( (string) get_post_meta( $id, 'directoryweb_enrich_checked', true ) );
	return $chk ? substr( $chk, 0, 10 ) : directoryweb_lf( $id, 'directoryweb_source_date' );
}

function directoryweb_block_listing_activity( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Activity: Google rating and reviews, Instagram followers, posts, last post' ) : '';
	}
	$rel   = directoryweb_listing_rel( $id );
	$boxes = '';
	if ( directoryweb_lf( $id, 'directoryweb_g_rating' ) ) {
		$on     = directoryweb_listing_figures_on( $id, 'google' );
		$parts  = directoryweb_listing_parts( array( '<span aria-hidden="true">★</span> ' . esc_html( directoryweb_lf( $id, 'directoryweb_g_rating' ) ) . '<span class="screen-reader-text"> ' . esc_html__( 'out of 5', 'directoryweb' ) . '</span>', directoryweb_lf( $id, 'directoryweb_g_reviews' ) ? esc_html( sprintf( /* translators: %s: number. */ _n( '%s review', '%s reviews', (int) directoryweb_lf( $id, 'directoryweb_g_reviews' ), 'directoryweb' ), number_format_i18n( (int) directoryweb_lf( $id, 'directoryweb_g_reviews' ) ) ) ) : '' ) );
		$boxes .= '<div class="directoryweb-activity-box"><p class="directoryweb-activity-name">' . directoryweb_i( 'map-pin' ) . esc_html__( 'Google', 'directoryweb' ) . '</p><p class="directoryweb-activity-figures">' . $parts . '</p>'
			. ( $on ? '<p class="directoryweb-activity-on">' . esc_html__( 'as on', 'directoryweb' ) . ' ' . directoryweb_date( $on ) . '</p>' : '' )
			. ( directoryweb_lf( $id, 'directoryweb_g_profile' ) ? '<a class="directoryweb-activity-link" href="' . esc_url( directoryweb_lf( $id, 'directoryweb_g_profile' ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank">' . esc_html__( 'View on Google', 'directoryweb' ) . ' <span aria-hidden="true">↗</span></a>' : '' ) . '</div>';
	}
	if ( directoryweb_lf( $id, 'directoryweb_ig_followers' ) || directoryweb_lf( $id, 'directoryweb_ig_posts' ) || directoryweb_lf( $id, 'directoryweb_last_post' ) ) {
		$on     = directoryweb_listing_figures_on( $id, 'instagram' );
		$parts  = directoryweb_listing_parts(
			array(
				directoryweb_lf( $id, 'directoryweb_ig_followers' ) ? esc_html( sprintf( /* translators: %s: number. */ __( '%s followers', 'directoryweb' ), number_format_i18n( (int) directoryweb_lf( $id, 'directoryweb_ig_followers' ) ) ) ) : '',
				directoryweb_lf( $id, 'directoryweb_ig_posts' ) ? esc_html( sprintf( /* translators: %s: number. */ __( '%s posts', 'directoryweb' ), number_format_i18n( (int) directoryweb_lf( $id, 'directoryweb_ig_posts' ) ) ) ) : '',
				directoryweb_lf( $id, 'directoryweb_last_post' ) ? esc_html__( 'last post', 'directoryweb' ) . ' ' . directoryweb_date( directoryweb_lf( $id, 'directoryweb_last_post' ) ) : '',
			)
		);
		$boxes .= '<div class="directoryweb-activity-box"><p class="directoryweb-activity-name">' . directoryweb_i( 'instagram' ) . esc_html__( 'Instagram', 'directoryweb' ) . '</p><p class="directoryweb-activity-figures">' . $parts . '</p>'
			. ( $on ? '<p class="directoryweb-activity-on">' . esc_html__( 'as on', 'directoryweb' ) . ' ' . directoryweb_date( $on ) . '</p>' : '' )
			. ( directoryweb_lf( $id, 'directoryweb_instagram' ) ? '<a class="directoryweb-activity-link" href="' . esc_url( directoryweb_lf( $id, 'directoryweb_instagram' ) ) . '" rel="' . esc_attr( $rel ) . '" target="_blank">' . esc_html__( 'View on Instagram', 'directoryweb' ) . ' <span aria-hidden="true">↗</span></a>' : '' ) . '</div>';
	}
	return directoryweb_listing_group( 'activity', __( 'Activity', 'directoryweb' ), '' !== $boxes ? '<div class="directoryweb-activity">' . $boxes . '</div>' : '' );
}

/* ---------- After Claim: In the press, Opportunities at the organisation ---------- */

/** A small card for "In the press": 4:3 picture, type and date, title (press coverage links to the article itself). */
function directoryweb_listing_press_card( $pid ) {
	directoryweb_use_component_style( 'directoryweb-single' ); // directoryweb-more-card, directoryweb-more-head, directoryweb-more-list
	$type = get_post_type( $pid );
	$url  = function_exists( 'directoryweb_featured_url' ) ? directoryweb_featured_url( $pid ) : get_permalink( $pid );
	$ext  = 'directoryweb_press' === $type;
	$ts   = function_exists( 'directoryweb_item_date_ts' ) ? directoryweb_item_date_ts( $pid ) : (int) get_post_time( 'U', true, $pid );
	$name = defined( 'DIRECTORYWEB_FEATURED_TYPES' ) ? ( DIRECTORYWEB_FEATURED_TYPES[ $type ] ?? '' ) : '';
	return '<li><article class="kit-card kit-minicard directoryweb-more-card">' . ( function_exists( 'directoryweb_item_card_media' ) ? directoryweb_item_card_media( $pid ) : '' ) . '<div class="kit-minicard-body directoryweb-more-body"><p class="kit-minicard-meta directoryweb-more-meta kit-meta-row">' . ( $ts ? directoryweb_date( $ts ) : '' ) . ( '' !== $name ? '<span class="directoryweb-more-type">' . esc_html( $name ) . '</span>' : '' ) . '</p>' // the date always first in a meta row (owner)
		. '<h3 class="kit-minicard-title directoryweb-more-title"><a href="' . esc_url( $url ) . '"' . ( $ext ? ' rel="noopener nofollow" target="_blank"' : '' ) . '>' . esc_html( html_entity_decode( get_the_title( $pid ), ENT_QUOTES ) ) . '</a></h3></div></article></li>';
}

function directoryweb_block_listing_press( $a, $id ) {
	directoryweb_use_component_style( 'directoryweb-single' ); // directoryweb-more-card, directoryweb-more-head, directoryweb-more-list
	if ( ! directoryweb_listing_here( $id ) || ! function_exists( 'directoryweb_featured_items' ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'In the press: items on the site that name this organisation' ) : '';
	}
	$all  = isset( $_GET['press'] ) && 'all' === $_GET['press']; // phpcs:ignore WordPress.Security.NonceVerification
	$ids  = directoryweb_featured_items( $id, $all ? 100 : 6 );
	if ( ! $ids ) {
		return '';
	}
	$n    = $all ? count( $ids ) : count( directoryweb_featured_items( $id, 100 ) );
	$show = $all ? $ids : array_slice( $ids, 0, 5 );
	$more = ! $all && $n > 5 ? '<p class="directoryweb-more-all"><a href="' . esc_url( add_query_arg( 'press', 'all', get_permalink( $id ) ) . '#directoryweb-press-h' ) . '">' . esc_html( sprintf( /* translators: %d: number. */ __( 'See all (%d)', 'directoryweb' ), $n ) ) . '</a></p>' : '';
	return '<section class="directoryweb-more-group directoryweb-listing-press" aria-labelledby="directoryweb-press-h"><h2 class="directoryweb-more-head has-x-large-font-size" id="directoryweb-press-h">' . esc_html__( 'In the press', 'directoryweb' ) . '</h2><ul class="kit-minicard-list directoryweb-more-list">' . implode( '', array_map( 'directoryweb_listing_press_card', $show ) ) . '</ul>' . $more . '</section>';
}


function directoryweb_block_listing_opportunities( $a, $id ) {
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Opportunities at the organisation (open only)' ) : '';
	}
	$q = array( 'post_type' => 'directoryweb_opportunity', 'post_status' => 'publish', 'numberposts' => 3, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array( array( 'key' => 'directoryweb_organisation', 'value' => (string) $id ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( function_exists( 'directoryweb_open_opportunities_meta_query' ) ) {
		$q['meta_query'][] = directoryweb_open_opportunities_meta_query();
		$q['meta_query']['relation'] = 'AND';
	}
	$ids = get_posts( $q );
	if ( ! $ids || ! function_exists( 'directoryweb_item_more_card' ) ) {
		return '';
	}
	directoryweb_use_component_style( 'directoryweb-single' ); // directoryweb-more-card, directoryweb-more-head, directoryweb-more-list
	/* translators: %s: organisation. */
	return '<section class="directoryweb-more-group directoryweb-listing-opps" aria-labelledby="directoryweb-opps-h"><h2 class="directoryweb-more-head has-x-large-font-size" id="directoryweb-opps-h">' . esc_html( sprintf( __( 'Opportunities at %s', 'directoryweb' ), get_the_title( $id ) ) ) . '</h2><ul class="kit-minicard-list directoryweb-more-list">' . implode( '', array_map( 'directoryweb_item_more_card', $ids ) ) . '</ul></section>';
}

/* ---------- Campaigns with {organisation}: role chip, up to 3 ---------- */

/** Campaigns linked to a listing, newest first: [ campaign ID => role ]. Organiser = directoryweb_organisation, else the older directoryweb_organiser; Partner = directoryweb_campaign_partners plus older directoryweb_participant rows; Supporter = directoryweb_campaign_supporters. */
function directoryweb_listing_campaigns( $id, $max = 3 ) {
	$base  = array( 'post_type' => 'directoryweb_campaign', 'post_status' => 'publish', 'numberposts' => 30, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' );
	$roles = array();
	foreach ( get_posts( $base + array( 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'directoryweb_organisation', 'value' => (string) $id ), array( 'key' => 'directoryweb_organiser', 'value' => (string) $id ) ) ) ) as $c ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( (int) $id === directoryweb_campaign_organiser( $c ) ) { // an older directoryweb_organiser row counts only while directoryweb_organisation is empty
			$roles[ $c ] = __( 'Organiser', 'directoryweb' );
		}
	}
	$partners = get_posts( $base + array( 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'directoryweb_participant', 'value' => (string) $id ), array( 'key' => 'directoryweb_campaign_partners', 'value' => (string) $id, 'compare' => 'LIKE' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $partners as $c ) {
		if ( ! isset( $roles[ $c ] ) && in_array( (int) $id, directoryweb_campaign_partner_ids( $c ), true ) ) {
			$roles[ $c ] = __( 'Partner', 'directoryweb' );
		}
	}
	$sups = get_posts( $base + array( 'meta_query' => array( array( 'key' => 'directoryweb_campaign_supporters', 'value' => (string) $id, 'compare' => 'LIKE' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $sups as $c ) {
		if ( ! isset( $roles[ $c ] ) && in_array( (int) $id, array_map( 'intval', explode( ',', (string) get_post_meta( $c, 'directoryweb_campaign_supporters', true ) ) ), true ) ) {
			$roles[ $c ] = __( 'Supporter', 'directoryweb' );
		}
	}
	uksort( $roles, fn( $x, $y ) => strcmp( get_post_field( 'post_date', $y ), get_post_field( 'post_date', $x ) ) ); // newest first
	return array_slice( $roles, 0, $max, true );
}

function directoryweb_block_listing_campaigns( $a, $id ) {
	directoryweb_use_component_style( 'directoryweb-single' ); // directoryweb-more-card, directoryweb-more-head, directoryweb-more-list
	if ( ! directoryweb_listing_here( $id ) ) {
		return function_exists( 'directoryweb_item_editor_preview' ) ? directoryweb_item_editor_preview( 'Campaigns with the organisation (Organiser / Partner / Supporter, up to 3)' ) : '';
	}
	$roles = directoryweb_listing_campaigns( $id );
	if ( ! $roles || ! function_exists( 'directoryweb_item_more_card' ) ) {
		return '';
	}
	$cards = '';
	foreach ( $roles as $c => $role ) {
		$chip   = '<span class="kit-tag directoryweb-camp-role">' . esc_html( $role ) . '</span>';
		$cards .= preg_replace( '#(<p class="kit-minicard-meta directoryweb-more-meta kit-meta-row">.*?)(</p>)#s', '$1' . $chip . '$2', directoryweb_item_more_card( $c ), 1 ); // after the date: the date always comes first
	}
	/* translators: %s: organisation. */
	return '<section class="directoryweb-more-group directoryweb-listing-campaigns" aria-labelledby="directoryweb-camps-h"><h2 class="directoryweb-more-head has-x-large-font-size" id="directoryweb-camps-h">' . esc_html( sprintf( __( 'Campaigns with %s', 'directoryweb' ), get_the_title( $id ) ) ) . '</h2><ul class="kit-minicard-list directoryweb-more-list">' . $cards . '</ul></section>';
}

/* ---------- Sidebar: Claim your listing (everywhere but an unclaimed profile, which has its own claim box) ---------- */

function directoryweb_block_claim_cta( $a = array(), $id = 0 ) {
	$q = get_queried_object_id();
	if ( is_singular( 'directoryweb_listing' ) && ! get_post_meta( $q, 'directoryweb_verified', true ) ) {
		return defined( 'REST_REQUEST' ) && REST_REQUEST ? '<p class="directoryweb-block-preview">' . esc_html__( 'Claim your listing', 'directoryweb' ) . '</p>' : '';
	}
	return '<section class="directoryweb-side-claim" aria-labelledby="directoryweb-side-claim-h"><h2 class="directoryweb-side-claim-title" id="directoryweb-side-claim-h">' . esc_html__( 'Claim your listing', 'directoryweb' ) . '</h2>'
		. '<p>' . esc_html__( 'Run an organisation listed in our Directory? Claim it to correct its details and add your logo and photos.', 'directoryweb' ) . '</p>'
		. '<a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/claim/' ) ) . '">' . esc_html__( 'Claim your listing', 'directoryweb' ) . '</a></section>';
}
