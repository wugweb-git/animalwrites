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
 * - Priorities: 0 replies typed by hand to an incoming email (Inbox → Reply, mail-reply.php, marks them with an
 *   X-AW-Priority header, removed before sending), 1 owner/admin notices (everything not listed here), 2 sign-up
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

const AW_MAIL_DB_VERSION = '2';
const AW_MAIL_TRIES      = 4; // attempts before an email is held: retries after 15 minutes, 1 hour, 6 hours

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
			error varchar(255) NULL,
			next_try datetime NULL,
			resend tinyint(1) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY sent (status, sent_at),
			KEY next (status, priority, id)
			) {$wpdb->get_charset_collate()};"
		);
		$wpdb->query( "UPDATE " . aw_mail_table() . " SET status = 'held' WHERE status = 'failed' AND next_try IS NULL" ); // phpcs:ignore -- failures of the old kind wait for a manual retry
		update_option( 'aw_mail_queue_db', AW_MAIL_DB_VERSION, false );
	},
	5
);


/* ---------- Failures: a send counts as sent only when the mail server accepted it ---------- */

/** The pause record (array) while sending is stopped, else false. */
function aw_mail_paused() {
	return get_option( 'aw_mail_pause', false );
}

/** Error text for the log: no addresses, one line, short. */
function aw_mail_clean_error( $msg ) {
	$msg = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[address]', wp_strip_all_tags( (string) $msg ) );
	return mb_substr( trim( preg_replace( '/\s+/', ' ', $msg ) ), 0, 250 );
}

/** Errors that mean the mailbox itself is refused (blocked, disabled, suspended, sign-in refused): stop everything. */
function aw_mail_is_block_error( $msg ) {
	return (bool) preg_match( '/\b5\.7\.\d+|\b554\b|\b550\b|\b535\b|disabled|blocked|suspend|abuse|not allowed|authenticat|too many|rate limit|quota/i', (string) $msg );
}

/** Stop all sending until someone presses Resume. No email is used to tell anyone. */
function aw_mail_pause( $msg ) {
	if ( ! aw_mail_paused() ) {
		update_option( 'aw_mail_pause', array( 'at' => time(), 'error' => $msg ), false );
		aw_mail_stat( 'paused' );
	}
}

/** Record a failed attempt on queue row $id: retry later (15 min, 1 h, 6 h), then hold. Mail content stays. */
function aw_mail_mark_failed( $id, $msg ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT tries FROM ' . aw_mail_table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	if ( ! $row ) {
		return;
	}
	$tries = (int) $row->tries + 1;
	$wait  = array( 1 => 15 * MINUTE_IN_SECONDS, 2 => HOUR_IN_SECONDS, 3 => 6 * HOUR_IN_SECONDS );
	$wpdb->update(
		aw_mail_table(),
		array(
			'tries'    => $tries,
			'status'   => $tries >= AW_MAIL_TRIES ? 'held' : 'failed',
			'error'    => $msg,
			'next_try' => $tries >= AW_MAIL_TRIES ? null : gmdate( 'Y-m-d H:i:s', time() + $wait[ $tries ] ),
		),
		array( 'id' => (int) $id )
	); // phpcs:ignore
	update_option( 'aw_mail_last_error', array( 'at' => time(), 'error' => $msg ), false );
	aw_mail_stat( 'rejected' );
	if ( aw_mail_is_block_error( $msg ) ) {
		aw_mail_pause( $msg );
	}
}

// PHPMailer's error, for the send in progress (queue sends read it after wp_mail returns; direct sends are handled here).
add_action(
	'wp_mail_failed',
	function ( $err ) {
		$msg = aw_mail_clean_error( is_wp_error( $err ) ? $err->get_error_message() : 'Send failed' );
		$GLOBALS['aw_mail_last_error'] = '' === $msg ? 'Send failed' : $msg;
		$id = (int) ( $GLOBALS['aw_mail_direct_id'] ?? 0 );
		if ( $id ) {
			unset( $GLOBALS['aw_mail_direct_id'] );
			aw_mail_mark_failed( $id, $GLOBALS['aw_mail_last_error'] );
		}
	},
	5
);

add_action(
	'wp_mail_succeeded',
	function () {
		$id = (int) ( $GLOBALS['aw_mail_direct_id'] ?? 0 );
		if ( $id ) {
			unset( $GLOBALS['aw_mail_direct_id'] );
			global $wpdb;
			$wpdb->update( aw_mail_table(), array( 'status' => 'sent', 'sent_at' => gmdate( 'Y-m-d H:i:s' ), 'mail' => null, 'error' => null ), array( 'id' => $id ) ); // phpcs:ignore
		}
	},
	5
);

/** Emails sent (or being sent) in the last 24 hours. */
function aw_mail_sent_24h() {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . aw_mail_table() . " WHERE ( status = 'sent' AND sent_at > %s ) OR status = 'sending'", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
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
		unset( $GLOBALS['aw_mail_next_priority'], $GLOBALS['aw_mail_direct_id'], $GLOBALS['aw_mail_last_error'] ); // for this email only
		if ( null !== $return || ! empty( $GLOBALS['aw_mail_flushing'] ) ) {
			return $return; // another filter decided, or this is the queue sending
		}
		global $wpdb;
		$now    = gmdate( 'Y-m-d H:i:s' );
		$resend = ! empty( $GLOBALS['aw_mail_resend'] ) ? 1 : 0; // a re-send of a lost email: always through the queue, 5 an hour
		$mail   = array(
			'to'          => $atts['to'] ?? '',
			'subject'     => $atts['subject'] ?? '',
			'message'     => $atts['message'] ?? '',
			'headers'     => $atts['headers'] ?? '',
			'attachments' => array_values( array_filter( (array) ( $atts['attachments'] ?? array() ), 'is_string' ) ),
		);
		$json = wp_json_encode( $mail );
		if ( ! $resend && ! aw_mail_paused() && aw_mail_room( $p ) > 0 && 0 === aw_mail_queued( $p ) ) {
			// Sent at once, but kept as "sending" with its content until the mail server accepts it.
			$ok = $json ? $wpdb->insert( aw_mail_table(), array( 'priority' => $p, 'status' => 'sending', 'mail' => $json, 'created_at' => $now ) ) : false; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $ok ) {
				$GLOBALS['aw_mail_direct_id'] = (int) $wpdb->insert_id;
				aw_mail_stat( 'sent_direct' );
			}
			return null; // wp_mail sends it now
		}
		$ok = $wpdb->insert( aw_mail_table(), array( 'priority' => $p, 'status' => 'queued', 'mail' => $json, 'created_at' => $now, 'resend' => $resend ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $ok ) {
			return null; // could not queue: send rather than lose it
		}
		aw_mail_stat( 'queued' );
		return true;
	},
	20,
	2
);

/** Send queued emails while the budget allows (highest priority, then oldest first). Stops at once if sending is paused. */
function aw_mail_flush( $max = 40 ) {
	global $wpdb;
	if ( aw_mail_paused() || get_transient( 'aw_mail_flush_lock' ) ) {
		return 0;
	}
	set_transient( 'aw_mail_flush_lock', 1, 5 * MINUTE_IN_SECONDS );
	$t    = aw_mail_table();
	$sent = 0;
	// A send that never reported back (crash, time-out): retry it, never lose it.
	$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'failed', tries = tries + 1, error = 'No confirmation from the mail server', next_try = %s WHERE status = 'sending' AND created_at < %s AND mail IS NOT NULL", gmdate( 'Y-m-d H:i:s', time() + 15 * MINUTE_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore
	for ( $i = 0; $i < $max; $i++ ) {
		if ( aw_mail_paused() ) {
			break;
		}
		$camp = aw_mail_room( 3 ) > 0 ? 3 : 2;
		if ( aw_mail_room( 1 ) < 1 ) {
			break;
		}
		$now      = gmdate( 'Y-m-d H:i:s' );
		$resent_h = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE resend = 1 AND status IN ('sent','sending') AND sent_at > %s", gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) ); // phpcs:ignore
		$no_resend = $resent_h >= 5 ? ' AND resend = 0' : ''; // re-sends of lost emails: at most 5 an hour
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE status IN ('queued','failed') AND mail IS NOT NULL AND (next_try IS NULL OR next_try <= %s) AND priority <= %d{$no_resend} ORDER BY priority, id LIMIT 1", $now, $camp ) ); // phpcs:ignore
		if ( ! $row ) {
			break;
		}
		$m = (array) json_decode( (string) $row->mail, true );
		if ( empty( $m['to'] ) ) {
			$wpdb->update( $t, array( 'status' => 'held', 'error' => 'No recipient stored' ), array( 'id' => (int) $row->id ) ); // phpcs:ignore
			continue;
		}
		$files = array_values( array_filter( (array) ( $m['attachments'] ?? array() ), 'is_readable' ) );
		$wpdb->update( $t, array( 'status' => 'sending', 'sent_at' => $now ), array( 'id' => (int) $row->id ) ); // phpcs:ignore
		unset( $GLOBALS['aw_mail_last_error'], $GLOBALS['aw_mail_direct_id'] );
		$GLOBALS['aw_mail_flushing'] = true;
		$ok = wp_mail( $m['to'], (string) ( $m['subject'] ?? '' ), (string) ( $m['message'] ?? '' ), $m['headers'] ?? '', $files );
		$GLOBALS['aw_mail_flushing'] = false;
		$err = $GLOBALS['aw_mail_last_error'] ?? '';
		if ( $ok && '' === $err ) {
			$wpdb->update( $t, array( 'status' => 'sent', 'sent_at' => gmdate( 'Y-m-d H:i:s' ), 'mail' => null, 'error' => null ), array( 'id' => (int) $row->id ) ); // phpcs:ignore
			aw_mail_stat( 'sent_from_queue' );
			++$sent;
		} else {
			$wpdb->update( $t, array( 'sent_at' => null ), array( 'id' => (int) $row->id ) ); // phpcs:ignore
			aw_mail_mark_failed( (int) $row->id, '' !== $err ? $err : 'Send failed' );
			break; // try again on the next run (not at all if sending was paused)
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
add_filter( 'fluent_crm/disable_email_processing', fn( $off ) => $off || (bool) aw_mail_paused() || ( empty( $GLOBALS['aw_crm_contact_job'] ) && ( aw_mail_room( 3 ) < 1 || aw_mail_queued( 3 ) > 0 ) ) );
add_filter( 'fluent_crm/mailer_handler_chunk_size', fn( $n ) => max( 1, min( (int) $n, aw_mail_room( 3 ) ) ) );

/* ---------- Settings → Mail: the cap, today's count and the queue ---------- */

add_action(
	'kits_mail_screen_after',
	function () {
		global $wpdb;
		$s     = aw_mail_cap_settings();
		$t     = aw_mail_table();
		$q     = wp_list_pluck( (array) $wpdb->get_results( "SELECT priority, COUNT(*) n FROM {$t} WHERE status = 'queued' GROUP BY priority", ARRAY_A ), 'n', 'priority' ); // phpcs:ignore
		$by    = wp_list_pluck( (array) $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} GROUP BY status", ARRAY_A ), 'n', 'status' ); // phpcs:ignore
		$today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE status = 'sent' AND sent_at >= %s", get_gmt_from_date( current_time( 'Y-m-d' ) . ' 00:00:00' ) ) ); // phpcs:ignore
		$last  = (array) get_option( 'aw_mail_last_error', array() );
		$pause = aw_mail_paused();
		$old   = $wpdb->get_var( "SELECT MIN(created_at) FROM {$t} WHERE status = 'queued'" ); // phpcs:ignore
		$stats = (array) get_option( 'aw_mail_stats', array() );
		$note  = sanitize_key( $_GET['aw_cap'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<hr><h2 id="cap">Daily sending limit</h2>';
		if ( 'saved' === $note ) {
			echo '<div class="notice notice-success inline"><p>Saved.</p></div>';
		} elseif ( 'resumed' === $note ) {
			echo '<div class="notice notice-success inline"><p>Sending resumed.</p></div>';
		} elseif ( 'retry' === $note ) {
			echo '<div class="notice notice-success inline"><p>Held emails will be tried again.</p></div>';
		} elseif ( 'flushed' === $note ) {
			echo '<div class="notice notice-success inline"><p>Sent ' . (int) ( $_GET['n'] ?? 0 ) . ' queued emails.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( $pause ) {
			echo '<div class="notice notice-error inline"><p><strong>Sending is paused.</strong> The mail server refused an email on ' . esc_html( wp_date( 'j M Y, H:i', (int) $pause['at'] ) ) . ': ' . esc_html( (string) $pause['error'] ) . '. Nothing is being sent and nothing is lost; emails wait in the queue. Resume only when the mailbox is working again.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'aw_mail_cap' );
			echo '<input type="hidden" name="action" value="aw_mail_cap"><p><button class="button button-primary" name="do" value="resume">Resume sending</button></p></form></div>';
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
			'Queued (waiting for the limit or a slot)' => (int) ( $by['queued'] ?? 0 ),
			'Failed, will be retried'                => (int) ( $by['failed'] ?? 0 ),
			'Held after 4 failed tries'              => (int) ( $by['held'] ?? 0 ),
			'Sent today'                             => $today,
			'Last error'                             => $last ? wp_date( 'j M H:i', (int) $last['at'] ) . ': ' . $last['error'] : '–',
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
		echo '</table><p><button class="button button-primary" name="do" value="save">Save limit</button> <button class="button" name="do" value="flush">Send queued emails now (within the limit)</button> <button class="button" name="do" value="retry">Retry held emails</button></p></form>';
	}
);

add_action(
	'admin_post_aw_mail_cap',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_mail_cap' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		if ( 'resume' === ( $_POST['do'] ?? '' ) ) {
			delete_option( 'aw_mail_pause' );
			wp_safe_redirect( admin_url( 'options-general.php?page=kits-mail&aw_cap=resumed#cap' ) );
			exit;
		}
		if ( 'retry' === ( $_POST['do'] ?? '' ) ) {
			global $wpdb;
			$wpdb->query( "UPDATE " . aw_mail_table() . " SET status = 'failed', tries = 0, next_try = NULL WHERE status = 'held' AND mail IS NOT NULL" ); // phpcs:ignore
			wp_safe_redirect( admin_url( 'options-general.php?page=kits-mail&aw_cap=retry#cap' ) );
			exit;
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

/* ---------- When sending is paused: admin notice (everywhere once a day; always on Dashboard, Mail and Check site) and a Check site entry. No email is used to tell anyone. ---------- */

add_action(
	'admin_notices',
	function () {
		$pause = aw_mail_paused();
		if ( ! $pause || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		$always = in_array( $id, array( 'dashboard', 'settings_page_kits-mail', 'tools_page_aw-site-check' ), true );
		if ( ! $always ) {
			if ( get_transient( 'aw_mail_pause_seen' ) ) {
				return;
			}
			set_transient( 'aw_mail_pause_seen', 1, DAY_IN_SECONDS );
		}
		echo '<div class="notice notice-error"><p><strong>Email sending is paused.</strong> The mail server refused an email (' . esc_html( (string) $pause['error'] ) . '). Form confirmations, volunteer emails and newsletters are waiting, not lost. <a href="' . esc_url( admin_url( 'options-general.php?page=kits-mail#cap' ) ) . '">Open Settings → Mail</a> to resume once the mailbox works.</p></div>';
	}
);

add_action(
	'aw_site_check_after',
	function () {
		$pause = aw_mail_paused();
		echo '<div class="notice ' . ( $pause ? 'notice-error' : 'notice-success' ) . ' inline"><p><strong>Email sending: ' . ( $pause ? 'PAUSED' : 'running' ) . '.</strong> ';
		if ( $pause ) {
			echo esc_html( 'Refused ' . wp_date( 'j M Y, H:i', (int) $pause['at'] ) . ': ' . $pause['error'] ) . ' <a href="' . esc_url( admin_url( 'options-general.php?page=kits-mail#cap' ) ) . '">Resume on Settings → Mail</a>.';
		} else {
			$last = (array) get_option( 'aw_mail_last_error', array() );
			echo $last ? esc_html( 'Last error ' . wp_date( 'j M H:i', (int) $last['at'] ) . ': ' . $last['error'] ) : 'No errors recorded.';
		}
		echo '</p></div>';
	}
);
