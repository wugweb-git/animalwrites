<?php
/**
 * IndexNow: when a public item is published or updated, Bing, Yandex and the other IndexNow engines are told at once.
 * The key is made once and served at /{key}.txt (the protocol's proof of ownership). Pings are sent in the background
 * (a one-off scheduled event), batched, at most once per URL per 10 minutes; failures are logged, never shown.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The site's IndexNow key (32 hex characters, made once). */
function kits_indexnow_key() {
	$k = (string) get_option( 'kits_indexnow_key' );
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $k ) ) {
		$k = bin2hex( random_bytes( 16 ) );
		update_option( 'kits_indexnow_key', $k, true );
	}
	return $k;
}

// Serve /{key}.txt.
add_action(
	'init',
	function () {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore
		if ( preg_match( '/^[a-f0-9]{32}\.txt$/', $path ) && kits_indexnow_key() . '.txt' === $path ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo kits_indexnow_key(); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
	},
	1
);

// Queue the URL when a public item goes live or a live item changes.
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( 'publish' !== $new || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->public || 0 === strpos( (string) wp_parse_url( home_url(), PHP_URL_HOST ), 'staging.' ) || ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) || 'production' !== wp_get_environment_type() && ! apply_filters( 'kits_indexnow_any_env', false ) ) {
			return;
		}
		$url = get_permalink( $post );
		if ( ! $url || get_transient( 'kits_in_' . md5( $url ) ) ) {
			return;
		}
		set_transient( 'kits_in_' . md5( $url ), 1, 10 * MINUTE_IN_SECONDS );
		$q   = (array) get_option( 'kits_indexnow_queue', array() );
		$q[] = $url;
		update_option( 'kits_indexnow_queue', array_values( array_unique( $q ) ), false );
		if ( ! wp_next_scheduled( 'kits_indexnow_send' ) ) {
			wp_schedule_single_event( time() + 60, 'kits_indexnow_send' );
		}
	},
	10,
	3
);

add_action(
	'kits_indexnow_send',
	function () {
		$q = array_slice( (array) get_option( 'kits_indexnow_queue', array() ), 0, 1000 );
		if ( ! $q ) {
			return;
		}
		delete_option( 'kits_indexnow_queue' );
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$res  = wp_remote_post(
			'https://api.indexnow.org/indexnow',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode( array( 'host' => $host, 'key' => kits_indexnow_key(), 'keyLocation' => home_url( '/' . kits_indexnow_key() . '.txt' ), 'urlList' => $q ) ),
			)
		);
		$code = is_wp_error( $res ) ? $res->get_error_message() : (int) wp_remote_retrieve_response_code( $res );
		update_option( 'kits_indexnow_last', array( 'at' => current_time( 'mysql' ), 'urls' => count( $q ), 'result' => $code ), false );
	}
);

// Shown on Tools → Check site.
add_action(
	'aw_site_check_after',
	function () {
		$l = get_option( 'kits_indexnow_last' );
		echo '<h2>IndexNow</h2><p>' . ( is_array( $l ) ? esc_html( sprintf( 'Last sent %s: %d URL(s), answer %s (200 or 202 = accepted).', $l['at'], $l['urls'], $l['result'] ) ) : 'Nothing sent yet; it runs when an item is published or updated.' ) . '</p>';
	}
);
