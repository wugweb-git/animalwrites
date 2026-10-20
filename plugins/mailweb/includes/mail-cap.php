<?php
/**
 * Daily sending limit and queue. Mail plans limit how many emails a mailbox may send in a rolling 24 hours (Hostinger's
 * free Business Email: 100). The site sends at most the limit set in Mail → Settings → Site sending (default 90).
 *
 * How it works
 * - Every wp_mail goes through pre_wp_mail here. If the rolling-24-hour count is under the limit and nothing of the same
 *   or higher priority is already waiting, the email is logged and sent at once.
 * - Otherwise it is queued in {prefix}mailweb_queue (to, subject, body, headers, attachment paths) and wp_mail returns
 *   true, so no sender ever sees a failure. WP-Cron (every 5 minutes) sends the queue as the limit frees: highest
 *   priority first, oldest first within a priority. An email that keeps failing is held (shown on the settings screen),
 *   never dropped.
 * - Priorities: 0 replies (an "X-Mailweb-Priority: 0" header, removed before sending), 1 notices (everything not listed
 *   here), 2 sign-up confirmations (Fluent Forms), 3 FluentCRM campaign emails.
 * - Campaign emails may use the limit minus a reserve (default 10), so notices and confirmations always have room.
 * - FluentCRM: sending speed 1 per second; its sender does not start while the campaign share is used up or campaign
 *   emails wait in the queue (not while FluentCRM sends one contact's emails at once), so the rest of a campaign stays
 *   in FluentCRM's own queue and goes out on the following days.
 * - If the mail server refuses an email as blocked, sending pauses until an administrator resumes it.
 * After sending, a queued email's content is cleared; the log keeps time, priority and status (30 days).
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAILWEB_DB_VERSION = '2';
const MAILWEB_TRIES      = 4; // attempts before an email is held: retries after 15 minutes, 1 hour, 6 hours

function mailweb_table() {
	global $wpdb;
	return $wpdb->prefix . 'mailweb_queue';
}

/** Settings: budget per rolling 24 hours, and the part kept back from campaigns. */
function mailweb_cap_settings() {
	$s = wp_parse_args( (array) get_option( 'mailweb_cap', array() ), array( 'budget' => 90, 'reserve' => 10 ) );
	$s['budget']  = max( 1, (int) $s['budget'] );
	$s['reserve'] = min( $s['budget'] - 1, max( 0, (int) $s['reserve'] ) );
	return $s;
}

add_action(
	'init',
	function () {
		if ( MAILWEB_DB_VERSION === get_option( 'mailweb_queue_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . mailweb_table() . " (
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
		$wpdb->query( "UPDATE " . mailweb_table() . " SET status = 'held' WHERE status = 'failed' AND next_try IS NULL" ); // phpcs:ignore -- failures of the old kind wait for a manual retry
		update_option( 'mailweb_queue_db', MAILWEB_DB_VERSION, false );
	},
	5
);


/* ---------- Failures: a send counts as sent only when the mail server accepted it ---------- */

/** The pause record (array) while sending is stopped, else false. */
function mailweb_paused() {
	return get_option( 'mailweb_pause', false );
}

/** Error text for the log: no addresses, one line, short. */
function mailweb_clean_error( $msg ) {
	$msg = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[address]', wp_strip_all_tags( (string) $msg ) );
	return mb_substr( trim( preg_replace( '/\s+/', ' ', $msg ) ), 0, 250 );
}

/** Errors that mean the mailbox itself is refused (blocked, disabled, suspended, sign-in refused): stop everything. */
function mailweb_is_block_error( $msg ) {
	return (bool) preg_match( '/\b5\.7\.\d+|\b554\b|\b550\b|\b535\b|disabled|blocked|suspend|abuse|not allowed|authenticat|too many|rate limit|quota/i', (string) $msg );
}

/** Stop all sending until someone presses Resume. No email is used to tell anyone. */
function mailweb_pause( $msg ) {
	if ( ! mailweb_paused() ) {
		update_option( 'mailweb_pause', array( 'at' => time(), 'error' => $msg ), false );
		mailweb_stat( 'paused' );
	}
}

/** Record a failed attempt on queue row $id: retry later (15 min, 1 h, 6 h), then hold. Mail content stays. */
function mailweb_mark_failed( $id, $msg ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT tries FROM ' . mailweb_table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	if ( ! $row ) {
		return;
	}
	$tries = (int) $row->tries + 1;
	$wait  = array( 1 => 15 * MINUTE_IN_SECONDS, 2 => HOUR_IN_SECONDS, 3 => 6 * HOUR_IN_SECONDS );
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own queue table
		mailweb_table(),
		array(
			'tries'    => $tries,
			'status'   => $tries >= MAILWEB_TRIES ? 'held' : 'failed',
			'error'    => $msg,
			'next_try' => $tries >= MAILWEB_TRIES ? null : gmdate( 'Y-m-d H:i:s', time() + $wait[ $tries ] ),
		),
		array( 'id' => (int) $id )
	); // phpcs:ignore
	update_option( 'mailweb_last_error', array( 'at' => time(), 'error' => $msg ), false );
	mailweb_stat( 'rejected' );
	if ( mailweb_is_block_error( $msg ) ) {
		mailweb_pause( $msg );
	}
}

// PHPMailer's error, for the send in progress (queue sends read it after wp_mail returns; direct sends are handled here).
add_action(
	'wp_mail_failed',
	function ( $err ) {
		$msg = mailweb_clean_error( is_wp_error( $err ) ? $err->get_error_message() : 'Send failed' );
		$GLOBALS['mailweb_last_error'] = '' === $msg ? 'Send failed' : $msg;
		$id = (int) ( $GLOBALS['mailweb_direct_id'] ?? 0 );
		if ( $id ) {
			unset( $GLOBALS['mailweb_direct_id'] );
			mailweb_mark_failed( $id, $GLOBALS['mailweb_last_error'] );
		}
	},
	5
);

add_action(
	'wp_mail_succeeded',
	function () {
		$id = (int) ( $GLOBALS['mailweb_direct_id'] ?? 0 );
		if ( $id ) {
			unset( $GLOBALS['mailweb_direct_id'] );
			global $wpdb;
			$wpdb->update( mailweb_table(), array( 'status' => 'sent', 'sent_at' => gmdate( 'Y-m-d H:i:s' ), 'mail' => null, 'error' => null ), array( 'id' => $id ) ); // phpcs:ignore
		}
	},
	5
);

/** Emails sent (or being sent) in the last 24 hours. */
function mailweb_sent_24h() {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . mailweb_table() . " WHERE ( status = 'sent' AND sent_at > %s ) OR status = 'sending'", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
}

/** Queued emails, all or of priority $max and higher (1 is highest). */
function mailweb_queued( $max = 3 ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . mailweb_table() . " WHERE status = 'queued' AND priority <= %d", $max ) ); // phpcs:ignore
}

/** How many emails a priority may still send now. */
function mailweb_room( $priority ) {
	$s = mailweb_cap_settings();
	return max( 0, ( 3 === $priority ? $s['budget'] - $s['reserve'] : $s['budget'] ) - mailweb_sent_24h() );
}

/** 0 replies from FluentCRM, 1 notices, 2 sign-up confirmations (Fluent Forms), 3 FluentCRM campaigns. */
function mailweb_priority() {
	if ( isset( $GLOBALS['mailweb_next_priority'] ) ) { // set from the email's own X-Mailweb-Priority header (below)
		return (int) $GLOBALS['mailweb_next_priority'];
	}
	if ( ! empty( $GLOBALS['mailweb_priority'] ) ) {
		return (int) $GLOBALS['mailweb_priority'];
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

function mailweb_stat( $key, $n = 1 ) {
	$s         = (array) get_option( 'mailweb_stats', array() );
	$s[ $key ] = (int) ( $s[ $key ] ?? 0 ) + $n;
	update_option( 'mailweb_stats', $s, false );
}

// An email may carry its priority as an "X-Mailweb-Priority: n" header: it is read here and removed before sending.
add_filter(
	'wp_mail',
	function ( $atts ) {
		unset( $GLOBALS['mailweb_next_priority'] );
		$h    = $atts['headers'] ?? '';
		$list = is_array( $h ) ? $h : explode( "\n", str_replace( "\r\n", "\n", (string) $h ) );
		$keep = array();
		foreach ( $list as $line ) {
			if ( is_string( $line ) && preg_match( '/^X-Mailweb-Priority:\s*([0-3])\s*$/i', trim( $line ), $m ) ) {
				$GLOBALS['mailweb_next_priority'] = (int) $m[1];
				continue;
			}
			$keep[] = $line;
		}
		if ( isset( $GLOBALS['mailweb_next_priority'] ) ) {
			$atts['headers'] = is_array( $h ) ? $keep : implode( "\n", $keep );
		}
		return $atts;
	},
	PHP_INT_MAX
);

add_filter(
	'pre_wp_mail',
	function ( $return, $atts ) {
		$p = mailweb_priority();
		unset( $GLOBALS['mailweb_next_priority'], $GLOBALS['mailweb_direct_id'], $GLOBALS['mailweb_last_error'] ); // for this email only
		if ( null !== $return || ! empty( $GLOBALS['mailweb_flushing'] ) ) {
			return $return; // another filter decided, or this is the queue sending
		}
		global $wpdb;
		$now    = gmdate( 'Y-m-d H:i:s' );
		$resend = ! empty( $GLOBALS['mailweb_resend'] ) ? 1 : 0; // a re-send of a lost email: always through the queue, 5 an hour
		$mail   = array(
			'to'          => $atts['to'] ?? '',
			'subject'     => $atts['subject'] ?? '',
			'message'     => $atts['message'] ?? '',
			'headers'     => $atts['headers'] ?? '',
			'attachments' => array_values( array_filter( (array) ( $atts['attachments'] ?? array() ), 'is_string' ) ),
		);
		$json = wp_json_encode( $mail );
		if ( ! $resend && ! mailweb_paused() && mailweb_room( $p ) > 0 && 0 === mailweb_queued( $p ) ) {
			// Sent at once, but kept as "sending" with its content until the mail server accepts it.
			$ok = $json ? $wpdb->insert( mailweb_table(), array( 'priority' => $p, 'status' => 'sending', 'mail' => $json, 'created_at' => $now ) ) : false; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $ok ) {
				$GLOBALS['mailweb_direct_id'] = (int) $wpdb->insert_id;
				mailweb_stat( 'sent_direct' );
			}
			return null; // wp_mail sends it now
		}
		$ok = $wpdb->insert( mailweb_table(), array( 'priority' => $p, 'status' => 'queued', 'mail' => $json, 'created_at' => $now, 'resend' => $resend ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $ok ) {
			return null; // could not queue: send rather than lose it
		}
		mailweb_stat( 'queued' );
		return true;
	},
	20,
	2
);

/** Send queued emails while the budget allows (highest priority, then oldest first). Stops at once if sending is paused. */
function mailweb_flush( $max = 40 ) {
	global $wpdb;
	if ( mailweb_paused() || get_transient( 'mailweb_flush_lock' ) ) {
		return 0;
	}
	set_transient( 'mailweb_flush_lock', 1, 5 * MINUTE_IN_SECONDS );
	$t    = mailweb_table();
	$sent = 0;
	// A send that never reported back (crash, time-out): retry it, never lose it.
	$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'failed', tries = tries + 1, error = 'No confirmation from the mail server', next_try = %s WHERE status = 'sending' AND created_at < %s AND mail IS NOT NULL", gmdate( 'Y-m-d H:i:s', time() + 15 * MINUTE_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore
	for ( $i = 0; $i < $max; $i++ ) {
		if ( mailweb_paused() ) {
			break;
		}
		$camp = mailweb_room( 3 ) > 0 ? 3 : 2;
		if ( mailweb_room( 1 ) < 1 ) {
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
		unset( $GLOBALS['mailweb_last_error'], $GLOBALS['mailweb_direct_id'] );
		$GLOBALS['mailweb_flushing'] = true;
		$ok = wp_mail( $m['to'], (string) ( $m['subject'] ?? '' ), (string) ( $m['message'] ?? '' ), $m['headers'] ?? '', $files );
		$GLOBALS['mailweb_flushing'] = false;
		$err = $GLOBALS['mailweb_last_error'] ?? '';
		if ( $ok && '' === $err ) {
			$wpdb->update( $t, array( 'status' => 'sent', 'sent_at' => gmdate( 'Y-m-d H:i:s' ), 'mail' => null, 'error' => null ), array( 'id' => (int) $row->id ) ); // phpcs:ignore
			mailweb_stat( 'sent_from_queue' );
			++$sent;
		} else {
			$wpdb->update( $t, array( 'sent_at' => null ), array( 'id' => (int) $row->id ) ); // phpcs:ignore
			mailweb_mark_failed( (int) $row->id, '' !== $err ? $err : 'Send failed' );
			break; // try again on the next run (not at all if sending was paused)
		}
	}
	// The log keeps 30 days (time, priority, status only once sent).
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE status = 'sent' AND sent_at < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
	delete_transient( 'mailweb_flush_lock' );
	return $sent;
}

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['mailweb_5min'] = array( 'interval' => 300, 'display' => __( 'Every 5 minutes', 'mailweb' ) );
		return $s;
	}
);
add_action( 'mailweb_flush', 'mailweb_flush' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'mailweb_flush' ) ) {
			wp_schedule_event( time() + 60, 'mailweb_5min', 'mailweb_flush' );
		}
	}
);

/* ---------- FluentCRM: slowest speed, and campaigns wait for the budget ---------- */

add_filter( 'fluent_crm/global_email_limit_per_second', fn() => 1 );
add_filter( 'fluent_crm/disable_email_processing', fn( $off ) => $off || (bool) mailweb_paused() || ( empty( $GLOBALS['mailweb_crm_contact_job'] ) && ( mailweb_room( 3 ) < 1 || mailweb_queued( 3 ) > 0 ) ) );
add_filter( 'fluent_crm/mailer_handler_chunk_size', fn( $n ) => max( 1, min( (int) $n, mailweb_room( 3 ) ) ) );

/* ---------- Mail → Settings → Site sending: the limit, today's count and the queue ---------- */

add_action(
	'mailweb_smtp_screen_after',
	function () {
		global $wpdb;
		$s     = mailweb_cap_settings();
		$t     = mailweb_table();
		$q     = wp_list_pluck( (array) $wpdb->get_results( "SELECT priority, COUNT(*) n FROM {$t} WHERE status = 'queued' GROUP BY priority", ARRAY_A ), 'n', 'priority' ); // phpcs:ignore
		$by    = wp_list_pluck( (array) $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} GROUP BY status", ARRAY_A ), 'n', 'status' ); // phpcs:ignore
		$today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE status = 'sent' AND sent_at >= %s", get_gmt_from_date( current_time( 'Y-m-d' ) . ' 00:00:00' ) ) ); // phpcs:ignore
		$last  = (array) get_option( 'mailweb_last_error', array() );
		$pause = mailweb_paused();
		$old   = $wpdb->get_var( "SELECT MIN(created_at) FROM {$t} WHERE status = 'queued'" ); // phpcs:ignore
		$stats = (array) get_option( 'mailweb_stats', array() );
		$note  = sanitize_key( $_GET['mailweb_cap'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<hr><h2 id="cap">' . esc_html__( 'Daily sending limit', 'mailweb' ) . '</h2>';
		if ( 'saved' === $note ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Saved.', 'mailweb' ) . '</p></div>';
		} elseif ( 'resumed' === $note ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Sending resumed.', 'mailweb' ) . '</p></div>';
		} elseif ( 'retry' === $note ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Held emails will be tried again.', 'mailweb' ) . '</p></div>';
		} elseif ( 'flushed' === $note ) {
			$sent_n = absint( wp_unslash( $_GET['n'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a count in the redirect after the nonced action
			/* translators: %d: number of queued emails sent */
			echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( _n( 'Sent %d queued email.', 'Sent %d queued emails.', $sent_n, 'mailweb' ), $sent_n ) ) . '</p></div>';
		}
		if ( $pause ) {
			echo '<div class="notice notice-error inline"><p><strong>' . esc_html__( 'Sending is paused.', 'mailweb' ) . '</strong> ' . esc_html(
				sprintf(
					/* translators: 1: date and time of the refusal, 2: the mail server's error message */
					__( 'The mail server refused an email on %1$s: %2$s. Nothing is being sent and nothing is lost; emails wait in the queue. Resume only when the mailbox is working again.', 'mailweb' ),
					wp_date( 'j M Y, H:i', (int) $pause['at'] ),
					(string) $pause['error']
				)
			) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'mailweb_cap' );
			echo '<input type="hidden" name="action" value="mailweb_cap"><p><button class="button button-primary" name="do" value="resume">' . esc_html__( 'Resume sending', 'mailweb' ) . '</button></p></form></div>';
		}
		echo '<p>' . esc_html__( 'Mail plans limit how many emails a mailbox may send in any 24 hours. The site keeps under the limit below; anything over it waits in the queue and is sent as the limit frees: replies typed in FluentCRM first, then notices, then sign-up confirmations, then newsletter campaigns.', 'mailweb' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:40rem"><tbody>';
		$rows = array(
			__( 'Sent in the last 24 hours', 'mailweb' )                 => sprintf(
				/* translators: 1: emails sent in the last 24 hours, 2: the daily limit */
				__( '%1$d of %2$d', 'mailweb' ),
				mailweb_sent_24h(),
				$s['budget']
			),
			__( 'Waiting: replies from FluentCRM', 'mailweb' )           => (int) ( $q[0] ?? 0 ),
			__( 'Waiting: notices', 'mailweb' )                          => (int) ( $q[1] ?? 0 ),
			__( 'Waiting: sign-up confirmations', 'mailweb' )            => (int) ( $q[2] ?? 0 ),
			__( 'Waiting: newsletter campaigns', 'mailweb' )             => (int) ( $q[3] ?? 0 ),
			__( 'Oldest waiting since (UTC)', 'mailweb' )                => $old ? $old : '–',
			__( 'Queued (waiting for the limit or a slot)', 'mailweb' )  => (int) ( $by['queued'] ?? 0 ),
			__( 'Failed, will be retried', 'mailweb' )                   => (int) ( $by['failed'] ?? 0 ),
			__( 'Held after 4 failed tries', 'mailweb' )                 => (int) ( $by['held'] ?? 0 ),
			__( 'Sent today', 'mailweb' )                                => $today,
			__( 'Last error', 'mailweb' )                                => $last ? wp_date( 'j M H:i', (int) $last['at'] ) . ': ' . $last['error'] : '–',
			__( 'All time: sent at once / queued / sent from the queue', 'mailweb' ) => (int) ( $stats['sent_direct'] ?? 0 ) . ' / ' . (int) ( $stats['queued'] ?? 0 ) . ' / ' . (int) ( $stats['sent_from_queue'] ?? 0 ),
		);
		foreach ( $rows as $k => $v ) {
			echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mailweb_cap' );
		echo '<input type="hidden" name="action" value="mailweb_cap"><table class="form-table">';
		echo '<tr><th scope="row"><label for="mailweb-cap-budget">' . esc_html__( 'Emails per 24 hours', 'mailweb' ) . '</label></th><td><input id="mailweb-cap-budget" name="budget" type="number" min="1" max="10000" value="' . (int) $s['budget'] . '"><p class="description">' . esc_html__( 'Your mail plan\'s daily limit, less a margin (Hostinger Business Email free: 100, so 90).', 'mailweb' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="mailweb-cap-reserve">' . esc_html__( 'Kept back from campaigns', 'mailweb' ) . '</label></th><td><input id="mailweb-cap-reserve" name="reserve" type="number" min="0" max="99" value="' . (int) $s['reserve'] . '"><p class="description">' . esc_html__( 'Campaigns use at most the limit minus this, so notices and confirmations always have room.', 'mailweb' ) . '</p></td></tr>';
		echo '</table><p><button class="button button-primary" name="do" value="save">' . esc_html__( 'Save limit', 'mailweb' ) . '</button> <button class="button" name="do" value="flush">' . esc_html__( 'Send queued emails now (within the limit)', 'mailweb' ) . '</button> <button class="button" name="do" value="retry">' . esc_html__( 'Retry held emails', 'mailweb' ) . '</button></p></form>';
	}
);

add_action(
	'admin_post_mailweb_cap',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mailweb_cap' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mailweb' ), 403 );
		}
		$do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		if ( 'resume' === $do ) {
			delete_option( 'mailweb_pause' );
			wp_safe_redirect( mailweb_settings_url( 'sending', array( 'mailweb_cap' => 'resumed' ), 'cap' ) );
			exit;
		}
		if ( 'retry' === $do ) {
			global $wpdb;
			$wpdb->query( "UPDATE " . mailweb_table() . " SET status = 'failed', tries = 0, next_try = NULL WHERE status = 'held' AND mail IS NOT NULL" ); // phpcs:ignore
			wp_safe_redirect( mailweb_settings_url( 'sending', array( 'mailweb_cap' => 'retry' ), 'cap' ) );
			exit;
		}
		if ( 'flush' === $do ) {
			$n = mailweb_flush();
			wp_safe_redirect( mailweb_settings_url( 'sending', array( 'mailweb_cap' => 'flushed', 'n' => (int) $n ), 'cap' ) );
			exit;
		}
		update_option( 'mailweb_cap', array( 'budget' => min( 10000, max( 1, absint( $_POST['budget'] ?? 90 ) ) ), 'reserve' => absint( $_POST['reserve'] ?? 10 ) ), false );
		wp_safe_redirect( mailweb_settings_url( 'sending', array( 'mailweb_cap' => 'saved' ), 'cap' ) );
		exit;
	}
);

/* ---------- When sending is paused: an admin notice (everywhere once a day; always on the Dashboard and Mail). No email is used to tell anyone. ---------- */

add_action(
	'admin_notices',
	function () {
		$pause = mailweb_paused();
		if ( ! $pause || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		$always = in_array( $id, array( 'dashboard', 'toplevel_page_mailweb' ), true ) || (bool) apply_filters( 'mailweb_pause_notice_always', false, $id );
		if ( ! $always ) {
			if ( get_transient( 'mailweb_pause_seen' ) ) {
				return;
			}
			set_transient( 'mailweb_pause_seen', 1, DAY_IN_SECONDS );
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Email sending is paused.', 'mailweb' ) . '</strong> ' . esc_html(
			sprintf(
				/* translators: %s: the mail server's error message */
				__( 'The mail server refused an email (%s). Emails are waiting in the queue, not lost.', 'mailweb' ),
				(string) $pause['error']
			)
		) . ' ' . sprintf(
			/* translators: %s: link to Mail → Settings → Site sending */
			esc_html__( '%s to resume once the mailbox works.', 'mailweb' ),
			'<a href="' . esc_url( mailweb_settings_url( 'sending', array(), 'cap' ) ) . '">' . esc_html__( 'Open Mail → Settings → Site sending', 'mailweb' ) . '</a>'
		) . '</p></div>';
	}
);
