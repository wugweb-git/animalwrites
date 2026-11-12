<?php
/**
 * Redirects. The one list is Rank Math → Redirections (owner, 10 Oct 2026): aw_redirect_add() adds to it, so forms,
 * merges and migrations keep writing to that one list. Author archives go to About by a Rank Math rule (^author);
 * /?author=N goes there in one hop (below).
 *
 * Fixed old addresses go into that list (audit item 18, owner 25 Oct 2026): `wp aw redirects-import` (places.php) adds
 * the old /location/{slug}/ and /source/{slug}/ pages (their PHP handler was removed once the crawl showed Rank Math
 * answers every one). `wp aw redirects-list`
 * prints the whole list for tools/redirects/crawl.py, which requests every old address and reports any 404, wrong
 * target, and which layer answered (X-Redirect-By: "Rank Math" or "Animal Writes").
 *
 * Rules Rank Math's list cannot hold (they rewrite the address or read the query string rather than match a path) stay
 * in PHP:
 * - /NEWS/ and /News/ → /news/ (one 301 to the lower-case path; the query string is kept).
 * - ?lang=, ?issuer= and ?court= on the Library → the same list without them; /?author=N → /about/ (below).
 * - A missing address is a 404, never a 301 to whichever item WordPress guesses from the slug.
 * - Labels are WordPress Tags with their own pages (/tag/{slug}/); old type addresses (/?cat=N, /directory-type/…,
 *   /opportunity-type/…) and ?kind=, ?type=, ?service= are redirected in animal-writes-core tags.php.
 * - animal-writes-core: /topic/{slug}/… and ?topic= (category-moves.php), older /directory/ shapes (directory.php),
 *   ?query-N-page= on hubs (hubs.php), ?s= → /search/ and ?about= on Contact (pages.php), /report/?listing=
 *   (form-blocks.php), comment feeds (admin-ia.php).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add one 301 to Rank Math's list: $pattern (a path, or a regex when $comparison is 'regex') to $to (a URL).
 * An exact path already in the list is left alone. Returns the redirection ID, or 0.
 *
 * @param string $pattern    Path without slashes at the ends, or a regex.
 * @param string $to         Destination URL (absolute, or a path on this site).
 * @param string $comparison exact | regex.
 * @return int
 */
function aw_rank_math_redirect( $pattern, $to, $comparison = 'exact' ) {
	if ( '' === $pattern || '' === $to || ! class_exists( '\RankMath\Redirections\Redirection' ) || ! \RankMath\Helper::is_module_active( 'redirections' ) ) {
		return 0;
	}
	if ( 'exact' === $comparison && class_exists( '\RankMath\Redirections\DB' ) ) {
		$have = \RankMath\Redirections\DB::match_redirections_source( $pattern );
		foreach ( (array) $have as $row ) {
			foreach ( (array) maybe_unserialize( $row['sources'] ?? '' ) as $src ) {
				if ( 'exact' === ( $src['comparison'] ?? '' ) && trim( (string) $src['pattern'], '/' ) === $pattern ) {
					return (int) $row['id'];
				}
			}
		}
	}
	$r = \RankMath\Redirections\Redirection::from(
		array(
			'sources'     => array( array( 'pattern' => $pattern, 'comparison' => $comparison ) ),
			'url_to'      => $to,
			'header_code' => 301,
			'status'      => 'active',
		)
	);
	return (int) $r->save();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * `wp aw redirects-list`: every active redirect in Rank Math's list as CSV (path, target, code, comparison), read
	 * only. Exact paths are what tools/redirects/crawl.py requests; regex and other rules are listed for reference.
	 */
	WP_CLI::add_command(
		'aw redirects-list',
		function () {
			global $wpdb;
			$table = $wpdb->prefix . 'rank_math_redirections';
			$rows  = $wpdb->get_results( "SELECT sources, url_to, header_code FROM {$table} WHERE status = 'active' ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB
			$out   = fopen( 'php://output', 'w' );
			fputcsv( $out, array( 'path', 'target', 'code', 'comparison' ) );
			foreach ( (array) $rows as $r ) {
				foreach ( (array) maybe_unserialize( $r['sources'] ) as $src ) {
					$cmp = (string) ( $src['comparison'] ?? '' );
					$pat = (string) ( $src['pattern'] ?? '' );
					$path = '/' . trim( $pat, '/' ) . ( false === strpos( basename( $pat ), '.' ) ? '/' : '' ); // a file keeps no trailing slash
					fputcsv( $out, array( 'exact' === $cmp ? $path : $pat, (string) $r['url_to'], (int) $r['header_code'], $cmp ) );
				}
			}
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	);
}

/** Remember that $old_url now lives at post $target (an ID), or at a URL (a string such as '/volunteer/'). */
function aw_redirect_add( $old_url, $target ) {
	$path = trim( (string) wp_parse_url( $old_url, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}
	$to = is_numeric( $target ) ? (string) get_permalink( (int) $target ) : home_url( (string) $target );
	if ( $to ) {
		aw_rank_math_redirect( rawurldecode( $path ), $to );
	}
}

// A link written as "https:/animalwrites.in/…" (one slash) or "animalwrites.in/…" without a scheme is read as a path on
// this site: /https:/animalwrites.in/news/ (79 hits in the 404 Monitor, no referer; audit 12 Nov 2026). It goes to
// the page it meant. The site's own templates and stored content have no such link; the hits come from outside.
add_action(
	'parse_request',
	function () {
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! preg_match( '#^/+(?:https?:/+)?(?:www\.)?animalwrites\.in(/[^?]*)?(\?.*)?$#i', $uri, $m ) ) {
			return;
		}
		wp_safe_redirect( home_url( '' !== ( $m[1] ?? '' ) ? $m[1] : '/' ) . ( $m[2] ?? '' ), 301, 'Animal Writes' );
		exit;
	},
	0
);

// Module imports by name ("@wordpress/interactivity") are resolved by the import map, which every page prints in its
// head. Crawlers that ignore import maps read the name as a path beside the importing file (core's navigation
// view.min.js, the theme's dropdown.js): 65 hits in the 404 Monitor (audit 12 Nov 2026). Browsers never ask for these
// paths; they go to the module itself.
add_action(
	'parse_request',
	function () {
		$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( preg_match( '#^/wp-(?:includes|content)/.*/@wordpress/interactivity(?:\.js)?$#', $path ) ) {
			wp_safe_redirect( includes_url( 'js/dist/script-modules/interactivity/index.min.js' ), 301, 'Animal Writes' );
			exit;
		}
	},
	0
);

// Addresses that are gone for good answer 410 (audit, 12 Nov 2026; checked the same day: no file of the build and none
// of 20 live pages prints them): the removed kits-core ad slot script and Core's old visit counter, plugin files under
// a server path (wp-content/plugins/home/…/aw_deploy/…, a plugins_url() address from before the plugin links of
// 10–11 Nov), and sitemaps of types or taxonomies that no longer exist (news-sitemap.xml itself is the Google News
// sitemap again since 12 Nov 2026, sitemap.php; only numbered copies stay gone). Old kits-cache CSS files are left at 404:
// their names come from each build, so a pattern could catch a file the next build writes.
add_action(
	'parse_request',
	function () {
		$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( preg_match( '#^/(?:wp-content/plugins/home/u544169507/.+|wp-content/plugins/kits-core/assets/js/ad-slot\.js|wp-content/plugins/animal-writes-core/visit\.php|(?:aw_listing_type|aw_guide)-sitemap\d*\.xml|news-sitemap\d+\.xml)$#', $path ) ) {
			status_header( 410 );
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'Gone';
			exit;
		}
	},
	0
);

// Upper-case paths go to the lower-case one. Files (a dot in the last part), the admin, REST and login are left alone.
add_action(
	'parse_request',
	function () {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$bare = preg_replace( '/%[0-9A-Fa-f]{2}/', '', $path ); // percent-encoded letters are not upper case
		if ( ! preg_match( '/[A-Z]/', $bare ) || preg_match( '#^/(wp-|xmlrpc)#i', $path ) || false !== strpos( basename( $path ), '.' ) ) {
			return;
		}
		$lower = preg_replace_callback( '/(%[0-9A-Fa-f]{2})|([^%]+)/', fn( $m ) => '' !== $m[1] ? $m[1] : strtolower( $m[2] ), $path );
		$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		wp_safe_redirect( home_url( $lower ) . ( '' !== $query ? '?' . $query : '' ), 301, 'Animal Writes' );
		exit;
	},
	0
);

// Language is gone from the Library (Phase 12 B4); Issuer and Court are labels now (8 Oct 2026).
add_action(
	'template_redirect',
	function () {
		if ( ( isset( $_GET['lang'] ) || isset( $_GET['issuer'] ) || isset( $_GET['court'] ) ) && is_post_type_archive( 'aw_legal' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( remove_query_arg( array( 'lang', 'issuer', 'court' ) ), 301 );
			exit;
		}
	},
	1
);

// /?author=N goes to About in one hop (WordPress would first send it to /author/{name}/, which the ^author rule sends on).
add_action(
	'template_redirect',
	function () {
		if ( isset( $_GET['author'] ) && is_author() ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/about/' ), 301, 'Animal Writes' );
			exit;
		}
	},
	1
);

// The Home page has no page 2: /page/2/ (any number) is a copy of Home, so it goes to Home in one hop (5 Nov 2026).
add_action(
	'template_redirect',
	function () {
		if ( is_front_page() && 'page' === get_option( 'show_on_front' ) && max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ) > 1 ) {
			wp_safe_redirect( home_url( '/' ), 301, 'Animal Writes' );
			exit;
		}
	},
	1
);

add_filter( 'do_redirect_guess_404_permalink', '__return_false' );

// A redirect never leads to the trash (owner, 15 Oct 2026): trashing an item switches off the redirects that point to
// it, and restoring it switches them back on. The status is changed with Rank Math's own call
// (\RankMath\Redirections\DB::change_status, which also stamps the redirect's "updated" date), so this needs Rank
// Math's Redirections module; without it no redirect is served anyway.
add_action(
	'wp_trash_post',
	function ( $post_id ) {
		global $wpdb;
		$url = (string) get_permalink( $post_id );
		if ( '' === $url || ! class_exists( '\RankMath\Redirections\DB' ) ) {
			return;
		}
		$table = $wpdb->prefix . 'rank_math_redirections';
		$ids   = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status = 'active' AND url_to IN ( %s, %s )", $url, untrailingslashit( $url ) ) ) ); // phpcs:ignore WordPress.DB
		if ( $ids ) {
			\RankMath\Redirections\DB::change_status( $ids, 'inactive' );
			update_post_meta( $post_id, '_aw_redirects_paused', $ids );
		}
	}
);
add_action(
	'untrashed_post',
	function ( $post_id ) {
		$ids = array_filter( array_map( 'intval', (array) get_post_meta( $post_id, '_aw_redirects_paused', true ) ) );
		if ( ! $ids || ! class_exists( '\RankMath\Redirections\DB' ) ) {
			return; // kept for when the Redirections module is back
		}
		\RankMath\Redirections\DB::change_status( $ids, 'active' );
		delete_post_meta( $post_id, '_aw_redirects_paused' );
	}
);

// Old archive addresses (/?cat=N of a Resource type, /directory-type/…, /opportunity-type/…): animal-writes-core tags.php.
