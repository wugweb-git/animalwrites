<?php
/**
 * MailWeb Dashboard counts (docs/mailweb-plan.md §2): built by the job mailweb_dashboard_build, never while a screen
 * loads. It runs hourly, and once more when the Dashboard asks (its first view, Count now, or counts older than
 * 15 minutes). It keeps the newest counts in the transient mailweb_dashboard and one row a day in the option
 * mailweb_dashboard_log (not autoloaded, 120 days), which the Dashboard's time range compares against.
 *
 * What it reads: each mailbox's Inbox through mailweb_overview_box() (the Mail API, cached 5 minutes, as the menu badge
 * and the WordPress Dashboard widget already do), the website forms source, and the queue table. It sends nothing,
 * marks nothing and moves nothing.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAILWEB_DASH          = 'mailweb_dashboard';
const MAILWEB_DASH_LOG      = 'mailweb_dashboard_log';
const MAILWEB_DASH_JOB      = 'mailweb_dashboard_build';
const MAILWEB_DASH_LOG_DAYS = 120;
const MAILWEB_DASH_FRESH    = 15 * MINUTE_IN_SECONDS;

add_action( MAILWEB_DASH_JOB, 'mailweb_dashboard_build' );

// Hourly, scheduled from an admin request, so a visitor's page view never schedules it.
add_action(
	'admin_init',
	function () {
		if ( ! wp_next_scheduled( MAILWEB_DASH_JOB ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', MAILWEB_DASH_JOB );
		}
	}
);

/**
 * Ask for a count now: one single event, never a count in this request.
 *
 * @return bool Whether a count is waiting to run.
 */
function mailweb_dashboard_request(): bool {
	if ( wp_next_scheduled( MAILWEB_DASH_JOB, array( 'now' ) ) ) {
		return true;
	}
	return false !== wp_schedule_single_event( time(), MAILWEB_DASH_JOB, array( 'now' ) );
}

/**
 * The job: every count, then the day's row in the log.
 *
 * @param string $why 'now' when the Dashboard asked, '' on the hourly run.
 * @return array The counts.
 */
function mailweb_dashboard_build( $why = '' ) {
	unset( $why );
	$c = array_merge(
		array(
			'at'    => time(),
			'boxes' => mailweb_dashboard_boxes(),
			'forms' => mailweb_forms() ? array(
				'label'  => (string) mailweb_forms()['label'],
				'unread' => (int) mailweb_forms_unread(),
			) : null,
		),
		mailweb_dashboard_queue()
	);
	set_transient( MAILWEB_DASH, $c, 3 * HOUR_IN_SECONDS );
	mailweb_dashboard_log( $c );
	return $c;
}

/**
 * Each mailbox: address, aliases, unread, whether the figures are the last good ones, and the newest five (sender,
 * subject, date, unread; no message text).
 *
 * @return array key => box.
 */
function mailweb_dashboard_boxes(): array {
	$out = array();
	foreach ( mailweb_all_boxes() as $key => $addr ) {
		$box         = mailweb_overview_box( $key );
		$out[ $key ] = array(
			'address' => $addr,
			'aliases' => mailweb_aliases_of( $addr ),
			'ok'      => is_array( $box ),
			'unread'  => is_array( $box ) ? (int) ( $box['unread'] ?? 0 ) : null,
			'stale'   => is_array( $box ) && ! empty( $box['stale'] ),
			'staleAt' => is_array( $box ) ? (int) ( $box['at'] ?? 0 ) : 0,
			'newest'  => array_map(
				fn( $m ) => array(
					'from'    => (string) ( $m['name'] ?? $m['from'] ?? '' ),
					'subject' => (string) ( $m['subject'] ?? '' ),
					'date'    => (string) ( $m['date'] ?? '' ),
					'unseen'  => ! empty( $m['unseen'] ),
				),
				array_slice( is_array( $box ) ? (array) ( $box['messages'] ?? array() ) : array(), 0, 5 )
			),
		);
	}
	return $out;
}

/**
 * The queue's numbers, from its table (mail-cap.php): sent, waiting by priority, failed and held, sent per day.
 */
function mailweb_dashboard_queue(): array {
	global $wpdb;
	$t   = mailweb_table();
	$now = time();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- background counts on the plugin's own table
	$by     = wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) n FROM %i GROUP BY status', $t ), ARRAY_A ), 'n', 'status' );
	$queued = wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( "SELECT priority, COUNT(*) n FROM %i WHERE status = 'queued' GROUP BY priority", $t ), ARRAY_A ), 'n', 'priority' );
	$oldest = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MIN(created_at) FROM %i WHERE status = 'queued'", $t ) );
	$next   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MIN(next_try) FROM %i WHERE status = 'failed'", $t ) );
	$prev   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'sent' AND sent_at > %s AND sent_at <= %s", $t, gmdate( 'Y-m-d H:i:s', $now - 2 * DAY_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS ) ) );
	$times  = (array) $wpdb->get_col( $wpdb->prepare( "SELECT sent_at FROM %i WHERE status = 'sent' AND sent_at > %s", $t, gmdate( 'Y-m-d H:i:s', $now - 30 * DAY_IN_SECONDS ) ) );
	$held   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT created_at, priority, tries, error FROM %i WHERE status = 'held' ORDER BY id DESC LIMIT 20", $t ), ARRAY_A );
	// phpcs:enable
	$days = array();
	foreach ( $times as $gmt ) {
		$day          = wp_date( 'Y-m-d', (int) strtotime( $gmt . ' UTC' ) );
		$days[ $day ] = ( $days[ $day ] ?? 0 ) + 1;
	}
	return array(
		'sent24'   => mailweb_sent_24h(),
		'sentPrev' => $prev,
		'room1'    => mailweb_room( 1 ),
		'room3'    => mailweb_room( 3 ),
		'queued'   => array_map( fn( $p ) => (int) ( $queued[ $p ] ?? 0 ), array( 0, 1, 2, 3 ) ),
		'oldest'   => '' !== $oldest ? (int) strtotime( $oldest . ' UTC' ) : 0,
		'failed'   => (int) ( $by['failed'] ?? 0 ),
		'held'     => (int) ( $by['held'] ?? 0 ),
		'nextTry'  => '' !== $next ? (int) strtotime( $next . ' UTC' ) : 0,
		'heldRows' => array_map(
			fn( $r ) => array(
				'at'       => (int) strtotime( $r['created_at'] . ' UTC' ),
				'priority' => (int) $r['priority'],
				'tries'    => (int) $r['tries'],
				'error'    => (string) $r['error'],
			),
			$held
		),
		'perDay'   => $days,
		'stats'    => (array) get_option( 'mailweb_stats', array() ),
	);
}

/**
 * Keep the day's numbers (the last run of the day wins), for the Dashboard's time range.
 *
 * @param array $c Counts from mailweb_dashboard_build().
 */
function mailweb_dashboard_log( array $c ): void {
	$log                       = (array) get_option( MAILWEB_DASH_LOG, array() );
	$log[ wp_date( 'Y-m-d' ) ] = mailweb_dashboard_numbers( $c );
	ksort( $log );
	$log = array_slice( $log, -MAILWEB_DASH_LOG_DAYS, null, true );
	update_option( MAILWEB_DASH_LOG, $log, false );
}

/**
 * The numbers the tiles compare over time.
 *
 * @param array $c Counts.
 * @return array metric => number.
 */
function mailweb_dashboard_numbers( array $c ): array {
	$unread = 0;
	foreach ( (array) ( $c['boxes'] ?? array() ) as $b ) {
		$unread += (int) ( $b['unread'] ?? 0 );
	}
	$n = array(
		'unread'  => $unread,
		'sent24'  => (int) ( $c['sent24'] ?? 0 ),
		'waiting' => (int) array_sum( (array) ( $c['queued'] ?? array() ) ),
		'failed'  => (int) ( $c['failed'] ?? 0 ) + (int) ( $c['held'] ?? 0 ),
		'sent'    => (int) ( ( (array) ( $c['perDay'] ?? array() ) )[ wp_date( 'Y-m-d' ) ] ?? 0 ),
	);
	if ( is_array( $c['forms'] ?? null ) ) {
		$n['forms'] = (int) $c['forms']['unread'];
	}
	return $n;
}

/**
 * The Dashboard's tiles from the stored counts plus three stored states read as they are (paused, the SMTP mailbox,
 * the Inbox send log). Formatting only.
 *
 * @param array $c    Counts (empty while the first count runs).
 * @param array $live paused (array|false), smtp { user, host, port, pass: set|unset|config }, replies (int), budget
 *                    (int), urls { inbox, forms, sending, status }.
 * @return array Tiles: key, label, value, raw, sub, href, tone, metric, hide.
 */
function mailweb_dashboard_tiles( array $c, array $live ): array {
	$num   = fn( $n ) => number_format_i18n( (int) $n );
	$tiles = array();

	$boxes  = (array) ( $c['boxes'] ?? array() );
	$unread = 0;
	$parts  = array();
	$down   = 0;
	$stale  = 0;
	foreach ( $boxes as $b ) {
		$unread += (int) ( $b['unread'] ?? 0 );
		$local   = strtok( (string) $b['address'], '@' ) . '@';
		if ( empty( $b['ok'] ) ) {
			++$down;
			/* translators: %s: mailbox, as "support@". */
			$parts[] = sprintf( __( '%s not reachable', 'mailweb' ), $local );
			continue;
		}
		if ( ! empty( $b['stale'] ) ) {
			$stale = max( $stale, (int) $b['staleAt'] );
		}
		$parts[] = $local . ' ' . $num( $b['unread'] );
	}
	$sub = $boxes ? implode( ' · ', $parts ) : __( 'No mailboxes yet: add the Mail API token in Settings', 'mailweb' );
	if ( $stale ) {
		/* translators: %s: time of the last good answer. */
		$sub .= '. ' . sprintf( __( 'Shown from %s', 'mailweb' ), wp_date( 'g:i a', $stale ) );
	}
	$tiles[] = array(
		'key'    => 'unread',
		'label'  => __( 'Unread mail', 'mailweb' ),
		'value'  => $num( $unread ),
		'raw'    => $unread,
		'sub'    => $sub,
		'href'   => $live['urls']['inbox'] ?? '',
		'tone'   => $down ? 'warn' : '',
		'metric' => 'unread',
	);

	$forms   = $c['forms'] ?? null;
	$tiles[] = array(
		'key'    => 'forms',
		'label'  => is_array( $forms ) ? (string) $forms['label'] : __( 'Website forms', 'mailweb' ),
		'value'  => $num( is_array( $forms ) ? $forms['unread'] : 0 ),
		'raw'    => is_array( $forms ) ? (int) $forms['unread'] : 0,
		'sub'    => __( 'Unread entries', 'mailweb' ),
		'href'   => $live['urls']['forms'] ?? '',
		'tone'   => '',
		'metric' => 'forms',
		'hide'   => ! is_array( $forms ) && ! empty( $c ),
	);

	$budget  = (int) ( $live['budget'] ?? 90 );
	$sent    = (int) ( $c['sent24'] ?? 0 );
	$tiles[] = array(
		'key'    => 'sent24',
		'label'  => __( 'Sent in 24 hours', 'mailweb' ),
		/* translators: 1: emails sent, 2: the daily limit. */
		'value'  => sprintf( __( '%1$s of %2$s', 'mailweb' ), $num( $sent ), $num( $budget ) ),
		'raw'    => $sent,
		/* translators: 1: emails notices may still send, 2: emails campaigns may still send. */
		'sub'    => sprintf( __( 'Room left: notices %1$s · campaigns %2$s', 'mailweb' ), $num( $c['room1'] ?? 0 ), $num( $c['room3'] ?? 0 ) ),
		'href'   => $live['urls']['sending'] ?? '',
		'tone'   => $sent >= $budget ? 'warn' : '',
		'metric' => 'sent24',
	);

	$q       = array_map( 'intval', (array) ( $c['queued'] ?? array( 0, 0, 0, 0 ) ) );
	$waiting = (int) array_sum( $q );
	$sub     = sprintf(
		/* translators: 1: replies, 2: notices, 3: sign-up confirmations, 4: campaign emails waiting. */
		__( 'Replies %1$s · notices %2$s · confirmations %3$s · campaigns %4$s', 'mailweb' ),
		$num( $q[0] ?? 0 ),
		$num( $q[1] ?? 0 ),
		$num( $q[2] ?? 0 ),
		$num( $q[3] ?? 0 )
	);
	if ( ! empty( $c['oldest'] ) ) {
		/* translators: %s: date and time. */
		$sub .= '. ' . sprintf( __( 'Oldest since %s', 'mailweb' ), wp_date( 'j M, g:i a', (int) $c['oldest'] ) );
	}
	$tiles[] = array(
		'key'    => 'waiting',
		'label'  => __( 'Waiting to send', 'mailweb' ),
		'value'  => $num( $waiting ),
		'raw'    => $waiting,
		'sub'    => $sub,
		'href'   => '#queue',
		'tone'   => '',
		'metric' => 'waiting',
	);

	$failed  = (int) ( $c['failed'] ?? 0 );
	$held    = (int) ( $c['held'] ?? 0 );
	$last    = (array) ( $live['lastError'] ?? array() );
	$tiles[] = array(
		'key'    => 'failed',
		'label'  => __( 'Failed and held', 'mailweb' ),
		'value'  => $num( $failed + $held ),
		'raw'    => $failed + $held,
		/* translators: 1: emails to be tried again, 2: emails held after 4 tries. */
		'sub'    => sprintf( __( '%1$s will be tried again · %2$s held', 'mailweb' ), $num( $failed ), $num( $held ) ) . ( ! empty( $last['at'] ) ? '. ' . sprintf(
			/* translators: 1: date and time, 2: the error, without addresses. */
			__( 'Last error %1$s: %2$s', 'mailweb' ),
			wp_date( 'j M, g:i a', (int) $last['at'] ),
			(string) ( $last['error'] ?? '' )
		) : '' ),
		'href'   => '#sent',
		'tone'   => $held ? 'warn' : '',
		'metric' => 'failed',
	);

	$pause = $live['paused'] ?? false;
	$smtp  = (array) ( $live['smtp'] ?? array() );
	if ( $pause ) {
		$value = __( 'Paused', 'mailweb' );
		/* translators: 1: date and time, 2: the mail server's refusal. */
		$sub  = sprintf( __( 'Since %1$s: %2$s', 'mailweb' ), wp_date( 'j M, g:i a', (int) ( $pause['at'] ?? 0 ) ), (string) ( $pause['error'] ?? '' ) );
		$tone = 'error';
	} else {
		$value = __( 'Running', 'mailweb' );
		$sub   = 'unset' === ( $smtp['pass'] ?? 'unset' )
			? __( 'Server default: no SMTP password set', 'mailweb' )
			/* translators: 1: sending mailbox, 2: SMTP server, 3: port. */
			: sprintf( __( 'Through %1$s (%2$s:%3$s)', 'mailweb' ), (string) ( $smtp['user'] ?? '' ), (string) ( $smtp['host'] ?? '' ), (string) ( $smtp['port'] ?? '' ) );
		$tone = '';
	}
	$tiles[] = array(
		'key'   => 'sending',
		'label' => __( 'Sending', 'mailweb' ),
		'value' => $value,
		'sub'   => $sub,
		'href'  => '#status',
		'tone'  => $tone,
	);

	$replies = (int) ( $live['replies'] ?? 0 );
	$tiles[] = array(
		'key'   => 'replies',
		'label' => __( 'Replies sent this hour', 'mailweb' ),
		'value' => $num( $replies ),
		'raw'   => $replies,
		/* translators: %d: replies the Inbox may send an hour. */
		'sub'   => sprintf( __( 'Of %d an hour, from the Inbox', 'mailweb' ), (int) ( $live['perHour'] ?? 30 ) ),
		'href'  => $live['urls']['inbox'] ?? '',
		'tone'  => $replies >= (int) ( $live['perHour'] ?? 30 ) ? 'warn' : '',
	);
	return $tiles;
}
