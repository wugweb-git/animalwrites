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
		aw_mail_call( 'POST', '/api/v1/mailboxes/' . rawurlencode( aw_mail_box_id( $key ) ) . '/folders', array( 'path' => 'INBOX.Archive' ) );
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

/* ---------- Screen state ---------- */

/** Current mailbox key, folder, page, search, message UID (validated). */
function aw_mail_state() {
	$boxes = aw_mail_all_boxes();
	$key   = sanitize_key( wp_unslash( $_GET['box'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$key   = isset( $boxes[ $key ] ) ? $key : (string) array_key_first( $boxes );
	$f     = (string) wp_unslash( $_GET['folder'] ?? 'INBOX' ); // phpcs:ignore WordPress.Security.NonceVerification
	$f     = AW_MAIL_FORMS === $f || preg_match( '/^INBOX(\.[\w .-]+)?$/', $f ) ? $f : 'INBOX';
	return array(
		'box'    => $key,
		'folder' => $f,
		'page'   => max( 1, absint( $_GET['p'] ?? 1 ) ), // phpcs:ignore WordPress.Security.NonceVerification
		'q'      => trim( sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification
		'uid'    => absint( $_GET['uid'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification
		'item'   => absint( $_GET['item'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification
	);
}

/** Mail screen URL with the given state changes. */
function aw_mail_url( $args = array() ) {
	$s    = aw_mail_state();
	$base = array( 'page' => 'aw-mail', 'box' => $s['box'], 'folder' => $s['folder'] );
	return add_query_arg( array_filter( array_merge( $base, $args ), fn( $v ) => null !== $v && '' !== $v && 0 !== $v ), admin_url( 'admin.php' ) );
}

/** URL of an action (admin-post, nonced). */
function aw_mail_act_url( $act, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'aw_mail_act', 'do' => $act ), $args ), admin_url( 'admin-post.php' ) ), 'aw_mail_act' );
}

/* ---------- The screen ---------- */

function aw_mail_inbox_page() {
	if ( ! current_user_can( AW_MAIL_CAP ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	$s     = aw_mail_state();
	$tab   = sanitize_key( $_GET['tab'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$boxes = aw_mail_all_boxes();
	echo '<div class="wrap" id="aw-mail"><h1 class="wp-heading-inline">Mail</h1> ';
	echo '<a class="page-title-action" href="' . esc_url( aw_mail_url( array( 'compose' => 'new', 'folder' => 'INBOX' ) ) ) . '">New message</a>';
	if ( current_user_can( 'manage_options' ) ) {
		echo ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=aw-mail&tab=settings' ) ) . '">Settings</a>';
	}
	echo '<hr class="wp-header-end">';
	aw_mail_notice();
	if ( 'settings' === $tab && current_user_can( 'manage_options' ) ) {
		do_action( 'aw_mail_settings_tab' );
		echo '</div>';
		return;
	}
	echo '<style>#aw-mail .awm{display:flex;gap:20px;align-items:flex-start}#aw-mail .awm-nav{flex:0 0 220px}#aw-mail .awm-main{flex:1;min-width:0}#aw-mail .awm-nav ul{margin:0 0 16px}#aw-mail .awm-nav li{margin:0}#aw-mail .awm-nav a{display:flex;justify-content:space-between;padding:6px 10px;border-radius:4px;text-decoration:none}#aw-mail .awm-nav a.current{background:#2271b1;color:#fff}#aw-mail .awm-nav h3{margin:12px 0 4px;font-size:12px;text-transform:uppercase;color:#646970}#aw-mail tr.unread td{font-weight:600}#aw-mail td.awm-date{white-space:nowrap;width:8em}#aw-mail td.awm-from{width:26%}#aw-mail .awm-row a{color:inherit;text-decoration:none;display:block}#aw-mail .awm-frame{width:100%;height:70vh;border:1px solid #dcdcde;background:#fff}#aw-mail .awm-head th{width:5em;text-align:left;vertical-align:top;padding:2px 8px 2px 0;color:#646970}#aw-mail .awm-tools{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 12px}@media (max-width:767px){#aw-mail .awm{flex-direction:column}#aw-mail .awm-nav{flex:none;width:100%}}</style>';
	echo '<div class="awm"><nav class="awm-nav" aria-label="Mailboxes and folders">';
	foreach ( $boxes as $key => $addr ) {
		$alias = aw_mail_aliases_of( $addr );
		echo '<h3>' . esc_html( $addr ) . '</h3>' . ( $alias ? '<p class="description" style="margin:0 0 4px 10px">also ' . esc_html( implode( ', ', $alias ) ) . '</p>' : '' ) . '<ul>';
		foreach ( aw_mail_folders( $key ) as $path => $f ) {
			$cur = $key === $s['box'] && $path === $s['folder'];
			echo '<li><a' . ( $cur ? ' class="current" aria-current="page"' : '' ) . ' href="' . esc_url( add_query_arg( array( 'page' => 'aw-mail', 'box' => $key, 'folder' => $path ), admin_url( 'admin.php' ) ) ) . '"><span>' . esc_html( $f[0] ) . '</span>' . ( $f[1] ? '<strong>' . (int) $f[1] . '</strong>' : '' ) . '</a></li>';
		}
		echo '</ul>';
	}
	$forms_unread = aw_mail_forms_unread();
	echo '<h3>Website</h3><ul><li><a' . ( AW_MAIL_FORMS === $s['folder'] ? ' class="current" aria-current="page"' : '' ) . ' href="' . esc_url( add_query_arg( array( 'page' => 'aw-mail', 'folder' => AW_MAIL_FORMS ), admin_url( 'admin.php' ) ) ) . '"><span>Website forms</span>' . ( $forms_unread ? '<strong>' . (int) $forms_unread . '</strong>' : '' ) . '</a></li></ul>';
	echo '<p><a href="' . esc_url( AW_MAIL_WEBMAIL ) . '" target="_blank" rel="noopener noreferrer">Open webmail</a></p></nav><div class="awm-main">';
	if ( isset( $_GET['compose'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		aw_mail_compose_screen( $s, sanitize_key( $_GET['compose'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	} elseif ( AW_MAIL_FORMS === $s['folder'] ) {
		$s['item'] ? aw_mail_form_item_screen( $s['item'] ) : aw_mail_forms_list( $s );
	} elseif ( $s['uid'] ) {
		aw_mail_message_screen( $s );
	} else {
		aw_mail_list_screen( $s );
	}
	echo '</div></div></div>';
}

/** One-line result notice after an action. */
function aw_mail_notice() {
	$n = sanitize_key( $_GET['aw_done'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$t = array(
		'sent'     => array( 'success', 'Sent. A copy is in Sent.' ),
		'moved'    => array( 'success', 'Moved.' ),
		'trashed'  => array( 'success', 'Moved to Trash.' ),
		'flagged'  => array( 'success', 'Updated.' ),
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

/** Mailbox folder: the list. */
function aw_mail_list_screen( $s ) {
	$folders = aw_mail_folders( $s['box'] );
	$label   = $folders[ $s['folder'] ][0] ?? aw_mail_folder_label( $s['folder'] );
	$q       = array( 'page' => $s['page'], 'perPage' => AW_MAIL_PER_PAGE, 'sort' => '-date' );
	if ( '' !== $s['q'] ) {
		list( $code, $j ) = aw_mail_call( 'POST', aw_mail_fpath( $s['box'], $s['folder'] ) . '/messages/search?' . http_build_query( $q ), array( 'text' => $s['q'] ) );
	} else {
		list( $code, $j ) = aw_mail_call( 'GET', aw_mail_fpath( $s['box'], $s['folder'] ) . '/messages?' . http_build_query( $q ) );
	}
	echo '<h2 style="margin-top:0">' . esc_html( $label ) . ' <span class="description" style="font-weight:400">' . esc_html( aw_mail_all_boxes()[ $s['box'] ] ) . '</span></h2>';
	echo '<form method="get" style="margin:0 0 10px"><input type="hidden" name="page" value="aw-mail"><input type="hidden" name="box" value="' . esc_attr( $s['box'] ) . '"><input type="hidden" name="folder" value="' . esc_attr( $s['folder'] ) . '"><label class="screen-reader-text" for="awm-q">Search this folder</label><input type="search" id="awm-q" name="q" value="' . esc_attr( $s['q'] ) . '" placeholder="Search sender, subject, text"> <button class="button">Search</button>' . ( '' !== $s['q'] ? ' <a href="' . esc_url( aw_mail_url() ) . '">Clear</a>' : '' ) . '</form>';
	if ( 200 !== $code ) {
		echo '<div class="notice notice-error inline"><p>The mailbox could not be read just now. Try again in a minute, or <a href="' . esc_url( AW_MAIL_WEBMAIL ) . '" target="_blank" rel="noopener noreferrer">open webmail</a>.</p></div>';
		return;
	}
	$rows = (array) ( $j['data'] ?? array() );
	$pg   = (array) ( $j['pagination'] ?? array() );
	if ( ! $rows ) {
		echo '<p>' . ( '' !== $s['q'] ? 'Nothing matches.' : 'No messages.' ) . '</p>';
		return;
	}
	$sent = 'INBOX.Sent' === $s['folder'];
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . ( $sent ? 'To' : 'From' ) . '</th><th scope="col">Subject</th><th scope="col">Date</th></tr></thead><tbody>';
	foreach ( $rows as $m ) {
		$url    = aw_mail_url( array( 'uid' => (int) $m['uid'], 'p' => $s['page'] > 1 ? $s['page'] : null, 'q' => $s['q'] ) );
		$unseen = ! empty( $m['unseen'] );
		$who    = $sent ? implode( ', ', array_map( fn( $a ) => aw_mail_who( $a, true ), (array) ( $m['to'] ?? array() ) ) ) : aw_mail_who( $m['from'] ?? array(), true );
		$clip   = ! empty( $m['attachments'] ) ? ' <span class="dashicons dashicons-paperclip" aria-label="has attachments" style="font-size:16px;color:#646970"></span>' : '';
		echo '<tr class="awm-row' . ( $unseen ? ' unread' : '' ) . '"><td class="awm-from"><a href="' . esc_url( $url ) . '">' . ( $unseen ? '<span class="screen-reader-text">Unread: </span>' : '' ) . esc_html( '' !== $who ? $who : '(unknown)' ) . '</a></td><td><a href="' . esc_url( $url ) . '">' . esc_html( '' !== trim( (string) $m['subject'] ) ? $m['subject'] : '(no subject)' ) . $clip . '</a></td><td class="awm-date">' . aw_mail_day( $m['date'] ?? '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts
	}
	echo '</tbody></table>';
	$pages = (int) ( $pg['totalPages'] ?? 1 );
	if ( $pages > 1 ) {
		echo '<p class="tablenav-pages" style="margin-top:10px">Page ' . (int) $s['page'] . ' of ' . (int) $pages . ' ';
		if ( $s['page'] > 1 ) {
			echo '<a class="button" href="' . esc_url( aw_mail_url( array( 'p' => $s['page'] - 1, 'q' => $s['q'] ) ) ) . '">Newer</a> ';
		}
		if ( $s['page'] < $pages ) {
			echo '<a class="button" href="' . esc_url( aw_mail_url( array( 'p' => $s['page'] + 1, 'q' => $s['q'] ) ) ) . '">Older</a>';
		}
		echo '</p>';
	}
}

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

/** Mailbox message: read screen. */
function aw_mail_message_screen( $s ) {
	$msg = aw_mail_get( $s['box'], $s['folder'], $s['uid'] );
	if ( ! $msg ) {
		echo '<div class="notice notice-error inline"><p>This message could not be opened (moved or deleted in webmail?). <a href="' . esc_url( aw_mail_url() ) . '">Back to the folder</a>.</p></div>';
		return;
	}
	delete_transient( 'aw_mail_folders_' . $s['box'] );
	if ( function_exists( 'aw_mail_overview_flush' ) ) {
		aw_mail_overview_flush(); // read now: the badge follows
	}
	$m      = $msg['m'];
	$images = ! empty( $_GET['images'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$inline = array();
	foreach ( (array) ( $m['attachments'] ?? array() ) as $a ) {
		if ( ! empty( $a['contentId'] ) && (int) ( $a['sizeBytes'] ?? 0 ) < 1048576 ) {
			list( $code, $bin, $h ) = aw_mail_call( 'GET', aw_mail_fpath( $s['box'], $s['folder'] ) . '/messages/' . (int) $s['uid'] . '/attachments/' . rawurlencode( (string) $a['id'] ), null, true );
			if ( 200 === $code && preg_match( '#^image/(png|jpe?g|gif|webp)$#', (string) ( $a['contentType'] ?? '' ) ) ) {
				$inline[ trim( (string) $a['contentId'], '<>' ) ] = 'data:' . $a['contentType'] . ';base64,' . base64_encode( $bin ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			}
		}
	}
	$html   = trim( (string) ( $msg['t']['html'] ?? '' ) );
	$doc    = '' !== $html ? aw_mail_safe_html( $html, $images, $inline ) : aw_mail_text_html( (string) ( $msg['t']['text'] ?? '' ) );
	$remote = '' !== $html && preg_match( '#<img\b[^>]*src=["\']https?:#i', $html );
	$back   = aw_mail_url( array( 'p' => $s['page'] > 1 ? $s['page'] : null, 'q' => $s['q'] ) );
	$u      = array( 'box' => $s['box'], 'folder' => $s['folder'], 'uid' => $s['uid'] );
	$in     = $s['folder'];
	echo '<p><a href="' . esc_url( $back ) . '">&larr; ' . esc_html( aw_mail_folder_label( $in ) ) . '</a></p>';
	echo '<h2 style="margin:0 0 8px">' . esc_html( '' !== trim( (string) $m['subject'] ) ? $m['subject'] : '(no subject)' ) . '</h2>';
	echo '<table class="awm-head"><tbody>';
	echo '<tr><th scope="row">From</th><td>' . esc_html( aw_mail_who( $m['from'] ?? array() ) ) . '</td></tr>';
	echo '<tr><th scope="row">To</th><td>' . esc_html( implode( ', ', array_map( 'aw_mail_who', (array) ( $m['to'] ?? array() ) ) ) ) . '</td></tr>';
	if ( ! empty( $m['cc'] ) ) {
		echo '<tr><th scope="row">Cc</th><td>' . esc_html( implode( ', ', array_map( 'aw_mail_who', (array) $m['cc'] ) ) ) . '</td></tr>';
	}
	echo '<tr><th scope="row">Date</th><td>' . aw_mail_day( $m['date'] ?? '' ) . ( ! empty( $m['date'] ) ? ' ' . esc_html( wp_date( 'g:i a', strtotime( (string) $m['date'] ) ) ) : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</tbody></table>';
	echo '<div class="awm-tools">';
	echo '<a class="button button-primary" href="' . esc_url( aw_mail_url( array( 'compose' => 'reply', 'uid' => $s['uid'] ) ) ) . '">Reply</a>';
	echo '<a class="button" href="' . esc_url( aw_mail_url( array( 'compose' => 'all', 'uid' => $s['uid'] ) ) ) . '">Reply all</a>';
	echo '<a class="button" href="' . esc_url( aw_mail_url( array( 'compose' => 'forward', 'uid' => $s['uid'] ) ) ) . '">Forward</a>';
	if ( 'INBOX.Archive' !== $in && 'INBOX.Trash' !== $in ) {
		echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'archive', $u ) ) . '">Archive</a>';
	}
	if ( 'INBOX' !== $in ) {
		echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'inbox', $u ) ) . '">' . ( 'INBOX.Junk' === $in ? 'Not spam' : 'Move to Inbox' ) . '</a>';
	}
	if ( 'INBOX.Junk' !== $in && 'INBOX.Sent' !== $in ) {
		echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'spam', $u ) ) . '">Spam</a>';
	}
	echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'unread', $u ) ) . '">Mark unread</a>';
	if ( 'INBOX.Trash' !== $in ) {
		echo '<a class="button" style="color:#b32d2e;border-color:#b32d2e" href="' . esc_url( aw_mail_act_url( 'trash', $u ) ) . '">Delete</a>';
	}
	$others = array_diff_key( aw_mail_folders( $s['box'] ), array( $in => 1 ) );
	if ( $others ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-flex;gap:4px">';
		wp_nonce_field( 'aw_mail_act' );
		echo '<input type="hidden" name="action" value="aw_mail_act"><input type="hidden" name="do" value="move"><input type="hidden" name="box" value="' . esc_attr( $s['box'] ) . '"><input type="hidden" name="folder" value="' . esc_attr( $in ) . '"><input type="hidden" name="uid" value="' . (int) $s['uid'] . '">';
		echo '<label class="screen-reader-text" for="awm-move">Move to folder</label><select id="awm-move" name="to">';
		foreach ( $others as $p => $f ) {
			echo '<option value="' . esc_attr( $p ) . '">' . esc_html( $f[0] ) . '</option>';
		}
		echo '</select><button class="button">Move</button></form>';
	}
	echo '</div>';
	if ( $remote && ! $images ) {
		echo '<p class="description">Pictures from the internet are hidden (they can tell the sender you opened it). <a href="' . esc_url( aw_mail_url( array( 'uid' => $s['uid'], 'images' => 1 ) ) ) . '">Show pictures</a></p>';
	}
	echo '<iframe class="awm-frame" title="Message" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="' . esc_attr( $doc ) . '"></iframe>';
	$files = array_filter( (array) ( $m['attachments'] ?? array() ), fn( $a ) => empty( $a['inline'] ) || empty( $a['contentId'] ) );
	if ( $files ) {
		echo '<h3>Attachments</h3><ul>';
		foreach ( $files as $a ) {
			echo '<li><span class="dashicons dashicons-paperclip" aria-hidden="true"></span> <a href="' . esc_url( aw_mail_act_url( 'file', $u + array( 'att' => (string) $a['id'] ) ) ) . '">' . esc_html( (string) ( $a['filename'] ?? 'attachment' ) ) . '</a> <span class="description">' . esc_html( size_format( (int) ( $a['sizeBytes'] ?? 0 ) ) ) . '</span></li>';
		}
		echo '</ul>';
	}
}

/* ---------- Compose / reply / forward ---------- */

/** "Re: " / "Fwd: " once. */
function aw_mail_prefix( $p, $subject ) {
	$subject = trim( (string) $subject );
	return preg_match( '/^' . preg_quote( $p, '/' ) . '/i', $subject ) ? $subject : $p . ' ' . ( '' !== $subject ? $subject : '(no subject)' );
}

function aw_mail_compose_screen( $s, $mode ) {
	$mode  = in_array( $mode, array( 'new', 'reply', 'all', 'forward' ), true ) ? $mode : 'new';
	$boxes = aw_mail_all_boxes();
	$from  = $s['box'];
	$to    = $cc = array();
	$subj  = '';
	$quote = '';
	$ref   = '';
	if ( 'new' !== $mode && AW_MAIL_FORMS === $s['folder'] && $s['item'] ) {
		// A website form message: reply from the contact@ mailbox to the person who wrote.
		$from = (string) array_search( (string) ( aw_mail_aliases()['contact@animalwrites.in'] ?? 'vedanshu@animalwrites.in' ), $boxes, true );
		$to   = array( (string) get_post_meta( $s['item'], 'aw_m_email', true ) );
		$subj = 'Re: ' . trim( preg_replace( '/^Contact form:\s*/', '', (string) get_the_title( $s['item'] ) ) );
		$quote = '<p></p><blockquote>' . wpautop( esc_html( (string) get_post_field( 'post_content', $s['item'] ) ) ) . '</blockquote>';
	} elseif ( 'new' !== $mode && $s['uid'] ) {
		$msg = aw_mail_get( $s['box'], $s['folder'], $s['uid'] );
		if ( $msg ) {
			$m    = $msg['m'];
			$own  = aw_mail_own();
			$fr   = aw_mail_addr_list( array( $m['from'] ?? array() ) );
			$sent = 'INBOX.Sent' === $s['folder'];
			if ( 'forward' === $mode ) {
				$subj = aw_mail_prefix( 'Fwd:', $m['subject'] ?? '' );
			} else {
				$subj = aw_mail_prefix( 'Re:', $m['subject'] ?? '' );
				$to   = $sent ? aw_mail_addr_list( $m['to'] ?? array() ) : $fr;
				if ( 'all' === $mode ) {
					$cc = array_values( array_diff( array_merge( aw_mail_addr_list( $m['to'] ?? array() ), aw_mail_addr_list( $m['cc'] ?? array() ) ), $own, $to ) );
				}
			}
			$ref   = $s['folder'] . '|' . (int) $s['uid'];
			$body  = trim( (string) ( $msg['t']['html'] ?? '' ) );
			$body  = '' !== $body ? wp_kses_post( preg_replace( '#<(script|style|head|title)[^>]*>.*?</\1>#is', '', $body ) ) : wpautop( esc_html( (string) ( $msg['t']['text'] ?? '' ) ) );
			$when  = ! empty( $m['date'] ) ? wp_date( 'j M Y, g:i a', strtotime( (string) $m['date'] ) ) : '';
			$quote = '<p></p><p>' . esc_html( ( 'forward' === $mode ? '---------- Forwarded message ----------' : 'On ' . $when . ', ' . aw_mail_who( $m['from'] ?? array() ) . ' wrote:' ) ) . '</p><blockquote>' . $body . '</blockquote>';
		}
	}
	$sig   = function_exists( 'aw_mail_profile' ) ? trim( (string) aw_mail_profile( $boxes[ $from ] ?? '' )['signature'] ) : '';
	$start = '<p></p>' . ( '' !== $sig ? '<div class="signature">' . wp_kses_post( $sig ) . '</div>' : '' ) . $quote;
	$title = array( 'new' => 'New message', 'reply' => 'Reply', 'all' => 'Reply all', 'forward' => 'Forward' )[ $mode ];
	echo '<h2 style="margin-top:0">' . esc_html( $title ) . '</h2>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'aw_mail_act' );
	echo '<input type="hidden" name="action" value="aw_mail_act"><input type="hidden" name="do" value="send"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '"><input type="hidden" name="ref" value="' . esc_attr( $ref ) . '"><input type="hidden" name="refbox" value="' . esc_attr( $s['box'] ) . '"><input type="hidden" name="item" value="' . (int) $s['item'] . '">';
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="awm-from">From</label></th><td><select id="awm-from" name="from"' . ( $ref ? ' disabled' : '' ) . '>';
	foreach ( $boxes as $k => $a ) {
		$p = function_exists( 'aw_mail_profile' ) ? aw_mail_profile( $a )['name'] : '';
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $from, false ) . '>' . esc_html( ( '' !== $p ? $p . ' ' : '' ) . '<' . $a . '>' ) . '</option>';
	}
	echo '</select>' . ( $ref ? '<input type="hidden" name="from" value="' . esc_attr( $from ) . '"><p class="description">A reply goes from the mailbox the message is in.</p>' : '' ) . '</td></tr>';
	foreach ( array( 'to' => array( 'To', $to ), 'cc' => array( 'Cc', $cc ), 'bcc' => array( 'Bcc', array() ) ) as $k => $v ) {
		echo '<tr><th scope="row"><label for="awm-' . esc_attr( $k ) . '">' . esc_html( $v[0] ) . '</label></th><td><input class="large-text" type="text" id="awm-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( implode( ', ', $v[1] ) ) . '"' . ( 'to' === $k ? ' required' : '' ) . ' autocomplete="email"><p class="description">' . ( 'to' === $k ? 'Separate addresses with commas.' : '' ) . '</p></td></tr>';
	}
	echo '<tr><th scope="row"><label for="awm-subject">Subject</label></th><td><input class="large-text" type="text" id="awm-subject" name="subject" value="' . esc_attr( $subj ) . '"></td></tr>';
	echo '<tr><th scope="row">Message</th><td>';
	wp_editor( $start, 'awm_body', array( 'textarea_name' => 'body', 'media_buttons' => false, 'teeny' => true, 'textarea_rows' => 14, 'quicktags' => array( 'buttons' => 'strong,em,link,ul,ol,li' ) ) );
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="awm-files">Attachments</label></th><td><input type="file" id="awm-files" name="files[]" multiple><p class="description">Up to 10 MB in total.' . ( 'forward' === $mode ? ' The forwarded message\'s own attachments are included.' : '' ) . '</p></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Send' );
	echo '</form>';
}

/* ---------- Website forms (contact form messages kept in WordPress) ---------- */

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

function aw_mail_forms_list( $s ) {
	$q = aw_mail_forms_query( $s['page'] );
	echo '<h2 style="margin-top:0">Website forms <span class="description" style="font-weight:400">contact form messages</span></h2>';
	if ( ! $q->posts ) {
		echo '<p>No messages.</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">From</th><th scope="col">About</th><th scope="col">Date</th></tr></thead><tbody>';
	foreach ( $q->posts as $p ) {
		$url    = add_query_arg( array( 'page' => 'aw-mail', 'folder' => AW_MAIL_FORMS, 'item' => $p->ID ), admin_url( 'admin.php' ) );
		$unread = '' === (string) get_post_meta( $p->ID, 'aw_read', true );
		$name   = trim( (string) get_post_meta( $p->ID, 'aw_m_name', true ) );
		$email  = trim( (string) get_post_meta( $p->ID, 'aw_m_email', true ) );
		echo '<tr class="awm-row' . ( $unread ? ' unread' : '' ) . '"><td class="awm-from"><a href="' . esc_url( $url ) . '">' . esc_html( '' !== $name ? $name : $email ) . '</a></td><td><a href="' . esc_url( $url ) . '">' . esc_html( trim( preg_replace( '/^Contact form:\s*/', '', $p->post_title ) ) ) . '</a></td><td class="awm-date">' . aw_mail_day( $p->post_date ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table>';
	if ( $q->max_num_pages > 1 ) {
		echo '<p style="margin-top:10px">Page ' . (int) $s['page'] . ' of ' . (int) $q->max_num_pages . ' ';
		if ( $s['page'] > 1 ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'aw-mail', 'folder' => AW_MAIL_FORMS, 'p' => $s['page'] - 1 ), admin_url( 'admin.php' ) ) ) . '">Newer</a> ';
		}
		if ( $s['page'] < $q->max_num_pages ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'aw-mail', 'folder' => AW_MAIL_FORMS, 'p' => $s['page'] + 1 ), admin_url( 'admin.php' ) ) ) . '">Older</a>';
		}
		echo '</p>';
	}
}

function aw_mail_form_item_screen( $id ) {
	if ( 'aw_message' !== get_post_type( $id ) ) {
		echo '<p>Not found.</p>';
		return;
	}
	update_post_meta( $id, 'aw_read', current_time( 'mysql' ) );
	$m    = fn( $k ) => trim( (string) get_post_meta( $id, 'aw_m_' . $k, true ) );
	$back = add_query_arg( array( 'page' => 'aw-mail', 'folder' => AW_MAIL_FORMS ), admin_url( 'admin.php' ) );
	echo '<p><a href="' . esc_url( $back ) . '">&larr; Website forms</a></p>';
	echo '<h2 style="margin:0 0 8px">' . esc_html( trim( preg_replace( '/^Contact form:\s*/', '', get_the_title( $id ) ) ) ) . '</h2><table class="awm-head"><tbody>';
	foreach ( array( 'From' => trim( $m( 'name' ) . ' <' . $m( 'email' ) . '>' ), 'Organisation' => $m( 'org' ), 'Sent from' => $m( 'source' ), 'Newsletter' => $m( 'contact' ) ) as $k => $v ) {
		if ( '' !== $v && '<>' !== $v ) {
			echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
	}
	echo '<tr><th scope="row">Date</th><td>' . aw_mail_day( get_post_field( 'post_date', $id ) ) . '</td></tr></tbody></table>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="awm-tools">';
	if ( is_email( $m( 'email' ) ) ) {
		echo '<a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => 'aw-mail', 'folder' => AW_MAIL_FORMS, 'item' => $id, 'compose' => 'reply' ), admin_url( 'admin.php' ) ) ) . '">Reply</a>';
	}
	echo '<a class="button" href="' . esc_url( aw_mail_act_url( 'formunread', array( 'item' => $id ) ) ) . '">Mark unread</a>';
	echo '<a class="button" style="color:#b32d2e;border-color:#b32d2e" href="' . esc_url( aw_mail_act_url( 'formtrash', array( 'item' => $id ) ) ) . '">Delete</a>';
	echo '</div><div class="card" style="max-width:none">' . wpautop( esc_html( (string) get_post_field( 'post_content', $id ) ) ) . '</div>';
}

/* ---------- Actions ---------- */

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
		$back  = add_query_arg( array( 'page' => 'aw-mail', 'box' => $key, 'folder' => $f ), admin_url( 'admin.php' ) );
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
				list( $code ) = aw_mail_call( 'PATCH', aw_mail_fpath( $key, $f ) . '/messages/' . $uid, array( 'removeFlags' => array( '\\Seen' ) ) );
				$flush();
				$go( $back, $code >= 200 && $code < 300 ? 'flagged' : 'fail' );
				break;
			case 'file':
				$att            = (string) ( $in['att'] ?? '' );
				$msg            = aw_mail_get( $key, $f, $uid );
				$meta           = $msg ? current( array_filter( (array) ( $msg['m']['attachments'] ?? array() ), fn( $a ) => (string) ( $a['id'] ?? '' ) === $att ) ) : null;
				list( $code, $bin ) = $meta ? aw_mail_call( 'GET', aw_mail_fpath( $key, $f ) . '/messages/' . $uid . '/attachments/' . rawurlencode( $att ), null, true ) : array( 0, '' );
				if ( 200 !== $code ) {
					$go( add_query_arg( 'uid', $uid, $back ), 'fail' );
				}
				nocache_headers();
				header( 'Content-Type: application/octet-stream' );
				header( 'X-Content-Type-Options: nosniff' );
				header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( (string) ( $meta['filename'] ?? 'attachment' ) ) . '"' );
				header( 'Content-Length: ' . strlen( $bin ) );
				echo $bin; // phpcs:ignore WordPress.Security.EscapeOutput -- file download
				exit;
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
	$html  = wp_kses_post( (string) ( $in['body'] ?? '' ) );
	$back  = add_query_arg( array( 'page' => 'aw-mail', 'box' => $from, 'folder' => 'INBOX' ), admin_url( 'admin.php' ) );
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
		'html'        => '<div style="font:14px/1.5 Arial,Helvetica,sans-serif">' . $html . '</div>',
		'text'        => trim( html_entity_decode( wp_strip_all_tags( preg_replace( '#<br\s*/?>|</p>|</div>|</li>#i', "\n", preg_replace( '#<blockquote[^>]*>(.*?)</blockquote>#is', "\n> $1\n", $html ) ) ), ENT_QUOTES ) ),
		'displayName' => function_exists( 'aw_mail_profile' ) && '' !== aw_mail_profile( $boxes[ $from ] )['name'] ? aw_mail_profile( $boxes[ $from ] )['name'] : 'Animal Writes',
	);
	if ( $cc ) {
		$req['cc'] = $cc;
	}
	if ( $bcc ) {
		$req['bcc'] = $bcc;
	}
	$ref = explode( '|', (string) ( $in['ref'] ?? '' ) );
	if ( 2 === count( $ref ) && preg_match( '/^INBOX(\.[\w .-]+)?$/', $ref[0] ) && absint( $ref[1] ) ) {
		$from = isset( $boxes[ $refbox ] ) ? $refbox : $from; // a reply goes from the mailbox the message is in
		$r    = array( 'uid' => absint( $ref[1] ), 'folder' => $ref[0] );
		$req[ 'forward' === $mode ? 'forwardOf' : 'inReplyTo' ] = $r;
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
	if ( 'forward' === $mode && isset( $req['forwardOf'] ) ) {
		$msg = aw_mail_get( $from, $req['forwardOf']['folder'], $req['forwardOf']['uid'] );
		foreach ( (array) ( $msg['m']['attachments'] ?? array() ) as $a ) {
			if ( $total + (int) ( $a['sizeBytes'] ?? 0 ) > 10485760 ) {
				continue;
			}
			list( $code, $bin ) = aw_mail_call( 'GET', aw_mail_fpath( $from, $req['forwardOf']['folder'] ) . '/messages/' . (int) $req['forwardOf']['uid'] . '/attachments/' . rawurlencode( (string) $a['id'] ), null, true );
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
	$log[] = time();
	update_option( 'aw_mail_send_log', $log, false ); // times only: no addresses, subjects or text
	if ( $item && 'aw_message' === get_post_type( $item ) ) {
		update_post_meta( $item, 'aw_m_replied', current_time( 'mysql' ) );
	}
	delete_transient( 'aw_mail_folders_' . $from );
	$dest = isset( $req['inReplyTo'] ) || isset( $req['forwardOf'] ) ? add_query_arg( array( 'page' => 'aw-mail', 'box' => $from, 'folder' => ( $req['inReplyTo'] ?? $req['forwardOf'] )['folder'] ), admin_url( 'admin.php' ) ) : add_query_arg( array( 'page' => 'aw-mail', 'box' => $from, 'folder' => 'INBOX.Sent' ), admin_url( 'admin.php' ) );
	wp_safe_redirect( add_query_arg( 'aw_done', 'sent', $dest ) );
	exit;
}
