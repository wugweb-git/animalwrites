<?php
/**
 * Categories moved to WordPress's own category (owner, 15 Oct 2026). The old addresses keep working with one 301:
 *
 * - /topic/{slug}/ (and its /page/N/ and /feed/) → /category/{slug}/…
 * - ?topic= on a list → ?category_name= (WordPress's own query var; the same list, same values).
 * - /?cat=N of an old Resource type: tags.php.
 * - /topics/ → /categories/ is in Rank Math → Redirections (tools/categories/migrate.php adds it).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Old topic slugs → the Category that holds them now. */
const AW_OLD_TOPIC_SLUGS = array( 'stray-animals' => 'community-dogs' );

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
		if ( preg_match( '#^/topic/([^/]+)(/.*)?$#', $path, $m ) ) {
			// Old slugs that are not a Category any more go to the Category that replaced them; any other unknown one to
			// the Categories index (never to a /category/ address that is a 404).
			$slug = AW_OLD_TOPIC_SLUGS[ $m[1] ] ?? $m[1];
			$to   = term_exists( $slug, 'category' ) ? '/category/' . $slug . ( $m[2] ?? '/' ) : '/categories/';
		}
		parse_str( $query, $args );
		if ( isset( $args['topic'] ) ) {
			if ( ! isset( $args['category_name'] ) ) {
				$args['category_name'] = $args['topic'];
			}
			unset( $args['topic'] );
			$to = '' !== $to ? $to : $path;
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
