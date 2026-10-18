<?php
/**
 * Mail menu, unread badge and dashboard widget (owner, 18 Oct 2026). Administrators only. For vedanshu@ and support@:
 * the unread count of the Inbox folder (Hostinger Mail API, folder list "unreadCount") and the latest messages (sender,
 * subject, date; no bodies), each with "Open in webmail". Counts and lists are cached for 5 minutes and refreshed as
 * soon as the webhook files a new email. Nothing is read, moved, sent or deleted from here.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_MAIL_CACHE = 5 * MINUTE_IN_SECONDS;

/**
 * One mailbox's Inbox: unread count and the newest messages, cached. null when the API cannot be reached.
 *
 * @param string $key Mailbox key (vedanshu, support).
 * @return array|null { unread: int, messages: list of { from, subject, date, unseen } }
 */
function aw_mail_overview_box( $key ) {
	$cached = get_transient( 'aw_mail_overview_' . $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	if ( get_transient( 'aw_mail_overview_down_' . $key ) ) {
		return null; // the API failed a moment ago: no new try on every admin page for 5 minutes
	}
	$box     = function_exists( 'aw_mail_box_id' ) ? aw_mail_box_id( $key ) : '';
	$folders = '' !== $box ? aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders' ) : null;
	if ( ! is_array( $folders ) ) {
		set_transient( 'aw_mail_overview_down_' . $key, 1, AW_MAIL_CACHE );
		return null;
	}
	$unread = 0;
	foreach ( $folders as $f ) {
		if ( 'INBOX' === ( $f['path'] ?? '' ) ) {
			$unread = (int) ( $f['unreadCount'] ?? 0 );
		}
	}
	$list = aw_mail_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/INBOX/messages?perPage=10&sort=-date&page=1' );
	$msgs = array();
	foreach ( is_array( $list ) ? $list : array() as $m ) {
		$from = $m['from'] ?? '';
		if ( is_array( $from ) && ! isset( $from['address'] ) && isset( $from[0] ) ) {
			$from = $from[0];
		}
		$name   = is_array( $from ) ? trim( (string) ( $from['name'] ?? '' ) ) : trim( preg_replace( '/<[^>]*>/', '', (string) $from ) );
		$addr   = is_array( $from ) ? (string) ( $from['address'] ?? '' ) : implode( '', aw_mail_addresses( $from ) );
		$msgs[] = array(
			'from'    => '' !== $name ? $name . ( $addr ? ' <' . $addr . '>' : '' ) : $addr,
			'name'    => '' !== $name ? $name : $addr,
			'subject' => trim( wp_strip_all_tags( (string) ( $m['subject'] ?? '' ) ) ),
			'date'    => (string) ( $m['date'] ?? '' ),
			'unseen'  => ! empty( $m['unseen'] ) || ! in_array( '\\Seen', (array) ( $m['flags'] ?? array() ), true ),
		);
	}
	$out = array( 'unread' => $unread, 'messages' => $msgs );
	set_transient( 'aw_mail_overview_' . $key, $out, AW_MAIL_CACHE );
	return $out;
}

/** Unread emails across our mailboxes (cached). */
function aw_mail_overview_unread() {
	$n = 0;
	foreach ( array_keys( aw_mail_all_boxes() ) as $key ) {
		$n += (int) ( aw_mail_overview_box( $key )['unread'] ?? 0 );
	}
	return $n;
}

/** Forget the cached counts and lists (a new email arrived). */
function aw_mail_overview_flush() {
	foreach ( array_keys( aw_mail_all_boxes() ) as $key ) {
		delete_transient( 'aw_mail_overview_' . $key );
		delete_transient( 'aw_mail_overview_down_' . $key );
	}
}
add_action( 'aw_mail_received', 'aw_mail_overview_flush' );

/** A message date as "j M Y" (the site's one date format) with the calendar icon. */
function aw_mail_overview_date( $date ) {
	$t = $date ? strtotime( $date ) : false;
	return $t ? '<span class="dashicons dashicons-calendar-alt" aria-hidden="true" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom"></span> ' . esc_html( wp_date( 'j M Y', $t ) ) : '–';
}

/**
 * Per mailbox: heading with the unread count and a table of the newest $max messages. The Mail page shows the full
 * sender and a webmail link on each row; the narrow dashboard widget shows the sender's name and one link per mailbox.
 *
 * @param int  $max     Messages per mailbox.
 * @param bool $compact Dashboard widget layout.
 */
function aw_mail_overview_render( $max, $compact = false ) {
	$link = '<a href="' . esc_url( AW_MAIL_WEBMAIL ) . '" target="_blank" rel="noopener noreferrer">Open in webmail<span class="screen-reader-text"> (opens in a new tab)</span></a>';
	foreach ( aw_mail_all_boxes() as $key => $addr ) {
		$box   = aw_mail_overview_box( $key );
		$alias = aw_mail_aliases_of( $addr );
		echo '<h3>' . ( function_exists( 'aw_mail_profile_picture' ) ? aw_mail_profile_picture( $addr, 24 ) : '' ) . esc_html( $addr . ( $alias ? ' (also ' . implode( ', ', $alias ) . ')' : '' ) ) . ' — ' . ( $box ? esc_html( sprintf( _n( '%d unread', '%d unread', (int) $box['unread'], 'aw-mail' ), (int) $box['unread'] ) ) : 'not available' ) . '</h3>';
		if ( ! $box ) {
			echo '<p>The Mail API could not be reached for this mailbox (token missing or not scoped to it). ' . $link . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
			continue;
		}
		$msgs = array_slice( $box['messages'], 0, $max );
		if ( ! $msgs ) {
			echo '<p>No messages in the Inbox.</p>';
			continue;
		}
		echo '<table class="widefat striped" style="table-layout:fixed;overflow-wrap:anywhere"><thead><tr><th scope="col"' . ( $compact ? ' style="width:30%"' : '' ) . '>From</th><th scope="col">Subject</th><th scope="col" style="width:' . ( $compact ? '7.5em' : '8.5em' ) . '">Date</th>' . ( $compact ? '' : '<th scope="col" style="width:8em"><span class="screen-reader-text">Webmail</span></th>' ) . '</tr></thead><tbody>';
		foreach ( $msgs as $m ) {
			$b    = $m['unseen'] ? array( '<strong>', '</strong>' ) : array( '', '' );
			$from = $compact ? (string) ( $m['name'] ?? $m['from'] ) : $m['from'];
			echo '<tr><td>' . $b[0] . esc_html( $from ) . $b[1] . '</td><td>' . $b[0] . esc_html( '' !== $m['subject'] ? $m['subject'] : '(no subject)' ) . $b[1] . ( $m['unseen'] ? ' <span class="screen-reader-text">(unread)</span>' : '' ) . '</td><td>' . aw_mail_overview_date( $m['date'] ) . '</td>' . ( $compact ? '' : '<td>' . $link . '</td>' ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
		}
		echo '</tbody></table>';
		if ( $compact ) {
			echo '<p>' . $link . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
		}
	}
}

// Top-level "Mail" menu with the unread badge (hidden at 0).
add_action(
	'admin_menu',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$n     = aw_mail_overview_unread();
		$badge = $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '';
		add_menu_page( 'Mail', 'Mail' . $badge, 'manage_options', 'aw-mail', 'aw_mail_overview_page', 'dashicons-email', 27 );
	}
);

/** Mail: unread counts and the last 10 messages per mailbox. */
function aw_mail_overview_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	echo '<div class="wrap"><h1>Mail</h1>';
	echo '<p>The newest messages in each mailbox (no message text is shown or kept here). Read and answer them in <a href="' . esc_url( AW_MAIL_WEBMAIL ) . '" target="_blank" rel="noopener noreferrer">Hostinger webmail</a>. Counts refresh every 5 minutes and when a new email arrives. Settings: <a href="' . esc_url( admin_url( 'options-general.php?page=aw-mail-in' ) ) . '">Settings → Incoming mail</a>.</p>';
	aw_mail_overview_render( 10 );
	echo '</div>';
}

// Dashboard widget "Mail": the same counts and the last 5 per mailbox.
add_action(
	'wp_dashboard_setup',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'aw_mail_widget', 'Mail', fn() => aw_mail_overview_render( 5, true ) );
		}
	}
);
