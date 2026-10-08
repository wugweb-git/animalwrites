<?php
/**
 * Incoming mail → Inbox (owner, 9 Oct 2026). Hostinger Mail calls POST /wp-json/aw/v1/mail-in for every new message
 * in support@animalwrites.in (vedanshu@, contact@ and the catch-all forward into it).
 *
 * - Only Hostinger may call it: the webhook's secret arrives as "Authorization: Bearer …" and must equal the secret
 *   saved in Settings → Incoming mail (or the AW_MAIL_WEBHOOK_SECRET constant in wp-config.php). Anything else gets 401.
 * - With an API token scoped to the mailbox and the mailbox ID (same settings page, or AW_MAIL_API_TOKEN /
 *   AW_MAIL_MAILBOX_ID), the full message is fetched from the Hostinger Mail API (subject, from, to, date, text,
 *   attachments, headers); otherwise the webhook's own fields are used.
 * - Each message becomes an Inbox item (Contact messages, tag "Email", unread) with the original To address
 *   (vedanshu@ / contact@ / support@ / other), deduplicated on Message-ID. Spam is skipped. Attachments: names only.
 * - Nothing is ever sent from WordPress (no auto-reply); replies stay in webmail.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A mail setting: the wp-config constant if defined, else the value saved in Settings → Incoming mail. */
function aw_mail_setting( $key ) {
	$const = strtoupper( 'aw_mail_' . $key );
	return defined( $const ) && constant( $const ) ? (string) constant( $const ) : (string) get_option( 'aw_mail_' . $key, '' );
}

/** Hostinger Mail API GET (with the saved API token), or null. */
function aw_mail_api( $path ) {
	$token = aw_mail_setting( 'api_token' );
	if ( '' === $token ) {
		return null;
	}
	$r = wp_remote_get( 'https://api.mail.hostinger.com' . $path, array( 'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ) ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}
	$body = wp_remote_retrieve_body( $r );
	$json = json_decode( $body, true );
	return is_array( $json ) ? ( $json['data'] ?? $json ) : $body;
}

/** First non-empty value among several possible payload keys (the webhook's field names are not documented). */
function aw_mail_pick( $a, $keys ) {
	foreach ( $keys as $k ) {
		$v = $a;
		foreach ( explode( '.', $k ) as $part ) {
			$v = is_array( $v ) && isset( $v[ $part ] ) ? $v[ $part ] : null;
		}
		if ( null !== $v && '' !== $v && array() !== $v ) {
			return $v;
		}
	}
	return null;
}

/** "Name <a@b>" / {name, address} / list → list of plain addresses. */
function aw_mail_addresses( $v ) {
	$out = array();
	foreach ( is_array( $v ) && ! isset( $v['address'] ) && ! isset( $v['email'] ) ? $v : array( $v ) as $x ) {
		$s = is_array( $x ) ? (string) ( $x['address'] ?? $x['email'] ?? '' ) : (string) $x;
		if ( preg_match_all( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $s, $m ) ) {
			$out = array_merge( $out, array_map( 'strtolower', $m[0] ) );
		}
	}
	return array_values( array_unique( $out ) );
}

/** A header's value from raw message source. */
function aw_mail_header( $source, $name ) {
	$head = (string) strstr( str_replace( "\r\n", "\n", (string) $source ), "\n\n", true );
	$head = preg_replace( "/\n[ \t]+/", ' ', $head ); // unfold
	return preg_match( '/^' . preg_quote( $name, '/' ) . ':\s*(.+)$/mi', $head, $m ) ? trim( $m[1] ) : '';
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/mail-in',
			array(
				'methods'             => 'POST',
				'permission_callback' => function ( WP_REST_Request $req ) {
					$secret = aw_mail_setting( 'webhook_secret' );
					$auth   = (string) $req->get_header( 'authorization' );
					$given  = preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ? trim( $m[1] ) : '';
					return '' !== $secret && '' !== $given && hash_equals( $secret, $given );
				},
				'callback'            => 'aw_mail_in',
			)
		);
	}
);

/** Handle one webhook delivery. */
function aw_mail_in( WP_REST_Request $req ) {
	$p    = (array) $req->get_json_params();
	$data = isset( $p['data'] ) && is_array( $p['data'] ) ? $p['data'] : $p;
	$evt  = (string) aw_mail_pick( $p, array( 'event', 'type', 'data.event' ) );
	if ( $evt && false === stripos( $evt, 'received' ) && false === stripos( $evt, 'test' ) ) {
		return new WP_REST_Response( array( 'status' => 'ignored' ), 200 );
	}
	$box    = (string) ( aw_mail_pick( $data, array( 'mailboxResourceId', 'mailbox.resourceId', 'mailbox_id', 'resourceId' ) ) ?? aw_mail_setting( 'mailbox_id' ) );
	$folder = (string) ( aw_mail_pick( $data, array( 'folder', 'path', 'message.folder', 'message.path' ) ) ?? 'INBOX' );
	$uid    = (string) aw_mail_pick( $data, array( 'uid', 'message.uid', 'messageUid' ) );
	$msg    = is_array( $data['message'] ?? null ) ? $data['message'] : $data;

	// The full message from the Mail API when we can; otherwise what the webhook carried.
	$source = '';
	if ( $box && $uid ) {
		$full = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ) );
		if ( is_array( $full ) ) {
			$msg  = $full + $msg;
			$text = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ) . '/text' );
			if ( $text ) {
				$msg['text'] = is_array( $text ) ? (string) ( $text['text'] ?? $text['plain'] ?? $text['content'] ?? '' ) : (string) $text;
			}
			$src    = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ) . '/source' );
			$source = is_array( $src ) ? (string) ( $src['source'] ?? $src['raw'] ?? '' ) : (string) $src;
		}
	}

	// Spam: skip what Hostinger files or flags as spam.
	$flags = array_map( 'strtolower', array_map( 'strval', (array) ( $msg['flags'] ?? array() ) ) );
	$spam  = preg_match( '/spam|junk/i', $folder ) || array_intersect( $flags, array( '$junk', '\\junk', 'junk', 'spam' ) ) || ! empty( $msg['spam'] ) || ! empty( $msg['isSpam'] ) || ( $source && preg_match( '/^yes/i', aw_mail_header( $source, 'X-Spam-Flag' ) ) );
	if ( $spam ) {
		return new WP_REST_Response( array( 'status' => 'spam-skipped' ), 200 );
	}

	$mid = trim( (string) ( aw_mail_pick( $msg, array( 'messageId', 'message_id', 'headers.message-id' ) ) ?? aw_mail_header( $source, 'Message-ID' ) ), '<> ' );
	$mid = '' !== $mid ? $mid : 'uid:' . $box . ':' . $folder . ':' . $uid . ':' . md5( wp_json_encode( $msg ) );
	if ( get_posts( array( 'post_type' => 'aw_message', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => 1, 'meta_key' => 'aw_m_message_id', 'meta_value' => $mid ) ) ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		return new WP_REST_Response( array( 'status' => 'duplicate' ), 200 );
	}

	$subject = trim( wp_strip_all_tags( (string) ( aw_mail_pick( $msg, array( 'subject', 'title' ) ) ?? '' ) ) );
	$from    = aw_mail_pick( $msg, array( 'from', 'sender', 'from.address' ) );
	$from_s  = is_array( $from ) ? trim( ( $from['name'] ?? '' ) . ' <' . ( $from['address'] ?? $from['email'] ?? '' ) . '>' ) : (string) $from;
	$to      = aw_mail_addresses( aw_mail_pick( $msg, array( 'to', 'recipients' ) ) ?? array() );
	// Forwarded mail keeps the address it was sent to in the headers (vedanshu@, contact@, any@ via the catch-all).
	$orig = '';
	foreach ( array( 'X-Original-To', 'X-Forwarded-To', 'Delivered-To', 'To' ) as $h ) {
		$a = aw_mail_addresses( aw_mail_header( $source, $h ) );
		$a = array_values( array_filter( $a, fn( $x ) => str_ends_with( $x, '@animalwrites.in' ) && 'support@animalwrites.in' !== $x ) );
		if ( $a ) {
			$orig = $a[0];
			break;
		}
	}
	if ( '' === $orig ) {
		$own  = array_values( array_filter( $to, fn( $x ) => str_ends_with( $x, '@animalwrites.in' ) ) );
		$orig = $own ? $own[0] : ( $to ? $to[0] : 'support@animalwrites.in' );
	}
	$text  = (string) ( aw_mail_pick( $msg, array( 'text', 'plain', 'body', 'preview', 'snippet', 'truncatedMessage', 'message' ) ) ?? '' );
	$text  = is_string( $text ) ? $text : wp_json_encode( $text );
	$files = array();
	foreach ( (array) ( $msg['attachments'] ?? array() ) as $att ) {
		$files[] = is_array( $att ) ? trim( (string) ( $att['filename'] ?? $att['name'] ?? 'attachment' ) . ( isset( $att['size'] ) ? ' (' . size_format( (int) $att['size'] ) . ')' : '' ) ) : (string) $att;
	}
	$date = (string) ( aw_mail_pick( $msg, array( 'date', 'receivedAt', 'timestamp' ) ) ?? '' );

	$id = wp_insert_post(
		array(
			'post_type'    => 'aw_message',
			'post_status'  => 'private',
			'post_title'   => 'Email: ' . ( '' !== $subject ? $subject : '(no subject)' ),
			'post_content' => sanitize_textarea_field( $text ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return new WP_REST_Response( array( 'status' => 'error' ), 500 );
	}
	$from_addr = aw_mail_addresses( $from_s );
	foreach ( array(
		'aw_m_tag'         => 'Email',
		'aw_m_about'       => 'email',
		'aw_m_name'        => trim( preg_replace( '/<[^>]*>/', '', $from_s ) ),
		'aw_m_email'       => $from_addr ? $from_addr[0] : '',
		'aw_m_to'          => $orig,
		'aw_m_mailbox'     => 'support@animalwrites.in',
		'aw_m_message_id'  => $mid,
		'aw_m_date'        => $date,
		'aw_m_attachments' => implode( "\n", $files ),
		'aw_m_source'      => 'email: ' . $orig,
		'aw_m_contact'     => 'Not subscribed', // never added to the newsletter without their own consent
	) as $k => $v ) {
		update_post_meta( $id, $k, sanitize_text_field( (string) $v ) );
	}
	update_post_meta( $id, 'aw_review', 'held: email — to review' );
	update_option( 'aw_mail_last', array( 'at' => current_time( 'mysql' ), 'to' => $orig, 'subject' => $subject ), false );
	return new WP_REST_Response( array( 'status' => 'created', 'id' => $id ), 201 );
}

// Inbox list: show who an email was sent to.
add_filter( 'manage_aw_message_posts_columns', fn( $c ) => array_slice( $c, 0, 3, true ) + array( 'aw_m_to' => 'Sent to' ) + array_slice( $c, 3, null, true ), 20 );

/* ---------- Settings → Incoming mail ---------- */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Incoming mail', 'Incoming mail', 'manage_options', 'aw-mail-in', 'aw_mail_in_screen' );
	}
);

function aw_mail_in_screen() {
	if ( isset( $_POST['aw_mail_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_mail_nonce'] ) ), 'aw_mail_in' ) && current_user_can( 'manage_options' ) ) {
		foreach ( array( 'webhook_secret', 'api_token', 'mailbox_id' ) as $k ) {
			$v = trim( sanitize_text_field( wp_unslash( $_POST[ 'aw_mail_' . $k ] ?? '' ) ) );
			if ( '' !== $v || 'mailbox_id' === $k ) { // an empty secret or token field keeps the saved one
				update_option( 'aw_mail_' . $k, $v, false );
			}
		}
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	$last = get_option( 'aw_mail_last' );
	echo '<div class="wrap"><h1>Incoming mail</h1>';
	echo '<p>Hostinger Mail sends each new message in support@animalwrites.in to this address. It arrives in Inbox → Contact messages, tagged Email. Nothing is ever sent from WordPress.</p>';
	echo '<p><strong>Webhook URL (paste in hPanel → Agentic mail → Webhooks, event "message.received"):</strong><br><code>' . esc_html( rest_url( 'aw/v1/mail-in' ) ) . '</code></p>';
	echo '<p>Last message received: <strong>' . esc_html( is_array( $last ) ? $last['at'] . ' — to ' . $last['to'] . ' — ' . $last['subject'] : 'none yet' ) . '</strong></p>';
	echo '<form method="post">' . wp_nonce_field( 'aw_mail_in', 'aw_mail_nonce', true, false ) . '<table class="form-table">';
	$rows = array(
		'webhook_secret' => array( 'Webhook secret', 'The secret Hostinger shows once when the webhook is created.', true ),
		'api_token'      => array( 'Mail API token (optional)', 'From Agentic mail → API. Lets WordPress fetch the full message, its headers and attachment names.', true ),
		'mailbox_id'     => array( 'Mailbox ID (optional)', 'The mailbox resource ID of support@animalwrites.in.', false ),
	);
	foreach ( $rows as $k => $r ) {
		$saved = '' !== aw_mail_setting( $k );
		echo '<tr><th><label for="aw_mail_' . esc_attr( $k ) . '">' . esc_html( $r[0] ) . '</label></th><td>'
			. ( $r[2]
				? '<input class="regular-text code" type="password" autocomplete="off" id="aw_mail_' . esc_attr( $k ) . '" name="aw_mail_' . esc_attr( $k ) . '" value=""' . ( $saved ? ' placeholder="Saved. Leave empty to keep it."' : '' ) . '>'
				: '<input class="regular-text code" id="aw_mail_' . esc_attr( $k ) . '" name="aw_mail_' . esc_attr( $k ) . '" value="' . esc_attr( aw_mail_setting( $k ) ) . '">' )
			. '<p class="description">' . esc_html( $r[1] ) . '</p></td></tr>';
	}
	echo '</table>';
	submit_button();
	echo '</form></div>';
}
