<?php
/**
 * Incoming mail → Inbox (owner, 9 Oct 2026). Hostinger Mail calls POST /wp-json/aw/v1/mail-in for every new message
 * in vedanshu@animalwrites.in (contact@ is its alias).
 *
 * - Only Hostinger may call it: the webhook's secret arrives as "Authorization: Bearer …" and must equal the secret from
 *   the server environment (HOSTINGER_MAIL_WEBHOOK_SECRET), wp-config (AW_MAIL_WEBHOOK_SECRET) or Settings → Incoming
 *   mail. Anything else gets 401.
 * - With an API token (HOSTINGER_MAIL_TOKEN, scope vedanshu@ + noreply@) the full message is fetched from the Hostinger
 *   Mail API (subject, from, to, date, text, attachments, headers); the mailbox ID is looked up from the token.
 * - Each message becomes an Inbox item (Contact messages, tag "Email", unread, dated when it was sent) with the
 *   original To address (vedanshu@ / contact@ / other), deduplicated on Message-ID. Spam is skipped. Attachments:
 *   names only. A one-time import brings in the mail already in the mailbox, newest first (Settings → Incoming mail,
 *   or wp aw-mail import).
 * - Nothing is ever sent from WordPress (no auto-reply); replies stay in webmail.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_MAIL_BOX = 'vedanshu@animalwrites.in';

/** A mail setting: the server environment, else wp-config, else Settings → Incoming mail. */
function aw_mail_setting( $key ) {
	$env = array( 'webhook_secret' => 'HOSTINGER_MAIL_WEBHOOK_SECRET', 'api_token' => 'HOSTINGER_MAIL_TOKEN', 'mailbox_id' => 'HOSTINGER_MAIL_MAILBOX_ID' );
	$v   = isset( $env[ $key ] ) ? getenv( $env[ $key ] ) : false;
	if ( $v ) {
		return (string) $v;
	}
	$const = strtoupper( 'aw_mail_' . $key );
	return defined( $const ) && constant( $const ) ? (string) constant( $const ) : (string) get_option( 'aw_mail_' . $key, '' );
}

/** The mailbox ID of vedanshu@: from the settings, else looked up from the token (and remembered). */
function aw_mail_box_id() {
	$id = aw_mail_setting( 'mailbox_id' );
	if ( '' !== $id ) {
		return $id;
	}
	$me = aw_mail_api( '/api/v1/me' );
	foreach ( (array) ( $me['mailboxes'] ?? array() ) as $mb ) {
		if ( strtolower( (string) ( $mb['address'] ?? '' ) ) === AW_MAIL_BOX ) {
			update_option( 'aw_mail_mailbox_id', (string) $mb['resourceId'], false );
			return (string) $mb['resourceId'];
		}
	}
	return '';
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
		// A browser or hPanel opening the URL (GET) gets a plain "ready" instead of "no route".
		register_rest_route( 'aw/v1', '/mail-in', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => fn() => new WP_REST_Response( array( 'status' => 'ready', 'method' => 'POST' ), 200 ) ) );
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
	$box    = (string) ( aw_mail_pick( $data, array( 'mailboxResourceId', 'mailbox.resourceId', 'mailbox_id', 'resourceId' ) ) ?? aw_mail_box_id() );
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
				$msg['text'] = is_array( $text ) ? (string) ( $text['text'] ?? '' ) : (string) $text;
				if ( '' === trim( $msg['text'] ) && is_array( $text ) && ! empty( $text['html'] ) ) {
					$msg['text'] = wp_strip_all_tags( (string) $text['html'] );
				}
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
		$a = array_values( array_filter( $a, fn( $x ) => str_ends_with( $x, '@animalwrites.in' ) && 'noreply@animalwrites.in' !== $x ) );
		if ( $a ) {
			$orig = $a[0];
			break;
		}
	}
	if ( '' === $orig ) {
		$own  = array_values( array_filter( $to, fn( $x ) => str_ends_with( $x, '@animalwrites.in' ) ) );
		$orig = $own ? $own[0] : ( $to ? $to[0] : AW_MAIL_BOX );
	}
	$text  = (string) ( aw_mail_pick( $msg, array( 'text', 'plain', 'body', 'preview', 'snippet', 'truncatedMessage', 'message' ) ) ?? '' );
	$text  = is_string( $text ) ? $text : wp_json_encode( $text );
	$files = array();
	foreach ( (array) ( $msg['attachments'] ?? array() ) as $att ) {
		$files[] = is_array( $att ) ? trim( (string) ( $att['filename'] ?? $att['name'] ?? 'attachment' ) . ( isset( $att['size'] ) ? ' (' . size_format( (int) $att['size'] ) . ')' : '' ) ) : (string) $att;
	}
	$date = (string) ( aw_mail_pick( $msg, array( 'date', 'receivedAt', 'timestamp' ) ) ?? '' );

	$sent = $date ? strtotime( $date ) : false;
	$id   = wp_insert_post(
		array(
			'post_type'    => 'aw_message',
			'post_date'    => $sent ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $sent ) ) : current_time( 'mysql' ),
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
		'aw_m_mailbox'     => AW_MAIL_BOX,
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
	echo '<p>Hostinger Mail sends each new message in vedanshu@animalwrites.in (contact@ is its alias) to this address. It arrives in Inbox → Contact messages, tagged Email. Nothing is ever sent from WordPress.</p>';
	echo '<p><strong>Webhook URL (paste in hPanel → Agentic mail → Webhooks, event "message.received"):</strong><br><code>' . esc_html( rest_url( 'aw/v1/mail-in' ) ) . '</code></p>';
	echo '<p>Last message received: <strong>' . esc_html( is_array( $last ) ? $last['at'] . ' — to ' . $last['to'] . ' — ' . $last['subject'] : 'none yet' ) . '</strong></p>';
	echo '<form method="post">' . wp_nonce_field( 'aw_mail_in', 'aw_mail_nonce', true, false ) . '<table class="form-table">';
	$rows = array(
		'webhook_secret' => array( 'Webhook secret', 'The secret Hostinger shows once when the webhook is created.', true ),
		'api_token'      => array( 'Mail API token (optional)', 'From Agentic mail → API. Lets WordPress fetch the full message, its headers and attachment names.', true ),
		'mailbox_id'     => array( 'Mailbox ID (optional)', 'Found from the API token when left empty.', false ),
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
	echo '</form>';
	$imp = get_option( 'aw_mail_imported' );
	echo '<h2>Mail already in the mailbox</h2><p>' . esc_html( is_array( $imp ) ? 'Imported ' . $imp['at'] . ': ' . wp_json_encode( $imp['counts'] ) : 'Not imported yet. Needs the API token.' ) . '</p>';
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aw_mail_import' ), 'aw_mail_import' ) ) . '">Import existing mail (newest first)</a></p></div>';
}

/**
 * One-time import of the mail already in vedanshu@ (newest first). Each message goes through the same path as a webhook
 * delivery, so duplicates (Message-ID) and spam are skipped the same way.
 *
 * @param int $max Most messages to import.
 * @return array Counts by result.
 */
function aw_mail_import( $max = 1000 ) {
	$box = aw_mail_box_id();
	if ( '' === $box ) {
		return array( 'error' => 'no API token or mailbox' );
	}
	$n = array();
	for ( $page = 1, $seen = 0; $seen < $max; $page++ ) {
		$list = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/INBOX/messages?perPage=100&sort=-date&page=' . $page );
		if ( ! is_array( $list ) || ! $list ) {
			break;
		}
		foreach ( $list as $m ) {
			if ( ++$seen > $max || empty( $m['uid'] ) ) {
				break;
			}
			$req = new WP_REST_Request( 'POST', '/aw/v1/mail-in' );
			$req->set_header( 'content-type', 'application/json' );
			$req->set_body( wp_json_encode( array( 'event' => 'message.received', 'data' => array( 'mailboxResourceId' => $box, 'folder' => 'INBOX', 'uid' => $m['uid'] ) ) ) );
			$st       = (string) ( aw_mail_in( $req )->get_data()['status'] ?? 'error' );
			$n[ $st ] = ( $n[ $st ] ?? 0 ) + 1;
		}
		if ( count( $list ) < 100 ) {
			break;
		}
	}
	update_option( 'aw_mail_imported', array( 'at' => current_time( 'mysql' ), 'counts' => $n ), false );
	return $n;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'aw-mail import', fn() => WP_CLI::success( wp_json_encode( aw_mail_import() ) ) );
}

add_action(
	'admin_post_aw_mail_import',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_mail_import' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		aw_mail_import();
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-mail-in&imported=1' ) );
		exit;
	}
);
