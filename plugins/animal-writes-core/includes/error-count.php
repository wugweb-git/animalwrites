<?php
/**
 * Daily server-error count. A 500 or 503 page never loads the analytics tag, so analytics cannot see it; the server's
 * own logs can.
 *
 * - Once a day (event aw_error_count_daily) the site counts, for the last 24 hours, the 5xx responses in the web
 *   server's access log and the "PHP Fatal error" / "PHP Parse error" lines in the PHP error log.
 * - The result (time, counts, files read, up to 5 samples each) is kept in the option aw_error_count (not autoloaded)
 *   and shown in the "Scheduled tasks" dashboard box. A log that cannot be found or read shows "Not found", never 0.
 * - When either count is above zero, the admin email gets one plain-text message for that run.
 * - Log paths are found at run time. Override them with the constants AW_ACCESS_LOG / AW_PHP_ERROR_LOG (a file or a
 *   folder) in wp-config.php, or the filters aw_error_count_access_paths / aw_error_count_php_paths.
 * - `wp aw error-count [--paths]` prints the counts and the files read (with --paths, every path tried), without
 *   saving or emailing.
 * Large files are read from the end (last 32 MB by default, filter aw_error_count_tail_bytes), line by line; gzipped
 * logs are streamed. Samples keep only the status and the path (no addresses, no query strings).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_ERROR_COUNT_SAMPLES = 5;
const AW_ERROR_COUNT_KEYS    = 500; // distinct samples tracked while counting (memory bound)

/** The site root's parents: the domain folder (…/domains/example.com) and the account's home folder. */
function aw_error_count_dirs() {
	$root   = untrailingslashit( ABSPATH );
	$domain = dirname( $root );
	$home   = dirname( dirname( $domain ) );
	return array( 'root' => $root, 'domain' => $domain, 'home' => $home );
}

/**
 * Access-log candidates in order (files or folders). The first one that gives a readable log is used.
 *
 * @return string[]
 */
function aw_error_count_access_candidates() {
	$d   = aw_error_count_dirs();
	$out = array();
	if ( defined( 'AW_ACCESS_LOG' ) && AW_ACCESS_LOG ) {
		$out[] = (string) AW_ACCESS_LOG;
	}
	$out[] = $d['domain'] . '/logs';
	$out[] = $d['home'] . '/logs';
	$out[] = $d['domain'] . '/access-logs';
	$out[] = $d['home'] . '/access-logs';
	return array_values( array_unique( (array) apply_filters( 'aw_error_count_access_paths', $out ) ) );
}

/**
 * PHP error-log candidates (files or folders). Every readable one is read (the same file only once).
 *
 * @return string[]
 */
function aw_error_count_php_candidates() {
	$d   = aw_error_count_dirs();
	$out = array();
	if ( defined( 'AW_PHP_ERROR_LOG' ) && AW_PHP_ERROR_LOG ) {
		$out[] = (string) AW_PHP_ERROR_LOG;
	}
	$ini = (string) ini_get( 'error_log' );
	if ( '' !== $ini && 'syslog' !== $ini ) {
		$out[] = $ini;
	}
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		$out[] = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
	}
	$out[] = WP_CONTENT_DIR . '/debug.log';
	$out[] = $d['root'] . '/error_log';
	$out[] = $d['root'] . '/wp-admin/error_log';
	return array_values( array_unique( (array) apply_filters( 'aw_error_count_php_paths', $out ) ) );
}

/**
 * Whether PHP may open the path at all (open_basedir), so a check never raises a warning.
 *
 * @param string $path File or folder.
 * @return bool
 */
function aw_error_count_allowed( $path ) {
	$base = (string) ini_get( 'open_basedir' );
	if ( '' === $base ) {
		return true;
	}
	foreach ( explode( PATH_SEPARATOR, $base ) as $b ) {
		$b = rtrim( $b, '/' );
		if ( '' !== $b && ( $path === $b || 0 === strpos( $path, $b . '/' ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Turn candidates into log files.
 *
 * @param string[] $candidates Files or folders.
 * @param string   $kind       'access' (first candidate with a log wins; folder files changed in the last 48 hours,
 *                             names without "error") or 'php' (every readable file; folder files with "error" in the name).
 * @return array{files:string[],tried:array<string,string>,unreadable:bool}
 */
function aw_error_count_resolve( array $candidates, $kind ) {
	$files      = array();
	$tried      = array();
	$unreadable = false;
	$recent     = time() - 2 * DAY_IN_SECONDS;
	foreach ( $candidates as $c ) {
		$c = (string) $c;
		if ( ! aw_error_count_allowed( $c ) || ! file_exists( $c ) ) {
			$tried[ $c ] = 'not found';
			continue;
		}
		$found = array();
		if ( is_dir( $c ) ) {
			$names = is_readable( $c ) ? (array) scandir( $c ) : array();
			foreach ( $names as $n ) {
				$p     = $c . '/' . $n;
				$error = false !== stripos( (string) $n, 'error' );
				if ( '.' === $n[0] || ! is_file( $p ) || ( 'access' === $kind ? $error : ! $error ) ) {
					continue;
				}
				if ( 'access' === $kind && (int) filemtime( $p ) < $recent ) {
					continue;
				}
				if ( is_readable( $p ) ) {
					$found[] = $p;
				} else {
					$unreadable = true;
				}
			}
			$tried[ $c ] = $found ? 'folder: ' . count( $found ) . ' file(s)' : ( is_readable( $c ) ? 'folder: no matching files' : 'folder not readable' );
			$unreadable  = $unreadable || ! is_readable( $c );
		} elseif ( is_readable( $c ) ) {
			$found[]     = $c;
			$tried[ $c ] = 'found';
		} else {
			$tried[ $c ] = 'not readable';
			$unreadable  = true;
		}
		foreach ( $found as $f ) {
			$real           = (string) realpath( $f );
			$files[ $real ] = $f;
		}
		if ( 'access' === $kind && $files ) {
			break;
		}
	}
	return array( 'files' => array_values( $files ), 'tried' => $tried, 'unreadable' => $unreadable );
}

/**
 * Call $cb for every line of a log: the last $tail bytes of a plain file, or all of a .gz file (streamed).
 *
 * @param string   $file Path.
 * @param callable $cb   function( string $line ).
 * @return array{lines:int,cut:bool} Lines read; cut = the start of the file was skipped.
 */
function aw_error_count_each_line( $file, callable $cb ) {
	$lines = 0;
	$cut   = false;
	// phpcs:disable WordPress.WP.AlternativeFunctions -- reads server log files line by line; WP_Filesystem cannot stream or seek
	if ( '.gz' === substr( $file, -3 ) ) {
		$h = function_exists( 'gzopen' ) ? gzopen( $file, 'rb' ) : false;
		if ( ! $h ) {
			return array( 'lines' => 0, 'cut' => false );
		}
		while ( ! gzeof( $h ) ) {
			$line = gzgets( $h, 65536 );
			if ( false === $line ) {
				break;
			}
			++$lines;
			$cb( $line );
		}
		gzclose( $h );
		return array( 'lines' => $lines, 'cut' => false );
	}
	$h = fopen( $file, 'rb' );
	if ( ! $h ) {
		return array( 'lines' => 0, 'cut' => false );
	}
	$tail = max( 1048576, (int) apply_filters( 'aw_error_count_tail_bytes', 32 * 1048576 ) );
	$size = (int) filesize( $file );
	if ( $size > $tail ) {
		fseek( $h, $size - $tail );
		fgets( $h, 65536 ); // drop the partial first line
		$cut = true;
	}
	while ( false !== ( $line = fgets( $h, 65536 ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- standard read loop
		++$lines;
		$cb( $line );
	}
	fclose( $h );
	// phpcs:enable WordPress.WP.AlternativeFunctions
	return array( 'lines' => $lines, 'cut' => $cut );
}

/**
 * Add one to a sample's count, keeping the number of distinct samples bounded.
 *
 * @param array  $samples Sample => count (changed in place).
 * @param string $key     Sample.
 */
function aw_error_count_tally( array &$samples, $key ) {
	if ( isset( $samples[ $key ] ) ) {
		++$samples[ $key ];
	} elseif ( count( $samples ) < AW_ERROR_COUNT_KEYS ) {
		$samples[ $key ] = 1;
	}
}

/**
 * The most frequent samples.
 *
 * @param array $samples Sample => count.
 * @return array Sample => count.
 */
function aw_error_count_top( array $samples ) {
	arsort( $samples );
	return array_slice( $samples, 0, AW_ERROR_COUNT_SAMPLES, true );
}

/**
 * Count 5xx responses since $since in the access logs.
 *
 * @param int $since Unix time.
 * @return array Result: status ok|not_found|unreadable|unrecognised, count, files (path => lines), partial, samples, tried.
 */
function aw_error_count_access( $since ) {
	$res = aw_error_count_resolve( aw_error_count_access_candidates(), 'access' );
	$out = array( 'status' => 'not_found', 'count' => 0, 'files' => array(), 'partial' => false, 'samples' => array(), 'tried' => $res['tried'] );
	if ( ! $res['files'] ) {
		$out['status'] = $res['unreadable'] ? 'unreadable' : 'not_found';
		return $out;
	}
	$count   = 0;
	$parsed  = 0;
	$samples = array();
	$re      = '~\[(\d{2}/\w{3}/\d{4}:\d{2}:\d{2}:\d{2} [+-]\d{4})\] "(?:[A-Z]+ )?([^" ]*)[^"]*" (\d{3}) ~';
	foreach ( $res['files'] as $f ) {
		if ( (int) filemtime( $f ) < $since ) {
			$out['files'][ $f ] = 0; // nothing written in the window
			continue;
		}
		$first              = null;
		$read               = aw_error_count_each_line(
			$f,
			function ( $line ) use ( $re, $since, &$count, &$parsed, &$samples, &$first ) {
				if ( null !== $first && ! preg_match( '~" 5\d\d ~', $line ) ) {
					return; // only the first line and 5xx lines need a full parse
				}
				if ( ! preg_match( $re, $line, $m ) ) {
					return;
				}
				++$parsed;
				$t = DateTime::createFromFormat( 'd/M/Y:H:i:s O', $m[1] );
				$t = $t ? $t->getTimestamp() : 0;
				if ( null === $first ) {
					$first = $t;
				}
				if ( $t < $since || '5' !== $m[3][0] ) {
					return;
				}
				++$count;
				$path = strtok( $m[2], '?' );
				aw_error_count_tally( $samples, $m[3] . ' ' . mb_substr( false === $path ? '' : $path, 0, 120 ) );
			}
		);
		$out['files'][ $f ] = $read['lines'];
		if ( $read['cut'] && null !== $first && $first >= $since ) {
			$out['partial'] = true; // the part read starts inside the window: the count is a minimum
		}
	}
	if ( array_sum( $out['files'] ) && ! $parsed ) { // lines, but none in the expected format
		$out['status'] = 'unrecognised';
		return $out;
	}
	$out['status']  = 'ok';
	$out['count']   = $count;
	$out['samples'] = aw_error_count_top( $samples );
	return $out;
}

/**
 * A fatal-error message for display: site paths made relative, single line, at most 200 characters.
 *
 * @param string $msg Message from the log.
 * @return string
 */
function aw_error_count_clean_message( $msg ) {
	$root = trailingslashit( aw_error_count_dirs()['root'] );
	$real = realpath( $root );
	$msg  = str_replace( array( $root, $real ? trailingslashit( $real ) : $root ), '', (string) $msg );
	$msg  = trim( preg_replace( '/\s+/', ' ', $msg ) );
	return mb_strlen( $msg ) > 200 ? mb_substr( $msg, 0, 199 ) . '…' : $msg;
}

/**
 * Count "PHP Fatal error" and "PHP Parse error" lines since $since in the PHP error logs.
 *
 * @param int $since Unix time.
 * @return array Same shape as aw_error_count_access().
 */
function aw_error_count_php( $since ) {
	$res = aw_error_count_resolve( aw_error_count_php_candidates(), 'php' );
	$out = array( 'status' => 'not_found', 'count' => 0, 'files' => array(), 'partial' => false, 'samples' => array(), 'tried' => $res['tried'] );
	if ( ! $res['files'] ) {
		$out['status'] = $res['unreadable'] ? 'unreadable' : 'not_found';
		return $out;
	}
	$count   = 0;
	$samples = array();
	foreach ( $res['files'] as $f ) {
		if ( (int) filemtime( $f ) < $since ) {
			$out['files'][ $f ] = 0; // nothing logged in the window
			continue;
		}
		$first              = null;
		$read               = aw_error_count_each_line(
			$f,
			function ( $line ) use ( $since, &$count, &$samples, &$first ) {
				if ( '[' !== substr( $line, 0, 1 ) || ! preg_match( '/^\[([^\]]+)\] (?:PHP (Fatal|Parse) error:)?\s*(.*)$/', $line, $m ) ) {
					return;
				}
				$t = (int) strtotime( $m[1] );
				if ( null === $first ) {
					$first = $t;
				}
				if ( '' === $m[2] || $t < $since ) {
					return;
				}
				++$count;
				aw_error_count_tally( $samples, aw_error_count_clean_message( ( 'Parse' === $m[2] ? 'Parse error: ' : '' ) . $m[3] ) );
			}
		);
		$out['files'][ $f ] = $read['lines'];
		if ( $read['cut'] && null !== $first && $first >= $since ) {
			$out['partial'] = true;
		}
	}
	$out['status']  = 'ok';
	$out['count']   = $count;
	$out['samples'] = aw_error_count_top( $samples );
	return $out;
}

/**
 * Count both logs for the last 24 hours.
 *
 * @return array{at:int,since:int,access:array,php:array}
 */
function aw_error_count_collect() {
	$now   = time();
	$since = $now - DAY_IN_SECONDS;
	return array(
		'at'     => $now,
		'since'  => $since,
		'access' => aw_error_count_access( $since ),
		'php'    => aw_error_count_php( $since ),
	);
}

/**
 * "3", "at least 3", or "Not found" / "Not readable" / "Unrecognised format".
 *
 * @param array $r One log's result.
 * @return string
 */
function aw_error_count_label( array $r ) {
	switch ( $r['status'] ?? '' ) {
		case 'ok':
			return ( empty( $r['partial'] ) ? '' : 'at least ' ) . (int) $r['count'];
		case 'unreadable':
			return 'Not readable';
		case 'unrecognised':
			return 'Unrecognised format';
		default:
			return 'Not found';
	}
}

/**
 * Errors found (both logs that could be read).
 *
 * @param array $run Result of aw_error_count_collect().
 * @return int
 */
function aw_error_count_total( array $run ) {
	$n = 0;
	foreach ( array( 'access', 'php' ) as $k ) {
		if ( 'ok' === ( $run[ $k ]['status'] ?? '' ) ) {
			$n += (int) $run[ $k ]['count'];
		}
	}
	return $n;
}

/** Daily event: count, store, and email the admin address when anything was found. */
function aw_error_count_daily() {
	$run = aw_error_count_collect();
	foreach ( array( 'access', 'php' ) as $k ) {
		unset( $run[ $k ]['tried'] ); // only the CLI shows them
	}
	update_option( 'aw_error_count', $run, false );
	$total = aw_error_count_total( $run );
	if ( ! $total || get_transient( 'aw_error_count_mailed' ) ) {
		return; // nothing found, or this day's message is already sent
	}
	$lines = array(
		sprintf( 'Server errors on %s in the 24 hours to %s:', wp_parse_url( home_url(), PHP_URL_HOST ), wp_date( 'j M Y, g:i a', $run['at'] ) ),
		'',
		'- 5xx responses: ' . aw_error_count_label( $run['access'] ),
		'- PHP fatal errors: ' . aw_error_count_label( $run['php'] ),
	);
	if ( $run['access']['samples'] ) {
		$lines[] = '';
		$lines[] = 'Most frequent 5xx responses:';
		foreach ( $run['access']['samples'] as $s => $n ) {
			$lines[] = sprintf( '- %s (%d)', $s, $n );
		}
	}
	if ( $run['php']['samples'] ) {
		$lines[] = '';
		$lines[] = 'Most frequent PHP fatal errors:';
		foreach ( $run['php']['samples'] as $s => $n ) {
			$lines[] = sprintf( '- %s (%d)', $s, $n );
		}
	}
	$lines[] = '';
	$lines[] = 'Dashboard: ' . admin_url();
	/* translators: %d: number of errors. */
	$subject = sprintf( _n( 'Animal Writes: %d server error in the last 24 hours', 'Animal Writes: %d server errors in the last 24 hours', $total, 'animal-writes-core' ), $total );
	if ( wp_mail( get_option( 'admin_email' ), $subject, implode( "\n", $lines ) . "\n" ) ) {
		set_transient( 'aw_error_count_mailed', 1, 20 * HOUR_IN_SECONDS );
	}
}
add_action( 'aw_error_count_daily', 'aw_error_count_daily' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_error_count_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aw_error_count_daily' );
		}
	}
);

// A table under the "Scheduled tasks" dashboard box (cron-health.php).
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_action(
			'aw_cron_health_after',
			function () {
				$run = (array) get_option( 'aw_error_count', array() );
				if ( empty( $run['at'] ) ) {
					echo '<p>Server errors: not counted yet (counted once a day).</p>';
					return;
				}
				echo '<p><strong>Server errors, last 24 hours</strong> (counted ' . esc_html( wp_date( 'j M Y, g:i a', (int) $run['at'] ) ) . ')</p>';
				$rows = array();
				foreach ( array( 'access' => '5xx responses', 'php' => 'PHP fatal errors' ) as $k => $name ) {
					$r     = (array) ( $run[ $k ] ?? array() );
					$label = aw_error_count_label( $r );
					$ok    = 'ok' === ( $r['status'] ?? '' );
					$files = array_map( 'wp_basename', array_keys( (array) ( $r['files'] ?? array() ) ) );
					$top   = array();
					foreach ( (array) ( $r['samples'] ?? array() ) as $s => $n ) {
						$top[] = esc_html( $s ) . ' (' . (int) $n . ')';
					}
					$rows[] = array(
						'check' => esc_html( $name ),
						'count' => $ok && ! $r['count'] ? esc_html( $label ) : '<strong>' . esc_html( $label ) . '</strong>',
						'from'  => $files ? esc_html( implode( ', ', $files ) ) : '—',
						'top'   => $top ? implode( '<br>', $top ) : '—',
					);
				}
				aw_core_simple_table( 'error-count', array( 'check' => 'Check', 'count' => 'Count', 'from' => 'Read from', 'top' => 'Most frequent' ), $rows );
			}
		);
	}
);

/* ---------- WP-CLI: wp aw error-count [--paths] ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'aw error-count',
		/**
		 * Count 5xx responses and PHP fatal errors in the last 24 hours and show the files read. Saves and emails nothing.
		 *
		 * [--paths]
		 * : Also list every path tried and what was found there.
		 */
		function ( $args, $assoc ) {
			$run = aw_error_count_collect();
			foreach ( array( 'access' => '5xx responses', 'php' => 'PHP fatal errors' ) as $k => $name ) {
				$r = $run[ $k ];
				WP_CLI::log( $name . ': ' . aw_error_count_label( $r ) );
				foreach ( $r['files'] as $f => $n ) {
					WP_CLI::log( '  read ' . $f . ' (' . (int) $n . ' lines)' );
				}
				foreach ( $r['samples'] as $s => $n ) {
					WP_CLI::log( '  ' . $s . ' (' . (int) $n . ')' );
				}
				if ( ! empty( $assoc['paths'] ) ) {
					foreach ( $r['tried'] as $p => $st ) {
						WP_CLI::log( '  tried ' . $p . ': ' . $st );
					}
				}
			}
		}
	);
}
