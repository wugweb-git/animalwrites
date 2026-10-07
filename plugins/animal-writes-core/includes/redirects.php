<?php
/**
 * Permanent redirects for items that moved section or were merged (restructure Phase 3).
 *
 * Option aw_redirects maps an old path (no slashes at the ends) to the post that now holds it. A request that would
 * 404 at an old path gets one 301 to that post's current URL, so a move never costs a redirect chain.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Remember that $old_url now lives at post $post_id. */
function aw_redirect_add( $old_url, $post_id ) {
	$path = trim( (string) wp_parse_url( $old_url, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}
	$map          = (array) get_option( 'aw_redirects', array() );
	$map[ $path ] = (int) $post_id;
	update_option( 'aw_redirects', $map, false );
}

add_action(
	'template_redirect',
	function () {
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$map  = (array) get_option( 'aw_redirects', array() );
		$id   = (int) ( $map[ $path ] ?? $map[ rawurldecode( $path ) ] ?? 0 );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			wp_safe_redirect( get_permalink( $id ), 301, 'Animal Writes' );
			exit;
		}
	},
	0 // before WordPress guesses a redirect from the slug
);
