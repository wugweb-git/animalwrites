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
		'organisation' => array( 'ORG', 'The organisation', '', '' ),
	);
}

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
	} elseif ( $name ) {
		$slugs[] = 'gov';
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
		if ( 'organisation' === $slug ) {
			$on = (string) get_post_meta( $id, 'aw_verified_on', true );
		}
		$out[] = array( $slug, $mark, $label, $on, $link );
	}
	return $out;
}

/**
 * Small labels for a listing's sources.
 *
 * @param int  $id   Listing ID.
 * @param bool $full Also write the source's name and date (profile page); cards show the mark only.
 * @return string
 */
function aw_source_labels( $id, $full = false ) {
	$variant = array( 'awbi' => 'success', 'awbi-abc' => 'success', 'gov' => 'info', 'awol' => '', 'organisation' => 'accent' );
	$html    = '';
	foreach ( aw_listing_sources( $id ) as list( $slug, $mark, $label, $on, $link ) ) {
		$title = $label . ( $on ? ', as on ' . wp_date( 'j M Y', strtotime( $on ) ) : '' );
		$tag   = '<span class="kit-tag aw-datasrc' . ( $variant[ $slug ] ? ' kit-tag--' . $variant[ $slug ] : '' ) . '" title="' . esc_attr( $title ) . '"><span class="aw-datasrc-mark" aria-hidden="true">' . esc_html( $mark ) . '</span><span class="screen-reader-text">' . esc_html( 'Source: ' . $title ) . '</span></span>';
		if ( $full ) {
			$text = $link ? '<a href="' . esc_url( $link ) . '" rel="noopener nofollow">' . esc_html( $title ) . '</a>' : esc_html( $title );
			$tag  = '<span class="aw-datasrc-row">' . $tag . ' ' . $text . '</span>';
		}
		$html .= $tag;
	}
	return $html ? '<span class="aw-datasrcs' . ( $full ? ' is-full' : '' ) . '">' . $html . '</span>' : '';
}
