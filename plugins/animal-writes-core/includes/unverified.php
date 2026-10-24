<?php
/**
 * Unverified values on a listing (meta aw_unverified): a value kept next to another one shows a small mark until the
 * organisation claims the listing. Written by enrichment (aw-sourcing) and claims; shown by the listing template.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A value in its comparable form (phones: last 10 digits; email: lower case; web address: no scheme, www or slash). */
function aw_unverified_norm( $v ) {
	$v = strtolower( trim( (string) $v ) );
	if ( false !== strpos( $v, '@' ) ) {
		return $v;
	}
	if ( '' !== $v && ! preg_match( '/[a-z]/', $v ) ) {
		return substr( preg_replace( '/\D+/', '', $v ), -10 );
	}
	return rtrim( preg_replace( '#^https?://(www\.|m\.)?#', '', $v ), '/' );
}

/** The values of a listing that are shown as unverified (kept next to another one; cleared when the organisation claims it). */
function aw_unverified_list( $lid ) {
	$v = get_post_meta( $lid, 'aw_unverified', true );
	return is_array( $v ) ? array_values( $v ) : array();
}

/** Mark values unverified. */
function aw_unverified_add( $lid, array $values ) {
	$list = array_merge( aw_unverified_list( $lid ), array_map( 'aw_unverified_norm', $values ) );
	update_post_meta( $lid, 'aw_unverified', array_values( array_unique( array_filter( $list ) ) ) );
}

/** A reviewer approved a value: it is no longer unverified. */
function aw_unverified_remove( $lid, $value ) {
	$n = aw_unverified_norm( $value );
	$l = array_values( array_diff( aw_unverified_list( $lid ), array( $n ) ) );
	$l ? update_post_meta( $lid, 'aw_unverified', $l ) : delete_post_meta( $lid, 'aw_unverified' );
}

/** The small mark after a value that is not verified. */
function aw_unverified_mark( $lid, $value ) {
	if ( ! in_array( aw_unverified_norm( $value ), aw_unverified_list( $lid ), true ) ) {
		return '';
	}
	$why = 'Unverified: this listing has another value for it. The organisation can confirm it by claiming the listing.';
	return ' <span class="aw-unverified" title="' . esc_attr( $why ) . '">' . aw_i( 'alert' ) . '<span class="screen-reader-text">' . esc_html( $why ) . '</span></span>';
}
