<?php
/**
 * Workers on time (owner, 31 Oct 2026). GitHub starts scheduled workflows 3 to 6 hours late and ran wp-cron.yml about
 * 5 times a day instead of 96. Hostinger's cron (hPanel → Advanced → Cron Jobs) runs this every 15 minutes instead:
 *
 *   cd /home/u544169507/domains/animalwrites.in/public_html && /usr/local/bin/wp aw workers-tick >> /home/u544169507/aw-cron.log 2>&1
 *
 * 1. WordPress's due cron events run here on the server (DISABLE_WP_CRON is on; nothing waits for GitHub).
 * 2. Each workflow in AW_WORKERS (workers-registry.php) whose cron time has come since the last tick is started through
 *    GitHub's API (aw_dispatch_workflow, animal-writes-core cron-health.php) with the input scheduled_cron, so the run
 *    behaves as GitHub's scheduled run for that cron. One start per cron, however many slots were missed.
 *
 * GitHub's own scheduled runs are skipped while the workers repository's variable AW_SCHEDULER is "site"; unset it to go
 * back to GitHub's schedule. This tick replaces the old hPanel job that started sourcing.yml every 4 hours.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Started elsewhere: wp-cron.yml (step 1 does its work), keepalive.yml (GitHub's own), backup-offsite.yml (UpdraftPlus). */
const AW_TICK_SKIP = array( 'wp-cron.yml', 'keepalive.yml', 'backup-offsite.yml' );

/** Longest look back for a missed slot (the server cron was down): one day. */
const AW_TICK_LOOKBACK_MIN = 1440;

/**
 * True when one cron field matches a value: *, n, a-b, a-b/n, * /n and lists of these.
 *
 * @param string $field Cron field.
 * @param int    $value Minute, hour, day, month or weekday.
 * @param int    $min   Smallest value of the field.
 * @param int    $max   Largest value of the field.
 * @return bool
 */
function aw_tick_field_matches( $field, $value, $min, $max ) {
	foreach ( explode( ',', $field ) as $part ) {
		$step = 1;
		if ( false !== strpos( $part, '/' ) ) {
			list( $part, $step ) = explode( '/', $part, 2 );
			$step                = max( 1, (int) $step );
		}
		if ( '*' === $part ) {
			list( $lo, $hi ) = array( $min, $max );
		} elseif ( false !== strpos( $part, '-' ) ) {
			list( $lo, $hi ) = array_map( 'intval', explode( '-', $part, 2 ) );
		} else {
			$lo = (int) $part;
			$hi = false !== strpos( $field, '/' ) ? $max : $lo; // "5/15" is 5, 20, 35, 50
		}
		if ( $value >= $lo && $value <= $hi && 0 === ( $value - $lo ) % $step ) {
			return true;
		}
	}
	return false;
}

/**
 * True when a five-field cron (UTC) fires at the minute $ts. Day of month and weekday: either one when both are set.
 *
 * @param string $cron Cron.
 * @param int    $ts   Unix time (any second of the minute).
 * @return bool
 */
function aw_tick_cron_matches( $cron, $ts ) {
	$f = preg_split( '/\s+/', trim( $cron ) );
	if ( 5 !== count( $f ) ) {
		return false;
	}
	$dow = (int) gmdate( 'w', $ts );
	$dom = aw_tick_field_matches( $f[2], (int) gmdate( 'j', $ts ), 1, 31 );
	$wd  = aw_tick_field_matches( $f[4], $dow, 0, 7 ) || ( 0 === $dow && aw_tick_field_matches( $f[4], 7, 0, 7 ) );
	$day = ( '*' !== $f[2] && '*' !== $f[4] ) ? ( $dom || $wd ) : ( $dom && $wd );
	return $day && aw_tick_field_matches( $f[0], (int) gmdate( 'i', $ts ), 0, 59 ) && aw_tick_field_matches( $f[1], (int) gmdate( 'G', $ts ), 0, 23 ) && aw_tick_field_matches( $f[3], (int) gmdate( 'n', $ts ), 1, 12 );
}

/**
 * The latest minute in ($after, $now] at which the cron fires, or 0.
 *
 * @param string $cron  Cron (UTC).
 * @param int    $after Unix time: slots after this minute only.
 * @param int    $now   Unix time.
 * @return int
 */
function aw_tick_last_slot( $cron, $after, $now ) {
	$now   -= $now % 60;
	$after  = max( $after - $after % 60, $now - AW_TICK_LOOKBACK_MIN * 60 );
	for ( $t = $now; $t > $after; $t -= 60 ) {
		if ( aw_tick_cron_matches( $cron, $t ) ) {
			return $t;
		}
	}
	return 0;
}

/**
 * Starts the workflows whose cron came since the last tick. Returns lines for the log.
 *
 * @param int           $now      Unix time.
 * @param callable|null $dispatch fn( $file, $inputs ) => true|WP_Error (default aw_dispatch_workflow).
 * @return string[]
 */
function aw_workers_tick( $now, $dispatch = null ) {
	$dispatch = $dispatch ?? 'aw_dispatch_workflow';
	$state    = (array) get_option( 'aw_workers_tick', array() ); // "file|cron" => last slot started; "_last" => last tick
	$last     = (int) ( $state['_last'] ?? 0 );
	$lines    = array();
	foreach ( AW_WORKERS as $file => $w ) {
		if ( in_array( $file, AW_TICK_SKIP, true ) ) {
			continue;
		}
		foreach ( (array) ( $w['crons'] ?? array() ) as $cron ) {
			$key = $file . '|' . $cron;
			// Slots since this cron's last start, else since the last tick; the very first tick looks back 20 minutes only,
			// so switching over does not start every workflow at once.
			$after = (int) ( $state[ $key ] ?? ( $last ? $last : $now - 20 * 60 ) );
			$slot  = aw_tick_last_slot( $cron, $after, $now );
			if ( ! $slot ) {
				continue;
			}
			$res             = call_user_func( $dispatch, $file, array( 'scheduled_cron' => $cron ) );
			$state[ $key ]   = $slot; // a failed start is not retried at the next tick: the next slot is
			$lines[]         = sprintf( '%s %s (%s, slot %s UTC): %s', gmdate( 'Y-m-d H:i', $now ), $file, $cron, gmdate( 'H:i', $slot ), true === $res ? 'started' : 'NOT started: ' . ( is_wp_error( $res ) ? $res->get_error_message() : 'unknown' ) );
		}
	}
	$state['_last'] = $now;
	update_option( 'aw_workers_tick', $state, false );
	return $lines;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * `wp aw workers-tick [--dry-run]`: WordPress's due cron events, then the workflows whose time has come (see above).
	 * --dry-run: lists what would start; runs no cron events and starts nothing.
	 */
	WP_CLI::add_command(
		'aw workers-tick',
		function ( $args, $assoc ) {
			$dry = ! empty( $assoc['dry-run'] );
			$now = time();
			if ( ! $dry ) {
				$cron = WP_CLI::runcommand( 'cron event run --due-now', array( 'return' => 'all', 'launch' => true, 'exit_error' => false ) );
				WP_CLI::log( gmdate( 'Y-m-d H:i', $now ) . ' wp-cron: ' . trim( (string) ( $cron->stdout ?? '' ) . ' ' . (string) ( $cron->stderr ?? '' ) ) );
			}
			if ( ! function_exists( 'aw_dispatch_workflow' ) ) {
				WP_CLI::error( 'aw_dispatch_workflow (animal-writes-core cron-health.php) is needed.' );
			}
			$saved = get_option( 'aw_workers_tick', array() );
			$lines = aw_workers_tick( $now, $dry ? fn() => true : null );
			if ( $dry ) {
				update_option( 'aw_workers_tick', $saved, false ); // a dry run moves nothing on
				$lines = array_map( fn( $l ) => str_replace( ': started', ': would start', $l ), $lines );
			}
			foreach ( $lines as $line ) {
				WP_CLI::log( $line );
			}
			WP_CLI::success( count( $lines ) . ( $dry ? ' would start.' : ' started or tried.' ) );
		}
	);
}
