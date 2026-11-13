<?php
/**
 * Mail: one inbox in WordPress for every Hostinger mailbox.
 *
 * Everything is read from and written to the Hostinger mailboxes through the Mail API, so webmail and WordPress always
 * show the same mail, flags and folders. Nothing from the mailboxes is copied into WordPress.
 *
 * - Mailboxes: every mailbox the token sees, one at a time or all mixed.
 * - Folders: Inbox, Sent, Archive, Spam, Trash (and any others), with unread counts; plus a "Website forms" folder when
 *   a site supplies its form messages through the `mailweb_forms_source` filter (see mailweb_forms()).
 * - List: 25 a page, newest first, unread in bold, search (sender, subject, body).
 * - Read: the message in a sandboxed frame (no scripts; remote pictures off until "Show pictures"), attachments to
 *   download. Opening a message marks it read in webmail too.
 * - Reply, Reply all, Forward, New message: sent from the mailbox through the Mail API (inReplyTo / forwardOf keep the
 *   thread; the original is flagged Answered / Forwarded); a copy lands in the mailbox's Sent. The mailbox's signature
 *   is added.
 * - Archive, Move, Spam / Not spam, Delete (to Trash only; nothing is ever deleted for good), Mark read / unread.
 * - Who: administrators and editors. Every action checks a nonce. Sending: at most 30 an hour. No message text is
 *   logged anywhere.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAILWEB_CAP       = 'edit_others_posts'; // administrators and editors
const MAILWEB_PER_PAGE  = 25;
const MAILWEB_SEND_HOUR = 30;
const MAILWEB_FORMS     = 'forms'; // the "Website forms" folder (mailweb_forms())
const MAILWEB_MAX_ATTACH = 10485760; // attachments per message: 10 MB in total

/**
 * The website forms source, or null. A site shows its form messages in Mail by returning an array of callables from
 * the `mailweb_forms_source` filter:
 * - label  (string)                   Folder name (default "Website forms").
 * - list   fn( $page, $per ): array   ['items' => list of [id, name, email, subject, date, unread, replied?], 'total' => int].
 * - unread fn(): int                  Unread count.
 * - get    fn( $id ): array|null      [subject, email, date, text, fields => [label => value]].
 * - mark   fn( $id, $what ): bool     $what = read, unread or trash (to the WordPress Trash: restorable).
 * - replied fn( $id ): void           Optional: a reply was sent.
 *
 * @return array|null
 */
function mailweb_forms() {
	static $src = false;
	if ( false === $src ) {
		$s   = apply_filters( 'mailweb_forms_source', null );
		$src = is_array( $s ) && isset( $s['list'], $s['unread'], $s['get'], $s['mark'] ) ? $s + array( 'label' => __( 'Website forms', 'mailweb' ) ) : null;
	}
	return $src;
}

/* ---------- Mail API ---------- */

/**
 * One Mail API call. Returns [code, data (decoded JSON or raw body), headers].
 *
 * @param string     $method GET, POST, PATCH, DELETE.
 * @param string     $path   Path after the host.
 * @param array|null $body   JSON body.
 * @param bool       $raw    Return the raw body (attachments).
 * @return array
 */
function mailweb_call( $method, $path, $body = null, $raw = false ) {
	$token = function_exists( 'mailweb_setting' ) ? mailweb_setting( 'api_token' ) : '';
	if ( '' === $token ) {
		return array( 0, null, array() );
	}
	$args = array(
		'method'  => $method,
		'timeout' => $raw ? 60 : 20,
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => $raw ? '*/*' : 'application/json' ),
	);
	if ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body );
	}
	$r = wp_remote_request( 'https://api.mail.hostinger.com' . $path, $args );
	if ( is_wp_error( $r ) ) {
		return array( 0, null, array() );
	}
	$b = wp_remote_retrieve_body( $r );
	return array( (int) wp_remote_retrieve_response_code( $r ), $raw ? $b : json_decode( $b, true ), wp_remote_retrieve_headers( $r ) );
}

/** The API path of a mailbox's folder. */
function mailweb_fpath( $key, $folder ) {
	return '/api/v1/mailboxes/' . rawurlencode( mailweb_box_id( $key ) ) . '/folders/' . rawurlencode( $folder );
}

/** A mailbox's folders (path => [name, unread, total]), cached for a minute. */
function mailweb_folders( $key, $fresh = false ) {
	$c = get_transient( 'mailweb_folders_' . $key );
	if ( is_array( $c ) && ! $fresh ) {
		return $c;
	}
	list( $code, $j ) = mailweb_call( 'GET', '/api/v1/mailboxes/' . rawurlencode( mailweb_box_id( $key ) ) . '/folders' );
	$out = array();
	foreach ( 200 === $code ? (array) ( $j['data'] ?? $j ) : array() as $f ) {
		$p = (string) ( $f['path'] ?? '' );
		if ( '' === $p || 'INBOX.Drafts' === $p ) {
			continue; // drafts are written in webmail
		}
		$out[ $p ] = array( mailweb_folder_label( $p, (string) ( $f['specialUse'] ?? '' ) ), (int) ( $f['unreadCount'] ?? 0 ), (int) ( $f['messageCount'] ?? 0 ) );
	}
	$order = array( 'INBOX' => 0, 'INBOX.Sent' => 1, 'INBOX.Archive' => 2, 'INBOX.Junk' => 3, 'INBOX.Trash' => 4 );
	uksort( $out, fn( $a, $b ) => ( $order[ $a ] ?? 9 ) <=> ( $order[ $b ] ?? 9 ) ?: strcmp( $a, $b ) );
	if ( $out ) {
		set_transient( 'mailweb_folders_' . $key, $out, MINUTE_IN_SECONDS );
	}
	return $out;
}

function mailweb_folder_label( $path, $special = '' ) {
	$names = array( 'INBOX' => __( 'Inbox', 'mailweb' ), 'INBOX.Sent' => __( 'Sent', 'mailweb' ), 'INBOX.Archive' => __( 'Archive', 'mailweb' ), 'INBOX.Junk' => __( 'Spam', 'mailweb' ), 'INBOX.Trash' => __( 'Trash', 'mailweb' ) );
	$by    = array( '\\Sent' => __( 'Sent', 'mailweb' ), '\\Junk' => __( 'Spam', 'mailweb' ), '\\Trash' => __( 'Trash', 'mailweb' ), '\\Archive' => __( 'Archive', 'mailweb' ) );
	return $names[ $path ] ?? ( $by[ $special ] ?? preg_replace( '/^INBOX\./', '', $path ) );
}

/** The folder with a role (Trash, Junk, Archive) in a mailbox; Archive is made when missing. */
function mailweb_role_folder( $key, $role ) {
	$want = array( 'trash' => 'INBOX.Trash', 'junk' => 'INBOX.Junk', 'archive' => 'INBOX.Archive', 'inbox' => 'INBOX' )[ $role ] ?? 'INBOX';
	$have = mailweb_folders( $key );
	if ( 'INBOX.Archive' === $want && ! isset( $have[ $want ] ) ) {
		mailweb_call( 'POST', '/api/v1/mailboxes/' . rawurlencode( mailweb_box_id( $key ) ) . '/folders', array( 'name' => 'Archive' ) ); // makes INBOX.Archive
		delete_transient( 'mailweb_folders_' . $key );
	}
	return $want;
}

/** "Name <address>" of an API address object (or list). */
function mailweb_who( $a, $name_only = false ) {
	if ( is_array( $a ) && isset( $a[0] ) ) {
		$a = $a[0];
	}
	$n = is_array( $a ) ? trim( (string) ( $a['name'] ?? '' ) ) : '';
	$e = is_array( $a ) ? strtolower( trim( (string) ( $a['address'] ?? '' ) ) ) : '';
	if ( $name_only ) {
		return '' !== $n ? $n : $e;
	}
	return '' !== $n ? $n . ' <' . $e . '>' : $e;
}

/** Our own addresses (mailboxes and aliases). */
function mailweb_own() {
	return array_merge( array_values( mailweb_all_boxes() ), array_keys( mailweb_aliases() ) );
}

/* ---------- Folders by role (so "All mailboxes" can mix them) ---------- */

/** Folder roles shown above the list, in order: role => [label, path]. */
function mailweb_roles() {
	return array(
		'inbox'   => array( __( 'Inbox', 'mailweb' ), 'INBOX' ),
		'sent'    => array( __( 'Sent', 'mailweb' ), 'INBOX.Sent' ),
		'archive' => array( __( 'Archive', 'mailweb' ), 'INBOX.Archive' ),
		'spam'    => array( __( 'Spam', 'mailweb' ), 'INBOX.Junk' ),
		'trash'   => array( __( 'Trash', 'mailweb' ), 'INBOX.Trash' ),
	);
}

function mailweb_role_path( $role ) {
	return mailweb_roles()[ $role ][1] ?? 'INBOX';
}

function mailweb_role_of( $path ) {
	foreach ( mailweb_roles() as $r => $v ) {
		if ( $v[1] === $path ) {
			return $r;
		}
	}
	return 'inbox';
}

/* ---------- Screen state ---------- */

/** Current view: box (a mailbox key or 'all'), folder role, page, search, message UID or form item. */
function mailweb_state() {
	$boxes = mailweb_all_boxes();
	$key   = sanitize_key( wp_unslash( $_GET['box'] ?? 'all' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$key   = isset( $boxes[ $key ] ) ? $key : 'all';
	$f     = sanitize_key( wp_unslash( $_GET['folder'] ?? 'inbox' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$f     = ( MAILWEB_FORMS === $f && mailweb_forms() ) || isset( mailweb_roles()[ $f ] ) ? $f : 'inbox';
	return array(
		'box'    => $key,
		'folder' => $f,
		'page'   => max( 1, absint( $_GET['paged'] ?? 1 ) ), // phpcs:ignore WordPress.Security.NonceVerification
		'q'      => trim( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification
		'uid'    => absint( $_GET['uid'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification
		'item'   => absint( $_GET['item'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification
		'view'   => sanitize_key( wp_unslash( $_GET['view'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification -- the list (box) a message was opened from
	);
}

/** URL of a single action (admin-post, nonced). */
function mailweb_act_url( $act, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'mailweb_act', 'do' => $act ), $args ), admin_url( 'admin-post.php' ) ), 'mailweb_act' );
}

/** Mailboxes in the current view. */
function mailweb_view_boxes( $box ) {
	$all = mailweb_all_boxes();
	return 'all' === $box ? $all : array( $box => $all[ $box ] );
}

/** Unread count of a role across the boxes in view. */
function mailweb_role_unread( $role, $box ) {
	$n = 0;
	foreach ( array_keys( mailweb_view_boxes( $box ) ) as $k ) {
		$n += (int) ( mailweb_folders( $k )[ mailweb_role_path( $role ) ][1] ?? 0 );
	}
	return $n;
}

/**
 * The bulk actions a folder offers (the Inbox screen's menu and its route's check).
 *
 * @param string $folder Folder role, or MAILWEB_FORMS.
 * @return array action => label
 */
function mailweb_bulk_actions( $folder ) {
	if ( MAILWEB_FORMS === $folder ) {
		return array(
			'read'   => __( 'Mark read', 'mailweb' ),
			'unread' => __( 'Mark unread', 'mailweb' ),
			'trash'  => __( 'Move to Trash', 'mailweb' ),
		);
	}
	$a = array(
		'read'   => __( 'Mark read', 'mailweb' ),
		'unread' => __( 'Mark unread', 'mailweb' ),
	);
	if ( ! in_array( $folder, array( 'archive', 'trash' ), true ) ) {
		$a['archive'] = __( 'Archive', 'mailweb' );
	}
	if ( 'inbox' !== $folder ) {
		$a['inbox'] = 'spam' === $folder ? __( 'Not spam', 'mailweb' ) : __( 'Move to Inbox', 'mailweb' );
	}
	if ( ! in_array( $folder, array( 'spam', 'sent' ), true ) ) {
		$a['spam'] = __( 'Spam', 'mailweb' );
	}
	if ( 'trash' !== $folder ) {
		$a['trash'] = __( 'Move to Trash', 'mailweb' );
	}
	return $a;
}

/**
 * One page of the list, for one mailbox, all mailboxes mixed (newest first) or the Website forms folder. Read from the
 * Mail API (or the forms source); nothing is marked or moved.
 *
 * @param array $s State: box, folder, page, q (mailweb_state()).
 * @return array { items: rows, total: int, failed: bool }
 */
function mailweb_list_rows( $s ) {
	$per  = MAILWEB_PER_PAGE;
	$page = max( 1, (int) $s['page'] );
	if ( MAILWEB_FORMS === $s['folder'] ) {
		$res   = mailweb_forms() ? (array) call_user_func( mailweb_forms()['list'], $page, $per ) : array();
		$items = array();
		foreach ( (array) ( $res['items'] ?? array() ) as $f ) {
			$name    = trim( (string) ( $f['name'] ?? '' ) );
			$email   = trim( (string) ( $f['email'] ?? '' ) );
			$items[] = array(
				'id'      => 'form|' . (int) $f['id'],
				'item'    => (int) $f['id'],
				'from'    => '' !== $name ? $name : $email,
				'email'   => $email,
				'subject' => (string) ( $f['subject'] ?? '' ),
				'date'    => (string) ( $f['date'] ?? '' ),
				'unseen'  => ! empty( $f['unread'] ),
				'files'   => false,
				'answered' => ! empty( $f['replied'] ),
				'ts'      => (int) strtotime( get_gmt_from_date( (string) ( $f['date'] ?? '' ) ) . ' UTC' ), // forms give site time
			);
		}
		return array(
			'items'  => $items,
			'total'  => (int) ( $res['total'] ?? 0 ),
			'failed' => false,
		);
	}
	$path  = mailweb_role_path( $s['folder'] );
	$rows  = array();
	$total = 0;
	$boxes = mailweb_view_boxes( $s['box'] );
	$mixed = count( $boxes ) > 1;
	// Mixed: each mailbox's newest page*per (at most $cap), read in API pages of up to 100, merged by date.
	// One mailbox: its own page.
	$cap  = 500;
	$need = $mixed ? min( $page * $per, $cap ) : $per;
	$size = $mixed ? min( 100, $need ) : $per;
	$asks = $mixed ? (int) ceil( $need / $size ) : 1;
	$okay = 0;
	$sent = 'sent' === $s['folder'];
	foreach ( $boxes as $key => $addr ) {
		if ( ! isset( mailweb_folders( $key )[ $path ] ) ) {
			continue; // this mailbox has no such folder (yet).
		}
		$ok  = false;
		$got = 0;
		$sum = 0;
		for ( $p = 1; $p <= $asks; $p++ ) {
			$qs = array(
				'page'    => $mixed ? $p : $page,
				'perPage' => $size,
				'sort'    => '-date',
			);
			if ( '' !== $s['q'] ) {
				list( $code, $j ) = mailweb_call( 'POST', mailweb_fpath( $key, $path ) . '/messages/search?' . http_build_query( $qs ), array( 'text' => $s['q'] ) );
			} else {
				list( $code, $j ) = mailweb_call( 'GET', mailweb_fpath( $key, $path ) . '/messages?' . http_build_query( $qs ) );
			}
			if ( 200 !== $code ) {
				break;
			}
			$ok   = true;
			$sum  = (int) ( $j['pagination']['total'] ?? 0 );
			$data = (array) ( $j['data'] ?? array() );
			foreach ( $data as $m ) {
				$rows[] = array(
					'id'      => $key . '|' . (int) $m['uid'],
					'box'     => $key,
					'uid'     => (int) $m['uid'],
					'from'    => $sent ? implode( ', ', array_map( fn( $a ) => mailweb_who( $a, true ), (array) ( $m['to'] ?? array() ) ) ) : mailweb_who( $m['from'] ?? array(), true ),
					'email'   => $sent ? '' : implode( '', mailweb_addresses( array( $m['from'] ?? array() ) ) ),
					'subject' => trim( (string) ( $m['subject'] ?? '' ) ),
					'date'    => (string) ( $m['date'] ?? '' ),
					'unseen'  => ! empty( $m['unseen'] ),
					'files'   => ! empty( $m['attachments'] ),
					'mailbox' => $addr,
					'answered' => in_array( '\\Answered', (array) ( $m['flags'] ?? array() ), true ),
					'forwarded' => in_array( '$Forwarded', (array) ( $m['flags'] ?? array() ), true ),
					'ts'      => (int) strtotime( (string) ( $m['date'] ?? '' ) ),
				);
			}
			$got += count( $data );
			if ( count( $data ) < $size || ( $sum && $got >= $sum ) ) {
				break; // this mailbox has no more.
			}
		}
		if ( ! $ok ) {
			continue;
		}
		++$okay;
		$total += $sum;
	}
	if ( $mixed ) {
		// Each mailbox is read to at most $cap, so only the newest $cap merged messages are in the right order:
		// the pager offers no more than that. Older mail: choose one mailbox.
		$total = min( $total, $cap );
		usort( $rows, fn( $a, $b ) => (int) strtotime( $b['date'] ) <=> (int) strtotime( $a['date'] ) ); // newest first; unreadable dates last.
		$rows = array_slice( $rows, ( $page - 1 ) * $per, $per );
	}
	return array(
		'items'  => $rows,
		'total'  => $total,
		'failed' => ! $okay && $boxes,
	);
}

/* ---------- Bulk actions and the page ---------- */

/**
 * Apply one action to several messages ("box|uid") or website form items ("form|id").
 *
 * @return bool All succeeded.
 */
function mailweb_bulk( $act, $ids, $role ) {
	$boxes = mailweb_all_boxes();
	$ok    = true;
	foreach ( $ids as $id ) {
		list( $key, $uid ) = array_pad( explode( '|', (string) $id, 2 ), 2, '' );
		$uid = absint( $uid );
		if ( 'form' === $key ) {
			if ( mailweb_forms() && in_array( $act, array( 'read', 'unread', 'trash' ), true ) ) {
				$ok = (bool) call_user_func( mailweb_forms()['mark'], $uid, $act ) && $ok;
			}
			continue;
		}
		if ( ! isset( $boxes[ $key ] ) || ! $uid ) {
			continue;
		}
		$path = mailweb_role_path( $role );
		if ( 'read' === $act || 'unread' === $act ) {
			list( $code ) = mailweb_call( 'PATCH', mailweb_fpath( $key, $path ) . '/messages/' . $uid, array( ( 'read' === $act ? 'addFlags' : 'removeFlags' ) => array( '\\Seen' ) ) );
		} else {
			$to = mailweb_role_folder( $key, array( 'archive' => 'archive', 'trash' => 'trash', 'spam' => 'junk', 'inbox' => 'inbox' )[ $act ] ?? 'inbox' );
			if ( $to === $path ) {
				continue;
			}
			list( $code ) = mailweb_call( 'POST', mailweb_fpath( $key, $path ) . '/messages/' . $uid . '/move', array( 'targetFolder' => $to ) );
		}
		$ok = $ok && $code >= 200 && $code < 300;
		delete_transient( 'mailweb_folders_' . $key );
	}
	if ( function_exists( 'mailweb_overview_flush' ) ) {
		mailweb_overview_flush();
	}
	return $ok;
}

/**
 * The Inbox view this request asks for that the page draws itself: 'compose', 'item' (a website form message),
 * 'message' (one mailbox message), or '' for the list (the shell screen).
 *
 * @param array $s State (mailweb_state()).
 * @return string
 */
function mailweb_inbox_view( $s ) {
	if ( isset( $_GET['compose'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return 'compose';
	}
	if ( MAILWEB_FORMS === $s['folder'] && $s['item'] ) {
		return 'item';
	}
	return $s['uid'] && 'all' !== $s['box'] ? 'message' : '';
}

function mailweb_inbox_page() {
	if ( ! current_user_can( MAILWEB_CAP ) ) {
		wp_die( esc_html__( 'Not allowed.', 'mailweb' ), 403 );
	}
	if ( mailweb_ui_page( 'inbox' ) ) {
		return; // the list (mailweb-ui.php).
	}
	$s    = mailweb_state();
	$view = mailweb_inbox_view( $s );
	if ( '' === $view ) {
		mailweb_ui_missing( __( 'MailWeb inbox', 'mailweb' ) );
		return;
	}
	echo '<style>#mailweb .awm-frame{width:100%;height:65vh;border:1px solid #c3c4c7;background:#fff;display:block}#mailweb .awm-actions .button{width:100%;text-align:center;margin:0 0 6px}#mailweb #titlewrap #title{padding:3px 8px;font-size:1.7em;height:1.7em;width:100%}#mailweb .awm-field{display:flex;gap:8px;align-items:center;margin:0 0 8px}#mailweb .awm-field label{flex:0 0 3.5em;color:#50575e}#mailweb .awm-field input{flex:1}</style>';
	echo '<div class="wrap" id="mailweb">';
	if ( 'compose' === $view ) {
		mailweb_compose_screen( $s, sanitize_key( wp_unslash( $_GET['compose'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- which view to show; mailweb_inbox_view() set compose
	} elseif ( 'item' === $view ) {
		mailweb_form_item_screen( $s );
	} else {
		mailweb_message_screen( $s );
	}
	echo '</div>';
}

/** Result notice after an action. */
function mailweb_notice() {
	$n = sanitize_key( $_GET['mailweb_done'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$t = array(
		'sent'     => array( 'success', __( 'Message sent. A copy is in Sent.', 'mailweb' ) ),
		'moved'    => array( 'success', __( 'Message moved.', 'mailweb' ) ),
		'trashed'  => array( 'success', __( 'Moved to Trash.', 'mailweb' ) ),
		'flagged'  => array( 'success', __( 'Done.', 'mailweb' ) ),
		'fail'     => array( 'error', __( 'That did not work: the mail server refused it. Nothing was changed.', 'mailweb' ) ),
		/* translators: %d: number of messages that may be sent per hour. */
		'limit'    => array( 'error', sprintf( __( 'Sending paused: %d messages were sent in the last hour. Try again later.', 'mailweb' ), MAILWEB_SEND_HOUR ) ),
		'missing'  => array( 'error', __( 'Add a recipient and a subject or message.', 'mailweb' ) ),
		'toolarge' => array( 'error', __( 'Attachments are limited to 10 MB in total.', 'mailweb' ) ),
	);
	if ( isset( $t[ $n ] ) ) {
		wp_admin_notice( esc_html( $t[ $n ][1] ), array( 'type' => $t[ $n ][0], 'dismissible' => true ) );
	}
}

/** A date as j M Y with the calendar icon. */
function mailweb_day( $date ) {
	$t = $date ? strtotime( (string) $date ) : false;
	return $t ? '<span class="dashicons dashicons-calendar-alt" aria-hidden="true" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom"></span> ' . esc_html( wp_date( 'j M Y', $t ) ) : '–';
}

/** One mailbox message: edit-screen layout, message left, actions right. */
function mailweb_message_screen( $s ) {
	$path = mailweb_role_path( $s['folder'] );
	$msg  = mailweb_get( $s['box'], $path, $s['uid'] );
	$view = 'all' === $s['view'] || isset( mailweb_all_boxes()[ $s['view'] ] ) ? $s['view'] : $s['box'];
	$back = add_query_arg( array( 'page' => 'mailweb', 'box' => $view, 'folder' => $s['folder'] ), admin_url( 'admin.php' ) );
	echo '<h1 class="wp-heading-inline">' . esc_html( mailweb_roles()[ $s['folder'] ][0] ?? __( 'Inbox', 'mailweb' ) ) . '</h1> <a class="page-title-action" href="' . esc_url( $back ) . '">' . esc_html__( 'Back to the list', 'mailweb' ) . '</a><hr class="wp-header-end">';
	mailweb_notice();
	if ( ! $msg ) {
		wp_admin_notice( esc_html__( 'This message could not be opened (moved or deleted in webmail?).', 'mailweb' ), array( 'type' => 'error' ) );
		return;
	}
	mailweb_mark_seen( $s['box'], $path, (int) $s['uid'], $msg );
	$m                    = $msg['m'];
	$images               = ! empty( $_GET['images'] ); // phpcs:ignore WordPress.Security.NonceVerification
	list( $doc, $remote ) = mailweb_message_doc( $s['box'], $path, (int) $s['uid'], $msg, $images );
	$u                    = array( 'box' => $s['box'], 'folder' => $path, 'uid' => $s['uid'], 'view' => $view );
	$c                    = fn( $mode ) => add_query_arg( array( 'page' => 'mailweb', 'box' => $s['box'], 'folder' => $s['folder'], 'uid' => $s['uid'], 'compose' => $mode, 'view' => $view ), admin_url( 'admin.php' ) );
	echo '<div id="poststuff"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content">';
	echo '<h2 style="font-size:1.5em;padding:0;margin:0 0 12px">' . esc_html( '' !== trim( (string) ( $m['subject'] ?? '' ) ) ? $m['subject'] : __( '(no subject)', 'mailweb' ) ) . '</h2>';
	echo '<div class="postbox"><div class="inside"><table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'From', 'mailweb' ) . '</th><td><strong>' . esc_html( mailweb_who( $m['from'] ?? array() ) ) . '</strong></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'To', 'mailweb' ) . '</th><td>' . esc_html( implode( ', ', array_map( 'mailweb_who', (array) ( $m['to'] ?? array() ) ) ) ) . '</td></tr>';
	if ( ! empty( $m['cc'] ) ) {
		echo '<tr><th scope="row">' . esc_html__( 'Cc', 'mailweb' ) . '</th><td>' . esc_html( implode( ', ', array_map( 'mailweb_who', (array) $m['cc'] ) ) ) . '</td></tr>';
	}
	echo '<tr><th scope="row">' . esc_html__( 'Date', 'mailweb' ) . '</th><td>' . mailweb_day( $m['date'] ?? '' ) . ( ! empty( $m['date'] ) ? ', ' . esc_html( wp_date( 'g:i a', strtotime( (string) $m['date'] ) ) ) : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</tbody></table></div></div>';
	if ( $remote && ! $images ) {
		wp_admin_notice( esc_html__( 'Pictures from the internet are hidden: they can tell the sender you opened the message.', 'mailweb' ) . ' <a href="' . esc_url( add_query_arg( 'images', 1 ) ) . '">' . esc_html__( 'Show pictures', 'mailweb' ) . '</a>', array( 'type' => 'info', 'additional_classes' => array( 'inline' ), 'attributes' => array( 'style' => 'margin:0 0 12px' ) ) );
	}
	echo '<iframe class="awm-frame" title="' . esc_attr__( 'Message', 'mailweb' ) . '" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="' . esc_attr( $doc ) . '"></iframe>';
	$files = array_filter( (array) ( $m['attachments'] ?? array() ), fn( $a ) => empty( $a['inline'] ) || empty( $a['contentId'] ) );
	if ( $files ) {
		echo '<div class="postbox" style="margin-top:16px"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Attachments', 'mailweb' ) . '</h2></div><div class="inside"><ul>';
		foreach ( $files as $a ) {
			echo '<li><span class="dashicons dashicons-paperclip" aria-hidden="true"></span> <a href="' . esc_url( mailweb_act_url( 'file', $u + array( 'att' => (string) $a['id'] ) ) ) . '">' . esc_html( (string) ( $a['filename'] ?? __( 'attachment', 'mailweb' ) ) ) . '</a> <span class="description">' . esc_html( size_format( (int) ( $a['sizeBytes'] ?? 0 ) ) ) . '</span></li>';
		}
		echo '</ul></div></div>';
	}
	echo '</div><div id="postbox-container-1" class="postbox-container">';
	echo '<div class="postbox awm-actions"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Reply', 'mailweb' ) . '</h2></div><div class="inside">';
	echo '<a class="button button-primary button-large" href="' . esc_url( $c( 'reply' ) ) . '">' . esc_html__( 'Reply', 'mailweb' ) . '</a><a class="button" href="' . esc_url( $c( 'all' ) ) . '">' . esc_html__( 'Reply all', 'mailweb' ) . '</a><a class="button" href="' . esc_url( $c( 'forward' ) ) . '">' . esc_html__( 'Forward', 'mailweb' ) . '</a>';
	/* translators: %s: mailbox address. */
	echo '<p class="description">' . esc_html( sprintf( __( 'Sent from %s.', 'mailweb' ), mailweb_all_boxes()[ $s['box'] ] ) ) . '</p></div></div>';
	echo '<div class="postbox awm-actions"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Message', 'mailweb' ) . '</h2></div><div class="inside">';
	if ( 'archive' !== $s['folder'] && 'trash' !== $s['folder'] ) {
		echo '<a class="button" href="' . esc_url( mailweb_act_url( 'archive', $u ) ) . '">' . esc_html__( 'Archive', 'mailweb' ) . '</a>';
	}
	if ( 'inbox' !== $s['folder'] ) {
		echo '<a class="button" href="' . esc_url( mailweb_act_url( 'inbox', $u ) ) . '">' . ( 'spam' === $s['folder'] ? esc_html__( 'Not spam', 'mailweb' ) : esc_html__( 'Move to Inbox', 'mailweb' ) ) . '</a>';
	}
	if ( 'spam' !== $s['folder'] && 'sent' !== $s['folder'] ) {
		echo '<a class="button" href="' . esc_url( mailweb_act_url( 'spam', $u ) ) . '">' . esc_html__( 'Spam', 'mailweb' ) . '</a>';
	}
	echo '<a class="button" href="' . esc_url( mailweb_act_url( 'unread', $u ) ) . '">' . esc_html__( 'Mark unread', 'mailweb' ) . '</a>';
	$others = array_diff_key( mailweb_folders( $s['box'] ), array( $path => 1 ) );
	if ( $others ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:4px;margin:4px 0 10px">';
		wp_nonce_field( 'mailweb_act' );
		echo '<input type="hidden" name="action" value="mailweb_act"><input type="hidden" name="do" value="move"><input type="hidden" name="box" value="' . esc_attr( $s['box'] ) . '"><input type="hidden" name="folder" value="' . esc_attr( $path ) . '"><input type="hidden" name="uid" value="' . (int) $s['uid'] . '"><input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
		echo '<label class="screen-reader-text" for="awm-move">' . esc_html__( 'Move to folder', 'mailweb' ) . '</label><select id="awm-move" name="to" style="flex:1">';
		foreach ( $others as $p => $f ) {
			echo '<option value="' . esc_attr( $p ) . '">' . esc_html( $f[0] ) . '</option>';
		}
		echo '</select><button class="button">' . esc_html__( 'Move', 'mailweb' ) . '</button></form>';
	}
	if ( 'trash' !== $s['folder'] ) {
		echo '<div id="delete-action"><a class="submitdelete deletion" href="' . esc_url( mailweb_act_url( 'trash', $u ) ) . '">' . esc_html__( 'Move to Trash', 'mailweb' ) . '</a></div>';
	}
	echo '</div></div></div></div></div>';
}

/** New message / reply / forward: laid out like Add New Post. */
function mailweb_compose_screen( $s, $mode ) {
	$mode  = in_array( $mode, array( 'new', 'reply', 'all', 'forward' ), true ) ? $mode : 'new';
	$boxes = mailweb_send_boxes();
	$from  = isset( $boxes[ $s['box'] ] ) ? $s['box'] : mailweb_default_from();
	$to    = $cc = array();
	$subj  = '';
	$quote = '';
	$ref   = '';
	$form  = 'new' !== $mode && MAILWEB_FORMS === $s['folder'] && $s['item'] && mailweb_forms() ? call_user_func( mailweb_forms()['get'], $s['item'] ) : null;
	if ( is_array( $form ) ) {
		$from  = mailweb_default_from();
		$to    = array_filter( array( (string) ( $form['email'] ?? '' ) ), 'is_email' );
		$subj  = mailweb_prefix( 'Re:', (string) ( $form['subject'] ?? '' ) );
		$quote = '<p></p><blockquote>' . wpautop( esc_html( (string) ( $form['text'] ?? '' ) ) ) . '</blockquote>';
	} elseif ( 'new' !== $mode && $s['uid'] && 'all' !== $s['box'] ) {
		$path = mailweb_role_path( $s['folder'] );
		$msg  = mailweb_get( $s['box'], $path, $s['uid'] );
		if ( $msg ) {
			$m   = $msg['m'];
			$own = mailweb_own();
			if ( 'forward' === $mode ) {
				$subj = mailweb_prefix( 'Fwd:', $m['subject'] ?? '' );
			} else {
				$subj = mailweb_prefix( 'Re:', $m['subject'] ?? '' );
				$to   = 'sent' === $s['folder'] ? mailweb_addresses( $m['to'] ?? array() ) : mailweb_addresses( array( $m['from'] ?? array() ) );
				if ( 'all' === $mode ) {
					$cc = array_values( array_diff( array_merge( mailweb_addresses( $m['to'] ?? array() ), mailweb_addresses( $m['cc'] ?? array() ) ), $own, $to ) );
				}
			}
			$ref   = $path . '|' . (int) $s['uid'];
			$body  = trim( (string) ( $msg['t']['html'] ?? '' ) );
			$body  = '' !== $body ? wp_kses_post( preg_replace( '#<(script|style|head|title)[^>]*>.*?</\1>#is', '', $body ) ) : wpautop( esc_html( (string) ( $msg['t']['text'] ?? '' ) ) );
			$when  = ! empty( $m['date'] ) ? wp_date( 'j M Y, g:i a', strtotime( (string) $m['date'] ) ) : '';
			$quote = '<p></p><p>' . esc_html(
				'forward' === $mode
				? __( '---------- Forwarded message ----------', 'mailweb' )
				/* translators: 1: date and time of the original message, 2: its sender. */
				: sprintf( __( 'On %1$s, %2$s wrote:', 'mailweb' ), $when, mailweb_who( $m['from'] ?? array() ) )
			) . '</p><blockquote>' . $body . '</blockquote>';
		}
	}
	if ( ! isset( $boxes[ $from ] ) ) {
		$from = mailweb_default_from(); // only from mailboxes allowed to send
	}
	$sig    = trim( (string) mailweb_profile( $boxes[ $from ] ?? '' )['signature'] );
	$start  = '<p></p>' . ( '' !== $sig ? '<div class="signature">' . wp_kses_post( $sig ) . '</div>' : '' ) . $quote;
	$title  = array( 'new' => __( 'New message', 'mailweb' ), 'reply' => __( 'Reply', 'mailweb' ), 'all' => __( 'Reply all', 'mailweb' ), 'forward' => __( 'Forward', 'mailweb' ) )[ $mode ];
	$cancel = $ref ? add_query_arg( array( 'page' => 'mailweb', 'box' => $s['box'], 'folder' => $s['folder'], 'uid' => $s['uid'], 'view' => $s['view'] ), admin_url( 'admin.php' ) ) : add_query_arg( array( 'page' => 'mailweb', 'box' => $s['box'], 'folder' => 'inbox' ), admin_url( 'admin.php' ) );
	echo '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1><hr class="wp-header-end">';
	mailweb_notice();
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'mailweb_act' );
	echo '<input type="hidden" name="action" value="mailweb_act"><input type="hidden" name="do" value="send"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '"><input type="hidden" name="ref" value="' . esc_attr( $ref ) . '"><input type="hidden" name="refbox" value="' . esc_attr( $s['box'] ) . '"><input type="hidden" name="item" value="' . (int) $s['item'] . '">';
	echo '<div id="poststuff"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content">';
	echo '<div class="awm-field"><label for="awm-to">' . esc_html__( 'To', 'mailweb' ) . '</label><input type="text" id="awm-to" name="to" class="regular-text" value="' . esc_attr( implode( ', ', $to ) ) . '" required autocomplete="email" placeholder="' . esc_attr__( 'name@example.com, …', 'mailweb' ) . '"></div>';
	$show_cc = (bool) $cc;
	echo '<p style="margin:-4px 0 8px 4.1em"' . ( $show_cc ? ' hidden' : '' ) . ' id="awm-cc-toggle"><a href="#" onclick="document.getElementById(\'awm-ccbcc\').hidden=false;this.parentNode.hidden=true;return false;">' . esc_html__( 'Add Cc / Bcc', 'mailweb' ) . '</a></p>';
	echo '<div id="awm-ccbcc"' . ( $show_cc ? '' : ' hidden' ) . '><div class="awm-field"><label for="awm-cc">' . esc_html__( 'Cc', 'mailweb' ) . '</label><input type="text" id="awm-cc" name="cc" value="' . esc_attr( implode( ', ', $cc ) ) . '" autocomplete="email"></div><div class="awm-field"><label for="awm-bcc">' . esc_html__( 'Bcc', 'mailweb' ) . '</label><input type="text" id="awm-bcc" name="bcc" value="" autocomplete="email"></div></div>';
	echo '<div id="titlediv"><div id="titlewrap"><label class="screen-reader-text" for="title">' . esc_html__( 'Subject', 'mailweb' ) . '</label><input type="text" name="subject" id="title" value="' . esc_attr( $subj ) . '" placeholder="' . esc_attr__( 'Subject', 'mailweb' ) . '" autocomplete="off"></div></div>';
	echo '<div id="postdivrich" class="postarea" style="margin-top:12px">';
	wp_editor( $start, 'awm_body', array( 'textarea_name' => 'body', 'media_buttons' => false, 'textarea_rows' => 18, 'editor_height' => 360 ) );
	$after = (string) apply_filters( 'mailweb_after_signature', '', 'inbox' );
	if ( '' !== $after ) {
		echo '<p class="description" style="margin:10px 0 4px">' . esc_html__( 'Added under your signature when sent:', 'mailweb' ) . '</p>' . wp_kses_post( $after );
	}
	echo '</div></div><div id="postbox-container-1" class="postbox-container">';
	echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Send', 'mailweb' ) . '</h2></div><div class="inside"><p><label for="awm-from"><strong>' . esc_html__( 'From', 'mailweb' ) . '</strong></label><br>';
	echo '<select id="awm-from" name="from" style="width:100%">';
	foreach ( $boxes as $k => $a ) {
		$p = function_exists( 'mailweb_profile' ) ? mailweb_profile( $a )['name'] : '';
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $from, false ) . '>' . esc_html( ( '' !== $p ? $p . ' – ' : '' ) . $a ) . '</option>';
	}
	echo '</select>' . ( $ref ? '<span class="description">' . esc_html__( 'Sent from a different mailbox than the one the message is in, the reply carries no thread link (most mail apps still group it by subject).', 'mailweb' ) . '</span>' : '' ) . '</p>';
	echo '<div id="major-publishing-actions" style="margin:12px -12px -12px;display:flex;justify-content:space-between;align-items:center"><div id="delete-action"><a class="submitdelete deletion" href="' . esc_url( $cancel ) . '">' . esc_html__( 'Discard', 'mailweb' ) . '</a></div><div id="publishing-action"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Send', 'mailweb' ) . '</button></div></div>';
	echo '</div></div>';
	echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Attachments', 'mailweb' ) . '</h2></div><div class="inside"><input type="file" id="awm-files" name="files[]" multiple><p class="description">' . esc_html__( 'Up to 10 MB in total.', 'mailweb' ) . ( 'forward' === $mode ? ' ' . esc_html__( 'The forwarded message\'s attachments are included.', 'mailweb' ) : '' ) . '</p></div></div>';
	echo '</div></div></div></form>';
	if ( function_exists( 'mailweb_profile' ) ) {
		// Switching From swaps in that mailbox's signature.
		$sigs = array();
		foreach ( $boxes as $k => $a ) {
			$sigs[ $k ] = wp_kses_post( (string) mailweb_profile( $a )['signature'] );
		}
		echo '<script>(function(){var sigs=' . wp_json_encode( $sigs ) . ',sel=document.getElementById("awm-from");if(!sel)return;sel.addEventListener("change",function(){var html=sigs[sel.value]||"",ed=window.tinymce&&tinymce.get("awm_body");if(ed&&!ed.isHidden()){var d=ed.getDoc().querySelector("div.signature");if(d){html?d.innerHTML=html:d.parentNode.removeChild(d);}else if(html){ed.dom.add(ed.getBody(),"div",{"class":"signature"},html);}ed.save();}else{var t=document.getElementById("awm_body");t.value=t.value.replace(/<div class="signature">[\\s\\S]*?<\\/div>/,html?\'<div class="signature">\'+html+"</div>":"");}});})();</script>';
	}
}

/** A website form message: same layout as a mail message. */
function mailweb_form_item_screen( $s ) {
	$id   = $s['item'];
	$src  = mailweb_forms();
	$back = add_query_arg( array( 'page' => 'mailweb', 'box' => $s['box'], 'folder' => MAILWEB_FORMS ), admin_url( 'admin.php' ) );
	echo '<h1 class="wp-heading-inline">' . esc_html( $src ? $src['label'] : '' ) . '</h1> <a class="page-title-action" href="' . esc_url( $back ) . '">' . esc_html__( 'Back to the list', 'mailweb' ) . '</a><hr class="wp-header-end">';
	mailweb_notice();
	$f = $src ? call_user_func( $src['get'], $id ) : null;
	if ( ! is_array( $f ) ) {
		echo '<p>' . esc_html__( 'Not found.', 'mailweb' ) . '</p>';
		return;
	}
	call_user_func( $src['mark'], $id, 'read' );
	echo '<div id="poststuff"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content">';
	echo '<h2 style="font-size:1.5em;padding:0;margin:0 0 12px">' . esc_html( (string) ( $f['subject'] ?? '' ) ) . '</h2>';
	echo '<div class="postbox"><div class="inside"><table class="form-table" role="presentation"><tbody>';
	foreach ( (array) ( $f['fields'] ?? array() ) as $k => $v ) {
		if ( '' !== trim( (string) $v ) ) {
			echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
	}
	echo '<tr><th scope="row">' . esc_html__( 'Date', 'mailweb' ) . '</th><td>' . mailweb_day( $f['date'] ?? '' ) . '</td></tr></tbody></table></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
	echo '<div class="postbox"><div class="inside">' . wp_kses_post( wpautop( esc_html( (string) ( $f['text'] ?? '' ) ) ) ) . '</div></div>';
	echo '</div><div id="postbox-container-1" class="postbox-container"><div class="postbox awm-actions"><div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Reply', 'mailweb' ) . '</h2></div><div class="inside">';
	if ( is_email( (string) ( $f['email'] ?? '' ) ) ) {
		echo '<a class="button button-primary button-large" href="' . esc_url( add_query_arg( array( 'page' => 'mailweb', 'box' => $s['box'], 'folder' => MAILWEB_FORMS, 'item' => $id, 'compose' => 'reply' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Reply', 'mailweb' ) . '</a>';
	}
	echo '<a class="button" href="' . esc_url( mailweb_act_url( 'formunread', array( 'item' => $id ) ) ) . '">' . esc_html__( 'Mark unread', 'mailweb' ) . '</a>';
	echo '<div id="delete-action"><a class="submitdelete deletion" href="' . esc_url( mailweb_act_url( 'formtrash', array( 'item' => $id ) ) ) . '">' . esc_html__( 'Move to Trash', 'mailweb' ) . '</a></div>';
	echo '</div></div></div></div></div>';
}


/* ---------- Reading ---------- */

/**
 * Mark a message read, as webmail does on open (the Mail API does not when its text is fetched), and drop the cached
 * unread counts.
 *
 * @param string $key  Mailbox key.
 * @param string $path Folder path.
 * @param int    $uid  Message.
 * @param array  $msg  From mailweb_get().
 */
function mailweb_mark_seen( $key, $path, $uid, $msg ) {
	if ( ! in_array( '\\Seen', (array) ( $msg['m']['flags'] ?? array() ), true ) ) {
		mailweb_call( 'PATCH', mailweb_fpath( $key, $path ) . '/messages/' . (int) $uid, array( 'addFlags' => array( '\\Seen' ) ) );
	}
	delete_transient( 'mailweb_folders_' . $key );
	if ( function_exists( 'mailweb_overview_flush' ) ) {
		mailweb_overview_flush();
	}
}

/**
 * The message as a safe document for the sandboxed frame, and whether it asks for pictures from the internet.
 *
 * @param string $key    Mailbox key.
 * @param string $path   Folder path.
 * @param int    $uid    Message.
 * @param array  $msg    From mailweb_get().
 * @param bool   $images Show pictures from the internet.
 * @return array [ document, remote pictures (bool) ].
 */
function mailweb_message_doc( $key, $path, $uid, $msg, $images ) {
	$inline = array();
	foreach ( (array) ( $msg['m']['attachments'] ?? array() ) as $a ) {
		if ( ! empty( $a['contentId'] ) && (int) ( $a['sizeBytes'] ?? 0 ) < 1048576 && preg_match( '#^image/(png|jpe?g|gif|webp)$#', (string) ( $a['contentType'] ?? '' ) ) ) {
			list( $code, $bin ) = mailweb_call( 'GET', mailweb_fpath( $key, $path ) . '/messages/' . (int) $uid . '/attachments/' . rawurlencode( (string) $a['id'] ), null, true );
			if ( 200 === $code ) {
				$inline[ trim( (string) $a['contentId'], '<>' ) ] = 'data:' . $a['contentType'] . ';base64,' . base64_encode( $bin ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			}
		}
	}
	$html   = trim( (string) ( $msg['t']['html'] ?? '' ) );
	$doc    = '' !== $html ? mailweb_safe_html( $html, $images, $inline ) : mailweb_text_html( (string) ( $msg['t']['text'] ?? '' ) );
	$remote = '' !== $html && (bool) preg_match( '#<img\b[^>]*src=["\']\s*(?:https?:)?//|url\(\s*[\'"]?\s*(?:https?:)?//#i', $html );
	return array( $doc, $remote );
}

/** One message: meta and text, cached per request. Fetching does not mark it read; mailweb_message_screen() does. */
function mailweb_get( $key, $folder, $uid ) {
	static $cache = array();
	$k = $key . '|' . $folder . '|' . $uid;
	if ( ! isset( $cache[ $k ] ) ) {
		list( $c1, $m ) = mailweb_call( 'GET', mailweb_fpath( $key, $folder ) . '/messages/' . (int) $uid );
		list( $c2, $t ) = 200 === $c1 ? mailweb_call( 'GET', mailweb_fpath( $key, $folder ) . '/messages/' . (int) $uid . '/text' ) : array( 0, null );
		$cache[ $k ]    = 200 === $c1 ? array( 'm' => (array) ( $m['data'] ?? $m ), 't' => (array) ( $t['data'] ?? $t ?? array() ) ) : null;
	}
	return $cache[ $k ];
}

/** Message HTML made safe for the frame: no scripts or active content; remote pictures only when allowed. */
function mailweb_safe_html( $html, $images, $inline ) {
	$html  = (string) $html;
	$style = '';
	if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $html, $mm ) ) {
		$style = implode( "\n", $mm[1] );
		$style = str_replace( array( '<!--', '-->' ), '', $style ); // old-style CSS wrappers; escaping them would break the first rule
		$style = str_replace( '<', '\\3C ', $style ); // cannot close the frame's <style> tag
		$style = preg_replace( '#@import[^;]*;#i', '', $style );
		$style = preg_replace( '#expression\s*\(#i', '(', $style );
		if ( ! $images ) {
			$style = preg_replace( '#url\(\s*[\'"]?\s*(?:https?:)?//[^)]*\)#i', 'none', $style );
		}
	}
	$html    = preg_replace( '#<(script|style|head|title|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html );
	$allowed = wp_kses_allowed_html( 'post' );
	foreach ( array( 'table', 'td', 'th', 'tr', 'tbody', 'thead', 'div', 'span', 'p', 'img', 'a', 'font', 'center' ) as $t ) {
		$allowed[ $t ] = array_merge( (array) ( $allowed[ $t ] ?? array() ), array( 'style' => true, 'class' => true, 'align' => true, 'valign' => true, 'bgcolor' => true, 'width' => true, 'height' => true, 'border' => true, 'cellpadding' => true, 'cellspacing' => true, 'color' => true, 'face' => true, 'size' => true ) );
	}
	$html = wp_kses( $html, $allowed, array( 'http', 'https', 'mailto', 'tel', 'cid', 'data' ) );
	// Inline (cid:) pictures from the message itself.
	$html = preg_replace_callback( '#src=(["\'])cid:([^"\']+)\1#i', fn( $m ) => isset( $inline[ $m[2] ] ) ? 'src="' . $inline[ $m[2] ] . '"' : 'src=""', $html );
	if ( ! $images ) {
		$html = preg_replace( '#<img\b([^>]*?)\ssrc=(["\'])\s*(?:https?:)?//[^"\']*\2#i', '<img$1 src="" data-blocked="1"', $html );
		$html = preg_replace( '#url\(\s*[\'"]?\s*(?:https?:)?//[^)]*\)#i', 'none', $html );
	}
	// The browser enforces this too, so escaped CSS, image-set() and the like load nothing remote. Inline pictures are data: URIs.
	$csp = $images ? "default-src 'none'; img-src https: http: data:; style-src 'unsafe-inline'; font-src https: data:" : "default-src 'none'; img-src data:; style-src 'unsafe-inline'; font-src data:";
	return '<!doctype html><html><head><meta http-equiv="Content-Security-Policy" content="' . $csp . '"><meta charset="utf-8"><base target="_blank"><style>body{font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1d2327;margin:12px;word-wrap:break-word}img[data-blocked]{display:none}blockquote{border-left:3px solid #dcdcde;margin:0 0 0 4px;padding-left:10px;color:#50575e}</style><style>' . $style . '</style></head><body>' . $html . '</body></html>';
}

/** Plain text as safe frame HTML (links clickable). */
function mailweb_text_html( $text ) {
	return '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:12px;white-space:pre-wrap;word-wrap:break-word}</style></head><body>' . make_clickable( esc_html( (string) $text ) ) . '</body></html>';
}

/** "Re: " / "Fwd: " once. */
function mailweb_prefix( $p, $subject ) {
	$subject = trim( (string) $subject );
	return preg_match( '/^' . preg_quote( $p, '/' ) . '/i', $subject ) ? $subject : $p . ' ' . ( '' !== $subject ? $subject : __( '(no subject)', 'mailweb' ) );
}

/**
 * The folders a message of a mailbox can be moved to: its own folders but the one it is in.
 *
 * @param string $key  Mailbox key.
 * @param string $from The folder path it is in.
 * @return array[] [ { value: path, label } ]
 */
function mailweb_move_choices( $key, $from ) {
	$out = array();
	foreach ( mailweb_folders( $key ) as $p => $f ) {
		if ( $p !== $from ) {
			$out[] = array(
				'value' => (string) $p,
				'label' => (string) $f[0],
			);
		}
	}
	return $out;
}

/**
 * Move messages ("box|uid") from a folder role to a folder of their own mailbox (Move to folder). A message whose
 * mailbox has no such folder, or that is in it already, is left where it is.
 *
 * @param array  $ids  Message ids.
 * @param string $role The folder role they are in.
 * @param string $to   The folder path to move them to.
 * @return bool Every message moved.
 */
function mailweb_move_to( $ids, $role, $to ) {
	$boxes = mailweb_all_boxes();
	$from  = mailweb_role_path( $role );
	$ok    = true;
	$moved = 0;
	foreach ( $ids as $id ) {
		list( $key, $uid ) = array_pad( explode( '|', (string) $id, 2 ), 2, '' );
		$uid               = absint( $uid );
		if ( ! isset( $boxes[ $key ] ) || ! $uid || $to === $from || ! isset( mailweb_folders( $key )[ $to ] ) ) {
			$ok = false;
			continue;
		}
		list( $code ) = mailweb_call( 'POST', mailweb_fpath( $key, $from ) . '/messages/' . $uid . '/move', array( 'targetFolder' => $to ) );
		$ok           = $ok && $code >= 200 && $code < 300;
		++$moved;
		delete_transient( 'mailweb_folders_' . $key );
	}
	if ( $moved && function_exists( 'mailweb_overview_flush' ) ) {
		mailweb_overview_flush();
	}
	return $ok && $moved > 0;
}

/* ---------- Website forms ---------- */

/** Unread website form messages (0 without a forms source). */
function mailweb_forms_unread() {
	return mailweb_forms() ? (int) call_user_func( mailweb_forms()['unread'] ) : 0;
}

/* ---------- Single actions ---------- */

add_action(
	'admin_post_mailweb_act',
	function () {
		if ( ! current_user_can( MAILWEB_CAP ) || ! check_admin_referer( 'mailweb_act' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mailweb' ), 403 );
		}
		$in    = wp_unslash( $_REQUEST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each value is validated below
		$do    = sanitize_key( $in['do'] ?? '' );
		$boxes = mailweb_all_boxes();
		$key   = sanitize_key( $in['box'] ?? '' );
		$key   = isset( $boxes[ $key ] ) ? $key : (string) array_key_first( $boxes );
		$f     = (string) ( $in['folder'] ?? 'INBOX' );
		$f     = preg_match( '/^INBOX(\.[\w .-]+)?$/', $f ) ? $f : 'INBOX';
		$uid   = absint( $in['uid'] ?? 0 );
		$item  = absint( $in['item'] ?? 0 );
		$view  = sanitize_key( $in['view'] ?? '' );
		$view  = 'all' === $view || isset( $boxes[ $view ] ) ? $view : $key;
		$back  = add_query_arg( array( 'page' => 'mailweb', 'box' => $view, 'folder' => mailweb_role_of( $f ) ), admin_url( 'admin.php' ) );
		$go    = function ( $url, $done ) {
			wp_safe_redirect( add_query_arg( 'mailweb_done', $done, $url ) );
			exit;
		};
		$flush = function () use ( $key ) {
			delete_transient( 'mailweb_folders_' . $key );
			if ( function_exists( 'mailweb_overview_flush' ) ) {
				mailweb_overview_flush();
			}
		};
		switch ( $do ) {
			case 'archive':
			case 'trash':
			case 'spam':
			case 'inbox':
			case 'move':
				$to = 'move' === $do ? (string) ( $in['to'] ?? '' ) : mailweb_role_folder( $key, array( 'archive' => 'archive', 'trash' => 'trash', 'spam' => 'junk', 'inbox' => 'inbox' )[ $do ] );
				if ( ! $uid || ! preg_match( '/^INBOX(\.[\w .-]+)?$/', $to ) || $to === $f ) {
					$go( $back, 'fail' );
				}
				list( $code ) = mailweb_call( 'POST', mailweb_fpath( $key, $f ) . '/messages/' . $uid . '/move', array( 'targetFolder' => $to ) );
				$flush();
				$go( $back, $code >= 200 && $code < 300 ? ( 'trash' === $do ? 'trashed' : 'moved' ) : 'fail' );
				break;
			case 'unread':
			case 'read':
				list( $code ) = mailweb_call( 'PATCH', mailweb_fpath( $key, $f ) . '/messages/' . $uid, array( ( 'read' === $do ? 'addFlags' : 'removeFlags' ) => array( '\\Seen' ) ) );
				$flush();
				$go( $back, $code >= 200 && $code < 300 ? 'flagged' : 'fail' );
				break;
			case 'file':
				$att            = (string) ( $in['att'] ?? '' );
				$msg            = mailweb_get( $key, $f, $uid );
				$meta           = $msg ? current( array_filter( (array) ( $msg['m']['attachments'] ?? array() ), fn( $a ) => (string) ( $a['id'] ?? '' ) === $att ) ) : null;
				list( $code, $bin ) = $meta ? mailweb_call( 'GET', mailweb_fpath( $key, $f ) . '/messages/' . $uid . '/attachments/' . rawurlencode( $att ), null, true ) : array( 0, '' );
				if ( 200 !== $code ) {
					$go( $back, 'fail' );
				}
				nocache_headers();
				header( 'Content-Type: application/octet-stream' );
				header( 'X-Content-Type-Options: nosniff' );
				header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( (string) ( $meta['filename'] ?? 'attachment' ) ) . '"' );
				header( 'Content-Length: ' . strlen( $bin ) );
				echo $bin; // phpcs:ignore WordPress.Security.EscapeOutput -- file download
				exit;
			case 'formread':
			case 'formunread':
			case 'formtrash':
				$what = substr( $do, 4 );
				$ok   = $item && mailweb_forms() && call_user_func( mailweb_forms()['mark'], $item, $what );
				$go( add_query_arg( array( 'page' => 'mailweb', 'folder' => MAILWEB_FORMS ), admin_url( 'admin.php' ) ), $ok ? ( 'trash' === $what ? 'trashed' : 'flagged' ) : 'fail' );
				break;
			case 'send':
				mailweb_send_from_form( $in, $key );
				break;
		}
		$go( $back, 'fail' );
	}
);

function mailweb_send_from_form( $in, $refbox ) {
	check_admin_referer( 'mailweb_act' ); // also checked by the caller; here too because this reads $_FILES
	$boxes = mailweb_send_boxes(); // only mailboxes allowed to send, whatever the form sends
	$from  = sanitize_key( $in['from'] ?? '' );
	$from  = isset( $boxes[ $from ] ) ? $from : mailweb_default_from();
	$mode  = sanitize_key( $in['mode'] ?? 'new' );
	$item  = absint( $in['item'] ?? 0 );
	$to    = mailweb_addresses( $in['to'] ?? '' );
	$cc    = mailweb_addresses( $in['cc'] ?? '' );
	$bcc   = mailweb_addresses( $in['bcc'] ?? '' );
	$subj  = sanitize_text_field( (string) ( $in['subject'] ?? '' ) );
	$html  = wpautop( wp_kses_post( (string) ( $in['body'] ?? '' ) ) ); // the editor saves line breaks as newlines
	$html  = preg_replace( '/<div class="signature"[^>]*>/', '<div class="signature" style="margin-top:28px">', $html, 1 ); // space between message and signature
	$back  = add_query_arg( array( 'page' => 'mailweb', 'box' => $from, 'folder' => 'inbox' ), admin_url( 'admin.php' ) );
	$retry = wp_get_referer() ? wp_get_referer() : $back;
	if ( ! $to || ( '' === $subj && '' === trim( wp_strip_all_tags( $html ) ) ) ) {
		wp_safe_redirect( add_query_arg( 'mailweb_done', 'missing', $retry ) );
		exit;
	}
	$log = array_values( array_filter( (array) get_option( 'mailweb_send_log', array() ), fn( $t ) => (int) $t > time() - HOUR_IN_SECONDS ) );
	if ( count( $log ) >= MAILWEB_SEND_HOUR ) {
		wp_safe_redirect( add_query_arg( 'mailweb_done', 'limit', $retry ) );
		exit;
	}
	$html = mailweb_utm_body( $html, (string) apply_filters( 'mailweb_email_type', 'inbox', array() ), true ); // the site's own links tagged (utm.php)
	$req = array(
		'to'          => $to,
		'subject'     => '' !== $subj ? $subj : __( '(no subject)', 'mailweb' ),
		'html'        => '<div style="font:14px/1.5 Arial,Helvetica,sans-serif">' . mailweb_add_after_signature( $html, 'inbox' ) . '</div>',
		'text'        => trim( html_entity_decode( wp_strip_all_tags( preg_replace( '#<br\s*/?>|</p>|</div>|</li>#i', "\n", preg_replace( '#<blockquote[^>]*>(.*?)</blockquote>#is', "\n> $1\n", $html ) ) ), ENT_QUOTES ) ),
		'displayName' => '' !== mailweb_profile( $boxes[ $from ] )['name'] ? mailweb_profile( $boxes[ $from ] )['name'] : get_bloginfo( 'name' ),
	);
	if ( $cc ) {
		$req['cc'] = $cc;
	}
	if ( $bcc ) {
		$req['bcc'] = $bcc;
	}
	$ref  = explode( '|', (string) ( $in['ref'] ?? '' ) );
	$orig = null; // the message replied to or forwarded: [mailbox key, folder, uid]
	if ( 2 === count( $ref ) && preg_match( '/^INBOX(\.[\w .-]+)?$/', $ref[0] ) && absint( $ref[1] ) && isset( $boxes[ $refbox ] ) ) {
		$orig = array( $refbox, $ref[0], absint( $ref[1] ) );
		if ( $refbox === $from ) { // the API threads only within one mailbox
			$req[ 'forward' === $mode ? 'forwardOf' : 'inReplyTo' ] = array( 'uid' => $orig[2], 'folder' => $orig[1] );
		}
	}
	// Attachments: uploaded files, and a forwarded message's own. The uploads' sizes are checked before any is read.
	$atts  = array();
	$total = 0;
	$sizes = isset( $_FILES['files']['size'] ) && is_array( $_FILES['files']['size'] ) ? array_map( 'absint', $_FILES['files']['size'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( array_sum( $sizes ) > MAILWEB_MAX_ATTACH ) {
		wp_safe_redirect( add_query_arg( 'mailweb_done', 'toolarge', $retry ) );
		exit;
	}
	if ( ! empty( $_FILES['files']['tmp_name'] ) && is_array( $_FILES['files']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( $_FILES['files']['tmp_name'] as $i => $tmp ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! $tmp || ! is_uploaded_file( $tmp ) || UPLOAD_ERR_OK !== (int) $_FILES['files']['error'][ $i ] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				continue;
			}
			$size = (int) filesize( $tmp );
			if ( $total + $size > MAILWEB_MAX_ATTACH ) {
				wp_safe_redirect( add_query_arg( 'mailweb_done', 'toolarge', $retry ) );
				exit;
			}
			$bin    = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$total += strlen( $bin );
			$name   = sanitize_file_name( (string) $_FILES['files']['name'][ $i ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$type   = wp_check_filetype( $name )['type'];
			$atts[] = array( 'filename' => $name, 'content' => base64_encode( $bin ), 'contentType' => $type ? $type : 'application/octet-stream' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}
	}
	if ( 'forward' === $mode && $orig ) {
		$msg = mailweb_get( $orig[0], $orig[1], $orig[2] );
		foreach ( (array) ( $msg['m']['attachments'] ?? array() ) as $a ) {
			if ( $total + (int) ( $a['sizeBytes'] ?? 0 ) > MAILWEB_MAX_ATTACH ) {
				continue;
			}
			list( $code, $bin ) = mailweb_call( 'GET', mailweb_fpath( $orig[0], $orig[1] ) . '/messages/' . (int) $orig[2] . '/attachments/' . rawurlencode( (string) $a['id'] ), null, true );
			if ( 200 === $code ) {
				$total += strlen( $bin );
				$one    = array( 'filename' => sanitize_file_name( (string) ( $a['filename'] ?? 'attachment' ) ), 'content' => base64_encode( $bin ), 'contentType' => (string) ( $a['contentType'] ?? 'application/octet-stream' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				if ( ! empty( $a['contentId'] ) ) {
					$one['cid'] = trim( (string) $a['contentId'], '<>' );
				}
				$atts[] = $one;
			}
		}
	}
	if ( $total > MAILWEB_MAX_ATTACH ) {
		wp_safe_redirect( add_query_arg( 'mailweb_done', 'toolarge', $retry ) );
		exit;
	}
	if ( $atts ) {
		$req['attachments'] = $atts;
	}
	list( $code ) = mailweb_call( 'POST', '/api/v1/mailboxes/' . rawurlencode( mailweb_box_id( $from ) ) . '/send', $req );
	if ( $code < 200 || $code >= 300 ) {
		wp_safe_redirect( add_query_arg( 'mailweb_done', 'fail', $retry ) );
		exit;
	}
	if ( $orig ) {
		// A replied or forwarded message has been read and is flagged Answered / Forwarded, as webmail would. Adding a
		// flag the Mail API already set does no harm.
		$flags = array( '\\Seen', 'forward' === $mode ? '$Forwarded' : '\\Answered' );
		mailweb_call( 'PATCH', mailweb_fpath( $orig[0], $orig[1] ) . '/messages/' . $orig[2], array( 'addFlags' => $flags ) );
		delete_transient( 'mailweb_folders_' . $orig[0] );
	}
	if ( function_exists( 'mailweb_crm_note_reply' ) ) {
		mailweb_crm_note_reply( $boxes[ $from ], array_merge( $to, $cc ), $req['subject'], $req['html'] ); // "Reply sent" on their FluentCRM contact
	}
	$log[] = time();
	update_option( 'mailweb_send_log', $log, false ); // times only: no addresses, subjects or text
	if ( $item && mailweb_forms() && isset( mailweb_forms()['replied'] ) ) {
		call_user_func( mailweb_forms()['replied'], $item );
	}
	delete_transient( 'mailweb_folders_' . $from );
	$dest = add_query_arg( array( 'page' => 'mailweb', 'box' => 'all', 'folder' => $orig ? mailweb_role_of( $orig[1] ) : 'sent' ), admin_url( 'admin.php' ) );
	wp_safe_redirect( add_query_arg( 'mailweb_done', 'sent', $dest ) );
	exit;
}
