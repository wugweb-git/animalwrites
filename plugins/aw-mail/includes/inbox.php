<?php
/**
 * Mail: one inbox in WordPress (owner, 18 Oct 2026: "Real inbox in WP", replacing the Mail list and Contact messages).
 *
 * Everything is read from and written to the Hostinger mailboxes through the Mail API, so webmail and WordPress always
 * show the same mail, flags and folders. Nothing from the mailboxes is copied into WordPress.
 *
 * - Mailboxes: every mailbox the token sees (vedanshu@ with its alias contact@, support@, noreply@).
 * - Folders: Inbox, Sent, Archive, Spam, Trash (and any others), with unread counts; plus "Website forms", the contact
 *   form messages kept in WordPress (aw_message), so all incoming messages are in one place.
 * - List: 25 a page, newest first, unread in bold, search (sender, subject, body).
 * - Read: the message in a sandboxed frame (no scripts; remote pictures off until "Show pictures"), attachments to
 *   download. Opening a message marks it read in webmail too.
 * - Reply, Reply all, Forward, New message: sent from the mailbox through the Mail API (inReplyTo / forwardOf keep the
 *   thread and set Answered / Forwarded); a copy lands in the mailbox's Sent. The mailbox's signature is added.
 * - Archive, Move, Spam / Not spam, Delete (to Trash only; nothing is ever deleted for good), Mark read / unread.
 * - Who: administrators and editors. Every action checks a nonce. Sending: at most 30 an hour. No message text is
 *   logged anywhere.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_MAIL_CAP       = 'edit_others_posts'; // administrators and editors
const AW_MAIL_PER_PAGE  = 25;
const AW_MAIL_SEND_HOUR = 30;
const AW_MAIL_FORMS     = 'forms'; // the "Website forms" folder (contact form messages in WordPress)

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
function aw_mail_call( $method, $path, $body = null, $raw = false ) {
	$token = function_exists( 'aw_mail_setting' ) ? aw_mail_setting( 'api_token' ) : '';
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
function aw_mail_fpath( $key, $folder ) {
	return '/api/v1/mailboxes/' . rawurlencode( aw_mail_box_id( $key ) ) . '/folders/' . rawurlencode( $folder );
}

/** A mailbox's folders (path => [name, unread, total]), cached for a minute. */
function aw_mail_folders( $key, $fresh = false ) {
	$c = get_transient( 'aw_mail_folders_' . $key );
	if ( is_array( $c ) && ! $fresh ) {
		return $c;
	}
	list( $code, $j ) = aw_mail_call( 'GET', '/api/v1/mailboxes/' . rawurlencode( aw_mail_box_id( $key ) ) . '/folders' );
	$out = array();
	foreach ( 200 === $code ? (array) ( $j['data'] ?? $j ) : array() as $f ) {
		$p = (string) ( $f['path'] ?? '' );
		if ( '' === $p || 'INBOX.Drafts' === $p ) {
			continue; // drafts are written in webmail
		}
		$out[ $p ] = array( aw_mail_folder_label( $p, (string) ( $f['specialUse'] ?? '' ) ), (int) ( $f['unreadCount'] ?? 0 ), (int) ( $f['messageCount'] ?? 0 ) );
	}
	$order = array( 'INBOX' => 0, 'INBOX.Sent' => 1, 'INBOX.Archive' => 2, 'INBOX.Junk' => 3, 'INBOX.Trash' => 4 );
	uksort( $out, fn( $a, $b ) => ( $order[ $a ] ?? 9 ) <=> ( $order[ $b ] ?? 9 ) ?: strcmp( $a, $b ) );
	if ( $out ) {
		set_transient( 'aw_mail_folders_' . $key, $out, MINUTE_IN_SECONDS );
	}
	return $out;
}

function aw_mail_folder_label( $path, $special = '' ) {
	$names = array( 'INBOX' => 'Inbox', 'INBOX.Sent' => 'Sent', 'INBOX.Archive' => 'Archive', 'INBOX.Junk' => 'Spam', 'INBOX.Trash' => 'Trash' );
	$by    = array( '\\Sent' => 'Sent', '\\Junk' => 'Spam', '\\Trash' => 'Trash', '\\Archive' => 'Archive' );
	return $names[ $path ] ?? ( $by[ $special ] ?? preg_replace( '/^INBOX\./', '', $path ) );
}

/** The folder with a role (Trash, Junk, Archive) in a mailbox; Archive is made when missing. */
function aw_mail_role_folder( $key, $role ) {
	$want = array( 'trash' => 'INBOX.Trash', 'junk' => 'INBOX.Junk', 'archive' => 'INBOX.Archive', 'inbox' => 'INBOX' )[ $role ] ?? 'INBOX';
	$have = aw_mail_folders( $key );
	if ( 'INBOX.Archive' === $want && ! isset( $have[ $want ] ) ) {
		aw_mail_call( 'POST', '/api/v1/mailboxes/' . rawurlencode( aw_mail_box_id( $key ) ) . '/folders', array( 'name' => 'Archive' ) ); // makes INBOX.Archive
		delete_transient( 'aw_mail_folders_' . $key );
	}
	return $want;
}

/** "Name <address>" of an API address object (or list). */
function aw_mail_who( $a, $name_only = false ) {
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

/** Plain addresses of an API address list. */
function aw_mail_addr_list( $list ) {
	$out = array();
	foreach ( (array) $list as $a ) {
		$e = strtolower( trim( (string) ( is_array( $a ) ? ( $a['address'] ?? '' ) : $a ) ) );
		if ( is_email( $e ) ) {
			$out[] = $e;
		}
	}
	return array_values( array_unique( $out ) );
}

/** Our own addresses (mailboxes and aliases). */
function aw_mail_own() {
	return array_merge( array_values( aw_mail_all_boxes() ), array_keys( aw_mail_aliases() ) );
}

/* ---------- Folders by role (so "All mailboxes" can mix them) ---------- */

/** Folder roles shown above the list, in order: role => [label, path]. */
function aw_mail_roles() {
	return array(
		'inbox'   => array( 'Inbox', 'INBOX' ),
		'sent'    => array( 'Sent', 'INBOX.Sent' ),
		'archive' => array( 'Archive', 'INBOX.Archive' ),
		'spam'    => array( 'Spam', 'INBOX.Junk' ),
		'trash'   => array( 'Trash', 'INBOX.Trash' ),
	);
}

function aw_mail_role_path( $role ) {
	return aw_mail_roles()[ $role ][1] ?? 'INBOX';
}

function aw_mail_role_of( $path ) {
	foreach ( aw_mail_roles() as $r => $v ) {
		if ( $v[1] === $path ) {
			return $r;
		}
	}
	return 'inbox';
}

/* ---------- Screen state ---------- */

/** Current view: box (a mailbox key or 'all'), folder role, page, search, message UID or form item. */
function aw_mail_state() {
	$boxes = aw_mail_all_boxes();
	$key   = sanitize_key( wp_unslash( $_GET['box'] ?? 'all' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$key   = isset( $boxes[ $key ] ) ? $key : 'all';
	$f     = sanitize_key( wp_unslash( $_GET['folder'] ?? 'inbox' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$f     = AW_MAIL_FORMS === $f || isset( aw_mail_roles()[ $f ] ) ? $f : 'inbox';
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

/** Mail screen URL. */
function aw_mail_url( $args = array() ) {
	$s = aw_mail_state();
	return add_query_arg( array_filter( array_merge( array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => $s['folder'] ), $args ), fn( $v ) => null !== $v && '' !== $v && 0 !== $v ), admin_url( 'admin.php' ) );
}

/** URL of a single action (admin-post, nonced). */
function aw_mail_act_url( $act, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'aw_mail_act', 'do' => $act ), $args ), admin_url( 'admin-post.php' ) ), 'aw_mail_act' );
}

/** Mailboxes in the current view. */
function aw_mail_view_boxes( $box ) {
	$all = aw_mail_all_boxes();
	return 'all' === $box ? $all : array( $box => $all[ $box ] );
}

/** Unread count of a role across the boxes in view. */
function aw_mail_role_unread( $role, $box ) {
	$n = 0;
	foreach ( array_keys( aw_mail_view_boxes( $box ) ) as $k ) {
		$n += (int) ( aw_mail_folders( $k )[ aw_mail_role_path( $role ) ][1] ?? 0 );
	}
	return $n;
}

/* ---------- The screen ---------- */

// Bulk actions from the list (WordPress list table form, GET to this page): done before any output, then back.
add_action(
	'load-toplevel_page_aw-mail',
	function () {
		$act = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '-1' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$act = '-1' === $act || '' === $act ? sanitize_key( wp_unslash( $_REQUEST['action2'] ?? '-1' ) ) : $act; // phpcs:ignore WordPress.Security.NonceVerification
		$ids = array_map( 'sanitize_text_field', (array) wp_unslash( $_REQUEST['message'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '-1' === $act || '' === $act || ! $ids ) {
			return;
		}
		check_admin_referer( 'bulk-messages' );
		if ( ! current_user_can( AW_MAIL_CAP ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$s    = aw_mail_state();
		$done = aw_mail_bulk( $act, $ids, $s['folder'] ) ? 'flagged' : 'fail';
		wp_safe_redirect( add_query_arg( array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => $s['folder'], 's' => $s['q'] ? $s['q'] : null, 'aw_done' => $done ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

/**
 * Apply one action to several messages ("box|uid") or website form items ("form|id").
 *
 * @return bool All succeeded.
 */
function aw_mail_bulk( $act, $ids, $role ) {
	$boxes = aw_mail_all_boxes();
	$ok    = true;
	foreach ( $ids as $id ) {
		list( $key, $uid ) = array_pad( explode( '|', (string) $id, 2 ), 2, '' );
		$uid = absint( $uid );
		if ( 'form' === $key ) {
			if ( 'aw_message' !== get_post_type( $uid ) ) {
				continue;
			}
			if ( 'read' === $act ) {
				update_post_meta( $uid, 'aw_read', current_time( 'mysql' ) );
			} elseif ( 'unread' === $act ) {
				delete_post_meta( $uid, 'aw_read' );
			} elseif ( 'trash' === $act ) {
				wp_trash_post( $uid );
			}
			continue;
		}
		if ( ! isset( $boxes[ $key ] ) || ! $uid ) {
			continue;
		}
		$path = aw_mail_role_path( $role );
		if ( 'read' === $act || 'unread' === $act ) {
			list( $code ) = aw_mail_call( 'PATCH', aw_mail_fpath( $key, $path ) . '/messages/' . $uid, array( ( 'read' === $act ? 'addFlags' : 'removeFlags' ) => array( '\\Seen' ) ) );
		} else {
			$to = aw_mail_role_folder( $key, array( 'archive' => 'archive', 'trash' => 'trash', 'spam' => 'junk', 'inbox' => 'inbox' )[ $act ] ?? 'inbox' );
			if ( $to === $path ) {
				continue;
			}
			list( $code ) = aw_mail_call( 'POST', aw_mail_fpath( $key, $path ) . '/messages/' . $uid . '/move', array( 'targetFolder' => $to ) );
		}
		$ok = $ok && $code >= 200 && $code < 300;
		delete_transient( 'aw_mail_folders_' . $key );
	}
	if ( function_exists( 'aw_mail_overview_flush' ) ) {
		aw_mail_overview_flush();
	}
	return $ok;
}

function aw_mail_inbox_page() {
	if ( ! current_user_can( AW_MAIL_CAP ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	$s   = aw_mail_state();
	$tab = sanitize_key( $_GET['tab'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<style>#aw-mail tr.unread td,#aw-mail tr.unread th{background-color:#fcf9e8}#aw-mail tr.unread .check-column{box-shadow:inset 4px 0 0 #2271b1}#aw-mail tr.unread .column-from,#aw-mail tr.unread .column-subject>a{font-weight:600}#aw-mail .column-date{width:9em}#aw-mail .column-mailbox{width:12em}#aw-mail .column-from{width:22%}#aw-mail .awm-box{float:right;margin-top:9px}#aw-mail .awm-frame{width:100%;height:65vh;border:1px solid #c3c4c7;background:#fff;display:block}#aw-mail .awm-meta th{text-align:left;padding:2px 12px 2px 0;color:#50575e;font-weight:400;vertical-align:top;width:4em}#aw-mail .awm-actions .button{width:100%;text-align:center;margin:0 0 6px}#aw-mail #titlewrap #title{padding:3px 8px;font-size:1.7em;height:1.7em;width:100%}#aw-mail .awm-field{display:flex;gap:8px;align-items:center;margin:0 0 8px}#aw-mail .awm-field label{flex:0 0 3.5em;color:#50575e}#aw-mail .awm-field input{flex:1}@media (max-width:767px){#aw-mail .awm-box{float:none;margin:8px 0}}</style>';
	echo '<div class="wrap" id="aw-mail">';
	if ( 'settings' === $tab && current_user_can( 'manage_options' ) ) {
		echo '<h1>Mail settings</h1>';
		aw_mail_notice();
		do_action( 'aw_mail_settings_tab' );
		echo '</div>';
		return;
	}
	if ( isset( $_GET['compose'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		aw_mail_compose_screen( $s, sanitize_key( $_GET['compose'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	} elseif ( AW_MAIL_FORMS === $s['folder'] && $s['item'] ) {
		aw_mail_form_item_screen( $s );
	} elseif ( $s['uid'] && 'all' !== $s['box'] ) {
		aw_mail_message_screen( $s );
	} else {
		aw_mail_list_screen( $s );
	}
	echo '</div>';
}

/** Result notice after an action. */
function aw_mail_notice() {
	$n = sanitize_key( $_GET['aw_done'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$t = array(
		'sent'     => array( 'success', 'Message sent. A copy is in Sent.' ),
		'moved'    => array( 'success', 'Message moved.' ),
		'trashed'  => array( 'success', 'Moved to Trash.' ),
		'flagged'  => array( 'success', 'Done.' ),
		'fail'     => array( 'error', 'That did not work: the mail server refused it. Nothing was changed.' ),
		'limit'    => array( 'error', 'Sending paused: ' . AW_MAIL_SEND_HOUR . ' messages were sent in the last hour. Try again later.' ),
		'missing'  => array( 'error', 'Add a recipient and a subject or message.' ),
		'toolarge' => array( 'error', 'Attachments are limited to 10 MB in total.' ),
	);
	if ( isset( $t[ $n ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $t[ $n ][0] ) . ' is-dismissible"><p>' . esc_html( $t[ $n ][1] ) . '</p></div>';
	}
}

/** A date as j M Y with the calendar icon. */
function aw_mail_day( $date ) {
	$t = $date ? strtotime( (string) $date ) : false;
	return $t ? '<span class="dashicons dashicons-calendar-alt" aria-hidden="true" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom"></span> ' . esc_html( wp_date( 'j M Y', $t ) ) : '–';
}

/** The list: title, mailbox picker, folder links, the WordPress list table. */
function aw_mail_list_screen( $s ) {
	require_once AW_MAIL_DIR . 'includes/list-table.php';
	$boxes = aw_mail_all_boxes();
	echo '<h1 class="wp-heading-inline">Mail</h1> <a class="page-title-action" href="' . esc_url( aw_mail_url( array( 'compose' => 'new', 'paged' => null, 's' => null ) ) ) . '">New message</a>';
	if ( current_user_can( 'manage_options' ) ) {
		echo ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=aw-mail&tab=settings' ) ) . '">Settings</a>';
	}
	// Mailbox picker (right).
	echo '<form class="awm-box" method="get"><input type="hidden" name="page" value="aw-mail"><input type="hidden" name="folder" value="' . esc_attr( AW_MAIL_FORMS === $s['folder'] ? 'inbox' : $s['folder'] ) . '"><label for="awm-box" class="screen-reader-text">Mailbox</label><select id="awm-box" name="box" onchange="this.form.submit()"><option value="all"' . selected( 'all', $s['box'], false ) . '>All mailboxes</option>';
	foreach ( $boxes as $k => $a ) {
		$al = aw_mail_aliases_of( $a );
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $s['box'], false ) . '>' . esc_html( $a . ( $al ? ' (+ ' . implode( ', ', $al ) . ')' : '' ) ) . '</option>';
	}
	echo '</select> <noscript><button class="button">Show</button></noscript></form>';
	echo '<hr class="wp-header-end">';
	aw_mail_notice();
	// Folder links, as WordPress lists have them.
	$links = array();
	foreach ( aw_mail_roles() as $r => $v ) {
		$n       = 'sent' === $r ? 0 : aw_mail_role_unread( $r, $s['box'] );
		$links[] = '<a href="' . esc_url( add_query_arg( array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => $r ), admin_url( 'admin.php' ) ) ) . '"' . ( $r === $s['folder'] ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $v[0] ) . ( $n ? ' <span class="count">(' . (int) $n . ')</span>' : '' ) . '</a>';
	}
	$fn      = aw_mail_forms_unread();
	$links[] = '<a href="' . esc_url( add_query_arg( array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => AW_MAIL_FORMS ), admin_url( 'admin.php' ) ) ) . '"' . ( AW_MAIL_FORMS === $s['folder'] ? ' class="current" aria-current="page"' : '' ) . '>Website forms' . ( $fn ? ' <span class="count">(' . (int) $fn . ')</span>' : '' ) . '</a>';
	echo '<ul class="subsubsub"><li>' . implode( ' |</li><li>', $links ) . '</li></ul>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
	$table = new AW_Mail_List_Table( $s );
	$table->prepare_items();
	echo '<form method="get"><input type="hidden" name="page" value="aw-mail"><input type="hidden" name="box" value="' . esc_attr( $s['box'] ) . '"><input type="hidden" name="folder" value="' . esc_attr( $s['folder'] ) . '">';
	if ( AW_MAIL_FORMS !== $s['folder'] ) {
		$table->search_box( 'Search mail', 'awm-search' );
	}
	$table->display();
	echo '</form><p class="description" style="clear:both">Read in step with <a href="' . esc_url( AW_MAIL_WEBMAIL ) . '" target="_blank" rel="noopener noreferrer">Hostinger webmail</a>: what you read, move or send here shows there too.</p>';
}

/** One mailbox message: edit-screen layout, message left, actions right. */
function aw_mail_message_screen( $s ) {
	$path = aw_mail_role_path( $s['folder'] );
	$msg  = aw_mail_get( $s['box'], $path, $s['uid'] );
	$view = 'all' === $s['view'] || isset( aw_mail_all_boxes()[ $s['view'] ] ) ? $s['view'] : $s['box'];
	$back = add_query_arg( array( 'page' => 'aw-mail', 'box' => $view, 'folder' => $s['folder'] ), admin_url( 'admin.php' ) );
	echo '<h1 class="wp-heading-inline">' . esc_html( aw_mail_roles()[ $s['folder'] ][0] ) . '</h1> <a class="page-title-action" href="' . esc_url( $back ) . '">Back to the list</a><hr class="wp-header-end">';
	aw_mail_notice();
	if ( ! $msg ) {
		echo '<div class="notice notice-error"><p>This message could not be opened (moved or deleted in webmail?).</p></div>';
		return;
	}
	delete_transient( 'aw_mail_folders_' . $s['box'] );
	if ( function_exists( 'aw_mail_overview_flush' ) ) {
		aw_mail_overview_flush();
	}
	$m      = $msg['m'];
	$images = ! empty( $_GET['images'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$inline = array();
	foreach ( (array) ( $m['attachments'] ?? array() ) as $a ) {
		if ( ! empty( $a['contentId'] ) && (int) ( $a['sizeBytes'] ?? 0 ) < 1048576 && preg_match( '#^image/(png|jpe?g|gif|webp)$#', (string) ( $a['contentType'] ?? '' ) ) ) {
			list( $code, $bin ) = aw_mail_call( 'GET', aw_mail_fpath( $s['box'], $path ) . '/messages/' . (int) $s['uid'] . '/attachments/' . rawurlencode( (string) $a['id'] ), null, true );
			if ( 200 === $code ) {
				$inline[ trim( (string) $a['contentId'], '<>' ) ] = 'data:' . $a['contentType'] . ';base64,' . base64_encode( $bin ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			}
		}
	}
	$html   = trim( (string) ( $msg['t']['html'] ?? '' ) );
	$doc    = '' !== $html ? aw_mail_safe_html( $html, $images, $inline ) : aw_mail_text_html( (string) ( $msg['t']['text'] ?? '' ) );
	$remote = '' !== $html && preg_match( '#<img\b[^>]*src=["\']https?:#i', $html );
	$u      = array( 'box' => $s['box'], 'folder' => $path, 'uid' => $s['uid'], 'view' => $view );
	$c      = fn( $mode ) => add_query_arg( array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => $s['folder'], 'uid' => $s['uid'], 'compose' => $mode, 'view' => $view ), admin_url( 'admin.php' ) );
	echo '<div id="poststuff"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content">';
	echo '<h2 style="font-size:1.5em;padding:0;margin:0 0 12px">' . esc_html( '' !== trim( (string) $m['subject'] ) ? $m['subject'] : '(no subject)' ) . '</h2>';
	echo '<div class="postbox"><div class="inside"><table class="awm-meta"><tbody>';
	echo '<tr><th scope="row">From</th><td><strong>' . esc_html( aw_mail_who( $m['from'] ?? array() ) ) . '</strong></td></tr>';
	echo '<tr><th scope="row">To</th><td>' . esc_html( implode( ', ', array_map( 'aw_mail_who', (array) ( $m['to'] ?? array() ) ) ) ) . '</td></tr>';
	if ( ! empty( $m['cc'] ) ) {
		echo '<tr><th scope="row">Cc</th><td>' . esc_html( implode( ', ', array_map( 'aw_mail_who', (array) $m['cc'] ) ) ) . '</td></tr>';
	}
	echo '<tr><th scope="row">Date</th><td>' . aw_mail_day( $m['date'] ?? '' ) . ( ! empty( $m['date'] ) ? ', ' . esc_html( wp_date( 'g:i a', strtotime( (string) $m['date'] ) ) ) : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</tbody></table></div></div>';
	if ( $remote && ! $images ) {
		echo '<div class="notice notice-info inline" style="margin:0 0 12px"><p>Pictures from the internet are hidden: they can tell the sender you opened the message. <a href="' . esc_url( add_query_arg( 'images', 1 ) ) . '">Show pictures</a></p></div>';
	}
	echo '<iframe class="awm-frame" title="Message" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="' . esc_attr( $doc ) . '"></iframe>';
	$files = array_filter( (array) ( $m['attachments'] ?? array() ), fn( $a ) => empty( $a['inline'] ) || empty( $a['contentId'] ) );
	if ( $files ) {
		echo '<div class="postbox" style="margin-top:16px"><div class="postbox-header"><h2 class="hndle">Attachments</h2></div><div class="inside"><ul>';
		foreach ( $files as $a ) {
			echo '<li><span class="dashicons dashicons-paperclip" aria-hidden="true"></span> <a href="' . esc_url( aw_mail_act_url( 'file', $u + array( 'att' => (string) $a['id'] ) ) ) . '">' . esc_html( (string) ( $a['filename'] ?? 'attachment' ) ) . '</a> <span class="description">' . esc_html( size_format( (int) ( $a['sizeBytes'] ?? 0 ) ) ) . '</span></li>';
		}
		echo '</ul></div></div>';
	}
	echo '</div><div id="postbox-container-1" class="postbox-container">';
	echo '<div class="postbox awm-actions"><div class="postbox-header"><h2 class="hndle">Reply</h2></div><div class="inside">';
	echo '<a class="button button-primary button-large" href="' . esc_url( $c( 'reply' ) ) . '">Reply</a><a class="button" href="' . esc_url( $c( 'all' ) ) . '">Reply all</a><a class="button" href="' . esc_url( $c( 'forward' ) ) . '">Forward</a>';
	echo '<p class="description">Sent from ' . esc_html( aw_mail_all_boxes()[ $s['box'] ] ) . '.</p></div></div>';
	echo '<div class="postbox awm-actions"><div class="postbox-header"><h2 class="hndle">Message</h2></div><div class="inside">';
	if ( 'archive' !== $s['folder'] && 'trash' !== $s['folder'] ) {
		echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'archive', $u ) ) . '">Archive</a>';
	}
	if ( 'inbox' !== $s['folder'] ) {
		echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'inbox', $u ) ) . '">' . ( 'spam' === $s['folder'] ? 'Not spam' : 'Move to Inbox' ) . '</a>';
	}
	if ( 'spam' !== $s['folder'] && 'sent' !== $s['folder'] ) {
		echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'spam', $u ) ) . '">Spam</a>';
	}
	echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'unread', $u ) ) . '">Mark unread</a>';
	$others = array_diff_key( aw_mail_folders( $s['box'] ), array( $path => 1 ) );
	if ( $others ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:4px;margin:4px 0 10px">';
		wp_nonce_field( 'aw_mail_act' );
		echo '<input type="hidden" name="action" value="aw_mail_act"><input type="hidden" name="do" value="move"><input type="hidden" name="box" value="' . esc_attr( $s['box'] ) . '"><input type="hidden" name="folder" value="' . esc_attr( $path ) . '"><input type="hidden" name="uid" value="' . (int) $s['uid'] . '"><input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
		echo '<label class="screen-reader-text" for="awm-move">Move to folder</label><select id="awm-move" name="to" style="flex:1">';
		foreach ( $others as $p => $f ) {
			echo '<option value="' . esc_attr( $p ) . '">' . esc_html( $f[0] ) . '</option>';
		}
		echo '</select><button class="button">Move</button></form>';
	}
	if ( 'trash' !== $s['folder'] ) {
		echo '<div id="delete-action"><a class="submitdelete deletion" href="' . esc_url( aw_mail_act_url( 'trash', $u ) ) . '">Move to Trash</a></div>';
	}
	echo '</div></div></div></div></div>';
}

/** New message / reply / forward: laid out like Add New Post. */
function aw_mail_compose_screen( $s, $mode ) {
	$mode  = in_array( $mode, array( 'new', 'reply', 'all', 'forward' ), true ) ? $mode : 'new';
	$boxes = aw_mail_all_boxes();
	$from  = 'all' === $s['box'] ? (string) array_key_first( $boxes ) : $s['box'];
	$to    = $cc = array();
	$subj  = '';
	$quote = '';
	$ref   = '';
	if ( 'new' !== $mode && AW_MAIL_FORMS === $s['folder'] && $s['item'] ) {
		$from  = (string) array_search( (string) ( aw_mail_aliases()['contact@animalwrites.in'] ?? 'vedanshu@animalwrites.in' ), $boxes, true );
		$to    = array( (string) get_post_meta( $s['item'], 'aw_m_email', true ) );
		$subj  = 'Re: ' . trim( preg_replace( '/^Contact form:\s*/', '', (string) get_the_title( $s['item'] ) ) );
		$quote = '<p></p><blockquote>' . wpautop( esc_html( (string) get_post_field( 'post_content', $s['item'] ) ) ) . '</blockquote>';
	} elseif ( 'new' !== $mode && $s['uid'] && 'all' !== $s['box'] ) {
		$path = aw_mail_role_path( $s['folder'] );
		$msg  = aw_mail_get( $s['box'], $path, $s['uid'] );
		if ( $msg ) {
			$m   = $msg['m'];
			$own = aw_mail_own();
			if ( 'forward' === $mode ) {
				$subj = aw_mail_prefix( 'Fwd:', $m['subject'] ?? '' );
			} else {
				$subj = aw_mail_prefix( 'Re:', $m['subject'] ?? '' );
				$to   = 'sent' === $s['folder'] ? aw_mail_addr_list( $m['to'] ?? array() ) : aw_mail_addr_list( array( $m['from'] ?? array() ) );
				if ( 'all' === $mode ) {
					$cc = array_values( array_diff( array_merge( aw_mail_addr_list( $m['to'] ?? array() ), aw_mail_addr_list( $m['cc'] ?? array() ) ), $own, $to ) );
				}
			}
			$ref   = $path . '|' . (int) $s['uid'];
			$body  = trim( (string) ( $msg['t']['html'] ?? '' ) );
			$body  = '' !== $body ? wp_kses_post( preg_replace( '#<(script|style|head|title)[^>]*>.*?</\1>#is', '', $body ) ) : wpautop( esc_html( (string) ( $msg['t']['text'] ?? '' ) ) );
			$when  = ! empty( $m['date'] ) ? wp_date( 'j M Y, g:i a', strtotime( (string) $m['date'] ) ) : '';
			$quote = '<p></p><p>' . esc_html( 'forward' === $mode ? '---------- Forwarded message ----------' : 'On ' . $when . ', ' . aw_mail_who( $m['from'] ?? array() ) . ' wrote:' ) . '</p><blockquote>' . $body . '</blockquote>';
		}
	}
	if ( 'new' !== $mode && isset( $boxes['support'] ) && 'vedanshu' === $from ) {
		$from = 'support'; // replies go from support@ (or contact@ once it is a mailbox), never from vedanshu@
	}
	$sig    = function_exists( 'aw_mail_profile' ) ? trim( (string) aw_mail_profile( $boxes[ $from ] ?? '' )['signature'] ) : '';
	$start  = '<p></p>' . ( '' !== $sig ? '<div class="signature">' . wp_kses_post( $sig ) . '</div>' : '' ) . $quote;
	$title  = array( 'new' => 'New message', 'reply' => 'Reply', 'all' => 'Reply all', 'forward' => 'Forward' )[ $mode ];
	$cancel = $ref ? add_query_arg( array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => $s['folder'], 'uid' => $s['uid'], 'view' => $s['view'] ), admin_url( 'admin.php' ) ) : add_query_arg( array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => 'inbox' ), admin_url( 'admin.php' ) );
	echo '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1><hr class="wp-header-end">';
	aw_mail_notice();
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'aw_mail_act' );
	echo '<input type="hidden" name="action" value="aw_mail_act"><input type="hidden" name="do" value="send"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '"><input type="hidden" name="ref" value="' . esc_attr( $ref ) . '"><input type="hidden" name="refbox" value="' . esc_attr( $s['box'] ) . '"><input type="hidden" name="item" value="' . (int) $s['item'] . '">';
	echo '<div id="poststuff"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content">';
	echo '<div class="awm-field"><label for="awm-to">To</label><input type="text" id="awm-to" name="to" class="regular-text" value="' . esc_attr( implode( ', ', $to ) ) . '" required autocomplete="email" placeholder="name@example.com, …"></div>';
	$show_cc = (bool) $cc;
	echo '<p style="margin:-4px 0 8px 4.1em"' . ( $show_cc ? ' hidden' : '' ) . ' id="awm-cc-toggle"><a href="#" onclick="document.getElementById(\'awm-ccbcc\').hidden=false;this.parentNode.hidden=true;return false;">Add Cc / Bcc</a></p>';
	echo '<div id="awm-ccbcc"' . ( $show_cc ? '' : ' hidden' ) . '><div class="awm-field"><label for="awm-cc">Cc</label><input type="text" id="awm-cc" name="cc" value="' . esc_attr( implode( ', ', $cc ) ) . '" autocomplete="email"></div><div class="awm-field"><label for="awm-bcc">Bcc</label><input type="text" id="awm-bcc" name="bcc" value="" autocomplete="email"></div></div>';
	echo '<div id="titlediv"><div id="titlewrap"><label class="screen-reader-text" for="title">Subject</label><input type="text" name="subject" id="title" value="' . esc_attr( $subj ) . '" placeholder="Subject" autocomplete="off"></div></div>';
	echo '<div id="postdivrich" class="postarea" style="margin-top:12px">';
	wp_editor( $start, 'awm_body', array( 'textarea_name' => 'body', 'media_buttons' => false, 'textarea_rows' => 18, 'editor_height' => 360 ) );
	if ( function_exists( 'aw_mail_campaign_strip_html' ) && '' !== aw_mail_campaign_strip_html() ) {
		echo '<p class="description" style="margin:10px 0 4px">Added under your signature when sent (the open campaign):</p>' . aw_mail_campaign_strip_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
	}
	echo '</div></div><div id="postbox-container-1" class="postbox-container">';
	echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">Send</h2></div><div class="inside"><p><label for="awm-from"><strong>From</strong></label><br>';
	echo '<select id="awm-from" name="from" style="width:100%">';
	foreach ( $boxes as $k => $a ) {
		$p = function_exists( 'aw_mail_profile' ) ? aw_mail_profile( $a )['name'] : '';
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $from, false ) . '>' . esc_html( ( '' !== $p ? $p . ' – ' : '' ) . $a ) . '</option>';
	}
	echo '</select>' . ( $ref ? '<span class="description">Sent from a different mailbox than the one the message is in, the reply carries no thread link (most mail apps still group it by subject).</span>' : '' ) . '</p>';
	echo '<div id="major-publishing-actions" style="margin:12px -12px -12px;display:flex;justify-content:space-between;align-items:center"><div id="delete-action"><a class="submitdelete deletion" href="' . esc_url( $cancel ) . '">Discard</a></div><div id="publishing-action"><button type="submit" class="button button-primary button-large">Send</button></div></div>';
	echo '</div></div>';
	echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">Attachments</h2></div><div class="inside"><input type="file" id="awm-files" name="files[]" multiple><p class="description">Up to 10 MB in total.' . ( 'forward' === $mode ? ' The forwarded message\'s attachments are included.' : '' ) . '</p></div></div>';
	echo '</div></div></div></form>';
	if ( function_exists( 'aw_mail_profile' ) ) {
		// Switching From swaps in that mailbox's signature.
		$sigs = array();
		foreach ( $boxes as $k => $a ) {
			$sigs[ $k ] = wp_kses_post( (string) aw_mail_profile( $a )['signature'] );
		}
		echo '<script>(function(){var sigs=' . wp_json_encode( $sigs ) . ',sel=document.getElementById("awm-from");if(!sel)return;sel.addEventListener("change",function(){var html=sigs[sel.value]||"",ed=window.tinymce&&tinymce.get("awm_body");if(ed&&!ed.isHidden()){var d=ed.getDoc().querySelector("div.signature");if(d){html?d.innerHTML=html:d.parentNode.removeChild(d);}else if(html){ed.dom.add(ed.getBody(),"div",{"class":"signature"},html);}ed.save();}else{var t=document.getElementById("awm_body");t.value=t.value.replace(/<div class="signature">[\\s\\S]*?<\\/div>/,html?\'<div class="signature">\'+html+"</div>":"");}});})();</script>';
	}
}

/** A website form message: same layout as a mail message. */
function aw_mail_form_item_screen( $s ) {
	$id = $s['item'];
	$back = add_query_arg( array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => AW_MAIL_FORMS ), admin_url( 'admin.php' ) );
	echo '<h1 class="wp-heading-inline">Website forms</h1> <a class="page-title-action" href="' . esc_url( $back ) . '">Back to the list</a><hr class="wp-header-end">';
	aw_mail_notice();
	if ( 'aw_message' !== get_post_type( $id ) ) {
		echo '<p>Not found.</p>';
		return;
	}
	update_post_meta( $id, 'aw_read', current_time( 'mysql' ) );
	$m = fn( $k ) => trim( (string) get_post_meta( $id, 'aw_m_' . $k, true ) );
	echo '<div id="poststuff"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content">';
	echo '<h2 style="font-size:1.5em;padding:0;margin:0 0 12px">' . esc_html( trim( preg_replace( '/^Contact form:\s*/', '', get_the_title( $id ) ) ) ) . '</h2>';
	echo '<div class="postbox"><div class="inside"><table class="awm-meta"><tbody>';
	foreach ( array( 'From' => trim( $m( 'name' ) . ' <' . $m( 'email' ) . '>' ), 'Organisation' => $m( 'org' ), 'Page' => $m( 'source' ), 'Newsletter' => $m( 'contact' ) ) as $k => $v ) {
		if ( '' !== $v && '<>' !== $v ) {
			echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
	}
	echo '<tr><th scope="row">Date</th><td>' . aw_mail_day( get_post_field( 'post_date', $id ) ) . '</td></tr></tbody></table></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="postbox"><div class="inside">' . wpautop( esc_html( (string) get_post_field( 'post_content', $id ) ) ) . '</div></div>';
	echo '</div><div id="postbox-container-1" class="postbox-container"><div class="postbox awm-actions"><div class="postbox-header"><h2 class="hndle">Reply</h2></div><div class="inside">';
	if ( is_email( $m( 'email' ) ) ) {
		echo '<a class="button button-primary button-large" href="' . esc_url( add_query_arg( array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => AW_MAIL_FORMS, 'item' => $id, 'compose' => 'reply' ), admin_url( 'admin.php' ) ) ) . '">Reply</a>';
	}
	echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'formunread', array( 'item' => $id ) ) ) . '">Mark unread</a>';
	echo '<div id="delete-action"><a class="submitdelete deletion" href="' . esc_url( aw_mail_act_url( 'formtrash', array( 'item' => $id ) ) ) . '">Move to Trash</a></div>';
	echo '</div></div></div></div></div>';
}


/* ---------- Reading ---------- */

/** One message: meta and text (text fetch marks it read), cached per request. */
function aw_mail_get( $key, $folder, $uid ) {
	static $cache = array();
	$k = $key . '|' . $folder . '|' . $uid;
	if ( ! isset( $cache[ $k ] ) ) {
		list( $c1, $m ) = aw_mail_call( 'GET', aw_mail_fpath( $key, $folder ) . '/messages/' . (int) $uid );
		list( $c2, $t ) = 200 === $c1 ? aw_mail_call( 'GET', aw_mail_fpath( $key, $folder ) . '/messages/' . (int) $uid . '/text' ) : array( 0, null );
		$cache[ $k ]    = 200 === $c1 ? array( 'm' => (array) ( $m['data'] ?? $m ), 't' => (array) ( $t['data'] ?? $t ?? array() ) ) : null;
	}
	return $cache[ $k ];
}

/** Message HTML made safe for the frame: no scripts or active content; remote pictures only when allowed. */
function aw_mail_safe_html( $html, $images, $inline ) {
	$html  = (string) $html;
	$style = '';
	if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $html, $mm ) ) {
		$style = implode( "\n", $mm[1] );
		$style = preg_replace( '#@import[^;]*;#i', '', $style );
		$style = preg_replace( '#expression\s*\(#i', '(', $style );
		if ( ! $images ) {
			$style = preg_replace( '#url\(\s*[\'"]?https?:[^)]*\)#i', 'none', $style );
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
		$html = preg_replace( '#<img\b([^>]*?)\ssrc=(["\'])https?:[^"\']*\2#i', '<img$1 src="" data-blocked="1"', $html );
		$html = preg_replace( '#url\(\s*[\'"]?https?:[^)]*\)#i', 'none', $html );
	}
	return '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1d2327;margin:12px;word-wrap:break-word}img[data-blocked]{display:none}blockquote{border-left:3px solid #dcdcde;margin:0 0 0 4px;padding-left:10px;color:#50575e}</style><style>' . $style . '</style></head><body>' . $html . '</body></html>';
}

/** Plain text as safe frame HTML (links clickable). */
function aw_mail_text_html( $text ) {
	return '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:12px;white-space:pre-wrap;word-wrap:break-word}</style></head><body>' . make_clickable( esc_html( (string) $text ) ) . '</body></html>';
}

/** "Re: " / "Fwd: " once. */
function aw_mail_prefix( $p, $subject ) {
	$subject = trim( (string) $subject );
	return preg_match( '/^' . preg_quote( $p, '/' ) . '/i', $subject ) ? $subject : $p . ' ' . ( '' !== $subject ? $subject : '(no subject)' );
}

/* ---------- Website forms ---------- */

function aw_mail_forms_query( $page = 1 ) {
	return new WP_Query(
		array(
			'post_type'      => 'aw_message',
			'post_status'    => array( 'private', 'publish', 'pending', 'draft' ),
			'posts_per_page' => AW_MAIL_PER_PAGE,
			'paged'          => $page,
			'meta_query'     => array( 'relation' => 'OR', array( 'key' => 'aw_m_about', 'value' => 'email', 'compare' => '!=' ), array( 'key' => 'aw_m_about', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}

function aw_mail_forms_unread() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = 'aw_m_about' LEFT JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = 'aw_read' WHERE p.post_type = 'aw_message' AND p.post_status NOT IN ('trash','auto-draft') AND ( a.meta_value IS NULL OR a.meta_value <> 'email' ) AND r.meta_id IS NULL" ); // phpcs:ignore
}

/* ---------- Single actions ---------- */

add_action(
	'admin_post_aw_mail_act',
	function () {
		if ( ! current_user_can( AW_MAIL_CAP ) || ! check_admin_referer( 'aw_mail_act' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$in    = wp_unslash( $_REQUEST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each value is validated below
		$do    = sanitize_key( $in['do'] ?? '' );
		$boxes = aw_mail_all_boxes();
		$key   = sanitize_key( $in['box'] ?? '' );
		$key   = isset( $boxes[ $key ] ) ? $key : (string) array_key_first( $boxes );
		$f     = (string) ( $in['folder'] ?? 'INBOX' );
		$f     = preg_match( '/^INBOX(\.[\w .-]+)?$/', $f ) ? $f : 'INBOX';
		$uid   = absint( $in['uid'] ?? 0 );
		$item  = absint( $in['item'] ?? 0 );
		$view  = sanitize_key( $in['view'] ?? '' );
		$view  = 'all' === $view || isset( $boxes[ $view ] ) ? $view : $key;
		$back  = add_query_arg( array( 'page' => 'aw-mail', 'box' => $view, 'folder' => aw_mail_role_of( $f ) ), admin_url( 'admin.php' ) );
		$go    = function ( $url, $done ) {
			wp_safe_redirect( add_query_arg( 'aw_done', $done, $url ) );
			exit;
		};
		$flush = function () use ( $key ) {
			delete_transient( 'aw_mail_folders_' . $key );
			if ( function_exists( 'aw_mail_overview_flush' ) ) {
				aw_mail_overview_flush();
			}
		};
		switch ( $do ) {
			case 'archive':
			case 'trash':
			case 'spam':
			case 'inbox':
			case 'move':
				$to = 'move' === $do ? (string) ( $in['to'] ?? '' ) : aw_mail_role_folder( $key, array( 'archive' => 'archive', 'trash' => 'trash', 'spam' => 'junk', 'inbox' => 'inbox' )[ $do ] );
				if ( ! $uid || ! preg_match( '/^INBOX(\.[\w .-]+)?$/', $to ) || $to === $f ) {
					$go( $back, 'fail' );
				}
				list( $code ) = aw_mail_call( 'POST', aw_mail_fpath( $key, $f ) . '/messages/' . $uid . '/move', array( 'targetFolder' => $to ) );
				$flush();
				$go( $back, $code >= 200 && $code < 300 ? ( 'trash' === $do ? 'trashed' : 'moved' ) : 'fail' );
				break;
			case 'unread':
			case 'read':
				list( $code ) = aw_mail_call( 'PATCH', aw_mail_fpath( $key, $f ) . '/messages/' . $uid, array( ( 'read' === $do ? 'addFlags' : 'removeFlags' ) => array( '\\Seen' ) ) );
				$flush();
				$go( $back, $code >= 200 && $code < 300 ? 'flagged' : 'fail' );
				break;
			case 'file':
				$att            = (string) ( $in['att'] ?? '' );
				$msg            = aw_mail_get( $key, $f, $uid );
				$meta           = $msg ? current( array_filter( (array) ( $msg['m']['attachments'] ?? array() ), fn( $a ) => (string) ( $a['id'] ?? '' ) === $att ) ) : null;
				list( $code, $bin ) = $meta ? aw_mail_call( 'GET', aw_mail_fpath( $key, $f ) . '/messages/' . $uid . '/attachments/' . rawurlencode( $att ), null, true ) : array( 0, '' );
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
				update_post_meta( $item, 'aw_read', current_time( 'mysql' ) );
				$go( add_query_arg( array( 'page' => 'aw-mail', 'folder' => AW_MAIL_FORMS ), admin_url( 'admin.php' ) ), 'flagged' );
				break;
			case 'formunread':
				delete_post_meta( $item, 'aw_read' );
				$go( add_query_arg( array( 'page' => 'aw-mail', 'folder' => AW_MAIL_FORMS ), admin_url( 'admin.php' ) ), 'flagged' );
				break;
			case 'formtrash':
				if ( 'aw_message' === get_post_type( $item ) ) {
					wp_trash_post( $item ); // WordPress Trash: restorable
				}
				$go( add_query_arg( array( 'page' => 'aw-mail', 'folder' => AW_MAIL_FORMS ), admin_url( 'admin.php' ) ), 'trashed' );
				break;
			case 'send':
				aw_mail_send_from_form( $in, $key );
				break;
		}
		$go( $back, 'fail' );
	}
);

/** Comma list of addresses → valid addresses. */
function aw_mail_parse_addrs( $s ) {
	return array_values( array_unique( array_filter( array_map( fn( $a ) => strtolower( trim( $a ) ), preg_split( '/[,;\s]+/', (string) $s ) ), 'is_email' ) ) );
}

function aw_mail_send_from_form( $in, $refbox ) {
	$boxes = aw_mail_all_boxes();
	$from  = sanitize_key( $in['from'] ?? '' );
	$from  = isset( $boxes[ $from ] ) ? $from : (string) array_key_first( $boxes );
	$mode  = sanitize_key( $in['mode'] ?? 'new' );
	$item  = absint( $in['item'] ?? 0 );
	$to    = aw_mail_parse_addrs( $in['to'] ?? '' );
	$cc    = aw_mail_parse_addrs( $in['cc'] ?? '' );
	$bcc   = aw_mail_parse_addrs( $in['bcc'] ?? '' );
	$subj  = sanitize_text_field( (string) ( $in['subject'] ?? '' ) );
	$html  = wpautop( wp_kses_post( (string) ( $in['body'] ?? '' ) ) ); // the editor saves line breaks as newlines
	$html  = preg_replace( '/<div class="signature"[^>]*>/', '<div class="signature" style="margin-top:28px">', $html, 1 ); // space between message and signature
	$back  = add_query_arg( array( 'page' => 'aw-mail', 'box' => $from, 'folder' => 'inbox' ), admin_url( 'admin.php' ) );
	$retry = wp_get_referer() ? wp_get_referer() : $back;
	if ( ! $to || ( '' === $subj && '' === trim( wp_strip_all_tags( $html ) ) ) ) {
		wp_safe_redirect( add_query_arg( 'aw_done', 'missing', $retry ) );
		exit;
	}
	$log = array_values( array_filter( (array) get_option( 'aw_mail_send_log', array() ), fn( $t ) => (int) $t > time() - HOUR_IN_SECONDS ) );
	if ( count( $log ) >= AW_MAIL_SEND_HOUR ) {
		wp_safe_redirect( add_query_arg( 'aw_done', 'limit', $retry ) );
		exit;
	}
	$req = array(
		'to'          => $to,
		'subject'     => '' !== $subj ? $subj : '(no subject)',
		'html'        => '<div style="font:14px/1.5 Arial,Helvetica,sans-serif">' . ( function_exists( 'aw_mail_add_campaign_strip' ) ? aw_mail_add_campaign_strip( $html ) : $html ) . '</div>', // open campaign strip under the signature
		'text'        => trim( html_entity_decode( wp_strip_all_tags( preg_replace( '#<br\s*/?>|</p>|</div>|</li>#i', "\n", preg_replace( '#<blockquote[^>]*>(.*?)</blockquote>#is', "\n> $1\n", $html ) ) ), ENT_QUOTES ) ),
		'displayName' => function_exists( 'aw_mail_profile' ) && '' !== aw_mail_profile( $boxes[ $from ] )['name'] ? aw_mail_profile( $boxes[ $from ] )['name'] : 'Animal Writes',
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
	// Attachments: uploaded files, and a forwarded message's own.
	$atts  = array();
	$total = 0;
	if ( ! empty( $_FILES['files']['tmp_name'] ) && is_array( $_FILES['files']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( $_FILES['files']['tmp_name'] as $i => $tmp ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! $tmp || ! is_uploaded_file( $tmp ) || UPLOAD_ERR_OK !== (int) $_FILES['files']['error'][ $i ] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				continue;
			}
			$bin    = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$total += strlen( $bin );
			$name   = sanitize_file_name( (string) $_FILES['files']['name'][ $i ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$type   = wp_check_filetype( $name )['type'];
			$atts[] = array( 'filename' => $name, 'content' => base64_encode( $bin ), 'contentType' => $type ? $type : 'application/octet-stream' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}
	}
	if ( 'forward' === $mode && $orig ) {
		$msg = aw_mail_get( $orig[0], $orig[1], $orig[2] );
		foreach ( (array) ( $msg['m']['attachments'] ?? array() ) as $a ) {
			if ( $total + (int) ( $a['sizeBytes'] ?? 0 ) > 10485760 ) {
				continue;
			}
			list( $code, $bin ) = aw_mail_call( 'GET', aw_mail_fpath( $orig[0], $orig[1] ) . '/messages/' . (int) $orig[2] . '/attachments/' . rawurlencode( (string) $a['id'] ), null, true );
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
	if ( $total > 10485760 ) {
		wp_safe_redirect( add_query_arg( 'aw_done', 'toolarge', $retry ) );
		exit;
	}
	if ( $atts ) {
		$req['attachments'] = $atts;
	}
	list( $code ) = aw_mail_call( 'POST', '/api/v1/mailboxes/' . rawurlencode( aw_mail_box_id( $from ) ) . '/send', $req );
	if ( $code < 200 || $code >= 300 ) {
		wp_safe_redirect( add_query_arg( 'aw_done', 'fail', $retry ) );
		exit;
	}
	if ( $orig && ! isset( $req['inReplyTo'] ) && ! isset( $req['forwardOf'] ) ) {
		// Sent from another mailbox: flag the original Answered / Forwarded ourselves, as webmail would.
		aw_mail_call( 'PATCH', aw_mail_fpath( $orig[0], $orig[1] ) . '/messages/' . $orig[2], array( 'addFlags' => array( 'forward' === $mode ? '$Forwarded' : '\\Answered' ) ) );
		delete_transient( 'aw_mail_folders_' . $orig[0] );
	}
	$log[] = time();
	update_option( 'aw_mail_send_log', $log, false ); // times only: no addresses, subjects or text
	if ( $item && 'aw_message' === get_post_type( $item ) ) {
		update_post_meta( $item, 'aw_m_replied', current_time( 'mysql' ) );
	}
	delete_transient( 'aw_mail_folders_' . $from );
	$dest = add_query_arg( array( 'page' => 'aw-mail', 'box' => 'all', 'folder' => $orig ? aw_mail_role_of( $orig[1] ) : 'sent' ), admin_url( 'admin.php' ) );
	wp_safe_redirect( add_query_arg( 'aw_done', 'sent', $dest ) );
	exit;
}
