<?php
/**
 * Settings → Sourcing → Workers (owner, 24 Oct 2026): every workflow of the workers repository, its last run, counts,
 * problems and Run now. Read-only otherwise: schedules are changed in the workflows, never here.
 *
 * Data: each workflow reports its start and end (POST aw/v1/worker-run, the workers' application password) from
 * .github/actions/report in wugweb-git/animal-writes-workers. Nothing polls GitHub.
 * Alerts: a workflow with no report for its late limit (late_after_h), a run that failed, was cancelled or skipped its
 * main step, or a run with no end after its timeout. Shown on this tab, in the dashboard box "Scheduled tasks", in
 * Tools → Site Health, and emailed to the owner once per new set of problems (the ingestion watchdog's pattern).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/workers-registry.php';

const AW_WORKERS_KEEP = 20; // runs kept per workflow

/** Workflow file => array of runs (run_id => record), newest last. */
function aw_workers_runs() {
	$all = array();
	foreach ( array_keys( AW_WORKERS ) as $file ) {
		$all[ $file ] = (array) get_option( aw_workers_option( $file ), array() );
	}
	return $all;
}

/** One option per workflow (aw_worker_runs_sourcing_yml), so reports from different workflows never overwrite each other. */
function aw_workers_option( $file ) {
	return 'aw_worker_runs_' . preg_replace( '/[^a-z0-9]+/', '_', strtolower( $file ) );
}

/** The newest run of a workflow (highest run id), or null. */
function aw_workers_last( $file ) {
	$runs = (array) get_option( aw_workers_option( $file ), array() );
	if ( ! $runs ) {
		return null;
	}
	krsort( $runs, SORT_NUMERIC );
	return reset( $runs );
}

/**
 * The values a cron field allows (*, n, a-b, a-b/n, *\/n, lists).
 *
 * @return int[]
 */
function aw_workers_cron_field( $spec, $lo, $hi ) {
	$out = array();
	foreach ( explode( ',', $spec ) as $part ) {
		$bits = explode( '/', $part );
		$step = max( 1, (int) ( $bits[1] ?? 1 ) );
		if ( '*' === $bits[0] ) {
			list( $a, $b ) = array( $lo, $hi );
		} elseif ( false !== strpos( $bits[0], '-' ) ) {
			list( $a, $b ) = array_map( 'intval', explode( '-', $bits[0] ) );
		} else {
			$a = (int) $bits[0];
			$b = isset( $bits[1] ) ? $hi : $a;
		}
		for ( $i = $a; $i <= $b; $i += $step ) {
			$out[ $i ] = $i;
		}
	}
	ksort( $out );
	return array_values( $out );
}

/** Next time (Unix) any of the crons fires after $from, or 0. */
function aw_workers_next( array $crons, $from ) {
	$best = 0;
	foreach ( $crons as $cron ) {
		list( $m, $h, $dom, $mon, $dow ) = preg_split( '/\s+/', trim( $cron ) );
		$mins                            = aw_workers_cron_field( $m, 0, 59 );
		$hrs                             = aw_workers_cron_field( $h, 0, 23 );
		$doms                            = aw_workers_cron_field( $dom, 1, 31 );
		$mons                            = aw_workers_cron_field( $mon, 1, 12 );
		$dows                            = array_map( fn( $d ) => $d % 7, aw_workers_cron_field( $dow, 0, 7 ) );
		$day                             = (int) ( floor( $from / DAY_IN_SECONDS ) * DAY_IN_SECONDS );
		for ( $i = 0; $i < 62; $i++, $day += DAY_IN_SECONDS ) {
			$dok    = in_array( (int) gmdate( 'j', $day ), $doms, true );
			$wok    = in_array( (int) gmdate( 'w', $day ), $dows, true );
			$day_ok = ( '*' !== $dom && '*' !== $dow ) ? ( $dok || $wok ) : ( $dok && $wok ); // cron's own rule
			if ( ! $day_ok || ! in_array( (int) gmdate( 'n', $day ), $mons, true ) ) {
				continue;
			}
			foreach ( $hrs as $hh ) {
				foreach ( $mins as $mm ) {
					$t = $day + $hh * HOUR_IN_SECONDS + $mm * MINUTE_IN_SECONDS;
					if ( $t > $from ) {
						$best = $best ? min( $best, $t ) : $t;
						break 3;
					}
				}
			}
		}
	}
	return $best;
}

/** The schedule in India time, in words ("05:47, 08:17, … IST", "every 15 min", "Mondays 09:10 IST"). */
function aw_workers_schedule( array $w ) {
	if ( ! empty( $w['after'] ) ) {
		return 'After "' . $w['after'] . '"' . ( 'tests.yml' === $w['file'] ? ' and every push' : '' );
	}
	if ( empty( $w['crons'] ) ) {
		return 'By hand';
	}
	$ist  = 330; // minutes, India is UTC+5:30
	$days = array();
	$mins = array();
	foreach ( $w['crons'] as $cron ) {
		list( $m, $h, $dom, , $dow ) = preg_split( '/\s+/', trim( $cron ) );
		foreach ( aw_workers_cron_field( $h, 0, 23 ) as $hh ) {
			foreach ( aw_workers_cron_field( $m, 0, 59 ) as $mm ) {
				$mins[] = ( $hh * 60 + $mm + $ist ) % 1440;
			}
		}
		$days[] = '*' !== $dow ? 'dow:' . $dow : ( '*' !== $dom ? 'dom:' . $dom : '' );
	}
	sort( $mins );
	if ( count( $mins ) > 8 ) {
		$n = (int) $w['interval_min'];
		return 'Every ' . ( 60 === $n ? 'hour' : ( $n > 60 && 0 === $n % 60 ? ( $n / 60 ) . ' h' : $n . ' min' ) );
	}
	$times = implode( ', ', array_map( fn( $x ) => sprintf( '%02d:%02d', intdiv( $x, 60 ), $x % 60 ), $mins ) ) . ' IST';
	$day   = (string) reset( $days );
	if ( 0 === strpos( $day, 'dow:' ) ) {
		$names = array( 'Sundays', 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays' );
		return implode( ', ', array_map( fn( $d ) => $names[ $d % 7 ], aw_workers_cron_field( substr( $day, 4 ), 0, 7 ) ) ) . ' ' . $times;
	}
	if ( 0 === strpos( $day, 'dom:' ) ) {
		return 'Day ' . substr( $day, 4 ) . ' of the month, ' . $times;
	}
	return ( 1 === count( $mins ) ? 'Daily ' : '' ) . $times;
}

/** "4 min 20 s" / "1 h 05 min". */
function aw_workers_duration( $s ) {
	$s = max( 0, (int) $s );
	return $s < 3600 ? ( $s >= 60 ? intdiv( $s, 60 ) . ' min ' : '' ) . ( $s % 60 ) . ' s' : aw_cron_span( $s );
}

/**
 * What is wrong with the workers now, as "file:kind" => sentence (empty when all is well). A workflow that has never
 * reported is not a problem (reports start when the workers' change is merged).
 *
 * @param int|null $now Unix time (tests).
 * @return array<string,string>
 */
function aw_workers_problems( $now = null ) {
	static $memo = null; // one check per request (notice, dashboard box, table, Site Health)
	if ( null === $now && null !== $memo ) {
		return $memo;
	}
	$cache = null === $now;
	$now   = $now ? $now : time();
	$out   = array();
	foreach ( AW_WORKERS as $file => $w ) {
		$last = aw_workers_last( $file );
		if ( ! $last ) {
			continue;
		}
		$seen = max( (int) ( $last['ended'] ?? 0 ), (int) ( $last['started'] ?? 0 ) );
		if ( ! empty( $w['late_after_h'] ) && $now - $seen > (int) $w['late_after_h'] * HOUR_IN_SECONDS ) {
			$out[ $file . ':late' ] = sprintf( '%s has not reported for %s (late after %d h; last: %s).', $w['name'], aw_cron_span( $now - $seen ), (int) $w['late_after_h'], wp_date( 'j M, g:i a', $seen ) );
		}
		$result = (string) ( $last['result'] ?? '' );
		if ( 'running' === $result && $now - (int) $last['started'] > ( (int) $w['timeout_min'] + 10 ) * MINUTE_IN_SECONDS ) {
			$out[ $file . ':stuck' ] = sprintf( '%s started %s and has not reported its end (timeout %d min).', $w['name'], wp_date( 'j M, g:i a', (int) $last['started'] ), (int) $w['timeout_min'] );
		} elseif ( in_array( $result, array( 'failed', 'cancelled', 'skipped' ), true ) ) {
			$step = (string) ( $last['failed_step'] ?? '' );
			if ( 'tests.yml' === $file && 'failed' === $result ) {
				$msg = 'Tests failed (sourcing is not blocked; GitHub opened an issue).';
			} elseif ( 'skipped' === $result ) {
				$msg = sprintf( '%s skipped: step "%s" did not run.', $w['name'], $last['main_step'] ?? 'main' );
			} else {
				$msg = sprintf( '%s %s%s.', $w['name'], $result, $step ? ': step "' . $step . '"' : '' );
			}
			$out[ $file . ':' . $result ] = $msg . ' ' . wp_date( 'j M, g:i a', max( (int) ( $last['ended'] ?? 0 ), (int) $last['started'] ) );
		}
	}
	if ( $cache ) {
		$memo = $out;
	}
	return $out;
}

/** Email the owner about worker problems, once per new set (hourly event, and after every end report). */
function aw_workers_watchdog() {
	update_option( 'aw_workers_watchdog_at', time(), true );
	$problems = aw_workers_problems();
	if ( ! $problems ) {
		delete_transient( 'aw_workers_alert_mailed' );
		return;
	}
	$sent = (string) get_transient( 'aw_workers_alert_mailed' );
	if ( $sent && ! array_diff( array_keys( $problems ), explode( ',', $sent ) ) ) {
		return; // the same problems were emailed in the last 12 hours
	}
	$body = "Animal Writes workers need attention:\n\n- " . implode( "\n- ", $problems ) . "\n\nWorkers: " . admin_url( 'options-general.php?page=aw-sourcing&tab=workers' ) . "\nRuns: https://github.com/wugweb-git/animal-writes-workers/actions\n";
	if ( wp_mail( get_option( 'admin_email' ), 'Animal Writes: worker problem — ' . reset( $problems ), $body ) ) {
		set_transient( 'aw_workers_alert_mailed', implode( ',', array_keys( $problems ) ), 12 * HOUR_IN_SECONDS );
	}
}
add_action( 'aw_workers_watchdog', fn() => aw_workers_watchdog() ); // the "report" argument only keeps the one-off event apart
// Like the ingestion watchdog: any request that reaches PHP also checks when the last check is an hour old, so a late
// worker is noticed even when scheduled tasks have stopped.
add_action(
	'shutdown',
	function () {
		if ( time() - (int) get_option( 'aw_workers_watchdog_at', 0 ) >= HOUR_IN_SECONDS && ! wp_installing() ) {
			aw_workers_watchdog();
		}
	}
);
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_workers_watchdog' ) ) {
			wp_schedule_event( time() + 900, 'hourly', 'aw_workers_watchdog' );
		}
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/worker-run',
			array(
				'methods'             => 'POST',
				// Only an application password (never a browser cookie), of the workers' own user or an administrator.
				'permission_callback' => fn() => did_action( 'application_password_did_authenticate' ) && ( 'ingest' === wp_get_current_user()->user_login || current_user_can( 'manage_options' ) ),
				'callback'            => function ( WP_REST_Request $req ) {
					$file = sanitize_file_name( (string) $req->get_param( 'workflow' ) );
					$run  = preg_replace( '/\D/', '', (string) $req->get_param( 'run_id' ) );
					if ( ! isset( AW_WORKERS[ $file ] ) || '' === $run ) {
						return new WP_Error( 'aw_worker_unknown', 'Unknown workflow or run.', array( 'status' => 400 ) );
					}
					$now    = time();
					$phase  = 'start' === $req->get_param( 'phase' ) ? 'start' : 'end';
					$result = sanitize_key( (string) $req->get_param( 'result' ) );
					// Reports for the same workflow can overlap (a run's end and the next run's start): read-modify-write under
					// a MySQL lock, released at the end of the request at the latest.
					global $wpdb;
					$lock   = 'aw_worker_runs_' . md5( $file );
					if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a MySQL named lock, nothing to cache
						return new WP_Error( 'aw_worker_busy', 'Another report for this workflow is being saved; nothing was written.', array( 'status' => 503 ) );
					}
					wp_cache_delete( aw_workers_option( $file ), 'options' );
					wp_cache_delete( 'notoptions', 'options' );
					$runs   = (array) get_option( aw_workers_option( $file ), array() );
					$r      = (array) ( $runs[ $run ] ?? array() );
					$try    = max( 1, (int) $req->get_param( 'attempt' ) );
					if ( $try < (int) ( $r['attempt'] ?? 0 ) ) { // a late report from an attempt a re-run has replaced
						$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- releases the lock above
						return new WP_REST_Response( array( 'ok' => true, 'ignored' => 'older attempt' ) );
					}
					if ( 'start' === $phase && $try > (int) ( $r['attempt'] ?? 0 ) ) {
						// A re-run reuses the run id with the next attempt: it starts afresh.
						$r = array_diff_key( $r, array_flip( array( 'ended', 'result', 'failed_step', 'skipped_steps', 'counts', 'started' ) ) );
					}
					$counts = array();
					foreach ( array_slice( (array) $req->get_param( 'counts' ), 0, 20, true ) as $k => $v ) {
						if ( is_scalar( $v ) ) {
							$counts[ sanitize_key( (string) $k ) ] = is_bool( $v ) ? (int) $v : ( is_numeric( $v ) ? $v + 0 : mb_substr( sanitize_text_field( (string) $v ), 0, 40 ) );
						}
					}
					$started = (int) $req->get_param( 'started_at' );
					$r       = array_merge(
						$r,
						array(
							'run_id'  => $run,
							'attempt' => max( $try, (int) ( $r['attempt'] ?? 0 ) ),
							'event'   => sanitize_key( (string) $req->get_param( 'event' ) ),
							'sha'     => preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $req->get_param( 'sha' ) ) ),
							'link'    => esc_url_raw( (string) $req->get_param( 'link' ) ),
							'dry_run' => (bool) $req->get_param( 'dry_run' ),
							'started' => $started > 0 && $started <= $now + 60 ? $started : (int) ( $r['started'] ?? $now ),
						)
					);
					if ( 'start' === $phase ) {
						$r['result'] = $r['result'] ?? 'running';
					} else {
						$r['ended']         = $now;
						$r['result']        = in_array( $result, array( 'success', 'failed', 'cancelled', 'skipped' ), true ) ? $result : 'failed';
						$r['failed_step']   = sanitize_key( (string) $req->get_param( 'failed_step' ) );
						$main               = sanitize_key( (string) $req->get_param( 'main_step' ) );
						$r['main_step']     = '' !== $main ? $main : 'main';
						$r['skipped_steps'] = array_map( 'sanitize_key', array_slice( array_filter( (array) $req->get_param( 'skipped_steps' ), 'is_scalar' ), 0, 10 ) );
						$r['counts']        = $counts;
					}
					$runs[ $run ] = $r;
					ksort( $runs, SORT_NUMERIC );
					update_option( aw_workers_option( $file ), array_slice( $runs, -AW_WORKERS_KEEP, null, true ), false );
					$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- releases the lock above
					if ( 'end' === $phase && ! wp_next_scheduled( 'aw_workers_watchdog', array( 'report' ) ) ) {
						// Checked (and emailed) by the next scheduled-task tick, not inside the worker's request.
						wp_schedule_single_event( time(), 'aw_workers_watchdog', array( 'report' ) );
					}
					return new WP_REST_Response( array( 'ok' => true ) );
				},
			)
		);
	}
);

/** Result of a run as a core notice badge with a dashicon. */
function aw_workers_badge( $last, $problem, $note = '' ) {
	if ( ! $last ) {
		return '<span class="dashicons dashicons-clock" aria-hidden="true"></span> No report yet';
	}
	$map                         = array(
		'success'   => array( 'success', 'yes-alt', 'Success' ),
		'running'   => array( 'info', 'clock', 'Running' ),
		'failed'    => array( 'error', 'dismiss', 'Failed' ),
		'cancelled' => array( 'error', 'dismiss', 'Cancelled' ),
		'skipped'   => array( 'warning', 'warning', 'Skipped' ),
	);
	list( $kind, $icon, $label ) = $map[ $last['result'] ] ?? $map['failed'];
	if ( $problem ) {
		$kind = 'error'; // a problem row's badge is always the error one
	}
	$step = '';
	if ( ! empty( $last['failed_step'] ) ) {
		$step = ': step "' . $last['failed_step'] . '"';
	} elseif ( 'skipped' === $last['result'] ) {
		$step = ': step "' . ( $last['main_step'] ?? 'main' ) . '" did not run';
	}
	return '<div class="notice inline notice-' . esc_attr( $kind ) . ' notice-alt"><p><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span> '
		. esc_html( $label . $step ) . ( ! empty( $last['dry_run'] ) ? ' (dry run)' : '' ) . ( $note ? '<br>' . esc_html( $note ) : '' ) . '</p></div>';
}

if ( is_admin() ) {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}

	/** The 17 workflows, one row each. */
	class AW_Workers_List_Table extends WP_List_Table {
		/** @var array<string,string> file => problem sentences */
		private $problems = array();

		/** @var array<string,bool> files with any problem */
		private $flagged = array();

		public function __construct() {
			parent::__construct(
				array(
					'singular' => 'workflow',
					'plural'   => 'workflows',
					'ajax'     => false,
					'screen'   => 'settings_page_aw-sourcing',
				)
			);
		}

		public function get_columns() {
			return aw_workers_columns();
		}

		protected function get_sortable_columns() {
			return array(
				'name'     => array( 'name', false ),
				'last_run' => array( 'last_run', true ),
				'result'   => array( 'result', false ),
				'next_due' => array( 'next_due', false ),
			);
		}

		protected function get_default_primary_column_name() {
			return 'name';
		}

		/** Table classes: "plugins" so problem rows take the core plugins-list highlight. */
		protected function get_table_classes() {
			return array( 'widefat', 'striped', 'plugins', $this->_args['plural'] );
		}

		public function prepare_items() {
			$now = time();
			foreach ( aw_workers_problems( $now ) as $k => $msg ) {
				$f                    = strstr( $k, ':', true );
				$this->problems[ $f ] = trim( ( $this->problems[ $f ] ?? '' ) . ( preg_match( '/:(late|stuck)$/', $k ) ? ' ' . $msg : '' ) );
				$this->flagged[ $f ]  = true;
			}
			$rows = array();
			foreach ( AW_WORKERS as $file => $w ) {
				$last   = aw_workers_last( $file );
				$rows[] = $w + array(
					'file'     => $file,
					'last'     => $last,
					'next'     => $w['crons'] ? aw_workers_next( $w['crons'], $now ) : 0,
					'problem'  => ! empty( $this->flagged[ $file ] ),
					'note'     => $this->problems[ $file ] ?? '',
					'sort_run' => $last ? (int) $last['started'] : 0,
				);
			}
			$by    = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
			$desc  = 'desc' === sanitize_key( wp_unslash( $_GET['order'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a sort order in a link
			$keyfn = array(
				'name'     => fn( $r ) => strtolower( $r['name'] ),
				'last_run' => fn( $r ) => $r['sort_run'],
				'result'   => fn( $r ) => ( $r['problem'] ? '0' : '1' ) . ( $r['last']['result'] ?? 'z' ),
				'next_due' => fn( $r ) => $r['next'] ? $r['next'] : PHP_INT_MAX,
			);
			if ( isset( $keyfn[ $by ] ) ) {
				usort( $rows, fn( $a, $b ) => ( $desc ? -1 : 1 ) * ( $keyfn[ $by ]( $a ) <=> $keyfn[ $by ]( $b ) ) );
			} else {
				usort( $rows, fn( $a, $b ) => ( (int) ! $a['problem'] <=> (int) ! $b['problem'] ) ); // problems first, then the registry order
			}
			$per                   = $this->get_items_per_page( 'aw_workers_per_page', 20 );
			$this->_column_headers = array( $this->get_columns(), get_hidden_columns( $this->screen ), $this->get_sortable_columns(), 'name' );
			$this->set_pagination_args(
				array(
					'total_items' => count( $rows ),
					'per_page'    => $per,
				)
			);
			$this->items = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		}

		/** The core nav without its bulk-action nonce: the table sits in the Sourcing form, whose own nonce must win. */
		protected function display_tablenav( $which ) {
			echo '<div class="tablenav ' . esc_attr( $which ) . '">';
			$this->pagination( $which );
			echo '<br class="clear"></div>';
		}

		public function single_row( $item ) {
			echo '<tr' . ( $item['problem'] ? ' class="active update"' : '' ) . '>';
			$this->single_row_columns( $item );
			echo '</tr>';
		}

		protected function column_name( $r ) {
			$gh = 'https://github.com/wugweb-git/animal-writes-workers';
			return '<strong>' . esc_html( $r['name'] ) . '</strong>'
				. $this->row_actions(
					array(
						'runs' => '<a href="' . esc_url( $gh . '/actions/workflows/' . $r['file'] ) . '" target="_blank" rel="noopener">Runs on GitHub</a>',
						'file' => '<a href="' . esc_url( $gh . '/blob/main/.github/workflows/' . $r['file'] ) . '" target="_blank" rel="noopener">' . esc_html( $r['file'] ) . '</a>',
					)
				);
		}

		protected function column_what( $r ) {
			return esc_html( $r['what'] );
		}

		protected function column_schedule( $r ) {
			$late = empty( $r['late_after_h'] ) ? ( $r['crons'] || ! empty( $r['after'] ) ? 'Never late (no alert)' : '' ) : 'Late after ' . ( $r['late_after_h'] >= 48 ? round( $r['late_after_h'] / 24 ) . ' days' : $r['late_after_h'] . ' h' );
			return esc_html( aw_workers_schedule( $r ) ) . ( $late ? '<br><span class="description">' . esc_html( $late ) . '</span>' : '' );
		}

		protected function column_last_run( $r ) {
			$l = $r['last'];
			if ( ! $l ) {
				return '—';
			}
			$dur = ! empty( $l['ended'] ) && ! empty( $l['started'] ) && $l['ended'] >= $l['started'] ? aw_workers_duration( $l['ended'] - $l['started'] ) : '';
			$ev  = array(
				'schedule'          => 'scheduled',
				'workflow_dispatch' => 'started by hand or from the site',
				'workflow_run'      => 'after another workflow',
				'push'              => 'push',
			);
			return esc_html( wp_date( 'j M, g:i a', (int) $l['started'] ) ) . ( $dur ? '<br>' . esc_html( $dur ) : '' )
				. '<br><span class="description">' . esc_html( $ev[ $l['event'] ] ?? $l['event'] ) . '</span>';
		}

		protected function column_result( $r ) {
			return aw_workers_badge( $r['last'], $r['problem'], $r['note'] );
		}

		protected function column_counts( $r ) {
			$c = (array) ( $r['last']['counts'] ?? array() );
			if ( ! $c ) {
				return '—';
			}
			return implode( '<br>', array_map( fn( $k, $v ) => esc_html( str_replace( '_', ' ', $k ) . ': ' . $v ), array_keys( $c ), $c ) );
		}

		protected function column_commit( $r ) {
			$l = $r['last'];
			if ( ! $l || empty( $l['sha'] ) ) {
				return '—';
			}
			return '<a href="' . esc_url( 'https://github.com/wugweb-git/animal-writes-workers/commit/' . $l['sha'] ) . '" target="_blank" rel="noopener"><code>' . esc_html( $l['sha'] ) . '</code></a>'
				. ( ! empty( $l['link'] ) ? '<br><a href="' . esc_url( $l['link'] ) . '" target="_blank" rel="noopener">Run log</a>' : '' );
		}

		protected function column_next_due( $r ) {
			if ( ! empty( $r['after'] ) ) {
				return esc_html( 'After "' . $r['after'] . '"' );
			}
			return $r['next'] ? esc_html( wp_date( 'j M, g:i a', $r['next'] ) ) : 'By hand';
		}

		protected function column_run_now( $r ) {
			if ( ! empty( $r['needs_input'] ) ) {
				return '<button type="button" class="button button-secondary" disabled>Run now</button><p class="description">Needs input: run it from GitHub.</p>';
			}
			if ( ! defined( 'AW_GITHUB_DISPATCH_TOKEN' ) || '' === (string) AW_GITHUB_DISPATCH_TOKEN ) {
				return '<button type="button" class="button button-secondary" disabled>Run now</button><p class="description">Needs AW_GITHUB_DISPATCH_TOKEN.</p>';
			}
			$in = (array) ( $r['runnow'] ?? array() );
			return '<button type="submit" class="button button-secondary" name="aw_run" value="' . esc_attr( $r['file'] ) . '">Run now</button>'
				. ( $in ? '<p class="description">' . esc_html( implode( ', ', array_map( fn( $k, $v ) => $k . '=' . $v, array_keys( $in ), $in ) ) ) . '</p>' : '' );
		}
	}
}

/** Column slug => label (also the Screen Options checkboxes). */
function aw_workers_columns() {
	return array(
		'name'     => 'Workflow',
		'what'     => 'What it does',
		'schedule' => 'Schedule (IST)',
		'last_run' => 'Last run',
		'result'   => 'Result',
		'counts'   => 'Counts',
		'commit'   => 'Commit',
		'next_due' => 'Next due',
		'run_now'  => 'Run now',
	);
}

// Screen Options (columns, rows per page) on the Workers tab only.
add_action(
	'load-settings_page_aw-sourcing',
	function () {
		if ( 'workers' !== sanitize_key( $_GET['tab'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		add_filter( 'manage_settings_page_aw-sourcing_columns', 'aw_workers_columns' );
		add_screen_option(
			'per_page',
			array(
				'label'   => 'Workflows per page',
				'default' => 20,
				'option'  => 'aw_workers_per_page',
			)
		);
	}
);
add_filter( 'set_screen_option_aw_workers_per_page', fn( $keep, $option, $value ) => max( 1, min( 100, (int) $value ) ), 10, 3 );

add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		$tabs['workers'] = array( 'Workers', 'aw_workers_tab' );
		return $tabs;
	}
);

/** Workers tab (inside the Sourcing page's form: Run now posts to admin-post.php through it). */
function aw_workers_tab() {
	if ( isset( $_GET['aw_ran'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$ok = ! empty( $_GET['aw_ok'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$f  = sanitize_file_name( wp_unslash( $_GET['aw_ran'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$l  = (array) ( get_option( 'aw_worker_dispatch_log', array() )[0] ?? array() );
		echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( ( AW_WORKERS[ $f ]['name'] ?? $f ) . ': ' . ( $ok ? 'started on GitHub. Its start report appears here within a minute or two.' : 'not started — ' . ( $l['msg'] ?? '' ) ) ) . '</p></div>';
	}
	echo '<h2>AI models</h2>';
	if ( function_exists( 'aw_ai_overview' ) && function_exists( 'aw_ai_rows' ) ) {
		aw_ai_overview( aw_ai_rows() );
	}
	echo '<h2>Workflows</h2><p>Each workflow of <a href="https://github.com/wugweb-git/animal-writes-workers/actions" target="_blank" rel="noopener">wugweb-git/animal-writes-workers</a> reports its start and end here. Schedules are changed in the workflows, not here. A problem (a failed, cancelled, skipped or stuck run, or no report within a workflow\'s late limit) is emailed to the owner once.</p>';
	if ( ! defined( 'AW_GITHUB_DISPATCH_TOKEN' ) || '' === (string) AW_GITHUB_DISPATCH_TOKEN ) {
		echo '<div class="notice notice-warning inline"><p>Run now is off: set <code>AW_GITHUB_DISPATCH_TOKEN</code> in wp-config.php (a fine-grained GitHub token for wugweb-git/animal-writes-workers only, Actions read and write).</p></div>';
	}
	$table = new AW_Workers_List_Table();
	$table->prepare_items();
	$table->display();
	$log = (array) get_option( 'aw_worker_dispatch_log', array() );
	echo '<h2>Run now log</h2>';
	if ( ! $log ) {
		echo '<p>None yet.</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>When</th><th>Who</th><th>Workflow</th><th>Result</th></tr></thead><tbody>';
	foreach ( $log as $e ) {
		echo '<tr><td>' . esc_html( wp_date( 'j M Y, g:i a', (int) $e['at'] ) ) . '</td><td>' . esc_html( $e['user'] ) . '</td><td>' . esc_html( AW_WORKERS[ $e['file'] ]['name'] ?? $e['file'] ) . '</td><td>' . esc_html( $e['ok'] ? 'started' : $e['msg'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

// Run now: the Sourcing page's save handler (nonce and capability checked there) passes the Workers tab here.
add_action(
	'kits_sourcing_save_workers',
	function () {
		$file = sanitize_file_name( wp_unslash( $_POST['aw_run'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
		if ( '' === $file ) { // Enter in the table's page box submits the Sourcing form: go to that page
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'aw-sourcing',
						'tab'   => 'workers',
						'paged' => max( 1, absint( $_POST['paged'] ?? 1 ) ), // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
					),
					admin_url( 'options-general.php' )
				)
			); // phpcs:ignore WordPress.Security.NonceVerification
			exit;
		}
		if ( ! current_user_can( 'manage_options' ) || ! isset( AW_WORKERS[ $file ] ) || ! empty( AW_WORKERS[ $file ]['needs_input'] ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$res = aw_dispatch_workflow( $file, (array) ( AW_WORKERS[ $file ]['runnow'] ?? array() ) );
		$log = (array) get_option( 'aw_worker_dispatch_log', array() );
		array_unshift(
			$log,
			array(
				'at'   => time(),
				'user' => wp_get_current_user()->user_login,
				'file' => $file,
				'ok'   => true === $res,
				'msg'  => true === $res ? 'started' : $res->get_error_message(),
			)
		);
		update_option( 'aw_worker_dispatch_log', array_slice( $log, 0, 50 ), false );
		kits_sourcing_log( 'workers', 'Run now: ' . $file . ( true === $res ? '' : ' (not started: ' . $res->get_error_message() . ')' ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'aw-sourcing',
					'tab'    => 'workers',
					'aw_ran' => $file,
					'aw_ok'  => true === $res ? 1 : 0,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
);

// Notice on the dashboard and Settings → Sourcing.
add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! current_user_can( 'manage_options' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'settings_page_aw-sourcing' ), true ) ) {
			return;
		}
		$problems = aw_workers_problems();
		if ( $problems ) {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Workers need attention.</strong> ' . esc_html( implode( ' ', $problems ) ) . ' <a href="' . esc_url( admin_url( 'options-general.php?page=aw-sourcing&tab=workers' ) ) . '">Workers</a></p></div>';
		}
	}
);

// The dashboard box "Scheduled tasks" (cron-health.php) lists the workflows with a problem.
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_action(
			'aw_cron_health_after',
			function () {
				$problems = aw_workers_problems();
				$url      = admin_url( 'options-general.php?page=aw-sourcing&tab=workers' );
				if ( ! $problems ) {
					echo '<p><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> Workers: no problems. <a href="' . esc_url( $url ) . '">Workers</a></p>';
					return;
				}
				foreach ( $problems as $msg ) {
					echo '<div class="notice inline notice-error notice-alt"><p><span class="dashicons dashicons-dismiss" aria-hidden="true"></span> ' . esc_html( $msg ) . '</p></div>';
				}
				echo '<p><a href="' . esc_url( $url ) . '">Workers</a></p>';
			}
		);
	}
);

// Tools → Site Health: "Workers report on time".
add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['aw_workers'] = array(
			'label' => 'Workers report on time',
			'test'  => function () {
				$problems = aw_workers_problems();
				return array(
					'label'       => $problems ? 'Workers need attention' : 'Workers report on time',
					'status'      => $problems ? 'critical' : 'good',
					'badge'       => array(
						'label' => 'Animal Writes',
						'color' => $problems ? 'red' : 'blue',
					),
					'description' => '<p>' . ( $problems ? esc_html( implode( ' ', $problems ) ) : 'Every workflow of the workers repository that has reported is on time and its last run went through.' ) . '</p>',
					'actions'     => '<p><a href="' . esc_url( admin_url( 'options-general.php?page=aw-sourcing&tab=workers' ) ) . '">Settings → Sourcing → Workers</a></p>',
					'test'        => 'aw_workers',
				);
			},
		);
		return $tests;
	}
);
