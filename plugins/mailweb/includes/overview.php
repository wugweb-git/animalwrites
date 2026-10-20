<?php
/**
 * Mail menu, unread badge and dashboard widget, for administrators and editors. For each mailbox:
 * the unread count of the Inbox folder (Hostinger Mail API, folder list "unreadCount") and the latest messages (sender,
 * subject, date; no bodies), each with "Open in webmail". Counts and lists are cached for 5 minutes and refreshed as
 * soon as the webhook files a new email. Nothing is read, moved, sent or deleted from here.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAILWEB_CACHE = 5 * MINUTE_IN_SECONDS;

/**
 * One mailbox's Inbox: unread count and the newest messages, cached. null when the API cannot be reached.
 *
 * @param string $key Mailbox key (the address's local part).
 * @return array|null { unread: int, messages: list of { from, subject, date, unseen } }
 */
function mailweb_overview_box( $key ) {
	$cached = get_transient( 'mailweb_overview_' . $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	if ( get_transient( 'mailweb_overview_down_' . $key ) ) {
		return null; // the API failed a moment ago: no new try on every admin page for 5 minutes
	}
	$box     = function_exists( 'mailweb_box_id' ) ? mailweb_box_id( $key ) : '';
	$folders = '' !== $box ? mailweb_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders' ) : null;
	if ( ! is_array( $folders ) ) {
		set_transient( 'mailweb_overview_down_' . $key, 1, MAILWEB_CACHE );
		return null;
	}
	$unread = 0;
	foreach ( $folders as $f ) {
		if ( 'INBOX' === ( $f['path'] ?? '' ) ) {
			$unread = (int) ( $f['unreadCount'] ?? 0 );
		}
	}
	$list = mailweb_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders/INBOX/messages?perPage=10&sort=-date&page=1' );
	$msgs = array();
	foreach ( is_array( $list ) ? $list : array() as $m ) {
		$from = $m['from'] ?? '';
		if ( is_array( $from ) && ! isset( $from['address'] ) && isset( $from[0] ) ) {
			$from = $from[0];
		}
		$name   = is_array( $from ) ? trim( (string) ( $from['name'] ?? '' ) ) : trim( preg_replace( '/<[^>]*>/', '', (string) $from ) );
		$addr   = is_array( $from ) ? (string) ( $from['address'] ?? '' ) : implode( '', mailweb_addresses( $from ) );
		$msgs[] = array(
			'from'    => '' !== $name ? $name . ( $addr ? ' <' . $addr . '>' : '' ) : $addr,
			'name'    => '' !== $name ? $name : $addr,
			'subject' => trim( wp_strip_all_tags( (string) ( $m['subject'] ?? '' ) ) ),
			'date'    => (string) ( $m['date'] ?? '' ),
			'unseen'  => ! empty( $m['unseen'] ) || ! in_array( '\\Seen', (array) ( $m['flags'] ?? array() ), true ),
		);
	}
	$out = array( 'unread' => $unread, 'messages' => $msgs );
	set_transient( 'mailweb_overview_' . $key, $out, MAILWEB_CACHE );
	return $out;
}

/** Unread emails across our mailboxes (cached). */
function mailweb_overview_unread() {
	$n = 0;
	foreach ( array_keys( mailweb_all_boxes() ) as $key ) {
		$n += (int) ( mailweb_overview_box( $key )['unread'] ?? 0 );
	}
	return $n;
}

/** Forget the cached counts and lists (a new email arrived). */
function mailweb_overview_flush() {
	foreach ( array_keys( mailweb_all_boxes() ) as $key ) {
		delete_transient( 'mailweb_overview_' . $key );
		delete_transient( 'mailweb_overview_down_' . $key );
	}
}

/** A message date as "j M Y" (the site's one date format) with the calendar icon. */
function mailweb_overview_date( $date ) {
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
function mailweb_overview_render( $max, $compact = false ) {
	$link = '<a href="' . esc_url( admin_url( 'admin.php?page=mailweb' ) ) . '">' . esc_html__( 'Open Mail', 'mailweb' ) . '</a>';
	foreach ( mailweb_all_boxes() as $key => $addr ) {
		$box   = mailweb_overview_box( $key );
		$alias = mailweb_aliases_of( $addr );
		$n      = (int) ( $box['unread'] ?? 0 );
		/* translators: %d: number of unread messages. */
		$unread = sprintf( _n( '%d unread', '%d unread', $n, 'mailweb' ), $n );
		/* translators: %s: comma-separated list of the mailbox's alias addresses. */
		$also = $alias ? ' ' . sprintf( __( '(also %s)', 'mailweb' ), implode( ', ', $alias ) ) : '';
		echo '<h3>' . ( function_exists( 'mailweb_profile_picture' ) ? wp_kses_post( mailweb_profile_picture( $addr, 24 ) ) : '' ) . esc_html( $addr . $also ) . ' — ' . ( $box ? esc_html( $unread ) : esc_html__( 'not available', 'mailweb' ) ) . '</h3>';
		if ( ! $box ) {
			echo '<p>' . esc_html__( 'The Mail API could not be reached for this mailbox (token missing or not scoped to it).', 'mailweb' ) . ' ' . $link . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
			continue;
		}
		$msgs = array_slice( $box['messages'], 0, $max );
		if ( ! $msgs ) {
			echo '<p>' . esc_html__( 'No messages in the Inbox.', 'mailweb' ) . '</p>';
			continue;
		}
		echo '<table class="widefat striped" style="table-layout:fixed;overflow-wrap:anywhere"><thead><tr><th scope="col"' . ( $compact ? ' style="width:30%"' : '' ) . '>' . esc_html__( 'From', 'mailweb' ) . '</th><th scope="col">' . esc_html__( 'Subject', 'mailweb' ) . '</th><th scope="col" style="width:' . ( $compact ? '7.5em' : '8.5em' ) . '">' . esc_html__( 'Date', 'mailweb' ) . '</th>' . ( $compact ? '' : '<th scope="col" style="width:8em"><span class="screen-reader-text">' . esc_html__( 'Webmail', 'mailweb' ) . '</span></th>' ) . '</tr></thead><tbody>';
		foreach ( $msgs as $m ) {
			$b    = $m['unseen'] ? array( '<strong>', '</strong>' ) : array( '', '' );
			$from = $compact ? (string) ( $m['name'] ?? $m['from'] ) : $m['from'];
			echo '<tr><td>' . $b[0] . esc_html( $from ) . $b[1] . '</td><td>' . $b[0] . esc_html( '' !== $m['subject'] ? $m['subject'] : __( '(no subject)', 'mailweb' ) ) . $b[1] . ( $m['unseen'] ? ' <span class="screen-reader-text">' . esc_html__( '(unread)', 'mailweb' ) . '</span>' : '' ) . '</td><td>' . mailweb_overview_date( $m['date'] ) . '</td>' . ( $compact ? '' : '<td>' . $link . '</td>' ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
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
		if ( ! current_user_can( MAILWEB_CAP ) ) {
			return;
		}
		$n     = mailweb_overview_unread() + ( function_exists( 'mailweb_forms_unread' ) ? mailweb_forms_unread() : 0 );
		$badge = $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '';
		add_menu_page( __( 'Mail', 'mailweb' ), __( 'Mail', 'mailweb' ) . $badge, MAILWEB_CAP, 'mailweb', 'mailweb_inbox_page', 'dashicons-email', 27 ); // the inbox (inbox.php)
	}
);

// Dashboard widget "Mail": the same counts and the last 5 per mailbox.
add_action(
	'wp_dashboard_setup',
	function () {
		if ( current_user_can( MAILWEB_CAP ) ) {
			wp_add_dashboard_widget( 'mailweb_widget', __( 'Mail', 'mailweb' ), fn() => mailweb_overview_render( 5, true ) );
		}
	}
);

// FluentCRM's dashboard: no "Set Up FluentSMTP" card; Mailweb sends the site's email.
add_filter( 'fluent_crm/fluentmail_suggest', '__return_true' );
