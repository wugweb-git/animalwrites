<?php
/**
 * Hostinger Mail API connection and the incoming-mail webhook.
 *
 * - Settings: the Mail API token and, for each mailbox, the secret of its webhook. Each can also be set in wp-config
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

/** Mail → Settings URL for a tab ('incoming' or 'sending'), with extra query arguments. */
function mailweb_settings_url( $sub = 'incoming', $args = array(), $anchor = '' ) {
	$url = add_query_arg( array_merge( array( 'page' => 'mailweb-settings', 'sub' => $sub ), $args ), admin_url( 'admin.php' ) );
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

/* ---------- Mail → Settings → Incoming mail ---------- */

/** The Incoming mail settings: token, webhook secrets, mailbox IDs; then mailboxes, aliases and profiles. */
function mailweb_in_screen() {
	if ( isset( $_POST['mailweb_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mailweb_nonce'] ) ), 'mailweb_in' ) && current_user_can( 'manage_options' ) ) {
		$keys = array( 'api_token' );
		foreach ( array_keys( mailweb_all_boxes() ) as $k ) {
			$keys[] = 'secret_' . $k;
		}
		foreach ( $keys as $k ) {
			$v = trim( sanitize_text_field( wp_unslash( $_POST[ 'mailweb_' . $k ] ?? '' ) ) );
			if ( '' !== $v ) { // an empty field keeps the saved value
				mailweb_save_setting( $k, $v );
			}
		}
		mailweb_box_ids( true ); // the mailboxes and their IDs, from the token
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Saved.', 'mailweb' ) . '</p></div>';
	}
	$ids  = '' !== mailweb_setting( 'api_token' ) ? mailweb_box_ids() : array();
	$last = get_option( 'mailweb_last' );
	echo '<h2>' . esc_html__( 'Incoming mail', 'mailweb' ) . '</h2>';
	echo '<p>' . esc_html__( 'Mail reads your Hostinger mailboxes live through the Mail API with the token below; nothing from the mailboxes is copied into WordPress. When mail arrives, Hostinger calls the webhook below, which refreshes the unread counts.', 'mailweb' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'Webhook URL (hPanel → Emails → each mailbox → Agentic mail → Webhooks, event "message.received"):', 'mailweb' ) . '</strong><br><code>' . esc_html( rest_url( 'mailweb/v1/mail-in' ) ) . '</code></p>';
	$when = is_array( $last ) ? trim( $last['at'] . ' — ' . ( $last['mailbox'] ?? '' ), ' —' ) : __( 'none yet', 'mailweb' );
	/* translators: %s: date and mailbox of the last webhook delivery, or "none yet". */
	echo '<p>' . esc_html( sprintf( __( 'Last webhook delivery: %s', 'mailweb' ), $when ) ) . '</p>';
	echo '<form method="post">';
	wp_nonce_field( 'mailweb_in', 'mailweb_nonce' );
	echo '<table class="form-table">';
	$field = function ( $k, $label, $help ) {
		$fixed = mailweb_setting_fixed( $k );
		$saved = ! $fixed && '' !== mailweb_setting( $k );
		echo '<tr><th><label for="mailweb_' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( $fixed ) {
			echo '<p>' . esc_html__( 'Set in wp-config or the server environment.', 'mailweb' ) . '</p>';
		} else {
			echo '<input class="regular-text code" type="password" autocomplete="off" id="mailweb_' . esc_attr( $k ) . '" name="mailweb_' . esc_attr( $k ) . '" value=""' . ( $saved ? ' placeholder="' . esc_attr__( 'Saved. Leave empty to keep it.', 'mailweb' ) . '"' : '' ) . '>';
		}
		echo '<p class="description">' . esc_html( $help ) . '</p></td></tr>';
	};
	$field( 'api_token', __( 'Mail API token', 'mailweb' ), __( 'hPanel → Emails → Agentic mail → API. Its scope decides which mailboxes Mail can read. Or set MAILWEB_API_TOKEN in wp-config.', 'mailweb' ) );
	foreach ( mailweb_all_boxes() as $key => $addr ) {
		/* translators: %s: mailbox address. */
		$label = sprintf( __( 'Webhook secret — %s', 'mailweb' ), $addr );
		/* translators: %s: wp-config constant name. */
		$help = sprintf( __( 'Shown once when the webhook is created on this mailbox. Or set %s in wp-config.', 'mailweb' ), strtoupper( 'mailweb_secret_' . $key ) );
		$field( 'secret_' . $key, $label, $help );
	}
	foreach ( mailweb_all_boxes() as $key => $addr ) {
		/* translators: %s: mailbox address. */
		echo '<tr><th>' . esc_html( sprintf( __( 'Mailbox ID — %s', 'mailweb' ), $addr ) ) . '</th><td><input class="regular-text code" readonly value="' . esc_attr( (string) ( $ids[ $key ] ?? '' ) ) . '"><p class="description">' . esc_html__( 'Read from the token (read only).', 'mailweb' ) . '</p></td></tr>';
	}
	echo '</table>';
	if ( ! mailweb_all_boxes() ) {
		echo '<p class="description">' . esc_html__( 'Save the token first: the mailboxes it can see, and a secret field for each, then appear here.', 'mailweb' ) . '</p>';
	}
	submit_button();
	echo '</form>';
	do_action( 'mailweb_settings_after' ); // mailboxes and aliases, profiles (mailboxes.php, profiles.php)
}
