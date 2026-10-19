<?php
/**
 * Incoming mail → Inbox (owner, 9 Oct 2026). Hostinger Mail calls POST /wp-json/aw/v1/mail-in for every new message
 * in vedanshu@animalwrites.in (contact@ is its alias) and support@animalwrites.in — each mailbox's webhook has its own
 * secret, and each item records the mailbox it came to.
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
 * - Each message is also a note on the sender's FluentCRM contact (found, or made as "transactional"; tag "Emailed
 *   us"); see fluentcrm.php. No auto-reply is ever sent. Each email can be answered from webmail or from its Reply
 *   (Inbox row, its screen, or its note on the contact → Inbox → Reply, mail-reply.php): Reply-To = the address that
 *   email was sent to, threaded on its Message-ID, so the answer comes back here.
 *
 * Moved from animal-writes-core to Mailweb (aw-mail) on 18 Oct 2026: same route, options, post type and meta keys.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Mailboxes that send webhooks here (owner, 9 Oct 2026): key => address. contact@ is vedanshu@'s alias. */
const AW_MAIL_BOXES = array( 'vedanshu' => 'vedanshu@animalwrites.in', 'support' => 'support@animalwrites.in' );
const AW_MAIL_BOX   = 'vedanshu@animalwrites.in'; // the main mailbox (default "Sent to")

/** A mail setting: the server environment, else wp-config, else Settings → Incoming mail. */
function aw_mail_setting( $key ) {
	if ( 'api_token' === $key && defined( 'AW_HOSTINGER_MAIL_TOKEN' ) && AW_HOSTINGER_MAIL_TOKEN ) {
		return (string) AW_HOSTINGER_MAIL_TOKEN; // wp-config (owner, 18 Oct 2026)
	}
	$env = array( 'webhook_secret' => 'HOSTINGER_MAIL_WEBHOOK_SECRET', 'secret_vedanshu' => 'HOSTINGER_MAIL_WEBHOOK_SECRET', 'secret_support' => 'HOSTINGER_MAIL_WEBHOOK_SECRET_SUPPORT', 'api_token' => 'HOSTINGER_MAIL_TOKEN' );
	$v   = isset( $env[ $key ] ) ? getenv( $env[ $key ] ) : false;
	if ( $v ) {
		return (string) $v;
	}
	$const = strtoupper( 'aw_mail_' . $key );
	if ( defined( $const ) && constant( $const ) ) {
		return (string) constant( $const );
	}
	$opt = (string) get_option( 'aw_mail_' . $key, '' );
	if ( '' === $opt && 'secret_vedanshu' === $key ) {
		$opt = (string) get_option( 'aw_mail_webhook_secret', '' ); // the single secret saved earlier
	}
	return $opt;
}

/**
 * Mailbox IDs (resourceId) for every mailbox the token can see (GET /api/v1/me), keyed by local part (vedanshu,
 * support, noreply), and remembered with their addresses (aw_mail_all_boxes, mailboxes.php).
 */
function aw_mail_box_ids( $refresh = false ) {
	$ids = (array) get_option( 'aw_mail_box_ids', array() );
	if ( $refresh || count( $ids ) < count( AW_MAIL_BOXES ) || false === get_option( 'aw_mail_box_addresses', false ) ) {
		$me = aw_mail_api( '/api/v1/me' );
		if ( is_array( $me ) && isset( $me['mailboxes'] ) ) {
			$adr = array();
			foreach ( (array) $me['mailboxes'] as $mb ) {
				$a = strtolower( (string) ( $mb['address'] ?? '' ) );
				if ( is_email( $a ) && ! empty( $mb['resourceId'] ) ) {
					$key         = sanitize_key( strstr( $a, '@', true ) );
					$ids[ $key ] = (string) $mb['resourceId'];
					$adr[ $key ] = $a;
				}
			}
			update_option( 'aw_mail_box_ids', $ids, false );
			update_option( 'aw_mail_box_addresses', $adr, false );
		}
	}
	return $ids;
}

/** The mailbox ID of one of our mailboxes ('' when unknown). */
function aw_mail_box_id( $key = 'vedanshu' ) {
	return (string) ( aw_mail_box_ids()[ $key ] ?? '' );
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

/**
 * Put \Seen back off a message that was unread before WordPress read it: the Mail API's text endpoint marks a message
 * read (Hostinger docs), which made filed mail show as read in webmail (found 18 Oct 2026). Read state stays the owner's.
 */
function aw_mail_restore_unseen( $box, $folder, $uid ) {
	$token = aw_mail_setting( 'api_token' );
	if ( '' === $token || '' === (string) $box || '' === (string) $uid ) {
		return false;
	}
	$r = wp_remote_request(
		'https://api.mail.hostinger.com/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ),
		array( 'method' => 'PATCH', 'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Accept' => 'application/json' ), 'body' => wp_json_encode( array( 'removeFlags' => array( '\\Seen' ) ) ) )
	);
	return ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) < 300;
}

/** Was this message unread (API message array)? */
function aw_mail_was_unseen( $m ) {
	return is_array( $m ) && ( ! empty( $m['unseen'] ) || ( isset( $m['flags'] ) && ! in_array( '\\Seen', (array) $m['flags'], true ) ) );
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
					$auth  = (string) $req->get_header( 'authorization' );
					$given = preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ? trim( $m[1] ) : '';
					foreach ( array_keys( AW_MAIL_BOXES ) as $key ) { // each mailbox's webhook has its own secret
						$secret = aw_mail_setting( 'secret_' . $key );
						if ( '' !== $secret && '' !== $given && hash_equals( $secret, $given ) ) {
							$req->set_param( '_aw_mailbox', $key );
							return true;
						}
					}
					return false;
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
	if ( ! apply_filters( 'aw_mail_file_copies', false ) && '' === (string) $req->get_param( '_aw_force' ) ) {
		// The inbox reads the mailboxes live (inbox.php): a delivery only refreshes the counts (owner, 18 Oct 2026).
		if ( function_exists( 'aw_mail_overview_flush' ) ) {
			aw_mail_overview_flush();
		}
		foreach ( array_keys( AW_MAIL_BOXES ) as $k ) {
			delete_transient( 'aw_mail_folders_' . $k );
		}
		update_option( 'aw_mail_last', array( 'at' => current_time( 'mysql' ), 'to' => '', 'mailbox' => AW_MAIL_BOXES[ (string) $req->get_param( '_aw_mailbox' ) ] ?? '', 'subject' => '' ), false );
		return new WP_REST_Response( array( 'status' => 'noted' ), 200 );
	}
	$boxkey = isset( AW_MAIL_BOXES[ (string) $req->get_param( '_aw_mailbox' ) ] ) ? (string) $req->get_param( '_aw_mailbox' ) : 'vedanshu';
	$boxadr = AW_MAIL_BOXES[ $boxkey ];
	$p      = (array) $req->get_json_params();
	if ( '' === (string) $req->get_param( '_aw_import' ) ) { // what Hostinger's webhook sends (field names only, for mapping)
		$keys = array_keys( $p );
		foreach ( array( 'data', 'message' ) as $k ) {
			if ( isset( $p[ $k ] ) && is_array( $p[ $k ] ) ) {
				$keys[] = $k . ': ' . implode( ', ', array_keys( $p[ $k ] ) );
			}
		}
		update_option( 'aw_mail_webhook_fields', $keys, false );
	}
	$data = isset( $p['data'] ) && is_array( $p['data'] ) ? $p['data'] : $p;
	$evt  = (string) aw_mail_pick( $p, array( 'event', 'type', 'data.event' ) );
	if ( $evt && false === stripos( $evt, 'received' ) && false === stripos( $evt, 'test' ) ) {
		return new WP_REST_Response( array( 'status' => 'ignored' ), 200 );
	}
	$box    = (string) ( aw_mail_pick( $data, array( 'mailboxResourceId', 'mailbox.resourceId', 'mailbox_id', 'resourceId' ) ) ?? aw_mail_box_id( $boxkey ) );
	$folder = (string) ( aw_mail_pick( $data, array( 'folder', 'path', 'message.folder', 'message.path' ) ) ?? 'INBOX' );
	$uid    = (string) aw_mail_pick( $data, array( 'uid', 'message.uid', 'messageUid' ) );
	$msg    = is_array( $data['message'] ?? null ) ? $data['message'] : $data;

	// The full message from the Mail API when we can; otherwise what the webhook carried.
	$source = '';
	if ( $box && $uid ) {
		$full = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ) );
		if ( is_array( $full ) ) {
			$unseen = aw_mail_was_unseen( $full );
			$msg  = $full + $msg;
			$text = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ) . '/text' );
			if ( ! $text || ( is_array( $text ) && '' === trim( (string) ( $text['text'] ?? '' ) . (string) ( $text['html'] ?? '' ) ) ) ) {
				sleep( 2 ); // just arrived: one quick retry
				$text = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ) . '/text' );
			}
			if ( $text ) {
				$msg['text'] = is_array( $text ) ? (string) ( $text['text'] ?? '' ) : (string) $text;
				if ( '' === trim( $msg['text'] ) && is_array( $text ) && ! empty( $text['html'] ) ) {
					$msg['text'] = wp_strip_all_tags( (string) $text['html'] );
				}
			}
			$src    = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ) . '/source' );
			$source = is_array( $src ) ? (string) ( $src['source'] ?? $src['raw'] ?? '' ) : (string) $src;
			if ( $unseen ) {
				aw_mail_restore_unseen( $box, $folder, $uid ); // reading it here must not mark it read in webmail
			}
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
	$dup = get_posts( array( 'post_type' => 'aw_message', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => 1, 'meta_key' => 'aw_m_message_id', 'meta_value' => $mid ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $dup ) {
		// Already in the Inbox: only fill in what an earlier, thinner delivery left empty (its text).
		$t = (string) ( aw_mail_pick( $msg, array( 'text', 'plainBody', 'plain', 'body' ) ) ?? '' );
		if ( '' !== trim( $t ) && '' === trim( (string) get_post_field( 'post_content', $dup[0] ) ) ) {
			wp_update_post( array( 'ID' => $dup[0], 'post_content' => sanitize_textarea_field( $t ) ) );
			if ( function_exists( 'aw_crm_message_note' ) ) {
				aw_crm_message_note( $dup[0] ); // the note gets the text too
			}
			return new WP_REST_Response( array( 'status' => 'filled' ), 200 );
		}
		return new WP_REST_Response( array( 'status' => 'duplicate' ), 200 );
	}

	$subject = trim( wp_strip_all_tags( (string) ( aw_mail_pick( $msg, array( 'subject', 'title' ) ) ?? '' ) ) );
	$from    = aw_mail_pick( $msg, array( 'from', 'sender', 'from.address' ) );
	$from_s  = is_array( $from ) ? trim( ( $from['name'] ?? '' ) . ' <' . ( $from['address'] ?? $from['email'] ?? '' ) . '>' ) : (string) $from;
	$to      = aw_mail_addresses( aw_mail_pick( $msg, array( 'to', 'recipients' ) ) ?? array() );
	// Forwarded mail keeps the address it was sent to in the headers (vedanshu@, contact@, any@ via the catch-all).
	$orig = '';
	// An alias it was written to (contact@ → vedanshu@) wins: Delivered-To names only the mailbox (18 Oct 2026).
	$aliases = function_exists( 'aw_mail_aliases' ) ? array_keys( aw_mail_aliases() ) : array();
	foreach ( array( 'X-Original-To', 'X-Forwarded-To', 'To', 'Cc' ) as $h ) {
		$hit = array_values( array_intersect( aw_mail_addresses( aw_mail_header( $source, $h ) ), $aliases ) );
		if ( $hit ) {
			$orig = $hit[0];
			break;
		}
	}
	$hit = array_values( array_intersect( $to, $aliases ) );
	if ( '' === $orig && $hit ) {
		$orig = $hit[0];
	}
	foreach ( '' === $orig ? array( 'X-Original-To', 'X-Forwarded-To', 'Delivered-To', 'To' ) : array() as $h ) {
		$a = aw_mail_addresses( aw_mail_header( $source, $h ) );
		$a = array_values( array_filter( $a, fn( $x ) => str_ends_with( $x, '@animalwrites.in' ) && 'noreply@animalwrites.in' !== $x ) );
		if ( $a ) {
			$orig = $a[0];
			break;
		}
	}
	if ( '' === $orig ) {
		$own  = array_values( array_filter( $to, fn( $x ) => str_ends_with( $x, '@animalwrites.in' ) ) );
		$orig = $own ? $own[0] : ( $to ? $to[0] : $boxadr );
	}
	$text  = aw_mail_pick( $msg, array( 'text', 'plainBody', 'plain', 'body', 'preview', 'snippet', 'truncatedMessage', 'message' ) ) ?? '';
	$text  = is_string( $text ) ? $text : wp_json_encode( $text );
	if ( '' === trim( $text ) && ! empty( $msg['htmlBody'] ) ) { // the webhook carries the body as plainBody / htmlBody
		$text = wp_strip_all_tags( (string) $msg['htmlBody'] );
	}
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
		'aw_m_mailbox'     => $boxadr,
		'aw_m_message_id'  => $mid,
		'aw_m_date'        => $date,
		'aw_m_attachments' => implode( "\n", $files ),
		'aw_m_source'      => '', // Sent from = a page on the site; emails have none
		'aw_m_contact'     => 'Not subscribed', // never added to the newsletter without their own consent
	) as $k => $v ) {
		update_post_meta( $id, $k, sanitize_text_field( (string) $v ) );
	}
	update_post_meta( $id, 'aw_review', 'held: email — to review' );
	if ( function_exists( 'aw_crm_message_note' ) ) {
		aw_crm_message_note( $id ); // a note on the sender's FluentCRM contact (fluentcrm.php)
	}
	if ( '' === trim( $text ) && $box && $uid ) {
		// The webhook arrives as the mail lands; the text may not be ready yet. Fetch it again in a minute.
		wp_schedule_single_event( time() + 60, 'aw_mail_refill', array( $id, $box, $folder, $uid ) );
	}
	update_option( 'aw_mail_last', array( 'at' => current_time( 'mysql' ), 'to' => $orig, 'mailbox' => $boxadr, 'subject' => $subject ), false );
	/**
	 * A new email is in the Inbox (unread badge refresh: overview.php).
	 * Not fired during the one-time import.
	 */
	if ( empty( $GLOBALS['aw_mail_importing'] ) ) {
		do_action( 'aw_mail_received', $id, array( 'mailbox' => $boxadr, 'to' => $orig, 'name' => trim( preg_replace( '/<[^>]*>/', '', $from_s ) ), 'email' => $from_addr ? $from_addr[0] : '', 'subject' => $subject, 'text' => (string) $text, 'date' => $date, 'source' => $source ) );
	}
	return new WP_REST_Response( array( 'status' => 'created', 'id' => $id ), 201 );
}


/* ---------- Settings → Incoming mail ---------- */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Incoming mail', 'Incoming mail', 'manage_options', 'aw-mail-in', 'aw_mail_in_screen' );
	}
);

function aw_mail_in_screen() {
	if ( isset( $_POST['aw_mail_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_mail_nonce'] ) ), 'aw_mail_in' ) && current_user_can( 'manage_options' ) ) {
		foreach ( array( 'secret_vedanshu', 'secret_support', 'api_token' ) as $k ) {
			$v = trim( sanitize_text_field( wp_unslash( $_POST[ 'aw_mail_' . $k ] ?? '' ) ) );
			if ( '' !== $v ) { // an empty field keeps the saved value
				update_option( 'aw_mail_' . $k, $v, false );
			}
		}
		aw_mail_box_ids( true ); // fill the mailbox IDs from the token
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	if ( isset( $_GET['imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>Import finished (see below).</p></div>';
	}
	$ids  = '' !== aw_mail_setting( 'api_token' ) ? aw_mail_box_ids() : array();
	$last = get_option( 'aw_mail_last' );
	echo '<div class="wrap"><h2>Incoming mail</h2>';
	echo '<p>The Mail inbox reads vedanshu@ (with contact@), support@ and noreply@ live through the Mail API with the token below; nothing from the mailboxes is copied into WordPress. Hostinger calls the webhook below when mail arrives, which refreshes the unread counts.</p>';
	echo '<p><strong>Webhook URL (hPanel → Emails → each mailbox → Agentic mail → Webhooks, event "message.received"):</strong><br><code>' . esc_html( rest_url( 'aw/v1/mail-in' ) ) . '</code></p>';
	echo '<p>Last message received: <strong>' . esc_html( is_array( $last ) ? $last['at'] . ' — ' . ( $last['mailbox'] ?? '' ) . ' — to ' . $last['to'] . ' — ' . $last['subject'] : 'none yet' ) . '</strong></p>';
	echo '<form method="post">';
	wp_nonce_field( 'aw_mail_in', 'aw_mail_nonce' );
	echo '<table class="form-table">';
	$field = function ( $k, $label, $help ) {
		$saved = '' !== aw_mail_setting( $k );
		echo '<tr><th><label for="aw_mail_' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text code" type="password" autocomplete="off" id="aw_mail_' . esc_attr( $k ) . '" name="aw_mail_' . esc_attr( $k ) . '" value=""' . ( $saved ? ' placeholder="Saved. Leave empty to keep it."' : '' ) . '><p class="description">' . esc_html( $help ) . '</p></td></tr>';
	};
	$field( 'secret_vedanshu', 'Webhook secret — vedanshu@', 'Shown once when the webhook is created on vedanshu@animalwrites.in.' );
	$field( 'secret_support', 'Webhook secret — support@', 'Shown once when the webhook is created on support@animalwrites.in.' );
	$field( 'api_token', 'Mail API token', 'From Agentic mail → API (scope: vedanshu@ and support@). Lets WordPress read the full message, its headers and attachment names, and fills the mailbox IDs below.' . ( defined( 'AW_HOSTINGER_MAIL_TOKEN' ) && AW_HOSTINGER_MAIL_TOKEN ? ' Set in wp-config (AW_HOSTINGER_MAIL_TOKEN): that one is used.' : '' ) );
	foreach ( aw_mail_all_boxes() as $key => $addr ) {
		echo '<tr><th>Mailbox ID — ' . esc_html( strtok( $addr, '@' ) . '@' ) . '</th><td><input class="regular-text code" readonly value="' . esc_attr( (string) ( $ids[ $key ] ?? '' ) ) . '" placeholder="Filled from the token"><p class="description">' . esc_html( $addr ) . ' (read only)</p></td></tr>';
	}
	echo '</table>';
	submit_button();
	echo '</form>';
	$imp = get_option( 'aw_mail_imported' );
	if ( apply_filters( 'aw_mail_file_copies', false ) ) {
	echo '<h2>Mail already in the mailboxes</h2><p>' . esc_html( is_array( $imp ) ? 'Imported ' . $imp['at'] . ': ' . wp_json_encode( $imp['counts'] ) : 'Not imported yet. Needs the API token.' ) . '</p>';
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aw_mail_import' ), 'aw_mail_import' ) ) . '">Import existing mail (newest first)</a></p>';
	}
	do_action( 'aw_mail_settings_after' ); // mailboxes and aliases, profiles (mailboxes.php, profiles.php)
	echo '</div>';
}

/**
 * One-time import of the mail already in vedanshu@ (newest first). Each message goes through the same path as a webhook
 * delivery, so duplicates (Message-ID) and spam are skipped the same way.
 *
 * @param int $max Most messages to import.
 * @return array Counts by result.
 */
function aw_mail_import( $max = 1000 ) {
	$GLOBALS['aw_mail_importing'] = true;
	$n = array();
	foreach ( array_keys( AW_MAIL_BOXES ) as $key ) {
		$box = aw_mail_box_id( $key );
		if ( '' === $box ) {
			$n[ $key ] = 'no mailbox ID (token missing or not scoped to this mailbox)';
			continue;
		}
		$c = array();
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
				$req->set_param( '_aw_mailbox', $key );
				$st       = (string) ( aw_mail_in( $req )->get_data()['status'] ?? 'error' );
				$c[ $st ] = ( $c[ $st ] ?? 0 ) + 1;
			}
			if ( count( $list ) < 100 ) {
				break;
			}
		}
		$n[ $key ] = $c;
	}
	update_option( 'aw_mail_imported', array( 'at' => current_time( 'mysql' ), 'counts' => $n ), false );
	$GLOBALS['aw_mail_importing'] = false;
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

/** Fill in an item's text a minute after it arrived (see the webhook handler). */
add_action(
	'aw_mail_refill',
	function ( $id, $box, $folder, $uid ) {
		if ( '' !== trim( (string) get_post_field( 'post_content', $id ) ) ) {
			return;
		}
		$was = aw_mail_was_unseen( aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ) ) );
		$t   = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/' . rawurlencode( $folder ) . '/messages/' . rawurlencode( $uid ) . '/text' );
		if ( $was ) {
			aw_mail_restore_unseen( $box, $folder, $uid );
		}
		$t   = is_array( $t ) ? ( '' !== trim( (string) ( $t['text'] ?? '' ) ) ? (string) $t['text'] : wp_strip_all_tags( (string) ( $t['html'] ?? '' ) ) ) : (string) $t;
		if ( '' !== trim( $t ) ) {
			wp_update_post( array( 'ID' => $id, 'post_content' => sanitize_textarea_field( $t ) ) );
			if ( function_exists( 'aw_crm_message_note' ) ) {
				aw_crm_message_note( $id );
			}
		}
	},
	10,
	4
);

/**
 * Safety net for the webhook (17 Oct 2026: it stopped delivering after 9 Oct). Every 5 minutes, list the newest 20
 * messages in each mailbox and pass on only those not seen before; aw_mail_in() still de-duplicates on Message-ID.
 */
function aw_mail_poll() {
	$seen = (array) get_option( 'aw_mail_seen_uids', array() );
	$n    = 0;
	foreach ( array_keys( AW_MAIL_BOXES ) as $key ) {
		$box = aw_mail_box_id( $key );
		if ( '' === $box ) {
			continue;
		}
		$list = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/INBOX/messages?perPage=20&sort=-date&page=1' );
		if ( ! is_array( $list ) ) {
			continue;
		}
		$known = (array) ( $seen[ $key ] ?? array() );
		foreach ( $list as $m ) {
			$uid = (string) ( $m['uid'] ?? '' );
			if ( '' === $uid || in_array( $uid, $known, true ) ) {
				continue;
			}
			$req = new WP_REST_Request( 'POST', '/aw/v1/mail-in' );
			$req->set_header( 'content-type', 'application/json' );
			$req->set_body( wp_json_encode( array( 'event' => 'message.received', 'data' => array( 'mailboxResourceId' => $box, 'folder' => 'INBOX', 'uid' => $uid ) ) ) );
			$req->set_param( '_aw_mailbox', $key );
			$st = (string) ( aw_mail_in( $req )->get_data()['status'] ?? 'error' );
			if ( 'error' !== $st ) {
				$known[] = $uid;
				$n      += 'created' === $st ? 1 : 0;
			}
		}
		$seen[ $key ] = array_slice( array_values( array_unique( $known ) ), -200 );
	}
	update_option( 'aw_mail_seen_uids', $seen, false );
	return $n;
}
add_action( 'aw_mail_poll', 'aw_mail_poll' );
add_action(
	'init',
	function () {
		$on = apply_filters( 'aw_mail_file_copies', false ); // no copies since 18 Oct 2026: no poll either
		if ( $on && ! wp_next_scheduled( 'aw_mail_poll' ) ) {
			wp_schedule_event( time() + 120, 'aw_5min', 'aw_mail_poll' );
		} elseif ( ! $on && wp_next_scheduled( 'aw_mail_poll' ) ) {
			wp_clear_scheduled_hook( 'aw_mail_poll' );
		}
	}
);
