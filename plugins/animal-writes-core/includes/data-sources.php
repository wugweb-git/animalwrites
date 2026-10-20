<?php
/**
 * Where an organisation's details come from: one small label per data source on every listing.
 *
 * Each source has its own schema (the fields it gives) and date:
 * - awbi       AWBI list of recognised organisations, as on 31 May 2026: name, recognition code, address, valid until.
 * - awbi-abc   AWBI list of ABC projects: name, district, recognition and validity of the ABC project.
 * - gov        State animal husbandry departments and DAHD: name, place, type (veterinary institutions).
 * - awol       AWO List compiled by Saumya Garg (Dec 2023 – Jan 2025), schema 1.2: name, address, phone, whether the
 *              organisation said it has a shelter, rescue and treatment, notes from the call.
 * - organisation  What the organisation itself told us (claim approved).
 * - darpan     NGO Darpan (NITI Aayog), read from its own search (owner types the CAPTCHA): Darpan ID, registration, sectors.
 * - datagov    data.gov.in (Open Government Data Platform India) datasets.
 * - nalsa      District Legal Services Authorities (NALSA / State Legal Services Authorities): office, address, phone.
 * - gujforest  Gujarat Forest Department (Karuna Abhiyan): permanent bird treatment centres.
 * - pfa        People For Animals: the unit's phone and email from its unit page.
 * - web        A public web page listing organisations (adopt-a-pet.in, AWBP Trust, dogsindia.com); the page names itself.
 *   These four carry their own date (aw_source_date) and link (aw_source_url) from the import.
 * A listing shows only the sources it really has; nothing is shown for a source it does not come from.
 * Imports record their source in the listing meta aw_data_sources (list of slugs); older imports are recognised from
 * the fields they left (AWBI code, source name).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Source registry: slug => [mark, label, as of (Y-m-d or ''), link]. */
function aw_data_sources() {
	return array(
		'awbi'         => array( 'AWBI', 'AWBI list of recognised organisations', '2026-05-31', 'https://awbi.gov.in/' ),
		'awbi-abc'     => array( 'AWBI', 'AWBI list of ABC projects', '', 'https://awbi.gov.in/' ),
		'gov'          => array( 'GOV', '', '', '' ), // label and link come from the listing's own source name and URL
		'awol'         => array( 'AWOL', 'AWO List compiled by Saumya Garg', '2025-01-03', '' ),
		'gmaps'        => array( 'MAPS', 'Google Maps', '', '' ), // link: the listing's own Google Maps link
		'ngobase'      => array( 'NGOB', 'ngobase.org', '2026-10-09', '' ), // link: the organisation's ngobase.org profile
		'organisation' => array( 'ORG', 'The organisation', '', '' ),
		'darpan'       => array( 'DARPAN', 'NGO Darpan (NITI Aayog)', '', 'https://ngodarpan.gov.in/' ),
		'datagov'      => array( 'OGD', 'data.gov.in', '', '' ),
		'nalsa'        => array( 'NALSA', 'Legal Services Authority', '', '' ),
		'gujforest'    => array( 'GUJ', 'Gujarat Forest Department', '', '' ),
		'pfa'          => array( 'PFA', 'People For Animals', '', '' ),
		'web'          => array( 'WEB', 'Public list', '', '' ),
	);
}

/** Import sources that carry their own date and link on the listing (aw_source_date, aw_source_url). */
const AW_IMPORT_SOURCES = array( 'darpan', 'datagov', 'nalsa', 'gujforest', 'pfa', 'web' );

/**
 * The sources of one listing: list of [slug, mark, label, as of, link].
 *
 * @param int $id Listing ID.
 * @return array
 */
function aw_listing_sources( $id ) {
	$reg   = aw_data_sources();
	$slugs = array_filter( (array) get_post_meta( $id, 'aw_data_sources', true ) );
	$name  = (string) get_post_meta( $id, 'aw_source_name', true );
	if ( get_post_meta( $id, 'aw_awbi_code', true ) ) {
		$slugs[] = 'awbi';
	}
	if ( 'AWBI ABC list' === $name ) {
		$slugs[] = 'awbi-abc';
	} elseif ( $name && ! array_intersect( $slugs, AW_IMPORT_SOURCES ) && ! ( get_post_meta( $id, 'aw_awbi_code', true ) && false !== stripos( $name, 'AWBI list' ) ) ) { // an import source names itself; an older AWBI list is the AWBI label
		$slugs[] = false !== stripos( $name, 'google' ) ? 'gmaps' : 'gov'; // a Google profile is not a government list
	}
	if ( get_post_meta( $id, 'aw_g_profile', true ) ) {
		$slugs[] = 'gmaps';
	}
	if ( get_post_meta( $id, 'aw_verified', true ) ) {
		$slugs[] = 'organisation';
	}
	$out = array();
	foreach ( array_unique( $slugs ) as $slug ) {
		if ( ! isset( $reg[ $slug ] ) ) {
			continue;
		}
		list( $mark, $label, $on, $link ) = $reg[ $slug ];
		if ( 'gov' === $slug ) {
			$label = $name;
			$on    = (string) get_post_meta( $id, 'aw_source_date', true );
			$link  = (string) get_post_meta( $id, 'aw_source_url', true );
		}
		if ( in_array( $slug, AW_IMPORT_SOURCES, true ) ) {
			$label = $name ? $name : $label; // the list's own publisher, e.g. "Telangana State Legal Services Authority"
			$on    = (string) get_post_meta( $id, 'aw_source_date', true );
			$link = (string) get_post_meta( $id, 'aw_source_url', true );
		}
		if ( 'awbi' === $slug && get_post_meta( $id, 'aw_awbi_list_on', true ) ) {
			$on = (string) get_post_meta( $id, 'aw_awbi_list_on', true ); // an older AWBI list (e.g. active list of 21 Feb 2025)
		}
		if ( 'organisation' === $slug ) {
			$on = (string) get_post_meta( $id, 'aw_verified_on', true );
		}
		if ( 'ngobase' === $slug ) {
			$link = (string) get_post_meta( $id, 'aw_ngobase_url', true );
		}
		if ( 'gmaps' === $slug ) {
			$link  = (string) ( get_post_meta( $id, 'aw_g_profile', true ) ? get_post_meta( $id, 'aw_g_profile', true ) : get_post_meta( $id, 'aw_maps_url', true ) );
			$label = 'Google Business Profile';
		}
		$out[] = array( $slug, $mark, $label, $on, $link );
	}
	return apply_filters( 'aw_listing_sources', $out, $id ); // enrichment.php adds the enrichment worker's sources
}

/**
 * Small labels for a listing's sources.
 *
 * @param int  $id   Listing ID.
 * @param bool $full Also write the source's name and date (profile page); cards show the mark only.
 * @return string
 */
function aw_source_labels( $id, $full = false ) {
	$variant = array( 'awbi' => 'success', 'awbi-abc' => 'success', 'gov' => 'info', 'nalsa' => 'info', 'gujforest' => 'info', 'awol' => '', 'organisation' => '', 'gmaps' => '' );
	// Google Maps shows as its own pin, the organisation as a building icon (owner, 12 Oct 2026); the others as their letters.
	$marks = array(
		'gmaps'        => '<img class="aw-datasrc-img" src="' . esc_url( get_theme_file_uri( 'assets/img/google-maps.png' ) ) . '" alt="" width="16" height="16">',
		'organisation' => '<svg class="aw-datasrc-img" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>',
	);
	$html    = '';
	foreach ( aw_listing_sources( $id ) as list( $slug, $mark, $label, $on, $link ) ) {
		$title = $label . ( $on ? ', as on ' . wp_date( 'j M Y', strtotime( $on ) ) : '' );
		$tag   = '<span class="kit-tag aw-datasrc' . ( ! empty( $variant[ $slug ] ) ? ' kit-tag--' . $variant[ $slug ] : '' ) . '" title="' . esc_attr( $title ) . '"><span class="aw-datasrc-mark" aria-hidden="true">' . ( $marks[ $slug ] ?? esc_html( $mark ) ) . '</span><span class="screen-reader-text">' . esc_html( 'Source: ' . $title ) . '</span></span>';
		if ( $full ) { // "Details from": the source's logo, like publishers on News
			$logo = aw_source_logo( $slug, $id, $link );
			$tag  = $logo ? '<span class="aw-datasrc-logo" title="' . esc_attr( $title ) . '">' . $logo . '</span>' : $tag;
			$shown = esc_html( $label ) . ( $on ? ', as on ' . aw_date( $on ) : '' ); // the one date component
			$text  = $link ? '<a href="' . esc_url( $link ) . '" rel="noopener nofollow">' . $shown . '</a>' : $shown;
			$tag  = '<span class="aw-datasrc-row">' . $tag . ' <span class="aw-datasrc-text">' . $text . '</span></span>'; // one text cell: the date stays beside its label
		}
		$html .= $tag;
	}
	return $html ? '<span class="aw-datasrcs' . ( $full ? ' is-full' : '' ) . '">' . $html . '</span>' : '';
}

/** Hosts whose logo stands for a source. */
const AW_SOURCE_HOSTS = array( 'awbi' => 'https://awbi.gov.in/', 'awbi-abc' => 'https://awbi.gov.in/', 'gmaps' => 'https://www.google.com/maps/', 'darpan' => 'https://ngodarpan.gov.in/', 'datagov' => 'https://www.data.gov.in/', 'nalsa' => 'https://nalsa.gov.in/', 'gujforest' => 'https://forests.gujarat.gov.in/', 'pfa' => 'https://peopleforanimalsindia.org/', 'incometax' => 'https://www.incometax.gov.in/', 'mha' => 'https://www.mha.gov.in/' ); // incometax (12A, 80G) and mha (FCRA): the issuing bodies of a listing's registrations (listing-template.php)

/**
 * The logo of a source as an <img>: the list's site (AWBI, the department, Google Maps) or, for "The organisation", the
 * listing's own logo. Logos are fetched once per site into the Media Library (never while a page is drawn); '' if none.
 *
 * @param string $slug Source slug.
 * @param int    $id   Listing ID.
 * @param string $link The source's link.
 * @return string
 */
function aw_source_logo( $slug, $id, $link ) {
	if ( 0 === strpos( $slug, 'enrich-' ) && function_exists( 'aw_enrich_icon' ) ) { // a source of the enrichment worker: its site's favicon
		return aw_enrich_icon( array( 'key' => substr( $slug, 7 ), 'name' => '', 'url' => $link ), $id );
	}
	if ( 'organisation' === $slug ) {
		$tid = (int) get_post_thumbnail_id( $id );
		return $tid ? (string) wp_get_attachment_image( $tid, 'thumbnail', false, array( 'alt' => '', 'width' => 20, 'height' => 20, 'loading' => 'lazy' ) ) : '';
	}
	$url   = AW_SOURCE_HOSTS[ $slug ] ?? $link;
	$host  = preg_replace( '/^www\./', '', (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	$cache = (array) get_option( 'aw_press_logos', array() );
	$aid   = (int) ( $cache[ $host ] ?? 0 );
	return $aid ? (string) wp_get_attachment_image( $aid, 'thumbnail', false, array( 'alt' => '', 'width' => 20, 'height' => 20, 'loading' => 'lazy' ) ) : '';
}

/** Fetch the logos of every source site once (wp eval 'aw_source_logos_fetch();'). */
function aw_source_logos_fetch() {
	global $wpdb;
	$urls = array_values( AW_SOURCE_HOSTS );
	foreach ( $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'aw_listing' WHERE pm.meta_key = 'aw_source_url' AND pm.meta_value <> ''" ) as $u ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$urls[] = $u;
	}
	$done = array();
	foreach ( $urls as $u ) {
		$h = (string) wp_parse_url( $u, PHP_URL_HOST );
		if ( $h && ! isset( $done[ $h ] ) && function_exists( 'aw_press_site_logo' ) ) {
			$done[ $h ] = aw_press_site_logo( $u );
		}
	}
	return $done;
}

