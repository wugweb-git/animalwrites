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
	// The last good answer (3 Nov 2026: support@ showed "Not reachable" after one failed call, then answered at once):
	// a failed call is tried once more, and while the API is down the box shows the last good figures, marked stale.
	$last = get_option( 'mailweb_overview_last_' . $key );
	$last = is_array( $last ) ? $last : null;
	if ( get_transient( 'mailweb_overview_down_' . $key ) ) {
		return $last ? $last + array( 'stale' => true ) : null; // the API failed a moment ago: no new try for 5 minutes
	}
	$box     = function_exists( 'mailweb_box_id' ) ? mailweb_box_id( $key ) : '';
	$folders = '' !== $box ? mailweb_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders' ) : null;
	if ( '' !== $box && ! is_array( $folders ) ) {
		$folders = mailweb_api( '/api/v1/mailboxes/' . rawurlencode( $box ) . '/folders' ); // once more: one-off failures happen
	}
	if ( ! is_array( $folders ) ) {
		set_transient( 'mailweb_overview_down_' . $key, 1, MAILWEB_CACHE );
		return $last ? $last + array( 'stale' => true ) : null;
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
			'unseen'  => ! empty( $m['unseen'] ),
		);
	}
	$out = array( 'unread' => $unread, 'messages' => $msgs );
	set_transient( 'mailweb_overview_' . $key, $out, MAILWEB_CACHE );
	update_option( 'mailweb_overview_last_' . $key, $out + array( 'at' => time() ), false );
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
	if ( $compact ) {
		mailweb_overview_widget( $max );
		return;
	}
	$link = '<a href="' . esc_url( admin_url( 'admin.php?page=mailweb' ) ) . '">' . esc_html__( 'Open Mail', 'mailweb' ) . '</a>';
	foreach ( mailweb_all_boxes() as $key => $addr ) {
		$box   = mailweb_overview_box( $key );
		$alias = mailweb_aliases_of( $addr );
		$n      = (int) ( $box['unread'] ?? 0 );
		/* translators: %d: number of unread messages. */
		$unread = sprintf( _n( '%d unread', '%d unread', $n, 'mailweb' ), $n );
		/* translators: %s: comma-separated list of the mailbox's alias addresses. */
		$also = $alias ? ' ' . sprintf( __( '(also %s)', 'mailweb' ), implode( ', ', $alias ) ) : '';
		if ( $box && ! empty( $box['stale'] ) ) {
			/* translators: %s: time of the last good answer. */
			$unread .= ' ' . sprintf( __( '(the Mail API is not answering; shown from %s)', 'mailweb' ), wp_date( 'g:i a', (int) ( $box['at'] ?? 0 ) ) );
		}
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
		$cols = array( 'from' => esc_html__( 'From', 'mailweb' ), 'subject' => esc_html__( 'Subject', 'mailweb' ), 'date' => esc_html__( 'Date', 'mailweb' ) );
		if ( ! $compact ) {
			$cols['webmail'] = '<span class="screen-reader-text">' . esc_html__( 'Webmail', 'mailweb' ) . '</span>';
		}
		$rows = array();
		foreach ( $msgs as $m ) {
			$b      = $m['unseen'] ? array( '<strong>', '</strong>' ) : array( '', '' );
			$from   = $compact ? (string) ( $m['name'] ?? $m['from'] ) : $m['from'];
			$rows[] = array(
				'from'    => $b[0] . esc_html( $from ) . $b[1],
				'subject' => $b[0] . esc_html( '' !== $m['subject'] ? $m['subject'] : __( '(no subject)', 'mailweb' ) ) . $b[1] . ( $m['unseen'] ? ' <span class="screen-reader-text">' . esc_html__( '(unread)', 'mailweb' ) . '</span>' : '' ),
				'date'    => mailweb_overview_date( $m['date'] ),
				'webmail' => $link,
			);
		}
		mailweb_simple_table( 'mail-' . sanitize_key( $key ), $cols, $rows, array( 'fixed' => true ) ); // fixed: long addresses wrap in the narrow dashboard box
		if ( $compact ) {
			echo '<p>' . $link . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
		}
	}
}

/**
 * The dashboard widget (owner, 3 Nov 2026: shorter): per mailbox one line with its unread count and a short list of
 * the newest emails (sender, bold when unread; date; subject on one line), then one link to the inbox. Core list
 * markup only.
 *
 * @param int $max Emails per mailbox.
 */
function mailweb_overview_widget( $max ) {
	// One table (owner, 3 Nov 2026: "some columns, some rows, status and dropdown"): a row per mailbox with its unread
	// count and status; the mailbox opens to its latest messages.
	$inbox = admin_url( 'admin.php?page=mailweb' );
	$rows  = array();
	foreach ( mailweb_all_boxes() as $key => $addr ) {
		$box  = mailweb_overview_box( $key );
		$msgs = $box ? array_slice( (array) $box['messages'], 0, $max ) : array();
		if ( ! $box ) {
			$status = array( 'warning', '#b32d2e', __( 'Not reachable', 'mailweb' ) );
		} elseif ( ! empty( $box['stale'] ) ) {
			/* translators: %s: time of the last good answer. */
			$status = array( 'clock', '#50575e', sprintf( __( 'Shown from %s', 'mailweb' ), wp_date( 'g:i a', (int) ( $box['at'] ?? 0 ) ) ) );
		} elseif ( ! $msgs ) {
			$status = array( 'minus', '#50575e', __( 'Empty', 'mailweb' ) );
		} else {
			$status = array( 'yes-alt', '#007017', __( 'OK', 'mailweb' ) );
		}
		$list = '';
		foreach ( $msgs as $m ) {
			$t     = $m['date'] ? strtotime( $m['date'] ) : false;
			$subj  = '' !== $m['subject'] ? $m['subject'] : __( '(no subject)', 'mailweb' );
			$name  = esc_html( (string) ( $m['name'] ?? $m['from'] ) );
			$list .= '<li>' . ( $m['unseen'] ? '<strong>' . $name . '</strong> <span class="screen-reader-text">' . esc_html__( '(unread)', 'mailweb' ) . '</span>' : $name ) . ( $t ? ' <span class="description">' . esc_html( wp_date( 'j M', $t ) ) . '</span>' : '' ) . '<br><a href="' . esc_url( $inbox ) . '" title="' . esc_attr( $subj ) . '">' . esc_html( mb_strimwidth( $subj, 0, 60, '…' ) ) . '</a></li>';
		}
		$who  = ( function_exists( 'mailweb_profile_picture' ) ? wp_kses_post( mailweb_profile_picture( $addr, 20 ) ) : '' ) . esc_html( $addr );
		$cell = $list ? '<details><summary>' . $who . '</summary><ul>' . $list . '</ul></details>' : $who;
		$rows[] = array(
			'mailbox' => '<div style="overflow-wrap:anywhere">' . $cell . '</div>',
			'unread'  => $box ? esc_html( number_format_i18n( (int) ( $box['unread'] ?? 0 ) ) ) : '–',
			'status'  => '<span class="dashicons dashicons-' . esc_attr( $status[0] ) . '" style="color:' . esc_attr( $status[1] ) . '" aria-hidden="true"></span> ' . esc_html( $status[2] ),
		);
	}
	mailweb_simple_table(
		'mailboxes',
		array(
			'mailbox' => esc_html__( 'Mailbox', 'mailweb' ),
			'unread'  => esc_html__( 'Unread', 'mailweb' ),
			'status'  => esc_html__( 'Status', 'mailweb' ),
		),
		$rows,
		array( 'foot' => false ) // a short table in the dashboard box: no repeated header row
	);
	echo '<p><a href="' . esc_url( $inbox ) . '">' . esc_html__( 'Open Mail', 'mailweb' ) . '</a></p>';
}

// Plugins → MailWeb with the unread badge (hidden at 0), opening the inbox (owner, 11 Nov 2026: "all plugins" under
// Plugins); Overview and Settings are its tabs (mailweb_screen_tabs()). Without Kits Core, a menu of its own as before.
add_action(
	'admin_menu',
	function () {
		if ( ! current_user_can( MAILWEB_CAP ) ) {
			return;
		}
		$n     = mailweb_overview_unread() + ( function_exists( 'mailweb_forms_unread' ) ? mailweb_forms_unread() : 0 );
		$badge = $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '';
		// The Wugweb block (owner, 12 Nov 2026): Kits, DirectoryWeb, SourceWeb, RankWeb, InsightWeb, MailWeb, PlanWeb at 58.1-58.7,
		// after one separator (58.05), before WordPress's second separator (59); the same with or without Kits Core.
		// The icon is the plugin's one-colour SVG as a data URI, which WordPress paints in the admin colour scheme.
		$icon = dirname( __DIR__ ) . '/assets/menu-icon.svg';
		$icon = is_readable( $icon ) ? 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( $icon ) ) : 'dashicons-email'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the plugin's own small SVG file.
		add_menu_page( __( 'MailWeb', 'mailweb' ), __( 'MailWeb', 'mailweb' ) . $badge, MAILWEB_CAP, 'mailweb', 'mailweb_inbox_page', $icon, '58.6' ); // the inbox (inbox.php); screens toplevel_page_mailweb, mailweb_page_…
		$GLOBALS['admin_page_hooks'][ 'mailweb' ] = 'mailweb'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the screens' hook prefix (mailweb_page_…), whatever the title.
		if ( ! isset( $GLOBALS['menu']['58.05'] ) ) {
			$GLOBALS['menu']['58.05'] = array( '', 'read', 'separator-wugweb', '', 'wp-menu-separator', 'separator-wugweb' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- one separator above the block, as core adds its own.
		}
		add_submenu_page( 'mailweb', __( 'MailWeb', 'mailweb' ), __( 'Inbox', 'mailweb' ), MAILWEB_CAP, 'mailweb', 'mailweb_inbox_page' );
		add_submenu_page( 'mailweb', __( 'MailWeb overview', 'mailweb' ), __( 'Overview', 'mailweb' ), MAILWEB_CAP, 'mailweb-overview', 'mailweb_home_page' ); // home.php
		add_submenu_page( 'mailweb', __( 'MailWeb settings', 'mailweb' ), __( 'Settings', 'mailweb' ), 'manage_options', 'mailweb-settings', 'mailweb_settings_page' ); // settings-tab.php
	}
);

// Dashboard widget "Mail": the same counts and the last 3 per mailbox.
add_action(
	'wp_dashboard_setup',
	function () {
		if ( current_user_can( MAILWEB_CAP ) ) {
			wp_add_dashboard_widget( 'mailweb_widget', __( 'Mail', 'mailweb' ), fn() => mailweb_overview_render( 3, true ) );
		}
	}
);

// FluentCRM's dashboard: no "Set Up FluentSMTP" card; MailWeb sends the site's email.
add_filter( 'fluent_crm/fluentmail_suggest', '__return_true' );

/** MailWeb's screens as tabs under a screen's heading (Inbox, Overview, Settings; those the user may open). */
function mailweb_screen_tabs() {
	if ( function_exists( 'kits_admin_fold_tabs' ) ) {
		kits_admin_fold_tabs( 'mailweb', __( 'MailWeb screens', 'mailweb' ) );
	}
}
