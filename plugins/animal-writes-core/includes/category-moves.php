<?php
/**
 * Categories moved to WordPress's own category (owner, 15 Oct 2026). The old addresses keep working with one 301:
 *
 * - /topic/{slug}/ (and its /page/N/ and /feed/) → /category/{slug}/…
 * - ?topic= on a list → ?category= (the same list, same values).
 * - /?cat=N for a Resource type (Guide, Research, FAQ, How-to were WordPress categories until then) → the Resources
 *   list filtered by that type (/resources/?kind={slug}).
 * - /topics/ → /categories/ is in Rank Math → Redirections (tools/categories/migrate.php adds it).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The current request's path and query string. */
function aw_moves_request() {
	$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return array( (string) wp_parse_url( $uri, PHP_URL_PATH ), (string) wp_parse_url( $uri, PHP_URL_QUERY ) );
}

add_action(
	'parse_request',
	function () {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		list( $path, $query ) = aw_moves_request();
		$to = '';
		if ( preg_match( '#^/topic/(.+)$#', $path, $m ) ) {
			$to = '/category/' . $m[1];
		}
		parse_str( $query, $args );
		if ( isset( $args['topic'] ) ) {
			if ( ! isset( $args['category'] ) ) {
				$args['category'] = $args['topic'];
			}
			unset( $args['topic'] );
			$to = '' !== $to ? $to : $path;
		}
		if ( isset( $args['cat'] ) && is_numeric( $args['cat'] ) ) {
			$t = get_term( (int) $args['cat'] );
			if ( $t instanceof WP_Term && 'aw_resource_type' === $t->taxonomy ) {
				unset( $args['cat'] );
				$to   = '/resources/';
				$args = array( 'kind' => $t->slug ) + $args;
			}
		}
		if ( '' === $to ) {
			return;
		}
		$q = $args ? '?' . str_replace( array( '%2C', '%5B', '%5D' ), array( ',', '[', ']' ), http_build_query( $args ) ) : '';
		wp_safe_redirect( home_url( $to ) . $q, 301, 'Animal Writes' );
		exit;
	},
	1
);
