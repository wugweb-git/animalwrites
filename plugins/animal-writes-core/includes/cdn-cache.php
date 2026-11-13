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
 * Off by default. Option aw_cdn_ttl (seconds, 0 = off; 60 to 3600). For a test, any public URL with ?cdnprobe=1 gets the
 * header while the option is 0.
 *
 * Pages that change rarely (1 Nov 2026): Directory listings, Library items and Resources get the longer lifetime of option
 * aw_cdn_ttl_long (default 3600 s; 0 = the same as aw_cdn_ttl), plus `stale-while-revalidate=600`, so an edge serves its
 * copy while it fetches a new one. Home, News, hubs, Directory places and campaigns keep aw_cdn_ttl: they change with
 * every sourcing run.
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
	$ttl = max( 60, min( 3600, $ttl ) );

	if ( 'GET' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || is_admin() || is_user_logged_in() || is_404() || is_search() || is_feed() || is_preview() || is_embed() || is_customize_preview() || is_robots() || post_password_required() || wp_doing_ajax() || wp_is_json_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return 0;
	}
	// Any query string (filters, ?q=, ?s=, tracking, previews) stays out; only the test flag is allowed.
	// The raw query string, not $_GET: blocks add their own keys to $_GET (query-N-page) while rendering.
	$qs = array();
	parse_str( (string) wp_unslash( $_SERVER['QUERY_STRING'] ?? '' ), $qs ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only read for its parameter names; a sanitizer would break the percent-encoding
	unset( $qs['cdnprobe'] );
	if ( $qs || ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		return 0;
	}
	// aw_fv (aw-analytics.js: first visit date and visit count) is on every returning visitor and never changes the page.
	foreach ( array_keys( $_COOKIE ) as $name ) {
		if ( 'aw_fv' !== $name && preg_match( '/^(wordpress_logged_in|wordpress_sec_|wp-postpass_|comment_author_|wp-settings-|aw_|kits_)/', (string) $name ) ) {
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
	if ( aw_cdn_is_long_lived() ) {
		$long = (int) get_option( 'aw_cdn_ttl_long', 3600 );
		$ttl  = $long > 0 ? max( $ttl, min( 3600, $long ) ) : $ttl;
	}
	return (int) apply_filters( 'aw_cdn_ttl', $ttl );
}

/** Whether this page changes rarely: a Directory listing, a Library item or a Resource (not a hub, not a crisis guide). */
function aw_cdn_is_long_lived() {
	return is_singular( array( 'aw_listing', 'aw_legal', 'post' ) ) && ! ( function_exists( 'aw_hub_type' ) && aw_hub_type() );
}

add_action(
	'template_redirect',
	function () {
		$ttl = aw_cdn_ttl();
		if ( $ttl > 0 ) {
			$swr = aw_cdn_is_long_lived() ? ', stale-while-revalidate=600' : '';
			header( 'Cache-Control: public, s-maxage=' . $ttl . $swr, true );
			header( 'X-AW-CDN: s-maxage=' . $ttl . $swr, true );
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

/**
 * Kits → Settings → Performance (Check site without Kits): a "Shared cache (CDN)" section for both lifetimes (owner, 2 Nov 2026). Core Settings API
 * and core markup only. Whole seconds: 0 (off; for the long one, the same as the short one) or 60 to 3600; anything
 * else is clamped into that range. The header a public page gets now is shown under the fields.
 *
 * @param mixed $v Submitted value.
 */
function aw_cdn_ttl_sanitize( $v ) {
	$v = (int) round( (float) $v );
	return $v <= 0 ? 0 : max( 60, min( 3600, $v ) );
}

add_action(
	'admin_init',
	function () {
		register_setting( 'aw_cdn', 'aw_cdn_ttl', array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'aw_cdn_ttl_sanitize', 'show_in_rest' => false ) );
		register_setting( 'aw_cdn', 'aw_cdn_ttl_long', array( 'type' => 'integer', 'default' => 3600, 'sanitize_callback' => 'aw_cdn_ttl_sanitize', 'show_in_rest' => false ) );
		add_settings_section( 'aw_cdn', 'Shared cache (CDN)', 'aw_cdn_settings_intro', 'aw-cdn' );
		add_settings_field( 'aw_cdn_ttl', 'Public pages', 'aw_cdn_settings_field', 'aw-cdn', 'aw_cdn', array( 'label_for' => 'aw_cdn_ttl', 'default' => 0, 'note' => 'Home, News, hubs, Directory places and campaigns. 0 = off.' ) );
		add_settings_field( 'aw_cdn_ttl_long', 'Listings, Library items and Resources', 'aw_cdn_settings_field', 'aw-cdn', 'aw_cdn', array( 'label_for' => 'aw_cdn_ttl_long', 'default' => 3600, 'note' => '0 = the same as public pages.' ) );
	}
);

/** Section text. */
function aw_cdn_settings_intro() {
	echo '<p>' . esc_html( 'Seconds the CDN keeps a copy of a page for visitors who are not logged in: 0, or 60 to 3600. A published change shows when the copy runs out.' ) . '</p>';
}

/**
 * One lifetime field.
 *
 * @param array $args label_for (the option), default, note.
 */
function aw_cdn_settings_field( $args ) {
	$id = (string) $args['label_for'];
	printf(
		'<input type="number" class="small-text" id="%1$s" name="%1$s" value="%2$d" min="0" max="3600" step="1"> %3$s<p class="description">%4$s</p>',
		esc_attr( $id ),
		(int) get_option( $id, (int) $args['default'] ),
		esc_html( 'seconds' ),
		esc_html( (string) $args['note'] )
	);
}

/** The Cache-Control header public pages get with the saved options ('' when off). */
function aw_cdn_header_now( bool $long ): string {
	$ttl = aw_cdn_ttl_sanitize( get_option( 'aw_cdn_ttl', 0 ) );
	if ( ! $ttl ) {
		return '';
	}
	if ( $long ) {
		$l   = aw_cdn_ttl_sanitize( get_option( 'aw_cdn_ttl_long', 3600 ) );
		$ttl = $l > 0 ? max( $ttl, $l ) : $ttl;
		return 'public, s-maxage=' . $ttl . ', stale-while-revalidate=600';
	}
	return 'public, s-maxage=' . $ttl;
}

/** Kits → Settings → Performance: CDN cache times (and Check site without Kits). */
function aw_cdn_settings_box() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! function_exists( 'kits_sections_page' ) ) {
		settings_errors( 'general' ); // options.php's "Settings saved." (core prints it only under the Settings menu; Kits → Settings prints it itself)
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
	settings_fields( 'aw_cdn' );
	do_settings_sections( 'aw-cdn' );
	$short = aw_cdn_header_now( false );
	$long  = aw_cdn_header_now( true );
	echo '<p>' . esc_html( 'Header now:' ) . '</p><ul>';
	echo '<li>' . esc_html( 'Public pages: ' ) . ( $short ? '<code>Cache-Control: ' . esc_html( $short ) . '</code>' : esc_html( 'none (CDN caching off)' ) ) . '</li>';
	echo '<li>' . esc_html( 'Listings, Library items and Resources: ' ) . ( $long ? '<code>Cache-Control: ' . esc_html( $long ) . '</code>' : esc_html( 'none (CDN caching off)' ) ) . '</li>';
	echo '</ul>';
	submit_button();
	echo '</form>';
}
add_action( 'aw_site_check_after', 'aw_cdn_settings_box', 20 );
