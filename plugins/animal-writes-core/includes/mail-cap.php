<?php
/**
 * Site-wide daily email cap (owner, 16 Oct 2026). The mailbox is Hostinger's free Business Email: 100 emails per
 * rolling 24 hours, 100 recipients per message. The site sends at most the budget (Settings → Mail, default 90).
 *
 * How it works
 * - Every wp_mail goes through pre_wp_mail here. If the rolling-24-hour count is under the budget and nothing of the same
 *   or higher priority is already waiting, the email is logged and sent at once.
 * - Otherwise it is queued in {prefix}aw_mail_queue (to, subject, body, headers, attachment paths) and wp_mail returns
 *   true, so no sender ever sees a failure. WP-Cron (every 5 minutes) sends the queue as the budget frees: highest
 *   priority first, oldest first within a priority. A queued email that fails 5 times is kept as "failed" (shown on
 *   Settings → Mail), never dropped.
 * - Priorities: 0 replies typed by hand in FluentCRM (a contact's profile → Send Email; fluentcrm.php marks them with
 *   an X-AW-Priority header, removed before sending), 1 owner/admin notices (everything not listed here), 2 sign-up
 *   confirmations (Fluent Forms), 3 FluentCRM campaign emails.
 * - Campaign emails may use the budget minus a reserve (default 10), so notices and confirmations always have room.
 * - FluentCRM: sending speed 1 per second (fluent_crm/global_email_limit_per_second); its sender does not start while
 *   the campaign share is used up or campaign emails wait in the site's queue (fluent_crm/disable_email_processing;
 *   not while FluentCRM sends one contact's emails at once, as it does for a reply from the profile),
 *   so the rest of a campaign stays in FluentCRM's own queue ("pending") and goes out on the following days; each run
 *   pulls at most the remaining campaign share (fluent_crm/mailer_handler_chunk_size). FluentCRM marks an email failed
 *   when wp_mail returns false; that never happens here: an email past the budget within a run is queued here, wp_mail
 *   returns true, FluentCRM records it as sent, and the site's queue delivers it later.
 * After sending, a queued email's content is cleared; the log keeps time, priority and status (30 days).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_MAIL_DB_VERSION = '1';
const AW_MAIL_TRIES      = 5;

function aw_mail_table() {
	global $wpdb;
	return $wpdb->prefix . 'aw_mail_queue';
}

/** Settings: budget per rolling 24 hours, and the part kept back from campaigns. */
function aw_mail_cap_settings() {
	$s = wp_parse_args( (array) get_option( 'aw_mail_cap', array() ), array( 'budget' => 90, 'reserve' => 10 ) );
	$s['budget']  = max( 1, (int) $s['budget'] );
	$s['reserve'] = min( $s['budget'] - 1, max( 0, (int) $s['reserve'] ) );
	return $s;
}

add_action(
	'init',
	function () {
		if ( AW_MAIL_DB_VERSION === get_option( 'aw_mail_queue_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . aw_mail_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			priority tinyint(3) unsigned NOT NULL DEFAULT 1,
			status varchar(10) NOT NULL,
			mail longtext NULL,
			tries tinyint(3) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			KEY sent (status, sent_at),
			KEY next (status, priority, id)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( 'aw_mail_queue_db', AW_MAIL_DB_VERSION, false );
	},
	5
);

/** Emails sent (or handed to the mailbox) in the last 24 hours. */
function aw_mail_sent_24h() {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . aw_mail_table() . " WHERE status = 'sent' AND sent_at > %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
}

/** Queued emails, all or of priority $max and higher (1 is highest). */
function aw_mail_queued( $max = 3 ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . aw_mail_table() . " WHERE status = 'queued' AND priority <= %d", $max ) ); // phpcs:ignore
}

/** How many emails a priority may still send now. */
function aw_mail_room( $priority ) {
	$s = aw_mail_cap_settings();
	return max( 0, ( 3 === $priority ? $s['budget'] - $s['reserve'] : $s['budget'] ) - aw_mail_sent_24h() );
}

/** 0 replies from FluentCRM, 1 notices, 2 sign-up confirmations (Fluent Forms), 3 FluentCRM campaigns. */
function aw_mail_priority() {
	if ( isset( $GLOBALS['aw_mail_next_priority'] ) ) { // set from the email's own X-AW-Priority header (below)
		return (int) $GLOBALS['aw_mail_next_priority'];
	}
	if ( ! empty( $GLOBALS['aw_mail_priority'] ) ) {
		return (int) $GLOBALS['aw_mail_priority'];
	}
	$p = 1;
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 ) as $f ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$c = $f['class'] ?? '';
		if ( 0 === strpos( $c, 'FluentCrm\\' ) ) {
			return 3;
		}
		if ( 0 === strpos( $c, 'FluentForm\\' ) ) {
			$p = 2;
		}
	}
	return $p;
}

function aw_mail_stat( $key, $n = 1 ) {
	$s         = (array) get_option( 'aw_mail_stats', array() );
	$s[ $key ] = (int) ( $s[ $key ] ?? 0 ) + $n;
	update_option( 'aw_mail_stats', $s, false );
}

// An email may carry its priority as an "X-AW-Priority: n" header: it is read here and removed before sending.
add_filter(
	'wp_mail',
	function ( $atts ) {
		unset( $GLOBALS['aw_mail_next_priority'] );
		$h    = $atts['headers'] ?? '';
		$list = is_array( $h ) ? $h : explode( "\n", str_replace( "\r\n", "\n", (string) $h ) );
		$keep = array();
		foreach ( $list as $line ) {
			if ( is_string( $line ) && preg_match( '/^X-AW-Priority:\s*([0-3])\s*$/i', trim( $line ), $m ) ) {
				$GLOBALS['aw_mail_next_priority'] = (int) $m[1];
				continue;
			}
			$keep[] = $line;
		}
		if ( isset( $GLOBALS['aw_mail_next_priority'] ) ) {
			$atts['headers'] = is_array( $h ) ? $keep : implode( "\n", $keep );
		}
		return $atts;
	},
	PHP_INT_MAX
);

add_filter(
	'pre_wp_mail',
	function ( $return, $atts ) {
		$p = aw_mail_priority();
		unset( $GLOBALS['aw_mail_next_priority'] ); // for this email only
		if ( null !== $return || ! empty( $GLOBALS['aw_mail_flushing'] ) ) {
			return $return; // another filter decided, or this is the queue sending
		}
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( aw_mail_room( $p ) > 0 && 0 === aw_mail_queued( $p ) ) {
			$wpdb->insert( aw_mail_table(), array( 'priority' => $p, 'status' => 'sent', 'created_at' => $now, 'sent_at' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			aw_mail_stat( 'sent_direct' );
			return null; // wp_mail sends it now
		}
		$mail = array(
			'to'          => $atts['to'] ?? '',
			'subject'     => $atts['subject'] ?? '',
			'message'     => $atts['message'] ?? '',
			'headers'     => $atts['headers'] ?? '',
			'attachments' => array_values( array_filter( (array) ( $atts['attachments'] ?? array() ), 'is_string' ) ),
		);
		$ok = $wpdb->insert( aw_mail_table(), array( 'priority' => $p, 'status' => 'queued', 'mail' => wp_json_encode( $mail ), 'created_at' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $ok ) {
			return null; // could not queue: send rather than lose it
		}
		aw_mail_stat( 'queued' );
		return true;
	},
	20,
	2
);

/** Send queued emails while the budget allows (highest priority, then oldest first). */
function aw_mail_flush( $max = 40 ) {
	global $wpdb;
	if ( get_transient( 'aw_mail_flush_lock' ) ) {
		return 0;
	}
	set_transient( 'aw_mail_flush_lock', 1, 5 * MINUTE_IN_SECONDS );
	$t    = aw_mail_table();
	$sent = 0;
	for ( $i = 0; $i < $max; $i++ ) {
		$camp = aw_mail_room( 3 ) > 0 ? 3 : 2;
		if ( aw_mail_room( 1 ) < 1 ) {
			break;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = 'queued' AND priority <= %d ORDER BY priority, id LIMIT 1", $camp ) ); // phpcs:ignore
		if ( ! $row ) {
			break;
		}
		$m     = (array) json_decode( (string) $row->mail, true );
		$files = array_values( array_filter( (array) ( $m['attachments'] ?? array() ), 'is_readable' ) );
		$GLOBALS['aw_mail_flushing'] = true;
		$ok = wp_mail( $m['to'] ?? '', (string) ( $m['subject'] ?? '' ), (string) ( $m['message'] ?? '' ), $m['headers'] ?? '', $files );
		$GLOBALS['aw_mail_flushing'] = false;
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( $ok ) {
			$wpdb->update( $t, array( 'status' => 'sent', 'sent_at' => $now, 'mail' => null ), array( 'id' => (int) $row->id ) ); // phpcs:ignore
			aw_mail_stat( 'sent_from_queue' );
			++$sent;
		} else {
			$tries = (int) $row->tries + 1;
			// a failed attempt still counts against the mailbox's limit
			$wpdb->update( $t, array( 'tries' => $tries, 'status' => $tries >= AW_MAIL_TRIES ? 'failed' : 'queued' ), array( 'id' => (int) $row->id ) ); // phpcs:ignore
			$wpdb->insert( $t, array( 'priority' => (int) $row->priority, 'status' => 'sent', 'created_at' => $now, 'sent_at' => $now ) ); // phpcs:ignore
			aw_mail_stat( $tries >= AW_MAIL_TRIES ? 'failed' : 'retry' );
			break; // try again on the next run
		}
	}
	// The log keeps 30 days (time, priority, status only once sent).
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE status = 'sent' AND sent_at < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
	delete_transient( 'aw_mail_flush_lock' );
	return $sent;
}

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['aw_5min'] = array( 'interval' => 300, 'display' => 'Every 5 minutes' );
		return $s;
	}
);
add_action( 'aw_mail_flush', 'aw_mail_flush' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_mail_flush' ) ) {
			wp_schedule_event( time() + 60, 'aw_5min', 'aw_mail_flush' );
		}
	}
);

/* ---------- FluentCRM: slowest speed, and campaigns wait for the budget ---------- */

add_filter( 'fluent_crm/global_email_limit_per_second', fn() => 1 );
add_filter( 'fluent_crm/disable_email_processing', fn( $off ) => $off || ( empty( $GLOBALS['aw_crm_contact_job'] ) && ( aw_mail_room( 3 ) < 1 || aw_mail_queued( 3 ) > 0 ) ) );
add_filter( 'fluent_crm/mailer_handler_chunk_size', fn( $n ) => max( 1, min( (int) $n, aw_mail_room( 3 ) ) ) );

/* ---------- Settings → Mail: the cap, today's count and the queue ---------- */

add_action(
	'kits_mail_screen_after',
	function () {
		global $wpdb;
		$s     = aw_mail_cap_settings();
		$t     = aw_mail_table();
		$q     = wp_list_pluck( (array) $wpdb->get_results( "SELECT priority, COUNT(*) n FROM {$t} WHERE status = 'queued' GROUP BY priority", ARRAY_A ), 'n', 'priority' ); // phpcs:ignore
		$fail  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'failed'" ); // phpcs:ignore
		$old   = $wpdb->get_var( "SELECT MIN(created_at) FROM {$t} WHERE status = 'queued'" ); // phpcs:ignore
		$stats = (array) get_option( 'aw_mail_stats', array() );
		$note  = sanitize_key( $_GET['aw_cap'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<hr><h2 id="cap">Daily sending limit</h2>';
		if ( 'saved' === $note ) {
			echo '<div class="notice notice-success inline"><p>Saved.</p></div>';
		} elseif ( 'flushed' === $note ) {
			echo '<div class="notice notice-success inline"><p>Sent ' . (int) ( $_GET['n'] ?? 0 ) . ' queued emails.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
		echo '<p>The mailbox may send 100 emails in any 24 hours. The site keeps under the limit below; anything over it waits in the queue and is sent as the limit frees: replies typed in FluentCRM first, then notices, then sign-up confirmations, then newsletter campaigns.</p>';
		echo '<table class="widefat striped" style="max-width:40rem"><tbody>';
		$rows = array(
			'Sent in the last 24 hours'              => aw_mail_sent_24h() . ' of ' . $s['budget'],
			'Waiting: replies from FluentCRM'        => (int) ( $q[0] ?? 0 ),
			'Waiting: notices'                       => (int) ( $q[1] ?? 0 ),
			'Waiting: sign-up confirmations'         => (int) ( $q[2] ?? 0 ),
			'Waiting: newsletter campaigns'          => (int) ( $q[3] ?? 0 ),
			'Oldest waiting since (UTC)'             => $old ? $old : '–',
			'Could not be sent after 5 tries'        => $fail,
			'All time: sent at once / queued / sent from the queue' => (int) ( $stats['sent_direct'] ?? 0 ) . ' / ' . (int) ( $stats['queued'] ?? 0 ) . ' / ' . (int) ( $stats['sent_from_queue'] ?? 0 ),
		);
		foreach ( $rows as $k => $v ) {
			echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'aw_mail_cap' );
		echo '<input type="hidden" name="action" value="aw_mail_cap"><table class="form-table">';
		echo '<tr><th scope="row"><label for="aw-cap-budget">Emails per 24 hours</label></th><td><input id="aw-cap-budget" name="budget" type="number" min="1" max="100" value="' . (int) $s['budget'] . '"><p class="description">Hostinger\'s limit is 100; 90 leaves a margin of 10.</p></td></tr>';
		echo '<tr><th scope="row"><label for="aw-cap-reserve">Kept back from campaigns</label></th><td><input id="aw-cap-reserve" name="reserve" type="number" min="0" max="99" value="' . (int) $s['reserve'] . '"><p class="description">Campaigns use at most the limit minus this, so notices and confirmations always have room.</p></td></tr>';
		echo '</table><p><button class="button button-primary" name="do" value="save">Save limit</button> <button class="button" name="do" value="flush">Send queued emails now (within the limit)</button></p></form>';
	}
);

add_action(
	'admin_post_aw_mail_cap',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_mail_cap' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		if ( 'flush' === ( $_POST['do'] ?? '' ) ) {
			$n = aw_mail_flush();
			wp_safe_redirect( admin_url( 'options-general.php?page=kits-mail&aw_cap=flushed&n=' . (int) $n . '#cap' ) );
			exit;
		}
		update_option( 'aw_mail_cap', array( 'budget' => min( 100, max( 1, absint( $_POST['budget'] ?? 90 ) ) ), 'reserve' => absint( $_POST['reserve'] ?? 10 ) ), false );
		wp_safe_redirect( admin_url( 'options-general.php?page=kits-mail&aw_cap=saved#cap' ) );
		exit;
	}
);
