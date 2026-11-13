<?php
/**
 * FluentCRM feed for mail. FluentCRM does not read mail; MailWeb feeds it (Mail → Settings → FluentCRM).
 *
 * - Incoming email to the chosen mailboxes: the sender's contact is found or made (status "transactional", so they
 *   are never added to a list or sent marketing) with the chosen tag, and gets a note "Email: <subject>" with who it was
 *   sent to, the mailbox, when it was sent, attachment names, the full text and an "Open in Mail" link. Each email is
 *   noted once (by Message-ID). Mail from our own domains, Hostinger and automatic senders makes no contact.
 * - Runs every 5 minutes on the newest mail and at once when the webhook fires; `wp mailweb crm-sync --all` goes
 *   through the whole Inbox and Archive. Reading the text leaves the message's read or unread state as it is. One
 *   run at a time: a webhook run that finds another running is skipped, and the next 5-minute run notes that mail.
 * - A reply sent from Mail adds "Reply sent: <subject>" with its text to each recipient's existing contact.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** FluentCRM feed settings: on, tag (title), boxes (mailbox keys; empty = all). */
function mailweb_crm_settings() {
	return wp_parse_args(
		(array) get_option( 'mailweb_crm', array() ),
		array(
			'on'    => true,
			'tag'   => __( 'Emailed us', 'mailweb' ),
			'boxes' => array(),
		)
	);
}

/** Mailboxes whose incoming mail goes to FluentCRM. */
function mailweb_crm_boxes() {
	$all  = array_keys( mailweb_all_boxes() );
	$want = (array) mailweb_crm_settings()['boxes'];
	return $want ? array_values( array_intersect( $all, $want ) ) : $all;
}

function mailweb_crm_on() {
	return function_exists( 'FluentCrmApi' ) && class_exists( '\FluentCrm\App\Models\SubscriberNote' ) && ! empty( mailweb_crm_settings()['on'] );
}

/**
 * The sender's contact: found, or made as "transactional" (no list, no marketing). The feed's tag is added.
 *
 * @param string $email Address.
 * @param string $name  Display name.
 * @return object|null
 */
function mailweb_crm_contact_for( $email, $name ) {
	$c     = mailweb_crm_upsert( $email, $name, 'mailweb' );
	$title = trim( (string) mailweb_crm_settings()['tag'] );
	if ( $c && '' !== $title ) {
		$tags = FluentCrmApi( 'tags' )->getInstance();
		$tag  = $tags->where( 'slug', sanitize_title( $title ) )->first();
		if ( ! $tag ) {
			$tag = $tags->create( array( 'title' => $title, 'slug' => sanitize_title( $title ) ) );
		}
		if ( $tag ) {
			$c->attachTags( array( (int) $tag->id ) );
		}
	}
	return $c ? $c : null;
}

/**
 * A FluentCRM contact by email: found, or made as "transactional" (no list, no marketing) with the name split into
 * first and last. The one copy of this step; Animal Writes Core's aw_crm_contact_for() calls it when MailWeb is active.
 *
 * @param string $email  Address.
 * @param string $name   Display name ('' or an address: no name).
 * @param string $source Where the contact came from (FluentCRM's source field), for a new contact.
 * @return object|null
 */
function mailweb_crm_upsert( $email, $name, $source ) {
	$c = FluentCrmApi( 'contacts' )->getContact( $email );
	if ( ! $c ) {
		$data  = array( 'email' => $email, 'status' => 'transactional', 'source' => $source );
		$name  = false !== strpos( (string) $name, '@' ) ? '' : trim( (string) $name, " \t\"'" );
		$parts = preg_split( '/\s+/', $name, 2 );
		if ( '' !== ( $parts[0] ?? '' ) ) {
			$data['first_name'] = $parts[0];
			$data['last_name']  = $parts[1] ?? '';
		}
		$c = FluentCrmApi( 'contacts' )->createOrUpdate( $data );
	}
	return $c ? $c : null;
}

/** Is this sender a person (not us, not a mail system or notification sender)? */
function mailweb_crm_person( $email ) {
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$domain = substr( strrchr( $email, '@' ), 1 );
	$local  = strstr( $email, '@', true );
	if ( preg_match( '/(^|\.)(hostinger\.[a-z.]+|hostingersite\.com)$/', $domain ) || in_array( $domain, mailweb_own_domains(), true ) ) {
		return false;
	}
	return ! preg_match( '/^(wordpress|no-?reply|do-?not-?reply|donotreply|mailer-daemon|postmaster|bounces?|notifications?|notify)([+._-].*)?$/', $local );
}

/** Message-IDs already noted: [message-id => note id]. */
function mailweb_crm_done() {
	return (array) get_option( 'mailweb_crm_done', array() );
}

function mailweb_crm_mark_done( $mid, $note ) {
	$done         = mailweb_crm_done();
	$done[ $mid ] = (int) $note;
	update_option( 'mailweb_crm_done', array_slice( $done, -5000, null, true ), false );
}

/**
 * Note one incoming email on its sender's contact. Returns 'noted', 'done', 'skipped' or 'error'.
 *
 * @param string $key    Mailbox key.
 * @param string $folder Folder path.
 * @param array  $m      Message (API list or get).
 */
function mailweb_crm_note_email( $key, $folder, $m ) {
	if ( ! mailweb_crm_on() ) {
		return 'skipped';
	}
	$mid = trim( (string) ( $m['messageId'] ?? '' ), '<> ' );
	$mid = '' !== $mid ? $mid : 'uid:' . $key . ':' . $folder . ':' . (int) $m['uid'];
	if ( isset( mailweb_crm_done()[ $mid ] ) ) {
		return 'done';
	}
	$from  = $m['from'] ?? array();
	$from  = is_array( $from ) && isset( $from[0] ) ? $from[0] : $from;
	$email = strtolower( (string) ( $from['address'] ?? '' ) );
	if ( ! mailweb_crm_person( $email ) || apply_filters( 'mailweb_crm_skip_email', false, $email ) ) {
		return 'skipped'; // not stored as done: this check is cheap and keeps the list for noted mail
	}
	$c = mailweb_crm_contact_for( $email, (string) ( $from['name'] ?? '' ) );
	if ( ! $c ) {
		return 'skipped';
	}
	// The text (fetching it does not mark the message read).
	list( $code, $t ) = mailweb_call( 'GET', mailweb_fpath( $key, $folder ) . '/messages/' . (int) $m['uid'] . '/text' );
	if ( 200 !== $code ) {
		return 'error';
	}
	$text = trim( (string) ( $t['data']['text'] ?? '' ) );
	if ( '' === $text ) {
		$text = trim( wp_strip_all_tags( (string) ( $t['data']['html'] ?? '' ) ) );
	}
	$box   = mailweb_all_boxes()[ $key ] ?? '';
	$own   = array_values( array_intersect( mailweb_addresses( array_merge( (array) ( $m['to'] ?? array() ), (array) ( $m['cc'] ?? array() ) ) ), mailweb_own() ) );
	$sent  = ! empty( $m['date'] ) ? strtotime( (string) $m['date'] ) : time();
	$files = array_map( fn( $a ) => (string) ( $a['filename'] ?? 'attachment' ), array_filter( (array) ( $m['attachments'] ?? array() ), fn( $a ) => empty( $a['inline'] ) ) );
	$head  = array_filter(
		array(
			__( 'Sent to', 'mailweb' )     => $own ? implode( ', ', $own ) : $box,
			__( 'Mailbox', 'mailweb' )     => $box,
			__( 'Sent', 'mailweb' )        => wp_date( 'j M Y, g:i a', $sent ),
			__( 'Attachments', 'mailweb' ) => implode( ', ', $files ),
		)
	);
	$html  = '';
	foreach ( $head as $k => $v ) {
		$html .= '<strong>' . esc_html( $k ) . ':</strong> ' . esc_html( $v ) . '<br>';
	}
	$open = add_query_arg( array( 'page' => 'mailweb', 'box' => $key, 'folder' => mailweb_role_of( $folder ), 'uid' => (int) $m['uid'], 'view' => 'all' ), admin_url( 'admin.php' ) );
	$note = \FluentCrm\App\Models\SubscriberNote::create(
		array(
			'subscriber_id' => (int) $c->id,
			'type'          => 'email',
			'title'         => sprintf(
				/* translators: %s: email subject */
				__( 'Email: %s', 'mailweb' ),
				'' !== trim( (string) ( $m['subject'] ?? '' ) ) ? $m['subject'] : __( '(no subject)', 'mailweb' )
			),
			'description'   => '<p>' . $html . '</p>' . ( '' !== $text ? wpautop( esc_html( $text ) ) : '<p><em>' . esc_html__( '(no text)', 'mailweb' ) . '</em></p>' ) . '<p><a href="' . esc_url( $open ) . '">' . esc_html__( 'Open in Mail', 'mailweb' ) . '</a></p>',
			'created_at'    => wp_date( 'Y-m-d H:i:s', $sent ),
		)
	);
	mailweb_crm_mark_done( $mid, $note ? (int) $note->id : 0 );
	return $note ? 'noted' : 'error';
}

/**
 * One sync at a time (cron, webhook and WP-CLI), so two runs never note the same email or overwrite the done list.
 * An atomic INSERT IGNORE on the options table; a lock older than 15 minutes is from a run that died.
 *
 * @return bool True when this run holds the lock.
 */
function mailweb_crm_lock() {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = 'mailweb_crm_lock' AND option_value < %d", time() - 15 * MINUTE_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('mailweb_crm_lock', %s, 'off')", (string) time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** Keep the lock fresh during a long run (WP-CLI --all). */
function mailweb_crm_lock_touch() {
	global $wpdb;
	$wpdb->update( $wpdb->options, array( 'option_value' => (string) time() ), array( 'option_name' => 'mailweb_crm_lock' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** Release the sync lock. */
function mailweb_crm_unlock() {
	global $wpdb;
	$wpdb->delete( $wpdb->options, array( 'option_name' => 'mailweb_crm_lock' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Note new mail in a mailbox's folder (newest $per, or every page with $all). Returns counts by result, or
 * array( 'busy' => 1 ) when another sync is running.
 */
function mailweb_crm_sync( $key, $folder = 'INBOX', $all = false, $per = 25 ) {
	if ( ! mailweb_crm_on() || '' === mailweb_box_id( $key ) ) {
		return array();
	}
	if ( ! mailweb_crm_lock() ) {
		return array( 'busy' => 1 );
	}
	try {
		$n = mailweb_crm_sync_pages( $key, $folder, $all, $per );
	} finally {
		mailweb_crm_unlock();
	}
	return $n;
}

/** The pages of one sync (called with the lock held). */
function mailweb_crm_sync_pages( $key, $folder, $all, $per ) {
	$n = array();
	for ( $page = 1; $page <= ( $all ? 50 : 1 ); $page++ ) {
		mailweb_crm_lock_touch();
		list( $code, $l ) = mailweb_call( 'GET', mailweb_fpath( $key, $folder ) . '/messages?' . http_build_query( array( 'page' => $page, 'perPage' => $all ? 100 : $per, 'sort' => '-date' ) ) );
		$rows             = 200 === $code ? (array) ( $l['data'] ?? array() ) : array();
		foreach ( $rows as $m ) {
			$r       = mailweb_crm_note_email( $key, $folder, $m );
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
	'mailweb_crm_sync',
	function () {
		foreach ( mailweb_crm_boxes() as $key ) {
			mailweb_crm_sync( $key );
		}
	}
);
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'mailweb_crm_sync' ) ) {
			wp_schedule_event( time() + 120, 'mailweb_5min', 'mailweb_crm_sync' );
		}
	}
);

// The webhook: sync that mailbox at once.
add_action(
	'mailweb_webhook_noted',
	function ( $key ) {
		if ( in_array( $key, mailweb_crm_boxes(), true ) ) {
			mailweb_crm_sync( $key, 'INBOX', false, 5 );
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
function mailweb_crm_note_reply( $from, $to, $subject, $html ) {
	if ( ! mailweb_crm_on() ) {
		return;
	}
	$user = wp_get_current_user();
	foreach ( (array) $to as $email ) {
		$c = FluentCrmApi( 'contacts' )->getContact( strtolower( (string) $email ) );
		if ( ! $c ) {
			continue;
		}
		$head = '<strong>' . esc_html__( 'To:', 'mailweb' ) . '</strong> ' . esc_html( $email ) . '<br><strong>' . esc_html__( 'From:', 'mailweb' ) . '</strong> ' . esc_html( $from ) . '<br><strong>' . esc_html__( 'Sent:', 'mailweb' ) . '</strong> ' . esc_html( wp_date( 'j M Y, g:i a' ) ) . '<br><strong>' . esc_html__( 'Sent by:', 'mailweb' ) . '</strong> ' . esc_html( $user && $user->exists() ? $user->display_name : get_bloginfo( 'name' ) );
		\FluentCrm\App\Models\SubscriberNote::create(
			array(
				'subscriber_id' => (int) $c->id,
				'type'          => 'email',
				/* translators: %s: email subject */
				'title'         => sprintf( __( 'Reply sent: %s', 'mailweb' ), $subject ),
				'description'   => '<p>' . $head . '</p>' . wp_kses_post( $html ),
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'mailweb crm-sync',
		function ( $args, $assoc ) {
			$all = ! empty( $assoc['all'] );
			foreach ( mailweb_crm_boxes() as $key ) {
				foreach ( $all ? array( 'INBOX', 'INBOX.Archive' ) : array( 'INBOX' ) as $folder ) {
					WP_CLI::log( $key . '/' . $folder . ': ' . wp_json_encode( mailweb_crm_sync( $key, $folder, $all ) ) );
				}
			}
		}
	);
}

// FluentCRM's per-contact job (its profile's "Send Email") runs at once; while it runs the campaign share of the daily
// limit does not hold it back (mail-cap.php). The limit itself still applies: past it, the email waits in the queue.
add_action( 'fluentcrm_process_contact_jobs', fn() => $GLOBALS['mailweb_crm_contact_job'] = true, 1 );
add_action( 'fluentcrm_process_contact_jobs', fn() => $GLOBALS['mailweb_crm_contact_job'] = false, 1000 );
