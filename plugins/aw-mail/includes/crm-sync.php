<?php
/**
 * FluentCRM sync for mail (owner, 19 Oct 2026: "full text, as before"). FluentCRM itself does not read mail; Mailweb
 * feeds it.
 *
 * - Incoming email to vedanshu@ (with contact@) or support@: the sender's contact is found or made ("transactional",
 *   tag "Emailed us", via aw_crm_contact_for in animal-writes-core) and gets a note "Email: <subject>" with who it was
 *   sent to, the mailbox, when it was sent, attachment names, the full text and an "Open in Mail" link. Each email is
 *   noted once (by Message-ID; emails noted before 18 Oct through their Inbox copy count as done). Mail from our own
 *   addresses, Hostinger, WordPress and other automatic senders makes no contact.
 * - Runs every 5 minutes on the newest mail and at once when the webhook fires; `wp aw-mail crm-sync --all` goes
 *   through the whole Inbox and Archive. Reading the text marks a message read in webmail, so unread mail is put back
 *   to unread.
 * - A reply sent from Mail adds "Reply sent: <subject>" with its text to each recipient's existing contact.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Mailboxes whose incoming mail goes to FluentCRM. */
const AW_MAIL_CRM_BOXES = array( 'vedanshu', 'support' );

function aw_mail_crm_on() {
	return function_exists( 'FluentCrmApi' ) && function_exists( 'aw_crm_contact_for' ) && class_exists( '\FluentCrm\App\Models\SubscriberNote' );
}

/** Is this sender a person (not us, not a mail system or notification sender)? */
function aw_mail_crm_person( $email ) {
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$domain = substr( strrchr( $email, '@' ), 1 );
	$local  = strstr( $email, '@', true );
	if ( preg_match( '/(^|\.)(hostinger\.[a-z.]+|hostingersite\.com|animalwrites\.in)$/', $domain ) ) {
		return false;
	}
	return ! preg_match( '/^(wordpress|no-?reply|do-?not-?reply|donotreply|mailer-daemon|postmaster|bounces?|notifications?|notify)([+._-].*)?$/', $local );
}

/** Message-IDs already noted: [message-id => note id], including those noted through Inbox copies before 18 Oct. */
function aw_mail_crm_done() {
	$done = get_option( 'aw_mail_crm_done', false );
	if ( false === $done ) {
		global $wpdb;
		$done = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time seed from the old Inbox copies
		foreach ( (array) $wpdb->get_results( "SELECT m.meta_value mid, n.meta_value note FROM {$wpdb->postmeta} m JOIN {$wpdb->postmeta} n ON n.post_id = m.post_id AND n.meta_key = 'aw_m_crm_note' WHERE m.meta_key = 'aw_m_message_id'" ) as $r ) {
			$done[ trim( (string) $r->mid, '<> ' ) ] = (int) $r->note;
		}
		update_option( 'aw_mail_crm_done', $done, false );
	}
	return (array) $done;
}

function aw_mail_crm_mark_done( $mid, $note ) {
	$done         = aw_mail_crm_done();
	$done[ $mid ] = (int) $note;
	update_option( 'aw_mail_crm_done', array_slice( $done, -2000, null, true ), false );
}

/**
 * Note one incoming email on its sender's contact. Returns 'noted', 'done', 'skipped' or 'error'.
 *
 * @param string $key    Mailbox key.
 * @param string $folder Folder path.
 * @param array  $m      Message (API list or get).
 */
function aw_mail_crm_note_email( $key, $folder, $m ) {
	if ( ! aw_mail_crm_on() ) {
		return 'skipped';
	}
	$mid = trim( (string) ( $m['messageId'] ?? '' ), '<> ' );
	$mid = '' !== $mid ? $mid : 'uid:' . $key . ':' . $folder . ':' . (int) $m['uid'];
	if ( isset( aw_mail_crm_done()[ $mid ] ) ) {
		return 'done';
	}
	$from  = $m['from'] ?? array();
	$from  = is_array( $from ) && isset( $from[0] ) ? $from[0] : $from;
	$email = strtolower( (string) ( $from['address'] ?? '' ) );
	if ( ! aw_mail_crm_person( $email ) ) {
		aw_mail_crm_mark_done( $mid, 0 );
		return 'skipped';
	}
	$c = aw_crm_contact_for( $email, (string) ( $from['name'] ?? '' ), array( 'emailed-us' ), 'email' );
	if ( ! $c ) {
		return 'skipped';
	}
	// The text (this marks it read in webmail: put unread mail back to unread).
	list( $code, $t ) = aw_mail_call( 'GET', aw_mail_fpath( $key, $folder ) . '/messages/' . (int) $m['uid'] . '/text' );
	if ( ! empty( $m['unseen'] ) ) {
		aw_mail_call( 'PATCH', aw_mail_fpath( $key, $folder ) . '/messages/' . (int) $m['uid'], array( 'removeFlags' => array( '\\Seen' ) ) );
	}
	if ( 200 !== $code ) {
		return 'error';
	}
	$text = trim( (string) ( $t['data']['text'] ?? '' ) );
	if ( '' === $text ) {
		$text = trim( wp_strip_all_tags( (string) ( $t['data']['html'] ?? '' ) ) );
	}
	$box   = aw_mail_all_boxes()[ $key ] ?? '';
	$own   = array_values( array_intersect( aw_mail_addr_list( array_merge( (array) ( $m['to'] ?? array() ), (array) ( $m['cc'] ?? array() ) ) ), aw_mail_own() ) );
	$sent  = ! empty( $m['date'] ) ? strtotime( (string) $m['date'] ) : time();
	$files = array_map( fn( $a ) => (string) ( $a['filename'] ?? 'attachment' ), array_filter( (array) ( $m['attachments'] ?? array() ), fn( $a ) => empty( $a['inline'] ) ) );
	$head  = array_filter(
		array(
			'Sent to'     => $own ? implode( ', ', $own ) : $box,
			'Mailbox'     => $box,
			'Sent'        => wp_date( 'j M Y, g:i a', $sent ),
			'Attachments' => implode( ', ', $files ),
		)
	);
	$html  = '';
	foreach ( $head as $k => $v ) {
		$html .= '<strong>' . esc_html( $k ) . ':</strong> ' . esc_html( $v ) . '<br>';
	}
	$open = add_query_arg( array( 'page' => 'aw-mail', 'box' => $key, 'folder' => aw_mail_role_of( $folder ), 'uid' => (int) $m['uid'], 'view' => 'all' ), admin_url( 'admin.php' ) );
	$note = \FluentCrm\App\Models\SubscriberNote::create(
		array(
			'subscriber_id' => (int) $c->id,
			'type'          => 'email',
			'title'         => 'Email: ' . ( '' !== trim( (string) ( $m['subject'] ?? '' ) ) ? $m['subject'] : '(no subject)' ),
			'description'   => '<p>' . $html . '</p>' . ( '' !== $text ? wpautop( esc_html( $text ) ) : '<p><em>(no text)</em></p>' ) . '<p><a href="' . esc_url( $open ) . '">Open in Mail</a></p>',
			'created_at'    => wp_date( 'Y-m-d H:i:s', $sent ),
		)
	);
	aw_mail_crm_mark_done( $mid, $note ? (int) $note->id : 0 );
	return $note ? 'noted' : 'error';
}

/**
 * Note new mail in a mailbox's folder (newest $per, or every page with $all). Returns counts by result.
 */
function aw_mail_crm_sync( $key, $folder = 'INBOX', $all = false, $per = 25 ) {
	$n = array();
	if ( ! aw_mail_crm_on() || '' === aw_mail_box_id( $key ) ) {
		return $n;
	}
	for ( $page = 1; $page <= ( $all ? 50 : 1 ); $page++ ) {
		list( $code, $l ) = aw_mail_call( 'GET', aw_mail_fpath( $key, $folder ) . '/messages?' . http_build_query( array( 'page' => $page, 'perPage' => $all ? 100 : $per, 'sort' => '-date' ) ) );
		$rows             = 200 === $code ? (array) ( $l['data'] ?? array() ) : array();
		foreach ( $rows as $m ) {
			$r       = aw_mail_crm_note_email( $key, $folder, $m );
			$n[ $r ] = ( $n[ $r ] ?? 0 ) + 1;
		}
		if ( count( $rows ) < ( $all ? 100 : $per ) ) {
			break;
		}
	}
	return $n;
}

// Every 5 minutes: the newest mail in each mailbox.
add_action(
	'aw_mail_crm_sync',
	function () {
		foreach ( AW_MAIL_CRM_BOXES as $key ) {
			aw_mail_crm_sync( $key );
		}
	}
);
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_mail_crm_sync' ) ) {
			wp_schedule_event( time() + 120, 'aw_5min', 'aw_mail_crm_sync' );
		}
	}
);

// The webhook: sync that mailbox at once.
add_action(
	'aw_mail_webhook_noted',
	function ( $key ) {
		if ( in_array( $key, AW_MAIL_CRM_BOXES, true ) ) {
			aw_mail_crm_sync( $key, 'INBOX', false, 5 );
		}
	}
);

/**
 * A reply sent from Mail: note it on each recipient's existing contact.
 *
 * @param string $from    Sending mailbox address.
 * @param array  $to      Recipient addresses.
 * @param string $subject Subject.
 * @param string $html    Message HTML (as sent).
 */
function aw_mail_crm_note_reply( $from, $to, $subject, $html ) {
	if ( ! aw_mail_crm_on() ) {
		return;
	}
	$user = wp_get_current_user();
	foreach ( (array) $to as $email ) {
		$c = FluentCrmApi( 'contacts' )->getContact( strtolower( (string) $email ) );
		if ( ! $c ) {
			continue;
		}
		$head = '<strong>To:</strong> ' . esc_html( $email ) . '<br><strong>From:</strong> ' . esc_html( $from ) . '<br><strong>Sent:</strong> ' . esc_html( wp_date( 'j M Y, g:i a' ) ) . '<br><strong>Sent by:</strong> ' . esc_html( $user && $user->exists() ? $user->display_name : 'Animal Writes' );
		\FluentCrm\App\Models\SubscriberNote::create(
			array(
				'subscriber_id' => (int) $c->id,
				'type'          => 'email',
				'title'         => 'Reply sent: ' . $subject,
				'description'   => '<p>' . $head . '</p>' . wp_kses_post( $html ),
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'aw-mail crm-sync',
		function ( $args, $assoc ) {
			$all = ! empty( $assoc['all'] );
			foreach ( AW_MAIL_CRM_BOXES as $key ) {
				foreach ( $all ? array( 'INBOX', 'INBOX.Archive' ) : array( 'INBOX' ) as $folder ) {
					WP_CLI::log( $key . '/' . $folder . ': ' . wp_json_encode( aw_mail_crm_sync( $key, $folder, $all ) ) );
				}
			}
		}
	);
}
