<?php
/**
 * Hostinger Mail API connection and the incoming-mail webhook.
 *
 * - Settings (saved through mailweb-rest.php): the Mail API token and, for each mailbox, the secret of its webhook. Each can also be set in wp-config
 *   (MAILWEB_API_TOKEN, MAILWEB_SECRET_<MAILBOX>) or the server environment; what is saved in the database is encrypted
 *   (crypto.php).
 * - Mailboxes: every mailbox the token can see (GET /api/v1/me), remembered with its ID (mailboxes.php).
 * - Webhook: Hostinger calls POST /wp-json/mailweb/v1/mail-in when mail arrives ("message.received"). The secret arrives
 *   as "Authorization: Bearer …" and must match one mailbox's secret; anything else gets 401. A delivery refreshes the
 *   unread counts and fires `mailweb_webhook_noted` (the FluentCRM feed listens). Nothing is copied into WordPress.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A Mail setting: the server environment (MAILWEB_<KEY>), else wp-config (MAILWEB_<KEY>), else the saved option.
 *
 * @param string $key api_token, or secret_<mailbox key>.
 * @return string
 */
function mailweb_setting( $key ) {
	$name = strtoupper( 'mailweb_' . $key );
	$env  = getenv( $name );
	if ( $env ) {
		return (string) $env;
	}
	if ( defined( $name ) && constant( $name ) ) {
		return (string) constant( $name );
	}
	return mailweb_decrypt( (string) get_option( 'mailweb_' . $key, '' ) );
}

/** Is this setting fixed in wp-config or the server environment (so the field is not used)? */
function mailweb_setting_fixed( $key ) {
	$name = strtoupper( 'mailweb_' . $key );
	return (bool) getenv( $name ) || ( defined( $name ) && constant( $name ) );
}

/** Save a setting, encrypted. */
function mailweb_save_setting( $key, $value ) {
	update_option( 'mailweb_' . $key, mailweb_encrypt( (string) $value ), false );
}

/**
 * MailWeb → Settings URL: 'sending' is Site sending (settings-tab.php), anything else the shell Settings screen.
 *
 * @param string $sub    'incoming' or 'sending'.
 * @param array  $args   More query arguments.
 * @param string $anchor A section id on the screen.
 * @return string
 */
function mailweb_settings_url( $sub = 'incoming', $args = array(), $anchor = '' ) {
	$base = 'sending' === $sub ? array(
		'page' => 'mailweb-settings',
		'sub'  => 'sending',
	) : array( 'page' => 'mailweb-settings' );
	$url  = add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
	return $url . ( '' !== $anchor ? '#' . $anchor : '' );
}

/**
 * Mailbox IDs (resourceId) for every mailbox the token can see (GET /api/v1/me), keyed by local part, remembered with
 * their addresses (option mailweb_box_addresses).
 *
 * @param bool $refresh Ask the API again.
 * @return array key => ID
 */
function mailweb_box_ids( $refresh = false ) {
	static $known = null; // one lookup per request
	if ( ! $refresh && null !== $known ) {
		return $known;
	}
	$ids = (array) get_option( 'mailweb_box_ids', array() );
	$ask = ! $ids || false === get_option( 'mailweb_box_addresses', false );
	// After a failed or empty lookup, wait a few minutes before asking again (this runs on every admin page).
	if ( $refresh || ( $ask && ! get_transient( 'mailweb_box_ids_wait' ) ) ) {
		$me = mailweb_api( '/api/v1/me' );
		if ( is_array( $me ) && isset( $me['mailboxes'] ) ) {
			$old  = array_flip( array_map( 'strtolower', (array) get_option( 'mailweb_box_addresses', array() ) ) ); // address => key
			$ids  = array();
			$adr  = array();
			$list = array();
			foreach ( (array) $me['mailboxes'] as $mb ) {
				$a = strtolower( (string) ( $mb['address'] ?? '' ) );
				if ( is_email( $a ) && ! empty( $mb['resourceId'] ) ) {
					$list[ $a ] = (string) $mb['resourceId'];
				}
			}
			// Mailboxes that already have a key go first and keep it (saved secrets, options and URLs use it).
			uksort( $list, fn( $x, $y ) => (int) isset( $old[ $y ] ) <=> (int) isset( $old[ $x ] ) );
			foreach ( $list as $a => $id ) {
				$local = sanitize_key( strstr( $a, '@', true ) );
				$key   = (string) ( $old[ $a ] ?? $local );
				if ( isset( $adr[ $key ] ) ) {
					// Same local part on another domain (support@a.in, support@b.in): add the domain to the key.
					$base = $local . '-' . sanitize_key( str_replace( '.', '-', substr( strrchr( $a, '@' ), 1 ) ) );
					$key  = $base;
					for ( $n = 2; isset( $adr[ $key ] ); $n++ ) {
						$key = $base . '-' . $n;
					}
				}
				$ids[ $key ] = $id;
				$adr[ $key ] = $a;
			}
			update_option( 'mailweb_box_ids', $ids, false );
			update_option( 'mailweb_box_addresses', $adr, false );
			if ( $ids ) {
				delete_transient( 'mailweb_box_ids_wait' );
			} else {
				set_transient( 'mailweb_box_ids_wait', 1, 5 * MINUTE_IN_SECONDS );
			}
		} else {
			set_transient( 'mailweb_box_ids_wait', 1, 5 * MINUTE_IN_SECONDS );
		}
	}
	$known = $ids;
	return $ids;
}

/** The ID of one mailbox ('' when unknown). */
function mailweb_box_id( $key ) {
	return (string) ( mailweb_box_ids()[ $key ] ?? '' );
}

/**
 * Mail API GET with the token: the response's data (or raw body), or null.
 *
 * @param string $path Path after the host.
 * @return mixed
 */
function mailweb_api( $path ) {
	$token = mailweb_setting( 'api_token' );
	if ( '' === $token ) {
		return null;
	}
	$r = wp_remote_get(
		MAILWEB_API_HOST . $path,
		array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		)
	);
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}
	$body = wp_remote_retrieve_body( $r );
	$json = json_decode( $body, true );
	return is_array( $json ) ? ( $json['data'] ?? $json ) : $body;
}

/* ---------- Webhook ---------- */

/**
 * Does a webhook request carry one mailbox's secret? Sets the request parameter _mailweb_box to that mailbox's key.
 *
 * @param WP_REST_Request $req Request.
 * @return bool
 */
function mailweb_webhook_permission( WP_REST_Request $req ) {
	$given = preg_match( '/^Bearer\s+(.+)$/i', (string) $req->get_header( 'authorization' ), $m ) ? trim( $m[1] ) : '';
	if ( '' === $given ) {
		return false;
	}
	foreach ( array_keys( mailweb_all_boxes() ) as $key ) { // each mailbox's webhook has its own secret
		$secret = mailweb_setting( 'secret_' . $key );
		if ( '' !== $secret && hash_equals( $secret, $given ) ) {
			$req->set_param( '_mailweb_box', $key );
			return true;
		}
	}
	return false;
}

/** A webhook delivery: refresh the counts and tell listeners which mailbox has new mail. */
function mailweb_in( WP_REST_Request $req ) {
	$key = (string) $req->get_param( '_mailweb_box' );
	if ( function_exists( 'mailweb_overview_flush' ) ) {
		mailweb_overview_flush();
	}
	foreach ( array_keys( mailweb_all_boxes() ) as $k ) {
		delete_transient( 'mailweb_folders_' . $k );
	}
	update_option(
		'mailweb_last',
		array(
			'at'      => current_time( 'mysql' ),
			'mailbox' => mailweb_all_boxes()[ $key ] ?? '',
		),
		false
	);
	/**
	 * New mail arrived in a mailbox (the webhook fired).
	 *
	 * @param string          $key Mailbox key.
	 * @param WP_REST_Request $req The delivery.
	 */
	do_action( 'mailweb_webhook_noted', $key, $req );
	return new WP_REST_Response( array( 'status' => 'noted' ), 200 );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'mailweb/v1',
			'/mail-in',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'mailweb_webhook_permission',
				'callback'            => 'mailweb_in',
			)
		);
		// Opening the URL in a browser (GET) answers "ready" instead of "no route"; nothing else.
		register_rest_route(
			'mailweb/v1',
			'/mail-in',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => fn() => new WP_REST_Response(
					array(
						'status' => 'ready',
						'method' => 'POST',
					),
					200
				),
			)
		);
	}
);
