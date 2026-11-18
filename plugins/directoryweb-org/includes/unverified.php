<?php
/**
 * Unverified values on a listing (meta directoryweb_unverified): a value kept next to another one shows a small mark until the
 * organisation claims the listing. Written by enrichment (directoryweb-sourcing) and claims; shown by the listing template.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A value in its comparable form (phones: last 10 digits; email: lower case; web address: no scheme, www or slash). */
function directoryweb_unverified_norm( $v ) {
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
function directoryweb_unverified_list( $lid ) {
	$v = get_post_meta( $lid, 'directoryweb_unverified', true );
	return is_array( $v ) ? array_values( $v ) : array();
}

/** Mark values unverified. */
function directoryweb_unverified_add( $lid, array $values ) {
	$list = array_merge( directoryweb_unverified_list( $lid ), array_map( 'directoryweb_unverified_norm', $values ) );
	update_post_meta( $lid, 'directoryweb_unverified', array_values( array_unique( array_filter( $list ) ) ) );
}

/** A reviewer approved a value: it is no longer unverified. */
function directoryweb_unverified_remove( $lid, $value ) {
	$n = directoryweb_unverified_norm( $value );
	$l = array_values( array_diff( directoryweb_unverified_list( $lid ), array( $n ) ) );
	$l ? update_post_meta( $lid, 'directoryweb_unverified', $l ) : delete_post_meta( $lid, 'directoryweb_unverified' );
}

/** The small mark after a value that is not verified. */
function directoryweb_unverified_mark( $lid, $value ) {
	if ( ! in_array( directoryweb_unverified_norm( $value ), directoryweb_unverified_list( $lid ), true ) ) {
		return '';
	}
	$why = 'Unverified: this listing has another value for it. The organisation can confirm it by claiming the listing.';
	return ' <span class="directoryweb-unverified" title="' . esc_attr( $why ) . '">' . directoryweb_i( 'alert' ) . '<span class="screen-reader-text">' . esc_html( $why ) . '</span></span>';
}
