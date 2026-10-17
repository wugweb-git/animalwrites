<?php
/**
 * Preview: things only the site owner should see before they go public (empty ad slots, sample items).
 *
 * Who previews: logged-in administrators, requests from a preview IP address (Settings > Ads; IPv4 exact, IPv6 by its
 * /64 network), and registered preview devices (a long-lived HttpOnly cookie holding a random token; only its hash is
 * stored). Pages are served from a public cache, so the page HTML never varies by IP or device: it carries everything,
 * and CSS hides preview-only parts unless the page is marked html.kits-preview (set by a small script after asking the
 * uncached endpoint GET /wp-json/kits/v1/preview, asked only by browsers carrying the kits_pv marker; a preview IP
 * address marks a browser by opening any page once with ?kits-preview=1) or body.kits-preview (administrators, who
 * bypass the cache).
 *
 * Preview-only items (kits_preview_only_ids): left out of every list and search for everyone but administrators (their
 * cards also carry .kits-preview-only) and out of the sitemaps, and their own URL is never cached and returns 404 unless
 * the request previews (checked on the server; that URL is not cached).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KITS_PREVIEW_COOKIE = 'kits_pd';

/** A plain marker (no secret) telling the page script this browser may preview, so other visitors never ask the server. */
const KITS_PREVIEW_MARK = 'kits_pv';

/** Mark this browser as one that previews (readable by the page script; the device token stays HttpOnly). */
function kits_preview_mark() {
	if ( headers_sent() || '1' === ( $_COOKIE[ KITS_PREVIEW_MARK ] ?? '' ) ) {
		return;
	}
	setcookie( KITS_PREVIEW_MARK, '1', array( 'expires' => time() + 400 * DAY_IN_SECONDS, 'path' => '/', 'secure' => true, 'httponly' => false, 'samesite' => 'Lax' ) );
}

// Administrators: marked on any admin screen.
add_action( 'admin_init', fn() => current_user_can( 'manage_options' ) ? kits_preview_mark() : null );

/** Items only previewers see (post IDs). */
function kits_preview_only_ids() {
	return array_values( array_filter( array_map( 'intval', (array) apply_filters( 'kits_preview_only_ids', array() ) ) ) );
}

/** The preview IP list (one per line in Settings > Ads). */
function kits_preview_ips() {
	return array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) get_option( 'kits_preview_ips', '' ) ) ) ) );
}

/** The visitor's IP. LiteSpeed puts the real client address in REMOTE_ADDR (the CDN's own is in PROXY_REMOTE_ADDR). */
function kits_client_ip() {
	return trim( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

/** Is an IP on the preview list? IPv4: exact. IPv6: same /64 network (an entry may be written as "prefix::/64"). */
function kits_preview_ip_allowed( $ip ) {
	$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( false === $bin ) {
		return false;
	}
	foreach ( kits_preview_ips() as $entry ) {
		$e = @inet_pton( preg_replace( '#/\d+$#', '', $entry ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $e || strlen( $e ) !== strlen( $bin ) ) {
			continue;
		}
		if ( 4 === strlen( $bin ) ? $e === $bin : substr( $e, 0, 8 ) === substr( $bin, 0, 8 ) ) {
			return true;
		}
	}
	return false;
}

/** Hash of a device token (only hashes are stored). */
function kits_preview_hash( $token ) {
	return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
}

/** Registered preview devices: hash => [label, added, seen]. */
function kits_preview_devices() {
	return (array) get_option( 'kits_preview_devices', array() );
}

/** Does this request carry a registered device cookie? */
function kits_preview_device_ok() {
	$t = (string) ( $_COOKIE[ KITS_PREVIEW_COOKIE ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! preg_match( '/^[a-f0-9]{64}$/', $t ) ) {
		return false;
	}
	$all = kits_preview_devices();
	$h   = kits_preview_hash( $t );
	if ( ! isset( $all[ $h ] ) ) {
		return false;
	}
	if ( ( $all[ $h ]['seen'] ?? '' ) !== gmdate( 'Y-m-d' ) ) {
		$all[ $h ]['seen'] = gmdate( 'Y-m-d' );
		update_option( 'kits_preview_devices', $all, false );
	}
	return true;
}

/** Does this request preview (administrator, preview IP or preview device)? Only for uncached responses. */
function kits_preview_request() {
	return current_user_can( 'manage_options' ) || kits_preview_ip_allowed( kits_client_ip() ) || kits_preview_device_ok();
}

/** Register the current browser as a preview device: random token in a cookie, its hash stored. */
function kits_preview_register_device( $label ) {
	$token = bin2hex( random_bytes( 32 ) );
	$all   = kits_preview_devices();
	$all[ kits_preview_hash( $token ) ] = array(
		'label' => '' !== trim( (string) $label ) ? sanitize_text_field( (string) $label ) : 'Device',
		'added' => gmdate( 'Y-m-d' ),
		'seen'  => gmdate( 'Y-m-d' ),
	);
	update_option( 'kits_preview_devices', $all, false );
	kits_preview_mark();
	setcookie(
		KITS_PREVIEW_COOKIE,
		$token,
		array(
			'expires'  => time() + 400 * DAY_IN_SECONDS, // the longest browsers keep a cookie
			'path'     => '/',
			'secure'   => true,
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

/** Never cache this response (LiteSpeed, the CDN, browsers). */
function kits_preview_nocache() {
	do_action( 'litespeed_control_set_nocache', 'kits preview' );
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
	}
}

/* ---------- The endpoint and the page script ---------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'kits/v1',
			'/preview',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					kits_preview_nocache();
					$yes = kits_preview_ip_allowed( kits_client_ip() ) || kits_preview_device_ok() || current_user_can( 'manage_options' );
					if ( $yes ) {
						kits_preview_mark(); // later pages ask only from this browser
					}
					$r = new WP_REST_Response( array( 'preview' => $yes ) );
					$r->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
					$r->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
					return $r;
				},
			)
		);
	}
);

// Asks the endpoint once per browser session, only on pages that have something preview-only, and only from a browser
// marked as previewing (kits_pv: administrators, registered devices, or any page opened once with ?kits-preview=1 from a
// preview IP address). Other visitors never ask (17 Oct 2026: it was one uncached request per visitor session).
add_action(
	'wp_head',
	function () {
		$url = wp_json_encode( esc_url_raw( rest_url( 'kits/v1/preview' ) ) );
		echo '<script>(function(d){var h=d.documentElement,k="kitsPreview",v=null;try{if(/[?&]kits-preview=1/.test(location.search)){sessionStorage.removeItem(k);}v=sessionStorage.getItem(k);}catch(e){}'
			. 'if(v==="1"){h.classList.add("kits-preview");return;}if(v==="0"){return;}'
			. 'function go(){if(!d.querySelector(".kit-ad,.kits-preview-only")||(!/(^|; )kits_pv=1/.test(d.cookie)&&!/[?&]kits-preview=1/.test(location.search))){return;}fetch(' . $url . ',{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.json();}).then(function(j){var p=!!(j&&j.preview);try{sessionStorage.setItem(k,p?"1":"0");}catch(e){}if(p){h.classList.add("kits-preview");}}).catch(function(){});}'
			. 'if(d.readyState==="loading"){d.addEventListener("DOMContentLoaded",go);}else{go();}})(document);</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	2
);

// Administrators (never served from the cache) preview on every page.
add_filter( 'body_class', fn( $c ) => current_user_can( 'manage_options' ) ? array_merge( $c, array( 'kits-preview' ) ) : $c );

/* ---------- Preview-only items ---------- */

// Their cards in any list: hidden unless previewing.
add_filter(
	'post_class',
	function ( $classes, $class, $id ) {
		return ! is_admin() && in_array( (int) $id, kits_preview_only_ids(), true ) ? array_merge( $classes, array( 'kits-preview-only' ) ) : $classes;
	},
	10,
	3
);

// Every list and search on the site leaves them out, except for administrators (who bypass the page cache), so no
// public page links to them (owner, 15 Oct 2026). Only the item's own address still finds it, for the preview check below.
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || current_user_can( 'manage_options' ) || ! kits_preview_only_ids() ) {
			return;
		}
		if ( $q->is_main_query() && $q->is_singular() ) {
			return;
		}
		$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), kits_preview_only_ids() ) );
	}
);

// Sitemaps: an SEO plugin leaves them out (kits_preview_only_ids() is public for that).

// Their own page: never cached; 404 unless the request previews.
add_action(
	'template_redirect',
	function () {
		if ( ! is_singular() || ! in_array( (int) get_queried_object_id(), kits_preview_only_ids(), true ) ) {
			return;
		}
		kits_preview_nocache();
		if ( ! kits_preview_request() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		}
	},
	1
);

/* ---------- Settings > Ads: preview IP addresses and devices ---------- */

add_action(
	'kits_ads_settings_fields',
	function () {
		echo '<h2 class="title" style="margin-top:2em">Preview IP addresses</h2><p class="description">One per line. IPv4: exact address. IPv6: the whole /64 network (e.g. 2401:db8:1:2::/64). Requests from these addresses see empty ad slots and preview-only items. Your address now: <code>' . esc_html( kits_client_ip() ) . '</code></p>';
		echo '<textarea name="kits_preview_ips" rows="4" class="large-text code">' . esc_textarea( (string) get_option( 'kits_preview_ips', '' ) ) . '</textarea>';
	}
);
add_action(
	'kits_ads_settings_save',
	function () {
		$lines = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', sanitize_textarea_field( wp_unslash( $_POST['kits_preview_ips'] ?? '' ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Ads page
		update_option( 'kits_preview_ips', implode( "\n", $lines ), false );
	}
);

add_action(
	'kits_ads_settings_after',
	function () {
		$post = esc_url( admin_url( 'admin-post.php' ) );
		echo '<h2 class="title" style="margin-top:2em">Preview devices</h2><p class="description">A registered device sees empty ad slots and preview-only items on any network, without logging in.</p>';
		$link = get_transient( 'kits_preview_link_' . get_current_user_id() );
		if ( $link ) {
			delete_transient( 'kits_preview_link_' . get_current_user_id() );
			echo '<div class="notice notice-info inline"><p>Device link (one use, valid 24 hours): <input type="text" readonly class="large-text code" value="' . esc_attr( $link ) . '" onfocus="this.select()"></p></div>';
		}
		$all = kits_preview_devices();
		if ( $all ) {
			echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>Device</th><th>Added</th><th>Last seen</th><th></th></tr></thead><tbody>';
			foreach ( $all as $h => $d ) {
				echo '<tr><td>' . esc_html( $d['label'] ?? '' ) . '</td><td>' . esc_html( $d['added'] ?? '' ) . '</td><td>' . esc_html( $d['seen'] ?? '' ) . '</td><td><form method="post" action="' . $post . '">' // phpcs:ignore WordPress.Security.EscapeOutput
					. '<input type="hidden" name="action" value="kits_preview_remove"><input type="hidden" name="device" value="' . esc_attr( $h ) . '">' . wp_nonce_field( 'kits_preview_remove', '_wpnonce', true, false )
					. '<button type="submit" class="button-link-delete">Remove</button></form></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<form method="post" action="' . $post . '" style="margin-top:1em"><input type="hidden" name="action" value="kits_preview_register">' . wp_nonce_field( 'kits_preview_register', '_wpnonce', true, false ) // phpcs:ignore WordPress.Security.EscapeOutput
			. '<label>Device name <input type="text" name="label" placeholder="Laptop"></label> <button type="submit" class="button">Register this device</button></form>';
		echo '<form method="post" action="' . $post . '" style="margin-top:1em"><input type="hidden" name="action" value="kits_preview_link">' . wp_nonce_field( 'kits_preview_link', '_wpnonce', true, false ) // phpcs:ignore WordPress.Security.EscapeOutput
			. '<label>Device name <input type="text" name="label" placeholder="Phone"></label> <button type="submit" class="button">Create a device link</button></form>';
	}
);

/** Back to Settings > Ads. */
function kits_preview_back() {
	wp_safe_redirect( admin_url( 'options-general.php?page=aw-ads' ) );
	exit;
}

add_action(
	'admin_post_kits_preview_register',
	function () {
		check_admin_referer( 'kits_preview_register' );
		if ( current_user_can( 'manage_options' ) ) {
			kits_preview_register_device( wp_unslash( $_POST['label'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized inside
		}
		kits_preview_back();
	}
);

add_action(
	'admin_post_kits_preview_remove',
	function () {
		check_admin_referer( 'kits_preview_remove' );
		if ( current_user_can( 'manage_options' ) ) {
			$all = kits_preview_devices();
			unset( $all[ sanitize_text_field( wp_unslash( $_POST['device'] ?? '' ) ) ] );
			update_option( 'kits_preview_devices', $all, false );
		}
		kits_preview_back();
	}
);

// A device link: one random token, valid 24 hours, used once. Opening it shows a button; pressing it registers that
// device (link previews in chat apps only open the page, so they cannot use up the link).
add_action(
	'admin_post_kits_preview_link',
	function () {
		check_admin_referer( 'kits_preview_link' );
		if ( current_user_can( 'manage_options' ) ) {
			$token = bin2hex( random_bytes( 24 ) );
			$links = array_filter( (array) get_option( 'kits_preview_links', array() ), fn( $l ) => (int) ( $l['until'] ?? 0 ) > time() );
			$links[ kits_preview_hash( $token ) ] = array(
				'until' => time() + DAY_IN_SECONDS,
				'label' => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			);
			update_option( 'kits_preview_links', $links, false );
			set_transient( 'kits_preview_link_' . get_current_user_id(), add_query_arg( array( 'action' => 'kits_preview_device', 't' => $token ), admin_url( 'admin-post.php' ) ), 10 * MINUTE_IN_SECONDS );
		}
		kits_preview_back();
	}
);

/** Opening a device link (GET: a button; POST: register). */
function kits_preview_device_link() {
	kits_preview_nocache();
	$token = sanitize_text_field( wp_unslash( $_REQUEST['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- the token is the check
	$links = (array) get_option( 'kits_preview_links', array() );
	$h     = kits_preview_hash( $token );
	$ok    = preg_match( '/^[a-f0-9]{48}$/', $token ) && isset( $links[ $h ] ) && (int) $links[ $h ]['until'] > time();
	if ( ! $ok ) {
		wp_die( 'This device link has expired or was already used.', 'Preview device', array( 'response' => 410 ) );
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		wp_die(
			'<form method="post"><input type="hidden" name="action" value="kits_preview_device"><input type="hidden" name="t" value="' . esc_attr( $token ) . '"><p><button type="submit" class="button">Register this device</button></p></form>',
			'Preview device',
			array( 'response' => 200 )
		);
	}
	$label = (string) ( $links[ $h ]['label'] ?? '' );
	unset( $links[ $h ] );
	update_option( 'kits_preview_links', $links, false );
	kits_preview_register_device( '' !== $label ? $label : 'Device (link)' );
	wp_safe_redirect( add_query_arg( 'kits-preview', '1', home_url( '/' ) ) );
	exit;
}
add_action( 'admin_post_kits_preview_device', 'kits_preview_device_link' );
add_action( 'admin_post_nopriv_kits_preview_device', 'kits_preview_device_link' );
