<?php
/**
 * Shared-cache (Hostinger CDN, hcdn) lifetime for public HTML (owner, 18 Oct 2026).
 *
 * Tested on a throwaway path: hcdn stores a response only when its Cache-Control gives a positive lifetime
 * (`s-maxage=N`, or `max-age=N`); `max-age=0, s-maxage=N` is NOT stored. It ignores request cookies (a logged-in request
 * is answered from a stored anonymous copy) and never stores a response that sets a cookie. Each edge keeps its own copy.
 * There is no per-URL purge we can call from WordPress, so a change shows within the lifetime (default 300 s).
 *
 * `Cache-Control: public, s-maxage=N` (browsers get no lifetime, so they always revalidate) is added only to anonymous GET
 * requests for public pages answered 200 without a query string. Never on: wp-admin, logged-in/commenter/password
 * cookies, REST, feeds, 404, search, previews, forms and nonce pages, crisis guides, anything that sets a cookie.
 *
 * Off by default. Option aw_cdn_ttl (seconds, 0 = off; 60 to 900). For a test, any public URL with ?cdnprobe=1 gets the
 * header while the option is 0.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Pages that carry a form, a nonce or personal data: never in the shared cache. */
function aw_cdn_excluded_slugs() {
	return (array) apply_filters(
		'aw_cdn_excluded_slugs',
		array( 'contact', 'volunteer', 'claim', 'remove-my-data', 'report', 'thank-you', 'corrections', 'helplines', 'search', 'login', 'register', 'my-account' )
	);
}

/** The lifetime to send for this request, 0 for none. */
function aw_cdn_ttl() {
	$ttl = (int) get_option( 'aw_cdn_ttl', 0 );
	if ( $ttl <= 0 && isset( $_GET['cdnprobe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$ttl = 300;
	}
	if ( $ttl <= 0 ) {
		return 0;
	}
	$ttl = max( 60, min( 900, $ttl ) );

	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || is_admin() || is_user_logged_in() || is_404() || is_search() || is_feed() || is_preview() || is_embed() || is_customize_preview() || is_robots() || post_password_required() || wp_doing_ajax() || wp_is_json_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return 0;
	}
	// Any query string (filters, ?q=, ?s=, tracking, previews) stays out; only the test flag is allowed.
	// The raw query string, not $_GET: blocks add their own keys to $_GET (query-N-page) while rendering.
	$qs = array();
	parse_str( (string) ( $_SERVER['QUERY_STRING'] ?? '' ), $qs );
	unset( $qs['cdnprobe'] );
	if ( $qs || ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		return 0;
	}
	foreach ( array_keys( $_COOKIE ) as $name ) {
		if ( preg_match( '/^(wordpress_logged_in|wordpress_sec_|wp-postpass_|comment_author_|wp-settings-|aw_|kits_)/', (string) $name ) ) {
			return 0;
		}
	}
	if ( is_page( aw_cdn_excluded_slugs() ) || ( is_singular() && false !== stripos( (string) get_post_field( 'post_content', get_queried_object_id() ), 'fluentform' ) ) ) {
		return 0;
	}
	if ( is_singular( 'post' ) && defined( 'AW_CRISIS_GUIDES' ) && in_array( get_queried_object_id(), AW_CRISIS_GUIDES, true ) ) {
		return 0;
	}
	if ( headers_sent() || preg_grep( '/^set-cookie:/i', headers_list() ) ) {
		return 0;
	}
	return (int) apply_filters( 'aw_cdn_ttl', $ttl );
}

add_action(
	'template_redirect',
	function () {
		$ttl = aw_cdn_ttl();
		if ( $ttl > 0 ) {
			header( 'Cache-Control: public, s-maxage=' . $ttl, true );
			header( 'X-AW-CDN: s-maxage=' . $ttl, true );
		}
	},
	99999
);

/**
 * A term created, renamed or edited purges only its own archive page, not the whole site (owner, 18 Oct 2026). The
 * LiteSpeed setting "purge-hook_all" no longer lists create_term / edit_terms; the workers create tags all day.
 */
add_action(
	'edited_term',
	function ( $term_id, $tt_id, $taxonomy ) {
		$link = get_term_link( (int) $term_id, $taxonomy );
		if ( ! is_wp_error( $link ) ) {
			do_action( 'litespeed_purge_url', $link );
		}
	},
	10,
	3
);

/*
 * Page cache purged after every deploy (owner, 24 Oct 2026). A merge that reaches the server through the Deploy check
 * (it moves the live branch, Hostinger pulls it) left LiteSpeed serving pages built by the old code; aw-ship.sh
 * purges, the gate does not. So the site checks the commit its plugins were deployed from (the git checkout the plugin
 * folders link into) on every web request PHP serves (cron, REST, admin, uncached pages) and purges the whole cache once when it
 * changes, whatever path the code came by. Reads one 41-byte file and an autoloaded option; no remote call, no secret.
 * The Hostinger CDN copy (aw_cdn_ttl, above) has no purge we can call: when it is on, a deploy shows there within its TTL.
 */

/** The deployed commit (40 hex characters), or '' when the plugins are not in a git checkout. */
function aw_deployed_commit() {
	static $commit = null;
	if ( null !== $commit ) {
		return $commit;
	}
	$commit = '';
	$dir    = realpath( AW_CORE_DIR );
	for ( $i = 0; $dir && $i < 4; $i++, $dir = dirname( $dir ) ) { // The plugin, plugins/, then the checkout.
		$head = $dir . '/.git/HEAD';
		if ( is_readable( $head ) ) {
			$ref = trim( (string) file_get_contents( $head ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( 0 === strpos( $ref, 'ref: ' ) ) { // A branch checkout: the commit is in the ref file.
				$file = $dir . '/.git/' . substr( $ref, 5 );
				$ref  = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
			$commit = preg_match( '/^[0-9a-f]{40}$/', $ref ) ? $ref : '';
			break;
		}
	}
	return $commit;
}

add_action(
	'init',
	function () {
		// A web request only: LiteSpeed purges through a header on this response, which WP-CLI never sends. The next
		// request PHP serves (cron over HTTP, a worker's REST call, an admin page) does it.
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || headers_sent() ) { // Headers already out: the next request purges.
			return;
		}
		$now = aw_deployed_commit();
		if ( '' === $now ) {
			return;
		}
		$was = (string) get_option( 'aw_deployed_commit', '' );
		if ( $now === $was ) {
			return;
		}
		update_option( 'aw_deployed_commit', $now, true ); // Autoloaded: the check costs no query.
		do_action( 'litespeed_purge_all' ); // The first check too: the deploy that ships this code purges as well.
	},
	99
);
